<?php

namespace App\Services\Rate;

use App\Models\ShiprocketRate;
use Illuminate\Support\Facades\DB;

class RateCalculationService
{
    // ── Static rate cache (loaded once per process) ───────────────────
    private static array $upsData        = [];
    private static array $fedexData      = [];
    private static array $upsDutyFree    = [];
    private static bool  $loaded         = false;

    // ── UPS: country_code → rate-column key ───────────────────────────
    private const UPS_COUNTRY_COLUMN = [
        // Direct named columns
        'US' => 'usa',   'PR' => 'usa',
        'CA' => 'canada',
        'MX' => 'mexico',
        'DE' => 'germany',
        'BE' => 'belgium', 'NL' => 'belgium', 'LU' => 'belgium',
        'GB' => 'uk',
        // Western Europe cluster → Denmark column
        'DK' => 'denmark', 'FR' => 'denmark', 'IT' => 'denmark',
        'ES' => 'denmark', 'CH' => 'denmark',
        // Austria cluster
        'AT' => 'austria', 'IE' => 'austria', 'PT' => 'austria', 'SE' => 'austria',
        // Norway cluster
        'NO' => 'norway', 'FI' => 'norway', 'GR' => 'norway',
        // Japan cluster
        'JP' => 'japan', 'KR' => 'japan',
        // Hong Kong cluster
        'HK' => 'hongkong', 'SG' => 'hongkong', 'MY' => 'hongkong', 'TH' => 'hongkong',
        // Australia / NZ
        'AU' => 'australia',
        'NZ' => 'nz',
        // Israel / Egypt
        'IL' => 'israel',
        'EG' => 'egypt',
        'GF' => 'frenchguinea',   // French Guiana
        'AE' => 'uae',
        // China cluster
        'CN' => 'china', 'ID' => 'china', 'PH' => 'china',
        // Zone 1 — South Asia
        'BD' => 'zone1', 'NP' => 'zone1', 'LK' => 'zone1',
        // Zone 2 — East Asia
        'MO' => 'zone2', 'TW' => 'zone2', 'VN' => 'zone2',
        // Zone 3 — Middle East
        'BH' => 'zone3', 'JO' => 'zone3', 'KW' => 'zone3', 'LB' => 'zone3',
        'OM' => 'zone3', 'PK' => 'zone3', 'QA' => 'zone3', 'SA' => 'zone3',
        // Zone 4 — Micro-states
        'AD' => 'zone4', 'SM' => 'zone4',
        // Zone 6
        'GG' => 'zone6', 'JE' => 'zone6', 'MP' => 'zone6',
        // Zone 8 — Central Europe
        'CZ' => 'zone8', 'HU' => 'zone8', 'PL' => 'zone8',
        // Zone 9 — Latin America
        'AI' => 'zone9', 'AG' => 'zone9', 'BB' => 'zone9', 'BZ' => 'zone9',
        'BO' => 'zone9', 'BR' => 'zone9', 'KY' => 'zone9', 'CL' => 'zone9',
        'CO' => 'zone9', 'CU' => 'zone9', 'DM' => 'zone9', 'DO' => 'zone9',
        'EC' => 'zone9', 'SV' => 'zone9', 'HN' => 'zone9', 'MS' => 'zone9',
        'NI' => 'zone9', 'PE' => 'zone9', 'KN' => 'zone9', 'VC' => 'zone9',
        'MF' => 'zone9', 'TC' => 'zone9', 'VE' => 'zone9',
        // Zone 10 — Eastern Europe / Balkans
        'AL' => 'zone10', 'AM' => 'zone10', 'BY' => 'zone10', 'BA' => 'zone10',
        'HR' => 'zone10', 'EE' => 'zone10', 'GE' => 'zone10', 'XK' => 'zone10',
        'LV' => 'zone10', 'LT' => 'zone10', 'MK' => 'zone10', 'MD' => 'zone10',
        'ME' => 'zone10', 'RS' => 'zone10', 'SI' => 'zone10', 'UA' => 'zone10',
        // Zone 11
        'MV' => 'zone11', 'MU' => 'zone11', 'NC' => 'zone11',
        // Zone 12 — Caribbean / Latin
        'AR' => 'zone12', 'AW' => 'zone12', 'BS' => 'zone12', 'BM' => 'zone12',
        'VG' => 'zone12', 'CR' => 'zone12', 'CW' => 'zone12', 'GD' => 'zone12',
        'GP' => 'zone12', 'GT' => 'zone12', 'GY' => 'zone12', 'HT' => 'zone12',
        'JM' => 'zone12', 'MQ' => 'zone12', 'PA' => 'zone12', 'PY' => 'zone12',
        'BL' => 'zone12', 'LC' => 'zone12', 'SR' => 'zone12', 'TT' => 'zone12',
        'VI' => 'zone12', 'UY' => 'zone12',
        // Zone 13 — SE Europe
        'BG' => 'zone13', 'CY' => 'zone13', 'GI' => 'zone13', 'IS' => 'zone13',
        'MT' => 'zone13', 'RO' => 'zone13', 'SK' => 'zone13', 'TR' => 'zone13',
        // Zone 14 — Central Asia / Remote
        'AO' => 'zone14', 'AZ' => 'zone14', 'CV' => 'zone14', 'KM' => 'zone14',
        'GQ' => 'zone14', 'ER' => 'zone14', 'FO' => 'zone14', 'GL' => 'zone14',
        'KZ' => 'zone14', 'KG' => 'zone14', 'YT' => 'zone14', 'TJ' => 'zone14',
        'UZ' => 'zone14',
        // Zone 15 — West / Central Africa
        'AS' => 'zone15', 'CM' => 'zone15', 'CG' => 'zone15', 'DJ' => 'zone15',
        'GM' => 'zone15', 'CI' => 'zone15', 'NE' => 'zone15', 'RE' => 'zone15',
        'SC' => 'zone15', 'SL' => 'zone15', 'WS' => 'zone15',
        // Zone 7 — Africa / Rest of World (also default)
        'DZ' => 'zone7', 'BJ' => 'zone7', 'BT' => 'zone7', 'BW' => 'zone7',
        'BN' => 'zone7', 'BF' => 'zone7', 'BI' => 'zone7', 'KH' => 'zone7',
        'CF' => 'zone7', 'TD' => 'zone7', 'CK' => 'zone7', 'ET' => 'zone7',
        'PF' => 'zone7', 'GA' => 'zone7', 'GH' => 'zone7', 'GU' => 'zone7',
        'GN' => 'zone7', 'GW' => 'zone7', 'KE' => 'zone7', 'KI' => 'zone7',
        'LA' => 'zone7', 'LS' => 'zone7', 'LR' => 'zone7', 'MG' => 'zone7',
        'MW' => 'zone7', 'ML' => 'zone7', 'MH' => 'zone7', 'MR' => 'zone7',
        'FM' => 'zone7', 'MN' => 'zone7', 'MA' => 'zone7', 'MZ' => 'zone7',
        'MM' => 'zone7', 'NA' => 'zone7', 'NG' => 'zone7', 'PW' => 'zone7',
        'PG' => 'zone7', 'RW' => 'zone7', 'SN' => 'zone7', 'SB' => 'zone7',
        'ZA' => 'zone7', 'SZ' => 'zone7', 'TZ' => 'zone7', 'TL' => 'zone7',
        'TG' => 'zone7', 'TO' => 'zone7', 'TN' => 'zone7', 'TV' => 'zone7',
        'UG' => 'zone7', 'VU' => 'zone7', 'ZM' => 'zone7', 'ZW' => 'zone7',
        'WF' => 'zone7', 'LY' => 'zone7', 'SD' => 'zone7', 'SO' => 'zone7',
        'RU' => 'zone10',
    ];

