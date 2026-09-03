<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class EnsureAssetExportPermissions
{
    public const PERMISSION = 'export_asset';

    /** @var list<string> */
    public const ROLE_NAMES = ['admin', 'general_affair'];

    /**
     * @param  list<string>|null  $roleNames
     */
    public static function run(?array $roleNames = null): void
    {
        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach ($roleNames ?? self::ROLE_NAMES as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if ($role === null) {
                continue;
            }

            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
