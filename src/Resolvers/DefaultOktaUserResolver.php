<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta\Resolvers;

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Contracts\OktaUserResolver;
use BBSLab\LaravelOkta\Okta;
use BBSLab\LaravelOkta\Support\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as OktaUser;

/**
 * Default resolver: map an Okta account to a local user through the auth guard's
 * own user provider (the Eloquent provider by default). It matches on the stable
 * identifier column first (when configured), then falls back to a verified email,
 * and never provisions a user. Override the lookup with Okta::resolveUserUsing(),
 * or bind your own OktaUserResolver for full control. Who may sign in and any
 * side effects are the Okta facade hooks' job — this class only finds the user.
 */
class DefaultOktaUserResolver implements OktaUserResolver
{
    public function __construct(
        protected Okta $okta,
        protected OktaPanel $panel,
    ) {}

    public function resolve(OktaUser $oktaUser): ?Authenticatable
    {
        if ($resolver = $this->okta->userResolver()) {
            return $resolver($oktaUser);
        }

        $provider = $this->userProvider();

        if ($provider === null) {
            return null;
        }

        $column = $this->panel->identifierColumn();
        $externalId = $this->externalId($oktaUser);

        // 1) Match by the stable identifier first — an already-linked account is
        // trusted and signs in without re-checking the (mutable) email.
        if ($column !== null && $externalId !== null) {
            $user = $provider->retrieveByCredentials([$column => $externalId]);

            if ($user instanceof Authenticatable) {
                return $user;
            }
        }

        // 2) Fall back to a verified email match.
        if ($this->panel->requireVerifiedEmail() && ! $this->emailIsVerified($oktaUser)) {
            return null;
        }

        $email = $oktaUser->getEmail();

        if ($email === null || $email === '') {
            return null;
        }

        $user = $provider->retrieveByCredentials(['email' => $email]);

        if (! $user instanceof Authenticatable) {
            return null;
        }

        // 3) Link the identifier for next time — but only from a VERIFIED email, so an
        // unverified assertion can never persist a durable link (even when
        // require_verified_email is off, which lets the login itself through).
        if ($this->emailIsVerified($oktaUser)) {
            $this->linkIdentifier($user, $column, $externalId);
        }

        return $user;
    }

    /**
     * The user provider backing the package's guard — resolved by name from the
     * guard's config, so the lookup goes through exactly the provider the guard
     * would use (the Eloquent provider by default), never reaching into a guard
     * instance. Null when the guard has no resolvable provider.
     */
    protected function userProvider(): ?UserProvider
    {
        $name = config('auth.guards.'.Guard::name().'.provider');

        return Auth::createUserProvider(is_string($name) ? $name : null);
    }

    /**
     * The Okta stable subject id (OIDC "sub"), as a string, or null.
     */
    protected function externalId(OktaUser $oktaUser): ?string
    {
        $id = $oktaUser->getId();

        return $id === '' ? null : $id;
    }

    /**
     * Backfill the identifier column the first time a user is matched by email.
     */
    protected function linkIdentifier(Authenticatable $user, ?string $column, ?string $externalId): void
    {
        if ($column === null || $externalId === null) {
            return;
        }

        if (! $this->panel->identifierUpdate()) {
            return;
        }

        if ($user instanceof Model && ! $user->getAttribute($column)) {
            $user->setAttribute($column, $externalId);
            $user->save();
        }
    }

    /**
     * Whether the Okta account's email is verified (OIDC "email_verified" claim).
     */
    protected function emailIsVerified(OktaUser $oktaUser): bool
    {
        $raw = $oktaUser instanceof AbstractUser ? $oktaUser->getRaw() : [];

        return ($raw['email_verified'] ?? false) === true;
    }
}
