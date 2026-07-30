import type { FetchOptions } from 'ofetch'

export function useApiClient() {
  const config = useRuntimeConfig()
  const tokenCookie = useCookie<string | null>('ym_auth_token', { default: () => null })

  const baseUrl = (config.public.apiBaseUrl as string) || 'http://127.0.0.1:8000/api'

  async function apiFetch<T = unknown>(endpoint: string, options: FetchOptions<'json'> = {}): Promise<T> {
    const headers = new Headers(options.headers)

    if (!headers.has('Accept')) {
      headers.set('Accept', 'application/json')
    }

    const isFormData =
      typeof FormData !== 'undefined'
      && options.body instanceof FormData

    if (isFormData) {
      headers.delete('Content-Type')
    } else if (!headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    if (tokenCookie.value) {
      headers.set('Authorization', `Bearer ${tokenCookie.value}`)
    }

    try {
      return await $fetch<T>(`${baseUrl}${endpoint}`, {
        ...options,
        headers
      })
    } catch (error: unknown) {
      if (
        error &&
        typeof error === 'object' &&
        'response' in error &&
        (error as any).response?.status === 401
      ) {
        const { useAuthStore } = await import('~/stores/authStore')
        const authStore = useAuthStore()
        authStore.clearAuth()
      }
      throw error
    }
  }

  return { apiFetch, tokenCookie }
}
