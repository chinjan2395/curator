# Curator Enhancement Report

**Date:** 2026-06-30  
**Codebase:** Curator — social media content aggregation and embed platform  
**Scope:** Full-stack review (Laravel backend + Vue 3 frontend)

---

## Executive Summary

Curator has a solid architectural foundation — clear service/repository separation, good use of dependency injection, consistent API response shapes, and a well-thought-out OAuth flow for self-hosted credentials. However, several production-readiness gaps remain: OAuth tokens are stored as plaintext, meaning any database breach exposes every connected social account; key public-facing endpoints (login, embed, public feed) have no rate limiting; and critical database columns used in every query lack indexes. Code quality issues are largely a matter of duplicated authorization logic and cross-cutting concerns that have crept across controllers. Addressing the top 10 items in the prioritised list would meaningfully reduce both security risk and latency for embedded feeds.

---

## 1. Code Quality & Refactoring

### CQ-1 — Duplicated `authorizeOwner` method across controllers
**Severity: Low**

The same `authorizeOwner(Request $request, Workspace $workspace): void` method body is copy-pasted verbatim into at least three controllers:
- `PostController.php:60`
- `FeedSyncController.php:241`
- `FeedPublishController.php:82`

**Recommendation:** Extract to the base `Controller.php` or a dedicated `AuthorizesWorkspaceOwner` trait, then call `$this->authorizeOwner(...)` from each subclass. A single source of truth prevents future divergence.

---

### CQ-2 — Duplicated `ensureFeedInWorkspace` method
**Severity: Low**

Identical guard exists in `PostController.php:67` and `FeedSyncController.php:248`. Same extraction strategy as CQ-1 applies.

---

### CQ-3 — Near-identical token refresh methods in `SocialCredential`
**Severity: Medium**

`SocialCredential.php` contains four private methods (`getValidYouTubeAccessToken`, `getValidTwitterAccessToken`, `getValidTikTokAccessToken`, `getValidThreadsAccessToken`) all following an identical structure:
1. Check `expires_at` against buffer
2. If expired and `refresh_token` exists, POST to token endpoint
3. On success, update `access_token`, `expires_at`, (optionally `refresh_token`) and `save()`
4. On `invalid_grant`-style error, return `null`; on other errors, throw

The ~50 lines of logic are repeated four times with only the endpoint URL, header style, and error-code string varying.

**Recommendation:** Extract a protected `refreshAccessToken(string $tokenUrl, array $params, array $headers = [], ?string $revokedErrorCode = null): ?string` method. Each provider method becomes 5-10 lines of configuration + one call. This will make adding future providers (LinkedIn, Pinterest) trivially safe.

---

### CQ-4 — Service layer leaking HTTP response objects
**Severity: Medium**

`FeedSyncService.php:35` declares `public function syncFeed(Feed $feed, string $triggeredBy = 'scheduler'): ?JsonResponse`. A service method should return domain data or throw exceptions — not HTTP response objects. This couples the service to Laravel's HTTP layer, prevents unit testing without a full HTTP request context, and is the reason `FeedSyncController.php:289` has the awkward `wrapSyncerResult()` helper.

The same antipattern is propagated through all syncer classes (`YouTubeSyncer`, `FacebookSyncer`, etc.) which return `array|JsonResponse`.

**Recommendation:** Define a `SyncResult` value object (or throw `SyncException`) from syncers, move HTTP response construction to the controller layer, and let services return pure data.

---

### CQ-5 — `syncStub()` stub code in production controller
**Severity: Low**

`FeedSyncController.php:292–329` contains a `syncStub()` method that generates random fake posts with `Str::random()`. It is invoked for unrecognised feed types at line 234. This is test scaffolding left in production code and could confuse users if they add an unsupported feed type.

**Recommendation:** Remove `syncStub()` and replace the `default` branch with a proper `422` error response: `ApiResponse::error("Unsupported feed type: {$feed->type}")`.

---

### CQ-6 — `PostController.index()` returns unbounded result set
**Severity: Medium**

`PostController.php:35` calls `$query->get()` with no `limit()` or pagination. A feed with hundreds of approved posts returns the entire collection in a single response, bloating payloads and increasing memory pressure.

