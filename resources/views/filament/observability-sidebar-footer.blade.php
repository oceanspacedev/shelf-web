@php
    $items = collect(\App\Support\ObservabilityAccess::filamentNavigationItems())
        ->filter(fn ($item) => $item->isVisible());
@endphp

@if ($items->isNotEmpty())
    <div class="mky-sidebar-group">
        <ul role="list" class="mky-sidebar-group-items">
            @foreach ($items as $item)
                @include('mekaya::livewire.partials.mekaya-sidebar-item', [
                    'item' => $item,
                ])
            @endforeach
        </ul>
    </div>
@endif
