<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetTransferResource;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\AssetRequestApproval;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\Division;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pembatasan per badan usaha pada Aset, BA, dan Pengajuan untuk user tanpa
 * flag "akses semua badan usaha".
 */
class BusinessEntityScopingTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISSIONS = [
        'view_any_asset', 'view_asset', 'update_asset',
        'view_any_asset::transfer', 'view_asset::transfer', 'update_asset::transfer',
        'view_any_asset::request', 'view_asset::request',
    ];

    private BusinessEntity $alpha;

    private BusinessEntity $beta;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->alpha = BusinessEntity::create(['name' => '__bes_alpha__']);
        $this->beta = BusinessEntity::create(['name' => '__bes_beta__']);
    }

    public function test_assets_are_visible_when_owned_held_or_used_in_an_accessible_entity(): void
    {
        $viewer = $this->restrictedUser($this->alpha);
        $alphaEmployee = $this->employee($this->alpha);
        $betaEmployee = $this->employee($this->beta);

        $owned = $this->asset($this->alpha, $this->beta, $betaEmployee);
        $heldForAlpha = $this->asset($this->beta, $this->alpha, $betaEmployee);
        $usedByAlphaEmployee = $this->asset($this->beta, $this->beta, $alphaEmployee);
        $betaOnly = $this->asset($this->beta, $this->beta, $betaEmployee);
        $ids = [$owned->id, $heldForAlpha->id, $usedByAlphaEmployee->id, $betaOnly->id];

        $this->actingAs($viewer);

        $this->assertEqualsCanonicalizing(
            [$owned->id, $heldForAlpha->id, $usedByAlphaEmployee->id],
            AssetResource::getEloquentQuery()->whereKey($ids)->pluck('id')->all(),
        );
        $this->assertTrue($viewer->can('view', $usedByAlphaEmployee));
        $this->assertFalse($viewer->can('view', $betaOnly));
        $this->get(AssetResource::getUrl('view', ['record' => $betaOnly]))->assertNotFound();

        $viewer->forceFill(['access_all_business_entities' => true])->save();
        $this->actingAs($viewer->fresh());

        $this->assertCount(4, AssetResource::getEloquentQuery()->whereKey($ids)->get());
    }

    public function test_transfers_are_limited_to_accessible_entities(): void
    {
        $viewer = $this->restrictedUser($this->alpha);
        $from = $this->employee($this->alpha);
        $to = $this->employee($this->alpha);
        $alphaTransfer = $this->transfer($this->alpha, $from, $to);
        $betaTransfer = $this->transfer($this->beta, $from, $to);

        $this->actingAs($viewer);

        $this->assertSame(
            [$alphaTransfer->id],
            AssetTransferResource::getEloquentQuery()->whereKey([$alphaTransfer->id, $betaTransfer->id])->pluck('id')->all(),
        );
        $this->assertFalse($viewer->can('view', $betaTransfer));
        $this->get(AssetTransferResource::getUrl('view', ['record' => $betaTransfer]))->assertNotFound();
        $this->get(route('asset-transfer.download', $betaTransfer))->assertForbidden();
    }

    public function test_requests_from_the_entity_on_its_assets_own_or_awaiting_approval_are_visible(): void
    {
        $viewer = $this->restrictedUser($this->alpha);
        $alphaRequester = $this->employee($this->alpha);
        $betaRequester = $this->employee($this->beta);
        $alphaAsset = $this->asset($this->alpha, $this->beta, $betaRequester);

        $fromAlpha = $this->request($alphaRequester);
        $fromBeta = $this->request($betaRequester);
        $onAlphaAsset = $this->request($betaRequester, $alphaAsset);
        $own = $this->request($viewer);
        $awaitingApproval = $this->request($betaRequester);
        AssetRequestApproval::create([
            'asset_request_id' => $awaitingApproval->id,
            'user_id' => $viewer->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $this->actingAs($viewer);

        $this->assertEqualsCanonicalizing(
            [$fromAlpha->id, $onAlphaAsset->id, $own->id, $awaitingApproval->id],
            AssetRequestResource::getEloquentQuery()
                ->whereKey([$fromAlpha->id, $fromBeta->id, $onAlphaAsset->id, $own->id, $awaitingApproval->id])
                ->pluck('id')
                ->all(),
        );
        $this->assertTrue($viewer->can('view', $onAlphaAsset));
        $this->assertFalse($viewer->can('view', $fromBeta));
    }

    public function test_business_entity_options_follow_the_viewer(): void
    {
        $viewer = $this->restrictedUser($this->alpha);

        $this->assertSame([$this->alpha->id => $this->alpha->name], BusinessEntity::optionsFor($viewer));
        $this->assertEqualsCanonicalizing(
            [$this->alpha->id, $this->beta->id],
            array_keys(BusinessEntity::optionsFor($viewer, $this->beta->id)),
            'Nilai record yang sedang diedit tetap tersedia.',
        );

        $viewer->forceFill(['access_all_business_entities' => true])->save();
        $this->assertArrayHasKey($this->beta->id, BusinessEntity::optionsFor($viewer->fresh()));
    }

    public function test_restricted_actor_cannot_move_assets_outside_their_entities(): void
    {
        $generalAffair = $this->restrictedUser($this->alpha);
        $generalAffair->assignRole(Role::findOrCreate(User::GENERAL_AFFAIR_ROLE, 'web'));
        $generalAffair = $generalAffair->fresh();
        $employee = $this->employee($this->alpha);
        $betaStock = Asset::create([
            'name' => '__bes_stok_beta__',
            'business_entity_id' => $this->beta->id,
            'recipient_business_entity_id' => $this->beta->id,
            'condition_status' => AssetCondition::Available,
        ]);

        $transfer = $this->transfer($this->alpha, $generalAffair, $employee, AssetTransferDocumentType::SerahTerima);
        AssetTransferDetail::create(['asset_transfer_id' => $transfer->id, 'asset_id' => $betaStock->id]);

        try {
            $transfer->applyLifecycleToAssets(actor: $generalAffair);
            $this->fail('Aset di luar badan usaha aktor tidak boleh dipindahkan.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('berada di luar badan usaha yang bisa Anda akses', $exception->getMessage());
        }

        $this->assertNull($betaStock->fresh()->recipient_id);
    }

    private function restrictedUser(BusinessEntity $entity): User
    {
        $role = Role::findOrCreate('__bes_operator__', 'web');
        $role->syncPermissions(self::PERMISSIONS);

        $user = User::factory()->create(['business_entity_id' => $entity->id]);
        $user->assignRole($role);

        return $user->fresh();
    }

    private function employee(BusinessEntity $entity): User
    {
        return User::factory()->create(['business_entity_id' => $entity->id]);
    }

    private function asset(BusinessEntity $owner, BusinessEntity $recipientEntity, User $holder): Asset
    {
        return Asset::create([
            'name' => '__bes_asset_'.uniqid().'__',
            'business_entity_id' => $owner->id,
            'recipient_business_entity_id' => $recipientEntity->id,
            'recipient_id' => $holder->id,
            'condition_status' => AssetCondition::Transferred,
        ]);
    }

    private function transfer(BusinessEntity $entity, User $from, User $to, AssetTransferDocumentType $type = AssetTransferDocumentType::PengalihanBarang): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'document_type' => $type,
            'letter_number' => '__BES/'.uniqid().'__',
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ]);
    }

    private function request(User $requester, ?Asset $asset = null): AssetRequest
    {
        return AssetRequest::create([
            'user_id' => $requester->id,
            'division_id' => Division::create(['name' => '__bes_division_'.uniqid().'__'])->id,
            'type' => $asset ? AssetRequestType::Penarikan : AssetRequestType::Pengadaan,
            'asset_id' => $asset?->id,
            'item_name' => $asset ? null : '__bes_item__',
            'qty' => 1,
        ]);
    }
}
