import { defineStore } from 'pinia'
import type { ApiResponse, AuthData, LoginPayload, RegisterPayload, ResetPasswordPayload, User } from '~/types/auth'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: null as string | null,
    role: null as string | null,
    permissions: [] as string[],
    isAuthenticated: false,
    isLoading: false,
    isInitialized: false,
    error: null as string | null
  }),

  actions: {
    _setAuth(data: AuthData) {
      this.user = data.user
      this.token = data.token
      this.role = data.role
      this.permissions = data.permissions
      this.isAuthenticated = true
      this.error = null
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
        this.permissions = []
        this.isAuthenticated = false
        this.error = null
        this.isLoading = false
      }
    },

    async fetchUser() {
      this.isLoading = true
      try {
        const { apiFetch } = useApiClient()
        const response = await apiFetch<ApiResponse<{ user: User; role?: string; permissions?: string[] }>>('/user')
        if (response.success && response.data) {
          this.user = response.data.user
          this.role = response.data.role ?? null
          this.permissions = response.data.permissions ?? []
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
      this.permissions = []
      this.isAuthenticated = false
      this.isInitialized = true
      this.error = null
    }
  }
})
