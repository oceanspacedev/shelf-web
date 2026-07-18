<?php

namespace App\Imports;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class AssetImport extends DefaultValueBinder implements ToCollection, WithChunkReading, WithHeadingRow, WithCustomValueBinder
{
    private array $businessEntityCache = [];

    private array $categoryCache = [];

    private array $brandCache = [];

    private array $assetLocationCache = [];

    private array $userCache = [];

    public function __construct()
    {
        $this->preloadCaches();
    }

    public function bindValue(Cell $cell, $value)
    {
        // If the value is a large numeric string or contains E, bind it as a string to prevent scientific notation conversion
        if (is_numeric($value) && (strlen((string) $value) >= 12 || str_contains((string) $value, 'E'))) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        // Else, use default value binder behavior
        return parent::bindValue($cell, $value);
    }

    private function preloadCaches(): void
    {
        $this->businessEntityCache = BusinessEntity::pluck('id', 'name')->toArray();
        $this->categoryCache = Category::pluck('id', 'name')->toArray();
        $this->brandCache = Brand::pluck('id', 'name')->toArray();
        $this->assetLocationCache = AssetLocation::pluck('id', 'name')->toArray();
        $this->userCache = User::pluck('id', 'name')->toArray();
    }

    public function collection(Collection $collection)
    {
        $assetsToInsert = [];
        $now = now();

        DB::beginTransaction();

        try {
            foreach ($collection as $index => $row) {
                $purchaseDate = $this->parseDate($row['tanggal_pembelian'] ?? null);
                $businessEntityId = $this->findOrCreateBusinessEntity($row['badan_usaha'] ?? null);
                $categoryId = $this->findOrCreateCategory($row['kategori'] ?? null);
                $brandId = $this->findOrCreateBrand($row['merek'] ?? null);
                $assetLocationId = $this->findOrCreateAssetLocation($row['lokasi_aset'] ?? null);
                $conditionStatus = $this->mapConditionStatus($row['status_aset'] ?? null);
                $isSold = $conditionStatus === AssetCondition::Sold->value;
                $nbhStatus = $isSold
                    ? NbhStatus::None->value
                    : $this->mapNbhStatus($row['status_nbh'] ?? null);
                $nbhResponsibleId = $isSold ? null : $this->findUserByName($row['penanggung_jawab_nbh'] ?? null);
                $recipientId = $isSold ? null : $this->findUserByName($row['penerima_aset'] ?? null);
                $recipientBusinessEntityId = $isSold ? null : $this->findOrCreateBusinessEntity($row['badan_usaha_penerima'] ?? null);
                $nbhReportedAt = $isSold ? null : $this->parseDate($row['tanggal_insiden'] ?? null);
                $saleAuditPayload = $this->buildSaleAuditPayload($row, $conditionStatus, (int) $index);

                $assetsToInsert[] = [
                    'purchase_date' => $purchaseDate,
                    'business_entity_id' => $businessEntityId,
                    'name' => $row['nama_aset'] ?? null,
                    'category_id' => $categoryId,
                    'brand_id' => $brandId,
                    'type' => $row['tipe'] ?? null,
                    'serial_number' => $row['serial_number'] ?? null,
                    'imei1' => $row['imei_1'] ?? null,
                    'imei2' => $row['imei_2'] ?? null,
                    'item_price' => $this->parsePrice($row['harga_aset'] ?? null),
                    'qty' => $row['qty'] ?? 1,
                    'asset_location_id' => $assetLocationId,
                    'condition_status' => $conditionStatus,
                    'nbh_status' => $nbhStatus,
                    'nbh_responsible_user_id' => $nbhResponsibleId,
                    'nbh_reported_at' => $nbhReportedAt,
                    'recipient_id' => $recipientId,
                    'recipient_business_entity_id' => $recipientBusinessEntityId,
                    'is_available' => $conditionStatus === AssetCondition::Available->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                    ...$saleAuditPayload,
                ];
            }

            if (! empty($assetsToInsert)) {
                Asset::insert($assetsToInsert);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        unset($assetsToInsert);
        gc_collect_cycles();
    }

    public function chunkSize(): int
    {
        return 500;
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($value - 25569) * 86400))->format('Y-m-d');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parsePrice($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9]/', '', $value);

        if ($cleaned === '') {
            return null;
        }

        return (int) $cleaned;
    }

