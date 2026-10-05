@include('filament.observability-sidebar-footer')

@if ($hasDatabaseNotificationsInSidebar && ($dbNotificationsComponent = mekaya_database_notifications_component()))
    @livewire($dbNotificationsComponent, [
        'lazy' => mekaya_database_notifications_is_lazy(),
    ])
@endif

@if ($hasUserMenuInSidebar)
    <x-filament-panels::user-menu />
@endif
