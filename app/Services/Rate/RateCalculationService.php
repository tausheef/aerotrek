<?php

namespace App\Services\Rate;

use App\Models\AustraliaPostcode;
use App\Models\RatePricing;
use App\Models\RateZone;

class RateCalculationService
{
    /**
     * Main entry point — returns all available carrier rates for a shipment.
     *
     * @param string      $country         Destination country name
     * @param float       $actualWeight    Actual weight in kg
     * @param float|null  $length          cm
     * @param float|null  $breadth         cm
     * @param float|null  $height          cm
     * @param string      $shipmentType    Document | Non-Document
     * @param string|null $postcode        Required for Australia / NZ / Canada
     * @param int         $packageCount    Number of packages (affects SELF tier)
     */
    public function calculate(
        string  $country,
        float   $actualWeight,
        ?float  $length      = null,
        ?float  $breadth     = null,
        ?float  $height      = null,
        string  $shipmentType = 'Non-Document',
        ?string $postcode    = null,
        int     $packageCount = 1
    ): array {
        // Step 1 — determine chargeable weight
        $chargeableWeight = $this->getChargeableWeight($actualWeight, $length, $breadth, $height);

        // Step 2 — round to nearest slab (0.5 increments)
        $slabWeight = $this->roundToSlab($chargeableWeight);

        // Step 3 — get rates from all carriers
        $rates = [];

        $carriers = [
            'DHL', 'FedEx', 'Aramex', 'UPS',
            'SELF-UK', 'SELF-EUROPE', 'SELF-DUBAI',
            'SELF-AUSTRALIA', 'SELF-NZ', 'SELF-CANADA',
        ];

        foreach ($carriers as $carrier) {
            $rate = $this->getCarrierRate(
                carrier:       $carrier,
                country:       $country,
                weight:        $slabWeight,
                shipmentType:  $shipmentType,
                postcode:      $postcode,
                packageCount:  $packageCount,
                actualWeight:  $actualWeight,
                chargeableWeight: $chargeableWeight
            );

            if ($rate) {
                $rates[] = $rate;
            }
        }

        // Step 4 — sort by price ascending
        usort($rates, fn($a, $b) => $a['price'] <=> $b['price']);

        return [
            'destination'         => $country,
            'actual_weight'       => $actualWeight,
            'volumetric_weight'   => $this->getVolumetricWeight($length, $breadth, $height),
            'chargeable_weight'   => $chargeableWeight,
            'shipment_type'       => $shipmentType,
            'rates'               => $rates,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Weight calculation
    // ──────────────────────────────────────────────────────────────────

    private function getVolumetricWeight(?float $l, ?float $b, ?float $h): ?float
    {
        if ($l && $b && $h) {
            return round(($l * $b * $h) / 5000, 2);
        }
        return null;
    }

    private function getChargeableWeight(float $actual, ?float $l, ?float $b, ?float $h): float
    {
        $volumetric = $this->getVolumetricWeight($l, $b, $h);
        if ($volumetric) {
            return max($actual, $volumetric);
        }
        return $actual;
    }

    private function roundToSlab(float $weight): float
    {
        // Rates are in 0.5kg slabs — round up to nearest 0.5
        return ceil($weight * 2) / 2;
    }

    // ──────────────────────────────────────────────────────────────────
    // Per-carrier rate lookup
    // ──────────────────────────────────────────────────────────────────

    private function getCarrierRate(
        string  $carrier,
        string  $country,
        float   $weight,
        string  $shipmentType,
        ?string $postcode,
        int     $packageCount,
        float   $actualWeight,
        float   $chargeableWeight
    ): ?array {
        return match ($carrier) {
            'DHL'            => $this->getDHLRate($country, $weight, $shipmentType),
            'FedEx'          => $this->getFedExRate($country, $weight, $shipmentType),
            'Aramex'         => $this->getAramexRate($country, $weight, $shipmentType),
            'UPS'            => $this->getUPSRate($country, $weight, $shipmentType),
            'SELF-UK'        => $this->getSelfUKRate($country, $weight),
            'SELF-EUROPE'    => $this->getSelfEuropeRate($country, $weight),
            'SELF-DUBAI'     => $this->getSelfDubaiRate($country, $weight, $packageCount),
            'SELF-AUSTRALIA' => $this->getSelfAustraliaRate($country, $weight, $postcode, $packageCount),
            'SELF-NZ'        => $this->getSelfNZRate($country, $weight, $postcode, $packageCount),
            'SELF-CANADA'    => $this->getSelfCanadaRate($country, $weight, $postcode),
            default          => null,
        };
    }

    // ── DHL ───────────────────────────────────────────────────────────
    private function getDHLRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zone = RateZone::where('carrier', 'DHL')
            ->where('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        // Map shipment type
        $type = $shipmentType === 'Document' ? 'Document' : 'Non-Document';

        $price = $this->lookupPrice('DHL', $zone, $type, $weight);

        if (! $price) return null;

        return [
            'carrier'      => 'DHL',
            'zone'         => $zone,
            'shipment_type'=> $type,
            'weight'       => $weight,
            'price'        => $price,
            'currency'     => 'INR',
            'service'      => 'DHL Express',
            'forwarder_code' => 'DHL',
            'estimated_days' => $this->getDHLEstimatedDays($zone),
        ];
    }

    // ── FedEx ─────────────────────────────────────────────────────────
    private function getFedExRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zone = RateZone::where('carrier', 'FedEx')
            ->where('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        // Map shipment type to FedEx types
        $type = match ($shipmentType) {
            'Document' => 'Envelope',
            default    => 'Pak',
        };

        $price = $this->lookupPrice('FedEx', $zone, $type, $weight);

        // fallback to Box if Pak not found
        if (! $price && $type === 'Pak') {
            $price = $this->lookupPrice('FedEx', $zone, 'Box', $weight);
            $type  = 'Box';
        }

        if (! $price) return null;

        return [
            'carrier'        => 'FedEx',
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'FedEx International Priority',
            'forwarder_code' => 'FED',
            'estimated_days' => $this->getFedExEstimatedDays($zone),
        ];
    }

    // ── Aramex ────────────────────────────────────────────────────────
    private function getAramexRate(string $country, float $weight, string $shipmentType): ?array
    {
        // Get all Aramex zones for this country
        $zones = RateZone::where('carrier', 'Aramex')
            ->where('countries', $country)
            ->get();

        if ($zones->isEmpty()) return null;

        // Prefer PPX zone
        $zone = $zones->first(fn($z) => str_contains($z->zone, 'PPX'))?->zone
            ?? $zones->first()?->zone;

        $type = $shipmentType === 'Document' ? 'Document' : 'Non-Document';

        $price = $this->lookupPrice('Aramex', $zone, $type, $weight);

        if (! $price) return null;

        return [
            'carrier'        => 'Aramex',
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'Aramex Priority Express',
            'forwarder_code' => 'ARA',
            'estimated_days' => '3-5 business days',
        ];
    }

    // ── UPS ───────────────────────────────────────────────────────────
    private function getUPSRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zone = RateZone::where('carrier', 'UPS')
            ->where('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $type = $shipmentType === 'Document' ? 'Document' : 'Non-Document';

        $price = $this->lookupPrice('UPS', $zone, $type, $weight);

        if (! $price) return null;

        return [
            'carrier'        => 'UPS',
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'UPS Worldwide Saver',
            'forwarder_code' => 'UPS',
            'estimated_days' => '3-5 business days',
        ];
    }

    // ── SELF UK ───────────────────────────────────────────────────────
    private function getSelfUKRate(string $country, float $weight): ?array
    {
        if (! in_array($country, ['UK', 'United Kingdom'])) return null;

        $price = $this->lookupPrice('SELF-UK', 'UK', 'Non-Document', $weight);

        // For weight > 6kg use per-kg rate
        if (! $price && $weight > 6) {
            $perKgRate = RatePricing::where('carrier', 'SELF-UK')
                ->where('is_per_kg', true)
                ->first()?->price;
            $price = $perKgRate ? round($perKgRate * $weight, 2) : null;
        }

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-UK',
            'zone'           => 'UK',
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF UK (DPD)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '4-6 business days',
        ];
    }

    // ── SELF Europe ───────────────────────────────────────────────────
    private function getSelfEuropeRate(string $country, float $weight): ?array
    {
        $zone = RateZone::where('carrier', 'SELF-EUROPE')
            ->where('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        // Europe rates are per-kg — find nearest slab and multiply
        $perKgRate = RatePricing::where('carrier', 'SELF-EUROPE')
            ->where('zone', $zone)
            ->where('weight', '<=', $weight)
            ->orderBy('weight', 'desc')
            ->first()?->price;

        if (! $perKgRate) return null;

        $price = round($perKgRate * $weight, 2);

        return [
            'carrier'        => 'SELF-EUROPE',
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF Europe (DPD via Germany)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '5-7 business days',
        ];
    }

    // ── SELF Dubai ────────────────────────────────────────────────────
    private function getSelfDubaiRate(string $country, float $weight, int $packageCount): ?array
    {
        $zone = RateZone::where('carrier', 'SELF-DUBAI')
            ->where('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $tier  = $packageCount >= 10 ? 'above_10' : 'below_10';
        $price = RatePricing::where('carrier', 'SELF-DUBAI')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->where('tier', $tier)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-DUBAI',
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'tier'           => $tier,
            'currency'       => 'INR',
            'service'        => 'SELF Dubai (Direct)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '2-4 business days',
        ];
    }

    // ── SELF Australia ────────────────────────────────────────────────
    private function getSelfAustraliaRate(string $country, float $weight, ?string $postcode, int $packageCount): ?array
    {
        if ($country !== 'Australia') return null;

        // Determine zone from postcode
        $zone = $postcode
            ? AustraliaPostcode::findZone($postcode)
            : 'Metro'; // default

        $tier  = $packageCount >= 10 ? 'above_10' : 'below_10';
        $price = RatePricing::where('carrier', 'SELF-AUSTRALIA')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->where('tier', $tier)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-AUSTRALIA',
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'tier'           => $tier,
            'currency'       => 'INR',
            'service'        => 'SELF Australia (Toll Express)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '5-8 business days',
        ];
    }

    // ── SELF NZ ───────────────────────────────────────────────────────
    private function getSelfNZRate(string $country, float $weight, ?string $postcode, int $packageCount): ?array
    {
        if ($country !== 'New Zealand') return null;

        $zone  = 'Zone 1'; // default — postcode-based NZ zones can be added later
        $tier  = $packageCount >= 10 ? 'above_10' : 'below_10';
        $price = RatePricing::where('carrier', 'SELF-NZ')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->where('tier', $tier)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-NZ',
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF New Zealand (NZ Post)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '6-10 business days',
        ];
    }

