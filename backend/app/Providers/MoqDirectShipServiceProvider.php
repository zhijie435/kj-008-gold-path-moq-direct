<?php

namespace App\Providers;

use App\Services\MoqDirectShipService;
use Illuminate\Support\ServiceProvider;

class MoqDirectShipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MoqDirectShipService::class);
    }

    public function boot(): void
    {
        //
    }
}
