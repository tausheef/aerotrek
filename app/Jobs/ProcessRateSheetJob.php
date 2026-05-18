<?php

namespace App\Jobs;

use App\Models\CarrierRate;
use App\Models\PostcodeZone;
use App\Models\RateUpload;
use App\Models\ShiprocketRate;
use App\Services\Rate\RateSheetParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessRateSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;  // 10 minutes — Shiprocket has 43k rows
    public int $tries   = 1;    // no retries; parse errors are deterministic

    public function __construct(private int $uploadId) {}

    public function handle(): void
    {
        $upload = RateUpload::findOrFail($this->uploadId);

        try {
            $upload->update(['status' => 'processing']);

            $filePath = Storage::path($upload->filename);
            $parser   = new RateSheetParser($filePath, $this->uploadId);

            $upload->update(['total_rows' => $parser->getTotalRows()]);

            $processed = 0;

            // ── 1. Carrier rates (UPS + FedEx + DHL) ─────────────────────────
            $carrierRates = $parser->parseCarrierRates();

            foreach (array_chunk($carrierRates, 500) as $chunk) {
                CarrierRate::insert($chunk);
                $processed += count($chunk);
                $upload->update(['processed_rows' => $processed]);
            }

            // ── 2. Postcode → zone lookups (AU_SELF, AU_DPEX_SELF, NZ_SELF) ───
            $postcodeBatch = $parser->parsePostcodeZones();
            foreach (array_chunk($postcodeBatch, 500) as $chunk) {
                PostcodeZone::insert($chunk);
                $processed += count($chunk);
                $upload->update(['processed_rows' => $processed]);
            }

            // ── 3. Shiprocket rates (43k rows — streamed in 500-row chunks) ──
            foreach ($parser->parseShiprocketRates() as $chunk) {
                ShiprocketRate::insert($chunk);
                $processed += count($chunk);
                $upload->update(['processed_rows' => $processed]);
            }

            // ── 3. Atomic swap: activate new, supersede old ──────────────────
            DB::transaction(function () use ($upload) {
                // Scope supersede + cleanup to same user_id (null = global)
                $scope = fn($q) => $upload->user_id
                    ? $q->where('user_id', $upload->user_id)
                    : $q->whereNull('user_id');

                $scope(RateUpload::where('status', 'active'))->update(['status' => 'superseded']);

                $upload->update([
                    'status'         => 'active',
                    'activated_at'   => now(),
                    'processed_rows' => $upload->total_rows,
                ]);

                // Keep only the 3 most-recent uploads for this scope
                $keepIds = $scope(RateUpload::whereIn('status', ['active', 'superseded']))
                    ->latest('activated_at')
                    ->take(3)
                    ->pluck('id');

                $scope(RateUpload::whereNotIn('id', $keepIds)->where('status', 'superseded'))->delete();
            });

            // ── 4. Bust rate cache so the service picks up new data ───────────
            $upload->user_id
                ? Cache::forget("active_rate_upload_id_user_{$upload->user_id}")
                : Cache::forget('active_rate_upload_id');

            // ── 5. Delete raw file — data is now in DB, file no longer needed ─
            if ($upload->filename && Storage::disk('local')->exists($upload->filename)) {
                Storage::disk('local')->delete($upload->filename);
                $upload->update(['filename' => null]);
            }

        } catch (\Throwable $e) {
            // Insertion may be partial — delete orphaned rows for this upload
            CarrierRate::where('upload_id', $this->uploadId)->delete();
            PostcodeZone::where('upload_id', $this->uploadId)->delete();
            ShiprocketRate::where('upload_id', $this->uploadId)->delete();

            if ($upload->filename && Storage::disk('local')->exists($upload->filename)) {
                Storage::disk('local')->delete($upload->filename);
                $upload->update(['filename' => null]);
            }

            $upload->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
