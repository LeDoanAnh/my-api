<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubmissionController extends Controller
{
    /**
     * API: /api/v1/user/statistics?user_id=...
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        // Truy vấn theo creator_id khớp với ảnh database của bạn
        $stats = [
            'total_submissions'    => Submission::where('creator_id', $userId)->count(),
            'pending_submissions'  => Submission::where('creator_id', $userId)->where('status', 'pending')->count(),
            'rejected_submissions' => Submission::where('creator_id', $userId)->where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * API: /api/v1/submissions/recent?user_id=...
     */
    public function getRecentSubmissions(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        $recentSubmissions = Submission::where('creator_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id'           => $item->id,
                    'title'        => $item->title ?? 'Tờ trình không tiêu đề',
                    'status'       => $item->status,
                    'status_label' => $this->getStatusLabel($item->status),
                    // Kiểm tra null tránh lỗi 500 nếu cột created_at trống
                    'time'         => $item->created_at ? $item->created_at->diffForHumans() : 'Vừa xong',
                    'created_at_formatted' => $item->created_at ? $item->created_at->format('H:i A') : '--:--',
                ];
            });

        return response()->json($recentSubmissions);
    }

    private function getStatusLabel(?string $status): string
    {
        $labels = [
            'pending'  => 'Chờ phê duyệt',
            'approved' => 'Đã đồng ý',
            'rejected' => 'Bị từ chối',
        ];

        return $labels[$status] ?? 'Không xác định';
    }
}
