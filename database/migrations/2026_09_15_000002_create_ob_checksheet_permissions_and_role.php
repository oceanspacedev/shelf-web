<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_any_ob::checksheet',
            'view_ob::checksheet',
            'create_ob::checksheet',
            'update_ob::checksheet',
            'delete_ob::checksheet',
            'delete_any_ob::checksheet',
        ];

        foreach ($permissions as $permName) {
            Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);
        }

        // Grant all permissions to super_admin
        $superAdminRole = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);
        $superAdminRole->givePermissionTo($permissions);

        // Create office_boy role and grant view and create permissions
        $officeBoyRole = Role::firstOrCreate([
            'name' => 'office_boy',
            'guard_name' => 'web',
        ]);
        $officeBoyRole->givePermissionTo([
            'view_any_ob::checksheet',
            'view_ob::checksheet',
            'create_ob::checksheet',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'view_any_ob::checksheet',
            'view_ob::checksheet',
            'create_ob::checksheet',
            'update_ob::checksheet',
            'delete_ob::checksheet',
            'delete_any_ob::checksheet',
        ];

        Permission::whereIn('name', $permissions)->delete();
        Role::where('name', 'office_boy')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
