<?php

namespace App\Console\Commands;

use App\Services\External\ShiprocketApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillShiprocketCourierIds extends Command
{
    protected $signature   = 'shiprocket:backfill-courier-ids {--dry-run : Show what would be updated without writing}';
    protected $description = 'Fetch courier list from Shiprocket and populate courier_company_id in shiprocket_rates';

    public function handle(ShiprocketApiService $shiprocket): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Fetching courier list from Shiprocket...');

        try {
            $couriers = $shiprocket->getAllCouriers();
        } catch (\Exception $e) {
            $this->error('Failed to fetch couriers: ' . $e->getMessage());
            return 1;
        }

        if (empty($couriers)) {
            $this->error('No couriers returned from Shiprocket API.');
            return 1;
        }

        // API returns: id, name (not courier_company_id / courier_name)
        $this->info('Found ' . count($couriers) . ' couriers on your Shiprocket account:');
        $this->table(['ID', 'Name'], array_map(
            fn($c) => [$c['id'], $c['name']],
            $couriers
        ));

        // Build lowercase name → id map
        $nameToId = [];
        foreach ($couriers as $c) {
            $nameToId[strtolower(trim($c['name']))] = (int) $c['id'];
        }

        // Get all distinct services that still have no courier_company_id
        $services = DB::table('shiprocket_rates')
            ->whereNull('courier_company_id')
            ->distinct()
            ->pluck('service');

        if ($services->isEmpty()) {
            $this->info('All rows already have courier_company_id set. Nothing to do.');
            return 0;
        }

        $this->newLine();
        $this->info('Matching ' . $services->count() . ' distinct service name(s)...');

        $matched   = [];
        $unmatched = [];

        foreach ($services as $service) {
            $lower = strtolower(trim($service));
            $id    = null;

            // 1. Exact match
            if (isset($nameToId[$lower])) {
                $id = $nameToId[$lower];
            }

            // 2. Substring match
            if ($id === null) {
                foreach ($nameToId as $courierName => $courierId) {
                    if (str_contains($courierName, $lower) || str_contains($lower, $courierName)) {
                        $id = $courierId;
                        break;
                    }
                }
            }

            // 3. Word-intersection match — all words of the shorter name found in the longer
            if ($id === null) {
                $serviceWords = preg_split('/\s+/', $lower);
                $bestScore    = 0;
                $bestId       = null;
                foreach ($nameToId as $courierName => $courierId) {
                    $courierWords  = preg_split('/\s+/', $courierName);
                    $commonWords   = array_intersect($serviceWords, $courierWords);
                    $shortestCount = min(count($serviceWords), count($courierWords));
                    if ($shortestCount > 0 && count($commonWords) === $shortestCount) {
                        // All words of the shorter name appear in the longer name
                        if (count($commonWords) > $bestScore) {
                            $bestScore = count($commonWords);
                            $bestId    = $courierId;
                        }
                    }
                }
                $id = $bestId;
            }

            if ($id !== null) {
                $matched[$service] = $id;
            } else {
                $unmatched[] = $service;
            }
        }

        if (!empty($matched)) {
            $this->newLine();
            $this->info('Matched services:');
            foreach ($matched as $service => $id) {
                $this->line("  [{$id}] {$service}");
                if (! $dryRun) {
                    DB::table('shiprocket_rates')
                        ->where('service', $service)
                        ->whereNull('courier_company_id')
                        ->update(['courier_company_id' => $id]);
                }
            }
        }

        if (!empty($unmatched)) {
            $this->newLine();
            $this->warn('Could NOT match these services (check names against the courier list above):');
            foreach ($unmatched as $service) {
                $this->line("  - {$service}");
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->warn('[DRY RUN] No changes written. Re-run without --dry-run to apply.');
        } else {
            $this->info('Done. Updated ' . count($matched) . ' service(s) across all rate rows.');
        }

        return 0;
    }
}
