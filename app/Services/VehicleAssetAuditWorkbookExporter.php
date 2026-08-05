<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Support\VehiclePlateNormalizer;
use LogicException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class VehicleAssetAuditWorkbookExporter
{
    public const SHEET_NAME = 'Monitoring Asset';

    /** @var list<string> */
    public const HEADERS = [
        'NO',
        'NOMOR POLISI',
        'NAMA STNK',
        'SHELF',
        'ACCOUNTING',
        'CSA',
        'CEK KEBERADAAN UNIT',
        'MERK KENDARAAN',
        'NAMA KENDARAAN',
        'JENIS KENDARAAN',
        'WARNA',
        'TAHUN KENDARAAN',
        'NO MESIN',
        'PEMEGANG INVENTARIS',
        'STATUS BPKB',
        'EXPIRED STNK H-45',
        'EXPIRED KIR',
        'EXPIRED PAJAK H-45',
        'EXPIRED DATE H-45',
        'NO.POLIS',
        'NAMA ASURANSI',
        'JENIS ASURANSI',
        'NO BPKB',
    ];

    public function exportToPath(?string $path = null): string
    {
        $rows = $this->vehicleRows();

        if ($rows === []) {
            throw new LogicException('Tidak ada aset MOBIL/MOTOR untuk diekspor ke format audit kendaraan.');
        }

        $path ??= storage_path('app/tmp/export_vehicle_audit_'.date('Y-m-d_His').'.xlsx');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Xlsx($this->build($rows));
        $writer->save($path);

        return $path;
    }

    /**
     * @param  list<array<string, mixed>>|null  $rows
     */
    public function build(?array $rows = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        foreach (self::HEADERS as $columnIndex => $header) {
            $sheet->setCellValue([$columnIndex + 1, 2], $header);
        }

        $rowNumber = 3;
        $no = 1;

        foreach ($rows ?? $this->vehicleRows() as $row) {
            $sheet->fromArray([
                $no,
                $row['license_plate'],
                $row['business_entity_name'],
                'SHELF',
                null,
                null,
                $row['location_name'],
                $row['brand'],
                $row['name'],
                $row['type'],
                null,
                null,
                $row['serial_number'],
                $row['holder'],
                $row['bpkb'],
                $row['stnk_expires_at'],
                $row['kir_expires_at'],
                $row['tax_expires_at'],
                $row['insurance_expires_at'],
                $row['insurance_policy_number'],
                $row['insurance_name'],
                $row['insurance_type'],
                $row['bpkb'],
            ], null, 'A'.$rowNumber);
            $rowNumber++;
            $no++;
        }

        $guide = $spreadsheet->createSheet();
        $guide->setTitle('PETUNJUK');
        $guide->fromArray([
            ['Export → isi ACCOUNTING / CEK KEBERADAAN / BPKB / EXPIRED STNK / KIR → Import VEHICLE_AUDIT → Laporan → Apply'],
            ['Jangan ubah kolom NOMOR POLISI kecuali koreksi plat.'],
            ['Tandai TERJUAL pada ACCOUNTING atau NAMA STNK untuk unit yang sudah dijual.'],
            ['EXPIRED STNK / KIR / PAJAK / ASURANSI mengisi custom attribute Dokumen/Masa Berlaku (pengingat H-30).'],
            ['BPKB dan Pemegang Inventaris disimpan sebagai attribute teks; lokasi diisi dari CEK KEBERADAAN bila cocok.'],
            ['Sheet KIR (Validity) digabung otomatis saat Import.'],
            ['Apply tidak menghapus aset; duplikat dinonaktifkan (inventory_active=false).'],
        ], null, 'A1');

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @return list<array<string, mixed>> */
    private function vehicleRows(): array
    {
        $categoryIds = Category::query()
            ->whereIn('name', config('vehicle-asset-reconciliation.category_names', ['MOBIL', 'MOTOR']))
            ->pluck('id');
        $plateAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.plate_attribute_name', 'Plat Nomor'))
            ->value('id');
        $stnkAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.stnk_attribute_name', 'STNK'))
            ->value('id');
        $kirAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.kir_attribute_name', 'KIR'))
            ->value('id');
        $taxAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.tax_attribute_name', 'Pajak'))
            ->value('id');
        $insuranceAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.insurance_attribute_name', 'Asuransi'))
            ->value('id');
        $bpkbAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.bpkb_attribute_name', 'BPKB'))
            ->value('id');
        $holderAttributeId = CustomAssetAttribute::query()
            ->where('name', config('vehicle-asset-reconciliation.holder_attribute_name', 'Pemegang Inventaris'))
            ->value('id');

        if ($categoryIds->isEmpty()) {
            return [];
        }

        $assets = Asset::query()
            ->with(['businessEntity:id,name', 'assetLocation:id,name', 'brand:id,name', 'attributes'])
            ->whereIn('category_id', $categoryIds)
            ->where(function ($query): void {
                $query->whereNull('condition_status')
                    ->orWhere('condition_status', '!=', AssetCondition::Sold->value);
            })
            ->orderBy('id')
            ->get();

        $rows = [];

        foreach ($assets as $asset) {
            $plate = null;
            if ($plateAttributeId !== null) {
                $plate = VehiclePlateNormalizer::display(
                    $asset->attributes->firstWhere('custom_attribute_id', $plateAttributeId)?->attribute_value
                );
            }
            $plate ??= VehiclePlateNormalizer::extractFromText($asset->name)
                ?? VehiclePlateNormalizer::extractFromText($asset->serial_number);

            $rows[] = [
                'license_plate' => $plate,
                'business_entity_name' => $asset->businessEntity?->name,
                'location_name' => $asset->assetLocation?->name,
                'brand' => $asset->brand?->name,
                'name' => $asset->name,
                'type' => $asset->type,
                'serial_number' => $asset->serial_number,
                'holder' => null,
                'stnk_expires_at' => $this->documentExpiry($asset, $stnkAttributeId),
                'kir_expires_at' => $this->documentExpiry($asset, $kirAttributeId),
                'tax_expires_at' => $this->documentExpiry($asset, $taxAttributeId),
                'insurance_expires_at' => $this->documentExpiry($asset, $insuranceAttributeId),
                'insurance_policy_number' => $this->documentNumber($asset, $insuranceAttributeId),
                'insurance_name' => null,
                'insurance_type' => null,
                'bpkb' => $bpkbAttributeId
                    ? $asset->attributes->firstWhere('custom_attribute_id', $bpkbAttributeId)?->attribute_value
                    : null,
                'holder' => $holderAttributeId
                    ? $asset->attributes->firstWhere('custom_attribute_id', $holderAttributeId)?->attribute_value
                    : null,
                'condition' => $asset->condition_status instanceof AssetCondition
                    ? $asset->condition_status->value
                    : $asset->condition_status,
            ];
        }

        return $rows;
    }

    private function documentExpiry(Asset $asset, ?int $attributeId): ?string
    {
        $decoded = $this->documentPayload($asset, $attributeId);

        return $decoded['expires_at'] ?? null;
    }

    private function documentNumber(Asset $asset, ?int $attributeId): ?string
    {
        $decoded = $this->documentPayload($asset, $attributeId);

        return $decoded['document_number'] ?? null;
    }

    /** @return array<string, mixed> */
    private function documentPayload(Asset $asset, ?int $attributeId): array
    {
        if ($attributeId === null) {
            return [];
        }

        $value = $asset->attributes->firstWhere('custom_attribute_id', $attributeId)?->attribute_value;
        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
