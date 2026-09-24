<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetLocation;
use App\Models\AssetReconciliation;
use App\Models\AssetReconciliationItem;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use App\Support\StoredFile;
use App\Support\VehiclePlateNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class VehicleAssetReconciliationService
{
    public function __construct(
        private readonly VehicleAssetAuditWorkbookParser $parser,
    ) {}

    public function compare(AssetReconciliation $reconciliation): AssetReconciliation
    {
        if ($reconciliation->applied_at !== null) {
            throw new LogicException('Batch yang sudah diterapkan bersifat immutable. Gunakan Compare Ulang.');
        }

        if (($reconciliation->source_system ?? null) !== config('vehicle-asset-reconciliation.source_system')) {
            throw new LogicException('Batch ini bukan VEHICLE_AUDIT.');
        }

        try {
            if ($reconciliation->business_entity_id === null
                || ! BusinessEntity::query()->whereKey($reconciliation->business_entity_id)->exists()) {
                throw new LogicException('Badan usaha wajib dipilih dari master resmi sebelum compare.');
            }

            $rows = StoredFile::withLocalPath(
                $reconciliation->stored_disk ?: 'local',
                $reconciliation->stored_path,
                fn (string $path): array => $this->parser->parse($path, $reconciliation->source_sheet),
            );

            DB::transaction(function () use ($reconciliation, $rows): void {
                $reconciliation->items()->delete();

                $businessEntities = BusinessEntity::query()->get(['id', 'name']);
                $vehicleAssets = $this->loadVehicleAssets();
                $plateIndex = $this->buildPlateIndex($vehicleAssets);
                $serialIndex = $this->buildSerialIndex($vehicleAssets);
                $plateAttributeId = $this->plateAttributeId();

                $counts = ['inline' => 0, 'gap' => 0, 'blocked' => 0];
                $actionCounts = [];
                $seenPlates = [];
                $auditPlateKeys = [];

                foreach ($rows as $row) {
                    $plateKey = VehiclePlateNormalizer::key($row['external_item_code'] ?? null);
                    if ($plateKey !== null) {
                        $auditPlateKeys[$plateKey] = true;
                    }

                    [$businessEntityId, $businessEntityError] = $this->resolveBusinessEntityId(
                        $reconciliation,
                        $row,
                        $businessEntities,
                    );

                    if ($businessEntityError !== null) {
                        $row['validation_errors'][] = $businessEntityError;
                    }

                    $serialKey = Normalizer::key($row['serial_number'] ?? null);
                    $candidates = array_values(array_unique(array_merge(
                        $plateKey !== null ? ($plateIndex[$plateKey] ?? []) : [],
                        $serialKey !== null ? ($serialIndex[$serialKey] ?? []) : [],
                    )));
                    $duplicateInWorkbook = $plateKey !== null && isset($seenPlates[$plateKey]);
                    if ($plateKey !== null) {
                        $seenPlates[$plateKey] = true;
                    }

                    $comparison = $this->compareRow(
                        $row,
                        $candidates,
                        $vehicleAssets,
                        $businessEntityId,
                        $reconciliation->auto_create_locations,
                        $duplicateInWorkbook,
                        $plateAttributeId,
                    );

                    $counts[$comparison['comparison_status']]++;
                    $action = $comparison['action'] ?? 'none';
                    $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;

                    $reconciliation->items()->create([
                        ...collect($row)->except(['validation_errors'])->all(),
                        'asset_location_id' => null,
                        'asset_catalog_item_id' => null,
                        'business_entity_id' => $businessEntityId,
                        ...$comparison,
                    ]);
                }

                $orphanCount = 0;
                foreach ($plateIndex as $key => $assetIds) {
                    if (! isset($auditPlateKeys[$key])) {
                        $orphanCount++;
                    }
                }

                $status = $counts['gap'] === 0 && $counts['blocked'] === 0
                    ? AssetReconciliation::STATUS_ALIGNED
                    : AssetReconciliation::STATUS_COMPARED;

                $reconciliation->update([
                    'status' => $status,
                    'total_rows' => count($rows),
                    'inline_rows' => $counts['inline'],
                    'gap_rows' => $counts['gap'],
                    'blocked_rows' => $counts['blocked'],
                    'summary' => [
                        'actions' => $actionCounts,
                        'source_sheet' => $reconciliation->source_sheet,
                        'orphan_shelf_plates' => $orphanCount,
                        'default_business_entity_id' => $reconciliation->business_entity_id,
                    ],
                    'failure_message' => null,
                    'compared_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $reconciliation->update([
                'status' => AssetReconciliation::STATUS_FAILED,
                'failure_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $reconciliation->refresh();
    }

    public function apply(AssetReconciliation $reconciliation): AssetReconciliation
    {
        return DB::transaction(function () use ($reconciliation): AssetReconciliation {
            /** @var AssetReconciliation $locked */
            $locked = AssetReconciliation::query()->lockForUpdate()->findOrFail($reconciliation->id);

            if ($locked->applied_at !== null || $locked->status === AssetReconciliation::STATUS_APPLIED) {
                throw new LogicException('Batch ini sudah pernah diterapkan.');
            }

            if ($locked->blocked_rows > 0) {
                throw new LogicException('Batch masih memiliki baris terblokir. Perbaiki mapping atau datanya lalu Compare Ulang.');
            }

            if (! in_array($locked->status, [AssetReconciliation::STATUS_COMPARED, AssetReconciliation::STATUS_ALIGNED], true)) {
                throw new LogicException('Batch harus selesai dibandingkan sebelum koreksi diterapkan.');
            }

            if ($locked->business_entity_id === null
                || BusinessEntity::query()->lockForUpdate()->find($locked->business_entity_id) === null) {
                throw new LogicException('Badan usaha batch tidak tersedia.');
            }

            $created = 0;
            $updated = 0;
            $retired = 0;
            $plateAttributeId = $this->plateAttributeId();
            $categories = $this->vehicleCategoryIds();

            $items = $locked->items()
                ->where('comparison_status', AssetReconciliationItem::STATUS_GAP)
                ->orderBy('source_row')
                ->lockForUpdate()
                ->get();

            $businessEntities = BusinessEntity::query()
                ->lockForUpdate()
                ->get(['id'])
                ->keyBy('id');

            foreach ($items as $item) {
                if ($item->business_entity_id === null || ! $businessEntities->has($item->business_entity_id)) {
                    throw new LogicException("Badan usaha target untuk baris {$item->source_row} belum dipetakan. Jalankan Compare Ulang sebelum apply.");
                }

                match ($item->action) {
                    'mark_sold' => $this->applyMarkSold($locked, $item, $updated, $retired),
                    'retire_duplicate' => $this->applyRetireDuplicate($locked, $item, $retired),
                    'create_missing' => $this->applyCreateMissing($locked, $item, $plateAttributeId, $categories, $created),
                    'enrich' => $this->applyEnrich($locked, $item, $plateAttributeId, $updated),
                    default => throw new LogicException("Aksi tidak dikenal untuk baris {$item->source_row}: {$item->action}"),
                };
            }

            $locked->update([
                'status' => AssetReconciliation::STATUS_APPLIED,
                'created_rows' => $created,
                'updated_rows' => $updated,
                'retired_rows' => $retired,
                'applied_at' => now(),
            ]);

            return $locked->refresh();
        });
    }

    public function recompare(
        AssetReconciliation $reconciliation,
        ?int $userId = null,
        ?int $businessEntityId = null,
        ?array $businessEntityMappings = null,
    ): AssetReconciliation {
        $targetBusinessEntityId = $businessEntityId ?? $reconciliation->business_entity_id;

        if ($targetBusinessEntityId === null
            || ! BusinessEntity::query()->whereKey($targetBusinessEntityId)->exists()) {
            throw new LogicException('Pilih badan usaha resmi untuk Compare Ulang.');
        }

        $followUp = AssetReconciliation::create([
            'parent_id' => $reconciliation->id,
            'source_system' => $reconciliation->source_system,
            'source_sheet' => $reconciliation->source_sheet,
            'business_entity_id' => $targetBusinessEntityId,
            'business_entity_mappings' => $businessEntityMappings ?? $reconciliation->business_entity_mappings,
            'original_filename' => $reconciliation->original_filename,
            'stored_path' => $reconciliation->stored_path,
            'stored_disk' => $reconciliation->stored_disk ?: 'local',
            'file_sha256' => $reconciliation->file_sha256,
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'auto_create_locations' => $reconciliation->auto_create_locations,
            'imported_by' => $userId,
        ]);

        return $this->compare($followUp);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $candidateIds
     * @param  Collection<int, Asset>  $vehicleAssets
     * @return array<string, mixed>
     */
    private function compareRow(
        array $row,
        array $candidateIds,
        Collection $vehicleAssets,
        ?int $businessEntityId,
        bool $autoCreateAssets,
        bool $duplicateInWorkbook,
        ?int $plateAttributeId,
    ): array {
        $errors = $row['validation_errors'] ?? [];
        $disposition = $row['raw_payload']['disposition'] ?? 'active';
        $assets = $vehicleAssets->whereIn('id', $candidateIds)->values();
        $activeAssets = $assets
            ->filter(fn (Asset $asset) => $asset->inventory_active !== false
                && $asset->condition_status !== AssetCondition::Sold)
            ->values();

        if ($errors !== [] || $businessEntityId === null) {
            return [
                'shelf_qty' => $activeAssets->count() > 0 ? 1 : 0,
                'gap_qty' => null,
                'matched_asset_id' => $activeAssets->first()?->id ?? $assets->first()?->id,
                'candidate_asset_ids' => $candidateIds,
                'match_strategy' => 'plate',
                'comparison_status' => AssetReconciliationItem::STATUS_BLOCKED,
                'action' => 'review',
                'message' => implode(' ', $errors) ?: 'Badan usaha target belum dipetakan.',
            ];
        }

        if ($duplicateInWorkbook) {
            return [
                'shelf_qty' => $activeAssets->count() > 0 ? 1 : 0,
                'gap_qty' => null,
                'matched_asset_id' => $activeAssets->first()?->id ?? $assets->first()?->id,
                'candidate_asset_ids' => $candidateIds,
                'match_strategy' => 'plate',
                'comparison_status' => AssetReconciliationItem::STATUS_BLOCKED,
                'action' => 'review',
                'message' => 'Plat muncul lebih dari sekali pada workbook audit.',
            ];
        }

        if ($disposition !== 'sold' && $activeAssets->count() > 1) {
            $ranked = $activeAssets->sortByDesc(fn (Asset $asset) => $this->completenessScore($asset, $plateAttributeId))->values();
            $keep = $ranked->first();
            $retireIds = $ranked->skip(1)->pluck('id')->all();

            return [
                'shelf_qty' => 1,
                'gap_qty' => count($retireIds),
                'matched_asset_id' => $keep->id,
                'candidate_asset_ids' => $retireIds,
                'match_strategy' => 'plate_duplicate',
                'comparison_status' => AssetReconciliationItem::STATUS_GAP,
                'action' => 'retire_duplicate',
                'message' => 'Double input: keep aset #'.$keep->id.'; nonaktifkan '.count($retireIds).' duplikat.',
            ];
        }

        if ($disposition === 'sold') {
            if ($assets->isEmpty()) {
                return [
                    'shelf_qty' => 0,
                    'gap_qty' => 0,
                    'matched_asset_id' => null,
                    'candidate_asset_ids' => [],
                    'match_strategy' => 'plate',
                    'comparison_status' => AssetReconciliationItem::STATUS_INLINE,
                    'action' => 'none',
                    'message' => 'Terjual di audit dan tidak ada di Shelf.',
                ];
            }

            $unsold = $assets->filter(fn (Asset $asset) => $asset->condition_status !== AssetCondition::Sold)->values();

            if ($unsold->isEmpty()) {
                return [
                    'shelf_qty' => 0,
                    'gap_qty' => 0,
                    'matched_asset_id' => $assets->first()->id,
                    'candidate_asset_ids' => $assets->pluck('id')->all(),
                    'match_strategy' => 'plate',
                    'comparison_status' => AssetReconciliationItem::STATUS_INLINE,
                    'action' => 'none',
                    'message' => 'Sudah berstatus Dijual di Shelf.',
                ];
            }

            $ranked = $unsold->sortByDesc(fn (Asset $asset) => $this->completenessScore($asset, $plateAttributeId))->values();
            $keep = $ranked->first();

            return [
                'shelf_qty' => 1,
                'gap_qty' => -1,
                'matched_asset_id' => $keep->id,
                'candidate_asset_ids' => $unsold->pluck('id')->all(),
                'match_strategy' => $unsold->count() > 1 ? 'plate_duplicate' : 'plate',
                'comparison_status' => AssetReconciliationItem::STATUS_GAP,
                'action' => 'mark_sold',
                'message' => 'Audit menandai terjual; Shelf masih aktif.',
            ];
        }

        if ($activeAssets->isEmpty()) {
            if (! $autoCreateAssets) {
                return [
                    'shelf_qty' => 0,
                    'gap_qty' => 1,
                    'matched_asset_id' => null,
                    'candidate_asset_ids' => [],
                    'match_strategy' => 'plate',
                    'comparison_status' => AssetReconciliationItem::STATUS_BLOCKED,
                    'action' => 'review',
                    'message' => 'Plat belum ada di Shelf. Aktifkan opsi buat aset baru atau input manual.',
                ];
            }

            return [
                'shelf_qty' => 0,
                'gap_qty' => 1,
                'matched_asset_id' => null,
                'candidate_asset_ids' => [],
                'match_strategy' => 'plate',
                'comparison_status' => AssetReconciliationItem::STATUS_GAP,
                'action' => 'create_missing',
                'message' => 'Plat aktif di audit belum ada di Shelf; akan dibuat saat Apply.',
            ];
        }

        $asset = $activeAssets->first();
        $needsEnrich = $this->needsEnrich($asset, $row, $plateAttributeId);
        $entityMismatch = $asset->business_entity_id !== null
            && $asset->business_entity_id !== $businessEntityId;

        if ($needsEnrich || $entityMismatch) {
            return [
                'shelf_qty' => 1,
                'gap_qty' => 0,
                'matched_asset_id' => $asset->id,
                'candidate_asset_ids' => [$asset->id],
                'match_strategy' => 'plate',
                'comparison_status' => AssetReconciliationItem::STATUS_GAP,
                'action' => 'enrich',
                'message' => $entityMismatch
                    ? 'Selaraskan identitas/plat/serial dan badan usaha.'
                    : 'Lengkapi plat/serial/nama yang masih kosong.',
            ];
        }

        return [
            'shelf_qty' => 1,
            'gap_qty' => 0,
            'matched_asset_id' => $asset->id,
            'candidate_asset_ids' => [$asset->id],
            'match_strategy' => 'plate',
            'comparison_status' => AssetReconciliationItem::STATUS_INLINE,
            'action' => 'none',
            'message' => 'Selaras dengan audit aktif.',
        ];
    }

    private function applyMarkSold(AssetReconciliation $batch, AssetReconciliationItem $item, int &$updated, int &$retired): void
    {
        $ids = collect($item->candidate_asset_ids ?: [$item->matched_asset_id])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $assets = Asset::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($assets->isEmpty()) {
            throw new LogicException("Aset untuk baris {$item->source_row} tidak tersedia.");
        }

        $before = $assets->map(fn (Asset $asset) => $this->assetSnapshot($asset))->all();
        $payload = $item->raw_payload ?? [];
        $soldTo = Normalizer::text($payload['holder'] ?? null)
            ?? $this->cleanEntityLabel($payload['stnk_name'] ?? null)
            ?? 'Terjual (audit kendaraan)';

        foreach ($assets as $asset) {
            $isKeep = (int) $asset->id === (int) $item->matched_asset_id;

            if ($isKeep || $asset->condition_status !== AssetCondition::Sold) {
                $asset->condition_status = AssetCondition::Sold;
                $asset->sold_at = $asset->sold_at ?? now()->toDateString();
                $asset->sold_to = $asset->sold_to ?: $soldTo;
                $asset->sold_price = $asset->sold_price ?? 0;
                $asset->sale_notes = trim(($asset->sale_notes ? $asset->sale_notes."\n" : '').'Ditandai terjual via rekonsiliasi VEHICLE_AUDIT batch #'.$batch->id);
            }

            $asset->is_available = false;
            $asset->inventory_active = false;
            $asset->reconciliation_source = $batch->source_system;
            $asset->last_reconciled_at = now();
            $asset->last_reconciliation_id = $batch->id;
            $asset->save();
            $retired++;
        }

        $item->update([
            'before_snapshot' => $before,
            'after_snapshot' => Asset::query()->whereKey($ids)->orderBy('id')->get()
                ->map(fn (Asset $asset) => $this->assetSnapshot($asset))
                ->all(),
            'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $updated++;
    }

    private function applyRetireDuplicate(AssetReconciliation $batch, AssetReconciliationItem $item, int &$retired): void
    {
        $ids = collect($item->candidate_asset_ids)->filter()->map(fn ($id) => (int) $id)->values();
        $assets = Asset::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();

        if ($assets->isEmpty()) {
            throw new LogicException("Duplikat untuk baris {$item->source_row} tidak tersedia.");
        }

        $before = $assets->map(fn (Asset $asset) => $this->assetSnapshot($asset))->all();

        foreach ($assets as $asset) {
            $asset->inventory_active = false;
            $asset->sale_notes = trim(($asset->sale_notes ? $asset->sale_notes."\n" : '').'Duplikat dinonaktifkan; keep aset #'.$item->matched_asset_id.' (VEHICLE_AUDIT #'.$batch->id.')');
            $asset->reconciliation_source = $batch->source_system;
            $asset->last_reconciled_at = now();
            $asset->last_reconciliation_id = $batch->id;
            $asset->save();
            $retired++;
        }

        if ($item->matched_asset_id) {
            $keep = Asset::query()->whereKey($item->matched_asset_id)->lockForUpdate()->first();
            if ($keep !== null) {
                if ($item->business_entity_id !== null) {
                    $keep->business_entity_id = $item->business_entity_id;
                }
                if ($keep->serial_number === null && $item->serial_number !== null) {
                    $keep->serial_number = $item->serial_number;
                }
                $keep->reconciliation_source = $batch->source_system;
                $keep->last_reconciled_at = now();
                $keep->last_reconciliation_id = $batch->id;
                $keep->save();
                $this->syncOperationalFields($keep, $item, $batch);
            }
        }

        $afterAssets = Asset::query()->whereKey($ids->push($item->matched_asset_id)->filter())->orderBy('id')->get();
        $item->update([
            'before_snapshot' => $before,
            'after_snapshot' => $afterAssets->map(fn (Asset $asset) => $this->assetSnapshot($asset))->all(),
            'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $categories
     */
    private function applyCreateMissing(
        AssetReconciliation $batch,
        AssetReconciliationItem $item,
        ?int $plateAttributeId,
        array $categories,
        int &$created,
    ): void {
        if ($item->business_entity_id === null) {
            throw new LogicException("Badan usaha target untuk baris {$item->source_row} belum dipetakan.");
        }

        if ($categories === []) {
            throw new LogicException('Kategori MOBIL/MOTOR belum tersedia di master.');
        }

        $payload = $item->raw_payload ?? [];
        $categoryId = $this->guessCategoryId($payload, $categories);

        $asset = Asset::create([
            'business_entity_id' => $item->business_entity_id,
            'name' => $item->item_name,
            'category_id' => $categoryId,
            'type' => Normalizer::text($payload['vehicle_type'] ?? null),
            'serial_number' => $item->serial_number,
            'qty' => 1,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
            'is_available' => true,
            'reconciliation_source' => $batch->source_system,
            'last_reconciled_at' => now(),
            'last_reconciliation_id' => $batch->id,
        ]);

        if ($plateAttributeId !== null && $item->external_item_code !== null) {
            AssetAttribute::create([
                'asset_id' => $asset->id,
                'custom_attribute_id' => $plateAttributeId,
                'attribute_value' => $item->external_item_code,
            ]);
        }

        $this->syncOperationalFields($asset, $item, $batch);

        $item->update([
            'matched_asset_id' => $asset->id,
            'candidate_asset_ids' => [$asset->id],
            'before_snapshot' => null,
            'after_snapshot' => [$this->assetSnapshot($asset->fresh(['attributes', 'assetLocation']))],
            'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $created++;
    }

    private function applyEnrich(
        AssetReconciliation $batch,
        AssetReconciliationItem $item,
        ?int $plateAttributeId,
        int &$updated,
    ): void {
        $asset = Asset::query()->whereKey($item->matched_asset_id)->lockForUpdate()->first();

        if ($asset === null) {
            throw new LogicException("Aset untuk baris {$item->source_row} tidak tersedia.");
        }

        $before = [$this->assetSnapshot($asset)];

        if ($item->business_entity_id !== null) {
            $asset->business_entity_id = $item->business_entity_id;
        }

        if (($asset->name === null || trim($asset->name) === '' || strlen($asset->name) < 4)
            && filled($item->item_name)) {
            $asset->name = $item->item_name;
        }

        if ($asset->serial_number === null && $item->serial_number !== null) {
            $asset->serial_number = $item->serial_number;
        }

        $asset->reconciliation_source = $batch->source_system;
        $asset->last_reconciled_at = now();
        $asset->last_reconciliation_id = $batch->id;
        $asset->save();

        if ($plateAttributeId !== null && $item->external_item_code !== null) {
            $existing = AssetAttribute::query()
                ->where('asset_id', $asset->id)
                ->where('custom_attribute_id', $plateAttributeId)
                ->first();

            if ($existing === null) {
                AssetAttribute::create([
                    'asset_id' => $asset->id,
                    'custom_attribute_id' => $plateAttributeId,
                    'attribute_value' => $item->external_item_code,
                ]);
            } elseif (VehiclePlateNormalizer::display($existing->attribute_value) === null) {
                $existing->update(['attribute_value' => $item->external_item_code]);
            }
        }

        $this->syncOperationalFields($asset, $item, $batch);

        $item->update([
            'before_snapshot' => $before,
            'after_snapshot' => [$this->assetSnapshot($asset->fresh(['attributes', 'assetLocation']))],
            'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
            'applied_at' => now(),
        ]);
        $updated++;
    }

    /** @return Collection<int, Asset> */
    private function loadVehicleAssets(): Collection
    {
        $categoryIds = $this->vehicleCategoryIds();

        $query = Asset::query()->with(['attributes.customAttribute', 'assetLocation:id,name']);

        if ($categoryIds !== []) {
            $query->whereIn('category_id', $categoryIds);
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<string, array<int, int>>
     */
    private function buildPlateIndex(Collection $assets): array
    {
        $index = [];
        $plateAttributeName = Normalizer::key(config('vehicle-asset-reconciliation.plate_attribute_name'));

        foreach ($assets as $asset) {
            $plates = [];

            foreach ($asset->attributes as $attribute) {
                $attrName = Normalizer::key($attribute->customAttribute?->name);

                if ($attrName !== null && $plateAttributeName !== null && $attrName === $plateAttributeName) {
                    $plates[] = VehiclePlateNormalizer::display($attribute->attribute_value);
                }
            }

            $plates[] = VehiclePlateNormalizer::extractFromText($asset->name);
            $plates[] = VehiclePlateNormalizer::extractFromText($asset->serial_number);

            foreach (array_filter($plates) as $plate) {
                $key = VehiclePlateNormalizer::key($plate);
                if ($key !== null) {
                    $index[$key][$asset->id] = $asset->id;
                }
            }
        }

        return array_map(fn (array $ids) => array_values($ids), $index);
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<string, array<int, int>>
     */
    private function buildSerialIndex(Collection $assets): array
    {
        $index = [];

        foreach ($assets as $asset) {
            $candidates = [
                $asset->serial_number,
                VehiclePlateNormalizer::chassis($asset->serial_number),
                VehiclePlateNormalizer::chassis($asset->name),
            ];

            if (preg_match('/(?:NO RANGKA|RANGKA)\s*[:\-]?\s*([A-Z0-9`]+)/i', (string) $asset->name, $matches) === 1) {
                $candidates[] = VehiclePlateNormalizer::chassis($matches[1]);
            }

            foreach ($candidates as $value) {
                $key = Normalizer::key($value);
                if ($key !== null && strlen($key) >= 8) {
                    $index[$key][$asset->id] = $asset->id;
                }
            }
        }

        return array_map(fn (array $ids) => array_values($ids), $index);
    }

    /**
     * @param  Collection<int, BusinessEntity>  $businessEntities
     * @param  array<string, mixed>  $row
     * @return array{0: ?int, 1: ?string}
     */
    private function resolveBusinessEntityId(
        AssetReconciliation $reconciliation,
        array $row,
        Collection $businessEntities,
    ): array {
        $locationKey = Normalizer::key($row['external_location_code'] ?? null);
        $mappings = collect($reconciliation->business_entity_mappings ?? [])
            ->mapWithKeys(function ($id, $location): array {
                $key = Normalizer::key((string) $location);

                return $key === null ? [] : [$key => (int) $id];
            });

        if ($locationKey !== null && $mappings->has($locationKey)) {
            return [(int) $mappings->get($locationKey), null];
        }

        $aliases = config('vehicle-asset-reconciliation.business_entity_aliases', []);
        $marker = $row['external_business_entity_code'] ?? null;
        $stnk = $this->cleanEntityLabel($row['raw_payload']['stnk_name'] ?? null);

        foreach ([$marker, $stnk] as $index => $candidate) {
            if ($candidate === null) {
                continue;
            }

            $aliasTarget = $aliases[$candidate] ?? $aliases[strtoupper((string) $candidate)] ?? null;
            $targetName = $aliasTarget ?? $candidate;
            $entity = $businessEntities->first(
                fn (BusinessEntity $entity) => strcasecmp($entity->name, (string) $targetName) === 0
            );

            if ($entity !== null) {
                return [$entity->id, null];
            }

            // ACC marker must resolve exactly; STNK name falls through to batch default.
            if ($index === 0 && $marker !== null) {
                return [null, "Marker/badan usaha \"{$candidate}\" tidak memiliki master exact."];
            }
        }

        return [$reconciliation->business_entity_id, null];
    }

    private function cleanEntityLabel(?string $value): ?string
    {
        $text = Normalizer::text($value);

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/\s*\/\s*TERJUAL.*$/i', '', $text) ?? $text;
        $text = Normalizer::text($text);

        return $text;
    }

    private function completenessScore(Asset $asset, ?int $plateAttributeId): int
    {
        $score = 0;
        if (filled($asset->serial_number)) {
            $score += 3;
        }
        if (filled($asset->asset_location_id)) {
            $score += 2;
        }
        if (strlen((string) $asset->name) >= 10) {
            $score += 2;
        }
        if ($plateAttributeId !== null
            && $asset->attributes->firstWhere('custom_attribute_id', $plateAttributeId)?->attribute_value) {
            $score += 2;
        }
        if ($asset->inventory_active) {
            $score += 1;
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function needsEnrich(Asset $asset, array $row, ?int $plateAttributeId): bool
    {
        if ($asset->serial_number === null && filled($row['serial_number'] ?? null)) {
            return true;
        }

        $payload = $row['raw_payload'] ?? [];

        foreach ($this->documentFieldMap($payload) as $attributeName => $meta) {
            if ($this->documentNeedsSync($asset, $meta['expires_at'] ?? null, $attributeName)) {
                return true;
            }
        }

        if ($this->textNeedsSync($asset, $payload['holder'] ?? null, config('vehicle-asset-reconciliation.holder_attribute_name', 'Pemegang Inventaris'))) {
            return true;
        }

        $bpkbText = $this->formatBpkbText($payload);
        if ($this->textNeedsSync($asset, $bpkbText, config('vehicle-asset-reconciliation.bpkb_attribute_name', 'BPKB'))) {
            return true;
        }

        if (filled($payload['presence'] ?? null) && $asset->asset_location_id === null) {
            return true;
        }

        if ($plateAttributeId === null) {
            return false;
        }

        $plate = $asset->attributes->firstWhere('custom_attribute_id', $plateAttributeId)?->attribute_value;

        return VehiclePlateNormalizer::display($plate) === null && filled($row['external_item_code'] ?? null);
    }

    private function documentNeedsSync(Asset $asset, mixed $auditExpiresAt, string $attributeName): bool
    {
        if (! filled($auditExpiresAt)) {
            return false;
        }

        $attributeId = CustomAssetAttribute::query()->where('name', $attributeName)->value('id');

        if ($attributeId === null) {
            return false;
        }

        $existing = $asset->attributes->firstWhere('custom_attribute_id', $attributeId);
        $current = null;

        if ($existing !== null) {
            $decoded = json_decode((string) $existing->attribute_value, true);
            $current = is_array($decoded) ? ($decoded['expires_at'] ?? null) : null;
        }

        return $current !== $auditExpiresAt;
    }

    private function textNeedsSync(Asset $asset, mixed $auditValue, string $attributeName): bool
    {
        if (! filled($auditValue)) {
            return false;
        }

        $attributeId = CustomAssetAttribute::query()->where('name', $attributeName)->value('id');

        if ($attributeId === null) {
            return false;
        }

        $existing = $asset->attributes->firstWhere('custom_attribute_id', $attributeId)?->attribute_value;

        return Normalizer::text($existing) !== Normalizer::text($auditValue);
    }

    private function syncOperationalFields(
        Asset $asset,
        AssetReconciliationItem $item,
        AssetReconciliation $batch,
    ): void {
        $this->syncDocumentExpiryAttributes($asset, $item);
        $this->syncTextAttributes($asset, $item);
        $this->syncLocation($asset, $item, $batch);
        $this->syncRecipient($asset, $item);
        $asset->refresh();
    }

    private function syncDocumentExpiryAttributes(Asset $asset, AssetReconciliationItem $item): void
    {
        $payload = $item->raw_payload ?? [];
        $offset = (int) config('vehicle-asset-reconciliation.document_reminder_offset_days', 30);

        foreach ($this->documentFieldMap($payload) as $attributeName => $meta) {
            $expiresAt = $meta['expires_at'] ?? null;

            if (! filled($expiresAt)) {
                continue;
            }

            $attributeId = CustomAssetAttribute::query()->where('name', $attributeName)->value('id');

            if ($attributeId === null) {
                continue;
            }

            $existing = AssetAttribute::query()
                ->where('asset_id', $asset->id)
                ->where('custom_attribute_id', $attributeId)
                ->first();

            $previous = [];
            if ($existing !== null) {
                $decoded = json_decode((string) $existing->attribute_value, true);
                $previous = is_array($decoded) ? $decoded : [];
            }

            $value = AssetAttribute::documentValue([
                'expires_at' => $expiresAt,
                'document_number' => $meta['document_number']
                    ?? $previous['document_number']
                    ?? $item->external_item_code,
                'document_path' => $previous['document_path'] ?? null,
                'notes' => $meta['notes']
                    ?? $previous['notes']
                    ?? ('Disinkron dari audit kendaraan batch #'.$item->asset_reconciliation_id),
                'reminder_start_days' => $previous['reminder_start_days'] ?? $offset,
                'renewed_at' => $previous['renewed_at'] ?? now()->toDateString(),
            ]);

            if ($existing === null) {
                AssetAttribute::create([
                    'asset_id' => $asset->id,
                    'custom_attribute_id' => $attributeId,
                    'attribute_value' => $value,
                ]);
            } else {
                $existing->update(['attribute_value' => $value]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array{expires_at: ?string, document_number?: ?string, notes?: ?string}>
     */
    private function documentFieldMap(array $payload): array
    {
        $insuranceNotes = collect([
            $payload['insurance_name'] ?? null,
            $payload['insurance_type'] ?? null,
            $payload['insurance_insured'] ?? null,
            isset($payload['insurance_join_date']) ? 'Join: '.$payload['insurance_join_date'] : null,
        ])->filter()->implode(' | ');

        return [
            config('vehicle-asset-reconciliation.stnk_attribute_name', 'STNK') => [
                'expires_at' => $payload['stnk_expires_at'] ?? null,
                'document_number' => $payload['license_plate'] ?? null,
            ],
            config('vehicle-asset-reconciliation.kir_attribute_name', 'KIR') => [
                'expires_at' => $payload['kir_expires_at'] ?? null,
                'document_number' => $payload['license_plate'] ?? null,
            ],
            config('vehicle-asset-reconciliation.tax_attribute_name', 'Pajak') => [
                'expires_at' => $payload['tax_expires_at'] ?? null,
                'document_number' => $payload['license_plate'] ?? null,
            ],
            config('vehicle-asset-reconciliation.insurance_attribute_name', 'Asuransi') => [
                'expires_at' => $payload['insurance_expires_at'] ?? null,
                'document_number' => $payload['insurance_policy_number'] ?? ($payload['license_plate'] ?? null),
                'notes' => $insuranceNotes !== '' ? $insuranceNotes : null,
            ],
        ];
    }

    private function syncTextAttributes(Asset $asset, AssetReconciliationItem $item): void
    {
        $payload = $item->raw_payload ?? [];

        $texts = [
            config('vehicle-asset-reconciliation.holder_attribute_name', 'Pemegang Inventaris') => $payload['holder'] ?? null,
            config('vehicle-asset-reconciliation.bpkb_attribute_name', 'BPKB') => $this->formatBpkbText($payload),
        ];

        foreach ($texts as $attributeName => $value) {
            if (! filled($value)) {
                continue;
            }

            $attributeId = CustomAssetAttribute::query()->where('name', $attributeName)->value('id');

            if ($attributeId === null) {
                continue;
            }

            AssetAttribute::query()->updateOrCreate(
                [
                    'asset_id' => $asset->id,
                    'custom_attribute_id' => $attributeId,
                ],
                ['attribute_value' => $value],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function formatBpkbText(array $payload): ?string
    {
        $status = Normalizer::text($payload['bpkb_status'] ?? null);
        $number = Normalizer::text($payload['bpkb_number'] ?? null);

        if ($status === null && $number === null) {
            return null;
        }

        return collect([$status, $number !== null ? 'No: '.$number : null])->filter()->implode(' | ');
    }

    private function syncLocation(
        Asset $asset,
        AssetReconciliationItem $item,
        AssetReconciliation $batch,
    ): void {
        $presence = Normalizer::text($item->raw_payload['presence'] ?? $item->external_location_code);

        if ($presence === null) {
            return;
        }

        $aliases = config('vehicle-asset-reconciliation.location_aliases', []);
        $targetName = $aliases[$presence]
            ?? $aliases[strtoupper($presence)]
            ?? $presence;

        $location = AssetLocation::query()->get(['id', 'name', 'external_code'])->first(
            function (AssetLocation $location) use ($targetName, $presence): bool {
                return Normalizer::key($location->name) === Normalizer::key($targetName)
                    || Normalizer::key($location->external_code) === Normalizer::key($presence)
                    || Normalizer::key($location->name) === Normalizer::key($presence);
            }
        );

        if ($location === null && $batch->auto_create_locations) {
            $location = AssetLocation::create([
                'name' => $targetName,
                'external_code' => Normalizer::key($presence),
            ]);
        }

        if ($location !== null && (int) $asset->asset_location_id !== (int) $location->id) {
            $asset->asset_location_id = $location->id;
            $asset->save();
        }
    }

    private function syncRecipient(Asset $asset, AssetReconciliationItem $item): void
    {
        $holder = Normalizer::text($item->raw_payload['holder'] ?? null);

        if ($holder === null) {
            return;
        }

        // Exact name match only — never fuzzy-guess recipients.
        $matches = User::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($holder)])
            ->limit(2)
            ->get(['id']);

        if ($matches->count() === 1 && (int) $asset->recipient_id !== (int) $matches->first()->id) {
            $asset->recipient_id = $matches->first()->id;
            $asset->condition_status = $asset->condition_status === AssetCondition::Available
                ? AssetCondition::Transferred
                : $asset->condition_status;
            $asset->is_available = false;
            $asset->save();
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, int>  $categories  name=>id actually values are ids keyed by name
     */
    private function guessCategoryId(array $payload, array $categories): int
    {
        $type = strtoupper((string) ($payload['vehicle_type'] ?? ''));
        $name = strtoupper((string) ($payload['vehicle_name'] ?? '').' '.($payload['brand'] ?? ''));

        if ((str_contains($type, 'MOTOR') || str_contains($name, 'MOTOR') || str_contains($name, 'YAMAHA') || str_contains($name, 'HONDA BEAT') || str_contains($name, 'REVO'))
            && isset($categories['MOTOR'])) {
            return $categories['MOTOR'];
        }

        return $categories['MOBIL'] ?? array_values($categories)[0];
    }

    /** @return array<string, int> */
    private function vehicleCategoryIds(): array
    {
        $names = config('vehicle-asset-reconciliation.category_names', ['MOBIL', 'MOTOR']);

        return Category::query()->whereIn('name', $names)->pluck('id', 'name')->all();
    }

    private function plateAttributeId(): ?int
    {
        $name = config('vehicle-asset-reconciliation.plate_attribute_name', 'Plat Nomor');

        return CustomAssetAttribute::query()->where('name', $name)->value('id');
    }

    /** @return array<string, mixed> */
    private function assetSnapshot(Asset $asset): array
    {
        $plate = null;
        $plateAttributeId = $this->plateAttributeId();
        if ($plateAttributeId !== null) {
            $plate = $asset->attributes
                ->firstWhere('custom_attribute_id', $plateAttributeId)
                ?->attribute_value;
        }

        return [
            'id' => $asset->id,
            'name' => $asset->name,
            'business_entity_id' => $asset->business_entity_id,
            'serial_number' => $asset->serial_number,
            'condition_status' => $asset->condition_status instanceof AssetCondition
                ? $asset->condition_status->value
                : $asset->condition_status,
            'inventory_active' => $asset->inventory_active,
            'is_available' => $asset->is_available,
            'license_plate' => $plate,
            'sold_at' => optional($asset->sold_at)?->toDateString(),
            'sold_to' => $asset->sold_to,
        ];
    }
}