    // ── FedEx: country_code → zone char ───────────────────────────────
    private const FEDEX_COUNTRY_ZONE = [
        'AE' => 'a',
        'BD' => 'b', 'BT' => 'b', 'MV' => 'b', 'NP' => 'b',
        'PK' => 'b', 'SG' => 'b', 'LK' => 'b',
        'AF' => 'c', 'EG' => 'c', 'IQ' => 'c', 'IR' => 'c', 'JO' => 'c',
        'LB' => 'c', 'MM' => 'c', 'PS' => 'c', 'SA' => 'c', 'SY' => 'c',
        'TM' => 'c', 'YE' => 'c',
        'CN' => 'd', 'HK' => 'd', 'TH' => 'd',
        'AS' => 'e', 'AU' => 'e', 'BN' => 'e', 'KH' => 'e', 'CK' => 'e',
        'TL' => 'e', 'FJ' => 'e', 'PF' => 'e', 'GU' => 'e', 'ID' => 'e',
        'LA' => 'e', 'MO' => 'e', 'MY' => 'e', 'MH' => 'e', 'FM' => 'e',
        'MN' => 'e', 'NC' => 'e', 'NZ' => 'e', 'PW' => 'e', 'PG' => 'e',
        'PH' => 'e', 'MP' => 'e', 'WS' => 'e', 'SB' => 'e', 'KR' => 'e',
        'TW' => 'e', 'TO' => 'e', 'TV' => 'e', 'VU' => 'e', 'VN' => 'e',
        'BE' => 'f', 'DK' => 'f', 'FO' => 'f', 'FR' => 'f', 'DE' => 'f',
        'GL' => 'f', 'IT' => 'f', 'LI' => 'f', 'LU' => 'f', 'NL' => 'f',
        'ES' => 'f', 'CH' => 'f', 'GB' => 'f',
        'MX' => 'g', 'US' => 'g', 'PR' => 'g',
        'JP' => 'h',
        'AL' => 'i', 'AD' => 'i', 'AM' => 'i', 'AT' => 'i', 'AZ' => 'i',
        'BY' => 'i', 'BA' => 'i', 'BG' => 'i', 'HR' => 'i', 'CY' => 'i',
        'CZ' => 'i', 'EE' => 'i', 'FI' => 'i', 'GE' => 'i', 'GI' => 'i',
        'GR' => 'i', 'HU' => 'i', 'IS' => 'i', 'IE' => 'i', 'KZ' => 'i',
        'KI' => 'i', 'KG' => 'i', 'LV' => 'i', 'LT' => 'i', 'MK' => 'i',
        'MT' => 'i', 'MD' => 'i', 'MC' => 'i', 'ME' => 'i', 'NO' => 'i',
        'PL' => 'i', 'PT' => 'i', 'RO' => 'i', 'RU' => 'i', 'RS' => 'i',
        'SK' => 'i', 'SI' => 'i', 'SE' => 'i', 'TR' => 'i', 'UA' => 'i',
        'UZ' => 'i',
        'ZA' => 'k',
        'CA' => 'l',
        'BH' => 'm', 'KW' => 'm', 'OM' => 'm', 'QA' => 'm',
        'CF' => 'n', 'TD' => 'n', 'DJ' => 'n', 'ER' => 'n', 'ET' => 'n',
        'KE' => 'n', 'MU' => 'n', 'SD' => 'n', 'TZ' => 'n', 'UG' => 'n',
        'DZ' => 'o', 'AO' => 'o', 'CI' => 'o', 'GH' => 'o', 'LY' => 'o',
        'MA' => 'o', 'NG' => 'o', 'SC' => 'o',
        'BW' => 'p', 'LS' => 'p', 'NA' => 'p', 'RW' => 'p', 'SZ' => 'p',
        'ZM' => 'p', 'ZW' => 'p',
        'BJ' => 'q', 'BF' => 'q', 'BI' => 'q', 'CM' => 'q', 'CV' => 'q',
        'CG' => 'q', 'GQ' => 'q', 'GA' => 'q', 'GM' => 'q', 'GW' => 'q',
        'LR' => 'q', 'MG' => 'q', 'MW' => 'q', 'ML' => 'q', 'MR' => 'q',
        'MZ' => 'q', 'NE' => 'q', 'RE' => 'q', 'SN' => 'q', 'SL' => 'q',
        'TG' => 'q', 'TN' => 'q',
    ];

