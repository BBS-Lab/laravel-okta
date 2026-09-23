<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Support;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Http\Controllers\OktaController;
use Illuminate\Support\Facades\Route;

/**
 * Registers the framework-agnostic Okta routes for a given panel. An adapter
 * calls this from the context where its panel path and middleware are known
 * (e.g. Nova::routes(), or a Filament panel's routes() callback), so the URIs,
 * middleware and route names all match the panel that owns them.
 */
class OktaRoutes
{
    public static function register(OktaPanel $panel): void
    {
        Route::prefix($panel->routePrefix())
            ->middleware($panel->middleware())
            ->name($panel->routeName().'.')
            ->group(function () {
                Route::get('okta/login', [OktaController::class, 'redirect'])->name('login');
                Route::get('okta/callback', [OktaController::class, 'callback'])->name('callback');
                Route::get('okta/logout', [OktaController::class, 'logout'])->name('logout');
                Route::get('okta/callback/logout', [OktaController::class, 'callbackLogout'])->name('callback.logout');
            });
    }
}
