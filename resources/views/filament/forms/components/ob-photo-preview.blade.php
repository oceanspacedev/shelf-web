@php
    $path = $getState();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @if (filled($path))
        <img
            src="{{ \App\Support\StoredFile::browserUrl($path) }}"
            alt="Foto sebelum pembersihan"
            data-lightbox="true"
            title="Klik untuk memperbesar"
            style="height: 10rem; width: 100%; object-fit: cover; border-radius: 0.5rem; cursor: zoom-in;"
        />
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">-</p>
    @endif
</x-dynamic-component>
