<?php

namespace App\Services;

use App\Support\AssetReconciliationNormalizer as Normalizer;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class CsaAssetAuditWorkbookParser
{
    /** @var array<string, array<int, string>> */
    private const HEADER_ALIASES = [
        'external_location_code' => ['kode', 'kode gudang', 'nama gudang', 'gudang', 'kode lokasi'],
        'external_item_code' => ['kd barang', 'kd. barang', 'kode barang', 'kode item', 'kd item'],
        'item_name' => ['nama barang', 'nama item', 'barang', 'item'],
        'serial_number' => ['s/n', 'sn', 'serial number', 'imei'],
        'system_qty' => ['saldo akhir', 'qty akhir', 'stock sistem', 'stok sistem'],
        'physical_qty' => ['saldo real', 'fisik', 'stock fisik', 'stok fisik'],
        'correction_qty' => ['selisih/koreksi', 'selisih koreksi', 'selisih', 'koreksi'],
        'notes' => ['keterangan', 'catatan'],
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $path, string $sheetName = 'ASET'): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $workbook = $reader->load($path);
        $sheet = $workbook->getSheetByName($sheetName);

        if ($sheet === null) {
            throw new InvalidArgumentException("Sheet \"{$sheetName}\" tidak ditemukan.");
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($rowNumber = 1; $rowNumber <= $highestRow; $rowNumber++) {
            $row = [];

            for ($column = 1; $column <= 8; $column++) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($column).$rowNumber)->getValue();
                $row[] = $value instanceof RichText ? $value->getPlainText() : $value;
            }

            $rows[$rowNumber] = $row;
        }

        $workbook->disconnectWorksheets();

        return $this->parseRows($rows);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function parseRows(array $rows): array
    {
        $mapping = null;
        $externalBusinessEntityCode = null;
        $pendingBusinessEntityCode = null;
        $parsed = [];

        foreach ($rows as $sourceRow => $row) {
            $marker = $this->detectExternalBusinessEntityCode($row);

            if ($marker !== null) {
                $pendingBusinessEntityCode = $marker;
            }

            $headerMapping = $this->detectHeader($row);

            if ($headerMapping !== null) {
                $mapping = $headerMapping;
                $externalBusinessEntityCode = $pendingBusinessEntityCode;
                $pendingBusinessEntityCode = null;

                continue;
            }

            if ($mapping === null) {
                continue;
            }

            $payload = $this->mapRow($row, $mapping);

            if (! $this->looksLikeDataRow($payload)) {
                continue;
            }

            $parsed[] = $this->normalizeDataRow(
                (int) $sourceRow,
                $payload,
                $externalBusinessEntityCode,
            );
        }

        if ($parsed === []) {
            throw new InvalidArgumentException('Tidak ada baris aset yang dapat dibaca dari workbook audit.');
        }

        return $parsed;
    }

    private function detectExternalBusinessEntityCode(array $row): ?string
    {
        foreach ($row as $value) {
            $text = Normalizer::text($value);

            if ($text !== null && preg_match('/\(\s*CSA\s+([^)]+)\)/iu', $text, $matches) === 1) {
                return strtoupper(trim($matches[1]));
            }
        }

        return null;
    }

    /** @return array<string, int>|null */
    private function detectHeader(array $row): ?array
    {
        $mapping = [];

        foreach (array_slice($row, 0, 8) as $index => $value) {
            $header = Normalizer::text($value);

            if ($header === null) {
                continue;
            }

            $headerKey = Normalizer::key($header);

            foreach (self::HEADER_ALIASES as $canonical => $aliases) {
                $aliasKeys = array_map(Normalizer::key(...), $aliases);

                if (in_array($headerKey, $aliasKeys, true)) {
                    $mapping[$canonical] = $index;
                    break;
                }
            }
        }

        $required = ['item_name', 'system_qty', 'correction_qty'];

        return count(array_intersect($required, array_keys($mapping))) === count($required)
            ? $mapping
            : null;
    }

    /** @param array<string, int> $mapping */
    private function mapRow(array $row, array $mapping): array
    {
        $payload = [];

        foreach (array_keys(self::HEADER_ALIASES) as $canonical) {
            $payload[$canonical] = array_key_exists($canonical, $mapping)
                ? ($row[$mapping[$canonical]] ?? null)
                : null;
        }

        return $payload;
    }

    private function looksLikeDataRow(array $payload): bool
    {
        return Normalizer::text($payload['item_name']) !== null
            && (
                Normalizer::text($payload['external_location_code']) !== null
                || Normalizer::text($payload['external_item_code']) !== null
            )
            && (
                Normalizer::quantity($payload['system_qty']) !== null
                || Normalizer::quantity($payload['physical_qty']) !== null
                || Normalizer::quantity($payload['correction_qty']) !== null
            );
    }

    /** @return array<string, mixed> */
    private function normalizeDataRow(
        int $sourceRow,
        array $payload,
        ?string $externalBusinessEntityCode,
    ): array {
        $system = Normalizer::quantity($payload['system_qty']);
        $physical = Normalizer::quantity($payload['physical_qty']);
        $correction = Normalizer::quantity($payload['correction_qty']);
        $errors = [];

        foreach (['system' => $system, 'physical' => $physical, 'correction' => $correction] as $label => $quantity) {
            if ($quantity !== null && abs($quantity - round($quantity)) > 0.00001) {
                $errors[] = "Kuantitas {$label} harus berupa bilangan bulat.";
            }
        }

        if ($correction === null && $system !== null && $physical !== null) {
            $correction = $physical - $system;
        }

        $target = match (true) {
            $physical !== null => $physical,
            $system !== null && $correction !== null => $system + $correction,
            $correction !== null => $correction,
            default => $system,
        };

        if ($target === null) {
            $errors[] = 'Target kuantitas tidak dapat dihitung.';
            $target = 0;
        }

        if ($target < 0) {
            $errors[] = 'Target kuantitas tidak boleh negatif.';
        }

        if ($system !== null && $physical !== null && $correction !== null && abs(($system + $correction) - $physical) > 0.00001) {
            $errors[] = 'Saldo Real tidak konsisten dengan Saldo Akhir + Koreksi.';
        }

        return [
            'source_row' => $sourceRow,
            'external_business_entity_code' => $externalBusinessEntityCode,
            'external_location_code' => Normalizer::text($payload['external_location_code']),
            'external_item_code' => Normalizer::text($payload['external_item_code']),
            'item_name' => Normalizer::text($payload['item_name']),
            'serial_number' => Normalizer::identity($payload['serial_number']) === null
                ? null
                : Normalizer::text($payload['serial_number']),
            'system_qty' => $system === null ? null : (int) round($system),
            'physical_qty' => $physical === null ? null : (int) round($physical),
            'correction_qty' => $correction === null ? null : (int) round($correction),
            'target_qty' => (int) round(max(0, $target)),
            'notes' => Normalizer::text($payload['notes']),
            'validation_errors' => $errors,
            'raw_payload' => $payload,
        ];
    }
}
