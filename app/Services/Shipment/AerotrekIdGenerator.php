<?php

namespace App\Services\Shipment;

use Illuminate\Support\Facades\DB;

class AerotrekIdGenerator
{
    public function generate(): string
    {
        $date    = now()->format('Ymd');
        $counter = $this->nextCounter($date);

        return sprintf('ATK-%s-%06d', $date, $counter);
    }

    private function nextCounter(string $date): int
    {
        // Atomic upsert: insert with seq=1 or increment existing row
        DB::statement(
            'INSERT INTO atk_counters (`date`, seq) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)',
            [$date]
        );

        return (int) DB::select('SELECT LAST_INSERT_ID() AS id')[0]->id;
    }
}
