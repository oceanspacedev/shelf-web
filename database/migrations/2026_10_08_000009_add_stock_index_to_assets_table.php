<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok = condition_status 'available' dan recipient_id NULL; index ini
 * melayani pencarian aset stok di form BA dan hitungan stok.
 */
return new class extends Migration
{
    private const INDEX = 'assets_condition_status_recipient_id_index';

    public function up(): void
    {
        if (Schema::hasIndex('assets', self::INDEX)) {
            return;
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->index(['condition_status', 'recipient_id'], self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('assets', self::INDEX)) {
            return;
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
