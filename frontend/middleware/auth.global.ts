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

  const routeRoleMap: Record<string, string[]> = {
    '/admin': ['admin'],
    '/staff': ['staff', 'admin'],
    '/designer': ['designer', 'admin'],
    '/client': ['client', 'admin']
  }

  const roleHomeMap: Record<string, string> = {
    admin: '/admin',
    staff: '/staff',
    designer: '/designer',
    client: '/'
  }

  if (authStore.isAuthenticated && isPublicRoute) {
    const target = roleHomeMap[authStore.role || ''] || '/'
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

    const allowedRoles = routeRoleMap[matchedProtectedPrefix]
    if (!allowedRoles.includes(authStore.role || '')) {
      const fallback = roleHomeMap[authStore.role || ''] || '/'
      if (to.path !== fallback) {
        return navigateTo(fallback)
      }
    }
  }
})
