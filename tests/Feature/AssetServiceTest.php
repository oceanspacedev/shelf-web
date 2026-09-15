<?php

namespace Tests\Feature;

use App\Enums\AssetServiceStatus;
use App\Filament\Exports\AssetServiceExporter;
use App\Filament\Resources\AssetServiceResource;
use App\Models\Asset;
use App\Models\AssetService;
use App\Models\AssetServiceItem;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Models\Vendor;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function createTestAsset(): Asset
    {
        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Test Entity']);

        return Asset::create([
            'name' => 'Laptop Lenovo ThinkPad T14',
            'business_entity_id' => $entity->id,
            'serial_number' => 'SN-LENOVO-12345',
            'type' => 'ThinkPad T14 Gen 2',
        ]);
    }

    public function test_asset_service_generates_service_number_automatically(): void
    {
        $asset = $this->createTestAsset();
        $user = User::factory()->create();

        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $user->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Baterai drop dan cepat habis',
            'status' => AssetServiceStatus::Pending->value,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($service->service_number);
        $this->assertStringStartsWith('SRV-', $service->service_number);
        $this->assertSame($asset->id, $service->asset->id);
        $this->assertSame($user->id, $service->servicedByUser->id);
        $this->assertSame(AssetServiceStatus::Pending, $service->status);
    }

    public function test_service_number_increments_consecutively(): void
    {
        $asset = $this->createTestAsset();
        $user = User::factory()->create();

        $first = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $user->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Servis pertama',
        ]);

        $second = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $user->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Servis kedua',
        ]);

        $this->assertNotSame($first->service_number, $second->service_number);
    }

    public function test_asset_service_items_calculate_subtotal_and_recalculate_total(): void
    {
        $asset = $this->createTestAsset();
        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'service_date' => now()->toDateString(),
            'issue_description' => 'Pergantian part',
        ]);

        $item1 = AssetServiceItem::create([
            'asset_service_id' => $service->id,
            'item_name' => 'Baterai Original',
            'quantity' => 1,
            'unit_price' => 750000,
        ]);

        $item2 = AssetServiceItem::create([
            'asset_service_id' => $service->id,
            'item_name' => 'Jasa Pemasangan',
            'quantity' => 1,
            'unit_price' => 100000,
        ]);

        $this->assertSame(750000, $item1->subtotal);
        $this->assertSame(100000, $item2->subtotal);

        $service->recalculateTotalCost();
        $this->assertSame(850000, $service->fresh()->total_cost);
    }

    public function test_vendor_provider_relationship(): void
    {
        $asset = $this->createTestAsset();
        $vendor = Vendor::create([
            'name' => 'PT Mitra Komputer Solusindo',
            'contact_person' => 'Budi Santoso',
            'location' => 'Jakarta Selatan',
            'last_price' => 0,
        ]);

        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'vendor',
            'vendor_id' => $vendor->id,
            'technician_name' => 'Pak Joko',
            'contact_number' => '081298765432',
            'service_date' => now()->toDateString(),
            'issue_description' => 'Mainboard mati total',
            'status' => AssetServiceStatus::InProgress->value,
            'before_service_photo' => 'asset-services/before/mainboard.jpg',
            'after_service_photo' => 'asset-services/after/mainboard_fixed.jpg',
            'receipt_document_path' => 'asset-services/receipts/nota_bengkel.pdf',
            'total_cost' => 1500000,
        ]);

        $this->assertSame($vendor->id, $service->vendor->id);
        $this->assertStringContainsString('Mitra Komputer', $service->provider_label);
        $this->assertSame(1500000, $service->total_cost);
        $this->assertNotNull($service->before_service_photo);
        $this->assertNotNull($service->after_service_photo);
        $this->assertNotNull($service->receipt_document_path);
    }

    public function test_asset_service_status_enum_helpers(): void
    {
        $this->assertSame('Menunggu Servis', AssetServiceStatus::Pending->label());
        $this->assertSame('Sedang Diservis', AssetServiceStatus::InProgress->label());
        $this->assertSame('Selesai', AssetServiceStatus::Completed->label());
        $this->assertSame('Dibatalkan', AssetServiceStatus::Cancelled->label());

        $options = AssetServiceStatus::options();
        $this->assertArrayHasKey('pending', $options);
        $this->assertArrayHasKey('in_progress', $options);
        $this->assertArrayHasKey('completed', $options);
        $this->assertArrayHasKey('cancelled', $options);
    }

    public function test_asset_services_relation_on_asset_model(): void
    {
        $asset = $this->createTestAsset();

        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'service_date' => now()->toDateString(),
            'issue_description' => 'Ganti keyboard',
            'status' => AssetServiceStatus::Completed->value,
            'completion_date' => now()->toDateString(),
        ]);

        $this->assertTrue($asset->services()->exists());
        $this->assertSame($service->id, $asset->services->first()->id);
    }

    public function test_asset_service_exporter_has_expected_columns(): void
    {
        $columns = AssetServiceExporter::getColumns();
        $columnNames = array_map(fn ($col) => $col->getName(), $columns);

        $this->assertContains('service_number', $columnNames);
        $this->assertContains('asset.name', $columnNames);
        $this->assertContains('provider_type', $columnNames);
        $this->assertContains('technician', $columnNames);
        $this->assertContains('service_date', $columnNames);
        $this->assertContains('completion_date', $columnNames);
        $this->assertContains('status', $columnNames);
        $this->assertContains('issue_description', $columnNames);
        $this->assertContains('total_cost', $columnNames);
    }

    public function test_admin_can_access_asset_service_index_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $response = $this->get(AssetServiceResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_admin_can_access_asset_service_view_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $asset = $this->createTestAsset();
        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'service_date' => now()->toDateString(),
            'issue_description' => 'Kerusakan port charger',
            'status' => AssetServiceStatus::Pending->value,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin);

        $response = $this->get(AssetServiceResource::getUrl('view', ['record' => $service]));
        $response->assertSuccessful();
        $response->assertSeeText($service->service_number);
        $response->assertSeeText($admin->name);
    }

    public function test_two_stage_workflow_marking_service_completed(): void
    {
        $asset = $this->createTestAsset();
        $user = User::factory()->create();

        // Stage 1: Pendaftaran Servis
        $service = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $user->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'LCD bergaris hijau',
            'before_service_photo' => 'asset-services/before/lcd_green.jpg',
            'status' => AssetServiceStatus::Pending->value,
        ]);

        $this->assertSame(AssetServiceStatus::Pending, $service->status);
        $this->assertNull($service->completion_date);
        $this->assertNull($service->after_service_photo);

        // Stage 2: Selesaikan Servis
        $service->update([
            'completion_date' => now()->toDateString(),
            'action_taken' => 'Ganti panel LCD dan kabel fleksibel',
            'after_service_photo' => 'asset-services/after/lcd_fixed.jpg',
            'receipt_document_path' => 'asset-services/receipts/nota_lcd.jpg',
            'status' => AssetServiceStatus::Completed,
        ]);

        $item = $service->items()->create([
            'item_name' => 'Panel LCD IPS 14 Inch',
            'quantity' => 1,
            'unit_price' => 850000,
        ]);

        $service->recalculateTotalCost();

        $fresh = $service->fresh();
        $this->assertSame(AssetServiceStatus::Completed, $fresh->status);
        $this->assertSame('Ganti panel LCD dan kabel fleksibel', $fresh->action_taken);
        $this->assertSame(850000, $fresh->total_cost);
        $this->assertNotNull($fresh->after_service_photo);
        $this->assertNotNull($fresh->receipt_document_path);
        $this->assertCount(1, $fresh->items);
    }

    public function test_query_scoping_for_roles(): void
    {
        $asset = $this->createTestAsset();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $serviceA = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $userA->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Servis User A',
            'created_by' => $userA->id,
        ]);

        $serviceB = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $userB->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Servis User B',
            'created_by' => $userB->id,
        ]);

        // When acting as User A (non-admin), query only returns User A's service
        $this->actingAs($userA);
        $scopedQueryA = AssetServiceResource::getEloquentQuery()->get();
        $this->assertTrue($scopedQueryA->contains('id', $serviceA->id));
        $this->assertFalse($scopedQueryA->contains('id', $serviceB->id));

        // When acting as Admin, query returns all services
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $adminQuery = AssetServiceResource::getEloquentQuery()->get();
        $this->assertTrue($adminQuery->contains('id', $serviceA->id));
        $this->assertTrue($adminQuery->contains('id', $serviceB->id));
    }

    public function test_exporter_query_scoping_for_roles(): void
    {
        $asset = $this->createTestAsset();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $serviceA = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $userA->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Export Servis A',
            'created_by' => $userA->id,
        ]);

        $serviceB = AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $userB->id,
            'service_date' => now()->toDateString(),
            'issue_description' => 'Export Servis B',
            'created_by' => $userB->id,
        ]);

        $this->actingAs($userA);
        $queryA = AssetServiceExporter::modifyQuery(AssetService::query())->get();
        $this->assertTrue($queryA->contains('id', $serviceA->id));
        $this->assertFalse($queryA->contains('id', $serviceB->id));
    }
}


