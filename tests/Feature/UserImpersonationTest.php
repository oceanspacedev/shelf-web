<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\BusinessEntity;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use STS\FilamentImpersonate\Facades\Impersonation;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Impersonation is limited to the impersonator's business entities, so
     * every user in these tests shares one entity.
     */
    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (['impersonate_user', 'view_any_user', 'view_user', 'update_user'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->entity = BusinessEntity::create(['name' => '__impersonate_entity__']);
    }

    public function test_shield_exposes_impersonate_as_a_user_resource_permission(): void
    {
        $permissions = FilamentShield::getResourcePolicyActionsWithPermissions(UserResource::class);

        $this->assertSame('impersonate_user', $permissions['impersonate'] ?? null);
        $this->assertContains('impersonate', config('filament-shield.policies.single_parameter_methods'));
    }

    public function test_impersonate_labels_are_translated_to_indonesian(): void
    {
        app()->setLocale('id');

        $this->assertSame('Login Sebagai', FilamentShield::getAffixLabel('impersonate'));
        $this->assertSame('Login Sebagai', __('filament-impersonate::action.label'));
        $this->assertSame('Anda sedang login sebagai', __('filament-impersonate::banner.impersonating'));
        $this->assertSame('Kembali ke Akun Saya', __('filament-impersonate::banner.leave'));

        app()->setLocale('en');

        $this->assertSame('Impersonate', FilamentShield::getAffixLabel('impersonate'));
    }

    public function test_impersonate_requires_the_shield_permission(): void
    {
        $withoutPermission = $this->userWithRole('__impersonate_none__', ['view_any_user']);
        $withPermission = $this->userWithRole('__impersonate_allowed__', ['view_any_user', 'impersonate_user']);

        $this->assertFalse($withoutPermission->canImpersonate());
        $this->assertTrue($withPermission->canImpersonate());
    }

    public function test_only_panel_users_can_be_impersonated(): void
    {
        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['impersonate_user']));

        $this->assertTrue($this->userWithRole('__impersonate_target__')->canBeImpersonated());
        $this->assertFalse(User::factory()->create(['business_entity_id' => $this->entity->id])->canBeImpersonated());
    }

    public function test_users_outside_the_impersonators_business_entities_cannot_be_impersonated(): void
    {
        $otherEntity = BusinessEntity::create(['name' => '__impersonate_other_entity__']);
        $target = $this->userWithRole('__impersonate_target__');
        $target->update(['business_entity_id' => $otherEntity->id]);

        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['impersonate_user']));
        $this->assertFalse($target->fresh()->canBeImpersonated());

        // Super admin menjangkau semua badan usaha lewat flag, bukan lewat rolenya.
        $superAdmin = $this->userWithRole(config('filament-shield.super_admin.name'));
        $this->actingAs($superAdmin);
        $this->assertFalse($target->fresh()->canBeImpersonated());

        $superAdmin->update(['access_all_business_entities' => true]);
        $this->actingAs($superAdmin->fresh());
        $this->assertTrue($target->fresh()->canBeImpersonated());
    }

    public function test_super_admin_can_only_be_impersonated_by_super_admin(): void
    {
        $superAdminRole = config('filament-shield.super_admin.name');
        $target = $this->userWithRole($superAdminRole);

        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['impersonate_user']));
        $this->assertFalse($target->canBeImpersonated());

        $this->actingAs($this->userWithRole($superAdminRole));
        $this->assertTrue($target->canBeImpersonated());
    }

    public function test_table_action_is_hidden_without_permission(): void
    {
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($this->userWithRole('__impersonate_none__', ['view_any_user']));

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('impersonate')->table($target));
    }

    public function test_table_action_impersonates_the_selected_user(): void
    {
        $impersonator = $this->userWithRole('__impersonate_allowed__', ['view_any_user', 'impersonate_user']);
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($impersonator);

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('impersonate')->table($target))
            ->assertActionHidden(TestAction::make('impersonate')->table($impersonator))
            ->callAction(TestAction::make('impersonate')->table($target))
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertSame($impersonator->getKey(), Impersonation::getImpersonatorId());
    }

    public function test_edit_page_shows_impersonate_header_action(): void
    {
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($this->userWithRole('__impersonate_allowed__', [
            'view_any_user',
            'view_user',
            'update_user',
            'impersonate_user',
        ]));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionVisible('impersonate');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['business_entity_id' => $this->entity->id]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
