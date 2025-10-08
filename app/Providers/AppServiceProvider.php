<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\Contracts\OcrResultRepositoryInterface;
use App\Repositories\Admin\OcrResultRepository;
use App\Services\Shared\LogActivityService;
use App\Services\Shared\MediaService;
use App\Services\Shared\ImportService;
use App\Services\Shared\ExportService;
use App\Services\Shared\NotificationService;
use App\Services\Shared\ApiResponseService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind Repository Interface to Implementation
        $this->app->bind(OcrResultRepositoryInterface::class, OcrResultRepository::class);
        
        // Register Shared Services as Singletons
        $this->app->singleton(LogActivityService::class);
        $this->app->singleton(MediaService::class);
        $this->app->singleton(ImportService::class);
        $this->app->singleton(ExportService::class);
        $this->app->singleton(NotificationService::class);
        $this->app->singleton(ApiResponseService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
