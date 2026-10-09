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
        Schema::create('ob_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shift_label')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ob_task_template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ob_task_template_id')->constrained('ob_task_templates')->cascadeOnDelete();
            $table->string('room');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ob_task_template_items');
        Schema::dropIfExists('ob_task_templates');
    }
};
