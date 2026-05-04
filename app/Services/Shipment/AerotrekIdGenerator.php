<?php

namespace App\Services\Shipment;

use App\Exceptions\ShipmentLimitReachedException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AerotrekIdGenerator
{
    public function generate(User $user): string
    {
        $this->enforceLimit($user);

        $firstName = explode(' ', trim($user->name))[0];
        $initials  = strtoupper(substr($firstName, 0, 1) . substr($firstName, -1));
        $mmyy      = now()->format('my'); // e.g. "0626" for June 2026
        $counter   = $this->nextCounter($user->id);

        return sprintf('%s%s%03d', $initials, $mmyy, $counter);
    }

    private function enforceLimit(User $user): void
    {
        $seq = DB::table('atk_counters')
            ->where('user_id', $user->id)
            ->value('seq') ?? 0;

        if ($seq >= $user->shipment_limit) {
            throw new ShipmentLimitReachedException($user->shipment_limit);
        }
    }

    private function nextCounter(string $userId): int
    {
        DB::statement(
            'INSERT INTO atk_counters (user_id, seq) VALUES (?, 1)
             ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)',
            [$userId]
        );

        return (int) DB::select('SELECT LAST_INSERT_ID() AS id')[0]->id;
    }
}
