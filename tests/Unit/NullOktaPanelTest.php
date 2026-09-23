<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Support\NullOktaPanel;
use Illuminate\Http\Request;

it('describes a plain application login by default', function (): void {
    $panel = new NullOktaPanel;

    expect($panel->guard())->toBeNull()
        ->and($panel->loginUrl())->toBe(url('/login'))
        ->and($panel->homeUrl(new Request))->toBe(url('/'))
        ->and($panel->routePrefix())->toBe('')
        ->and($panel->routeName())->toBe('okta')
        ->and($panel->middleware())->toBe(['web'])
        ->and($panel->socialiteDriver())->toBe('okta');
});

it('reads its okta settings from the global config', function (): void {
    config([
        'okta.sso_logout' => false,
        'okta.require_verified_email' => true,
        'okta.identifier.column' => 'okta_id',
        'okta.identifier.update' => false,
    ]);

    $panel = new NullOktaPanel;

    expect($panel->ssoLogout())->toBeFalse()
        ->and($panel->requireVerifiedEmail())->toBeTrue()
        ->and($panel->identifierColumn())->toBe('okta_id')
        ->and($panel->identifierUpdate())->toBeFalse();
});

it('treats an empty identifier column as none', function (): void {
    config(['okta.identifier.column' => '']);

    expect((new NullOktaPanel)->identifierColumn())->toBeNull();
});

it('casts truthy non-boolean okta settings to a strict boolean', function (): void {
    // A truthy non-bool value (int 1) must come back as strictly true — proving the
    // (bool) cast rather than a passthrough of the raw config value.
    config([
        'okta.sso_logout' => 1,
        'okta.require_verified_email' => 1,
        'okta.identifier.update' => 1,
    ]);

    $panel = new NullOktaPanel;

    expect($panel->ssoLogout())->toBeTrue()
        ->and($panel->requireVerifiedEmail())->toBeTrue()
        ->and($panel->identifierUpdate())->toBeTrue();
});

it('casts falsy non-boolean okta settings to a strict boolean', function (): void {
    // A falsy non-bool value (int 0) must come back as strictly false.
    config([
        'okta.sso_logout' => 0,
        'okta.require_verified_email' => 0,
        'okta.identifier.update' => 0,
    ]);

    $panel = new NullOktaPanel;

    expect($panel->ssoLogout())->toBeFalse()
        ->and($panel->requireVerifiedEmail())->toBeFalse()
        ->and($panel->identifierUpdate())->toBeFalse();
});

it('defaults its boolean okta settings to true when the keys are absent', function (): void {
    // Drop the keys entirely so the method's own default (true) is what answers —
    // not the config file's value.
    $okta = (array) config('okta');
    unset($okta['sso_logout'], $okta['require_verified_email']);

    if (is_array($okta['identifier'] ?? null)) {
        unset($okta['identifier']['update']);
    }

    config(['okta' => $okta]);

    $panel = new NullOktaPanel;

    expect($panel->ssoLogout())->toBeTrue()
        ->and($panel->requireVerifiedEmail())->toBeTrue()
        ->and($panel->identifierUpdate())->toBeTrue();
});
