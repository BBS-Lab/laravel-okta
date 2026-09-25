<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Facades\Okta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as OktaUserContract;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('redirects to okta to start the login', function (): void {
    $response = $this->get(route('okta.login'));

    $response->assertStatus(302);
    expect($response->headers->get('Location'))
        ->toContain('example.okta.com')
        ->toContain('authorize');
});

it('derives the redirect_uri from the panel callback route when none is configured', function (): void {
    // No explicit services.okta.redirect — it must fall back to the route the
    // package generates, so OKTA_REDIRECT_URI is not required.
    config(['services.okta.redirect' => null]);

    $location = (string) $this->get(route('okta.login'))->headers->get('Location');

    expect($location)->toContain('redirect_uri='.urlencode(route('okta.callback')));
});

it('honours an explicitly configured redirect_uri', function (): void {
    // An explicit value still wins (e.g. behind a reverse proxy).
    config(['services.okta.redirect' => 'https://proxied.example/admin/authorization-code/callback']);

    $location = (string) $this->get(route('okta.login'))->headers->get('Location');

    expect($location)->toContain('redirect_uri='.urlencode('https://proxied.example/admin/authorization-code/callback'));
});

it('flashes an error and returns to login when the driver cannot start the redirect', function (): void {
    // e.g. the panel points at a Socialite driver that is not registered.
    Socialite::shouldReceive('driver')->with('okta')->andThrow(new RuntimeException('driver not registered'));

    $this->get(route('okta.login'))
        ->assertRedirect(url('/login'))
        ->assertSessionHas('okta::error');
});

it('adds a PKCE code challenge to the authorize url when enabled', function (): void {
    config(['okta.pkce' => true]);

    $location = (string) $this->get(route('okta.login'))->headers->get('Location');

    expect($location)->toContain('code_challenge=')
        ->toContain('code_challenge_method=S256');
});

it('omits PKCE from the authorize url by default', function (): void {
    $location = (string) $this->get(route('okta.login'))->headers->get('Location');

    expect($location)->not->toContain('code_challenge=');
});

it('logs in a resolved user on callback and stores the id token', function (): void {
    $user = User::factory()->create(['email' => 'okta@example.com']);

    $oktaUser = (new SocialiteUser)->map(['email' => 'okta@example.com', 'name' => 'Okta User']);
    $oktaUser->setAccessTokenResponseBody(['id_token' => 'the-id-token']);

    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))->assertRedirect();

    $this->assertAuthenticatedAs($user->fresh());
    expect(session('okta_authenticated'))->toBeTrue()
        ->and(session('okta_id_token'))->toBe('the-id-token');
});

it('honours the intended url after login', function (): void {
    User::factory()->create(['email' => 'intended@example.com']);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('intended@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->withSession(['url.intended' => 'http://localhost/admin/resources/things'])
        ->get(route('okta.callback'))
        ->assertRedirect('http://localhost/admin/resources/things');

    $this->assertAuthenticated();
});

it('logs in when the okta user has no access-token body (null id token)', function (): void {
    User::factory()->create(['email' => 'plain@example.com']);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('plain@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);

    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))->assertRedirect();

    $this->assertAuthenticated();
    expect(session('okta_id_token'))->toBeNull();
});

it('runs the beforeLogin and afterLogin hooks on a successful login', function (): void {
    User::factory()->create(['email' => 'hooks@example.com']);

    $order = [];
    Okta::beforeLogin(function () use (&$order): void {
        $order[] = 'before';
    });
    Okta::afterLogin(function () use (&$order): void {
        $order[] = 'after';
    });

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('hooks@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))->assertRedirect();

    $this->assertAuthenticated();
    expect($order)->toBe(['before', 'after']);
});

it('rejects an unknown user and runs onLoginDenied with a null user', function (): void {
    $deniedUser = 'unset';
    Okta::onLoginDenied(function ($user) use (&$deniedUser): void {
        $deniedUser = $user;
    });

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('ghost@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))
        ->assertRedirect(url('/login'))
        ->assertSessionHas('okta::error');

    $this->assertGuest();
    expect($deniedUser)->toBeNull();
});

it('rejects when an authorize callback denies the resolved user', function (): void {
    $user = User::factory()->create(['email' => 'blocked@example.com']);
    Okta::authorizeUserToLogin(fn (): bool => false);

    $denied = null;
    Okta::onLoginDenied(function ($u) use (&$denied): void {
        $denied = $u;
    });

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('blocked@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))
        ->assertRedirect(url('/login'))
        ->assertSessionHas('okta::error');

    $this->assertGuest();
    expect($denied?->getKey())->toBe($user->getKey());
});

it('flashes an error when the okta callback throws', function (): void {
    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('boom'));
    Socialite::shouldReceive('driver')->with('okta')->andReturn($provider);

    $this->get(route('okta.callback'))
        ->assertRedirect(url('/login'))
        ->assertSessionHas('okta::error');

    $this->assertGuest();
});

it('logs out via okta end-session when an id token is present', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'));

    $response->assertStatus(302);
    expect($response->headers->get('Location'))
        ->toContain('example.okta.com')
        ->toContain('/v1/logout')
        ->toContain('id_token_hint=the-id-token')
        ->toContain('post_logout_redirect_uri=');
    $this->assertGuest();
});

it('logs out to the login screen when no id token is present', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    $this->assertGuest();
});

