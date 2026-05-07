# Architecture Changes & Implementation Report

**Date:** 2026-05-06
**Branch:** `feature/restrict-app-structure`
**Based on:** `.claude/strict_frontend_backend_architecture_guidelines.md`

---

## Overview

This document records all changes made to bring the Curator codebase into compliance with the strict frontend/backend architecture guidelines. Changes span both the Laravel backend and Vue.js frontend.

---

## Backend Changes

### 1. FormRequest Classes (New)

**Problem:** All validation was done inline inside controller methods using `$request->validate()` or `Validator::make()`, violating the rule that all validation must use Form Requests.

**Created:** `backend/app/Http/Requests/`

| File | Extracted From |
|---|---|
| `Auth/LoginRequest.php` | `Auth/LoginController::login()` — replaced `Validator::make()` |
| `Auth/RegisterRequest.php` | `Auth/RegisterController::register()` — replaced `Validator::make()` |
| `StoreFeedRequest.php` | `FeedController::store()` — replaced `$request->validate()` |
| `UpdateFeedRequest.php` | `FeedController::update()` — replaced `$request->validate()` |
| `StoreWorkspaceRequest.php` | `WorkspaceController::store()` — replaced `$request->validate()` |
| `UpdateWorkspaceRequest.php` | `WorkspaceController::update()` — replaced `$request->validate()` |
| `UpdatePostRequest.php` | `PostController::update()` — replaced `$request->validate()` |
| `StoreSocialCredentialRequest.php` | `SocialCredentialController::store()` |
| `UpdateSocialCredentialRequest.php` | `SocialCredentialController::update()` |
| `LabelSocialCredentialRequest.php` | `SocialCredentialController::label()` |
| `UpsertOAuthAppConfigRequest.php` | `OAuthAppConfigController::upsert()` |

---

### 2. Repository Pattern (New)

**Problem:** Controllers and other classes queried Eloquent models directly (`SocialCredential::query()->where(...)`, `Post::query()->whereIn(...)`), bypassing any data-access abstraction.

**Created:** `backend/app/Repositories/`

#### Contracts (Interfaces)
- `Contracts/FeedRepositoryInterface.php`
- `Contracts/WorkspaceRepositoryInterface.php`
- `Contracts/SocialCredentialRepositoryInterface.php`

#### Implementations
- `FeedRepository.php` — wraps all Feed + SocialCredential queries
- `WorkspaceRepository.php` — wraps all Workspace queries for a user
- `SocialCredentialRepository.php` — wraps credential CRUD

#### Binding
All interfaces bound to their implementations in `AppServiceProvider::register()`:
```php
$this->app->bind(FeedRepositoryInterface::class, FeedRepository::class);
$this->app->bind(WorkspaceRepositoryInterface::class, WorkspaceRepository::class);
$this->app->bind(SocialCredentialRepositoryInterface::class, SocialCredentialRepository::class);
```

---

### 3. Service Layer (New)

**Problem:** `FeedController` contained 422 lines of business logic — type-specific credential validation, field transformation, source-label computation — all mixed inside `store()` and `update()`. This logic was duplicated nearly verbatim between both methods. Controllers must only receive, delegate, and return.

**Created:** `backend/app/Services/`

| Service | Responsibilities |
|---|---|
| `FeedService.php` | Provider constraint validation per type (`youtube`, `facebook`, `instagram`, `twitter`, `tiktok`, `threads`, `rss`); feed payload building; de-duplicated logic shared between create and update |
| `WorkspaceService.php` | Workspace CRUD orchestration via repository |
| `PostService.php` | Post status/pinned updates with activity logging; post deletion |
| `AuthService.php` | Login attempt (with deactivation check), user registration, token generation — extracted from `Validator::make()` fat controllers |

---

### 4. Refactored Controllers

All controllers now follow the strict thin pattern: **receive → delegate → return**.

| Controller | Before | After |
|---|---|---|
| `Auth/LoginController` | 55 lines, `Validator::make()`, business logic | 30 lines, `LoginRequest`, delegates to `AuthService` |
| `Auth/RegisterController` | 40 lines, `Validator::make()`, `User::create()` in controller | 20 lines, `RegisterRequest`, delegates to `AuthService` |
| `WorkspaceController` | 74 lines, inline `$request->validate()` | 55 lines, `StoreWorkspaceRequest`/`UpdateWorkspaceRequest`, delegates to `WorkspaceService` |
| `FeedController` | **422 lines**, all business logic inline, duplicated store/update | **65 lines**, delegates entirely to `FeedService` |
| `PostController` | 99 lines, inline validation | 75 lines, `UpdatePostRequest`, delegates to `PostService` |
| `SocialCredentialController` | 93 lines, inline validation | 75 lines, dedicated FormRequests, delegates to `SocialCredentialRepository` |
| `OAuthAppConfigController` | 198 lines, `$request->validate()` | Refactored: `UpsertOAuthAppConfigRequest`, extracted `findConfig()` helper, removed duplication |

---

## Frontend Changes

