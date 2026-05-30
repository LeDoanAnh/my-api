<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Contract\Messaging;
use App\Models\Notification;

class NotificationController extends Controller
{
    // 1. Dùng Dependency Injection để tránh lỗi app('firebase.messaging') ở một số môi trường
    protected $messaging;

    // public function __construct(Messaging $messaging)
    // {
    //     $this->messaging = $messaging;
    // }

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
                'message' => 'Đã cập nhật Token thành công',
                'current_token' => $user->fcm_token
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi Server: ' . $e->getMessage()], 500);
        }
    }

    public function sendTest(Request $request)
    {
        if (!$request->has('token')) {
            return response()->json(['message' => 'Thiếu Device Token!'], 400);
        }

        try {
            $user = $request->user();
            $deviceToken = $request->token;
            $submissionId = '12345';
            $title = 'Tờ trình đã được phê duyệt';
            $body = 'Yêu cầu mượn thiết bị của bạn đã được Admin phê duyệt.';
            $type = 'success';

            $localNoti = Notification::create([
                'user_id' => $user->id,
                'title'   => $title,
                'message' => $body,
                'type'    => $type,
                'is_read' => false,
            ]);

            $message = CloudMessage::fromArray([
                'token' => $deviceToken,
                'notification' => ['title' => $title, 'body' => $body],
                'data' => [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'id'           => (string)$submissionId,
                    'type'         => $type,
                    'local_id'     => (string)$localNoti->id,
                    'screen'       => 'DETAIL_SCREEN',
                ],
            ]);

            $this->messaging->send($message);

            return response()->json(['status' => 'success', 'message' => 'Đã gửi thành công!']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            \Carbon\Carbon::setLocale('vi');
            // Lấy user_id từ query string (?user_id=3)
            $userId = $request->query('user_id');

            // Kiểm tra nếu không truyền user_id thì báo lỗi 400 luôn
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vui lòng cung cấp user_id trên URL (ví dụ: ?user_id=3)'
                ], 400);
            }

            // Truy vấn dữ liệu dựa trên user_id đơn thuần
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
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi SQL: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->update(['is_read' => true]);
        return response()->json(['message' => 'Đã đánh dấu đọc']);
    }

    public function markAllAsRead($user_id)
    {
        Notification::where('user_id', $user_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Đã đọc tất cả']);
    }

    public function notifyApprover($approver, $submission)
    {
        $title = 'Yêu cầu phê duyệt mới';
        $body = 'Tờ trình: ' . $submission->title . ' đang chờ bạn xử lý.';
        $type = 'warning';

        Notification::create([
            'user_id' => $approver->id,
            'submission_id' => $submission->id,
            'title' => $title,
            'message' => $body,
            'type' => $type,
            'is_read' => false,
        ]);

        $message = CloudMessage::fromArray([
            'token' => $approver->fcm_token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => [
                'id' => (string)$submission->id,
                'screen' => 'APPROVE_SCREEN',
                'type' => $type
            ],
        ]);
        $this->messaging->send($message);
    }
    // Thêm vào NotificationController
    public function notifyCreator($creator, $submission, $action)
    {
        $isApproved = $action === 'approved';
        $title   = $isApproved ? 'Tờ trình đã được duyệt' : 'Tờ trình bị từ chối';
        $body    = $isApproved
            ? "Tờ trình \"{$submission->title}\" đã được tất cả phòng ban phê duyệt."
            : "Tờ trình \"{$submission->title}\" đã bị từ chối bởi một phòng ban.";

        Notification::create([
            'user_id'  => $creator->id,
            'title'    => $title,
            'message'  => $body,
            'type'     => $isApproved ? 'approved' : 'rejected',
            'is_read'  => false,
        ]);

        if ($creator->fcm_token) {
            $message = CloudMessage::fromArray([
                'token'        => $creator->fcm_token,
                'notification' => ['title' => $title, 'body' => $body],
                'data'         => [
                    'id'     => (string)$submission->id,
                    'screen' => 'DETAIL_SCREEN',
                    'type'   => $isApproved ? 'approved' : 'rejected',
                ],
            ]);
            $this->messaging->send($message);
        }
    }
}
