<?php

namespace App\Filament\Resources\AssetRequestResource\Pages;

use App\Filament\Resources\AssetRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAssetRequest extends CreateRecord
{
    protected static string $resource = AssetRequestResource::class;

    public array $requestItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $items = $data['request_items'] ?? [];
        unset($data['request_items']);

        $firstItem = $items[0] ?? [];
        $data['asset_id'] = $firstItem['asset_id'] ?? null;
        $data['item_name'] = $firstItem['item_name'] ?? null;
        $data['qty'] = $firstItem['qty'] ?? 1;

        $this->requestItems = array_slice($items, 1);

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        foreach ($this->requestItems ?? [] as $itemRow) {
            $record->items()->create($itemRow);
        }
    }
}
