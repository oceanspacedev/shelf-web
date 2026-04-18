@extends('public-asset-requests.layout')

@section('title', $requestType['label'])
@section('header_title', $requestType['label'])
@section('header_subtitle', 'Silakan isi data berikut untuk mengirim request.')
@section('show_required', true)

@section('content')
<form method="POST" action="{{ route('asset-requests.store', $slug) }}" enctype="multipart/form-data">
    @csrf

    <div class="card">
        <label for="requester_name">Nama<span class="required">*</span></label>
        <input id="requester_name" name="requester_name" type="text" autocomplete="off" value="{{ old('requester_name') }}" required placeholder="Nama lengkap Anda">
    </div>

    <div class="card">
        <label for="email">Alamat Email<span class="required">*</span></label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required placeholder="email@perusahaan.com">
        <p class="hint">Konfirmasi request akan dikirim ke email ini.</p>
    </div>

    <div class="card">
        <label for="division">Divisi<span class="required">*</span></label>
        <input
            id="division"
            name="division"
            type="text"
            value="{{ old('division') }}"
            placeholder="Contoh: Finance, Operations"
            @if($divisions->isNotEmpty()) list="division-options" @endif
            required
        >
        @if($divisions->isNotEmpty())
            <datalist id="division-options">
                @foreach($divisions as $division)
                    <option value="{{ $division }}"></option>
                @endforeach
            </datalist>
            <p class="hint">Gunakan nama divisi sesuai konfigurasi approval. Divisi di luar daftar tetap bisa diajukan jika ada jalur approval umum.</p>
        @else
            <p class="hint">Master divisi belum tersedia, jadi sementara masih input manual.</p>
        @endif
    </div>

    <div class="card">
        <label for="placement">Penempatan<span class="required">*</span></label>
        <input id="placement" name="placement" type="text" value="{{ old('placement') }}" required placeholder="Contoh: Central Jakarta">
    </div>

    <div class="card">
        <label for="item_name">Nama barang<span class="required">*</span></label>
        <input id="item_name" name="item_name" type="text" autocomplete="off" value="{{ old('item_name') }}" required placeholder="Nama barang yang di-request">
        @if (in_array($slug, ['perbaikan-aset', 'penarikan-aset'], true))
            <p class="hint">Untuk perbaikan atau penarikan, sistem akan mencoba mereferensikan aset berdasarkan nama pemohon dan nama barang saat form dikirim.</p>
        @endif
    </div>

    <div class="card">
        <label for="qty">Qty<span class="required">*</span></label>
        <input id="qty" name="qty" type="number" min="1" value="{{ old('qty', 1) }}" required placeholder="Jumlah barang">
    </div>

    <div class="card">
        <label for="attachment">Lampiran</label>
        <input id="attachment" name="attachment" type="file" accept=".pdf,image/*">
        <p class="hint">Opsional. Maksimal 1 file, ukuran 1 MB, format PDF atau image.</p>
    </div>

    <div class="button-group">
        <span class="page-indicator">Halaman 2 dari 2</span>
        <div class="form-actions">
            <a class="back" href="{{ route('asset-requests.index') }}">Kembali</a>
            <button class="button" type="submit">Submit Request</button>
        </div>
    </div>
</form>

@endsection
