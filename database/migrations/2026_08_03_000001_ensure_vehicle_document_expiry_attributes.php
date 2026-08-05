<?php

use App\Models\Category;
use App\Models\CustomAssetAttribute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('custom_asset_attributes') || ! Schema::hasTable('categories')) {
            return;
        }

        $categoryIds = Category::query()
            ->whereIn('name', ['MOBIL', 'MOTOR'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($categoryIds === []) {
            return;
        }

        $stnk = $this->ensureAttribute('STNK', $categoryIds);
        $this->ensureAttribute('KIR', $categoryIds, $stnk);
    }

    public function down(): void
    {
        // Keep attributes; reminders are operational master data.
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function ensureAttribute(string $name, array $categoryIds, ?object $template = null): object
    {
        $existing = DB::table('custom_asset_attributes')->where('name', $name)->first();

        $payload = [
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'required' => false,
            'is_active' => true,
            'category_id' => json_encode($categoryIds),
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => $template->notification_offset ?? 30,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('custom_asset_attributes', 'notification_channels')) {
            $payload['notification_channels'] = $template->notification_channels
                ?? json_encode(['whatsapp', 'email']);
        }

        if (Schema::hasColumn('custom_asset_attributes', 'notification_recipient_user_ids')) {
            $payload['notification_recipient_user_ids'] = $template->notification_recipient_user_ids
                ?? json_encode([]);
        }

        if (Schema::hasColumn('custom_asset_attributes', 'notification_recipient_emails')) {
            $payload['notification_recipient_emails'] = $template->notification_recipient_emails
                ?? json_encode([]);
        }

        if (Schema::hasColumn('custom_asset_attributes', 'notification_recipient_whatsapp_numbers')) {
            $payload['notification_recipient_whatsapp_numbers'] = $template->notification_recipient_whatsapp_numbers
                ?? json_encode([]);
        }

        if ($existing === null) {
            $payload['name'] = $name;
            $payload['created_at'] = now();
            $id = DB::table('custom_asset_attributes')->insertGetId($payload);

            return DB::table('custom_asset_attributes')->where('id', $id)->first();
        }

        // Merge categories; keep existing recipients/channels if already configured.
        $currentCats = json_decode((string) $existing->category_id, true) ?: [];
        $mergedCats = array_values(array_unique(array_map('intval', array_merge($currentCats, $categoryIds))));
        $update = [
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => json_encode($mergedCats),
            'is_notifiable' => true,
            'notification_type' => $existing->notification_type ?: 'relative_date',
            'notification_offset' => $existing->notification_offset ?: 30,
            'updated_at' => now(),
        ];

        DB::table('custom_asset_attributes')->where('id', $existing->id)->update($update);

        return DB::table('custom_asset_attributes')->where('id', $existing->id)->first();
    }
};
