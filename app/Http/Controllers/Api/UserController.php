<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User; // Đảm bảo đã có Model User
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        // Lấy toàn bộ danh sách user từ database
        $users = User::all();

        // Trả về định dạng JSON cho Flutter dễ đọc
        return response()->json([
            'success' => true,
            'message' => 'Danh sách người dùng',
            'data' => $users
        ], 200);
    }
}
