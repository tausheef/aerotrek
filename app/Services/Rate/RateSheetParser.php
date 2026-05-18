<?php

namespace App\Services\Rate;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Parses the weekly "RATES TEMPLATE.xlsx" file and converts it into
 * flat arrays ready for bulk-insert into carrier_rates / shiprocket_rates.
 *
 * Sheet coverage:
 *   UPS SAVER           → carrier=UPS, sub_type=document|non_document|null(per-kg)
 *   UPS DUTY FREE       → carrier=UPS, sub_type=duty_free  (USA only)
 *   FEDEX               → carrier=FEDEX, sub_type=envelope|pak|package
 *   DHL                 → carrier=DHL, sub_type=document|non_document|null(per-kg)
 *   ARAMEX              → carrier=ARAMEX, sub_type=document|non_document|null
 *   Canada SELF         → carrier=CANADA_SELF, zone1-zone8
 *   EU via LHR SELF     → carrier=EU_SELF, zone1-zone7
 *   UK SELF             → carrier=UK_SELF, zone_key=uk
 *   Australia SELF      → carrier=AU_SELF, zone1-zone3
 *   Australia DPEX SELF → carrier=AU_DPEX_SELF, zone1-zone5
 *   New Zealand SELF    → carrier=NZ_SELF, zone1-zone4
 *   UAE SELF            → carrier=UAE_SELF, zone_key=uae
 *   DPEX SELF           → carrier=DPEX_SELF, sg_direct|my_kl|my_rest|my_langawi|my_sabah|tw_direct|vn_direct
 *   SHIPROCKET          → shiprocket_rates (streamed in 500-row chunks via generator)
 */
class RateSheetParser
{
    private Spreadsheet $spreadsheet;
    private int $uploadId;

    // ── UPS: Excel column header → zone_key (matches UPS_COUNTRY_COLUMN) ─────
    private const UPS_HEADER_ZONE = [
        'ZONE 7'        => 'zone7',
        'ZONE 8'        => 'zone8',
        'ZONE 9'        => 'zone9',
        'USA'           => 'usa',
        'Canada'        => 'canada',
        'Mexico'        => 'mexico',
        'Germany'       => 'germany',
        'Belgium'       => 'belgium',
        'UK'            => 'uk',
        'Denmark'       => 'denmark',
        'Austria'       => 'austria',
        'Norway'        => 'norway',
        'Japan'         => 'japan',
        'Hong Kong'     => 'hongkong',
        'Australia'     => 'australia',
        'New Zealand'   => 'nz',
        'ZONE 1'        => 'zone1',
        'ZONE 2'        => 'zone2',
        'ZONE 3'        => 'zone3',
        'ZONE 4'        => 'zone4',
        'ZONE 6'        => 'zone6',
        'ZONE 10'       => 'zone10',
        'ZONE 11'       => 'zone11',
        'ZONE 12'       => 'zone12',
        'Israel'        => 'israel',
        'zone-13'       => 'zone13',
        'Egypt'         => 'egypt',
        'FRENCH GUINEA' => 'frenchguinea',
        'UAE'           => 'uae',
        'China'         => 'china',
        'zone-14'       => 'zone14',
        'zone-15'       => 'zone15',
    ];

    // ── FedEx: Excel column header → zone_key (matches FEDEX_COUNTRY_ZONE) ──
    private const FEDEX_HEADER_ZONE = [
        'Zone A' => 'a', 'Zone B' => 'b', 'Zone C' => 'c', 'Zone D' => 'd',
        'Zone E' => 'e', 'Zone F' => 'f', 'Zone G' => 'g', 'Zone H' => 'h',
        'Zone I' => 'i', 'Zone J' => 'j', 'Zone K' => 'k', 'Zone L' => 'l',
        'Zone M' => 'm', 'Zone N' => 'n', 'Zone O' => 'o', 'Zone P' => 'p',
        'Zone Q' => 'q', 'ISRAEL' => 'israel',
    ];

