<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Http\Controllers;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Contracts\OktaUserResolver;
use BBSLab\LaravelOkta\Okta;
use BBSLab\LaravelOkta\Support\Guard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use SocialiteProviders\Manager\OAuth2\User as OktaOAuth2User;
use SocialiteProviders\Okta\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class OktaController
{
    /**
     * Session key set once an Okta login succeeds — a signal other packages
     * (e.g. a forced-2FA enrolment guard) can read to skip forced enrolment,
     * since Okta already enforces MFA.
     */
    public const AUTHENTICATED_SESSION_KEY = 'okta_authenticated';

    /**
     * Session key holding the OIDC id_token, needed to build Okta's logout URL.
     */
    public const ID_TOKEN_SESSION_KEY = 'okta_id_token';

    public function __construct(
        protected OktaUserResolver $resolver,
        protected Okta $okta,
        protected OktaPanel $panel,
    ) {}

    /**
     * Send the browser to Okta to start the authorization-code flow.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        try {
            /** @var SymfonyRedirectResponse $response */
            $response = $this->oktaDriver()->redirect();

            return $response;
        } catch (Throwable $e) {
            // e.g. a panel points at a Socialite driver that is not registered.
            report($e);

            $this->panel->flashError((string) trans('okta::messages.sso_error'));

            return redirect($this->panel->loginUrl());
        }
    }

    /**
     * Handle Okta's callback: resolve the local user and log them in.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $oktaUser = $this->oktaDriver()->user();
        } catch (Throwable $e) {
            report($e);

            $this->panel->flashError((string) trans('okta::messages.sso_error'));

            return redirect($this->panel->loginUrl());
        }

        $user = $this->resolver->resolve($oktaUser);

        if ($user === null || ! $this->okta->authorizesUser($user, $oktaUser)) {
            $this->okta->runLoginDenied($user, $oktaUser);

            $this->panel->flashError((string) trans('okta::messages.not_allowed'));

            return redirect($this->panel->loginUrl());
        }

        $this->okta->runBeforeLogin($user, $oktaUser);

        Session::put(self::ID_TOKEN_SESSION_KEY, $this->idTokenFrom($oktaUser));
        Session::put(self::AUTHENTICATED_SESSION_KEY, true);

        Auth::guard(Guard::name())->login($user);
        $request->session()->regenerate();

        $this->okta->runAfterLogin($user, $oktaUser);

        return redirect()->intended($this->panel->homeUrl($request));
    }

    /**
     * Sign the user out locally and, when possible, out of Okta too (OIDC
     * end-session), returning them to the post-logout callback.
     */
    public function logout(Request $request): RedirectResponse
    {
        // Read the Okta end-session URL before the session is cleared below.
        $logoutUrl = $this->oktaLogoutUrl();

        // Always clear local auth, even if building the Okta logout URL failed —
        // otherwise a misconfigured driver would trap the user signed in.
        Auth::guard(Guard::name())->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect($logoutUrl ?? $this->panel->loginUrl());
    }

    /**
     * Okta's post-logout redirect target: bounce back to the login screen.
     */
    public function callbackLogout(): RedirectResponse
    {
        return redirect($this->panel->loginUrl());
    }

    /**
     * The Socialite driver for the panel, with its redirect_uri set to the panel's
     * own callback route. Because the callback is a route this package generates,
     * the redirect_uri never has to be configured (and, for Filament, each panel
     * gets its own). An explicit services.{driver}.redirect still wins — e.g. when
     * the public URL differs from APP_URL behind a reverse proxy.
     */
    protected function oktaDriver(): SocialiteProvider
    {
        $driver = Socialite::driver($this->panel->socialiteDriver());

        if ($driver instanceof AbstractProvider) {
            $driver->redirectUrl($this->redirectUrl());

            if ((bool) config('okta.pkce')) {
                $driver->enablePKCE();
            }
        }

        return $driver;
    }

    /**
     * The effective OIDC redirect_uri: an explicit services.{driver}.redirect when
     * set, otherwise the panel's callback route.
     */
    protected function redirectUrl(): string
    {
        $configured = config('services.'.$this->panel->socialiteDriver().'.redirect');

        return is_string($configured) && $configured !== ''
            ? $configured
            : route($this->panel->routeName().'.callback');
    }

    /**
     * Build Okta's OIDC end-session URL, or null when SSO logout is disabled,
     * there is no id_token, or the Okta driver is unavailable. Never throws, so
     * logout always completes locally.
     */
    protected function oktaLogoutUrl(): ?string
    {
        if (! $this->panel->ssoLogout()) {
            return null;
        }

        $idToken = Session::get(self::ID_TOKEN_SESSION_KEY);

        if (! is_string($idToken) || $idToken === '') {
            return null;
        }

        try {
            $driver = Socialite::driver($this->panel->socialiteDriver());

            return $driver instanceof Provider
                ? $driver->getLogoutUrl($idToken, route($this->panel->routeName().'.callback.logout'))
                : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Pull the OIDC id_token out of the Okta access-token response.
     */
    protected function idTokenFrom(User $oktaUser): ?string
    {
        if (! $oktaUser instanceof OktaOAuth2User) {
            return null;
        }

        $idToken = $oktaUser->accessTokenResponseBody['id_token'] ?? null;

        return is_string($idToken) ? $idToken : null;
    }
}
