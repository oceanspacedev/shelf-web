<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_date',
        'business_entity_id',
        'name',
        'image',
        'category_id',
        'brand_id',
        'type',
        'serial_number',
        'imei1',
        'imei2',
        'item_price',
        'asset_location_id',
        'condition_status',
        'nbh_status',
        'nbh_reported_at',
        'audit_document_path',
        'nbh_document_path',
        'nbh_notes',
        'nbh_responsible_user_id',
        'sold_at',
        'sold_to',
        'sold_price',
        'sale_document_path',
        'sale_notes',
        'qty',
        'is_available',
        'recipient_id',
        'recipient_business_entity_id',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'condition_status' => AssetCondition::class,
        'nbh_status' => NbhStatus::class,
        'nbh_reported_at' => 'date',
        'sold_at' => 'date',
        'sold_price' => 'integer',
    ];

    public function attributes(): HasMany
    {
        return $this->hasMany(AssetAttribute::class);
    }

    // Relasi ke tabel business_entities
    public function businessEntity()
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    // Relasi ke tabel categories
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Relasi ke tabel brands
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    // Relasi ke tabel asset_locations
    public function assetLocation()
    {
        return $this->belongsTo(AssetLocation::class);
    }

    // Relasi ke tabel asset_transfers
    public function assetTransfers()
    {
        return $this->hasMany(AssetTransfer::class);
    }

    // Relasi ke tabel asset_transfer_details
    public function assetTransferDetails()
    {
        return $this->hasMany(AssetTransferDetail::class);
    }

    public function latestTransferDetail(): ?AssetTransferDetail
    {
        return $this->assetTransferDetails()
            ->with(['assetTransfer.toUser'])
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    public function syncRecipientFromLatestTransferDetail(): bool
    {
        $latestTransfer = $this->latestTransferDetail()?->assetTransfer;

        if (! $latestTransfer?->toUser) {
            return false;
        }

        $this->recipient_id = $latestTransfer->to_user_id;
        $this->recipient_business_entity_id = $latestTransfer->business_entity_id;
        $this->condition_status = $latestTransfer->toUser->hasRole('general_affair')
            ? AssetCondition::Available
            : AssetCondition::Transferred;

        if ($this->condition_status instanceof AssetCondition && $this->condition_status->isTransferable()) {
            $this->nbh_status = NbhStatus::None;
            $this->nbh_responsible_user_id = null;
        }

        $this->unsetRelation('recipient');
        $this->cachedValidRecipientResult = null;

        return $this->save();
    }

    // Relasi ke tabel users untuk recipient_id
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    // Relasi ke tabel business_entities untuk recipient_business_entity_id
    public function recipientBusinessEntity()
    {
        return $this->belongsTo(BusinessEntity::class, 'recipient_business_entity_id');
    }

    public function nbhResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nbh_responsible_user_id');
    }

    private function formatDiff($value, $unit)
    {
        return $value.' '.$unit;
    }

    public function getItemAgeAttribute()
    {
        $purchaseDate = Carbon::parse($this->attributes['purchase_date']);
        $now = Carbon::now();

        $diff = $purchaseDate->diff($now);

        if ($diff->y > 0 && $diff->m > 0) {
            return $diff->y.' tahun '.$diff->m.' bulan';
        }

        if ($diff->y > 0) {
            return $this->formatDiff($diff->y, 'tahun');
        }

        if ($diff->m > 0) {
            return $this->formatDiff($diff->m, 'bulan');
        }

        return $this->formatDiff($diff->d, 'hari');
    }

    public function scopeSortByItemAge(Builder $query, string $direction = 'asc')
    {
        $query->orderByRaw('DATEDIFF(NOW(), purchase_date) '.$direction);
    }

    public function getIsAvailableAttribute($value)
    {
        if ($this->condition_status instanceof AssetCondition) {
            return $this->condition_status->label();
        }

        return $value ? 'Tersedia' : 'Transfer';
    }

    public function setIsAvailableAttribute($value): void
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue === null) {
            $this->attributes['is_available'] = $value;

            return;
        }

        $this->attributes['is_available'] = $boolValue;
        $this->attributes['condition_status'] = $boolValue
            ? AssetCondition::Available->value
            : AssetCondition::Transferred->value;
    }

    public function getConditionStatusLabelAttribute(): string
    {
        return $this->condition_status instanceof AssetCondition
            ? $this->condition_status->label()
            : 'Tidak Diketahui';
    }

    public function getConditionStatusColorAttribute(): string
    {
        return $this->condition_status instanceof AssetCondition
            ? $this->condition_status->color()
            : 'secondary';
    }

    public function setConditionStatusAttribute($value): void
    {
        if ($value instanceof AssetCondition) {
            $enum = $value;
        } else {
            $enum = AssetCondition::tryFrom((string) $value) ?? AssetCondition::Available;
        }

        $this->attributes['condition_status'] = $enum->value;
        $this->attributes['is_available'] = $enum === AssetCondition::Available;

        if ($enum === AssetCondition::Sold) {
            $this->attributes['recipient_id'] = null;
            $this->attributes['recipient_business_entity_id'] = null;
        } else {
            $this->clearSaleAuditAttributes();
        }

        if ($enum->isIncident()) {
            if (($this->attributes['nbh_status'] ?? null) === NbhStatus::None->value || ! isset($this->attributes['nbh_status'])) {
                $this->setNbhStatusAttribute(NbhStatus::Pending);
            }
        } else {
            $this->setNbhStatusAttribute(NbhStatus::None);
        }
    }

    protected function clearSaleAuditAttributes(): void
    {
        $this->attributes['sold_at'] = null;
        $this->attributes['sold_to'] = null;
        $this->attributes['sold_price'] = null;
        $this->attributes['sale_document_path'] = null;
        $this->attributes['sale_notes'] = null;
    }

    public function getNbhStatusLabelAttribute(): string
    {
        return $this->nbh_status instanceof NbhStatus
            ? $this->nbh_status->label()
            : NbhStatus::None->label();
    }

    public function getNbhStatusColorAttribute(): string
    {
        return $this->nbh_status instanceof NbhStatus
            ? $this->nbh_status->color()
            : NbhStatus::None->color();
    }

    public function setNbhStatusAttribute($value): void
    {
        if ($value instanceof NbhStatus) {
            $enum = $value;
        } else {
            $enum = NbhStatus::tryFrom((string) $value) ?? NbhStatus::None;
        }

        $this->attributes['nbh_status'] = $enum->value;

        if ($enum === NbhStatus::None) {
            $this->attributes['nbh_responsible_user_id'] = null;
            $this->attributes['nbh_reported_at'] = null;
            $this->attributes['audit_document_path'] = null;
            $this->attributes['nbh_document_path'] = null;
            $this->attributes['nbh_notes'] = null;
        }
    }

    protected ?bool $cachedValidRecipientResult = null;

    public function checkValidRecipient(): bool
    {
        if ($this->cachedValidRecipientResult !== null) {
            return $this->cachedValidRecipientResult;
        }

        $this->cachedValidRecipientResult = $this->performValidRecipientCheck();

        return $this->cachedValidRecipientResult;
    }

    protected function performValidRecipientCheck(): bool
    {
        if ($this->condition_status === AssetCondition::Sold) {
            return true;
        }

        $latestTransfer = $this->latestTransferDetail()?->assetTransfer;

        if (! $latestTransfer) {
            return true;
        }

        if ($this->recipient_id != $latestTransfer->to_user_id) {
            return false;
        }

        $recipient = $this->recipient ?? User::find($this->recipient_id);

        if (! $recipient) {
            return false;
        }

        $hasGeneralAffairRole = $recipient->hasRole('general_affair');

        if ($this->condition_status instanceof AssetCondition && $this->condition_status->isIncident()) {
            if ($this->nbh_status === NbhStatus::None) {
                return false;
            }

            if ($this->nbh_status === NbhStatus::Resolved) {
                return ! empty($this->audit_document_path)
                    && ! empty($this->nbh_responsible_user_id);
            }

            return true;
        }

        if ($hasGeneralAffairRole && $this->condition_status !== AssetCondition::Available) {
            return false;
        }

        if (! $hasGeneralAffairRole && $this->condition_status !== AssetCondition::Transferred) {
            return false;
        }

        return true;
    }

    public function vehicleChecksheets(): HasMany
    {
        return $this->hasMany(VehicleChecksheet::class, 'asset_id');
    }
}
