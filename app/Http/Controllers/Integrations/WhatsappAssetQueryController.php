<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAttribute;
use App\Models\CustomAssetAttribute;
use App\Services\WhatsappService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WhatsappAssetQueryController extends Controller
{
    private const SUPPORTED_ROUTES = [
        'shelf.search_asset',
        'shelf.asset_history',
        'shelf.expiring_documents',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'request_id' => ['nullable', 'string', 'max:100'],
            'route' => ['required', Rule::in(self::SUPPORTED_ROUTES)],
            'intent' => ['nullable', 'string', 'max:100'],
            'sender_phone' => ['nullable', 'string', 'max:50'],
            'contract_version' => ['nullable', 'string', 'max:20'],
            'query' => ['required', 'array'],
            'query.asset_tag' => ['nullable', 'string', 'max:100'],
            'query.serial_number' => ['nullable', 'string', 'max:100'],
            'query.imei' => ['nullable', 'string', 'max:100'],
            'query.plate_number' => ['nullable', 'string', 'max:50'],
            'query.license_plate' => ['nullable', 'string', 'max:50'],
            'query.plat_nomor' => ['nullable', 'string', 'max:50'],
            'query.person_name' => ['nullable', 'string', 'max:150'],
            'query.recipient_name' => ['nullable', 'string', 'max:150'],
            'query.person_phone' => ['nullable', 'string', 'max:50'],
            'query.recipient_phone' => ['nullable', 'string', 'max:50'],
            'query.location' => ['nullable', 'string', 'max:150'],
            'query.location_name' => ['nullable', 'string', 'max:150'],
            'query.asset_type' => ['nullable', 'string', 'max:150'],
            'query.category_name' => ['nullable', 'string', 'max:150'],
            'query.brand_name' => ['nullable', 'string', 'max:150'],
            'query.status' => ['nullable', 'string', 'max:50'],
            'query.document_status' => ['nullable', 'string', 'max:50'],
            'query.document_name' => ['nullable', 'string', 'max:150'],
            'query.due_within_days' => ['nullable'],
            'query.include_expired' => ['nullable'],
            'query.notifiable_only' => ['nullable'],
            'query.limit' => ['nullable'],
            'query.keywords' => ['nullable', 'array'],
            'query.keywords.*' => ['nullable', 'string', 'max:100'],
            'query.allow_broad_search' => ['nullable'],
            'query.include_history' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return $this->envelope([
                'ok' => false,
                'result_status' => 'validation_error',
                'next_question' => 'Format request Shelf belum sesuai kontrak integrasi.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $data = $validator->validated();
        $query = $this->normalizeQuery($data['query'] ?? []);

        return match ($data['route']) {
            'shelf.expiring_documents' => $this->expiringDocuments($query),
            default => $this->assets($data['route'], $query),
        };
    }

    private function assets(string $route, array $query): JsonResponse
    {
        $includeHistory = $route === 'shelf.asset_history' || $this->bool($query['include_history'] ?? false);
        $assetTag = $this->text($query['asset_tag'] ?? '');
        $serialNumber = $this->text($query['serial_number'] ?? '');
        $imei = preg_replace('/\D+/', '', $this->text($query['imei'] ?? '')) ?: '';
        $plateNumber = $this->plateNumber($query);
        $phone = WhatsappService::normalizeNumber(
            $this->text($query['recipient_phone'] ?? '') ?: $this->text($query['person_phone'] ?? '')
        );
        $location = $this->text($query['location_name'] ?? '') ?: $this->text($query['location'] ?? '');
        $assetType = $this->text($query['asset_type'] ?? '');
        $categoryName = $this->text($query['category_name'] ?? '');

        $hasUniqueKey = ($assetTag !== '' && ctype_digit($assetTag))
            || $serialNumber !== ''
            || $imei !== ''
            || $plateNumber !== ''
            || filled($phone);
        $hasBroadScope = $this->bool($query['allow_broad_search'] ?? false)
            && $location !== ''
            && ($assetType !== '' || $categoryName !== '');

        if (! $hasUniqueKey && ! $hasBroadScope) {
            $hasNameOnly = $this->text($query['person_name'] ?? '') !== ''
                || $this->text($query['recipient_name'] ?? '') !== '';

            return $this->envelope([
                'ok' => false,
                'result_status' => 'validation_error',
                'next_question' => $hasNameOnly
                    ? 'Nama bisa sama. Kirim serial number, IMEI, plat nomor, atau nomor WhatsApp pemegang.'
                    : 'Kirim serial number, IMEI, plat nomor, nomor WhatsApp pemegang, atau lokasi + jenis aset.',
            ], 422);
        }

        $assets = $this->assetQuery($query, $includeHistory)
            ->limit(11)
            ->get();

        return $this->assetResponse($assets, $includeHistory);
    }

    private function expiringDocuments(array $query): JsonResponse
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $dueWithinDays = $this->intInRange($query['due_within_days'] ?? 0, 0, 0, 365);
        $limit = $this->intInRange($query['limit'] ?? 10, 10, 1, 10);
        $status = $this->normalizeDocumentStatus($query['document_status'] ?? $query['status'] ?? 'due');
        $includeExpired = ! $this->falseLike($query['include_expired'] ?? true);
        $notifiableOnly = ! $this->falseLike($query['notifiable_only'] ?? true);
        $documentName = $this->text($query['document_name'] ?? '');

        $attributes = AssetAttribute::query()
            ->whereNotNull('attribute_value')
            ->whereHas('customAttribute', function (Builder $custom) use ($documentName, $notifiableOnly): void {
                $custom
                    ->where('type', CustomAssetAttribute::TYPE_DOCUMENT_EXPIRY)
                    ->where('is_active', true)
                    ->when($notifiableOnly, fn (Builder $q) => $q
                        ->where('is_notifiable', true)
                        ->where('notification_type', 'relative_date'))
                    ->when($documentName !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$documentName.'%'));
            })
            ->whereHas('asset', fn (Builder $asset) => $this->applyAssetFilters($asset, $query))
            ->with([
                'customAttribute:id,name,type,is_notifiable,notification_type,notification_offset',
                'asset:id,name,category_id,brand_id,type,serial_number,imei1,imei2,asset_location_id,recipient_id,business_entity_id,condition_status,nbh_status,is_available,qty,updated_at',
                'asset.category:id,name,parent_id',
                'asset.brand:id,name',
                'asset.assetLocation:id,name,address,description',
                'asset.businessEntity:id,name,format,color',
                'asset.recipient:id,name,username,email,whatsapp_number,business_entity_id,job_title_id',
                'asset.recipient.businessEntity:id,name',
                'asset.recipient.jobTitle:id,title',
            ])
            ->get()
            ->filter(fn (AssetAttribute $attribute) => $this->matchesDocumentStatus(
                $attribute,
                $today,
                $status,
                $dueWithinDays,
                $includeExpired,
            ))
            ->sortBy(fn (AssetAttribute $attribute) => sprintf(
                '%020d:%020d',
                $attribute->expiryDate()?->timestamp ?? PHP_INT_MAX,
                $attribute->id,
            ))
            ->values();

        $items = $attributes
            ->take($limit)
            ->map(fn (AssetAttribute $attribute) => $this->serializeExpiringDocument($attribute, $today))
            ->values();

        $count = $attributes->count();

        return $this->envelope([
            'ok' => true,
            'result_status' => $this->resultStatus($count),
            'count' => $count,
            'items' => $items,
            'candidates' => [],
            'next_question' => $count > 1 ? 'Pilih asset_id atau nama dokumen untuk melihat detail yang lebih spesifik.' : '',
            'freshness' => [
                'newest_updated_at' => $this->newestUpdatedAt($items),
            ],
        ]);
    }

    private function assetQuery(array $query, bool $includeHistory): Builder
    {
        $assetTag = $this->text($query['asset_tag'] ?? '');
        $serialNumber = $this->text($query['serial_number'] ?? '');
        $imei = preg_replace('/\D+/', '', $this->text($query['imei'] ?? '')) ?: '';
        $plateNumber = $this->plateNumber($query);
        $phone = WhatsappService::normalizeNumber(
            $this->text($query['recipient_phone'] ?? '') ?: $this->text($query['person_phone'] ?? '')
        );
        $status = strtolower($this->text($query['status'] ?? ''));

        $assetQuery = Asset::query()
            ->with([
                'category:id,name,parent_id',
                'brand:id,name',
                'assetLocation:id,name,address,description',
                'businessEntity:id,name,format,color',
                'recipient:id,name,username,email,whatsapp_number,business_entity_id,job_title_id',
                'recipient.businessEntity:id,name',
                'recipient.jobTitle:id,title',
                'recipientBusinessEntity:id,name',
                'attributes:id,asset_id,custom_attribute_id,attribute_value',
                'attributes.customAttribute:id,name,is_active',
                'assetTransferDetails.assetTransfer.businessEntity:id,name',
                'assetTransferDetails.assetTransfer.fromUser:id,name,whatsapp_number,business_entity_id',
                'assetTransferDetails.assetTransfer.toUser:id,name,whatsapp_number,business_entity_id',
            ]);

        if ($assetTag !== '' && ctype_digit($assetTag)) {
            $assetQuery->whereKey((int) $assetTag);
        }

        if ($serialNumber !== '') {
            $this->whereIdentifierMatches($assetQuery, ['serial_number'], ['Serial Number'], $serialNumber);
        }

        if ($imei !== '') {
            $this->whereIdentifierMatches($assetQuery, ['imei1', 'imei2'], ['IMEI1', 'IMEI2'], $imei);
        }

        if ($plateNumber !== '') {
            $this->whereIdentifierMatches($assetQuery, [], ['Plat Nomor'], $plateNumber);
        }

        if (filled($phone)) {
            $phoneOptions = $this->phoneOptions($phone);

            $assetQuery->whereHas('recipient', function (Builder $recipient) use ($phoneOptions): void {
                $recipient->where(function (Builder $q) use ($phoneOptions): void {
                    foreach ($phoneOptions as $phoneOption) {
                        $q->orWhere('whatsapp_number', $phoneOption);
                    }
                });
            });
        }

        $this->applyAssetFilters($assetQuery, $query);

        $assetStatuses = array_map(fn (AssetCondition $condition) => $condition->value, AssetCondition::cases());

        if (in_array($status, $assetStatuses, true)) {
            $assetQuery->where('condition_status', $status);
        }

        $keywords = collect($query['keywords'] ?? [])
            ->map(fn ($keyword) => $this->text($keyword))
            ->filter()
            ->take(5)
            ->values();

        if ($keywords->isNotEmpty()) {
            $assetQuery->where(function (Builder $q) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $q->orWhere('name', 'like', '%'.$keyword.'%')
                        ->orWhere('type', 'like', '%'.$keyword.'%')
                        ->orWhere('serial_number', 'like', '%'.$keyword.'%');
                }
            });
        }

        return $assetQuery;
    }

    private function applyAssetFilters(Builder $query, array $filters): void
    {
        $location = $this->text($filters['location_name'] ?? '') ?: $this->text($filters['location'] ?? '');
        $assetType = $this->text($filters['asset_type'] ?? '');
        $categoryName = $this->text($filters['category_name'] ?? '');
        $brandName = $this->text($filters['brand_name'] ?? '');

        if ($location !== '') {
            $query->whereHas('assetLocation', function (Builder $assetLocation) use ($location): void {
                $assetLocation
                    ->where('name', 'like', '%'.$location.'%')
                    ->orWhere('address', 'like', '%'.$location.'%');
            });
        }

        if ($assetType !== '') {
            $query->where('type', 'like', '%'.$assetType.'%');
        }

        if ($categoryName !== '') {
            $query->whereHas('category', fn (Builder $category) => $category->where('name', 'like', '%'.$categoryName.'%'));
        }

        if ($brandName !== '') {
            $query->whereHas('brand', fn (Builder $brand) => $brand->where('name', 'like', '%'.$brandName.'%'));
        }
    }

    /**
     * @param  array<int, string>  $nativeColumns
     * @param  array<int, string>  $customAttributeNames
     */
    private function whereIdentifierMatches(
        Builder $query,
        array $nativeColumns,
        array $customAttributeNames,
        string $value,
    ): void {
        $needle = $this->normalizeIdentifier($value);

        if ($needle === '') {
            return;
        }

        $query->where(function (Builder $identifier) use ($nativeColumns, $customAttributeNames, $needle): void {
            $hasClause = false;

            foreach ($nativeColumns as $column) {
                if ($hasClause) {
                    $identifier->orWhereRaw($this->normalizedIdentifierExpression($column).' LIKE ?', ['%'.$needle.'%']);
                } else {
                    $identifier->whereRaw($this->normalizedIdentifierExpression($column).' LIKE ?', ['%'.$needle.'%']);
                    $hasClause = true;
                }
            }

            if ($customAttributeNames !== []) {
                $customFilter = function (Builder $attribute) use ($customAttributeNames, $needle): void {
                    $attribute
                        ->whereRaw($this->normalizedIdentifierExpression('attribute_value').' LIKE ?', ['%'.$needle.'%'])
                        ->whereHas('customAttribute', function (Builder $custom) use ($customAttributeNames): void {
                            $custom
                                ->whereIn('name', $customAttributeNames)
                                ->where('is_active', true);
                        });
                };

                if ($hasClause) {
                    $identifier->orWhereHas('attributes', $customFilter);
                } else {
                    $identifier->whereHas('attributes', $customFilter);
                }
            }
        });
    }

    private function normalizedIdentifierExpression(string $column): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE($column, '')), ' ', ''), '-', ''), '.', ''), '/', ''))";
    }

    private function normalizeIdentifier(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $this->text($value)));
    }

    /**
     * @param  Collection<int, Asset>  $assets
     */
    private function assetResponse(Collection $assets, bool $includeHistory): JsonResponse
    {
        $items = $assets
            ->take(10)
            ->map(fn (Asset $asset) => $this->serializeAsset($asset, $includeHistory))
            ->values();

        // count mencerminkan jumlah item yang benar-benar dikembalikan; has_more
        // menandai bila hasil asli dipotong (query memakai limit 11 untuk mendeteksi
        // truncation) sehingga bot WhatsApp tahu ada lebih dari 10 aset.
        $count = $items->count();
        $hasMore = $assets->count() > $items->count();

        return $this->envelope([
            'ok' => true,
            'result_status' => $this->resultStatus($count),
            'count' => $count,
            'has_more' => $hasMore,
            'items' => $items,
            'candidates' => $count > 1 ? $items->map(fn (array $item) => [
                'asset_id' => $item['asset_id'],
                'name' => $item['name'],
                'serial_number' => $item['serial_number'],
                'plate_number' => $item['plate_number'],
                'recipient_name' => $item['recipient']['name'] ?? null,
                'location_name' => $item['location']['name'] ?? null,
            ])->values() : [],
            'next_question' => $count > 1 ? 'Pilih asset_id dari kandidat, atau kirim serial number/IMEI/plat nomor yang lebih spesifik.' : '',
            'freshness' => [
                'newest_updated_at' => $this->newestUpdatedAt($items),
            ],
        ]);
    }

    private function serializeAsset(Asset $asset, bool $includeHistory): array
    {
        $condition = $asset->condition_status instanceof AssetCondition
            ? $asset->condition_status
            : AssetCondition::tryFrom((string) $asset->condition_status);
        $nbh = $asset->nbh_status instanceof NbhStatus
            ? $asset->nbh_status
            : NbhStatus::tryFrom((string) $asset->nbh_status);

        return [
            'asset_id' => $asset->id,
            'asset_code' => (string) $asset->id,
            'name' => $asset->name,
            'category' => $this->idName($asset->category),
            'brand' => $this->idName($asset->brand),
            'type' => $asset->type,
            'serial_number' => $this->assetIdentifier($asset, 'serial_number', ['Serial Number']),
            'imei1' => $this->assetIdentifier($asset, 'imei1', ['IMEI1']),
            'imei2' => $this->assetIdentifier($asset, 'imei2', ['IMEI2']),
            'plate_number' => $this->customAssetIdentifier($asset, ['Plat Nomor']),
            'condition_status' => $condition ? [
                'value' => $condition->value,
                'label' => $condition->label(),
                'color' => $condition->color(),
            ] : null,
            'nbh_status' => $nbh ? [
                'value' => $nbh->value,
                'label' => $nbh->label(),
                'color' => $nbh->color(),
            ] : null,
            'is_available' => (bool) $asset->is_available,
            'qty' => (int) ($asset->qty ?? 1),
            'location' => $asset->assetLocation ? [
                'id' => $asset->assetLocation->id,
                'name' => $asset->assetLocation->name,
                'address' => $asset->assetLocation->address,
            ] : null,
            'business_entity' => $this->idName($asset->businessEntity),
            'recipient' => $asset->recipient ? [
                'id' => $asset->recipient->id,
                'name' => $asset->recipient->name,
                'username' => $asset->recipient->username,
                'whatsapp_number' => $asset->recipient->whatsapp_number,
                'business_entity' => $this->idName($asset->recipient->businessEntity),
                'job_title' => $asset->recipient->jobTitle ? [
                    'id' => $asset->recipient->jobTitle->id,
                    'name' => $asset->recipient->jobTitle->title,
                ] : null,
            ] : null,
            'latest_transfer' => $this->latestTransfer($asset),
            'history' => $includeHistory ? $this->history($asset) : [],
            'source_updated_at' => $asset->updated_at?->toIso8601String(),
        ];
    }

    private function serializeExpiringDocument(AssetAttribute $attribute, CarbonImmutable $today): array
    {
        $asset = $attribute->asset;
        $expiryDate = $attribute->expiryDate();
        $status = $attribute->expiryReminderStatusOn($today);

        return [
            'asset_id' => $asset?->id,
            'asset_code' => $asset ? (string) $asset->id : null,
            'asset_name' => $asset?->name,
            'name' => $asset?->name,
            'serial_number' => $asset?->serial_number,
            'category' => $this->idName($asset?->category),
            'brand' => $this->idName($asset?->brand),
            'type' => $asset?->type,
            'location' => $asset?->assetLocation ? [
                'id' => $asset->assetLocation->id,
                'name' => $asset->assetLocation->name,
                'address' => $asset->assetLocation->address,
            ] : null,
            'recipient' => $asset?->recipient ? [
                'id' => $asset->recipient->id,
                'name' => $asset->recipient->name,
                'whatsapp_number' => $asset->recipient->whatsapp_number,
            ] : null,
            'document' => [
                'custom_attribute_id' => $attribute->customAttribute?->id,
                'name' => $attribute->customAttribute?->name,
                'number' => $attribute->documentNumber(),
                'expires_at' => $expiryDate?->toDateString(),
                'days_until_expiry' => $expiryDate ? (int) $today->diffInDays($expiryDate, false) : null,
                'status' => [
                    'value' => $status,
                    'label' => $attribute->expiryReminderStatusLabelOn($today),
                    'color' => $attribute->expiryReminderStatusColorOn($today),
                ],
                'reminder_start_days' => $attribute->reminderStartDays(),
                'notes' => $attribute->documentNotes(),
            ],
            'source_updated_at' => $this->maxDate($asset?->updated_at, $attribute->updated_at),
        ];
    }

    private function matchesDocumentStatus(
        AssetAttribute $attribute,
        CarbonImmutable $today,
        string $status,
        int $dueWithinDays,
        bool $includeExpired,
    ): bool {
        $expiryDate = $attribute->expiryDate();

        if (! $expiryDate) {
            return $status === AssetAttribute::STATUS_NO_EXPIRY;
        }

        $actualStatus = $attribute->expiryReminderStatusOn($today);

        if (! $includeExpired && $actualStatus === AssetAttribute::STATUS_EXPIRED) {
            return false;
        }

        if ($dueWithinDays > 0 && $expiryDate->greaterThan($today->addDays($dueWithinDays))) {
            return false;
        }

        if (in_array($status, ['', 'due', 'perlu_diperpanjang', 'perlu_diperbarui', 'jatuh_tempo'], true)) {
            return in_array($actualStatus, [
                AssetAttribute::STATUS_EXPIRED,
                AssetAttribute::STATUS_DUE_TODAY,
                AssetAttribute::STATUS_DUE_SOON,
            ], true);
        }

        return $actualStatus === $status;
    }

    private function latestTransfer(Asset $asset): ?array
    {
        $detail = $this->transferDetails($asset)->first();
        $transfer = $detail?->assetTransfer;

        if (! $transfer) {
            return null;
        }

        return [
            'letter_number' => $transfer->letter_number,
            'transfer_date' => $this->dateString($transfer->transfer_date),
            'from_user' => $this->idName($transfer->fromUser),
            'to_user' => $this->idName($transfer->toUser),
            'business_entity' => $this->idName($transfer->businessEntity),
        ];
    }

    private function history(Asset $asset): array
    {
        return $this->transferDetails($asset)
            ->map(function ($detail): ?array {
                $transfer = $detail->assetTransfer;

                if (! $transfer) {
                    return null;
                }

                return [
                    'event_type' => 'asset_transfer',
                    'letter_number' => $transfer->letter_number,
                    'document_type' => 'BAST',
                    'transfer_date' => $this->dateString($transfer->transfer_date),
                    'from_user' => $this->idName($transfer->fromUser),
                    'to_user' => $this->idName($transfer->toUser),
                    'business_entity' => $this->idName($transfer->businessEntity),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function transferDetails(Asset $asset): Collection
    {
        return $asset->assetTransferDetails
            ->filter(fn ($detail) => $detail->assetTransfer !== null)
            ->sortByDesc(function ($detail): int {
                $date = $detail->assetTransfer?->transfer_date ?: $detail->created_at;

                return CarbonImmutable::parse($date)->timestamp;
            })
            ->values();
    }

    private function resultStatus(int $count): string
    {
        if ($count === 0) {
            return 'not_found';
        }

        return $count === 1 ? 'confirmed' : 'multiple';
    }

    private function normalizeQuery(array $query): array
    {
        return collect($query)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
    }

    private function normalizeDocumentStatus(mixed $value): string
    {
        $status = strtolower($this->text($value));

        return match ($status) {
            'expired', 'kadaluarsa', 'kedaluwarsa' => AssetAttribute::STATUS_EXPIRED,
            'today', 'hari_ini', 'jatuh_tempo_hari_ini' => AssetAttribute::STATUS_DUE_TODAY,
            'soon', 'perlu_diperbarui', 'perlu_diperpanjang' => AssetAttribute::STATUS_DUE_SOON,
            'tanpa_tanggal', 'missing_date' => AssetAttribute::STATUS_NO_EXPIRY,
            default => $status,
        };
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function plateNumber(array $query): string
    {
        return $this->text($query['plate_number'] ?? '')
            ?: $this->text($query['plat_nomor'] ?? '')
            ?: $this->text($query['license_plate'] ?? '');
    }

    /**
     * @param  array<int, string>  $customAttributeNames
     */
    private function assetIdentifier(Asset $asset, string $nativeColumn, array $customAttributeNames): ?string
    {
        $nativeValue = $this->text($asset->getAttribute($nativeColumn));

        if ($nativeValue !== '' && ! $this->isPlaceholderIdentifier($nativeValue)) {
            return $nativeValue;
        }

        return $this->customAssetIdentifier($asset, $customAttributeNames);
    }

    /**
     * @param  array<int, string>  $customAttributeNames
     */
    private function customAssetIdentifier(Asset $asset, array $customAttributeNames): ?string
    {
        $allowedNames = array_map('strtolower', $customAttributeNames);
        $attributes = $asset->relationLoaded('attributes')
            ? $asset->attributes
            : $asset->attributes()->with('customAttribute:id,name,is_active')->get();

        foreach ($attributes as $attribute) {
            $customAttribute = $attribute->customAttribute;
            $value = $this->text($attribute->attribute_value);

            if (! $customAttribute?->is_active || $value === '' || $this->isPlaceholderIdentifier($value)) {
                continue;
            }

            if (in_array(strtolower((string) $customAttribute->name), $allowedNames, true)) {
                return $value;
            }
        }

        return null;
    }

    private function isPlaceholderIdentifier(string $value): bool
    {
        return in_array(strtolower($this->text($value)), [
            '-',
            '0',
            '00',
            '000',
            '0000',
            'n/a',
            'na',
            'tidak ada',
        ], true);
    }

    private function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower($this->text($value)), ['1', 'true', 'ya', 'iya', 'yes', 'setuju', 'ok', 'oke'], true);
    }

    private function falseLike(mixed $value): bool
    {
        if ($value === false || $value === 0) {
            return true;
        }

        return in_array(strtolower($this->text($value)), ['0', 'false', 'tidak', 'no', 'nggak', 'gak', 'ga'], true);
    }

    private function intInRange(mixed $value, int $fallback, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        if ($parsed === false) {
            $parsed = $fallback;
        }

        return min($max, max($min, (int) $parsed));
    }

    private function phoneOptions(string $normalized): array
    {
        $options = [$normalized];
        $countryCode = preg_replace('/\D+/', '', (string) config('services.whatsapp_gateway.country_code', '62'));

        if ($countryCode !== '' && str_starts_with($normalized, $countryCode)) {
            $options[] = '0'.substr($normalized, strlen($countryCode));
        }

        return array_values(array_unique(array_filter($options)));
    }

    private function idName(mixed $model): ?array
    {
        if (! $model) {
            return null;
        }

        return [
            'id' => $model->id,
            'name' => $model->name ?? $model->title ?? null,
        ];
    }

    private function dateString(mixed $date): ?string
    {
        if (! filled($date)) {
            return null;
        }

        return CarbonImmutable::parse($date)->toDateString();
    }

    private function maxDate(mixed ...$dates): ?string
    {
        return collect($dates)
            ->filter()
            ->map(fn ($date) => CarbonImmutable::parse($date))
            ->sortByDesc(fn (CarbonInterface $date) => $date->timestamp)
            ->first()
            ?->toIso8601String();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function newestUpdatedAt(Collection $items): ?string
    {
        return $items
            ->pluck('source_updated_at')
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function envelope(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(array_merge([
            'ok' => false,
            'result_status' => 'service_error',
            'count' => 0,
            'items' => [],
            'candidates' => [],
            'next_question' => '',
        ], $payload), $status);
    }
}
