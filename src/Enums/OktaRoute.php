<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Enums;

/**
 * The Okta routes a panel mounts, and the single source of truth for each one's
 * config key, default path and route-name suffix. The path (the part after the
 * panel's route prefix) is configurable — by config for Nova, per panel for
 * Filament — but always defaults to the value here.
 *
 * The backing value is the config key under `okta.paths.*` (and the setter name
 * on the Filament plugin). The route-name suffix stays stable across the
 * configurable path, so `route('{panel}.login')` etc. keep working regardless of
 * where the URI is mounted.
 */
enum OktaRoute: string
{
    case Login = 'login';
    case Callback = 'callback';
    case Logout = 'logout';
    case CallbackLogout = 'callback_logout';

    /**
     * The default URI (relative to the panel's route prefix, without surrounding
     * slashes) this route mounts at.
     *
     * Deliberate name<->URI inversion on the logout pair: the post-logout LANDING
     * (CallbackLogout — the value sent to Okta as the sign-out redirect URI) sits at
     * the short 'authorization-code/logout', while the RP-initiated INITIATOR
     * (Logout — OktaController::logout(), which starts the OIDC end-session) sits at
     * the nested 'authorization-code/logout/redirect'. This is intentional: the
     * route NAMES stay stable, so route('{panel}.logout') still *initiates* logout.
     * Do NOT "correct" the pairing — swapping them would move the sign-out redirect
     * URI (breaking every Okta whitelist) and flip what route('{panel}.logout') does.
     */
    public function defaultPath(): string
    {
        return match ($this) {
            self::Login => 'authorization-code/redirect',
            self::Callback => 'authorization-code/callback',
            self::Logout => 'authorization-code/logout/redirect',
            self::CallbackLogout => 'authorization-code/logout',
        };
    }

    /**
     * The route-name suffix appended after the panel's route-name prefix, e.g.
     * `{panel}.login` or `{panel}.callback.logout`. Stable across path changes.
     */
    public function routeName(): string
    {
        return $this === self::CallbackLogout ? 'callback.logout' : $this->value;
    }

    /**
     * The controller method this route dispatches to.
     */
    public function controllerMethod(): string
    {
        return match ($this) {
            self::Login => 'redirect',
            self::Callback => 'callback',
            self::Logout => 'logout',
            self::CallbackLogout => 'callbackLogout',
        };
    }
}
