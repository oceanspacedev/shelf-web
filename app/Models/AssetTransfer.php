<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Enums\NbhStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class AssetTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_entity_id',
        'letter_number',
        'from_user_id',
        'to_user_id',
        'document',
        'transfer_date',
    ];

    // Relasi ke tabel business_entities
    public function businessEntity()
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    // Relasi ke tabel assets
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    // Relasi ke tabel asset_transfer_details
    public function details(): HasMany
    {
        return $this->hasMany(AssetTransferDetail::class);
    }

    // Relasi ke tabel users untuk from_user_id
    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    // Relasi ke tabel users untuk to_user_id
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * Generate nomor BA berdasarkan format business entity, melanjutkan sequence.
     */
    public static function generateLetterNumber(?BusinessEntity $businessEntity, $newNumber = null): string
    {
        if (! $businessEntity) {
            return '';
        }

        $format = $businessEntity->format;

        if ($newNumber === null) {
            $lastTransfer = self::where('business_entity_id', $businessEntity->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $lastNumber = $lastTransfer ? (int) preg_replace('/\D/', '', substr($lastTransfer->letter_number, -6)) : 0;
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        }

        return "{$format}{$newNumber}";
    }

    public function scopeGeneralAffair($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'general_affair');
        });
    }

    public function documentType(): ?AssetTransferDocumentType
    {
        $this->loadMissing('fromUser.roles', 'toUser.roles');

        return AssetTransferDocumentType::fromUsers($this->fromUser, $this->toUser);
    }

    public function documentCode(): string
    {
        return $this->documentType()?->code() ?? 'UNKNOWN';
    }

    public function documentColor(): string
    {
        return $this->documentType()?->color() ?? 'gray';
    }

    public function getStatusAttribute(): string
    {
        return $this->documentType()?->label() ?? 'Status Transfer Tidak Valid';
    }

    public function scopeForDocumentType(Builder $query, AssetTransferDocumentType|string|null $type): Builder
    {
        if (is_string($type)) {
            $type = AssetTransferDocumentType::tryFrom($type);
        }

        if (! $type) {
            return $query;
        }

        $hasGeneralAffairRole = fn (Builder $roleQuery): Builder => $roleQuery->where('name', 'general_affair');

        return match ($type) {
            AssetTransferDocumentType::SerahTerima => $query
                ->whereHas('fromUser.roles', $hasGeneralAffairRole)
                ->whereDoesntHave('toUser.roles', $hasGeneralAffairRole),
            AssetTransferDocumentType::PengalihanBarang => $query
                ->whereDoesntHave('fromUser.roles', $hasGeneralAffairRole)
                ->whereDoesntHave('toUser.roles', $hasGeneralAffairRole),
            AssetTransferDocumentType::PengembalianBarang => $query
                ->whereDoesntHave('fromUser.roles', $hasGeneralAffairRole)
                ->whereHas('toUser.roles', $hasGeneralAffairRole),
        };
    }

    public function applyLifecycleToAssets(?AssetRequest $sourceAssetRequest = null): void
    {
        $documentType = $this->documentType();

        if (! $documentType) {
            throw new RuntimeException('Alur transfer aset tidak valid untuk kombinasi pemberi dan penerima ini.');
        }

        $this->loadMissing('details.asset.recipient');
        $this->ensureAssetsCanMove($documentType, $sourceAssetRequest);

        foreach ($this->details as $detail) {
            $asset = $detail->asset;

            if (! $asset) {
                continue;
            }

            $asset->recipient_id = $this->to_user_id;
            $asset->recipient_business_entity_id = $this->business_entity_id;
            $asset->condition_status = $this->conditionAfterTransfer($documentType);

            if ($asset->condition_status instanceof AssetCondition && $asset->condition_status->isTransferable()) {
                $asset->nbh_status = NbhStatus::None;
                $asset->nbh_responsible_user_id = null;
            }

            $asset->save();
        }
    }

    protected function ensureAssetsCanMove(AssetTransferDocumentType $documentType, ?AssetRequest $sourceAssetRequest = null): void
    {
        if ($this->details->isEmpty()) {
            throw new RuntimeException('BA transfer wajib memiliki minimal satu aset.');
        }

        $assetIds = $this->details
            ->pluck('asset_id')
            ->filter()
            ->map(fn ($assetId): int => (int) $assetId)
            ->values();

        if ($assetIds->count() !== $assetIds->unique()->count()) {
            throw new RuntimeException('Aset dalam BA transfer tidak boleh duplikat.');
        }

        foreach ($this->details as $detail) {
            $asset = $detail->asset;

            if (! $detail->asset_id || ! $asset) {
                throw new RuntimeException('Detail BA transfer memuat aset yang tidak ditemukan.');
            }

            if ($this->assetAlreadyReflectsTransfer($asset, $documentType)) {
                continue;
            }

            if (! ($asset->condition_status instanceof AssetCondition) || ! $asset->condition_status->isTransferable()) {
                throw new RuntimeException(sprintf(
                    'Aset "%s" tidak bisa ditransfer karena statusnya %s.',
                    $asset->name,
                    $asset->condition_status_label,
                ));
            }

            if ($asset->hasOpenAssetRequestLock($sourceAssetRequest?->id)) {
                throw new RuntimeException(sprintf(
                    'Aset "%s" sedang dalam pengajuan aktif dan belum bisa ditransfer.',
                    $asset->name,
                ));
            }

            if ($documentType === AssetTransferDocumentType::SerahTerima) {
                $this->ensureAssetCanBeDispatchedFromGeneralAffair($asset);

                continue;
            }

            if ((int) $asset->recipient_id !== (int) $this->from_user_id) {
                throw new RuntimeException(sprintf(
                    'Aset "%s" bukan milik pemberi transfer.',
                    $asset->name,
                ));
            }
        }
    }

    protected function assetAlreadyReflectsTransfer(Asset $asset, AssetTransferDocumentType $documentType): bool
    {
        return (int) $asset->recipient_id === (int) $this->to_user_id
            && (int) ($asset->recipient_business_entity_id ?? 0) === (int) ($this->business_entity_id ?? 0)
            && $asset->condition_status === $this->conditionAfterTransfer($documentType);
    }

    protected function conditionAfterTransfer(AssetTransferDocumentType $documentType): AssetCondition
    {
        return $documentType->returnsToGeneralAffair()
            ? AssetCondition::Available
            : AssetCondition::Transferred;
    }

    protected function ensureAssetCanBeDispatchedFromGeneralAffair(Asset $asset): void
    {
        if ($asset->condition_status !== AssetCondition::Available) {
            throw new RuntimeException(sprintf(
                'Aset "%s" harus berstatus Tersedia sebelum diserahterimakan dari General Affairs.',
                $asset->name,
            ));
        }

        if ($asset->recipient_id && ! Asset::userHasGeneralAffairRole($asset->recipient)) {
            throw new RuntimeException(sprintf(
                'Aset "%s" masih tercatat pada pemegang non-General Affairs.',
                $asset->name,
            ));
        }
    }
}
