<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'View:Horizon',
        'View:LogViewer',
        'Download:LogViewer',
        'Delete:LogViewer',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('name', config('filament-shield.super_admin.name', 'super_admin'))
            ->where('guard_name', 'web')
            ->first()
            ?->givePermissionTo($permissions);

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
