<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetCatalogItem;
use App\Models\AssetLocation;
use App\Models\AssetReconciliation;
use App\Models\AssetReconciliationItem;
use App\Models\BusinessEntity;
use App\Services\AssetReconciliationService;
use App\Services\CsaAssetAuditWorkbookParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class AssetReconciliationServiceTest extends TestCase
{
    private int $retailBusinessEntityId;

    private int $csnBusinessEntityId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('format')->nullable();
            $table->string('color')->nullable();
            $table->string('letterhead')->nullable();
            $table->timestamps();
        });

        $this->retailBusinessEntityId = BusinessEntity::create([
            'name' => 'PT. RETAIL INDONESIA SELALU MAJU',
        ])->id;
        $this->csnBusinessEntityId = BusinessEntity::create([
            'name' => 'PT. COMPLETE SOLUSI NUSANTARA',
        ])->id;

        Schema::create('asset_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('imei1')->nullable();
            $table->string('imei2')->nullable();
            $table->bigInteger('item_price')->nullable();
            $table->unsignedBigInteger('asset_location_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('condition_status')->default(AssetCondition::Available->value);
            $table->string('nbh_status')->default(NbhStatus::None->value);
            $table->date('nbh_reported_at')->nullable();
            $table->string('audit_document_path')->nullable();
            $table->string('nbh_document_path')->nullable();
            $table->text('nbh_notes')->nullable();
            $table->unsignedBigInteger('nbh_responsible_user_id')->nullable();
            $table->date('sold_at')->nullable();
            $table->string('sold_to')->nullable();
            $table->bigInteger('sold_price')->nullable();
            $table->string('sale_document_path')->nullable();
            $table->text('sale_notes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('recipient_business_entity_id')->nullable();
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_08_01_000001_create_asset_reconciliation_tables.php');
        $migration->up();
        $businessEntityMigration = require database_path('migrations/2026_08_01_000002_add_business_entity_to_asset_reconciliations.php');
        $businessEntityMigration->up();
        $perItemBusinessEntityMigration = require database_path('migrations/2026_08_01_000003_add_per_item_business_entity_to_asset_reconciliations.php');
        $perItemBusinessEntityMigration->up();
    }

    public function test_compare_apply_and_recompare_make_shelf_inline_without_deleting_the_asset(): void
    {
        $location = AssetLocation::create(['name' => 'CS PERUMNAS', 'external_code' => 'TPRM']);
        $asset = Asset::create([
            'name' => 'APAR PYRAMID',
            'asset_location_id' => $location->id,
            'qty' => 2,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);

        $service = $this->serviceForRows([
            $this->row(2, 'TPRM', 'AT-GA0011', 'APAR PYRAMID', 2, 1, -1),
        ]);
        $batch = $this->batch();

        $service->compare($batch);
        $batch->refresh();

        $this->assertSame(1, $batch->gap_rows);
        $this->assertSame('adjust', $batch->items()->first()->action);
        $this->assertSame(2, $batch->items()->first()->shelf_qty);
        $this->assertSame(-1, $batch->items()->first()->gap_qty);

        $service->apply($batch);
        $asset->refresh();

        $this->assertSame(1, $asset->qty);
        $this->assertSame($this->retailBusinessEntityId, $asset->business_entity_id);
        $this->assertTrue($asset->inventory_active);
        $this->assertNotNull($asset->asset_catalog_item_id);
        $this->assertDatabaseHas('asset_catalog_items', [
            'external_code' => 'AT-GA0011',
            'name' => 'APAR PYRAMID',
        ]);

        $verification = $service->recompare($batch->fresh());

        $this->assertSame(AssetReconciliation::STATUS_ALIGNED, $verification->status);
        $this->assertSame(1, $verification->inline_rows);
        $this->assertSame(0, $verification->gap_rows);
        $this->assertDatabaseCount('assets', 1);
    }

    public function test_apply_creates_missing_location_catalog_and_asset_for_physical_surplus(): void
    {
        $service = $this->serviceForRows([
            $this->row(10, 'TNEW', 'AT-NEW', 'RAK BARU', 0, 2, 2),
        ]);
        $batch = $this->batch(autoCreateLocations: true);

        $service->compare($batch);
        $batch->refresh();

        $this->assertSame(0, $batch->blocked_rows);
        $this->assertSame('create', $batch->items()->first()->action);

        $service->apply($batch);

        $location = AssetLocation::where('external_code', 'TNEW')->firstOrFail();
        $catalog = AssetCatalogItem::where('external_code', 'AT-NEW')->firstOrFail();
        $asset = Asset::where('name', 'RAK BARU')->firstOrFail();

        $this->assertSame($location->id, $asset->asset_location_id);
        $this->assertSame($this->retailBusinessEntityId, $asset->business_entity_id);
        $this->assertSame($catalog->id, $asset->asset_catalog_item_id);
        $this->assertSame(2, $asset->qty);
    }

    public function test_repeated_unit_rows_without_serial_are_aggregated_into_one_asset_balance(): void
    {
        $service = $this->serviceForRows([
            $this->row(40, 'TPLAZA', 'AT-GA0059', 'KURSI TINGGI', 1, 1, 0),
            $this->row(41, 'TPLAZA', 'AT-GA0059', 'KURSI TINGGI', 1, 1, 0),
            $this->row(42, 'TPLAZA', 'AT-GA0059', 'KURSI TINGGI', 1, 1, 0),
        ]);
        $batch = $this->batch();

        $service->compare($batch);

        $this->assertSame(3, $batch->fresh()->total_rows);
        $this->assertSame(1, $batch->items()->count());
        $this->assertSame([40, 41, 42], $batch->items()->first()->source_rows);
        $this->assertSame(3, $batch->items()->first()->target_qty);

        $service->apply($batch->fresh());

        $this->assertDatabaseCount('assets', 1);
        $this->assertSame(3, Asset::firstOrFail()->qty);
    }

    public function test_serialized_candidates_block_ambiguous_aggregate_rows(): void
    {
        $location = AssetLocation::create(['name' => 'Gudang', 'external_code' => 'GDG']);

        foreach (['SERIAL-A', 'SERIAL-B'] as $serial) {
            Asset::create([
                'name' => 'MONITOR BENQ',
                'asset_location_id' => $location->id,
                'serial_number' => $serial,
                'qty' => 1,
                'condition_status' => AssetCondition::Available,
                'nbh_status' => NbhStatus::None,
                'inventory_active' => true,
            ]);
        }

        $service = $this->serviceForRows([
            $this->row(20, 'GDG', null, 'MONITOR BENQ', 2, 1, -1),
        ]);
        $batch = $this->batch();

        $service->compare($batch);
        $batch->refresh();

        $this->assertSame(1, $batch->blocked_rows);
        $this->assertSame(AssetReconciliationItem::STATUS_BLOCKED, $batch->items()->first()->comparison_status);

        $this->expectException(LogicException::class);
        $service->apply($batch);
    }

    public function test_multiple_non_serial_assets_are_consolidated_and_stay_inline_on_verification(): void
    {
        $location = AssetLocation::create(['name' => 'Gudang', 'external_code' => 'GDG']);

        foreach ([1, 2] as $sequence) {
            Asset::create([
                'name' => 'KURSI BAR',
                'asset_location_id' => $location->id,
                'qty' => 1,
                'condition_status' => AssetCondition::Available,
                'nbh_status' => NbhStatus::None,
                'inventory_active' => true,
            ]);
        }

        $service = $this->serviceForRows([
            $this->row(25, 'GDG', 'AT-GA0052', 'KURSI BAR', 2, 1, -1),
        ]);
        $batch = $this->batch();

        $service->compare($batch);
        $this->assertSame('adjust', $batch->items()->first()->action);

        $service->apply($batch);

        $this->assertSame([0, 1], Asset::orderBy('qty')->pluck('qty')->all());
        $this->assertSame([false, true], Asset::orderBy('qty')->pluck('inventory_active')->map(fn ($value) => (bool) $value)->all());

        $verification = $service->recompare($batch->fresh());

        $this->assertSame(AssetReconciliation::STATUS_ALIGNED, $verification->status);
        $this->assertSame(1, $verification->inline_rows);
    }

    public function test_apply_is_idempotent_and_a_zero_target_keeps_auditable_asset_history(): void
    {
        $location = AssetLocation::create(['name' => 'Gudang', 'external_code' => 'GDG']);
        $asset = Asset::create([
            'name' => 'GENSET KRISBOW',
            'asset_location_id' => $location->id,
            'qty' => 1,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);
        $service = $this->serviceForRows([
            $this->row(30, 'GDG', 'AT-GA0028', 'GENSET KRISBOW', 1, null, -1),
        ]);
        $batch = $this->batch();

        $service->compare($batch);
        $service->apply($batch);

        $asset->refresh();
        $this->assertSame(0, $asset->qty);
        $this->assertFalse($asset->inventory_active);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);

        $this->expectException(LogicException::class);
        $service->apply($batch->fresh());
    }

    public function test_compare_rejects_a_batch_without_an_explicit_business_entity(): void
    {
        $service = $this->serviceForRows([
            $this->row(50, 'GDG', 'AT-001', 'MEJA', 0, 1, 1),
        ]);
        $batch = $this->batch();
        $batch->update(['business_entity_id' => null]);

        try {
            $service->compare($batch->fresh());
            $this->fail('Compare seharusnya ditolak tanpa badan usaha.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Badan usaha wajib dipilih', $exception->getMessage());
        }

        $this->assertSame(AssetReconciliation::STATUS_FAILED, $batch->fresh()->status);
    }

    public function test_explicit_batch_business_entity_replaces_a_different_existing_value_only_on_apply(): void
    {
        $otherEntity = BusinessEntity::create(['name' => 'PT BADAN USAHA LAIN']);
        $location = AssetLocation::create(['name' => 'Gudang', 'external_code' => 'GDG']);
        Asset::create([
            'business_entity_id' => $otherEntity->id,
            'name' => 'MEJA RETAIL',
            'asset_location_id' => $location->id,
            'qty' => 1,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);

        $service = $this->serviceForRows([
            $this->row(51, 'GDG', null, 'MEJA RETAIL', 1, 1, 0),
        ]);
        $batch = $this->batch();

        $service->compare($batch);

        $this->assertSame(0, $batch->fresh()->blocked_rows);
        $this->assertSame(1, $batch->fresh()->gap_rows);
        $this->assertSame($otherEntity->id, Asset::firstOrFail()->business_entity_id);

        $service->apply($batch->fresh());

        $this->assertSame($this->retailBusinessEntityId, Asset::firstOrFail()->business_entity_id);
    }

    public function test_legacy_batch_requires_and_accepts_an_explicit_business_entity_for_recompare(): void
    {
        $service = $this->serviceForRows([
            $this->row(52, 'GDG', 'AT-LEGACY', 'LEMARI', 0, 1, 1),
        ]);
        $legacy = $this->batch();
        $service->compare($legacy);
        $legacy->update(['business_entity_id' => null]);

        $followUp = $service->recompare($legacy->fresh(), null, $this->retailBusinessEntityId);

        $this->assertSame($legacy->id, $followUp->parent_id);
        $this->assertSame($this->retailBusinessEntityId, $followUp->business_entity_id);
        $this->assertSame(1, $followUp->gap_rows);
    }

    public function test_explicit_csn_marker_uses_the_exact_csn_master_instead_of_the_batch_default(): void
    {
        $service = $this->serviceForRows([
            $this->row(53, 'AKTIVA', 'AT-CSN', 'BARCODE SCANNER', 0, 1, 1, null, 'CSN'),
        ]);
        $batch = $this->batch();

        $service->compare($batch);

        $item = $batch->items()->firstOrFail();
        $this->assertSame('CSN', $item->external_business_entity_code);
        $this->assertSame($this->csnBusinessEntityId, $item->business_entity_id);

        $service->apply($batch->fresh());

        $this->assertSame($this->csnBusinessEntityId, Asset::firstOrFail()->business_entity_id);
    }

    public function test_location_override_wins_and_unknown_source_alias_is_blocked(): void
    {
        $service = $this->serviceForRows([
            $this->row(54, 'AKTIVA', 'AT-OVERRIDE', 'LEMARI CSN', 0, 1, 1, null, 'CSN'),
            $this->row(55, 'UNKNOWN', 'AT-UNKNOWN', 'MEJA UNKNOWN', 0, 1, 1, null, 'UNKNOWN'),
        ]);
        $batch = $this->batch();
        $batch->update([
            'business_entity_mappings' => ['AKTIVA' => $this->retailBusinessEntityId],
        ]);

        $service->compare($batch->fresh());

        $override = $batch->items()->where('external_location_code', 'AKTIVA')->firstOrFail();
        $unknown = $batch->items()->where('external_location_code', 'UNKNOWN')->firstOrFail();
        $this->assertSame($this->retailBusinessEntityId, $override->business_entity_id);
        $this->assertSame(AssetReconciliationItem::STATUS_BLOCKED, $unknown->comparison_status);
        $this->assertStringContainsString('UNKNOWN belum memiliki mapping resmi', $unknown->message);
    }

    public function test_apply_rejects_legacy_staging_items_without_a_per_item_business_entity(): void
    {
        $service = $this->serviceForRows([
            $this->row(56, 'GDG', 'AT-LEGACY-ITEM', 'RAK LEGACY', 0, 1, 1),
        ]);
        $batch = $this->batch();
        $service->compare($batch);
        $batch->items()->update(['business_entity_id' => null]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('belum dipetakan');
        $service->apply($batch->fresh());
    }

    private function batch(bool $autoCreateLocations = true): AssetReconciliation
    {
        return AssetReconciliation::create([
            'source_system' => 'CSA',
            'source_sheet' => 'ASET',
            'business_entity_id' => $this->retailBusinessEntityId,
            'original_filename' => 'audit.xlsx',
            'stored_path' => 'ignored-by-test.xlsx',
            'file_sha256' => str_repeat('a', 64),
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'auto_create_locations' => $autoCreateLocations,
        ]);
    }

    private function serviceForRows(array $rows): AssetReconciliationService
    {
        $parser = new class($rows) extends CsaAssetAuditWorkbookParser
        {
            public function __construct(private readonly array $rows) {}

            public function parse(string $path, string $sheetName = 'ASET'): array
            {
                return $this->rows;
            }
        };

        return new AssetReconciliationService($parser);
    }

    private function row(
        int $sourceRow,
        string $location,
        ?string $itemCode,
        string $name,
        ?int $system,
        ?int $physical,
        ?int $correction,
        ?string $serial = null,
        ?string $externalBusinessEntityCode = null,
    ): array {
        $target = $physical ?? (($system ?? 0) + ($correction ?? 0));

        return [
            'source_row' => $sourceRow,
            'source_rows' => [$sourceRow],
            'external_business_entity_code' => $externalBusinessEntityCode,
            'external_location_code' => $location,
            'external_item_code' => $itemCode,
            'item_name' => $name,
            'serial_number' => $serial,
            'system_qty' => $system,
            'physical_qty' => $physical,
            'correction_qty' => $correction,
            'target_qty' => $target,
            'notes' => null,
            'validation_errors' => [],
            'raw_payload' => [],
        ];
    }
}
