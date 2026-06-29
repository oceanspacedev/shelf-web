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
        Schema::table('asset_request_approvals', function (Blueprint $table): void {
            $table->string('public_token', 64)
                ->nullable()
                ->unique()
                ->after('user_id');
        });

        DB::table('asset_request_approvals')
            ->whereNull('public_token')
            ->orderBy('id')
            ->select('id')
            ->chunkById(100, function ($approvals): void {
                foreach ($approvals as $approval) {
                    do {
                        $token = Str::random(48);
                    } while (DB::table('asset_request_approvals')->where('public_token', $token)->exists());

                    DB::table('asset_request_approvals')
                        ->where('id', $approval->id)
                        ->update(['public_token' => $token]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('asset_request_approvals', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
