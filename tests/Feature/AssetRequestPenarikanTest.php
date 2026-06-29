<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\NbhStatus;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetTransferResource\Pages\CreateAssetTransfer;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\Division;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetRequestPenarikanTest extends TestCase
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
    }

    public function test_fulfill_penarikan_creates_ba_pengembalian_and_mutates_asset(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang']);
        $requester = User::create(['name' => 'Pemegang Aset']);
        $operator = User::create(['name' => 'Operator']);
        $generalAffair = User::create(['name' => 'GA']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');

        $division = Division::create(['name' => 'Operasional']); // tanpa approver -> auto-approve

        $asset = Asset::create([
            'name' => 'Laptop Unit 12',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $requester->id,
            'recipient_business_entity_id' => $entity->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals(RequestStatus::Approved, $request->fresh()->status);
        $this->assertFalse($request->fresh()->is_fulfilled);

        $transfer = $request->fulfillPenarikan($generalAffair, $operator, 'BAPEB-TEST-001');

        // BA pengembalian terbentuk dengan benar.
        $this->assertSame('BAPEB-TEST-001', $transfer->letter_number);
        $this->assertSame($requester->id, $transfer->from_user_id);
        $this->assertSame($generalAffair->id, $transfer->to_user_id);
        $this->assertSame($entity->id, $transfer->business_entity_id);

        $this->assertNotNull(AssetTransferDetail::where('asset_transfer_id', $transfer->id)->where('asset_id', $asset->id)->first());

        // Aset bermutasi: diterima GA -> Available, NBH None.
        $asset = $asset->fresh();
        $this->assertSame($generalAffair->id, $asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertSame(NbhStatus::None, $asset->nbh_status);

        // Pengajuan ditandai fulfilled & ter-link ke BA.
        $request = $request->fresh();
        $this->assertNotNull($request->fulfilled_at);
        $this->assertSame($operator->id, $request->fulfilled_by_user_id);
        $this->assertSame($transfer->id, $request->asset_transfer_id);
    }

    public function test_fulfill_penarikan_rejects_non_general_affair_recipient(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang 2']);
        $requester = User::create(['name' => 'Pemegang Aset 2']);
        $operator = User::create(['name' => 'Operator 2']);
        $nonGa = User::create(['name' => 'Bukan GA']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']); // role ada, tapi $nonGa tidak diberi

        $division = Division::create(['name' => 'Logistik']);

        $asset = Asset::create([
            'name' => 'Printer',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $requester->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $asset->id,
        ]);

        $this->expectException(AuthorizationException::class);
        $request->fulfillPenarikan($nonGa, $operator, 'BAPEB-TEST-002');
    }

    public function test_fulfill_penarikan_rejects_double_fulfillment(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang 3']);
        $requester = User::create(['name' => 'Pemegang Aset 3']);
        $operator = User::create(['name' => 'Operator 3']);
        $generalAffair = User::create(['name' => 'GA 3']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');

        $division = Division::create(['name' => 'IT']);

        $asset = Asset::create([
            'name' => 'Monitor',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $requester->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $asset->id,
        ]);

        $request->fulfillPenarikan($generalAffair, $operator, 'BAPEB-TEST-003');

        $this->expectException(\RuntimeException::class);
        $request->fulfillPenarikan($generalAffair, $operator, 'BAPEB-TEST-003B');
    }

    public function test_asset_transfer_create_page_can_prefill_from_approved_penarikan_request(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang Prefill']);
        $requester = User::create(['name' => 'Pemegang Aset Prefill']);
        $generalAffair = User::create(['name' => 'GA Prefill']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');
        $division = Division::create(['name' => 'Gudang Prefill']);

        $asset = Asset::create([
            'name' => 'Laptop Penarikan Prefill',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'recipient_id' => $requester->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $asset->id,
        ]);

        $prefill = CreateAssetTransfer::prefillDataFromAssetRequest($request);

        $this->assertSame($entity->id, $prefill['business_entity_id']);
        $this->assertSame($requester->id, $prefill['from_user_id']);
        $this->assertSame($generalAffair->id, $prefill['to_user_id']);
        $this->assertSame(now()->toDateString(), $prefill['transfer_date']);
        $this->assertSame([
            [
                'asset_id' => $asset->id,
                'equipment' => null,
            ],
        ], $prefill['details']);
    }

    public function test_asset_request_can_be_marked_fulfilled_by_return_transfer(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang Fulfill Transfer']);
        $requester = User::create(['name' => 'Pemegang Aset Fulfill Transfer']);
        $operator = User::create(['name' => 'Operator Fulfill Transfer']);
        $generalAffair = User::create(['name' => 'GA Fulfill Transfer']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');
        $division = Division::create(['name' => 'Gudang Fulfill Transfer']);

        $asset = Asset::create([
            'name' => 'Laptop Penarikan Fulfill Transfer',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'recipient_id' => $requester->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $asset->id,
        ]);

        $transfer = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'BAPEB-FULFILL-001',
            'from_user_id' => $requester->id,
            'to_user_id' => $generalAffair->id,
            'transfer_date' => now()->toDateString(),
        ]);

        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $request->markFulfilledByAssetTransfer($transfer, $operator);

        $request = $request->fresh();

        $this->assertNotNull($request->fulfilled_at);
        $this->assertSame($operator->id, $request->fulfilled_by_user_id);
        $this->assertSame($transfer->id, $request->asset_transfer_id);
    }

    protected function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('business_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('division_approvers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('division_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('level');
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('imei1')->nullable();
            $table->string('imei2')->nullable();
            $table->string('type')->nullable();
            $table->integer('item_price')->nullable();
            $table->date('purchase_date')->nullable();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
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
            $table->integer('sold_price')->nullable();
            $table->string('sale_document_path')->nullable();
            $table->text('sale_notes')->nullable();
            $table->boolean('is_available')->default(true);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->unsignedBigInteger('recipient_business_entity_id')->nullable();
            $table->unsignedBigInteger('asset_request_id')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_entity_id')->nullable();
            $table->string('letter_number')->nullable();
            $table->unsignedBigInteger('from_user_id');
            $table->unsignedBigInteger('to_user_id');
            $table->timestamp('transfer_date')->nullable();
            $table->string('document')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_transfer_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_transfer_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('equipment')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->unique()->nullable();
            $table->string('public_token')->unique()->nullable();
            $table->string('type')->default('pengadaan');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('qty')->nullable();
            $table->integer('current_level')->default(1);
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->unsignedBigInteger('fulfilled_by_user_id')->nullable();
            $table->unsignedBigInteger('asset_transfer_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_request_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_request_id');
            $table->unsignedBigInteger('asset_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('qty')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('fulfilled_asset_id')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('asset_request_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('decided_by_user_id')->nullable();
            $table->string('public_token')->unique()->nullable();
            $table->integer('level');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_fulfill_penarikan_creates_one_ba_with_all_requested_asset_items(): void
    {
        $entity = BusinessEntity::create(['name' => 'CV Gudang Multi']);
        $requester = User::create(['name' => 'Pemegang Banyak Aset']);
        $operator = User::create(['name' => 'Operator Multi']);
        $generalAffair = User::create(['name' => 'GA Multi']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);
        $generalAffair->assignRole('general_affair');
        $division = Division::create(['name' => 'Operasional Multi']);

        $laptop = Asset::create([
            'name' => 'Laptop Unit 12',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'recipient_id' => $requester->id,
            'recipient_business_entity_id' => $entity->id,
            'business_entity_id' => $entity->id,
        ]);
        $phone = Asset::create([
            'name' => 'iPhone Operasional',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'recipient_id' => $requester->id,
            'recipient_business_entity_id' => $entity->id,
            'business_entity_id' => $entity->id,
        ]);

        $request = AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => $division->id,
            'type' => AssetRequestType::Penarikan,
            'asset_id' => $laptop->id,
        ]);
        $request->items()->create([
            'asset_id' => $phone->id,
            'item_name' => $phone->name,
            'qty' => 1,
        ]);

        $transfer = $request->fulfillPenarikan($generalAffair, $operator, 'BAPEB-MULTI-001');

        $this->assertSame('BAPEB-MULTI-001', $transfer->letter_number);
        $this->assertSame($requester->id, $transfer->from_user_id);
        $this->assertSame($generalAffair->id, $transfer->to_user_id);
        $this->assertEqualsCanonicalizing(
            [$laptop->id, $phone->id],
            $transfer->details()->pluck('asset_id')->all(),
        );

        $this->assertSame($generalAffair->id, $laptop->fresh()->recipient_id);
        $this->assertSame($generalAffair->id, $phone->fresh()->recipient_id);
        $this->assertSame(AssetCondition::Available, $laptop->fresh()->condition_status);
        $this->assertSame(AssetCondition::Available, $phone->fresh()->condition_status);
        $this->assertTrue($request->fresh()->is_fulfilled);
    }
}
