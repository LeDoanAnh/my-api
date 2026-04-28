<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Hash;

Route::get('/authentication/token/new', [AuthController::class, 'getRequestToken']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/authentication/session/new', [AuthController::class, 'createSession']);
Route::get('/account', [AuthController::class, 'getAccountDetails']);

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toDateTimeString()]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('v1')->group(function () {
        Route::get('/user/statistics', [SubmissionController::class, 'getStatistics']);
        Route::get('/submissions/recent', [SubmissionController::class, 'getRecentSubmissions']);
});
});

Route::get('/create-admin', function () {
    $user = \App\Models\User::create([
        'username' => 'admin_test',
        'full_name' => 'Admin Test',
        'email' => 'admin@gmail.com',
        'password' => Hash::make('12345678'),
        'status' => 'active',
        'role_id' => 1,
    ]);
    return "User created: " . $user->username;
});
