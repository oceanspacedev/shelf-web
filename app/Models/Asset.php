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

    protected static function booted(): void
    {
        // Stok (Tersedia) tidak pernah punya pemegang, lewat jalur mana pun aset
        // disimpan: form yang field pemegangnya terkunci atau tersembunyi (tidak
        // ikut tersimpan), setIsAvailableAttribute(true), import, dan sebagainya.
        static::saving(function (Asset $asset): void {
            if ($asset->condition_status === AssetCondition::Available && $asset->recipient_id !== null) {
                $asset->recipient_id = null;
            }
        });

        static::created(function (Asset $asset): void {
            // Test fixtures may build the asset schema without the QR tables.
            if (! Schema::hasTable('asset_qrs')) {
                return;
            }

            app(\App\Services\AssetQrService::class)->ensureForAsset($asset);
        });
    }

    protected $fillable = [
        'purchase_date',
        'business_entity_id',
        'name',
        'asset_catalog_item_id',
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
        'inventory_active',
        'reconciliation_source',
        'last_reconciled_at',
        'last_reconciliation_id',
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
        'inventory_active' => 'boolean',
        'last_reconciled_at' => 'datetime',
    ];

    public function attributes(): HasMany
    {
        return $this->hasMany(AssetAttribute::class);
    }

    public function qr(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AssetQr::class);
    }

    public function qrScans(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(AssetQrScan::class, AssetQr::class);
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

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(AssetCatalogItem::class, 'asset_catalog_item_id');
    }

    public function lastReconciliation(): BelongsTo
    {
        return $this->belongsTo(AssetReconciliation::class, 'last_reconciliation_id');
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

    public function services(): HasMany
    {
        return $this->hasMany(AssetService::class);
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

    /**
     * Sesuaikan pemegang dan kondisi dengan BA terbaru: BA Pengembalian
     * memulangkan aset ke stok (tanpa pemegang), BA lain menyerahkannya ke
     * penerima BA.
     */
    public function syncRecipientFromLatestTransferDetail(): bool
    {
        $latestTransfer = $this->latestTransferDetail()?->assetTransfer;
        $documentType = $latestTransfer?->documentType();

        if (! $latestTransfer || ! $documentType) {
            return false;
        }

        if (! $documentType->returnsToStock() && ! $latestTransfer->toUser) {
            return false;
        }

        $this->recipient_id = $documentType->returnsToStock() ? null : $latestTransfer->to_user_id;
        $this->recipient_business_entity_id = $latestTransfer->business_entity_id;
        $this->condition_status = $documentType->returnsToStock()
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

    /**
     * Setelah perbaikan selesai: aset tanpa pemegang kembali ke stok (Tersedia),
     * aset yang dipegang seseorang kembali Digunakan.
     */
    protected function operationalConditionAfterRepair(): AssetCondition
    {
        return $this->recipient_id ? AssetCondition::Transferred : AssetCondition::Available;
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
    /**
     * Aset yang boleh dilihat $user: badan usaha pemilik, badan usaha penerima
     * (BA terakhir / penanda stok), atau badan usaha pemegangnya termasuk akses
     * user, supaya aset yang dipakai lintas badan usaha tetap terlihat oleh
     * semua pihak yang mengurusnya.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->hasUnrestrictedBusinessEntityAccess()) {
            return $query;
        }

        $ids = $user->accessibleBusinessEntityIds();

        return $query->where(fn (Builder $scoped): Builder => $scoped
            ->whereIn($query->qualifyColumn('business_entity_id'), $ids)
            ->orWhereIn($query->qualifyColumn('recipient_business_entity_id'), $ids)
            ->orWhereHas('recipient', fn (Builder $holder): Builder => $holder->whereIn('business_entity_id', $ids)));
    }

    public function isAccessibleBy(User $user): bool
    {
        return $user->canAccessBusinessEntity($this->business_entity_id)
            || $user->canAccessBusinessEntity($this->recipient_business_entity_id)
            || ($this->recipient_id !== null && $user->canAccessBusinessEntity($this->recipient?->business_entity_id));
    }

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

        if (self::assetRequestItemsTableAvailable()) {
            $query->whereDoesntHave('assetRequestItems.assetRequest', function (Builder $q): void {
                $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                    ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                    ->whereNull('fulfilled_at');
            });
        }

        return $query;
    }

    /**
     * Aset yang boleh diajukan penarikan/perbaikan: tidak terkunci request terbuka
     * dan kondisi masih transferable (bukan Damaged/Lost/Sold).
     */
    public function scopeEligibleForPenarikanOrPerbaikan(Builder $query): Builder
    {
        return $query
            ->notLockedForOpenRequest()
            ->whereIn('condition_status', AssetCondition::transferableValues());
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

        if ($legacyLockExists || ! self::assetRequestItemsTableAvailable()) {
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

    /**
     * Cek skema dipanggil per query aset (mis. tiap baris form BA); hasilnya
     * tidak berubah selama request sehingga cukup sekali ke information_schema.
     */
    protected static function assetRequestLockColumnsAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable('asset_requests')
            && Schema::hasColumn('asset_requests', 'type')
            && Schema::hasColumn('asset_requests', 'status')
            && Schema::hasColumn('asset_requests', 'fulfilled_at'));
    }

    protected static function assetRequestItemsTableAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable('asset_request_items'));
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
        $latestDocumentType = $latestTransfer?->documentType();

        if ($latestTransfer && $latestDocumentType) {
            $expectedRecipientId = $latestDocumentType->returnsToStock() ? 0 : (int) $latestTransfer->to_user_id;

            if ((int) ($this->recipient_id ?? 0) !== $expectedRecipientId) {
                return false;
            }
        }

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

        // Stok (Tersedia) tidak punya pemegang; aset Digunakan selalu punya pemegang.
        if ($this->condition_status === AssetCondition::Available) {
            return $this->recipient_id === null;
        }

        if ($this->condition_status === AssetCondition::Transferred) {
            return $this->recipient_id !== null;
        }

        return true;
    }

    public function vehicleChecksheets(): HasMany
    {
        return $this->hasMany(VehicleChecksheet::class, 'asset_id');
    }
}
