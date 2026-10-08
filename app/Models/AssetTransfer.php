<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Enums\NbhStatus;
use App\Exceptions\AssetTransferException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Berita acara transfer aset. Jenis dokumen (document_type) dipilih eksplisit;
 * pemberi dan penerima selalu orang. Stok direpresentasikan sebagai aset tanpa
 * pemegang, sehingga BA Serah Terima mengeluarkan aset dari stok dan BA
 * Pengembalian memulangkannya ke stok.
 */
class AssetTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_entity_id',
        'document_type',
        'letter_number',
        'from_user_id',
        'to_user_id',
        'document',
        'transfer_date',
    ];

    protected $casts = [
        'document_type' => AssetTransferDocumentType::class,
    ];

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(BusinessEntity::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(AssetTransferDetail::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
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
        if (empty($format) || $format === '0' || $format === '-') {
            $name = preg_replace('/^(pt\.|pt|cv\.|cv)\s+/i', '', trim($businessEntity->name));
            $words = preg_split('/[\s\-\.]+/', $name);
            if (count($words) === 1) {
                $prefix = strtoupper(substr($words[0], 0, 6));
            } else {
                $initials = '';
                foreach ($words as $word) {
                    $cleanedWord = preg_replace('/[^a-zA-Z0-9]/', '', $word);
                    if ($cleanedWord !== '') {
                        $initials .= substr($cleanedWord, 0, 1);
                    }
                }
                $prefix = strtoupper($initials);
            }
            $format = $prefix.'/';
        }

        if ($newNumber === null) {
            $lastTransfer = self::where('business_entity_id', $businessEntity->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $lastNumber = 0;
            if ($lastTransfer && str_starts_with($lastTransfer->letter_number, $format)) {
                $lastNumber = (int) preg_replace('/\D/', '', substr($lastTransfer->letter_number, -6));
            }

            do {
                $lastNumber++;
                $newNumberStr = str_pad($lastNumber, 6, '0', STR_PAD_LEFT);
                $candidate = "{$format}{$newNumberStr}";
                $exists = self::where('letter_number', $candidate)->exists();
            } while ($exists);

            return $candidate;
        }

        return "{$format}{$newNumber}";
    }

    public function documentType(): ?AssetTransferDocumentType
    {
        return $this->document_type;
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

        return $query->where('document_type', $type->value);
    }

    /**
     * BA yang boleh dilihat $user: badan usaha BA termasuk akses user.
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return $user->limitToAccessibleBusinessEntities($query, $query->qualifyColumn('business_entity_id'));
    }

    public function isAccessibleBy(User $user): bool
    {
        return $user->canAccessBusinessEntity($this->business_entity_id);
    }

    /**
     * Mutasi pemegang dan kondisi aset sesuai jenis BA. Idempoten: aset yang
     * sudah merefleksikan BA ini dilewati.
     *
     * $actor adalah akun yang sedang membuat BA; bila diberikan, pihak GA pada
     * BA harus akun itu sendiri (lihat ensureGeneralAffairPartyIsActor).
     */
    public function applyLifecycleToAssets(?AssetRequest $sourceAssetRequest = null, ?User $actor = null): void
    {
        $documentType = $this->documentType();

        if (! $documentType) {
            throw new AssetTransferException('Jenis berita acara belum ditentukan.');
        }

        $this->loadMissing('details.asset', 'fromUser', 'toUser');
        $this->ensurePartiesMatchDocumentType($documentType);
        $this->ensureGeneralAffairPartyIsActor($documentType, $actor);
        $this->ensureActorCanReach($actor);
        $this->ensureAssetsCanMove($documentType, $sourceAssetRequest);

        foreach ($this->details as $detail) {
            $asset = $detail->asset;

            if (! $asset) {
                continue;
            }

            $asset->recipient_id = $documentType->returnsToStock() ? null : $this->to_user_id;
            $asset->recipient_business_entity_id = $this->business_entity_id;
            $asset->condition_status = $this->conditionAfterTransfer($documentType);

            if ($asset->condition_status instanceof AssetCondition && $asset->condition_status->isTransferable()) {
                $asset->nbh_status = NbhStatus::None;
                $asset->nbh_responsible_user_id = null;
            }

            $asset->save();
        }
    }

    protected function ensurePartiesMatchDocumentType(AssetTransferDocumentType $documentType): void
    {
        if (! $this->fromUser || ! $this->toUser) {
            throw new AssetTransferException('Pemberi dan penerima BA wajib diisi.');
        }

        if ($this->fromUser->is($this->toUser)) {
            throw new AssetTransferException('Pemberi dan penerima BA tidak boleh orang yang sama.');
        }

        if ($documentType->requiresGeneralAffairFrom() && ! $this->fromUser->isGeneralAffair()) {
            throw new AssetTransferException(sprintf(
                'BA Serah Terima harus dibuat oleh staf General Affairs; "%s" bukan staf GA.',
                $this->fromUser->name,
            ));
        }

        if ($documentType->requiresGeneralAffairTo() && ! $this->toUser->isGeneralAffair()) {
            throw new AssetTransferException(sprintf(
                'Penerima BA Pengembalian harus staf General Affairs; "%s" bukan staf GA.',
                $this->toUser->name,
            ));
        }
    }

    /**
     * Pihak GA pada BA adalah akun yang sedang membuatnya: staf GA tidak bisa
     * membuat BA atas nama staf GA lain, dan yang bukan staf GA tidak bisa
     * membuat BA yang menyentuh stok. Pemegang izin "Kelola BA Stok"
     * dikecualikan. Tanpa aktor (proses latar) aturan ini tidak dicek.
     */
    protected function ensureGeneralAffairPartyIsActor(AssetTransferDocumentType $documentType, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $generalAffairParty = match (true) {
            $documentType->requiresGeneralAffairFrom() => $this->fromUser,
            $documentType->requiresGeneralAffairTo() => $this->toUser,
            default => null,
        };

        // Pengalihan tidak punya pihak GA; staf GA boleh selalu bertindak atas
        // nama sendiri (ensurePartiesMatchDocumentType sudah memastikan pihak GA
        // memang staf GA).
        if ($generalAffairParty === null || $generalAffairParty->is($actor) || $actor->canManageStockTransfers()) {
            return;
        }

        $label = ucwords(strtolower($documentType->label()));

        if (! $actor->isGeneralAffair()) {
            throw new AssetTransferException(sprintf(
                'Hanya staf General Affairs atau pemegang izin Kelola BA Stok yang bisa membuat %s.',
                $label,
            ));
        }

        throw new AssetTransferException(sprintf(
            'Pihak GA pada %s harus akun Anda sendiri (%s), bukan "%s".',
            $label,
            $actor->name,
            $generalAffairParty->name,
        ));
    }

    /**
     * Aktor dengan akses badan usaha terbatas hanya bisa membuat BA di badan
     * usaha yang bisa diaksesnya, untuk aset yang bisa diaksesnya.
     */
    protected function ensureActorCanReach(?User $actor): void
    {
        if ($actor === null || $actor->hasUnrestrictedBusinessEntityAccess()) {
            return;
        }

        if (! $this->isAccessibleBy($actor)) {
            throw new AssetTransferException('Badan usaha BA ini berada di luar akses badan usaha Anda.');
        }

        foreach ($this->details as $detail) {
            if ($detail->asset && ! $detail->asset->isAccessibleBy($actor)) {
                throw new AssetTransferException(sprintf(
                    'Aset "%s" berada di luar badan usaha yang bisa Anda akses.',
                    $detail->asset->name,
                ));
            }
        }
    }

    protected function ensureAssetsCanMove(AssetTransferDocumentType $documentType, ?AssetRequest $sourceAssetRequest = null): void
    {
        if ($this->details->isEmpty()) {
            throw new AssetTransferException('BA transfer wajib memiliki minimal satu aset.');
        }

        $assetIds = $this->details
            ->pluck('asset_id')
            ->filter()
            ->map(fn ($assetId): int => (int) $assetId)
            ->values();

        if ($assetIds->count() !== $assetIds->unique()->count()) {
            throw new AssetTransferException('Aset dalam BA transfer tidak boleh duplikat.');
        }

        foreach ($this->details as $detail) {
            $asset = $detail->asset;

            if (! $detail->asset_id || ! $asset) {
                throw new AssetTransferException('Detail BA transfer memuat aset yang tidak ditemukan.');
            }

            if ($this->assetAlreadyReflectsTransfer($asset, $documentType)) {
                continue;
            }

            if (! ($asset->condition_status instanceof AssetCondition) || ! $asset->condition_status->isTransferable()) {
                throw new AssetTransferException(sprintf(
                    'Aset "%s" tidak bisa ditransfer karena statusnya %s.',
                    $asset->name,
                    $asset->condition_status_label,
                ));
            }

            if ($asset->hasOpenAssetRequestLock($sourceAssetRequest?->id)) {
                throw new AssetTransferException(sprintf(
                    'Aset "%s" sedang dalam pengajuan aktif dan belum bisa ditransfer.',
                    $asset->name,
                ));
            }

            if ($documentType->dispatchesFromStock()) {
                $this->ensureAssetIsInStock($asset);

                continue;
            }

            if ((int) $asset->recipient_id !== (int) $this->from_user_id) {
                throw new AssetTransferException(sprintf(
                    'Aset "%s" bukan milik pemberi transfer.',
                    $asset->name,
                ));
            }
        }
    }

    protected function assetAlreadyReflectsTransfer(Asset $asset, AssetTransferDocumentType $documentType): bool
    {
        $expectedRecipientId = $documentType->returnsToStock() ? 0 : (int) $this->to_user_id;

        return (int) ($asset->recipient_id ?? 0) === $expectedRecipientId
            && (int) ($asset->recipient_business_entity_id ?? 0) === (int) ($this->business_entity_id ?? 0)
            && $asset->condition_status === $this->conditionAfterTransfer($documentType);
    }

    protected function conditionAfterTransfer(AssetTransferDocumentType $documentType): AssetCondition
    {
        return $documentType->returnsToStock()
            ? AssetCondition::Available
            : AssetCondition::Transferred;
    }

    /**
     * Stok = Tersedia dan tanpa pemegang.
     */
    protected function ensureAssetIsInStock(Asset $asset): void
    {
        if ($asset->condition_status !== AssetCondition::Available) {
            throw new AssetTransferException(sprintf(
                'Aset "%s" harus berstatus Tersedia sebelum diserahterimakan dari stok.',
                $asset->name,
            ));
        }

        if ($asset->recipient_id) {
            $asset->loadMissing('recipient');

            throw new AssetTransferException(sprintf(
                'Aset "%s" masih tercatat dipegang %s. Buat BA Pengembalian lebih dulu agar aset kembali ke stok.',
                $asset->name,
                $asset->recipient?->name ?? 'pengguna lain',
            ));
        }
    }
}
