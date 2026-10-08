<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Kelola BA Stok": membuat BA Serah Terima/Pengembalian atas nama staf GA
 * mana pun. Role admin sudah melakukannya di alur lama, jadi diberi izin ini
 * supaya alurnya tidak terputus.
 */
return new class extends Migration
{
    private const PERMISSION = 'manage_stock_asset::transfer';

    /** @var list<string> */
    private const ROLE_NAMES = ['super_admin', 'admin'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (self::ROLE_NAMES as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::where('name', self::PERMISSION)
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
