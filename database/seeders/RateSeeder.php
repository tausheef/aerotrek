<?php

namespace Database\Seeders;

use App\Models\AustraliaPostcode;
use App\Models\RatePricing;
use App\Models\RateZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── Clear existing data ───────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        RateZone::truncate();
        RatePricing::truncate();
        AustraliaPostcode::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Seed Zone Maps ────────────────────────────────────────────
        $this->command->info('Seeding rate zones...');

        $zonesJson = database_path('data/zone_maps_data.json');

        if (! file_exists($zonesJson)) {
            $this->command->error('zone_maps_data.json not found in database/data/');
            return;
        }

        $zones = json_decode(file_get_contents($zonesJson), true);

        // Encode countries array to JSON string — insert() bypasses Eloquent casts
        $zones = array_map(function ($zone) use ($now) {
            if (is_array($zone['countries'] ?? null)) {
                $zone['countries'] = json_encode($zone['countries']);
            }
            $zone['created_at'] = $now;
            $zone['updated_at'] = $now;
            return $zone;
        }, $zones);

        foreach (array_chunk($zones, 50) as $chunk) {
            RateZone::insert($chunk);
        }

        $this->command->info("  -> Zone maps seeded: " . count($zones));

        // ── Seed Rate Pricing ─────────────────────────────────────────
        $this->command->info('Seeding rate pricing (7540 records)...');

        $ratesJson = database_path('data/all_rates.json');

        if (! file_exists($ratesJson)) {
            $this->command->error('all_rates.json not found in database/data/');
            return;
        }

        $rates = json_decode(file_get_contents($ratesJson), true);

        // Normalize all records to same columns — insert() uses first record's keys for all rows
        $rates = array_map(fn($r) => [
            'carrier'       => $r['carrier'],
            'zone'          => $r['zone'],
            'shipment_type' => $r['shipment_type'],
            'weight'        => $r['weight'],
            'price'         => $r['price'],
            'is_per_kg'     => $r['is_per_kg'] ?? false,
            'tier'          => $r['tier'] ?? null,
            'route'         => $r['route'] ?? null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $rates);
        $chunks = array_chunk($rates, 200);
        $total  = 0;

        foreach ($chunks as $chunk) {
            RatePricing::insert($chunk);
            $total += count($chunk);
            $this->command->info("  -> Inserted {$total} / " . count($rates) . " rates...");
        }

        $this->command->info("  -> Rate pricing seeded: " . count($rates));
        $this->command->info('Done! Rate seeding complete.');
    }
}