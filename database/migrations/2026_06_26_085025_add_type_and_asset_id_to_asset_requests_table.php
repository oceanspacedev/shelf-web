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
            $table->string('type')->default('pengadaan')->after('reference_number');
            $table->foreignId('asset_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('attachment')->nullable()->after('description');
            $table->string('item_name')->nullable()->change();
            $table->integer('qty')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('asset_id');
            $table->dropColumn(['type', 'attachment']);
            $table->string('item_name')->nullable(false)->change();
            $table->integer('qty')->nullable(false)->change();
        });
    }
};
