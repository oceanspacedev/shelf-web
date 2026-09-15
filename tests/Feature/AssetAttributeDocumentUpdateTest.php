<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\BusinessEntity;
use App\Models\Category;
use App\Models\CustomAssetAttribute;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetAttributeDocumentUpdateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_update_stnk_and_kir_individually(): void
    {
        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Armada Maju']);
        $category = Category::create(['name' => 'Kendaraan Operasional']);

        $asset = Asset::create([
            'name' => 'Mobil Van Daihatsu GranMax',
            'business_entity_id' => $entity->id,
            'category_id' => $category->id,
            'serial_number' => 'B 1234 XYZ',
        ]);

        $stnkAttr = CustomAssetAttribute::create([
            'name' => 'STNK',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => [$category->id],
        ]);

        $kirAttr = CustomAssetAttribute::create([
            'name' => 'KIR',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => [$category->id],
        ]);

        // 1. Update STNK first
        $stnkValue = AssetAttribute::documentValue([
            'document_number' => 'STNK-VAN-2026',
            'expires_at' => '2027-09-15',
            'document_path' => 'asset-documents/stnk_2027.pdf',
            'notes' => 'Perpanjangan Samsat 1 Tahun',
            'renewed_at' => now()->toDateString(),
        ]);

        AssetAttribute::updateOrCreate(
            ['asset_id' => $asset->id, 'custom_attribute_id' => $stnkAttr->id],
            ['attribute_value' => $stnkValue]
        );

        $freshAsset = $asset->fresh();
        $savedStnk = $freshAsset->attributes()->where('custom_attribute_id', $stnkAttr->id)->first();
        $this->assertNotNull($savedStnk);
        $payloadStnk = $savedStnk->documentPayload();
        $this->assertSame('STNK-VAN-2026', $payloadStnk['document_number']);
        $this->assertSame('2027-09-15', $payloadStnk['expires_at']);
        $this->assertSame('asset-documents/stnk_2027.pdf', $payloadStnk['document_path']);

        // KIR is not updated yet
        $this->assertNull($freshAsset->attributes()->where('custom_attribute_id', $kirAttr->id)->first());

        // 2. Update KIR individually
        $kirValue = AssetAttribute::documentValue([
            'document_number' => 'KIR-JKT-9988',
            'expires_at' => '2027-03-20',
            'document_path' => 'asset-documents/kir_2027.pdf',
            'notes' => 'Uji KIR Dishub Lulus',
            'renewed_at' => now()->toDateString(),
        ]);

        AssetAttribute::updateOrCreate(
            ['asset_id' => $asset->id, 'custom_attribute_id' => $kirAttr->id],
            ['attribute_value' => $kirValue]
        );

        $freshAsset = $asset->fresh();
        $savedKir = $freshAsset->attributes()->where('custom_attribute_id', $kirAttr->id)->first();
        $this->assertNotNull($savedKir);
        $payloadKir = $savedKir->documentPayload();
        $this->assertSame('KIR-JKT-9988', $payloadKir['document_number']);
        $this->assertSame('2027-03-20', $payloadKir['expires_at']);
        $this->assertSame('asset-documents/kir_2027.pdf', $payloadKir['document_path']);

        // STNK is still intact and unchanged
        $savedStnkAgain = $freshAsset->attributes()->where('custom_attribute_id', $stnkAttr->id)->first();
        $this->assertSame('STNK-VAN-2026', $savedStnkAgain->documentPayload()['document_number']);
    }

    public function test_admin_can_access_asset_page_with_action_group(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin);

        $response = $this->get(AssetResource::getUrl('index'));
        $response->assertSuccessful();
    }

    public function test_admin_can_access_view_asset_page_and_execute_update_helper(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Armada Maju']);
        $category = Category::create(['name' => 'Kendaraan']);
        $asset = Asset::create([
            'name' => 'Mobil Van Test',
            'business_entity_id' => $entity->id,
            'category_id' => $category->id,
            'serial_number' => 'B 9999 TEST',
        ]);

        $stnkAttr = CustomAssetAttribute::create([
            'name' => 'STNK',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => [$category->id],
        ]);

        $this->actingAs($admin);
        $response = $this->get(AssetResource::getUrl('view', ['record' => $asset]));
        $response->assertSuccessful();

        // Test handleAttributeDocumentUpdate helper
        AssetResource::handleAttributeDocumentUpdate($asset, [
            'custom_attribute_id' => $stnkAttr->id,
            'document_number' => 'STNK-TEST-001',
            'document_expires_at' => '2028-01-01',
            'document_file_path' => null,
            'document_notes' => 'Catatan uji',
        ]);

        $saved = $asset->fresh()->attributes()->where('custom_attribute_id', $stnkAttr->id)->first();
        $this->assertNotNull($saved);
        $this->assertSame('STNK-TEST-001', $saved->documentPayload()['document_number']);
        $this->assertSame('2028-01-01', $saved->documentPayload()['expires_at']);
    }

    public function test_only_document_expiry_attributes_are_available_in_form_options(): void
    {
        $entity = BusinessEntity::firstOrCreate(['name' => 'PT Armada Maju']);
        $category = Category::create(['name' => 'Kendaraan']);
        $asset = Asset::create([
            'name' => 'Mobil Van Form Test',
            'business_entity_id' => $entity->id,
            'category_id' => $category->id,
            'serial_number' => 'B 1111 FORM',
        ]);

        $docAttr = CustomAssetAttribute::create([
            'name' => 'STNK Khusus',
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => [$category->id],
        ]);

        $textAttr = CustomAssetAttribute::create([
            'name' => 'Warna Kendaraan',
            'type' => 'text',
            'is_active' => true,
            'category_id' => [$category->id],
        ]);

        $schema = AssetResource::attributeDocumentUpdateFormSchema($asset);
        $selectComponent = $schema[0];
        $options = $selectComponent->getOptions();

        $this->assertArrayHasKey($docAttr->id, $options);
        $this->assertArrayNotHasKey($textAttr->id, $options);
        $this->assertTrue(AssetResource::hasDocumentExpiryAttributes($asset));

        // Create asset without document expiry attributes
        $otherCategory = Category::create(['name' => 'Elektronik']);
        $laptopAsset = Asset::create([
            'name' => 'Laptop ASUS',
            'business_entity_id' => $entity->id,
            'category_id' => $otherCategory->id,
            'serial_number' => 'LAPTOP-001',
        ]);
        $this->assertFalse(AssetResource::hasDocumentExpiryAttributes($laptopAsset));
    }
}
