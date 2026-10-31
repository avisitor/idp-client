# IDP Client Session Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make IDP logins survive PHP session GC and browser restarts by persisting the IDP JWT at login and silently recovering the session from it on later requests — always-on, for every consuming app, with no app code changes.

**Architecture:** The shared login callback (`CallbackHandler`) stores the verified JWT via `TokenStore` (session + 30-day `idp_jwt` cookie). A new `SessionRecovery` class re-establishes `$_SESSION` from the durable token when the native session is empty, refreshing expired tokens through the IDP. `ExternalAuthProvider::isAuthenticated()` (the per-request gate every app uses) invokes it; its `logout()` clears the durable token so recovery never resurrects a logout.

**Tech Stack:** PHP >= 8.1, firebase/php-jwt ^7.0, PHPUnit ^9.0, package repo `/var/www/html/idp-client` (namespace `WorldSpot\IDPClient`).

**Spec:** `docs/superpowers/specs/2026-10-30-session-recovery-design.md`

## Global Constraints

- All changes live in the `/var/www/html/idp-client` repo (never app repos or vendor dirs); tests there via `tests/run-tests.sh` (AGENTS.md).
- Ship as version 2.2.0 (backward-compatible additions only): bump `composer.json` `"version"` and commit as "Release 2.2.0".
- Never hydrate session state from unverified JWT claims. Claims may only be trusted after `JwtVerifier::verify()` passes (signature + exp), including after any refresh (re-verify the refreshed token).
- Refreshed tokens from `refresh-or-enhance-token.php` carry `appId` but NOT a `roles` claim — recovery must not require `roles` to be present.
- Style: 4-space indent, `declare(strict_types=1)`, typed params/returns, K&R braces, camelCase.
- Rolling out to apps is `composer update avisitor/idp-client` in each consuming repo (constraints are `^2.0`), then that app's `tests/run-tests.sh`.

## Review Focus

| # | Condition | Expected behavior | Pinned by |
|---|---|---|---|
| 1 | User logs out (session and cookie previously set) | Next request must NOT silently re-authenticate them | Task 4 test |
| 2 | Durable cookie holds a token issued for a different app | Recovery fails, session stays empty | Task 2 + Task 3 tests |
| 3 | Cookie holds a tampered/forged token | No hydration; refresh round-trip rejects server-side; attempt() returns false | Task 2 test |
| 4 | Token expired with valid signature, IDP unreachable (refresh fails) | attempt() returns false cleanly; no half-hydrated session | Task 2 test |
| 5 | Already-authenticated request | No recovery attempt, no TokenStore read, no network | Task 3 test |

---

### Task 1: Persist the verified JWT at login

**Files:**
- Modify: `src/Auth/CallbackHandler.php`
- Test: `tests/CallbackHandlerTest.php` (new)

**Interfaces:**
- Consumes: `TokenStore::store(?string $token)` (existing), `TokenStore::get()` (existing).
- Produces: `CallbackHandler::persistToken(string $token): void` (protected) — called from `handle()` right after `JwtVerifier::verify()` succeeds and before `setAuthenticatedUser()`. Later tasks rely only on the fact that after a successful callback the token is in `TokenStore`.

- [ ] **Step 1: Write the failing test**

`tests/CallbackHandlerTest.php`, namespace `WorldSpot\IDPClient\Tests`, a subclass `ExposedCallbackHandler extends CallbackHandler` with `public function exposePersist(string $token): void { $this->persistToken($token); }`. Test `testPersistTokenStoresToken`: `session_start(); $_SESSION = [];` then `$h->exposePersist($t); assertSame($t, TokenStore::get());` Test `testPersistTokenClearsOnEmpty`: `exposePersist(''); assertNull(TokenStore::get());` (follow the session/`$_COOKIE` setup pattern of `TokenStoreTest::setUp()`).

- [ ] **Step 2: Run test to verify it fails**

