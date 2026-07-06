<?php

namespace App\Services;

use App\Models\ApprovalLog;
use App\Models\Submission;
use App\Models\SubmissionPreApproval;
use App\Models\SubmissionStepContent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubmissionDetailService
{
    public function buildDetail(Submission $submission): array
    {
        $steps = collect($submission->steps)
            ->sortBy(fn($step) => sprintf('%010d-%010d', $step->step_order, $step->id))
            ->values();

        $deptActors = $this->resolveDepartmentActors(
            $steps
                ->pluck('target_dept_id')
                ->filter()
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );

        $flow = $this->buildFlow($submission, $steps, $deptActors);

        return [
            'creator_id' => $submission->creator_id,
            'current_step' => $flow['current_step'],
            'total_steps' => $steps->count(),
            'logs' => $flow['legacy_logs'],
            'flow_steps' => $flow['flow_steps'],
            'last_dept_name' => $flow['last_dept_name'],
            'can_edit' => $flow['can_edit'],
            'rejection_reason' => $flow['rejection_reason'],
            'rejected_step_name' => $flow['rejected_step_name'],
            'edit_form' => $this->buildEditForm($submission, $steps),
        ];
    }

    private function resolveDepartmentActors(array $deptIds): array
    {
        if (empty($deptIds)) {
            return [];
        }

        $users = User::with('roles')
            ->whereIn('department_id', $deptIds)
            ->get();

        $actors = [];

        foreach ($deptIds as $deptId) {
            $deptUsers = $users->where('department_id', $deptId)->values();
            $staff = $deptUsers->first(fn($user) => $user->roles->contains('id', 4));
            $manager = $deptUsers->first(fn($user) => $user->roles->contains('id', 3));

            $actors[(int) $deptId] = [
                'staff_id' => $staff?->id,
                'staff_name' => $staff?->full_name,
                'manager_id' => $manager?->id,
                'manager_name' => $manager?->full_name,
                'has_staff' => (bool) $staff,
                'has_manager' => (bool) $manager,
            ];
        }

        return $actors;
    }

    private function buildFlow(Submission $submission, Collection $steps, array $deptActors): array
    {
        $totalSteps = $steps->count();
        $currentStepIndex = $this->findCurrentStepIndex($steps);
        $flowSteps = [];
        $legacyLogs = [];
        $canEdit = false;
        $rejectionReason = null;
        $rejectedStepName = null;
        $lastDeptName = null;

        foreach ($steps as $index => $step) {
            $deptId = (int) $step->target_dept_id;
            $actors = $deptActors[$deptId] ?? [
                'staff_id' => null,
                'staff_name' => null,
                'manager_id' => null,
                'manager_name' => null,
                'has_staff' => false,
                'has_manager' => false,
            ];

            $staffRequired = (bool) ($actors['has_staff'] ?? false);
            $managerRequired = (bool) ($actors['has_manager'] ?? false);
            $preApproval = $this->latestPreApproval($step);
            $decision = $this->latestDecision($step);

            $isPast = $index < $currentStepIndex;
            $isCurrent = $index === $currentStepIndex && $currentStepIndex < $totalSteps;
            $isFuture = $index > $currentStepIndex;

            $deptName = $step->department->dept_name ?? 'N/A';
            $stepStatus = 'pending';
            $stepTime = $this->formatDateTime($step->created_at);
            $stepComment = 'Dang cho xu ly...';
            $rejectedBy = null;
            $stepRejectionReason = null;
            $stages = [];

            if ($isPast) {
                $stepStatus = 'done';
                $stepTime = $this->formatDateTime($decision?->created_at ?? $preApproval?->created_at ?? $step->created_at);
                $stepComment = $decision?->comment ?? $preApproval?->comment ?? 'Da hoan tat';
                $stages = $this->normalizeStages($this->buildCompletedStages(
                    $staffRequired,
                    $managerRequired,
                    $actors['staff_name'] ?? null,
                    $actors['manager_name'] ?? null,
                    $step,
                    $preApproval,
                    $decision
                ));
            } elseif ($isCurrent) {
                if ($preApproval && $preApproval->action === 'revision_requested') {
                    $stepStatus = 'rejected';
                    $stepTime = $this->formatDateTime($preApproval->created_at);
                    $stepComment = $preApproval->comment ?: 'Phong da tu choi va yeu cau chinh sua.';
                    $rejectedBy = $actors['staff_name'] ?? null;
                    $stepRejectionReason = $stepComment;
                    $canEdit = true;
                    $rejectionReason = $stepRejectionReason;
                    $rejectedStepName = $deptName;
                    $stages = $this->normalizeStages([
                        $this->buildStage('sent', 'Đã gửi đến phòng', 'done', $this->formatDateTime($step->created_at)),
                        $this->buildStage(
                            'staff_wait',
                            'Đang chờ cán bộ phòng kiểm tra',
                            'done',
                            $this->formatDateTime($preApproval->created_at),
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_signed',
                            'Cán bộ đã ký nháy xác nhận',
                            'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_rejected',
                            'Cán bộ từ chối',
                            'rejected',
                            $this->formatDateTime($preApproval->created_at),
                            $preApproval->comment,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_wait',
                            'Đang chờ quản lý phòng ban ký xác nhận',
                            'skipped',
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_signed',
                            'Quản lý phòng ban đã ký xác nhận',
                            'skipped',
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                    ]);
                } elseif ($preApproval && $preApproval->action === 'signed' && $decision && $decision->action === 'rejected') {
                    $stepStatus = 'rejected';
                    $stepTime = $this->formatDateTime($decision->created_at);
                    $stepComment = $decision->comment ?: 'Quan ly phong ban da tu choi.';
                    $rejectedBy = $actors['manager_name'] ?? null;
                    $stepRejectionReason = $stepComment;
                    $canEdit = true;
                    $rejectionReason = $stepRejectionReason;
                    $rejectedStepName = $deptName;
                    $stages = $this->normalizeStages([
                        $this->buildStage('sent', 'Đã gửi đến phòng', 'done', $this->formatDateTime($step->created_at)),
                        $this->buildStage(
                            'staff_wait',
                            'Đang chờ cán bộ phòng kiểm tra',
                            'done',
                            $this->formatDateTime($preApproval->created_at),
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_signed',
                            'Cán bộ đã ký nháy xác nhận',
                            'done',
                            $this->formatDateTime($preApproval->created_at),
                            $preApproval->comment,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_rejected',
                            'Cán bộ từ chối',
                            'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_wait',
                            'Đang chờ quản lý phòng ban ký xác nhận',
                            'done',
                            $this->formatDateTime($decision->created_at),
                            null,
                            $actors['manager_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_signed',
                            'Quản lý phòng ban đã ký xác nhận',
                            'rejected',
                            $this->formatDateTime($decision->created_at),
                            $decision->comment,
                            $actors['manager_name'] ?? null
                        ),
                    ]);
                } elseif ($preApproval && $preApproval->action === 'signed' && !$decision) {
                    $stepStatus = 'current';
                    $stepTime = $this->formatDateTime($preApproval->created_at);
                    $stepComment = 'Dang cho quan ly phong ban ky xac nhan...';
                    $stages = $this->normalizeStages([
                        $this->buildStage('sent', 'Đã gửi đến phòng', 'done', $this->formatDateTime($step->created_at)),
                        $this->buildStage(
                            'staff_wait',
                            'Đang chờ cán bộ phòng kiểm tra',
                            'done',
                            $this->formatDateTime($preApproval->created_at),
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_signed',
                            'Cán bộ đã ký nháy xác nhận',
                            'done',
                            $this->formatDateTime($preApproval->created_at),
                            $preApproval->comment,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_rejected',
                            'Cán bộ từ chối',
                            'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_wait',
                            'Đang chờ quản lý phòng ban ký xác nhận',
                            'current',
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_signed',
                            'Quản lý phòng ban đã ký xác nhận',
                            'pending',
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                    ]);
                } elseif (!$preApproval) {
                    $stepStatus = 'current';
                    $stepComment = $staffRequired
                        ? 'Dang cho can bo phong kiem tra...'
                        : 'Dang cho quan ly phong ban ky xac nhan...';
                    $stages = $this->normalizeStages([
                        $this->buildStage('sent', 'Đã gửi đến phòng', 'done', $this->formatDateTime($step->created_at)),
                        $this->buildStage(
                            'staff_wait',
                            'Đang chờ cán bộ phòng kiểm tra',
                            $staffRequired ? 'current' : 'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_signed',
                            'Cán bộ đã ký nháy xác nhận',
                            $staffRequired ? 'pending' : 'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'staff_rejected',
                            'Cán bộ từ chối',
                            $staffRequired ? 'pending' : 'skipped',
                            null,
                            null,
                            $actors['staff_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_wait',
                            'Đang chờ quản lý phòng ban ký xác nhận',
                            $staffRequired && $managerRequired ? 'pending' : ($managerRequired ? 'current' : 'skipped'),
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                        $this->buildStage(
                            'manager_signed',
                            'Quản lý phòng ban đã ký xác nhận',
                            $managerRequired ? 'pending' : 'skipped',
                            null,
                            null,
                            $actors['manager_name'] ?? null
                        ),
                    ]);
                } else {
                    $stepStatus = 'current';
                    $stepComment = 'Dang cho xu ly...';
                    $stages = $this->normalizeStages($this->buildPendingStages(
                        $staffRequired,
                        $managerRequired,
                        $actors['staff_name'] ?? null,
                        $actors['manager_name'] ?? null,
                        $step,
                        false
                    ));
                }
            } else {
                $stepStatus = 'pending';
                $stepComment = 'Dang cho den luot phong ban nay...';
                $stages = $this->normalizeStages($this->buildPendingStages(
                    $staffRequired,
                    $managerRequired,
                    $actors['staff_name'] ?? null,
                    $actors['manager_name'] ?? null,
                    $step,
                    true
                ));
            }

            if ($stepStatus === 'rejected') {
                $canEdit = true;
            }

            if ($stepStatus === 'done' && $currentStepIndex >= $totalSteps && $index === $totalSteps - 1) {
                $lastDeptName = $deptName;
            } elseif ($isCurrent || $stepStatus === 'rejected') {
                $lastDeptName = $deptName;
            } elseif ($lastDeptName === null) {
                $lastDeptName = $deptName;
            }

            if ($rejectionReason === null && $stepRejectionReason !== null) {
                $rejectionReason = $stepRejectionReason;
                $rejectedStepName = $deptName;
            }

            $flowSteps[] = [
                'step_id' => $step->id,
                'step_order' => $step->step_order,
                'dept_id' => $deptId,
                'dept_name' => $deptName,
                'staff_id' => $actors['staff_id'] ?? null,
                'staff_name' => $actors['staff_name'] ?? null,
                'manager_id' => $actors['manager_id'] ?? null,
                'manager_name' => $actors['manager_name'] ?? null,
                'status' => $stepStatus,
                'time' => $stepTime,
                'comment' => $stepComment,
                'request_content' => $step->content_text,
                'rejection_reason' => $stepRejectionReason,
                'rejected_by' => $rejectedBy,
                'stages' => $stages,
            ];

            $legacyLogs[] = [
                'dept_name' => $deptName,
                'status' => $stepStatus,
                'time' => $stepTime ?? '--:--',
                'comment' => $stepComment,
                'request_content' => $step->content_text,
                'staff_name' => $actors['staff_name'] ?? null,
                'manager_name' => $actors['manager_name'] ?? null,
                'rejection_reason' => $stepRejectionReason,
            ];
        }

        $currentStep = $totalSteps === 0
            ? 0
            : min($currentStepIndex + 1, $totalSteps);

        if ($lastDeptName === null && $steps->isNotEmpty()) {
            $lastDeptName = $steps->last()->department->dept_name ?? null;
        }

        return [
            'current_step' => $currentStep,
            'legacy_logs' => $legacyLogs,
            'flow_steps' => $flowSteps,
            'last_dept_name' => $lastDeptName,
            'can_edit' => $canEdit,
            'rejection_reason' => $rejectionReason,
            'rejected_step_name' => $rejectedStepName,
        ];
    }

    private function findCurrentStepIndex(Collection $steps): int
    {
        foreach ($steps as $index => $step) {
            $preApproval = $this->latestPreApproval($step);
            $decision = $this->latestDecision($step);

            if ($preApproval && $preApproval->action === 'revision_requested') {
                return $index;
            }

            if (!$preApproval) {
                return $index;
            }

            if ($preApproval->action === 'signed' && !$decision) {
                return $index;
            }

            if ($decision && $decision->action === 'rejected') {
                return $index;
            }

            if ($preApproval->action === 'signed' && $decision && $decision->action === 'approved') {
                continue;
            }
        }

        return $steps->count();
    }

    private function latestPreApproval(SubmissionStepContent $step): ?SubmissionPreApproval
    {
        return $step->preApprovals->sortByDesc('created_at')->first();
    }

    private function latestDecision(SubmissionStepContent $step): ?ApprovalLog
    {
        return $step->approvalLogs
            ->whereIn('action', ['approved', 'rejected'])
            ->sortByDesc('created_at')
            ->first();
    }

    private function buildPendingStages(
        bool $staffRequired,
        bool $managerRequired,
        ?string $staffName,
        ?string $managerName,
        SubmissionStepContent $step,
        bool $isFuture
    ): array {
        return [
            $this->buildStage('sent', 'Đã gửi đến phòng', $isFuture ? 'pending' : 'done', $isFuture ? null : $this->formatDateTime($step->created_at)),
            $this->buildStage('staff_wait', 'Đang chờ cán bộ phòng kiểm tra', $staffRequired ? 'pending' : 'skipped', null, null, $staffName),
            $this->buildStage('staff_signed', 'Cán bộ đã ký nháy xác nhận', $staffRequired ? 'pending' : 'skipped', null, null, $staffName),
            $this->buildStage('staff_rejected', 'Cán bộ từ chối', $staffRequired ? 'pending' : 'skipped', null, null, $staffName),
            $this->buildStage('manager_wait', 'Đang chờ quản lý phòng ban ký xác nhận', $managerRequired ? 'pending' : 'skipped', null, null, $managerName),
            $this->buildStage('manager_signed', 'Quản lý phòng ban đã ký xác nhận', $managerRequired ? 'pending' : 'skipped', null, null, $managerName),
        ];
    }

    private function buildCompletedStages(
        bool $staffRequired,
        bool $managerRequired,
        ?string $staffName,
        ?string $managerName,
        SubmissionStepContent $step,
        ?SubmissionPreApproval $preApproval,
        ?ApprovalLog $decision
    ): array {
        return [
            $this->buildStage('sent', 'Đã gửi đến phòng', 'done', $this->formatDateTime($step->created_at)),
            $this->buildStage('staff_wait', 'Đang chờ cán bộ phòng kiểm tra', $staffRequired ? 'done' : 'skipped', $staffRequired ? $this->formatDateTime($preApproval?->created_at ?? $step->created_at) : null, null, $staffName),
            $this->buildStage('staff_signed', 'Cán bộ đã ký nháy xác nhận', $staffRequired ? 'done' : 'skipped', $staffRequired ? $this->formatDateTime($preApproval?->created_at ?? $step->created_at) : null, $preApproval?->comment, $staffName),
            $this->buildStage('staff_rejected', 'Cán bộ từ chối', 'skipped', null, null, $staffName),
            $this->buildStage('manager_wait', 'Đang chờ quản lý phòng ban ký xác nhận', $managerRequired ? 'done' : 'skipped', $managerRequired ? $this->formatDateTime($decision?->created_at ?? $step->created_at) : null, null, $managerName),
            $this->buildStage('manager_signed', 'Quản lý phòng ban đã ký xác nhận', $managerRequired ? 'done' : 'skipped', $managerRequired ? $this->formatDateTime($decision?->created_at ?? $step->created_at) : null, $decision?->comment, $managerName),
        ];
    }

    private function buildStage(
        string $key,
        string $label,
        string $status,
        ?string $time = null,
        ?string $comment = null,
        ?string $actorName = null
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'time' => $time,
            'comment' => $comment,
            'actor_name' => $actorName,
        ];
    }

    /**
     * Loai bo stage placeholder khong ap dung de timeline khong hien mau phu.
     */
    private function normalizeStages(array $stages): array
    {
        $filtered = array_filter($stages, function (array $stage) {
            $key = $stage['key'] ?? null;
            $status = $stage['status'] ?? null;

            if ($key === 'staff_rejected') {
                return $status === 'rejected';
            }

            return $status !== 'skipped';
        });

        return array_values($filtered);
    }

    private function formatDateTime(mixed $value, string $format = 'H:i d/m/Y'): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildEditForm(Submission $submission, Collection $steps): array
    {
        $assetRequests = collect($submission->assetRequests);
        $locations = collect($submission->submissionLocations);

        $departments = [];

        foreach ($steps as $step) {
            $deptId = (int) $step->target_dept_id;
            $deptAssets = $assetRequests->filter(function ($request) use ($deptId) {
                return $request->asset && (int) $request->asset->department_id === $deptId;
            })->values();

            $deptLocations = $locations->filter(function ($entry) use ($deptId) {
                return $entry->location && (int) $entry->location->department_id === $deptId;
            })->values();

            $items = [];

            foreach ($deptAssets as $request) {
                $items[] = [
                    'entity_id' => $request->asset_id,
                    'name' => $request->asset?->asset_name,
                    'type' => $request->asset?->type ?? 'returnable',
                    'quantity' => $this->parseQuantity($request->note),
                    'time_info' => $request->note,
                    'expected_borrow_date' => $request->expected_borrow_date ? Carbon::parse($request->expected_borrow_date)->format('Y-m-d H:i:s') : null,
                    'expected_return_date' => $request->expected_return_date ? Carbon::parse($request->expected_return_date)->format('Y-m-d H:i:s') : null,
                ];
            }

            foreach ($deptLocations as $location) {
                $items[] = [
                    'entity_id' => $location->location_id,
                    'name' => $location->location?->location_name,
                    'type' => 'location',
                    'quantity' => 1,
                    'time_info' => $location->start_time && $location->end_time
                        ? Carbon::parse($location->start_time)->format('d/m/Y H:i') . ' - ' . Carbon::parse($location->end_time)->format('d/m/Y H:i')
                        : null,
                    'start_time' => $location->start_time ? Carbon::parse($location->start_time)->format('Y-m-d H:i:s') : null,
                    'end_time' => $location->end_time ? Carbon::parse($location->end_time)->format('Y-m-d H:i:s') : null,
                ];
            }

            $departments[] = [
                'dept_name' => $step->department->dept_name ?? 'N/A',
                'dept_id' => $deptId,
                'note' => $step->content_text,
                'priority' => (int) $step->step_order,
                'opinion_only' => empty($items),
                'items' => $items,
            ];
        }

        $attachments = DB::table('submission_attachments')
            ->where('submission_id', $submission->id)
            ->orderBy('id')
            ->get()
            ->map(function ($file) {
                return [
                    'file_name' => $file->file_name,
                    'file_size' => $file->file_size,
                    'file_type' => $file->file_type,
                    'file_path' => $file->file_path,
                    'url' => url('/storage/' . ltrim($file->file_path, '/')),
                ];
            })
            ->values();

        return [
            'title' => $submission->title,
            'description' => $submission->content,
            'workflow_id' => $submission->category_id,
            'creator_id' => $submission->creator_id,
            'start_date' => $submission->start_time ? Carbon::parse($submission->start_time)->format('Y-m-d H:i:s') : null,
            'end_date' => $submission->end_time ? Carbon::parse($submission->end_time)->format('Y-m-d H:i:s') : null,
            'departments' => $departments,
            'attachments' => $attachments,
        ];
    }

    private function parseQuantity(?string $note): int
    {
        if (!$note) {
            return 1;
        }

        if (preg_match('/SL:\\s*(\\d+)/i', $note, $matches)) {
            return max(1, (int) $matches[1]);
        }

        return 1;
    }
}
