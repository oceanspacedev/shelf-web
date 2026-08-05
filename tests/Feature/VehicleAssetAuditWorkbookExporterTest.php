<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Services\VehicleAssetAuditWorkbookExporter;
use App\Services\VehicleAssetAuditWorkbookParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class VehicleAssetAuditWorkbookExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'activitylog.enabled' => false,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('custom_asset_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('text');
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
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->string('type')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedBigInteger('asset_location_id')->nullable();
            $table->integer('qty')->default(1);
            $table->string('condition_status')->default(AssetCondition::Available->value);
            $table->string('nbh_status')->default(NbhStatus::None->value);
            $table->unsignedBigInteger('nbh_responsible_user_id')->nullable();
            $table->date('nbh_reported_at')->nullable();
            $table->string('audit_document_path')->nullable();
            $table->string('nbh_document_path')->nullable();
            $table->text('nbh_notes')->nullable();
            $table->date('sold_at')->nullable();
            $table->string('sold_to')->nullable();
            $table->bigInteger('sold_price')->nullable();
            $table->string('sale_document_path')->nullable();
            $table->text('sale_notes')->nullable();
            $table->boolean('inventory_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->timestamps();
        });

        $entity = BusinessEntity::create(['name' => 'PT MEDIA SELULAR INDONESIA']);
        $category = Category::create(['name' => 'MOBIL']);
        $plate = CustomAssetAttribute::create(['name' => 'Plat Nomor', 'type' => 'text']);
        $asset = Asset::create([
            'name' => 'GRANMAX',
            'business_entity_id' => $entity->id,
            'category_id' => $category->id,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'inventory_active' => true,
        ]);
        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $plate->id,
            'attribute_value' => 'E 8195 BY',
        ]);
    }

    public function test_export_builds_monitoring_asset_sheet(): void
    {
        $spreadsheet = app(VehicleAssetAuditWorkbookExporter::class)->build();
        $this->assertSame('Monitoring Asset', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('NOMOR POLISI', $spreadsheet->getSheet(0)->getCell('B2')->getValue());
        $this->assertSame('E 8195 BY', $spreadsheet->getSheet(0)->getCell('B3')->getValue());
        $this->assertSame('PETUNJUK', $spreadsheet->getSheet(1)->getTitle());
    }

    public function test_export_parser_round_trip_preserves_plate(): void
    {
        $spreadsheet = app(VehicleAssetAuditWorkbookExporter::class)->build();
        $path = storage_path('framework/testing/vehicle-export-roundtrip-'.uniqid('', true).'.xlsx');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        (new Xlsx($spreadsheet))->save($path);

        $parsed = app(VehicleAssetAuditWorkbookParser::class)->parse($path);
        $this->assertNotEmpty($parsed);
        $this->assertSame('E 8195 BY', $parsed[0]['external_item_code']);
        @unlink($path);
    }
}
