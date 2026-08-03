<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCatalogItem extends Model
{
    protected $fillable = [
        'source_system',
        'external_code',
        'name',
        'normalized_name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