**Recommendation:** Replace with `->paginate($request->integer('per_page', 50))` and document the cursor/page parameters in the API. The frontend curate views will need updating to use paginated responses.

---

### CQ-7 — Public key generated without uniqueness guarantee
**Severity: Medium**

`PublishService.php:18–20` generates a workspace public key with `Str::random(32)` when none exists, but there is no database uniqueness constraint on `workspaces.public_key` and no retry loop. While a collision of a 32-character random string is extremely unlikely in practice, the database should enforce the invariant.

**Recommendation:** Add a `$table->unique('public_key')` migration to the workspaces table and wrap key generation in a `do { ... } while (Workspace::where('public_key', $key)->exists())` guard, or use `Str::uuid()` which is structurally unique by design.

---

### CQ-8 — Inconsistent use of repository pattern
**Severity: Low**

`PublishService` correctly injects `PostRepositoryInterface`, but `FeedSyncService` directly uses Eloquent models (`Post::where(...)`, `SyncLog::create(...)`) inline. This inconsistency makes it harder to swap storage or mock in tests.

**Recommendation:** Either commit to the repository pattern across all services (preferred) or remove the repository abstraction and use Eloquent directly everywhere. Mixing both strategies adds conceptual overhead for little gain.

---

### CQ-9 — `AdminSyncController.runAll()` uses `app()->terminating()` instead of the queue
**Severity: Medium**

`Admin/SyncController.php:67` registers a `terminating` hook that runs all feed syncs synchronously after the HTTP response is sent. For a deployment with many feeds this blocks the PHP-FPM worker for minutes, preventing it from handling other requests.

**Recommendation:** Dispatch individual `SyncFeedJob` jobs into the queue for each feed rather than using `app()->terminating()`. This is the intended path — `SyncFeedJob.php` already exists.

---

### CQ-10 — `SocialConnectController` is excessively large (~800 lines)
**Severity: Low**

The entire OAuth flow for 7 providers is in one file. While each flow is logically distinct, the file is difficult to navigate and test.

**Recommendation:** Extract each provider's connect and callback logic into a dedicated class (e.g., `App\OAuth\YouTubeOAuthHandler`) that implements a common interface. `SocialConnectController` becomes a thin router.

---

## 2. Missing Features / UX Gaps

### UX-1 — No bulk post curation operations
**Severity: Medium**

The `PostController` only supports updating or deleting one post at a time (`PATCH /workspaces/{w}/feeds/{f}/posts/{p}`). Users with a newly synced feed of 200 pending posts must approve them one at a time.

**Recommendation:** Add `POST /workspaces/{workspace}/feeds/{feed}/posts/bulk-update` accepting `{ ids: [...], status: 'approved' }`. A single `whereIn` + `update()` call handles this efficiently.

---

### UX-2 — Email verification not enforced
**Severity: Medium**

`User.php:5` has `MustVerifyEmail` commented out. Users can register with any email address and immediately get a Sanctum token, with no verification that they own the email.

**Recommendation:** Uncomment `MustVerifyEmail`, configure SMTP, and add an `EnsureEmailIsVerified` middleware guard to the authenticated route group (or just the workspace-modifying routes). Unverified accounts can be permitted to log in but blocked from syncing.

---

### UX-3 — Deactivated users retain valid Sanctum tokens
**Severity: High**

`LoginController.php:27` correctly blocks deactivated users at login. However, existing Sanctum tokens are never invalidated when `AdminUserController.deactivate()` is called. A user who was active at login retains full API access until their token naturally expires.

**Recommendation:** In the `deactivate()` action, call `$user->tokens()->delete()` to revoke all Sanctum tokens. Also add a deactivation check in a middleware or to the Sanctum authenticator so old tokens can't be recycled.

---

### UX-4 — No per-feed sync schedule configuration
**Severity: Low**

All feeds sync on the global scheduler cadence (every 15 minutes based on `AdminSyncController.php:21`). Users cannot configure a feed to sync hourly, daily, or on-demand only.

