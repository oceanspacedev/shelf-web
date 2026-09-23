<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->text('after_photo')->nullable()->change();
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('cleaned_at');
            $table->dateTime('finished_at')->nullable()->after('started_at');
            $table->string('status')->default('in_progress')->after('finished_at')->index();
            $table->integer('duration_minutes')->nullable()->after('status');
        });

        DB::table('ob_checksheets')
            ->whereNull('started_at')
            ->update([
                'started_at' => DB::raw('cleaned_at'),
                'finished_at' => DB::raw('cleaned_at'),
                'status' => 'completed',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ob_checksheets', function (Blueprint $table) {
            $columns = array_filter(['started_at', 'finished_at', 'status', 'duration_minutes'], function ($col) {
                return Schema::hasColumn('ob_checksheets', $col);
            });

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }

            $table->text('after_photo')->nullable(false)->change();
        });
    }
};
