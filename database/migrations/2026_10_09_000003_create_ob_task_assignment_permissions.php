<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Atasan menugaskan pembersihan ke OB dari template. Izin template mengikuti
 * pola Shield (view_any, view, create, update, delete, delete_any).
 */
return new class extends Migration
{
    private const SUPER_ADMIN = 'super_admin';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'assign_ob::checksheet',
        'view_any_ob::task::template',
        'view_ob::task::template',
        'create_ob::task::template',
        'update_ob::task::template',
        'delete_ob::task::template',
        'delete_any_ob::task::template',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = Role::query()
            ->whereIn('name', [config('filament-shield.super_admin.name', self::SUPER_ADMIN), 'admin'])
            ->where('guard_name', 'web')
            ->get();

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::findOrCreate($name, 'web');

            foreach ($roles as $role) {
                $role->givePermissionTo($permission);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
