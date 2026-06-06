# Curator Backend

Laravel 12 backend API for the Curator platform.

It provides:
- authentication (register/login/logout + Sanctum token auth)
- workspace/feed/post management
- social OAuth connection flows (YouTube/Google, Facebook/Instagram, X/Twitter, TikTok)
- feed sync from social providers and RSS/Atom
- curation and publishing workflows
- public feed + embed JS/CSS endpoints for external websites

## Stack

- PHP 8.4
- Laravel 12
- Laravel Sanctum
- Laravel Socialite
- MySQL

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

Optional frontend asset pipeline in backend package:

```bash
npm install
npm run dev
```

## Environment

Important `.env` values:

- `APP_URL` - backend base URL used to generate embed/public URLs
- `FRONTEND_URL` - frontend URL used for OAuth callback redirects back to UI
- `DB_*` - database settings

OAuth provider env keys (used as defaults and reference):

- Google/YouTube: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- Facebook/Instagram: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`
- X/Twitter: `TWITTER_CLIENT_ID`, `TWITTER_CLIENT_SECRET`, `TWITTER_REDIRECT_URI`
- TikTok: `TIKTOK_CLIENT_ID`, `TIKTOK_CLIENT_SECRET`, `TIKTOK_REDIRECT_URI`

The app also supports per-user OAuth app settings stored in `oauth_app_configs` (managed from frontend Credentials screen).

## Railway Deployment

This backend includes `railway.json` configured for Nixpacks.

### Required Railway variables

Set these on the backend Railway service:

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` (generate with `php artisan key:generate --show`)
- `APP_URL=https://<your-backend-domain>`
- `FRONTEND_URL=https://<your-frontend-domain>`
- `DB_CONNECTION=mysql`
- `DB_HOST=<railway-mysql-host>`
- `DB_PORT=<railway-mysql-port>`
- `DB_DATABASE=<railway-mysql-db>`
- `DB_USERNAME=<railway-mysql-user>`
- `DB_PASSWORD=<railway-mysql-password>`

Recommended:

- `LOG_CHANNEL=stack`
- `LOG_LEVEL=info`
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`

### OAuth callback URLs (provider app settings)

Use your backend public domain exactly:

- YouTube: `https://<your-backend-domain>/api/social/callback/youtube`
- Google (feed connect): `https://<your-backend-domain>/api/social/callback/google`
- Google (login): `https://<your-backend-domain>/api/auth/social/google/callback`
- Facebook + Instagram: `https://<your-backend-domain>/api/social/callback/facebook`
- Facebook (login): `https://<your-backend-domain>/api/auth/social/facebook/callback`
- GitHub (login): `https://<your-backend-domain>/api/auth/social/github/callback`
- X/Twitter (feed connect): `https://<your-backend-domain>/api/social/callback/twitter`
- X/Twitter (login): `https://<your-backend-domain>/api/auth/social/twitter/callback`
- TikTok: `https://<your-backend-domain>/api/social/callback/tiktok`

### Frontend/CORS alignment

- Ensure `FRONTEND_URL` matches your deployed frontend origin.
- Ensure frontend uses backend API origin (for Vite/proxy or runtime base URL).
- If OAuth redirects are landing on wrong host, check both `APP_URL` and `FRONTEND_URL`.

