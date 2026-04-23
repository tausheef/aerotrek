<?php

namespace App\Services\Shipment;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Generates Aerotrek-owned shipment IDs: ATK-YYYYMMDD-XXXXXX
 * Uses MongoDB atomic counter so IDs are sequential per day and never duplicate.
 */
class AerotrekIdGenerator
{
    /**
     * Generate next ATK ID for today.
     * Example: ATK-20260423-000047
     */
    public function generate(): string
    {
        $date    = now()->format('Ymd');
        $counter = $this->nextCounter($date);

        return sprintf('ATK-%s-%06d', $date, $counter);
    }

    /**
     * Atomically increment today's counter and return new value.
     */
    private function nextCounter(string $date): int
    {
        $collection = $this->getCollection();

        $result = $collection->findOneAndUpdate(
            ['_id' => $date],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        return (int) $result['seq'];
    }

    private function getCollection(): \MongoDB\Collection
    {
        $connection = app('db')->connection('mongodb');
        $db         = $connection->getMongoDB();

        return $db->selectCollection('atk_counters');
    }
}
