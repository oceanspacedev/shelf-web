<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('updateAttributeDocument')
                ->label('Perbarui Dokumen (STNK/KIR)')
                ->icon('heroicon-o-document-check')
                ->color('warning')
                ->visible(fn (): bool => AssetResource::hasDocumentExpiryAttributes($this->record))
                ->modalWidth('lg')
                ->modalHeading(fn (): string => 'Perbarui Dokumen: ' . $this->record->name)
                ->modalSubmitActionLabel('Simpan Pembaruan')
                ->form(fn (): array => AssetResource::attributeDocumentUpdateFormSchema($this->record))
                ->action(function (array $data): void {
                    AssetResource::handleAttributeDocumentUpdate($this->record, $data);
                    $this->record->refresh();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
