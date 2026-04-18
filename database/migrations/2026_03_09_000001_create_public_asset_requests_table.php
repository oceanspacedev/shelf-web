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
        Schema::create('public_asset_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('request_type', ['pengadaan_aset', 'perbaikan_aset', 'penarikan_aset']);
            $table->string('requester_name');
            $table->string('email');
            $table->string('division');
            $table->string('placement');
            $table->string('item_name');
            $table->integer('qty');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('public_asset_requests');
    }
};
