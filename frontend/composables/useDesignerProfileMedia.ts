import type { DesignerProfile } from '~/types/designer-profile'

interface MediaResponse {
  success: boolean
  message: string
  data: {
    profile: DesignerProfile
  }
}

export const useDesignerProfileMedia = () => {
  const config = useRuntimeConfig()
  const token = useCookie<string | null>('ym_auth_token')
  const pending = ref(false)
  const error = ref<string | null>(null)

  const request = async (
    path: string,
    options: {
      method: 'POST' | 'PATCH' | 'DELETE'
      body?: FormData | Record<string, number>
    },
  ): Promise<DesignerProfile> => {
    pending.value = true
    error.value = null

    const isFormData = options.body instanceof FormData
    const headers: Record<string, string> = {
      Accept: 'application/json',
    }

    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    if (!isFormData && options.body) {
      headers['Content-Type'] = 'application/json'
    }

    try {
      const response = await $fetch<MediaResponse>(path, {
        baseURL: config.public.apiBaseUrl,
        method: options.method,
        body: options.body,
        headers,
      })

      return response.data.profile
    } catch (requestError: any) {
      error.value = requestError?.data?.message
        || 'تعذر حفظ الوسائط. تحقق من الملف وحاول مرة أخرى.'
      throw requestError
    } finally {
      pending.value = false
    }
  }

  const uploadAvatar = (file: File) => {
    const form = new FormData()
    form.append('avatar', file)
    return request('/designer/profile/avatar', { method: 'POST', body: form })
  }

  const uploadCover = (file: File) => {
    const form = new FormData()
    form.append('cover', file)
    return request('/designer/profile/cover', { method: 'POST', body: form })
  }

  const deleteAvatar = () =>
    request('/designer/profile/avatar', { method: 'DELETE' })

  const deleteCover = () =>
    request('/designer/profile/cover', { method: 'DELETE' })

  const updateCoverFocalPoint = (x: number, y: number) =>
    request('/designer/profile/cover/focal-point', {
      method: 'PATCH',
      body: { x, y },
    })

  const loadMedia = async (url: string): Promise<Blob> => {
    const headers: Record<string, string> = {
      Accept: 'image/*',
    }

    if (token.value) {
      headers.Authorization = `Bearer ${token.value}`
    }

    return await $fetch<Blob>(url, {
      headers,
      responseType: 'blob',
    })
  }

  return {
    pending: readonly(pending),
    error: readonly(error),
    uploadAvatar,
    deleteAvatar,
    uploadCover,
    deleteCover,
    updateCoverFocalPoint,
    loadMedia,
  }
}
