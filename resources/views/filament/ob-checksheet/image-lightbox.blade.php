{{-- Klik gambar bertanda data-lightbox untuk memperbesarnya; Esc atau klik di luar gambar menutup. --}}
{{-- Gaya penting dibuat inline agar tetap benar sebelum CSS tema di-build ulang. --}}
<div
    x-data="{
        open: false,
        src: '',
        show(image) {
            this.src = image.currentSrc || image.src
            this.open = true
        },
        close() {
            this.open = false
        },
    }"
    x-on:click.capture.document="
        const image = $event.target.closest ? $event.target.closest('img[data-lightbox]') : null

        if (! image) {
            return
        }

        $event.preventDefault()
        $event.stopPropagation()
        show(image)
    "
    x-on:keydown.escape.window="close()"
    x-on:click.self="close()"
    x-show="open"
    x-cloak
    x-transition.opacity
    class="fixed inset-0 flex items-center justify-center"
    style="z-index: 100; padding: 1rem; background-color: rgba(3, 7, 18, 0.85);"
    role="dialog"
    aria-modal="true"
    aria-label="Pratinjau foto"
>
    <div style="position: absolute; top: 1rem; right: 1rem; display: flex; gap: 0.5rem;">
        <a
            x-bind:href="src"
            target="_blank"
            rel="noopener"
            style="border-radius: 0.5rem; background-color: rgba(255, 255, 255, 0.12); padding: 0.375rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: #fff;"
        >
            Buka di tab baru
        </a>

        <button
            type="button"
            x-on:click="close()"
            aria-label="Tutup"
            style="border-radius: 0.5rem; background-color: rgba(255, 255, 255, 0.12); padding: 0.375rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: #fff;"
        >
            Tutup ✕
        </button>
    </div>

    <img
        x-bind:src="src"
        alt="Foto diperbesar"
        style="max-height: 90vh; max-width: 100%; object-fit: contain; border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);"
    />
</div>
