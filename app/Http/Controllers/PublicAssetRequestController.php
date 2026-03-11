<?php

namespace App\Http\Controllers;

use App\Enums\ApprovalStatus;
use App\Enums\RequestStatus;
use App\Mail\ApprovalRequested;
use App\Mail\PublicAssetRequestStatusChanged;
use App\Mail\PublicAssetRequestSubmitted;
use App\Models\ApprovalLevel;
use App\Models\Asset;
use App\Models\PublicAssetRequest;
use App\Models\RequestApproval;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PublicAssetRequestController extends Controller
{
    private const REQUEST_TYPES = [
        'pengadaan-aset' => [
            'value' => 'pengadaan_aset',
            'label' => 'Pengadaan Aset',
        ],
        'perbaikan-aset' => [
            'value' => 'perbaikan_aset',
            'label' => 'Perbaikan Aset',
        ],
        'penarikan-aset' => [
            'value' => 'penarikan_aset',
            'label' => 'Penarikan Aset',
        ],
    ];

    private function getRequestTypeLabel(string $value): string
    {
        foreach (self::REQUEST_TYPES as $type) {
            if ($type['value'] === $value) {
                return $type['label'];
            }
        }

        return 'Request Aset';
    }

    public function index(): View
    {
        return view('public-asset-requests.index', [
            'types' => self::REQUEST_TYPES,
        ]);
    }

    public function create(string $type): View
    {
        $requestType = self::REQUEST_TYPES[$type] ?? null;

        abort_if($requestType === null, 404);

        return view('public-asset-requests.form', [
            'slug' => $type,
            'requestType' => $requestType,
            'divisions' => $this->getDivisionOptions($requestType['value']),
        ]);
    }

    public function legacyIndexRedirect(): RedirectResponse
    {
        return redirect()->route('asset-requests.index', [], 301);
    }

    public function legacySuccessRedirect(string $uuid): RedirectResponse
    {
        return redirect()->route('asset-requests.success', ['uuid' => $uuid], 301);
    }

    public function legacyApprovalRedirect(string $token): RedirectResponse
    {
        return redirect()->route('asset-requests.show-approval', ['token' => $token], 301);
    }

    public function legacyCreateRedirect(string $type): RedirectResponse
    {
        return redirect()->route('asset-requests.create', ['type' => $type], 301);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $requestType = self::REQUEST_TYPES[$type] ?? null;

        abort_if($requestType === null, 404);

        $validated = $request->validate([
            'requester_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'division' => ['required', 'string', 'max:255'],
            'placement' => ['required', 'string', 'max:255'],
            'item_name' => ['required', 'string', 'max:255'],
            'qty' => ['required', 'integer', 'min:1'],
            'attachment' => ['nullable', 'file', 'max:1024', 'mimes:pdf,jpg,jpeg,png,gif,webp,bmp'],
        ]);

        $validated['requester_name'] = trim($validated['requester_name']);
        $validated['email'] = trim($validated['email']);
        $validated['division'] = $this->validateAndNormalizeDivision(
            $requestType['value'],
            $validated['division'],
        );
        $validated['placement'] = trim($validated['placement']);
        $validated['item_name'] = trim($validated['item_name']);

        $approvalTrack = $this->resolveApprovalTrack(
            $requestType['value'],
            $validated['division'],
        );

        $matchedUsers = User::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['requester_name'])])
            ->get(['id']);

        $userId = $matchedUsers->count() === 1 ? $matchedUsers->first()->id : null;
        $assetId = null;

        if (
            $userId !== null
            && in_array($requestType['value'], ['perbaikan_aset', 'penarikan_aset'], true)
        ) {
            $matchedAssets = Asset::query()
                ->where('recipient_id', $userId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($validated['item_name'])])
                ->get(['id']);

            $assetId = $matchedAssets->count() === 1 ? $matchedAssets->first()->id : null;
        }

        $temporaryAttachmentPath = null;
        $finalAttachmentPath = null;
        $attachmentOriginalName = null;
        $attachmentFile = $request->file('attachment');

        if ($attachmentFile) {
            $temporaryAttachmentPath = $attachmentFile->store('public-asset-requests/tmp', 'public');
            $attachmentOriginalName = mb_substr(
                $attachmentFile->getClientOriginalName(),
                0,
                255,
            );
        }

        try {
            $result = DB::transaction(function () use (
                $requestType,
                $validated,
                $userId,
                $assetId,
                $approvalTrack,
                $attachmentFile,
                $temporaryAttachmentPath,
                $attachmentOriginalName,
                &$finalAttachmentPath,
            ) {
                $assetRequest = PublicAssetRequest::create([
                    'uuid' => Str::uuid()->toString(),
                    'request_type' => $requestType['value'],
                    'requester_name' => $validated['requester_name'],
                    'email' => $validated['email'],
                    'division' => $validated['division'],
                    'approval_track' => $approvalTrack,
                    'placement' => $validated['placement'],
                    'item_name' => $validated['item_name'],
                    'qty' => $validated['qty'],
                    'user_id' => $userId,
                    'asset_id' => $assetId,
                ]);

                if ($attachmentFile && $temporaryAttachmentPath) {
                    $finalAttachmentPath = $this->buildAttachmentPath($assetRequest, $attachmentFile);

                    if (! Storage::disk('public')->move($temporaryAttachmentPath, $finalAttachmentPath)) {
                        throw new \RuntimeException('Lampiran gagal dipindahkan ke lokasi final.');
                    }

                    $assetRequest->update([
                        'attachment_path' => $finalAttachmentPath,
                        'attachment_original_name' => $attachmentOriginalName,
                    ]);
                }

                $initialApproval = $this->initiateApprovalFlow($assetRequest);

                if ($initialApproval) {
                    $this->sendApprovalRequestNotification($assetRequest, $initialApproval);
                }

                return [
                    'assetRequest' => $assetRequest,
                    'initialApproval' => $initialApproval,
                ];
            });
        } catch (Throwable $exception) {
            if ($temporaryAttachmentPath) {
                Storage::disk('public')->delete($temporaryAttachmentPath);
            }

            if ($finalAttachmentPath) {
                Storage::disk('public')->delete($finalAttachmentPath);
            }

            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'request' => 'Request tidak dapat diproses saat ini. Silakan coba lagi beberapa saat lagi.',
                ]);
        }

        /** @var PublicAssetRequest $assetRequest */
        $assetRequest = $result['assetRequest'];

        /** @var RequestApproval|null $initialApproval */
        $initialApproval = $result['initialApproval'];

        $this->sendInitialNotifications($assetRequest, $initialApproval);

        return redirect()->route('asset-requests.success', $assetRequest->uuid);
    }

    public function success(string $uuid): View
    {
        $assetRequest = PublicAssetRequest::where('uuid', $uuid)
            ->with('approvals')
            ->firstOrFail();

        return view('public-asset-requests.success', [
            'assetRequest' => $assetRequest,
            'requestTypeLabel' => $this->getRequestTypeLabel($assetRequest->request_type),
        ]);
    }

    /**
     * Show the public approval page (no login required).
     */
    public function showApproval(string $token): View
    {
        $approval = RequestApproval::where('token', $token)
            ->with(['publicAssetRequest' => fn ($query) => $query->withTrashed()->with('approvals')])
            ->firstOrFail();

        $assetRequest = $approval->publicAssetRequest;

        abort_if($assetRequest === null, 404);

        $currentApproval = $assetRequest->approvals
            ->first(fn (RequestApproval $step): bool => $step->status === ApprovalStatus::Pending);

        $requestClosed = $assetRequest->trashed() || $assetRequest->status !== RequestStatus::Pending;
        $hasResponded = $approval->status !== ApprovalStatus::Pending;
        $isCurrentApproval = $currentApproval?->is($approval) ?? false;

        return view('public-asset-requests.approval', [
            'approval' => $approval,
            'assetRequest' => $assetRequest,
            'requestTypeLabel' => $this->getRequestTypeLabel($assetRequest->request_type),
            'hasResponded' => $hasResponded,
            'isCurrentApproval' => $isCurrentApproval,
            'requestClosed' => $requestClosed,
            'canRespond' => (! $hasResponded) && (! $requestClosed) && $isCurrentApproval,
        ]);
    }

    /**
     * Process the approval action (approve/reject) from the public page.
     */
    public function processApproval(Request $request, string $token): RedirectResponse
    {
        $approval = RequestApproval::where('token', $token)
            ->with([
                'publicAssetRequest' => fn ($query) => $query->withTrashed(),
                'approvalLevel',
            ])
            ->firstOrFail();

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = DB::transaction(function () use ($approval, $validated) {
                $lockedApproval = RequestApproval::query()
                    ->with('approvalLevel')
                    ->lockForUpdate()
                    ->findOrFail($approval->getKey());

                $assetRequest = PublicAssetRequest::query()
                    ->withTrashed()
                    ->lockForUpdate()
                    ->findOrFail($lockedApproval->public_asset_request_id);

                if ($assetRequest->trashed()) {
                    return [
                        'flash' => 'info',
                        'message' => 'Pengajuan ini sudah diarsipkan. Link approval tidak lagi aktif.',
                    ];
                }

                if ($assetRequest->status !== RequestStatus::Pending) {
                    return [
                        'flash' => 'info',
                        'message' => 'Pengajuan ini sudah selesai diproses. Link approval tidak lagi aktif.',
                    ];
                }

                if ($lockedApproval->status !== ApprovalStatus::Pending) {
                    return [
                        'flash' => 'info',
                        'message' => 'Anda sudah merespons pengajuan ini sebelumnya.',
                    ];
                }

                $currentApprovalId = RequestApproval::query()
                    ->where('public_asset_request_id', $assetRequest->id)
                    ->where('status', ApprovalStatus::Pending)
                    ->orderBy('level')
                    ->value('id');

                if ($currentApprovalId !== $lockedApproval->id) {
                    return [
                        'flash' => 'info',
                        'message' => 'Pengajuan ini masih menunggu approval level sebelumnya.',
                    ];
                }

                $isApproved = $validated['action'] === 'approve';

                $lockedApproval->update([
                    'status' => $isApproved ? ApprovalStatus::Approved : ApprovalStatus::Rejected,
                    'notes' => $validated['notes'],
                    'responded_at' => now(),
                ]);

                $nextApproval = null;
                $shouldNotifyRequester = false;

                if ($isApproved) {
                    $nextApproval = RequestApproval::query()
                        ->where('public_asset_request_id', $assetRequest->id)
                        ->where('status', ApprovalStatus::Pending)
                        ->orderBy('level')
                        ->first();

                    if ($nextApproval) {
                        $this->sendApprovalRequestNotification($assetRequest, $nextApproval);
                    } else {
                        $assetRequest->update(['status' => RequestStatus::Approved]);
                        $shouldNotifyRequester = true;
                    }
                } else {
                    $assetRequest->update([
                        'status' => RequestStatus::Rejected,
                        'admin_notes' => "Ditolak oleh {$lockedApproval->approver_name} (Level {$lockedApproval->level}): ".($validated['notes'] ?? '-'),
                    ]);

                    $shouldNotifyRequester = true;
                }

                return [
                    'flash' => null,
                    'message' => null,
                    'assetRequest' => $assetRequest,
                    'shouldNotifyRequester' => $shouldNotifyRequester,
                ];
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
            ->route('asset-requests.show-approval', $token)
                ->with('error', 'Pengajuan tidak dapat diproses saat ini. Silakan coba lagi beberapa saat lagi.');
        }

        if ($result['shouldNotifyRequester'] ?? false) {
            $this->notifyRequesterStatusChanged($result['assetRequest']);
        }

        $redirect = redirect()->route('asset-requests.show-approval', $token);

        if (filled($result['flash']) && filled($result['message'])) {
            $redirect->with($result['flash'], $result['message']);
        }

        return $redirect;
    }

    /**
     * Initiate the approval flow by sending email to Level 1 approver.
     */
    private function initiateApprovalFlow(PublicAssetRequest $assetRequest): ?RequestApproval
    {
        $approvalTrack = $assetRequest->approval_track
            ?? $this->resolveApprovalTrack($assetRequest->request_type, $assetRequest->division);

        if (! $approvalTrack) {
            $this->autoApproveRequest($assetRequest);

            return null;
        }

        if ($assetRequest->approval_track !== $approvalTrack) {
            $assetRequest->forceFill(['approval_track' => $approvalTrack])->save();
        }

        $approvalLevels = ApprovalLevel::query()
            ->forTrack($assetRequest->request_type, $approvalTrack)
            ->orderBy('level')
            ->get();

        if ($approvalLevels->isEmpty()) {
            $this->autoApproveRequest($assetRequest);

            return null;
        }

        $approvals = $approvalLevels->map(
            fn (ApprovalLevel $level): RequestApproval => $this->createApproval($assetRequest, $level),
        );

        return $approvals->first();
    }

    private function getDivisionOptions(string $requestType): Collection
    {
        return ApprovalLevel::query()
            ->where('request_type', $requestType)
            ->where('division', '!=', ApprovalLevel::ALL_DIVISIONS)
            ->pluck('division')
            ->map(fn ($division) => ApprovalLevel::normalizeDivision($division))
            ->filter()
            ->unique(fn (string $division) => ApprovalLevel::normalizeDivisionKey($division))
            ->sortBy(fn (string $division) => ApprovalLevel::normalizeDivisionKey($division))
            ->values();
    }

    private function validateAndNormalizeDivision(string $requestType, string $division): string
    {
        $normalizedDivision = ApprovalLevel::normalizeDivision($division);
        $configuredDivisions = $this->getDivisionOptions($requestType);

        if ($configuredDivisions->isEmpty()) {
            return $normalizedDivision;
        }

        $matchedDivision = $configuredDivisions->first(
            fn (string $configuredDivision): bool => ApprovalLevel::normalizeDivisionKey($configuredDivision)
                === ApprovalLevel::normalizeDivisionKey($normalizedDivision),
        );

        if ($matchedDivision !== null) {
            return $matchedDivision;
        }

        if ($this->hasGlobalApprovalTrack($requestType)) {
            return $normalizedDivision;
        }

        throw ValidationException::withMessages([
            'division' => 'Divisi yang dipilih tidak tersedia untuk jenis pengajuan ini.',
        ]);
    }

    private function resolveApprovalTrack(string $requestType, string $division): ?string
    {
        $normalizedDivision = ApprovalLevel::normalizeDivision($division);

        if (ApprovalLevel::query()->forTrack($requestType, $normalizedDivision)->exists()) {
            return $normalizedDivision;
        }

        if ($this->hasGlobalApprovalTrack($requestType)) {
            return ApprovalLevel::ALL_DIVISIONS;
        }

        return null;
    }

    private function hasGlobalApprovalTrack(string $requestType): bool
    {
        return ApprovalLevel::query()
            ->forTrack($requestType, ApprovalLevel::ALL_DIVISIONS)
            ->exists();
    }

    private function createApproval(PublicAssetRequest $assetRequest, ApprovalLevel $approvalLevel): RequestApproval
    {
        return RequestApproval::create([
            'public_asset_request_id' => $assetRequest->id,
            'approval_level_id' => $approvalLevel->id,
            'token' => Str::uuid()->toString(),
            'level' => $approvalLevel->level,
            'approver_name' => $approvalLevel->approver_name,
            'approver_email' => $approvalLevel->approver_email,
        ]);
    }

    private function autoApproveRequest(PublicAssetRequest $assetRequest): void
    {
        $assetRequest->update([
            'status' => RequestStatus::Approved,
            'admin_notes' => 'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
        ]);
    }

    private function notifyRequesterStatusChanged(PublicAssetRequest $assetRequest): void
    {
        if (! $assetRequest->email) {
            return;
        }

        $this->dispatchMailSafely(
            $assetRequest->email,
            new PublicAssetRequestStatusChanged($assetRequest),
        );
    }

    private function sendInitialNotifications(
        PublicAssetRequest $assetRequest,
        ?RequestApproval $initialApproval,
    ): void {
        $this->dispatchMailSafely(
            $assetRequest->email,
            new PublicAssetRequestSubmitted($assetRequest),
        );

        if ($initialApproval) {
            return;
        }

        if ($assetRequest->status === RequestStatus::Approved) {
            $this->notifyRequesterStatusChanged($assetRequest);
        }
    }

    private function sendApprovalRequestNotification(
        PublicAssetRequest $assetRequest,
        RequestApproval $approval,
    ): void {
        $this->dispatchMailOrFail(
            $approval->approver_email,
            new ApprovalRequested($assetRequest, $approval),
        );
    }

    private function dispatchMailOrFail(string $recipient, Mailable $mailable): void
    {
        if (config('queue.default') !== 'sync') {
            Mail::to($recipient)->queue($mailable);

            return;
        }

        Mail::to($recipient)->send($mailable);
    }

    private function dispatchMailSafely(string $recipient, Mailable $mailable): void
    {
        if (app()->runningUnitTests()) {
            $this->sendMailSafely(fn () => Mail::to($recipient)->send($mailable));

            return;
        }

        if (config('queue.default') !== 'sync') {
            $this->sendMailSafely(fn () => Mail::to($recipient)->queue($mailable));

            return;
        }

        app()->terminating(function () use ($recipient, $mailable): void {
            $this->sendMailSafely(fn () => Mail::to($recipient)->send($mailable));
        });
    }

    private function sendMailSafely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function buildAttachmentPath(
        PublicAssetRequest $assetRequest,
        UploadedFile $attachment,
    ): string {
        $extension = strtolower($attachment->guessExtension() ?: $attachment->getClientOriginalExtension() ?: 'file');

        return "public-asset-requests/attachments/{$assetRequest->id}.{$extension}";
    }
}
