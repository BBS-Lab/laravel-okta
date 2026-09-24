<?php

declare(strict_types=1);

use BBSLab\LaravelForceTwoFactor\Facades\ForceTwoFactor;
use BBSLab\LaravelOkta\Http\Controllers\OktaController;
use BBSLab\LaravelPasswordRotation\Concerns\RotatesPassword;
use BBSLab\LaravelPasswordRotation\Contracts\MustRotatePassword;
use BBSLab\LaravelPasswordRotation\Facades\PasswordRotation;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Workbench\App\Models\User;

function requestWithOktaSession(bool $authenticated): Request
{
    $session = new Store('test-session', new ArraySessionHandler(120));

    if ($authenticated) {
        $session->put(OktaController::AUTHENTICATED_SESSION_KEY, true);
    }

    $request = Request::create('/');
    $request->setLaravelSession($session);

    return $request;
}

/**
 * The password-rotation registry type-hints MustRotatePassword, so the bypass is
 * exercised with a rotatable user (the Okta bypass callback ignores it anyway).
 */
function rotatableUser(): MustRotatePassword
{
    return new class extends User implements MustRotatePassword
    {
        use RotatesPassword;
    };
}

it('exempts an okta-authenticated session from forced 2FA', function (): void {
    expect(ForceTwoFactor::shouldBypass(requestWithOktaSession(true), User::factory()->make()))->toBeTrue();
});

it('does not exempt a non-okta session from forced 2FA', function (): void {
    expect(ForceTwoFactor::shouldBypass(requestWithOktaSession(false), User::factory()->make()))->toBeFalse();
});

it('exempts an okta-authenticated session from forced password rotation', function (): void {
    expect(PasswordRotation::shouldBypass(requestWithOktaSession(true), rotatableUser()))->toBeTrue();
});

it('does not exempt a non-okta session from forced password rotation', function (): void {
    expect(PasswordRotation::shouldBypass(requestWithOktaSession(false), rotatableUser()))->toBeFalse();
});
