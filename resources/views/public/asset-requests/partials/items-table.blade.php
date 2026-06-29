@php
    $items = $items ?? (
        $assetRequest->items->isNotEmpty()
            ? $assetRequest->items
            : collect([(object) [
                'asset' => $assetRequest->asset,
                'item_name' => $assetRequest->item_name,
                'qty' => $assetRequest->qty ?? 1,
            ]])
    );
@endphp

<span class="field-label">Item Pengajuan</span>
<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama Item / Aset</th>
                <th>Serial Number</th>
                <th class="col-num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item->asset?->name ?? $item->item_name ?? '-' }}</td>
                    <td>{{ $item->asset?->serial_number ?? '-' }}</td>
                    <td class="col-num">{{ $item->qty ?? 1 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
