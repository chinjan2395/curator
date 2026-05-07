# Strict Frontend + Backend Architecture & Design System Rules

## Project Context

You are working on an enterprise-scale application with:

- Frontend: Vue.js
- Backend: Laravel

Both projects already contain many modules and existing business logic.

Your responsibility is NOT only to implement features. Your primary responsibility is to preserve:

- visual consistency
- architectural consistency
- predictable code structure
- reusable design system
- strict maintainability

This project must behave like a controlled framework, NOT a free-style development environment.

---

# Core Philosophy

The application must follow:

- Design System Driven Development
- Component-First Architecture
- Strict Reusable UI Rules
- Zero freestyle UI implementation
- Strict Backend Layer Separation
- Predictable folder structure
- Controlled developer behavior

Developers are NOT allowed to:

- invent new UI styles
- add random classes
- directly use native HTML UI elements in pages
- create ad-hoc layouts
- create business logic inside controllers/pages/components
- bypass architecture layers

Everything must use centralized reusable abstractions.

---

# FRONTEND RULES (Vue.js)

# Primary Objective

Every screen in the application should feel like:

- same design language
- same spacing system
- same typography
- same component behavior
- same layout rules
- same interaction patterns

A developer should NOT be able to visually identify who developed a page.

The application must look like it was developed by a single system.

---

# Strict UI System

## NEVER USE RAW HTML DIRECTLY IN PAGES

Inside pages/views, developers MUST NOT directly use:

- button
- input
- select
- textarea
- table
- modal
- card
- h1-h6
- p
- span for text rendering
- div for layout styling
- native checkbox/radio
- badges
- alerts
- tabs
- dropdowns
- loaders
- pagination
- tooltips

Instead ONLY use predefined reusable components.

### BAD

```vue
<button class="bg-blue-500 px-4 py-2 rounded">
 Save
</button>
```

### GOOD

```vue
<AppButton variant="primary">
 Save
</AppButton>
```

---

# Design System Is Mandatory

All visual elements MUST come from centralized design system components.

## Required Reusable Components

- AppPage
- AppSection
- AppCard
- AppButton
- AppInput
- AppSelect
- AppCheckbox
- AppTable
- AppModal
- AppTabs
- AppBadge
- AppAlert
- AppTypography
- AppTitle
- AppSubtitle
- AppText
- AppForm
- AppFormField
- AppEmptyState
- AppLoader
- AppPagination
- AppDropdown
- AppTooltip

No duplicates allowed.

If functionality exists already: DO NOT recreate it.

---

# No Inline Styling

STRICTLY FORBIDDEN:

- inline style=""
- random Tailwind classes
- page-specific styling
- component-level freestyle spacing
- hardcoded colors
- hardcoded font sizes
- hardcoded border radius
- hardcoded shadows

Everything must come from:

- design tokens
- shared utility system
- centralized theme configuration

---

# Design Tokens Only

Use centralized tokens for:

- spacing
- colors
- typography
- border radius
- shadow
- z-index
- animations
- transitions
- responsive breakpoints

### Example

```js
spacing.sm
spacing.md
spacing.lg

colors.primary
colors.danger

radius.md
shadow.card
```

No magic values allowed.

---

# Typography System

Text rendering MUST use predefined typography components.

### BAD

```vue
<h2 class="text-2xl font-bold">
 Users
</h2>
```

### GOOD

```vue
<AppTitle>
 Users
</AppTitle>
```

All typography must follow:

- fixed font scale
- fixed weight scale
- fixed line-height system
- fixed semantic usage

---

# Strict Layout System

Pages must follow fixed layout primitives.

## Allowed Layout Components

- AppPage
- AppContainer
- AppGrid
- AppStack
- AppSection

No freestyle nested div structures.

### BAD

```vue
<div class="p-7 flex gap-5">
```

### GOOD

```vue
<AppStack spacing="lg">
```

---

# Table System

Tables are highly controlled.

Developers MUST NOT:

- create custom tables
- add custom table styling
- use raw table tags directly

All tables MUST use:

```vue
<AppTable />
```

Supported through configuration only:

- columns
- sorting
- pagination
- actions
- loading
- empty state

No custom visual styling per page.

---

# Form System

All forms must use centralized form system.

## Required

- validation abstraction
- reusable field wrapper
- consistent error display
- consistent labels
- consistent spacing
- centralized form rules

No direct form layouts allowed.

---

# Modal System

Only centralized modal system allowed.

No local modal implementations.

All modals must:

- use same spacing
- same animation
- same header/footer
- same close behavior
- same overlay
- same accessibility rules

---

# Page Structure Rules

Every page must follow same structure.

### Example

```vue
<AppPage>
  <AppPageHeader />

  <AppSection>
    <AppCard>
      <AppTable />
    </AppCard>
  </AppSection>
</AppPage>
```

No freestyle page composition.

---

# Component Architecture

Components must follow:

- Presentational components
- Business components
- Layout components
- Shared system components

No mixed responsibilities.

---

# Business Logic Rules

Pages must NOT contain:

- API calls
- transformation logic
- validation logic
- business rules

Use:

- composables
- services
- stores
- domain modules

---

# API Layer Rules

API communication must be centralized.

