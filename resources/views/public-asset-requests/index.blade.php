@extends('public-asset-requests.layout')

@section('title', 'Form Request Aset')
@section('header_title', 'Form Request Aset')
@section('header_subtitle', 'Pilih jenis request yang ingin diajukan.')

@section('content')
<script>
    function selectType(slug, element) {
        document.querySelectorAll('.radio-option').forEach(el => el.classList.remove('selected'));
        element.classList.add('selected');
        document.getElementById('selected_slug').value = slug;
        document.getElementById('btn-lanjut').disabled = false;
    }

    function goToForm() {
        const slug = document.getElementById('selected_slug').value;
        if (slug) {
            window.location.href = "{{ url('asset-requests') }}/" + slug;
        }
    }
</script>

<div class="card">
    <div class="radio-options-container">
        <input type="hidden" id="selected_slug" value="">
        @foreach ($types as $slug => $item)
            <div class="radio-option" onclick="selectType('{{ $slug }}', this)">
                <div class="radio-circle">
                    <div class="radio-inner-circle"></div>
                </div>
                <span class="radio-label">{{ $item['label'] }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="button-group">
    <span class="page-indicator">Halaman 1 dari 2</span>
    <button id="btn-lanjut" class="next" onclick="goToForm()" disabled>Lanjut</button>
</div>
@endsection
