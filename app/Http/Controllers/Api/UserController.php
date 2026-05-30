<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        try {
            // --- 1. Xử lý lấy danh sách người dùng (Kèm filter) ---
            $query = User::with(['department', 'roles']);

            // Tìm kiếm theo tên, username hoặc email
            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'LIKE', "%{$search}%")
                      ->orWhere('username', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%");
                });
            }

            // Lọc theo Trạng thái
            if ($request->has('status') && $request->status != 'Tất cả') {
                $query->where('status', $request->status);
            }

            // Lọc theo Phòng ban
            if ($request->has('department_id') && $request->department_id != 'all') {
                $query->where('department_id', $request->department_id);
            }

            $users = $query->orderBy('created_at', 'desc')->get();

            // --- 2. Xử lý lấy thống kê nhanh ---
            // Thống kê này thường tính trên toàn bộ DB, không phụ thuộc vào filter ở trên
            $statistics = [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'locked' => User::where('status', 'locked')->count(),
            ];

            // --- 3. Trả về kết quả gộp ---
            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu thành công',
                'statistics' => $statistics, // Trả về kèm thống kê
                'data' => $users            // Trả về danh sách user
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(CreateUserRequest $request) // Sửa 'request thành $request
    {
        DB::beginTransaction();
        try {
            // 1. Tạo bản ghi User mới
            $user = User::create([ // Sửa 'user thành $user
                'full_name'     => $request->full_name,     // Sửa các dấu nháy đơn trước request
                'email'         => $request->email,
                'username'      => $request->username,
                'password'      => Hash::make($request->password),
                'department_id' => $request->department_id,
                'status'        => $request->status,
            ]);

            // 2. Đồng bộ danh sách vai trò vào bảng trung gian role_user
            $user->roles()->sync($request->role_ids);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo tài khoản người dùng thành công!',
                'data'    => [
                    'id' => $user->id // Sửa 'user thành $user
                ]
            ], 201);

        } catch (Exception $e) { // Sửa 'e thành $e
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo tài khoản: ' . $e->getMessage() // Sửa 'e thành $e
            ], 500);
        }
    }
    public function show(int $id)
    {
        try {
            $user = User::with(['department', 'roles'])
                ->withCount('submissions as total_submissions')
                ->withCount(['notifications as unread_notifications' => function ($q) {
                    $q->where('is_read', false);
                }])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Lấy thông tin người dùng thành công',
                'data' => [
                    'id'                   => $user->id,
                    'username'             => $user->username,
                    'full_name'            => $user->full_name,
                    'email'                => $user->email,
                    'status'               => $user->status,
                    'department_id'        => $user->department_id,
                    'department_name'      => $user->department?->dept_name,
                    'roles'                => $user->roles->map(fn($r) => [
                        'id'          => $r->id,
                        'role_name'   => $r->role_name,
                        'description' => $r->description,
                    ]),
                    'total_submissions'    => $user->total_submissions,
                    'unread_notifications' => $user->unread_notifications,
                    'created_at'           => $user->created_at,
                    'updated_at'           => $user->updated_at,
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra: ' . $e->getMessage()
            ], 500);
        }
    }
}
