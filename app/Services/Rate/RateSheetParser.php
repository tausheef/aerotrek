<?php

namespace App\Services\Rate;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Parses the weekly "RATES TEMPLATE.xlsx" file and converts it into
 * flat arrays ready for bulk-insert into carrier_rates / shiprocket_rates.
 *
 * Sheet coverage:
 *   UPS SAVER      → carrier=UPS, sub_type=document|non_document|null(per-kg)
 *   UPS DUTY FREE  → carrier=UPS, sub_type=duty_free  (USA only)
 *   FEDEX          → carrier=FEDEX, sub_type=envelope|pak|package
 *   DHL            → carrier=DHL, sub_type=document|non_document|null(per-kg)
 *   SHIPROCKET     → shiprocket_rates (streamed in 500-row chunks via generator)
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
        // Shiprocket dominates; everything else is ~400 rows
        return max(0, $sheet->getHighestRow() - 7) + 400;
    }

    // ── Carrier rates (UPS + FedEx + DHL) — returned as one flat array ───────

    public function parseCarrierRates(): array
    {
        return array_merge(
            $this->parseUPSSaver(),
            $this->parseUPSDutyFree(),
            $this->parseFedEx(),
            $this->parseDHL(),
        );
    }

    // ── Shiprocket — yielded in 500-row chunks to keep memory low ─────────────

    public function parseShiprocketRates(): \Generator
    {
        $sheet = $this->spreadsheet->getSheetByName('SHIPROCKET');
        // Header is row 7 (1-indexed). Data starts at row 8.
        $highestRow = $sheet->getHighestRow();
        $batch = [];

        for ($r = 8; $r <= $highestRow; $r++) {
            $countryCode = trim((string)$sheet->getCell("A{$r}")->getCalculatedValue());
            $weight      = $sheet->getCell("C{$r}")->getCalculatedValue();
            $service     = trim((string)$sheet->getCell("D{$r}")->getCalculatedValue());
            $rate        = $sheet->getCell("E{$r}")->getCalculatedValue();

            if ($countryCode === '' || $weight === null || !is_numeric($weight)) {
                continue;
            }

            $batch[] = [
                'upload_id'    => $this->uploadId,
                'country_code' => $countryCode,
                'weight'       => (float)$weight,
                'service'      => $service,
                'rate'         => (int)round((float)$rate),
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
