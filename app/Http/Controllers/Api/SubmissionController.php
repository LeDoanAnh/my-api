<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubmissionController extends Controller
{
    /**
     * Lấy thống kê số lượng tờ trình theo user_id
     */
    public function getStatistics(Request $request): JsonResponse
{
    $userId = $request->query('user_id'); // Cái này lấy từ URL thì giữ nguyên

    $stats = [
        // Đổi chỗ này thành creator_id
        'total_submissions' => Submission::where('creator_id', $userId)->count(),
        'pending_submissions' => Submission::where('creator_id', $userId)->where('status', 'pending')->count(),
        'rejected_submissions' => Submission::where('creator_id', $userId)->where('status', 'rejected')->count(),
    ];

    return response()->json($stats);
}

public function getRecentSubmissions(Request $request): JsonResponse
{
    $userId = $request->query('user_id');

    // Đổi chỗ này thành creator_id
    $recentSubmissions = Submission::where('creator_id', $userId)
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get()
        ->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'status' => $item->status,
                'status_label' => $this->getStatusLabel($item->status),
                'time' => $item->created_at ? $item->created_at->diffForHumans() : 'Vừa xong',
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
