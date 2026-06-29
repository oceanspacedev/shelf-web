<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hardening skema lifecycle pengajuan aset:
     *  - attachment (asset_requests) string -> text agar multi-file JSON tidak overflow.
     *  - FK user_id pada asset_requests / asset_request_approvals / division_approvers
     *    cascade -> restrict, supaya penghapusan user tidak menghapus audit trail /
     *    merusak rantai approval yang sedang pending (request stuck).
     *  - unique (division_id, user_id) pada division_approvers (approver tidak ganda
     *    per divisi) dan (asset_request_id, level) pada asset_request_approvals,
     *    supaya level tidak duplikat (approveCurrentLevel ->first() tidak ambiguous).
     *    Catatan: unique (division_id, level) sengaja TIDAK ditambahkan karena Filament
     *    Repeater menyimpan orderColumn baris-demi-baris; unique level membuat reorder
     *    approver gagal (transien duplikat level saat swap).
     *  - kolom link asset_request_id pada assets (nullable) untuk menelusuri aset
     *    yang dibuat dari sebuah pengajuan (operator-initiated, bukan auto).
     *  - kolom audit decided_by_user_id & decided_at pada asset_request_approvals
     *    untuk membuktikan siapa & kapan keputusan approval diambil.
     */
    public function up(): void
    {
        // --- asset_requests: attachment -> text, FK user_id restrict ---
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->text('attachment')->nullable()->change();
        });

        // --- division_approvers: FK user_id restrict + unique user per divisi ---
        Schema::table('division_approvers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['division_id', 'user_id'], 'div_approvers_div_user_unique');
        });

        // --- asset_request_approvals: FK user_id restrict + unique level + audit kolom ---
        Schema::table('asset_request_approvals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->unique(['asset_request_id', 'level'], 'req_approvals_req_level_unique');
            $table->foreignId('decided_by_user_id')
                ->nullable()
                ->after('user_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('decided_at')->nullable()->after('status');
        });

        // --- assets: link balik ke pengajuan (nullable) ---
        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('asset_request_id')
                ->nullable()
                ->after('recipient_business_entity_id')
                ->constrained('asset_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_request_id');
        });

        Schema::table('asset_request_approvals', function (Blueprint $table) {
            $table->dropColumn(['decided_at', 'decided_by_user_id']);
            $table->dropUnique('req_approvals_req_level_unique');
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('division_approvers', function (Blueprint $table) {
            $table->dropUnique('div_approvers_div_user_unique');
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('asset_requests', function (Blueprint $table) {
            $table->string('attachment')->nullable()->change();
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
