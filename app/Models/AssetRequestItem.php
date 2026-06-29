<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_request_id',
        'asset_id',
        'item_name',
        'qty',
        'notes',
        'fulfilled_asset_id',
        'fulfilled_at',
    ];

    protected $casts = [
        'qty' => 'integer',
        'fulfilled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AssetRequestItem $item): void {
            if ($item->asset_id && ! $item->item_name) {
                $item->item_name = $item->asset?->name ?? 'Aset Terpilih';
            }

            if (! $item->qty || $item->qty < 1) {
                $item->qty = 1;
            }
        });
    }

    public function assetRequest(): BelongsTo
    {
        return $this->belongsTo(AssetRequest::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fulfilledAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'fulfilled_asset_id');
    }
}
