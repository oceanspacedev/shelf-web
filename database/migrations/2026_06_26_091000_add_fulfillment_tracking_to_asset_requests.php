<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracking tindak lanjut operator pada AssetRequest yang sudah Approved.
     *
     * Sesuai filosofi lifecycle: approval hanyalah persetujuan untuk tindak
     * lanjut; operator yang menjalankan tindak lanjut (buat aset / BA pengembalian /
     * tandai perbaikan) secara manual. Kolom ini menandai kapan & siapa yang
     * menyelesaikan tindak lanjut, serta link ke downstream record bila ada.
     */
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->timestamp('fulfilled_at')->nullable()->after('notes');
            $table->foreignId('fulfilled_by_user_id')
                ->nullable()
                ->after('fulfilled_at')
                ->constrained('users')
                ->nullOnDelete();
            // Link ke AssetTransfer yang dibuat dari tindak lanjut (mis. penarikan).
            $table->foreignId('asset_transfer_id')
                ->nullable()
                ->after('fulfilled_by_user_id')
                ->constrained('asset_transfers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_transfer_id');
            $table->dropConstrainedForeignId('fulfilled_by_user_id');
            $table->dropColumn('fulfilled_at');
        });
    }
};
