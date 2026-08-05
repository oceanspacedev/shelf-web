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

        $stnk = DB::table('custom_asset_attributes')->where('name', 'STNK')->first();

        $this->ensureDocumentAttribute('Pajak', $categoryIds, $stnk);
        $this->ensureDocumentAttribute('Asuransi', $categoryIds, $stnk);
        $this->upgradeBayarPajak($categoryIds, $stnk);
        $this->ensureTextAttribute('BPKB', $categoryIds);
        $this->ensureTextAttribute('Pemegang Inventaris', $categoryIds);
    }

    public function down(): void
    {
        // Keep operational attributes.
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function ensureDocumentAttribute(string $name, array $categoryIds, ?object $template = null): void
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

        $this->copyNotificationColumns($payload, $template);

        if ($existing === null) {
            $payload['name'] = $name;
            $payload['created_at'] = now();
            DB::table('custom_asset_attributes')->insert($payload);

            return;
        }

        $currentCats = json_decode((string) $existing->category_id, true) ?: [];
        $mergedCats = array_values(array_unique(array_map('intval', array_merge($currentCats, $categoryIds))));

        DB::table('custom_asset_attributes')->where('id', $existing->id)->update([
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => json_encode($mergedCats),
            'is_notifiable' => true,
            'notification_type' => $existing->notification_type ?: 'relative_date',
            'notification_offset' => $existing->notification_offset ?: 30,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function upgradeBayarPajak(array $categoryIds, ?object $template = null): void
    {
        $existing = DB::table('custom_asset_attributes')->where('name', 'Bayar Pajak')->first();

        if ($existing === null) {
            return;
        }

        $currentCats = json_decode((string) $existing->category_id, true) ?: [];
        $mergedCats = array_values(array_unique(array_map('intval', array_merge($currentCats, $categoryIds))));

        $update = [
            'type' => CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY,
            'is_active' => true,
            'category_id' => json_encode($mergedCats),
            'is_notifiable' => true,
            'notification_type' => 'relative_date',
            'notification_offset' => $existing->notification_offset ?: ($template->notification_offset ?? 30),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('custom_asset_attributes', 'notification_channels')
            && blank($existing->notification_channels)
            && $template?->notification_channels) {
            $update['notification_channels'] = $template->notification_channels;
        }

        if (Schema::hasColumn('custom_asset_attributes', 'notification_recipient_user_ids')
            && blank($existing->notification_recipient_user_ids)
            && $template?->notification_recipient_user_ids) {
            $update['notification_recipient_user_ids'] = $template->notification_recipient_user_ids;
        }

        DB::table('custom_asset_attributes')->where('id', $existing->id)->update($update);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function ensureTextAttribute(string $name, array $categoryIds): void
    {
        $existing = DB::table('custom_asset_attributes')->where('name', $name)->first();

        if ($existing === null) {
            DB::table('custom_asset_attributes')->insert([
                'name' => $name,
                'type' => CustomAssetAttribute::TYPE_TEXT,
                'required' => false,
                'is_active' => true,
                'category_id' => json_encode($categoryIds),
                'is_notifiable' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $currentCats = json_decode((string) $existing->category_id, true) ?: [];
        $mergedCats = array_values(array_unique(array_map('intval', array_merge($currentCats, $categoryIds))));

        DB::table('custom_asset_attributes')->where('id', $existing->id)->update([
            'is_active' => true,
            'category_id' => json_encode($mergedCats),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function copyNotificationColumns(array &$payload, ?object $template): void
    {
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
    }
};
