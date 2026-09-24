<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Imports\UserImport;
use App\Services\TalentaUserImportService;
use Asmit\ResizedColumn\HasResizableColumn;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListUsers extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $canImport = fn (): bool => auth()->user()?->can('import', static::$resource::getModel()) ?? false;

        return [
            Actions\ActionGroup::make([
                ExcelImportAction::make()
                    ->label('Import Excel')
                    ->color('success')
                    ->use(UserImport::class)
                    ->authorize($canImport)
                    ->visible($canImport),
                Actions\Action::make('importTalenta')
                    ->label('Import Talenta')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->authorize($canImport)
                    ->visible($canImport)
                    ->schema([
                        FileUpload::make('file')
                            ->label('Upload JSON Talenta')
                            ->helperText('Ekspor karyawan Talenta (employee_id / id_employee dan nomor HP). User yang cocok akan diisi Employee ID dan nomor WhatsApp.')
                            ->acceptedFileTypes([
                                'application/json',
                                'text/json',
                                'text/plain',
                            ])
                            ->required()
                            ->storeFiles(false),
                    ])
                    ->modalHeading('Import Talenta')
                    ->modalSubmitActionLabel('Import')
                    ->action(function (array $data): void {
                        $file = $data['file'] ?? null;
                        $content = $file instanceof TemporaryUploadedFile
                            ? $file->get()
                            : (is_string($file) && is_readable($file) ? file_get_contents($file) : false);

                        if (! is_string($content) || $content === '') {
                            Notification::make()
                                ->title('File JSON Talenta tidak dapat dibaca.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $result = (new TalentaUserImportService)->importFromContent($content);
                        $notification = Notification::make()->title($result['message']);

                        if (! ($result['success'] ?? false) || ($result['error_count'] ?? 0) > 0) {
                            $notification
                                ->body(implode("\n", array_slice($result['errors'] ?? [], 0, 8)))
                                ->warning()
                                ->persistent();
                        } else {
                            $notification->success();
                        }

                        $notification->send();
                    }),
            ])
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->button()
                ->visible($canImport),
            Actions\CreateAction::make(),
        ];
    }
}
