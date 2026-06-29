<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),

            // Custom Download Action (color: red, with download icon)
            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray') // Use the download icon for download
                ->color('success') // Use 'danger' for red
                ->visible(fn ($record) => $record->status === 'completed') // Only show if task is completed
                ->url(fn ($record) => route('task-completion.download', $record->id)) // Generate URL for download
                ->openUrlInNewTab(), // Open the download in a new tab
        ];
    }
}
