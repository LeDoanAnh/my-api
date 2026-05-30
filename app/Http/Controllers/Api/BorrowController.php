<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetRequest;
use Illuminate\Http\Request;
use Carbon\Carbon;

class BorrowController extends Controller
{
    /**
     * GET danh sách đồ đang mượn của user
     * GET /v1/borrow/list?user_id={userId}&search={search}
     */
    public function index(Request $request)
    {
        $userId = $request->query('user_id');
        $search = $request->query('search');

        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu user_id'], 422);
        }

        $query = AssetRequest::where('borrower_id', $userId)
            ->whereIn('status', ['pending', 'borrowed', 'returned'])
            ->with([
                'asset.department',
                'submission',
                'handler',
            ]);

        if ($search) {
            $query->whereHas('submission', function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        // Group theo submission_id
        $requests = $query->orderBy('created_at', 'desc')->get();
        $grouped  = $requests->groupBy('submission_id');

        $data = $grouped->map(function ($items) {
            $first      = $items->first();
            $submission = $first->submission;
            $handler    = $first->handler;
            $now        = Carbon::now();

            // Tính trạng thái chung của nhóm
            $allReturned  = $items->every(fn($i) => $i->status === 'returned');
            $anyPending   = $items->contains(fn($i) => $i->status === 'pending');
            $nearDeadline = $items
                ->where('status', 'borrowed')
                ->filter(fn($i) => $i->expected_return_date &&
                    Carbon::parse($i->expected_return_date)->diffInHours($now) < 24 &&
                    Carbon::parse($i->expected_return_date)->isAfter($now))
                ->isNotEmpty();

            return [
                'submission_id'  => $submission->id,
                'submission_code'=> "TT-" . str_pad($submission->id, 4, '0', STR_PAD_LEFT),
                'title'          => $submission->title,
                'is_returned'    => $allReturned,
                'user_confirmed' => !$anyPending,
                'is_urgent'      => $nearDeadline,
                'staff_name'     => $handler ? $handler->full_name : 'N/A',
                'items'          => $items->map(fn($ar) => [
                    'asset_request_id' => $ar->id,
                    'name'             => $ar->asset->asset_name,
                    'qty'              => 1,
                    'is_consumable'    => $ar->asset->type === 'consumable',
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
     * POST xác nhận đã nhận đủ đồ (pending → borrowed)
     * POST /v1/borrow/{submissionId}/confirm-receive
     */
    public function confirmReceive(Request $request, int $submissionId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $updated = AssetRequest::where('submission_id', $submissionId)
            ->where('borrower_id', $request->user_id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'borrowed',
                'borrow_date' => now(),
            ]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn cần xác nhận',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã xác nhận nhận đủ đồ!',
        ]);
    }

    /**
     * POST báo trả đồ (borrowed → returned)
     * POST /v1/borrow/{submissionId}/return
     */
    public function returnAsset(Request $request, int $submissionId)
    {
        $request->validate([
            'user_id'          => 'required|exists:users,id',
            'asset_request_ids'=> 'required|array',
            'asset_request_ids.*' => 'exists:asset_requests,id',
        ]);

        AssetRequest::whereIn('id', $request->asset_request_ids)
            ->where('submission_id', $submissionId)
            ->where('borrower_id', $request->user_id)
            ->where('status', 'borrowed')
            ->update([
                'status'             => 'returned',
                'actual_return_date' => now(),
            ]);

        // Kiểm tra tất cả đã trả chưa
        $total    = AssetRequest::where('submission_id', $submissionId)
            ->where('borrower_id', $request->user_id)
            ->whereIn('status', ['borrowed', 'returned'])
            ->count();

        $returned = AssetRequest::where('submission_id', $submissionId)
            ->where('borrower_id', $request->user_id)
            ->where('status', 'returned')
            ->count();

        $allReturned = $total > 0 && $returned >= $total;

        return response()->json([
            'success'      => true,
            'message'      => $allReturned
                ? 'Đã báo trả toàn bộ! Chờ phòng ban xác nhận.'
                : 'Đã cập nhật trạng thái trả đồ.',
            'all_returned' => $allReturned,
        ]);
    }
}
