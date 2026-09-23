<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Tests;

use BBSLab\LaravelOkta\LaravelOktaServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use SocialiteProviders\Manager\ServiceProvider as SocialiteManagerServiceProvider;
use Workbench\App\Providers\WorkbenchServiceProvider;

/**
 * Harness for the browser / live e2e tests: boots the workbench the way a plain
 * consumer would (the default panel's okta/* routes plus a login page), rather
 * than the FakeOktaPanel used by the unit/feature suite.
 */
abstract class BrowserTestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SocialiteManagerServiceProvider::class,
            LaravelOktaServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }
}
