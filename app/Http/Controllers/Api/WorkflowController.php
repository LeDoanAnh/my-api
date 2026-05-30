<?php

// app/Http/Controllers/Api/WorkflowController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubmissionCategory;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index()
    {
        $categories = SubmissionCategory::with([
            'approvalConfigs.targetRole',
            'approvalConfigs.targetDept'
        ])->get();

        $data = $categories->map(function ($category) {
            // Biến đổi các bản ghi approval_configs thành mảng tên các bước
            $steps = $category->approvalConfigs->map(function ($config) {
                $roleName = $config->targetRole->role_name ?? 'N/A';
                $deptName = $config->targetDept->dept_name ?? 'Cơ quan';
                return "$deptName";
            });

            return [
                "id" => $category->id,
                "name" => $category->category_name, // Map với name trong Flutter
                "description" => $category->description,
                "apply_to" => $category->description, // Tạm thời dùng description cho apply_to
                "steps_count" => $steps->count(),
                "status" => "active", // Bảng của bạn chưa có cột status, tạm để mặc định
                "steps" => $steps,
            ];
        });

        return response()->json([
            "success" => true,
            "data" => $data
        ]);
    }
    // app/Http/Controllers/Api/WorkflowController.php

public function show($id)
{
    // 1. Lấy thông tin loại tờ trình và các bước duyệt liên quan
    $category = SubmissionCategory::with([
        'approvalConfigs.targetRole',
        'approvalConfigs.targetDept'
    ])->find($id);

    if (!$category) {
        return response()->json([
            'success' => false,
            'message' => 'Không tìm thấy cấu hình luồng này'
        ], 404);
    }

    // 2. Định dạng dữ liệu các bước duyệt khớp với mảng _approvalSteps trong Flutter
    $steps = $category->approvalConfigs->map(function ($config) {
        $roleName = $config->targetRole->role_name ?? 'Chưa xác định';
        $deptName = $config->targetDept->dept_name ?? 'Cơ quan';

        return [
            "role" => $roleName,
            "desc" => $deptName, // Mô tả động
            "step_order" => $config->step_order,
            "dept_id" => $config->target_dept_id
        ];
    });

    // 3. Trả về cấu trúc JSON
    return response()->json([
        "success" => true,
        "data" => [
            "id" => $category->id,
            "category_name" => $category->category_name, // Chú ý lỗi chính tả 'catergory' trong DB
            "apply_for_dept" => "Tất cả đơn vị", // Có thể logic thêm dựa trên apply_for_dept_id
            "approval_steps" => $steps
        ]
    ]);
}
}
