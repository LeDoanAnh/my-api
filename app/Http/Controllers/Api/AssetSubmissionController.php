<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Submission;
use Illuminate\Http\Request;

class AssetSubmissionController extends Controller
{
    public function getDepartmentTasks(Request $request)
    {
        // 1. Lấy ID phòng ban từ Query Parameter (?dept_id=...)
        $myDeptId = $request->query('dept_id');

        if (!$myDeptId) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng truyền dept_id trên URL.'
            ], 400);
        }

        // 2. Truy vấn tờ trình
        $submissions = Submission::query()
            ->where('status', 'approved')
            // Lọc các tờ trình có yêu cầu vật tư thuộc phòng ban này
            ->whereHas('assetRequests.asset', function ($q) use ($myDeptId) {
                $q->where('department_id', $myDeptId);
            })
            ->with([
                // Load vật tư kèm người đi giao (handler)
                'assetRequests' => function ($q) use ($myDeptId) {
                    $q->whereHas('asset', function ($assetQ) use ($myDeptId) {
                        $assetQ->where('department_id', $myDeptId);
                    })->with(['asset', 'handler']);
                },
                'user', // Thông tin người tạo tờ trình
                'approvalLogs.approver' // QUAN TRỌNG: Load log duyệt và người duyệt để tìm người ký
            ])
            ->get();

        // 3. Định dạng dữ liệu (Truyền thêm $myDeptId vào hàm transform)
        $formattedData = $this->transformData($submissions, $myDeptId);

        return response()->json([
            'success' => true,
            'dept_id_tested' => $myDeptId,
            'data' => $formattedData
        ]);
    }

    /**
     * Định dạng dữ liệu JSON trả về
     */
    private function transformData($submissions, $myDeptId)
    {
        return $submissions->map(function ($sub) use ($myDeptId) {
            $items = $sub->assetRequests;

            // Kiểm tra trạng thái bàn giao của các món đồ thuộc phòng ban này
            $isHandedOver = $items->isNotEmpty() && $items->every(fn($req) => $req->handler_id !== null);

            // LOGIC TÌM NGƯỜI KÝ CỦA PHÒNG BAN:
            // Tìm trong danh sách log, người nào có department_id trùng với phòng ban đang xem
            $deptApproverLog = $sub->approvalLogs->first(function ($log) use ($myDeptId) {
                return $log->approver &&
                       $log->approver->department_id == $myDeptId &&
                       $log->action == 'approved';
            });

            return [
                'id' => "VT-" . str_pad($sub->id, 4, '0', STR_PAD_LEFT),
                'submission_id' => $sub->id,
                'title' => $sub->title,
                'date' => $sub->created_at ? $sub->created_at->format('Y-m-d') : null,

                // Thông tin người tạo (Phòng ban yêu cầu)
                'creator' => $sub->user ? [
                    'id' => $sub->user->id,
                    'name' => $sub->user->full_name,
                    'dept_id' => $sub->user->department_id
                ] : null,

                // NGƯỜI KÝ CỦA PHÒNG BAN VẬT TƯ (Dựa trên log duyệt của phòng ban đó)
                'dept_approver' => $deptApproverLog ? [
                    'id' => $deptApproverLog->approver->id,
                    'name' => $deptApproverLog->approver->full_name,
                    'approved_at' => $deptApproverLog->created_at->format('d/m/Y H:i')
                ] : [
                    'name' => 'Chưa xác định người ký'
                ],

                'item_count' => $items->count(),
                'delivery_status' => $isHandedOver ? 'delivered' : 'pending',
                'delivery_status_text' => $isHandedOver ? 'Đã bàn giao đủ' : 'Chờ bàn giao',

                'items' => $items->map(function ($req) {
                    return [
                        'asset_request_id' => $req->id,
                        'asset_name' => $req->asset->asset_name ?? 'N/A',
                        'asset_code' => $req->asset->asset_code ?? 'N/A',
                        'handler' => $req->handler ? [
                            'id' => $req->handler->id,
                            'name' => $req->handler->full_name
                        ] : null,
                        'status' => $req->handler_id ? 'handed_over' : 'pending'
                    ];
                })
            ];
        });
    }
}
