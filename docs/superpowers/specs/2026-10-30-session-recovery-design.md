# Design: IDP session recovery in idp-client (always-on)

Date: 2026-10-30

## Intent and why

WorldSpot apps (retree-hawaii, mailgroups, outings, kamaolepoint,
authorization, noiiolelo, skeleton) log users out after roughly 25–55
minutes, although the IDP issues 24-hour JWTs
(`IDP_TOKEN_EXPIRY_SECONDS=86400`). Root cause: the login flow verifies
the JWT and then discards it; the only login state is the native PHP
session, which the server garbage-collects after ~25 minutes of
inactivity (`session.gc_maxlifetime=1440`, external cleanup cron), and
`session.cookie_lifetime=0` drops the cookie on browser close. The
`TokenStore` durable-cookie/refresh design already in idp-client is never
wired into the shared login flow.

Fix it once in idp-client so every consuming app inherits it through a
composer update, with no app code changes.

## Goals

- Persist the verified JWT at login (session + 30-day `idp_jwt` cookie).
- On each request, when the native session is empty but a durable token
  exists: verify it, confirm it belongs to this app, re-hydrate the
  session, and refresh the token when near/after expiry.
- Logout removes the durable token so recovery never resurrects it.
- Always-on: no env flag, no app opt-in (decided with Rob).

## Non-goals

- Not extending PHP session GC or cookie lifetime server-side.
- Not adding IDP GET auto-SSO (separate future work).
- Not changing any consuming app's code (composer update only).
- kamaolepoint already stores its token; its hand-rolled flow stays as is.

## Anti-goals

- No silent re-login after an explicit logout.
- No app accepting another app's token (same-domain multi-app safety).
- No network round-trip on ordinary authenticated requests (recovery
  runs only when the session has no auth state; JWKS is cached).

## Constraints

- Package edits happen in the package's own repo (/var/www/html/idp-client),
  with tests there, then commit/push, then `composer update avisitor/idp-client`
  in each consuming app (AGENTS.md). Current version 2.1.4; ship as 2.2.0
  (backward-compatible additions).
- Tests via the repo's `tests/run-tests.sh` (phpunit).

## Approaches

- **A (chosen): shared seams.** Store the token in `CallbackHandler` (the
  login callback every app already routes through) and recover in
  `ExternalAuthProvider::isAuthenticated()` (the check every app already
  makes per request). Logout clears the cookie in the shared
  `ExternalAuthProvider::logout()`. One place to build, zero app changes.
- B: do it all in `IDPManager::getValidToken()` only. Rejected — apps'
  `isAuthenticated()` never consults it, so it would not be automatic.
- C: opt-in per app via env flag. Rejected — Rob chose always-on.

## How (decided, and my calls marked)

- **Login:** `CallbackHandler::handle()` calls `TokenStore::store($token)`
  after `JwtVerifier::verify()` succeeds. (kamaolepoint is unaffected.)
- **Recovery:** inside `ExternalAuthProvider::isAuthenticated()`, only when
  the session lacks auth state and `TokenStore::get()` has a token:
  1. `JwtVerifier::verify()` (signature + exp; JWKS cache as today).
  2. Check `payload['appId']` equals the configured `IDP_APP_ID`
     (my call: reject mismatch; also add the same check to
     `IDPManager::isValidTokenWithRoles()`).
  3. If expired or missing the `roles` claim, refresh through
     `IDPManager::getValidToken()` (keeps the app's
     `getEnhancedJwtFromIDP()` role enhancement where present) with
     `TokenStore::refresh()` as fallback (my call).
  4. Re-hydrate the same keys `CallbackHandler::setAuthenticatedUser()`
     sets (`authenticated`, `username`, `email`, `roles`, `is_admin`).
     App-specific enrichment (contact record, admin level) restores
     itself through each app's existing lazy paths — verified for
     retree-hawaii: `isAdmin()` reloads the contact record from the DB
     when `$_SESSION['contact']` is missing.
- **Logout:** `ExternalAuthProvider::logout()` calls `TokenStore::clear()`
  in both the success and the catch paths.
- **Recovery helper:** extracted as its own small class (e.g.
  `Auth\SessionRecovery`) with injectable verifier/clock so phpunit can
  test it without HTTP (my call).

## Left to the builder

Exact method names and file placement inside `src/Auth`; how
`SessionRecovery` is injected into `ExternalAuthProvider` for tests;
wording of any new error-log lines.
