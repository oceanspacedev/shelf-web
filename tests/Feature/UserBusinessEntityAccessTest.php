<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Imports\UserImport;
use App\Models\BusinessEntity;
use App\Models\User;
use App\Services\TalentaUserImportService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserBusinessEntityAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const MANAGE_PERMISSIONS = ['view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user'];

    private BusinessEntity $alpha;

    private BusinessEntity $beta;

    private BusinessEntity $gamma;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach ([...self::MANAGE_PERMISSIONS, 'manage_access_user', 'manage_business_entity_access_user', 'impersonate_user'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->alpha = BusinessEntity::create(['name' => '__bea_alpha__']);
        $this->beta = BusinessEntity::create(['name' => '__bea_beta__']);
        $this->gamma = BusinessEntity::create(['name' => '__bea_gamma__']);
    }

    public function test_accessible_entities_are_own_entity_plus_granted_ones(): void
    {
        $user = $this->scopedUser($this->alpha, granted: [$this->beta]);

        $this->assertFalse($user->hasUnrestrictedBusinessEntityAccess());
        $this->assertEqualsCanonicalizing([$this->alpha->id, $this->beta->id], $user->accessibleBusinessEntityIds());
        $this->assertTrue($user->canAccessBusinessEntity($this->alpha));
        $this->assertTrue($user->canAccessBusinessEntity($this->beta->id));
        $this->assertFalse($user->canAccessBusinessEntity($this->gamma->id));
        $this->assertFalse($user->canAccessBusinessEntity(null));

        $superAdmin = $this->superAdmin();
        $this->assertTrue($superAdmin->hasUnrestrictedBusinessEntityAccess());
        $this->assertTrue($superAdmin->canAccessBusinessEntity($this->gamma));
        $this->assertTrue($superAdmin->canAccessBusinessEntity(null));
    }

    public function test_user_without_entity_or_grants_can_access_nothing(): void
    {
        $user = $this->scopedUser(null);

        $this->assertSame([], $user->accessibleBusinessEntityIds());
        $this->assertFalse($user->canAccessBusinessEntity($this->alpha));
    }

    public function test_scoped_admin_only_lists_users_in_accessible_entities_and_themself(): void
    {
        $admin = $this->scopedUser($this->alpha, granted: [$this->beta]);
        $inAlpha = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $inBeta = User::factory()->create(['business_entity_id' => $this->beta->id]);
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);
        $withoutEntity = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$admin, $inAlpha, $inBeta])
            ->assertCanNotSeeTableRecords([$inGamma, $withoutEntity]);
    }

    public function test_super_admin_lists_users_from_every_entity(): void
    {
        $inAlpha = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);
        $withoutEntity = User::factory()->create();

        $this->actingAs($this->superAdmin());

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$inAlpha, $inGamma, $withoutEntity]);
    }

    public function test_scoped_admin_cannot_open_users_outside_their_entities(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $inAlpha = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);

        $this->actingAs($admin);

        $this->get(UserResource::getUrl('edit', ['record' => $inAlpha]))->assertOk();
        $this->get(UserResource::getUrl('edit', ['record' => $inGamma]))->assertNotFound();
        $this->get(UserResource::getUrl('view', ['record' => $inGamma]))->assertNotFound();
    }

    public function test_policy_denies_records_outside_the_business_entity_scope(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $inAlpha = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);

        $this->assertTrue($admin->can('view', $inAlpha));
        $this->assertTrue($admin->can('update', $inAlpha));
        $this->assertTrue($admin->can('delete', $inAlpha));
        $this->assertTrue($admin->can('update', $admin));

        $this->assertFalse($admin->can('view', $inGamma));
        $this->assertFalse($admin->can('update', $inGamma));
        $this->assertFalse($admin->can('delete', $inGamma));

        $this->assertTrue($this->superAdmin()->can('update', $inGamma));
    }

    public function test_scoped_admin_can_only_create_users_inside_accessible_entities(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '__bea_outside__',
                'business_entity_id' => $this->gamma->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['business_entity_id']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => '__bea_no_entity__'])
            ->call('create')
            ->assertHasFormErrors(['business_entity_id' => 'required']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => '__bea_inside__',
                'business_entity_id' => $this->alpha->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseMissing('users', ['name' => '__bea_outside__']);
        $this->assertDatabaseMissing('users', ['name' => '__bea_no_entity__']);
        $this->assertDatabaseHas('users', ['name' => '__bea_inside__', 'business_entity_id' => $this->alpha->id]);
    }

    public function test_super_admin_may_create_users_without_an_entity(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => '__bea_super_created__'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', ['name' => '__bea_super_created__', 'business_entity_id' => null]);
    }

    public function test_super_admin_grants_extra_business_entity_access(): void
    {
        $target = $this->scopedUser($this->alpha);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldVisible('accessibleBusinessEntities')
            ->fillForm(['accessibleBusinessEntities' => [$this->beta->id, $this->gamma->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$this->alpha->id, $this->beta->id, $this->gamma->id],
            $target->fresh()->accessibleBusinessEntityIds(),
        );
    }

    public function test_scoped_admin_cannot_grant_business_entity_access(): void
    {
        // The real `admin` role holds "Kelola Akses" (section visible) but not
        // "Kelola Akses Badan Usaha", so the access field stays hidden.
        $admin = $this->scopedUser($this->alpha);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $admin = $admin->fresh();

        $target = User::factory()->create(['business_entity_id' => $this->alpha->id]);

        $this->actingAs($admin);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldHidden('accessibleBusinessEntities')
            ->fillForm([
                'name' => $target->name,
                'business_entity_id' => $this->alpha->id,
                'accessibleBusinessEntities' => [$this->gamma->id],
            ])
            ->call('save');

        $this->assertSame([$this->alpha->id], $target->fresh()->accessibleBusinessEntityIds());
    }

    public function test_user_flagged_for_all_entities_reaches_entities_created_later(): void
    {
        $user = $this->scopedUser($this->alpha);
        $user->forceFill(['access_all_business_entities' => true])->save();
        $user = $user->fresh();

        $createdLater = BusinessEntity::create(['name' => '__bea_created_later__']);

        $this->assertTrue($user->hasUnrestrictedBusinessEntityAccess());
        $this->assertTrue($user->canAccessBusinessEntity($createdLater));
        $this->assertTrue($user->canAccessBusinessEntity(null));
    }

    public function test_flagged_admin_lists_users_of_every_entity_but_never_super_admins(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $admin->forceFill(['access_all_business_entities' => true])->save();
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);
        $withoutEntity = User::factory()->create();
        $superAdmin = $this->superAdmin();

        $this->actingAs($admin->fresh());

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$inGamma, $withoutEntity])
            ->assertCanNotSeeTableRecords([$superAdmin]);

        $this->get(UserResource::getUrl('edit', ['record' => $superAdmin]))->assertNotFound();
    }

    public function test_only_super_admin_sees_and_sets_the_all_entities_flag(): void
    {
        $target = $this->scopedUser($this->alpha);

        $this->actingAs($this->superAdmin());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldVisible('access_all_business_entities')
            ->fillForm(['access_all_business_entities' => true])
            ->assertFormFieldHidden('accessibleBusinessEntities')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($target->fresh()->access_all_business_entities);

        $admin = $this->scopedUser($this->alpha);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $admin->forceFill(['access_all_business_entities' => true])->save();
        $other = User::factory()->create(['business_entity_id' => $this->alpha->id]);

        $this->actingAs($admin->fresh());

        Livewire::test(EditUser::class, ['record' => $other->getRouteKey()])
            ->assertFormFieldHidden('access_all_business_entities')
            ->assertFormFieldHidden('accessibleBusinessEntities')
            ->fillForm(['name' => $other->name, 'access_all_business_entities' => true])
            ->call('save');

        $this->assertFalse($other->fresh()->access_all_business_entities);
    }

    public function test_flagged_importer_may_still_create_new_business_entities(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $admin->forceFill(['access_all_business_entities' => true])->save();

        (new UserImport)->forActor($admin->fresh())->collection(Collection::make([
            collect(['__bea_flag_import__', '__bea_flag_new_entity__', '__bea_title__', '__bea_flag_emp__', '']),
        ]));

        $newEntityId = BusinessEntity::query()->where('name', '__bea_flag_new_entity__')->value('id');

        $this->assertNotNull($newEntityId, 'Seperti alur lama, badan usaha baru dibuat saat import.');
        $this->assertDatabaseHas('users', ['name' => '__bea_flag_import__', 'business_entity_id' => $newEntityId]);
    }

    public function test_effective_entities_are_listed_by_name_from_loaded_relations(): void
    {
        $user = $this->scopedUser($this->beta, granted: [$this->gamma, $this->alpha]);

        $this->assertSame(
            [$this->alpha->name, $this->beta->name, $this->gamma->name],
            $user->effectiveBusinessEntities()->pluck('name')->all(),
        );

        $loaded = User::query()->with(['businessEntity', 'accessibleBusinessEntities'])->findOrFail($user->id);

        $this->assertSame(
            [$this->alpha->name, $this->beta->name, $this->gamma->name],
            $loaded->effectiveBusinessEntities()->pluck('name')->all(),
        );
    }

    public function test_excel_import_skips_rows_outside_the_importers_entities(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $existingInGamma = User::factory()->create([
            'business_entity_id' => $this->gamma->id,
            'employee_id' => '__bea_emp_gamma__',
        ]);

        (new UserImport)->forActor($admin)->collection(Collection::make([
            collect(['Nama', 'Badan Usaha', 'Jabatan', 'Employee ID', 'No. HP']),
            collect(['__bea_import_alpha__', $this->alpha->name, '__bea_title__', '__bea_emp_alpha__', '']),
            collect(['__bea_import_gamma__', $this->gamma->name, '__bea_title__', '__bea_emp_gamma_new__', '']),
            collect(['__bea_import_unknown__', '__bea_brand_new_entity__', '__bea_title__', '__bea_emp_unknown__', '']),
            collect(['__bea_moved__', $this->alpha->name, '__bea_title__', '__bea_emp_gamma__', '']),
        ]));

        $this->assertDatabaseHas('users', ['name' => '__bea_import_alpha__', 'business_entity_id' => $this->alpha->id]);
        $this->assertDatabaseMissing('users', ['name' => '__bea_import_gamma__']);
        $this->assertDatabaseMissing('users', ['name' => '__bea_import_unknown__']);
        $this->assertDatabaseMissing('business_entities', ['name' => '__bea_brand_new_entity__']);

        $existingInGamma->refresh();
        $this->assertSame($this->gamma->id, $existingInGamma->business_entity_id);
        $this->assertNotSame('__bea_moved__', $existingInGamma->name);
    }

    public function test_talenta_import_rejects_rows_outside_the_importers_entities(): void
    {
        $admin = $this->scopedUser($this->alpha);
        $existingInGamma = User::factory()->create([
            'business_entity_id' => $this->gamma->id,
            'employee_id' => '__bea_tal_gamma__',
        ]);

        $result = (new TalentaUserImportService($admin))->importFromContent(json_encode([
            'data' => [
                ['id_employee' => '__bea_tal_alpha__', 'full_name' => '__bea_tal_alpha_name__', 'branch' => $this->alpha->name],
                ['id_employee' => '__bea_tal_gamma_new__', 'full_name' => '__bea_tal_gamma_name__', 'branch' => $this->gamma->name],
                ['id_employee' => '__bea_tal_gamma__', 'full_name' => '__bea_tal_renamed__'],
                ['id_employee' => '__bea_tal_default__', 'full_name' => '__bea_tal_default_name__'],
            ],
        ]));

        $this->assertSame(2, $result['success_count']);
        $this->assertSame(2, $result['error_count']);
        $this->assertDatabaseHas('users', ['employee_id' => '__bea_tal_alpha__', 'business_entity_id' => $this->alpha->id]);
        $this->assertDatabaseHas('users', ['employee_id' => '__bea_tal_default__', 'business_entity_id' => $this->alpha->id]);
        $this->assertDatabaseMissing('users', ['employee_id' => '__bea_tal_gamma_new__']);
        $this->assertNotSame('__bea_tal_renamed__', $existingInGamma->fresh()->name);
    }

    /**
     * A panel user with full user-management permissions but no super admin
     * role, belonging to $own and granted the $granted entities.
     *
     * @param  list<BusinessEntity>  $granted
     */
    public function test_role_choices_only_include_roles_within_the_editors_permissions(): void
    {
        $editor = $this->accessManager($this->alpha);
        $target = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $lesser = Role::findOrCreate('__bea_lesser__', 'web');
        $lesser->syncPermissions(['view_user']);
        $greater = Role::findOrCreate('__bea_greater__', 'web');
        $greater->syncPermissions(['view_user', 'impersonate_user']);

        $this->actingAs($editor);

        $assignable = UserResource::assignableRoleIds();
        $this->assertContains($lesser->id, $assignable);
        $this->assertNotContains($greater->id, $assignable);
        $this->assertNotContains(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web')->id, $assignable);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => $greater->id])
            ->call('save');
        $this->assertFalse($target->fresh()->hasRole($greater));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->fillForm(['roles' => $lesser->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($target->fresh()->hasRole($lesser));
    }

    public function test_role_outside_the_editors_permissions_is_locked_and_kept(): void
    {
        $editor = $this->accessManager($this->alpha);
        $greater = Role::findOrCreate('__bea_greater__', 'web');
        $greater->syncPermissions(['view_user', 'impersonate_user']);
        $target = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $target->assignRole($greater);

        $this->actingAs($editor);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldDisabled('roles')
            ->fillForm(['name' => '__bea_locked_role__'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$greater->name], $target->fresh()->getRoleNames()->all());
    }

    public function test_saving_a_user_without_manage_access_keeps_their_roles(): void
    {
        $editor = $this->scopedUser($this->alpha);
        $target = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $target->assignRole(Role::findOrCreate('__bea_target_role__', 'web'));

        $this->actingAs($editor);

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldHidden('roles')
            ->fillForm(['name' => '__bea_renamed__'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('__bea_renamed__', $target->fresh()->name);
        $this->assertTrue($target->fresh()->hasRole('__bea_target_role__'));
    }

    public function test_restricted_entity_access_manager_cannot_widen_access(): void
    {
        $manager = $this->scopedUser($this->alpha);
        $manager->givePermissionTo('manage_business_entity_access_user');
        $manager = $manager->fresh();

        $this->actingAs($manager);

        Livewire::test(EditUser::class, ['record' => $manager->getRouteKey()])
            ->assertFormFieldHidden('access_all_business_entities')
            ->assertFormFieldHidden('accessibleBusinessEntities')
            ->fillForm([
                'name' => $manager->name,
                'access_all_business_entities' => true,
                'accessibleBusinessEntities' => [$this->gamma->id],
            ])
            ->call('save');

        $this->assertFalse($manager->fresh()->access_all_business_entities);
        $this->assertSame([$this->alpha->id], $manager->fresh()->accessibleBusinessEntityIds());
    }

    public function test_entity_access_permission_works_without_manage_access(): void
    {
        $manager = $this->scopedUser($this->alpha);
        $manager->givePermissionTo('manage_business_entity_access_user');
        $manager->forceFill(['access_all_business_entities' => true])->save();
        $target = User::factory()->create(['business_entity_id' => $this->alpha->id]);

        $this->actingAs($manager->fresh());

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertFormFieldHidden('roles')
            ->assertFormFieldVisible('access_all_business_entities');
    }

    public function test_super_admin_without_the_flag_is_scoped_like_everyone_else(): void
    {
        $superAdmin = $this->superAdmin();
        $superAdmin->forceFill(['access_all_business_entities' => false, 'business_entity_id' => $this->alpha->id])->save();
        $inAlpha = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $inGamma = User::factory()->create(['business_entity_id' => $this->gamma->id]);

        $this->actingAs($superAdmin->fresh());

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$inAlpha])
            ->assertCanNotSeeTableRecords([$inGamma]);
    }

    /**
     * Seperti role admin: boleh "Kelola Akses" di dalam badan usahanya.
     */
    private function accessManager(BusinessEntity $own): User
    {
        $user = $this->scopedUser($own);
        $user->givePermissionTo('manage_access_user');

        return $user->fresh();
    }

    private function scopedUser(?BusinessEntity $own, array $granted = []): User
    {
        $role = Role::findOrCreate('__bea_manager__', 'web');
        $role->syncPermissions(self::MANAGE_PERMISSIONS);

        $user = User::factory()->create(['business_entity_id' => $own?->id]);
        $user->assignRole($role);
        $user->accessibleBusinessEntities()->sync(collect($granted)->map->getKey()->all());

        return $user->fresh();
    }

    /**
     * Seperti di production: akses badan usaha super admin datang dari flag,
     * izinnya dari permission role super_admin.
     */
    private function superAdmin(): User
    {
        $user = User::factory()->create(['access_all_business_entities' => true]);
        $user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));

        return $user->fresh();
    }
}