**Recommendation:** Add a `sync_interval_minutes` column to feeds with a default of 15. The scheduler job reads this and skips feeds that have been synced more recently than their interval.

---

### UX-5 — No keyword filter / content blocklist per feed
**Severity: Low**

Synced posts are always upserted as `pending` (or auto-approved). There is no way to automatically reject posts containing specific words or from specific dates.

**Recommendation:** Add a `blocked_keywords` JSON column to feeds. `PostSyncUpsert::upsert()` checks new posts against this list and sets `status = 'rejected'` automatically.

---

### UX-6 — No soft delete on feeds or workspaces
**Severity: Low**

`SocialCredential::booted()` (line 36) hard-deletes all feeds and posts when a credential is removed. Workspaces have no soft delete either. Accidental deletion is unrecoverable.

**Recommendation:** Add Laravel's `SoftDeletes` trait to `Feed`, `Post`, and `Workspace`. Cascade to posts via `withTrashed()` scoping rather than physical deletes. Add a restore endpoint.

---

### UX-7 — Embed preview absent from the publish flow
**Severity: Low**

The `Publish.vue` view generates embed code but provides no in-browser preview of what the widget looks like. Users must copy the snippet to an external page to verify appearance.

**Recommendation:** Render the embed widget inline in the publish page by injecting the embed JS/CSS into a sandboxed `<iframe>` that points to the current workspace's public key.

---

### UX-8 — `GET /user` exposes the full user object without field selection
**Severity: Low**

`api.php:43` returns `$request->user()` directly — the full Eloquent model. If new sensitive fields are added to `$fillable` (e.g., `sync_notifications_seen_at`, `last_login_at`) they are exposed. A dedicated `UserResource` already exists for register/login responses but is not used here.

**Recommendation:** Replace the inline closure with a controller that returns `new UserResource($request->user())`.

---

## 3. Security

### SEC-1 — OAuth tokens stored as plaintext
**Severity: High**

`social_credentials` stores `access_token` and `refresh_token` as plain `string` columns (confirmed in `2026_03_17_000002_create_social_credentials_table.php`). If the database is exfiltrated (SQL injection, backup theft, misconfigured cloud storage), every connected social account for every user is immediately compromised.

`OAuthAppConfig` correctly uses `Crypt::encryptString()` for `client_secret` — the same treatment should apply to social tokens.

**Recommendation:** Add encrypted model casts to `SocialCredential`:
```php
protected $casts = [
    'access_token'  => 'encrypted',
    'refresh_token' => 'encrypted',
    'expires_at'    => 'datetime',
];
```
Add a migration that re-encrypts existing rows (read, encrypt, save each credential). The `$hidden` array already hides these fields from JSON serialisation, but that protection is insufficient without at-rest encryption.

---

### SEC-2 — No rate limiting on login/registration endpoints
**Severity: High**

`POST /api/login` and `POST /api/register` have no throttle middleware in `api.php`. An attacker can perform unlimited credential-stuffing or password-spray attacks.

**Recommendation:** Add Laravel's built-in `throttle` middleware to auth routes:
```php
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login',  [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/forgot-password', ...);
    Route::post('/reset-password', ...);
});
```

---

### SEC-3 — No rate limiting on public embed endpoints
**Severity: High**

`GET /api/public/feeds/{publicKey}/posts`, `/api/embed/{publicKey}.js`, and `/api/embed/{publicKey}.css` are completely unthrottled. These are unauthenticated endpoints hit by every page load of every embedded widget. A single embed on a high-traffic site could already generate significant load; a deliberate scraper or DDoS could take down the backend entirely.

**Recommendation:** Apply `throttle:120,1` (120 requests per minute per IP) to the public route group. For the embed JS/CSS, increase caching (`Cache-Control: public, max-age=3600`) to dramatically reduce origin hits.

---

### SEC-4 — `role` field is mass-assignable in `User` model
**Severity: High**

`User.php:29` includes `'role'` in `$fillable`. Any code path that calls `$user->fill($request->all())` or `User::create($request->all())` could allow an attacker to self-promote to `admin` by including `role: "admin"` in a request body.

