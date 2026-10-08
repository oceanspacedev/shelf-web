@php
    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $storedPath = $getState();

    if (is_array($storedPath)) {
        $storedPath = collect($storedPath)->first(fn (mixed $value): bool => is_string($value) && $value !== '');
    }

    $storedPath = is_string($storedPath) ? $storedPath : null;
    $storedUrl = filled($storedPath)
        ? (filter_var($storedPath, FILTER_VALIDATE_URL) ? $storedPath : \App\Support\StoredFile::url($storedPath))
        : '';
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
>
    <div
        data-stored-path="{{ $storedPath }}"
        data-stored-url="{{ $storedUrl }}"
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            statePath: @js($statePath),
            folder: @js($getFolder()),
            uploadUrl: '/admin/camera-upload',
            csrfToken: @js(csrf_token()),
            isCameraOpen: false,
            isUploading: false,
            capturedImage: null,
            mediaStream: null,
            facingMode: 'environment',
            uploadedPath: null,
            uploadedUrl: null,
            localPreview: @js(config('filesystems.disks.public.driver') !== 's3'),

            storageUrl(value) {
                if (!value || typeof value !== 'string') return '';
                if (value.startsWith('/storage/')) return value;
                if (value.startsWith('http://') || value.startsWith('https://')) {
                    if (!this.localPreview) return value;
                    try {
                        const parsed = new URL(value);
                        if (parsed.pathname.startsWith('/storage/')) {
                            return parsed.pathname + parsed.search;
                        }
                    } catch (e) {}
                    return value;
                }
                return '/storage/' + value.replace(/^\/+/, '');
            },

            get imageUrl() {
                if (!this.state || typeof this.state !== 'string') return '';
                if (this.localPreview) return this.storageUrl(this.state);
                if (this.state === this.uploadedPath && this.uploadedUrl) return this.uploadedUrl;
                return this.$el.dataset.storedUrl || '';
            },

            async openCamera() {
                this.capturedImage = null;
                this.isUploading = false;

                // Cek apakah browser mendukung getUserMedia pada secure context (desktop / HTTPS)
                if (window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    try {
                        this.isCameraOpen = true;
                        await this.startStream();
                        return;
                    } catch (err) {
                        console.warn('getUserMedia error, fallback to native camera:', err);
                    }
                }

                // Fallback untuk HP / HTTP: buka kamera bawaan perangkat
                this.isCameraOpen = false;
                if (this.$refs.nativeCameraInput) {
                    this.$refs.nativeCameraInput.click();
                }
            },

            openGallery() {
                this.capturedImage = null;
                this.isUploading = false;
                this.isCameraOpen = false;
                if (this.$refs.galleryInput) {
                    this.$refs.galleryInput.click();
                }
            },

            async startStream() {
                if (this.mediaStream) {
                    this.stopStream();
                }

                try {
                    const constraints = {
                        video: {
                            facingMode: { ideal: this.facingMode },
                            width: { ideal: 1280 },
                            height: { ideal: 960 }
                        },
                        audio: false
                    };

                    this.mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
                    if (this.$refs.videoElement) {
                        this.$refs.videoElement.srcObject = this.mediaStream;
                    }
                } catch (error) {
                    console.error('Gagal mengakses kamera:', error);
                    this.closeCamera();
                    if (this.$refs.nativeCameraInput) {
                        this.$refs.nativeCameraInput.click();
                    }
                }
            },

            stopStream() {
                if (this.mediaStream) {
                    this.mediaStream.getTracks().forEach(track => track.stop());
                    this.mediaStream = null;
                }
            },

            switchCamera() {
                this.facingMode = this.facingMode === 'environment' ? 'user' : 'environment';
                this.startStream();
            },

            closeCamera() {
                this.stopStream();
                this.isCameraOpen = false;
                this.capturedImage = null;
                this.isUploading = false;
            },

            // Tahap 1: Ambil foto snapshot dari live stream
            takeSnapshot() {
                const video = this.$refs.videoElement;
                const canvas = this.$refs.canvasElement;

                if (!video || !video.videoWidth) {
                    alert('Kamera belum siap, tunggu sebentar...');
                    return;
                }

                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                this.capturedImage = canvas.toDataURL('image/jpeg', 0.85);
                this.stopStream();
            },

            // Foto ulang: Kembali ke live stream (Desktop) atau buka kembali kamera HP (Mobile)
            retakePhoto() {
                this.capturedImage = null;
                if (this.mediaStream || (window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia)) {
                    this.startStream();
                } else {
                    this.isCameraOpen = false;
                    if (this.$refs.nativeCameraInput) {
                        this.$refs.nativeCameraInput.click();
                    }
                }
            },

            // Tahap 2: Konfirmasi dan simpan foto
            async confirmAndUploadPhoto() {
                if (!this.capturedImage) {
                    return;
                }

                this.isUploading = true;
                await this.uploadBase64(this.capturedImage);
            },

            async uploadBlob(blob) {
                const formData = new FormData();
                formData.append('photo', blob, 'cam_' + Date.now() + '.jpg');
                formData.append('folder', this.folder);

                await this.sendUploadRequest(formData);
            },

            async uploadBase64(dataUrl) {
                const formData = new FormData();
                formData.append('image_data', dataUrl);
                formData.append('folder', this.folder);

                await this.sendUploadRequest(formData);
            },

            async sendUploadRequest(bodyData) {
                try {
                    const response = await fetch(this.uploadUrl, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: bodyData
                    });

                    if (!response.ok) {
                        const errData = await response.json().catch(() => null);
                        throw new Error(errData?.message || 'Server error ' + response.status);
                    }

                    const result = await response.json();

                    if (result.success && result.path) {
                        this.uploadedPath = result.path;
                        this.uploadedUrl = result.url;
                        this.state = result.path;
                        if (typeof $wire !== 'undefined' && this.statePath) {
                            $wire.set(this.statePath, result.path);
                        }
                        this.closeCamera();
                    } else {
                        alert(result.message || 'Gagal menyimpan foto kamera.');
                    }
                } catch (err) {
                    console.error('Upload foto gagal:', err);
                    alert('Terjadi kesalahan saat mengunggah foto kamera: ' + (err.message || ''));
                } finally {
                    this.isUploading = false;
                }
            },

            // Tangani foto dari kamera HP / galeri dengan kompresi otomatis & preview review
            async handleNativeFile(event) {
                const file = event.target.files[0];
                if (!file) return;

                this.isUploading = true;

                try {
                    // Kompresi foto sisi client (canvas) maksimal 1280px agar ringan dan cepat di HP
                    const compressedDataUrl = await new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = new Image();
                            img.onload = () => {
                                const canvas = this.$refs.canvasElement || document.createElement('canvas');
                                let width = img.width;
                                let height = img.height;
                                const maxDim = 1280;

                                if (width > maxDim || height > maxDim) {
                                    if (width > height) {
                                        height = Math.round((height * maxDim) / width);
                                        width = maxDim;
                                    } else {
                                        width = Math.round((width * maxDim) / height);
                                        height = maxDim;
                                    }
                                }

                                canvas.width = width;
                                canvas.height = height;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(img, 0, 0, width, height);

                                resolve(canvas.toDataURL('image/jpeg', 0.85));
                            };
                            img.onerror = () => reject(new Error('Gagal memproses gambar kamera.'));
                            img.src = e.target.result;
                        };
                        reader.onerror = () => reject(new Error('Gagal membaca file foto.'));
                        reader.readAsDataURL(file);
                    });

                    // Tampilkan foto di layar pratinjau (preview modal) agar user bisa review
                    this.capturedImage = compressedDataUrl;
                    this.isCameraOpen = true;
                } catch (err) {
                    console.error('Gagal memproses foto:', err);
                    alert('Gagal memproses foto: ' + err.message);
                } finally {
                    this.isUploading = false;
                    event.target.value = '';
                }
            },

            clearPhoto() {
                if (confirm('Hapus foto ini dan ambil ulang?')) {
                    this.state = null;
                    if (typeof $wire !== 'undefined' && this.statePath) {
                        $wire.set(this.statePath, null);
                    }
                }
            }
        }"
        class="fi-fo-camera-capture relative w-full"
    >
        <!-- Hidden input for form submission -->
        <input type="hidden" :name="statePath" :value="state">

        <!-- Native Camera input (Khusus HP dengan capture="environment") -->
        <input
            type="file"
            x-ref="nativeCameraInput"
            accept="image/*"
            capture="environment"
            class="hidden"
            @change="handleNativeFile($event)"
        >

        <!-- Gallery / File input (Unggah file dari galeri atau penyimpanan lokal) -->
        <input
            type="file"
            x-ref="galleryInput"
            accept="image/*"
            class="hidden"
            @change="handleNativeFile($event)"
        >

        <!-- TAMPILAN JIKA SUDAH ADA FOTO (SINKRON DENGAN FILAMENT FILEUPLOAD IMAGE PREVIEW) -->
        <div
            x-show="state"
            x-cloak
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs transition-all dark:border-white/10 dark:bg-white/5"
        >
            <div class="relative w-full h-56 sm:h-64 overflow-hidden bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
                <img
                    :src="imageUrl"
                    alt="Hasil Foto Kamera"
                    class="w-full h-full object-cover"
                />
                <div class="absolute top-3 right-3 shadow-xs">
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                        Tersimpan
                    </x-filament::badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2.5 p-3 border-t border-gray-100 bg-gray-50/60 dark:border-white/5 dark:bg-white/[0.02]">
                <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400 min-w-0">
                    <x-filament::icon icon="heroicon-m-photo" class="size-4 shrink-0 text-gray-400 dark:text-gray-500" />
                    <span class="truncate">Foto bukti berhasil tersimpan</span>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-camera"
                        @click="openCamera()"
                    >
                        Foto Ulang
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-photo"
                        @click="openGallery()"
                    >
                        Ganti dari Galeri
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        size="sm"
                        color="danger"
                        icon="heroicon-m-trash"
                        @click="clearPhoto()"
                    >
                        Hapus
                    </x-filament::button>
                </div>
            </div>
        </div>

        <!-- TAMPILAN JIKA BELUM ADA FOTO (SINKRON DENGAN FILAMENT FILEUPLOAD DROPZONE) -->
        <div
            x-show="!state"
            class="group relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50/50 p-6 sm:p-8 text-center transition-all duration-150 hover:border-primary-500 hover:bg-primary-50/20 dark:border-white/15 dark:bg-white/5 dark:hover:border-primary-400 dark:hover:bg-primary-950/20"
        >
            <div
                class="mb-3 flex size-12 items-center justify-center rounded-full bg-primary-50 text-primary-600 ring-1 ring-primary-500/15 shadow-xs transition-transform duration-150 group-hover:scale-105 dark:bg-primary-950/50 dark:text-primary-400 dark:ring-primary-400/20"
            >
                <x-filament::icon icon="heroicon-o-camera" class="size-6" />
            </div>

            <div class="flex flex-wrap items-center justify-center gap-2.5 mb-2">
                <x-filament::button
                    type="button"
                    icon="heroicon-m-camera"
                    color="primary"
                    size="sm"
                    @click="openCamera()"
                >
                    {{ $getCaptureLabel() }}
                </x-filament::button>

                <x-filament::button
                    type="button"
                    icon="heroicon-m-photo"
                    color="gray"
                    size="sm"
                    @click="openGallery()"
                >
                    Pilih dari Galeri
                </x-filament::button>
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm mt-1">
                Buka kamera langsung untuk bukti ruangan, atau pilih foto dari perangkat (Maks. 10MB)
            </p>
        </div>

        <!-- MODAL LIVE VIEWFINDER KAMERA & REVIEW FOTO -->
        <div
            x-show="isCameraOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-gray-950/80 backdrop-blur-sm overflow-y-auto"
            @keydown.escape.window="closeCamera()"
        >
            <div
                class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-gray-900 border border-gray-800 shadow-2xl text-white flex flex-col my-auto max-h-[92vh]"
                @click.away="closeCamera()"
            >
                <!-- Header Modal -->
                <div class="flex items-center justify-between px-4 py-3 sm:px-5 sm:py-3.5 border-b border-gray-800 bg-gray-900/90 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="size-2.5 rounded-full transition-all duration-200"
                            :class="capturedImage ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]' : 'bg-rose-500 animate-pulse shadow-[0_0_8px_rgba(244,63,94,0.8)]'"
                        ></span>
                        <h3
                            class="text-sm font-semibold text-white tracking-tight"
                            x-text="capturedImage ? 'Review Hasil Foto' : 'Kamera Aktif (Foto Langsung)'"
                        ></h3>
                    </div>

                    <button
                        type="button"
                        @click="closeCamera()"
                        class="rounded-lg p-1.5 text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
                        title="Tutup"
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" class="size-5" />
                    </button>
                </div>

                <!-- Video Stream & Preview Area -->
                <div class="relative w-full aspect-4/3 min-h-[260px] max-h-[55vh] overflow-hidden bg-black flex items-center justify-center">
                    <!-- Live Camera Stream -->
                    <video
                        x-show="!capturedImage"
                        x-ref="videoElement"
                        autoplay
                        playsinline
                        muted
                        class="w-full h-full object-cover"
                    ></video>

                    <!-- Framing Brackets Overlay -->
                    <div x-show="!capturedImage" class="pointer-events-none absolute inset-6 border border-white/20 rounded-xl">
                        <div class="absolute -top-1 -left-1 size-4 border-t-2 border-l-2 border-white rounded-tl"></div>
                        <div class="absolute -top-1 -right-1 size-4 border-t-2 border-r-2 border-white rounded-tr"></div>
                        <div class="absolute -bottom-1 -left-1 size-4 border-b-2 border-l-2 border-white rounded-bl"></div>
                        <div class="absolute -bottom-1 -right-1 size-4 border-b-2 border-r-2 border-white rounded-br"></div>
                    </div>

                    <!-- Preview Hasil Foto -->
                    <img
                        x-show="capturedImage"
                        :src="capturedImage"
                        alt="Preview Foto"
                        class="w-full h-full object-contain bg-black"
                    />

                    <!-- Canvas tersembunyi -->
                    <canvas x-ref="canvasElement" class="hidden"></canvas>

                    <!-- Tombol Balik Kamera (Depan / Belakang) di dalam viewfinder -->
                    <button
                        x-show="!capturedImage"
                        type="button"
                        @click="switchCamera()"
                        class="absolute top-3 right-3 p-2.5 rounded-full bg-gray-900/70 hover:bg-gray-900 text-white backdrop-blur-md transition-all shadow-md"
                        title="Balik Kamera (Depan / Belakang)"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-path" class="size-5" />
                    </button>

                    <!-- Loading Overlay saat upload -->
                    <div
                        x-show="isUploading"
                        class="absolute inset-0 flex flex-col items-center justify-center bg-gray-950/80 text-white z-20 backdrop-blur-xs"
                    >
                        <x-filament::loading-indicator class="size-8 text-primary-500 mb-2" />
                        <p class="text-xs font-medium text-gray-200">Sedang menyimpan foto...</p>
                    </div>
                </div>

                <!-- Footer / Controls -->
                <div class="bg-gray-950 p-4 sm:p-5 border-t border-gray-800 shrink-0">
                    <!-- Kontrol Saat Live Stream -->
                    <div
                        x-show="!capturedImage"
                        class="grid grid-cols-3 items-center w-full"
                    >
                        <div class="flex justify-start">
                            <x-filament::button
                                type="button"
                                color="gray"
                                size="sm"
                                @click="openGallery()"
                            >
                                Galeri
                            </x-filament::button>
                        </div>

                        <!-- Shutter Button -->
                        <div class="flex justify-center">
                            <button
                                type="button"
                                @click="takeSnapshot()"
                                class="size-16 rounded-full border-4 border-white/80 p-1 flex items-center justify-center hover:scale-105 active:scale-95 transition-transform duration-100 cursor-pointer shadow-lg bg-transparent"
                                title="Ambil Foto"
                            >
                                <div class="size-12 rounded-full bg-white shadow-inner"></div>
                            </button>
                        </div>

                        <div class="flex justify-end">
                            <x-filament::button
                                type="button"
                                color="gray"
                                size="sm"
                                @click="closeCamera()"
                            >
                                Batal
                            </x-filament::button>
                        </div>
                    </div>

                    <!-- Kontrol Saat Review Foto -->
                    <div
                        x-show="capturedImage"
                        class="grid grid-cols-2 gap-3 items-center w-full"
                    >
                        <x-filament::button
                            type="button"
                            color="gray"
                            icon="heroicon-m-arrow-path"
                            @click="retakePhoto()"
                            x-bind:disabled="isUploading"
                            class="w-full"
                        >
                            Foto Ulang
                        </x-filament::button>

                        <x-filament::button
                            type="button"
                            color="primary"
                            icon="heroicon-m-check"
                            @click="confirmAndUploadPhoto()"
                            x-bind:disabled="isUploading"
                            class="w-full"
                        >
                            <span x-show="!isUploading">Gunakan Foto Ini</span>
                            <span x-show="isUploading">Menyimpan...</span>
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
