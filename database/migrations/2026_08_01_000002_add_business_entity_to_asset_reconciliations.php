<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            // Nullable only for batches created before business-entity enforcement.
            // New batches are required to provide this value at the form and service layers.
            $table->foreignId('business_entity_id')
                ->nullable()
                ->after('source_sheet')
                ->constrained('business_entities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_entity_id');
        });
    }
};
