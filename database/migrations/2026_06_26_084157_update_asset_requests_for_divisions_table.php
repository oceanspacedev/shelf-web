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
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->foreignId('division_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->integer('current_level')->default(1)->after('qty');
            $table->dropColumn('division');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table) {
            $table->string('division')->after('user_id');
            $table->dropConstrainedForeignId('division_id');
            $table->dropColumn('current_level');
        });
    }
};
