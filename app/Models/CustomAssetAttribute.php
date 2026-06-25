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
    ];

    protected $casts = [
        'category_id' => 'array',
        'required' => 'boolean',
        'is_active' => 'boolean',
        'is_notifiable' => 'boolean',
        'notification_offset' => 'integer',
        'fixed_notification_date' => 'date',
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
