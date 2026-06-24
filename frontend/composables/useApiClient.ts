import type { FetchOptions } from 'ofetch'

export function useApiClient() {
  const config = useRuntimeConfig()
  const tokenCookie = useCookie<string | null>('ym_auth_token', { default: () => null })

  const baseUrl = (config.public.apiBaseUrl as string) || 'http://127.0.0.1:8000/api'

  async function apiFetch<T = unknown>(endpoint: string, options: FetchOptions<'json'> = {}): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(options.headers as Record<string, string> | undefined)
    }

    if (tokenCookie.value) {
      headers.Authorization = `Bearer ${tokenCookie.value}`
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
