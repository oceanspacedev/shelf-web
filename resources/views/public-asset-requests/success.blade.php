@extends('public-asset-requests.layout')

@section('title', 'Request Berhasil Dikirim')
@section('header_title', $requestTypeLabel)
@section('header_subtitle', 'Respons Anda telah direkam.')

@section('styles')
    .confirmation-text {
        font-size: 14px;
        color: #137333;
        font-weight: 500;
        margin: 0 0 8px;
    }

    .section-title {
        font-size: 16px;
        font-weight: 500;
        color: #202124;
        margin: 0 0 6px;
    }

    .section-hint {
        font-size: 13px;
        color: #5f6368;
        margin: 0 0 18px;
    }

    .status-value {
        font-size: 13px;
        font-weight: 500;
    }

    .status-pending {
        color: #b45309;
    }

    .status-approved {
        color: #137333;
    }

    .status-rejected {
        color: #c5221f;
    }

    .detail-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #f1f3f4;
        font-size: 14px;
        gap: 16px;
    }

    .detail-row:first-of-type {
        padding-top: 0;
    }

    .detail-row:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }

    .detail-label {
        width: 170px;
        flex-shrink: 0;
        color: #5f6368;
        font-weight: 500;
    }

    .detail-value {
        flex: 1;
        color: #202124;
        min-width: 0;
        word-break: break-word;
    }

    .approval-block {
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px solid #f1f3f4;
    }

    .approval-block:first-of-type {
        margin-top: 0;
        padding-top: 0;
        border-top: none;
    }

    .approval-title {
        font-size: 14px;
        font-weight: 500;
        color: #202124;
        margin: 0 0 12px;
    }

    .attachment-link,
    .inline-link {
        color: #1a73e8;
        text-decoration: none;
        font-weight: 500;
    }

    .attachment-link:hover,
    .inline-link:hover {
        text-decoration: underline;
    }

    @media (max-width: 640px) {
        .detail-row,
        .button-group {
            flex-direction: column;
            align-items: stretch;
        }

        .detail-label {
            width: auto;
        }

        .form-actions .back {
            width: 100%;
            text-align: center;
        }
    }
@endsection

@section('content')
    @php
        $hasApprovalNotes = $assetRequest->approvals->contains(fn ($approval) => filled($approval->notes));
    @endphp

    <div class="card">
        <p class="confirmation-text">Request berhasil dikirim.</p>
        <p class="section-hint">Simpan halaman ini untuk melihat status pengajuan tanpa perlu login.</p>

        <div class="detail-row">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                <span class="status-value status-{{ $assetRequest->status->value }}">
                    {{ $assetRequest->status->label() }}
                </span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Dikirim</span>
            <span class="detail-value">{{ $assetRequest->created_at->format('d M Y, H:i') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Konfirmasi</span>
            <span class="detail-value">
                Dikirim ke {{ $assetRequest->email }}. Jika email belum masuk, tetap gunakan tautan halaman ini sebagai referensi.
            </span>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Ringkasan Pengajuan</h2>
        <p class="section-hint">Data di bawah ini sama dengan yang Anda kirim pada form.</p>

        <div class="detail-row">
            <span class="detail-label">ID Request</span>
            <span class="detail-value">{{ $assetRequest->uuid }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Jenis Request</span>
            <span class="detail-value">{{ $requestTypeLabel }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Nama Pemohon</span>
            <span class="detail-value">{{ $assetRequest->requester_name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Email</span>
            <span class="detail-value">{{ $assetRequest->email }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Divisi</span>
            <span class="detail-value">{{ $assetRequest->division }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Penempatan</span>
            <span class="detail-value">{{ $assetRequest->placement }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Nama Barang</span>
            <span class="detail-value">{{ $assetRequest->item_name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Qty</span>
            <span class="detail-value">{{ $assetRequest->qty }}</span>
        </div>
        @if($assetRequest->attachment_url)
            <div class="detail-row">
                <span class="detail-label">Lampiran</span>
                <span class="detail-value">
                    <a class="attachment-link" href="{{ $assetRequest->attachment_url }}" target="_blank" rel="noopener noreferrer">
                        {{ $assetRequest->attachment_label }}
                    </a>
                </span>
            </div>
        @endif
    </div>

    <div class="card">
        <h2 class="section-title">Status Pengajuan</h2>
        <p class="section-hint">
            @if($assetRequest->approvals->isNotEmpty())
                Progress approval akan diperbarui di halaman ini.
            @else
                Pengajuan ini tidak memakai level approval tambahan.
            @endif
        </p>

        @if($assetRequest->approvals->isNotEmpty())
            @foreach($assetRequest->approvals as $step)
                <div class="approval-block">
                    <p class="approval-title">Level {{ $step->level }}</p>

                    <div class="detail-row">
                        <span class="detail-label">Approver</span>
                        <span class="detail-value">{{ $step->approver_name }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value">{{ $step->approver_email }}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <span class="status-value status-{{ $step->status->value }}">
                                {{ $step->status->label() }}
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Waktu Respons</span>
                        <span class="detail-value">
                            @if($step->responded_at)
                                {{ $step->responded_at->format('d M Y, H:i') }}
                            @else
                                Menunggu respons approver
                            @endif
                        </span>
                    </div>
                    @if($step->notes)
                        <div class="detail-row">
                            <span class="detail-label">Catatan</span>
                            <span class="detail-value">{{ $step->notes }}</span>
                        </div>
                    @endif
                </div>
            @endforeach
        @else
            <div class="detail-row">
                <span class="detail-label">Status</span>
                <span class="detail-value">{{ $assetRequest->status->label() }}</span>
            </div>
        @endif

        @if($assetRequest->admin_notes && ($assetRequest->approvals->isEmpty() || ! $hasApprovalNotes))
            <div class="approval-block">
                <p class="approval-title">Keterangan Proses</p>
                <div class="detail-row">
                    <span class="detail-label">Catatan</span>
                    <span class="detail-value">{{ $assetRequest->admin_notes }}</span>
                </div>
            </div>
        @endif
    </div>

    <div class="button-group">
        <span class="page-indicator">
            Butuh form baru? <a class="inline-link" href="{{ route('asset-requests.index') }}">Buka halaman awal</a>
        </span>
        <div class="form-actions">
            <a class="back" href="{{ route('asset-requests.index') }}">Kembali</a>
        </div>
    </div>
@endsection
