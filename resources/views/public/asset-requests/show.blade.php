<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Progress Pengajuan Aset {{ $assetRequest->reference_number }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    @include('public.asset-requests.partials.form-ui')
</head>
<body>
    <div class="form-wrapper">
        <div class="header-card">
            <div class="header-banner"></div>
            <div class="header-body">
                <h1 class="header-title">Progress Pengajuan Aset</h1>
                <p class="header-desc">
                    Pantau status pengajuan aset Anda, proses persetujuan, dan tindak lanjut operasional dari halaman ini.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="form-alert" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;margin-bottom:12px;">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="form-alert" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;margin-bottom:12px;">
                {{ session('error') }}
            </div>
        @endif

        <div class="page-stack">
            <div class="field-card">
                <span class="field-label">Nomor Referensi</span>
                <div class="gf-readonly">{{ $assetRequest->reference_number }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Status Pengajuan</span>
                <div class="gf-readonly">{{ $assetRequest->status?->label() ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Tahap Lifecycle</span>
                <div class="gf-readonly">{{ $assetRequest->lifecycleStageLabel() }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Langkah Berikutnya</span>
                <div class="gf-readonly">{{ $assetRequest->nextStepLabel() }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Pemohon</span>
                <div class="gf-readonly">{{ $assetRequest->user?->name ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Divisi</span>
                <div class="gf-readonly">{{ $assetRequest->division?->name ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Jenis Pengajuan</span>
                <div class="gf-readonly">{{ $assetRequest->type?->label() ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Tanggal Pengajuan</span>
                <div class="gf-readonly">{{ $assetRequest->created_at?->translatedFormat('d M Y H:i') ?? '-' }}</div>
            </div>

            <div class="field-card">
                @include('public.asset-requests.partials.items-table', ['assetRequest' => $assetRequest])
            </div>

            <div class="field-card">
                <span class="field-label">Keterangan / Keperluan</span>
                <div class="gf-readonly">{{ $assetRequest->description ?: '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Approval</span>
                @if ($assetRequest->approvals->isEmpty())
                    <div class="gf-readonly">Pengajuan ini disetujui otomatis karena divisi tidak memiliki approver.</div>
                @else
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Approver</th>
                                    <th>Status</th>
                                    <th>Keputusan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($assetRequest->approvals as $approval)
                                    @php
                                        $approvalStatus = $approval->status instanceof \App\Enums\RequestStatus
                                            ? $approval->status->label()
                                            : ucfirst((string) $approval->status);
                                        $approvalClass = match ($approval->status instanceof \App\Enums\RequestStatus ? $approval->status->value : (string) $approval->status) {
                                            'approved' => 'approved',
                                            'rejected' => 'rejected',
                                            default => 'pending',
                                        };
                                    @endphp
                                    <tr>
                                        <td>{{ $approval->user?->name ?? '-' }}</td>
                                        <td><span class="badge {{ $approvalClass }}">{{ $approvalStatus }}</span></td>
                                        <td>
                                            @if ($approval->decided_at)
                                                {{ $approval->decided_at->translatedFormat('d M Y H:i') }}
                                                @if ($approval->notes)
                                                    — {{ $approval->notes }}
                                                @endif
                                            @else
                                                Menunggu keputusan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="field-card">
                <span class="field-label">Status Tindak Lanjut</span>
                <div class="gf-readonly">{{ $assetRequest->isFulfilled() ? 'Selesai' : 'Belum selesai' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Ditindaklanjuti Oleh</span>
                <div class="gf-readonly">{{ $assetRequest->fulfilledBy?->name ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Tanggal Tindak Lanjut</span>
                <div class="gf-readonly">{{ $assetRequest->fulfilled_at?->translatedFormat('d M Y H:i') ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Catatan Status</span>
                <div class="gf-readonly">{{ $assetRequest->notes ?: '-' }}</div>
            </div>
        </div>

        <div class="gf-footer">
            <span>Perkembangan pengajuan akan diberitahukan melalui WhatsApp atau email.</span>
        </div>
    </div>
</body>
</html>
