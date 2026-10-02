<?php

namespace Modules\UserFlightMap\Providers;

use App\Contracts\Modules\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'userflightmap');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'userflightmap');
    }
}
