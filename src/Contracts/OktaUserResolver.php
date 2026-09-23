<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as OktaUser;

interface OktaUserResolver
{
    /**
     * Map an authenticated Okta account to a local, loggable user — or null when
     * the account is not allowed to sign in.
     */
    public function resolve(OktaUser $oktaUser): ?Authenticatable;
}
