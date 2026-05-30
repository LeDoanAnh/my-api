<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use App\Models\Submission;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ManagerRecoveryController extends Controller
{
    /**
     * GET danh sách tờ trình cần thu hồi
     * GET /v1/manager/recovery/list?handler_id={id}&search={search}
     */
    public function index(Request $request)
    {
        $handlerId = $request->query('handler_id');
        $search    = $request->query('search');

        if (!$handlerId) {
            return response()->json(['success' => false, 'message' => 'Thiếu handler_id'], 422);
        }

        $query = AssetRequest::where('handler_id', $handlerId)
            ->whereIn('status', ['pending', 'borrowed', 'returned'])
            ->with(['asset', 'submission', 'borrower']);

        if ($search) {
            $query->whereHas('submission', function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            })->orWhereHas('borrower', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%");
            });
        }

        $requests = $query->orderBy('created_at', 'desc')->get();
        $grouped  = $requests->groupBy('submission_id');
        $now      = Carbon::now();

        $data = $grouped->map(function ($items) use ($now) {
            $first      = $items->first();
            $submission = $first->submission;
            $borrower   = $first->borrower;

            $allReturned  = $items->every(fn($i) => $i->status === 'returned');
            $anyPending   = $items->contains(fn($i) => $i->status === 'pending');

            // Deadline lấy từ item borrowed gần nhất
            $earliestDeadline = $items
                ->where('status', 'borrowed')
                ->filter(fn($i) => $i->expected_return_date)
                ->sortBy('expected_return_date')
                ->first()?->expected_return_date;

            $isUrgent = $earliestDeadline &&
                Carbon::parse($earliestDeadline)->isAfter($now) &&
                Carbon::parse($earliestDeadline)->diffInHours($now) < 24;

            return [
                'submission_id'   => $submission->id,
                'submission_code' => "TT-" . str_pad($submission->id, 4, '0', STR_PAD_LEFT),
                'title'           => $submission->title,
                'borrower_name'   => $borrower?->full_name ?? 'N/A',
                'borrower_id'     => $borrower?->id,
                'is_returned'     => $allReturned,      // Người mượn đã nhấn trả
                'user_confirmed'  => !$anyPending,      // Người mượn đã xác nhận nhận
                'is_urgent'       => $isUrgent,
                'return_date'     => $earliestDeadline
                    ? Carbon::parse($earliestDeadline)->format('H:i - d/m/Y')
                    : null,
                'items' => $items->map(fn($ar) => [
                    'asset_request_id' => $ar->id,
                    'name'             => $ar->asset->asset_name,
                    'qty'              => 1,
                    'status'           => $ar->status,
                    'expected_return'  => $ar->expected_return_date
                        ? Carbon::parse($ar->expected_return_date)->format('H:i - d/m/Y')
                        : null,
                ]),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'total'   => $data->count(),
            'data'    => $data,
        ]);
    }

    /**
     * POST xác nhận đã thu hồi (returned → completed/closed)
     * POST /v1/manager/{submissionId}/confirm-recovery
     */
    public function confirmRecovery(Request $request, int $submissionId)
    {
        $request->validate([
            'handler_id' => 'required|exists:users,id',
        ]);

        $updated = AssetRequest::where('submission_id', $submissionId)
            ->where('handler_id', $request->handler_id)
            ->where('status', 'returned')
            ->update([
                'status'             => 'completed',
                'actual_return_date' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không có đồ nào cần xác nhận thu hồi',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận thu hồi thành công!',
        ]);
    }

    /**
     * POST nhắc trả đồ → gửi notification
     * POST /v1/manager/{submissionId}/remind-return
     */
    public function remindReturn(Request $request, int $submissionId)
    {
        $request->validate([
            'handler_id' => 'required|exists:users,id',
        ]);

        // Lấy borrower để gửi notification
        $assetRequest = AssetRequest::where('submission_id', $submissionId)
            ->where('handler_id', $request->handler_id)
            ->with(['borrower', 'submission'])
            ->first();

        if (!$assetRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy tờ trình',
            ], 404);
        }

        // Ghi notification vào DB
        \App\Models\Notification::create([
            'user_id'       => $assetRequest->borrower_id,
            'title'         => 'Nhắc trả đồ',
            'message'       => "Bạn sắp đến hạn trả đồ cho tờ trình \"{$assetRequest->submission->title}\". Vui lòng trả đúng hạn.",
            'is_read'       => false,
            'type'          => 'remind_return',
            'department_id' => null,
        ]);

        // TODO: Push FCM nếu cần
        // $fcmToken = $assetRequest->borrower->fcm_token;

        return response()->json([
            'success' => true,
            'message' => 'Đã gửi nhắc nhở đến người mượn!',
        ]);
    }
}
