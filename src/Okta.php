<?php

declare(strict_types=1);

namespace BBSLab\LaravelOkta;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Socialite\Contracts\User as OktaUser;

/**
 * Registry of lifecycle hooks for the Okta login flow. Everything the default
 * resolver used to bake in (SSO flag, role gate, last-login stamp) is now the
 * consumer's choice, wired through these callbacks — typically from a service
 * provider's boot().
 */
class Okta
{
    /** @var array<int, Closure(Authenticatable, OktaUser): bool> */
    protected array $authorizeCallbacks = [];

    /** @var array<int, Closure(Authenticatable, OktaUser): void> */
    protected array $beforeLoginCallbacks = [];

    /** @var array<int, Closure(Authenticatable, OktaUser): void> */
    protected array $afterLoginCallbacks = [];

    /** @var array<int, Closure(?Authenticatable, OktaUser): void> */
    protected array $loginDeniedCallbacks = [];

    /** @var (Closure(OktaUser): ?Authenticatable)|null */
    protected ?Closure $userResolver = null;

    /**
     * Authorize a resolved user (replaces the old is_sso_allowed / role gates).
     * Every callback must return true for the login to proceed.
     *
     * @param  Closure(Authenticatable, OktaUser): bool  $callback
     */
    public function authorizeUserToLogin(Closure $callback): static
    {
        $this->authorizeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run a side effect just before the user is logged in (e.g. provisioning).
     *
     * @param  Closure(Authenticatable, OktaUser): void  $callback
     */
    public function beforeLogin(Closure $callback): static
    {
        $this->beforeLoginCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run a side effect just after the user is logged in (e.g. stamping a
     * last-login column).
     *
     * @param  Closure(Authenticatable, OktaUser): void  $callback
     */
    public function afterLogin(Closure $callback): static
    {
        $this->afterLoginCallbacks[] = $callback;

        return $this;
    }

    /**
     * Run a side effect when a login is refused — the user could not be resolved
     * or an authorize callback denied it (e.g. audit logging).
     *
     * @param  Closure(?Authenticatable, OktaUser): void  $callback
     */
    public function onLoginDenied(Closure $callback): static
    {
        $this->loginDeniedCallbacks[] = $callback;

        return $this;
    }

    /**
     * Override how an Okta account is mapped to a local user, instead of the
     * default (the auth guard's user provider, matched by email). Return null to
     * deny. A lighter alternative to binding the OktaUserResolver contract.
     *
     * @param  Closure(OktaUser): ?Authenticatable  $callback
     */
    public function resolveUserUsing(Closure $callback): static
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * @return (Closure(OktaUser): ?Authenticatable)|null
     */
    public function userResolver(): ?Closure
    {
        return $this->userResolver;
    }

    /**
     * Whether every registered authorize callback allows this user in.
     */
    public function authorizesUser(Authenticatable $user, OktaUser $oktaUser): bool
    {
        foreach ($this->authorizeCallbacks as $callback) {
            if ($callback($user, $oktaUser) !== true) {
                return false;
            }
        }

        return true;
    }

    public function runBeforeLogin(Authenticatable $user, OktaUser $oktaUser): void
    {
        foreach ($this->beforeLoginCallbacks as $callback) {
            $callback($user, $oktaUser);
        }
    }

    public function runAfterLogin(Authenticatable $user, OktaUser $oktaUser): void
    {
        foreach ($this->afterLoginCallbacks as $callback) {
            $callback($user, $oktaUser);
        }
    }

    public function runLoginDenied(?Authenticatable $user, OktaUser $oktaUser): void
    {
        foreach ($this->loginDeniedCallbacks as $callback) {
            $callback($user, $oktaUser);
        }
    }

    /**
     * Forget every registered callback.
     */
    public function flushCallbacks(): void
    {
        $this->authorizeCallbacks = [];
        $this->beforeLoginCallbacks = [];
        $this->afterLoginCallbacks = [];
        $this->loginDeniedCallbacks = [];
        $this->userResolver = null;
    }
}
