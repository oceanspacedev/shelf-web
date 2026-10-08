<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetQrLabelHistoryResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\ViewAsset;
use App\Filament\Resources\ObChecksheetResource\Pages\ListObChecksheets;
use App\Models\Asset;
use App\Models\AssetQrLabelHistory;
use App\Models\AssetService;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\ObChecksheet;
use App\Models\User;
use App\Support\ObservabilityAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Akses hanya dari permission Shield: nama role (termasuk super_admin) tidak
 * memberi pengecualian apa pun.
 */
class PermissionOnlyAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, array{0: string, 1: class-string}> */
    private const ABILITIES = [
        'view_any_asset::qr::label::history' => ['viewAny', AssetQrLabelHistory::class],
        'manage_condition_asset' => ['manageCondition', Asset::class],
        'repair_validation_asset' => ['repairValidation', Asset::class],
        'regenerate_qr_asset' => ['regenerateQr', Asset::class],
        'update_recipient_asset' => ['updateRecipient', Asset::class],
        'merge_asset' => ['merge', Asset::class],
        'manage_stock_asset::transfer' => ['manageStock', AssetTransfer::class],
        'update_core_asset::transfer' => ['updateCore', AssetTransfer::class],
        'delete_detail_asset::transfer' => ['deleteDetail', AssetTransfer::class],
        'view_all_asset::service' => ['viewAll', AssetService::class],
        'update_all_asset::service' => ['updateAll', AssetService::class],
        'delete_all_asset::service' => ['deleteAll', AssetService::class],
        'export_asset::service' => ['export', AssetService::class],
        'view_all_ob::checksheet' => ['viewAll', ObChecksheet::class],
        'update_all_ob::checksheet' => ['updateAll', ObChecksheet::class],
        'delete_all_ob::checksheet' => ['deleteAll', ObChecksheet::class],
        'import_user' => ['import', User::class],
        'manage_access_user' => ['manageAccess', User::class],
        'manage_business_entity_access_user' => ['manageBusinessEntityAccess', User::class],
    ];

    /** @var list<string> */
    private const EXTRA_PERMISSIONS = [
        'view_any_asset', 'view_asset',
        'view_any_ob::checksheet', 'view_ob::checksheet', 'delete_ob::checksheet', 'delete_any_ob::checksheet',
        'create_asset::qr::label::history', 'update_asset::qr::label::history',
        'delete_asset::qr::label::history', 'delete_any_asset::qr::label::history',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);

        foreach ([...array_keys(self::ABILITIES), ...array_keys(ObservabilityAccess::shieldPermissions()), ...self::EXTRA_PERMISSIONS] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_former_hardcoded_roles_grant_nothing_without_permissions(): void
    {
        foreach (['super_admin', 'admin', 'general_affair', 'audit'] as $roleName) {
            $user = $this->userWithRole($roleName);

            foreach (self::ABILITIES as [$ability, $model]) {
                $this->assertFalse($user->can($ability, $model), "{$roleName} → {$ability} {$model}");
            }

            $this->assertFalse($user->canManageStockTransfers(), $roleName);
            $this->assertFalse($user->hasUnrestrictedBusinessEntityAccess(), $roleName);
            $this->assertFalse(Gate::forUser($user)->allows('viewHorizon'), $roleName);
            $this->assertFalse(Gate::forUser($user)->allows('viewPulse'), $roleName);
        }
    }

    public function test_each_permission_grants_its_ability_under_any_role_name(): void
    {
        foreach (self::ABILITIES as $permission => [$ability, $model]) {
            $user = $this->userWithRole('__permission_only__', [$permission]);

            $this->assertTrue($user->can($ability, $model), "{$permission} → {$ability} {$model}");
        }
    }

    public function test_records_of_other_users_need_the_all_permission(): void
    {
        $owner = User::factory()->create();
        $checksheet = (new ObChecksheet)->forceFill(['user_id' => $owner->id]);
        $checksheet->exists = true;
        $service = (new AssetService)->forceFill(['created_by' => $owner->id]);
        $service->exists = true;

        $ownOnly = $this->userWithRole('__own_only__', ['view_ob::checksheet', 'update_ob::checksheet', 'view_asset::service', 'update_asset::service']);
        $this->assertFalse($ownOnly->can('view', $checksheet));
        $this->assertFalse($ownOnly->can('update', $checksheet));
        $this->assertFalse($ownOnly->can('view', $service));
        $this->assertFalse($ownOnly->can('update', $service));

        $supervisor = $this->userWithRole('__supervisor__', [
            'view_ob::checksheet', 'view_all_ob::checksheet',
            'view_asset::service', 'view_all_asset::service',
        ]);
        $this->assertTrue($supervisor->can('view', $checksheet));
        $this->assertFalse($supervisor->can('update', $checksheet));
        $this->assertTrue($supervisor->can('view', $service));
        $this->assertFalse($supervisor->can('update', $service));
    }

    public function test_qr_label_history_stays_read_only_whatever_the_permissions(): void
    {
        $this->actingAs($this->userWithRole('__qr_everything__', [
            'view_any_asset::qr::label::history', 'create_asset::qr::label::history', 'update_asset::qr::label::history',
            'delete_asset::qr::label::history', 'delete_any_asset::qr::label::history',
        ]));
        $history = new AssetQrLabelHistory;

        $this->assertTrue(AssetQrLabelHistoryResource::canViewAny());
        $this->assertFalse(AssetQrLabelHistoryResource::canCreate());
        $this->assertFalse(AssetQrLabelHistoryResource::canEdit($history));
        $this->assertFalse(AssetQrLabelHistoryResource::canDelete($history));
        $this->assertFalse(AssetQrLabelHistoryResource::canDeleteAny());
    }

    public function test_bulk_delete_skips_records_of_other_users_without_delete_all(): void
    {
        $user = $this->userWithRole('__ob_bulk__', [
            'view_any_ob::checksheet', 'view_ob::checksheet', 'view_all_ob::checksheet',
            'delete_ob::checksheet', 'delete_any_ob::checksheet',
        ]);
        $own = ObChecksheet::create(['user_id' => $user->id, 'room' => '__perm_own__', 'before_photo' => 'ob/own.jpg']);
        $other = ObChecksheet::create(['user_id' => User::factory()->create()->id, 'room' => '__perm_other__', 'before_photo' => 'ob/other.jpg']);

        $this->actingAs($user);

        Livewire::test(ListObChecksheets::class)
            ->callTableBulkAction('delete', [$own, $other]);

        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($other);
    }

    public function test_merge_cannot_reach_assets_outside_the_users_business_entities(): void
    {
        $alpha = BusinessEntity::create(['name' => '__perm_alpha__']);
        $beta = BusinessEntity::create(['name' => '__perm_beta__']);
        $user = $this->userWithRole('__merger__', ['view_any_asset', 'view_asset', 'merge_asset']);
        $user->update(['business_entity_id' => $alpha->id]);
        $target = Asset::create(['name' => '__perm_merge_target__', 'business_entity_id' => $alpha->id]);
        $outside = Asset::create(['name' => '__perm_merge_outside__', 'business_entity_id' => $beta->id]);

        $this->actingAs($user->fresh());

        Livewire::test(ViewAsset::class, ['record' => $target->getRouteKey()])
            ->assertActionVisible('mergeAsset')
            ->callAction('mergeAsset', data: ['source_asset_id' => $outside->id])
            ->assertHasActionErrors(['source_asset_id']);

        $this->assertModelExists($outside);
    }

    public function test_recipient_choices_are_limited_to_the_users_business_entities(): void
    {
        $alpha = BusinessEntity::create(['name' => '__perm_alpha__']);
        $beta = BusinessEntity::create(['name' => '__perm_beta__']);
        $user = $this->userWithRole('__recipient_editor__', ['update_recipient_asset']);
        $user->update(['business_entity_id' => $alpha->id]);
        $inAlpha = User::factory()->create(['business_entity_id' => $alpha->id]);
        $inBeta = User::factory()->create(['business_entity_id' => $beta->id]);
        $currentHolder = User::factory()->create(['business_entity_id' => $beta->id]);
        $asset = (new Asset)->forceFill(['recipient_id' => $currentHolder->id]);

        $this->actingAs($user->fresh());

        $options = (new ReflectionMethod(AssetResource::class, 'recipientOptions'))->invoke(null, $asset);

        $this->assertArrayHasKey($inAlpha->id, $options);
        $this->assertArrayHasKey($currentHolder->id, $options);
        $this->assertArrayNotHasKey($inBeta->id, $options);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->fresh();
    }
}
