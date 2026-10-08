<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stok kini berarti aset tanpa pemegang. Tiga kelompok aset lama dilepas dari
 * akun GA yang dulu berperan sebagai stok; entitas penerima dibiarkan sebagai
 * penanda stok badan usaha mana:
 *
 *  1. aset Tersedia yang masih tercatat punya pemegang;
 *  2. aset yang BA terakhirnya adalah Pengembalian dan masih tercatat dipegang
 *     penerima BA itu (mis. aset yang dikembalikan lalu ditandai Rusak);
 *  3. aset insiden (Rusak/Hilang) yang masih dipegang staf GA. Di alur lama
 *     aset yang dipegang GA berarti stok, jadi setelah diperbaiki aset ini
 *     harus kembali Tersedia, bukan menjadi Digunakan oleh staf GA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assets') || ! Schema::hasColumn('assets', 'recipient_id')) {
            return;
        }

        DB::table('assets')
            ->where('condition_status', 'available')
            ->whereNotNull('recipient_id')
            ->update(['recipient_id' => null]);

        $this->releaseAssetsReturnedToStock();
        $this->releaseIncidentAssetsHeldByGeneralAffair();
    }

    public function down(): void
    {
        // Pemegang lama tidak disimpan; perubahan ini tidak dapat dibalik.
    }

    private function releaseAssetsReturnedToStock(): void
    {
        if (! Schema::hasTable('asset_transfer_details')
            || ! Schema::hasTable('asset_transfers')
            || ! Schema::hasColumn('asset_transfers', 'document_type')) {
            return;
        }

        $latestDetailPerAsset = DB::table('asset_transfer_details')
            ->selectRaw('max(id) as id')
            ->groupBy('asset_id');

        $assetIds = DB::table('asset_transfer_details as details')
            ->joinSub($latestDetailPerAsset, 'latest', fn (JoinClause $join) => $join->on('latest.id', '=', 'details.id'))
            ->join('asset_transfers as transfers', 'transfers.id', '=', 'details.asset_transfer_id')
            ->join('assets', 'assets.id', '=', 'details.asset_id')
            ->where('transfers.document_type', 'pengembalian_barang')
            ->whereNotNull('assets.recipient_id')
            ->whereColumn('assets.recipient_id', 'transfers.to_user_id')
            ->pluck('assets.id');

        if ($assetIds->isEmpty()) {
            return;
        }

        DB::table('assets')
            ->whereIn('id', $assetIds)
            ->update(['recipient_id' => null]);
    }

    private function releaseIncidentAssetsHeldByGeneralAffair(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $generalAffairIds = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'general_affair')
            ->where('model_has_roles.model_type', User::class)
            ->select('model_has_roles.model_id');

        DB::table('assets')
            ->whereIn('condition_status', ['lost', 'damaged'])
            ->whereIn('recipient_id', $generalAffairIds)
            ->update(['recipient_id' => null]);
    }
};
