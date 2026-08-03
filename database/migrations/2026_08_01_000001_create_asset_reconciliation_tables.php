<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_catalog_items', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system', 50)->default('CSA');
            $table->string('external_code')->nullable();
            $table->string('name');
            $table->string('normalized_name');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'external_code']);
            $table->index(['source_system', 'normalized_name']);
        });

        Schema::table('asset_locations', function (Blueprint $table): void {
            $table->string('external_code')->nullable()->unique()->after('name');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('asset_catalog_item_id')
                ->nullable()
                ->after('name')
                ->constrained('asset_catalog_items')
                ->nullOnDelete();
            $table->boolean('inventory_active')->default(true)->after('qty');
            $table->string('reconciliation_source', 50)->nullable()->after('inventory_active');
            $table->timestamp('last_reconciled_at')->nullable()->after('reconciliation_source');
        });

        Schema::create('asset_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('asset_reconciliations')->nullOnDelete();
            $table->string('source_system', 50)->default('CSA');
            $table->string('source_sheet')->default('ASET');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('file_sha256', 64)->index();
            $table->string('status', 30)->default('processing')->index();
            $table->boolean('auto_create_locations')->default(true);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('inline_rows')->default(0);
            $table->unsignedInteger('gap_rows')->default(0);
            $table->unsignedInteger('blocked_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('retired_rows')->default(0);
            $table->json('summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('compared_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreignId('last_reconciliation_id')
                ->nullable()
                ->after('last_reconciled_at')
                ->constrained('asset_reconciliations')
                ->nullOnDelete();
        });

        Schema::create('asset_reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_reconciliation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->json('source_rows');
            $table->string('external_location_code')->nullable()->index();
            $table->string('external_item_code')->nullable()->index();
            $table->string('item_name');
            $table->string('serial_number')->nullable()->index();
            $table->integer('system_qty')->nullable();
            $table->integer('physical_qty')->nullable();
            $table->integer('correction_qty')->nullable();
            $table->integer('target_qty');
            $table->integer('shelf_qty')->nullable();
            $table->integer('gap_qty')->nullable();
            $table->foreignId('asset_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('matched_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->json('candidate_asset_ids')->nullable();
            $table->string('match_strategy', 40)->nullable();
            $table->string('comparison_status', 20)->index();
            $table->string('action', 30)->nullable();
            $table->text('message')->nullable();
            $table->text('notes')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['asset_reconciliation_id', 'source_row'],
                'asset_recon_items_batch_row_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_reconciliation_items');

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('last_reconciliation_id');
        });

        Schema::dropIfExists('asset_reconciliations');

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('asset_catalog_item_id');
            $table->dropColumn([
                'inventory_active',
                'reconciliation_source',
                'last_reconciled_at',
            ]);
        });

        Schema::table('asset_locations', function (Blueprint $table): void {
            $table->dropUnique(['external_code']);
            $table->dropColumn('external_code');
        });

        Schema::dropIfExists('asset_catalog_items');
    }
};
