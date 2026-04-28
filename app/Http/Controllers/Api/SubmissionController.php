<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubmissionController extends Controller
{
    /**
     * Lấy thống kê số lượng tờ trình theo user_id truyền từ URL
     * API: /api/v1/user/statistics?user_id=...
     */
    public function getStatistics(Request $request): JsonResponse
    {
        // Lấy user_id từ query string (?user_id=...) giống cách lấy session_id
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        // Truy vấn dựa trên cột creator_id trong database của bạn
        $stats = [
            'total_submissions' => Submission::where('creator_id', $userId)->count(),
            'pending_submissions' => Submission::where('creator_id', $userId)->where('status', 'pending')->count(),
            'rejected_submissions' => Submission::where('creator_id', $userId)->where('status', 'rejected')->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Lấy danh sách 5 tờ trình gần nhất theo user_id
     * API: /api/v1/submissions/recent?user_id=...
     */
    public function getRecentSubmissions(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['message' => 'Missing user_id parameter'], 400);
        }

        // Lấy 5 đơn mới nhất. Sửa 'user_id' thành 'creator_id' cho đúng database thực tế
        $recentSubmissions = Submission::where('creator_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title ?? 'Tờ trình không tiêu đề',
                    'status' => $item->status,
                    'status_label' => $this->getStatusLabel($item->status),
                    // diffForHumans() sẽ trả về "10 minutes ago" hoặc "10 phút trước"
                    'time' => $item->created_at->diffForHumans(),
                    'created_at_formatted' => $item->created_at->format('H:i A'),
                ];
            });

        return response()->json($recentSubmissions);
    }

    /**
     * Hàm phụ trợ chuyển đổi status sang tiếng Việt
     */
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
