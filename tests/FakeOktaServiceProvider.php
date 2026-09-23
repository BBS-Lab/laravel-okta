<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Tests;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Support\OktaRoutes;
use Illuminate\Support\ServiceProvider;

/**
 * Stands in for an adapter's service provider in the base test harness: binds a
 * concrete OktaPanel and registers the Okta routes for it — exactly the two
 * things nova-okta / filament-okta do.
 */
class FakeOktaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Overrides the base bindIf(NullOktaPanel) — this provider is registered last.
        $this->app->bind(OktaPanel::class, FakeOktaPanel::class);
    }

    public function boot(): void
    {
        OktaRoutes::register($this->app->make(OktaPanel::class));
    }
}
