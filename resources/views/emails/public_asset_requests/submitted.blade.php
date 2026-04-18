<x-mail::message>
@php
    $requestTypeLabel = match ($assetRequest->request_type) {
        'pengadaan_aset' => 'Pengadaan Aset',
        'perbaikan_aset' => 'Perbaikan Aset',
        'penarikan_aset' => 'Penarikan Aset',
        default => 'Request Aset',
    };
@endphp

# Request aset berhasil dikirim

<div class="mail-hero">
    <p class="mail-copy">
        Halo <strong>{{ $assetRequest->requester_name }}</strong>, pengajuan Anda sudah kami terima dan sedang masuk ke alur proses yang sesuai.
    </p>
    <p class="mail-helper">
        Anda bisa membuka kembali email ini kapan saja untuk melihat status pengajuan tanpa perlu login.
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

<x-mail::button :url="$detailUrl" color="primary">
Lihat status pengajuan
</x-mail::button>

<x-slot:subcopy>
Gunakan tombol di atas jika Anda ingin memantau perkembangan pengajuan atau membuka kembali detail request.
</x-slot:subcopy>
</x-mail::message>
