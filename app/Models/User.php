<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\Concerns\HasBusinessEntityAccess;
use App\Support\PhoneNumber;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasBusinessEntityAccess, HasFactory, HasPanelShield, HasRoles, Notifiable;

    /**
     * Role operasional yang boleh mengeluarkan aset dari stok (BA Serah Terima)
     * dan menerima pengembalian aset ke stok (BA Pengembalian).
     */
    public const GENERAL_AFFAIR_ROLE = 'general_affair';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'employee_id',
        'whatsapp_number',
        'whatsapp_login_number',
        'password',
        'email_verified_at',
        'business_entity_id',
        'access_all_business_entities',
        'job_title_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'whatsapp_login_number',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'access_all_business_entities' => 'boolean',
    ];

    public function setEmailAttribute($value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['email'] = null;

            return;
        }

        $email = trim((string) $value);

        if (preg_match('/[\r\n]/', $email) === 1) {
            throw new InvalidArgumentException('Email must not contain line breaks.');
        }

        $this->attributes['email'] = $email;
    }

    public function setWhatsappLoginNumberAttribute($value): void
    {
        $phone = PhoneNumber::canonical($value);
        if (filled($value) && $phone === null) {
            throw new InvalidArgumentException('Nomor WhatsApp untuk login tidak valid.');
        }

        $this->attributes['whatsapp_login_number'] = $phone;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }

    /**
     * Filament Impersonate: controlled by the Shield `impersonate_user` permission.
     */
    public function canImpersonate(): bool
    {
        return $this->can('impersonate', self::class);
    }

    /**
     * Filament Impersonate: only users who can open the panel, only within
     * the impersonator's business entities, and never a super admin unless
     * the impersonator is a super admin too.
     */
    public function canBeImpersonated(): bool
    {
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        if ($this->roles->isEmpty()) {
            return false;
        }

        $impersonator = Filament::auth()->user();

        if (! $impersonator instanceof self) {
            return false;
        }

        if (! $impersonator->canAccessBusinessEntity($this->business_entity_id)) {
            return false;
        }

        if (! $this->hasRole($superAdmin)) {
            return true;
        }

        return $impersonator->hasRole($superAdmin);
    }

    public function isGeneralAffair(): bool
    {
        return $this->hasRole(self::GENERAL_AFFAIR_ROLE);
    }

    /**
     * Akun bersama yang mewakili departemen (mis. akun GA "adminga"), bukan
     * orang; lihat config/asset-transfer.php.
     */
    public function isSharedAccount(): bool
    {
        $username = strtolower(trim((string) $this->username));
        $sharedUsernames = array_map('strtolower', (array) config('asset-transfer.shared_general_affair_usernames', []));

        return $username !== '' && in_array($username, $sharedUsernames, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    /**
     * Boleh membuat BA stok (Serah Terima, Pengembalian) atas nama staf GA mana
     * pun: pemegang izin Shield "Kelola BA Stok".
     */
    public function canManageStockTransfers(): bool
    {
        return $this->can('manageStock', AssetTransfer::class);
    }

    /**
     * Boleh membuat BA stok sama sekali. Staf GA tanpa izin "Kelola BA Stok"
     * hanya bisa menjadi pihak GA di BA-nya sendiri.
     */
    public function canCreateStockTransfers(): bool
    {
        return $this->isGeneralAffair() || $this->canManageStockTransfers();
    }

    /**
     * Users holding the general_affair role.
     */
    public function scopeGeneralAffair(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', self::GENERAL_AFFAIR_ROLE));
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function nameWithJobTitle(): string
    {
        $this->loadMissing('jobTitle');

        $title = $this->jobTitle?->title;

        return filled($title) ? "{$this->name} ({$title})" : $this->name;
    }

    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    public function assetTransfersFrom()
    {
        return $this->hasMany(AssetTransfer::class, 'from_user_id');
    }

    public function assetTransfersTo()
    {
        return $this->hasMany(AssetTransfer::class, 'to_user_id');
    }

    public function assetTransferDetails()
    {
        return $this->hasManyThrough(
            AssetTransferDetail::class, // Model tujuan akhir (AssetTransferDetail)
            AssetTransfer::class, // Model perantara (AssetTransfer)
            'from_user_id', // Foreign key di AssetTransfer (relasi ke User)
            'asset_transfer_id', // Foreign key di AssetTransferDetail (relasi ke AssetTransfer)
            'id', // Local key di User
            'id'  // Local key di AssetTransfer
        );
    }

    public function isDuplicate(): bool
    {
        return User::where('name', $this->name)
            ->where('id', '!=', $this->id)  // Hindari perbandingan dengan dirinya sendiri
            ->exists();
    }
}
