<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot for the business entities a user may see and manage, in addition to
 * the entity they belong to (users.business_entity_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_entity_user')) {
            return;
        }

        Schema::create('business_entity_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_entity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_user');
    }
};