**Recommendation:** Remove `'role'` from `$fillable`. Set role explicitly in the `AuthService::registerUser()` method: `$user->role = User::ROLE_USER;`. Only admin-specific controller actions should write to this field.

---

### SEC-5 — OAuth state parameter has no expiry
**Severity: Medium**

`SocialConnectController::encryptState()` (bottom of the file) creates `Crypt::encryptString(json_encode(['user_id' => ..., 'provider' => ...]))`. Laravel's `Crypt` encrypts but does not embed an expiry. An intercepted or leaked `state` URL parameter can be replayed indefinitely (until the APP_KEY rotates).

**Recommendation:** Embed a `expires_at` timestamp in the state payload and validate it in each callback: `if ($decoded['expires_at'] < now()->timestamp) { abort(422, 'OAuth state expired.'); }`. A 10-minute TTL is sufficient for the OAuth round-trip.

---

### SEC-6 — Missing database foreign key constraints
**Severity: Medium**

All original migrations (`2026_03_17_000002` through `2026_03_17_000004`) declare foreign-key columns with `unsignedBigInteger()` but add no `->foreign()` or `->constrained()` call. This means the database does not enforce referential integrity: a feed can reference a non-existent workspace, posts can reference non-existent feeds, and orphaned rows accumulate silently.

The application-level `SocialCredential::booted()` cascade deletes attempt to compensate, but this is fragile if any deletion bypass (bulk delete, direct SQL) is used.

**Recommendation:** Add a migration that retrofits FK constraints:
```php
$table->foreign('feed_id')->references('id')->on('feeds')->onDelete('cascade');
$table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
```
This also enables the database to enforce cascades, removing the need for the model-level `booted()` cascade.

---

### SEC-7 — Deactivated users retain valid Sanctum tokens (security dimension)
**Severity: High**

*(See also UX-3)* This is both a UX gap and a security issue. A deactivated user who has been deactivated for policy reasons (e.g., abuse, account compromise) retains API access via existing tokens. An attacker who compromises a user account remains active even after the account is deactivated.

**Recommendation:** `$user->tokens()->delete()` on deactivation (see UX-3 above).

---

### SEC-8 — Admin `syncFeed` endpoint does not verify feed ownership
**Severity: Medium**

`Admin/SyncController.php:111`: `public function syncFeed(Request $request, Feed $feed)` accepts any feed ID. There is no check that the feed belongs to a specific user. While this endpoint requires admin middleware, it means any admin can trigger sync for any feed of any user — and the credential's user-scoped OAuth token is used against that user's social provider account.

**Recommendation:** This is arguably acceptable for admin operations, but should at minimum log which user's feed was synced and by which admin for audit purposes. The existing activity log is present but not called in the admin `syncFeed` path.

---

### SEC-9 — No Content-Security-Policy on embed responses
**Severity: Low**

`EmbedController.php:472,504` returns CSS and JS with only `Cache-Control` headers. No `Content-Security-Policy`, `X-Content-Type-Options`, or `X-Frame-Options` headers are set. Third-party sites embedding the widget inherit no security policy from Curator.

**Recommendation:** Add at minimum `X-Content-Type-Options: nosniff` to the CSS and JS responses. The JS response could also include a `Content-Security-Policy` comment block describing the expected policy for host pages.

---

### SEC-10 — Embed JS outputs `publish_settings` as raw JSON without sanitisation
**Severity: Low**

`EmbedController.php:498–499`:
```php
$bootstrap = 'var CRT_POSTS_URL = '.$this->jsString($postsUrl).";\n"
    .'var CRT_SETTINGS = '.$settingsJson.";\n";
```

`$settingsJson` is the output of `json_encode(PublishSettings::merge(...))`. `PublishSettings::validateAndNormalize()` does sanitise URL fields and colour strings. However, `branding.*.custom_url` fields are passed through `sanitizeOptionalHttpUrl()` which only validates scheme and length — the URLs themselves appear verbatim in the JS globals and could contain data: URIs or other unexpected payloads if the DB is tampered with.

**Recommendation:** This is low risk given the existing sanitisation, but add an explicit allowlist of URL schemes (`http`, `https`) at the point of serialisation in `jsString()`, and confirm no `javascript:` or `data:` URI can survive the validation chain.

