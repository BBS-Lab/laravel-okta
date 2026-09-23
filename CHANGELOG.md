# Changelog

All notable changes to `bbs-lab/laravel-okta` will be documented in this file.

## v1.0.0 - 2026-09-23

Framework-agnostic base for Okta SSO on Laravel. Extracted from `bbs-lab/nova-okta` so Nova and Filament (and any custom panel) share one Okta flow.

### ✨ Features

- Registers the `socialiteproviders/okta` driver via the `SocialiteWasCalled` event.
- Ships the login / callback / logout controller and `OktaRoutes::register()`, which mounts `okta/login`, `okta/callback`, `okta/logout`, `okta/callback/logout` for a panel.
- Derives the OIDC `redirect_uri` from the panel's `okta/callback` route, so `OKTA_REDIRECT_URI` is optional (an explicit `services.{driver}.redirect` still wins).
- `OktaPanel` seam: everything panel-specific (guard, login/home URLs, route prefix + name, middleware, Socialite driver, and the behaviour flags) lives on the panel, so several panels can run side by side with different Okta configurations. `ConfigOktaPanel` provides config-backed defaults for the single-panel case.
- `OktaPanel::flashError()` so each panel surfaces login errors natively (session flash by default; adapters can override).
- Configurable SSO logout (`sso_logout`, default on): logout ends the Okta session via OIDC end-session, or clears only the local session when disabled.
- Bindable `OktaUserResolver` (default: maps an Okta account to a local user via the auth guard's user provider, resolved by name, by email — never creates a user).
- Optional stable-identifier matching (`okta.identifier`): match on the Okta `sub` via a configurable column first, fall back to verified email, and backfill the column on first match.
- Optional PKCE for the authorization-code flow (`OKTA_PKCE`, off by default).
- Optional publishable `okta_id` migration (`--tag=okta-migrations`).
- `Okta` facade of login-lifecycle hooks — `resolveUserUsing`, `authorizeUserToLogin`, `beforeLogin`, `afterLogin`, `onLoginDenied` — so authorization and side effects (last-login stamp, provisioning, audit) are opt-in callbacks instead of baked-in config.
- Sets an `okta_authenticated` session flag other packages can read to skip forced 2FA enrolment.

### 🔒 Security

- The default resolver rejects unverified Okta emails via the `email_verified` claim (`require_verified_email`, on by default).
- `redirect()` fails gracefully (flash + back to login) instead of a 500 when the Socialite driver cannot be built.
- Logout always clears local auth even if the Okta driver is misconfigured.
- The stable-identifier link is only ever backfilled from a **verified** email, so an unverified assertion can never persist a durable account link (the identifier column must be unique).

### 📦 Requirements

- PHP 8.2+, Laravel 11/12/13.

## Unreleased

Framework-agnostic base for Okta SSO on Laravel. Extracted from `bbs-lab/nova-okta` so Nova and Filament (and any custom panel) share one Okta flow.

### ✨ Features

- Registers the `socialiteproviders/okta` driver via the `SocialiteWasCalled` event.
- Ships the login / callback / logout controller and `OktaRoutes::register()`, which mounts `okta/login`, `okta/callback`, `okta/logout`, `okta/callback/logout` for a panel.
- Derives the OIDC `redirect_uri` from the panel's `okta/callback` route, so `OKTA_REDIRECT_URI` is optional (an explicit `services.{driver}.redirect` still wins).
- `OktaPanel` seam: everything panel-specific (guard, login/home URLs, route prefix + name, middleware, Socialite driver, and the behaviour flags) lives on the panel, so several panels can run side by side with different Okta configurations. `ConfigOktaPanel` provides config-backed defaults for the single-panel case.
- `OktaPanel::flashError()` so each panel surfaces login errors natively (session flash by default; adapters can override).
- Configurable SSO logout (`sso_logout`, default on): logout ends the Okta session via OIDC end-session, or clears only the local session when disabled.
- Bindable `OktaUserResolver` (default: maps an Okta account to a local user via the auth guard's user provider, resolved by name, by email — never creates a user).
- Optional stable-identifier matching (`okta.identifier`): match on the Okta `sub` via a configurable column first, fall back to verified email, and backfill the column on first match.
- Optional PKCE for the authorization-code flow (`OKTA_PKCE`, off by default).
- Optional publishable `okta_id` migration (`--tag=okta-migrations`).
- `Okta` facade of login-lifecycle hooks — `resolveUserUsing`, `authorizeUserToLogin`, `beforeLogin`, `afterLogin`, `onLoginDenied` — so authorization and side effects (last-login stamp, provisioning, audit) are opt-in callbacks instead of baked-in config.
- Sets an `okta_authenticated` session flag other packages can read to skip forced 2FA enrolment.

### 🔒 Security

- The default resolver rejects unverified Okta emails via the `email_verified` claim (`require_verified_email`, on by default).
- `redirect()` fails gracefully (flash + back to login) instead of a 500 when the Socialite driver cannot be built.
- Logout always clears local auth even if the Okta driver is misconfigured.
- The stable-identifier link is only ever backfilled from a **verified** email, so an unverified assertion can never persist a durable account link (the identifier column must be unique).

### 📦 Requirements

- PHP 8.2+, Laravel 11/12/13.
