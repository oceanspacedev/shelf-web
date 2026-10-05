@php
    use Illuminate\View\ComponentAttributeBag;
    use function Filament\Support\generate_icon_html;

    $navigation = filament()->getNavigation();
    $hasDatabaseNotificationsInSidebar = mekaya_database_notifications_enabled()
        && enum_exists(\Filament\Enums\DatabaseNotificationsPosition::class)
        && mekaya_database_notifications_position() === \Filament\Enums\DatabaseNotificationsPosition::Sidebar;
    $hasUserMenuInSidebar = filament()->hasUserMenu()
        && enum_exists(\Filament\Enums\UserMenuPosition::class)
        && method_exists(filament(), 'getUserMenuPosition')
        && filament()->getUserMenuPosition() === \Filament\Enums\UserMenuPosition::Sidebar;
    $hasObservabilityLinks = \App\Support\ObservabilityAccess::allowed(filament()->auth()->user());
    $shouldRenderSidebarFooter = filament()->auth()->check()
        && ($hasDatabaseNotificationsInSidebar || $hasUserMenuInSidebar || $hasObservabilityLinks);
@endphp

<div class="h-full">
    <script>
        if (
            localStorage.getItem('collapsedGroups') === null ||
            localStorage.getItem('collapsedGroups') === 'null'
        ) {
            localStorage.setItem('collapsedGroups', JSON.stringify([]))
        }
    </script>

    <!-- Desktop Sidebar -->
    <aside
        class="mky-si hidden h-full lg:flex lg:shrink-0"
        x-bind:class="{ 'mky-si-collapsed': $store.sidebar.isCollapsed }"
    >
        <div class="mky-si-content h-full flex-1 overflow-hidden transition-[width] duration-200">
            <div class="from-primary-600 to-primary-100 dark:to-primary-600/10 h-1 bg-linear-to-br"></div>

            <div class="flex h-full flex-col">
                <!-- Header / Branding -->
                <div class="py-4 px-6 border-b border-dashed border-gray-200 dark:border-white/20">
                    <div class="relative flex items-center gap-3">
                        <x-mekaya::brand class="size-6 shrink-0" aria-hidden="true" />

                        <div
                            class="min-w-0 truncate overflow-hidden transition-all duration-200"
                            x-show="! $store.sidebar.isCollapsed"
                            x-transition:enter="transition-opacity delay-100 duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition-opacity duration-100"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                        >
                            <h4 class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ mekaya_setting('name') }}
                            </h4>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="flex min-h-0 flex-1 flex-col justify-between">
                    <div class="relative min-h-0 flex-1">
                        <!-- Top fade gradient -->
                        <div
                            class="pointer-events-none absolute top-0 right-0.5 left-0 z-10 h-6 bg-linear-to-b from-gray-50 to-transparent dark:from-gray-950"
                        ></div>

                        <div class="h-full overflow-y-auto">
                            <nav class="mky-si-nav px-3 py-3">
                                @include('mekaya::livewire.partials.mekaya-sidebar-navigation', [
                                    'navigation' => $navigation,
                                ])
                            </nav>
                        </div>

                        <!-- Bottom fade gradient -->
                        <div
                            class="pointer-events-none absolute right-0.5 bottom-0 left-0 z-10 h-6 bg-linear-to-t from-gray-50 to-transparent dark:from-gray-950"
                        ></div>
                    </div>

                    <!-- Footer -->
                    @if ($shouldRenderSidebarFooter)
                        <div class="mky-sidebar border-t border-gray-200 px-3 pt-3 pb-6 dark:border-white/20">
                            @include('filament.mekaya-sidebar-footer')
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </aside>

    <div x-cloak x-show="$store.sidebar.isOpen" class="lg:hidden">
        <div
            x-show="$store.sidebar.isOpen"
            x-transition:enter="transition-opacity duration-300 ease-linear"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300 ease-linear"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="$store.sidebar.close()"
            class="fixed inset-0 z-40 bg-gray-950/50 backdrop-blur-xs dark:bg-gray-950/75"
        ></div>

        <div class="pointer-events-none fixed inset-0 z-50 flex">
            <div
                x-cloak
                x-show="$store.sidebar.isOpen"
                x-transition:enter="transform transition duration-200 ease-in-out"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transform transition duration-200 ease-in-out"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="pointer-events-auto relative flex w-full max-w-xs flex-col bg-white dark:bg-gray-900"
            >
                <div class="from-primary-600 to-primary-100 dark:to-primary-600/10 h-1 bg-linear-to-br"></div>

                <div class="flex h-full flex-col overflow-hidden">
                    <!-- Header / Branding -->
                    <div class="px-3 py-4">
                        <div
                            class="relative flex items-start gap-3 rounded-lg bg-white py-2 shadow-xs ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/20"
                        >
                            <a href="{{ filament()->getUrl() }}" class="shrink-0">
                                <x-mekaya::brand class="size-8" aria-hidden="true" />
                                <span class="absolute inset-0"></span>
                            </a>

                            <div class="truncate">
                                <h4 class="font-heading truncate text-sm/4 font-medium text-gray-900 dark:text-white">
                                    {{ mekaya_setting('name') }}
                                </h4>
                                <span class="text-sm/4 text-gray-500 dark:text-gray-400">
                                    {{ mekaya_setting('email') }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="flex min-h-0 flex-1 flex-col justify-between">
                        <div class="relative min-h-0 flex-1">
                            <!-- Top fade gradient -->
                            <div
                                class="pointer-events-none absolute top-0 right-0.5 left-0 z-10 h-6 bg-linear-to-b from-gray-50 to-transparent dark:from-gray-950"
                            ></div>

                            <div class="h-full overflow-y-auto">
                                <nav class="mky-si-nav px-3 py-3">
                                    @include('mekaya::livewire.partials.mekaya-sidebar-navigation', [
                                        'navigation' => $navigation,
                                    ])
                                </nav>
                            </div>

                            <!-- Bottom fade gradient -->
                            <div
                                class="pointer-events-none absolute right-0.5 bottom-0 left-0 z-10 h-6 bg-linear-to-t from-gray-50 to-transparent dark:from-gray-950"
                            ></div>
                        </div>

                        <!-- Footer -->
                        @if ($shouldRenderSidebarFooter)
                            <div class="mky-sidebar border-t border-gray-200 px-3 pt-3 pb-6 dark:border-white/20">
                                @include('filament.mekaya-sidebar-footer')
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pointer-events-auto z-10 p-2">
                <button
                    x-show="$store.sidebar.isOpen"
                    @click="$store.sidebar.close()"
                    class="flex size-10 items-center justify-center rounded-full bg-gray-900/50 text-white hover:bg-gray-900/70 focus:outline-none"
                >
                    <span class="sr-only">Close sidebar</span>
                    <x-untitledui-x-close class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</div>