    // ── Main entry point ──────────────────────────────────────────────

    public function calculate(
        string  $countryCode,
        string  $country,
        float   $actualWeight,
        ?float  $length       = null,
        ?float  $breadth      = null,
        ?float  $height       = null,
        string  $shipmentType = 'Non-Document',
        ?string $postcode     = null,
        int     $packageCount = 1,
        string  $serviceType  = 'standard',   // 'standard' | 'ecommerce'
    ): array {
        $this->loadRates();

        $volumetricWeight = $this->getVolumetricWeight($length, $breadth, $height);
        $chargeableWeight = $volumetricWeight
            ? max($actualWeight, $volumetricWeight)
            : $actualWeight;

        $rates = [];

        if ($serviceType === 'standard') {
            // UPS Express
            $ups = $this->getUPSRate($countryCode, $chargeableWeight, $shipmentType);
            if ($ups) $rates[] = $ups;

            // UPS Duty Free (USA / PR only, Non-Document only)
            if ($shipmentType === 'Non-Document' && in_array($countryCode, ['US', 'PR'])) {
                $upsd = $this->getUPSDutyFreeRate($chargeableWeight);
                if ($upsd) $rates[] = $upsd;
            }

            // FedEx Express
            foreach ($this->getFedExRates($countryCode, $chargeableWeight, $shipmentType) as $r) {
                $rates[] = $r;
            }
        } else {
            // eCommerce — Shiprocket services (SRX / Aramex / India Post)
            foreach ($this->getShiprocketRates($countryCode, $chargeableWeight) as $r) {
                $rates[] = $r;
            }
        }

        usort($rates, fn($a, $b) => $a['rate'] <=> $b['rate']);

        return [
            'destination'        => $country,
            'country_code'       => $countryCode,
            'actual_weight'      => $actualWeight,
            'volumetric_weight'  => $volumetricWeight,
            'chargeable_weight'  => $chargeableWeight,
            'shipment_type'      => $shipmentType,
            'rates'              => $rates,
        ];
    }