Run: `./tests/run-tests.sh -v` (from repo root; or `vendor/bin/phpunit tests/CallbackHandlerTest.php`)
Expected: FAIL — `Call to undefined method ... persistToken()`.

- [ ] **Step 3: Implement in `src/Auth/CallbackHandler.php`**

Add `protected function persistToken(string $token): void { TokenStore::store($token); }` and, in `handle()`, call `$this->persistToken($token);` on the line after the `if (!$payload)` check passes. No other changes to `handle()`.

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/CallbackHandlerTest.php`
Expected: PASS (2 assertions).

- [ ] **Step 5: Commit**

```bash
git add src/Auth/CallbackHandler.php tests/CallbackHandlerTest.php
git commit -m "feat: persist verified JWT via TokenStore on login callback"
```

---

### Task 2: SessionRecovery class

**Files:**
- Create: `src/Auth/SessionRecovery.php`
- Test: `tests/SessionRecoveryTest.php` (new)

**Interfaces:**
- Consumes: `TokenStore::get()`, `TokenStore::store()`, `TokenStore::needsRefresh(string $token, int $lead = 600)`, `JwtVerifier::verify(string $token, string $idpUrl)`, `TokenStore::refresh(string $token, string $appId, string $idpUrl)` (all existing).
- Produces (used by Task 3):

```php
namespace WorldSpot\IDPClient\Auth;
final class SessionRecovery
{
    public function __construct(?callable $verify = null, ?callable $refresh = null);
    public function attempt(string $appId, string $idpUrl): bool;
}
```

`$verify` receives `(string $token)` and returns `?array` payload; `$refresh` receives `(string $token)` and returns `?string` new token. When null, `attempt()` binds defaults: `$verify = fn($t) => JwtVerifier::verify($t, $idpUrl);` and `$refresh = fn($t) => TokenStore::refresh($t, $appId, $idpUrl);`.

**Behavior `attempt()` must implement (the tests pin exactly this):**
1. `$token = TokenStore::get();` — null/empty → return false.
2. `$payload = verify($token);`
3. If `$payload === null` OR `TokenStore::needsRefresh($token, 600)`: `$new = refresh($token);` if null → return false; `TokenStore::store($new);` `$token = $new;` `$payload = verify($token);` if null → return false. (Expired-but-signed tokens refresh server-side; tampered ones fail refresh. Never trust claims until verify passes.)
4. `($payload['appId'] ?? '') === $appId`, else false. `!empty($payload['sub'])`, else false.
5. Hydrate `$_SESSION`: `authenticated=true`, `username=email=sub`, `roles = $payload['roles'] ?? ['user']` (is_array check, else `['user']`), `is_admin = in_array('admin', $roles, true)`, `authenticated_at = time()`. Then `TokenStore::store($token)` (refreshes session copy + cookie). Return true.

Test setup reuses the `JwtVerifierTest` offline pattern: generate an RSA key in `setUp()`, write its JWKS to `sys_get_temp_dir() . '/idp-client-jwks-' . md5($idpUrl) . '.json'` with `touch()`, sign tokens with `JWT::encode($payload, $pem, 'RS256', $kid)`; `$idpUrl = 'https://fake-idp.test'`. `session_start(); $_SESSION = []; $_COOKIE = [];` per test.

- [ ] **Step 1: Write the failing tests**

`tests/SessionRecoveryTest.php` with these tests:
- `testRecoversValidToken` — cookie holds valid token (`sub` 'a@b.com', `appId` 'app-x', `roles` ['editor'], `exp` +3600); `attempt('app-x', $idpUrl)` → true; `$_SESSION['authenticated']===true`, `$_SESSION['email']==='a@b.com'`, `$_SESSION['username']==='a@b.com'`, `$_SESSION['roles']===['editor']`, `$_SESSION['is_admin']===false`.
- `testRejectsTokenForOtherApp` — same token, `attempt('app-y', $idpUrl)` → false; `assertArrayNotHasKey('authenticated', $_SESSION)`.
- `testRejectsGarbageToken` — `$_COOKIE[TokenStore::COOKIE_NAME] = 'garbage'`; construct with refresh stub `fn($t) => null` (keeps the test offline); → false, session empty.
- `testRejectsForgedSignature` — token signed with a different key (`roles ['admin']`): verify fails and refresh stub returns null → false; session empty (never trusts forged admin role).
- `testRefreshesExpiredToken` — token `exp: time()-3600`, refresh stub returns a freshly signed valid token (exp +3600, same appId); → true; `TokenStore::get()` === new token.
- `testRefreshFailureReturnsFalse` — expired token, refresh stub returns null → false; `assertArrayNotHasKey('authenticated', $_SESSION)`.
- `testHydratesAdminFlag` — roles ['admin'] → `$_SESSION['is_admin']===true`.
- `testNoTokenReturnsFalse` — no cookie, no session token → false.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/SessionRecoveryTest.php`
Expected: FAIL — class `SessionRecovery` not found.

