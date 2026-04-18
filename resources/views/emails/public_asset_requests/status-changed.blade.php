<x-mail::message>
@php
    $requestTypeLabel = match ($assetRequest->request_type) {
        'pengadaan_aset' => 'Pengadaan Aset',
        'perbaikan_aset' => 'Perbaikan Aset',
        'penarikan_aset' => 'Penarikan Aset',
        default => 'Request Aset',
    };
@endphp

# Status pengajuan diperbarui

<div class="mail-hero">
    <p class="mail-copy">
        Halo <strong>{{ $assetRequest->requester_name }}</strong>, ada pembaruan untuk pengajuan Anda.
    </p>
    <p class="mail-helper">
        Untuk saat ini statusnya <span class="status-text status-{{ $assetRequest->status->value }}">{{ $assetRequest->status->label() }}</span>.
    </p>
</div>

<div class="mail-section">
    <p class="mail-section-title">Ringkasan pengajuan</p>
    <div class="section-card">
        <table role="presentation" class="summary-table">
            <tr>
                <td class="summary-label">ID Request</td>
                <td class="summary-value">{{ $assetRequest->uuid }}</td>
            </tr>
            <tr>
                <td class="summary-label">Jenis Request</td>
                <td class="summary-value">{{ $requestTypeLabel }}</td>
            </tr>
            <tr>
                <td class="summary-label">Nama Barang</td>
                <td class="summary-value">{{ $assetRequest->item_name }}</td>
            </tr>
            <tr>
                <td class="summary-label">Qty</td>
                <td class="summary-value">{{ $assetRequest->qty }}</td>
            </tr>
        </table>
    </div>
</div>

@if($assetRequest->admin_notes)
<div class="mail-section">
    <p class="mail-section-title">Keterangan proses</p>
    <div class="note-box">{{ $assetRequest->admin_notes }}</div>
</div>
@endif

<x-mail::button :url="$detailUrl" color="primary">
Lihat status pengajuan
</x-mail::button>

<x-slot:subcopy>
Gunakan tautan di atas untuk melihat detail pengajuan dan perkembangan proses approval.
</x-slot:subcopy>
</x-mail::message>
