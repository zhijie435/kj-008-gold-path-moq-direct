<?php

namespace App\Providers;

use App\Services\MoqDirectShipService;
use Illuminate\Support\ServiceProvider;

class MoqDirectShipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MoqDirectShipService::class, function ($app) {
            return new MoqDirectShipService();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/moq-direct-ship.php' => config_path('moq-direct-ship.php'),
            ], 'moq-direct-ship-config');
        }
    }
}
