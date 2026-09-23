<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Contracts\OktaUserResolver;
use BBSLab\LaravelOkta\Facades\Okta;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as OktaUserContract;
use SocialiteProviders\Manager\OAuth2\User as OktaOAuth2User;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('resolves a matching user by email via the auth guard provider', function (): void {
    $user = User::factory()->create(['email' => 'a@b.c']);

    expect(resolveOktaUser('a@b.c')?->getKey())->toBe($user->getKey());
});

it('returns null for an unknown email', function (): void {
    expect(resolveOktaUser('nobody@example.com'))->toBeNull();
});

it('returns null when the email is empty', function (): void {
    expect(resolveOktaUser(''))->toBeNull();
});

it('returns null for an empty email even if such a user exists', function (): void {
    User::factory()->create(['email' => '']);

    expect(resolveOktaUser(''))->toBeNull();
});

it('resolves the user through the Okta::resolveUserUsing callback', function (): void {
    $user = User::factory()->create(['email' => 'real@b.c']);
    Okta::resolveUserUsing(fn (): Authenticatable => $user);

    // The email is irrelevant once a resolver callback is registered.
    expect(resolveOktaUser('anything@b.c')?->getKey())->toBe($user->getKey());
});

it('returns null when the resolveUserUsing callback returns null', function (): void {
    Okta::resolveUserUsing(fn () => null);
    User::factory()->create(['email' => 'present@b.c']);

    expect(resolveOktaUser('present@b.c'))->toBeNull();
});

it('resolves a user whose okta email is verified', function (): void {
    config(['okta.require_verified_email' => true]);
    $user = User::factory()->create(['email' => 'verified@b.c']);

    expect(resolveVerifiedOktaUser('verified@b.c', true)?->getKey())->toBe($user->getKey());
});

it('rejects a user whose okta email is not verified', function (): void {
    config(['okta.require_verified_email' => true]);
    User::factory()->create(['email' => 'unverified@b.c']);

    expect(resolveVerifiedOktaUser('unverified@b.c', false))->toBeNull();
});

it('rejects when verification is required but the claim cannot be read', function (): void {
    config(['okta.require_verified_email' => true]);
    User::factory()->create(['email' => 'noclaim@b.c']);

    // A plain contract user (no getRaw) cannot expose email_verified.
    expect(resolveOktaUser('noclaim@b.c'))->toBeNull();
});

it('matches by the identifier column before email', function (): void {
    config(['okta.identifier.column' => 'okta_id']);
    $user = User::factory()->create(['email' => 'stored@b.c', 'okta_id' => 'sub-123']);

    // The Okta email changed, but the stable id still finds the linked account.
    expect(resolveOktaUserWithId('changed@b.c', 'sub-123')?->getKey())->toBe($user->getKey());
});

it('backfills the identifier column on a verified email match', function (): void {
    config(['okta.identifier.column' => 'okta_id']);
    $user = User::factory()->create(['email' => 'link@b.c', 'okta_id' => null]);

    resolveOktaOAuth2('link@b.c', 'sub-new', verified: true);

    expect($user->fresh()->okta_id)->toBe('sub-new');
});

it('does not backfill the identifier column when update is disabled', function (): void {
    config(['okta.identifier.column' => 'okta_id', 'okta.identifier.update' => false]);
    $user = User::factory()->create(['email' => 'noupd@b.c', 'okta_id' => null]);

    resolveOktaOAuth2('noupd@b.c', 'sub-x', verified: true);

    expect($user->fresh()->okta_id)->toBeNull();
});

it('never persists a link from an unverified email, even with verification disabled', function (): void {
    config(['okta.identifier.column' => 'okta_id', 'okta.require_verified_email' => false]);
    $user = User::factory()->create(['email' => 'victim@b.c', 'okta_id' => null]);

    // Unverified email still logs in (flag off) but must not leave a durable link.
    $resolved = resolveOktaOAuth2('victim@b.c', 'attacker-sub', verified: false);

    expect($resolved?->getKey())->toBe($user->getKey())
        ->and($user->fresh()->okta_id)->toBeNull();
});

it('does not overwrite an existing identifier when matched by email', function (): void {
    config(['okta.identifier.column' => 'okta_id']);
    $user = User::factory()->create(['email' => 'keep@b.c', 'okta_id' => 'old-sub']);

    // Incoming id misses the id-lookup, falls through to email; the stored id stays.
    resolveOktaOAuth2('keep@b.c', 'new-sub', verified: true);

    expect($user->fresh()->okta_id)->toBe('old-sub');
});

