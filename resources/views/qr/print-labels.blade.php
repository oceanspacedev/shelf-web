<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Label QR</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 1.25rem;
            background: #f3f4f6;
            color: #111827;
        }
        .toolbar {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }
        .toolbar button {
            appearance: none;
            border: none;
            border-radius: 0.5rem;
            padding: 0.5rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print { background: #111827; color: #fff; }
        .btn-close { background: #e5e7eb; color: #111827; }
        .hint { font-size: 0.75rem; color: #6b7280; }
        .sheet {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: flex-start;
        }
        /* Preview ukuran mirip stiker laptop (~3.5 x 4 cm) */
        .label {
            width: 3.5cm;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 0.25rem;
            padding: 0.25cm;
            text-align: center;
            page-break-inside: avoid;
        }
        .name {
            font-size: 7px;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 0.15cm;
            word-break: break-word;
        }
        .qr {
            width: 2.4cm;
            height: 2.4cm;
            image-rendering: pixelated;
        }
        @media print {
            body { background: #fff; padding: 0.3cm; }
            .toolbar { display: none !important; }
            .sheet { gap: 0.35cm; }
            .label {
                border: 1px solid #111;
                border-radius: 0;
                width: 3.2cm;
                padding: 0.2cm;
            }
            .qr {
                width: 2.2cm;
                height: 2.2cm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-print" onclick="window.print()">Print Label</button>
        <button type="button" class="btn-close" onclick="window.close()">Tutup</button>
        <span class="hint">{{ $assets->count() }} label stiker laptop (~3 cm). Cetak sekaligus, lalu tempel di perangkat.</span>
    </div>

    <div class="sheet">
        @foreach ($assets as $asset)
            @php
                $qr = $asset->qr ?? $qrService->ensureForAsset($asset);
                $png = base64_encode($qrService->png($qr, 240));
            @endphp
            <div class="label">
                <div class="name">{{ $asset->name }}</div>
                <img class="qr" src="data:image/png;base64,{{ $png }}" alt="QR {{ $asset->name }}">
            </div>
        @endforeach
    </div>

    @if (! empty($autoPrint))
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () { window.print(); }, 300);
            });
        </script>
    @endif
</body>
</html>
