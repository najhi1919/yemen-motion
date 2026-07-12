import type { RouteLocationNormalized } from 'vue-router'

const INTERNAL_ROLES = new Set(['super-admin', 'admin', 'staff'])

type TrackableRoute = Pick<RouteLocationNormalized, 'name' | 'path'>

function isTrackedPath(path: string): boolean {
  return path === '/admin'
    || path.startsWith('/admin/')
    || path === '/staff'
    || path.startsWith('/staff/')
}

function pageKeyForRoute(route: TrackableRoute): string {
  const routeName = typeof route.name === 'string' ? route.name : ''
  const source = routeName || route.path
  const normalized = source
    .toLowerCase()
    .replace(/^\/+|\/+$/g, '')
    .replace(/[^a-z0-9._-]+/g, '.')
    .replace(/^[._-]+|[._-]+$/g, '')
    .slice(0, 120)

  if (normalized.length >= 2) return normalized

  return route.path === '/staff' || route.path.startsWith('/staff/')
    ? 'staff.page'
    : 'admin.page'
}

function sectionForPath(path: string): string {
  const matches = (prefix: string) => path === prefix || path.startsWith(`${prefix}/`)

  if (matches('/admin/users')) return 'users'
  if (matches('/admin/staff')) return 'staff'
  if (matches('/admin/roles')) return 'roles'
  if (matches('/admin/permissions')) return 'permissions'
  if (matches('/admin/reports')) return 'reports'
  if (matches('/admin/analytics')) return 'analytics'
  if (path === '/admin' || path === '/admin/') return 'dashboard'
  if (path === '/staff' || path.startsWith('/staff/')) return 'staff'

  return 'general'
}

export default defineNuxtPlugin((nuxtApp) => {
  const router = useRouter()
  const authStore = useAuthStore()
  const { apiFetch } = useApiClient()
  let currentPath: string | null = null
  let currentPathTracked = false

  const trackRoute = (route: TrackableRoute): void => {
    // نعتمد route.path فقط، لذلك لا يدخل query string أو hash أو رابط كامل في الطلب.
    const path = route.path

    if (path !== currentPath) {
      currentPath = path
      currentPathTracked = false
    }

    if (
      currentPathTracked
      || !isTrackedPath(path)
      || !authStore.isInitialized
      || !authStore.isAuthenticated
      || !authStore.role
      || !INTERNAL_ROLES.has(authStore.role)
    ) {
      return
    }

    currentPathTracked = true

    // الإرسال مركزي وغير حاجب للتنقل، وأي فشل يبقى صامتًا دون التأثير على الواجهة.
    void apiFetch('/audit/page-view', {
      method: 'POST',
      body: {
        page_key: pageKeyForRoute(route),
        path,
        section: sectionForPath(path)
      }
    }).catch(() => {})
  }

  router.afterEach((to) => {
    trackRoute(to)
  })

  watch(
    () => [authStore.isInitialized, authStore.isAuthenticated, authStore.role] as const,
    () => trackRoute(router.currentRoute.value),
    { flush: 'post' }
  )

  nuxtApp.hook('app:mounted', () => {
    trackRoute(router.currentRoute.value)
  })
})
