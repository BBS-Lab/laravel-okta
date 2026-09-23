<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SSO (single) logout
    |--------------------------------------------------------------------------
    |
    | When enabled, logging out also ends the Okta session via its OIDC
    | end-session endpoint (RP-initiated logout) — so the user must re-authenticate
    | at Okta next time instead of being silently signed back in. Set
    | OKTA_SSO_LOGOUT=false to only clear the local session and leave the Okta
    | session alone (e.g. to avoid signing the user out of other Okta apps). The
    | local session is always cleared either way.
    |
    */

    'sso_logout' => (bool) env('OKTA_SSO_LOGOUT', true),

    /*
    |--------------------------------------------------------------------------
    | PKCE (Proof Key for Code Exchange)
    |--------------------------------------------------------------------------
    |
    | Adds a PKCE code challenge to the authorization-code flow (defense in depth,
    | recommended by OAuth 2.1 even for confidential clients). Off by default to
    | avoid changing the flow for existing deployments; enable with OKTA_PKCE=true.
    | Your Okta application must allow PKCE (Okta web apps do).
    |
    */

    'pkce' => (bool) env('OKTA_PKCE', false),

    /*
    |--------------------------------------------------------------------------
    | Require a verified Okta email
    |--------------------------------------------------------------------------
    |
    | When enabled, the default resolver rejects a login whose Okta account has
    | not verified its email (the OIDC "email_verified" claim). This guards
    | against the account-takeover class where an org with self-service
    | registration or inbound federation lets an attacker assert someone else's
    | email. Disable only if your Okta org never sends email_verified and you
    | trust every email it asserts.
    |
    */

    'require_verified_email' => (bool) env('OKTA_REQUIRE_VERIFIED_EMAIL', true),

    /*
    |--------------------------------------------------------------------------
    | Stable identifier column (optional)
    |--------------------------------------------------------------------------
    |
    | Okta issues a stable subject id (the OIDC "sub", exposed as
    | $oktaUser->getId()). If you store it on your users table, set its column
    | here and the default resolver matches on it FIRST — surviving email changes
    | and letting an already-linked account sign in without re-checking the email.
    | It falls back to a (verified) email match when there is no id match. Leave
    | 'column' null to match by email only.
    |
    | When 'update' is true, the column is backfilled the first time a user is
    | matched by a verified email, linking the account for subsequent logins. The
    | column must exist on your users table and be UNIQUE (add a migration) — the
    | id match trusts a single row.
    |
    */

    'identifier' => [
        'column' => env('OKTA_IDENTIFIER_COLUMN'), // e.g. 'okta_id' or 'provider_id'; null = email only
        'update' => (bool) env('OKTA_IDENTIFIER_UPDATE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | User resolution & lifecycle
    |--------------------------------------------------------------------------
    |
    | There is intentionally no user-mapping config here. The default resolver
    | maps an Okta account to a local user through the auth guard's own user
    | provider (the Eloquent provider by default), matched by the identifier
    | column then a verified email, and never creates a user. Customise everything
    | through the Okta facade hooks, or bind your own
    | BBSLab\LaravelOkta\Contracts\OktaUserResolver:
    |
    |   Okta::resolveUserUsing(fn ($oktaUser) => ...);              // override the lookup
    |   Okta::authorizeUserToLogin(fn ($user, $oktaUser) => ...);   // who may sign in
    |   Okta::beforeLogin(fn ($user, $oktaUser) => ...);
    |   Okta::afterLogin(fn ($user, $oktaUser) => ...);             // e.g. stamp last-login
    |   Okta::onLoginDenied(fn ($user, $oktaUser) => ...);          // e.g. audit logging
    |
    */

];