    // ── SELF Canada ───────────────────────────────────────────────────
    private function getSelfCanadaRate(string $country, float $weight, ?string $postcode): ?array
    {
        if ($country !== 'Canada') return null;

        $zone  = 'YVR-Zone-1'; // default
        $price = RatePricing::where('carrier', 'SELF-CANADA')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-CANADA',
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF Canada (UPS Last Mile)',
            'forwarder_code' => 'SELF',
            'estimated_days' => '7-10 business days',
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function lookupPrice(string $carrier, string $zone, string $type, float $weight): ?float
    {
        // Exact weight match first
        $price = RatePricing::where('carrier', $carrier)
            ->where('zone', $zone)
            ->where('shipment_type', $type)
            ->where('weight', $weight)
            ->first()?->price;

        if ($price) return $price;

        // If weight > 30, use per-kg rate
        if ($weight > 30) {
            $perKg = RatePricing::where('carrier', $carrier)
                ->where('zone', $zone)
                ->where('shipment_type', 'Per-KG-30Plus')
                ->first()?->price;

            if ($perKg) {
                return round($perKg * $weight, 2);
            }
        }

        return null;
    }

    private function getDHLEstimatedDays(string $zone): string
    {
        return match ($zone) {
            'Zone 1', 'Zone 2' => '2-3 business days',
            'Zone 3', 'Zone 4' => '3-4 business days',
            'Zone 7', 'Zone 8' => '3-5 business days',
            'Zone 12'          => '4-6 business days',
            default            => '3-7 business days',
        };
    }

    private function getFedExEstimatedDays(string $zone): string
    {
        return match ($zone) {
            'Zone A', 'Zone B' => '2-3 business days',
            'Zone D'           => '3-4 business days',
            'Zone F', 'Zone G' => '3-5 business days',
            default            => '3-7 business days',
        };
    }
}