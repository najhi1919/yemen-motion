import type {
  DesignerProfilePreview,
  DesignerProfilePreviewResponse,
  DesignerProfilePublicationActionResponse,
  DesignerProfilePublicationState,
  DesignerProfilePublicationResponse,
} from '~/types/designer-profile-publication'

const publicationState = ref<DesignerProfilePublicationState | null>(null)
const previewState = ref<DesignerProfilePreview | null>(null)
const publicationLoading = ref(false)
const previewLoading = ref(false)
const publicationSaving = ref(false)
const publicationError = ref<string | null>(null)
const previewError = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]>>({})

interface ApiFailure {
  statusCode?: number
  status?: number
  data?: { message?: string, errors?: Record<string, string[]>, data?: { code?: string } }
  response?: { status?: number, _data?: { message?: string, errors?: Record<string, string[]>, data?: { code?: string } } }
}

const conflictMessage = 'تغيّرت بيانات الملف في نافذة أخرى. راجع الحالة الحالية ثم أعد المحاولة.'

export function useDesignerProfilePublication() {
  const { apiFetch } = useApiClient()

  const failureData = (failure: unknown) => {
    const candidate = failure as ApiFailure
    return {
      status: candidate.statusCode ?? candidate.status ?? candidate.response?.status,
      data: candidate.data ?? candidate.response?._data,
    }
  }

  const clearErrors = () => {
    publicationError.value = null
    previewError.value = null
    validationErrors.value = {}
  }

  const clearPreview = () => {
    previewState.value = null
    previewError.value = null
  }

  async function fetchPublication(): Promise<DesignerProfilePublicationState> {
    publicationLoading.value = true
    publicationError.value = null

    try {
      const response = await apiFetch<DesignerProfilePublicationResponse>('/designer/profile/publication')
      publicationState.value = response.data
      return response.data
    } catch (failure: unknown) {
      const { data } = failureData(failure)
      publicationError.value = data?.message || 'تعذر جلب حالة نشر الملف.'
      throw failure
    } finally {
      publicationLoading.value = false
    }
  }

  async function fetchPreview(): Promise<DesignerProfilePreview> {
    previewLoading.value = true
    previewError.value = null

    try {
      const response = await apiFetch<DesignerProfilePreviewResponse>('/designer/profile/publication/preview')
      previewState.value = response.data.preview
      return response.data.preview
    } catch (failure: unknown) {
      const { data } = failureData(failure)
      previewError.value = data?.message || 'تعذر تحميل معاينة الملف.'
      throw failure
    } finally {
      previewLoading.value = false
    }
  }

  async function runAction(
    action: 'publish' | 'hide',
    expectedUpdatedAt: string,
  ): Promise<boolean> {
    publicationSaving.value = true
    publicationError.value = null
    validationErrors.value = {}

    try {
      const response = await apiFetch<DesignerProfilePublicationActionResponse>(
        `/designer/profile/publication/${action}`,
        {
          method: 'PATCH',
          body: { expected_updated_at: expectedUpdatedAt },
        },
      )
      publicationState.value = response.data
      clearPreview()
      return true
    } catch (failure: unknown) {
      const { status, data } = failureData(failure)

      if (status === 409 && data?.data?.code === 'designer_profile_publication_version_conflict') {
        try {
          await fetchPublication()
        } catch {
          // Keep the action conflict as the primary reader-facing message.
        }
        publicationError.value = conflictMessage
      } else {
        publicationError.value = data?.message || (action === 'publish'
          ? 'تعذر نشر الملف.'
          : 'تعذر إخفاء الملف.')
      }

      if (status === 422) {
        validationErrors.value = data?.errors || {}
      }

      return false
    } finally {
      publicationSaving.value = false
    }
  }

  const publishProfile = (expectedUpdatedAt: string) => runAction('publish', expectedUpdatedAt)
  const hideProfile = (expectedUpdatedAt: string) => runAction('hide', expectedUpdatedAt)

  return {
    publicationState: readonly(publicationState),
    previewState: readonly(previewState),
    publicationLoading: readonly(publicationLoading),
    previewLoading: readonly(previewLoading),
    publicationSaving: readonly(publicationSaving),
    publicationError: readonly(publicationError),
    previewError: readonly(previewError),
    validationErrors: readonly(validationErrors),
    fetchPublication,
    fetchPreview,
    publishProfile,
    hideProfile,
    clearPreview,
    clearErrors,
  }
}