    private function findOrCreateBusinessEntity($name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);

        if (isset($this->businessEntityCache[$name])) {
            return $this->businessEntityCache[$name];
        }

        $entity = BusinessEntity::firstOrCreate(['name' => $name]);
        $this->businessEntityCache[$name] = $entity->id;

        return $entity->id;
    }

    private function findOrCreateCategory($name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);

        if (isset($this->categoryCache[$name])) {
            return $this->categoryCache[$name];
        }

        $entity = Category::firstOrCreate(['name' => $name]);
        $this->categoryCache[$name] = $entity->id;

        return $entity->id;
    }

    private function findOrCreateBrand($name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);

        if (isset($this->brandCache[$name])) {
            return $this->brandCache[$name];
        }

        $entity = Brand::firstOrCreate(['name' => $name]);
        $this->brandCache[$name] = $entity->id;

        return $entity->id;
    }

    private function findOrCreateAssetLocation($name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);

        if (isset($this->assetLocationCache[$name])) {
            return $this->assetLocationCache[$name];
        }

        $entity = AssetLocation::firstOrCreate(['name' => $name]);
        $this->assetLocationCache[$name] = $entity->id;

        return $entity->id;
    }

    private function findUserByName($name): ?int
    {
        if (empty($name)) {
            return null;
        }

        $name = trim($name);

        if (isset($this->userCache[$name])) {
            return $this->userCache[$name];
        }

        return null;
    }

    private function mapConditionStatus($status): string
    {
        if (empty($status)) {
            return AssetCondition::Available->value;
        }

        return match (strtolower(trim($status))) {
            'tersedia' => AssetCondition::Available->value,
            'digunakan' => AssetCondition::Transferred->value,
            'dijual', 'terjual', 'sold' => AssetCondition::Sold->value,
            'hilang' => AssetCondition::Lost->value,
            'rusak' => AssetCondition::Damaged->value,
            default => AssetCondition::Available->value,
        };
    }

    private function mapNbhStatus($status): string
    {
        if (empty($status)) {
            return NbhStatus::None->value;
        }

        return match (strtolower(trim($status))) {
            'tidak ada' => NbhStatus::None->value,
            'menunggu nbh' => NbhStatus::Pending->value,
            'nbh selesai' => NbhStatus::Resolved->value,
            default => NbhStatus::None->value,
        };
    }

    private function buildSaleAuditPayload($row, string $conditionStatus, int $index): array
    {
        $payload = [
            'sold_at' => null,
            'sold_to' => null,
            'sold_price' => null,
            'sale_document_path' => null,
            'sale_notes' => null,
        ];

        if ($conditionStatus !== AssetCondition::Sold->value) {
            return $payload;
        }

        $soldAt = $this->parseDate($row['tanggal_jual'] ?? null);
        $soldTo = $this->normalizeText($row['dijual_ke'] ?? null);
        $soldPrice = $this->parsePrice($row['harga_jual'] ?? null);
        $saleDocumentPath = $this->normalizeText($row['dokumen_penjualan'] ?? null);
        $saleNotes = $this->normalizeText($row['catatan_penjualan'] ?? null);

        $missingFields = [];

        if ($soldAt === null) {
            $missingFields[] = 'Tanggal Jual';
        }

        if (! filled($soldTo)) {
            $missingFields[] = 'Dijual Ke';
        }

        if ($soldPrice === null) {
            $missingFields[] = 'Harga Jual';
        }

        if ($missingFields !== []) {
            $assetName = $this->normalizeText($row['nama_aset'] ?? null) ?? 'tanpa nama';

            throw new \InvalidArgumentException(
                sprintf(
                    'Baris %d untuk aset "%s" berstatus Dijual dan wajib mengisi: %s.',
                    $index + 2,
                    $assetName,
                    implode(', ', $missingFields),
                )
            );
        }

        return [
            'sold_at' => $soldAt,
            'sold_to' => $soldTo,
            'sold_price' => $soldPrice,
            'sale_document_path' => $saleDocumentPath,
            'sale_notes' => $saleNotes,
        ];
    }

    private function normalizeText($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
