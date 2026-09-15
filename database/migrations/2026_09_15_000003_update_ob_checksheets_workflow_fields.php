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

            if (! Schema::hasColumn('ob_checksheets', 'started_at')) {
                $table->dateTime('started_at')->nullable()->after('cleaned_at');
            }

            if (! Schema::hasColumn('ob_checksheets', 'finished_at')) {
                $table->dateTime('finished_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('ob_checksheets', 'status')) {
                $table->string('status')->default('in_progress')->after('finished_at')->index();
            }

            if (! Schema::hasColumn('ob_checksheets', 'duration_minutes')) {
                $table->integer('duration_minutes')->nullable()->after('status');
            }
        });

        // Populate started_at for existing records
        DB::statement('UPDATE ob_checksheets SET started_at = cleaned_at, finished_at = cleaned_at, status = "completed" WHERE started_at IS NULL');
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
