<?php

namespace App\Providers;

use App\Models\ParametroAgua;
use App\Models\ParametroAmbiente;
use App\Observers\ParametroAguaObserver;
use App\Observers\ParametroAmbienteObserver;
use Illuminate\Support\ServiceProvider;

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
        ParametroAgua::observe(ParametroAguaObserver::class);
        ParametroAmbiente::observe(ParametroAmbienteObserver::class);
    }
}
