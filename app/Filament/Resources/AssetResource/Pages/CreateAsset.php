<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\AssetRequestItem;
use Filament\Resources\Pages\CreateRecord;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    public ?int $sourceAssetRequestId = null;

    public ?int $sourceAssetRequestItemId = null;

    protected ?AssetRequest $sourceAssetRequest = null;

    protected ?int $fulfilledSourceAssetRequestId = null;

    public function mount(): void
    {
        $this->sourceAssetRequestId = request()->integer('asset_request_id') ?: null;
        $this->sourceAssetRequestItemId = request()->integer('asset_request_item_id') ?: null;

        parent::mount();
    }

    /**
     * @return array<string, mixed>
     */
    public static function prefillDataFromAssetRequest(AssetRequest $assetRequest, ?int $assetRequestItemId = null): array
    {
        $item = self::resolvePrefillItem($assetRequest, $assetRequestItemId);

        return [
            'asset_request_id' => $assetRequest->id,
            'asset_request_item_id' => $item?->id,
            'name' => $item?->item_name ?? $assetRequest->item_name,
            'qty' => $item?->qty ?? $assetRequest->qty ?? 1,
            'business_entity_id' => $assetRequest->user?->business_entity_id,
            'asset_location_id' => $assetRequest->asset_location_id,
            'purchase_date' => now()->toDateString(),
            'condition_status' => AssetCondition::Available->value,
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
                ? self::prefillDataFromAssetRequest($sourceAssetRequest, $this->sourceAssetRequestItemId)
                : []
        );

        $this->callHook('afterFill');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($sourceAssetRequest = $this->getSourceAssetRequest()) {
            $data['asset_request_id'] = $sourceAssetRequest->id;
        }

        unset($data['asset_request_item_id']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $sourceAssetRequest = $this->getSourceAssetRequest();

        if (! $sourceAssetRequest || ! auth()->user() || ! $this->record instanceof Asset) {
            return;
        }

        $this->fulfilledSourceAssetRequestId = $sourceAssetRequest->id;
        $sourceAssetRequest->markFulfilledByAsset(
            $this->record,
            auth()->user(),
            $this->sourceAssetRequestItemId
        );
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

        $assetRequest = AssetRequest::find($this->sourceAssetRequestId);

        if (! $assetRequest || ! $this->sourceAssetRequestIsFillable($assetRequest)) {
            return null;
        }

        return $this->sourceAssetRequest = $assetRequest;
    }

    protected function sourceAssetRequestIsFillable(AssetRequest $assetRequest): bool
    {
        return $assetRequest->type === AssetRequestType::Pengadaan
            && ! $assetRequest->is_fulfilled
            && $assetRequest->status === RequestStatus::Approved;
    }

    protected static function resolvePrefillItem(AssetRequest $assetRequest, ?int $assetRequestItemId): ?AssetRequestItem
    {
        if ($assetRequestItemId) {
            $item = $assetRequest->items()
                ->whereKey($assetRequestItemId)
                ->whereNull('fulfilled_asset_id')
                ->first();

            if ($item) {
                return $item;
            }
        }

        return $assetRequest->nextUnfulfilledPengadaanItem();
    }
}
