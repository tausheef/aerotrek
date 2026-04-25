<?php

namespace App\Services\Rate;

use App\Models\AustraliaPostcode;
use App\Models\RatePricing;
use App\Models\RateZone;

class RateCalculationService
{
    /**
     * Main entry point — returns all available carrier rates for a shipment.
     */
    public function calculate(
        string  $country,
        float   $actualWeight,
        ?float  $length       = null,
        ?float  $breadth      = null,
        ?float  $height       = null,
        string  $shipmentType = 'Non-Document',
        ?string $postcode     = null,
        int     $packageCount = 1
    ): array {
        $chargeableWeight = $this->getChargeableWeight($actualWeight, $length, $breadth, $height);
        $slabWeight       = $this->roundToSlab($chargeableWeight);

        $rates = [];

        $carriers = [
            'DHL', 'FedEx', 'Aramex', 'UPS',
            'SELF-UK', 'SELF-EUROPE', 'SELF-DUBAI',
            'SELF-AUSTRALIA', 'SELF-NZ', 'SELF-CANADA',
        ];

        foreach ($carriers as $carrier) {
            $rate = $this->getCarrierRate(
                carrier:          $carrier,
                country:          $country,
                weight:           $slabWeight,
                shipmentType:     $shipmentType,
                postcode:         $postcode,
                packageCount:     $packageCount,
                actualWeight:     $actualWeight,
                chargeableWeight: $chargeableWeight
            );

            if ($rate) {
                $rates[] = $rate;
            }
        }

        usort($rates, fn($a, $b) => $a['price'] <=> $b['price']);

        return [
            'destination'       => $country,
            'actual_weight'     => $actualWeight,
            'volumetric_weight' => $this->getVolumetricWeight($length, $breadth, $height),
            'chargeable_weight' => $chargeableWeight,
            'shipment_type'     => $shipmentType,
            'rates'             => $rates,
        ];
    }

