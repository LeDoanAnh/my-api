<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Đăng nhập
    public function login(Request $request)
    {
        $fields = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        // Tìm user theo email
        $user = User::where('email', $fields['email'])->first();

        // Kiểm tra mật khẩu
        if (!$user || !Hash::check($fields['password'], $user->password)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không chính xác'
            ], 401);
        }

        // Tạo Token cho thiết bị di động
        $token = $user->createToken('flutter_api_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user' => $user,
            'token' => $token
        ], 200);
    }

    // Đăng xuất
    public function logout(Request $request)
    {
        // Hủy bỏ token hiện tại đang dùng để gọi API này
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đã đăng xuất thành công'
        ]);
    }
}
