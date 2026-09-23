<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Tests;

use Illuminate\Foundation\Application;

/**
 * The base package installed on its own — no adapter (FakeOktaServiceProvider is
 * dropped). This exercises the default bindings the base provider registers when
 * neither the Nova nor the Filament adapter overrides them.
 */
abstract class BaseOnlyTestCase extends TestCase
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return array_values(array_filter(
            parent::getPackageProviders($app),
            fn (string $provider): bool => $provider !== FakeOktaServiceProvider::class,
        ));
    }
}
