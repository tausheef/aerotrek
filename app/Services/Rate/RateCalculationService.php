<?php

namespace App\Services\Rate;

use App\Models\CarrierRate;
use App\Models\PostcodeZone;
use App\Models\RateUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RateCalculationService
{
    // ── DB rate cache (keyed by upload_id, reset when upload changes) ─────────
    private static ?int  $cachedUploadId    = null;
    private static array $dbRates           = [];  // [carrier][sub_type][zone_key][weight_key]
    private static array $postcodeZoneCache = [];  // [carrier][postcode] = zone_key

    // ── Canada postal FSA first-letter → approximate zone (YYZ-hub routing) ──
    // Zone 1 = Toronto downtown (closest), Zone 13 = Territories/far west
    private const CANADA_FSA_ZONE = [
        'M' => 'zone1',  // Toronto (downtown)
        'L' => 'zone2',  // Greater Toronto Area
        'K' => 'zone3',  // Ottawa / Kingston
        'N' => 'zone4',  // Southwestern Ontario (London, Windsor)
        'P' => 'zone5',  // Northern Ontario
        'H' => 'zone6',  // Montreal
        'G' => 'zone7',  // Quebec City / Eastern Quebec
        'J' => 'zone8',  // Western Quebec / Laurentians
        'E' => 'zone9',  // New Brunswick
        'B' => 'zone10', // Nova Scotia
        'C' => 'zone11', // Prince Edward Island
        'A' => 'zone11', // Newfoundland
        'R' => 'zone12', // Manitoba
        'S' => 'zone12', // Saskatchewan
        'T' => 'zone13', // Alberta
        'V' => 'zone13', // British Columbia (has its own YVR service)
        'X' => 'zone13', // NWT / Nunavut
        'Y' => 'zone13', // Yukon
    ];

    // ── UPS: country_code → zone_key ─────────────────────────────────────────
    private const UPS_COUNTRY_COLUMN = [
        'US' => 'usa',   'PR' => 'usa',
        'CA' => 'canada',
        'MX' => 'mexico',
        'DE' => 'germany',
        'BE' => 'belgium', 'NL' => 'belgium', 'LU' => 'belgium',
        'GB' => 'uk',
        'DK' => 'denmark', 'FR' => 'denmark', 'IT' => 'denmark',
        'ES' => 'denmark', 'CH' => 'denmark',
        'AT' => 'austria', 'IE' => 'austria', 'PT' => 'austria', 'SE' => 'austria',
        'NO' => 'norway',  'FI' => 'norway',  'GR' => 'norway',
        'JP' => 'japan',   'KR' => 'japan',
        'HK' => 'hongkong', 'SG' => 'hongkong', 'MY' => 'hongkong', 'TH' => 'hongkong',
        'AU' => 'australia',
        'NZ' => 'nz',
        'IL' => 'israel',
        'EG' => 'egypt',
        'GF' => 'frenchguinea',
        'AE' => 'uae',
        'CN' => 'china', 'ID' => 'china', 'PH' => 'china',
        'BD' => 'zone1', 'NP' => 'zone1', 'LK' => 'zone1',
        'MO' => 'zone2', 'TW' => 'zone2', 'VN' => 'zone2',
        'BH' => 'zone3', 'JO' => 'zone3', 'KW' => 'zone3', 'LB' => 'zone3',
        'OM' => 'zone3', 'PK' => 'zone3', 'QA' => 'zone3', 'SA' => 'zone3',
        'AD' => 'zone4', 'SM' => 'zone4',
        'GG' => 'zone6', 'JE' => 'zone6', 'MP' => 'zone6',
        'CZ' => 'zone8', 'HU' => 'zone8', 'PL' => 'zone8',
        'AI' => 'zone9', 'AG' => 'zone9', 'BB' => 'zone9', 'BZ' => 'zone9',
        'BO' => 'zone9', 'BR' => 'zone9', 'KY' => 'zone9', 'CL' => 'zone9',
        'CO' => 'zone9', 'CU' => 'zone9', 'DM' => 'zone9', 'DO' => 'zone9',
        'EC' => 'zone9', 'SV' => 'zone9', 'HN' => 'zone9', 'MS' => 'zone9',
        'NI' => 'zone9', 'PE' => 'zone9', 'KN' => 'zone9', 'VC' => 'zone9',
        'MF' => 'zone9', 'TC' => 'zone9', 'VE' => 'zone9',
        'AL' => 'zone10', 'AM' => 'zone10', 'BY' => 'zone10', 'BA' => 'zone10',
        'HR' => 'zone10', 'EE' => 'zone10', 'GE' => 'zone10', 'XK' => 'zone10',
        'LV' => 'zone10', 'LT' => 'zone10', 'MK' => 'zone10', 'MD' => 'zone10',
        'ME' => 'zone10', 'RS' => 'zone10', 'SI' => 'zone10', 'UA' => 'zone10',
        'RU' => 'zone10',
        'MV' => 'zone11', 'MU' => 'zone11', 'NC' => 'zone11',
        'AR' => 'zone12', 'AW' => 'zone12', 'BS' => 'zone12', 'BM' => 'zone12',
        'VG' => 'zone12', 'CR' => 'zone12', 'CW' => 'zone12', 'GD' => 'zone12',
        'GP' => 'zone12', 'GT' => 'zone12', 'GY' => 'zone12', 'HT' => 'zone12',
        'JM' => 'zone12', 'MQ' => 'zone12', 'PA' => 'zone12', 'PY' => 'zone12',
        'BL' => 'zone12', 'LC' => 'zone12', 'SR' => 'zone12', 'TT' => 'zone12',
        'VI' => 'zone12', 'UY' => 'zone12',
        'BG' => 'zone13', 'CY' => 'zone13', 'GI' => 'zone13', 'IS' => 'zone13',
        'MT' => 'zone13', 'RO' => 'zone13', 'SK' => 'zone13', 'TR' => 'zone13',
        'AO' => 'zone14', 'AZ' => 'zone14', 'CV' => 'zone14', 'KM' => 'zone14',
        'GQ' => 'zone14', 'ER' => 'zone14', 'FO' => 'zone14', 'GL' => 'zone14',
        'KZ' => 'zone14', 'KG' => 'zone14', 'YT' => 'zone14', 'TJ' => 'zone14',
        'UZ' => 'zone14',
        'AS' => 'zone15', 'CM' => 'zone15', 'CG' => 'zone15', 'DJ' => 'zone15',
        'GM' => 'zone15', 'CI' => 'zone15', 'NE' => 'zone15', 'RE' => 'zone15',
        'SC' => 'zone15', 'SL' => 'zone15', 'WS' => 'zone15',
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
    ];

    // ── FedEx: country_code → zone_key ───────────────────────────────────────
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

    // ── ARAMEX: country_code → [{zone, name}] (multiple service tiers per country) ─
    private const ARAMEX_COUNTRY_ZONES = [
        'AE' => [
            ['zone' => 'uae_ppx', 'name' => 'Aramex Priority UAE (PPX)'],
            ['zone' => 'uae_dpx', 'name' => 'Aramex Express UAE (DPX)'],
        ],
        'AU' => [
            ['zone' => 'au_ppx_metro', 'name' => 'Aramex Australia (PPX)'],
            ['zone' => 'au_dpx_metro', 'name' => 'Aramex Australia (DPX)'],
        ],
        'BH' => [
            ['zone' => 'bh_kw_ppx', 'name' => 'Aramex Priority Bahrain (PPX)'],
            ['zone' => 'bh_kw_dpx', 'name' => 'Aramex Express Bahrain (DPX)'],
        ],
        'KW' => [
            ['zone' => 'bh_kw_ppx', 'name' => 'Aramex Priority Kuwait (PPX)'],
            ['zone' => 'bh_kw_dpx', 'name' => 'Aramex Express Kuwait (DPX)'],
        ],
        'OM' => [
            ['zone' => 'oman_ppx', 'name' => 'Aramex Priority Oman (PPX)'],
            ['zone' => 'oman_gpx', 'name' => 'Aramex Ground Oman (GPX)'],
        ],
        'QA' => [
            ['zone' => 'qatar_ppx', 'name' => 'Aramex Priority Qatar (PPX)'],
        ],
        'SA' => [
            ['zone' => 'sa_ppx', 'name' => 'Aramex Priority Saudi Arabia (PPX)'],
            ['zone' => 'sa_dpx', 'name' => 'Aramex Express Saudi Arabia (DPX)'],
        ],
    ];

    // ── EU_SELF: country_code → zone_key (EU via LHR zone structure) ─────────
    private const EU_SELF_COUNTRY_ZONE = [
        // Zone 1 — Benelux (closest to UK transit hub)
        'BE' => 'zone1', 'NL' => 'zone1', 'LU' => 'zone1',
        // Zone 2 — Germany, France
        'DE' => 'zone2', 'FR' => 'zone2',
        // Zone 3 — Austria, Switzerland, Italy, Spain
        'AT' => 'zone3', 'CH' => 'zone3', 'IT' => 'zone3', 'ES' => 'zone3',
        // Zone 4 — Nordics, Portugal, Ireland
        'SE' => 'zone4', 'DK' => 'zone4', 'NO' => 'zone4', 'FI' => 'zone4',
        'PT' => 'zone4', 'IE' => 'zone4',
        // Zone 5 — Central/Eastern Europe
        'PL' => 'zone5', 'CZ' => 'zone5', 'HU' => 'zone5', 'SK' => 'zone5',
        'RO' => 'zone5', 'SI' => 'zone5', 'HR' => 'zone5',
        // Zone 6 — Baltics, Bulgaria, southern EU
        'EE' => 'zone6', 'LV' => 'zone6', 'LT' => 'zone6', 'BG' => 'zone6',
        'GR' => 'zone6', 'CY' => 'zone6', 'MT' => 'zone6',
        // Zone 7 — Non-EU/remote European destinations
        'AL' => 'zone7', 'RS' => 'zone7', 'ME' => 'zone7', 'MK' => 'zone7',
        'BA' => 'zone7', 'XK' => 'zone7', 'IS' => 'zone7', 'MC' => 'zone7',
        'SM' => 'zone7', 'AD' => 'zone7', 'LI' => 'zone7', 'GI' => 'zone7',
    ];

    // ── DPEX_SELF: country_code → zone_key ───────────────────────────────────
    private const DPEX_SELF_COUNTRY_ZONE = [
        'SG' => 'sg_direct',
        'MY' => 'my_kl',
        'TW' => 'tw_direct',
        'VN' => 'vn_direct',
    ];

    // ── DHL: country_code → zone_key ─────────────────────────────────────────
    private const DHL_COUNTRY_ZONE = [
        'BD' => 'zone1', 'BT' => 'zone1', 'MV' => 'zone1', 'NP' => 'zone1', 'LK' => 'zone1', 'AE' => 'zone1',
        'HK' => 'zone2', 'MY' => 'zone2', 'SG' => 'zone2', 'TH' => 'zone2',
        'CN' => 'zone3',
        'BH' => 'zone4', 'JO' => 'zone4', 'KW' => 'zone4', 'OM' => 'zone4',
        'PK' => 'zone4', 'QA' => 'zone4', 'SA' => 'zone4',
        'BN' => 'zone5', 'KH' => 'zone5', 'TL' => 'zone5', 'ID' => 'zone5',
        'JP' => 'zone5', 'KR' => 'zone5', 'LA' => 'zone5', 'MO' => 'zone5',
        'MM' => 'zone5', 'PH' => 'zone5', 'TW' => 'zone5', 'VN' => 'zone5',
        'NZ' => 'zone6', 'PG' => 'zone6',
        'AT' => 'zone7', 'BE' => 'zone7', 'CZ' => 'zone7', 'DK' => 'zone7',
        'FR' => 'zone7', 'DE' => 'zone7', 'HU' => 'zone7', 'IE' => 'zone7',
        'IT' => 'zone7', 'LI' => 'zone7', 'LU' => 'zone7', 'MC' => 'zone7',
        'NL' => 'zone7', 'PL' => 'zone7', 'PT' => 'zone7', 'RO' => 'zone7',
        'SK' => 'zone7', 'ES' => 'zone7', 'SE' => 'zone7', 'CH' => 'zone7',
        'GB' => 'zone7',
        'AD' => 'zone8', 'BY' => 'zone8', 'BG' => 'zone8', 'CY' => 'zone8',
        'EE' => 'zone8', 'FI' => 'zone8', 'GI' => 'zone8', 'GR' => 'zone8',
        'GL' => 'zone8', 'GG' => 'zone8', 'IS' => 'zone8', 'IL' => 'zone8',
        'JE' => 'zone8', 'LV' => 'zone8', 'LT' => 'zone8', 'MT' => 'zone8',
        'NO' => 'zone8', 'SI' => 'zone8', 'TR' => 'zone8',
        'CA' => 'zone9', 'MX' => 'zone9', 'PR' => 'zone9',
        'AS' => 'zone9', 'GU' => 'zone9', 'MH' => 'zone9', 'VI' => 'zone9',
        'AI' => 'zone10', 'AG' => 'zone10', 'AR' => 'zone10', 'AW' => 'zone10',
        'BS' => 'zone10', 'BB' => 'zone10', 'BZ' => 'zone10', 'BM' => 'zone10',
        'BO' => 'zone10', 'BR' => 'zone10', 'KY' => 'zone10', 'CL' => 'zone10',
        'CO' => 'zone10', 'CR' => 'zone10', 'CU' => 'zone10', 'CW' => 'zone10',
        'DM' => 'zone10', 'DO' => 'zone10', 'EC' => 'zone10', 'SV' => 'zone10',
        'GF' => 'zone10', 'GP' => 'zone10', 'GD' => 'zone10', 'GT' => 'zone10',
        'GY' => 'zone10', 'HT' => 'zone10', 'HN' => 'zone10', 'JM' => 'zone10',
        'MQ' => 'zone10', 'MS' => 'zone10', 'NI' => 'zone10', 'PA' => 'zone10',
        'PE' => 'zone10', 'PY' => 'zone10', 'BL' => 'zone10', 'KN' => 'zone10',
        'LC' => 'zone10', 'MF' => 'zone10', 'VC' => 'zone10', 'SR' => 'zone10',
        'TT' => 'zone10', 'TC' => 'zone10', 'UY' => 'zone10', 'VE' => 'zone10',
        'VG' => 'zone10',
        'US' => 'zone12',
        'EG' => 'zone13', 'GH' => 'zone13', 'KE' => 'zone13', 'MU' => 'zone13',
        'MZ' => 'zone13', 'NG' => 'zone13', 'ZA' => 'zone13', 'SD' => 'zone13',
        'TZ' => 'zone13', 'UG' => 'zone13', 'ZW' => 'zone13',
        'AU' => 'zone14',
    ];

    // ─────────────────────────────────────────────────────────────────────────
    // Main entry point
    // ─────────────────────────────────────────────────────────────────────────

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
        string  $serviceType  = 'standard',
        ?string $userId       = null,
    ): array {
        $volumetricWeight = $this->getVolumetricWeight($length, $breadth, $height);
        $chargeableWeight = $volumetricWeight
            ? max($actualWeight, $volumetricWeight)
            : $actualWeight;

        $uploadId = $this->getActiveUploadId($userId);
        $rates    = [];

        if ($serviceType === 'standard') {
            if ($uploadId) {
                $this->loadDbRates($uploadId);
                $rates = array_merge(
                    $this->getUPSRates($countryCode, $chargeableWeight, $shipmentType),
                    $this->getFedExRates($countryCode, $chargeableWeight, $shipmentType),
                    $this->getDHLRates($countryCode, $chargeableWeight, $shipmentType),
                    $this->getAramexRates($countryCode, $chargeableWeight, $shipmentType),
                    $this->getCanadaSelfRates($countryCode, $chargeableWeight, $postcode),
                    $this->getEuSelfRates($countryCode, $chargeableWeight),
                    $this->getUkSelfRates($countryCode, $chargeableWeight),
                    $this->getAuSelfRates($countryCode, $chargeableWeight, $postcode),
                    $this->getNzSelfRates($countryCode, $chargeableWeight, $postcode),
                    $this->getUaeSelfRates($countryCode, $chargeableWeight),
                    $this->getDpexSelfRates($countryCode, $chargeableWeight),
                );
            }
            // No rates if no upload has been done yet
        } else {
            $rates = $this->getShiprocketRates($countryCode, $chargeableWeight, $uploadId);
        }

        usort($rates, fn($a, $b) => $a['rate'] <=> $b['rate']);

        return [
            'destination'       => $country,
            'country_code'      => $countryCode,
            'actual_weight'     => $actualWeight,
            'volumetric_weight' => $volumetricWeight,
            'chargeable_weight' => $chargeableWeight,
            'shipment_type'     => $shipmentType,
            'rates'             => $rates,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Active upload ID — cached 60 s; busted immediately on new upload
    // ─────────────────────────────────────────────────────────────────────────

    private function getActiveUploadId(?string $userId = null): ?int
    {
        if ($userId) {
            $userUploadId = Cache::remember("active_rate_upload_id_user_{$userId}", 60, function () use ($userId) {
                return RateUpload::where('status', 'active')
                    ->where('user_id', $userId)
                    ->latest('activated_at')
                    ->value('id');
            });
            if ($userUploadId) return $userUploadId;
        }

        return Cache::remember('active_rate_upload_id', 60, function () {
            return RateUpload::where('status', 'active')
                ->whereNull('user_id')
                ->latest('activated_at')
                ->value('id');
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DB loader — one query per upload_id, cached in static array
    // ─────────────────────────────────────────────────────────────────────────

    private function loadDbRates(int $uploadId): void
    {
        if (self::$cachedUploadId === $uploadId) return;

        self::$dbRates           = [];
        self::$postcodeZoneCache = [];
        self::$cachedUploadId    = $uploadId;

        $rows = CarrierRate::where('upload_id', $uploadId)
            ->get(['carrier', 'sub_type', 'zone_key', 'weight_key', 'rate', 'is_per_kg'])
            ->toArray();

        foreach ($rows as $row) {
            $sub = $row['sub_type'] ?? '__any__';
            self::$dbRates[$row['carrier']][$sub][$row['zone_key']][$row['weight_key']] = [
                'rate'      => $row['rate'],
                'is_per_kg' => $row['is_per_kg'],
            ];
        }

        // Load postcode→zone maps for all SELF carriers in one query
        $pzRows = PostcodeZone::where('upload_id', $uploadId)
            ->get(['carrier', 'postcode', 'zone_key'])
            ->toArray();

        foreach ($pzRows as $row) {
            self::$postcodeZoneCache[$row['carrier']][$row['postcode']] = $row['zone_key'];
        }
    }

    // Normalise postcode and look it up in the in-memory cache.
    // Strips leading zeros so "0505" and "505" both match the stored "505".
    private function lookupPostcodeZone(string $carrier, ?string $postcode): ?string
    {
        if (!$postcode) return null;
        $key = ltrim(trim($postcode), '0') ?: '0';
        return self::$postcodeZoneCache[$carrier][$key] ?? null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPS
    // ─────────────────────────────────────────────────────────────────────────

    private function getUPSRates(string $countryCode, float $chargeable, string $shipmentType): array
    {
        $zoneKey = self::UPS_COUNTRY_COLUMN[$countryCode] ?? 'zone7';
        $slabW   = ceil($chargeable * 2) / 2;
        $rates   = [];

        // Standard saver
        if ($slabW <= 20) {
            $wKey    = number_format($slabW, 1, '.', '');
            $subType = ($shipmentType === 'Document' && $slabW <= 0.5) ? 'document' : 'non_document';
            $entry   = self::$dbRates['UPS'][$subType][$zoneKey][$wKey]
                    ?? self::$dbRates['UPS']['non_document'][$zoneKey][$wKey]
                    ?? null;
        } else {
            $tier  = $this->upsPerKgTier($slabW);
            $entry = self::$dbRates['UPS']['__any__'][$zoneKey][$tier] ?? null;
        }

        if ($entry) {
            $rate    = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
            $rates[] = [
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

        // Duty Free (USA / PR, Non-Document, ≤ 20 kg)
        if ($shipmentType === 'Non-Document' && in_array($countryCode, ['US', 'PR']) && $slabW <= 20) {
            $wKey  = number_format(min($slabW, 20), 1, '.', '');
            $entry = self::$dbRates['UPS']['duty_free']['usa'][$wKey] ?? null;
            if ($entry) {
                $rates[] = [
                    'carrier'            => 'UPS',
                    'network'            => 'UPS',
                    'service_code'       => 'UPS_DUTY_FREE',
                    'platform'           => 'overseas',
                    'requires_otp'       => false,
                    'shipment_type'      => 'Non-Document',
                    'chargeable_weight'  => $chargeable,
                    'rate'               => $entry['rate'],
                    'currency'           => 'INR',
                    'service_name'       => 'UPS Duty Free (C2C USA)',
                    'estimated_delivery' => '3-5 business days',
                ];
            }
        }

        return $rates;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FedEx
    // ─────────────────────────────────────────────────────────────────────────

    private function getFedExRates(string $countryCode, float $chargeable, string $shipmentType): array
    {
        $zone  = self::FEDEX_COUNTRY_ZONE[$countryCode] ?? 'j';
        $wKey  = number_format(min(ceil($chargeable * 2) / 2, 70.5), 1, '.', '');
        $rates = [];

        // Envelope — Documents ≤ 0.5 kg
        if ($shipmentType === 'Document' && $chargeable <= 0.5) {
            $entry = self::$dbRates['FEDEX']['envelope'][$zone]['0.5'] ?? null;
            if ($entry) {
                $rates[] = $this->fedexResult('FEDEX_ENVELOPE', 'FedEx International Priority (Envelope)',
                    $entry['rate'], $chargeable, $zone, $shipmentType);
            }
        }

        // Pak — Documents ≤ 2.5 kg
        if ($shipmentType === 'Document' && $chargeable <= 2.5) {
            $pakKey = number_format(min(ceil($chargeable * 2) / 2, 2.5), 1, '.', '');
            $entry  = self::$dbRates['FEDEX']['pak'][$zone][$pakKey] ?? null;
            if ($entry) {
                $rates[] = $this->fedexResult('FEDEX_PAK', 'FedEx International Priority (Pak)',
                    $entry['rate'], $chargeable, $zone, $shipmentType);
            }
        }

        // Package — all Non-Document, and Document > 2.5 kg
        $entry = self::$dbRates['FEDEX']['package'][$zone][$wKey] ?? null;
        if ($entry) {
            $rates[] = $this->fedexResult('FEDEX_IP', 'FedEx International Priority',
                $entry['rate'], $chargeable, $zone, $shipmentType);
        }

        return $rates;
    }

    private function fedexResult(string $code, string $name, int $rate, float $chargeable, string $zone, string $shipmentType): array
    {
        return [
            'carrier'            => 'FedEx',
            'network'            => 'FEDEX',
            'service_code'       => $code,
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => $shipmentType,
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => $name,
            'estimated_delivery' => $this->fedexETA($zone),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DHL
    // ─────────────────────────────────────────────────────────────────────────

    private function getDHLRates(string $countryCode, float $chargeable, string $shipmentType): array
    {
        $zone  = self::DHL_COUNTRY_ZONE[$countryCode] ?? 'zone11';
        $slabW = ceil($chargeable * 2) / 2;

        if ($slabW <= 30) {
            $wKey  = number_format($slabW, 1, '.', '');
            $sub   = $shipmentType === 'Document' ? 'document' : 'non_document';
            $entry = self::$dbRates['DHL'][$sub][$zone][$wKey]
                  ?? self::$dbRates['DHL']['non_document'][$zone][$wKey]
                  ?? null;
        } else {
            $tier  = $this->dhlPerKgTier($slabW);
            $entry = self::$dbRates['DHL']['__any__'][$zone][$tier] ?? null;
        }

        if (! $entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];

        return [[
            'carrier'            => 'DHL',
            'network'            => 'DHL',
            'service_code'       => 'DHL_EXPRESS',
            'platform'           => 'overseas',
            'requires_otp'       => true,
            'shipment_type'      => $shipmentType,
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'DHL Express',
            'estimated_delivery' => $this->dhlETA($zone),
        ]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ARAMEX
    // ─────────────────────────────────────────────────────────────────────────

    private function getAramexRates(string $countryCode, float $chargeable, string $shipmentType): array
    {
        $zoneServices = self::ARAMEX_COUNTRY_ZONES[$countryCode] ?? [];
        if (empty($zoneServices)) return [];

        $rates = [];
        $slabW = ceil($chargeable * 2) / 2;
        $isDoc = $shipmentType === 'Document';

        foreach ($zoneServices as ['zone' => $zoneKey, 'name' => $serviceName]) {
            if ($slabW <= 0.5) {
                $sub   = $isDoc ? 'document' : 'non_document';
                $entry = self::$dbRates['ARAMEX'][$sub][$zoneKey]['0.5']
                      ?? self::$dbRates['ARAMEX']['__any__'][$zoneKey]['0.5']
                      ?? null;
            } else {
                $entry = $this->lookupSelfRate('ARAMEX', $zoneKey, $chargeable);
            }

            if (!$entry) continue;

            $rate    = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
            $rates[] = [
                'carrier'            => 'Aramex',
                'network'            => 'ARAMEX',
                'service_code'       => 'ARAMEX_' . strtoupper($zoneKey),
                'platform'           => 'overseas',
                'requires_otp'       => false,
                'shipment_type'      => $shipmentType,
                'chargeable_weight'  => $chargeable,
                'rate'               => $rate,
                'currency'           => 'INR',
                'service_name'       => $serviceName,
                'estimated_delivery' => '3-5 business days',
            ];
        }

        return $rates;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SELF carriers
    // ─────────────────────────────────────────────────────────────────────────

    private function getCanadaSelfRates(string $countryCode, float $chargeable, ?string $postcode): array
    {
        if ($countryCode !== 'CA') return [];

        // Derive zone from FSA first letter (Canadian postal code format: A1A 1A1)
        $firstLetter = strtoupper(substr(trim((string)$postcode), 0, 1));
        $zoneKey     = self::CANADA_FSA_ZONE[$firstLetter] ?? 'zone1';

        $entry = $this->lookupSelfRate('CANADA_SELF', $zoneKey, $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'AeroTrek Canada',
            'network'            => 'CANADA_SELF',
            'service_code'       => 'CANADA_SELF',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'AeroTrek Canada Express',
            'estimated_delivery' => '7-10 business days',
        ]];
    }

    private function getEuSelfRates(string $countryCode, float $chargeable): array
    {
        $zoneKey = self::EU_SELF_COUNTRY_ZONE[$countryCode] ?? null;
        if (!$zoneKey) return [];

        $entry = $this->lookupSelfRate('EU_SELF', $zoneKey, $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'AeroTrek EU',
            'network'            => 'EU_SELF',
            'service_code'       => 'EU_SELF_' . strtoupper($zoneKey),
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'AeroTrek EU Express (via London)',
            'estimated_delivery' => '5-7 business days',
        ]];
    }

    private function getUkSelfRates(string $countryCode, float $chargeable): array
    {
        if ($countryCode !== 'GB') return [];

        $entry = $this->lookupSelfRate('UK_SELF', 'uk', $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'AeroTrek UK',
            'network'            => 'UK_SELF',
            'service_code'       => 'UK_SELF',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'AeroTrek UK Express',
            'estimated_delivery' => '5-7 business days',
        ]];
    }

    private function getAuSelfRates(string $countryCode, float $chargeable, ?string $postcode): array
    {
        if ($countryCode !== 'AU') return [];

        $rates = [];

        $auZone = $this->lookupPostcodeZone('AU_SELF', $postcode) ?? 'zone1';
        $entry  = $this->lookupSelfRate('AU_SELF', $auZone, $chargeable);
        if ($entry) {
            $rate    = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
            $rates[] = [
                'carrier'            => 'AeroTrek Australia',
                'network'            => 'AU_SELF',
                'service_code'       => 'AU_SELF',
                'platform'           => 'overseas',
                'requires_otp'       => false,
                'shipment_type'      => 'Non-Document',
                'chargeable_weight'  => $chargeable,
                'rate'               => $rate,
                'currency'           => 'INR',
                'service_name'       => 'AeroTrek Australia Express',
                'estimated_delivery' => '5-8 business days',
            ];
        }

        $dpexZone = $this->lookupPostcodeZone('AU_DPEX_SELF', $postcode) ?? 'zone1';
        $entry    = $this->lookupSelfRate('AU_DPEX_SELF', $dpexZone, $chargeable);
        if ($entry) {
            $rate    = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
            $rates[] = [
                'carrier'            => 'DPEX Australia',
                'network'            => 'AU_DPEX_SELF',
                'service_code'       => 'AU_DPEX_SELF',
                'platform'           => 'overseas',
                'requires_otp'       => false,
                'shipment_type'      => 'Non-Document',
                'chargeable_weight'  => $chargeable,
                'rate'               => $rate,
                'currency'           => 'INR',
                'service_name'       => 'DPEX Australia Express',
                'estimated_delivery' => '5-8 business days',
            ];
        }

        return $rates;
    }

    private function getNzSelfRates(string $countryCode, float $chargeable, ?string $postcode): array
    {
        if ($countryCode !== 'NZ') return [];

        $zoneKey = $this->lookupPostcodeZone('NZ_SELF', $postcode) ?? 'zone1';
        $entry   = $this->lookupSelfRate('NZ_SELF', $zoneKey, $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'AeroTrek New Zealand',
            'network'            => 'NZ_SELF',
            'service_code'       => 'NZ_SELF',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'AeroTrek New Zealand Express',
            'estimated_delivery' => '7-10 business days',
        ]];
    }

    private function getUaeSelfRates(string $countryCode, float $chargeable): array
    {
        if ($countryCode !== 'AE') return [];

        $entry = $this->lookupSelfRate('UAE_SELF', 'uae', $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'AeroTrek UAE',
            'network'            => 'UAE_SELF',
            'service_code'       => 'UAE_SELF',
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'AeroTrek UAE Express',
            'estimated_delivery' => '3-5 business days',
        ]];
    }

    private function getDpexSelfRates(string $countryCode, float $chargeable): array
    {
        $zoneKey = self::DPEX_SELF_COUNTRY_ZONE[$countryCode] ?? null;
        if (!$zoneKey) return [];

        $entry = $this->lookupSelfRate('DPEX_SELF', $zoneKey, $chargeable);
        if (!$entry) return [];

        $rate = $entry['is_per_kg'] ? (int)round($entry['rate'] * $chargeable) : $entry['rate'];
        return [[
            'carrier'            => 'DPEX',
            'network'            => 'DPEX_SELF',
            'service_code'       => 'DPEX_' . $countryCode,
            'platform'           => 'overseas',
            'requires_otp'       => false,
            'shipment_type'      => 'Non-Document',
            'chargeable_weight'  => $chargeable,
            'rate'               => $rate,
            'currency'           => 'INR',
            'service_name'       => 'DPEX Express',
            'estimated_delivery' => '3-5 business days',
        ]];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generic SELF rate lookup
    // Finds the smallest numeric slab >= chargeable weight; falls back to
    // the largest per-kg band whose threshold is <= chargeable weight.
    // ─────────────────────────────────────────────────────────────────────────

    private function lookupSelfRate(string $carrier, string $zoneKey, float $chargeable): ?array
    {
        $pool = self::$dbRates[$carrier]['__any__'][$zoneKey] ?? [];
        if (empty($pool)) return null;

        $slabW   = ceil($chargeable * 2) / 2;
        $bestKey = null;
        $bestKg  = PHP_FLOAT_MAX;

        // Find smallest numeric slab >= slabW
        foreach ($pool as $key => $val) {
            if (!str_ends_with((string)$key, '+') && (float)$key >= $slabW && (float)$key < $bestKg) {
                $bestKey = $key;
                $bestKg  = (float)$key;
            }
        }
        if ($bestKey !== null) return $pool[$bestKey];

        // Fall back to largest per-kg band whose threshold is <= slabW
        $bestKey = null;
        $bestKg  = 0.0;
        foreach ($pool as $key => $val) {
            if (str_ends_with((string)$key, '+')) {
                $kg = (float)rtrim((string)$key, '+');
                if ($kg <= $slabW && $kg > $bestKg) {
                    $bestKey = $key;
                    $bestKg  = $kg;
                }
            }
        }

        return $bestKey !== null ? $pool[$bestKey] : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Shiprocket
    // ─────────────────────────────────────────────────────────────────────────

    private function getShiprocketRates(string $countryCode, float $chargeable, ?int $uploadId): array
    {
        $srWeight    = max(0.05, ceil($chargeable * 20) / 20);
        // Inner subquery has no table alias — use bare column name
        $innerFilter = $uploadId ? "AND upload_id = {$uploadId}"    : "AND upload_id IS NULL";
        // Outer query aliases the table as sr
        $outerFilter = $uploadId ? "AND sr.upload_id = {$uploadId}" : "AND sr.upload_id IS NULL";

        $rows = DB::select(
            "SELECT sr.service, MIN(sr.rate) AS rate, sr.courier_company_id
             FROM shiprocket_rates sr
             INNER JOIN (
                 SELECT service, MIN(weight) AS min_w
                 FROM shiprocket_rates
                 WHERE country_code = ? AND weight >= ? {$innerFilter}
                 GROUP BY service
             ) best ON sr.service = best.service
                    AND sr.weight  = best.min_w
                    AND sr.country_code = ? {$outerFilter}
             GROUP BY sr.service, sr.courier_company_id
             ORDER BY rate ASC",
            [$countryCode, $srWeight, $countryCode]
        );

        return array_map(fn($row) => [
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
            'courier_company_id' => $row->courier_company_id ? (int)$row->courier_company_id : null,
        ], $rows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getVolumetricWeight(?float $l, ?float $b, ?float $h): ?float
    {
        return ($l && $b && $h) ? round(($l * $b * $h) / 5000, 2) : null;
    }

    private function upsPerKgTier(float $weight): string
    {
        return match(true) {
            $weight >= 1000 => '1000+',
            $weight >= 500  => '500+',
            $weight >= 300  => '300+',
            $weight >= 100  => '100+',
            $weight >= 70   => '70+',
            $weight >= 50   => '50+',
            $weight >= 30   => '30+',
            default         => '20+',
        };
    }

    private function dhlPerKgTier(float $weight): string
    {
        return match(true) {
            $weight >= 500 => '500+',
            $weight >= 300 => '300+',
            $weight >= 100 => '100+',
            $weight >= 70  => '70+',
            $weight >= 50  => '50+',
            default        => '30+',
        };
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

    private function dhlETA(string $zone): string
    {
        return match($zone) {
            'zone1', 'zone2', 'zone3', 'zone4' => '2-3 business days',
            'zone5', 'zone6'                   => '3-4 business days',
            'zone7', 'zone9', 'zone12'         => '3-5 business days',
            default                            => '4-7 business days',
        };
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
            default                              => '7-14 business days',
        };
    }
}