---

## 4. Performance

### PERF-1 — Missing index on `posts.feed_id`
**Severity: High**

`posts` was created with `unsignedBigInteger('feed_id')` (migration `2026_03_17_000004`) and no index. Every query involving `$feed->posts()`, `Post::where('feed_id', ...)`, and `Post::whereIn('feed_id', ...)` (used in the public feed endpoint) performs a full table scan as posts grow. This is the single highest-impact database fix in the codebase.

**Recommendation:**
```php
$table->index('feed_id');
```

---

### PERF-2 — Missing indexes on `posts.status`, `posts.posted_at`, `posts.published_at`
**Severity: High**

`PostController.index()` filters by `status` and orders by `posted_at`. `PublicFeedController.posts()` filters by `status`, `published_at`, and orders by `pinned` then `posted_at`. None of these columns have indexes.

**Recommendation:** Add a composite index that covers the public feed query pattern:
```php
$table->index(['feed_id', 'status', 'published_at', 'posted_at']);
```

---

### PERF-3 — Missing index on `workspaces.public_key`
**Severity: High**

`EmbedController.php:480,13` and `PublicFeedController.php:15` both look up workspaces by `public_key` on every embed page load. With no index this is a full table scan, and it happens on every CSS, JS, and posts API hit — potentially millions of requests per day for active embeds.

**Recommendation:**
```php
$table->unique('public_key'); // enforces uniqueness and creates the index
```

---

### PERF-4 — Missing indexes on `social_credentials.user_id` and `feeds.workspace_id`
**Severity: Medium**

Both are plain `unsignedBigInteger` columns without indexes. `SocialCredential::where('user_id', ...)` is called on every credential operation; `$workspace->feeds()` translates to `feeds WHERE workspace_id = ?` on every sync, publish, and curate page load.

**Recommendation:** Add indexes in a single migration:
```php
$table->index('user_id');    // on social_credentials
$table->index('workspace_id'); // on feeds
```

---

### PERF-5 — `PublishService.getStats()` runs 3 separate COUNT queries
**Severity: Medium**

`PublishService.php:37–45` calls `countApprovedForWorkspace`, `countPublishedForWorkspace`, and `countPendingForWorkspace` as three separate database round-trips. Each runs a `SELECT COUNT(*) WHERE feed_id IN (subquery)`.

**Recommendation:** Replace with a single query:
```php
$counts = Post::query()
    ->selectRaw('status, COUNT(*) as cnt')
    ->whereIn('feed_id', $workspace->feeds()->select('id'))
    ->groupBy('status')
    ->pluck('cnt', 'status');
```
Then derive `approved`, `pending`, `published` from the result. The current approach triples the database load on every visit to the Publish page.

---

### PERF-6 — Public feed endpoint has no response caching
**Severity: High**

`PublicFeedController.posts()` runs at minimum 2 database queries on every request (count + paginated posts) with no caching. For a workspace embed on a popular website this endpoint is hit on every page view. At 1,000 page views/hour the database receives 2,000 queries/hour for a single embed.

**Recommendation:** Cache the JSON response in Redis for 30–60 seconds using Laravel's response cache or manual `Cache::remember()`:
```php
$cacheKey = "feed:{$workspace->id}:posts:{$limit}:{$offset}";
return Cache::remember($cacheKey, 30, fn () => [...]);
```
Invalidate the cache on publish events.

---

### PERF-7 — Embed JS file read from disk on every request
**Severity: Medium**

`EmbedController.js()` (line 491) calls `file_get_contents($path)` on every request. The current `Cache-Control: public, max-age=60` means the file is re-read from disk every minute per CDN edge node, and on every non-cached request. For a server under embed load this creates unnecessary I/O.

**Recommendation:** Cache the raw file contents in application memory:
```php
$runtime = Cache::remember('embed_runtime_js', 3600, fn () => file_get_contents($path));
```
Additionally, increase the `Cache-Control: max-age` to 3600 (1 hour) since the runtime changes only on deployment.

---

### PERF-8 — `YouTubeSyncer.sync()` makes extra channel-label API call on every sync
**Severity: Medium**

