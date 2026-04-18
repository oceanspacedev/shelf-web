<x-mail::message>
@php
    $requestTypeLabel = match ($assetRequest->request_type) {
        'pengadaan_aset' => 'Pengadaan Aset',
        'perbaikan_aset' => 'Perbaikan Aset',
        'penarikan_aset' => 'Penarikan Aset',
        default => 'Request Aset',
    };
@endphp

# Persetujuan pengajuan aset

<div class="mail-hero">
    <p class="mail-copy">
        Halo <strong>{{ $approval->approver_name }}</strong>, ada pengajuan aset yang menunggu peninjauan Anda pada level {{ $approval->level }}.
    </p>
    <p class="mail-helper">
        Silakan buka halaman persetujuan untuk melihat detail pengajuan lalu berikan keputusan.
    </p>
</div>

<div class="mail-section">
    <p class="mail-section-title">Ringkasan pengajuan</p>
    <div class="section-card">
        <table role="presentation" class="summary-table">
            <tr>
                <td class="summary-label">Jenis Request</td>
                <td class="summary-value">{{ $requestTypeLabel }}</td>
            </tr>
            <tr>
                <td class="summary-label">Nama Pemohon</td>
                <td class="summary-value">{{ $assetRequest->requester_name }}</td>
            </tr>
            <tr>
                <td class="summary-label">Divisi</td>
                <td class="summary-value">{{ $assetRequest->division }}</td>
            </tr>
            <tr>
                <td class="summary-label">Penempatan</td>
                <td class="summary-value">{{ $assetRequest->placement }}</td>
            </tr>
            <tr>
                <td class="summary-label">Nama Barang</td>
                <td class="summary-value">{{ $assetRequest->item_name }}</td>
            </tr>
            <tr>
                <td class="summary-label">Qty</td>
                <td class="summary-value">{{ $assetRequest->qty }}</td>
            </tr>
            <tr>
                <td class="summary-label">Dikirim</td>
                <td class="summary-value">{{ $assetRequest->created_at->format('d M Y, H:i') }}</td>
            </tr>
        </table>
    </div>
</div>

<x-mail::button :url="$approvalUrl" color="primary">
Buka halaman persetujuan
</x-mail::button>

<div class="note-box">
    Tautan persetujuan ini bersifat unik untuk penerima email dan tidak perlu dibagikan ke pihak lain.
</div>

<x-slot:subcopy>
Jika tombol tidak dapat dibuka, salin dan buka tautan persetujuan berikut: {{ $approvalUrl }}
</x-slot:subcopy>
</x-mail::message>