### Post-deploy checklist

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan optimize
```

Verify:
- `GET /api/public/feeds/{publicKey}/posts` works from public internet.
- `GET /api/embed/{publicKey}.js` and `.css` return 200.
- OAuth callback URLs are reachable over HTTPS.

## Authentication Model

- `POST /api/register` and `POST /api/login` are public.
- Protected routes require `Authorization: Bearer <token>` with Sanctum.
- `POST /api/logout` invalidates current token.

## API Overview

### Public endpoints

- `GET /api/social/callback/{provider}` OAuth callbacks for:
  - `youtube`, `google`, `facebook`, `twitter`, `tiktok`
- `GET /api/public/feeds/{publicKey}/posts` published feed payload
- `GET /api/embed/{publicKey}.css` embed stylesheet
- `GET /api/embed/{publicKey}.js` embed runtime script

### Authenticated endpoints

- `GET /api/user`
- Workspaces:
  - `GET/POST /api/workspaces`
  - `GET/PUT/DELETE /api/workspaces/{workspace}`
- Feeds:
  - `GET/POST /api/workspaces/{workspace}/feeds`
  - `GET/PUT/DELETE /api/workspaces/{workspace}/feeds/{feed}`
  - `PATCH /api/workspaces/{workspace}/feeds/{feed}/sync-settings` (updates `auto_publish_new_posts` only; allowed even when the feed has approved posts)
  - `POST /api/workspaces/{workspace}/feeds/{feed}/sync`
  - Provider helper endpoints:
    - `GET /api/workspaces/{workspace}/feeds/youtube/channels`
    - `GET /api/workspaces/{workspace}/feeds/facebook/pages`
    - `GET /api/workspaces/{workspace}/feeds/twitter/account`
    - `GET /api/workspaces/{workspace}/feeds/tiktok/account`
  - Connection test endpoints:
    - `POST /api/workspaces/{workspace}/feeds/test-youtube`
    - `POST /api/workspaces/{workspace}/feeds/test-facebook`
    - `POST /api/workspaces/{workspace}/feeds/test-twitter`
    - `POST /api/workspaces/{workspace}/feeds/test-tiktok`
    - `POST /api/workspaces/{workspace}/feeds/test-rss`
- Posts:
  - `GET /api/workspaces/{workspace}/feeds/{feed}/posts`
  - `PUT /api/workspaces/{workspace}/feeds/{feed}/posts/{post}`
  - `DELETE /api/workspaces/{workspace}/feeds/{feed}/posts/{post}`
- Publish:
  - `GET /api/workspaces/{workspace}/feeds/{feed}/publish/stats`
  - `PUT /api/workspaces/{workspace}/feeds/{feed}/publish/settings`
  - `POST /api/workspaces/{workspace}/feeds/{feed}/publish`
  - `GET /api/workspaces/{workspace}/feeds/{feed}/publish/code`
- Social credentials:
  - `GET /api/social-credentials`
  - `POST /api/social/connect`
  - `POST /api/social/disconnect`
- OAuth app configs:
  - `GET /api/oauth-app-configs`
  - `POST /api/oauth-app-configs`
  - `DELETE /api/oauth-app-configs/{provider}`

## Feed Sync Behavior

Implemented feed types:

- `youtube`: uses connected user-owned channel uploads playlist
- `facebook`: reads managed Page feed via Graph API
- `twitter`: reads authenticated account posts from X API v2
- `tiktok`: reads account videos from TikTok API v2
- `rss`: parses RSS 2.0 and Atom feeds from `source_url`
- other types currently use stub sample post generation

Synced posts are upserted by `external_id`. Content fields refresh on every sync; `status`, `pinned`, and `published_at` are set **only on first insert** so manual curation is preserved. New inserts default to `pending` unless the feed has `auto_publish_new_posts` enabled (then they are `approved` with `published_at` set and the workspace `public_key` is ensured for embed URLs).

## Scheduler (background sync)

- `bootstrap/app.php` schedules `php artisan feeds:sync-scheduled` every fifteen minutes. Sync runs **inline** in the scheduler process (no queue worker required).
- Production needs a process that runs `php artisan schedule:run` often enough for Laravel to fire due tasks — **every minute** is recommended (`* * * * *`). A cron that only runs every fifteen minutes also works but is less precise.
- **Railway:** add a Cron service with command `php artisan schedule:run` (every minute) or `php artisan feeds:sync-scheduled` (every fifteen minutes). The main web service does not need `queue:work` for scheduled feed sync.
- **Docker Compose:** the `scheduler` service runs `php artisan schedule:work` continuously. The `queue` service is optional unless you dispatch `SyncFeedJob` manually.

## Curation + Publish Rules

- Curation updates post `status` (`pending`, `approved`, `rejected`) and `pinned`.
- Publish marks approved + unpublished posts with `published_at`.
- Public feed endpoint returns posts that are **approved** and have `published_at` set.
- Feed edit/delete is blocked when feed has approved posts (use `PATCH .../sync-settings` to toggle auto-publish independently).

## Embed

`GET /api/workspaces/{workspace}/feeds/{feed}/publish/code` returns:
- public posts URL
- embed JS URL
- embed CSS URL
- `embed_html` snippet for external sites

Embed JS bootstraps from backend runtime (`resources/embed/curator-embed.js`) and applies publish settings.

## Testing

Run test suite:

```bash
php artisan test
```

Feature tests include `tests/Feature/TikTokIntegrationTest.php` and `tests/Feature/FeedAutoPublishTest.php`.
