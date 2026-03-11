<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_original_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path',
                'attachment_original_name',
            ]);
        });
    }
};
