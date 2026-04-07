<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// Chỉ giữ lại Login vì Admin sẽ tạo tài khoản trên Web/DB trước
Route::post('/login', [AuthController::class, 'login']);

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toDateTimeString()]);
});

/*
|--------------------------------------------------------------------------
| Protected Routes (Cần Token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Lấy thông tin user (Để Flutter biết ai đang đăng nhập)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Đăng xuất
    Route::post('/logout', [AuthController::class, 'logout']);
});
Route::get('/init-db', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        return "Database migrated successfully!";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});

Route::get('/create-admin', function () {
    $user = \App\Models\User::create([
        'name' => 'Admin Test',
        'email' => 'admin@gmail.com',
        'password' => Hash::make('12345678'),
    ]);
    return "User created: " . $user->email;
});
