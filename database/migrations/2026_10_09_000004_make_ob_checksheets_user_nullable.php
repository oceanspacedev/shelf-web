<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tugas atasan bisa masuk kolam bersama tanpa petugas; petugasnya terisi saat OB mengambilnya.
     */
    public function up(): void
    {
        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Tugas kolam yang belum diambil tidak punya pemilik, tidak bisa kembali ke kolom wajib.
        DB::table('ob_checksheets')->whereNull('user_id')->delete();

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
