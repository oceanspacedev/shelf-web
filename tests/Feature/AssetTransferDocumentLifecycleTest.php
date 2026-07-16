<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Enums\NbhStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssetTransferDocumentLifecycleTest extends TestCase
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

    public function test_document_type_is_derived_from_general_affair_role_flow(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-001');
        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-001');
        $pengembalian = $this->makeTransfer($entity, $nextHolder, $generalAffair, 'BAPEB-001');
        $gaToGaStaff = $this->makeTransfer($entity, $generalAffair, $secondGeneralAffair, 'BA-GA-STAFF-001');
        $gaStaffToGa = $this->makeTransfer($entity, $secondGeneralAffair, $generalAffair, 'BAPEB-STAFF-GA-001');

        $this->assertSame(AssetTransferDocumentType::SerahTerima, $serahTerima->documentType());
        $this->assertSame('BERITA ACARA SERAH TERIMA', $serahTerima->status);
        $this->assertSame('BA', $serahTerima->documentCode());

        $this->assertSame(AssetTransferDocumentType::PengalihanBarang, $pengalihan->documentType());
        $this->assertSame('BERITA ACARA PENGALIHAN BARANG', $pengalihan->status);
        $this->assertSame('BAPAB', $pengalihan->documentCode());

        $this->assertSame(AssetTransferDocumentType::PengembalianBarang, $pengembalian->documentType());
        $this->assertSame('BERITA ACARA PENGEMBALIAN BARANG', $pengembalian->status);
        $this->assertSame('BAPEB', $pengembalian->documentCode());

        $this->assertSame(AssetTransferDocumentType::SerahTerima, $gaToGaStaff->documentType());
        $this->assertSame(AssetTransferDocumentType::PengembalianBarang, $gaStaffToGa->documentType());
    }

    public function test_document_type_scope_matches_computed_lifecycle(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-002');
        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-002');
        $pengembalian = $this->makeTransfer($entity, $nextHolder, $generalAffair, 'BAPEB-002');
        $gaToGaStaff = $this->makeTransfer($entity, $generalAffair, $secondGeneralAffair, 'BA-GA-STAFF-002');
        $gaStaffToGa = $this->makeTransfer($entity, $secondGeneralAffair, $generalAffair, 'BAPEB-STAFF-GA-002');

        $this->assertSame(
            [$serahTerima->id, $gaToGaStaff->id],
            AssetTransfer::query()
                ->forDocumentType(AssetTransferDocumentType::SerahTerima)
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$pengalihan->id],
            AssetTransfer::query()
                ->forDocumentType(AssetTransferDocumentType::PengalihanBarang)
                ->pluck('id')
                ->all(),
        );

        $this->assertSame(
            [$pengembalian->id, $gaStaffToGa->id],
            AssetTransfer::query()
                ->forDocumentType(AssetTransferDocumentType::PengembalianBarang)
                ->pluck('id')
                ->all(),
        );
    }

    public function test_applying_transfer_lifecycle_mutates_assets_from_document_type(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $asset = Asset::create([
            'name' => 'Laptop Operasional',
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'is_available' => true,
        ]);

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-003');
        AssetTransferDetail::create([
            'asset_transfer_id' => $serahTerima->id,
            'asset_id' => $asset->id,
        ]);

        $serahTerima->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($holder->id, $asset->recipient_id);
        $this->assertSame($entity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
        $this->assertSame(NbhStatus::None, $asset->nbh_status);

        $pengembalian = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-003');
        AssetTransferDetail::create([
            'asset_transfer_id' => $pengembalian->id,
            'asset_id' => $asset->id,
        ]);

        $pengembalian->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($generalAffair->id, $asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertSame(NbhStatus::None, $asset->nbh_status);
        $this->assertTrue($asset->is_available);
    }

    public function test_applying_transfer_lifecycle_is_idempotent_after_asset_was_already_moved(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $asset = Asset::create([
            'name' => 'Laptop Operasional',
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'is_available' => true,
        ]);

        $transfer = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-IDEMP-001');
        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $transfer->applyLifecycleToAssets();
        $transfer->refresh()->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($holder->id, $asset->recipient_id);
        $this->assertSame($entity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
    }

    public function test_applying_transfer_lifecycle_requires_at_least_one_asset_detail(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-EMPTY-001');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BA transfer wajib memiliki minimal satu aset.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_applying_transfer_lifecycle_rejects_asset_not_held_by_from_user(): void
    {
        [$entity, $generalAffair, , $holder, $otherHolder] = $this->fixtureUsers();

        $asset = Asset::create([
            'name' => 'Laptop Salah Pemegang',
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $otherHolder->id,
        ]);

        $transfer = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-WRONG-001');
        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aset "Laptop Salah Pemegang" bukan milik pemberi transfer.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_applying_transfer_lifecycle_rejects_non_transferable_asset(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $asset = Asset::create([
            'name' => 'Laptop Rusak',
            'condition_status' => AssetCondition::Damaged,
            'nbh_status' => NbhStatus::Pending,
            'is_available' => false,
            'recipient_id' => $holder->id,
        ]);

        $transfer = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-DAMAGED-001');
        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aset "Laptop Rusak" tidak bisa ditransfer karena statusnya Rusak.');

        $transfer->applyLifecycleToAssets();
    }

    /**
     * @return array{BusinessEntity, User, User, User, User}
     */
    private function fixtureUsers(): array
    {
        $entity = BusinessEntity::create(['name' => 'CV Complete']);
        Role::create(['name' => 'general_affair', 'guard_name' => 'web']);

        $generalAffair = User::create(['name' => 'GA']);
        $secondGeneralAffair = User::create(['name' => 'GA Kedua']);
        $holder = User::create(['name' => 'Pemegang Aset']);
        $nextHolder = User::create(['name' => 'Penerima Lanjutan']);

        $generalAffair->assignRole('general_affair');
        $secondGeneralAffair->assignRole('general_affair');

        return [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder];
    }

    private function makeTransfer(BusinessEntity $entity, User $fromUser, User $toUser, string $letterNumber): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => $letterNumber,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'transfer_date' => now(),
        ]);
    }

    private function createSchema(): void
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
            $table->string('format')->nullable();
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
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
    }
}
