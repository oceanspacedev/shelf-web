<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .grid { width: 100%; }
        .label {
            display: inline-block;
            width: 45%;
            vertical-align: top;
            border: 1px solid #ccc;
            padding: 8px;
            margin: 1%;
            text-align: center;
            page-break-inside: avoid;
        }
        .name { font-weight: bold; font-size: 12px; margin-bottom: 4px; }
        .meta { color: #444; margin-top: 4px; }
        img.qr { width: 120px; height: 120px; }
    </style>
</head>
<body>
@foreach ($assets as $asset)
    @php
        $qr = $asset->qr ?? $qrService->ensureForAsset($asset);
        $png = base64_encode($qrService->png($qr, 240));
    @endphp
    <div class="label">
        <div class="name">{{ $asset->name }}</div>
        <img class="qr" src="data:image/png;base64,{{ $png }}" alt="QR">
        <div class="meta">{{ $asset->assetLocation?->name ?? '-' }}</div>
        <div class="meta">{{ $qr->id }}</div>
    </div>
@endforeach
</body>
</html>
