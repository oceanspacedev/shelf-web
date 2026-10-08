<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource\Pages\CreateAssetTransfer;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Alur BA dari form Filament: jenis dipilih eksplisit, staf GA yang login
 * menjadi pihak stok, aset keluar dari dan kembali ke stok tanpa pemegang.
 */
class AssetTransferFormTest extends TestCase
{
    use DatabaseTransactions;

    private const TRANSFER_PERMISSIONS = ['view_any_asset::transfer', 'view_asset::transfer', 'create_asset::transfer'];

    private const MANAGE_STOCK_PERMISSION = 'manage_stock_asset::transfer';

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([...self::TRANSFER_PERMISSIONS, self::MANAGE_STOCK_PERMISSION] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->entity = BusinessEntity::create(['name' => '__atf_entity__', 'format' => 'ATF/']);
    }

    public function test_general_affair_staff_is_preselected_as_the_stock_side(): void
    {
        $meka = $this->generalAffairStaff('__atf_meka__');

        $this->actingAs($meka);

        Livewire::test(CreateAssetTransfer::class)
            ->assertFormFieldExists('document_type', fn (Select $field): bool => count($field->getOptions()) === 3)
            ->assertSchemaStateSet([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'from_user_id' => $meka->id,
            ])
            // Pihak GA terkunci ke akun yang login; sisi lain tetap bisa dipilih.
            ->assertFormFieldDisabled('from_user_id')
            ->assertFormFieldExists('to_user_id', fn (Select $field): bool => ! $field->isDisabled())
            ->fillForm(['document_type' => AssetTransferDocumentType::PengembalianBarang->value])
            ->assertSchemaStateSet([
                'from_user_id' => null,
                'to_user_id' => $meka->id,
            ])
            ->assertFormFieldDisabled('to_user_id')
            ->assertFormFieldExists('from_user_id', fn (Select $field): bool => ! $field->isDisabled());
    }

    public function test_operator_without_general_affair_role_can_only_make_pengalihan(): void
    {
        $this->actingAs($this->operator('__atf_operator__'));

        Livewire::test(CreateAssetTransfer::class)
            ->assertFormFieldExists('document_type', fn (Select $field): bool => array_keys($field->getOptions()) === [AssetTransferDocumentType::PengalihanBarang->value])
            ->assertSchemaStateSet([
                'document_type' => AssetTransferDocumentType::PengalihanBarang->value,
                'from_user_id' => null,
                'to_user_id' => null,
            ]);
    }

