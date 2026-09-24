<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetQrScan extends Model
{
    protected $fillable = [
        'asset_qr_id',
        'latitude',
        'longitude',
        'user_agent',
        'user_id',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function qr(): BelongsTo
    {
        return $this->belongsTo(AssetQr::class, 'asset_qr_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
