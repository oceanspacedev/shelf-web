<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            $table->json('business_entity_mappings')->nullable()->after('business_entity_id');
        });

        Schema::table('asset_reconciliation_items', function (Blueprint $table): void {
            $table->string('external_business_entity_code', 50)
                ->nullable()
                ->after('source_rows')
                ->index();
            $table->foreignId('business_entity_id')
                ->nullable()
                ->after('asset_catalog_item_id')
                ->constrained('business_entities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asset_reconciliation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_entity_id');
            $table->dropIndex(['external_business_entity_code']);
            $table->dropColumn('external_business_entity_code');
        });

        Schema::table('asset_reconciliations', function (Blueprint $table): void {
            $table->dropColumn('business_entity_mappings');
        });
    }
};
