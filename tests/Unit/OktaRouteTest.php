<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Enums\OktaRoute;

it('maps each route to its config key, default path, route name and controller method', function (
    OktaRoute $route,
    string $configKey,
    string $defaultPath,
    string $routeName,
    string $controllerMethod,
): void {
    expect($route->value)->toBe($configKey)
        ->and($route->defaultPath())->toBe($defaultPath)
        ->and($route->routeName())->toBe($routeName)
        ->and($route->controllerMethod())->toBe($controllerMethod);
})->with([
    // route, config key (okta.paths.*), default path, route-name suffix, controller method
    'login' => [OktaRoute::Login, 'login', 'authorization-code/redirect', 'login', 'redirect'],
    'callback' => [OktaRoute::Callback, 'callback', 'authorization-code/callback', 'callback', 'callback'],
    'logout' => [OktaRoute::Logout, 'logout', 'authorization-code/logout', 'logout', 'logout'],
    'callback logout' => [OktaRoute::CallbackLogout, 'callback_logout', 'authorization-code/callback/logout', 'callback.logout', 'callbackLogout'],
]);

it('covers every case in the mapping', function (): void {
    // Guards against a new case being added without a matching data row above.
    expect(OktaRoute::cases())->toHaveCount(4);
});

it('names the login route after the login path a consumer wires the button to', function (): void {
    // The login initiator sits at 'authorization-code/redirect' but keeps the stable
    // 'login' route-name suffix, so route('{panel}.login') is unaffected by the path.
    expect(OktaRoute::Login->defaultPath())->toBe('authorization-code/redirect')
        ->and(OktaRoute::Login->routeName())->toBe('login');
});
