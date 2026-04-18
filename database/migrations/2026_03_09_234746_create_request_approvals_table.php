<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('public_asset_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_level_id')->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->unsignedSmallInteger('level');
            $table->string('approver_name');
            $table->string('approver_email');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_approvals');
    }
};