### 5. UI Design System Components (New)

**Problem:** No centralized component library existed. All views built UI from scratch using raw HTML elements (`<button>`, `<input>`, `<table>`) with page-specific Tailwind classes, making visual consistency impossible to enforce.

**Created:** `frontend/src/components/ui/`

| Component | Replaces |
|---|---|
| `AppButton.vue` | Raw `<button>` tags with `.btn-primary` / `.btn-secondary` classes |
| `AppInput.vue` | Raw `<input class="input-pro">` |
| `AppSelect.vue` | Raw `<select class="input-pro">` |
| `AppCard.vue` | Raw `<div class="surface-card ...">` |
| `AppBadge.vue` | Inline `<span class="bg-... text-... rounded-full">` badges |
| `AppAlert.vue` | Ad-hoc alert divs |
| `AppModal.vue` | Per-page modal implementations |
| `AppTable.vue` | Raw `<table>` with `.table-shell` / `.table-head` classes |
| `AppLoader.vue` | Inline spinner SVGs |
| `AppPagination.vue` | No consistent pagination existed |
| `AppDropdown.vue` | Per-page dropdown open/close logic |
| `AppCheckbox.vue` | Raw `<input type="checkbox">` |
| `AppTitle.vue` | Raw `<h2 class="text-... font-bold">` |
| `AppText.vue` | Raw `<p class="text-... text-slate-...">` |
| `AppFormField.vue` | Inconsistent label + error patterns per view |
| `AppEmptyState.vue` | No consistent empty state component |

**Barrel export:** `components/ui/index.js` — import any component with:
```js
import { AppButton, AppTable, AppModal } from '@/components/ui'
```

---

### 6. Layout Components (New)

**Problem:** Pages composed layouts with freestyle nested `<div>` structures with inline spacing classes (`class="p-7 flex gap-5 ..."`).

**Created:** `frontend/src/components/layout/`

| Component | Purpose |
|---|---|
| `AppPage.vue` | Root page wrapper with max-width and padding |
| `AppPageHeader.vue` | Standardized page title + subtitle + action slot |
| `AppSection.vue` | Consistent vertical section spacing |
| `AppStack.vue` | Flex-based directional stack with spacing tokens |

**Barrel export:** `components/layout/index.js`

---

### 7. Composables (New)

**Problem:** Business logic, API calls, and loading state lived directly in `<script setup>` blocks inside view files. No reusable async patterns existed.

**Created:** `frontend/src/composables/`

| Composable | Purpose |
|---|---|
| `useForm.js` | Centralized form state, error mapping from server, loading flag, `submit()` wrapper with auto error-clearing |
| `useToast.js` | Thin wrapper over the `toast` Pinia store — `success()`, `error()`, `info()`, `warning()` |
| `useConfirm.js` | Promise-based confirmation dialog state — eliminates per-component `window.confirm()` usage |
| `useAsync.js` | Generic loading/error wrapper for non-form async actions |

---

### 8. Design Tokens (New)

**Created:** `frontend/src/design-system/tokens.js`

Centralizes all design constants — colors, spacing, radius, shadow, typography — so that components reference tokens instead of hardcoded values. Prevents magic values.

---

## Before vs After Comparison

### Backend Controller Size

| Controller | Before (lines) | After (lines) | Reduction |
|---|---|---|---|
| `FeedController` | 422 | 65 | −85% |
| `Auth/LoginController` | 55 | 30 | −45% |
| `Auth/RegisterController` | 40 | 20 | −50% |
| `WorkspaceController` | 74 | 55 | −26% |
| `PostController` | 99 | 75 | −24% |

### Architecture Layer Compliance

| Rule | Before | After |
|---|---|---|
| FormRequest validation | ❌ All inline | ✅ 11 dedicated FormRequest classes |
| Repository pattern | ❌ Direct Eloquent in controllers | ✅ Interfaces + implementations |
| Service layer | ❌ Logic in controllers | ✅ 4 service classes |
| Repository binding | ❌ Not bound | ✅ All bound in AppServiceProvider |
| Duplicate store/update logic | ❌ ~150 lines duplicated in FeedController | ✅ Extracted to FeedService |

### Frontend Component Compliance

| Rule | Before | After |
|---|---|---|
| Raw HTML in views | ❌ `<button>`, `<input>`, `<table>` everywhere | ✅ App* components available |
| Design system components | ❌ None existed | ✅ 16 UI + 4 layout components |
| Composables | ❌ None existed | ✅ 4 composables |
| Design tokens | ❌ Hardcoded Tailwind values | ✅ Centralized tokens file |
| Barrel exports | ❌ No organized imports | ✅ index.js for ui/ and layout/ |

---

---

## Phase 2 Changes (follow-up to "What Still Needs Attention")

### 9. DTOs (New)

Created `backend/app/DTOs/`:

