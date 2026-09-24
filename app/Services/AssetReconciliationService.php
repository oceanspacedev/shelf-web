<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetCatalogItem;
use App\Models\AssetLocation;
use App\Models\AssetReconciliation;
use App\Models\AssetReconciliationItem;
use App\Models\BusinessEntity;
use App\Support\AssetReconciliationNormalizer as Normalizer;
use App\Support\StoredFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class AssetReconciliationService
{
    public function __construct(
        private readonly CsaAssetAuditWorkbookParser $parser,
    ) {}

    public function compare(AssetReconciliation $reconciliation): AssetReconciliation
    {
        if ($reconciliation->applied_at !== null) {
            throw new LogicException('Batch yang sudah diterapkan bersifat immutable. Gunakan Compare Ulang.');
        }

        try {
            if ($reconciliation->business_entity_id === null
                || ! BusinessEntity::query()->whereKey($reconciliation->business_entity_id)->exists()) {
                throw new LogicException('Badan usaha wajib dipilih dari master resmi sebelum compare. Sistem tidak akan mengisi atau menebaknya otomatis.');
            }

            $sourceRows = StoredFile::withLocalPath(
                $reconciliation->stored_disk ?: 'local',
                $reconciliation->stored_path,
                fn (string $path): array => $this->parser->parse($path, $reconciliation->source_sheet),
            );
            $rows = $this->aggregateNonSerializedRows($sourceRows);

            DB::transaction(function () use ($reconciliation, $rows, $sourceRows): void {
                $reconciliation->items()->delete();

                $locations = AssetLocation::query()->get(['id', 'name', 'external_code']);
                $catalogItems = AssetCatalogItem::query()
                    ->where('source_system', $reconciliation->source_system)
                    ->get(['id', 'source_system', 'external_code', 'name', 'normalized_name']);
                $businessEntities = BusinessEntity::query()->get(['id', 'name']);
                $assets = Asset::query()
                    ->with('catalogItem:id,source_system,external_code,name,normalized_name')
                    ->get([
                        'id',
                        'business_entity_id',
                        'name',
                        'asset_catalog_item_id',
                        'serial_number',
                        'imei1',
                        'imei2',
                        'qty',
                        'asset_location_id',
                        'inventory_active',
                    ]);

                $locationIndex = $this->buildLocationIndex($locations);
                $catalogIndex = $this->buildCatalogIndex($catalogItems);
                $assetIndexes = $this->buildAssetIndexes($assets);
                $seen = [];
                $counts = ['inline' => 0, 'gap' => 0, 'blocked' => 0];
                $actionCounts = [];
                $businessEntityCounts = [];

                foreach ($rows as $row) {
                    $identityKey = $this->rowIdentityKey($row);
                    $duplicate = isset($seen[$identityKey]);
                    $seen[$identityKey] = true;

                    $locationId = $this->resolveLocationId($row['external_location_code'], $locationIndex);
                    $catalogId = $this->resolveCatalogId($row, $catalogIndex);
                    [$businessEntityId, $businessEntityError] = $this->resolveBusinessEntityId(
                        $reconciliation,
                        $row,
                        $businessEntities,
                    );

                    if ($businessEntityError !== null) {
                        $row['validation_errors'][] = $businessEntityError;
                    }

                    $comparison = $this->compareRow(
                        $row,
                        $locationId,
                        $catalogId,
                        $assets,
                        $assetIndexes,
                        $businessEntityId,
                        $reconciliation->auto_create_locations,
                        $duplicate,
                    );

                    $counts[$comparison['comparison_status']]++;
                    $action = $comparison['action'] ?? 'none';
                    $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;

                    if ($businessEntityId !== null) {
                        $businessEntityCounts[$businessEntityId] = ($businessEntityCounts[$businessEntityId] ?? 0) + 1;
                    }

                    $reconciliation->items()->create([
                        ...$row,
                        'validation_errors' => null,
                        'asset_location_id' => $locationId,
                        'asset_catalog_item_id' => $catalogId,
                        'business_entity_id' => $businessEntityId,
                        ...$comparison,
                    ]);
                }

                $status = $counts['gap'] === 0 && $counts['blocked'] === 0
                    ? AssetReconciliation::STATUS_ALIGNED
                    : AssetReconciliation::STATUS_COMPARED;

                $reconciliation->update([
                    'status' => $status,
                    'total_rows' => count($sourceRows),
                    'inline_rows' => $counts['inline'],
                    'gap_rows' => $counts['gap'],
                    'blocked_rows' => $counts['blocked'],
                    'summary' => [
                        'actions' => $actionCounts,
                        'logical_rows' => count($rows),
                        'locations' => collect($rows)->pluck('external_location_code')->filter()->unique()->count(),
                        'source_sheet' => $reconciliation->source_sheet,
                        'default_business_entity_id' => $reconciliation->business_entity_id,
                        'business_entities' => $businessEntityCounts,
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
                throw new LogicException('Badan usaha batch tidak tersedia. Koreksi dibatalkan tanpa perubahan data.');
            }

            $created = 0;
            $updated = 0;
            $retired = 0;

            $items = $locked->items()
                ->where('comparison_status', AssetReconciliationItem::STATUS_GAP)
                ->orderBy('source_row')
                ->lockForUpdate()
                ->get();

            $businessEntities = BusinessEntity::query()
                ->whereIn('id', $items->pluck('business_entity_id')->filter()->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                if ($item->business_entity_id === null || ! $businessEntities->has($item->business_entity_id)) {
                    throw new LogicException("Badan usaha target untuk baris {$item->source_row} belum dipetakan. Jalankan Compare Ulang sebelum apply.");
                }

                $location = $this->locationForApply($locked, $item);
                $catalogItem = $this->catalogItemForApply($locked, $item);
                $candidateIds = collect($item->candidate_asset_ids)->filter()->map(fn ($id) => (int) $id)->values();
                $assets = $candidateIds->isEmpty()
                    ? new Collection
                    : Asset::query()->whereKey($candidateIds)->orderBy('id')->lockForUpdate()->get();

                if ($item->action === 'create') {
                    $asset = Asset::create([
                        'business_entity_id' => $item->business_entity_id,
                        'name' => $item->item_name,
                        'asset_catalog_item_id' => $catalogItem->id,
                        'serial_number' => $item->serial_number,
                        'qty' => $item->target_qty,
                        'asset_location_id' => $location->id,
                        'condition_status' => AssetCondition::Available,
                        'nbh_status' => NbhStatus::None,
                        'inventory_active' => $item->target_qty > 0,
                        'reconciliation_source' => $locked->source_system,
                        'last_reconciled_at' => now(),
                        'last_reconciliation_id' => $locked->id,
                    ]);

                    $item->update([
                        'asset_location_id' => $location->id,
                        'asset_catalog_item_id' => $catalogItem->id,
                        'matched_asset_id' => $asset->id,
                        'candidate_asset_ids' => [$asset->id],
                        'before_snapshot' => null,
                        'after_snapshot' => [$this->assetSnapshot($asset)],
                        'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
                        'applied_at' => now(),
                    ]);
                    $created++;

                    continue;
                }

                if ($assets->isEmpty()) {
                    throw new LogicException("Aset kandidat untuk baris {$item->source_row} tidak lagi tersedia.");
                }

                $before = $assets->map(fn (Asset $asset) => $this->assetSnapshot($asset))->all();
                $primary = $assets->first();

                foreach ($assets as $index => $asset) {
                    $asset->business_entity_id = $item->business_entity_id;
                    $asset->asset_catalog_item_id = $catalogItem->id;
                    $asset->asset_location_id = $location->id;
                    $asset->inventory_active = $index === 0 && $item->target_qty > 0;
                    $asset->qty = $index === 0 ? $item->target_qty : 0;
                    $asset->reconciliation_source = $locked->source_system;
                    $asset->last_reconciled_at = now();
                    $asset->last_reconciliation_id = $locked->id;

                    if ($index === 0) {
                        $asset->name = $item->item_name;

                        if ($item->serial_number !== null && $this->assetIdentifiers($asset) === []) {
                            $asset->serial_number = $item->serial_number;
                        }
                    }

                    $asset->save();
                }

                $assets = Asset::query()->whereKey($candidateIds)->orderBy('id')->get();
                $item->update([
                    'asset_location_id' => $location->id,
                    'asset_catalog_item_id' => $catalogItem->id,
                    'matched_asset_id' => $primary->id,
                    'before_snapshot' => $before,
                    'after_snapshot' => $assets->map(fn (Asset $asset) => $this->assetSnapshot($asset))->all(),
                    'comparison_status' => AssetReconciliationItem::STATUS_APPLIED,
                    'applied_at' => now(),
                ]);

                $updated++;

                if ($item->target_qty === 0) {
                    $retired++;
                }
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
            throw new LogicException('Pilih badan usaha resmi untuk Compare Ulang. Sistem tidak akan menebaknya dari batch lama.');
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

    /** @return array<string, int> */
    private function buildLocationIndex(Collection $locations): array
    {
        $index = [];

        foreach ($locations as $location) {
            foreach ([$location->external_code, $location->name] as $value) {
                if (($key = Normalizer::key($value)) !== null) {
                    $index[$key] = $location->id;
                }
            }
        }

        return $index;
    }

    /** @return array<string, int> */
    private function buildCatalogIndex(Collection $catalogItems): array
    {
        $index = [];

        foreach ($catalogItems as $item) {
            if (($code = Normalizer::key($item->external_code)) !== null) {
                $index['code:'.$code] = $item->id;
            }

            $index['name:'.$item->normalized_name] = $item->id;
        }

        return $index;
    }

    /** @return array{serial: array<string, array<int, int>>, code_location: array<string, array<int, int>>, name_location: array<string, array<int, int>>} */
    private function buildAssetIndexes(Collection $assets): array
    {
        $indexes = ['serial' => [], 'code_location' => [], 'name_location' => []];

        foreach ($assets as $asset) {
            foreach ($this->assetIdentifiers($asset) as $identity) {
                $indexes['serial'][$identity][$asset->id] = $asset->id;
            }

            if ($asset->asset_location_id !== null) {
                $location = (string) $asset->asset_location_id;
                $code = Normalizer::key($asset->catalogItem?->external_code);
                $name = Normalizer::key($asset->catalogItem?->name ?? $asset->name);

                if ($code !== null) {
                    $indexes['code_location'][$location.'|'.$code][$asset->id] = $asset->id;
                }

                if ($name !== null) {
                    $indexes['name_location'][$location.'|'.$name][$asset->id] = $asset->id;
                }
            }
        }

        return $indexes;
    }

    private function resolveLocationId(?string $externalCode, array $locationIndex): ?int
    {
        $key = Normalizer::key($externalCode);

        return $key === null ? null : ($locationIndex[$key] ?? null);
    }

    private function resolveCatalogId(array $row, array $catalogIndex): ?int
    {
        $code = Normalizer::key($row['external_item_code']);

        if ($code !== null && isset($catalogIndex['code:'.$code])) {
            return $catalogIndex['code:'.$code];
        }

        $name = Normalizer::key($row['item_name']);

        return $name === null ? null : ($catalogIndex['name:'.$name] ?? null);
    }

    /** @return array{0: int|null, 1: string|null} */
    private function resolveBusinessEntityId(
        AssetReconciliation $reconciliation,
        array $row,
        Collection $businessEntities,
    ): array {
        $locationKey = Normalizer::key($row['external_location_code'] ?? null);
        $mapping = collect($reconciliation->business_entity_mappings ?? [])
            ->mapWithKeys(fn ($id, $location): array => [Normalizer::key($location) => (int) $id]);

        if ($locationKey !== null && $mapping->has($locationKey)) {
            $mappedId = $mapping->get($locationKey);

            return $businessEntities->contains('id', $mappedId)
                ? [$mappedId, null]
                : [null, "Mapping badan usaha untuk Gudang {$row['external_location_code']} tidak lagi terdaftar."];
        }

        $sourceCode = strtoupper(trim((string) ($row['external_business_entity_code'] ?? '')));

        if ($sourceCode !== '') {
            $aliases = config('asset-reconciliation.business_entity_aliases', []);
            $exactName = $aliases[$sourceCode] ?? null;

            if (! is_string($exactName) || $exactName === '') {
                return [null, "Kode badan usaha CSA {$sourceCode} belum memiliki mapping resmi."];
            }

            $entity = $businessEntities->first(fn (BusinessEntity $candidate): bool => $candidate->name === $exactName);

            return $entity === null
                ? [null, "Master badan usaha {$exactName} untuk kode CSA {$sourceCode} tidak ditemukan."]
                : [$entity->id, null];
        }

        return [$reconciliation->business_entity_id, null];
    }

    /** @return array<string, mixed> */
    private function compareRow(
        array $row,
        ?int $locationId,
        ?int $catalogId,
        Collection $assets,
        array $indexes,
        ?int $businessEntityId,
        bool $autoCreateLocations,
        bool $duplicate,
    ): array {
        $errors = $row['validation_errors'];

        if ($businessEntityId === null) {
            $errors[] = 'Badan usaha target belum dipetakan.';
        }

        if ($duplicate) {
            $errors[] = 'Identitas Gudang + Item + Serial duplikat di workbook.';
        }

        if ($row['external_location_code'] === null) {
            $errors[] = 'Gudang wajib diisi.';
        } elseif ($locationId === null && ! $autoCreateLocations) {
            $errors[] = 'Gudang belum dipetakan ke Lokasi Shelf.';
        }

        if ($row['item_name'] === null) {
            $errors[] = 'Nama Barang/Item wajib diisi.';
        }

        if ($errors !== []) {
            return [
                'shelf_qty' => null,
                'gap_qty' => null,
                'candidate_asset_ids' => [],
                'match_strategy' => null,
                'matched_asset_id' => null,
                'comparison_status' => AssetReconciliationItem::STATUS_BLOCKED,
                'action' => 'review',
                'message' => implode(' ', $errors),
            ];
        }

        [$candidateIds, $strategy, $ambiguous] = $this->matchCandidates($row, $locationId, $assets, $indexes);

        if ($ambiguous) {
            return [
                'shelf_qty' => $assets->whereIn('id', $candidateIds)->sum('qty'),
                'gap_qty' => null,
                'candidate_asset_ids' => $candidateIds,
                'match_strategy' => $strategy,
                'matched_asset_id' => null,
                'comparison_status' => AssetReconciliationItem::STATUS_BLOCKED,
                'action' => 'review',
                'message' => 'Lebih dari satu aset Shelf cocok dan memiliki identitas serial. Baris harus ditinjau manual.',
            ];
        }

        $matched = $assets->whereIn('id', $candidateIds);
        $shelfQty = (int) $matched->sum('qty');
        $gap = $row['target_qty'] - $shelfQty;
        $metadataDiffers = $matched->contains(function (Asset $asset) use ($row, $locationId, $catalogId, $businessEntityId): bool {
            $catalogDiffers = $catalogId === null || $asset->asset_catalog_item_id !== $catalogId;

            return $asset->business_entity_id !== $businessEntityId
                || ($locationId !== null && $asset->asset_location_id !== $locationId)
                || $catalogDiffers
                || Normalizer::key($asset->name) !== Normalizer::key($row['item_name']);
        });

        if ($candidateIds === []) {
            if ($row['target_qty'] === 0) {
                return [
                    'shelf_qty' => 0,
                    'gap_qty' => 0,
                    'candidate_asset_ids' => [],
                    'match_strategy' => 'none',
                    'matched_asset_id' => null,
                    'comparison_status' => AssetReconciliationItem::STATUS_INLINE,
                    'action' => 'none',
                    'message' => 'Tidak ada aset Shelf dan target audit juga 0.',
                ];
            }

            return [
                'shelf_qty' => 0,
                'gap_qty' => $row['target_qty'],
                'candidate_asset_ids' => [],
                'match_strategy' => 'none',
                'matched_asset_id' => null,
                'comparison_status' => AssetReconciliationItem::STATUS_GAP,
                'action' => 'create',
                'message' => $locationId === null
                    ? 'Aset dan lokasi baru akan dibuat saat koreksi diterapkan.'
                    : 'Aset belum ada di Shelf dan akan dibuat saat koreksi diterapkan.',
            ];
        }

        $inventoryStateAligned = $matched->every(
            fn (Asset $asset): bool => $asset->qty > 0 ? $asset->inventory_active : ! $asset->inventory_active
        );

        if ($gap === 0 && ! $metadataDiffers && $inventoryStateAligned) {
            return [
                'shelf_qty' => $shelfQty,
                'gap_qty' => 0,
                'candidate_asset_ids' => $candidateIds,
                'match_strategy' => $strategy,
                'matched_asset_id' => count($candidateIds) === 1 ? $candidateIds[0] : null,
                'comparison_status' => AssetReconciliationItem::STATUS_INLINE,
                'action' => 'none',
                'message' => 'Kuantitas dan identitas Shelf sudah sesuai hasil audit.',
            ];
        }

        return [
            'shelf_qty' => $shelfQty,
            'gap_qty' => $gap,
            'candidate_asset_ids' => $candidateIds,
            'match_strategy' => $strategy,
            'matched_asset_id' => count($candidateIds) === 1 ? $candidateIds[0] : null,
            'comparison_status' => AssetReconciliationItem::STATUS_GAP,
            'action' => $row['target_qty'] === 0 ? 'retire' : 'adjust',
            'message' => $row['target_qty'] === 0
                ? 'Saldo fisik 0; aset akan dinonaktifkan dari inventori tanpa menghapus riwayat.'
                : 'Kuantitas, identitas, dan badan usaha aset akan diselaraskan ke hasil audit dan pilihan batch.',
        ];
    }

    /** @return array{0: array<int, int>, 1: string, 2: bool} */
    private function matchCandidates(array $row, ?int $locationId, Collection $assets, array $indexes): array
    {
        $serial = Normalizer::identity($row['serial_number']);

        if ($serial !== null && isset($indexes['serial'][$serial])) {
            $ids = array_values($indexes['serial'][$serial]);

            return [$ids, 'serial', count($ids) > 1];
        }

        if ($locationId !== null) {
            $code = Normalizer::key($row['external_item_code']);

            if ($code !== null && isset($indexes['code_location'][$locationId.'|'.$code])) {
                $ids = array_values($indexes['code_location'][$locationId.'|'.$code]);

                return [$ids, 'item_code_location', $this->isAmbiguousGroup($ids, $row, $assets)];
            }

            $name = Normalizer::key($row['item_name']);

            if ($name !== null && isset($indexes['name_location'][$locationId.'|'.$name])) {
                $ids = array_values($indexes['name_location'][$locationId.'|'.$name]);

                return [$ids, 'name_location', $this->isAmbiguousGroup($ids, $row, $assets)];
            }
        }

        return [[], 'none', false];
    }

    private function isAmbiguousGroup(array $ids, array $row, Collection $assets): bool
    {
        if (count($ids) <= 1) {
            return false;
        }

        if (Normalizer::identity($row['serial_number']) !== null) {
            return true;
        }

        return $assets->whereIn('id', $ids)->contains(fn (Asset $asset) => $this->assetIdentifiers($asset) !== []);
    }

    /** @return array<int, string> */
    private function assetIdentifiers(Asset $asset): array
    {
        return collect([$asset->serial_number, $asset->imei1, $asset->imei2])
            ->map(Normalizer::identity(...))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function rowIdentityKey(array $row): string
    {
        return implode('|', [
            Normalizer::key($row['external_business_entity_code'] ?? null) ?? '-',
            Normalizer::key($row['external_location_code']) ?? '-',
            Normalizer::key($row['external_item_code']) ?? Normalizer::key($row['item_name']) ?? '-',
            Normalizer::identity($row['serial_number']) ?? '-',
        ]);
    }

    /**
     * Workbook tertentu menulis satu baris per unit tetapi tidak memberi S/N.
     * Baris seperti itu adalah satu saldo Barang/Item, sehingga wajib dijumlahkan
     * sebelum dibandingkan dengan qty Shelf agar tidak membuat aset duplikat.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function aggregateNonSerializedRows(array $rows): array
    {
        $aggregated = [];

        foreach ($rows as $row) {
            $row['source_rows'] = [$row['source_row']];

            if (Normalizer::identity($row['serial_number']) !== null) {
                $aggregated[] = $row;

                continue;
            }

            $key = implode('|', [
                Normalizer::key($row['external_business_entity_code'] ?? null) ?? '-',
                Normalizer::key($row['external_location_code']) ?? '-',
                Normalizer::key($row['external_item_code']) ?? Normalizer::key($row['item_name']) ?? '-',
            ]);

            if (! isset($aggregated[$key])) {
                $row['raw_payload'] = [
                    'source_rows' => [$row['source_row']],
                    'rows' => [$row['raw_payload']],
                ];
                $aggregated[$key] = $row;

                continue;
            }

            $current = $aggregated[$key];
            $current['source_rows'][] = $row['source_row'];
            $current['source_row'] = min($current['source_row'], $row['source_row']);
            $current['system_qty'] = $this->sumNullable($current['system_qty'], $row['system_qty']);
            $current['physical_qty'] = $current['physical_qty'] === null || $row['physical_qty'] === null
                ? null
                : $current['physical_qty'] + $row['physical_qty'];
            $current['correction_qty'] = $this->sumNullable($current['correction_qty'], $row['correction_qty']);
            $current['target_qty'] += $row['target_qty'];
            $current['notes'] = collect([$current['notes'], $row['notes']])->filter()->unique()->implode(' | ') ?: null;
            $current['validation_errors'] = array_values(array_unique([
                ...$current['validation_errors'],
                ...$row['validation_errors'],
            ]));
            $current['raw_payload']['source_rows'][] = $row['source_row'];
            $current['raw_payload']['rows'][] = $row['raw_payload'];
            $aggregated[$key] = $current;
        }

        return array_values($aggregated);
    }

    private function sumNullable(?int $left, ?int $right): ?int
    {
        if ($left === null && $right === null) {
            return null;
        }

        return ($left ?? 0) + ($right ?? 0);
    }

    private function locationForApply(AssetReconciliation $reconciliation, AssetReconciliationItem $item): AssetLocation
    {
        if ($item->asset_location_id !== null) {
            return AssetLocation::query()->lockForUpdate()->findOrFail($item->asset_location_id);
        }

        if (! $reconciliation->auto_create_locations) {
            throw new LogicException("Gudang {$item->external_location_code} belum dipetakan.");
        }

        return AssetLocation::firstOrCreate(
            ['external_code' => $item->external_location_code],
            [
                'name' => $item->external_location_code,
                'description' => "Dibuat otomatis dari rekonsiliasi {$reconciliation->uuid}",
            ],
        );
    }

    private function catalogItemForApply(AssetReconciliation $reconciliation, AssetReconciliationItem $item): AssetCatalogItem
    {
        if ($item->asset_catalog_item_id !== null) {
            $catalog = AssetCatalogItem::query()->lockForUpdate()->findOrFail($item->asset_catalog_item_id);
            $catalog->update([
                'name' => $item->item_name,
                'normalized_name' => Normalizer::key($item->item_name),
            ]);

            return $catalog;
        }

        $identity = $item->external_item_code !== null
            ? ['source_system' => $reconciliation->source_system, 'external_code' => $item->external_item_code]
            : [
                'source_system' => $reconciliation->source_system,
                'external_code' => null,
                'normalized_name' => Normalizer::key($item->item_name),
            ];

        return AssetCatalogItem::updateOrCreate($identity, [
            'name' => $item->item_name,
            'normalized_name' => Normalizer::key($item->item_name),
        ]);
    }

    /** @return array<string, mixed> */
    private function assetSnapshot(Asset $asset): array
    {
        return [
            'id' => $asset->id,
            'business_entity_id' => $asset->business_entity_id,
            'name' => $asset->name,
            'asset_catalog_item_id' => $asset->asset_catalog_item_id,
            'serial_number' => $asset->serial_number,
            'imei1' => $asset->imei1,
            'imei2' => $asset->imei2,
            'qty' => $asset->qty,
            'asset_location_id' => $asset->asset_location_id,
            'inventory_active' => (bool) $asset->inventory_active,
        ];
    }
}