- [ ] **Step 3: Implement `src/Auth/SessionRecovery.php`**

Class as specified in Interfaces; body per the numbered behavior above. Use `TokenStore::decode()` nowhere for claims (only verified payloads).

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/SessionRecoveryTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Auth/SessionRecovery.php tests/SessionRecoveryTest.php
git commit -m "feat: add SessionRecovery for durable-token session rehydration"
```

---

### Task 3: Wire recovery into the auth provider; appId check in IDPManager

**Files:**
- Modify: `src/Auth/Providers/ExternalAuthProvider.php`
- Modify: `src/IDPManager.php` (`isValidTokenWithRoles`)
- Test: `tests/ExternalAuthProviderTest.php` (new), `tests/IDPManagerTest.php` (new)

**Interfaces:**
- Consumes: `SessionRecovery` from Task 2 (`attempt(string $appId, string $idpUrl): bool`).
- Produces: `ExternalAuthProvider::__construct(array $config = [], ?SessionRecovery $sessionRecovery = null)` — new optional param, stored in `private $sessionRecovery` (default `new SessionRecovery()`). `isAuthenticated(): bool` now performs recovery. Existing callers construct with only `$config` — unchanged behavior for them.

- [ ] **Step 1: Write the failing tests**

`tests/ExternalAuthProviderTest.php` — setUp: `putenv('IDP_URL=https://fake-idp.test'); putenv('IDP_APP_ID=app-x'); $_ENV['IDP_URL']=...; $_ENV['IDP_APP_ID']=...;` + JWKS cache seeded exactly as in Task 2 (same URL string), `session_start(); $_SESSION = []; $_COOKIE = [];`. tearDown: unlink cache file, clear putenv. Tests:
- `testIsAuthenticatedRecoversFromDurableCookie` — session empty, `$_COOKIE[TokenStore::COOKIE_NAME]` = valid signed token (appId 'app-x', roles [], exp +3600); `(new ExternalAuthProvider([]))->isAuthenticated()` → true; `$_SESSION['email']==='a@b.com'`.
- `testIsAuthenticatedRejectsForeignAppToken` — same but appId 'app-y' → false, session not hydrated.
- `testIsAuthenticatedShortCircuitsWhenAlreadyAuthenticated` — `$_SESSION = ['email'=>'a@b.com','authenticated'=>true];` cookie set to `'garbage'`; → true, no exception, `$_SESSION` unchanged.

`tests/IDPManagerTest.php` — setUp same env + cache seeding; `session_start(); $_SESSION = ['email'=>'a@b.com']; $_COOKIE=[];`. Tests:
- `testGetValidTokenAcceptsOwnAppToken` — `TokenStore::store(validToken('app-x', roles []))`; `(new IDPManager())->getValidToken()` → same token string.
- `testGetValidTokenRejectsForeignAppToken` — store token with appId 'app-y'; `getValidToken()` → null (refresh path yields null because `getEnhancedJwtFromIDP` is undefined in the test process).

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/ExternalAuthProviderTest.php tests/IDPManagerTest.php`
Expected: FAIL — recovery never happens (foreign-app token also accepted by IDPManager).

