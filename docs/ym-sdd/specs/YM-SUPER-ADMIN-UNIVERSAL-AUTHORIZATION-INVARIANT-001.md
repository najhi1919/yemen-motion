# Super Admin Universal Authorization Invariant

**Task:** `YM-SUPER-ADMIN-UNIVERSAL-AUTHORIZATION-INVARIANT-001`
**Scope:** Backend authorization, authenticated-user payload, frontend
permission presentation, and the current works taxonomy interface.

## Stable Identity

The canonical role identifier is `super-admin`, declared by
`yemen-motion-permissions.super_admin_role`. Authorization never infers this
identity from a translated label, display name, email address, or database ID.
`User::isSuperAdmin()` is the application-level identity check.

## Backend Source of Truth

`AppServiceProvider` registers a global `Gate::before` callback. It returns
`true` only for an authenticated user whose stable role is `super-admin`, and
returns `null` for every other user so Laravel policies, gates, Form Requests,
controller checks, and Spatie permission middleware continue normally.

The bypass authorizes current and future abilities without requiring a direct
user permission or role-permission pivot. Seeder grants remain synchronized
for permission-catalog and role-management visibility, but they are not a
runtime authorization prerequisite.

## Permission Versus Business Rules

The bypass removes only permission denial. It does not bypass authentication,
Sanctum, request validation, state transitions, readiness blockers,
optimistic concurrency, transaction locks, unique constraints, media
processing, file type or size limits, environmental limits, or missing
resources. A super admin may invoke an authorized operation and still receive
`404`, `409`, or `422` when its business contract is not satisfied.

## Authenticated Session Contract

Register, login, and current-user responses preserve their existing keys and
add the server-derived Boolean `is_super_admin`. When a user has multiple
roles, the compatibility `role` value is normalized to `super-admin` whenever
the canonical role is present. The Boolean is derived from the database role
relationship and is never accepted from client input.

## Frontend Permission Helper

The Pinia auth store owns `isSuperAdmin`, `can(permission)`,
`canAny(permissions)`, and `canAll(permissions)`. An authenticated super admin
passes every permission helper even when the session permission array is
empty. Ordinary users continue to use the permission array. Guests, expired
sessions, empty ability names, and unloaded sessions always fail closed.

Route middleware, the administrative sidebar, and works taxonomy capability
flags consume this central contract. Editing browser state can reveal a
client-side control but cannot grant server access because the backend Gate is
authoritative.

## Taxonomy Interface

The taxonomy page now resolves category and tag view, create, update, disable,
and merge controls through the auth-store helper. Therefore a super admin sees
the existing management UI even without material permission grants. No new
taxonomy endpoint or operation is introduced.

## Security Constraints

- `is_super_admin` is output-only and derived on the server.
- Display names and translated labels never grant authorization.
- `client`, `designer`, and ordinary internal roles receive no bypass.
- Authentication middleware remains mandatory.
- Permission denial auditing and all business validation remain active.
- Storage, protected media, review decisions, and workflow services are
  unchanged.

## Testing Strategy

The focused feature test removes every super-admin role grant, then verifies
all dynamically registered abilities through Laravel Gate, Spatie permission
middleware, a representative Form Request, taxonomy mutations, the current
user payload, and ordinary-role denial. It also submits an incomplete draft to
prove readiness validation still returns `422`.

No frontend test framework exists in the project. The auth helper therefore
requires the normal frontend build and a manual session check after the user
runs verification.

## Adding Future Permissions

Add the permission to the established registry or protected permission
management flow and keep ordinary-role grants explicit. No super-admin grant,
frontend exception, or page-specific `isSuperAdmin || permissions.includes`
condition is required; the central backend Gate and frontend helper apply the
invariant automatically.
