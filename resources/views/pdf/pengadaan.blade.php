<!DOCTYPE html>
<html>

<head>
    <title>BERITA ACARA PENGADAAN</title>
    <style>
        @page {
            margin: 0.5cm 1cm;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.5;
            margin: 0;
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
        <h1>BERITA ACARA PENGADAAN</h1>
        <h2>Nomor: {{ $assetRequest->reference_number }}</h2>

        <div class="details">
            <p>Pada hari ini, {{ \Carbon\Carbon::parse($assetRequest->fulfilled_at ?? $assetRequest->created_at)->translatedFormat('l, d F Y') }},
                yang bertanda tangan di bawah ini, atas nama perusahaan, menerangkan bahwa telah dilakukan
                pengadaan aset berdasarkan pengajuan dari:</p>
            <table class="details-table">
                <tr>
                    <td style="width: 18%;">Nama Pemohon</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 71%;"><strong>{{ $assetRequest->user->name }}</strong></td>
                </tr>
                <tr>
                    <td style="width: 18%;">Jabatan</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 71%;">{{ optional($assetRequest->user->jobTitle)->title }}</td>
                </tr>
                <tr>
                    <td style="width: 18%;">Divisi</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 71%;">{{ optional($assetRequest->division)->name }}</td>
                </tr>
            </table>

            <p>Pengadaan dilaksanakan oleh:</p>
            <table class="details-table">
                <tr>
                    <td style="width: 18%;">Nama Operator</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 71%;"><strong>{{ optional($assetRequest->fulfilledBy)->name }}</strong></td>
                </tr>
                <tr>
                    <td style="width: 18%;">Jabatan</td>
                    <td style="width: 1%;">:</td>
                    <td style="width: 71%;">{{ optional($assetRequest->fulfilledBy?->jobTitle)->title }}</td>
                </tr>
            </table>
        </div>

        @if ($assetRequest->description)
            <p class="justify" style="margin-bottom: 20px;">Keterangan pengajuan: {{ $assetRequest->description }}</p>
        @endif

        <p style="margin-bottom: 5px;">Adapun aset yang diadakan antara lain berupa:</p>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Nama Aset</th>
                        <th>Kategori</th>
                        <th>Merek/Type</th>
                        <th>Serial Number/IMEI</th>
                        <th>Lokasi</th>
                        <th>Qty</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($assetRequest->createdAssets as $index => $asset)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $asset->name }}</td>
                            <td>{{ optional($asset->category)->name }}</td>
                            <td>{{ optional($asset->brand)->name }} {{ $asset->type ?? '' }}</td>
                            <td>{{ trim(($asset->serial_number ?? '') . ' ' . ($asset->imei1 ?? '') . ' ' . ($asset->imei2 ?? '')) }}</td>
                            <td>{{ optional($asset->assetLocation)->name }}</td>
                            <td>{{ $asset->qty }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="justify">Demikian Berita Acara Pengadaan ini dibuat dengan sebenarnya, untuk dapat dipergunakan
            sebagaimana mestinya.</p>

        <p></p>

        <table class="signature-table">
            <tr>
                <td>
                    <p>Pemohon</p>
                    <div class="signature-space"></div>
                    <p><strong>{{ $assetRequest->user->name }}</strong></p>
                </td>
                <td>
                    <p>Operator</p>
                    <div class="signature-space"></div>
                    <p><strong>{{ optional($assetRequest->fulfilledBy)->name }}</strong></p>
                </td>
                <td>
                    <p>Mengetahui</p>
                    <div class="signature-space"></div>
                    <p><strong>ARLENI</strong></p>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>