<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Pengerjaan {{ $task->name }}</title>
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
            text-transform: uppercase;
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

        .signature-table {
            margin-top: 40px;
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

        .attachments .grid-container {
            margin-top: 28px;
            width: 100%;
            padding: 0;
            box-sizing: border-box;
        }

        .attachments .grid-container img {
            width: 128px;
            height: 200px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

    </style>
</head>

<body>
    @php
        $isGa = function ($user) {
            if (!$user) return false;
            return $user->id === 2 
                || in_array(strtolower($user->name), ['ga', 'general affair', 'general_affair']) 
                || strtolower($user->username ?? '') === 'adminga';
        };
    @endphp
    <!-- Kop Surat -->
    <div class="header">
        <img src="{{ $headerImage }}" alt="Kop Surat">
    </div>

    <div class="content">
        <!-- Judul dan Nomor Surat -->
        <h1>Berita Acara Pengerjaan {{ $task->name }}</h1>
        <h2>Nomer: {{ $task->code }}</h2>

        <!-- Detail Pengerjaan -->
        <div class="details">
            <p>Pada hari <strong>{{ \Carbon\Carbon::parse($task->work_timestamp)->translatedFormat('l, d F Y H:i') }}</strong>, telah dilakukan pengerjaan berupa:</p>
            <table class="details-table">
                <tr>
                    <td style="width: 22%;">Nama Pekerjaan</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 66%;"><strong>{{ $task->name }}</strong></td>
                </tr>
                <tr>
                    <td style="width: 22%;">Deskripsi</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 66%;">{{ $task->description }}</td>
                </tr>
                <tr>
                    <td style="width: 22%;">Tempat Pelaksanaan</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 66%;">{{ $task->location }}</td>
                </tr>
                <tr>
                    <td style="width: 22%;">Harga Pengerjaan</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 66%;">Rp {{ number_format($task->cost, 2) }}</td>
                </tr>
                <tr>
                    <td style="width: 22%;">Lampiran</td>
                    <td style="width: 2%;">:</td>
                    <td style="width: 66%;"></td>
                </tr>
            </table>
        </div>

        <div class="attachments">
                <div class="grid-container">
                    {!! $attachments !!}
                </div>
        </div>

        <p>Dengan ini, disampaikan bahwa pengerjaan telah <strong>selesai</strong>.</p>

        <!-- Tanda Tangan -->
        <table class="signature-table">
            <tr>
                <td>
                    <p>Mengetahui</p>
                    <div class="signature-space"></div>
                    <p><strong>HR Manager</strong></p>
                </td>
                <td>
                    <p>Pelaksana</p>
                    <div class="signature-space"></div>
                    <p><strong>{{ $task->user && $isGa($task->user) ? '' : ($task->user->name ?? '') }}</strong></p>
                </td>
            </tr>
        </table>
    </div>
</body>

</html>
