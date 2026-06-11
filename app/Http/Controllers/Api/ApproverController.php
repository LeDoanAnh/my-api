<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\Submission;
use App\Models\SubmissionPreApproval;
use App\Models\SubmissionPreApprovalAttachment;
use App\Models\SubmissionStepContent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ApproverController extends Controller
{
    public function show(Request $request, int $submissionId)
    {
        $deptId = (int) $request->query('dept_id');

        if (!$deptId) {
            return response()->json(['success' => false, 'message' => 'Thieu dept_id'], 422);
        }

        $submission = Submission::with(['creator.department'])->find($submissionId);
        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay to trinh'], 404);
        }

        $currentStep = $this->getCurrentStep($submission);
        $stepContent = SubmissionStepContent::where('submission_id', $submissionId)
            ->where('target_dept_id', $deptId)
            ->orderBy('step_order')
            ->first();

        $locations = $submission->submissionLocations()
            ->whereHas('location', fn($q) => $q->where('department_id', $deptId))
            ->with('location')
            ->get()
            ->map(fn($sl) => [
                'location_name' => $sl->location->location_name,
                'start_time' => Carbon::parse($sl->start_time)->format('H:i d/m/Y'),
                'end_time' => Carbon::parse($sl->end_time)->format('H:i d/m/Y'),
            ]);

        $assets = $submission->assetRequests()
            ->whereHas('asset', fn($q) => $q->where('department_id', $deptId))
            ->with('asset')
            ->get()
            ->map(fn($ar) => [
                'asset_name' => $ar->asset->asset_name,
                'quantity' => 1,
            ]);

        $attachments = DB::table('submission_attachments')
            ->where('submission_id', $submissionId)
            ->orderBy('id')
            ->get()
            ->map(fn($file) => [
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'file_type' => $file->file_type,
                'file_path' => $file->file_path,
                'url' => $request->getSchemeAndHttpHost() . '/storage/' . ltrim($file->file_path, '/'),
            ]);

        $approvalLog = $stepContent
            ? $this->getStepDecision($stepContent)
            : ApprovalLog::where('submission_id', $submissionId)
                ->whereHas('approver', fn($q) => $q->where('department_id', $deptId))
                ->latest()
                ->first();
        $previousApprovals = $this->getPreviousApprovals(
            $submissionId,
            $stepContent ?: $currentStep
        );
        $preApproval = $stepContent ? $this->getStepPreApproval($stepContent) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'submission_id' => $submission->id,
                'title' => $submission->title,
                'content' => $submission->content,
                'status' => $submission->status,
                'current_step_id' => $currentStep?->id,
                'current_dept_id' => $currentStep?->target_dept_id,
                'is_current_department' => $currentStep?->target_dept_id === $deptId,
                'start_time' => $submission->start_time ? Carbon::parse($submission->start_time)->format('H:i d/m/Y') : null,
                'end_time' => $submission->end_time ? Carbon::parse($submission->end_time)->format('H:i d/m/Y') : null,
                'sender' => ($submission->creator->full_name ?? 'N/A') . ' - ' . ($submission->creator->department->dept_name ?? 'N/A'),
                'note_for_dept' => $stepContent?->content_text,
                'locations' => $locations,
                'assets' => $assets,
                'attachments' => $attachments,
                'previous_approvals' => $previousApprovals,
                'pre_approval' => $this->formatPreApproval($preApproval, $request),
                'is_pre_approved' => $preApproval?->action === 'signed',
                'my_decision' => $approvalLog ? [
                    'action' => $approvalLog->action,
                    'comment' => $approvalLog->comment,
                    'decided_at' => Carbon::parse($approvalLog->created_at)->format('H:i d/m/Y'),
                ] : null,
            ],
        ]);
    }

    public function decide(Request $request, int $submissionId)
    {
        $request->validate([
            'approver_id' => 'required|exists:users,id',
            'action' => 'required|in:approved,rejected',
            'password' => 'required|string',
            'comment' => 'nullable|string|max:500',
        ]);

        $submission = Submission::with('creator')->find($submissionId);
        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay to trinh'], 404);
        }

        $approver = User::with('roles')->find($request->approver_id);
        if (!$approver || !Hash::check($request->password, $approver->password)) {
            return response()->json(['success' => false, 'message' => 'Mat khau khong dung'], 401);
        }

        if (!$approver->roles->contains('id', 3)) {
            return response()->json(['success' => false, 'message' => 'Chi tai khoan role 3 moi duoc duyet to trinh'], 403);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'To trinh nay da ket thuc xu ly'], 409);
        }

        $currentStep = $this->getCurrentStep($submission);
        if (!$currentStep) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay step dang cho duyet'], 409);
        }

        if ((int) $approver->department_id !== (int) $currentStep->target_dept_id) {
            return response()->json([
                'success' => false,
                'message' => 'Chua den luot phong ban cua ban duyet to trinh nay',
            ], 403);
        }

        if (!$this->stepIsPreApproved($currentStep)) {
            return response()->json([
                'success' => false,
                'message' => 'Can nhan vien phong ban ky nhay truoc khi truong phong duyet',
            ], 409);
        }

        $existingStepDecision = ApprovalLog::where('step_content_id', $currentStep->id)->first();
        if ($existingStepDecision) {
            return response()->json(['success' => false, 'message' => 'Step nay da duoc xu ly'], 409);
        }

        DB::transaction(function () use ($request, $submission, $approver, $currentStep) {
            ApprovalLog::create([
                'submission_id' => $submission->id,
                'step_content_id' => $currentStep->id,
                'approver_id' => $approver->id,
                'action' => $request->action,
                'comment' => $request->comment ?? '',
            ]);

            $this->updateSubmissionStatus($submission);
        });

        $submission->refresh();
        $this->sendNextNotification($submission);

        return response()->json([
            'success' => true,
            'message' => $request->action === 'approved' ? 'Da duyet thanh cong' : 'Da tu choi to trinh',
        ]);
    }

    private function updateSubmissionStatus(Submission $submission): void
    {
        if (ApprovalLog::where('submission_id', $submission->id)->where('action', 'rejected')->exists()) {
            $submission->update(['status' => 'rejected']);
            return;
        }

        $steps = SubmissionStepContent::where('submission_id', $submission->id)->orderBy('step_order')->get();
        if ($steps->isEmpty()) {
            return;
        }

        $allApproved = $steps->every(fn($step) => $this->getStepDecision($step)?->action === 'approved');
        if ($allApproved) {
            $submission->update(['status' => 'approved']);
        }
    }

    private function sendNextNotification(Submission $submission): void
    {
        $submission->loadMissing('creator');
        $notifier = app(NotificationController::class);

        if ($submission->status === 'approved' || $submission->status === 'rejected') {
            if ($submission->creator) {
                $notifier->notifyCreator($submission->creator, $submission, $submission->status);
            }
            return;
        }

        $nextStep = $this->getCurrentStep($submission);
        if ($nextStep) {
            $notifier->notifyPreApproversForStep($nextStep);
        }
    }

    public function preSign(Request $request, int $submissionId)
    {
        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'action' => 'required|in:signed,revision_requested',
            'comment' => 'nullable|string|max:1000',
            'attachments.*' => 'file|max:10240',
        ]);

        $submission = Submission::with('creator')->find($submissionId);
        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay to trinh'], 404);
        }

        $staff = User::with('roles')->find($request->staff_id);
        if (!$staff || !$staff->roles->contains('id', 4)) {
            return response()->json(['success' => false, 'message' => 'Chi tai khoan role 4 moi duoc ky nhay'], 403);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'To trinh nay da ket thuc xu ly'], 409);
        }

        $currentStep = $this->getCurrentStep($submission);
        if (!$currentStep) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay step dang cho ky nhay'], 409);
        }

        if ((int) $staff->department_id !== (int) $currentStep->target_dept_id) {
            return response()->json([
                'success' => false,
                'message' => 'Chua den luot phong ban cua ban xem truoc to trinh nay',
            ], 403);
        }

        if ($this->getStepDecision($currentStep)) {
            return response()->json(['success' => false, 'message' => 'Step nay da duoc truong phong xu ly'], 409);
        }

        $preApproval = DB::transaction(function () use ($request, $submission, $staff, $currentStep) {
            $preApproval = SubmissionPreApproval::create([
                'submission_id' => $submission->id,
                'step_content_id' => $currentStep->id,
                'staff_id' => $staff->id,
                'action' => $request->action,
                'comment' => $request->comment ?? '',
            ]);

            $this->storePreApprovalAttachments($request, $preApproval);

            return $preApproval;
        });

        $notifier = app(NotificationController::class);
        if ($request->action === 'signed') {
            $notifier->notifyApproversForStep($currentStep);
        } elseif ($submission->creator) {
            $notifier->notifyCreatorRevisionRequested($submission->creator, $submission, $staff, $request->comment);
        }

        return response()->json([
            'success' => true,
            'message' => $request->action === 'signed'
                ? 'Da ky nhay thanh cong'
                : 'Da gui yeu cau chinh sua cho nguoi tao',
            'data' => $this->formatPreApproval($preApproval->fresh(['staff.department', 'attachments']), $request),
        ]);
    }

    private function getCurrentStep(Submission $submission): ?SubmissionStepContent
    {
        if ($submission->status !== 'pending') {
            return null;
        }

        $steps = SubmissionStepContent::where('submission_id', $submission->id)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        foreach ($steps as $step) {
            $decision = $this->getStepDecision($step);
            if (!$decision) {
                return $step;
            }
            if ($decision->action === 'rejected') {
                return null;
            }
        }

        return null;
    }

    private function getStepDecision(SubmissionStepContent $step): ?ApprovalLog
    {
        return ApprovalLog::where('submission_id', $step->submission_id)
            ->where(function ($query) use ($step) {
                $query->where('step_content_id', $step->id)
                    ->orWhere(function ($legacy) use ($step) {
                        $legacy->whereNull('step_content_id')
                            ->whereHas('approver', fn($approver) => $approver->where('department_id', $step->target_dept_id));
                    });
            })
            ->whereIn('action', ['approved', 'rejected'])
            ->latest()
            ->first();
    }

    private function getStepPreApproval(SubmissionStepContent $step): ?SubmissionPreApproval
    {
        return SubmissionPreApproval::with(['staff.department', 'attachments'])
            ->where('submission_id', $step->submission_id)
            ->where('step_content_id', $step->id)
            ->latest()
            ->first();
    }

    private function stepIsPreApproved(SubmissionStepContent $step): bool
    {
        $hasStaff = User::where('department_id', $step->target_dept_id)
            ->whereHas('roles', fn($q) => $q->where('roles.id', 4))
            ->exists();

        if (!$hasStaff) {
            return true;
        }

        return $this->getStepPreApproval($step)?->action === 'signed';
    }

    private function formatPreApproval(?SubmissionPreApproval $preApproval, Request $request): ?array
    {
        if (!$preApproval) {
            return null;
        }

        $preApproval->loadMissing(['staff.department', 'attachments']);

        return [
            'id' => $preApproval->id,
            'action' => $preApproval->action,
            'comment' => $preApproval->comment,
            'decided_at' => Carbon::parse($preApproval->created_at)->format('H:i d/m/Y'),
            'staff_id' => $preApproval->staff_id,
            'staff_name' => $preApproval->staff?->full_name,
            'staff_dept' => $preApproval->staff?->department?->dept_name,
            'attachments' => $preApproval->attachments->map(fn($file) => [
                'file_name' => $file->file_name,
                'file_size' => $file->file_size,
                'file_type' => $file->file_type,
                'file_path' => $file->file_path,
                'url' => $request->getSchemeAndHttpHost() . '/storage/' . ltrim($file->file_path, '/'),
            ])->values(),
        ];
    }

    private function storePreApprovalAttachments(Request $request, SubmissionPreApproval $preApproval): void
    {
        $uploadedFiles = $request->file('attachments') ?? [];
        if (!is_array($uploadedFiles)) {
            $uploadedFiles = [$uploadedFiles];
        }

        foreach ($uploadedFiles as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('uploads/pre-approvals', 'public');
            SubmissionPreApprovalAttachment::create([
                'pre_approval_id' => $preApproval->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_type' => $file->getClientOriginalExtension(),
            ]);
        }
    }

    private function getPreviousApprovals(int $submissionId, ?SubmissionStepContent $currentStep)
    {
        if (!$currentStep) {
            return collect();
        }

        return SubmissionStepContent::where('submission_id', $submissionId)
            ->where(function ($query) use ($currentStep) {
                $query->where('step_order', '<', $currentStep->step_order)
                    ->orWhere(function ($sameOrder) use ($currentStep) {
                        $sameOrder->where('step_order', $currentStep->step_order)
                            ->where('id', '<', $currentStep->id);
                    });
            })
            ->with('department:id,dept_name')
            ->orderBy('step_order')
            ->orderBy('id')
            ->get()
            ->map(function (SubmissionStepContent $step) {
                $decision = $this->getStepDecision($step);
                $decision?->loadMissing('approver.department');

                return [
                    'step_id' => $step->id,
                    'step_order' => $step->step_order,
                    'dept_id' => $step->target_dept_id,
                    'dept_name' => $step->department->dept_name ?? 'N/A',
                    'action' => $decision?->action,
                    'comment' => $decision?->comment,
                    'decided_at' => $decision
                        ? Carbon::parse($decision->created_at)->format('H:i d/m/Y')
                        : null,
                    'approver_id' => $decision?->approver?->id,
                    'approver_name' => $decision?->approver?->full_name,
                    'approver_dept' => $decision?->approver?->department?->dept_name,
                ];
            });
    }
}
