<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pembatasan akses per badan usaha mulai diberlakukan. Supaya tidak ada user
 * panel yang tiba-tiba kehilangan data yang selama ini bisa mereka lihat,
 * setiap user yang punya role (kecuali super_admin, yang memang tanpa
 * batasan) ditandai "akses semua badan usaha", termasuk badan usaha yang
 * dibuat nanti. Super admin mempersempitnya lewat menu Users.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'access_all_business_entities')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('access_all_business_entities')->default(false)->after('business_entity_id');
            });
        }

        if (! Schema::hasTable('model_has_roles') || ! Schema::hasTable('roles')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')
            ->where('name', config('filament-shield.super_admin.name', 'super_admin'))
            ->where('guard_name', 'web')
            ->value('id');

        $panelUserIds = DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->when($superAdminRoleId !== null, fn ($query) => $query->where('role_id', '!=', $superAdminRoleId))
            ->select('model_id');

        DB::table('users')
            ->whereIn('id', $panelUserIds)
            ->update(['access_all_business_entities' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'access_all_business_entities')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('access_all_business_entities');
        });
    }
};
