<?php

namespace App\Services;

use App\Support\AssetReconciliationNormalizer as Normalizer;
use App\Support\VehicleAuditDate;
use App\Support\VehiclePlateNormalizer;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VehicleAssetAuditWorkbookParser
{
    public const DEFAULT_SHEET = 'Monitoring Asset';

    public const KIR_SHEET = 'KIR';

    /** @var array<string, array<int, string>> */
    private const HEADER_ALIASES = [
        'license_plate' => ['nomor polisi', 'no polisi', 'no. polisi', 'plat nomor'],
        'stnk_name' => ['nama stnk'],
        'shelf_marker' => ['shelf'],
        'accounting' => ['accounting'],
        'csa_marker' => ['csa'],
        'presence' => ['cek keberadaan unit', 'keberadaan'],
        'brand' => ['merk kendaraan', 'merk'],
        'vehicle_name' => ['nama kendaraan'],
        'vehicle_type' => ['jenis kendaraan'],
        'color' => ['warna'],
        'year' => ['tahun kendaraan'],
        'engine_chassis' => ['no mesin', 'no rangka'],
        'holder' => ['pemegang inventaris'],
        'bpkb_status' => ['status bpkb'],
        'bpkb_number' => ['no bpkb', 'nomor bpkb'],
        'stnk_expires_at' => ['expired stnk h-45', 'expired stnk', 'masa berlaku stnk', 'stnk expired'],
        'tax_expires_at' => ['expired pajak h-45', 'expired pajak', 'masa berlaku pajak'],
        'insurance_expires_at' => ['expired date h-45', 'expired date', 'masa berlaku asuransi'],
        'insurance_policy_number' => ['no.polis', 'no polis', 'nomor polis'],
        'insurance_name' => ['nama asuransi'],
        'insurance_type' => ['jenis asuransi'],
        'insurance_insured' => ['nama tertanggung'],
        'insurance_join_date' => ['join date'],
    ];

    /** Fields that must use exact/prefix-safe alias matching (avoid loose contains). */
    private const STRICT_HEADER_FIELDS = [
        'stnk_expires_at',
        'tax_expires_at',
        'insurance_expires_at',
        'insurance_policy_number',
        'insurance_name',
        'insurance_type',
        'insurance_insured',
        'insurance_join_date',
        'bpkb_number',
        'stnk_name',
        'vehicle_name',
        'accounting',
        'csa_marker',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path, string $sheetName = self::DEFAULT_SHEET): array
    {
        $kirSheetName = self::KIR_SHEET;
        if (function_exists('app') && app()->bound('config')) {
            $kirSheetName = (string) config('vehicle-asset-reconciliation.kir_sheet', self::KIR_SHEET);
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly(array_values(array_unique([$sheetName, $kirSheetName])));
        }

        $workbook = $reader->load($path);
        $sheet = $workbook->getSheetByName($sheetName);

        if ($sheet === null) {
            $workbook->disconnectWorksheets();
            throw new InvalidArgumentException("Sheet \"{$sheetName}\" tidak ditemukan.");
        }

        $rows = $this->sheetToRows($sheet);
        $kirByPlate = [];
        $kirSheet = $workbook->getSheetByName($kirSheetName);

        if ($kirSheet !== null) {
            $kirByPlate = $this->parseKirRows($this->sheetToRows($kirSheet));
        }

        $workbook->disconnectWorksheets();

        return $this->mergeKirDates($this->parseRows($rows), $kirByPlate);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parseRows(array $rows): array
    {
        $mapping = null;
        $parsed = [];

        foreach ($rows as $sourceRow => $row) {
            $headerMapping = $this->detectHeader($row);

            if ($headerMapping !== null) {
                $mapping = $headerMapping;

                continue;
            }

            if ($mapping === null) {
                continue;
            }

            $payload = $this->mapRow($row, $mapping);

            if (! $this->looksLikeDataRow($payload)) {
                continue;
            }

            $parsed[] = $this->normalizeDataRow((int) $sourceRow, $payload);
        }

        if ($parsed === []) {
            throw new InvalidArgumentException('Tidak ada baris kendaraan valid pada sheet Monitoring Asset.');
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<string, mixed>>  $parsed
     * @param  array<string, string>  $kirByPlate plate key => Y-m-d
     * @return array<int, array<string, mixed>>
     */
    public function mergeKirDates(array $parsed, array $kirByPlate): array
    {
        if ($kirByPlate === []) {
            return $parsed;
        }

        foreach ($parsed as $index => $row) {
            $key = VehiclePlateNormalizer::key($row['external_item_code'] ?? null);

            if ($key === null || ! isset($kirByPlate[$key])) {
                continue;
            }

            $parsed[$index]['raw_payload']['kir_expires_at'] = $kirByPlate[$key];
        }

        return $parsed;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<string, string>
     */
    public function parseKirRows(array $rows): array
    {
        $plateIndex = null;
        $validityIndex = null;
        $result = [];

        foreach ($rows as $row) {
            $normalized = [];
            foreach ($row as $index => $value) {
                $key = Normalizer::key($value);
                if ($key !== null) {
                    $normalized[$index] = $key;
                }
            }

            if ($plateIndex === null) {
                foreach ($normalized as $index => $headerKey) {
                    if (in_array($headerKey, ['nopolisi', 'nomorpolisi', 'platnomor', 'plat'], true)
                        || str_contains($headerKey, 'nopolisi')
                        || str_contains($headerKey, 'nomorpolisi')) {
                        $plateIndex = $index;
                    }
                    if (in_array($headerKey, ['validity', 'masa berlaku', 'masaberlaku', 'berlaku'], true)
                        || str_contains($headerKey, 'validity')
                        || str_contains($headerKey, 'berlaku')) {
                        $validityIndex = $index;
                    }
                }

                continue;
            }

            if ($validityIndex === null) {
                break;
            }

            $plate = VehiclePlateNormalizer::display($row[$plateIndex] ?? null)
                ?? VehiclePlateNormalizer::extractFromText($row[$plateIndex] ?? null);
            $expires = VehicleAuditDate::toDateString($row[$validityIndex] ?? null);
            $key = VehiclePlateNormalizer::key($plate);

            if ($key !== null && $expires !== null) {
                $result[$key] = $expires;
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sheetToRows(Worksheet $sheet): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $rows = [];

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];

            for ($column = 1; $column <= $highestColumn; $column++) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$rowNumber)->getValue();
                $row[] = $value instanceof RichText ? $value->getPlainText() : $value;
            }

            $rows[$rowNumber] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<string, int>|null
     */
    private function detectHeader(array $row): ?array
    {
        $normalized = [];

        foreach ($row as $index => $value) {
            $key = Normalizer::key($value);

            if ($key !== null) {
                $normalized[$index] = $key;
            }
        }

        if ($normalized === []) {
            return null;
        }

        $mapping = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            $strict = in_array($field, self::STRICT_HEADER_FIELDS, true);

            foreach ($aliases as $alias) {
                $aliasKey = Normalizer::key($alias);

                if ($aliasKey === null) {
                    continue;
                }

                foreach ($normalized as $index => $headerKey) {
                    $matched = $strict
                        ? ($headerKey === $aliasKey || str_starts_with($headerKey, $aliasKey))
                        : ($headerKey === $aliasKey || str_contains($headerKey, $aliasKey));

                    if ($matched) {
                        $mapping[$field] = $index;
                        break 2;
                    }
                }
            }
        }

        if (! isset($mapping['license_plate'])) {
            return null;
        }

        return $mapping;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $mapping
     * @return array<string, mixed>
     */
    private function mapRow(array $row, array $mapping): array
    {
        $payload = [];

        foreach ($mapping as $field => $index) {
            $payload[$field] = $row[$index] ?? null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function looksLikeDataRow(array $payload): bool
    {
        $plate = VehiclePlateNormalizer::display($payload['license_plate'] ?? null);

        if ($plate !== null) {
            return true;
        }

        $no = Normalizer::text($payload['license_plate'] ?? null);

        return $no !== null && is_numeric($no) === false && strcasecmp($no, 'NO') !== 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeDataRow(int $sourceRow, array $payload): array
    {
        $plate = VehiclePlateNormalizer::display($payload['license_plate'] ?? null)
            ?? VehiclePlateNormalizer::extractFromText($payload['license_plate'] ?? null);
        $accounting = Normalizer::text($payload['accounting'] ?? null);
        $stnkName = Normalizer::text($payload['stnk_name'] ?? null);
        $sold = $this->isSoldMarker($accounting) || $this->isSoldMarker($stnkName);
        $brand = Normalizer::text($payload['brand'] ?? null);
        $vehicleName = Normalizer::text($payload['vehicle_name'] ?? null);
        $vehicleType = Normalizer::text($payload['vehicle_type'] ?? null);
        $itemName = trim(implode(' ', array_filter([$brand, $vehicleName, $vehicleType])));
        $chassis = VehiclePlateNormalizer::chassis($payload['engine_chassis'] ?? null);
        $entityCode = $this->entityCodeFromAccounting($accounting);
        $stnkExpires = VehicleAuditDate::toDateString($payload['stnk_expires_at'] ?? null);
        $taxExpires = VehicleAuditDate::toDateString($payload['tax_expires_at'] ?? null);
        $insuranceExpires = VehicleAuditDate::toDateString($payload['insurance_expires_at'] ?? null);
        $insuranceJoin = VehicleAuditDate::toDateString($payload['insurance_join_date'] ?? null);
        $bpkbStatus = Normalizer::text($payload['bpkb_status'] ?? null);
        $bpkbNumber = Normalizer::text($payload['bpkb_number'] ?? null);
        $holder = Normalizer::text($payload['holder'] ?? null);
        $presence = Normalizer::text($payload['presence'] ?? null);

        $errors = [];

        if ($plate === null) {
            $errors[] = 'Nomor polisi tidak valid.';
        }

        return [
            'source_row' => $sourceRow,
            'source_rows' => [$sourceRow],
            'external_business_entity_code' => $entityCode,
            'external_location_code' => $presence,
            'external_item_code' => $plate,
            'item_name' => $itemName !== '' ? $itemName : ($plate ?? 'Kendaraan'),
            'serial_number' => $chassis,
            'system_qty' => null,
            'physical_qty' => $sold ? 0 : 1,
            'correction_qty' => null,
            'target_qty' => $sold ? 0 : 1,
            'notes' => $bpkbStatus,
            'validation_errors' => $errors,
            'raw_payload' => [
                'license_plate' => $plate,
                'stnk_name' => $stnkName,
                'accounting' => $accounting,
                'csa_marker' => Normalizer::text($payload['csa_marker'] ?? null),
                'presence' => $presence,
                'brand' => $brand,
                'vehicle_name' => $vehicleName,
                'vehicle_type' => $vehicleType,
                'color' => Normalizer::text($payload['color'] ?? null),
                'year' => Normalizer::text($payload['year'] ?? null),
                'engine_chassis' => Normalizer::text($payload['engine_chassis'] ?? null),
                'holder' => $holder,
                'bpkb_status' => $bpkbStatus,
                'bpkb_number' => $bpkbNumber,
                'disposition' => $sold ? 'sold' : 'active',
                'stnk_expires_at' => $stnkExpires,
                'tax_expires_at' => $taxExpires,
                'insurance_expires_at' => $insuranceExpires,
                'insurance_join_date' => $insuranceJoin,
                'insurance_policy_number' => Normalizer::text($payload['insurance_policy_number'] ?? null),
                'insurance_name' => Normalizer::text($payload['insurance_name'] ?? null),
                'insurance_type' => Normalizer::text($payload['insurance_type'] ?? null),
                'insurance_insured' => Normalizer::text($payload['insurance_insured'] ?? null),
                'kir_expires_at' => null,
            ],
        ];
    }

    private function isSoldMarker(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return str_contains(strtoupper($value), 'TERJUAL');
    }

    private function entityCodeFromAccounting(?string $accounting): ?string
    {
        if ($accounting === null || $this->isSoldMarker($accounting) || str_contains(strtoupper($accounting), '#N/A')) {
            return null;
        }

        $code = strtoupper(trim($accounting));

        return $code === '' ? null : $code;
    }
}
