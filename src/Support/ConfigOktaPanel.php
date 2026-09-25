<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Support;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Enums\OktaRoute;

/**
 * An OktaPanel whose Okta settings come from the global config('okta.*') and the
 * 'okta' Socialite driver — the single-panel case (the base default panel, and
 * the Nova adapter). Multi-panel adapters (Filament) implement OktaPanel directly
 * so each panel can carry its own settings. Subclasses supply only the routing +
 * guard methods.
 */
abstract class ConfigOktaPanel implements OktaPanel
{
    public function socialiteDriver(): string
    {
        return 'okta';
    }

    public function path(OktaRoute $route): string
    {
        $configured = config('okta.paths.'.$route->value);

        return is_string($configured) && $configured !== '' ? trim($configured, '/') : $route->defaultPath();
    }

    public function flashError(string $message): void
    {
        session()->flash('okta::error', $message);
    }

    public function ssoLogout(): bool
    {
        return (bool) config('okta.sso_logout', true);
    }

    public function requireVerifiedEmail(): bool
    {
        return (bool) config('okta.require_verified_email', true);
    }

    public function identifierColumn(): ?string
    {
        $column = config('okta.identifier.column');

        return is_string($column) && $column !== '' ? $column : null;
    }

    public function identifierUpdate(): bool
    {
        return (bool) config('okta.identifier.update', true);
    }
}
