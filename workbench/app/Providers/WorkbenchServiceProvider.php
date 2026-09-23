<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use BBSLab\LaravelOkta\Support\NullOktaPanel;
use BBSLab\LaravelOkta\Support\OktaRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Models\User;

/**
 * Wires the base package into the workbench the way a plain (non-Nova, non-Filament)
 * consumer would: it mounts the Okta routes for the default panel and serves a
 * small login page carrying the Okta button. Also used by `composer serve` and the
 * browser / live e2e tests.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config([
            'auth.providers.users.model' => User::class,
            'okta.require_verified_email' => false,
            'services.okta' => [
                'client_id' => 'demo-client-id',
                'client_secret' => 'demo-client-secret',
                // Left unset: the redirect_uri is derived from the okta/callback route.
                'redirect' => env('OKTA_REDIRECT_URI'),
                'base_url' => 'https://example.okta.com',
            ],
        ]);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'workbench');

        // Mount the base okta/* routes for the default (plain-application) panel.
        OktaRoutes::register(new NullOktaPanel);

        // The login screen the default panel returns to (NullOktaPanel::loginUrl()).
        Route::middleware('web')
            ->get('/login', fn () => view('workbench::login'))
            ->name('login');
    }
}
