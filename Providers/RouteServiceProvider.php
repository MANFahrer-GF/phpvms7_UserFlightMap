<?php

namespace Modules\UserFlightMap\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\\UserFlightMap\\Http\\Controllers';

    public function map(): void
    {
        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->namespace($this->namespace.'\\Api')
            ->prefix('userflightmap')
            ->as('modules.userflightmap.')
            ->group(__DIR__.'/../Routes/api.php');
    }
}