`YouTubeSyncer.php:89` calls `$this->refreshFeedAccountLabel($feed, $token, $resolved['channel_id'])` unconditionally on every sync. This adds a third YouTube API call (after channel resolution and playlist items) to every sync run, consuming quota even when the channel name hasn't changed.

**Recommendation:** Only call `refreshFeedAccountLabel()` when `$feed->source_account_label` is null or empty, or at most once per day:
```php
if (! $feed->source_account_label || $feed->updated_at->diffInHours(now()) > 24) {
    $this->refreshFeedAccountLabel($feed, $token, $resolved['channel_id']);
}
```

---

### PERF-9 — Multi-feed credential sync is sequential
**Severity: Medium**

`Admin/SyncController.php:92–97` and `SocialCredentialController.sync()` sync each feed associated with a credential in a `foreach` loop. Feeds for the same credential are synced serially, so a credential with 5 feeds takes 5× the wall time.

**Recommendation:** Dispatch a `SyncFeedJob` for each feed into the queue instead of calling `syncService->syncFeed()` directly in the loop. This parallelises the work across available workers and eliminates request-blocking.

---

### PERF-10 — `PostController.index()` returns unbounded posts (performance dimension)
**Severity: Medium**

*(See also CQ-6)* Beyond the payload size issue, a `->get()` on a feed with 5,000 posts hydrates 5,000 Eloquent objects into memory. Combined with the missing `feed_id` index (PERF-1), this query becomes progressively slower as the table grows.

**Recommendation:** As noted in CQ-6, add server-side pagination. Also ensure the `PostResource` serialiser does not trigger any lazy-loaded relationships (each post resolves its own `feed` relationship — verify eager loading is applied).

---

## Prioritised Action List

The following 10 items are ordered by impact (risk reduction × fix effort ratio):

| Priority | ID | Area | Finding | Est. Effort |
|---|---|---|---|---|
| 1 | SEC-1 | Security | Encrypt OAuth tokens at rest (`access_token`, `refresh_token` in `social_credentials`) | Medium |
| 2 | PERF-1 | Performance | Add index on `posts.feed_id` (used in every post query) | Low |
| 3 | PERF-3 | Performance | Add unique index on `workspaces.public_key` (hit on every embed request) | Low |
| 4 | SEC-2 | Security | Add rate limiting to login, register, forgot-password routes | Low |
| 5 | SEC-3 | Security | Add rate limiting to public embed and public feed endpoints | Low |
| 6 | SEC-7 / UX-3 | Security | Revoke Sanctum tokens when a user is deactivated | Low |
| 7 | SEC-4 | Security | Remove `role` from `User::$fillable` | Low |
| 8 | PERF-2 | Performance | Add composite index on `posts(feed_id, status, published_at, posted_at)` | Low |
| 9 | PERF-6 | Performance | Cache public feed API response in Redis (30-60s TTL) | Medium |
| 10 | SEC-6 | Security | Add FK constraints to `posts`, `feeds`, `social_credentials` | Low |

---

### Secondary Priorities (11–20)

| Priority | ID | Area | Finding |
|---|---|---|---|
| 11 | SEC-5 | Security | Add expiry to OAuth state parameter |
| 12 | CQ-3 | Code Quality | Extract token refresh logic in `SocialCredential` to shared method |
| 13 | PERF-5 | Performance | Collapse 3 COUNT queries in `getStats()` into one |
| 14 | UX-2 | UX | Enforce email verification on new registrations |
| 15 | CQ-4 | Code Quality | Remove `JsonResponse` from service/syncer return types |
| 16 | CQ-6 / PERF-10 | Code Quality | Add pagination to `PostController.index()` |
| 17 | UX-1 | UX | Add bulk approve/reject endpoint for posts |
| 18 | PERF-4 | Performance | Add indexes on `social_credentials.user_id` and `feeds.workspace_id` |
| 19 | PERF-7 | Performance | Cache embed JS runtime in application memory |
| 20 | CQ-9 | Code Quality | Replace `app()->terminating()` sync-all with queue jobs |

---

*Report generated by automated codebase review on 2026-06-30.*
