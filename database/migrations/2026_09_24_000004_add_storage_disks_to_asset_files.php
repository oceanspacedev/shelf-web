<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_qr_label_histories', function (Blueprint $table): void {
            $table->string('file_disk')->default('local');
        });
        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            $table->string('stored_disk')->default('local');
        });
    }

    public function down(): void
    {
        Schema::table('asset_qr_label_histories', function (Blueprint $table): void {
            $table->dropColumn('file_disk');
        });
        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            $table->dropColumn('stored_disk');
        });
    }
};
