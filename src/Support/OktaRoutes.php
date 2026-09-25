<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Support;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Enums\OktaRoute;
use BBSLab\LaravelOkta\Http\Controllers\OktaController;
use Illuminate\Support\Facades\Route;

/**
 * Registers the framework-agnostic Okta routes for a given panel. An adapter
 * calls this from the context where its panel path and middleware are known
 * (e.g. Nova::routes(), or a Filament panel's routes() callback), so the URIs,
 * middleware and route names all match the panel that owns them.
 *
 * Each route's URI comes from {@see OktaPanel::path()} (configurable, defaulting
 * to {@see OktaRoute::defaultPath()}); its name stays {@see OktaRoute::routeName()}
 * so route() callers are unaffected by a path change.
 */
class OktaRoutes
{
    public static function register(OktaPanel $panel): void
    {
        Route::prefix($panel->routePrefix())
            ->middleware($panel->middleware())
            ->name($panel->routeName().'.')
            ->group(function () use ($panel) {
                foreach (OktaRoute::cases() as $route) {
                    Route::get($panel->path($route), [OktaController::class, $route->controllerMethod()])
                        ->name($route->routeName());
                }
            });
    }
}
