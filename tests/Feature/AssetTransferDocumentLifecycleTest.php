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
use Spatie\Permission\Models\Permission;
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

        // User fixture mewakili user panel lama (akses semua badan usaha);
        // pembatasan per badan usaha diuji di BusinessEntityScopingTest.
        User::creating(fn (User $user) => $user->access_all_business_entities ??= true);
    }

    public function test_document_type_is_explicit_and_drives_labels(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-001', AssetTransferDocumentType::SerahTerima);
        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-001', AssetTransferDocumentType::PengalihanBarang);
        $pengembalian = $this->makeTransfer($entity, $nextHolder, $generalAffair, 'BAPEB-001', AssetTransferDocumentType::PengembalianBarang);
        // Antar staf GA pun jenisnya mengikuti pilihan, bukan ditebak dari peran.
        $betweenGeneralAffairStaff = $this->makeTransfer($entity, $generalAffair, $secondGeneralAffair, 'BAPAB-GA-001', AssetTransferDocumentType::PengalihanBarang);

        $this->assertSame(AssetTransferDocumentType::SerahTerima, $serahTerima->documentType());
        $this->assertSame('BERITA ACARA SERAH TERIMA', $serahTerima->status);
        $this->assertSame('BA', $serahTerima->documentCode());

        $this->assertSame(AssetTransferDocumentType::PengalihanBarang, $pengalihan->documentType());
        $this->assertSame('BERITA ACARA PENGALIHAN BARANG', $pengalihan->status);
        $this->assertSame('BAPAB', $pengalihan->documentCode());

        $this->assertSame(AssetTransferDocumentType::PengembalianBarang, $pengembalian->documentType());
        $this->assertSame('BERITA ACARA PENGEMBALIAN BARANG', $pengembalian->status);
        $this->assertSame('BAPEB', $pengembalian->documentCode());

        $this->assertSame(AssetTransferDocumentType::PengalihanBarang, $betweenGeneralAffairStaff->documentType());

        $untyped = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'LEGACY-001',
            'from_user_id' => $generalAffair->id,
            'to_user_id' => $holder->id,
            'transfer_date' => now(),
        ]);

        $this->assertNull($untyped->documentType());
        $this->assertSame('Status Transfer Tidak Valid', $untyped->status);
        $this->assertSame('UNKNOWN', $untyped->documentCode());
    }

    public function test_document_type_scope_filters_by_column(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-002', AssetTransferDocumentType::SerahTerima);
        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-002', AssetTransferDocumentType::PengalihanBarang);
        $pengembalian = $this->makeTransfer($entity, $nextHolder, $secondGeneralAffair, 'BAPEB-002', AssetTransferDocumentType::PengembalianBarang);

        $this->assertSame([$serahTerima->id], AssetTransfer::query()->forDocumentType(AssetTransferDocumentType::SerahTerima)->pluck('id')->all());
        $this->assertSame([$pengalihan->id], AssetTransfer::query()->forDocumentType('pengalihan_barang')->pluck('id')->all());
        $this->assertSame([$pengembalian->id], AssetTransfer::query()->forDocumentType(AssetTransferDocumentType::PengembalianBarang)->pluck('id')->all());
        $this->assertSame(3, AssetTransfer::query()->forDocumentType(null)->count());
    }

    public function test_serah_terima_hands_stock_asset_to_holder_and_pengembalian_returns_it_to_stock(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder] = $this->fixtureUsers();

        $asset = $this->stockAsset('Laptop Operasional');

        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-003', AssetTransferDocumentType::SerahTerima);
        $this->attach($serahTerima, $asset);

        $serahTerima->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($holder->id, $asset->recipient_id);
        $this->assertSame($entity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
        $this->assertSame(NbhStatus::None, $asset->nbh_status);
        $this->assertTrue($asset->checkValidRecipient());

        // Dikembalikan ke staf GA mana pun: aset pulang ke stok, bukan ke akun si staf.
        $pengembalian = $this->makeTransfer($entity, $holder, $secondGeneralAffair, 'BAPEB-003', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($pengembalian, $asset);

        $pengembalian->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertNull($asset->recipient_id);
        $this->assertSame($entity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertSame(NbhStatus::None, $asset->nbh_status);
        $this->assertTrue($asset->is_available);
        $this->assertTrue($asset->checkValidRecipient());
    }

    public function test_pengalihan_moves_asset_between_people_including_general_affair_staff(): void
    {
        [$entity, , $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();

        $asset = $this->heldAsset('Printer Divisi', $holder, $entity);

        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-003', AssetTransferDocumentType::PengalihanBarang);
        $this->attach($pengalihan, $asset);
        $pengalihan->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($nextHolder->id, $asset->recipient_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);

        // Staf GA juga bisa memegang aset secara pribadi; itu bukan stok.
        $toGeneralAffairStaff = $this->makeTransfer($entity, $nextHolder, $secondGeneralAffair, 'BAPAB-004', AssetTransferDocumentType::PengalihanBarang);
        $this->attach($toGeneralAffairStaff, $asset);
        $toGeneralAffairStaff->applyLifecycleToAssets();

        $asset->refresh();
        $this->assertSame($secondGeneralAffair->id, $asset->recipient_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
        $this->assertTrue($asset->checkValidRecipient());
    }

    public function test_applying_transfer_lifecycle_is_idempotent_after_asset_was_already_moved(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $asset = $this->stockAsset('Laptop Operasional');

        $transfer = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-IDEMP-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $asset);

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

        $transfer = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-EMPTY-001', AssetTransferDocumentType::SerahTerima);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BA transfer wajib memiliki minimal satu aset.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_applying_transfer_lifecycle_requires_a_document_type(): void
    {
        [$entity, $generalAffair, , $holder] = $this->fixtureUsers();

        $transfer = AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'letter_number' => 'LEGACY-002',
            'from_user_id' => $generalAffair->id,
            'to_user_id' => $holder->id,
            'transfer_date' => now(),
        ]);
        $this->attach($transfer, $this->stockAsset('Laptop Tanpa Jenis'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Jenis berita acara belum ditentukan.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_serah_terima_must_come_from_general_affair_staff(): void
    {
        [$entity, , , $holder, $nextHolder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $holder, $nextHolder, 'BA-NONGA-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $this->stockAsset('Laptop Stok'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Pemegang Aset" bukan staf GA');

        $transfer->applyLifecycleToAssets();
    }

    public function test_pengembalian_must_go_to_general_affair_staff(): void
    {
        [$entity, , , $holder, $nextHolder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPEB-NONGA-001', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($transfer, $this->heldAsset('Laptop Dipegang', $holder, $entity));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('"Penerima Lanjutan" bukan staf GA');

        $transfer->applyLifecycleToAssets();
    }

    public function test_general_affair_party_must_be_the_actor_creating_the_transfer(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-ACTOR-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $this->stockAsset('Laptop Stok'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('harus akun Anda sendiri (Uwis), bukan "Meka Nurul"');

        $transfer->applyLifecycleToAssets(actor: $secondGeneralAffair);
    }

    public function test_only_general_affair_staff_or_stock_managers_may_create_stock_transfers(): void
    {
        [$entity, $generalAffair, , $holder, $nextHolder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-ACTOR-001', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($transfer, $this->heldAsset('Laptop Dipegang', $holder, $entity));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hanya staf General Affairs atau pemegang izin Kelola BA Stok yang bisa membuat Berita Acara Pengembalian Barang');

        $transfer->applyLifecycleToAssets(actor: $nextHolder);
    }

    public function test_actor_rule_allows_own_transfers_pengalihan_by_anyone_and_super_admin_on_behalf(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder] = $this->fixtureUsers();
        // Seperti di production, super admin memegang izin "Kelola BA Stok"; rolenya sendiri tidak memberi akses.
        Role::create(['name' => config('filament-shield.super_admin.name', 'super_admin'), 'guard_name' => 'web'])
            ->givePermissionTo(Permission::create(['name' => 'manage_stock_asset::transfer', 'guard_name' => 'web']));
        $superAdmin = User::create(['name' => 'Super Admin']);
        $superAdmin->assignRole(config('filament-shield.super_admin.name', 'super_admin'));

        // Staf GA menyerahkan atas nama sendiri.
        $laptop = $this->stockAsset('Laptop Stok');
        $own = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-ACTOR-002', AssetTransferDocumentType::SerahTerima);
        $this->attach($own, $laptop);
        $own->applyLifecycleToAssets(actor: $generalAffair);
        $this->assertSame($holder->id, $laptop->fresh()->recipient_id);

        // Pengalihan tidak punya pihak GA; pemegang mana pun boleh membuatnya.
        $printer = $this->heldAsset('Printer Dipegang', $holder, $entity);
        $pengalihan = $this->makeTransfer($entity, $holder, $nextHolder, 'BAPAB-ACTOR-001', AssetTransferDocumentType::PengalihanBarang);
        $this->attach($pengalihan, $printer);
        $pengalihan->applyLifecycleToAssets(actor: $holder);
        $this->assertSame($nextHolder->id, $printer->fresh()->recipient_id);

        // Super admin boleh membuat BA atas nama staf GA lain.
        $monitor = $this->stockAsset('Monitor Stok');
        $onBehalf = $this->makeTransfer($entity, $secondGeneralAffair, $nextHolder, 'BA-ACTOR-003', AssetTransferDocumentType::SerahTerima);
        $this->attach($onBehalf, $monitor);
        $onBehalf->applyLifecycleToAssets(actor: $superAdmin);
        $this->assertSame($nextHolder->id, $monitor->fresh()->recipient_id);
    }

    public function test_stock_manager_permission_allows_stock_transfers_on_behalf_of_general_affair_staff(): void
    {
        [$entity, $generalAffair, $secondGeneralAffair, $holder] = $this->fixtureUsers();
        $permission = Permission::create(['name' => 'manage_stock_asset::transfer', 'guard_name' => 'web']);
        Role::create(['name' => 'admin', 'guard_name' => 'web'])->givePermissionTo($permission);
        $admin = User::create(['name' => 'Admin Kantor']);
        $admin->assignRole('admin');

        // Alur lama role admin: Serah Terima atas nama staf GA ...
        $laptop = $this->stockAsset('Laptop Stok');
        $serahTerima = $this->makeTransfer($entity, $generalAffair, $holder, 'BA-STOCK-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($serahTerima, $laptop);
        $serahTerima->applyLifecycleToAssets(actor: $admin);
        $this->assertSame($holder->id, $laptop->fresh()->recipient_id);

        // ... dan Pengembalian ke staf GA lain.
        $pengembalian = $this->makeTransfer($entity, $holder, $secondGeneralAffair, 'BAPEB-STOCK-001', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($pengembalian, $laptop);
        $pengembalian->applyLifecycleToAssets(actor: $admin);
        $this->assertNull($laptop->fresh()->recipient_id);
        $this->assertSame(AssetCondition::Available, $laptop->fresh()->condition_status);
    }

    public function test_same_person_cannot_be_both_parties(): void
    {
        [$entity, $generalAffair] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $generalAffair, $generalAffair, 'BA-SELF-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $this->stockAsset('Laptop Stok'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('tidak boleh orang yang sama');

        $transfer->applyLifecycleToAssets();
    }

    public function test_serah_terima_rejects_asset_that_is_still_held_by_someone(): void
    {
        [$entity, $generalAffair, , $holder, $nextHolder] = $this->fixtureUsers();

        // Tersedia tapi masih tercatat punya pemegang: data lama yang belum dipulangkan ke stok.
        // Ditulis langsung ke tabel karena Asset::saving kini mengosongkan pemegang aset Tersedia.
        $asset = Asset::findOrFail(DB::table('assets')->insertGetId([
            'name' => 'Laptop Nyangkut',
            'condition_status' => AssetCondition::Available->value,
            'nbh_status' => NbhStatus::None->value,
            'is_available' => true,
            'recipient_id' => $holder->id,
        ]));

        $transfer = $this->makeTransfer($entity, $generalAffair, $nextHolder, 'BA-HELD-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $asset);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aset "Laptop Nyangkut" masih tercatat dipegang Pemegang Aset.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_serah_terima_rejects_asset_that_is_in_use(): void
    {
        [$entity, $generalAffair, , $holder, $nextHolder] = $this->fixtureUsers();

        $transfer = $this->makeTransfer($entity, $generalAffair, $nextHolder, 'BA-INUSE-001', AssetTransferDocumentType::SerahTerima);
        $this->attach($transfer, $this->heldAsset('Laptop Dipakai', $holder, $entity));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aset "Laptop Dipakai" harus berstatus Tersedia sebelum diserahterimakan dari stok.');

        $transfer->applyLifecycleToAssets();
    }

    public function test_applying_transfer_lifecycle_rejects_asset_not_held_by_from_user(): void
    {
        [$entity, $generalAffair, , $holder, $otherHolder] = $this->fixtureUsers();

        $asset = $this->heldAsset('Laptop Salah Pemegang', $otherHolder, $entity);

        $transfer = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-WRONG-001', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($transfer, $asset);

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

        $transfer = $this->makeTransfer($entity, $holder, $generalAffair, 'BAPEB-DAMAGED-001', AssetTransferDocumentType::PengembalianBarang);
        $this->attach($transfer, $asset);

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

        $generalAffair = User::create(['name' => 'Meka Nurul']);
        $secondGeneralAffair = User::create(['name' => 'Uwis']);
        $holder = User::create(['name' => 'Pemegang Aset']);
        $nextHolder = User::create(['name' => 'Penerima Lanjutan']);

        $generalAffair->assignRole('general_affair');
        $secondGeneralAffair->assignRole('general_affair');

        return [$entity, $generalAffair, $secondGeneralAffair, $holder, $nextHolder];
    }

    private function makeTransfer(BusinessEntity $entity, User $fromUser, User $toUser, string $letterNumber, AssetTransferDocumentType $type): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'document_type' => $type,
            'letter_number' => $letterNumber,
            'from_user_id' => $fromUser->id,
            'to_user_id' => $toUser->id,
            'transfer_date' => now(),
        ]);
    }

    private function attach(AssetTransfer $transfer, Asset $asset): void
    {
        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);
    }

    private function stockAsset(string $name): Asset
    {
        return Asset::create([
            'name' => $name,
            'condition_status' => AssetCondition::Available,
            'nbh_status' => NbhStatus::None,
            'is_available' => true,
        ]);
    }

    private function heldAsset(string $name, User $holder, BusinessEntity $entity): Asset
    {
        return Asset::create([
            'name' => $name,
            'condition_status' => AssetCondition::Transferred,
            'nbh_status' => NbhStatus::None,
            'is_available' => false,
            'recipient_id' => $holder->id,
            'recipient_business_entity_id' => $entity->id,
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
            $table->boolean('access_all_business_entities')->default(false);
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

        // Izin "Kelola BA Stok" dicek lewat Gate spatie saat aktor bukan pihak GA.
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
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
            $table->string('document_type', 32)->nullable();
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
