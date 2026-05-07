# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Curator is a social media content aggregation and embed platform. Users connect their own social accounts (YouTube, Instagram, Facebook, X/Twitter, TikTok), sync posts, curate them (approve/reject/pin), and embed a published feed widget on any website.

## Commands

### Docker (recommended)
```bash
docker compose up --build
# Frontend: http://localhost:5173 | Backend: http://localhost:8000
```

### Backend (Laravel)
## Architecture

### Request Flow
- Frontend makes all API calls to relative `/api/*` paths via Axios
- In dev, Vite proxies `/api/*` to `VITE_API_PROXY_TARGET` (avoids CORS)
- In production, `VITE_API_BASE_URL` must point to the backend domain
- Auth: Sanctum Bearer tokens stored in `localStorage` under `token`; attached via `axios.defaults.headers.common` on app init

### Key Data Model
- `User` → many `Workspace` (via `owner_id`) → many `Feed`
- `Feed` → belongs to `SocialCredential`; stores provider-specific IDs
- `Post` → belongs to `Feed`; status: `pending` / `approved` / `rejected`; has `pinned` and `published_at`
- `SocialCredential` → one row per user per provider; stores access/refresh tokens + expiry
- `OAuthAppConfig` → user-provided OAuth app credentials; `client_secret` stored encrypted via Laravel `Crypt`
- `Workspace.publish_settings` → JSON blob of layout/appearance config (see `App\Support\PublishSettings`)

### Post Lifecycle
1. **Sync** — `FeedSyncController` calls the social API and upserts posts as `pending`
2. **Curate** — user approves/rejects/pins posts via `PostController`
3. **Publish** — workspace-level action; stamps `published_at` on all `approved` posts where it's null
4. **Embed** — public embed endpoint serves the published posts; no auth required

### OAuth / Social Credential Flow
Each user registers their own OAuth app on each platform (no shared platform credentials). The flow:
1. User stores their `client_id`/`client_secret` on the Credentials page (encrypted in DB)
2. `POST /api/social/connect` returns a provider `auth_url`
3. Browser redirects to provider; provider redirects back to `GET /api/social/callback/{provider}`
4. Backend decrypts `state` (JSON: `user_id`, `provider`, optionally `code_verifier`), exchanges code for tokens, saves `SocialCredential`
5. Backend redirects browser to `{FRONTEND_URL}/credentials?connected={provider}`

**Non-obvious OAuth details:**
- **Twitter PKCE workaround**: No session exists on `/api` routes, so the PKCE `code_verifier` is embedded in the encrypted `state` parameter instead of session storage
- **Facebook long-lived tokens**: Short-lived tokens are automatically exchanged for long-lived (~60 days) at callback time
- **Instagram shares the Facebook OAuth app**: Provider disambiguation is done via `provider` field in encrypted state
- `SocialCredential::getValidAccessToken()` transparently refreshes tokens for YouTube, Twitter, and TikTok before every API call

### Embed System
- `GET /api/embed/{publicKey}.js` — PHP assembles a per-workspace JS bundle: injects `CRT_POSTS_URL`, `CRT_PUBLIC_KEY`, `CRT_SETTINGS` as globals, then appends `backend/resources/embed/curator-embed.js` (vanilla JS IIFE)
- `GET /api/embed/{publicKey}.css` — inline CSS served by `EmbedController`
- `GET /api/public/feeds/{publicKey}/posts` — unauthenticated endpoint returning published posts JSON
- Embed snippet: `<div data-curator-feed="<key>"></div>` + `<link>` + `<script>`
- The public key belongs to the **workspace** (not individual feeds) — publish operates workspace-wide

### Social Providers

| Provider | OAuth Mechanism | Notes |
|---|---|---|
| YouTube | Socialite (Google) | Scopes: `youtube.readonly` + profile; `access_type=offline&prompt=consent` for refresh token |
| Facebook | Socialite | Graph API v23.0; auto-exchanges for long-lived token at callback |
| Instagram | Socialite (Facebook app) | Instagram Graph API via Facebook Page access tokens; requires linked Business/Creator account |
| X/Twitter | Manual PKCE OAuth 2 | PKCE verifier in encrypted state; tokens rotated on refresh |
| TikTok | Manual OAuth 2 | `open.tiktokapis.com/v2`; scopes: `user.info.basic`, `video.list` |

### Frontend Structure
- **Router** ([frontend/src/router/index.js](frontend/src/router/index.js)): navigation guard rehydrates auth from localStorage on cold load
- **Stores** ([frontend/src/stores/](frontend/src/stores/)): Pinia stores — `auth`, `feeds`, `publish`, etc.
- **Views** ([frontend/src/views/](frontend/src/views/)): one view per route — `Dashboard`, `FeedsList`, `FeedForm`, `Curate`, `WorkspaceCurate`, `Publish`, `Credentials`, `WorkspacesList`, `WorkspaceForm`

### Backend Structure
- All routes: [backend/routes/api.php](backend/routes/api.php)
- Controllers: [backend/app/Http/Controllers/](backend/app/Http/Controllers/) — one controller per domain area
- Key controllers: `SocialConnectController` (OAuth), `FeedSyncController` (all provider sync logic), `FeedPublishController` (publish + stats + settings + embed code generation), `EmbedController` (public embed JS/CSS), `PublicFeedController` (unauthenticated posts API)
- Publish layout config: [backend/app/Support/PublishSettings.php](backend/app/Support/PublishSettings.php) — 11 layout styles; settings merged with defaults on read
- Embed runtime: [backend/resources/embed/curator-embed.js](backend/resources/embed/curator-embed.js)