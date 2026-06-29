<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_requests', function (Blueprint $table): void {
            $table->string('public_token', 64)
                ->nullable()
                ->unique()
                ->after('reference_number');
        });

        DB::table('asset_requests')
            ->whereNull('public_token')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('asset_requests')->where('public_token', $token)->exists());

                    DB::table('asset_requests')
                        ->where('id', $request->id)
                        ->update(['public_token' => $token]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('asset_requests', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