    // ── Lazy-load JSON rate files ─────────────────────────────────────

    private function loadRates(): void
    {
        if (self::$loaded) return;

        $upsPath   = storage_path('app/rates/ups_rates.json');
        $fedexPath = storage_path('app/rates/fedex_rates.json');
        $upsdPath  = storage_path('app/rates/ups_duty_free_rates.json');

        self::$upsData     = file_exists($upsPath)   ? json_decode(file_get_contents($upsPath),   true) : [];
        self::$fedexData   = file_exists($fedexPath) ? json_decode(file_get_contents($fedexPath), true) : [];
        self::$upsDutyFree = file_exists($upsdPath)  ? json_decode(file_get_contents($upsdPath),  true) : [];
        self::$loaded      = true;
    }

    // ── Weight helpers ────────────────────────────────────────────────

    private function getVolumetricWeight(?float $l, ?float $b, ?float $h): ?float
    {
        if ($l && $b && $h) {
            return round(($l * $b * $h) / 5000, 2);
        }
        return null;
    }

    private function upsWeightKey(float $chargeable): string
    {
        // Round up to nearest 0.5 kg
        return (string)(ceil($chargeable * 2) / 2);
    }

    private function fedexWeightKey(float $chargeable): string
    {
        // Round up to nearest 0.5 kg, max 70.5
        $w = min(ceil($chargeable * 2) / 2, 70.5);
        return (string)$w;
    }

    private function srWeightKey(float $chargeable): string
    {
        // Round up to nearest 0.05 kg, minimum 0.05
        $w = max(0.05, ceil($chargeable * 20) / 20);
        // Format to avoid floating-point string artifacts
        return number_format($w, 2, '.', '');
    }

    // ── UPS Express ───────────────────────────────────────────────────

    private function getUPSRate(string $countryCode, float $chargeable, string $shipmentType): ?array
    {
        $col = self::UPS_COUNTRY_COLUMN[$countryCode] ?? 'zone7';

        $slabW = ceil($chargeable * 2) / 2;

        if ($slabW <= 20) {
            $key  = (string)$slabW;
            $rate = self::$upsData['rates'][$key][$col] ?? null;
        } else {
            $tier    = $this->upsPerKgTier($slabW);
            $perKgR  = self::$upsData['perKg'][$tier][$col] ?? null;
            $rate    = $perKgR ? (int)round($perKgR * $chargeable) : null;
        }

        if (! $rate) return null;

        return [
            'carrier'            => 'UPS',
            'network'            => 'UPS',
            'service_code'       => 'UPS_SAVER',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => $shipmentType,
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'UPS Worldwide Saver',
            'estimated_delivery' => '3-5 business days',
        ];
    }

    private function upsPerKgTier(float $weight): string
    {
        return match(true) {
            $weight >= 1000 => '1000plus',
            $weight >= 500  => '500plus',
            $weight >= 300  => '300plus',
            $weight >= 100  => '100plus',
            $weight >= 70   => '70plus',
            $weight >= 50   => '50plus',
            $weight >= 30   => '30plus',
            default         => '20plus',
        };
    }

    // ── UPS Duty Free ─────────────────────────────────────────────────

    private function getUPSDutyFreeRate(float $chargeable): ?array
    {
        if (empty(self::$upsDutyFree)) return null;

        $slabW = min(ceil($chargeable * 2) / 2, 20); // max 20 kg
        $key   = (string)$slabW;
        $rate  = self::$upsDutyFree[$key] ?? null;

        if (! $rate) return null;

        return [
            'carrier'            => 'UPS',
            'network'            => 'UPS',
            'service_code'       => 'UPS_DUTY_FREE',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'UPS Duty Free (C2C USA)',
            'estimated_delivery' => '3-5 business days',
        ];
    }

