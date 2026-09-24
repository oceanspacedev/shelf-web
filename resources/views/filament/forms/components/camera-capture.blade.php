@php
    $fieldWrapperView = $getFieldWrapperView();
    $statePath = $getStatePath();
    $storedPath = $getState();
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

            get imageUrl() {
                if (!this.state) return '';
                if (this.state.startsWith('http://') || this.state.startsWith('https://')) {
                    return this.state;
                }
                if (this.state === this.uploadedPath) return this.uploadedUrl;
                return this.state === this.$el.dataset.storedPath ? this.$el.dataset.storedUrl : '';
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

            // Tangani foto dari kamera HP dengan kompresi otomatis & preview review
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
                    console.error('Gagal memproses foto HP:', err);
                    alert('Gagal memproses foto kamera: ' + err.message);
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
        style="position: relative; width: 100%;"
    >
        <!-- Hidden input for form submission -->
        <input type="hidden" :name="statePath" :value="state">

        <!-- Native Camera input (Khusus HP dengan capture="environment") -->
        <input
            type="file"
            x-ref="nativeCameraInput"
            accept="image/*"
            capture="environment"
            style="display: none;"
            @change="handleNativeFile($event)"
        >

        <!-- TAMPILAN JIKA SUDAH ADA FOTO -->
        <div
            x-show="state"
            style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: flex; flex-direction: column; gap: 12px;"
        >
            <div style="position: relative; width: 100%; height: 180px; overflow: hidden; border-radius: 8px; background-color: #f1f5f9;">
                <img
                    :src="imageUrl"
                    alt="Hasil Foto Kamera"
                    style="width: 100%; height: 100%; object-fit: cover; display: block;"
                />
                <div style="position: absolute; top: 8px; right: 8px; background: rgba(16, 185, 129, 0.95); color: #ffffff; border-radius: 9999px; padding: 4px 8px; font-size: 11px; font-weight: 600; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">
                    <svg style="width: 13px; height: 13px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Tersimpan
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 8px;">
                <button
                    type="button"
                    @click="openCamera()"
                    style="display: inline-flex; align-items: center; gap: 6px; background-color: #2563eb; color: #ffffff; font-size: 12px; font-weight: 600; padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer; transition: background-color 0.15s;"
                    onmouseover="this.style.backgroundColor='#1d4ed8'"
                    onmouseout="this.style.backgroundColor='#2563eb'"
                >
                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Foto Ulang
                </button>
                <button
                    type="button"
                    @click="clearPhoto()"
                    style="display: inline-flex; align-items: center; background: #ffffff; color: #dc2626; font-size: 12px; font-weight: 500; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; cursor: pointer;"
                >
                    Hapus
                </button>
            </div>
        </div>

        <!-- TAMPILAN JIKA BELUM ADA FOTO (KOTAK KAMERA RAPI, BERSIH & PRESISI) -->
        <div
            x-show="!state"
            @click="openCamera()"
            style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 24px 16px; text-align: center !important; display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; min-height: 120px; cursor: pointer; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.03);"
            onmouseover="this.style.borderColor='#3b82f6'; this.style.backgroundColor='#eff6ff';"
            onmouseout="this.style.borderColor='#cbd5e1'; this.style.backgroundColor='#f8fafc';"
        >
            <div style="width: 44px; height: 44px; border-radius: 9999px; background-color: #ffffff; color: #2563eb; display: flex !important; align-items: center !important; justify-content: center !important; margin: 0 auto 10px auto !important; align-self: center !important; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </div>

            <div
                style="display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 8px; background-color: #2563eb; color: #ffffff; font-size: 13px; font-weight: 600; padding: 8px 22px; border-radius: 8px; box-shadow: 0 2px 4px rgba(37,99,235,0.25); margin: 0 auto !important; align-self: center !important;"
            >
                <span>Buka Kamera</span>
            </div>
        </div>

        <!-- MODAL LIVE VIEWFINDER KAMERA & REVIEW FOTO (RESPONSIF HP & DESKTOP) -->
        <div
            x-show="isCameraOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            style="position: fixed; inset: 0; z-index: 99999; background-color: rgba(0, 0, 0, 0.88); display: flex; align-items: center; justify-content: center; padding: 12px; backdrop-filter: blur(4px); overflow-y: auto;"
            @keydown.escape.window="closeCamera()"
        >
            <div style="position: relative; width: 100%; max-width: 480px; max-height: 94vh; overflow: hidden; border-radius: 16px; background-color: #111827; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.8); border: 1px solid rgba(255, 255, 255, 0.15); display: flex; flex-direction: column;">
                <!-- Header Modal -->
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1f2937; padding: 12px 16px; color: #ffffff; background-color: #111827; flex-shrink: 0;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span
                            style="display: inline-block; width: 10px; height: 10px; border-radius: 9999px; transition: all 0.2s;"
                            :style="capturedImage ? 'background-color: #10b981; box-shadow: 0 0 8px #10b981;' : 'background-color: #ef4444; box-shadow: 0 0 8px #ef4444;'"
                        ></span>
                        <h3
                            style="font-size: 14px; font-weight: 600; color: #ffffff; margin: 0;"
                            x-text="capturedImage ? 'Review Hasil Foto' : 'Kamera Aktif (Foto Langsung)'"
                        ></h3>
                    </div>
                    <button
                        type="button"
                        @click="closeCamera()"
                        style="background: transparent; border: none; color: #9ca3af; cursor: pointer; padding: 6px; border-radius: 6px; display: flex; align-items: center; justify-content: center;"
                        title="Tutup"
                    >
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Video Stream & Preview Area (Aspect ratio pas di layar HP & PC) -->
                <div style="position: relative; width: 100%; max-height: 55vh; min-height: 260px; aspect-ratio: 4/3; overflow: hidden; background-color: #000000; display: flex; align-items: center; justify-content: center;">
                    <!-- Live Camera Stream (tampil saat belum jepret) -->
                    <video
                        x-show="!capturedImage"
                        x-ref="videoElement"
                        autoplay
                        playsinline
                        muted
                        style="width: 100%; height: 100%; object-fit: cover;"
                    ></video>

                    <!-- Preview Hasil Foto (tampil setelah jepret sebelum disimpan) -->
                    <img
                        x-show="capturedImage"
                        :src="capturedImage"
                        alt="Preview Foto"
                        style="width: 100%; height: 100%; object-fit: contain; background-color: #000000;"
                    />

                    <!-- Canvas tersembunyi untuk proses snapshot & kompresi -->
                    <canvas x-ref="canvasElement" style="display: none;"></canvas>

                    <!-- Loading Overlay saat upload -->
                    <div
                        x-show="isUploading"
                        style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; background-color: rgba(0, 0, 0, 0.75); color: #ffffff; z-index: 10;"
                    >
                        <svg style="width: 36px; height: 36px; animation: spin 1s linear infinite;" fill="none" viewBox="0 0 24 24">
                            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p style="margin-top: 8px; font-size: 13px; font-weight: 500;">Sedang menyimpan foto...</p>
                    </div>
                </div>

                <!-- Footer / Controls (Touch friendly di HP & Desktop) -->
                <div style="background-color: #030712; padding: 16px 20px; border-top: 1px solid #1f2937; flex-shrink: 0; width: 100%;">
                    <!-- TAMPILAN KONTROL SAAT LIVE STREAM (BELUM AMBIL FOTO) -->
                    <div
                        x-show="!capturedImage"
                        style="display: grid !important; grid-template-columns: 1fr auto 1fr !important; align-items: center !important; width: 100% !important; gap: 12px;"
                    >
                        <!-- Kolom 1 Kiri: Tombol Balik Kamera (Depan / Belakang) -->
                        <div style="display: flex !important; justify-content: flex-start !important; align-items: center !important;">
                            <button
                                type="button"
                                @click="switchCamera()"
                                style="background-color: #1f2937; border: 1px solid #374151; color: #d1d5db; width: 44px; height: 44px; min-width: 44px; border-radius: 9999px; cursor: pointer; display: flex !important; align-items: center !important; justify-content: center !important; transition: all 0.2s;"
                                title="Balik Kamera (Depan / Belakang)"
                            >
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>

                        <!-- Kolom 2 Tengah: Tombol Shutter Utama (AMBIL FOTO) -->
                        <div style="display: flex !important; justify-content: center !important; align-items: center !important;">
                            <button
                                type="button"
                                @click="takeSnapshot()"
                                style="background-color: #dc2626; width: 68px; height: 68px; min-width: 68px; border-radius: 9999px; border: 4px solid #ffffff; box-shadow: 0 4px 14px rgba(220, 38, 38, 0.5); display: flex !important; align-items: center !important; justify-content: center !important; cursor: pointer; transition: transform 0.1s; margin: 0 auto !important;"
                                onmouseover="this.style.transform='scale(1.06)'"
                                onmouseout="this.style.transform='scale(1)'"
                                title="Ambil Foto"
                            >
                                <div style="width: 44px; height: 44px; border-radius: 9999px; background-color: rgba(255, 255, 255, 0.3); display: flex !important; align-items: center !important; justify-content: center !important;">
                                    <svg style="width: 22px; height: 22px; color: #ffffff;" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="6"></circle>
                                    </svg>
                                </div>
                            </button>
                        </div>

                        <!-- Kolom 3 Kanan: Tombol Batal -->
                        <div style="display: flex !important; justify-content: flex-end !important; align-items: center !important;">
                            <button
                                type="button"
                                @click="closeCamera()"
                                style="background: transparent; border: 1px solid #374151; color: #9ca3af; padding: 8px 16px; min-height: 40px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; display: inline-flex !important; align-items: center !important; justify-content: center !important;"
                            >
                                Batal
                            </button>
                        </div>
                    </div>

                    <!-- TAMPILAN KONTROL SETELAH AMBIL FOTO (REVIEW DULU SEBELUM SIMPAN) -->
                    <div
                        x-show="capturedImage"
                        style="display: grid !important; grid-template-columns: 1fr 1fr !important; align-items: center !important; width: 100% !important; gap: 12px !important;"
                    >
                        <!-- Tombol Foto Ulang -->
                        <button
                            type="button"
                            @click="retakePhoto()"
                            style="width: 100%; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px; background-color: #374151; color: #f3f4f6; border: none; min-height: 48px; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer;"
                            :disabled="isUploading"
                        >
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Foto Ulang
                        </button>

                        <!-- Tombol Gunakan Foto Ini (Simpan) -->
                        <button
                            type="button"
                            @click="confirmAndUploadPhoto()"
                            style="width: 100%; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px; background-color: #16a34a; color: #ffffff; border: none; min-height: 48px; padding: 10px 14px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; box-shadow: 0 2px 6px rgba(22, 163, 74, 0.4);"
                            :disabled="isUploading"
                        >
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span x-text="isUploading ? 'Menyimpan...' : 'Gunakan Foto Ini'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
