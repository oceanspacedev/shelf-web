<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_levels', function (Blueprint $table) {
            $table->string('division')
                ->default('*')
                ->after('request_type');
        });

        Schema::table('approval_levels', function (Blueprint $table) {
            $table->dropUnique('approval_levels_request_type_level_unique');
            $table->unique(['request_type', 'division', 'level']);
        });
    }

    public function down(): void
    {
        Schema::table('approval_levels', function (Blueprint $table) {
            $table->dropUnique(['request_type', 'division', 'level']);
        });

        Schema::table('approval_levels', function (Blueprint $table) {
            $table->dropColumn('division');
        });

        Schema::table('approval_levels', function (Blueprint $table) {
            $table->unique(['request_type', 'level']);
        });
    }
};