    // ── DHL: Excel column header → zone_key ──────────────────────────────────
    private const DHL_HEADER_ZONE = [
        'Zone 1'  => 'zone1',  'Zone 2'  => 'zone2',  'Zone 3'  => 'zone3',
        'Zone 4'  => 'zone4',  'Zone 5'  => 'zone5',  'Zone 6'  => 'zone6',
        'Zone 7'  => 'zone7',  'Zone 8'  => 'zone8',  'Zone 9'  => 'zone9',
        'Zone 10' => 'zone10', 'Zone 11' => 'zone11', 'Zone 12' => 'zone12',
        'Zone 13' => 'zone13', 'Zone 14' => 'zone14',
    ];

    // ── ARAMEX: Excel column header → zone_key ───────────────────────────────
    private const ARAMEX_HEADER_ZONE = [
        'UAE-PPX'                    => 'uae_ppx',
        'UAE-DPX'                    => 'uae_dpx',
        'Australia--DPX--Metro'      => 'au_dpx_metro',
        'Australia--DPX--Main'       => 'au_dpx_main',
        'Australia--DPX--Interstate' => 'au_dpx_interstate',
        'Australia--DPX--Remote'     => 'au_dpx_remote',
        'Australia--PPX--Metro'      => 'au_ppx_metro',
        'Australia--PPX--Main'       => 'au_ppx_main',
        'Australia--PPX--Interstate' => 'au_ppx_interstate',
        'Australia--PPX--Remote'     => 'au_ppx_remote',
        'Bahrain,  Kuwait--  DPX'    => 'bh_kw_dpx',
        'Bahrain, Kuwait-PPX'        => 'bh_kw_ppx',
        'Oman--GPX'                  => 'oman_gpx',
        'Oman-PPX'                   => 'oman_ppx',
        'Qatar-PPX'                  => 'qatar_ppx',
        'Saudi Arabia--DPX'          => 'sa_dpx',
        'Saudi Arabia -PPX'          => 'sa_ppx',
    ];

    // ── EU via LHR SELF: Excel column header → zone_key ─────────────────────
    private const EU_HEADER_ZONE = [
        'Zone -1' => 'zone1', 'Zone -2' => 'zone2', 'Zone -3' => 'zone3',
        'Zone -4' => 'zone4', 'Zone -5' => 'zone5', 'Zone -6' => 'zone6',
        'Zone -7' => 'zone7',
    ];

    // ── DPEX SELF: column index (0-based) → zone_key ────────────────────────
    // Row 2 = main header (Singapore-Self, MALAYSIA-DPEX, Taiwan-DPEX, VIETNAM-DPEX)
    // Row 3 = sub-header  (Direct, Kualalampur, Rest of Malaysia, Langawi, Sabah & Sarawak)
    private const DPEX_ZONE_MAP = [
        1 => 'sg_direct',
        2 => 'my_kl',
        3 => 'my_rest',
        4 => 'my_langawi',
        5 => 'my_sabah',
        6 => 'tw_direct',
        7 => 'vn_direct',
    ];

    public function __construct(string $filePath, int $uploadId)
    {
        ini_set('memory_limit', '512M');
        $this->spreadsheet = IOFactory::load($filePath);
        $this->uploadId    = $uploadId;
    }

    // ── Total rows to process (used for progress bar) ─────────────────────────

    public function getTotalRows(): int
    {
        $sheet = $this->spreadsheet->getSheetByName('SHIPROCKET');
        // Shiprocket dominates; 1500 covers all carrier + SELF sheets combined
        return max(0, $sheet->getHighestRow() - 7) + 1500;
    }

    // ── Carrier rates (UPS + FedEx + DHL) — returned as one flat array ───────

    public function parseCarrierRates(): array
    {
        return array_merge(
            $this->parseUPSSaver(),
            $this->parseUPSDutyFree(),
            $this->parseFedEx(),
            $this->parseDHL(),
            $this->parseAramex(),
            $this->parseCanadaSelf(),
            $this->parseEuSelf(),
            $this->parseUkSelf(),
            $this->parseAustraliaSelf(),
            $this->parseAustraliaDpexSelf(),
            $this->parseNewZealandSelf(),
            $this->parseUaeSelf(),
            $this->parseDpexSelf(),
        );
    }

    // ── Postcode → zone_key mappings (AU_SELF, AU_DPEX_SELF, NZ_SELF) ──────────
    // Returns flat array ready for bulk-insert into postcode_zones.
    // Postcodes are stored without leading zeros so lookup is consistent
    // regardless of whether the user types "0505" or "505".

