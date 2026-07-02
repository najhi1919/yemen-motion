# YM-ADMIN-UI-001C — Admin Users Shared Table Refinements Spec

## Scope

This refinement keeps the existing YM-ADMIN-UI-001B admin visual direction and applies focused updates to:

- `frontend/pages/admin/users/index.vue`
- `frontend/pages/admin/staff/index.vue`
- `frontend/pages/admin/roles/index.vue`

No backend, routes, auth, API client, layout, sidebar, or shared component changes are part of this task.

## Users Page Notes

- The users page keeps the existing `/admin/users` endpoint, pagination, loading, error, empty, and read-only table states.
- The internal search remains absent.
- The role filter remains functional but is now rendered as dashboard-style pill buttons instead of a `select`.
- The role options include `All roles`, `admin`, `staff`, `client`, `designer`, and any unique role returned through `availableRoles`.

## Shared Admin Table Rules

The users, staff, and roles admin pages share these frontend-only table refinements:

- Sortable table headers use `<button type="button">` inside sortable `<th>` elements.
- Sorting is frontend-only and applies only to the currently displayed data.
- Sorting does not change API endpoints, request query params, or pagination behavior.
- Sort indicators show inactive and active direction states.
- Columns use local `columnWidths` reactive state and `<colgroup>`.
- Header resize handles allow horizontal column resizing without any external table library.
- Resize state is not persisted to `localStorage`.

## Sortable Columns

Users and staff:

- `id`
- `name`
- `email`
- `created_at`

Roles:

- `id`
- `name`
- `guard_name`
- `users_count`
- `permissions_count`
- `created_at`

## Hero Section Colors

Each admin subsection keeps the dashboard-like hero style with gradients, orbs, grid overlay, chips, summary panel, large soft cards, and a rich table card.

Local temporary section accent colors are used inside each page:

- Users: `#10b981`
- Staff: `#06b6d4`
- Roles: `#8b5cf6`

These are page-local values only and can later be connected to admin settings.

## Summary Card Accents

Summary cards now receive individual accent colors through inline CSS variables.

The accent is used for card glow, border hover treatment, and the top line while preserving the existing visual card style and displayed data.

## Text Clamping And Tooltips

Users and staff tables clamp long names and emails with ellipsis.

- Names use `title` attributes for full-value hover tooltips.
- Emails are displayed in an LTR container with `unicode-bidi: plaintext`, ellipsis, and `title` attributes.
- Role badges use consistent minimum sizing, centered content, ellipsis, and `title` attributes.

## Latin Digits And Dates

Users, staff, and roles dates are formatted with `Intl.DateTimeFormat('en-GB', { numberingSystem: 'latn' })`.

IDs, counts, pagination numbers, and date output remain Latin/English digits even when the Arabic UI locale is active.

## Explicit Non-Goals

This task does not:

- Modify backend code.
- Modify routes.
- Modify auth.
- Modify models, controllers, migrations, or seeders.
- Modify `frontend/composables/useApiClient.ts`.
- Modify `frontend/stores/authStore.ts`.
- Add internal search.
- Add create, edit, or delete actions.
- Add or change API endpoints.
- Add external table libraries.
