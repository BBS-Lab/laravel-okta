# Changelog

All notable changes to `bbs-lab/laravel-okta` will be documented in this file.

## v2.0.0 - 2026-09-25

### ⚠️ Breaking

- **The Okta route paths changed** from `okta/*` to `authorization-code/*` (defaults):

  | Purpose | Before | After (default) |
  |---------|--------|-----------------|
  | Login (start redirect) | `{prefix}/okta/login` | `{prefix}/authorization-code/redirect` |
  | Callback (redirect URI) | `{prefix}/okta/callback` | `{prefix}/authorization-code/callback` |
  | Logout | `{prefix}/okta/logout` | `{prefix}/authorization-code/logout` |
  | Post-logout landing | `{prefix}/okta/callback/logout` | `{prefix}/authorization-code/callback/logout` |

  **Action required:** update your Okta application's **Sign-in** and **Sign-out redirect URIs** in the Okta admin console to the new paths, or logins fail with `redirect_uri` mismatch (400). An explicit `services.{driver}.redirect` still wins and is unaffected.

- The **route names are unchanged** (`{panel}.login`, `.callback`, `.logout`, `.callback.logout`), so `route()` callers, the login button and the derived redirect URI keep working without changes.
- Added `OktaPanel::path(OktaRoute $route): string` to the contract — a breaking change **only** for code that implements `OktaPanel` directly (adapters extending `ConfigOktaPanel`, incl. `NullOktaPanel` and the Nova adapter, inherit it).

### ✨ Added

- **Configurable route paths.** Each path (the part after the panel's route prefix) is configurable and defaults to the value above:
  - by config for the base package / Nova, under `okta.paths.*` (env `OKTA_LOGIN_PATH`, `OKTA_CALLBACK_PATH`, `OKTA_LOGOUT_PATH`, `OKTA_CALLBACK_LOGOUT_PATH`);
  - per panel for Filament, via `OktaPlugin::make()->paths(...)`.
- `BBSLab\LaravelOkta\Enums\OktaRoute` — the single source of truth for each route's config key, default path, route-name suffix and controller method.

## v1.1.0 - 2026-09-24

### ✨ Added

- Optional interop: Okta-authenticated sessions (`okta_authenticated`) are automatically exempted from the bbs-lab forced-security gates when those packages are installed —
  - from forced **2FA** enrolment ([bbs-lab/laravel-force-two-factor](https://github.com/BBS-Lab/laravel-force-two-factor)): their second factor is enforced by Okta;
  - from forced **password rotation** ([bbs-lab/laravel-password-rotation](https://github.com/BBS-Lab/laravel-password-rotation)): SSO users have no local password to rotate.

  Both are soft `class_exists` integrations (a no-op when the package is absent, no new hard dependency).

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
