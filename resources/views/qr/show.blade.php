@php
    $badgeColors = [
        'success' => '#16a34a',
        'warning' => '#ca8a04',
        'danger' => '#dc2626',
        'gray' => '#6b7280',
        'secondary' => '#6b7280',
    ];
    $badgeColor = $badgeColors[$asset->condition_status_color] ?? '#6b7280';
    $visibleAttributes = $asset->attributes->filter(
        fn ($attribute) => ! $attribute->isDocumentExpiryAttribute() && ! $attribute->documentUrl()
    );
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $asset->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 1rem;
            color: #111827;
            background: #f9fafb;
            line-height: 1.5;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .header h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
        }
        .highlight-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        .highlight-value {
            font-size: 1.375rem;
            font-weight: 700;
            color: #111827;
        }
        .highlight-sub {
            font-size: 0.875rem;
            color: #4b5563;
            margin-top: 0.125rem;
        }
        .highlight-grid {
            display: grid;
            gap: 1rem;
        }
        .last-seen {
            margin-top: 1.25rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        .last-seen-coords {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #111827;
            word-break: break-all;
        }
        .last-seen-time {
            font-size: 0.8125rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        .last-seen-link {
            display: inline-block;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #2563eb;
            text-decoration: none;
        }
        .last-seen-link:hover { text-decoration: underline; }
        .last-seen-empty {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .detail-grid {
            display: grid;
            gap: 0.75rem;
        }
        .detail-row {
            display: grid;
            grid-template-columns: 7rem 1fr;
            gap: 0.5rem;
            font-size: 0.875rem;
        }
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-value {
            color: #111827;
            word-break: break-word;
        }
        .asset-image {
            width: 100%;
            max-width: 240px;
            border-radius: 8px;
            display: block;
            margin: 0 auto;
        }
        .section-title {
            font-size: 0.875rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
            color: #374151;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1>{{ $asset->name }}</h1>
            <span class="badge" style="background: {{ $badgeColor }};">{{ $asset->condition_status_label }}</span>
        </div>

        <div class="highlight-grid">
            <div>
                <div class="highlight-label">Lokasi</div>
                <div class="highlight-value">{{ $asset->assetLocation?->name ?? '-' }}</div>
                @if ($asset->businessEntity?->name)
                    <div class="highlight-sub">{{ $asset->businessEntity->name }}</div>
                @endif
            </div>
            <div>
                <div class="highlight-label">Kuantitas</div>
                <div class="highlight-value">{{ $asset->qty }}</div>
            </div>
        </div>

        <div class="last-seen" id="last-seen-card">
            <div class="highlight-label">Terakhir dilihat (GPS)</div>
            @if ($lastSeenScan)
                <div class="last-seen-coords" id="last-seen-coords">
                    {{ number_format((float) $lastSeenScan->latitude, 6, '.', '') }},
                    {{ number_format((float) $lastSeenScan->longitude, 6, '.', '') }}
                </div>
                <div class="last-seen-time" id="last-seen-time">
                    {{ $lastSeenScan->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                </div>
                <a
                    class="last-seen-link"
                    id="last-seen-maps"
                    href="https://www.google.com/maps?q={{ $lastSeenScan->latitude }},{{ $lastSeenScan->longitude }}"
                    target="_blank"
                    rel="noopener"
                >Buka di Google Maps</a>
            @else
                <div class="last-seen-empty" id="last-seen-empty">Belum ada data GPS</div>
                <div class="last-seen-coords" id="last-seen-coords" hidden></div>
                <div class="last-seen-time" id="last-seen-time" hidden></div>
                <a class="last-seen-link" id="last-seen-maps" href="#" target="_blank" rel="noopener" hidden>Buka di Google Maps</a>
            @endif
            <div class="last-seen-empty" id="last-seen-pending" hidden>Mengambil lokasi…</div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Detail Aset</h2>
        <div class="detail-grid">
            @if ($asset->category?->name)
                <div class="detail-row">
                    <div class="detail-label">Kategori</div>
                    <div class="detail-value">{{ $asset->category->name }}</div>
                </div>
            @endif
            @if ($asset->brand?->name)
                <div class="detail-row">
                    <div class="detail-label">Merek</div>
                    <div class="detail-value">{{ $asset->brand->name }}</div>
                </div>
            @endif
            @if ($asset->type)
                <div class="detail-row">
                    <div class="detail-label">Tipe</div>
                    <div class="detail-value">{{ $asset->type }}</div>
                </div>
            @endif
            @if ($asset->serial_number)
                <div class="detail-row">
                    <div class="detail-label">Serial</div>
                    <div class="detail-value">{{ $asset->serial_number }}</div>
                </div>
            @endif
            @if ($asset->imei1)
                <div class="detail-row">
                    <div class="detail-label">IMEI 1</div>
                    <div class="detail-value">{{ $asset->imei1 }}</div>
                </div>
            @endif
            @if ($asset->imei2)
                <div class="detail-row">
                    <div class="detail-label">IMEI 2</div>
                    <div class="detail-value">{{ $asset->imei2 }}</div>
                </div>
            @endif
            @if ($asset->recipient?->name)
                <div class="detail-row">
                    <div class="detail-label">Penerima</div>
                    <div class="detail-value">{{ $asset->recipient->name }}</div>
                </div>
            @endif
            @if ($asset->nbh_status && $asset->nbh_status->value !== 'none')
                <div class="detail-row">
                    <div class="detail-label">Status NBH</div>
                    <div class="detail-value">{{ $asset->nbh_status_label }}</div>
                </div>
            @endif
        </div>
    </div>

    @if ($asset->image)
        <div class="card">
            <h2 class="section-title">Foto Aset</h2>
            <img class="asset-image" src="{{ \Illuminate\Support\Facades\Storage::url($asset->image) }}" alt="{{ $asset->name }}">
        </div>
    @endif

    @if ($visibleAttributes->isNotEmpty())
        <div class="card">
            <h2 class="section-title">Atribut Khusus</h2>
            <div class="detail-grid">
                @foreach ($visibleAttributes as $attribute)
                    <div class="detail-row">
                        <div class="detail-label">{{ $attribute->customAttribute?->name ?? 'Atribut' }}</div>
                        <div class="detail-value">{{ $attribute->displayValue() }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <script>
    (function () {
      var coordsEl = document.getElementById('last-seen-coords');
      var timeEl = document.getElementById('last-seen-time');
      var mapsEl = document.getElementById('last-seen-maps');
      var emptyEl = document.getElementById('last-seen-empty');
      var pendingEl = document.getElementById('last-seen-pending');

      function showPending(on) {
        if (pendingEl) pendingEl.hidden = !on;
      }

      function applyLastSeen(data) {
        if (!coordsEl || !timeEl || !mapsEl) return;
        var lat = Number(data.latitude).toFixed(6);
        var lng = Number(data.longitude).toFixed(6);
        coordsEl.textContent = lat + ', ' + lng;
        coordsEl.hidden = false;
        timeEl.textContent = data.scanned_at || '';
        timeEl.hidden = false;
        mapsEl.href = data.maps_url;
        mapsEl.hidden = false;
        if (emptyEl) emptyEl.hidden = true;
        showPending(false);
      }

      if (!navigator.geolocation) return;

      showPending(true);
      navigator.geolocation.getCurrentPosition(function (pos) {
        fetch(@json(route('qr.location', $qr)), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            latitude: pos.coords.latitude,
            longitude: pos.coords.longitude
          })
        })
          .then(function (res) { return res.ok ? res.json() : null; })
          .then(function (data) {
            if (data && data.ok) {
              applyLastSeen(data);
            } else {
              showPending(false);
            }
          })
          .catch(function () { showPending(false); });
      }, function () {
        showPending(false);
      }, { enableHighAccuracy: false, timeout: 10000 });
    })();
    </script>
</body>
</html>
