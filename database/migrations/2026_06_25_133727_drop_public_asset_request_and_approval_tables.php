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
        Schema::dropIfExists('request_approvals');
        Schema::dropIfExists('approval_levels');
        Schema::dropIfExists('public_asset_requests');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
