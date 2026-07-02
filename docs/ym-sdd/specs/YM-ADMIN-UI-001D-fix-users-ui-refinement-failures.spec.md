# YM-ADMIN-UI-001D — Fix Users UI Refinement Failures Spec

## Scope

This task corrects focused failures from YM-ADMIN-UI-001C without redesigning the admin pages.

Allowed touched areas:

- `app/Http/Controllers/Api/Admin/UserController.php`
- `frontend/pages/admin/users/index.vue`
- `frontend/pages/admin/staff/index.vue`
- `frontend/pages/admin/roles/index.vue`

No routes, auth, middleware, models, migrations, seeders, API client, store, layouts, sidebar, topbar, or shared component changes are included.

## Users Feedback Addressed

The users page required fixes for:

- Unacceptable users hero gradient.
- Summary card accents not being visually clear.
- Role filter buttons looking too plain.
- Name and email not truncating visibly.
- Email alignment drifting away from the email column.
- Role badge hover tooltip reliability.
- Column resizing not visibly changing table widths.
- Date format being English but not in the required order.
- Users/staff sorting incorrectly applying only to the current page.
- ID order starting from descending backend pagination by default.

## Limited Backend Sorting Change

Users and staff share the `/admin/users` paginated endpoint. Frontend-only sorting cannot correctly sort across all pages.

`UserController@index` now accepts:

- `sort_by`
- `sort_direction`

Allowed `sort_by` values:

- `id`
- `name`
- `email`
- `created_at`

Allowed `sort_direction` values:

- `asc`
- `desc`

Defaults:

- `sort_by = id`
- `sort_direction = asc`

The backend applies `orderBy` before `paginate`, while preserving the existing response shape, endpoint, admin authorization, role filtering, and existing search handling.

## Users And Staff Frontend Sorting

Users and staff now keep:

- `sortBy`
- `sortDirection`

Clicking a sortable header updates the sort state, resets `page` to `1`, and refetches data from `/admin/users` with:

- `sort_by`
- `sort_direction`

Local current-page sorting was removed from users and staff.

## Roles Sorting

Roles remain frontend-only because `/admin/roles` currently has no pagination.

Sortable roles columns remain:

- `id`
- `name`
- `guard_name`
- `users_count`
- `permissions_count`
- `created_at`

## Visual Fixes

The users hero gradient was improved with a deeper emerald/green mix and a Yemen Motion red/cyan accent while preserving:

- orbs
- grid overlay
- chips
- summary panel
- dashboard-like style

Summary cards in users, staff, and roles now use visible `--card-accent` treatments:

- tinted border
- radial background tint
- top glowing line
- accent dot

The users role filter remains button-based and now uses larger dashboard-style pills with:

- role colors
- visible borders
- strong active state
- hover state
- focus-visible state

## Table Text And Badges

Users and staff names use a truncated inline container with native `title` tooltips.

Users and staff emails use an LTR truncated inline container with:

- `direction: ltr`
- `unicode-bidi: plaintext`
- `text-align: left`
- native `title` tooltip

Role badges keep fixed visual sizing and native `title` tooltips.

## Resizable Columns

Users, staff, and roles tables use:

- `<colgroup>`
- reactive `columnWidths`
- computed table width from the column widths
- `table-layout: fixed`
- `@pointerdown.stop.prevent`
- window-level `pointermove` and `pointerup` listeners
- minimum column widths

No external table library and no persisted resize settings are used.

## Date Format

Users, staff, and roles dates use fixed Latin digit formatting:

```text
YYYY-MM-DD HH:mm
```

Example:

```text
2026-07-02 05:10
```

## Explicit Non-Goals

This task does not:

- Add internal search.
- Add create, edit, or delete actions.
- Change routes.
- Change auth.
- Change middleware.
- Change frontend shared components.
- Change layouts, sidebar, or topbar.
- Add external libraries.
