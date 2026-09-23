<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Support;

use Illuminate\Http\Request;

/**
 * Default OktaPanel used when the base package is installed on its own (no Nova
 * or Filament adapter). It targets a plain application login at '/login', mounts
 * the Okta routes at the root under the 'web' middleware, and takes its Okta
 * settings from the global config (via ConfigOktaPanel). Adapters override this
 * binding with a panel-aware implementation.
 */
class NullOktaPanel extends ConfigOktaPanel
{
    public function guard(): ?string
    {
        return null;
    }

    public function loginUrl(): string
    {
        return url('/login');
    }

    public function homeUrl(Request $request): string
    {
        return url('/');
    }

    public function routePrefix(): string
    {
        return '';
    }

    public function routeName(): string
    {
        return 'okta';
    }

    public function middleware(): array
    {
        return ['web'];
    }
}
