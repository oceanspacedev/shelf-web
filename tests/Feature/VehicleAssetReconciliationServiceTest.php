<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetLocation;
use App\Models\AssetReconciliation;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use App\Services\VehicleAssetAuditWorkbookParser;
use App\Services\VehicleAssetReconciliationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VehicleAssetReconciliationServiceTest extends TestCase
{
    private int $defaultEntityId;

    private int $mobilCategoryId;

    private int $plateAttributeId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'vehicle-asset-reconciliation.business_entity_aliases' => [
                'MSI' => 'PT MEDIA SELULAR INDONESIA',
                'CS' => 'CV COMPLETE SELULAR',
                'TOP' => 'CV TOP SELULAR',
                'MKLI' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
                'PT MKLI' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
                'PT. MAJU KENDARAAN LISTRIK INDONESIA' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA',
                'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                'PT. MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                'CV TOP SELULAR' => 'CV TOP SELULAR',
                'CV. TOP SELULAR' => 'CV TOP SELULAR',
            ],
            'vehicle-asset-reconciliation.location_aliases' => [
                'HO' => 'HEAD OFFICE PIK',
                'JATIWANGI' => 'AUTO EV JATIWANGI',
            ],
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

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_asset_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('text');
            $table->boolean('required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('category_id')->nullable();
            $table->boolean('is_notifiable')->default(false);
            $table->string('notification_type')->nullable();
            $table->integer('notification_offset')->nullable();
            $table->date('fixed_notification_date')->nullable();
            $table->json('notification_channels')->nullable();
            $table->json('notification_recipient_user_ids')->nullable();
            $table->json('notification_recipient_emails')->nullable();
            $table->json('notification_recipient_whatsapp_numbers')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_attributes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('custom_attribute_id');
            $table->text('attribute_value')->nullable();
            $table->timestamps();
        });

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

        foreach ([
            '2026_09_23_150000_create_asset_qrs_tables',
            '2026_09_24_000001_create_asset_qr_label_histories_table',
            '2026_09_24_000002_batch_asset_qr_label_histories',
            '2026_09_24_000003_add_file_to_asset_qr_label_histories',
            '2026_09_24_000004_add_storage_disks_to_asset_files',
        ] as $fileMigration) {
            (require database_path('migrations/'.$fileMigration.'.php'))->up();
        }

        $this->defaultEntityId = BusinessEntity::create(['name' => 'PT MEDIA SELULAR INDONESIA'])->id;
        BusinessEntity::create(['name' => 'CV COMPLETE SELULAR']);
        BusinessEntity::create(['name' => 'CV TOP SELULAR']);
        BusinessEntity::create(['name' => 'PT. MAJU KENDARAAN LISTRIK INDONESIA']);
        BusinessEntity::create(['name' => 'PT RETAIL INDONESIA SELALU MAJU']);
        BusinessEntity::create(['name' => 'CV BERSAMA CS']);
        BusinessEntity::create(['name' => 'CV MAJU TECNOLOGI']);

        AssetLocation::create(['name' => 'HEAD OFFICE PIK', 'external_code' => 'ho']);
        AssetLocation::create(['name' => 'GUDANG PRIMA CENTER', 'external_code' => 'pc']);
        AssetLocation::create(['name' => 'PURWOKERTO', 'external_code' => 'purwokerto']);
        AssetLocation::create(['name' => 'AUTO EV JATIWANGI', 'external_code' => 'jatiwangi']);

        $this->mobilCategoryId = Category::create(['name' => 'MOBIL'])->id;
        Category::create(['name' => 'MOTOR']);
        $this->plateAttributeId = CustomAssetAttribute::create([
            'name' => 'Plat Nomor',
            'type' => 'text',
            'required' => false,
            'is_active' => true,
        ])->id;
        CustomAssetAttribute::create([
            'name' => 'STNK',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'required' => false,
            'is_active' => true,
            'category_id' => [$this->mobilCategoryId],
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => 30,
        ]);
        CustomAssetAttribute::create([
            'name' => 'KIR',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'required' => false,
            'is_active' => true,
            'category_id' => [$this->mobilCategoryId],
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => 30,
        ]);
        foreach (['Pajak', 'Asuransi'] as $docName) {
            CustomAssetAttribute::create([
                'name' => $docName,
                'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
                'required' => false,
                'is_active' => true,
                'category_id' => [$this->mobilCategoryId],
                'is_notifiable' => true,
                'notification_type' => 'relative_date',
                'notification_offset' => 30,
            ]);
        }
        foreach (['BPKB', 'Pemegang Inventaris'] as $textName) {
            CustomAssetAttribute::create([
                'name' => $textName,
                'type' => CustomAssetAttribute::TYPE_TEXT,
                'required' => false,
                'is_active' => true,
                'category_id' => [$this->mobilCategoryId],
            ]);
        }

        Storage::fake('local');
    }

    public function test_compare_apply_mark_sold_retire_duplicate_create_and_recompare(): void
    {
        $location = AssetLocation::query()->where('name', 'AUTO EV JATIWANGI')->firstOrFail();
        $sold = $this->vehicle('H 9527 EA', 'CARRY', AssetCondition::Available);
        $dupThin = $this->vehicle('H 9526 EA', 'CARRY', AssetCondition::Available, serial: null);
        $dupRich = $this->vehicle('H 9526 EA', 'NEW CARRY 03-PU', AssetCondition::Available, serial: 'MHYHDC61TNJ257726', locationId: $location->id);
        $inline = $this->vehicle('E 8195 BY', 'GRANMAX', AssetCondition::Available);

        $batch = $this->batchFromFixture(autoCreate: true);
        $service = app(VehicleAssetReconciliationService::class);

        $service->compare($batch);
        $batch->refresh();

        $this->assertSame(0, $batch->blocked_rows, $batch->failure_message ?? '');
        $this->assertGreaterThan(0, $batch->gap_rows);

        $byPlate = $batch->items->keyBy('external_item_code');
        $this->assertSame('mark_sold', $byPlate['H 9527 EA']->action);
        $this->assertSame('retire_duplicate', $byPlate['H 9526 EA']->action);
        $this->assertSame($dupRich->id, $byPlate['H 9526 EA']->matched_asset_id);
        $this->assertSame('create_missing', $byPlate['E 9999 ZZ']->action);
        $this->assertSame('enrich', $byPlate['E 8195 BY']->action, 'STNK/KIR dates from audit should trigger enrich');
        $this->assertSame('gap', $byPlate['E 8195 BY']->comparison_status);

        $service->apply($batch);
        $batch->refresh();

        $sold->refresh();
        $dupThin->refresh();
        $dupRich->refresh();
        $inline->refresh();

        $this->assertSame(AssetCondition::Sold, $sold->condition_status);
        $this->assertFalse($sold->inventory_active);
        $this->assertFalse($dupThin->inventory_active);
        $this->assertTrue($dupRich->inventory_active);
        $this->assertSame(AssetCondition::Available, $inline->condition_status);
        $inline->load('attributes');
        $inlineStnkId = CustomAssetAttribute::query()->where('name', 'STNK')->value('id');
        $inlineKirId = CustomAssetAttribute::query()->where('name', 'KIR')->value('id');
        $inlineStnk = json_decode((string) $inline->attributes->firstWhere('custom_attribute_id', $inlineStnkId)?->attribute_value, true);
        $inlineKir = json_decode((string) $inline->attributes->firstWhere('custom_attribute_id', $inlineKirId)?->attribute_value, true);
        $this->assertSame('2027-02-07', $inlineStnk['expires_at'] ?? null);
        $this->assertSame('2026-11-12', $inlineKir['expires_at'] ?? null);
        $this->assertGreaterThanOrEqual(1, $batch->created_rows, 'Expected create_missing to create assets');

        $createdItem = $batch->items()->where('external_item_code', 'E 9999 ZZ')->first();
        $this->assertSame('applied', $createdItem->comparison_status);
        $this->assertNotNull($createdItem->matched_asset_id);
        $created = Asset::query()->with('attributes')->find($createdItem->matched_asset_id);
        $this->assertNotNull($created);
        $this->assertSame('E 9999 ZZ', $created->attributes->firstWhere('custom_attribute_id', $this->plateAttributeId)?->attribute_value);

        $stnkId = CustomAssetAttribute::query()->where('name', 'STNK')->value('id');
        $kirId = CustomAssetAttribute::query()->where('name', 'KIR')->value('id');
        $stnk = json_decode((string) $created->attributes->firstWhere('custom_attribute_id', $stnkId)?->attribute_value, true);
        $kir = json_decode((string) $created->attributes->firstWhere('custom_attribute_id', $kirId)?->attribute_value, true);
        $this->assertSame('2026-11-01', $stnk['expires_at'] ?? null);
        $this->assertSame('2026-09-15', $kir['expires_at'] ?? null);

        $pajakId = CustomAssetAttribute::query()->where('name', 'Pajak')->value('id');
        $asuransiId = CustomAssetAttribute::query()->where('name', 'Asuransi')->value('id');
        $bpkbId = CustomAssetAttribute::query()->where('name', 'BPKB')->value('id');
        $holderId = CustomAssetAttribute::query()->where('name', 'Pemegang Inventaris')->value('id');
        $pajak = json_decode((string) $created->attributes->firstWhere('custom_attribute_id', $pajakId)?->attribute_value, true);
        $asuransi = json_decode((string) $created->attributes->firstWhere('custom_attribute_id', $asuransiId)?->attribute_value, true);
        $this->assertSame('2026-10-01', $pajak['expires_at'] ?? null);
        $this->assertSame('2026-05-01', $asuransi['expires_at'] ?? null);
        $this->assertSame('POL5', $asuransi['document_number'] ?? null);
        $this->assertSame('ADA | No: N-9999', $created->attributes->firstWhere('custom_attribute_id', $bpkbId)?->attribute_value);
        $this->assertSame('TEST HOLDER', $created->attributes->firstWhere('custom_attribute_id', $holderId)?->attribute_value);

        $dupRich->load('attributes');
        $carryStnk = json_decode((string) $dupRich->attributes->firstWhere('custom_attribute_id', $stnkId)?->attribute_value, true);
        $carryKir = json_decode((string) $dupRich->attributes->firstWhere('custom_attribute_id', $kirId)?->attribute_value, true);
        $this->assertSame('2027-02-07', $carryStnk['expires_at'] ?? null);
        $this->assertSame('2026-10-02', $carryKir['expires_at'] ?? null);

        $verification = $service->recompare($batch->fresh());
        $this->assertContains($verification->status, [
            AssetReconciliation::STATUS_ALIGNED,
            AssetReconciliation::STATUS_COMPARED,
        ]);
        $verifyByPlate = $verification->items->keyBy('external_item_code');
        $this->assertSame('inline', $verifyByPlate['H 9527 EA']->comparison_status);
        $this->assertSame('inline', $verifyByPlate['H 9526 EA']->comparison_status);
        $this->assertSame('inline', $verifyByPlate['E 9999 ZZ']->comparison_status);
    }

    public function test_tc_va_009_unknown_acc_marker_is_blocked(): void
    {
        $path = $this->writeWorkbook([
            ['NO', 'NOMOR POLISI', 'NAMA STNK', 'ACCOUNTING', 'CEK KEBERADAAN UNIT', 'NAMA KENDARAAN'],
            [1, 'B 1111 AA', 'PT UNKNOWN', 'ZZZ_UNKNOWN', 'HO', 'TEST CAR'],
        ]);

        $batch = $this->batchFromPath($path, autoCreate: false);
        app(VehicleAssetReconciliationService::class)->compare($batch);
        $batch->refresh();

        $this->assertSame(1, $batch->blocked_rows);
        $item = $batch->items->first();
        $this->assertSame('blocked', $item->comparison_status);
        $this->assertStringContainsString('ZZZ_UNKNOWN', (string) $item->message);
    }

    public function test_tc_va_010_create_flag_off_blocks_missing_plate(): void
    {
        $this->vehicle('H 9527 EA', 'CARRY', AssetCondition::Available);
        $this->vehicle('H 9526 EA', 'NEW CARRY', AssetCondition::Available, serial: 'MHYHDC61TNJ257726');
        $this->vehicle('E 8195 BY', 'GRANMAX', AssetCondition::Available);

        $batch = $this->batchFromFixture(autoCreate: false);
        app(VehicleAssetReconciliationService::class)->compare($batch);
        $batch->refresh();

        $missing = $batch->items->firstWhere('external_item_code', 'E 9999 ZZ');
        $this->assertNotNull($missing);
        $this->assertSame('blocked', $missing->comparison_status);
        $this->assertSame('review', $missing->action);
        $this->assertGreaterThan(0, $batch->blocked_rows);
    }

    public function test_tc_va_011_second_apply_is_rejected(): void
    {
        $this->vehicle('H 9527 EA', 'CARRY', AssetCondition::Available);
        $batch = $this->batchFromMinimalSoldRow();
        $service = app(VehicleAssetReconciliationService::class);
        $service->compare($batch);
        $service->apply($batch->fresh());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('sudah pernah diterapkan');
        $service->apply($batch->fresh());
    }

    public function test_tc_va_012_orphan_shelf_plates_are_counted(): void
    {
        $this->vehicle('H 9527 EA', 'CARRY', AssetCondition::Available);
        $this->vehicle('Z 0000 OR', 'ORPHAN', AssetCondition::Available);

        $batch = $this->batchFromMinimalSoldRow();
        app(VehicleAssetReconciliationService::class)->compare($batch);
        $batch->refresh();

        $this->assertSame(1, $batch->summary['orphan_shelf_plates'] ?? null);
    }

    public function test_tc_va_013_location_alias_sync_on_apply(): void
    {
        $asset = $this->vehicle('B 2222 BB', 'SIGRA', AssetCondition::Available);
        $path = $this->writeWorkbook([
            ['NO', 'NOMOR POLISI', 'NAMA STNK', 'ACCOUNTING', 'CEK KEBERADAAN UNIT', 'NAMA KENDARAAN', 'EXPIRED STNK H-45'],
            [1, 'B 2222 BB', 'PT MEDIA SELULAR INDONESIA', 'MSI', 'JATIWANGI', 'SIGRA', '2027-01-01'],
        ]);

        $batch = $this->batchFromPath($path, autoCreate: false);
        $service = app(VehicleAssetReconciliationService::class);
        $service->compare($batch);
        $batch->refresh();
        $this->assertSame(0, $batch->blocked_rows, $batch->failure_message ?? '');
        $service->apply($batch->fresh());

        $asset->refresh();
        $expected = AssetLocation::query()->where('name', 'AUTO EV JATIWANGI')->value('id');
        $this->assertSame($expected, $asset->asset_location_id);
    }

    public function test_tc_va_014_unique_holder_sets_recipient_id(): void
    {
        $user = User::create(['name' => 'BU RIKA']);
        $asset = $this->vehicle('B 3333 CC', 'AYLA', AssetCondition::Available);
        $path = $this->writeWorkbook([
            ['NO', 'NOMOR POLISI', 'NAMA STNK', 'ACCOUNTING', 'CEK KEBERADAAN UNIT', 'NAMA KENDARAAN', 'PEMEGANG INVENTARIS', 'EXPIRED STNK H-45'],
            [1, 'B 3333 CC', 'PT MEDIA SELULAR INDONESIA', 'MSI', 'HO', 'AYLA', 'BU RIKA', '2027-01-01'],
        ]);

        $batch = $this->batchFromPath($path, autoCreate: false);
        $service = app(VehicleAssetReconciliationService::class);
        $service->compare($batch);
        $this->assertSame(0, $batch->fresh()->blocked_rows);
        $service->apply($batch->fresh());

        $asset->refresh();
        $this->assertSame($user->id, $asset->recipient_id);
    }

    public function test_validate_masters_command_passes_when_aliases_resolve(): void
    {
        $exit = Artisan::call('vehicle-audit:validate-masters');
        $this->assertSame(0, $exit, Artisan::output());
    }

    public function test_validate_masters_command_fails_when_target_missing(): void
    {
        config([
            'vehicle-asset-reconciliation.business_entity_aliases' => [
                'GHOST' => 'PT TIDAK ADA SAMA SEKALI',
            ],
        ]);

        $exit = Artisan::call('vehicle-audit:validate-masters');
        $this->assertSame(1, $exit);
        $this->assertStringContainsString('PT TIDAK ADA SAMA SEKALI', Artisan::output());
    }

    private function vehicle(
        string $plate,
        string $name,
        AssetCondition $condition,
        ?string $serial = 'SERIAL',
        ?int $locationId = null,
    ): Asset {
        $asset = Asset::create([
            'name' => $name.' '.$plate,
            'category_id' => $this->mobilCategoryId,
            'business_entity_id' => $this->defaultEntityId,
            'serial_number' => $serial,
            'asset_location_id' => $locationId,
            'qty' => 1,
            'condition_status' => $condition,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
            'is_available' => true,
        ]);

        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $this->plateAttributeId,
            'attribute_value' => $plate,
        ]);

        return $asset;
    }

    private function batchFromFixture(bool $autoCreate = true): AssetReconciliation
    {
        $fixture = base_path('tests/fixtures/vehicle-audit-sample.xlsx');
        $stored = 'asset-reconciliations/vehicle-sample.xlsx';
        Storage::disk('local')->put($stored, file_get_contents($fixture));

        return AssetReconciliation::create([
            'source_system' => 'VEHICLE_AUDIT',
            'source_sheet' => VehicleAssetAuditWorkbookParser::DEFAULT_SHEET,
            'business_entity_id' => $this->defaultEntityId,
            'original_filename' => 'vehicle-audit-sample.xlsx',
            'stored_path' => $stored,
            'file_sha256' => hash_file('sha256', $fixture),
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'auto_create_locations' => $autoCreate,
            'imported_by' => null,
        ]);
    }

    private function batchFromMinimalSoldRow(): AssetReconciliation
    {
        $path = $this->writeWorkbook([
            ['NO', 'NOMOR POLISI', 'NAMA STNK', 'ACCOUNTING', 'CEK KEBERADAAN UNIT', 'NAMA KENDARAAN'],
            [1, 'H 9527 EA', 'PT MKLI / TERJUAL', 'TERJUAL', 'HO', 'CARRY'],
        ]);

        return $this->batchFromPath($path, autoCreate: false);
    }

    private function batchFromPath(string $absolutePath, bool $autoCreate): AssetReconciliation
    {
        $stored = 'asset-reconciliations/'.basename($absolutePath);
        Storage::disk('local')->put($stored, file_get_contents($absolutePath));

        return AssetReconciliation::create([
            'source_system' => 'VEHICLE_AUDIT',
            'source_sheet' => VehicleAssetAuditWorkbookParser::DEFAULT_SHEET,
            'business_entity_id' => $this->defaultEntityId,
            'original_filename' => basename($absolutePath),
            'stored_path' => $stored,
            'file_sha256' => hash_file('sha256', $absolutePath),
            'status' => AssetReconciliation::STATUS_PROCESSING,
            'auto_create_locations' => $autoCreate,
            'imported_by' => null,
        ]);
    }

    /** @param list<list<mixed>> $rows including header */
    private function writeWorkbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(VehicleAssetAuditWorkbookParser::DEFAULT_SHEET);

        foreach ($rows as $rowIndex => $columns) {
            foreach ($columns as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
            }
        }

        $path = storage_path('framework/testing/vehicle-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
