<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource\Pages\EditAssetTransfer;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Edit BA: menyimpan perubahan non-struktural (scan dokumen, tanggal, nomor
 * surat) tidak memindahkan aset lagi, dan perubahan struktural yang ditolak
 * aturan siklus hidup dibatalkan seluruhnya.
 */
class AssetTransferEditTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->entity = BusinessEntity::create(['name' => '__ate_entity__', 'format' => 'ATE/']);
    }

    public function test_saving_an_older_transfer_does_not_move_its_assets_again(): void
    {
        $hilman = $this->employee('__ate_hilman__');
        $budi = $this->employee('__ate_budi__');
        $asset = $this->heldAsset('__ate_laptop__', $hilman);

        $first = $this->appliedTransfer(AssetTransferDocumentType::PengalihanBarang, $hilman, $budi, $asset, '__ATE/000001__');
        $this->appliedTransfer(AssetTransferDocumentType::PengalihanBarang, $budi, $hilman, $asset, '__ATE/000002__');
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditAssetTransfer::class, ['record' => $first->getRouteKey()])
            ->fillForm(['transfer_date' => now()->subDay()->toDateString()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(now()->subDay()->toDateString(), substr((string) $first->fresh()->transfer_date, 0, 10));
        $this->assertSame($hilman->id, $asset->fresh()->recipient_id, 'BA lama yang disimpan ulang tidak boleh memindahkan aset kembali.');
    }

    public function test_structural_change_rejected_by_the_lifecycle_is_rolled_back(): void
    {
        $generalAffair = $this->employee('__ate_ga__');
        $generalAffair->assignRole(Role::findOrCreate(User::GENERAL_AFFAIR_ROLE, 'web'));
        $hilman = $this->employee('__ate_hilman__');
        $budi = $this->employee('__ate_budi__');
        $siti = $this->employee('__ate_siti__');
        $asset = $this->stockAsset('__ate_printer__');

        $serahTerima = $this->appliedTransfer(AssetTransferDocumentType::SerahTerima, $generalAffair->fresh(), $hilman, $asset, '__ATE/000003__');
        $this->appliedTransfer(AssetTransferDocumentType::PengalihanBarang, $hilman, $budi, $asset, '__ATE/000004__');

        $this->actingAs($this->superAdmin());

        Livewire::test(EditAssetTransfer::class, ['record' => $serahTerima->getRouteKey()])
            ->fillForm(['to_user_id' => $siti->id])
            ->call('save')
            ->assertNotified('Tidak dapat menyimpan perubahan');

        $this->assertSame($hilman->id, $serahTerima->fresh()->to_user_id, 'Perubahan BA ikut dibatalkan.');
        $this->assertSame($budi->id, $asset->fresh()->recipient_id);
    }

    private function appliedTransfer(AssetTransferDocumentType $type, User $from, User $to, Asset $asset, string $letterNumber): AssetTransfer
    {
        $transfer = AssetTransfer::create([
            'business_entity_id' => $this->entity->id,
            'document_type' => $type,
            'letter_number' => $letterNumber,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ]);

        AssetTransferDetail::create([
            'asset_transfer_id' => $transfer->id,
            'asset_id' => $asset->id,
        ]);

        $transfer->applyLifecycleToAssets();

        return $transfer;
    }

    private function superAdmin(): User
    {
        $user = $this->employee('__ate_super__');
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
