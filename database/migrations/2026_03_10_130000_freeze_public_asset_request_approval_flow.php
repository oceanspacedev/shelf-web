<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ALL_DIVISIONS = '*';

    public function up(): void
    {
        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->string('approval_track')->nullable()->after('division');
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropForeign(['approval_level_id']);
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_level_id')->nullable()->change();
            $table->foreign('approval_level_id')
                ->references('id')
                ->on('approval_levels')
                ->nullOnDelete();
        });

        $this->backfillApprovalRouting();
    }

    public function down(): void
    {
        DB::table('request_approvals')
            ->whereNull('approval_level_id')
            ->delete();

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->dropForeign(['approval_level_id']);
        });

        Schema::table('request_approvals', function (Blueprint $table) {
            $table->unsignedBigInteger('approval_level_id')->change();
            $table->foreign('approval_level_id')
                ->references('id')
                ->on('approval_levels')
                ->cascadeOnDelete();
        });

        Schema::table('public_asset_requests', function (Blueprint $table) {
            $table->dropColumn('approval_track');
        });
    }

    private function backfillApprovalRouting(): void
    {
        $now = now();

        DB::table('public_asset_requests')
            ->select(['id', 'request_type', 'division', 'status', 'admin_notes', 'deleted_at'])
            ->orderBy('id')
            ->get()
            ->each(function (object $request) use ($now): void {
                $approvalTrack = $this->resolveApprovalTrackForRequest($request);

                if ($approvalTrack !== null) {
                    DB::table('public_asset_requests')
                        ->where('id', $request->id)
                        ->update(['approval_track' => $approvalTrack]);
                }

                if ($approvalTrack === null) {
                    if ($request->status === 'pending' && $request->deleted_at === null) {
                        $this->autoApproveRequestWithoutTrack($request, $now);
                    }

                    return;
                }

                if ($request->status !== 'pending' || $request->deleted_at !== null) {
                    return;
                }

                $existingLevels = DB::table('request_approvals')
                    ->where('public_asset_request_id', $request->id)
                    ->pluck('id', 'level')
                    ->all();

                $this->getApprovalLevelsForTrack($request->request_type, $approvalTrack)
                    ->each(function (object $approvalLevel) use ($existingLevels, $request, $now): void {
                        if (array_key_exists($approvalLevel->level, $existingLevels)) {
                            return;
                        }

                        DB::table('request_approvals')->insert([
                            'public_asset_request_id' => $request->id,
                            'approval_level_id' => $approvalLevel->id,
                            'token' => Str::uuid()->toString(),
                            'level' => $approvalLevel->level,
                            'approver_name' => $approvalLevel->approver_name,
                            'approver_email' => $approvalLevel->approver_email,
                            'status' => 'pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    });
            });
    }

    private function autoApproveRequestWithoutTrack(object $request, $now): void
    {
        DB::table('public_asset_requests')
            ->where('id', $request->id)
            ->update([
                'status' => 'approved',
                'admin_notes' => filled($request->admin_notes)
                    ? $request->admin_notes
                    : 'Disetujui otomatis karena tidak ada approval yang dikonfigurasi untuk divisi ini.',
                'updated_at' => $now,
            ]);
    }

    private function resolveApprovalTrackForRequest(object $request): ?string
    {
        $trackFromExistingApprovals = DB::table('request_approvals')
            ->join('approval_levels', 'approval_levels.id', '=', 'request_approvals.approval_level_id')
            ->where('request_approvals.public_asset_request_id', $request->id)
            ->orderBy('request_approvals.level')
            ->value('approval_levels.division');

        if (is_string($trackFromExistingApprovals) && $trackFromExistingApprovals !== '') {
            return $this->normalizeDivision($trackFromExistingApprovals);
        }

        $normalizedDivision = $this->normalizeDivision($request->division);
        $normalizedDivisionKey = $this->normalizeDivisionKey($normalizedDivision);

        $hasSpecificTrack = DB::table('approval_levels')
            ->where('request_type', $request->request_type)
            ->whereRaw('LOWER(division) = ?', [$normalizedDivisionKey])
            ->exists();

        if ($hasSpecificTrack) {
            return $normalizedDivision;
        }

        $hasGlobalTrack = DB::table('approval_levels')
            ->where('request_type', $request->request_type)
            ->where('division', self::ALL_DIVISIONS)
            ->exists();

        return $hasGlobalTrack ? self::ALL_DIVISIONS : null;
    }

    private function getApprovalLevelsForTrack(string $requestType, string $approvalTrack)
    {
        return DB::table('approval_levels')
            ->where('request_type', $requestType)
            ->whereRaw('LOWER(division) = ?', [$this->normalizeDivisionKey($approvalTrack)])
            ->orderBy('level')
            ->get();
    }

    private function normalizeDivision(?string $division): string
    {
        $division = preg_replace('/\s+/u', ' ', trim((string) $division)) ?? '';

        return $division === '' ? self::ALL_DIVISIONS : $division;
    }

    private function normalizeDivisionKey(?string $division): string
    {
        return mb_strtolower($this->normalizeDivision($division));
    }
};
