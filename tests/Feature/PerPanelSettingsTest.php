<?php

declare(strict_types=1);

use BBSLab\LaravelOkta\Contracts\OktaPanel;
use BBSLab\LaravelOkta\Tests\FakeOktaPanel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as OktaUserContract;
use Laravel\Socialite\Facades\Socialite;
use Workbench\App\Models\User;

uses(RefreshDatabase::class);

it('honours a per-panel sso_logout over the global config', function (): void {
    // Global config says end-session, but the active panel disables it.
    config(['okta.sso_logout' => true]);
    app()->bind(OktaPanel::class, fn (): OktaPanel => new class extends FakeOktaPanel
    {
        public function ssoLogout(): bool
        {
            return false;
        }
    });

    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['okta_id_token' => 'the-id-token'])
        ->get(route('okta.logout'))
        ->assertRedirect(url('/login')); // local-only, never Okta's end-session

    $this->assertGuest();
});

it('logs in through the per-panel socialite driver', function (): void {
    app()->bind(OktaPanel::class, fn (): OktaPanel => new class extends FakeOktaPanel
    {
        public function socialiteDriver(): string
        {
            return 'okta-admin';
        }
    });

    User::factory()->create(['email' => 'admin@example.com']);

    $oktaUser = Mockery::mock(OktaUserContract::class);
    $oktaUser->shouldReceive('getEmail')->andReturn('admin@example.com');
    $oktaUser->shouldReceive('getId')->andReturn(null);

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('user')->andReturn($oktaUser);
    // Only the panel's driver is stubbed — a call to the default 'okta' would fail.
    Socialite::shouldReceive('driver')->with('okta-admin')->andReturn($provider);

    $this->get(route('okta.callback'))->assertRedirect();

    $this->assertAuthenticated();
});
