export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()

  if (!authStore.isInitialized) {
    await authStore.hydrateAuth()
  }

  const publicRoutes: string[] = [
    '/auth/login',
    '/auth/register',
    '/auth/forgot-password',
    '/auth/reset-password'
  ]
  const isPublicRoute = publicRoutes.includes(to.path)

  const adminRoles = ['super-admin', 'admin']
  const internalDashboardRoles = [...adminRoles, 'staff']

  const routeRoleMap: Record<string, string[]> = {
    '/admin': internalDashboardRoles,
    '/staff': internalDashboardRoles,
    '/designer': ['designer'],
    '/client': ['client', ...adminRoles]
  }

  const roleHomeMap: Record<string, string> = {
    'super-admin': '/admin',
    admin: '/admin',
    staff: '/staff',
    designer: '/designer',
    client: '/client'
  }

  if (authStore.isAuthenticated && isPublicRoute) {
    const target = authStore.isSuperAdmin
      ? '/admin'
      : roleHomeMap[authStore.role || ''] || '/'
    if (to.path !== target) {
      return navigateTo(target)
    }
    return
  }

  const matchedProtectedPrefix = Object.keys(routeRoleMap).find((prefix) =>
    to.path === prefix || to.path.startsWith(prefix + '/')
  )

  if (matchedProtectedPrefix) {
    if (!authStore.isAuthenticated) {
      return navigateTo(`/auth/login?redirect=${encodeURIComponent(to.path)}`)
    }

    if (matchedProtectedPrefix === '/designer') {
      if (authStore.hasRole('designer')) {
        return
      }

      const fallback = authStore.isSuperAdmin || authStore.hasRole('admin')
        ? '/admin'
        : authStore.hasRole('staff')
          ? '/staff'
          : authStore.hasRole('client')
            ? '/client'
            : roleHomeMap[authStore.role || ''] || '/'

      if (to.path !== fallback) {
        return navigateTo(fallback)
      }

      return
    }

    const allowedRoles = routeRoleMap[matchedProtectedPrefix]
    if (!authStore.isSuperAdmin && !allowedRoles.includes(authStore.role || '')) {
      const fallback = roleHomeMap[authStore.role || ''] || '/'
      if (to.path !== fallback) {
        return navigateTo(fallback)
      }
    }
  }
})
