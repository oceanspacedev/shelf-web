<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis BA menjadi kolom eksplisit. Baris lama diisi dengan aturan lama yang
 * menebak jenis dari peran pihak, di mana satu akun GA bersama (id 2 /
 * username adminga / bernama "GA") berperan sebagai stok.
 */
return new class extends Migration
{
    private const GENERAL_AFFAIR_ROLE = 'general_affair';

    public function up(): void
    {
        if (! Schema::hasColumn('asset_transfers', 'document_type')) {
            Schema::table('asset_transfers', function (Blueprint $table): void {
                $table->string('document_type', 32)->nullable()->after('business_entity_id')->index();
            });
        }

        $this->backfillLegacyDocumentTypes();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asset_transfers', 'document_type')) {
            return;
        }

        Schema::table('asset_transfers', function (Blueprint $table): void {
            $table->dropIndex(['document_type']);
            $table->dropColumn('document_type');
        });
    }

    private function backfillLegacyDocumentTypes(): void
    {
        $generalAffairIds = $this->generalAffairUserIds();
        $mainGeneralAffairIds = $this->mainGeneralAffairUserIds();

        DB::table('asset_transfers')
            ->whereNull('document_type')
            ->orderBy('id')
            ->chunkById(500, function (Collection $transfers) use ($generalAffairIds, $mainGeneralAffairIds): void {
                foreach ($transfers as $transfer) {
                    DB::table('asset_transfers')
                        ->where('id', $transfer->id)
                        ->update([
                            'document_type' => $this->legacyDocumentType(
                                (int) $transfer->from_user_id,
                                (int) $transfer->to_user_id,
                                $generalAffairIds,
                                $mainGeneralAffairIds,
                            ),
                        ]);
                }
            });
    }

    /**
     * @param  list<int>  $generalAffairIds
     * @param  list<int>  $mainGeneralAffairIds
     */
    private function legacyDocumentType(int $fromUserId, int $toUserId, array $generalAffairIds, array $mainGeneralAffairIds): string
    {
        $fromIsGa = in_array($fromUserId, $generalAffairIds, true);
        $toIsGa = in_array($toUserId, $generalAffairIds, true);

        if ($fromIsGa && $toIsGa) {
            if (in_array($toUserId, $mainGeneralAffairIds, true)) {
                return 'pengembalian_barang';
            }

            if (in_array($fromUserId, $mainGeneralAffairIds, true)) {
                return 'serah_terima';
            }

            return 'pengalihan_barang';
        }

        if ($fromIsGa) {
            return 'serah_terima';
        }

        if ($toIsGa) {
            return 'pengembalian_barang';
        }

        return 'pengalihan_barang';
    }

    /**
     * @return list<int>
     */
    private function generalAffairUserIds(): array
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', self::GENERAL_AFFAIR_ROLE)
            ->where('model_has_roles.model_type', User::class)
            ->pluck('model_has_roles.model_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function mainGeneralAffairUserIds(): array
    {
        $hasUsername = Schema::hasColumn('users', 'username');

        return DB::table('users')
            ->where(function ($query) use ($hasUsername): void {
                $query->where('name', 'GA');

                if ($hasUsername) {
                    $query->orWhere(fn ($main) => $main->where('id', 2)->where('username', 'adminga'));
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
};
