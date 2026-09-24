<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        /* Samakan ukuran stiker print label QR (~3.2 cm) */
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7px;
            margin: 0;
            padding: 0.3cm;
        }
        .sheet {
            width: 100%;
        }
        .label {
            display: inline-block;
            width: 3.2cm;
            vertical-align: top;
            border: 1px solid #111;
            padding: 0.2cm;
            margin: 0 0.25cm 0.25cm 0;
            text-align: center;
            page-break-inside: avoid;
        }
        .name {
            font-weight: bold;
            font-size: 7px;
            line-height: 1.25;
            margin-bottom: 0.15cm;
            word-wrap: break-word;
        }
        img.qr {
            width: 2.2cm;
            height: 2.2cm;
        }
    </style>
</head>
<body>
<div class="sheet">
@foreach ($assets as $asset)
    @php
        $qr = $asset->qr ?? $qrService->ensureForAsset($asset);
        $png = base64_encode($qrService->png($qr, 240));
    @endphp
    <div class="label">
        <div class="name">{{ $asset->name }}</div>
        <img class="qr" src="data:image/png;base64,{{ $png }}" alt="QR">
    </div>
@endforeach
</div>
</body>
</html>
