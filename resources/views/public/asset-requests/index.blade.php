<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>FORM PENGAJUAN ASET</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-light: #eff6ff;
            --danger: #dc2626;
            --bg: #eef2f7;
            --card: #ffffff;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --text-label: #374151;
            --border: #d1d5db;
            --border-focus: #2563eb;
            --input-bg: #ffffff;
            --radius: 8px;
            --chevron: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' viewBox='0 0 24 24' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            --font-body: 'Inter', 'Roboto', Arial, sans-serif;
            --font-heading: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        body {
            font-family: var(--font-body);
            background: var(--bg);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 40px 16px 64px;
            line-height: 1.5;
        }

        .form-wrapper {
            max-width: 640px;
            margin: 0 auto;
        }

        .header-card {
            background: var(--card);
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .08);
            overflow: hidden;
        }

        .header-banner {
            height: 10px;
            background: linear-gradient(90deg, #1d4ed8, #3b82f6);
        }

        .header-body {
            padding: 24px 24px 20px;
        }

        .header-title {
            font-family: var(--font-heading);
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1.25;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .header-desc {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 12px;
            line-height: 1.5;
            max-width: 65ch;
        }

        .header-required-note {
            font-size: 13px;
            color: var(--danger);
            margin-top: 16px;
        }

        #asset-request-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 12px;
            overflow: visible;
        }

        .field-card {
            position: relative;
            z-index: 1;
            background: var(--card);
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .08);
            transition: box-shadow .2s;
            overflow: visible;
        }

        .field-card:focus-within,
        .field-card.dropdown-active {
            z-index: 50;
            box-shadow: 0 2px 6px rgba(0, 0, 0, .15), 0 1px 4px rgba(0, 0, 0, .1);
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-top: 24px;
            padding-top: 8px;
        }

        .field-label {
            display: block;
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            color: var(--text-label);
            margin-bottom: 8px;
        }

        .field-label .req {
            color: var(--danger);
            margin-left: 2px;
        }

        .field-desc {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 8px;
            line-height: 1.45;
            max-width: 65ch;
        }

        .field-desc.field-warning {
            color: #b45309;
        }

        .form-alert {
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: var(--radius);
            padding: 12px 14px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .form-alert.hidden {
            display: none;
        }

        .gf-input,
        .custom-select-trigger {
            width: 100%;
            min-height: 44px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--input-bg);
            font-family: inherit;
            font-size: 14px;
            color: var(--text-primary);
            padding: 10px 14px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            appearance: none;
            -webkit-appearance: none;
        }

        .gf-input::placeholder,
        .custom-select-trigger .placeholder {
            color: #9ca3af;
        }

        .gf-input:focus,
        .custom-select-trigger:focus,
        .field-card.dropdown-active .custom-select-trigger {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .gf-input.has-error,
        .custom-select-trigger.has-error {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }

        textarea.gf-input {
            resize: vertical;
            min-height: 96px;
            line-height: 1.5;
        }

        input[type="number"].gf-input {
            -moz-appearance: textfield;
        }

        input[type="number"].gf-input::-webkit-outer-spin-button,
        input[type="number"].gf-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .gf-select-wrapper {
            position: relative;
        }

        select.gf-input {
            cursor: pointer;
            padding-right: 40px;
            background: var(--input-bg) var(--chevron) no-repeat right 14px center / 16px 16px;
        }

        .custom-select-container {
            position: relative;
        }

        .custom-select-trigger {
            cursor: pointer;
            text-align: left;
            display: flex;
            align-items: center;
            padding-right: 40px;
        }

        .custom-select-trigger.is-disabled,
        .custom-select-trigger:disabled {
            background: #f9fafb;
            color: #9ca3af;
            cursor: not-allowed;
            opacity: 1;
        }

        .custom-select-trigger::after {
            content: '';
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background: var(--chevron) no-repeat center / 16px 16px;
            pointer-events: none;
        }

        .field-card.dropdown-active .custom-select-trigger::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .custom-select-trigger .select-value {
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .custom-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            z-index: 200;
            max-height: 260px;
            display: none;
            flex-direction: column;
            overflow: hidden;
        }

        .custom-dropdown.open {
            display: flex;
        }

        .custom-dropdown .search-box {
            padding: 10px 12px;
            border-bottom: 1px solid #eef2f7;
            flex-shrink: 0;
        }

        .custom-dropdown .search-box input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 13px;
            outline: none;
            background: #fff;
        }

        .custom-dropdown .search-box input:focus {
            border-color: var(--border-focus);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .custom-dropdown .option-list {
            overflow-y: auto;
            flex-grow: 1;
        }

        .custom-dropdown .option-item {
            padding: 11px 14px;
            font-size: 14px;
            color: var(--text-primary);
            cursor: pointer;
            transition: background .12s;
        }

        .custom-dropdown .option-item:hover {
            background: #f3f4f6;
        }

        .custom-dropdown .option-item.asset-option-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .custom-dropdown .option-item.asset-option-item.is-selected {
            background: var(--accent-light);
            color: #1e40af;
            font-weight: 600;
        }

        .asset-option-checkbox {
            width: 16px;
            height: 16px;
            flex: 0 0 16px;
            accent-color: var(--accent);
            pointer-events: none;
        }

        .custom-dropdown .option-item.hidden {
            display: none;
        }

        .custom-dropdown .option-item.option-special {
            color: var(--accent);
            font-weight: 600;
            border-bottom: 1px solid #eef2f7;
        }

        .dropdown-empty-state {
            padding: 16px;
            font-size: 13px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .asset-select-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 10px 0 8px;
            flex-wrap: wrap;
        }

        .asset-select-toolbar button,
        .asset-chip button {
            border: none;
            background: none;
            font: inherit;
            cursor: pointer;
        }

        .asset-select-toolbar button {
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
            padding: 0;
        }

        .asset-select-toolbar button:hover {
            color: var(--accent-hover);
        }

        .asset-selected-summary {
            color: var(--text-secondary);
            font-size: 13px;
            line-height: 1.45;
            margin-top: 10px;
        }

        .asset-chip-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .asset-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            max-width: 100%;
            border: 1px solid #bfdbfe;
            border-radius: var(--radius);
            background: var(--accent-light);
            color: #1e3a8a;
            padding: 6px 8px;
            font-size: 12px;
            line-height: 1.25;
        }

        .asset-chip span {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .asset-chip button {
            color: #1d4ed8;
            line-height: 1;
            padding: 0 2px;
        }

        .procurement-extra-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 12px;
        }

        .procurement-extra-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 92px 32px;
            gap: 8px;
            align-items: center;
        }

        .procurement-extra-row .remove-extra-item {
            width: 32px;
            height: 32px;
            border: none;
            border-radius: 6px;
            background: #f3f4f6;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
        }

        .procurement-extra-row .remove-extra-item:hover {
            background: #fee2e2;
            color: var(--danger);
        }

        .add-procurement-item {
            border: none;
            background: none;
            color: var(--accent);
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            margin-top: 10px;
        }

        .add-procurement-item:hover {
            color: var(--accent-hover);
        }

        .inline-field-wrap {
            margin-top: 16px;
        }

        .inline-field-wrap.hidden {
            display: none;
        }

        .inline-field-wrap .field-label {
            margin-top: 14px;
        }

        .inline-field-wrap .field-label:first-child {
            margin-top: 0;
        }

        .pemohon-subfield {
            margin-top: 20px;
        }

        .pemohon-subfield .field-label {
            margin-top: 0;
        }

        .pemohon-subfield.hidden {
            display: none;
        }

        .pemohon-subfield.posisi-readonly .custom-select-trigger {
            background: #f9fafb;
            color: var(--text-primary);
            cursor: default;
            pointer-events: none;
        }

        .pemohon-subfield.posisi-readonly .custom-select-trigger::after {
            display: none;
        }

        .pemohon-subfield.badan-usaha-readonly select.gf-input {
            background: #f9fafb;
            color: var(--text-primary);
            cursor: default;
            pointer-events: none;
        }

        .error-msg {
            display: none;
            font-size: 12px;
            color: var(--danger);
            margin-top: 6px;
            align-items: center;
            gap: 4px;
        }

        .error-msg.visible {
            display: flex;
        }

        .file-dropzone {
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius);
            background: #fafbfc;
            padding: 28px 20px;
            text-align: center;
            cursor: pointer;
            transition: border-color .15s, background .15s;
        }

        .file-dropzone:hover,
        .file-dropzone.dragover {
            border-color: var(--accent);
            background: var(--accent-light);
        }

        .file-dropzone-text {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .file-dropzone-text strong {
            color: var(--accent);
            font-weight: 600;
        }

        .file-list {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 12px;
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 13px;
        }

        .file-item .file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
            overflow: hidden;
        }

        .file-item .file-name {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 320px;
        }

        .file-item .file-size {
            color: var(--text-secondary);
            font-size: 12px;
            flex-shrink: 0;
        }

        .file-item .remove-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-secondary);
            padding: 4px;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }

        .file-item .remove-btn:hover {
            background: #fee2e2;
            color: var(--danger);
        }

        .submit-btn {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            padding: 11px 22px;
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background .15s, transform .1s;
            box-shadow: 0 1px 2px rgba(37, 99, 235, 0.25);
        }

        .submit-btn:hover {
            background: var(--accent-hover);
        }

        .submit-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
        }

        .submit-btn .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        .submit-btn.loading .spinner {
            display: block;
        }

        .submit-btn.loading span:first-child {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .clear-btn {
            background: none;
            border: none;
            color: var(--accent);
            font-family: var(--font-heading);
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            padding: 0;
        }

        .clear-btn:hover {
            color: var(--accent-hover);
        }

        .gf-footer {
            text-align: center;
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 40px;
            padding-top: 8px;
            max-width: 520px;
            margin-left: auto;
            margin-right: auto;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-card {
            background: #fff;
            border-radius: 12px;
            padding: 32px 28px 24px;
            max-width: 440px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
            text-align: center;
            animation: fadeUp .25s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-check {
            width: 56px;
            height: 56px;
            background: #dcfce7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .modal-check svg {
            width: 28px;
            height: 28px;
            stroke: #16a34a;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .modal-body {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 20px;
        }

        .modal-ref-box {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 12px 16px;
            margin-bottom: 20px;
            text-align: left;
        }

        .modal-ref-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-secondary);
            margin-bottom: 4px;
        }

        .modal-ref-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent);
            word-break: break-word;
        }

        .modal-step-value {
            font-size: 14px;
            font-weight: 500;
            color: var(--text-primary);
            line-height: 1.45;
            margin-bottom: 10px;
        }

        .modal-step-value:last-child {
            margin-bottom: 0;
        }

        .modal-progress-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--accent);
            font-size: 14px;
            font-weight: 700;
            text-decoration: underline;
            text-underline-offset: 3px;
            margin: 0 0 18px;
        }

        .modal-progress-link:hover {
            color: var(--accent-hover);
        }

        .modal-close-btn {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 10px 24px;
            font-family: inherit;
            font-size: 14px;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .modal-close-btn:hover {
            background: #f9fafb;
        }

        @media (max-width: 640px) {
            body { padding: 16px 12px 40px; }
            .header-body { padding: 20px 18px 16px; }
            .header-title { font-size: 24px; }
            .field-card { padding: 20px 18px; }
            .form-actions {
                flex-direction: column-reverse;
                align-items: stretch;
            }
            .submit-btn { justify-content: center; width: 100%; }
            .clear-btn { text-align: center; }
            .file-item .file-name { max-width: 180px; }
            .procurement-extra-row { grid-template-columns: 1fr; }
            .procurement-extra-row .remove-extra-item { width: 100%; }
        }

        .hidden { display: none !important; }
    </style>
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

        <!-- Nama Aset yang Diajukan -->
        <div class="field-card" id="item-name-card">
            <label class="field-label">
                Nama Aset yang Diajukan <span class="req">*</span>
            </label>

            <div id="item-name-input-wrap">
                <input type="text" name="item_name" id="item_name" class="gf-input" placeholder="Masukkan nama aset" autocomplete="off">
                <div class="procurement-extra-list" id="procurement-extra-items"></div>
                <button type="button" class="add-procurement-item" onclick="addProcurementItem()">+ Tambah item pengadaan</button>
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

        <!-- Jumlah (Pengadaan only) -->
        <div class="field-card" id="qty-card">
            <label class="field-label" for="qty">
                Jumlah <span class="req">*</span>
            </label>
            <input type="number" name="qty" id="qty" class="gf-input" value="1" min="1" placeholder="Masukkan jumlah">
            <p class="error-msg" id="err-qty">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Jumlah wajib diisi dan minimal 1.
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
                Lampiran / Dokumen Pendukung
            </label>
            <p class="field-desc">Opsional. Unggah maksimum 10MB per file. Format: gambar, PDF, Word, Excel.</p>

            <input type="file" id="attachments-input" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx" onchange="handleFileSelect(event)">

            <div class="file-dropzone" id="file-dropzone" onclick="document.getElementById('attachments-input').click()">
                <p class="file-dropzone-text">Seret &amp; jatuhkan berkas Anda atau <strong>Jelajahi</strong></p>
            </div>

            <div class="file-list" id="file-list"></div>

            <p class="error-msg" id="err-attachments">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                Format atau ukuran file lampiran tidak valid.
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

<!-- Success Modal -->
<div class="modal-overlay" id="success-modal">
    <div class="modal-card">
        <div class="modal-check">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>
        <h2 class="modal-title">Pengajuan Terkirim</h2>
        <p class="modal-body" id="modal-success-body">
            Pengajuan aset Anda berhasil disimpan. Notifikasi email &amp; WhatsApp telah dikirimkan ke pemohon dan penanggung jawab persetujuan.
        </p>
        <div class="modal-ref-box">
            <p class="modal-ref-label" id="modal-ref-label">Nomor Referensi</p>
            <p class="modal-ref-value" id="modal-ref-num">—</p>
        </div>
        <div class="modal-ref-box">
            <p class="modal-ref-label">Status Awal</p>
            <p class="modal-step-value" id="modal-status">—</p>
            <p class="modal-ref-label">Langkah Berikutnya</p>
            <p class="modal-step-value" id="modal-next-step">—</p>
        </div>
        <a href="#" class="modal-progress-link hidden" id="modal-progress-link" target="_blank" rel="noopener noreferrer">Lihat progress pengajuan</a>
        <button class="modal-close-btn" onclick="closeModal()">Isi pengajuan baru</button>
    </div>
</div>

<script>
    const assetsByRecipient = @json($assetsByRecipient);
    const usersById = @json($usersById);
    const defaultApplicantId = @json($defaultApplicantId);
    const hasJobTitles = @json($jobTitles->isNotEmpty());
    const hasBusinessEntities = @json($businessEntities->isNotEmpty());
    const hasDivisions = @json($divisions->isNotEmpty());

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
        const qtyCard = document.getElementById('qty-card');
        const itemNameInputWrap = document.getElementById('item-name-input-wrap');
        const itemNameAssetWrap = document.getElementById('item-name-asset-wrap');

        if (type === 'pengadaan') {
            qtyCard.classList.remove('hidden');
            itemNameInputWrap.classList.remove('hidden');
            itemNameAssetWrap.classList.add('hidden');
        } else {
            qtyCard.classList.add('hidden');
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

    function showFieldError(field) {
        const normalizedField = normalizeErrorField(field);
        const errEl = document.getElementById(`err-${normalizedField}`);
        if (errEl) {
            errEl.classList.add('visible');
        }
        highlightFieldError(normalizedField);
    }

    function showValidationErrors(errors) {
        Object.keys(errors || {}).forEach(field => showFieldError(field));

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
                const referenceNumbers = Array.isArray(data.reference_numbers) && data.reference_numbers.length
                    ? data.reference_numbers
                    : [data.reference_number].filter(Boolean);
                const createdCount = Number(data.created_count || referenceNumbers.length || 1);
                const itemCount = Number(data.item_count || 1);

                document.getElementById('modal-success-body').textContent = itemCount > 1
                    ? `1 pengajuan dengan ${itemCount} item berhasil disimpan. Notifikasi email & WhatsApp telah dikirimkan ke pemohon dan penanggung jawab persetujuan.`
                    : createdCount > 1
                        ? `${createdCount} pengajuan aset berhasil disimpan. Notifikasi email & WhatsApp telah dikirimkan ke pemohon dan penanggung jawab persetujuan.`
                    : 'Pengajuan aset Anda berhasil disimpan. Notifikasi email & WhatsApp telah dikirimkan ke pemohon dan penanggung jawab persetujuan.';
                document.getElementById('modal-ref-label').textContent = createdCount > 1
                    ? 'Nomor Referensi'
                    : 'Nomor Referensi';
                document.getElementById('modal-ref-num').textContent = referenceNumbers.join(', ');
                document.getElementById('modal-status').textContent = data.lifecycle_stage || data.status || 'Menunggu';
                document.getElementById('modal-next-step').textContent = data.next_step || 'Pengajuan akan diproses oleh tim terkait.';
                const progressLink = document.getElementById('modal-progress-link');
                if (data.progress_url) {
                    progressLink.href = data.progress_url;
                    progressLink.classList.remove('hidden');
                } else {
                    progressLink.href = '#';
                    progressLink.classList.add('hidden');
                }
                document.getElementById('success-modal').classList.add('open');
                return;
            }

            showFormAlert(data.message || 'Terjadi kesalahan sistem.');
        })
        .catch(() => showFormAlert('Gagal menghubungi server. Silakan coba lagi.'))
        .finally(() => {
            btn.disabled = !hasDivisions;
            btn.classList.remove('loading');
        });
    });

    if (!hasDivisions) {
        document.getElementById('submit-btn').disabled = true;
    }

    selectDefaultApplicantIfAvailable();
    updateFieldDescriptions();

    // -------- Close Modal --------
    function closeModal() {
        document.getElementById('success-modal').classList.remove('open');
        clearForm();
    }

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
