<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --accent: #2563eb;
        --accent-hover: #1d4ed8;
        --accent-light: #eff6ff;
        --danger: #dc2626;
        --success: #16a34a;
        --success-hover: #15803d;
        --bg: #eef2f7;
        --card: #ffffff;
        --text-primary: #111827;
        --text-secondary: #6b7280;
        --text-label: #374151;
        --border: #d1d5db;
        --border-focus: #2563eb;
        --input-bg: #ffffff;
        --radius: 8px;
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

    .page-stack {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 12px;
    }

    .field-card {
        background: var(--card);
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, .12), 0 1px 2px rgba(0, 0, 0, .08);
    }

    .field-label {
        display: block;
        font-family: var(--font-heading);
        font-size: 14px;
        font-weight: 600;
        color: var(--text-label);
        margin-bottom: 8px;
    }

    .field-label + .field-label {
        margin-top: 12px;
    }

    .field-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin-bottom: 8px;
        line-height: 1.45;
        max-width: 65ch;
    }

    .gf-input,
    .gf-readonly {
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
    }

    .gf-readonly {
        background: #f9fafb;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    textarea.gf-input,
    textarea.gf-readonly {
        resize: vertical;
        min-height: 96px;
        line-height: 1.5;
        display: block;
        align-items: unset;
    }

    .badge {
        display: inline-flex;
        align-items: center;
        min-height: 26px;
        border-radius: 999px;
        padding: 3px 10px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge.pending { background: #fef3c7; color: #92400e; }
    .badge.approved { background: #dcfce7; color: #166534; }
    .badge.rejected { background: #fee2e2; color: #991b1b; }
    .badge.info { background: #dbeafe; color: #1e40af; }

    .form-alert {
        border: 1px solid #fecaca;
        background: #fef2f2;
        color: #991b1b;
        border-radius: var(--radius);
        padding: 12px 14px;
        font-size: 13px;
        line-height: 1.5;
    }

    .form-alert.warning {
        border-color: #fde68a;
        background: #fffbeb;
        color: #92400e;
    }

    .form-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-top: 24px;
        padding-top: 8px;
    }

    .form-actions.single {
        justify-content: flex-end;
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
        transition: background .15s;
        box-shadow: 0 1px 2px rgba(37, 99, 235, 0.25);
    }

    .submit-btn:hover { background: var(--accent-hover); }

    .submit-btn.success {
        background: var(--success);
        box-shadow: 0 1px 2px rgba(22, 163, 74, 0.25);
    }

    .submit-btn.success:hover { background: var(--success-hover); }

    .submit-btn.danger {
        background: var(--danger);
        box-shadow: 0 1px 2px rgba(220, 38, 38, 0.25);
    }

    .submit-btn.danger:hover { background: #b91c1c; }

    .clear-btn {
        background: none;
        border: none;
        color: var(--accent);
        font-family: var(--font-heading);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        padding: 0;
        text-decoration: none;
    }

    .clear-btn:hover { color: var(--accent-hover); }

    .error-msg {
        margin-top: 8px;
        color: var(--danger);
        font-size: 13px;
        font-weight: 600;
    }

    .gf-footer {
        text-align: center;
        font-size: 12px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-top: 40px;
        padding-top: 8px;
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
        padding: 28px 24px 22px;
        max-width: 440px;
        width: 100%;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
        text-align: center;
    }

    .modal-title {
        font-family: var(--font-heading);
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .modal-body {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin-bottom: 22px;
    }

    .modal-actions {
        display: flex;
        justify-content: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .modal-cancel-btn {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 10px 20px;
        font-family: var(--font-heading);
        font-size: 14px;
        font-weight: 500;
        color: var(--text-secondary);
        cursor: pointer;
    }

    .modal-cancel-btn:hover {
        background: #f9fafb;
    }

    .hidden { display: none !important; }

    .table-wrap {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 12px;
        font-weight: 600;
        text-align: left;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .data-table td {
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 14px;
        vertical-align: top;
    }

    .data-table tr:last-child td {
        border-bottom: 0;
    }

    .data-table td.col-num,
    .data-table th.col-num {
        text-align: right;
        white-space: nowrap;
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
        .modal-actions { flex-direction: column-reverse; }
        .modal-actions .submit-btn,
        .modal-actions .modal-cancel-btn { width: 100%; justify-content: center; }
    }
</style>