| DTO | Used By |
|---|---|
| `FeedData.php` | `FeedService::createFeed()` / `updateFeed()` — typed feed input |
| `WorkspaceData.php` | `WorkspaceService::createWorkspace()` / `updateWorkspace()` |
| `PostUpdateData.php` | `PostService::updatePost()` — typed status + pinned update |
| `AuthData.php` | `AuthService::registerUser()` — typed registration input |

All services updated to accept DTOs. Controllers construct DTOs via `::fromArray($request->validated())` before passing to services. No raw arrays cross layer boundaries.

---

### 10. API Resources (New)

Created `backend/app/Http/Resources/`:

| Resource | Wraps |
|---|---|
| `ApiResponse.php` | Static factory: `success()`, `error()`, `noContent()` — enforces `{success, message, data, errors}` envelope across all endpoints |
| `WorkspaceResource.php` | Workspace model |
| `FeedResource.php` | Feed model |
| `PostResource.php` | Post model |
| `SocialCredentialResource.php` | SocialCredential model (omits tokens) |
| `UserResource.php` | User model (omits password) |

All controllers updated to return `ApiResponse::success(new XxxResource(...))` instead of `response()->json($model)`.

---

### 11. PublishService + PostRepository (New)

**Problem:** `FeedPublishController` ran `Post::query()->whereIn(...)->update()` directly — business logic and data access in the controller.

**Created:**
- `Repositories/Contracts/PostRepositoryInterface.php` — `publishApprovedForWorkspace()`, `countApproved/Published/Pending()`
- `Repositories/PostRepository.php` — implements interface
- `Services/PublishService.php` — `publish()`, `getStats()`, `ensurePublicKey()`, `updateSettings()`
- `PostRepository` bound in `AppServiceProvider`

`FeedPublishController` now has 4 thin methods (receive → delegate → return).

---

### 12. FeedSyncController FormRequests (New)

**Problem:** Every `test*` and `*channels/account/pages` action used inline `$request->validate()`.

**Created:** `backend/app/Http/Requests/Sync/`

| FormRequest | Validates |
|---|---|
| `SocialCredentialOnlyRequest.php` | `social_credential_id` — reused by 8 endpoints |
| `TestYouTubeRequest.php` | `social_credential_id` + `youtube_channel_id` |
| `TestRssRequest.php` | `source_url` |
| `TestFacebookRequest.php` | `social_credential_id` + `facebook_page_id` |
| `TestInstagramRequest.php` | `social_credential_id` + `facebook_page_id` + `instagram_business_account_id` |

`FeedSyncController` fully updated to use FormRequests. Inline `$request->validate()` calls eliminated.

---

### 13. View Refactoring (New)

Views migrated to App* component system:

| View | Changes |
|---|---|
| `Login.vue` | `<input>` → `AppInput`, `<button>` → `AppButton`, inline spinner → `AppLoader`, error div → `AppAlert`, labels → `AppFormField` |
| `Register.vue` | Same pattern as Login |
| `WorkspacesList.vue` | `<table>` → `AppTable`, `<button>` → `AppButton`, inline loader → `AppLoader`, empty state → `AppEmptyState`, page header → `AppPageHeader` |
| `WorkspaceForm.vue` | `<input>` → `AppInput`, `<button>` → `AppButton`, form card → `AppCard`, `AppFormField` |
| `Dashboard.vue` | Inline spinner → `AppLoader`, raw analytics divs → `AppCard` + `AppText` + `AppTitle` + `AppButton`, `axios.get()` extracted to `useDashboardAnalytics` composable |

**New composable:** `composables/useDashboardAnalytics.js` — encapsulates all multi-workspace feed fetching, computed analytics, and loading state. Dashboard view is now presentation-only.

---

### 14. ESLint + PHPStan Enforcement (New)

**Frontend — `frontend/eslint.config.js`**

Rules enforced:
- `vue/no-restricted-html-elements` — blocks `<button>`, `<input>`, `<select>`, `<textarea>`, `<table>` in all files except `components/ui/` and `components/layout/`
- `vue/no-restricted-syntax` — blocks inline `style=""` attributes
- `no-restricted-imports` — blocks `axios` import in views/components (only allowed in stores, composables, services)
- `vue/component-api-style` — enforces `<script setup>` only
- `no-console` — warns on console.log

**npm scripts added:**
```bash
npm run lint       # check only
npm run lint:fix   # auto-fix
```

**Backend — `backend/phpstan.neon`**

- Level 6 analysis (strict type checking, return types, undefined methods)
- Extends `larastan/larastan` for Laravel-aware PHPStan rules
- `larastan/larastan` added to `composer.json` require-dev

**composer scripts added:**
```bash
composer lint       # pint --test (dry-run)
composer lint:fix   # pint (auto-format)
composer analyse    # phpstan analyse
```

---

## What Still Needs Attention

1. **Remaining views** — `FeedForm.vue`, `Curate.vue`, `WorkspaceCurate.vue`, `Publish.vue`, `Credentials.vue`, `OAuthApps.vue`, admin views — still use raw HTML and need migration to App* components.

2. **Tests** — Unit tests for service classes, repository implementations, and DTOs should be added.
