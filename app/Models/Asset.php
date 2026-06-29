<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\NbhStatus;
use App\Enums\RequestStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

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
        'asset_request_id',
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
        $this->condition_status = self::userHasGeneralAffairRole($latestTransfer->toUser)
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

    /**
     * Menutup proses perbaikan/NBH dan mengembalikan aset ke status operasional.
     *
     * @param  array<string, mixed>  $data
     */
    public function completeRepairProcessing(User $actor, array $data = []): bool
    {
        if ($this->condition_status !== AssetCondition::Damaged) {
            throw new \InvalidArgumentException('Hanya aset Rusak yang bisa diselesaikan perbaikannya.');
        }

        $auditDocumentPath = self::normalizeUploadPath($data['audit_document_path'] ?? $this->audit_document_path);
        $nbhDocumentPath = self::normalizeUploadPath($data['nbh_document_path'] ?? $this->nbh_document_path);

        // Tangkap nilai lama SEBELUM menulis condition_status: mutator
        // setConditionStatusAttribute() me-null-kan kolom NBH (termasuk
        // nbh_reported_at & nbh_notes) saat kondisi bukan Rusak, sehingga
        // fallback `?? $this->X` setelahnya akan membaca null (dead code).
        $originalReportedAt = $this->nbh_reported_at;
        $originalNotes = $this->nbh_notes;

        $this->condition_status = $this->operationalConditionAfterRepair();
        $this->nbh_status = NbhStatus::Resolved;
        // Pertahankan tanggal insiden asli (nbh_reported_at dilabel "Tanggal Insiden"
        // pada form utama/infolist/export). Jangan timpa dengan tanggal selesai dari
        // input agar audit trail insiden tidak rusak; tanggal selesai terekam lewat
        // updated_at saat save().
        $this->nbh_reported_at = $originalReportedAt;
        $this->nbh_responsible_user_id = $data['nbh_responsible_user_id'] ?? $actor->id;
        $this->audit_document_path = $auditDocumentPath;
        $this->nbh_document_path = $nbhDocumentPath;
        $this->nbh_notes = $data['nbh_notes'] ?? $originalNotes;

        return $this->save();
    }

    protected function operationalConditionAfterRepair(): AssetCondition
    {
        if (! $this->recipient_id) {
            return AssetCondition::Available;
        }

        $recipient = $this->relationLoaded('recipient')
            ? $this->recipient
            : User::find($this->recipient_id);

        if (self::userHasGeneralAffairRole($recipient)) {
            return AssetCondition::Available;
        }

        return AssetCondition::Transferred;
    }

    public static function userHasGeneralAffairRole(?User $user): bool
    {
        return $user !== null
            && Schema::hasTable('roles')
            && Schema::hasTable('model_has_roles')
            && $user->hasRole('general_affair');
    }

    protected static function normalizeUploadPath(mixed $path): ?string
    {
        if (is_array($path)) {
            $path = reset($path) ?: null;
        }

        return filled($path) ? (string) $path : null;
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

    // Relasi ke pengajuan aset yang menjadi sumber aset ini (nullable).
    public function assetRequest(): BelongsTo
    {
        return $this->belongsTo(AssetRequest::class, 'asset_request_id');
    }

    /**
     * Pengajuan yang menargetkan aset ini sebagai subjek (penarikan/perbaikan).
     * Berbeda dari assetRequest() (link "aset dibuat dari pengajuan X"):
     * relasi ini me-link aset ke pengajuan yang menarik/memperbaiki aset ini.
     */
    public function assetRequests(): HasMany
    {
        return $this->hasMany(AssetRequest::class, 'asset_id');
    }

    public function assetRequestItems(): HasMany
    {
        return $this->hasMany(AssetRequestItem::class, 'asset_id');
    }

    /**
     * Scope: aset yang TIDAK sedang dalam pengajuan penarikan/perbaikan terbuka
     * (status Pending atau Approved yang belum di-fulfill). Dipakai untuk
     * mengunci aset dari opsi transfer selama pengajuan belum selesai ditindak
     * lanjuti, agar tidak dobel-tindak (dipindah sambil ditarik/diperbaiki).
     */
    public function scopeNotLockedForOpenRequest(Builder $query): Builder
    {
        if (! self::assetRequestLockColumnsAvailable()) {
            return $query;
        }

        $query->whereDoesntHave('assetRequests', function (Builder $q): void {
            $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                ->whereNull('fulfilled_at');
        });

        if (Schema::hasTable('asset_request_items')) {
            $query->whereDoesntHave('assetRequestItems.assetRequest', function (Builder $q): void {
                $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                    ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                    ->whereNull('fulfilled_at');
            });
        }

        return $query;
    }

    public function hasOpenAssetRequestLock(?int $exceptAssetRequestId = null): bool
    {
        if (! self::assetRequestLockColumnsAvailable()) {
            return false;
        }

        $legacyLockExists = $this->assetRequests()
            ->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
            ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
            ->whereNull('fulfilled_at')
            ->when($exceptAssetRequestId, fn (Builder $q) => $q->whereKeyNot($exceptAssetRequestId))
            ->exists();

        if ($legacyLockExists || ! Schema::hasTable('asset_request_items')) {
            return $legacyLockExists;
        }

        return $this->assetRequestItems()
            ->whereHas('assetRequest', function (Builder $q) use ($exceptAssetRequestId): void {
                $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                    ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                    ->whereNull('fulfilled_at')
                    ->when($exceptAssetRequestId, fn (Builder $query) => $query->whereKeyNot($exceptAssetRequestId));
            })
            ->exists();
    }

    protected static function assetRequestLockColumnsAvailable(): bool
    {
        return Schema::hasTable('asset_requests')
            && Schema::hasColumn('asset_requests', 'type')
            && Schema::hasColumn('asset_requests', 'status')
            && Schema::hasColumn('asset_requests', 'fulfilled_at');
    }

    private function formatDiff($value, $unit)
    {
        return $value.' '.$unit;
    }

    public function getItemAgeAttribute()
    {
        $rawPurchaseDate = $this->attributes['purchase_date'] ?? null;

        if (blank($rawPurchaseDate)) {
            return '-';
        }

        try {
            $purchaseDate = Carbon::parse($rawPurchaseDate);
        } catch (\Throwable) {
            return '-';
        }

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
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $purchaseDateDirection = $direction === 'asc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw('purchase_date IS NULL asc')
            ->orderBy('purchase_date', $purchaseDateDirection);
    }

    public function getIsAvailableAttribute($value)
    {
        // Nilai boolean murni untuk konsumsi programatik (filter/where/if).
        // Label tampilan tersedia via getConditionStatusLabelAttribute().
        if ($this->condition_status instanceof AssetCondition) {
            return $this->condition_status === AssetCondition::Available;
        }

        return (bool) $value;
    }

    public function setIsAvailableAttribute($value): void
    {
        $boolValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($boolValue === null) {
            $this->attributes['is_available'] = $value;

            return;
        }

        $this->attributes['is_available'] = $boolValue;

        $currentCondition = isset($this->attributes['condition_status'])
            ? AssetCondition::tryFrom((string) $this->attributes['condition_status'])
            : null;

        if ($boolValue) {
            $this->attributes['condition_status'] = AssetCondition::Available->value;

            return;
        }

        if (! $currentCondition || $currentCondition === AssetCondition::Available) {
            $this->attributes['condition_status'] = AssetCondition::Transferred->value;
        }
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

        if ($latestTransfer && $this->recipient_id != $latestTransfer->to_user_id) {
            return false;
        }

        $recipient = $this->recipient ?? User::find($this->recipient_id);
        $hasGeneralAffairRole = self::userHasGeneralAffairRole($recipient);

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

        if ($this->condition_status === AssetCondition::Available) {
            return ! $recipient || $hasGeneralAffairRole;
        }

        if ($this->condition_status === AssetCondition::Transferred && ! $recipient) {
            return false;
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
