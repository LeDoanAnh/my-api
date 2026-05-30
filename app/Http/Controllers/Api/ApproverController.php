<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalLog;
use App\Models\Submission;
use App\Models\SubmissionStepContent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApproverController extends Controller
{
    /**
     * Lấy thông tin tờ trình chỉ phần của phòng ban đang duyệt.
     */
    public function show(Request $request, int $submissionId)
    {
        $deptId = $request->query('dept_id');

        if (!$deptId) {
            return response()->json(['success' => false, 'message' => 'Thiếu dept_id'], 422);
        }

        $submission = Submission::with(['creator.department'])->find($submissionId);

        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tờ trình'], 404);
        }

        $stepContent = SubmissionStepContent::where('submission_id', $submissionId)
            ->where('target_dept_id', $deptId)
            ->first();

        $locations = $submission->submissionLocations()
            ->whereHas('location', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            })
            ->with('location')
            ->get()
            ->map(fn($sl) => [
                'location_name' => $sl->location->location_name,
                'start_time'    => Carbon::parse($sl->start_time)->format('H:i d/m/Y'),
                'end_time'      => Carbon::parse($sl->end_time)->format('H:i d/m/Y'),
            ]);

        $assets = $submission->assetRequests()
            ->whereHas('asset', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            })
            ->with('asset')
            ->get()
            ->map(fn($ar) => [
                'asset_name' => $ar->asset->asset_name,
                'quantity'   => 1,
            ]);

        $approvalLog = ApprovalLog::where('submission_id', $submissionId)
            ->whereHas('approver', function ($q) use ($deptId) {
                $q->where('department_id', $deptId);
            })
            ->latest()
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'submission_id' => $submission->id,
                'title'         => $submission->title,
                'content'       => $submission->content,
                'status'        => $submission->status,
                'start_time'    => $submission->start_time
                    ? Carbon::parse($submission->start_time)->format('H:i d/m/Y') : null,
                'end_time'      => $submission->end_time
                    ? Carbon::parse($submission->end_time)->format('H:i d/m/Y') : null,
                'sender'        => ($submission->creator->full_name ?? 'N/A')
                    . ' - ' . ($submission->creator->department->dept_name ?? 'N/A'),
                'note_for_dept' => $stepContent?->content_text ?? null,
                'locations'     => $locations,
                'assets'        => $assets,
                'my_decision'   => $approvalLog ? [
                    'action'     => $approvalLog->action,
                    'comment'    => $approvalLog->comment,
                    'decided_at' => Carbon::parse($approvalLog->created_at)->format('H:i d/m/Y'),
                ] : null,
            ],
        ]);
    }

    /**
     * Duyệt hoặc từ chối phần của phòng ban.
     */
    public function decide(Request $request, int $submissionId)
    {
        $request->validate([
            'approver_id' => 'required|exists:users,id',
            'action'      => 'required|in:approved,rejected',
            'password'    => 'required|string',
            'comment'     => 'nullable|string|max:500',
        ]);

        $submission = Submission::find($submissionId);
        if (!$submission) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy tờ trình'], 404);
        }

        $approver = User::find($request->approver_id);
        if (!$approver || !Hash::check($request->password, $approver->password)) {
            return response()->json(['success' => false, 'message' => 'Mật khẩu không đúng'], 401);
        }

        $existing = ApprovalLog::where('submission_id', $submissionId)
            ->where('approver_id', $request->approver_id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Bạn đã xử lý tờ trình này rồi'], 409);
        }

        ApprovalLog::create([
            'submission_id' => $submissionId,
            'approver_id'   => $request->approver_id,
            'action'        => $request->action,
            'comment'       => $request->comment ?? '',
        ]);

        $this->_updateSubmissionStatus($submission);

        return response()->json([
            'success' => true,
            'message' => $request->action === 'approved' ? 'Đã duyệt thành công!' : 'Đã từ chối tờ trình.',
        ]);
    }

    private function _updateSubmissionStatus(Submission $submission): void
    {
        $hasRejected = ApprovalLog::where('submission_id', $submission->id)
            ->where('action', 'rejected')->exists();

        if ($hasRejected) {
            $submission->update(['status' => 'rejected']);
            $this->_createNotification($submission, 'rejected');
            return;
        }

        $totalSteps = SubmissionStepContent::where('submission_id', $submission->id)->count();
        $approvedSteps = ApprovalLog::where('submission_id', $submission->id)
            ->where('action', 'approved')->count();

        if ($totalSteps > 0 && $approvedSteps >= $totalSteps) {
            $submission->update(['status' => 'approved']);
            $this->_createNotification($submission, 'approved');
        }
    }

    private function _createNotification(Submission $submission, string $action): void
    {
        $isApproved = $action === 'approved';

        \App\Models\Notification::create([
            'user_id' => $submission->creator_id,
            'title'   => $isApproved ? 'Tờ trình đã được duyệt' : 'Tờ trình bị từ chối',
            'message' => $isApproved
                ? "Tờ trình \"{$submission->title}\" đã được tất cả phòng ban phê duyệt."
                : "Tờ trình \"{$submission->title}\" đã bị từ chối bởi một phòng ban.",
            'type'    => $action,
            'is_read' => false,
        ]);
    }
}