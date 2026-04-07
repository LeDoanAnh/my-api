<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/ping', function () {
    return response()->json([
        'status' => 'ok',
        'time' => now()->toDateTimeString()
    ]);
});
Route::middleware('auth:sanctum')->group(function () {

    // Lấy thông tin user đang đăng nhập
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Đăng xuất (Hủy token)
    Route::post('/logout', [AuthController::class, 'logout']);
});
