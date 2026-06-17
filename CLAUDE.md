# CLAUDE.md

**Curator / OmniPresence AI** — social media aggregation, AI content generation, scheduling, and embed platform. Stack: Laravel 12 + Vue 3 + MySQL.

## Before working
Read **`docs/STATUS.md`** — current phase, module health, what's done, what's left.

## After every task
Update **`docs/STATUS.md`** — module health table + change log row.
If a named roadmap step completes, also update `docs/reports/omnipresence-development-roadmap.md` changelog.
Never edit the baseline/alignment/modify-gaps historical reports — they are read-only.

## Run the project
```bash
docker compose up --build          # Frontend :5173  |  Backend :8000
docker compose exec backend php artisan test
```
AI features: set `AI_DRIVER=groq` + `GROQ_API_KEY=sk-...` in `backend/.env`.

## Sub-agents — delegate to these for detail

| Task | Agent |
|------|-------|
| Laravel controllers, services, migrations, tests | `backend-dev` |
| Vue 3 views, components, stores, composables | `frontend-dev` |
| Code review, lint, test run | `code-reviewer` |
| PDF / HTML reports | `report-generator` |

## Key locations

| What | Where |
|------|-------|
| All API routes | `backend/routes/api.php` |
| Social publishers | `backend/app/Services/Social/Publishers/` |
| AI providers | `backend/app/Services/AI/` |
| Embed runtime | `backend/resources/embed/curator-embed.js` |
| Frontend views | `frontend/src/views/` |
| Frontend stores | `frontend/src/stores/` |
| Architecture rules | `.claude/strict_frontend_backend_architecture_guidelines.md` |
