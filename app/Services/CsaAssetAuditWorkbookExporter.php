<?php

namespace App\Services;

use App\Models\Asset;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CsaAssetAuditWorkbookExporter
{
    public const SHEET_NAME = 'ASET';

    /** @var list<string> */
    public const HEADERS = [
        'Kode Gudang',
        'Kode Item',
        'Nama Item',
        'S/N',
        'Qty Akhir',
        'Fisik',
        'Selisih',
        'Keterangan',
    ];

    public function exportToPath(?string $path = null): string
    {
        $groups = $this->groupedAssets();

        if ($groups === []) {
            throw new \LogicException('Tidak ada aset inventori aktif dengan lokasi untuk diekspor ke format CSA.');
        }

        $path ??= storage_path('app/tmp/export_csa_audit_aset_'.date('Y-m-d_His').'.xlsx');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Xlsx($this->build($groups));
        $writer->save($path);

        return $path;
    }

    /**
     * @param  list<array{
     *     business_entity_name: ?string,
     *     location_name: string,
     *     location_code: string,
     *     assets: list<array{external_item_code: ?string, item_name: string, serial_number: ?string, system_qty: int}>
     * }>|null  $groups
     */
    public function build(?array $groups = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        $rowNumber = 1;
        $aliasByEntityName = $this->aliasByBusinessEntityName();

        foreach ($groups ?? $this->groupedAssets() as $group) {
            $locationLabel = 'Gudang '.$group['location_name'];
            $alias = $group['business_entity_name'] !== null
                ? ($aliasByEntityName[$group['business_entity_name']] ?? null)
                : null;

            if ($alias !== null) {
                $locationLabel .= ' (CSA '.$alias.')';
            }

            $sheet->setCellValue('A'.$rowNumber, $locationLabel);
            $rowNumber++;

            foreach (self::HEADERS as $columnIndex => $header) {
                $sheet->setCellValue([$columnIndex + 1, $rowNumber], $header);
            }
            $rowNumber++;

            foreach ($group['assets'] as $asset) {
                $sheet->fromArray([
                    $group['location_code'],
                    $asset['external_item_code'],
                    $asset['item_name'],
                    $asset['serial_number'],
                    $asset['system_qty'],
                    null,
                    null,
                    null,
                ], null, 'A'.$rowNumber);
                $rowNumber++;
            }

            $rowNumber++;
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('PETUNJUK');
        $guide->fromArray([
            ['Export Format CSA dari Shelf'],
            ['1. Jangan ubah nama sheet ASET atau header kolom.'],
            ['2. Qty Akhir = saldo Shelf saat export. Isi Fisik (audit) dan/atau Selisih.'],
            ['3. Selisih = Fisik - Qty Akhir bila keduanya diisi.'],
            ['4. Simpan file, lalu Import di menu Import & Laporan Audit.'],
            ['5. Tinjau Laporan (Inline/Gap/Blocked) sebelum Apply.'],
            ['6. Judul blok bertanda (CSA CSN) menargetkan badan usaha CSN.'],
        ], null, 'A1');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @return list<array{
     *     business_entity_name: ?string,
     *     location_name: string,
     *     location_code: string,
     *     assets: list<array{external_item_code: ?string, item_name: string, serial_number: ?string, system_qty: int}>
     * }>
     */
    private function groupedAssets(): array
    {
        $assets = Asset::query()
            ->with([
                'assetLocation:id,name,external_code',
                'catalogItem:id,external_code,name',
                'businessEntity:id,name',
            ])
            ->where('inventory_active', true)
            ->whereNotNull('asset_location_id')
            ->whereNull('sold_at')
            ->orderBy('business_entity_id')
            ->orderBy('asset_location_id')
            ->orderBy('name')
            ->get();

        return $assets
            ->groupBy(function (Asset $asset): string {
                $entityKey = $asset->business_entity_id ?? 'none';
                $locationKey = $asset->asset_location_id ?? 'none';

                return $entityKey.'|'.$locationKey;
            })
            ->map(function (Collection $group): array {
                /** @var Asset $first */
                $first = $group->first();
                $location = $first->assetLocation;
                $locationCode = Normalizer::text($location?->external_code)
                    ?? Normalizer::text($location?->name)
                    ?? 'UNKNOWN';

                $rows = $group
                    ->sortBy(fn (Asset $asset): string => implode('|', [
                        Normalizer::text($asset->catalogItem?->external_code) ?? '',
                        Normalizer::text($asset->name) ?? '',
                        Normalizer::text($asset->serial_number) ?? Normalizer::text($asset->imei1) ?? '',
                    ]))
                    ->values()
                    ->map(function (Asset $asset): array {
                        $serial = Normalizer::identity($asset->serial_number) !== null
                            ? Normalizer::text($asset->serial_number)
                            : (Normalizer::identity($asset->imei1) !== null
                                ? Normalizer::text($asset->imei1)
                                : null);

                        return [
                            'external_item_code' => Normalizer::text($asset->catalogItem?->external_code),
                            'item_name' => Normalizer::text($asset->catalogItem?->name)
                                ?? Normalizer::text($asset->name)
                                ?? 'UNKNOWN',
                            'serial_number' => $serial,
                            'system_qty' => (int) $asset->qty,
                        ];
                    })
                    ->all();

                return [
                    'business_entity_name' => Normalizer::text($first->businessEntity?->name),
                    'location_name' => Normalizer::text($location?->name) ?? $locationCode,
                    'location_code' => $locationCode,
                    'assets' => $rows,
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, string> */
    private function aliasByBusinessEntityName(): array
    {
        $aliases = config('asset-reconciliation.business_entity_aliases', []);
        $reversed = [];

        foreach ($aliases as $alias => $entityName) {
            $reversed[(string) $entityName] = strtoupper((string) $alias);
        }

        return $reversed;
    }
}
