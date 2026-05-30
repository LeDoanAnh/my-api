<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Lấy ra danh sách tất cả các Role trong Database
            $roles = Role::select('id', 'role_name', 'description')->get();

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách vai trò thành công',
                'data' => $roles
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ], 500);
        }
    }
}
