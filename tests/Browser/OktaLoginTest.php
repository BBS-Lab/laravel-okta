<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Browser (Pest v4) coverage of the pre-redirect login UX for a plain consumer
 * of the base package: the workbench login screen carries the Okta button, which
 * targets the base okta/login route that starts the OIDC redirect.
 */
it('shows the Log In with Okta button on the login screen', function (): void {
    $page = visit('/login');

    $page->assertSee('Log In with Okta')
        ->assertPresent('#okta-login');
});

it('points the Okta button at the base okta login route', function (): void {
    visit('/login')
        ->assertAttribute('#okta-login', 'href', route('okta.login'));
});
