<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->foreignId('asset_location_id')
                ->nullable()
                ->after('division_id')
                ->constrained('asset_locations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->dropForeign(['asset_location_id']);
            $table->dropColumn('asset_location_id');
        });
    }
};
