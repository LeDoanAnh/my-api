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
        \Illuminate\Support\Facades\Schema::defaultStringLength(191);
        // 1. Fix lỗi độ dài key cho database
    //     Schema::defaultStringLength(191);

    // // Chỉ chạy migrate nếu là môi trường production và không phải là chạy qua Terminal
    //     if (config('app.env') === 'production' && !$this->app->runningInConsole()) {
    //         try {
    //             // Thiết lập timeout ngắn để không làm treo tiến trình khởi động của PHP-FPM
    //             Artisan::call('migrate', ['--force' => true]);
    //         } catch (\Exception $e) {
    //             Log::error("Migration error: " . $e->getMessage());
    //         }
    //     }
    }
}
