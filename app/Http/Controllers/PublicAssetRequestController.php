<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestType;
use App\Enums\RequestStatus;
use App\Models\Asset;
use App\Models\AssetLocation;
use App\Models\AssetRequest;
use App\Models\AssetRequestApproval;
use App\Models\BusinessEntity;
use App\Models\Division;
use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PublicAssetRequestController extends Controller
{
    /**
     * Display the public asset request form.
     */
    public function index()
    {
        $users = User::with(['jobTitle', 'businessEntity'])
            ->orderBy('name')
            ->get(['id', 'name', 'job_title_id', 'business_entity_id', 'whatsapp_number', 'email']);

        $defaultApplicantId = auth()->check() && $users->contains('id', auth()->id())
            ? (string) auth()->id()
            : ($users->count() === 1 ? (string) $users->first()->id : null);

        $jobTitles = JobTitle::orderBy('title')->get(['id', 'title']);

        if ($jobTitles->isEmpty()) {
            $jobTitles = User::query()
                ->whereNotNull('job_title_id')
                ->with('jobTitle:id,title')
                ->get()
                ->pluck('jobTitle')
                ->filter()
                ->unique('id')
                ->sortBy('title')
                ->values();
        }

        $divisions = Division::orderBy('name')->get(['id', 'name']);
        $businessEntities = BusinessEntity::orderBy('name')->get(['id', 'name']);
        $locations = AssetLocation::orderBy('name')->get(['id', 'name']);

        $visibleUserIds = $users->pluck('id');
        $assetsByRecipient = $visibleUserIds->isEmpty()
            ? []
            : Asset::query()
                ->eligibleForPenarikanOrPerbaikan()
                ->whereIn('recipient_id', $visibleUserIds)
                ->orderBy('name')
                ->get(['id', 'name', 'serial_number', 'recipient_id'])
                ->groupBy(fn (Asset $asset) => (string) ($asset->recipient_id ?? ''))
                ->map(fn ($group) => $group->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'serial_number' => $asset->serial_number,
                    'label' => $this->formatAssetLabel($asset),
                ])->values())
                ->toArray();

        $usersById = $users->mapWithKeys(fn (User $user) => [
            $user->id => [
                'name' => $user->name,
                'job_title_id' => $user->job_title_id,
                'job_title' => $user->jobTitle?->title,
                'business_entity_id' => $user->business_entity_id,
                'business_entity' => $user->businessEntity?->name,
                'has_whatsapp_number' => filled($user->whatsapp_number),
                'has_email' => filled($user->email),
            ],
        ]);

        return view('public.asset-requests.index', compact(
            'users',
            'usersById',
            'jobTitles',
            'businessEntities',
            'divisions',
            'locations',
            'assetsByRecipient',
            'defaultApplicantId',
        ));
    }

    public function show(string $token): View
    {
        $assetRequest = AssetRequest::query()
            ->where('public_token', $token)
            ->with([
                'asset',
                'items.asset',
                'user.jobTitle',
                'user.businessEntity',
                'division',
                'assetLocation',
                'approvals.user.jobTitle',
                'approvals.decidedBy',
                'createdAssets',
                'fulfilledBy',
                'assetTransfer',
            ])
            ->firstOrFail();

        return view('public.asset-requests.show', compact('assetRequest'));
    }

    public function showApproval(string $token): View
    {
        $approval = $this->findPublicApproval($token);
        $assetRequest = $approval->assetRequest;
        $canDecide = $this->approvalCanBeDecided($approval);

        return view('public.asset-requests.approval', compact('approval', 'assetRequest', 'canDecide'));
    }

    public function approveApproval(Request $request, string $token): RedirectResponse
    {
        $approval = $this->findPublicApproval($token);
        $this->abortIfApprovalCannotBeDecided($approval);

        $data = $request->validate([
            'notes' => 'nullable|string|max:65535',
        ]);

        try {
            $approval->assetRequest->approveCurrentLevel($data['notes'] ?? null, $approval->user);
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('public.asset-requests.approval', $token)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->to($approval->assetRequest->fresh()->publicProgressUrl())
            ->with('status', 'Persetujuan berhasil direkam.');
    }

    public function rejectApproval(Request $request, string $token): RedirectResponse
    {
        $approval = $this->findPublicApproval($token);
        $this->abortIfApprovalCannotBeDecided($approval);

        $data = $request->validate([
            'notes' => 'required|string|max:65535',
        ]);

        try {
            $approval->assetRequest->rejectCurrentLevel($data['notes'], $approval->user);
        } catch (AuthorizationException $e) {
            return redirect()
                ->route('public.asset-requests.approval', $token)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->to($approval->assetRequest->fresh()->publicProgressUrl())
            ->with('status', 'Penolakan berhasil direkam.');
    }

    /**
     * Store a public asset request.
     */
    public function store(Request $request)
    {
        $jobTitleIds = JobTitle::pluck('id')->map(fn ($id) => (string) $id)->all();

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:penarikan,perbaikan,pengadaan',
            'user_id' => [
                Rule::requiredIf(fn () => in_array($request->input('type'), ['penarikan', 'perbaikan'], true)),
                'nullable',
                'exists:users,id',
            ],
            'applicant_name' => 'required_without:user_id|nullable|string|max:255',
            'whatsapp_number' => 'nullable|string|max:255',
            'email' => ['nullable', 'email:strict', 'max:255', 'not_regex:/[\r\n]/'],
            'job_title_id' => ['nullable', Rule::in(array_merge(['', 'other'], $jobTitleIds))],
            'custom_job_title' => 'required_if:job_title_id,other|nullable|string|max:255',
            'business_entity_id' => 'required|exists:business_entities,id',
            'division_id' => 'required|exists:divisions,id',
            'asset_location_id' => [
                'required',
                Rule::in(array_merge(['other'], AssetLocation::pluck('id')->map(fn ($id) => (string) $id)->all())),
            ],
            'custom_asset_location' => 'required_if:asset_location_id,other|nullable|string|max:255',
            'asset_id' => 'nullable|exists:assets,id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer|distinct|exists:assets,id',
            'item_name' => 'nullable|string|max:255',
            'qty' => 'nullable|integer|min:1',
            'items' => 'nullable|array',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.qty' => 'nullable|integer|min:1',
            'description' => 'nullable|string|max:65535',
            'attachments' => 'required|array|min:1',
            'attachments.*' => 'required|file|mimes:jpeg,jpg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ], [
            'type.required' => 'Jenis pengajuan wajib dipilih.',
            'type.in' => 'Jenis pengajuan tidak valid.',
            'user_id.required' => 'Nama pemohon wajib dipilih dari daftar untuk jenis pengajuan ini.',
            'user_id.exists' => 'Pemohon tidak terdaftar.',
            'applicant_name.required_without' => 'Nama pemohon wajib diisi.',
            'whatsapp_number.required' => 'No. WhatsApp wajib diisi.',
            'whatsapp_number.max' => 'Nomor WhatsApp terlalu panjang.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'job_title_id.in' => 'Posisi tidak valid.',
            'custom_job_title.required_if' => 'Posisi lainnya wajib diisi.',
            'business_entity_id.required' => 'Badan usaha wajib dipilih.',
            'business_entity_id.exists' => 'Badan usaha tidak terdaftar.',
            'division_id.required' => 'Divisi wajib diisi.',
            'division_id.exists' => 'Divisi tidak terdaftar.',
            'asset_location_id.required' => 'Lokasi wajib diisi.',
            'asset_location_id.in' => 'Lokasi tidak valid.',
            'custom_asset_location.required_if' => 'Lokasi manual wajib diisi.',
            'asset_id.required_if' => 'Aset wajib dipilih melalui kolom Nama Pemohon.',
            'asset_id.exists' => 'Aset tidak ditemukan.',
            'asset_ids.array' => 'Format pilihan aset tidak valid.',
            'asset_ids.*.integer' => 'Format pilihan aset tidak valid.',
            'asset_ids.*.distinct' => 'Aset yang dipilih tidak boleh duplikat.',
            'asset_ids.*.exists' => 'Aset tidak ditemukan.',
            'item_name.required_if' => 'Nama aset baru wajib diisi.',
            'qty.required_if' => 'Jumlah wajib diisi.',
            'qty.integer' => 'Jumlah harus berupa angka.',
            'qty.min' => 'Jumlah minimal adalah 1.',
            'attachments.required' => 'Lampiran / dokumen pendukung wajib diunggah minimal 1.',
            'attachments.min' => 'Lampiran / dokumen pendukung wajib diunggah minimal 1.',
            'attachments.array' => 'Format lampiran tidak valid.',
            'attachments.*.file' => 'File lampiran tidak valid.',
            'attachments.*.mimes' => 'Format file harus berupa: jpeg, jpg, png, pdf, doc, docx, xls, atau xlsx.',
            'attachments.*.max' => 'Ukuran maksimal setiap file adalah 10MB.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $selectedUser = null;

            if ($request->filled('user_id')) {
                $selectedUser = User::find($request->input('user_id'));
            }

            if (! $selectedUser) {
                if (blank($request->input('whatsapp_number'))) {
                    $validator->errors()->add('whatsapp_number', 'No. WhatsApp wajib diisi.');
                }

                if (blank($request->input('email'))) {
                    $validator->errors()->add('email', 'Email wajib diisi.');
                }
            } else {
                if (blank($selectedUser->whatsapp_number) && blank($request->input('whatsapp_number'))) {
                    $validator->errors()->add('whatsapp_number', 'No. WhatsApp pemohon belum ada, wajib dilengkapi.');
                }

                if (blank($selectedUser->email) && blank($request->input('email'))) {
                    $validator->errors()->add('email', 'Email pemohon belum ada, wajib dilengkapi.');
                }
            }

            if ($request->input('type') === AssetRequestType::Pengadaan->value) {
                $items = $this->pengadaanItemsFromRequest($request);

                if ($items === []) {
                    if (blank($request->input('item_name'))) {
                        $validator->errors()->add('item_name', 'Nama aset baru wajib diisi.');
                    }

                    if (blank($request->input('qty'))) {
                        $validator->errors()->add('qty', 'Jumlah wajib diisi.');
                    }
                }

                return;
            }

            if (! in_array($request->input('type'), ['penarikan', 'perbaikan'], true)) {
                return;
            }

            $assetIds = $this->selectedAssetIdsFromRequest($request);
            if ($assetIds === []) {
                $validator->errors()->add(
                    $request->has('asset_ids') ? 'asset_ids' : 'asset_id',
                    'Aset wajib dipilih melalui kolom Nama Pemohon.'
                );

                return;
            }

            // Validasi agar aset yang sudah dalam proses pengajuan aktif (pending / approved) tidak bisa diajukan kembali
            $lockedAssets = Asset::query()
                ->whereIn('id', $assetIds)
                ->where(function (Builder $query) {
                    $query->whereHas('assetRequests', function (Builder $q) {
                        $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                            ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                            ->whereNull('fulfilled_at');
                    })
                        ->orWhereHas('assetRequestItems.assetRequest', function (Builder $q) {
                            $q->whereIn('type', [AssetRequestType::Penarikan->value, AssetRequestType::Perbaikan->value])
                                ->whereIn('status', [RequestStatus::Pending->value, RequestStatus::Approved->value])
                                ->whereNull('fulfilled_at');
                        });
                })
                ->pluck('name')
                ->all();

            if (! empty($lockedAssets)) {
                $names = implode(', ', $lockedAssets);
                $validator->errors()->add(
                    $request->has('asset_ids') ? 'asset_ids' : 'asset_id',
                    "Aset berikut sedang dalam proses pengajuan aktif: {$names}. Silakan tunggu hingga pengajuan sebelumnya selesai ditindaklanjuti."
                );
            }

            $ineligibleConditionAssets = Asset::query()
                ->whereIn('id', $assetIds)
                ->whereNotIn('condition_status', AssetCondition::transferableValues())
                ->get(['name', 'condition_status']);

            if ($ineligibleConditionAssets->isNotEmpty()) {
                $names = $ineligibleConditionAssets
                    ->map(function (Asset $asset): string {
                        $condition = $asset->condition_status instanceof AssetCondition
                            ? $asset->condition_status->label()
                            : (string) $asset->condition_status;

                        return "{$asset->name} ({$condition})";
                    })
                    ->implode(', ');

                $validator->errors()->add(
                    $request->has('asset_ids') ? 'asset_ids' : 'asset_id',
                    "Aset berikut tidak dapat diajukan karena kondisinya tidak memenuhi: {$names}."
                );
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $candidateUserId = $this->candidateUserId($data);
            $assetsToRequest = collect();
            $itemRows = [];

            if (in_array($data['type'], ['penarikan', 'perbaikan'], true)) {
                if ($candidateUserId === null) {
                    return response()->json([
                        'success' => false,
                        'errors' => [
                            'user_id' => ['Nama pemohon wajib dipilih dari daftar untuk jenis pengajuan ini.'],
                        ],
                    ], 422);
                }

                $assetIds = $this->selectedAssetIds($data);
                $assets = Asset::query()
                    ->whereIn('id', $assetIds)
                    ->get()
                    ->keyBy('id');
                $assetsToRequest = collect($assetIds)
                    ->map(fn (int $assetId) => $assets->get($assetId))
                    ->filter()
                    ->values();

                $unownedAssets = $assetsToRequest
                    ->filter(fn (Asset $asset) => (int) $asset->recipient_id !== (int) $candidateUserId);

                if ($unownedAssets->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'errors' => [
                            count($assetIds) > 1 ? 'asset_ids' : 'asset_id' => ['Aset yang dipilih tidak dimiliki oleh pemohon.'],
                        ],
                    ], 422);
                }

                $itemRows = $assetsToRequest
                    ->map(fn (Asset $asset): array => [
                        'asset_id' => $asset->id,
                        'item_name' => $asset->name,
                        'qty' => 1,
                    ])
                    ->all();
            } else {
                $itemRows = $this->pengadaanItemsFromRequest($request);
            }

            $attachments = [];
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file->isValid()) {
                        $attachments[] = $file->store('asset-requests', 'public');
                    }
                }
            }

            $assetRequest = DB::transaction(function () use ($data, $attachments, $itemRows) {
                if (in_array($data['type'], ['penarikan', 'perbaikan'], true)) {
                    $assetIds = collect($itemRows)
                        ->pluck('asset_id')
                        ->filter()
                        ->map(fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $lockedAssets = Asset::query()
                        ->whereIn('id', $assetIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    $stillLocked = $lockedAssets
                        ->filter(fn (Asset $asset) => $asset->hasOpenAssetRequestLock())
                        ->pluck('name')
                        ->all();

                    if ($stillLocked !== []) {
                        $field = count($assetIds) > 1 ? 'asset_ids' : 'asset_id';
                        throw ValidationException::withMessages([
                            $field => [
                                'Aset berikut sedang dalam proses pengajuan aktif: '.implode(', ', $stillLocked).'. Silakan tunggu hingga pengajuan sebelumnya selesai ditindaklanjuti.',
                            ],
                        ]);
                    }

                    $ineligible = $lockedAssets
                        ->filter(function (Asset $asset): bool {
                            $condition = $asset->condition_status instanceof AssetCondition
                                ? $asset->condition_status->value
                                : (string) $asset->condition_status;

                            return ! in_array($condition, AssetCondition::transferableValues(), true);
                        });

                    if ($ineligible->isNotEmpty()) {
                        $field = count($assetIds) > 1 ? 'asset_ids' : 'asset_id';
                        $names = $ineligible
                            ->map(function (Asset $asset): string {
                                $condition = $asset->condition_status instanceof AssetCondition
                                    ? $asset->condition_status->label()
                                    : (string) $asset->condition_status;

                                return "{$asset->name} ({$condition})";
                            })
                            ->implode(', ');

                        throw ValidationException::withMessages([
                            $field => [
                                "Aset berikut tidak dapat diajukan karena kondisinya tidak memenuhi: {$names}.",
                            ],
                        ]);
                    }
                }

                $userId = $this->resolveUserId($data);
                $assetLocationId = $this->resolveAssetLocationId($data);
                $firstItem = $itemRows[0] ?? [
                    'asset_id' => null,
                    'item_name' => $data['item_name'] ?? null,
                    'qty' => $data['qty'] ?? 1,
                ];

                $assetRequest = AssetRequest::create([
                    'type' => $data['type'],
                    'user_id' => $userId,
                    'division_id' => $data['division_id'],
                    'asset_location_id' => $assetLocationId,
                    'asset_id' => $firstItem['asset_id'] ?? null,
                    'item_name' => $firstItem['item_name'] ?? null,
                    'qty' => $firstItem['qty'] ?? 1,
                    'description' => $data['description'] ?? null,
                    'attachment' => $attachments,
                    'status' => 'pending',
                    'current_level' => 1,
                ]);

                foreach (array_slice($itemRows, 1) as $itemRow) {
                    $assetRequest->items()->create($itemRow);
                }

                return $assetRequest->fresh(['items.asset']);
            });

            $itemCount = $assetRequest->items()->count();

            return response()->json([
                'success' => true,
                'message' => $itemCount > 1 ? "Pengajuan aset berhasil dibuat dengan {$itemCount} item." : 'Pengajuan aset berhasil dibuat.',
                'reference_number' => $assetRequest->reference_number,
                'progress_url' => $assetRequest->publicProgressUrl(),
                'created_count' => 1,
                'item_count' => $itemCount,
                'status' => $assetRequest->status?->label() ?? 'Pending',
                'lifecycle_stage' => $assetRequest->lifecycleStageLabel(),
                'next_step' => $assetRequest->nextStepLabel(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            Log::error('Gagal memproses pengajuan aset publik: '.$e->getMessage(), [
                'exception' => $e,
                'request' => $request->except('attachments'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem. Silakan coba beberapa saat lagi.',
            ], 500);
        }
    }

    private function resolveUserId(array $data): int
    {
        $contactData = $this->resolveContactData($data);

        if (! empty($data['user_id'])) {
            $user = User::findOrFail($data['user_id']);
            $updates = [];

            if (blank($user->whatsapp_number) && isset($contactData['whatsapp_number'])) {
                $updates['whatsapp_number'] = $contactData['whatsapp_number'];
            }

            if (blank($user->email) && isset($contactData['email'])) {
                $updates['email'] = $contactData['email'];
            }

            if ($updates !== []) {
                $user->update($updates);
            }

            return $user->id;
        }

        $jobTitleId = $this->resolveJobTitleId($data);
        $user = User::create([
            'name' => $data['applicant_name'],
            'job_title_id' => $jobTitleId,
            'business_entity_id' => $data['business_entity_id'],
            'whatsapp_number' => $contactData['whatsapp_number'] ?? null,
            'email' => $contactData['email'] ?? null,
        ]);

        Cache::forget('user_options');
        Cache::forget('job_title_options');
        Cache::forget('business_entity_options');

        return $user->id;
    }

    private function candidateUserId(array $data): ?int
    {
        return ! empty($data['user_id']) ? (int) $data['user_id'] : null;
    }

    private function findPublicApproval(string $token): AssetRequestApproval
    {
        return AssetRequestApproval::query()
            ->where('public_token', $token)
            ->with([
                'user',
                'assetRequest.asset',
                'assetRequest.items.asset',
                'assetRequest.user.jobTitle',
                'assetRequest.user.businessEntity',
                'assetRequest.division',
                'assetRequest.assetLocation',
                'assetRequest.approvals.user.jobTitle',
                'assetRequest.approvals.decidedBy',
            ])
            ->firstOrFail();
    }

    private function approvalCanBeDecided(AssetRequestApproval $approval): bool
    {
        $assetRequest = $approval->assetRequest;

        return $assetRequest !== null
            && $assetRequest->status === RequestStatus::Pending
            && (int) $assetRequest->current_level === (int) $approval->level
            && $approval->status === RequestStatus::Pending;
    }

    private function abortIfApprovalCannotBeDecided(AssetRequestApproval $approval): void
    {
        if (! $this->approvalCanBeDecided($approval)) {
            abort(403, 'Link approval ini sudah tidak aktif.');
        }
    }

    private function selectedAssetIds(array $data): array
    {
        $assetIds = $data['asset_ids'] ?? [];

        if ($assetIds === [] && ! empty($data['asset_id'])) {
            $assetIds = [$data['asset_id']];
        }

        return collect($assetIds)
            ->filter(fn ($assetId) => $assetId !== null && $assetId !== '')
            ->map(fn ($assetId) => (int) $assetId)
            ->unique()
            ->values()
            ->all();
    }

    private function selectedAssetIdsFromRequest(Request $request): array
    {
        return $this->selectedAssetIds([
            'asset_id' => $request->input('asset_id'),
            'asset_ids' => $request->input('asset_ids', []),
        ]);
    }

    /**
     * @return array<int, array{asset_id: null, item_name: string, qty: int}>
     */
    private function pengadaanItemsFromRequest(Request $request): array
    {
        $items = collect($request->input('items', []))
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item): ?array {
                $name = trim((string) ($item['item_name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'asset_id' => null,
                    'item_name' => $name,
                    'qty' => max(1, (int) ($item['qty'] ?? 1)),
                ];
            })
            ->filter()
            ->values();

        if ($items->isNotEmpty()) {
            return $items->all();
        }

        $name = trim((string) $request->input('item_name', ''));

        if ($name === '') {
            return [];
        }

        return [[
            'asset_id' => null,
            'item_name' => $name,
            'qty' => max(1, (int) $request->input('qty', 1)),
        ]];
    }

    private function resolveContactData(array $data): array
    {
        $contact = [];

        if (array_key_exists('whatsapp_number', $data) && $data['whatsapp_number'] !== null && $data['whatsapp_number'] !== '') {
            $contact['whatsapp_number'] = $data['whatsapp_number'];
        }

        if (array_key_exists('email', $data) && $data['email'] !== null && $data['email'] !== '') {
            $contact['email'] = $data['email'];
        }

        return $contact;
    }

    private function resolveJobTitleId(array $data): ?int
    {
        $jobTitleId = $data['job_title_id'] ?? null;

        if (empty($jobTitleId)) {
            return null;
        }

        if ($jobTitleId === 'other') {
            $title = trim((string) ($data['custom_job_title'] ?? ''));

            $jobTitle = JobTitle::firstOrCreate(['title' => $title]);
            Cache::forget('job_title_options');

            return $jobTitle->id;
        }

        return (int) $jobTitleId;
    }

    private function resolveAssetLocationId(array $data): ?int
    {
        $locationId = $data['asset_location_id'] ?? null;

        if (empty($locationId)) {
            return null;
        }

        if ($locationId === 'other') {
            $name = trim((string) ($data['custom_asset_location'] ?? ''));

            $location = AssetLocation::firstOrCreate(['name' => $name]);
            Cache::forget('asset_location_options');

            return $location->id;
        }

        return (int) $locationId;
    }

    private function formatAssetLabel(Asset $asset): string
    {
        $label = $asset->name;

        if ($asset->serial_number) {
            $label .= ' (SN: '.$asset->serial_number.')';
        }

        return $label;
    }
}
