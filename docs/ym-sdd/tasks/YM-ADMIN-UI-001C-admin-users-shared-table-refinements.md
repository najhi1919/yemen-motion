# YM-ADMIN-UI-001C — Admin Users Shared Table Refinements Task

## Checklist

- [x] Kept the existing dashboard-like admin visual style.
- [x] Updated users hero accent to `#10b981`.
- [x] Updated staff hero accent to `#06b6d4`.
- [x] Updated roles hero accent to `#8b5cf6`.
- [x] Added per-card summary accents on users.
- [x] Added per-card summary accents on staff.
- [x] Added per-card summary accents on roles.
- [x] Converted users role filter from `select` to dashboard-style pill buttons.
- [x] Preserved users role filtering behavior.
- [x] Kept internal search absent.
- [x] Added frontend-only sorting to users columns: `id`, `name`, `email`, `created_at`.
- [x] Added frontend-only sorting to staff columns: `id`, `name`, `email`, `created_at`.
- [x] Added frontend-only sorting to roles columns: `id`, `name`, `guard_name`, `users_count`, `permissions_count`, `created_at`.
- [x] Added sort direction indicators to sortable headers.
- [x] Added name ellipsis and `title` tooltips on users.
- [x] Added email ellipsis, LTR direction, plaintext bidi, and `title` tooltips on users.
- [x] Added name ellipsis and `title` tooltips on staff.
- [x] Added email ellipsis, LTR direction, plaintext bidi, and `title` tooltips on staff.
- [x] Standardized users role badge width, centering, ellipsis, and `title` tooltip.
- [x] Standardized staff role badge width, centering, ellipsis, and `title` tooltip.
- [x] Added resizable table columns to users without external libraries.
- [x] Added resizable table columns to staff without external libraries.
- [x] Added resizable table columns to roles without external libraries.
- [x] Kept resize state page-local and non-persistent.
- [x] Ensured created dates use Latin/English digits.
- [x] Kept users endpoint as `/admin/users`.
- [x] Kept staff endpoint as `/admin/users` with fixed `role: staff`.
- [x] Kept roles endpoint as `/admin/roles`.
- [x] Did not add create, edit, or delete actions.
- [x] Did not modify backend, routes, auth, API client, store, layouts, sidebar, or shared components.
- [x] Did not run build or tests, per instruction.
- [x] Did not create a commit or push changes.
