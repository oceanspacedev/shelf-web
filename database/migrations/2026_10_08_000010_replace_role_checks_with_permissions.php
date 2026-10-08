<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Akses aplikasi kini hanya dari permission Shield; tidak ada lagi
 * pengecualian berdasarkan nama role di kode. Supaya tidak ada yang kehilangan
 * akses saat deploy, setiap role diberi permission yang selama ini ia dapat
 * dari pengecualian tersebut. Setelah ini semuanya diatur dari Shield > Roles.
 */
return new class extends Migration
{
    private const SUPER_ADMIN = 'super_admin';

    /**
     * Permission baru, menggantikan cek role yang tidak punya permission.
     *
     * @var array<string, list<string>>
     */
    private const NEW_PERMISSIONS = [
        'manage_condition_asset' => [self::SUPER_ADMIN, 'general_affair'],
        'repair_validation_asset' => [self::SUPER_ADMIN, 'general_affair'],
        'regenerate_qr_asset' => [self::SUPER_ADMIN, 'general_affair'],
        'update_recipient_asset' => [self::SUPER_ADMIN],
        'merge_asset' => [self::SUPER_ADMIN],
        'update_core_asset::transfer' => [self::SUPER_ADMIN],
        'delete_detail_asset::transfer' => [self::SUPER_ADMIN],
        'view_all_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'update_all_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair'],
        'delete_all_asset::service' => [self::SUPER_ADMIN, 'admin'],
        'view_all_ob::checksheet' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'update_all_ob::checksheet' => [self::SUPER_ADMIN, 'admin', 'general_affair'],
        'delete_all_ob::checksheet' => [self::SUPER_ADMIN, 'admin'],
        'manage_access_user' => [self::SUPER_ADMIN, 'admin'],
        'manage_business_entity_access_user' => [self::SUPER_ADMIN],
        'View:Pulse' => [self::SUPER_ADMIN],
    ];

    /**
     * Permission yang sudah ada tetapi selama ini ditutupi cek role (salinan
     * persis daftar role di kode lama).
     *
     * @var array<string, list<string>>
     */
    private const EXISTING_PERMISSIONS = [
        'view_any_asset::qr::label::history' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'view_asset::qr::label::history' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'view_any_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'view_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'create_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair'],
        'update_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair'],
        'delete_asset::service' => [self::SUPER_ADMIN, 'admin'],
        'delete_any_asset::service' => [self::SUPER_ADMIN, 'admin'],
        'export_asset::service' => [self::SUPER_ADMIN, 'admin', 'general_affair', 'audit'],
        'import_user' => [self::SUPER_ADMIN, 'admin'],
        'manage_stock_asset::transfer' => [self::SUPER_ADMIN],
        'View:Horizon' => [self::SUPER_ADMIN],
        'View:LogViewer' => [self::SUPER_ADMIN],
        'Download:LogViewer' => [self::SUPER_ADMIN],
        'Delete:LogViewer' => [self::SUPER_ADMIN],
    ];

    /**
     * Kode lama juga membuka akses lewat permission lain: History QR untuk
     * pemegang view_any_asset, import user untuk pemegang create_user.
     *
     * @var array<string, list<string>>
     */
    private const INHERITED_PERMISSIONS = [
        'view_any_asset::qr::label::history' => ['view_any_asset'],
        'view_asset::qr::label::history' => ['view_any_asset'],
        'import_user' => ['create_user'],
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Dibaca sebelum ada grant supaya hanya mengikuti kondisi lama.
        $inheritingRoles = collect(self::INHERITED_PERMISSIONS)
            ->map(fn (array $sources): array => Role::query()
                ->where('guard_name', 'web')
                ->whereHas('permissions', fn ($query) => $query->whereIn('name', $sources))
                ->get()
                ->all());

        foreach ([...self::NEW_PERMISSIONS, ...self::EXISTING_PERMISSIONS] as $permissionName => $roleNames) {
            $permission = Permission::findOrCreate($permissionName, 'web');

            foreach ($roleNames as $roleName) {
                $this->role($roleName)?->givePermissionTo($permission);
            }

            foreach ($inheritingRoles->get($permissionName, []) as $role) {
                $role->givePermissionTo($permission);
            }
        }

        // Super admin dulu tidak dibatasi badan usaha karena rolenya; kini lewat flag.
        $superAdmin = $this->role(self::SUPER_ADMIN);

        if ($superAdmin !== null) {
            DB::table('users')
                ->whereIn('id', DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('role_id', $superAdmin->getKey())
                    ->select('model_id'))
                ->update(['access_all_business_entities' => true]);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Hanya permission baru yang dihapus. Grant permission lama dan flag badan
     * usaha dibiarkan: kode lama memberi akses yang sama lewat cek role.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->whereIn('name', array_keys(self::NEW_PERMISSIONS))
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function role(string $name): ?Role
    {
        $name = $name === self::SUPER_ADMIN
            ? config('filament-shield.super_admin.name', self::SUPER_ADMIN)
            : $name;

        return Role::query()
            ->where('name', $name)
            ->where('guard_name', 'web')
            ->first();
    }
};
