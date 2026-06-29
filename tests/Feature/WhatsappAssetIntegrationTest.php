<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\AssetLocation;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\JobTitle;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappAssetIntegrationTest extends TestCase
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

        $this->createSchema();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-28 10:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_whatsapp_asset_query_is_public_for_ita_functional_testing(): void
    {
        $asset = $this->createAssetFixture();

        $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.search_asset',
            'query' => [
                'serial_number' => 'SN-ASSET-001',
            ],
        ])->assertOk()
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('items.0.asset_id', $asset->id);
    }

    public function test_it_returns_asset_by_serial_number_for_ita(): void
    {
        $asset = $this->createAssetFixture();

        $response = $this->postJson(route('integrations.whatsapp.assets.query'), [
            'request_id' => 'trace-search-001',
            'route' => 'shelf.search_asset',
            'intent' => 'search_asset',
            'sender_phone' => '628123456789',
            'query' => [
                'serial_number' => 'SN-ASSET-001',
            ],
            'contract_version' => '1.2',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.asset_id', $asset->id)
            ->assertJsonPath('items.0.asset_code', (string) $asset->id)
            ->assertJsonPath('items.0.name', 'Laptop Lenovo ThinkPad')
            ->assertJsonPath('items.0.category.name', 'BARANG ELEKTRONIK')
            ->assertJsonPath('items.0.brand.name', 'Lenovo')
            ->assertJsonPath('items.0.location.name', 'HO Bandung')
            ->assertJsonPath('items.0.recipient.name', 'Budi')
            ->assertJsonPath('items.0.condition_status.value', 'transferred')
            ->assertJsonPath('items.0.history', []);
    }

    public function test_it_returns_asset_by_custom_serial_number_for_ita(): void
    {
        $asset = $this->createAssetFixture();
        $asset->forceFill(['serial_number' => null])->save();

        $serialAttribute = CustomAssetAttribute::create([
            'name' => 'Serial Number',
            'type' => CustomAssetAttribute::TYPE_TEXT,
            'required' => false,
            'is_active' => true,
            'category_id' => [$asset->category_id],
        ]);

        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $serialAttribute->id,
            'attribute_value' => 'CUST-SERIAL-7788',
        ]);

        $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.search_asset',
            'query' => [
                'serial_number' => 'CUST-SERIAL-7788',
            ],
        ])->assertOk()
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('items.0.asset_id', $asset->id)
            ->assertJsonPath('items.0.serial_number', 'CUST-SERIAL-7788');
    }

    public function test_it_returns_vehicle_by_plate_number_for_ita(): void
    {
        $asset = $this->createAssetFixture();
        $asset->forceFill([
            'name' => 'Mobil Operasional',
            'serial_number' => null,
            'imei1' => null,
            'imei2' => null,
        ])->save();

        $plateAttribute = CustomAssetAttribute::create([
            'name' => 'Plat Nomor',
            'type' => CustomAssetAttribute::TYPE_TEXT,
            'required' => true,
            'is_active' => true,
            'category_id' => [$asset->category_id],
        ]);

        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $plateAttribute->id,
            'attribute_value' => 'B 1234 XYZ',
        ]);

        $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.search_asset',
            'query' => [
                'plate_number' => 'B 1234 XYZ',
            ],
        ])->assertOk()
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('items.0.asset_id', $asset->id)
            ->assertJsonPath('items.0.plate_number', 'B 1234 XYZ');
    }

    public function test_it_returns_asset_by_custom_imei_for_ita(): void
    {
        $asset = $this->createAssetFixture();
        $asset->forceFill([
            'imei1' => null,
            'imei2' => null,
        ])->save();

        $imeiAttribute = CustomAssetAttribute::create([
            'name' => 'IMEI1',
            'type' => CustomAssetAttribute::TYPE_NUMBER,
            'required' => false,
            'is_active' => true,
            'category_id' => [$asset->category_id],
        ]);

        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $imeiAttribute->id,
            'attribute_value' => '359876543210123',
        ]);

        $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.search_asset',
            'query' => [
                'imei' => '359876543210123',
            ],
        ])->assertOk()
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('items.0.asset_id', $asset->id)
            ->assertJsonPath('items.0.imei1', '359876543210123');
    }

    public function test_it_includes_asset_history_for_history_route(): void
    {
        $asset = $this->createAssetFixture();
        $ga = User::create(['name' => 'General Affairs', 'whatsapp_number' => '628111111111']);
        $recipient = User::where('name', 'Budi')->firstOrFail();
        $entity = BusinessEntity::where('name', 'Complete Selular')->firstOrFail();

        $transfer = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'BAST-2026-001',
            'from_user_id' => $ga->id,
            'to_user_id' => $recipient->id,
            'transfer_date' => '2026-06-20',
        ]);

        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $response = $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.asset_history',
            'query' => [
                'asset_tag' => (string) $asset->id,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('items.0.latest_transfer.letter_number', 'BAST-2026-001')
            ->assertJsonPath('items.0.history.0.event_type', 'asset_transfer')
            ->assertJsonPath('items.0.history.0.transfer_date', '2026-06-20')
            ->assertJsonPath('items.0.history.0.from_user.name', 'General Affairs')
            ->assertJsonPath('items.0.history.0.to_user.name', 'Budi');
    }

    public function test_it_returns_expiring_documents_that_ita_can_answer(): void
    {
        $asset = $this->createAssetFixture();
        $documentAttribute = CustomAssetAttribute::create([
            'name' => 'STNK',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'required' => false,
            'is_active' => true,
            'category_id' => [$asset->category_id],
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => 30,
        ]);
        $ignoredDocumentAttribute = CustomAssetAttribute::create([
            'name' => 'Polis Asuransi',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'required' => false,
            'is_active' => true,
            'category_id' => [$asset->category_id],
            'is_notifiable' => false,
            'notification_type' => 'relative_date',
            'notification_offset' => 30,
        ]);

        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $documentAttribute->id,
            'attribute_value' => AssetAttribute::documentValue([
                'expires_at' => '2026-07-15',
                'document_number' => 'STNK-2026-001',
                'document_path' => 'asset-documents/stnk.pdf',
                'notes' => 'Perpanjang sebelum jatuh tempo',
                'reminder_start_days' => 30,
            ]),
        ]);
        AssetAttribute::create([
            'asset_id' => $asset->id,
            'custom_attribute_id' => $ignoredDocumentAttribute->id,
            'attribute_value' => AssetAttribute::documentValue([
                'expires_at' => '2026-07-10',
                'document_number' => 'POLIS-001',
            ]),
        ]);

        $response = $this->postJson(route('integrations.whatsapp.assets.query'), [
            'route' => 'shelf.expiring_documents',
            'intent' => 'expiring_documents',
            'query' => [
                'document_name' => 'STNK',
                'status' => 'due',
                'due_within_days' => 30,
                'location' => 'Bandung',
                'asset_type' => 'ThinkPad',
                'include_expired' => true,
                'notifiable_only' => true,
                'limit' => 10,
            ],
            'contract_version' => '1.2',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('result_status', 'confirmed')
            ->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.asset_id', $asset->id)
            ->assertJsonPath('items.0.asset_name', 'Laptop Lenovo ThinkPad')
            ->assertJsonPath('items.0.document.name', 'STNK')
            ->assertJsonPath('items.0.document.number', 'STNK-2026-001')
            ->assertJsonPath('items.0.document.expires_at', '2026-07-15')
            ->assertJsonPath('items.0.document.days_until_expiry', 17)
            ->assertJsonPath('items.0.document.status.value', AssetAttribute::STATUS_DUE_SOON)
            ->assertJsonPath('items.0.document.status.label', 'Perlu diperbarui')
            ->assertJsonPath('items.0.document.reminder_start_days', 30)
            ->assertJsonMissingPath('items.0.document.url');
    }

    private function createAssetFixture(): Asset
    {
        $entity = BusinessEntity::create(['name' => 'Complete Selular', 'format' => 'CS']);
        $category = Category::create(['name' => 'BARANG ELEKTRONIK']);
        $brand = Brand::create(['name' => 'Lenovo']);
        $location = AssetLocation::create(['name' => 'HO Bandung', 'address' => 'Bandung']);
        $jobTitle = JobTitle::create(['title' => 'Staff IT']);
        $recipient = User::create([
            'name' => 'Budi',
            'username' => 'budi',
            'email' => 'budi@example.test',
            'whatsapp_number' => '628123456789',
            'business_entity_id' => $entity->id,
            'job_title_id' => $jobTitle->id,
        ]);

        return Asset::create([
            'purchase_date' => '2025-01-15',
            'business_entity_id' => $entity->id,
            'name' => 'Laptop Lenovo ThinkPad',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'type' => 'ThinkPad T14',
            'serial_number' => 'SN-ASSET-001',
            'imei1' => '111222333444555',
            'imei2' => null,
            'item_price' => 15000000,
            'asset_location_id' => $location->id,
            'condition_status' => 'transferred',
            'nbh_status' => 'none',
            'qty' => 1,
            'recipient_id' => $recipient->id,
            'recipient_business_entity_id' => $entity->id,
        ]);
    }

    protected function createSchema(): void
    {
        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('format')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
        });

        Schema::create('job_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('job_title_id')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('asset_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->text('description')->nullable();
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
            $table->string('condition_status')->default('available');
            $table->string('nbh_status')->default('none');
            $table->date('nbh_reported_at')->nullable();
            $table->string('audit_document_path')->nullable();
            $table->string('nbh_document_path')->nullable();
            $table->text('nbh_notes')->nullable();
            $table->unsignedBigInteger('nbh_responsible_user_id')->nullable();
            $table->date('sold_at')->nullable();
            $table->string('sold_to')->nullable();
            $table->integer('sold_price')->nullable();
            $table->string('sale_document_path')->nullable();
            $table->text('sale_notes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->integer('qty')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('recipient_business_entity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id');
            $table->string('letter_number')->unique();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->string('document')->nullable();
            $table->date('transfer_date')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_transfer_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('equipment')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_asset_attributes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
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
            $table->unsignedBigInteger('custom_attribute_id')->nullable();
            $table->text('attribute_value')->nullable();
            $table->timestamps();
        });
    }
}
