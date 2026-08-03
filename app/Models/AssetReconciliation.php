<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AssetReconciliation extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPARED = 'compared';

    public const STATUS_ALIGNED = 'aligned';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid',
        'parent_id',
        'source_system',
        'source_sheet',
        'business_entity_id',
        'business_entity_mappings',
        'original_filename',
        'stored_path',
        'file_sha256',
        'status',
        'auto_create_locations',
        'imported_by',
        'total_rows',
        'inline_rows',
        'gap_rows',
        'blocked_rows',
        'created_rows',
        'updated_rows',
        'retired_rows',
        'summary',
        'failure_message',
        'compared_at',
        'applied_at',
    ];

    protected $casts = [
        'auto_create_locations' => 'boolean',
        'business_entity_mappings' => 'array',
        'summary' => 'array',
        'compared_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reconciliation): void {
            $reconciliation->uuid ??= (string) Str::uuid();
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetReconciliationItem::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function hasBlockingRows(): bool
    {
        return $this->blocked_rows > 0;
    }
}
