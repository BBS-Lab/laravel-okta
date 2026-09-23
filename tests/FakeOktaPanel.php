<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Tests;

use BBSLab\LaravelOkta\Support\ConfigOktaPanel;
use Illuminate\Http\Request;

/**
 * Test panel standing in for a real adapter (nova-okta / filament-okta): mounts
 * the Okta routes under an "admin" prefix with the default guard. Its Okta
 * settings come from the global config (via ConfigOktaPanel), so tests drive
 * behaviour with config('okta.*') just like the single-panel adapters do.
 */
class FakeOktaPanel extends ConfigOktaPanel
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
        return url('/home');
    }

    public function routePrefix(): string
    {
        return 'admin';
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
