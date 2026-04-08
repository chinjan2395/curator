# Curator

Curator is a full-stack social feed curation and publishing platform.

It includes:
- `frontend` - Vue 3 app for auth, feed management, curation, publishing, and embed preview
- `backend` - Laravel API for OAuth, sync, curation state, publish workflow, and embed/public endpoints
- `mysql` (via Docker Compose) for persistence

## Repository Structure

- `frontend` - Vue + Vite + Pinia
- `backend` - Laravel 12 + Sanctum + Socialite
- `docker-compose.yml` - local multi-service setup
- `mysql-data` - MySQL data volume directory

## Prerequisites

- Node.js 20+
- npm
- PHP 8.4+
- Composer
- MySQL 8 (if running without Docker)
- Docker Desktop (optional, for Compose workflow)

## Run With Docker Compose

From repository root:

```bash
docker compose up --build
```

Services (as configured):
- Frontend: `http://localhost:5173`
- Backend: `http://localhost:8000`
- MySQL: `localhost:3306`

Notes:
- Frontend container uses `VITE_API_PROXY_TARGET=http://backend:8000`.
- Backend service in Compose is configured to use MySQL host `mysql`.

## Run Locally (without Docker)

### 1) Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve --host=0.0.0.0 --port=8000
```

### 2) Frontend

In a separate terminal:

```bash
cd frontend
npm install
npm run dev
```

Set frontend env values (see `frontend/.env.example`) so API proxy points to backend, for example:
- `VITE_API_PROXY_TARGET=http://127.0.0.1:8000`

## Main Product Flows

1. Register/login.
2. Create workspace(s).
3. Connect provider credentials in Credentials screen.
4. Create feed (YouTube/Facebook/X/TikTok/RSS/etc.).
5. Sync feed posts.
6. Curate posts (approve/reject/pin/delete).
7. Publish approved posts.
8. Copy embed HTML and use it on external websites.

## OAuth and Social Providers

Supported providers:
- YouTube
- Google
- Facebook
- Instagram
- X/Twitter
- TikTok

Backend callback routes:
- `/api/social/callback/youtube`
- `/api/social/callback/google`
- `/api/social/callback/facebook`
- `/api/social/callback/twitter`
- `/api/social/callback/tiktok`

Provider app credentials can be managed per user in the frontend Credentials page and stored in backend `oauth_app_configs`.

## API Surfaces

- Auth + workspace/feed/post APIs (protected with Sanctum bearer tokens)
- Social connect/disconnect + credential APIs
- Publish APIs:
  - stats
  - settings
  - publish action
  - embed code generation
- Public APIs:
  - `GET /api/public/feeds/{publicKey}/posts`
  - `GET /api/embed/{publicKey}.js`
  - `GET /api/embed/{publicKey}.css`

## Documentation

- Frontend guide: `frontend/README.md`
- Backend guide: `backend/README.md`

## Useful Commands

Backend tests:

```bash
cd backend
php artisan test
```

Frontend production build:

```bash
cd frontend
npm run build
```
