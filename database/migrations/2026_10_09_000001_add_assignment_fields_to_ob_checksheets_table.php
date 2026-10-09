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
        // Tugas dari atasan belum punya foto sebelum sampai OB memulainya.
        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->text('before_photo')->nullable()->change();
        });

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->string('source', 20)->default('initiative')->after('status')->index();
            $table->foreignId('assigned_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->date('scheduled_date')->nullable()->after('cleaned_at')->index();
            $table->string('shift_label')->nullable()->after('scheduled_date');
        });

        // Data lama dianggap inisiatif OB, terjadwal pada hari ia dimulai.
        DB::table('ob_checksheets')
            ->whereNull('scheduled_date')
            ->update(['scheduled_date' => DB::raw('DATE(COALESCE(started_at, cleaned_at, created_at))')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tugas yang belum dimulai tidak punya foto sebelum, tidak bisa dikembalikan ke kolom wajib.
        DB::table('ob_checksheets')->where('status', 'pending')->delete();

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn(['source', 'scheduled_date', 'shift_label']);
        });

        DB::table('ob_checksheets')->whereNull('before_photo')->update(['before_photo' => '']);

        Schema::table('ob_checksheets', function (Blueprint $table) {
            $table->text('before_photo')->nullable(false)->change();
        });
    }
};
