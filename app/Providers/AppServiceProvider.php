<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. Fix lỗi độ dài key cho database
        Schema::defaultStringLength(191);

        // 2. Tự động chạy migrate khi ứng dụng chạy trên Render
        // Code này sẽ tự tạo bảng sessions, users... cho em
        if (config('app.env') === 'production') {
            try {
                Artisan::call('migrate', ['--force' => true]);
            } catch (\Exception $e) {
                // Nếu lỗi thì ghi vào log để mình kiểm tra, không làm sập app
                Log::error("Auto-migrate failed: " . $e->getMessage());
            }
        }
    }
}
