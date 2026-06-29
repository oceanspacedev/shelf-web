<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_request_id')->constrained('asset_requests')->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->string('item_name')->nullable();
            $table->integer('qty')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('fulfilled_asset_id')->nullable()->constrained('assets')->nullOnDelete();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });

        DB::table('asset_requests')
            ->where(function ($query) {
                $query->whereNotNull('asset_id')
                    ->orWhereNotNull('item_name');
            })
            ->orderBy('id')
            ->get()
            ->each(function ($request): void {
                DB::table('asset_request_items')->insert([
                    'asset_request_id' => $request->id,
                    'asset_id' => $request->asset_id,
                    'item_name' => $request->item_name,
                    'qty' => $request->qty ?: 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_request_items');
    }
};
