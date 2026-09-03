<?php

use App\Support\EnsureAssetExportPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        EnsureAssetExportPermissions::run();
    }

    public function down(): void
    {
        if (! $this->permissionTablesExist()) {
            return;
        }

        $permission = Permission::query()
            ->where('name', EnsureAssetExportPermissions::PERMISSION)
            ->where('guard_name', 'web')
            ->first();

        if ($permission === null) {
            return;
        }

        foreach (EnsureAssetExportPermissions::ROLE_NAMES as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            $role?->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionTablesExist(): bool
    {
        return Schema::hasTable('roles')
            && Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions');
    }
};
