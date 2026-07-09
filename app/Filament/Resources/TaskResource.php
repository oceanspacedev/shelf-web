<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    public static function getModelLabel(): string
    {
        return __('Task');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Tasks');
    }

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Umum')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nama Pekerjaan')
                                    ->required(),

                                TextInput::make('cost')
                                    ->label('Biaya')
                                    ->numeric()
                                    ->prefix('Rp ')
                                    ->required(),
                            ]),

                        DateTimePicker::make('work_timestamp')
                            ->native(false)
                            ->default(now())
                            ->required(),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required(),

                        TextInput::make('location')
                            ->label('Lokasi')
                            ->required(),

                        Select::make('business_entity_id')
                            ->label('Entitas Bisnis')
                            ->relationship('businessEntity', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('user_id')
                            ->label('PIC')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn () => auth()->id())
                            // ->disabled(fn() => !auth()->user()->hasRole('super_admin'))
                            ->dehydrated(true),
                    ]),

                // Vendor Information Section
                Forms\Components\Section::make('Informasi Vendor')
                    ->schema([
                        Select::make('vendor_id')
                            ->label('Vendor')
                            ->relationship('vendor', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nama Vendor')
                                    ->required(),

                                TextInput::make('last_price')
                                    ->label('Harga Terakhir (Rp)')
                                    ->numeric()
                                    ->prefix('Rp ')
                                    ->required()
                                    ->placeholder('Masukkan harga terakhir'),
                            ]),
                    ]),

                // Attachment Section
                Forms\Components\Section::make('Lampiran')
                    ->schema([
                        FileUpload::make('document_upload')
                            ->label('Upload Dokumen')
                            ->directory('documents') // Define a different directory for documents
                            ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) // Allow only document types
                            ->maxSize(5120) // Set a max size of 5MB
                            ->required() // Optionally, limit the number of files (example: 5)
                            ->hiddenOn('create'),
                    ])
                    ->columns(1)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Nomor Surat')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('businessEntity.name')
                    ->label('Badan Usaha')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('vendor.name')
                    ->label('Vendor')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cost')
                    ->money('IDR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('PIC')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status') // Label in Indonesian
                    ->badge() // Enables badge display
                    ->colors([
                        'danger' => 'open',         // Red badge for 'open' status
                        'warning' => 'in_progress', // Yellow badge for 'in_progress' status
                        'success' => 'completed',   // Green badge for 'completed' status
                    ])
                    ->toggleable(),
                Tables\Columns\TextColumn::make('document_upload')
                    ->url(fn ($record) => $record && $record->document_upload ? Storage::url($record->document_upload) : null, true) // Membuat kolom URL untuk unduh
                    ->openUrlInNewTab()
                    ->translateLabel()
                    ->getStateUsing(fn ($record) => $record && $record->document_upload ? 'Dokumen' : '-')
                    ->icon('heroicon-o-document-text')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('businessEntity')->preload()->searchable()->relationship('businessEntity', 'name')->translateLabel(),
                SelectFilter::make('vendor')
                    ->relationship('vendor', 'name')
                    ->label('Vendor')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->label('PIC')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ]),
            ])
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnToggleFormColumns(2)
            ->actions([
                // Group the custom actions together
                \Filament\Actions\ActionGroup::make([
                    // Edit Action (with pencil icon)
                    \Filament\Actions\EditAction::make()
                        ->label('Edit')
                        ->icon('heroicon-o-pencil') // Use the pencil icon for edit
                        ->visible(fn ($record) => ! in_array($record->status, ['in_progress', 'completed'])),

                    // Custom Process Action (color: blue, with play icon)
                    \Filament\Actions\Action::make('process')
                        ->label('Process')
                        ->icon('heroicon-o-play') // Use the play icon for process
                        ->color('primary') // Use 'primary' for blue
                        ->visible(fn ($record) => $record->status === 'open')
                        ->action(function ($record) {
                            $record->update(['status' => 'in_progress']);
                        }),

                    // Custom Complete Action (color: green, with check icon)
                    \Filament\Actions\Action::make('complete')
                        ->label('Complete')
                        ->icon('heroicon-o-check-circle') // Use the check circle icon for complete
                        ->color('success') // Use 'success' for green
                        ->visible(fn ($record) => $record->status === 'in_progress')
                        ->form([
                            FileUpload::make('attachment')
                                ->label('Upload Lampiran')
                                ->directory('task') // Define the directory to store images
                                ->image() // Only allow image uploads
                                ->maxSize(2048) // Maximum size (optional)
                                ->required()
                                ->multiple() // Enable multiple file uploads
                                ->maxFiles(5), // Optionally, limit the number of files (example: 5)
                        ])
                        ->action(function ($record, $data) {
                            $record->update([
                                'status' => 'completed',
                                'attachment' => $data['attachment'],
                            ]);
                        }),

                    \Filament\Actions\Action::make('upload')
                        ->label('Upload')
                        ->icon('heroicon-o-check-circle') // Use the check circle icon for complete
                        ->color('success') // Use 'success' for green
                        ->visible(fn ($record) => $record->status === 'completed' && is_null($record->document_upload))
                        ->form([
                            FileUpload::make('document_upload')
                                ->label('Upload Dokumen')
                                ->directory('documents') // Define a different directory for documents
                                ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']) // Allow only document types
                                ->maxSize(5120) // Set a max size of 5MB
                                ->required(),
                        ])
                        ->action(function ($record, $data) {
                            $record->update([
                                'document_upload' => $data['document_upload'], // Save the uploaded document
                            ]);
                        }),

                    // Custom Download Action (color: red, with download icon)
                    \Filament\Actions\Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray') // Use the download icon for download
                        ->color('danger') // Use 'danger' for red
                        ->visible(fn ($record) => $record->status === 'completed' && is_null($record->document_upload))
                        ->url(fn ($record) => route('task-completion.download', $record->id))
                        ->openUrlInNewTab(),
                ]),

            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $infolist): Schema
    {
        return $infolist
            ->schema([
                // General Information Section
                Section::make('Informasi Umum')
                    ->description('Detail penting mengenai tugas dan entitas terkait.')
                    ->schema([
                        Grid::make(2) // Two-column grid layout for better spacing
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nama Tugas')
                                    ->placeholder('Tidak ada nama tugas'),

                                TextEntry::make('vendor.name')
                                    ->label('Nama Vendor')
                                    ->placeholder('Tidak ada vendor yang ditugaskan'),

                                TextEntry::make('businessEntity.name')
                                    ->label('Badan Usaha')
                                    ->placeholder('Tidak ada badan usaha terkait'),

                                TextEntry::make('code')
                                    ->label('Nomor Tugas')
                                    ->placeholder('Kode tugas belum dibuat'),
                            ]),
                    ])
                    ->columns(1) // Single column for easier readability
                    ->collapsible(), // Allow section to be collapsible for a cleaner UI

                // Status Section
                Section::make('Status Pekerjaan')
                    ->description('Periksa status terbaru dari tugas ini.')
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->placeholder('Status belum diperbarui'),
                    ])
                    ->columns(1)
                    ->collapsible(), // Make it collapsible

                // Attachments Section
                Section::make('Lampiran')
                    ->description('Lampiran terkait tugas ini.')
                    ->schema([
                        TextEntry::make('attachment')
                            ->label('Lampiran')
                            ->formatStateUsing(function ($state) {
                                $baseUrl = asset('storage'); // Path dasar untuk storage

                                // Jika state adalah JSON-encoded string, ubah menjadi array
                                if (is_string($state) && str_starts_with($state, '[')) {
                                    $state = json_decode($state, true); // Decode JSON string to array
                                }

                                // Jika state adalah array, tampilkan gambar
                                if (is_array($state)) {
                                    return "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>"
                                        .collect($state)->map(function ($image) use ($baseUrl) {
                                            return "<img src='{$baseUrl}/{$image}' alt='Lampiran' style='max-width: 100px; border-radius: 5px;'>";
                                        })->implode('').
                                        '</div>';
                                }

                                // Jika hanya satu gambar
                                if (is_string($state) && ! empty($state)) {
                                    return "<img src='{$baseUrl}/{$state}' alt='Lampiran' style='max-width: 100px; border-radius: 5px;'>";
                                }

                                return 'Tidak ada lampiran';
                            })
                            ->html(), // Enable HTML rendering for images
                    ])
                    ->collapsible() // Allow section to be collapsible
                    ->columns(1),

                // Timestamps Section
                Section::make('Tanggal')
                    ->description('Waktu pembuatan dan pembaruan tugas ini.')
                    ->schema([
                        Grid::make(2) // Two-column grid for created and updated timestamps
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime()
                                    ->placeholder('Tanggal pembuatan belum tersedia'),

                                TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime()
                                    ->placeholder('Tanggal pembaruan belum tersedia'),
                            ]),
                    ])
                    ->columns(1)
                    ->collapsible(), // Make this section collapsible too
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            'view' => Pages\ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
