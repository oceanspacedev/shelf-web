<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Do not backfill public, unverified notification contacts as credentials.
            $table->string('whatsapp_login_number', 15)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['whatsapp_login_number']);
            $table->dropColumn('whatsapp_login_number');
        });
    }
};