    public function parsePostcodeZones(): array
    {
        return array_merge(
            $this->parseAuSelfZones(),
            $this->parseAuDpexSelfZones(),
            $this->parseNzSelfZones(),
        );
    }

    private function parseAuSelfZones(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('Australia SELF');
        $zones = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells     = $this->rowCells($row);
            $postcode  = trim((string)($cells[6] ?? ''));  // col G
            $zoneLabel = trim((string)($cells[7] ?? ''));  // col H

            if (!is_numeric($postcode) || (int)$postcode <= 0) continue;
            if (!preg_match('/zone\s*(\d+)/i', $zoneLabel, $m)) continue;

            $zones[] = [
                'upload_id' => $this->uploadId,
                'carrier'   => 'AU_SELF',
                'postcode'  => ltrim($postcode, '0') ?: '0',
                'zone_key'  => 'zone' . $m[1],
            ];
        }

        return $zones;
    }

    private function parseAuDpexSelfZones(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('Australia DPEX SELF');
        $zones = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells     = $this->rowCells($row);
            $postcode  = trim((string)($cells[8] ?? ''));  // col I
            $zoneLabel = trim((string)($cells[9] ?? ''));  // col J

            if (!is_numeric($postcode) || (int)$postcode <= 0) continue;
            if (!preg_match('/zone\s*(\d+)/i', $zoneLabel, $m)) continue;

            $zones[] = [
                'upload_id' => $this->uploadId,
                'carrier'   => 'AU_DPEX_SELF',
                'postcode'  => ltrim($postcode, '0') ?: '0',
                'zone_key'  => 'zone' . $m[1],
            ];
        }

        return $zones;
    }

    private function parseNzSelfZones(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('New Zealand SELF');
        $rows  = $sheet->toArray(null, true, false, false);
        $zones = [];

        // Each row has 4 zone columns side by side:
        //   col J (idx 9)  = Zone 1 postcode
        //   col M (idx 12) = Zone 2 postcode
        //   col P (idx 15) = Zone 3 postcode
        //   col S (idx 18) = Zone 4 postcode
        $zoneCodeCols = [9 => 'zone1', 12 => 'zone2', 15 => 'zone3', 18 => 'zone4'];

        foreach ($rows as $row) {
            foreach ($zoneCodeCols as $colIdx => $zoneKey) {
                $raw = trim((string)($row[$colIdx] ?? ''));
                if (!is_numeric($raw) || (int)$raw <= 0) continue;
                // NZ postcodes are 4-digit but stored without leading zeros in Excel
                $postcode = ltrim($raw, '0') ?: '0';
                $zones[] = [
                    'upload_id' => $this->uploadId,
                    'carrier'   => 'NZ_SELF',
                    'postcode'  => $postcode,
                    'zone_key'  => $zoneKey,
                ];
            }
        }

        return $zones;
    }

    // ── Shiprocket — yielded in 500-row chunks to keep memory low ─────────────

    public function parseShiprocketRates(): \Generator
    {
        $sheet = $this->spreadsheet->getSheetByName('SHIPROCKET');
        // Header is row 7 (1-indexed). Data starts at row 8.
        $highestRow = $sheet->getHighestRow();
        $batch = [];

        for ($r = 8; $r <= $highestRow; $r++) {
            $countryCode      = trim((string)$sheet->getCell("A{$r}")->getCalculatedValue());
            $weight           = $sheet->getCell("C{$r}")->getCalculatedValue();
            $service          = trim((string)$sheet->getCell("D{$r}")->getCalculatedValue());
            $rate             = $sheet->getCell("E{$r}")->getCalculatedValue();
            $courierCompanyId = $sheet->getCell("F{$r}")->getCalculatedValue();

            if ($countryCode === '' || $weight === null || !is_numeric($weight)) {
                continue;
            }

            $batch[] = [
                'upload_id'          => $this->uploadId,
                'country_code'       => $countryCode,
                'weight'             => (float)$weight,
                'service'            => $service,
                'rate'               => (int)round((float)$rate),
                'courier_company_id' => is_numeric($courierCompanyId) ? (int)$courierCompanyId : null,
            ];

            if (count($batch) >= 500) {
                yield $batch;
                $batch = [];
            }
        }

        if (!empty($batch)) {
            yield $batch;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UPS SAVER
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseUPSSaver(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('UPS SAVER');
        $rows  = $sheet->toArray(null, true, false, false);

        // Row index 3 (0-based) contains zone headers
        $zoneMap = $this->buildZoneMap($rows[3], self::UPS_HEADER_ZONE);

        $rates      = [];
        $seenHalf   = false; // first 0.5 = document, second = non_document

        for ($i = 8; $i < count($rows); $i++) {
            $row         = $rows[$i];
            $rawLabel    = trim((string)($row[0] ?? ''));

            if ($rawLabel === '' || $rawLabel === 'nan') continue;

            $isPerKg    = str_ends_with($rawLabel, '+');
            $weightKey  = $rawLabel;

            // Determine sub_type
            if ($isPerKg) {
                $subType = null;
            } elseif ($rawLabel === '0.5' && !$seenHalf) {
                $subType  = 'document';
                $seenHalf = true;
            } elseif ($rawLabel === '0.5' && $seenHalf) {
                $subType  = 'non_document';
            } else {
                $subType  = 'non_document';
            }

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $row[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;

                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'UPS',
                    'sub_type'   => $subType,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $weightKey,
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $isPerKg ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UPS DUTY FREE  (USA only, data in cols 3+4 and cols 6+7)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseUPSDutyFree(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('UPS DUTY FREE');
        $rows  = $sheet->toArray(null, true, false, false);

        $rates = [];

        // Left block: col3 = weight, col4 = rate (rows 10-30, 0-indexed)
        // Right block: col6 = weight, col7 = rate (rows 10-29)
        $blocks = [[3, 4], [6, 7]];

        foreach ($rows as $row) {
            foreach ($blocks as [$wCol, $rCol]) {
                $wLabel = trim((string)($row[$wCol] ?? ''));
                $rVal   = $row[$rCol] ?? null;

                if ($wLabel === '' || !is_numeric($wLabel)) continue;
                if ($rVal === null || !is_numeric($rVal) || (float)$rVal <= 0) continue;

                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'UPS',
                    'sub_type'   => 'duty_free',
                    'zone_key'   => 'usa',
                    'weight_key' => number_format((float)$wLabel, 1, '.', ''),
                    'rate'       => (int)round((float)$rVal),
                    'is_per_kg'  => 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // FEDEX  (sections: Envelope / Pak / Package, detected by row labels)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseFedEx(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('FEDEX');
        $rows    = $sheet->toArray(null, true, false, false);
        $rates   = [];
        $subType = null;
        $zoneMap = [];

        for ($i = 0; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            $clean    = strtolower(preg_replace('/\s+/', '', $rawLabel)); // normalise

            // Section headers reset the subType and rebuild zoneMap from same row
            if (str_starts_with($clean, 'envelope')) {
                $subType = 'envelope';
                $zoneMap = $this->buildZoneMap($row, self::FEDEX_HEADER_ZONE);
                continue;
            }
            if (str_starts_with($clean, 'pak')) {
                $subType = 'pak';
                $zoneMap = $this->buildZoneMap($row, self::FEDEX_HEADER_ZONE);
                continue;
            }
            if (str_starts_with($clean, 'package')) {
                $subType = 'package';
                $zoneMap = $this->buildZoneMap($row, self::FEDEX_HEADER_ZONE);
                continue;
            }

            if ($subType === null || !is_numeric($rawLabel)) continue;
            if ((float)$rawLabel <= 0) continue;

            $weightKey = number_format((float)$rawLabel, 1, '.', '');

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $row[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;

                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'FEDEX',
                    'sub_type'   => $subType,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $weightKey,
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DHL  (sections: Document / Non-Documents, then per-kg tiers)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseDHL(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('DHL');
        $rows    = $sheet->toArray(null, true, false, false);
        $rates   = [];
        $subType = null;
        $zoneMap = [];

        for ($i = 0; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            $clean    = strtolower(preg_replace('/\s+/', '', $rawLabel));

            // Section header rows
            if (str_starts_with($clean, 'document')) {
                $subType = 'document';
                $zoneMap = $this->buildZoneMap($row, self::DHL_HEADER_ZONE);
                continue;
            }
            if (str_starts_with($clean, 'non-document') || str_starts_with($clean, 'nondocument')) {
                $subType = 'non_document';
                $zoneMap = $this->buildZoneMap($row, self::DHL_HEADER_ZONE);
                continue;
            }
            if ($rawLabel === 'From') {
                // separator row before per-kg tiers — keep current subType
                continue;
            }

            if ($subType === null) continue;

            $isPerKg   = str_ends_with($rawLabel, '+');
            $isNumeric = is_numeric($rawLabel);

            if (!$isNumeric && !$isPerKg) continue;
            if ($isNumeric && (float)$rawLabel <= 0) continue;

            $weightKey = $isPerKg ? $rawLabel : number_format((float)$rawLabel, 1, '.', '');

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $row[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;

                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'DHL',
                    'sub_type'   => $isPerKg ? null : $subType,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $weightKey,
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $isPerKg ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ARAMEX  (destinations as columns; Dox/SPX 500g + numeric slabs + per-kg bands)
    // Row 4 (1-indexed) = zone header; Australia lookup starts at "Metro" — stop there.
    // Uses row iterator to avoid toArray() OOM on 17 k-row sheet.
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseAramex(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('ARAMEX');
        $rates   = [];
        $zoneMap = [];

        foreach ($sheet->getRowIterator() as $rowIdx => $row) {
            $cells    = $this->rowCells($row);
            $rawLabel = trim((string)($cells[0] ?? ''));

            if ($rowIdx === 4) {
                // Row 4 = header ("Country-->", "UAE-PPX", ...)
                $zoneMap = $this->buildZoneMap($cells, self::ARAMEX_HEADER_ZONE);
                continue;
            }

            if ($rowIdx < 5 || $rawLabel === '') continue;

            // Australia suburb lookup starts here — stop
            if (in_array($rawLabel, ['Metro', 'Main', 'Interstate', 'Remote'])) break;

            if (str_starts_with($rawLabel, 'Dox')) {
                $subType = 'document'; $weightKey = '0.5'; $isPerKg = false;
            } elseif (str_starts_with($rawLabel, 'SPX')) {
                $subType = 'non_document'; $weightKey = '0.5'; $isPerKg = false;
            } else {
                $parsed = $this->parseSelfWeightLabel($rawLabel);
                if ($parsed === null) continue;
                $subType = null; $weightKey = $parsed['weight_key']; $isPerKg = $parsed['is_per_kg'];
            }

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $cells[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'ARAMEX',
                    'sub_type'   => $subType,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $weightKey,
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $isPerKg ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Canada SELF  (Zone 1-8; one YYZ rate table; rows 15-90; postal lookup after)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseCanadaSelf(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('Canada SELF');
        $rates   = [];
        $zoneMap = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells    = $this->rowCells($row);
            $rawLabel = trim((string)($cells[0] ?? ''));

            if (strtolower($rawLabel) === 'weight') {
                $zoneMap = $this->buildSelfZoneMap($cells);
                continue;
            }

            if (empty($zoneMap) || $rawLabel === '') continue;

            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $cells[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'CANADA_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // EU via LHR SELF  (Zone -1 to -7; all per-kg rates; 64 rows total)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseEuSelf(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('EU via LHR SELF');
        $rows  = $sheet->toArray(null, true, false, false);

        // Row index 9 (0-based) = header ("Country", "Zone -1", ..., "Zone -7")
        $zoneMap = $this->buildZoneMap($rows[9], self::EU_HEADER_ZONE);
        $rates   = [];

        for ($i = 10; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            if ($rawLabel === '') continue;
            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;
            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $row[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'EU_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UK SELF  (single zone "uk"; 186 rows total)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseUkSelf(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('UK SELF');
        $rows  = $sheet->toArray(null, true, false, false);

        // Row index 7 (0-based) = header ("Country", "UK")
        $ukCol = null;
        foreach ($rows[7] as $colIdx => $cell) {
            if (trim((string)$cell) === 'UK') { $ukCol = $colIdx; break; }
        }
        if ($ukCol === null) return [];

        $rates = [];
        for ($i = 8; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            if ($rawLabel === '') continue;
            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;
            $rate = $row[$ukCol] ?? null;
            if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
            $rates[] = [
                'upload_id'  => $this->uploadId,
                'carrier'    => 'UK_SELF',
                'sub_type'   => null,
                'zone_key'   => 'uk',
                'weight_key' => $parsed['weight_key'],
                'rate'       => (int)round((float)$rate),
                'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
            ];
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Australia SELF  (Zone 1-3; zip lookup on right side — ignored)
    // Uses row iterator to avoid toArray() OOM on 6 k-row sheet.
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseAustraliaSelf(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('Australia SELF');
        $rates   = [];
        $zoneMap = [];

        foreach ($sheet->getRowIterator() as $rowIdx => $row) {
            $cells    = $this->rowCells($row);
            $rawLabel = trim((string)($cells[0] ?? ''));

            if ($rowIdx === 4) {
                // Row 4 = header ("Weight", "Zone 1", "Zone 2", "Zone 3")
                $zoneMap = $this->buildSelfZoneMap($cells);
                continue;
            }

            if ($rowIdx < 5 || $rawLabel === '') continue;

            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $cells[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'AU_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Australia DPEX SELF  (Zone 1-5; uses row iterator for 13 k-row sheet)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseAustraliaDpexSelf(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('Australia DPEX SELF');
        $rates   = [];
        $zoneMap = [];

        foreach ($sheet->getRowIterator() as $rowIdx => $row) {
            $cells    = $this->rowCells($row);
            $rawLabel = trim((string)($cells[0] ?? ''));

            if ($rowIdx === 9) {
                // Row 9 = header ("Weight", "Zone 1", ..., "Zone 5")
                $zoneMap = $this->buildSelfZoneMap($cells);
                continue;
            }

            if ($rowIdx < 10 || $rawLabel === '') continue;

            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $cells[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'AU_DPEX_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // New Zealand SELF  (Zone 1-4; uses row iterator for 3.5 k-row sheet)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseNewZealandSelf(): array
    {
        $sheet   = $this->spreadsheet->getSheetByName('New Zealand SELF');
        $rates   = [];
        $zoneMap = [];

        foreach ($sheet->getRowIterator() as $rowIdx => $row) {
            $cells    = $this->rowCells($row);
            $rawLabel = trim((string)($cells[0] ?? ''));

            if ($rowIdx === 9) {
                // Row 9 = header ("Weight", "Zone 1", ..., "Zone 4")
                $zoneMap = $this->buildSelfZoneMap($cells);
                continue;
            }

            if ($rowIdx < 10 || $rawLabel === '') continue;

            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;

            foreach ($zoneMap as $colIdx => $zoneKey) {
                $rate = $cells[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'NZ_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // UAE SELF  (single zone "uae"; 31 rows total)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseUaeSelf(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('UAE SELF');
        $rows  = $sheet->toArray(null, true, false, false);

        // Row index 2 (0-based) = header ("Weight", "UAE")
        $rateCol = null;
        foreach ($rows[2] as $colIdx => $cell) {
            if (trim((string)$cell) === 'UAE') { $rateCol = $colIdx; break; }
        }
        if ($rateCol === null) return [];

        $rates = [];
        for ($i = 3; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            if ($rawLabel === '') continue;
            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;
            $rate = $row[$rateCol] ?? null;
            if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
            $rates[] = [
                'upload_id'  => $this->uploadId,
                'carrier'    => 'UAE_SELF',
                'sub_type'   => null,
                'zone_key'   => 'uae',
                'weight_key' => $parsed['weight_key'],
                'rate'       => (int)round((float)$rate),
                'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
            ];
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // DPEX SELF  (Singapore / Malaysia sub-zones / Taiwan / Vietnam; 45 rows)
    // Zone map is hardcoded; "NO SERVICE" values skipped via is_numeric check.
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseDpexSelf(): array
    {
        $sheet = $this->spreadsheet->getSheetByName('DPEX SELF');
        $rows  = $sheet->toArray(null, true, false, false);
        $rates = [];

        for ($i = 3; $i < count($rows); $i++) {
            $row      = $rows[$i];
            $rawLabel = trim((string)($row[0] ?? ''));
            if ($rawLabel === '') continue;
            $parsed = $this->parseSelfWeightLabel($rawLabel);
            if ($parsed === null) continue;
            foreach (self::DPEX_ZONE_MAP as $colIdx => $zoneKey) {
                $rate = $row[$colIdx] ?? null;
                if ($rate === null || !is_numeric($rate) || (float)$rate <= 0) continue;
                $rates[] = [
                    'upload_id'  => $this->uploadId,
                    'carrier'    => 'DPEX_SELF',
                    'sub_type'   => null,
                    'zone_key'   => $zoneKey,
                    'weight_key' => $parsed['weight_key'],
                    'rate'       => (int)round((float)$rate),
                    'is_per_kg'  => $parsed['is_per_kg'] ? 1 : 0,
                ];
            }
        }

        return $rates;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper — convert a Row object into a sparse 0-indexed array of cell values
    // Used by row-iterator parsers to avoid the OOM of toArray() on large sheets
    // ═══════════════════════════════════════════════════════════════════════════

    private function rowCells(\PhpOffice\PhpSpreadsheet\Worksheet\Row $row): array
    {
        $cells    = [];
        $cellIter = $row->getCellIterator();
        $cellIter->setIterateOnlyExistingCells(true);
        foreach ($cellIter as $cell) {
            $colIdx          = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($cell->getColumn()) - 1;
            $cells[$colIdx]  = $cell->getCalculatedValue();
        }
        return $cells;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper — parse SELF sheet weight labels into a normalised weight_key
    //
    // Handles patterns:
    //   "0.500 Kg"  → { weight_key: "0.5",  is_per_kg: false }
    //   "5.0 Kg"    → { weight_key: "5.0",  is_per_kg: false }
    //   "6 / Kg"    → { weight_key: "6.0",  is_per_kg: true  }
    //   "10+/ Kg"   → { weight_key: "10+",  is_per_kg: true  }
    //   "20+"       → { weight_key: "20+",  is_per_kg: true  }   (ARAMEX per-kg bands)
    // Returns null for non-weight strings (suburb names, postal codes, etc.)
    // ═══════════════════════════════════════════════════════════════════════════

    private function parseSelfWeightLabel(string $raw): ?array
    {
        $raw     = trim($raw);
        $isPerKg = str_contains($raw, '/');

        // Strip units: " Kg", "/ Kg", "/Kg", " Gm"
        $clean = preg_replace('/\s*\/?\s*(Kg|Gm)\s*/i', '', $raw);
        $clean = trim($clean);

        // Per-kg bands ending with "+": "20+", "10+", "15+ "
        if (str_ends_with($clean, '+')) {
            $numPart = rtrim($clean, '+ ');
            if (!is_numeric($numPart)) return null;
            return ['weight_key' => $numPart . '+', 'is_per_kg' => true];
        }

        if (!is_numeric($clean)) return null;

        $val = (float)$clean;
        // Reject zero, negatives, and anything that looks like a zip code (> 200 kg)
        if ($val <= 0 || $val > 200) return null;

        return [
            'weight_key' => number_format($val, 1, '.', ''),
            'is_per_kg'  => $isPerKg,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper — build colIndex → zoneKey map from a SELF sheet header row
    //
    // Maps "Zone 1" → "zone1", "Zone -3" → "zone3", "UK" → "uk", etc.
    // Skips "Weight" and "Country" columns.
    // ═══════════════════════════════════════════════════════════════════════════

    private function buildSelfZoneMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $colIdx => $cell) {
            if ($cell === null) continue;
            $label = trim((string)$cell);
            if ($label === '' || in_array(strtolower($label), ['weight', 'country'])) continue;

            if (preg_match('/Zone\s*-?\s*(\d+)/i', $label, $m)) {
                $map[$colIdx] = 'zone' . $m[1];
            } else {
                $key = strtolower(preg_replace('/[^a-z0-9]/i', '', $label));
                if ($key !== '') $map[$colIdx] = $key;
            }
        }
        return $map;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Helper — build colIndex → zoneKey map from a header row
    // ═══════════════════════════════════════════════════════════════════════════

    private function buildZoneMap(array $headerRow, array $headerZone): array
    {
        $map = [];
        foreach ($headerRow as $colIdx => $cell) {
            if ($cell === null) continue;
            $normalized = trim((string)$cell);
            if (isset($headerZone[$normalized])) {
                $map[$colIdx] = $headerZone[$normalized];
            }
        }
        return $map;
    }
}
