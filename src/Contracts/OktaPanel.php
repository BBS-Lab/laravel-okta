<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Contracts;

use Illuminate\Http\Request;

/**
 * The single seam between the framework-agnostic Okta flow and the admin panel
 * it protects. An adapter (nova-okta, filament-okta) binds an implementation so
 * the base controller and route registrar know which guard to use, where the
 * login screen lives, where to land after login, and which Okta settings apply —
 * without ever referencing Nova or Filament.
 *
 * Everything panel-specific lives here (not in global config) so several panels
 * can run side by side with different Okta configurations: a Filament plugin is
 * activated per panel, and each panel resolves its own OktaPanel per request.
 */
interface OktaPanel
{
    /**
     * The auth guard the panel authenticates against, or null to fall back to
     * the application default (config('auth.defaults.guard')).
     */
    public function guard(): ?string;

    /**
     * Absolute URL of the panel's login screen — where the flow returns the
     * browser on a denied or failed callback, and after logout.
     */
    public function loginUrl(): string;

    /**
     * Absolute URL to land on after a successful login. The controller wraps it
     * in redirect()->intended(), so a pending intended URL still wins.
     */
    public function homeUrl(Request $request): string;

    /**
     * URI prefix the Okta routes mount under (e.g. the panel path), or '' for
     * the application root.
     */
    public function routePrefix(): string;

    /**
     * Route-name prefix for the Okta routes (e.g. 'nova-okta', 'filament-okta').
     * Produces '{name}.login', '{name}.callback', '{name}.logout',
     * '{name}.callback.logout'.
     */
    public function routeName(): string;

    /**
     * Middleware applied to the Okta routes (e.g. ['web'] plus the panel's own).
     *
     * @return array<int, string>
     */
    public function middleware(): array;

    /**
     * The Socialite driver name this panel logs in through. Defaults to 'okta'
     * (credentials from config('services.okta')); a panel with its own Okta
     * application returns a distinct registered driver name.
     */
    public function socialiteDriver(): string;

    /**
     * Surface a login error on the panel's login screen — e.g. flash it to the
     * session for a Blade screen to render, or send a native panel notification.
     */
    public function flashError(string $message): void;

    /**
     * Whether logout also ends the Okta session (OIDC end-session / single
     * sign-out). False clears only the local session.
     */
    public function ssoLogout(): bool;

    /**
     * Whether the default resolver rejects a login whose Okta email is not
     * verified (OIDC "email_verified" claim).
     */
    public function requireVerifiedEmail(): bool;

    /**
     * The users-table column holding Okta's stable subject id, matched before
     * email, or null to match by email only.
     */
    public function identifierColumn(): ?string;

    /**
     * Whether the identifier column is backfilled on the first verified-email match.
     */
    public function identifierUpdate(): bool;
}
