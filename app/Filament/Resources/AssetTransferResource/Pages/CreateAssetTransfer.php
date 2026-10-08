<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Enums\AssetRequestType;
use App\Enums\AssetTransferDocumentType;
use App\Enums\RequestStatus;
use App\Exceptions\AssetTransferException;
use App\Filament\Resources\AssetRequestResource;
use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetRequest;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    public ?int $sourceAssetRequestId = null;

    protected ?AssetRequest $sourceAssetRequest = null;

    protected ?int $fulfilledSourceAssetRequestId = null;

    /**
     * BA, detail aset, dan mutasi aset disimpan dalam satu transaksi: bila
     * aturan siklus hidup menolak (pihak bukan staf GA, aset tidak di stok,
     * dan sebagainya) tidak ada BA yatim yang tertinggal.
     */
    protected ?bool $hasDatabaseTransactions = true;

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
        $actor = Auth::user();

        // Badan usaha BA: pemilik aset, lalu badan usaha penerima, lalu badan usaha
        // pemohon; pilih yang pertama bisa diakses pengguna yang login.
        $candidateEntityIds = array_values(array_filter([
            $asset?->business_entity_id,
            $asset?->recipient_business_entity_id,
            $assetRequest->user?->business_entity_id,
        ]));
        $businessEntityId = collect($candidateEntityIds)
            ->first(fn ($id): bool => ! $actor instanceof User || $actor->canAccessBusinessEntity($id))
            ?? ($candidateEntityIds[0] ?? null);

        // Penerima pengembalian adalah staf GA yang sedang login; bila yang
        // membuka halaman bukan staf GA, tawarkan staf GA pertama.
        $generalAffairUserId = $actor instanceof User && $actor->isGeneralAffair()
            ? $actor->getKey()
            : User::query()->generalAffair()->orderBy('name')->value('id');

        return [
            'business_entity_id' => $businessEntityId,
            'document_type' => AssetTransferDocumentType::PengembalianBarang->value,
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

        // null (bukan []) agar default field, seperti jenis BA dan staf GA yang
        // login, tetap diterapkan saat tidak ada pengajuan sumber.
        $this->form->fill(
            ($sourceAssetRequest = $this->getSourceAssetRequest())
                ? self::prefillDataFromAssetRequest($sourceAssetRequest)
                : null
        );

        $this->callHook('afterFill');
    }

    /**
     * Pihak GA pada BA adalah akun yang sedang login. Field-nya terkunci di
     * form, tetapi nilainya ditetapkan di sini supaya tidak bergantung pada
     * apa yang dikirim browser. Pemegang izin "Kelola BA Stok" boleh memilih
     * staf GA lain.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Auth::user();

        if (! $actor instanceof User || $actor->canManageStockTransfers() || ! $actor->isGeneralAffair()) {
            return $data;
        }

        $type = $data['document_type'] ?? null;
        $type = $type instanceof AssetTransferDocumentType ? $type : AssetTransferDocumentType::tryFrom((string) $type);

        if ($type?->requiresGeneralAffairFrom()) {
            $data['from_user_id'] = $actor->getKey();
        }

        if ($type?->requiresGeneralAffairTo()) {
            $data['to_user_id'] = $actor->getKey();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $sourceAssetRequest = $this->getSourceAssetRequest();
        $actor = auth()->user();

        try {
            if ($sourceAssetRequest && $actor && $this->record instanceof AssetTransfer) {
                $this->fulfilledSourceAssetRequestId = $sourceAssetRequest->id;
                $sourceAssetRequest->markFulfilledByAssetTransfer($this->record, $actor);
            }

            $this->record->applyLifecycleToAssets($sourceAssetRequest, $actor instanceof User ? $actor : null);
        } catch (AssetTransferException|AuthorizationException $exception) {
            // BA ini di-rollback. Lepaskan record-nya supaya Livewire tidak
            // memuat ulang baris yang sudah tidak ada (404) saat form dikirim lagi.
            $this->record = null;
            $this->fulfilledSourceAssetRequestId = null;

            Notification::make()
                ->title('BA tidak dapat dibuat')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
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

        $viewer = Auth::user();
        $assetRequest = AssetRequest::with(['asset', 'user'])
            ->when($viewer instanceof User, fn ($query) => $query->accessibleBy($viewer))
            ->find($this->sourceAssetRequestId);

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