    // ── FedEx Express ─────────────────────────────────────────────────

    private function getFedExRates(string $countryCode, float $chargeable, string $shipmentType): array
    {
        $zone = self::FEDEX_COUNTRY_ZONE[$countryCode] ?? 'j';
        $rates = [];

        if ($shipmentType === 'Document' && $chargeable <= 2.5) {
            // Pak rate for light documents
            $key  = $this->fedexWeightKey($chargeable);
            // For Pak, weights only go to 2.5; if rounded key > 2.5, skip
            if ((float)$key <= 2.5) {
                $rate = self::$fedexData['pak'][$key][$zone] ?? null;
                if ($rate) {
                    $rates[] = [
                        'carrier'            => 'FedEx',
                        'network'            => 'FEDEX',
                        'service_code'       => 'FEDEX_PAK',
                        'platform'           => 'overseas',
                        'requires_otp'       => false,
                        'shipment_type'      => 'Document',
                        'chargeable_weight'  => $chargeable,
                        'rate'               => $rate,
                        'currency'           => 'INR',
                        'service_name'       => 'FedEx International Priority (Pak)',
                        'estimated_delivery' => $this->fedexETA($zone),
                    ];
                }
            }
        }

        // Package rate (for all non-docs and docs > 2.5 kg)
        $key  = $this->fedexWeightKey($chargeable);
        $rate = self::$fedexData['package'][$key][$zone] ?? null;

        if ($rate) {
            $rates[] = [
                'carrier'            => 'FedEx',
                'network'            => 'FEDEX',
                'service_code'       => 'FEDEX_IP',
                'platform'           => 'overseas',
                'requires_otp'       => false,
                'shipment_type'      => $shipmentType,
                'chargeable_weight'  => $chargeable,
                'rate'               => $rate,
                'currency'           => 'INR',
                'service_name'       => 'FedEx International Priority',
                'estimated_delivery' => $this->fedexETA($zone),
            ];
        }

        return $rates;
    }

    private function fedexETA(string $zone): string
    {
        return match($zone) {
            'a', 'b', 'c', 'm' => '2-3 business days',
            'd', 'h'           => '3-4 business days',
            'f', 'g', 'i', 'l' => '3-5 business days',
            default            => '4-7 business days',
        };
    }

    // ── Shiprocket (SRX / Aramex / India Post) ────────────────────────

    private function getShiprocketRates(string $countryCode, float $chargeable): array
    {
        // Find the minimum available weight per service >= chargeable weight
        $srWeight = max(0.05, ceil($chargeable * 20) / 20);

        $rows = DB::select(
            'SELECT sr.service, sr.rate
             FROM shiprocket_rates sr
             INNER JOIN (
                 SELECT service, MIN(weight) AS min_w
                 FROM shiprocket_rates
                 WHERE country_code = ? AND weight >= ?
                 GROUP BY service
             ) best ON sr.service = best.service
                    AND sr.weight  = best.min_w
                    AND sr.country_code = ?
             ORDER BY sr.rate ASC',
            [$countryCode, $srWeight, $countryCode]
        );

        return array_map(function ($row) use ($chargeable) {
            return [
                'carrier'            => $this->srCarrierName($row->service),
                'network'            => 'SHIPROCKET',
                'service_code'       => $row->service,
                'platform'           => 'shiprocket',
                'requires_otp'       => false,
                'shipment_type'      => 'Non-Document',
                'chargeable_weight'  => $chargeable,
                'rate'               => (int)$row->rate,
                'currency'           => 'INR',
                'service_name'       => $row->service,
                'estimated_delivery' => $this->srETA($row->service),
            ];
        }, $rows);
    }

    private function srCarrierName(string $service): string
    {
        return match(true) {
            str_starts_with($service, 'Aramex')     => 'Aramex',
            str_starts_with($service, 'India Post') => 'India Post',
            default                                  => 'Shiprocket',
        };
    }

    private function srETA(string $service): string
    {
        return match(true) {
            str_contains($service, 'Priority')   => '5-7 business days',
            str_contains($service, 'Premium')    => '7-10 business days',
            str_contains($service, 'Economy')    => '10-15 business days',
            str_contains($service, 'India Post') => '15-25 business days',
            default                               => '7-14 business days',
        };
    }
}