it('resolves via the default provider when the guard names no provider', function (): void {
    // is_string() is false, so createUserProvider(null) falls back to the default.
    config(['auth.guards.web.provider' => null, 'auth.defaults.provider' => 'users']);
    $user = User::factory()->create(['email' => 'noprovider@b.c']);

    expect(resolveOktaUser('noprovider@b.c')?->getKey())->toBe($user->getKey());
});

it('returns null when the guard has no resolvable user provider', function (): void {
    config(['auth.guards.web.provider' => 'ghost']);
    User::factory()->create(['email' => 'x@b.c']);

    expect(resolveOktaUser('x@b.c'))->toBeNull();
});

it('matches by identifier even when the email is unverified', function (): void {
    config(['okta.identifier.column' => 'okta_id', 'okta.require_verified_email' => true]);
    $user = User::factory()->create(['email' => 'idonly@b.c', 'okta_id' => 'sub-skip']);

    $oktaUser = (new OktaOAuth2User)
        ->map(['id' => 'sub-skip', 'email' => 'idonly@b.c'])
        ->setRaw(['email' => 'idonly@b.c', 'email_verified' => false]);

    expect(app(OktaUserResolver::class)->resolve($oktaUser)?->getKey())->toBe($user->getKey());
});

it('never tries to link an unresolvable account (null user short-circuits)', function (): void {
    config(['okta.identifier.column' => 'okta_id']);

    // No user matches by id or email. The account carries a verified claim and a
    // sub, so if the null-user early return were skipped the code would reach
    // linkIdentifier() with a null user — which its Authenticatable type would
    // reject. A clean null proves the guard fires first.
    expect(resolveOktaOAuth2('ghost@b.c', 'sub-ghost', verified: true))->toBeNull();
});

it('ignores an empty okta sub and matches by email, not the empty-id row', function (): void {
    config(['okta.identifier.column' => 'okta_id']);
    User::factory()->create(['email' => 'emptyid@b.c', 'okta_id' => '']);
    $emailUser = User::factory()->create(['email' => 'target@b.c', 'okta_id' => 'kept']);

    // An empty sub is not an identifier: it must fall through to the email match and
    // never resolve to the (unrelated) row that happens to store an empty id.
    expect(resolveOktaUserWithId('target@b.c', '')?->getKey())->toBe($emailUser->getKey());
});

it('does not attempt a backfill when no identifier column is configured', function (): void {
    // No okta.identifier.column: linkIdentifier() must bail on the null column and
    // never touch the model, even for a verified email match carrying a sub.
    $user = User::factory()->create(['email' => 'nolink@b.c']);

    expect(resolveOktaOAuth2('nolink@b.c', 'sub-nolink', verified: true)?->getKey())
        ->toBe($user->getKey());
});

it('leaves a non-eloquent (database provider) user untouched instead of backfilling', function (): void {
    // A database provider returns a GenericUser, not an Eloquent Model: the
    // Model-only backfill must be skipped rather than calling getAttribute() on it.
    config([
        'okta.identifier.column' => 'okta_id',
        'auth.providers.dbusers' => ['driver' => 'database', 'table' => 'users'],
        'auth.guards.web.provider' => 'dbusers',
    ]);
    $user = User::factory()->create(['email' => 'generic@b.c', 'okta_id' => null]);

    $resolved = resolveOktaOAuth2('generic@b.c', 'sub-generic', verified: true);

    expect($resolved?->getAuthIdentifier())->toEqual($user->getKey());
});

/**
 * Run the default resolver against an Okta account with the given email.
 */
function resolveOktaUser(string $email): ?Authenticatable
{
    return resolveOktaUserWithId($email, null);
}

/**
 * Run the default resolver against an Okta account with an email and a stable id (sub).
 */
function resolveOktaUserWithId(string $email, ?string $id): ?Authenticatable
{
    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn($email);
    $oktaUser->shouldReceive('getId')->andReturn($id);

    return app(OktaUserResolver::class)->resolve($oktaUser);
}

/**
 * Run the resolver against a real Okta user object carrying an email_verified claim.
 */
function resolveVerifiedOktaUser(string $email, bool $verified): ?Authenticatable
{
    return resolveOktaOAuth2($email, null, $verified);
}

/**
 * Run the resolver against a real Okta user object carrying an id (sub) and an
 * email_verified claim.
 */
function resolveOktaOAuth2(string $email, ?string $id, bool $verified): ?Authenticatable
{
    $oktaUser = (new OktaOAuth2User)
        ->map(['id' => $id, 'email' => $email])
        ->setRaw(['email' => $email, 'email_verified' => $verified]);

    return app(OktaUserResolver::class)->resolve($oktaUser);
}
