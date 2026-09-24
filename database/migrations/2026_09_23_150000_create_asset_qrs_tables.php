<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_qrs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique('asset_id');
        });

        Schema::create('asset_qr_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('asset_qr_id')->constrained('asset_qrs')->cascadeOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_qr_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_qr_scans');
        Schema::dropIfExists('asset_qrs');
    }
};
