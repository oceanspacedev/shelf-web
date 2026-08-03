<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetCatalogItem;
use App\Models\AssetLocation;
use App\Models\BusinessEntity;
use App\Services\CsaAssetAuditWorkbookExporter;
use App\Services\CsaAssetAuditWorkbookParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CsaAssetAuditWorkbookExporterTest extends TestCase
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
            'asset-reconciliation.business_entity_aliases' => [
                'CSN' => 'PT. COMPLETE SOLUSI NUSANTARA',
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

    public function test_export_produces_csa_workbook_that_parser_can_round_trip(): void
    {
        $retailLocation = AssetLocation::create(['name' => 'CS PERUMNAS', 'external_code' => 'TPRM']);
        $csnLocation = AssetLocation::create(['name' => 'Gudang Aktiva', 'external_code' => 'AKTIVA']);

        $retailCatalog = AssetCatalogItem::create([
            'source_system' => 'CSA',
            'external_code' => 'AT-GA0011',
            'name' => 'APAR PYRAMID',
            'normalized_name' => 'apar pyramid',
        ]);
        $csnCatalog = AssetCatalogItem::create([
            'source_system' => 'CSA',
            'external_code' => 'AT-CSN',
            'name' => 'BARCODE SCANNER',
            'normalized_name' => 'barcode scanner',
        ]);

        Asset::create([
            'name' => 'APAR PYRAMID',
            'business_entity_id' => $this->retailBusinessEntityId,
            'asset_catalog_item_id' => $retailCatalog->id,
            'asset_location_id' => $retailLocation->id,
            'serial_number' => 'AST/TPRM/2',
            'qty' => 2,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);

        Asset::create([
            'name' => 'BARCODE SCANNER',
            'business_entity_id' => $this->csnBusinessEntityId,
            'asset_catalog_item_id' => $csnCatalog->id,
            'asset_location_id' => $csnLocation->id,
            'serial_number' => 'VSC-1',
            'qty' => 1,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);

        Asset::create([
            'name' => 'ASET NONAKTIF',
            'business_entity_id' => $this->retailBusinessEntityId,
            'asset_location_id' => $retailLocation->id,
            'qty' => 0,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => false,
        ]);

        $path = storage_path('framework/testing/csa-export-'.uniqid('', true).'.xlsx');
        @mkdir(dirname($path), 0777, true);

        $exportedPath = app(CsaAssetAuditWorkbookExporter::class)->exportToPath($path);

        $this->assertFileExists($exportedPath);

        $parsed = app(CsaAssetAuditWorkbookParser::class)->parse($exportedPath, 'ASET');

        $this->assertCount(2, $parsed);

        $bySerial = collect($parsed)->keyBy('serial_number');

        $this->assertSame('TPRM', $bySerial['AST/TPRM/2']['external_location_code']);
        $this->assertSame('AT-GA0011', $bySerial['AST/TPRM/2']['external_item_code']);
        $this->assertSame('APAR PYRAMID', $bySerial['AST/TPRM/2']['item_name']);
        $this->assertSame(2, $bySerial['AST/TPRM/2']['system_qty']);
        $this->assertNull($bySerial['AST/TPRM/2']['physical_qty']);
        $this->assertNull($bySerial['AST/TPRM/2']['correction_qty']);
        $this->assertNull($bySerial['AST/TPRM/2']['external_business_entity_code']);

        $this->assertSame('AKTIVA', $bySerial['VSC-1']['external_location_code']);
        $this->assertSame('AT-CSN', $bySerial['VSC-1']['external_item_code']);
        $this->assertSame(1, $bySerial['VSC-1']['system_qty']);
        $this->assertSame('CSN', $bySerial['VSC-1']['external_business_entity_code']);

        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::load($exportedPath);
        $this->assertNotNull($reader->getSheetByName('PETUNJUK'));
        $this->assertSame('ASET', $reader->getSheet(0)->getTitle());
        $reader->disconnectWorksheets();

        @unlink($exportedPath);
    }
}
