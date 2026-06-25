<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomAssetAttribute extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';

    public const TYPE_NUMBER = 'number';

    public const TYPE_TEXTAREA = 'textarea';

    public const TYPE_DATE = 'date';

    public const TYPE_DOCUMENT_EXPIRY = 'document_expiry';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    protected $fillable = [
        'name',
        'type',
        'required',
        'is_active',
        'category_id',
        'is_notifiable',
        'notification_type',
        'notification_offset',
        'fixed_notification_date',
        'notification_channels',
        'notification_recipient_user_ids',
        'notification_recipient_emails',
        'notification_recipient_whatsapp_numbers',
    ];

    protected $casts = [
        'category_id' => 'array',
        'required' => 'boolean',
        'is_active' => 'boolean',
        'is_notifiable' => 'boolean',
        'notification_offset' => 'integer',
        'fixed_notification_date' => 'date',
        'notification_channels' => 'array',
        'notification_recipient_user_ids' => 'array',
        'notification_recipient_emails' => 'array',
        'notification_recipient_whatsapp_numbers' => 'array',
    ];

    public static function typeOptions(): array
    {
        return [
            self::TYPE_TEXT => 'Text Input',
            self::TYPE_NUMBER => 'Number Input',
            self::TYPE_TEXTAREA => 'Textarea',
            self::TYPE_DATE => 'Date Picker',
            self::TYPE_DOCUMENT_EXPIRY => 'Dokumen / Masa Berlaku',
        ];
    }

    public static function notificationChannelOptions(): array
    {
        return [
            self::CHANNEL_WHATSAPP => 'WhatsApp',
            self::CHANNEL_EMAIL => 'Email',
        ];
    }

    public function notificationChannels(): array
    {
        $channels = collect($this->arrayAttribute('notification_channels'))
            ->map(fn ($channel) => is_string($channel) ? trim($channel) : $channel)
            ->filter(fn ($channel) => in_array($channel, array_keys(self::notificationChannelOptions()), true))
            ->unique()
            ->values()
            ->all();

        return $channels === [] ? [self::CHANNEL_WHATSAPP] : $channels;
    }

    public function usesNotificationChannel(string $channel): bool
    {
        return in_array($channel, $this->notificationChannels(), true);
    }

    public function notificationRecipientUserIds(): array
    {
        return collect($this->arrayAttribute('notification_recipient_user_ids'))
            ->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT))
            ->filter(fn ($id) => $id !== false && $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function notificationRecipientEmails(): array
    {
        return collect($this->arrayAttribute('notification_recipient_emails'))
            ->map(fn ($email) => is_string($email) ? trim($email) : null)
            ->filter(fn ($email) => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public function notificationRecipientWhatsappNumbers(): array
    {
        return collect($this->arrayAttribute('notification_recipient_whatsapp_numbers'))
            ->map(function ($number) {
                if (! is_string($number) && ! is_numeric($number)) {
                    return null;
                }

                return preg_replace('/[^\d+]/', '', (string) $number);
            })
            ->filter(fn ($number) => filled($number))
            ->unique()
            ->values()
            ->all();
    }

    protected function arrayAttribute(string $key): array
    {
        $value = $this->getAttribute($key);

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        return preg_split('/[\r\n,]+/', $value, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public function setCategoryIdAttribute($value)
    {
        $this->attributes['category_id'] = json_encode(array_map('intval', $value));
    }

    // Relasi ke kategori
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relasi ke asset_attributes
    public function assetAttributes()
    {
        return $this->hasMany(AssetAttribute::class, 'custom_attribute_id');
    }
}
