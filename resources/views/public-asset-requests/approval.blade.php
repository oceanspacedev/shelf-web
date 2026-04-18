@extends('public-asset-requests.layout')

@section('title', 'Persetujuan Pengajuan')
@section('header_title', $requestTypeLabel)
@section('header_subtitle', 'Persetujuan Level ' . $approval->level . ' — ' . $approval->approver_name)

@section('styles')
    .detail-row {
        display: flex;
        padding: 12px 0;
        border-bottom: 1px solid #f1f3f4;
        font-size: 14px;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-label {
        width: 160px;
        flex-shrink: 0;
        font-weight: 500;
        color: #5f6368;
    }

    .detail-value {
        color: #202124;
        flex: 1;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 500;
    }

    .status-pending { background-color: #fef7e0; color: #b45309; }
    .status-approved { background-color: #e6f4ea; color: #137333; }
    .status-rejected { background-color: #fce8e6; color: #c5221f; }

    .approval-actions {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }

    .approval-actions button {
        flex: 1;
        padding: 12px 24px;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        border: none;
        transition: all 0.2s;
    }

    .btn-approve {
        background-color: #137333;
        color: #fff;
    }

    .btn-approve:hover {
        background-color: #0d5c28;
    }

    .btn-reject {
        background-color: #fff;
        color: #c5221f;
        border: 1px solid #dadce0 !important;
    }

    .btn-reject:hover {
        background-color: #fce8e6;
    }

    textarea {
        width: 100%;
        padding: 10px 14px;
        font-size: 14px;
        border: 1px solid #dadce0;
        border-radius: 4px;
        font-family: 'Roboto', sans-serif;
        box-sizing: border-box;
        resize: vertical;
        min-height: 80px;
        transition: all 0.2s;
    }

    textarea:focus {
        border-color: #1a73e8;
        box-shadow: 0 0 0 1px #1a73e8;
        outline: none;
    }

    .responded-notice {
        display: flex;
        align-items: center;
        gap: 8px;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 12px;
        font-size: 13px;
    }

    .responded-approved {
        background-color: #e6f4ea;
        color: #137333;
    }

    .responded-rejected {
        background-color: #fce8e6;
        color: #c5221f;
    }

    .flash-message {
        background-color: #e6f4ea;
        color: #137333;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 12px;
        font-size: 13px;
    }

    .attachment-link {
        color: #1a73e8;
        text-decoration: none;
        font-weight: 500;
    }

    .attachment-link:hover {
        text-decoration: underline;
    }
@endsection

@section('content')
    @if($requestClosed && ! $hasResponded)
        <div class="flash-message" style="background-color: #e8f0fe; color: #1a73e8;">
            Pengajuan ini sudah selesai diproses. Link approval ini tidak lagi aktif.
        </div>
    @elseif(! $hasResponded && ! $isCurrentApproval)
        <div class="flash-message" style="background-color: #e8f0fe; color: #1a73e8;">
            Pengajuan ini masih menunggu approval level sebelumnya.
        </div>
    @elseif($hasResponded)
        <div class="responded-notice responded-{{ $approval->status->value }}">
            Anda telah <strong>{{ mb_strtolower($approval->status->label()) }}</strong> pengajuan ini
            pada {{ $approval->responded_at?->format('d M Y, H:i') }}.
        </div>
    @endif

    <div class="card">
        <div class="detail-row">
            <span class="detail-label">Jenis Request</span>
            <span class="detail-value">{{ $requestTypeLabel }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Nama Pemohon</span>
            <span class="detail-value">{{ $assetRequest->requester_name }}</span>
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
        <div class="detail-row">
            <span class="detail-label">Tanggal Submit</span>
            <span class="detail-value">{{ $assetRequest->created_at->format('d M Y, H:i') }}</span>
        </div>
    </div>

    @if($canRespond)
        <div class="card">
            <label for="notes" style="font-size: 14px; font-weight: 500; color: #202124; margin-bottom: 8px; display: block;">
                Catatan (opsional)
            </label>
            <form id="approval-form" method="POST" action="{{ route('asset-requests.process-approval', $approval->token) }}">
                @csrf
                <input type="hidden" name="action" id="approval-action" value="">
                <textarea id="notes" name="notes" placeholder="Tambahkan catatan jika diperlukan..."></textarea>

                <div class="approval-actions">
                    <button type="button" class="btn-reject" onclick="submitApproval('reject')">Tolak</button>
                    <button type="button" class="btn-approve" onclick="submitApproval('approve')">Setujui</button>
                </div>
            </form>
        </div>
    @endif

<script>
    function submitApproval(action) {
        const actionLabel = action === 'approve' ? 'menyetujui' : 'menolak';
        if (confirm(`Apakah Anda yakin ingin ${actionLabel} pengajuan ini?`)) {
            document.getElementById('approval-action').value = action;
            document.getElementById('approval-form').submit();
        }
    }
</script>
@endsection
