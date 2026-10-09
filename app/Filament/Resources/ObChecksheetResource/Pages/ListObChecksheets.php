<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Exports\ObChecksheetExporter;
use App\Filament\Resources\ObChecksheetResource;
use App\Forms\Components\CameraCapture;
use App\Models\ObChecksheet;
use App\Models\ObTaskTemplate;
use App\Models\User;
use App\Services\ObTaskAssignmentService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListObChecksheets extends ListRecords
{
    protected static string $resource = ObChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->startAllAction(),
            $this->finishAllAction(),
            $this->assignFromTemplateAction(),
            $this->assignManualAction(),
            ExportAction::make()
                ->exporter(ObChecksheetExporter::class)
                ->label('Export')
                ->color('warning')
                ->visible(fn () => auth()->user()?->can('export', ObChecksheet::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Foto sebelum untuk semua tugas hari ini sekaligus. Hanya ruangan yang difoto
     * yang dimulai; sisanya tetap Belum Dikerjakan.
     */
    protected function startAllAction(): Action
    {
        return Action::make('startAll')
            ->label('Mulai Semua & Foto Sebelum')
            ->icon('heroicon-o-play')
            ->color('warning')
            ->visible(fn (): bool => $this->startableTasks()->exists())
            ->modalHeading('Foto Sebelum Pembersihan')
            ->modalDescription('Foto ruangan yang akan dikerjakan. Ruangan tanpa foto tetap Belum Dikerjakan. Ruangan yang tidak ada nama petugasnya menjadi milik OB yang lebih dulu mengirim fotonya.')
            ->modalWidth('2xl')
            ->modalAutofocus(false)
            ->modalSubmitActionLabel('Simpan & Mulai')
            ->fillForm(fn (): array => [
                'tasks' => $this->startableTasks()
                    ->get()
                    ->map(fn (ObChecksheet $task): array => [
                        'id' => $task->id,
                        'room' => $task->room,
                        'photo' => null,
                        'notes' => null,
                    ])
                    ->all(),
            ])
            ->form([
                Repeater::make('tasks')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('room')->dehydrated(false),
                        Text::make(fn (Get $get): string => (string) $get('room'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color('primary'),
                        CameraCapture::make('photo')
                            ->label('Foto Sebelum Pembersihan')
                            ->folder('ob-checksheets/before')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan (Opsional)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data, Action $action): void {
                $submitted = $this->submittedPhotos($data['tasks'] ?? []);

                if ($submitted === []) {
                    Notification::make()
                        ->title('Isi foto minimal satu ruangan')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                // Dicek ulang ke database: hanya tugas pending milik user sendiri atau kolam yang boleh dimulai.
                $tasks = $this->startableTasks()
                    ->whereKey(array_keys($submitted))
                    ->get();

                $started = 0;

                foreach ($tasks as $task) {
                    // Tugas kolam diambil atomik; OB yang kalah cepat tidak memulai tugas itu.
                    if ($task->isUnclaimed() && ! $task->claim(auth()->user())) {
                        continue;
                    }

                    $task->startWork($submitted[$task->id]['photo'], $submitted[$task->id]['notes']);
                    $started++;
                }

                // Selisihnya sudah diambil OB lain sejak form dibuka (atau bukan tugas yang boleh dimulai).
                $taken = count($submitted) - $started;

                $notification = Notification::make()
                    ->title($started.' ruangan dimulai')
                    ->body($taken > 0
                        ? $taken.' ruangan sudah diambil OB lain.'
                        : 'Ambil Foto Sesudah setelah ruangan selesai dibersihkan.');

                ($started > 0 ? $notification->success() : $notification->warning())->send();
            });
    }

    /**
     * Foto sesudah untuk semua ruangan yang sedang dikerjakan hari ini sekaligus.
     */
    protected function finishAllAction(): Action
    {
        return Action::make('finishAll')
            ->label('Selesaikan Semua & Foto Sesudah')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (): bool => $this->todayTasks(ObChecksheet::STATUS_IN_PROGRESS)->exists())
            ->modalHeading('Foto Sesudah Pembersihan')
            ->modalDescription('Foto ruangan yang sudah selesai. Ruangan tanpa foto tetap Sedang Dikerjakan.')
            ->modalWidth('2xl')
            ->modalAutofocus(false)
            ->modalSubmitActionLabel('Simpan & Selesaikan')
            ->fillForm(fn (): array => [
                'tasks' => $this->todayTasks(ObChecksheet::STATUS_IN_PROGRESS)
                    ->get()
                    ->map(fn (ObChecksheet $task): array => [
                        'id' => $task->id,
                        'room' => $task->room,
                        'before' => $task->before_photo,
                        'photo' => null,
                        'notes' => null,
                    ])
                    ->all(),
            ])
            ->form([
                Repeater::make('tasks')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->schema([
                        Hidden::make('id'),
                        Hidden::make('room')->dehydrated(false),
                        Text::make(fn (Get $get): string => (string) $get('room'))
                            ->weight(FontWeight::Bold)
                            ->size(TextSize::Large)
                            ->color('primary'),
                        ViewField::make('before')
                            ->label('Foto Sebelum')
                            ->view('filament.forms.components.ob-photo-preview')
                            ->dehydrated(false),
                        CameraCapture::make('photo')
                            ->label('Foto Sesudah Pembersihan')
                            ->folder('ob-checksheets/after')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Catatan (Opsional)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (array $data, Action $action): void {
                $submitted = $this->submittedPhotos($data['tasks'] ?? []);

                if ($submitted === []) {
                    Notification::make()
                        ->title('Isi foto minimal satu ruangan')
                        ->danger()
                        ->send();

                    $action->halt();
                }

                $tasks = $this->todayTasks(ObChecksheet::STATUS_IN_PROGRESS)
                    ->whereKey(array_keys($submitted))
                    ->get();

                foreach ($tasks as $task) {
                    $task->markAsCompleted($submitted[$task->id]['photo'], $submitted[$task->id]['notes']);
                }

                Notification::make()
                    ->title($tasks->count().' ruangan selesai')
                    ->success()
                    ->send();
            });
    }

    protected function assignFromTemplateAction(): Action
    {
        return Action::make('assignFromTemplate')
            ->label('Tugaskan dari Template')
            ->icon('heroicon-o-clipboard-document-list')
            ->color('info')
            ->visible(fn (): bool => $this->canAssign())
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Tugaskan')
            ->form([
                Select::make('template_id')
                    ->label('Template')
                    ->options(fn (): array => ObTaskTemplate::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('user_id')
                    ->label('Petugas OB')
                    ->placeholder('Semua OB (kolam bersama)')
                    ->helperText('Kosongkan agar tugas muncul untuk semua OB; petugasnya terisi saat ada yang mengambil.')
                    ->options(fn (): array => ObChecksheetResource::officeBoyOptions())
                    ->searchable(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),
            ])
            ->action(function (array $data, ObTaskAssignmentService $assignments): void {
                abort_unless($this->canAssign(), 403);

                $result = $assignments->assignTemplate(
                    ObTaskTemplate::query()->where('is_active', true)->findOrFail($data['template_id']),
                    $this->officeBoy($data['user_id'] ?? null),
                    Carbon::parse($data['date']),
                    auth()->user(),
                );

                $this->notifyAssigned($result);
            });
    }

    protected function assignManualAction(): Action
    {
        return Action::make('assignManual')
            ->label('Tambah Tugas')
            ->icon('heroicon-o-plus-circle')
            ->color('info')
            ->visible(fn (): bool => $this->canAssign())
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Tugaskan')
            ->form([
                Select::make('user_id')
                    ->label('Petugas OB')
                    ->placeholder('Semua OB (kolam bersama)')
                    ->helperText('Kosongkan agar tugas muncul untuk semua OB; petugasnya terisi saat ada yang mengambil.')
                    ->options(fn (): array => ObChecksheetResource::officeBoyOptions())
                    ->searchable(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->default(now())
                    ->required(),
                TextInput::make('shift_label')
                    ->label('Keterangan Shift (Opsional)')
                    ->datalist(['Shift Pagi', 'Shift Middle'])
                    ->maxLength(255),
                Repeater::make('rooms')
                    ->label('Ruangan / Pekerjaan')
                    ->simple(
                        TextInput::make('room')
                            ->placeholder('Contoh: Ruang Meeting Finance')
                            ->required()
                            ->maxLength(255),
                    )
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Tambah ruangan'),
            ])
            ->action(function (array $data, ObTaskAssignmentService $assignments): void {
                abort_unless($this->canAssign(), 403);

                $result = $assignments->assignRooms(
                    array_values($data['rooms'] ?? []),
                    $this->officeBoy($data['user_id'] ?? null),
                    Carbon::parse($data['date']),
                    auth()->user(),
                    filled($data['shift_label'] ?? null) ? $data['shift_label'] : null,
                );

                $this->notifyAssigned($result);
            });
    }

    private function canAssign(): bool
    {
        return auth()->user()?->can('assign', ObChecksheet::class) ?? false;
    }

    /**
     * Hanya user dengan role office_boy yang bisa menerima tugas.
     */
    private function officeBoy(mixed $id): ?User
    {
        if (blank($id)) {
            return null;
        }

        abort_unless(array_key_exists((int) $id, ObChecksheetResource::officeBoyOptions()), 422);

        return User::query()->findOrFail((int) $id);
    }

    /**
     * @param  array{created: int, skipped: int}  $result
     */
    private function notifyAssigned(array $result): void
    {
        Notification::make()
            ->title($result['created'].' tugas dibuat')
            ->body($result['skipped'] > 0 ? $result['skipped'].' ruangan sudah ditugaskan pada tanggal itu dan dilewati.' : null)
            ->success()
            ->send();
    }

    /**
     * Tugas milik user yang sedang login untuk hari ini.
     *
     * @return Builder<ObChecksheet>
     */
    private function todayTasks(string $status): Builder
    {
        return ObChecksheet::query()
            ->where('user_id', auth()->id())
            ->where('status', $status)
            ->whereDate('scheduled_date', today())
            ->orderBy('id');
    }

    /**
     * Tugas yang bisa dimulai OB: miliknya hari ini, dan tugas kolam yang belum diambil
     * (tetap tampil walau jadwalnya sudah lewat, sampai ada yang mengambil).
     *
     * @return Builder<ObChecksheet>
     */
    private function startableTasks(): Builder
    {
        return ObChecksheet::query()
            ->where('status', ObChecksheet::STATUS_PENDING)
            ->where(function (Builder $query): void {
                $query
                    ->where(fn (Builder $query) => $query
                        ->where('user_id', auth()->id())
                        ->whereDate('scheduled_date', today()))
                    ->orWhere(fn (Builder $query) => $query
                        ->unclaimed()
                        ->whereDate('scheduled_date', '<=', today()));
            })
            ->orderBy('id');
    }

    /**
     * Ruangan yang fotonya diisi, dikunci per id tugas.
     *
     * @param  array<int|string, array<string, mixed>>  $tasks
     * @return array<int, array{photo: string, notes: ?string}>
     */
    private function submittedPhotos(array $tasks): array
    {
        $submitted = [];

        foreach ($tasks as $task) {
            $photo = ObChecksheetResource::photoPath($task['photo'] ?? null);

            if ($photo === '' || blank($task['id'] ?? null)) {
                continue;
            }

            $submitted[(int) $task['id']] = [
                'photo' => $photo,
                'notes' => filled($task['notes'] ?? null) ? $task['notes'] : null,
            ];
        }

        return $submitted;
    }
}
