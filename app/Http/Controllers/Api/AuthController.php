<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

       if (!$user || !Hash::check($fields['password'], $user->password)) {
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

        $user = $tokenModel->tokenable()
            ->with(['roles', 'department'])
            ->withCount('submissions as total_submissions')
            ->withCount(['notifications as unread_notifications' => function ($q) {
                $q->where('is_read', false);
            }])
            ->first();

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'department_id' => $user->department_id,
            'department_name' => $user->department?->dept_name,
            'status' => $user->status,
            'signature_url' => $this->buildSignatureUrl($user->signature_path),
            'session_id' => $sessionId,
            'total_submissions' => $user->total_submissions,
            'unread_notifications' => $user->unread_notifications,
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
            'is_first_login' => (bool) $user->is_first_login,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'role_name' => $role->role_name,
                    'description' => $role->description,
                ];
            }),
        ]);
    }

    public function saveSignature(Request $request)
    {
        $request->validate([
            'signature' => ['required', 'image', 'max:5120'],
        ]);

        $token = $request->bearerToken();
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$tokenModel) {
            return response()->json(['message' => 'Session không hợp lệ'], 401);
        }

        $user = $tokenModel->tokenable;
        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }

        $signature = $request->file('signature');
        if (!$signature || !$signature->isValid()) {
            return response()->json(['message' => 'File chữ ký không hợp lệ'], 422);
        }

        $path = $signature->storeAs(
            'signatures',
            'user-' . $user->id . '.png',
            'public'
        );

        $user->update([
            'signature_path' => $path,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã lưu chữ ký thành công',
            'data' => [
                'signature_path' => $path,
                'signature_url' => $this->buildSignatureUrl($path),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đã đăng xuất thành công'
        ]);
    }

    public function changePassword(Request $request)
    {
        $fields = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $token = $request->bearerToken();
        $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($token);

        if (!$tokenModel) {
            return response()->json(['message' => 'Session không hợp lệ'], 401);
        }

        $user = $tokenModel->tokenable;

        if (!$user || !Hash::check($fields['current_password'], $user->password)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 422);
        }

        $user->password = $fields['password'];
        $user->is_first_login = false;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Đổi mật khẩu thành công',
            'is_first_login' => false,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $fields = $request->validate([
            'identifier' => 'required|string',
        ]);

        $identifier = $fields['identifier'];
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Không tìm thấy người dùng'], 404);
        }

        if (empty($user->email)) {
            return response()->json(['message' => 'Người dùng chưa có email'], 422);
        }

        $newPassword = Str::random(10);
        $user->password = $newPassword;
        $user->is_first_login = true;
        $user->save();

        Mail::raw(
            "Mật khẩu mới của bạn là: {$newPassword}\nVui lòng đăng nhập và đổi mật khẩu ngay.",
            function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Mật khẩu mới');
            }
        );

        return response()->json([
            'success' => true,
            'message' => 'Mật khẩu mới đã được gửi về email của bạn',
        ]);
    }

    private function buildSignatureUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return url('/storage/' . ltrim($path, '/'));
    }
}
