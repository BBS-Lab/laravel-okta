<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta;

use BBSLab\LaravelForceTwoFactor\Facades\ForceTwoFactor;
use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Contracts\OktaUserResolver;
use BBSLab\LaravelOkta\Http\Controllers\OktaController;
use BBSLab\LaravelOkta\Resolvers\DefaultOktaUserResolver;
use BBSLab\LaravelOkta\Support\NullOktaPanel;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Okta\Provider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelOktaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('okta')
            ->hasConfigFile()
            ->hasTranslations()
            // Optional: publish with `--tag=okta-migrations` to add the users.okta_id
            // column for the stable-identifier matching mode.
            ->hasMigration('add_okta_id_to_users_table');
    }

    public function packageRegistered(): void
    {
        // Shared registry for the Okta lifecycle hooks (Okta::authorizeUserToLogin(), …).
        $this->app->singleton(Okta::class);

        // The default resolver matches an existing user and never creates one.
        // Consuming apps override it by binding their own OktaUserResolver.
        $this->app->bindIf(OktaUserResolver::class, DefaultOktaUserResolver::class);

        // Panel seam: a plain-application default. Nova/Filament adapters override
        // this binding with a panel-aware implementation before routes register.
        $this->app->bindIf(OktaPanel::class, NullOktaPanel::class);

        // Register the driver extension during the register phase: socialiteproviders/
        // manager dispatches SocialiteWasCalled from an app->booted() callback, so the
        // listener must be in place before the application finishes booting.
        $this->registerOktaSocialiteDriver();
    }

    protected function registerOktaSocialiteDriver(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('okta', Provider::class);
        });
    }

    public function packageBooted(): void
    {
        $this->registerSsoSecurityBypasses();
    }

    /**
     * When the bbs-lab force-two-factor / password-rotation packages are installed,
     * exempt Okta-authenticated users from BOTH forced 2FA enrolment and forced
     * password rotation: their second factor is enforced by Okta, and they have no
     * local password to rotate. Soft integrations — a no-op when a package is absent.
     */
    protected function registerSsoSecurityBypasses(): void
    {
        $authenticatedViaOkta = fn (Request $request, Authenticatable $user): bool => $request->hasSession()
            && $request->session()->get(OktaController::AUTHENTICATED_SESSION_KEY) === true;

        if (class_exists(ForceTwoFactor::class)) {
            ForceTwoFactor::bypass($authenticatedViaOkta);
        }

        if (class_exists(PasswordRotation::class)) {
            PasswordRotation::bypass($authenticatedViaOkta);
        }
    }
}
