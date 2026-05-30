<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Lấy danh sách các phòng ban (Thông tin cơ bản)
     */
    public function index(Request $request)
    {
        // Sử dụng eager loading cho 'parent' để lấy thông tin phòng ban cha nhanh chóng
        $query = Department::with('parent:id,dept_name');

        // Tìm kiếm theo tên phòng ban
        if ($request->has('search')) {
            $query->where('dept_name', 'like', '%' . $request->search . '%');
        }

        $departments = $query->get()->map(function ($dept) {
            return [
                'id' => $dept->id,
                'dept_name' => $dept->dept_name,
                'location_desc' => $dept->location_desc,
                'parent_name' => $dept->parent ? $dept->parent->dept_name : null,
                'has_children' => Department::where('parent_dept_id', $dept->id)->exists(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $departments
        ]);
    }
    public function getDepartmentsWithResources(): JsonResponse
    {
        try {
            // Eager load đồng thời cả assets và locations trực thuộc phòng ban
            $departments = Department::with([
                'assets' => function($query) {
                    // Bạn có thể lọc bớt các cột cần thiết để giảm tải dung lượng API
                    $query->select('id', 'department_id', 'asset_name', 'asset_code', 'status', 'unit');
                },
                'locations' => function($query) {
                    $query->select('id', 'department_id', 'location_name', 'capacity', 'status');
                }
            ])->withCount('users')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $departments
            ], 200);

        } catch (\Exception $e) {
            Log::error("Error fetching departments resources: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể tải danh sách tài nguyên phòng ban.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // app/Http/Controllers/Api/DepartmentController.php
    public function show(int $id): JsonResponse
    {
        try {
            $department = Department::with([
                // Thông tin đơn vị cấp trên
                'parent',

                // Danh sách địa điểm
                'locations:id,department_id,location_name,capacity,status',

                // Danh sách tài sản (lấy 5 cái đầu để preview)
                'assets' => function($query) {
                    $query->select('id', 'department_id', 'asset_name', 'asset_code', 'status', 'unit')
                        ->limit(5);
                },

                // Nhân sự thuộc phòng ban
                'users:id,department_id,full_name,email,status',

            ])
            ->withCount([
                'users',
                'assets',
                // Đếm tờ trình liên quan đến phòng ban này
                'submissionContents as submissions_count',
            ])
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $department,
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn vị.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dept_name'      => 'required|string|max:255|unique:departments,dept_name',
            'location_desc'  => 'nullable|string|max:500',
            'parent_dept_id' => 'nullable|integer|exists:departments,id',
        ], [
            'dept_name.required' => 'Tên phòng ban không được để trống.',
            'dept_name.unique'   => 'Tên phòng ban đã tồn tại trong hệ thống.',
            'parent_dept_id.exists' => 'Đơn vị cha không tồn tại.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $department = Department::create([
            'dept_name'      => $request->dept_name,
            'location_desc'  => $request->location_desc,
            'parent_dept_id' => $request->parent_dept_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tạo phòng ban thành công.',
            'data'    => $department,
        ], 201);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUT /api/departments/{id}
    // Cập nhật phòng ban
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'dept_name'      => 'sometimes|required|string|max:255|unique:departments,dept_name,' . $id,
            'location_desc'  => 'nullable|string|max:500',
            'parent_dept_id' => 'nullable|integer|exists:departments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $department->update($request->only(['dept_name', 'location_desc', 'parent_dept_id']));

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật phòng ban thành công.',
            'data'    => $department,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DELETE /api/departments/{id}
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(int $id): JsonResponse
    {
        $department = Department::findOrFail($id);

        // Không cho xoá nếu còn đơn vị con
        if ($department->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể xoá đơn vị đang có đơn vị con trực thuộc.',
            ], 409);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá phòng ban thành công.',
        ]);
    }
}
