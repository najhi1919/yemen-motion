import type {
  DesignerProfileProfessionalEnvelope,
  DesignerProfileProfessionalPayload,
  DesignerProfileProfessionalResponse,
} from '~/types/designer-profile-professional'

const state = ref<DesignerProfileProfessionalEnvelope | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)
const validationErrors = ref<Record<string, string[]>>({})

interface ApiFailure {
  statusCode?: number
  status?: number
  data?: { message?: string, errors?: Record<string, string[]> }
  response?: { status?: number, _data?: { message?: string, errors?: Record<string, string[]> } }
}

export function useDesignerProfileProfessional() {
  const { apiFetch } = useApiClient()

  const failureData = (failure: unknown) => {
    const candidate = failure as ApiFailure
    return {
      status: candidate.statusCode ?? candidate.status ?? candidate.response?.status,
      data: candidate.data ?? candidate.response?._data,
    }
  }

  const clearError = () => {
    error.value = null
    validationErrors.value = {}
  }

  async function fetchProfessional(): Promise<DesignerProfileProfessionalEnvelope> {
    loading.value = true
    clearError()
    try {
      const response = await apiFetch<DesignerProfileProfessionalResponse>('/designer/profile/professional')
      state.value = response.data
      return response.data
    } catch (failure: unknown) {
      const { data } = failureData(failure)
      error.value = data?.message || 'تعذر تحميل البيانات المهنية.'
      throw failure
    } finally {
      loading.value = false
    }
  }

  async function saveProfessional(payload: DesignerProfileProfessionalPayload): Promise<DesignerProfileProfessionalEnvelope> {
    if (!state.value) throw new Error('Professional profile state is not loaded.')
    saving.value = true
    clearError()
    try {
      const response = await apiFetch<DesignerProfileProfessionalResponse>('/designer/profile/professional', {
        method: 'PUT',
        body: {
          expected_updated_at: state.value.professional.updated_at,
          ...payload,
        },
      })
      state.value = response.data
      return response.data
    } catch (failure: unknown) {
      const { status, data } = failureData(failure)
      error.value = data?.message || (status === 409
        ? 'تغيرت بيانات الملف في الخادم.'
        : 'تعذر حفظ البيانات المهنية.')
      if (status === 422) validationErrors.value = data?.errors || {}
      throw failure
    } finally {
      saving.value = false
    }
  }

  return {
    professionalState: readonly(state),
    loading: readonly(loading),
    saving: readonly(saving),
    error: readonly(error),
    validationErrors: readonly(validationErrors),
    fetchProfessional,
    saveProfessional,
    clearError,
  }
}
