<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubmissionController extends Controller
{
    /**
     * Lấy thống kê số lượng tờ trình của người dùng hiện tại
     * Phục vụ cho Widget: _buildPersonalStats trong Flutter
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Truy vấn dữ liệu từ bảng submissions
        $stats = [
            'total_submissions' => Submission::where('user_id', $userId)->count(),
            'pending_submissions' => Submission::where('user_id', $userId)
                                        ->where('status', 'pending')
                                        ->count(),
            'rejected_submissions' => Submission::where('user_id', $userId)
                                        ->where('status', 'rejected')
                                        ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Lấy danh sách tờ trình gần đây
     * Phục vụ cho Widget: _buildRecentActivityList trong Flutter
     */
    public function getRecentSubmissions(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Lấy 5 đơn mới nhất của người dùng
        $recentSubmissions = Submission::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title ?? 'Tờ trình không tiêu đề',
                    'status' => $item->status,
                    'status_label' => $this->getStatusLabel($item->status),
                    'time' => $item->created_at->diffForHumans(), // Ví dụ: "10 phút trước"
                    'created_at_formatted' => $item->created_at->format('H:i A'), // Ví dụ: "10:30 AM"
                ];
            });

        return response()->json($recentSubmissions);
    }

    /**
     * Hàm phụ trợ chuyển đổi status sang ngôn ngữ hiển thị
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending'  => 'Chờ phê duyệt',
            'approved' => 'Đã đồng ý',
            'rejected' => 'Bị từ chối',
        ];

        return $labels[$status] ?? $status;
    }
}
