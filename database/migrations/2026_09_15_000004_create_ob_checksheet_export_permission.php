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

        $exportPermission = Permission::firstOrCreate([
            'name' => 'export_ob::checksheet',
            'guard_name' => 'web',
        ]);

        $rolesToGrant = ['super_admin', 'admin', 'general_affair', 'audit', 'office_boy'];

        foreach ($rolesToGrant as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($exportPermission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $exportPermission = Permission::where('name', 'export_ob::checksheet')->first();
        if ($exportPermission) {
            $exportPermission->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
