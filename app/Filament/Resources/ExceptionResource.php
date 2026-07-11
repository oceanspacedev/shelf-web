<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExceptionResource\Pages;
use BezhanSalleh\FilamentExceptions\Models\Exception;
use BezhanSalleh\FilamentExceptions\Resources\ExceptionResource as BaseExceptionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ExceptionResource extends BaseExceptionResource
{
    protected static bool $isDiscovered = false;

    public static function getModelLabel(): string
    {
        return 'Exception';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Exceptions';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->select([
                'id',
                'path',
                'method',
                'type',
                'code',
                'message',
                'ip',
                'created_at',
            ]))
            ->columns([
                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->colors([
                        'success' => 'GET',
                        'primary' => 'POST',
                        'warning' => fn (string $state): bool => in_array($state, ['PUT', 'PATCH'], true),
                        'danger' => 'DELETE',
                        'gray' => 'OPTIONS',
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('path')
                    ->label('Path')
                    ->searchable()
                    ->wrap()
                    ->limit(40),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                    ->badge()
                    ->color('danger')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('message')
                    ->label('Pesan')
                    ->limit(60)
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip')
                    ->label('IP')
                    ->badge()
                    ->color('gray')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Terjadi pada')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Ringkasan Exception')
                    ->icon('heroicon-o-bug-ant')
                    ->iconColor('danger')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('type')
                                    ->label('Tipe')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->badge()
                                    ->color('danger')
                                    ->formatStateUsing(fn (?string $state): string => class_basename((string) $state))
                                    ->tooltip(fn (Exception $record): string => (string) $record->type),
                                TextEntry::make('code')
                                    ->label('Kode')
                                    ->icon('heroicon-o-hashtag')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Terjadi pada')
                                    ->icon('heroicon-o-calendar')
                                    ->dateTime('d M Y H:i:s'),
                                TextEntry::make('message')
                                    ->label('Pesan')
                                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                                    ->columnSpanFull()
                                    ->wrap()
                                    ->copyable(),
                                TextEntry::make('file')
                                    ->label('File')
                                    ->icon('heroicon-o-document')
                                    ->placeholder('—')
                                    ->fontFamily(FontFamily::Mono)
                                    ->wrap()
                                    ->copyable(),
                                TextEntry::make('line')
                                    ->label('Baris')
                                    ->icon('heroicon-o-bars-3-bottom-left')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Request')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('method')
                                    ->label('Method')
                                    ->icon('heroicon-o-arrow-path-rounded-square')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'GET' => 'success',
                                        'POST' => 'primary',
                                        'PUT', 'PATCH' => 'warning',
                                        'DELETE' => 'danger',
                                        default => 'gray',
                                    }),
                                TextEntry::make('path')
                                    ->label('Path')
                                    ->icon('heroicon-o-link')
                                    ->fontFamily(FontFamily::Mono)
                                    ->wrap()
                                    ->copyable(),
                                TextEntry::make('ip')
                                    ->label('IP')
                                    ->icon('heroicon-o-map-pin')
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->collapsible(),

                Section::make('Headers')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        TextEntry::make('headers')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::formatHeadersHtml($record?->headers ?? []))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (?Exception $record): bool => filled($record?->headers)),

                Section::make('Body')
                    ->icon('heroicon-o-code-bracket')
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::preformatted(self::prettyJson($record?->body) ?? '—'))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (?Exception $record): bool => filled($record?->body)),

                Section::make('Stack Trace')
                    ->icon('heroicon-o-queue-list')
                    ->schema([
                        TextEntry::make('trace')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::preformatted(self::formatTrace($record?->trace ?? [])))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (?Exception $record): bool => filled($record?->trace)),

                Section::make('Queries')
                    ->icon('heroicon-o-circle-stack')
                    ->schema([
                        TextEntry::make('query')
                            ->hiddenLabel()
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::preformatted(self::formatQueries($record?->query ?? [])))
                            ->columnSpanFull(),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (?Exception $record): bool => filled($record?->query)),

                Section::make('Route')
                    ->icon('heroicon-o-map')
                    ->schema([
                        TextEntry::make('route_context')
                            ->label('Context')
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::formatHeadersHtml($record?->route_context ?? []))
                            ->columnSpanFull()
                            ->visible(fn (?Exception $record): bool => filled($record?->route_context)),
                        TextEntry::make('route_parameters')
                            ->label('Parameters')
                            ->html()
                            ->getStateUsing(fn (?Exception $record): string => self::preformatted(self::prettyJson($record?->route_parameters) ?? '—'))
                            ->columnSpanFull()
                            ->visible(fn (?Exception $record): bool => filled($record?->route_parameters)),
                    ])
                    ->collapsed()
                    ->collapsible()
                    ->visible(fn (?Exception $record): bool => filled($record?->route_context) || filled($record?->route_parameters)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExceptions::route('/'),
            'view' => Pages\ViewException::route('/{record}'),
        ];
    }

    /**
     * @param  array<string, array<int, string>|string>  $headers
     */
    protected static function formatHeadersHtml(array $headers): string
    {
        if ($headers === []) {
            return '—';
        }

        $rows = collect($headers)
            ->map(function ($value, $key): string {
                $text = is_array($value) ? implode(', ', $value) : (string) $value;

                return '<div class="grid gap-1 border-b border-gray-200 py-2 last:border-0 dark:border-white/10 sm:grid-cols-[12rem_minmax(0,1fr)]">'
                    .'<div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">'.e((string) $key).'</div>'
                    .'<div class="min-w-0 break-all font-mono text-sm text-gray-950 dark:text-gray-100">'.e($text).'</div>'
                    .'</div>';
            })
            ->implode('');

        return '<div class="min-w-0">'.$rows.'</div>';
    }

    protected static function preformatted(string $value): string
    {
        return '<pre class="max-w-full min-w-0 whitespace-pre-wrap break-all rounded-lg bg-gray-50 p-3 font-mono text-sm text-gray-950 ring-1 ring-gray-200 dark:bg-gray-950 dark:text-gray-100 dark:ring-white/10">'
            .e($value).
            '</pre>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $trace
     */
    protected static function formatTrace(array $trace): string
    {
        if ($trace === []) {
            return 'No stack trace available.';
        }

        return collect($trace)
            ->values()
            ->map(function (array $frame, int $index): string {
                $file = $frame['file'] ?? '[internal function]';
                $line = $frame['line'] ?? 0;
                $class = $frame['class'] ?? null;
                $type = $frame['type'] ?? '';
                $function = $frame['function'] ?? null;

                $call = $class && $function
                    ? "{$class}{$type}{$function}()"
                    : ($function ? "{$function}()" : '');

                return trim("#{$index} {$file}:{$line} {$call}");
            })
            ->implode("\n");
    }

    /**
     * @param  array<int, array<string, mixed>>  $queries
     */
    protected static function formatQueries(array $queries): string
    {
        return collect($queries)
            ->map(function (array $query, int $index): string {
                $connection = $query['connectionName'] ?? $query['connection'] ?? 'default';
                $time = $query['time'] ?? 0;
                $sql = $query['sql'] ?? '';

                return '#'.($index + 1)." [{$connection}] ({$time} ms)\n{$sql}";
            })
            ->implode("\n\n");
    }

    protected static function prettyJson(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return Str::of(json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '')
            ->toString();
    }
}
