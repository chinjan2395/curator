# Curator Frontend

Vue 3 + Vite + Pinia frontend for the Curator app.

This app lets users:
- register/login
- create workspaces
- connect social credentials (YouTube, Google, Facebook, Instagram, X/Twitter, TikTok)
- configure feeds per workspace
- curate synced posts (approve/reject/pin/delete)
- publish approved posts
- copy and preview embed code for external websites

## Tech Stack

- Vue 3
- Vite
- Pinia
- Vue Router
- Axios
- Tailwind CSS

## Prerequisites

- Node.js 20+
- npm
- Curator backend running and reachable

## Environment

Copy `.env.example` to `.env` and set values as needed.

### Variables

- `VITE_API_PROXY_TARGET`: dev-server proxy target for `/api/*` requests.
  - Local backend example: `http://127.0.0.1:8000`
  - Docker Compose example: `http://backend:8000`
- `VITE_API_BASE_URL`: browser runtime API origin for embed assets when frontend/backend are on different origins.
  - If frontend and backend share origin, you can leave this unset.

## Run Locally

```bash
npm install
npm run dev
```

Frontend runs at `http://localhost:5173` by default.

## Build and Preview

```bash
npm run build
npm run serve
```

## Docker

The frontend can be run in Docker (see project `docker-compose.yml`).
When running in Docker, set `VITE_API_PROXY_TARGET` so `/api` requests resolve to the backend service.

## App Routes and Features

### Authentication

- `/login`: email/password sign in
- `/register`: create account
- auth token is persisted in `localStorage` and attached as `Authorization: Bearer ...`

### Dashboard

- `/`: analytics cards for workspaces/feeds/publish/sync coverage
- links into workspace and publish flows

### Workspaces

- `/workspaces`: list, create, edit, delete workspaces
- `/workspaces/new`
- `/workspaces/:id/edit`

### Feeds

- `/workspaces/:workspaceId/feeds`: list feeds, preview embed output, open curate/publish/edit
- `/workspaces/:workspaceId/feeds/new`
- `/workspaces/:workspaceId/feeds/:feedId/edit`

Supported feed types in UI:
- YouTube
- Facebook
- X/Twitter
- TikTok
- RSS/Atom
- Instagram
- Other

For social feed types, users select a connected credential and can run connection tests before save.
For RSS feeds, users can test feed URL and see parsed summary.

### Curation

- `/workspaces/:workspaceId/feeds/:feedId/curate`
- sync posts now
- filter posts by status
- approve/reject posts
- pin/unpin posts
- delete posts

### Publish

- `/workspaces/:workspaceId/feeds/:feedId/publish`
- `/publish` (global selector for workspace/feed)
- view publish stats (approved/published/pending)
- publish approved posts
- manage feed appearance settings (layout, visibility options, colors)
- preview published output using embed CSS
- open/copy generated embed HTML

### Credentials and OAuth App Config

- `/credentials`
- connect/disconnect providers
- configure OAuth app settings by provider (client ID, secret, redirect URI)
- provider callback patterns used by backend:
  - `/api/social/callback/google`
  - `/api/social/callback/facebook`
  - `/api/social/callback/twitter`
  - `/api/social/callback/tiktok`

## API Usage Notes

- Frontend API calls are made against `/api/...` and rely on Vite proxy in development.
- Publish preview fetches public posts from:
  - `/api/public/feeds/{public_key}/posts`

## Troubleshooting

- If login or data calls fail, verify backend is running and `VITE_API_PROXY_TARGET` is correct.
- If social connect fails, verify OAuth app settings on `/credentials` and backend callback URLs.
- If embed preview is empty, ensure there are approved posts and click **Publish changes**.