- [ ] **Step 3: Implement**

In `src/Auth/Providers/ExternalAuthProvider.php`:
- `use WorldSpot\IDPClient\Auth\SessionRecovery;` and `use Throwable;` (if not present).
- Constructor: `public function __construct(array $config = [], ?SessionRecovery $sessionRecovery = null)` — assign `$this->sessionRecovery = $sessionRecovery ?? new SessionRecovery();`, keep the existing `$this->idpManager` line. Add `private $sessionRecovery;` property.
- In `isAuthenticated()`, replace the `if (!$userEmail) { ... return false; }` block with: first try recovery — `$this->sessionRecovery->attempt($this->appIdFromEnv(), $this->idpUrlFromEnv())` inside try/catch (`Throwable` → `$this->debuglog("session recovery failed: ...", "", Levels::WARNING); return false;`); if attempt() returns false → `return false`; then re-read `$userEmail` from `$_SESSION['email'] ?? $_SESSION['username'] ?? null` and if still empty `return false`. Private helpers `appIdFromEnv(): string` / `idpUrlFromEnv(): string` read `$_ENV['IDP_APP_ID'] ?? getenv('IDP_APP_ID')` (same as `IDPManager::getIDPConfig()`), returning `''` when unset (attempt() then fails the appId match).

In `src/IDPManager.php`, inside `isValidTokenWithRoles()` after the existing exp check: if `$this->appId !== '' && ($payload['appId'] ?? '') !== $this->appId` → `error_log("IDPManager: Token appId mismatch (app " . $this->appId . ")"); return false;`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/ExternalAuthProviderTest.php tests/IDPManagerTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Auth/Providers/ExternalAuthProvider.php src/IDPManager.php tests/ExternalAuthProviderTest.php tests/IDPManagerTest.php
git commit -m "feat: recover session from durable IDP token; enforce token appId"
```

---

### Task 4: Logout clears the durable token

**Files:**
- Modify: `src/Auth/Providers/ExternalAuthProvider.php` (`logout()`)
- Test: `tests/ExternalAuthProviderTest.php` (extend)

**Interfaces:**
- Consumes: `TokenStore::store()`, `TokenStore::clear()`, `TokenStore::get()`.
- Produces: logout always ends with `TokenStore::get() === null` (after the browser drops the expired cookie).

- [ ] **Step 1: Write the failing tests**

Add to `tests/ExternalAuthProviderTest.php`:
- `testLogoutClearsDurableToken` — `TokenStore::store(validToken(...))`; provider config `['app_base_url' => 'https://app.test', 'app_auth_path' => 'auth']`; `$provider->logout(null)`; then `unset($_COOKIE[TokenStore::COOKIE_NAME]);` (simulates the browser dropping the expired Set-Cookie) and `assertNull(TokenStore::get())`.
- `testLogoutCatchPathClearsDurableToken` — `TokenStore::store(...)`; provider config `[]` (missing `app_base_url` forces the catch path); same assertions. (Both tests may also assert `$result['success'] === true`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/ExternalAuthProviderTest.php`
Expected: FAIL — token survives logout.

- [ ] **Step 3: Implement**

