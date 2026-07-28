import { defineStore } from 'pinia'
import type { ApiResponse, AuthData, LoginPayload, RegisterPayload, ResetPasswordPayload, User } from '~/types/auth'

type AuthenticatedUser = User & { roles: string[] }
type UserRolesPayload = User & { roles?: unknown }

function normalizedRoles(user: UserRolesPayload, fallbackRole?: string | null): string[] {
  const source = Array.isArray(user.roles)
    ? user.roles
    : fallbackRole
      ? [fallbackRole]
      : []

  return [...new Set(
    source
      .filter((role): role is string => typeof role === 'string' && role.trim().length > 0)
      .map(role => role.trim())
  )].sort()
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthenticatedUser | null,
    token: null as string | null,
    role: null as string | null,
    roles: [] as string[],
    permissions: [] as string[],
    superAdmin: false,
    isAuthenticated: false,
    isLoading: false,
    isInitialized: false,
    error: null as string | null
  }),

  getters: {
    isSuperAdmin: (state): boolean => state.isAuthenticated && state.superAdmin
  },

  actions: {
    _setAuth(data: AuthData) {
      this.token = data.token
      this.role = data.role
      this.roles = normalizedRoles(data.user as UserRolesPayload, data.role)
      this.user = { ...data.user, roles: this.roles }
      this.permissions = Array.isArray(data.permissions) ? data.permissions : []
      this.superAdmin = data.is_super_admin === true
      this.isAuthenticated = true
      this.error = null
    },

    hasRole(roleName: string): boolean {
      return Boolean(roleName) && this.roles.includes(roleName)
    },

    can(permission: string): boolean {
      if (!this.isAuthenticated || !permission) return false
      if (this.isSuperAdmin) return true

      return this.permissions.includes(permission)
    },

    canAny(permissions: string[]): boolean {
      if (!this.isAuthenticated || permissions.length === 0) return false

      return permissions.some(permission => this.can(permission))
    },

    canAll(permissions: string[]): boolean {
      if (!this.isAuthenticated || permissions.length === 0) return false

      return permissions.every(permission => this.can(permission))
    },

    async register(payload: RegisterPayload) {
      this.isLoading = true
      this.error = null
      try {
        const { apiFetch, tokenCookie } = useApiClient()
        const response = await apiFetch<ApiResponse<AuthData>>('/auth/register', {
          method: 'POST',
          body: payload
        })
        if (response.success && response.data) {
          this._setAuth(response.data)
          tokenCookie.value = response.data.token
        }
        return response
      } catch (error: unknown) {
        const err = error as any
        this.error = err?.data?.message || err?.message || 'حدث خطأ في التسجيل'
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async login(payload: LoginPayload) {
      this.isLoading = true
      this.error = null
      try {
        const { apiFetch, tokenCookie } = useApiClient()
        const response = await apiFetch<ApiResponse<AuthData>>('/auth/login', {
          method: 'POST',
          body: payload
        })
        if (response.success && response.data) {
          this._setAuth(response.data)
          tokenCookie.value = response.data.token
        }
        return response
      } catch (error: unknown) {
        const err = error as any
        this.error = err?.data?.message || err?.message || 'فشل تسجيل الدخول'
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async logout() {
      this.isLoading = true
      try {
        const { apiFetch, tokenCookie } = useApiClient()
        await apiFetch('/auth/logout', { method: 'POST' })
        tokenCookie.value = null
      } catch {
        // Clear auth regardless of API outcome
      } finally {
        this.user = null
        this.token = null
        this.role = null
        this.roles = []
        this.permissions = []
        this.superAdmin = false
        this.isAuthenticated = false
        this.error = null
        this.isLoading = false
      }
    },

    async fetchUser() {
      this.isLoading = true
      try {
        const { apiFetch } = useApiClient()
        const response = await apiFetch<ApiResponse<{
          user: User
          role?: string
          permissions?: string[]
          is_super_admin?: boolean
        }>>('/user')
        if (response.success && response.data) {
          this.role = response.data.role ?? null
          this.roles = normalizedRoles(
            response.data.user as UserRolesPayload,
            this.role
          )
          this.user = { ...response.data.user, roles: this.roles }
          this.permissions = response.data.permissions ?? []
          this.superAdmin = response.data.is_super_admin === true
          this.isAuthenticated = true
        }
        return response
      } catch (error: unknown) {
        const err = error as any
        if (err?.response?.status === 401) {
          this.clearAuth()
        } else {
          this.error = err?.data?.message || err?.message || 'فشل تحميل بيانات المستخدم'
        }
      } finally {
        this.isLoading = false
        this.isInitialized = true
      }
    },

    async forgotPassword(email: string) {
      this.isLoading = true
      this.error = null
      try {
        const { apiFetch } = useApiClient()
        const response = await apiFetch<ApiResponse<null>>('/auth/forgot-password', {
          method: 'POST',
          body: { email }
        })
        return response
      } catch (error: unknown) {
        const err = error as any
        this.error = err?.data?.message || err?.message || 'فشل إرسال رابط استعادة كلمة المرور'
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async resetPassword(payload: ResetPasswordPayload) {
      this.isLoading = true
      this.error = null
      try {
        const { apiFetch } = useApiClient()
        const response = await apiFetch<ApiResponse<null>>('/auth/reset-password', {
          method: 'POST',
          body: payload
        })
        return response
      } catch (error: unknown) {
        const err = error as any
        this.error = err?.data?.message || err?.message || 'فشل إعادة تعيين كلمة المرور'
        throw error
      } finally {
        this.isLoading = false
      }
    },

    async hydrateAuth() {
      const { tokenCookie } = useApiClient()
      const savedToken = tokenCookie.value
      if (savedToken) {
        this.token = savedToken
        this.isAuthenticated = true
        await this.fetchUser()
      } else {
        this.isInitialized = true
      }
    },

    clearAuth() {
      const { tokenCookie } = useApiClient()
      tokenCookie.value = null
      this.user = null
      this.token = null
      this.role = null
      this.roles = []
      this.permissions = []
      this.superAdmin = false
      this.isAuthenticated = false
      this.isInitialized = true
      this.error = null
    }
  }
})
