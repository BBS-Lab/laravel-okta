<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Tests;

use BBSLab\LaravelForceTwoFactor\LaravelForceTwoFactorServiceProvider;
use BBSLab\LaravelOkta\LaravelOktaServiceProvider;
use BBSLab\LaravelPasswordRotation\LaravelPasswordRotationServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as Orchestra;
use SocialiteProviders\Manager\ServiceProvider as SocialiteManagerServiceProvider;
use Workbench\App\Models\User;

abstract class TestCase extends Orchestra
{
    use WithWorkbench;

    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            // socialiteproviders/manager (extends Laravel's SocialiteServiceProvider)
            // is a deferred provider, so register it explicitly in the isolated
            // harness — it binds the Socialite factory and dispatches SocialiteWasCalled.
            SocialiteManagerServiceProvider::class,
            // The forced-2FA + password-rotation bases: bind the registries the
            // package's boot-time SSO bypass registrations land in.
            LaravelForceTwoFactorServiceProvider::class,
            LaravelPasswordRotationServiceProvider::class,
            LaravelOktaServiceProvider::class,
            // Registered last so its OktaPanel binding wins over the base default,
            // and its boot() mounts the Okta routes for that panel.
            FakeOktaServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Point auth — and therefore the default resolver — at the workbench user.
        $app['config']->set('auth.providers.users.model', User::class);

        // Most tests exercise matching/routing, not email verification; the
        // dedicated resolver tests flip this back on to assert that behaviour.
        $app['config']->set('okta.require_verified_email', false);

        // Fake Okta credentials so the Socialite okta driver can be built.
        $app['config']->set('services.okta', [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://app.test/admin/okta/callback',
            'base_url' => 'https://example.okta.com',
        ]);
    }
}
