<!DOCTYPE html>
<html>

<head>
    <title>{{ $assetTransfer->status }}</title>
    <style>
        @page {
            margin: 0.5cm 1cm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.5;
            margin: 0;
            /* Mengatur margin body ke 0 untuk menghindari jarak di sekitar body */
            padding: 0;
        }

        .header {
            text-align: center;
            margin: 0;
            padding: 0;
        }

        .header img {
            width: 100%;
            height: auto;
        }

        .content {
            margin: 32px;
            /* Mengatur margin untuk konten selain header */
        }

        h1,
        h2 {
            text-align: center;
            font-size: 18px;
            margin: 0;
            padding: 0;
        }

        h2 {
            margin-bottom: 32px;
        }

        .details,
        .table-container {
            margin-bottom: 30px;
        }

        .details p {
            margin: 0px;
            font-size: 16px;
            line-height: 1.4;
        }

        .details-table,
        .signature-table {
            width: 100%;
            margin-bottom: 30px;
            font-size: 16px;
            border: none;
        }

        .details-table td,
        .signature-table td {
            padding: 0px;
            vertical-align: top;
        }

        .details-table td {
            border: none;
        }

        .signature-table td {
            text-align: center;
            width: 33.33%;
            border: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 16px;
        }

        table,
        th,
        td {
            border: 1px solid black;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
        }

        .signature-table p {
            margin: 0;
        }

        .signature-space {
            height: 80px;
            /* Menyesuaikan tinggi ruang untuk tanda tangan */
        }

        .justify {
            text-align: justify;
        }
    </style>
</head>

<body>
    <div class="header">
        <img src="{{ $headerImage }}" alt="Kop Surat">
    </div>
    <div class="content">
        <h1>{{ $assetTransfer->status }}</h1>
        <h2>Nomor: {{ $assetTransfer->letter_number }}</h2>

        <div class="details">
            <p>Pada hari ini, {{ \Carbon\Carbon::parse($assetTransfer->transfer_date)->translatedFormat('l, d F Y') }},
                Karyawan yang bertanda tangan di bawah ini:</p>
            <table class="details-table">
                <tr>
                    <td style="width: 10%;">Nama</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 79%;"><strong>{{ $assetTransfer->toUser->name }}</strong></td>
                </tr>
                <tr>
                    <td style="width: 10%;">Jabatan</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 79%;">{{ optional($assetTransfer->toUser->jobTitle)->title }}</td>
                </tr>
            </table>

            @if (
                $assetTransfer->status === 'BERITA ACARA SERAH TERIMA' ||
                    $assetTransfer->status === 'BERITA ACARA PENGALIHAN BARANG' ||
                    $assetTransfer->status === 'BERITA ACARA PENGEMBALIAN BARANG')
                <p>Menyatakan telah menerima <strong>Aset Perusahaan</strong> dari:</p>
                <table class="details-table">
                    <tr>
                        <td style="width: 10%;">Nama</td>
                        <td style="width: 1%;">:</td>
                        <td style="width: 79%;"><strong>{{ $assetTransfer->fromUser->name === 'GA' ? '' : $assetTransfer->fromUser->name }}</strong></td>
                    </tr>
                    <tr>
                        <td style="width: 10%;">Jabatan</td>
                        <td style="width: 1%;">:</td>
                        <td style="width: 79%;">{{ $assetTransfer->fromUser->name === 'GA' ? '' : optional($assetTransfer->fromUser->jobTitle)->title }}</td>
                    </tr>
                </table>
            @endif
        </div>

        <p style="margin-bottom: 5px;">Adapun Aset Perusahaan yang diserahkan kepada Karyawan antara lain berupa:</p>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kategori</th>
                        <th>Merek/Type</th>
                        <th>Serial Number/IMEI</th>
                        <th>Perlengkapan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assetTransfer->details as $index => $detail)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $detail->asset->category->name }}</td>
                            <td>{{ $detail->asset->brand->name ?? '' }} {{ $detail->asset->type ?? '' }}</td>
                            <td>{{ $detail->asset->attributes[0]->attribute_value ?? '' }}</td>
                            <td>{{ $detail->equipment }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($assetTransfer->status == 'BERITA ACARA SERAH TERIMA')
            <p class="justify">Inventaris tersebut digunakan untuk menunjang kinerja karyawan dalam menjalankan tugas dan tanggungjawab
                pada perusahaan. Apabila masa kerja saya sudah berakhir, saya akan mengembalikannya pada perusahaan.
                Kerusakan dan kehilangan terhadap inventaris beserta kelengkapannya sepenuhnya menjadi tanggungjawab
                saya kecuali kerusakan yang terjadi akibat <i>force majeure</i> seperti bencana alam seperti gempa, banjir,
                tanah longsor, dan lain-lain.</p>
        @endif

        <p class="justify">Demikian {{ ucwords(strtolower($assetTransfer->status)) }} ini dibuat dengan sebenarnya dan untuk
            dipergunakan sebagaimana mestinya.</p>

        <p></p>

        <table class="signature-table">
            <tr>
                <td>
                    <p>Penerima</p>
                    <div class="signature-space"></div>
                    <p><strong>{{ $assetTransfer->toUser->name }}</strong></p>
                </td>
                <td>
                    <p>Pemberi</p>
                    <div class="signature-space"></div>
                    <p><strong>{{ $assetTransfer->fromUser->name === 'GA' ? '' : $assetTransfer->fromUser->name }}</strong></p>
                </td>
                @if ($assetTransfer->status === 'BERITA ACARA PENGEMBALIAN BARANG')
                    <td>
                        <p>Mengetahui</p>
                        <div class="signature-space"></div>
                        <p><strong>ARLENI</strong></p>
                    </td>
                @endif
            </tr>
        </table>
    </div>
</body>

</html>
