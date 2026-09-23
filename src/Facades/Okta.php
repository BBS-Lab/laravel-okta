<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The public API is the login-lifecycle hook registrars below. The manager also
 * exposes userResolver()/authorizesUser()/runBeforeLogin()/... and flushCallbacks(),
 * but those are consumed by the controller/resolver (and tests) — not part of the
 * documented facade contract.
 *
 * @method static \BBSLab\LaravelOkta\Okta authorizeUserToLogin(\Closure $callback)
 * @method static \BBSLab\LaravelOkta\Okta beforeLogin(\Closure $callback)
 * @method static \BBSLab\LaravelOkta\Okta afterLogin(\Closure $callback)
 * @method static \BBSLab\LaravelOkta\Okta onLoginDenied(\Closure $callback)
 * @method static \BBSLab\LaravelOkta\Okta resolveUserUsing(\Closure $callback)
 *
 * @see \BBSLab\LaravelOkta\Okta
 */
class Okta extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BBSLab\LaravelOkta\Okta::class;
    }
}
