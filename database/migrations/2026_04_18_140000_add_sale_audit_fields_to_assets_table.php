<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->date('sold_at')->nullable()->after('nbh_notes');
            $table->string('sold_to')->nullable()->after('sold_at');
            $table->bigInteger('sold_price')->nullable()->after('sold_to');
            $table->string('sale_document_path')->nullable()->after('sold_price');
            $table->text('sale_notes')->nullable()->after('sale_document_path');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'sold_at',
                'sold_to',
                'sold_price',
                'sale_document_path',
                'sale_notes',
            ]);
        });
    }
};
