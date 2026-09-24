<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_qr_label_histories', function (Blueprint $table) {
            $table->json('asset_ids')->nullable()->after('id');
            $table->unsignedInteger('asset_count')->default(0)->after('asset_ids');
            $table->text('asset_summary')->nullable()->after('asset_count');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('
                UPDATE asset_qr_label_histories h
                LEFT JOIN assets a ON a.id = h.asset_id
                SET
                    h.asset_ids = JSON_ARRAY(h.asset_id),
                    h.asset_count = 1,
                    h.asset_summary = a.name
                WHERE h.asset_id IS NOT NULL
            ');
        } else {
            DB::table('asset_qr_label_histories')
                ->orderBy('id')
                ->each(function ($row): void {
                    $name = DB::table('assets')->where('id', $row->asset_id)->value('name');

                    DB::table('asset_qr_label_histories')
                        ->where('id', $row->id)
                        ->update([
                            'asset_ids' => json_encode([(int) $row->asset_id]),
                            'asset_count' => 1,
                            'asset_summary' => $name,
                        ]);
                });
        }

        Schema::table('asset_qr_label_histories', function (Blueprint $table) {
            $table->dropForeign(['asset_id']);
            $table->dropIndex(['asset_id', 'created_at']);
            $table->dropColumn('asset_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_qr_label_histories', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['asset_id', 'created_at']);
        });

        DB::table('asset_qr_label_histories')
            ->orderBy('id')
            ->each(function ($row): void {
                $ids = json_decode((string) $row->asset_ids, true);
                $firstId = is_array($ids) && $ids !== [] ? (int) $ids[0] : null;
                if ($firstId !== null && ! DB::table('assets')->where('id', $firstId)->exists()) {
                    $firstId = null;
                }

                DB::table('asset_qr_label_histories')
                    ->where('id', $row->id)
                    ->update(['asset_id' => $firstId]);
            });

        Schema::table('asset_qr_label_histories', function (Blueprint $table) {
            $table->dropColumn(['asset_ids', 'asset_count', 'asset_summary']);
        });
    }
};
