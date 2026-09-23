<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Facades\Okta;
use BBSLab\LaravelOkta\Okta as OktaManager;
use Laravel\Socialite\Contracts\User as OktaUserContract;
use Workbench\App\Models\User;

function okta(): OktaManager
{
    return app(OktaManager::class);
}

function fakeOktaUser(): OktaUserContract
{
    return Mockery::mock(OktaUserContract::class);
}

it('authorizes by default when no callbacks are registered', function (): void {
    expect(okta()->authorizesUser(new User, fakeOktaUser()))->toBeTrue();
});

it('denies when a registered authorize callback returns false', function (): void {
    Okta::authorizeUserToLogin(fn (): bool => false);

    expect(okta()->authorizesUser(new User, fakeOktaUser()))->toBeFalse();
});

it('requires every authorize callback to pass', function (): void {
    Okta::authorizeUserToLogin(fn (): bool => true);
    Okta::authorizeUserToLogin(fn (): bool => false);

    expect(okta()->authorizesUser(new User, fakeOktaUser()))->toBeFalse();
});

it('passes the local user and the okta user to the authorize callback', function (): void {
    $user = new User(['email' => 'cb@b.c']);
    $seen = [];

    Okta::authorizeUserToLogin(function ($localUser, $oktaUser) use (&$seen): bool {
        $seen = [$localUser, $oktaUser];

        return true;
    });

    $okta = fakeOktaUser();
    okta()->authorizesUser($user, $okta);

    expect($seen[0])->toBe($user)->and($seen[1])->toBe($okta);
});

it('runs beforeLogin callbacks with the user and okta user', function (): void {
    $user = new User;
    $okta = fakeOktaUser();
    $seen = [];

    Okta::beforeLogin(function ($u, $o) use (&$seen): void {
        $seen = [$u, $o];
    });

    okta()->runBeforeLogin($user, $okta);

    expect($seen)->toBe([$user, $okta]);
});

it('runs afterLogin callbacks with the user and okta user', function (): void {
    $user = new User;
    $okta = fakeOktaUser();
    $seen = [];

    Okta::afterLogin(function ($u, $o) use (&$seen): void {
        $seen = [$u, $o];
    });

    okta()->runAfterLogin($user, $okta);

    expect($seen)->toBe([$user, $okta]);
});

it('runs onLoginDenied callbacks, with a null user when unresolved', function (): void {
    $okta = fakeOktaUser();
    $seen = 'unset';

    Okta::onLoginDenied(function ($u, $o) use (&$seen): void {
        $seen = $u;
    });

    okta()->runLoginDenied(null, $okta);

    expect($seen)->toBeNull();
});

it('flushes every registered callback', function (): void {
    Okta::authorizeUserToLogin(fn (): bool => false);
    Okta::beforeLogin(fn (): null => null);
    Okta::afterLogin(fn (): null => null);
    Okta::onLoginDenied(fn (): null => null);

    Okta::flushCallbacks();

    expect(okta()->authorizesUser(new User, fakeOktaUser()))->toBeTrue();
});
