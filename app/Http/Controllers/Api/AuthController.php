<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function getRequestToken()
    {
        $token = Str::random(40);
        Cache::put('req_token_' . $token, 'pending', now()->addMinutes(15));

        return response()->json([
            'success' => true,
            'expires_at' => now()->addMinutes(15)->toDateTimeString(),
            'request_token' => $token
        ]);
    }

    public function login(Request $request)
    {
        $fields = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'request_token' => 'required|string'
        ]);

        if (!Cache::has('req_token_' . $fields['request_token'])) {
            return response()->json(['message' => 'Token không tồn tại hoặc hết hạn'], 401);
        }

        $user = User::where('username', $fields['username'])->first();

       if (!$user || $fields['password'] !== $user->password) {
            return response()->json(['message' => 'Thông tin đăng nhập sai'], 401);
        }

        Cache::put('req_token_' . $fields['request_token'], 'validated', now()->addMinutes(15));
        Cache::put('user_for_token_' . $fields['request_token'], $user->id, now()->addMinutes(15));

        return response()->json(['success' => true]);
    }

    public function createSession(Request $request)
    {
        $requestToken = $request->input('request_token');

        if (Cache::get('req_token_' . $requestToken) !== 'validated') {
            return response()->json(['success' => false, 'message' => 'Token chưa xác thực'], 401);
        }

        $userId = Cache::get('user_for_token_' . $requestToken);
        $user = User::find($userId);

        $sessionId = $user->createToken('session_id')->plainTextToken;

        Cache::forget('req_token_' . $requestToken);
        Cache::forget('user_for_token_' . $requestToken);

        return response()->json([
            'success' => true,
            'session_id' => $sessionId
        ]);
    }

    public function getAccountDetails(Request $request)
    {
        $sessionId = $request->query('session_id');
    $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($sessionId);

    if (!$tokenModel) {
        return response()->json(['message' => 'Session không hợp lệ'], 401);
    }

    // Load kèm theo các roles của user
    $user = $tokenModel->tokenable()->with('roles')->first();

    return response()->json([
        'id' => $user->id,
        'username' => $user->username,
        'full_name' => $user->full_name,
        'email' => $user->email,
        'department_id' => $user->department_id,
        'roles' => $user->roles->map(function($role) {
            return [
                'id' => $role->id,
                'role_name' => $role->role_name,
                'description' => $role->description,
            ];
        })
    ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đã đăng xuất thành công'
        ]);
    }
}
