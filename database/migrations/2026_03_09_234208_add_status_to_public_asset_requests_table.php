<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('qty');
            $table->text('admin_notes')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->dropColumn(['status', 'admin_notes']);
        });
    }
};
