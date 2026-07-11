@php
    use Filament\Support\Enums\Width;
    use Filament\Support\Enums\MaxWidth;

    $livewire ??= null;
    $hasTopbar = filament()->hasTopbar();
    $hasNavigation = filament()->hasNavigation();
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $defaultWidth = enum_exists(Width::class) ? Width::SevenExtraLarge : MaxWidth::SevenExtraLarge;
    $maxContentWidth ??= (filament()->getMaxContentWidth() ?? $defaultWidth);

    if (is_string($maxContentWidth)) {
        if (enum_exists(Width::class)) {
            $maxContentWidth = Width::tryFrom($maxContentWidth) ?? $maxContentWidth;
        } elseif (enum_exists(MaxWidth::class)) {
            $maxContentWidth = MaxWidth::tryFrom($maxContentWidth) ?? $maxContentWidth;
        }
    }
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="fi-layout flex h-dvh max-h-dvh overflow-hidden bg-gray-50 dark:bg-gray-950" x-data @keydown.window.escape="$store.sidebar.close()">
        @if ($hasNavigation)
            @persist('sidebar')
                @livewire(filament()->getSidebarLivewireComponent())
            @endpersist
        @endif

        <div class="fi-main-ctn flex min-h-0 w-0 flex-1 flex-col overflow-hidden bg-white ring-1 ring-gray-200 lg:my-2 lg:max-h-[calc(100dvh-1rem)] lg:rounded-tl-xl lg:rounded-bl-xl dark:bg-gray-900 dark:ring-white/20">
            <div class="flex min-h-0 flex-1 flex-col overflow-y-auto">
                @if ($hasTopbar)
                    @livewire(filament()->getTopbarLivewireComponent())
                @endif

                <main
                    @class([
                        'fi-main mky-main flex-1',
                        ($maxContentWidth instanceof \BackedEnum) ? "fi-width-{$maxContentWidth->value}" : $maxContentWidth,
                    ])
                >
                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::CONTENT_START, scopes: $renderHookScopes) }}

                    <div {{ $attributes->twMerge(['class' => 'min-w-0 flex-1']) }}>
                        {{ $slot }}
                    </div>

                    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::CONTENT_END, scopes: $renderHookScopes) }}
                </main>

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
