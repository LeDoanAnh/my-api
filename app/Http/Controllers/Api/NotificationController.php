<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\SubmissionStepContent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;

class NotificationController extends Controller
{
    public function updateToken(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            if (!$request->has('token')) {
                return response()->json(['message' => 'Token field is required'], 400);
            }

            $user->fcm_token = $request->token;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Da cap nhat FCM token thanh cong',
                'current_token' => $user->fcm_token,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Loi Server: ' . $e->getMessage()], 500);
        }
    }

    public function sendTest(Request $request)
    {
        if (!$request->has('token')) {
            return response()->json(['message' => 'Thieu device token'], 400);
        }

        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            $notification = $this->createAndSend($user, 'Thong bao thu', 'Kiem tra Firebase Cloud Messaging thanh cong.', 'test', [
                'id' => '12345',
                'screen' => 'DETAIL_SCREEN',
            ], $request->token);

            return response()->json([
                'status' => 'success',
                'message' => 'Da gui thanh cong',
                'notification_id' => $notification->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            \Carbon\Carbon::setLocale('vi');
            $userId = $request->query('user_id');

            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui long cung cap user_id tren URL',
                ], 400);
            }

            $notifications = Notification::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            $notifications->map(function ($notification) {
                $notification->time_ago = $notification->created_at->diffForHumans();
                return $notification;
            });

            return response()->json([
                'status' => 'success',
                'user_id' => $userId,
                'total' => $notifications->count(),
                'data' => $notifications,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Loi SQL: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function markAsRead($id)
    {
        $notification = Notification::find($id);
        if (!$notification) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khong tim thay thong bao',
            ], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Da danh dau da doc',
            'data' => $notification->fresh(),
        ]);
    }

    public function markAllAsRead($user_id)
    {
        $updated = Notification::where('user_id', $user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Da doc tat ca',
            'updated' => $updated,
        ]);
    }

    public function notifyApproversForStep(SubmissionStepContent $step): void
    {
        $step->loadMissing(['submission', 'department']);

        $approvers = User::where('department_id', $step->target_dept_id)
            ->whereHas('roles', fn($q) => $q->where('roles.id', 3))
            ->get();

        foreach ($approvers as $approver) {
            $this->notifyApprover($approver, $step);
        }
    }

    public function notifyPreApproversForStep(SubmissionStepContent $step): void
    {
        $step->loadMissing(['submission', 'department']);

        $staffUsers = User::where('department_id', $step->target_dept_id)
            ->whereHas('roles', fn($q) => $q->where('roles.id', 4))
            ->get();

        if ($staffUsers->isEmpty()) {
            $this->notifyApproversForStep($step);
            return;
        }

        foreach ($staffUsers as $staff) {
            $this->notifyPreApprover($staff, $step);
        }
    }

    public function notifyPreApprover(User $staff, SubmissionStepContent $step): void
    {
        $step->loadMissing(['submission', 'department']);

        $title = 'Yeu cau ky nhay moi';
        $body = 'To trinh "' . $step->submission->title . '" dang cho phong ban ban xem truoc.'
            . ($step->department ? ' Phong: ' . $step->department->dept_name . '.' : '');

        $this->createAndSend($staff, $title, $body, 'pending_pre_approval', [
            'id' => (string) $step->submission_id,
            'step_id' => (string) $step->id,
            'dept_id' => (string) $step->target_dept_id,
            'screen' => 'APPROVE_SCREEN',
        ]);
    }

    public function notifyApprover(User $approver, SubmissionStepContent $step): void
    {
        $step->loadMissing(['submission', 'department']);

        $title = 'Yeu cau phe duyet moi';
        $body = 'To trinh "' . $step->submission->title . '" dang cho ban xu ly.'
            . ($step->department ? ' Phong: ' . $step->department->dept_name . '.' : '');

        $this->createAndSend($approver, $title, $body, 'pending_approval', [
            'id' => (string) $step->submission_id,
            'step_id' => (string) $step->id,
            'dept_id' => (string) $step->target_dept_id,
            'screen' => 'APPROVE_SCREEN',
        ]);
    }

    public function notifyCreator(User $creator, $submission, string $action): void
    {
        $isApproved = $action === 'approved';
        $title = $isApproved ? 'To trinh da duoc duyet' : 'To trinh bi tu choi';
        $body = $isApproved
            ? "To trinh \"{$submission->title}\" da duoc tat ca phong ban phe duyet."
            : "To trinh \"{$submission->title}\" da bi tu choi boi mot phong ban.";

        $this->createAndSend($creator, $title, $body, $isApproved ? 'approved' : 'rejected', [
            'id' => (string) $submission->id,
            'screen' => 'DETAIL_SCREEN',
        ]);
    }

    public function notifyCreatorRevisionRequested(User $creator, $submission, User $staff, ?string $comment = null): void
    {
        $title = 'To trinh can chinh sua';
        $body = "To trinh \"{$submission->title}\" can chinh sua theo gop y cua {$staff->full_name}."
            . ($comment ? " Noi dung: {$comment}" : '');

        $this->createAndSend($creator, $title, $body, 'revision_requested', [
            'id' => (string) $submission->id,
            'screen' => 'DETAIL_SCREEN',
        ]);
    }

    private function createAndSend(User $user, string $title, string $body, string $type, array $data = [], ?string $overrideToken = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'submission_id' => $data['id'] ?? null,
            'title' => $title,
            'message' => $body,
            'type' => $type,
            'is_read' => false,
        ]);

        $token = $overrideToken ?: $user->fcm_token;
        if (!$token) {
            return $notification;
        }

        try {
            $payload = array_merge([
                'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                'type' => $type,
                'local_id' => (string) $notification->id,
            ], array_map(fn($value) => (string) $value, $data));

            $message = CloudMessage::fromArray([
                'token' => $token,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => $payload,
            ]);

            app('firebase.messaging')->send($message);
        } catch (\Throwable $e) {
            Log::warning('[FCM] Cannot send notification', [
                'user_id' => $user->id,
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }
}
