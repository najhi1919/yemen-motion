import type {
  DesignerProfileFeaturedWork,
  DesignerProfileFeaturedWorksEnvelope,
  DesignerProfileFeaturedWorksResponse,
} from '~/types/designer-profile-featured-works'

const featuredWorksState = ref<DesignerProfileFeaturedWorksEnvelope | null>(null)
const featuredWorksLoading = ref(false)
const featuredWorksSaving = ref(false)
const featuredWorksError = ref<string | null>(null)
const featuredWorksValidationErrors = ref<Record<string, string[]>>({})
const featuredWorksCoverUrls = ref<Record<number, string>>({})

interface ApiFailure {
  statusCode?: number
  status?: number
  data?: {
    message?: string
    errors?: Record<string, string[]>
    data?: { code?: string }
  }
  response?: {
    status?: number
    _data?: {
      message?: string
      errors?: Record<string, string[]>
      data?: { code?: string }
    }
  }
}

const conflictMessage = 'تغيّرت بيانات الملف في نافذة أخرى. حُمّلت أحدث قائمة محفوظة؛ راجعها ثم أعد التعديل.'

export function useDesignerProfileFeaturedWorks() {
  const { apiFetch, tokenCookie } = useApiClient()
  const config = useRuntimeConfig()
  const apiBaseUrl = String(
    config.public.apiBaseUrl
      || 'http://127.0.0.1:8000/api',
  ).replace(/\/+$/, '')

  const failureData = (failure: unknown) => {
    const candidate = failure as ApiFailure

    return {
      status: candidate.statusCode
        ?? candidate.status
        ?? candidate.response?.status,
      data: candidate.data ?? candidate.response?._data,
    }
  }

  const clearError = () => {
    featuredWorksError.value = null
    featuredWorksValidationErrors.value = {}
  }

  const revokeCoverUrls = () => {
    if (import.meta.client) {
      Object.values(featuredWorksCoverUrls.value)
        .forEach(url => URL.revokeObjectURL(url))
    }

    featuredWorksCoverUrls.value = {}
  }

  const resolveCovers = async (
    works: DesignerProfileFeaturedWork[],
  ): Promise<void> => {
    if (!import.meta.client) return

    const resolved: Record<number, string> = {}
    const handledMediaIds = new Set<number>()

    await Promise.all(works.map(async work => {
      const cover = work.cover_media

      if (
        !cover
        || cover.processing_status !== 'ready'
        || handledMediaIds.has(cover.id)
      ) {
        return
      }

      handledMediaIds.add(cover.id)

      const source = cover.kind === 'video'
        ? cover.poster_url
        : cover.content_url

      if (!source) return

      try {
        const requestUrl = /^https?:\/\//i.test(source)
          ? source
          : `${apiBaseUrl}${
              source.startsWith('/')
                ? source
                : `/${source}`
            }`

        const headers = new Headers({
          Accept: '*/*',
        })

        if (tokenCookie.value) {
          headers.set(
            'Authorization',
            `Bearer ${tokenCookie.value}`,
          )
        }

        const response = await fetch(requestUrl, {
          headers,
        })

        if (!response.ok) {
          throw new Error(
            `Featured work cover failed: ${response.status}`,
          )
        }

        const blob = await response.blob()
        resolved[cover.id] = URL.createObjectURL(blob)
      } catch {
        // تعذر غلاف منفرد لا يمنع إدارة بقية الأعمال.
      }
    }))

    revokeCoverUrls()
    featuredWorksCoverUrls.value = resolved
  }

  async function fetchFeaturedWorks(): Promise<DesignerProfileFeaturedWorksEnvelope> {
    featuredWorksLoading.value = true
    clearError()

    try {
      const response = await apiFetch<DesignerProfileFeaturedWorksResponse>(
        '/designer/profile/featured-works',
      )

      featuredWorksState.value = response.data
      await resolveCovers(response.data.eligible)

      return response.data
    } catch (failure: unknown) {
      const { data } = failureData(failure)

      featuredWorksError.value = data?.message
        || 'تعذر تحميل الأعمال المميزة.'

      throw failure
    } finally {
      featuredWorksLoading.value = false
    }
  }

  async function saveFeaturedWorks(workIds: number[]): Promise<boolean> {
    if (!featuredWorksState.value) {
      featuredWorksError.value = 'حمّل قائمة الأعمال المميزة أولًا.'
      return false
    }

    featuredWorksSaving.value = true
    clearError()

    try {
      const response = await apiFetch<DesignerProfileFeaturedWorksResponse>(
        '/designer/profile/featured-works',
        {
          method: 'PUT',
          body: {
            expected_updated_at:
              featuredWorksState.value.expected_updated_at,
            work_ids: workIds,
          },
        },
      )

      featuredWorksState.value = response.data
      await resolveCovers(response.data.eligible)

      return true
    } catch (failure: unknown) {
      const { status, data } = failureData(failure)

      if (
        status === 409
        && data?.data?.code === 'designer_profile_version_conflict'
      ) {
        try {
          await fetchFeaturedWorks()
        } catch {
          // يبقى تعارض النسخة هو الخطأ الأساسي الظاهر للمستخدم.
        }

        featuredWorksError.value = conflictMessage
      } else {
        featuredWorksError.value = data?.message
          || 'تعذر حفظ الأعمال المميزة.'
      }

      if (status === 422) {
        featuredWorksValidationErrors.value = data?.errors || {}
      }

      return false
    } finally {
      featuredWorksSaving.value = false
    }
  }

  return {
    featuredWorksState: readonly(featuredWorksState),
    loading: readonly(featuredWorksLoading),
    saving: readonly(featuredWorksSaving),
    error: readonly(featuredWorksError),
    validationErrors: readonly(featuredWorksValidationErrors),
    coverUrls: readonly(featuredWorksCoverUrls),
    fetchFeaturedWorks,
    saveFeaturedWorks,
    clearError,
    disposeCoverUrls: revokeCoverUrls,
  }
}
