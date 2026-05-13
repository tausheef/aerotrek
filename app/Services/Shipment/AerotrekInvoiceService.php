<?php

namespace App\Services\Shipment;

use App\Models\Kyc;
use App\Models\Shipment;
use App\Services\Storage\StorageService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AerotrekInvoiceService
{
    public function __construct(private StorageService $storage) {}

    /**
     * Generate AeroTrek invoice PDF for a booked shipment,
     * upload to storage, and return the public URL.
     * Returns null if generation fails (non-fatal).
     */
    public function generate(Shipment $shipment): ?string
    {
        try {
            $kyc            = $this->getKyc($shipment->user_id);
            $signatureBase64 = $this->getSignatureBase64($kyc);
            [$gstin, $iecCode] = $this->extractKycMeta($kyc);

            $pdf = Pdf::loadView('pdf.aerotrek_invoice', [
                'shipment'        => $shipment,
                'sender'          => $shipment->sender   ?? [],
                'receiver'        => $shipment->receiver ?? [],
                'gstin'           => $gstin,
                'iecCode'         => $iecCode,
                'signatureBase64' => $signatureBase64,
            ]);

            $pdf->setPaper('A4', 'portrait');

            $path = "invoices/{$shipment->aerotrek_id}/invoice.pdf";
            $url  = $this->storage->uploadRawPdf($path, $pdf->output());

            return $url;
        } catch (\Exception $e) {
            Log::error('AeroTrek invoice generation failed', [
                'aerotrek_id' => $shipment->aerotrek_id,
                'error'       => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getKyc(string $userId): ?Kyc
    {
        return Kyc::where('user_id', $userId)
            ->where('status', 'verified')
            ->latest()
            ->first();
    }

    private function getSignatureBase64(?Kyc $kyc): ?string
    {
        if (! $kyc || ! $kyc->signature_image) {
            return null;
        }

        try {
            $driver  = config('filesystems.storage_driver', 'local');
            $disk    = $driver === 'r2' ? 'r2' : 'public';
            $content = Storage::disk($disk)->get($kyc->signature_image);

            return $content ? base64_encode($content) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function extractKycMeta(?Kyc $kyc): array
    {
        if (! $kyc) {
            return [null, null];
        }

        $gstin   = null;
        $iecCode = null;

        if (in_array($kyc->document_type, ['gst', 'company_pan'])) {
            $gstin = $kyc->document_number;
        }

        // IEC is typically embedded as first 10 chars of GSTIN
        if ($gstin && strlen($gstin) >= 10) {
            $iecCode = substr($gstin, 2, 10);
        }

        return [$gstin, $iecCode];
    }
}
