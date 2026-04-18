<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_levels', function (Blueprint $table) {
            $table->id();
            $table->enum('request_type', ['pengadaan_aset', 'perbaikan_aset', 'penarikan_aset']);
            $table->unsignedSmallInteger('level');
            $table->string('approver_name');
            $table->string('approver_email');
            $table->timestamps();

            $table->unique(['request_type', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_levels');
    }
};
