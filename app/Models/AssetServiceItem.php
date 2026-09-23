<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetServiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_service_id',
        'item_name',
        'quantity',
        'unit_price',
        'subtotal',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'subtotal' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (AssetServiceItem $item) {
            $qty = $item->quantity ?: 1;
            $price = $item->unit_price ?: 0;
            $item->subtotal = $qty * $price;
        });
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(AssetService::class, 'asset_service_id');
    }
}
