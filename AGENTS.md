# AGENTS.md

> Entry point for all AI agents working in this repository.
> Cursor agents: also read `CLAUDE.md` (already loaded automatically) and `.cursor/rules/`.

---

## Before you do anything

1. **Read `docs/STATUS.md`** — tells you exactly where the project stands, what's done, and what's left.
2. **Read `CLAUDE.md`** — architecture, data model, conventions, key file locations.

---

## After you complete work

Update documentation in this order:

| What you did | Files to update |
|-------------|-----------------|
| Shipped any feature | `docs/STATUS.md` → module table + change log |
| Completed a named Phase 4 step | `docs/STATUS.md` + `docs/reports/omnipresence-development-roadmap.md` changelog |
| Added a new social provider | `docs/STATUS.md` native publishing matrix + CLAUDE.md provider table |
| Changed module status | `docs/STATUS.md` module health table |

**Never edit** `docs/reports/omnipresence-gap-report-baseline.md`, `omnipresence-alignment-completion-report.md`, or `omnipresence-modify-gaps-completion-report.md` — these are historical records.

**Never create new `.md` report files** in `docs/reports/`. Add a change log row to `docs/STATUS.md` instead.

---

## Current development phase

Phase 4 tail-end (~97% spec coverage). Core product is feature-complete.
See `docs/STATUS.md` for the full picture.

---

## Cursor rules (persistent agent guidance)

| Rule file | Scope | What it enforces |
|-----------|-------|-----------------|
| `.cursor/rules/doc-maintenance.mdc` | Always applied | Which file to update after completing work |
| `.cursor/rules/backend-conventions.mdc` | `backend/**/*.php` | Laravel architecture layers, testing, AI drivers |
| `.cursor/rules/frontend-conventions.mdc` | `frontend/src/**` | App* component system, stores, composables |