In `ExternalAuthProvider::logout()`, add `TokenStore::clear();` immediately before `$_SESSION = array(); session_destroy();` in BOTH the try (success) path and the catch (fallback) path.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/ExternalAuthProviderTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Auth/Providers/ExternalAuthProvider.php tests/ExternalAuthProviderTest.php
git commit -m "fix: clear durable IDP token on logout"
```

---

### Task 4b: Clear the durable token from every logout entry point

**Files:**
- Modify: `src/Auth/LogoutHandler.php` (`clearAuthentication()`)
- Modify: `src/IDPManager.php` (`logoutUser()`)
- Modify: `src/Auth/Providers/LocalAuthProvider.php` (`logout()`)
- Test: `tests/LogoutHandlerTest.php` (new)
- Test: `tests/IDPManagerTest.php` (extend)
- Test: `tests/LocalAuthProviderTest.php` (new)

**Interfaces:**
- Consumes: `TokenStore::clear()`, `TokenStore::store()`, `TokenStore::get()`, `TokenStore::COOKIE_NAME`.
- Produces: every logout path ends with `TokenStore::get() === null`.

Rationale: Task 4 fixed `ExternalAuthProvider::logout()`, but the package also exposes
`LogoutHandler::handle()` (used by `templates/logout.php`), `IDPManager::logoutUser()` /
`IDPClient::logoutUser()`, and `LocalAuthProvider::logout()`. Each leaves the durable
`idp_jwt` cookie intact, so `SessionRecovery` would silently re-authenticate a user who
logged out through any of them.

- [ ] **Step 1: Write the failing tests**
  - `tests/LogoutHandlerTest.php` — a test subclass exposing `clearAuthentication()`; seed `TokenStore::store($token)` plus `$_COOKIE[TokenStore::COOKIE_NAME] = $token`; call it; assert `TokenStore::get() === null`.
  - `tests/IDPManagerTest.php` — `testLogoutUserClearsDurableToken`: seed both stores, `$manager->logoutUser('/')`, assert `TokenStore::get() === null`.
  - `tests/LocalAuthProviderTest.php` — same seeding, `$provider->logout()`, assert `TokenStore::get() === null`.

- [ ] **Step 2: Run tests to verify they fail** — `php -d output_buffering=4096 vendor/bin/phpunit tests/LogoutHandlerTest.php tests/LocalAuthProviderTest.php` and the focused IDPManager test. Expected: FAIL — cookie survives.

- [ ] **Step 3: Implement** — call `TokenStore::clear();` before the session teardown in each of the three paths (add the import where missing). Do not otherwise restructure those methods.

- [ ] **Step 4: Run the full suite** — `./tests/run-tests.sh` — expected all pass.

- [ ] **Step 5: Commit** — `git commit -m "fix: clear durable IDP token from all logout entry points"`.

---

### Task 5: Version bump and full suite

**Files:**
- Modify: `composer.json` (`"version": "2.2.0"`)

- [ ] **Step 1:** Change `"version"` in `composer.json` from `"2.1.4"` to `"2.2.0"`.
- [ ] **Step 2:** Run `./tests/run-tests.sh` — Expected: all tests pass, JSON report written to `tests/reports/`, success rate 100%.
- [ ] **Step 3:** Commit and push:

```bash
git add composer.json
git commit -m "Release 2.2.0"
git push
```

---

### Task 6: Roll out to consuming apps

**Files:** none in the package; per-app `composer.lock` updates only.

- [ ] **Step 1:** For each consuming repo — `retree-hawaii`, `mailgroups`, `outings`, `kamaolepoint`, `authorization`, `noiiolelo`, `skeleton` — run `composer update avisitor/idp-client` and confirm the lock file resolves 2.2.0.
- [ ] **Step 2:** Run each app's test suite (`tests/run-tests.sh` where present) and fix nothing in the package from this step — any failure is an app-level regression to report.
- [ ] **Step 3:** Manual smoke on retree-hawaii (primary reporter): log in via IDP, confirm the `idp_jwt` cookie is set, delete the session file (`sudo rm /var/lib/php/session/sess_<PHPSESSID>`), reload the page — expect to still be authenticated; then log out and reload — expect login required.
- [ ] **Step 4:** Commit lock-file updates per app repo.