    public function test_general_affair_staff_cannot_hand_over_on_behalf_of_another_staff(): void
    {
        $meka = $this->generalAffairStaff('__atf_meka__');
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->stockAsset('__atf_laptop__');

        $this->actingAs($meka);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000004__',
                'from_user_id' => $uwis->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000004__')->firstOrFail();
        $this->assertSame($meka->id, $transfer->from_user_id, 'Pihak GA dipaksa ke akun yang login, bukan yang dikirim form.');
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id);
    }

    public function test_general_affair_staff_cannot_receive_a_return_on_behalf_of_another_staff(): void
    {
        $meka = $this->generalAffairStaff('__atf_meka__');
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->heldAsset('__atf_printer__', $hilman);

        $this->actingAs($meka);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::PengembalianBarang->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000005__',
                'from_user_id' => $hilman->id,
                'to_user_id' => $uwis->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000005__')->firstOrFail();
        $this->assertSame($meka->id, $transfer->to_user_id, 'Pihak GA dipaksa ke akun yang login, bukan yang dikirim form.');
        $this->assertNull($asset->fresh()->recipient_id);
    }

    public function test_super_admin_may_create_serah_terima_on_behalf_of_general_affair_staff(): void
    {
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->stockAsset('__atf_laptop__');

        $this->actingAs($this->superAdmin('__atf_super__'));

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->assertFormFieldExists('document_type', fn (Select $field): bool => count($field->getOptions()) === 3)
            ->assertSchemaStateSet(['document_type' => null, 'from_user_id' => null])
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000006__',
                'from_user_id' => $uwis->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->assertFormFieldExists('from_user_id', fn (Select $field): bool => ! $field->isDisabled())
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000006__')->firstOrFail();
        $this->assertSame($uwis->id, $transfer->from_user_id);
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id);
    }

    public function test_operator_without_general_affair_role_cannot_create_pengembalian(): void
    {
        $operator = $this->operator('__atf_operator__');
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->heldAsset('__atf_printer__', $hilman);

        $this->actingAs($operator);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::PengembalianBarang->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000007__',
                'from_user_id' => $hilman->id,
                'to_user_id' => $uwis->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->call('create');

        $undoRepeaterFake();

        $this->assertDatabaseMissing('asset_transfers', ['letter_number' => '__ATF/000007__']);
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id);
    }

    public function test_serah_terima_from_the_form_hands_a_stock_asset_to_its_new_holder(): void
    {
        $meka = $this->generalAffairStaff('__atf_meka__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->stockAsset('__atf_laptop__');

        $this->actingAs($meka);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000001__',
                'from_user_id' => $meka->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => 'Charger']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000001__')->firstOrFail();
        $this->assertSame(AssetTransferDocumentType::SerahTerima, $transfer->document_type);
        $this->assertSame($meka->id, $transfer->from_user_id);
        $this->assertSame($hilman->id, $transfer->to_user_id);
        $this->assertSame([$asset->id], $transfer->details()->pluck('asset_id')->all());

        $asset->refresh();
        $this->assertSame($hilman->id, $asset->recipient_id);
        $this->assertSame($this->entity->id, $asset->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Transferred, $asset->condition_status);
    }

    public function test_pengembalian_from_the_form_returns_the_asset_to_stock(): void
    {
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->heldAsset('__atf_printer__', $hilman);

        $this->actingAs($uwis);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::PengembalianBarang->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000002__',
                'from_user_id' => $hilman->id,
                'to_user_id' => $uwis->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000002__')->firstOrFail();
        $this->assertSame(AssetTransferDocumentType::PengembalianBarang, $transfer->document_type);
        $this->assertSame($uwis->id, $transfer->to_user_id);

        $asset->refresh();
        $this->assertNull($asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
        $this->assertTrue($asset->checkValidRecipient());
    }

    public function test_serah_terima_by_a_non_general_affair_user_leaves_no_orphan_transfer(): void
    {
        $operator = $this->operator('__atf_operator__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->stockAsset('__atf_laptop__');

        $this->actingAs($operator);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000003__',
                'from_user_id' => $operator->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->call('create');

        $undoRepeaterFake();

        $this->assertDatabaseMissing('asset_transfers', ['letter_number' => '__ATF/000003__']);

        $asset->refresh();
        $this->assertNull($asset->recipient_id);
        $this->assertSame(AssetCondition::Available, $asset->condition_status);
    }

    public function test_rejected_transfer_can_be_fixed_and_submitted_again(): void
    {
        $meka = $this->generalAffairStaff('__atf_meka__');
        $hilman = $this->employee('__atf_hilman__');
        $budi = $this->employee('__atf_budi__');
        $inUse = $this->heldAsset('__atf_dipakai__', $budi);
        $stock = $this->stockAsset('__atf_laptop__');

        $this->actingAs($meka);

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000008__',
                'from_user_id' => $meka->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $inUse->id, 'equipment' => null]],
            ])
            ->call('create')
            ->assertNotified('BA tidak dapat dibuat')
            ->assertSet('record', null)
            // Kiriman kedua tidak boleh 404 karena record dari percobaan pertama sudah di-rollback.
            ->fillForm(['details' => [['asset_id' => $stock->id, 'equipment' => null]]])
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $this->assertSame(1, AssetTransfer::query()->where('letter_number', '__ATF/000008__')->count());
        $this->assertSame($hilman->id, $stock->fresh()->recipient_id);
        $this->assertSame($budi->id, $inUse->fresh()->recipient_id);
    }

    public function test_stock_manager_may_create_serah_terima_on_behalf_of_general_affair_staff(): void
    {
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->stockAsset('__atf_laptop__');

        $this->actingAs($this->stockManager('__atf_admin__'));

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->assertFormFieldExists('document_type', fn (Select $field): bool => count($field->getOptions()) === 3)
            ->assertSchemaStateSet(['document_type' => null, 'from_user_id' => null])
            ->fillForm([
                'document_type' => AssetTransferDocumentType::SerahTerima->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000009__',
                'from_user_id' => $uwis->id,
                'to_user_id' => $hilman->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->assertFormFieldExists('from_user_id', fn (Select $field): bool => ! $field->isDisabled())
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000009__')->firstOrFail();
        $this->assertSame($uwis->id, $transfer->from_user_id);
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id);
    }

    public function test_stock_manager_may_receive_a_return_for_general_affair_staff(): void
    {
        $uwis = $this->generalAffairStaff('__atf_uwis__');
        $hilman = $this->employee('__atf_hilman__');
        $asset = $this->heldAsset('__atf_printer__', $hilman);

        $this->actingAs($this->stockManager('__atf_admin__'));

        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreateAssetTransfer::class)
            ->fillForm([
                'document_type' => AssetTransferDocumentType::PengembalianBarang->value,
                'business_entity_id' => $this->entity->id,
                'letter_number' => '__ATF/000010__',
                'from_user_id' => $hilman->id,
                'to_user_id' => $uwis->id,
                'transfer_date' => now()->toDateString(),
                'details' => [['asset_id' => $asset->id, 'equipment' => null]],
            ])
            ->assertFormFieldExists('to_user_id', fn (Select $field): bool => ! $field->isDisabled())
            ->call('create')
            ->assertHasNoFormErrors();

        $undoRepeaterFake();

        $transfer = AssetTransfer::query()->where('letter_number', '__ATF/000010__')->firstOrFail();
        $this->assertSame($uwis->id, $transfer->to_user_id);
        $this->assertNull($asset->fresh()->recipient_id);
    }

    private function generalAffairStaff(string $name): User
    {
        $user = $this->operator($name);
        $user->assignRole(Role::findOrCreate(User::GENERAL_AFFAIR_ROLE, 'web'));

        return $user->fresh();
    }

    /**
     * Panel user allowed to manage transfers, without the general_affair role.
     */
    private function operator(string $name): User
    {
        $role = Role::findOrCreate('__atf_transfer_operator__', 'web');
        $role->syncPermissions(self::TRANSFER_PERMISSIONS);

        $user = User::factory()->create(['name' => $name, 'business_entity_id' => $this->entity->id]);
        $user->assignRole($role);

        return $user->fresh();
    }

    /**
     * Panel user without the general_affair role but with the Shield "Kelola
     * BA Stok" permission, like the admin role in the old flow.
     */
    private function stockManager(string $name): User
    {
        $role = Role::findOrCreate('__atf_stock_manager__', 'web');
        $role->syncPermissions([...self::TRANSFER_PERMISSIONS, self::MANAGE_STOCK_PERMISSION]);

        $user = User::factory()->create(['name' => $name, 'business_entity_id' => $this->entity->id]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function superAdmin(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'business_entity_id' => $this->entity->id]);
        $user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));

        return $user->fresh();
    }

    private function employee(string $name): User
    {
        return User::factory()->create(['name' => $name, 'business_entity_id' => $this->entity->id]);
    }

    private function stockAsset(string $name): Asset
    {
        return Asset::create([
            'name' => $name,
            'business_entity_id' => $this->entity->id,
            'condition_status' => AssetCondition::Available,
        ]);
    }

    private function heldAsset(string $name, User $holder): Asset
    {
        return Asset::create([
            'name' => $name,
            'business_entity_id' => $this->entity->id,
            'condition_status' => AssetCondition::Transferred,
            'recipient_id' => $holder->id,
            'recipient_business_entity_id' => $this->entity->id,
        ]);
    }
}
