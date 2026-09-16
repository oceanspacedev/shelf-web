<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\AssetTransferDocumentType;
use App\Enums\RequestStatus;
use App\Services\AssetNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AssetRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference_number',
        'public_token',
        'type',
        'user_id',
        'division_id',
        'asset_location_id',
        'asset_id',
        'item_name',
        'qty',
        'description',
        'attachment',
        'status',
        'notes',
        'current_level',
        'fulfilled_at',
        'fulfilled_by_user_id',
        'asset_transfer_id',
    ];

    protected $casts = [
        'status' => RequestStatus::class,
        'type' => AssetRequestType::class,
        'attachment' => 'array',
        'fulfilled_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'current_level' => 1,
        'type' => 'pengadaan',
    ];

    protected static function booted()
    {
        static::creating(function ($assetRequest) {
            if (! $assetRequest->reference_number) {
                $assetRequest->reference_number = self::generateReferenceNumber();
            }

            if (! $assetRequest->public_token) {
                $assetRequest->public_token = self::generatePublicToken();
            }

            if (in_array($assetRequest->type, [AssetRequestType::Penarikan, AssetRequestType::Perbaikan], true) && $assetRequest->asset_id) {
                if (! $assetRequest->item_name) {
                    $assetRequest->item_name = $assetRequest->asset?->name ?? 'Aset Terpilih';
                }
                if (! $assetRequest->qty) {
                    $assetRequest->qty = 1;
                }
            }
        });

        static::created(function ($assetRequest) {
            $assetRequest->createLegacyItemIfNeeded();
            $assetRequest->rebuildApprovalChain();
        });
    }

    public function isMaterialScopeEditable(): bool
    {
        return $this->status === RequestStatus::Pending && ! $this->isFulfilled();
    }

    public function materialScopeFingerprint(): string
    {
        $this->loadMissing('items');

        $items = $this->items
            ->map(fn (AssetRequestItem $item): array => [
                'asset_id' => $item->asset_id,
                'item_name' => $item->item_name,
                'qty' => (int) $item->qty,
            ])
            ->values()
            ->all();

        $type = $this->type instanceof AssetRequestType ? $this->type->value : $this->type;

        return hash('sha256', json_encode([
            'user_id' => $this->user_id,
            'division_id' => $this->division_id,
            'type' => $type,
            'asset_location_id' => $this->asset_location_id,
            'description' => $this->description,
            'attachment' => $this->attachment,
            'items' => $items,
        ], JSON_THROW_ON_ERROR));
    }

    public function resetAndRebuildApprovalsForMaterialChange(bool $notify = true): void
    {
        if (! $this->isMaterialScopeEditable()) {
            return;
        }

        $this->approvals()->delete();

        $this->forceFill([
            'status' => RequestStatus::Pending,
            'current_level' => 1,
        ])->saveQuietly();

        $this->rebuildApprovalChain($notify);
    }

    public function rebuildApprovalChain(bool $notify = true): void
    {
        $this->loadMissing(['asset', 'items.asset', 'user', 'division']);

        // Ambil approver divisi dan lewati pemohon sendiri (self-approval dilarang,
        // lihat approveCurrentLevel()). Divisi null / tanpa approver / hanya berisi
        // pemohon sendiri -> tidak ada approver yang valid -> auto-approve.
        $approvers = $this->division_id
            ? DivisionApprover::where('division_id', $this->division_id)
                ->orderBy('level', 'asc')
                ->get()
            : collect();

        $approvers = $approvers
            ->filter(fn ($approver) => $approver->user_id !== $this->user_id)
            ->values();

        // Divisi tanpa approver valid -> auto-approve. Persetujuan hanya membuka jalan
        // tindak lanjut; pembuatan aset / BA pengembalian / BA perbaikan tetap
        // dilakukan operator secara terpisah (alur dinamis, tidak auto).
        if ($approvers->isEmpty()) {
            $this->forceFill([
                'status' => RequestStatus::Approved,
                'notes' => 'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
            ])->saveQuietly();

            if ($notify && $this->user) {
                $assetRequest = $this;
                DB::afterCommit(function () use ($assetRequest) {
                    AssetNotificationService::dispatch(
                        $assetRequest->user,
                        'Pengajuan aset disetujui - '.$assetRequest->reference_number,
                        $assetRequest->formatRequesterApprovedNotificationMessage()
                    );
                });
            }

            return;
        }

        // Renumber level menjadi 1..N berurutan agar current_level selalu menunjuk
        // approver pertama yang valid (mendukung data division_approvers yang dibuat
        // di luar UI Filament dengan level tidak mulai dari 1).
        $level = 1;
        foreach ($approvers as $approver) {
            AssetRequestApproval::create([
                'asset_request_id' => $this->id,
                'user_id' => $approver->user_id,
                'level' => $level,
                'status' => 'pending',
            ]);
            $level++;
        }

        $this->forceFill(['current_level' => 1, 'status' => RequestStatus::Pending])->saveQuietly();

        if (! $notify) {
            return;
        }

        $firstApprover = $approvers->first();
        if ($firstApprover && $firstApprover->user) {
            $firstApproval = $this->approvals()
                ->where('level', 1)
                ->first();
            $approverSubject = 'Persetujuan pengajuan aset - '.$this->reference_number;
            $approverMessage = $this->formatApproverActionNotificationMessage(
                $firstApprover->user,
                $firstApproval?->publicApprovalUrl() ?? $this->publicProgressUrl(),
            );
            $approverUser = $firstApprover->user;
            DB::afterCommit(fn () => AssetNotificationService::dispatch($approverUser, $approverSubject, $approverMessage));
        }

        if ($this->user) {
            $requesterSubject = 'Pengajuan aset diterima - '.$this->reference_number;
            $requesterMessage = $this->formatRequesterCreatedNotificationMessage($firstApprover?->user);
            $requester = $this->user;
            DB::afterCommit(fn () => AssetNotificationService::dispatch($requester, $requesterSubject, $requesterMessage));
        }
    }

    public static function generateReferenceNumber(): string
    {
        return DB::transaction(function () {
            $year = date('Y');
            $prefix = "REQ-{$year}-";

            // Kunci baris tahun berjalan supaya dua submission publik
            // konkuren tidak membaca sequence sama (race -> duplikat nomor).
            // Sequence dihitung numerik (bukan string sort) agar 1000 > 999.
            $references = self::withTrashed()
                ->where('reference_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->pluck('reference_number');

            $lastNumber = $references
                ->map(fn (string $reference): int => (int) Str::afterLast($reference, '-'))
                ->max() ?? 0;

            $newNumber = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);

            return $prefix.$newNumber;
        });
    }

    public static function generatePublicToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::withTrashed()->where('public_token', $token)->exists());

        return $token;
    }

    public function ensurePublicToken(): string
    {
        if (blank($this->public_token)) {
            $this->public_token = self::generatePublicToken();

            if ($this->exists) {
                $this->saveQuietly();
            }
        }

        return (string) $this->public_token;
    }

    public function publicProgressUrl(): string
    {
        return route('public.asset-requests.show', $this->ensurePublicToken());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assetLocation(): BelongsTo
    {
        return $this->belongsTo(AssetLocation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssetRequestItem::class)->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(AssetRequestApproval::class)->orderBy('level', 'asc');
    }

    public function currentPendingApproval(): ?AssetRequestApproval
    {
        return $this->approvals()
            ->with('user.jobTitle')
            ->where('level', $this->current_level)
            ->where('status', RequestStatus::Pending->value)
            ->first();
    }

    /**
     * @return array{recipient: User, subject: string, message: array{whatsapp: string, email: array<string, mixed>}, approval: AssetRequestApproval}
     */
    public function buildCurrentApprovalReminderNotification(): array
    {
        $this->loadMissing(['asset', 'items.asset', 'user', 'division']);

        $currentApproval = $this->currentPendingApproval();

        if (! $currentApproval || ! $currentApproval->user) {
            throw new \RuntimeException('Tidak ada approval yang sedang menunggu keputusan.');
        }

        $message = $this->formatApproverReminderNotificationMessage(
            $currentApproval->user,
            $currentApproval->publicApprovalUrl(),
        );

        return [
            'recipient' => $currentApproval->user,
            'subject' => 'Pengingat persetujuan aset - '.$this->reference_number,
            'message' => $message,
            'approval' => $currentApproval,
        ];
    }

    /**
     * @return array{recipient: User, subject: string, message: array{whatsapp: string, email: array<string, mixed>}}
     */
    public function buildRequesterProgressNotification(): array
    {
        $this->loadMissing(['asset', 'items.asset', 'user', 'division']);

        if (! $this->user) {
            throw new \RuntimeException('Pengajuan ini tidak memiliki data pemohon.');
        }

        $message = $this->formatRequesterProgressNotificationMessage();

        return [
            'recipient' => $this->user,
            'subject' => 'Status pengajuan aset - '.$this->reference_number,
            'message' => $message,
        ];
    }

    /**
     * @return array{recipient: User, subject: string, message: array{whatsapp: string, email: array<string, mixed>}, approval: AssetRequestApproval}
     */
    public function sendCurrentApprovalReminder(): array
    {
        $notification = $this->buildCurrentApprovalReminderNotification();

        AssetNotificationService::dispatch(
            $notification['recipient'],
            $notification['subject'],
            $notification['message'],
        );

        return $notification;
    }

    /**
     * @return array{recipient: User, subject: string, message: array{whatsapp: string, email: array<string, mixed>}}
     */
    public function sendRequesterProgressReminder(): array
    {
        $notification = $this->buildRequesterProgressNotification();

        AssetNotificationService::dispatch(
            $notification['recipient'],
            $notification['subject'],
            $notification['message'],
        );

        return $notification;
    }

    /**
     * Aset-aset yang dibuat dari pengajuan ini (tindak lanjut operator,
     * terutama untuk type=pengadaan). Bisa kosong bila aset dibuat manual
     * tanpa pengajuan atau tindak lanjut belum dilakukan.
     */
    public function createdAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_request_id');
    }

    /**
     * Operator yang menyelesaikan tindak lanjut (fulfillment).
     */
    public function fulfilledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by_user_id');
    }

    /**
     * AssetTransfer yang dibuat dari tindak lanjut (mis. penarikan -> BA pengembalian).
     */
    public function assetTransfer(): BelongsTo
    {
        return $this->belongsTo(AssetTransfer::class, 'asset_transfer_id');
    }

    /**
     * Apakah tindak lanjut operator sudah dilakukan pada pengajuan Approved ini.
     */
    public function getIsFulfilledAttribute(): bool
    {
        return $this->fulfilled_at !== null;
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }

    public function createLegacyItemIfNeeded(): void
    {
        if (! Schema::hasTable('asset_request_items') || $this->items()->exists()) {
            return;
        }

        if (! $this->asset_id && blank($this->item_name)) {
            return;
        }

        $this->items()->create([
            'asset_id' => $this->asset_id,
            'item_name' => $this->item_name ?: ($this->asset?->name ?? null),
            'qty' => $this->qty ?: 1,
        ]);

        $this->unsetRelation('items');
    }

    /**
     * Replace all request items and mirror the first row onto legacy columns.
     *
     * @param  array<int, array{asset_id?: mixed, item_name?: mixed, qty?: mixed}>  $items
     */
    public function syncItemsFromArray(array $items): void
    {
        if (! Schema::hasTable('asset_request_items')) {
            return;
        }

        $normalized = collect($items)
            ->map(function (array $item): array {
                return [
                    'asset_id' => $item['asset_id'] ?? null,
                    'item_name' => $item['item_name'] ?? null,
                    'qty' => max(1, (int) ($item['qty'] ?? 1)),
                ];
            })
            ->values()
            ->all();

        $first = $normalized[0] ?? [
            'asset_id' => null,
            'item_name' => null,
            'qty' => 1,
        ];

        $this->forceFill([
            'asset_id' => $first['asset_id'],
            'item_name' => $first['item_name'],
            'qty' => $first['qty'],
        ])->save();

        $this->items()->delete();

        foreach ($normalized as $itemRow) {
            $this->items()->create($itemRow);
        }

        $this->unsetRelation('items');
    }

    public function nextUnfulfilledPengadaanItem(): ?AssetRequestItem
    {
        if (! Schema::hasTable('asset_request_items')) {
            return null;
        }

        $this->createLegacyItemIfNeeded();

        return $this->items()
            ->whereNull('fulfilled_asset_id')
            ->orderBy('id')
            ->first();
    }

    public function hasUnfulfilledPengadaanItems(): bool
    {
        if (! Schema::hasTable('asset_request_items')) {
            return false;
        }

        $this->createLegacyItemIfNeeded();

        return $this->items()
            ->whereNull('fulfilled_asset_id')
            ->exists();
    }

    /**
     * @return array<int, int>
     */
    public function requestedAssetIds(): array
    {
        $this->load('items');

        $ids = $this->items
            ->pluck('asset_id')
            ->filter()
            ->map(fn ($assetId): int => (int) $assetId)
            ->values();

        if ($ids->isEmpty() && $this->asset_id) {
            $ids->push((int) $this->asset_id);
        }

        return $ids->unique()->values()->all();
    }

    public function requestedAssets()
    {
        $assetIds = $this->requestedAssetIds();

        if ($assetIds === []) {
            return collect();
        }

        return Asset::query()
            ->whereIn('id', $assetIds)
            ->get()
            ->sortBy(fn (Asset $asset): int => array_search($asset->id, $assetIds, true))
            ->values();
    }

    public function itemQuantityTotal(): int
    {
        $this->load('items');

        if ($this->items->isNotEmpty()) {
            return max(1, (int) $this->items->sum(fn (AssetRequestItem $item): int => max(1, (int) $item->qty)));
        }

        return max(1, (int) ($this->qty ?: 1));
    }

    public function itemSummaryLabel(): string
    {
        $this->load('items.asset');

        $names = $this->items
            ->map(fn (AssetRequestItem $item): ?string => $item->asset?->name ?? $item->item_name)
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            $fallback = $this->asset?->name ?? $this->item_name;

            return filled($fallback) ? (string) $fallback : '-';
        }

        if ($names->count() === 1) {
            return (string) $names->first();
        }

        $prefix = $this->type === AssetRequestType::Pengadaan ? 'item' : 'aset';

        return $names->count().' '.$prefix.': '.$names->take(3)->implode(', ').($names->count() > 3 ? ', ...' : '');
    }

    public function lifecycleStageLabel(): string
    {
        if ($this->status === RequestStatus::Rejected) {
            return 'Ditolak';
        }

        if ($this->status === RequestStatus::Pending) {
            return 'Menunggu Persetujuan';
        }

        if (! $this->isFulfilled()) {
            return match ($this->type) {
                AssetRequestType::Pengadaan => 'Disetujui - Perlu Buat Aset',
                AssetRequestType::Penarikan => 'Disetujui - Perlu BA Pengembalian',
                AssetRequestType::Perbaikan => 'Disetujui - Perlu Tandai Perbaikan',
            };
        }

        return match ($this->type) {
            AssetRequestType::Pengadaan => 'Selesai - Aset Dibuat',
            AssetRequestType::Penarikan => 'Selesai - Aset Ditarik',
            AssetRequestType::Perbaikan => 'Selesai - Masuk Proses Perbaikan',
        };
    }

    public function lifecycleStageColor(): string
    {
        if ($this->status === RequestStatus::Rejected) {
            return 'danger';
        }

        if ($this->status === RequestStatus::Pending) {
            return 'warning';
        }

        return $this->isFulfilled() ? 'success' : 'info';
    }

    public function nextStepLabel(): string
    {
        if ($this->status === RequestStatus::Rejected) {
            return 'Tidak ada tindak lanjut';
        }

        if ($this->status === RequestStatus::Pending) {
            $pendingApprover = $this->currentPendingApproval()?->user;

            if ($pendingApprover) {
                return 'Menunggu persetujuan dari '.$pendingApprover->nameWithJobTitle();
            }

            return 'Menunggu persetujuan';
        }

        if (! $this->isFulfilled()) {
            return match ($this->type) {
                AssetRequestType::Pengadaan => 'Operator membuat data aset dari pengajuan ini',
                AssetRequestType::Penarikan => 'Operator membuat BA pengembalian ke General Affairs',
                AssetRequestType::Perbaikan => 'Operator menandai aset rusak dan membuka proses NBH',
            };
        }

        return match ($this->type) {
            AssetRequestType::Pengadaan => 'Aset sudah terhubung ke pengajuan, BA Pengadaan bisa diunduh',
            AssetRequestType::Penarikan => 'BA pengembalian sudah terhubung dan aset kembali ke General Affairs',
            AssetRequestType::Perbaikan => 'Aset sudah masuk status Rusak, lanjutkan penyelesaian perbaikan dari halaman aset',
        };
    }

    public function getLifecycleStageLabelAttribute(): string
    {
        return $this->lifecycleStageLabel();
    }

    public function getNextStepLabelAttribute(): string
    {
        return $this->nextStepLabel();
    }

    /**
     * Tindak lanjut pengadaan: operator membuat Asset dari pengajuan.
     * Asset dibuat satu row (qty sesuai pengajuan, konsisten dengan AssetImport).
     *
     * @param  array<string, mixed>  $assetAttributes  atribut Asset dari form operator.
     */
    public function fulfillPengadaan(array $assetAttributes, User $actor): Asset
    {
        $this->ensureFulfillable(AssetRequestType::Pengadaan);

        return DB::transaction(function () use ($assetAttributes, $actor) {
            $this->refresh();
            $this->ensureFulfillable(AssetRequestType::Pengadaan);

            $item = $this->nextUnfulfilledPengadaanItem();

            $attributeRows = is_array($assetAttributes['attributes'] ?? null)
                ? $assetAttributes['attributes']
                : [];

            unset($assetAttributes['attributes']);

            $asset = Asset::create(array_merge([
                'name' => $item?->item_name ?? $this->item_name,
                'qty' => $item?->qty ?? $this->qty ?? 1,
                'condition_status' => AssetCondition::Available,
                'asset_request_id' => $this->id,
                'purchase_date' => now()->toDateString(),
            ], $assetAttributes));

            foreach (self::normalizeAssetAttributeRows($attributeRows) as $attributeRow) {
                $asset->attributes()->create($attributeRow);
            }

            $this->markPengadaanItemFulfilledByAsset($asset);

            if (! $this->hasUnfulfilledPengadaanItems()) {
                $this->markFulfilled($actor);
            }

            return $asset->fresh('attributes');
        });
    }

    public function markFulfilledByAsset(Asset $asset, User $actor, ?int $assetRequestItemId = null): void
    {
        $this->ensureFulfillable(AssetRequestType::Pengadaan);

        if ((int) $asset->asset_request_id !== (int) $this->id) {
            throw new \InvalidArgumentException('Aset tidak terhubung ke pengajuan ini.');
        }

        DB::transaction(function () use ($asset, $actor, $assetRequestItemId): void {
            $this->refresh();
            $this->ensureFulfillable(AssetRequestType::Pengadaan);
            $this->markPengadaanItemFulfilledByAsset($asset, $assetRequestItemId);

            if (! $this->hasUnfulfilledPengadaanItems()) {
                $this->markFulfilled($actor);
            }
        });
    }

    protected function markPengadaanItemFulfilledByAsset(Asset $asset, ?int $assetRequestItemId = null): void
    {
        $item = null;

        if ($assetRequestItemId) {
            $item = $this->items()
                ->whereKey($assetRequestItemId)
                ->whereNull('fulfilled_asset_id')
                ->first();

            if (! $item) {
                throw new \InvalidArgumentException('Item pengadaan tidak ditemukan atau sudah dipenuhi.');
            }
        } else {
            $item = $this->nextUnfulfilledPengadaanItem();
        }

        if (! $item) {
            return;
        }

        $item->update([
            'fulfilled_asset_id' => $asset->id,
            'fulfilled_at' => now(),
        ]);

        $this->unsetRelation('items');
    }

    public function markFulfilledByAssetTransfer(AssetTransfer $assetTransfer, User $actor): void
    {
        $this->ensureFulfillable(AssetRequestType::Penarikan);

        $assetIds = $this->requestedAssetIds();

        if ($assetIds === []) {
            throw new \InvalidArgumentException('Pengajuan penarikan tidak memiliki aset terkait.');
        }

        $assets = Asset::query()
            ->whereIn('id', $assetIds)
            ->get()
            ->keyBy('id');
        $expectedFromUserId = $assets->first()?->recipient_id ?? $this->user_id;

        if ($expectedFromUserId && (int) $assetTransfer->from_user_id !== (int) $expectedFromUserId) {
            throw new \InvalidArgumentException('Transfer pengembalian harus berasal dari pemegang aset pengajuan ini.');
        }

        $transferAssetIds = $assetTransfer->details()
            ->pluck('asset_id')
            ->map(fn ($assetId): int => (int) $assetId)
            ->all();

        if (array_values(array_diff($assetIds, $transferAssetIds)) !== []) {
            throw new \InvalidArgumentException('Transfer pengembalian harus memuat semua aset dari pengajuan ini.');
        }

        if ($assetTransfer->documentType() !== AssetTransferDocumentType::PengembalianBarang) {
            throw new \InvalidArgumentException('Transfer penarikan harus berupa BAPEB ke General Affairs.');
        }

        $this->markFulfilled($actor, $assetTransfer->id);
    }

    /**
     * @param  array<int, mixed>  $attributeRows
     * @return array<int, array{custom_attribute_id: int, attribute_value: mixed}>
     */
    protected static function normalizeAssetAttributeRows(array $attributeRows): array
    {
        return collect($attributeRows)
            ->filter(fn ($row): bool => is_array($row))
            ->map(function (array $row): ?array {
                $customAttributeId = filter_var($row['custom_attribute_id'] ?? null, FILTER_VALIDATE_INT);

                if (! $customAttributeId) {
                    return null;
                }

                $customAttribute = CustomAssetAttribute::find($customAttributeId);

                if (! $customAttribute) {
                    return null;
                }

                if ($customAttribute->type === CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY) {
                    $documentPath = $row['document_file_path'] ?? null;

                    if (is_array($documentPath)) {
                        $documentPath = reset($documentPath) ?: null;
                    }

                    return [
                        'custom_attribute_id' => $customAttribute->id,
                        'attribute_value' => AssetAttribute::documentValue([
                            'expires_at' => $row['document_expires_at'] ?? null,
                            'document_number' => $row['document_number'] ?? null,
                            'document_path' => $documentPath,
                            'notes' => $row['document_notes'] ?? null,
                        ]),
                    ];
                }

                $value = $row['attribute_value'] ?? null;

                return [
                    'custom_attribute_id' => $customAttribute->id,
                    'attribute_value' => is_array($value) ? null : $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Tindak lanjut perbaikan: tandai aset terkait Rusak (Damaged) + NBH Pending.
     */
    public function fulfillPerbaikan(User $actor): Asset
    {
        $this->ensureFulfillable(AssetRequestType::Perbaikan);

        $assets = $this->requestedAssets();

        if ($assets->isEmpty()) {
            throw new \RuntimeException('Pengajuan perbaikan tidak memiliki aset terkait.');
        }

        return DB::transaction(function () use ($assets, $actor) {
            foreach ($assets as $asset) {
                $asset->condition_status = AssetCondition::Damaged;
                // Mutator setConditionStatusAttribute otomatis men-set nbh_status=Pending
                // untuk kondisi incident (Damaged) bila belum diset.
                $asset->save();
            }

            $this->markFulfilled($actor);

            return $assets->first()->fresh();
        });
    }

    /**
     * Tindak lanjut penarikan: buat AssetTransfer (BA pengembalian) ke user General Affairs
     * + AssetTransferDetail untuk aset terkait, lalu mutasi aset (recipient & condition).
     *
     * @param  User  $gaUser  penerima GA (wajib punya role general_affair).
     * @param  ?string  $letterNumber  nomor BA; bila null akan di-generate dari business entity.
     */
    public function fulfillPenarikan(User $gaUser, User $actor, ?string $letterNumber = null): AssetTransfer
    {
        $this->ensureFulfillable(AssetRequestType::Penarikan);

        $assets = $this->requestedAssets();

        if ($assets->isEmpty()) {
            throw new \RuntimeException('Pengajuan penarikan tidak memiliki aset terkait.');
        }
        if (! $gaUser->hasRole('general_affair')) {
            throw new AuthorizationException('Penerima pengembalian harus user General Affairs.');
        }

        return DB::transaction(function () use ($assets, $gaUser, $actor, $letterNumber) {
            $firstAsset = $assets->first();
            $fromUserId = $firstAsset->recipient_id ?? $this->user_id;

            $assets->each(function (Asset $asset) use ($fromUserId): void {
                if ((int) ($asset->recipient_id ?? $this->user_id) !== (int) $fromUserId) {
                    throw new \InvalidArgumentException('Semua aset dalam pengajuan penarikan harus berasal dari pemegang yang sama.');
                }
            });

            $businessEntityId = $firstAsset->business_entity_id
                ?? $firstAsset->recipient_business_entity_id
                ?? $this->user?->business_entity_id
                ?? null;

            $transfer = AssetTransfer::create([
                'business_entity_id' => $businessEntityId,
                'letter_number' => $letterNumber ?? AssetTransfer::generateLetterNumber(
                    $businessEntityId ? BusinessEntity::find($businessEntityId) : null
                ),
                'from_user_id' => $fromUserId,
                'to_user_id' => $gaUser->id,
                'transfer_date' => now()->toDateString(),
                'document' => null,
            ]);

            foreach ($assets as $asset) {
                AssetTransferDetail::create([
                    'asset_transfer_id' => $transfer->id,
                    'asset_id' => $asset->id,
                    'equipment' => null,
                ]);
            }

            $transfer->applyLifecycleToAssets($this);

            $this->markFulfilled($actor, $transfer->id);

            return $transfer->fresh();
        });
    }

    /**
     * Guard: pengajuan harus Approved, belum fulfilled, dan bertipe sesuai.
     */
    protected function ensureFulfillable(AssetRequestType $expectedType): void
    {
        if ($this->status !== RequestStatus::Approved) {
            throw new AuthorizationException(
                'Tindak lanjut hanya untuk pengajuan yang sudah disetujui.'
            );
        }
        if ($this->isFulfilled()) {
            throw new \RuntimeException('Tindak lanjut pengajuan ini sudah dilakukan.');
        }
        if ($this->type !== $expectedType) {
            throw new \InvalidArgumentException(
                'Jenis pengajuan tidak sesuai untuk tindak lanjut ini.'
            );
        }
    }

    protected function markFulfilled(User $actor, ?int $assetTransferId = null): void
    {
        $this->update([
            'fulfilled_at' => now(),
            'fulfilled_by_user_id' => $actor->id,
            'asset_transfer_id' => $assetTransferId ?? $this->asset_transfer_id,
        ]);
    }

    public function approveCurrentLevel(?string $notes = null, ?User $actor = null): void
    {
        $actor = $actor ?? auth()->user();

        $notifications = DB::transaction(function () use ($actor, $notes) {
            // Kunci baris request untuk mencegah race antar approver / double-submit.
            self::whereKey($this->id)->lockForUpdate()->first();
            $this->refresh();

            if ($this->status !== RequestStatus::Pending) {
                throw new AuthorizationException(
                    'Pengajuan ini tidak dapat disetujui (status: '.$this->status->label().').'
                );
            }

            $currentApproval = $this->approvals()
                ->where('level', $this->current_level)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $currentApproval) {
                throw new AuthorizationException(
                    'Tidak ada approval yang sedang menunggu keputusan.'
                );
            }
            if ($actor === null) {
                throw new AuthorizationException('Pemberi persetujuan tidak teridentifikasi.');
            }
            if ($currentApproval->user_id !== $actor->id) {
                throw new AuthorizationException('Anda bukan approver untuk persetujuan ini.');
            }
            if ($this->user_id === $actor->id) {
                throw new AuthorizationException('Pemohon tidak boleh menyetujui pengajuan sendiri.');
            }

            $currentApproval->update([
                'status' => 'approved',
                'notes' => $notes,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            $nextApproval = $this->approvals()
                ->where('level', '>', $this->current_level)
                ->where('status', 'pending')
                ->orderBy('level', 'asc')
                ->first();

            $approverLabel = $this->notificationPersonLabel($actor, 'Approver');
            $notifications = [];

            if ($nextApproval) {
                $this->update([
                    'current_level' => $nextApproval->level,
                ]);

                if ($nextApproval->user) {
                    $notifications[] = [
                        $nextApproval->user,
                        'Persetujuan pengajuan aset - '.$this->reference_number,
                        $this->formatApproverActionNotificationMessage(
                            $nextApproval->user,
                            $nextApproval->publicApprovalUrl(),
                        ),
                    ];
                }

                if ($this->user) {
                    $notifications[] = [
                        $this->user,
                        'Pengajuan aset diperbarui - '.$this->reference_number,
                        $this->formatRequesterAdvancedNotificationMessage(
                            $approverLabel,
                            $this->notificationPersonLabel($nextApproval->user),
                        ),
                    ];
                }
            } else {
                // Persetujuan final: status Approved. Tindak lanjut (pembuatan aset /
                // BA pengembalian / BA perbaikan) tetap dilakukan operator terpisah.
                $this->update([
                    'status' => RequestStatus::Approved,
                ]);

                if ($this->user) {
                    $notifications[] = [
                        $this->user,
                        'Pengajuan aset disetujui - '.$this->reference_number,
                        $this->formatRequesterApprovedNotificationMessage(),
                    ];
                }
            }

            return $notifications;
        });

        // Notifikasi dikirim setelah commit supaya kegagalan pengiriman tidak
        // merusak state approval, dan approval tidak diblokir HTTP WhatsApp/Email.
        foreach ($notifications as [$notifiable, $subject, $message]) {
            AssetNotificationService::dispatch($notifiable, $subject, $message);
        }
    }

    public function rejectCurrentLevel(string $notes, ?User $actor = null): void
    {
        $actor = $actor ?? auth()->user();

        $notifications = DB::transaction(function () use ($actor, $notes) {
            self::whereKey($this->id)->lockForUpdate()->first();
            $this->refresh();

            if ($this->status !== RequestStatus::Pending) {
                throw new AuthorizationException(
                    'Pengajuan ini tidak dapat ditolak (status: '.$this->status->label().').'
                );
            }

            $currentApproval = $this->approvals()
                ->where('level', $this->current_level)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $currentApproval) {
                throw new AuthorizationException(
                    'Tidak ada approval yang sedang menunggu keputusan.'
                );
            }
            if ($actor === null) {
                throw new AuthorizationException('Pemberi penolakan tidak teridentifikasi.');
            }
            if ($currentApproval->user_id !== $actor->id) {
                throw new AuthorizationException('Anda bukan approver untuk persetujuan ini.');
            }
            if ($this->user_id === $actor->id) {
                throw new AuthorizationException('Pemohon tidak boleh menolak pengajuan sendiri.');
            }

            $currentApproval->update([
                'status' => 'rejected',
                'notes' => $notes,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            $approverLabel = $this->notificationPersonLabel($actor, 'Approver');

            $this->update([
                'status' => RequestStatus::Rejected,
                'notes' => 'Ditolak oleh '.$approverLabel.'. Alasan: '.$notes,
            ]);

            $notifications = [];
            if ($this->user) {
                $notifications[] = [
                    $this->user,
                    'Pengajuan aset ditolak - '.$this->reference_number,
                    $this->formatRequesterRejectedNotificationMessage($approverLabel, $notes),
                ];
            }

            return $notifications;
        });

        foreach ($notifications as [$notifiable, $subject, $message]) {
            AssetNotificationService::dispatch($notifiable, $subject, $message);
        }
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatRequesterCreatedNotificationMessage(?User $approver = null): array
    {
        return $this->buildNotificationPayload(
            title: null,
            intro: 'Pengajuan aset Anda sudah masuk dan menunggu persetujuan.',
            extraFields: [
                'Status' => 'Pengajuan Baru',
                'Menunggu Persetujuan Dari' => $this->notificationPersonLabel($approver),
            ],
            ctaLabel: 'Lihat Progress',
            ctaUrl: $this->publicProgressUrl(),
            ctaVariant: 'progress',
            whatsappCtaLabel: 'Lihat progress:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatRequesterApprovedNotificationMessage(): array
    {
        return $this->buildNotificationPayload(
            title: 'Disetujui',
            intro: 'Pengajuan aset Anda disetujui.',
            extraFields: [
                'Status' => 'Disetujui',
                'Langkah Berikutnya' => $this->nextStepLabel(),
            ],
            ctaLabel: 'Lihat Progress',
            ctaUrl: $this->publicProgressUrl(),
            ctaVariant: 'progress',
            whatsappCtaLabel: 'Lihat progress:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatRequesterRejectedNotificationMessage(string $approverLabel, string $notes): array
    {
        return $this->buildNotificationPayload(
            title: 'Ditolak',
            intro: 'Pengajuan aset Anda ditolak.',
            extraFields: [
                'Status' => 'Ditolak',
                'Ditolak Oleh' => $approverLabel,
                'Alasan Penolakan' => $notes,
            ],
            ctaLabel: 'Lihat Progress',
            ctaUrl: $this->publicProgressUrl(),
            ctaVariant: 'progress',
            whatsappCtaLabel: 'Lihat progress:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatRequesterAdvancedNotificationMessage(string $approverLabel, string $nextApproverLabel): array
    {
        return $this->buildNotificationPayload(
            title: 'Tahap Berikutnya',
            intro: 'Satu tahap disetujui. Menunggu penyetuju berikutnya.',
            extraFields: [
                'Status' => 'Menunggu Persetujuan Berikutnya',
                'Disetujui Oleh' => $approverLabel,
                'Menunggu Persetujuan Dari' => $nextApproverLabel,
            ],
            ctaLabel: 'Lihat Progress',
            ctaUrl: $this->publicProgressUrl(),
            ctaVariant: 'progress',
            whatsappCtaLabel: 'Lihat progress:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatRequesterProgressNotificationMessage(): array
    {
        return $this->buildNotificationPayload(
            title: 'Status',
            intro: 'Status pengajuan aset Anda.',
            extraFields: [
                'Status' => $this->status?->label() ?? '-',
                'Tahap' => $this->lifecycleStageLabel(),
                'Langkah Berikutnya' => $this->nextStepLabel(),
            ],
            ctaLabel: 'Lihat Progress',
            ctaUrl: $this->publicProgressUrl(),
            ctaVariant: 'progress',
            whatsappCtaLabel: 'Lihat progress:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatApproverActionNotificationMessage(User $approver, string $approvalUrl): array
    {
        $approver->loadMissing('jobTitle');
        $title = $approver->jobTitle?->title;

        $intro = $title
            ? "Sebagai {$title}, setujui atau tolak pengajuan aset ini."
            : 'Setujui atau tolak pengajuan aset ini.';

        return $this->buildNotificationPayload(
            title: null,
            intro: $intro,
            extraFields: [
                'Status' => 'Pengajuan Baru',
                'Penyetuju' => $this->notificationPersonLabel($approver),
            ],
            ctaLabel: 'Setujui atau Tolak',
            ctaUrl: $approvalUrl,
            ctaVariant: 'approve',
            whatsappCtaLabel: 'Setujui atau tolak:',
        );
    }

    /**
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    public function formatApproverReminderNotificationMessage(User $approver, string $approvalUrl): array
    {
        $approver->loadMissing('jobTitle');
        $title = $approver->jobTitle?->title;

        $intro = $title
            ? "Sebagai {$title}, pengajuan aset ini masih menunggu keputusan Anda."
            : 'Pengajuan aset ini masih menunggu keputusan Anda.';

        return $this->buildNotificationPayload(
            title: 'Pengingat',
            intro: $intro,
            extraFields: [
                'Status' => 'Menunggu Persetujuan',
                'Penyetuju' => $this->notificationPersonLabel($approver),
            ],
            ctaLabel: 'Setujui atau Tolak',
            ctaUrl: $approvalUrl,
            ctaVariant: 'approve',
            whatsappCtaLabel: 'Setujui atau tolak:',
        );
    }

    /**
     * @param  array<string, string|int|null>  $extraFields
     * @return array{whatsapp: string, email: array<string, mixed>}
     */
    protected function buildNotificationPayload(
        ?string $title,
        string $intro,
        array $extraFields,
        string $ctaLabel,
        string $ctaUrl,
        string $ctaVariant,
        string $whatsappCtaLabel,
    ): array {
        $this->loadMissing([
            'user.jobTitle',
            'division',
            'asset',
            'items.asset',
            'approvals.user.jobTitle',
        ]);

        $fields = [];
        foreach (array_merge([
            'Referensi' => $this->reference_number ?: '-',
            'Waktu' => $this->created_at?->format('n/j/Y H:i:s') ?? '-',
            'Email' => $this->user?->email ?: '-',
            'Nama' => $this->user?->name ?: '-',
            'Jabatan' => $this->user?->jobTitle?->title ?: '-',
            'Divisi' => $this->division?->name ?: '-',
            'Jenis Pengajuan' => $this->type?->label() ?? '-',
            'Nama Aset' => $this->itemSummaryLabel(),
            'Jumlah' => $this->itemQuantityTotal(),
            'Keterangan' => $this->description ?: '-',
        ], $extraFields) as $label => $value) {
            $fields[$label] = $this->notificationFieldValue($value);
        }

        $header = $title
            ? "Pengajuan Aset: {$title}"
            : 'Pengajuan Aset';

        $whatsappLines = [$header, ''];
        foreach ($fields as $label => $value) {
            $whatsappLines[] = $label.': '.$value;
        }
        $whatsappLines[] = '';
        $whatsappLines[] = $whatsappCtaLabel;
        $whatsappLines[] = $ctaUrl;
        $whatsappLines[] = '';
        $whatsappLines[] = 'IT Support';

        return [
            'whatsapp' => implode("\n", $whatsappLines),
            'email' => [
                'form_title' => 'FORM PENGAJUAN ASET',
                'intro' => $intro,
                'fields' => $fields,
                'approvals' => $this->notificationApprovalRows(),
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaUrl,
                'cta_variant' => $ctaVariant,
            ],
        ];
    }

    /**
     * @return array<int, array{approver: string, title: string, status: string, comments: string, timestamp: string}>
     */
    protected function notificationApprovalRows(): array
    {
        $this->loadMissing(['approvals.user.jobTitle']);

        return $this->approvals
            ->sortBy('level')
            ->values()
            ->map(function (AssetRequestApproval $approval): array {
                $status = $approval->status instanceof RequestStatus
                    ? match ($approval->status) {
                        RequestStatus::Approved => 'Disetujui',
                        RequestStatus::Rejected => 'Ditolak',
                        RequestStatus::Pending => 'Menunggu',
                    }
                : ucfirst((string) $approval->status);

                return [
                    'approver' => $approval->user?->name ?? '-',
                    'title' => $approval->user?->jobTitle?->title ?? '-',
                    'status' => $status,
                    'comments' => $approval->notes ?: '',
                    'timestamp' => $approval->decided_at?->format('n/j/Y, g:i:s A')
                        ?? $approval->updated_at?->format('n/j/Y, g:i:s A')
                        ?? '-',
                ];
            })
            ->all();
    }

    protected function notificationPersonLabel(?User $user, string $fallback = '-'): string
    {
        return $user?->nameWithJobTitle() ?? $fallback;
    }

    protected function notificationFieldValue(string|int|null $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }
}
