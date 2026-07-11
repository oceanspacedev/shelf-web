<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Approval Pengajuan Aset {{ $assetRequest->reference_number }}</title>
    @include('public.asset-requests.partials.head-assets')
</head>
<body>
    <div class="form-wrapper">
        <div class="header-card">
            <div class="header-banner"></div>
            <div class="header-body">
                <h1 class="header-title">Approval Pengajuan Aset</h1>
                <p class="header-desc">
                    Tinjau ringkasan pengajuan aset berikut, lalu tentukan keputusan persetujuan atau penolakan.
                </p>
            </div>
        </div>

        <div class="page-stack">
            <div class="field-card">
                <span class="field-label">Nomor Referensi</span>
                <div class="gf-readonly">{{ $assetRequest->reference_number }}</div>
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
                <span class="field-label">Lokasi</span>
                <div class="gf-readonly">{{ $assetRequest->assetLocation?->name ?? '-' }}</div>
            </div>

            <div class="field-card">
                <span class="field-label">Approver Saat Ini</span>
                <div class="gf-readonly">{{ $approval->user?->name ?? '-' }}</div>
            </div>

            <div class="field-card">
                @include('public.asset-requests.partials.items-table', ['assetRequest' => $assetRequest])
            </div>

            @if (! $canDecide)
                <div class="field-card">
                    <span class="field-label">Keputusan</span>
                    <div class="form-alert warning">
                        Link approval ini sudah tidak aktif atau bukan giliran Anda untuk memutuskan.
                    </div>
                </div>
            @else
                <div class="field-card">
                    <span class="field-label">Catatan Keputusan</span>
                    <p class="field-desc">Opsional saat menyetujui. Wajib diisi jika menolak pengajuan aset ini.</p>
                    <form id="decision-form" method="POST" action="{{ route('public.asset-requests.approval.approve', $approval->public_token) }}">
                        @csrf
                        <textarea id="decision-notes" name="notes" class="gf-input" placeholder="Tambahkan catatan keputusan" rows="3"></textarea>
                        <div class="error-msg hidden" id="decision-client-error">Alasan penolakan wajib diisi.</div>
                        @error('notes')
                            <div class="error-msg visible">{{ $message }}</div>
                        @enderror
                        <div class="form-actions">
                            <button class="submit-btn danger" type="button" onclick="requestReject()">Tolak</button>
                            <button class="submit-btn success" type="button" onclick="requestApprove()">Setujui</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="gf-footer">
            <span>Keputusan approval akan otomatis memperbarui status pengajuan aset.</span>
        </div>
    </div>

    <div class="modal-overlay" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="modal-card">
            <h2 class="modal-title" id="confirm-title">Konfirmasi</h2>
            <p class="modal-body" id="confirm-message"></p>
            <div class="modal-actions">
                <button type="button" class="modal-cancel-btn" onclick="closeConfirm()">Batal</button>
                <button type="button" class="submit-btn" id="confirm-yes" onclick="submitConfirm()">Ya</button>
            </div>
        </div>
    </div>

    <script>
        const referenceNumber = @json($assetRequest->reference_number);
        const approveAction = @json(route('public.asset-requests.approval.approve', $approval->public_token));
        const rejectAction = @json(route('public.asset-requests.approval.reject', $approval->public_token));
        let pendingAction = null;

        function openConfirm({ title, message, yesLabel, yesClass, action }) {
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-message').textContent = message;

            const yesBtn = document.getElementById('confirm-yes');
            yesBtn.textContent = yesLabel;
            yesBtn.className = 'submit-btn ' + yesClass;

            pendingAction = action;
            document.getElementById('confirm-modal').classList.add('open');
        }

        function closeConfirm() {
            pendingAction = null;
            document.getElementById('confirm-modal').classList.remove('open');
        }

        function submitConfirm() {
            if (pendingAction) {
                // Nonaktifkan tombol keputusan & konfirmasi saat submit untuk mencegah
                // double-submit (server tetap aman via lockForUpdate, ini mencegah
                // POST kedua yang menimbulkan 403 sesaat).
                document.querySelectorAll('#decision-form .submit-btn').forEach((btn) => {
                    btn.disabled = true;
                });
                document.getElementById('confirm-yes').disabled = true;

                const form = document.getElementById('decision-form');
                form.action = pendingAction;
                form.submit();
            }

            closeConfirm();
        }

        function requestApprove() {
            openConfirm({
                title: 'Konfirmasi Persetujuan',
                message: 'Apakah Anda yakin ingin menyetujui pengajuan ' + referenceNumber + '?',
                yesLabel: 'Ya, Setujui',
                yesClass: 'success',
                action: approveAction,
            });
        }

        function requestReject() {
            const notesField = document.getElementById('decision-notes');
            const clientError = document.getElementById('decision-client-error');

            if (! notesField.value.trim()) {
                clientError.classList.remove('hidden');
                notesField.focus();
                return;
            }

            clientError.classList.add('hidden');

            openConfirm({
                title: 'Konfirmasi Penolakan',
                message: 'Apakah Anda yakin ingin menolak pengajuan ' + referenceNumber + '? Tindakan ini tidak dapat dibatalkan.',
                yesLabel: 'Ya, Tolak',
                yesClass: 'danger',
                action: rejectAction,
            });
        }

        document.getElementById('confirm-modal').addEventListener('click', function (event) {
            if (event.target === this) {
                closeConfirm();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeConfirm();
            }
        });
    </script>
</body>
</html>
