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
        Schema::table('custom_asset_attributes', function (Blueprint $table) {
            $table->json('notification_channels')->nullable()->after('fixed_notification_date');
            $table->json('notification_recipient_user_ids')->nullable()->after('notification_channels');
            $table->json('notification_recipient_emails')->nullable()->after('notification_recipient_user_ids');
            $table->json('notification_recipient_whatsapp_numbers')->nullable()->after('notification_recipient_emails');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_asset_attributes', function (Blueprint $table) {
            $table->dropColumn([
                'notification_channels',
                'notification_recipient_user_ids',
                'notification_recipient_emails',
                'notification_recipient_whatsapp_numbers',
            ]);
        });
    }
};
