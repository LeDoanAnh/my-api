<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Controllers\Api\CalendarController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\WorkflowController;
use App\Http\Controllers\Api\AssetSubmissionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ApproverController;
use App\Http\Controllers\Api\AssetTaskController;
use App\Http\Controllers\Api\BorrowController;
use App\Http\Controllers\Api\ManagerRecoveryController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;

Route::get('/authentication/token/new', [AuthController::class, 'getRequestToken']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/authentication/session/new', [AuthController::class, 'createSession']);
Route::get('/account', [AuthController::class, 'getAccountDetails']);
Route::post('/change-password', [AuthController::class, 'changePassword']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

Route::get('/ping', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toDateTimeString()]);
});
Route::get('/submissions/calendar', [CalendarController::class, 'getCalendarEvents']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/send-test-notification', [NotificationController::class, 'sendTest']);

    Route::prefix('notifications')->group(function () {
        Route::post('/save-fcm-token', [NotificationController::class, 'updateToken']);
    });
});

Route::prefix('v1')->group(function () {
        Route::get('/user/statistics', [SubmissionController::class, 'getStatistics']);
        Route::get('/submissions/recent', [SubmissionController::class, 'getRecentSubmissions']);
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::get('/submissions/{id}/detail', [SubmissionController::class, 'show']);
        Route::post('/submissions', [SubmissionController::class, 'store']);
        Route::get('/departments/resources', [DepartmentController::class, 'getDepartmentsWithResources']);
});
Route::prefix('actor')->group(function () {
        Route::get('list', [UserController::class, 'index']);
        Route::post('/create',[UserController::class, "store"]);
        Route::put('/update/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'deactivate']);
        Route::get('/detail/{id}',[UserController::class, "show"]);
});
Route::prefix('department')->group(function () {
        Route::get('list', [DepartmentController::class, 'index']);
        Route::get('/{id}/detail', [DepartmentController::class, 'show']);
        Route::post('/create',        [DepartmentController::class, 'store']);
        Route::put('/{id}',     [DepartmentController::class, 'update']);
        Route::delete('/{id}',  [DepartmentController::class, 'destroy']);
});
Route::prefix('location')->group(function () {
        Route::get('list', [LocationController::class, 'index']);
        Route::get('/detail/{id}', [LocationController::class, 'show']);
        Route::post('/create', [LocationController::class, 'store']);
        Route::put('/update/{id}', [LocationController::class, 'update']);
});

Route::prefix('asset')->group(function () {
        Route::get('list', [AssetController::class, 'index']);
        Route::get('/detail/{id}', [AssetController::class, 'show']);
        Route::post('/create', [AssetController::class, 'store']);
        Route::put('/update/{id}', [AssetController::class, 'update']);
        Route::get('/asset-tasks', [AssetTaskController::class, 'index']);
        Route::get('/asset-tasks/{submissionId}', [AssetTaskController::class, 'show']);
        Route::post('/asset-tasks/{submissionId}/handover', [AssetTaskController::class, 'handover']);
});
Route::prefix('asset-submissions')->group(function () {
        Route::get('/tasks', [AssetSubmissionController::class, 'getDepartmentTasks']);
});
Route::prefix('workflow')->group(function () {
        Route::get('/list', [WorkflowController::class, 'index']);
        Route::get('/detail/{id}', [WorkflowController::class, 'show']);
        Route::post('/store', [WorkflowController::class, 'store']);
        Route::put('/update/{id}', [WorkflowController::class, 'update']);
});

Route::prefix('notifications')->group(function () {
        Route::get('/list', [NotificationController::class, 'index']);
        Route::post('/read_all/{user_id}', [NotificationController::class, 'markAllAsRead']);
        Route::match(['post', 'patch'], '/{id}/read', [NotificationController::class, 'markAsRead']);
    });

Route::prefix('role')->group(function () {
    Route::get('list', [RoleController::class, 'index']);
});

Route::prefix('v1/approver')->group(function () {
    Route::get('/submission/{submissionId}', [ApproverController::class, 'show']);
    Route::post('/submission/{submissionId}/pre-sign', [ApproverController::class, 'preSign']);
    Route::post('/submission/{submissionId}/decide', [ApproverController::class, 'decide']);
});
Route::prefix('v1/borrow')->group(function () {
    Route::get('/list', [BorrowController::class, 'index']);           // GET danh sách
    Route::post('/{id}/confirm-receive', [BorrowController::class, 'confirmReceive']); // POST xác nhận nhận đồ
    Route::post('/{id}/return', [BorrowController::class, 'returnAsset']);             // POST trả đồ
});
Route::prefix('v1/manager')->group(function () {
    Route::get('/recovery/list', [ManagerRecoveryController::class, 'index']);
    Route::post('/{id}/confirm-recovery', [ManagerRecoveryController::class, 'confirmRecovery']);
    Route::post('/{id}/remind-return', [ManagerRecoveryController::class, 'remindReturn']);
});

Route::prefix('v1/history')->group(function () {
    Route::get('/borrow', [HistoryController::class, 'borrowHistory']);
    Route::get('/handover', [HistoryController::class, 'handoverHistory']);
});
Route::get('/v1/search', [SearchController::class, 'search']);
