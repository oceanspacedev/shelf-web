<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class BusinessEntity extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'format', 'color', 'letterhead'];

    /**
     * Badan usaha yang boleh dipilih $viewer di form: semuanya untuk akses tanpa
     * batas, selain itu hanya yang bisa diaksesnya. $include (nilai record yang
     * sedang diedit) selalu disertakan supaya pilihannya tidak hilang.
     *
     * @return array<int, string>
     */
    public static function optionsFor(?Authenticatable $viewer, mixed $include = null): array
    {
        if (! $viewer instanceof User || $viewer->hasUnrestrictedBusinessEntityAccess()) {
            // Kunci cache dipakai bersama (dan di-forget) di tempat lain, kadang
            // berisi Collection, kadang array.
            return collect(Cache::remember(
                'business_entity_options',
                300,
                fn () => static::query()->orderBy('name')->pluck('name', 'id')->all(),
            ))->all();
        }

        $ids = $viewer->accessibleBusinessEntityIds();

        if (filled($include)) {
            $ids[] = (int) $include;
        }

        return static::query()
            ->whereKey($ids)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    // Relasi ke tabel asset_transfers
    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    /**
     * Users that belong to this business entity (users.business_entity_id).
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Users granted access to this business entity on top of their own.
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
