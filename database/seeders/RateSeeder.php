<?php

namespace Database\Seeders;

use App\Models\AustraliaPostcode;
use App\Models\RatePricing;
use App\Models\RateZone;
use Illuminate\Database\Seeder;

class RateSeeder extends Seeder
{
    public function run(): void
    {
        // ── Clear existing data ───────────────────────────────────────
        RateZone::truncate();
        RatePricing::truncate();
        AustraliaPostcode::truncate();

        // ── Seed Zone Maps ────────────────────────────────────────────
        $this->command->info('Seeding rate zones...');

        $zonesJson = database_path('data/zone_maps_data.json');

        if (! file_exists($zonesJson)) {
            $this->command->error('zone_maps_data.json not found in database/data/');
            return;
        }

        $zones = json_decode(file_get_contents($zonesJson), true);

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

        $rates  = json_decode(file_get_contents($ratesJson), true);
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