<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Enums\OktaRoute;
use BBSLab\LaravelOkta\Support\OktaRoutes;
use BBSLab\LaravelOkta\Tests\FakeOktaPanel;

/** The URI a named route is mounted at (relative, e.g. 'admin/authorization-code/redirect'). */
function routeUri(string $name): string
{
    // Iterate the Route objects rather than getByName(): the collection's name
    // lookup is not refreshed for routes added after boot (the custom-panel test).
    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        if ($route->getName() === $name) {
            return $route->uri();
        }
    }

    return '';
}

it('mounts each okta route at its default authorization path', function (): void {
    // The suite's FakeOktaPanel mounts under the 'admin' prefix with routeName 'okta'.
    expect(routeUri('okta.login'))->toBe('admin/authorization-code/redirect')
        ->and(routeUri('okta.callback'))->toBe('admin/authorization-code/callback')
        ->and(routeUri('okta.logout'))->toBe('admin/authorization-code/logout')
        ->and(routeUri('okta.callback.logout'))->toBe('admin/authorization-code/callback/logout');
});

it('keeps the derived redirect_uri pointing at the (moved) callback route', function (): void {
    // The default redirect_uri is route('{panel}.callback'); it must now resolve to
    // the authorization-code/callback path, not the old okta/callback.
    config(['services.okta.redirect' => null]);

    $location = (string) $this->get(route('okta.login'))->headers->get('Location');

    expect(route('okta.callback'))->toEndWith('/admin/authorization-code/callback')
        ->and($location)->toContain('redirect_uri='.urlencode(route('okta.callback')));
});

it('registers each route at the path the panel returns', function (): void {
    // A panel can move any single path independently — proving register() reads
    // path() per route (this is the per-panel seam Filament configures).
    OktaRoutes::register(new class extends FakeOktaPanel
    {
        public function routeName(): string
        {
            return 'custom-okta';
        }

        public function path(OktaRoute $route): string
        {
            return $route === OktaRoute::Login ? 'sso/start' : parent::path($route);
        }
    });

    expect(routeUri('custom-okta.login'))->toBe('admin/sso/start')
        ->and(routeUri('custom-okta.callback'))->toBe('admin/authorization-code/callback')
        ->and(routeUri('custom-okta.callback.logout'))->toBe('admin/authorization-code/callback/logout');
});