    // ── Weight calculation ─────────────────────────────────────────────

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
        return ceil($weight * 2) / 2;
    }

    // ── Per-carrier rate lookup ────────────────────────────────────────

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
            ->whereJsonContains('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $type  = $shipmentType === 'Document' ? 'Document' : 'Non-Document';
        $price = $this->lookupPrice('DHL', $zone, $type, $weight);

        if (! $price) return null;

        return [
            'carrier'        => 'DHL',
            'network'        => 'DHL',
            'service_code'   => 'DHL_EXPRESS',   // Overseas API service code
            'requires_otp'   => true,             // DHL requires OTP verification
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'DHL Express',
            'estimated_days' => $this->getDHLEstimatedDays($zone),
        ];
    }

    // ── FedEx ─────────────────────────────────────────────────────────
    private function getFedExRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zone = RateZone::where('carrier', 'FedEx')
            ->whereJsonContains('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $type = match ($shipmentType) {
            'Document' => 'Envelope',
            default    => 'Pak',
        };

        $price = $this->lookupPrice('FedEx', $zone, $type, $weight);

        if (! $price && $type === 'Pak') {
            $price = $this->lookupPrice('FedEx', $zone, 'Box', $weight);
            $type  = 'Box';
        }

        if (! $price) return null;

        // Map FedEx service code based on shipment type
        $serviceCode = match ($type) {
            'Envelope' => 'FEDEX_ENVELOP',
            'Pak'      => 'FEDEX_PAK',
            default    => 'FEDEX_IP',
        };

        return [
            'carrier'        => 'FedEx',
            'network'        => 'FEDEX',
            'service_code'   => $serviceCode,    // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'FedEx International Priority',
            'estimated_days' => $this->getFedExEstimatedDays($zone),
        ];
    }

    // ── Aramex ────────────────────────────────────────────────────────
    private function getAramexRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zones = RateZone::where('carrier', 'Aramex')
            ->whereJsonContains('countries', $country)
            ->get();

        if ($zones->isEmpty()) return null;

        $zone = $zones->first(fn($z) => str_contains($z->zone, 'PPX'))?->zone
            ?? $zones->first()?->zone;

        $type  = $shipmentType === 'Document' ? 'Document' : 'Non-Document';
        $price = $this->lookupPrice('Aramex', $zone, $type, $weight);

        if (! $price) return null;

        // Map Aramex zone to service code
        $serviceCode = match (true) {
            str_contains($zone, 'PPX') => 'ARAMEX_PPX',
            str_contains($zone, 'DPX') => 'ARAMEX_DPX',
            str_contains($zone, 'GPX') => 'ARAMEX_GPX',
            str_contains($zone, 'EPX') => 'ARAMEX_EPX',
            default                    => 'ARAMEX_PPX',
        };

        return [
            'carrier'        => 'Aramex',
            'network'        => 'ARAMEX',
            'service_code'   => $serviceCode,    // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'Aramex Priority Express',
            'estimated_days' => '3-5 business days',
        ];
    }

    // ── UPS ───────────────────────────────────────────────────────────
    private function getUPSRate(string $country, float $weight, string $shipmentType): ?array
    {
        $zone = RateZone::where('carrier', 'UPS')
            ->whereJsonContains('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $type  = $shipmentType === 'Document' ? 'Document' : 'Non-Document';
        $price = $this->lookupPrice('UPS', $zone, $type, $weight);

        if (! $price) return null;

        return [
            'carrier'        => 'UPS',
            'network'        => 'UPS',
            'service_code'   => 'UPS_SAVER',     // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => $type,
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'UPS Worldwide Saver',
            'estimated_days' => '3-5 business days',
        ];
    }

    // ── SELF UK ───────────────────────────────────────────────────────
    private function getSelfUKRate(string $country, float $weight): ?array
    {
        if (! in_array($country, ['UK', 'United Kingdom'])) return null;

        $price = $this->lookupPrice('SELF-UK', 'UK', 'Non-Document', $weight);

        if (! $price && $weight > 6) {
            $perKgRate = RatePricing::where('carrier', 'SELF-UK')
                ->where('is_per_kg', true)
                ->first()?->price;
            $price = $perKgRate ? round($perKgRate * $weight, 2) : null;
        }

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-UK',
            'network'        => 'SELF',
            'service_code'   => 'UK_DPD',        // Overseas API service code
            'requires_otp'   => false,
            'zone'           => 'UK',
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF UK (DPD)',
            'estimated_days' => '4-6 business days',
        ];
    }

    // ── SELF Europe ───────────────────────────────────────────────────
    private function getSelfEuropeRate(string $country, float $weight): ?array
    {
        $zone = RateZone::where('carrier', 'SELF-EUROPE')
            ->whereJsonContains('countries', $country)
            ->first()?->zone;

        if (! $zone) return null;

        $perKgRate = RatePricing::where('carrier', 'SELF-EUROPE')
            ->where('zone', $zone)
            ->where('weight', '<=', $weight)
            ->orderBy('weight', 'desc')
            ->first()?->price;

        if (! $perKgRate) return null;

        $price = round($perKgRate * $weight, 2);

        return [
            'carrier'        => 'SELF-EUROPE',
            'network'        => 'SELF',
            'service_code'   => 'EU_FR_DPD',     // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF Europe (DPD via Germany)',
            'estimated_days' => '5-7 business days',
        ];
    }

    // ── SELF Dubai ────────────────────────────────────────────────────
    private function getSelfDubaiRate(string $country, float $weight, int $packageCount): ?array
    {
        $zone = RateZone::where('carrier', 'SELF-DUBAI')
            ->whereJsonContains('countries', $country)
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
            'network'        => 'SELF',
            'service_code'   => 'GULF_EXPRESS',  // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'tier'           => $tier,
            'currency'       => 'INR',
            'service'        => 'SELF Dubai (Direct)',
            'estimated_days' => '2-4 business days',
        ];
    }

    // ── SELF Australia ────────────────────────────────────────────────
    private function getSelfAustraliaRate(string $country, float $weight, ?string $postcode, int $packageCount): ?array
    {
        if ($country !== 'Australia') return null;

        $zone  = $postcode ? AustraliaPostcode::findZone($postcode) : 'Metro';
        $tier  = $packageCount >= 10 ? 'above_10' : 'below_10';
        $price = RatePricing::where('carrier', 'SELF-AUSTRALIA')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->where('tier', $tier)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-AUSTRALIA',
            'network'        => 'SELF',
            'service_code'   => 'AU_SAVER',      // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'tier'           => $tier,
            'currency'       => 'INR',
            'service'        => 'SELF Australia (Toll Express)',
            'estimated_days' => '5-8 business days',
        ];
    }

    // ── SELF NZ ───────────────────────────────────────────────────────
    private function getSelfNZRate(string $country, float $weight, ?string $postcode, int $packageCount): ?array
    {
        if ($country !== 'New Zealand') return null;

        $zone  = 'Zone 1';
        $tier  = $packageCount >= 10 ? 'above_10' : 'below_10';
        $price = RatePricing::where('carrier', 'SELF-NZ')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->where('tier', $tier)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-NZ',
            'network'        => 'SELF',
            'service_code'   => 'NZ_ECONOMY',    // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF New Zealand (NZ Post)',
            'estimated_days' => '6-10 business days',
        ];
    }

    // ── SELF Canada ───────────────────────────────────────────────────
    private function getSelfCanadaRate(string $country, float $weight, ?string $postcode): ?array
    {
        if ($country !== 'Canada') return null;

        $zone  = 'YVR-Zone-1';
        $price = RatePricing::where('carrier', 'SELF-CANADA')
            ->where('zone', $zone)
            ->where('weight', $weight)
            ->first()?->price;

        if (! $price) return null;

        return [
            'carrier'        => 'SELF-CANADA',
            'network'        => 'SELF',
            'service_code'   => 'CANADA_YVR_SELF', // Overseas API service code
            'requires_otp'   => false,
            'zone'           => $zone,
            'shipment_type'  => 'Non-Document',
            'weight'         => $weight,
            'price'          => $price,
            'currency'       => 'INR',
            'service'        => 'SELF Canada (UPS Last Mile)',
            'estimated_days' => '7-10 business days',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function lookupPrice(string $carrier, string $zone, string $type, float $weight): ?float
    {
        $price = RatePricing::where('carrier', $carrier)
            ->where('zone', $zone)
            ->where('shipment_type', $type)
            ->where('weight', $weight)
            ->first()?->price;

        if ($price) return $price;

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