No direct axios/fetch usage inside components.

## Required Structure

```txt
services/
  user/
    user.api.ts
    user.mapper.ts
    user.types.ts
```

---

# State Management Rules

No random state logic.

## Global State

- Pinia/Vuex only

## Local State

- component-scoped only

Business state must never be duplicated.

---

# Component Creation Policy

Before creating any new component:

Developer MUST verify:

1. Does similar component already exist?
2. Can existing component be extended?
3. Can variant system solve this?

New components should be rare.

---

# Frontend Folder Structure

```txt
src/
  components/
    ui/
    layout/
    business/
  pages/
  modules/
  services/
  stores/
  composables/
  types/
  utils/
  design-system/
```

No random folder creation.

---

# Frontend Strict Code Review Rules

Reject PR immediately if:

- raw button/input/table exists
- custom spacing added
- inline style used
- duplicate component created
- API call inside page/component
- business logic mixed with UI
- random Tailwind classes used
- inconsistent typography used
- design token bypassed

Consistency is more important than speed.

---

# BACKEND RULES (Laravel)

# Primary Objective

Backend must be:

- modular
- predictable
- interface-driven
- highly maintainable
- testable
- scalable

No shortcut coding allowed.

---

# Strict Layered Architecture

Mandatory layers:

```txt
Controller
  -> Service
    -> Action
      -> Repository
        -> Model
```

Controllers must stay extremely thin.

Controllers should ONLY:

- receive request
- call service
- return response

No business logic inside controllers.

---

# No Fat Controllers

### BAD

```php
public function store(Request $request)
{
    $user = User::create([...]);

    Mail::send(...);

    return response()->json(...);
}
```

### GOOD

```php
public function store(CreateUserRequest $request)
{
    return $this->userService->create($request->validated());
}
```

---

# Strict Service Layer

All business rules belong in services/actions.

Services handle:

- workflows
- orchestration
- transactions
- domain coordination

Repositories handle:

- database access only

---

# Repository Rules

Repositories must:

- abstract Eloquent
- centralize queries
- avoid duplicated query logic

Controllers/services must NOT directly query models.

### BAD

```php
User::where(...)->get();
```

### GOOD

```php
$this->userRepository->getActiveUsers();
```

---

# Request Validation Rules

All validation MUST use Form Requests.

No inline validation.

### BAD

```php
$request->validate([...]);
```

### GOOD

```php
CreateUserRequest
```

---

# Response Structure Rules

All API responses must follow same format.

### Example

```json
{
  "success": true,
  "message": "User created successfully",
  "data": {},
  "errors": null
}
```

No inconsistent API response structures.

---

# No Business Logic In Models

Models should remain lightweight.

Avoid:

- large business methods
- workflow logic
- service orchestration

Use domain services/actions instead.

---

# Strict Module Structure

```txt
Modules/
  User/
    Controllers/
    Requests/
    Services/
    Actions/
    Repositories/
    DTOs/
    Resources/
    Policies/
    Models/
    Tests/
```

No random file placement.

---

# DTO Usage Is Required

Use DTOs for:

- service communication
- request mapping
- response transformation

Avoid passing raw arrays everywhere.

---

# Interface-Driven Development

Repositories and services should use interfaces.

### Example

```php
UserRepositoryInterface
UserServiceInterface
```

Bind implementations via service container.

---

# Database Rules

- No raw queries unless necessary
- No duplicated query conditions
- Use migrations consistently
- Use transactions for critical workflows
- Avoid N+1 queries
- Use eager loading intentionally

---

# Error Handling Rules

All exceptions must be:

- centralized
- logged properly
- transformed into consistent API responses

No random try/catch everywhere.

---

# Testing Rules

Mandatory:

- feature tests
- unit tests
- service tests
- repository tests

Critical business logic MUST be tested.

---

# Naming Rules

Names must be:

- explicit
- readable
- domain-oriented

### BAD

```php
doStuff()
data()
handle()
```

### GOOD

```php
createUserAccount()
calculateInvoiceTotal()
syncNutritionGoals()
```

---

# No Duplicated Logic

If logic appears more than once: extract it immediately.

---

# Enforcement Rules

## Mandatory Enforcement Tools

Frontend:

- ESLint
- Prettier
- Stylelint
- strict TypeScript rules
- import sorting
- component restrictions
- Tailwind class restrictions

Backend:

- PHPStan
- Laravel Pint
- Rector
- PHPUnit
- architectural tests

CI/CD must fail if rules are violated.

---

# Pull Request Rules

Every PR must:

- follow existing patterns
- avoid duplication
- use existing components
- preserve design consistency
- preserve architecture consistency
- include tests if business logic changes

PRs must be rejected if:

- architecture boundaries are violated
- design system is bypassed
- custom UI implementation added
- duplicated logic introduced
- folder structure ignored
- naming conventions ignored

---

# Development Philosophy

The project is NOT:

- a playground
- rapid prototype environment
- freestyle codebase

The project is

- a controlled architecture system
- a reusable design system
- a strict engineering environment

Every developer must prioritize:

1. consistency
2. reusability
3. predictability
4. maintainability
5. scalability

over:
- personal coding style
- speed shortcuts
- visual experimentation