it('still logs out locally when the okta driver cannot be built', function (): void {
    $user = User::factory()->create();

    Socialite::shouldReceive('driver')->with('okta')->andThrow(new RuntimeException('misconfig'));

    $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    $this->assertGuest();
});

it('does a local-only logout when sso logout is disabled', function (): void {
    config(['okta.sso_logout' => false]);
    $user = User::factory()->create();

    // Even with an id token, we must not redirect to Okta's end-session.
    $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    $this->assertGuest();
});

it('bounces the post-logout callback back to the login screen', function (): void {
    $this->get(route('okta.callback.logout'))
        ->assertRedirect(url('/login'));
});

it('reports the exception when the okta callback throws', function (): void {
    Exceptions::fake();

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('boom'));
    Socialite::shouldReceive('driver')->with('okta')->andReturn($provider);

    $this->get(route('okta.callback'))->assertRedirect(url('/login'));

    Exceptions::assertReported(RuntimeException::class);
});

it('flashes the sso_error message (distinct from not_allowed) when the callback throws', function (): void {
    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andThrow(new RuntimeException('boom'));
    Socialite::shouldReceive('driver')->with('okta')->andReturn($provider);

    $this->get(route('okta.callback'))
        ->assertSessionHas('okta::error', (string) trans('okta::messages.sso_error'));

    // The transport-failure message must not be the authorization-refusal one.
    expect((string) trans('okta::messages.sso_error'))
        ->not->toBe((string) trans('okta::messages.not_allowed'));
});

it('flashes the not_allowed message (distinct from sso_error) when authorization is denied', function (): void {
    User::factory()->create(['email' => 'refused@example.com']);
    Okta::authorizeUserToLogin(fn (): bool => false);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('refused@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))
        ->assertSessionHas('okta::error', (string) trans('okta::messages.not_allowed'));
});

it('marks the session as okta-authenticated on a successful login', function (): void {
    User::factory()->create(['email' => 'authflag@example.com']);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('authflag@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))->assertRedirect();

    expect(session('okta_authenticated'))->toBeTrue();
});

it('regenerates the session token on a successful login', function (): void {
    User::factory()->create(['email' => 'regen@example.com']);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('regen@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);
    fakeSocialiteUser($oktaUser);

    $this->withSession(['_token' => 'stale-csrf-token'])
        ->get(route('okta.callback'))
        ->assertRedirect();

    // Session fixation protection: a successful login must rotate the CSRF token,
    // never carry the pre-login one forward.
    expect(session('_token'))->not->toBe('stale-csrf-token');
});

it('stores no id token when the okta user is not a socialite oauth2 user', function (): void {
    User::factory()->create(['email' => 'contract@example.com']);

    // A bare contract user (not a SocialiteProviders OAuth2 user) has no access-token
    // response to read — even one carrying an id_token-shaped payload must be ignored.
    $oktaUser = new class implements OktaUserContract
    {
        /** @var array<string, string> */
        public array $accessTokenResponseBody = ['id_token' => 'leaked-token'];

        public function getId()
        {
            return null;
        }

        public function getNickname()
        {
            return null;
        }

        public function getName()
        {
            return null;
        }

        public function getEmail()
        {
            return 'contract@example.com';
        }

        public function getAvatar()
        {
            return null;
        }
    };
    fakeSocialiteUser($oktaUser);

    $this->get(route('okta.callback'))->assertRedirect();

    $this->assertAuthenticated();
    expect(session('okta_id_token'))->toBeNull();
});

it('invalidates the session and rotates its token on logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['probe' => 'keep-me', '_token' => 'stale-csrf-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    // invalidate() flushes arbitrary session data; regenerateToken() then issues a
    // fresh CSRF token (never left null, never the stale one).
    expect(session('probe'))->toBeNull()
        ->and(session('_token'))->toBeString()
        ->and(session('_token'))->not->toBe('stale-csrf-token');
});

it('reports the exception when building the okta logout url fails', function (): void {
    Exceptions::fake();
    $user = User::factory()->create();

    Socialite::shouldReceive('driver')->with('okta')->andThrow(new RuntimeException('misconfig'));

    $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    Exceptions::assertReported(RuntimeException::class);
});

it('skips okta end-session when the driver is not an okta provider', function (): void {
    $user = User::factory()->create();

    // A driver that is not a SocialiteProviders\Okta\Provider — even one exposing a
    // getLogoutUrl() — must not drive the end-session redirect.
    $driver = Mockery::mock(SocialiteProvider::class);
    $driver->shouldReceive('getLogoutUrl')->andReturn('https://intruder.test/logout');
    Socialite::shouldReceive('driver')->with('okta')->andReturn($driver);

    $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    $this->assertGuest();
});

it('skips okta end-session when the stored id token is an empty string', function (): void {
    $user = User::factory()->create();

    // An empty id_token is unusable: logout stays local instead of hitting Okta's
    // end-session endpoint with an empty id_token_hint.
    $this->actingAs($user)
        ->withSession(['okta_id_token' => ''])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login'));

    $this->assertGuest();
});

/**
 * Swap the Socialite okta driver for a stub whose user() returns $oktaUser.
 */
function fakeSocialiteUser(OktaUserContract $oktaUser): void
{
    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andReturn($oktaUser);

    Socialite::shouldReceive('driver')->with('okta')->andReturn($provider);
}
