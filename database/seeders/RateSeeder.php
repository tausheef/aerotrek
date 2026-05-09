<?php

namespace Database\Seeders;

use App\Models\ShiprocketRate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RateSeeder extends Seeder
{
    public function run(): void
    {
        // ── Clear existing data ───────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        ShiprocketRate::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ── Seed Shiprocket rates ─────────────────────────────────────
        $this->command->info('Seeding Shiprocket rates (~140k records)...');

        $jsonPath = storage_path('app/rates/shiprocket_rates.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('shiprocket_rates.json not found in storage/app/rates/');
            return;
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        $total = count($rows);
        $inserted = 0;

        foreach (array_chunk($rows, 1000) as $chunk) {
            $records = array_map(fn($r) => [
                'country_code' => $r['c'],
                'weight'       => $r['w'],
                'service'      => $r['s'],
                'rate'         => $r['r'],
            ], $chunk);

            ShiprocketRate::insert($records);
            $inserted += count($chunk);

            if ($inserted % 10000 === 0) {
                $this->command->info("  -> Inserted {$inserted} / {$total}...");
            }
        }

        $this->command->info("  -> Shiprocket rates seeded: {$total}");
        $this->command->info('Done! Rate seeding complete.');
    }
}
