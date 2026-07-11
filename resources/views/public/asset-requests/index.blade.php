<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>FORM PENGAJUAN ASET</title>

    @include('public.asset-requests.partials.head-assets')
</head>
<body>

<div class="form-wrapper">

    <div class="header-card">
        <div class="header-banner"></div>
        <div class="header-body">
            <h1 class="header-title">Form Pengajuan Aset</h1>
            <p class="header-desc">
                Isi formulir ini untuk mengajukan penarikan, perbaikan, atau pengadaan aset baru.
                Pengajuan yang berhasil dikirim akan langsung masuk ke alur persetujuan (approval flow).
            </p>
            <p class="header-required-note">* Menandakan pertanyaan yang wajib diisi</p>
        </div>
    </div>

    <form id="asset-request-form" enctype="multipart/form-data" novalidate>

        <!-- Jenis Pengajuan -->
        <div class="field-card">
            <label class="field-label" for="type">
                Jenis Pengajuan <span class="req">*</span>
            </label>
            <div class="gf-select-wrapper">
                <select name="type" id="type" class="gf-input" onchange="handleTypeChange(this.value)" required>
                    <option value="pengadaan" selected>Pengadaan Aset Baru</option>
                    <option value="penarikan">Penarikan Aset</option>
                    <option value="perbaikan">Perbaikan Aset</option>
                </select>
            </div>
            <p class="error-msg" id="err-type">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Jenis pengajuan wajib dipilih.
            </p>
        </div>

        <!-- Nama Pemohon -->
        <div class="field-card" id="pemohon-card">
            <label class="field-label">
                Nama Pemohon <span class="req">*</span>
            </label>
            <p class="field-desc" id="pemohon-desc">Pilih pemohon dari daftar. Jika belum terdaftar, pilih <strong>Tidak ada / Lainnya</strong> lalu lengkapi data pemohon.</p>
            <input type="hidden" name="user_id" id="user_id">
            <div class="custom-select-container">
                <button type="button" class="custom-select-trigger" id="user-trigger" onclick="toggleDropdown('user')" aria-haspopup="listbox">
                    <span id="user-display" class="select-value placeholder">Pilih nama pemohon...</span>
                </button>
                <div class="custom-dropdown" id="user-dropdown" role="listbox">
                    <div class="search-box">
                        <input type="text" placeholder="Cari nama..." id="user-search-input" oninput="filterDropdown('user', this.value)" autocomplete="off">
                    </div>
                    <div class="option-list" id="user-option-list">
                        <div class="option-item option-special"
                            data-value="other"
                            data-label="Tidak ada / Lainnya"
                            onclick="selectApplicantOther()">
                            Tidak ada / Lainnya
                        </div>
                        @foreach($users as $user)
                            <div class="option-item"
                                data-value="{{ $user->id }}"
                                data-label="{{ $user->name }}"
                                onclick="selectApplicantUser('{{ $user->id }}')">
                                {{ $user->name }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="pemohon-subfield hidden" id="badan-usaha-wrap">
                <label class="field-label" for="business_entity_id">Badan Usaha <span class="req">*</span></label>
                <p class="field-desc" id="badan-usaha-desc">Badan usaha mengikuti data pemohon yang dipilih.</p>
                <div class="gf-select-wrapper">
                    <select name="business_entity_id" id="business_entity_id" class="gf-input">
                        <option value="">Pilih badan usaha</option>
                        @foreach($businessEntities as $businessEntity)
                            <option value="{{ $businessEntity->id }}">{{ $businessEntity->name }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="error-msg" id="err-business_entity_id">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    Badan usaha wajib dipilih.
                </p>
            </div>

            <div class="pemohon-subfield hidden" id="posisi-wrap">
                <label class="field-label">Posisi</label>
                <p class="field-desc" id="posisi-desc">Posisi mengikuti data pemohon yang dipilih.</p>
                <input type="hidden" name="job_title_id" id="job_title_id">
                <div class="custom-select-container" id="posisi-dropdown-wrap">
                    <button type="button" class="custom-select-trigger" id="posisi-trigger" onclick="toggleDropdown('posisi')" aria-haspopup="listbox">
                        <span id="posisi-display" class="select-value placeholder">Pilih posisi...</span>
                    </button>
                    <div class="custom-dropdown" id="posisi-dropdown" role="listbox">
                        <div class="search-box">
                            <input type="text" placeholder="Cari posisi..." id="posisi-search-input" oninput="filterDropdown('posisi', this.value)" autocomplete="off">
                        </div>
                        <div class="option-list" id="posisi-option-list">
                            @forelse($jobTitles as $jobTitle)
                                <div class="option-item"
                                    data-value="{{ $jobTitle->id }}"
                                    data-label="{{ $jobTitle->title }}"
                                    onclick='selectPosisiJob(@json((string) $jobTitle->id), @json($jobTitle->title))'>
                                    {{ $jobTitle->title }}
                                </div>
                            @empty
                                <div class="dropdown-empty-state">Belum ada daftar posisi. Pilih <strong>Lainnya</strong> lalu isi manual.</div>
                            @endforelse
                            <div class="option-item option-special"
                                data-value="other"
                                data-label="Lainnya"
                                onclick="selectPosisiOther()">
                                Lainnya
                            </div>
                        </div>
                    </div>
                </div>

                <div class="inline-field-wrap hidden" id="posisi-manual-wrap">
                    <label class="field-label" for="custom_job_title">Posisi (Manual) <span class="req">*</span></label>
                    <input type="text" name="custom_job_title" id="custom_job_title" class="gf-input" placeholder="Masukkan posisi / jabatan" autocomplete="off">
                </div>

                <p class="error-msg" id="err-custom_job_title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    Posisi lainnya wajib diisi.
                </p>
            </div>

            <div class="inline-field-wrap hidden" id="pemohon-details-wrap">
                <div class="inline-field-wrap hidden" id="applicant-name-wrap">
                    <label class="field-label" for="applicant_name">Nama Pemohon (Manual) <span class="req">*</span></label>
                    <input type="text" name="applicant_name" id="applicant_name" class="gf-input" placeholder="Masukkan nama pemohon" autocomplete="off">
                </div>

                <p class="field-desc" id="contact-desc">Informasi kontak pemohon untuk menerima notifikasi status pengajuan via WhatsApp dan email.</p>

                <div class="inline-field-wrap" id="whatsapp-wrap">
                    <label class="field-label" for="whatsapp_number">No. WhatsApp <span class="req">*</span></label>
                    <input type="text" name="whatsapp_number" id="whatsapp_number" class="gf-input" placeholder="Masukkan nomor WhatsApp" autocomplete="off">
                </div>

                <div class="inline-field-wrap" id="email-wrap">
                    <label class="field-label" for="email">Email <span class="req">*</span></label>
                    <input type="email" name="email" id="email" class="gf-input" placeholder="Masukkan email" autocomplete="off">
                </div>
            </div>

            <p class="error-msg" id="err-pemohon_selection">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Nama pemohon wajib dipilih terlebih dahulu.
            </p>
            <p class="error-msg" id="err-user_id">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Nama pemohon wajib dipilih dari daftar untuk jenis pengajuan ini.
            </p>
            <p class="error-msg" id="err-applicant_name">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Nama pemohon manual wajib diisi.
            </p>
            <p class="error-msg" id="err-whatsapp_number">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                No. WhatsApp wajib diisi.
            </p>
            <p class="error-msg" id="err-email">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Email wajib diisi.
            </p>
        </div>

        <!-- Divisi -->
        <div class="field-card">
            <label class="field-label" for="division_id">
                Divisi <span class="req">*</span>
            </label>
            @if($divisions->isEmpty())
                <p class="field-desc field-warning" id="division-empty-note">Belum ada divisi terdaftar. Silakan hubungi admin untuk menambahkan divisi sebelum mengajukan aset.</p>
            @endif
            <input type="hidden" name="division_id" id="division_id">
            <div class="custom-select-container">
                <button type="button"
                    class="custom-select-trigger @if($divisions->isEmpty()) is-disabled @endif"
                    id="division-trigger"
                    onclick="toggleDropdown('division')"
                    aria-haspopup="listbox"
                    @if($divisions->isEmpty()) disabled @endif>
                    <span id="division-display" class="select-value placeholder">Pilih divisi...</span>
                </button>
                <div class="custom-dropdown" id="division-dropdown" role="listbox">
                    <div class="search-box">
                        <input type="text" placeholder="Cari divisi..." id="division-search-input" oninput="filterDropdown('division', this.value)" autocomplete="off">
                    </div>
                    <div class="option-list" id="division-option-list">
                        @forelse($divisions as $div)
                            <div class="option-item"
                                data-value="{{ $div->id }}"
                                data-label="{{ $div->name }}"
                                onclick='selectDivision(@json((string) $div->id), @json($div->name))'>
                                {{ $div->name }}
                            </div>
                        @empty
                            <div class="dropdown-empty-state">Belum ada divisi terdaftar.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <p class="error-msg" id="err-division_id">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Divisi wajib dipilih.
            </p>
        </div>

        <!-- Lokasi -->
        <div class="field-card">
            <label class="field-label" for="asset_location_id">
                Lokasi <span class="req">*</span>
            </label>
            <p class="field-desc">Pilih lokasi aset. Jika belum terdaftar, pilih <strong>Lainnya</strong> lalu lengkapi secara manual.</p>
            <input type="hidden" name="asset_location_id" id="asset_location_id">
            <div class="custom-select-container">
                <button type="button"
                    class="custom-select-trigger"
                    id="asset_location-trigger"
                    onclick="toggleDropdown('asset_location')"
                    aria-haspopup="listbox">
                    <span id="asset_location-display" class="select-value placeholder">Pilih lokasi...</span>
                </button>
                <div class="custom-dropdown" id="asset_location-dropdown" role="listbox">
                    <div class="search-box">
                        <input type="text" placeholder="Cari lokasi..." id="asset_location-search-input" oninput="filterDropdown('asset_location', this.value)" autocomplete="off">
                    </div>
                    <div class="option-list" id="asset_location-option-list">
                        @forelse($locations as $loc)
                            <div class="option-item"
                                data-value="{{ $loc->id }}"
                                data-label="{{ $loc->name }}"
                                onclick='selectAssetLocation(@json((string) $loc->id), @json($loc->name))'>
                                {{ $loc->name }}
                            </div>
                        @empty
                            <div class="dropdown-empty-state">Belum ada lokasi terdaftar. Pilih <strong>Lainnya</strong> lalu isi manual.</div>
                        @endforelse
                        <div class="option-item option-special"
                            data-value="other"
                            data-label="Lainnya"
                            onclick="selectAssetLocationOther()">
                            Lainnya
                        </div>
                    </div>
                </div>
            </div>

            <div class="inline-field-wrap hidden" id="asset_location-manual-wrap">
                <label class="field-label" for="custom_asset_location">Lokasi (Manual) <span class="req">*</span></label>
                <input type="text" name="custom_asset_location" id="custom_asset_location" class="gf-input" placeholder="Masukkan lokasi secara manual" autocomplete="off">
            </div>

            <p class="error-msg" id="err-asset_location_id">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Lokasi wajib dipilih.
            </p>
            <p class="error-msg" id="err-custom_asset_location">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Lokasi manual wajib diisi.
            </p>
        </div>

        <!-- Nama Aset yang Diajukan -->
        <div class="field-card" id="item-name-card">
            <label class="field-label">
                Nama Aset yang Diajukan <span class="req">*</span>
            </label>

            <div id="item-name-input-wrap">
                <div class="procurement-extra-row procurement-primary-row" id="procurement-primary-row">
                    <input type="text" name="item_name" id="item_name" class="gf-input" placeholder="Masukkan nama aset" autocomplete="off">
                    <input type="number" name="qty" id="qty" class="gf-input procurement-item-qty" value="1" min="1" placeholder="Jumlah" aria-label="Jumlah">
                </div>
                <div class="procurement-extra-list" id="procurement-extra-items"></div>
                <button type="button" class="add-procurement-item" onclick="addProcurementItem()">+ Tambah item pengadaan</button>
                <p class="error-msg" id="err-qty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                    Jumlah wajib diisi dan minimal 1.
                </p>
            </div>

            <div id="item-name-asset-wrap" class="hidden">
                <p class="field-desc" id="asset-desc">Pilih pemohon terlebih dahulu. Daftar aset akan menampilkan aset milik pemohon yang dipilih.</p>
                <input type="hidden" name="asset_id" id="asset_id">
                <div id="asset-id-inputs"></div>
                <div class="asset-select-toolbar hidden" id="asset-select-toolbar">
                    <button type="button" onclick="selectAllAssets()">Pilih semua aset</button>
                    <button type="button" onclick="clearAssetSelection()">Hapus pilihan</button>
                </div>
                <div class="custom-select-container">
                    <button type="button" class="custom-select-trigger is-disabled" id="asset-trigger" onclick="toggleDropdown('asset')" aria-haspopup="listbox" disabled>
                        <span id="asset-display" class="select-value placeholder">Pilih aset...</span>
                    </button>
                    <div class="custom-dropdown" id="asset-dropdown" role="listbox">
                        <div class="search-box">
                            <input type="text" placeholder="Cari aset..." id="asset-search-input" oninput="filterDropdown('asset', this.value)" autocomplete="off">
                        </div>
                        <div class="option-list" id="asset-option-list"></div>
                    </div>
                </div>
                <p class="asset-selected-summary hidden" id="asset-selected-summary"></p>
                <div class="asset-chip-list" id="asset-chip-list"></div>
            </div>

            <p class="error-msg" id="err-item_name">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Nama aset wajib diisi.
            </p>
            <p class="error-msg" id="err-asset_id">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Aset wajib dipilih.
            </p>
            <p class="error-msg" id="err-asset_ids">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Minimal satu aset wajib dipilih.
            </p>
        </div>

        <!-- Keterangan -->
        <div class="field-card">
            <label class="field-label" for="description">Keterangan / Keperluan</label>
            <textarea name="description" id="description" class="gf-input" placeholder="Masukkan keterangan / keperluan" rows="3"></textarea>
        </div>

        <!-- Lampiran -->
        <div class="field-card">
            <label class="field-label">
                Lampiran / Dokumen Pendukung <span class="req">*</span>
            </label>
            <p class="field-desc">Wajib diunggah minimal 1. Unggah maksimum 10MB per file. Format: gambar, PDF, Word, Excel.</p>

            <input type="file" id="attachments-input" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleFileSelect(event)">

            <div class="file-dropzone" id="file-dropzone" onclick="document.getElementById('attachments-input').click()">
                <p class="file-dropzone-text">Seret &amp; jatuhkan berkas Anda atau <strong>Jelajahi</strong></p>
            </div>

            <div class="file-list" id="file-list"></div>

            <p class="error-msg" id="err-attachments">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Format atau ukuran file lampiran tidak valid atau lampiran belum diunggah.
            </p>
        </div>

        <div class="form-alert hidden" id="form-alert" role="alert"></div>

        <div class="form-actions">
            <button type="button" class="clear-btn" onclick="clearForm()">Kosongkan formulir</button>
            <button type="submit" id="submit-btn" class="submit-btn">
                <span>Kirim Pengajuan</span>
                <span class="spinner"></span>
            </button>
        </div>

    </form>

    <!-- Footer -->
    <div class="gf-footer">
        <span>Formulir ini khusus untuk pengajuan penarikan, perbaikan, dan pengadaan aset perusahaan. Apabila data pemohon belum tersedia, pastikan informasi yang diisi mengikuti data pada aplikasi Talenta. Setelah dikirim, perkembangan pengajuan akan diberitahukan melalui WhatsApp atau email.</span>
    </div>

</div><!-- /.form-wrapper -->

<script>
    const assetsByRecipient = @json($assetsByRecipient);
    const usersById = @json($usersById);
    const defaultApplicantId = @json($defaultApplicantId);
    const hasJobTitles = @json($jobTitles->isNotEmpty());
    const hasBusinessEntities = @json($businessEntities->isNotEmpty());
    const hasDivisions = @json($divisions->isNotEmpty());
    const hasLocations = @json($locations->isNotEmpty());

    let selectedFiles = [];
    let applicantMode = 'none';
    let selectedApplicant = { id: '', name: '' };
    let availableAssets = [];
    let selectedAssets = [];
    let procurementExtraIndex = 0;

    function getRequestType() {
        return document.getElementById('type').value;
    }

    function isAssetSelectionType() {
        return ['penarikan', 'perbaikan'].includes(getRequestType());
    }

    function addProcurementItem(name = '', qty = 1) {
        const list = document.getElementById('procurement-extra-items');
        const row = document.createElement('div');
        row.className = 'procurement-extra-row';
        row.dataset.index = String(procurementExtraIndex++);

        const nameInput = document.createElement('input');
        nameInput.type = 'text';
        nameInput.className = 'gf-input procurement-item-name';
        nameInput.placeholder = 'Nama aset tambahan';
        nameInput.autocomplete = 'off';
        nameInput.value = name;

        const qtyInput = document.createElement('input');
        qtyInput.type = 'number';
        qtyInput.className = 'gf-input procurement-item-qty';
        qtyInput.placeholder = 'Jumlah';
        qtyInput.min = '1';
        qtyInput.value = qty;

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'remove-extra-item';
        remove.textContent = 'x';
        remove.setAttribute('aria-label', 'Hapus item pengadaan');
        remove.onclick = () => row.remove();

        row.appendChild(nameInput);
        row.appendChild(qtyInput);
        row.appendChild(remove);
        list.appendChild(row);
        nameInput.focus();
    }

    function clearProcurementItems() {
        document.getElementById('procurement-extra-items').innerHTML = '';
        procurementExtraIndex = 0;
    }

    function collectProcurementItems() {
        const items = [];
        const firstName = document.getElementById('item_name').value.trim();
        const firstQty = Number(document.getElementById('qty').value);

        if (firstName) {
            items.push({
                item_name: firstName,
                qty: firstQty && firstQty > 0 ? firstQty : 1,
            });
        }

        document.querySelectorAll('#procurement-extra-items .procurement-extra-row').forEach(row => {
            const nameInput = row.querySelector('.procurement-item-name');
            const qtyInput = row.querySelector('.procurement-item-qty');
            const name = nameInput.value.trim();
            const qty = Number(qtyInput.value);

            if (!name) {
                return;
            }

            items.push({
                item_name: name,
                qty: qty && qty > 0 ? qty : 1,
            });
        });

        return items;
    }

    function appendProcurementItems(formData) {
        if (isAssetSelectionType()) {
            return;
        }

        collectProcurementItems().forEach((item, index) => {
            formData.append(`items[${index}][item_name]`, item.item_name);
            formData.append(`items[${index}][qty]`, item.qty);
        });
    }

    function updateFieldDescriptions() {
        const pemohonDesc = document.getElementById('pemohon-desc');
        const assetDesc = document.getElementById('asset-desc');

        if (isAssetSelectionType()) {
            pemohonDesc.innerHTML = 'Pilih pemohon dari daftar. Untuk penarikan/perbaikan, pemohon harus terdaftar agar aset miliknya bisa dipilih di field berikutnya.';
            assetDesc.textContent = selectedApplicant.id
                ? `Menampilkan aset milik ${selectedApplicant.name}. Centang satu atau lebih aset yang akan diproses.`
                : 'Pilih pemohon terlebih dahulu. Daftar aset akan menampilkan aset milik pemohon yang dipilih.';
            return;
        }

        pemohonDesc.innerHTML = 'Pilih pemohon dari daftar. Jika belum terdaftar, pilih <strong>Tidak ada / Lainnya</strong> lalu lengkapi data pemohon.';
    }

    function setSelectDisplay(displayId, text, isPlaceholder = false) {
        const display = document.getElementById(displayId);
        display.textContent = text;
        display.classList.toggle('placeholder', isPlaceholder);
    }

    function resetPemohonSelection() {
        applicantMode = 'none';
        selectedApplicant = { id: '', name: '' };

        document.getElementById('user_id').value = '';
        document.getElementById('applicant_name').value = '';
        document.getElementById('whatsapp_number').value = '';
        document.getElementById('email').value = '';
        document.getElementById('pemohon-details-wrap').classList.add('hidden');
        document.getElementById('applicant-name-wrap').classList.add('hidden');
        setContactFieldVisibility(false, false);

        setSelectDisplay('user-display', 'Pilih nama pemohon...', true);
        document.getElementById('user-trigger').classList.remove('has-error');
        hideBadanUsahaWrap();
        hidePosisiWrap();
    }

    function selectDefaultApplicantIfAvailable() {
        if (!defaultApplicantId || !usersById[defaultApplicantId]) {
            return false;
        }

        selectApplicantUser(defaultApplicantId);

        return true;
    }

    function hideBadanUsahaWrap() {
        const wrap = document.getElementById('badan-usaha-wrap');
        wrap.classList.add('hidden');
        wrap.classList.remove('badan-usaha-readonly');
        resetBadanUsahaSelection();
    }

    function resetBadanUsahaSelection() {
        document.getElementById('business_entity_id').value = '';
        document.getElementById('business_entity_id').classList.remove('has-error');
    }

    function setBadanUsahaMode(mode) {
        const wrap = document.getElementById('badan-usaha-wrap');
        const desc = document.getElementById('badan-usaha-desc');
        const select = document.getElementById('business_entity_id');

        wrap.classList.remove('hidden');
        wrap.classList.toggle('badan-usaha-readonly', mode === 'list');

        if (mode === 'list') {
            desc.textContent = 'Badan usaha mengikuti data pemohon yang dipilih.';
            return;
        }

        select.value = '';
        select.classList.remove('has-error');
        desc.textContent = hasBusinessEntities
            ? 'Pilih badan usaha pemohon dari daftar.'
            : 'Belum ada daftar badan usaha. Hubungi admin untuk menambahkan data badan usaha.';
    }

    function applyBadanUsahaFromUser(user) {
        if (!user || !user.business_entity_id) {
            setBadanUsahaMode('edit');
            return;
        }

        setBadanUsahaMode('list');
        document.getElementById('business_entity_id').value = String(user.business_entity_id);
    }

    function hidePosisiWrap() {
        const wrap = document.getElementById('posisi-wrap');
        wrap.classList.add('hidden');
        wrap.classList.remove('posisi-readonly');
        resetPosisiSelection();
    }

    function setPosisiMode(mode) {
        const wrap = document.getElementById('posisi-wrap');
        const desc = document.getElementById('posisi-desc');
        const dropdownWrap = document.getElementById('posisi-dropdown-wrap');
        const manualWrap = document.getElementById('posisi-manual-wrap');

        wrap.classList.remove('hidden');
        wrap.classList.toggle('posisi-readonly', mode === 'list');

        if (mode === 'list') {
            desc.textContent = 'Posisi mengikuti data pemohon yang dipilih.';
            manualWrap.classList.add('hidden');
            dropdownWrap.classList.remove('hidden');
            return;
        }

        document.getElementById('job_title_id').value = '';
        document.getElementById('custom_job_title').value = '';
        manualWrap.classList.add('hidden');
        setSelectDisplay('posisi-display', 'Pilih posisi...', true);
        document.getElementById('posisi-trigger').classList.remove('has-error');

        if (!hasJobTitles) {
            desc.textContent = 'Isi posisi / jabatan pemohon secara manual.';
            dropdownWrap.classList.add('hidden');
            manualWrap.classList.remove('hidden');
            document.getElementById('job_title_id').value = 'other';
            return;
        }

        desc.innerHTML = 'Pilih posisi pemohon dari daftar. Jika belum ada, pilih <strong>Lainnya</strong> lalu isi manual.';
        dropdownWrap.classList.remove('hidden');
    }

    function resetPosisiSelection() {
        document.getElementById('job_title_id').value = '';
        document.getElementById('custom_job_title').value = '';
        document.getElementById('posisi-manual-wrap').classList.add('hidden');
        setSelectDisplay('posisi-display', 'Pilih posisi...', true);
        document.getElementById('posisi-trigger').classList.remove('has-error');
    }

    function setPosisiValue(value, label, isPlaceholder = false) {
        document.getElementById('job_title_id').value = value;
        setSelectDisplay('posisi-display', label, isPlaceholder);
        document.getElementById('posisi-trigger').classList.remove('has-error');
    }

    function selectPosisiOther() {
        setPosisiValue('other', 'Lainnya');
        document.getElementById('posisi-manual-wrap').classList.remove('hidden');
        document.getElementById('custom_job_title').focus();
        closeAllDropdowns();
    }

    function selectPosisiJob(jobTitleId, jobTitleName) {
        document.getElementById('custom_job_title').value = '';
        document.getElementById('posisi-manual-wrap').classList.add('hidden');
        setPosisiValue(String(jobTitleId), jobTitleName);
        closeAllDropdowns();
    }

    function selectDivision(divisionId, divisionName) {
        document.getElementById('division_id').value = String(divisionId);
        setSelectDisplay('division-display', divisionName);
        document.getElementById('division-trigger').classList.remove('has-error');
        closeAllDropdowns();
    }

    function resetDivisionSelection() {
        document.getElementById('division_id').value = '';
        setSelectDisplay('division-display', 'Pilih divisi...', true);
        document.getElementById('division-trigger').classList.remove('has-error');
    }

    function selectAssetLocation(locationId, locationName) {
        document.getElementById('custom_asset_location').value = '';
        document.getElementById('asset_location-manual-wrap').classList.add('hidden');
        document.getElementById('asset_location_id').value = String(locationId);
        setSelectDisplay('asset_location-display', locationName);
        document.getElementById('asset_location-trigger').classList.remove('has-error');
        closeAllDropdowns();
    }

    function selectAssetLocationOther() {
        document.getElementById('asset_location_id').value = 'other';
        setSelectDisplay('asset_location-display', 'Lainnya');
        document.getElementById('asset_location-trigger').classList.remove('has-error');
        document.getElementById('asset_location-manual-wrap').classList.remove('hidden');
        document.getElementById('custom_asset_location').focus();
        closeAllDropdowns();
    }

    function resetAssetLocationSelection() {
        document.getElementById('asset_location_id').value = '';
        document.getElementById('custom_asset_location').value = '';
        document.getElementById('asset_location-manual-wrap').classList.add('hidden');
        setSelectDisplay('asset_location-display', 'Pilih lokasi...', true);
        document.getElementById('asset_location-trigger').classList.remove('has-error');
    }

    function applyPosisiFromUser(user) {
        if (!user || !user.job_title_id) {
            setPosisiMode('edit');
            return;
        }

        setPosisiMode('list');
        selectPosisiJob(user.job_title_id, user.job_title || 'Posisi terpilih');
    }

    function setContactFieldVisibility(showWhatsapp, showEmail) {
        const whatsappWrap = document.getElementById('whatsapp-wrap');
        const emailWrap = document.getElementById('email-wrap');

        whatsappWrap.classList.toggle('hidden', !showWhatsapp);
        emailWrap.classList.toggle('hidden', !showEmail);

        if (!showWhatsapp) {
            document.getElementById('whatsapp_number').value = '';
            document.getElementById('whatsapp_number').classList.remove('has-error');
        }

        if (!showEmail) {
            document.getElementById('email').value = '';
            document.getElementById('email').classList.remove('has-error');
        }
    }

    function contactFieldIsVisible(fieldId) {
        return !document.getElementById(`${fieldId}-wrap`).classList.contains('hidden');
    }

    function showPemohonDetails(mode, user = null) {
        const detailsWrap = document.getElementById('pemohon-details-wrap');
        const applicantNameWrap = document.getElementById('applicant-name-wrap');
        const contactDesc = document.getElementById('contact-desc');

        detailsWrap.classList.remove('hidden');
        applicantNameWrap.classList.toggle('hidden', mode !== 'other');

        if (mode === 'other') {
            document.getElementById('applicant_name').value = '';
            document.getElementById('whatsapp_number').value = '';
            document.getElementById('email').value = '';
            contactDesc.textContent = 'Lengkapi kontak pemohon baru untuk menerima notifikasi status pengajuan.';
            setContactFieldVisibility(true, true);
            return;
        }

        document.getElementById('applicant_name').value = '';
        document.getElementById('whatsapp_number').value = '';
        document.getElementById('email').value = '';

        const needsWhatsapp = !user?.has_whatsapp_number;
        const needsEmail = !user?.has_email;

        setContactFieldVisibility(needsWhatsapp, needsEmail);

        if (!needsWhatsapp && !needsEmail) {
            detailsWrap.classList.add('hidden');
            return;
        }

        contactDesc.textContent = 'Data kontak pemohon belum lengkap. Lengkapi field yang muncul agar notifikasi pengajuan bisa dikirim.';
    }

    function handleTypeChange(type) {
        const itemNameInputWrap = document.getElementById('item-name-input-wrap');
        const itemNameAssetWrap = document.getElementById('item-name-asset-wrap');

        if (type === 'pengadaan') {
            itemNameInputWrap.classList.remove('hidden');
            itemNameAssetWrap.classList.add('hidden');
        } else {
            itemNameInputWrap.classList.add('hidden');
            itemNameAssetWrap.classList.remove('hidden');
            clearProcurementItems();
        }

        resetPemohonSelection();
        resetAssetSelection();

        if (!selectDefaultApplicantIfAvailable()) {
            updateFieldDescriptions();
        }
    }

    function resetAssetSelection() {
        document.getElementById('asset_id').value = '';
        document.getElementById('asset-id-inputs').innerHTML = '';
        availableAssets = [];
        selectedAssets = [];

        const display = document.getElementById('asset-display');
        display.textContent = 'Pilih aset...';
        display.classList.add('placeholder');

        const trigger = document.getElementById('asset-trigger');
        trigger.disabled = true;
        trigger.classList.add('is-disabled');
        trigger.classList.remove('has-error');

        document.getElementById('asset-select-toolbar').classList.add('hidden');
        document.getElementById('asset-option-list').innerHTML = '';
        document.getElementById('asset-selected-summary').classList.add('hidden');
        document.getElementById('asset-selected-summary').textContent = '';
        document.getElementById('asset-chip-list').innerHTML = '';
    }

    function setSelectedAssets(assets) {
        selectedAssets = assets.map(asset => ({
            id: String(asset.id),
            label: asset.label,
        }));

        syncAssetInputs();
        renderSelectedAssetState();
    }

    function syncAssetInputs() {
        document.getElementById('asset_id').value = selectedAssets[0]?.id || '';

        const inputsWrap = document.getElementById('asset-id-inputs');
        inputsWrap.innerHTML = '';

        selectedAssets.forEach(asset => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'asset_ids[]';
            input.value = asset.id;
            inputsWrap.appendChild(input);
        });
    }

    function renderSelectedAssetState() {
        const selectedIds = new Set(selectedAssets.map(asset => asset.id));
        const display = document.getElementById('asset-display');
        const trigger = document.getElementById('asset-trigger');
        const summary = document.getElementById('asset-selected-summary');
        const chipList = document.getElementById('asset-chip-list');

        document.querySelectorAll('#asset-option-list .asset-option-item').forEach(item => {
            const isSelected = selectedIds.has(String(item.dataset.value));
            item.classList.toggle('is-selected', isSelected);

            const checkbox = item.querySelector('.asset-option-checkbox');
            if (checkbox) {
                checkbox.checked = isSelected;
            }
        });

        chipList.innerHTML = '';

        if (!selectedAssets.length) {
            display.textContent = 'Pilih aset...';
            display.classList.add('placeholder');
            summary.classList.add('hidden');
            summary.textContent = '';
            return;
        }

        display.textContent = selectedAssets.length === 1
            ? selectedAssets[0].label
            : `${selectedAssets.length} aset dipilih`;
        display.classList.remove('placeholder');
        trigger.classList.remove('has-error');

        summary.textContent = `${selectedAssets.length} aset dipilih untuk ${getRequestType() === 'perbaikan' ? 'perbaikan' : 'penarikan'}.`;
        summary.classList.remove('hidden');

        selectedAssets.forEach(asset => {
            const chip = document.createElement('div');
            chip.className = 'asset-chip';

            const label = document.createElement('span');
            label.textContent = asset.label;

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'x';
            remove.setAttribute('aria-label', `Hapus ${asset.label}`);
            remove.onclick = () => removeSelectedAsset(asset.id);

            chip.appendChild(label);
            chip.appendChild(remove);
            chipList.appendChild(chip);
        });
    }

    function renderAssetOptions(userId) {
        const assetList = document.getElementById('asset-option-list');
        const trigger = document.getElementById('asset-trigger');
        const assets = assetsByRecipient[String(userId)] || [];

        assetList.innerHTML = '';
        resetAssetSelection();
        availableAssets = assets.map(asset => ({
            id: String(asset.id),
            label: asset.label,
        }));
        selectedApplicant = { id: String(userId), name: document.getElementById('user-display').textContent };

        if (!assets.length) {
            assetList.innerHTML = '<div class="dropdown-empty-state">Pemohon ini belum memiliki aset terdaftar.</div>';
            trigger.disabled = true;
            trigger.classList.add('is-disabled');
            document.getElementById('asset-desc').textContent = `${selectedApplicant.name} belum memiliki aset terdaftar.`;
            return;
        }

        availableAssets.forEach(asset => {
            const item = document.createElement('div');
            item.className = 'option-item asset-option-item';
            item.dataset.value = asset.id;
            item.dataset.label = asset.label;

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'asset-option-checkbox';
            checkbox.tabIndex = -1;

            const label = document.createElement('span');
            label.textContent = asset.label;

            item.appendChild(checkbox);
            item.appendChild(label);
            item.onclick = () => toggleAssetSelection(asset);
            assetList.appendChild(item);
        });

        trigger.disabled = false;
        trigger.classList.remove('is-disabled');
        document.getElementById('asset-select-toolbar').classList.remove('hidden');
        document.getElementById('asset-desc').textContent = `Menampilkan aset milik ${selectedApplicant.name}. Centang satu atau lebih aset yang akan diproses.`;
        renderSelectedAssetState();
    }

    function toggleAssetSelection(asset) {
        const assetId = String(asset.id);
        const existingIndex = selectedAssets.findIndex(selectedAsset => selectedAsset.id === assetId);

        if (existingIndex >= 0) {
            selectedAssets.splice(existingIndex, 1);
        } else {
            selectedAssets.push({
                id: assetId,
                label: asset.label,
            });
        }

        syncAssetInputs();
        renderSelectedAssetState();
    }

    function removeSelectedAsset(assetId) {
        selectedAssets = selectedAssets.filter(asset => asset.id !== String(assetId));
        syncAssetInputs();
        renderSelectedAssetState();
    }

    function selectAllAssets() {
        if (!availableAssets.length) {
            return;
        }

        setSelectedAssets(availableAssets);
    }

    function clearAssetSelection() {
        setSelectedAssets([]);
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.custom-dropdown').forEach(d => d.classList.remove('open'));
        document.querySelectorAll('.field-card.dropdown-active').forEach(card => card.classList.remove('dropdown-active'));
    }

    function toggleDropdown(key) {
        const dropdown = document.getElementById(`${key}-dropdown`);
        const trigger = document.getElementById(`${key}-trigger`);

        if (trigger && trigger.disabled) {
            return;
        }

        if (key === 'posisi' && document.getElementById('posisi-wrap').classList.contains('posisi-readonly')) {
            return;
        }

        const isOpen = dropdown.classList.contains('open');
        const fieldCard = dropdown.closest('.field-card');

        closeAllDropdowns();

        if (!isOpen) {
            dropdown.classList.add('open');
            if (fieldCard) {
                fieldCard.classList.add('dropdown-active');
            }

            const searchInput = document.getElementById(`${key}-search-input`);
            if (searchInput) {
                searchInput.value = '';
                filterDropdown(key, '');
                setTimeout(() => searchInput.focus(), 50);
            }
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-select-container')) {
            closeAllDropdowns();
        }
    });

    function filterDropdown(key, query) {
        const list = document.getElementById(`${key}-option-list`);
        if (!list) {
            return;
        }

        const q = query.toLowerCase();
        list.querySelectorAll('.option-item').forEach(item => {
            const label = (item.getAttribute('data-label') || item.textContent || '').toLowerCase();
            item.classList.toggle('hidden', !label.includes(q));
        });
    }

    function selectApplicantOther() {
        applicantMode = 'other';
        selectedApplicant = { id: '', name: '' };

        document.getElementById('user_id').value = '';
        document.getElementById('user-trigger').classList.remove('has-error');
        setSelectDisplay('user-display', 'Tidak ada / Lainnya');

        showPemohonDetails('other');
        setBadanUsahaMode('edit');
        setPosisiMode('edit');
        resetAssetSelection();
        closeAllDropdowns();
        updateFieldDescriptions();

        document.getElementById('applicant_name').focus();
    }

    function selectApplicantUser(userId) {
        const user = usersById[userId] || {};
        applicantMode = 'list';
        selectedApplicant = { id: String(userId), name: user.name || '' };

        document.getElementById('user_id').value = userId;
        document.getElementById('user-trigger').classList.remove('has-error');
        setSelectDisplay('user-display', selectedApplicant.name);

        showPemohonDetails('list', user);
        applyBadanUsahaFromUser(user);
        applyPosisiFromUser(user);
        closeAllDropdowns();

        if (isAssetSelectionType()) {
            renderAssetOptions(userId);
        } else {
            resetAssetSelection();
        }

        updateFieldDescriptions();
    }

    function selectAsset(assetId, label) {
        toggleAssetSelection({ id: assetId, label });
    }

    // -------- File Upload --------
    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        files.forEach(f => selectedFiles.push(f));
        renderFileList();
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        renderFileList();
    }

    function renderFileList() {
        const list = document.getElementById('file-list');
        list.innerHTML = '';
        selectedFiles.forEach((file, i) => {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const item = document.createElement('div');
            item.className = 'file-item';
            item.innerHTML = `
                <div class="file-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="#5f6368"><path d="M14,2H6C4.9,2,4,2.9,4,4v16c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V8L14,2z M16,18H8v-2h8V18z M16,14H8v-2h8V14z M13,9V3.5L18.5,9H13z"/></svg>
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">${sizeMB} MB</span>
                </div>
                <button type="button" class="remove-btn" onclick="removeFile(${i})" title="Hapus">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                </button>`;
            list.appendChild(item);
        });
    }

    // -------- Clear Form --------
    function clearForm() {
        document.getElementById('asset-request-form').reset();
        selectedFiles = [];
        renderFileList();
        handleTypeChange('pengadaan');
        clearProcurementItems();
        resetDivisionSelection();
        resetAssetLocationSelection();
        closeAllDropdowns();
        clearFormErrors();

        if (!hasDivisions) {
            document.getElementById('submit-btn').disabled = true;
        }
    }

    function normalizeErrorField(field) {
        if (field.startsWith('attachments.')) {
            return 'attachments';
        }

        if (field.startsWith('asset_ids.')) {
            return 'asset_ids';
        }

        return field;
    }

    function highlightFieldError(field) {
        const normalizedField = normalizeErrorField(field);
        const triggerMap = {
            pemohon_selection: 'user-trigger',
            user_id: 'user-trigger',
            asset_id: 'asset-trigger',
            asset_ids: 'asset-trigger',
            applicant_name: 'applicant_name',
            whatsapp_number: 'whatsapp_number',
            email: 'email',
            custom_job_title: 'custom_job_title',
            business_entity_id: 'business_entity_id',
            division_id: 'division-trigger',
            asset_location_id: 'asset_location-trigger',
            custom_asset_location: 'custom_asset_location',
            attachments: 'file-dropzone',
        };

        if (triggerMap[normalizedField]) {
            const target = document.getElementById(triggerMap[normalizedField]);
            if (target) {
                target.classList.add('has-error');
            }
            return;
        }

        const inputEl = document.getElementById(normalizedField);
        if (inputEl && inputEl.classList.contains('gf-input')) {
            inputEl.classList.add('has-error');
        }
    }

    function clearFormErrors() {
        document.querySelectorAll('.error-msg').forEach(el => el.classList.remove('visible'));
        document.querySelectorAll('.gf-input, .custom-select-trigger').forEach(el => el.classList.remove('has-error'));
        document.getElementById('form-alert')?.classList.add('hidden');
    }

    function showFieldError(field, message = null) {
        const normalizedField = normalizeErrorField(field);
        const errEl = document.getElementById(`err-${normalizedField}`);
        if (errEl) {
            if (message) {
                const icon = errEl.querySelector('svg');
                errEl.textContent = '';
                if (icon) {
                    errEl.appendChild(icon);
                } else {
                    const fallbackIcon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    fallbackIcon.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
                    fallbackIcon.setAttribute('width', '12');
                    fallbackIcon.setAttribute('height', '12');
                    fallbackIcon.setAttribute('viewBox', '0 0 24 24');
                    fallbackIcon.setAttribute('fill', 'currentColor');
                    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    path.setAttribute('d', 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z');
                    fallbackIcon.appendChild(path);
                    errEl.appendChild(fallbackIcon);
                }
                errEl.appendChild(document.createTextNode(' ' + message));
            }
            errEl.classList.add('visible');
        }
        highlightFieldError(normalizedField);
        return Boolean(errEl);
    }

    function showValidationErrors(errors) {
        const unmatchedMessages = [];

        Object.keys(errors || {}).forEach(field => {
            const messages = errors[field];
            const message = Array.isArray(messages) ? messages[0] : messages;
            const shown = showFieldError(field, message || null);

            if (!shown && message) {
                unmatchedMessages.push(message);
            }
        });

        if (unmatchedMessages.length) {
            showFormAlert(unmatchedMessages.join(' '));
        }

        const firstErr = document.querySelector('.error-msg.visible');
        if (firstErr) {
            firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function showFormAlert(message) {
        const alertEl = document.getElementById('form-alert');
        if (!alertEl) {
            return;
        }

        alertEl.textContent = message;
        alertEl.classList.remove('hidden');
        alertEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function validateFormBeforeSubmit() {
        clearFormErrors();
        let valid = true;

        if (applicantMode === 'none') {
            showFieldError('pemohon_selection');
            valid = false;
        }

        if (applicantMode === 'other') {
            if (!document.getElementById('applicant_name').value.trim()) {
                showFieldError('applicant_name');
                valid = false;
            }
        }

        if (applicantMode !== 'none') {
            if (contactFieldIsVisible('whatsapp') && !document.getElementById('whatsapp_number').value.trim()) {
                showFieldError('whatsapp_number');
                valid = false;
            }

            if (contactFieldIsVisible('email') && !document.getElementById('email').value.trim()) {
                showFieldError('email');
                valid = false;
            }
        }

        if (!document.getElementById('badan-usaha-wrap').classList.contains('hidden')
            && !document.getElementById('business_entity_id').value) {
            showFieldError('business_entity_id');
            valid = false;
        }

        if (document.getElementById('job_title_id').value === 'other'
            && !document.getElementById('custom_job_title').value.trim()) {
            showFieldError('custom_job_title');
            valid = false;
        }

        if (!hasDivisions || !document.getElementById('division_id').value) {
            showFieldError('division_id');
            valid = false;
        }

        if (!document.getElementById('asset_location_id').value) {
            showFieldError('asset_location_id');
            valid = false;
        }

        if (document.getElementById('asset_location_id').value === 'other'
            && !document.getElementById('custom_asset_location').value.trim()) {
            showFieldError('custom_asset_location');
            valid = false;
        }

        if (isAssetSelectionType()) {
            if (!document.getElementById('user_id').value) {
                showFieldError('user_id');
                valid = false;
            }

            if (!selectedAssets.length) {
                showFieldError('asset_ids');
                valid = false;
            }
        } else {
            if (!document.getElementById('item_name').value.trim()) {
                showFieldError('item_name');
                valid = false;
            }

            const qty = Number(document.getElementById('qty').value);
            if (!qty || qty < 1) {
                showFieldError('qty');
                valid = false;
            }

            document.querySelectorAll('#procurement-extra-items .procurement-extra-row').forEach(row => {
                const nameInput = row.querySelector('.procurement-item-name');
                const qtyInput = row.querySelector('.procurement-item-qty');
                const hasName = Boolean(nameInput.value.trim());
                const qtyValue = Number(qtyInput.value);

                if (hasName && (!qtyValue || qtyValue < 1)) {
                    qtyInput.classList.add('has-error');
                    valid = false;
                }
            });
        }

        if (selectedFiles.length === 0) {
            showFieldError('attachments');
            valid = false;
        }

        if (!valid) {
            const firstErr = document.querySelector('.error-msg.visible');
            if (firstErr) {
                firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        return valid;
    }

    // -------- Form Submit --------
    document.getElementById('asset-request-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const btn = document.getElementById('submit-btn');

        if (!validateFormBeforeSubmit()) {
            return;
        }

        btn.disabled = true;
        btn.classList.add('loading');

        const formData = new FormData(this);
        formData.delete('attachments[]');
        appendProcurementItems(formData);
        selectedFiles.forEach(f => formData.append('attachments[]', f));

        let redirectingAfterSuccess = false;

        fetch('{{ route("public.asset-requests.store") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: formData,
        })
        .then(async response => {
            const raw = await response.text();
            let data = {};

            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (error) {
                throw new Error('Respons server tidak valid.');
            }

            return { status: response.status, data };
        })
        .then(({ status, data }) => {
            if (status === 422) {
                showValidationErrors(data.errors || {});

                if ((!data.errors || Object.keys(data.errors).length === 0) && data.message) {
                    showFormAlert(data.message);
                }
                return;
            }

            if (status >= 500) {
                showFormAlert(data.message || 'Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.');
                return;
            }

            if (data.success) {
                if (data.progress_url) {
                    redirectingAfterSuccess = true;
                    window.location.assign(data.progress_url);
                    return;
                }

                showFormAlert(data.message || 'Pengajuan berhasil disimpan.');
                return;
            }

            showFormAlert(data.message || 'Terjadi kesalahan sistem.');
        })
        .catch(() => showFormAlert('Gagal menghubungi server. Silakan coba lagi.'))
        .finally(() => {
            if (redirectingAfterSuccess) {
                return;
            }

            btn.disabled = !hasDivisions;
            btn.classList.remove('loading');
        });
    });

    if (!hasDivisions) {
        document.getElementById('submit-btn').disabled = true;
    }

    selectDefaultApplicantIfAvailable();
    updateFieldDescriptions();

    (function initFileDropzone() {
        const dropzone = document.getElementById('file-dropzone');
        if (!dropzone) return;

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, e => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', e => {
            const files = Array.from(e.dataTransfer.files || []);
            files.forEach(f => selectedFiles.push(f));
            renderFileList();
        });
    })();
</script>
</body>
</html>
