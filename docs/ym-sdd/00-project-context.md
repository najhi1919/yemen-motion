# Project Context

- Local path: `/home/kali/projects/yemen-motion`
- Laravel backend exists at the project root.
- Nuxt frontend exists inside `frontend/`.
- Current baseline: `9a7c91a fix: polish topbar and dashboard hero visuals`.
- `/admin` is the reference template for the remaining platform pages.
- `/staff` exists.
- Placeholder pages exist:
  - `frontend/pages/admin/[...slug].vue`
  - `frontend/pages/staff/[...slug].vue`

## Sensitive Areas

- Auth
- `frontend/stores/authStore.ts`
- `frontend/composables/useApiClient.ts`
- middleware
- roles / permissions
- routes
- migrations
- `.env`
- dependencies

Do not state implementation details such as Sanctum or Spatie as facts unless
they have been verified by direct inspection.
