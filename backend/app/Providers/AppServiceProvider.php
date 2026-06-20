<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->registerMoqDirectShipGates();
    }

    protected function registerMoqDirectShipGates(): void
    {
        Gate::before(function (User $user) {
            return $user->role === User::ROLE_ADMIN ? true : null;
        });

        foreach (['moq-orders', 'shipments', 'products', 'suppliers'] as $resource) {
            Gate::define("view-{$resource}", fn (User $user) => true);
            Gate::define("manage-{$resource}", fn (User $user) => $user->role === User::ROLE_OPERATOR);
            Gate::define("delete-{$resource}", fn (User $user) => false);
        }

        Gate::define('finance-moq-orders', fn (User $user) => false);
    }
}
