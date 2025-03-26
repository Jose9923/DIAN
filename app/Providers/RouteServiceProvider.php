<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Carga de rutas para la API
            Route::middleware('api') // Middleware API
                ->prefix('api')      // Prefijo para rutas API
                ->group(base_path('routes/api.php')); // Archivo api.php

            // Carga de rutas web (si es necesario)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
