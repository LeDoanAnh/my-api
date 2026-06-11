<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalConfig;
use App\Models\SubmissionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkflowController extends Controller
{
    public function index()
    {
        $categories = SubmissionCategory::with([
            'approvalConfigs.targetRole',
            'approvalConfigs.targetDept',
            'applyForDept',
        ])->latest('id')->get();

        $data = $categories->map(function ($category) {
            $steps = $category->approvalConfigs->map(function ($config) {
                return $config->targetDept->dept_name ?? 'Đơn vị người gửi';
            })->values();

            return [
                'id' => $category->id,
                'name' => $category->category_name,
                'description' => $category->description,
                'apply_to' => $category->applyForDept->dept_name ?? 'Tất cả đơn vị',
                'steps_count' => $steps->count(),
                'status' => $category->status ?? 'active',
                'steps' => $steps,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateWorkflow($request);

        $category = DB::transaction(function () use ($validated) {
            $category = SubmissionCategory::create([
                'category_name' => $validated['category_name'],
                'description' => $validated['description'] ?? null,
                'apply_for_dept_id' => $validated['apply_for_dept_id'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $this->syncSteps($category, $validated['workflow_steps']);

            return $category;
        });

        return response()->json([
            'success' => true,
            'message' => 'Tạo luồng duyệt thành công',
            'data' => ['id' => $category->id],
        ], 201);
    }

    public function show($id)
    {
        $category = SubmissionCategory::with([
            'approvalConfigs.targetRole',
            'approvalConfigs.targetDept',
            'applyForDept',
        ])->find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cấu hình luồng này',
            ], 404);
        }

        $steps = $category->approvalConfigs->map(function ($config) {
            return [
                'role' => $config->targetRole->role_name ?? 'Chưa xác định',
                'desc' => $config->targetDept->dept_name ?? 'Đơn vị người gửi',
                'step_order' => $config->step_order,
                'dept_id' => $config->target_dept_id,
                'target_role_id' => $config->target_role_id,
                'target_dept_name' => $config->targetDept->dept_name ?? 'Đơn vị người gửi (Tự động)',
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'category_name' => $category->category_name,
                'description' => $category->description,
                'apply_for_dept_id' => $category->apply_for_dept_id,
                'apply_for_dept' => $category->applyForDept->dept_name ?? 'Tất cả đơn vị',
                'status' => $category->status ?? 'active',
                'approval_steps' => $steps,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $category = SubmissionCategory::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy cấu hình luồng này',
            ], 404);
        }

        $validated = $this->validateWorkflow($request);

        DB::transaction(function () use ($category, $validated) {
            $category->update([
                'category_name' => $validated['category_name'],
                'description' => $validated['description'] ?? null,
                'apply_for_dept_id' => $validated['apply_for_dept_id'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            $category->approvalConfigs()->delete();
            $this->syncSteps($category, $validated['workflow_steps']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật luồng duyệt thành công',
        ]);
    }

    private function validateWorkflow(Request $request): array
    {
        return $request->validate([
            'category_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'apply_for_dept_id' => ['nullable', 'exists:departments,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'workflow_steps' => ['required', 'array', 'min:1'],
            'workflow_steps.*.step_order' => ['required', 'integer', 'min:1'],
            'workflow_steps.*.target_role_id' => ['required', 'exists:roles,id'],
            'workflow_steps.*.target_dept_id' => ['nullable', 'exists:departments,id'],
        ]);
    }

    private function syncSteps(SubmissionCategory $category, array $steps): void
    {
        foreach (array_values($steps) as $index => $step) {
            ApprovalConfig::create([
                'category_id' => $category->id,
                'step_order' => $index + 1,
                'target_role_id' => $step['target_role_id'],
                'target_dept_id' => $step['target_dept_id'] ?? null,
                'apply_for_dept_id' => $category->apply_for_dept_id,
            ]);
        }
    }
}
