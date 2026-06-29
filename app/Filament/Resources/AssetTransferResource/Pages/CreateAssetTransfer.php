<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    public ?int $sourceAssetRequestId = null;

    protected ?AssetRequest $sourceAssetRequest = null;

    protected ?int $fulfilledSourceAssetRequestId = null;

    public function mount(): void
    {
        $this->sourceAssetRequestId = request()->integer('asset_request_id') ?: null;

        parent::mount();
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefillDataFromAssetRequest(AssetRequest $assetRequest): array
    {
        $assetRequest->loadMissing('items.asset', 'asset', 'user');
        $assets = $assetRequest->requestedAssets();
        $asset = $assets->first() ?? $assetRequest->asset;
        $businessEntityId = $asset?->business_entity_id
            ?? $asset?->recipient_business_entity_id
            ?? $assetRequest->user?->business_entity_id;
        $generalAffairUserId = User::whereHas('roles', fn ($query) => $query->where('name', 'general_affair'))
            ->orderBy('name')
            ->value('id');

        return [
            'business_entity_id' => $businessEntityId,
            'letter_number' => AssetTransfer::generateLetterNumber(
                $businessEntityId ? BusinessEntity::find($businessEntityId) : null
            ),
            'from_user_id' => $asset?->recipient_id ?? $assetRequest->user_id,
            'to_user_id' => $generalAffairUserId,
            'transfer_date' => now()->toDateString(),
            'details' => $assets
                ->map(fn ($asset): array => [
                    'asset_id' => $asset->id,
                    'equipment' => null,
                ])
                ->values()
                ->all(),
        ];
    }

    public function getSubheading(): ?string
    {
        $sourceAssetRequest = $this->getSourceAssetRequest();

        if (! $sourceAssetRequest) {
            return null;
        }

        return "Tindak lanjut {$sourceAssetRequest->reference_number}: {$sourceAssetRequest->nextStepLabel()}";
    }

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill(
            ($sourceAssetRequest = $this->getSourceAssetRequest())
                ? self::prefillDataFromAssetRequest($sourceAssetRequest)
                : []
        );

        $this->callHook('afterFill');
    }

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $sourceAssetRequest = $this->getSourceAssetRequest();

            if ($sourceAssetRequest && auth()->user() && $this->record instanceof AssetTransfer) {
                $this->fulfilledSourceAssetRequestId = $sourceAssetRequest->id;
                $sourceAssetRequest->markFulfilledByAssetTransfer($this->record, auth()->user());
            }

            $this->record->applyLifecycleToAssets($sourceAssetRequest);
        });
    }

    protected function getRedirectUrl(): string
    {
        $sourceAssetRequestId = $this->fulfilledSourceAssetRequestId ?? $this->sourceAssetRequestId;

        if ($sourceAssetRequestId) {
            return AssetRequestResource::getUrl('view', ['record' => $sourceAssetRequestId]);
        }

        return parent::getRedirectUrl();
    }

    protected function getSourceAssetRequest(): ?AssetRequest
    {
        if (! $this->sourceAssetRequestId) {
            return null;
        }

        if ($this->sourceAssetRequest !== null) {
            return $this->sourceAssetRequestIsFillable($this->sourceAssetRequest)
                ? $this->sourceAssetRequest
                : null;
        }

        $assetRequest = AssetRequest::with(['asset', 'user'])->find($this->sourceAssetRequestId);

        if (! $assetRequest || ! $this->sourceAssetRequestIsFillable($assetRequest)) {
            return null;
        }

        return $this->sourceAssetRequest = $assetRequest;
    }

    protected function sourceAssetRequestIsFillable(AssetRequest $assetRequest): bool
    {
        return $assetRequest->type === AssetRequestType::Penarikan
            && ! $assetRequest->is_fulfilled
            && $assetRequest->status === RequestStatus::Approved;
    }
}
