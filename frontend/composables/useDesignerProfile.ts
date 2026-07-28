import type {
  DesignerApiResponse,
  DesignerProfileEnvelope,
  DesignerProfilePayload,
  UsernameAvailability
} from '~/types/designer-profile'

const designerProfile = ref<DesignerProfileEnvelope | null>(null)
const designerProfileLoading = ref(false)
const designerProfileSaving = ref(false)
const designerProfileError = ref<string | null>(null)
const designerProfileValidationErrors = ref<Record<string, string[]>>({})

export function useDesignerProfile() {
  const { apiFetch } = useApiClient()

  async function fetchProfile(): Promise<DesignerProfileEnvelope> {
    designerProfileLoading.value = true
    designerProfileError.value = null

    try {
      const response = await apiFetch<DesignerApiResponse<DesignerProfileEnvelope>>('/designer/profile')
      designerProfile.value = response.data

      return response.data
    } catch (error: unknown) {
      const apiError = error as any
      designerProfileError.value = apiError?.data?.message || 'تعذر تحميل بيانات الملف.'
      throw error
    } finally {
      designerProfileLoading.value = false
    }
  }

  async function saveProfile(payload: DesignerProfilePayload): Promise<DesignerProfileEnvelope> {
    designerProfileSaving.value = true
    designerProfileError.value = null
    designerProfileValidationErrors.value = {}

    try {
      const response = await apiFetch<DesignerApiResponse<DesignerProfileEnvelope>>('/designer/profile', {
        method: 'PUT',
        body: payload
      })
      designerProfile.value = response.data

      return response.data
    } catch (error: unknown) {
      const apiError = error as any
      designerProfileError.value = apiError?.data?.message || 'تعذر حفظ بيانات الملف.'
      designerProfileValidationErrors.value = apiError?.data?.errors || {}
      throw error
    } finally {
      designerProfileSaving.value = false
    }
  }

  async function checkUsernameAvailability(username: string): Promise<UsernameAvailability> {
    const response = await apiFetch<DesignerApiResponse<UsernameAvailability>>(
      `/designer/profile/username-availability?username=${encodeURIComponent(username)}`
    )

    return response.data
  }

  return {
    profileState: readonly(designerProfile),
    loading: readonly(designerProfileLoading),
    saving: readonly(designerProfileSaving),
    error: readonly(designerProfileError),
    validationErrors: readonly(designerProfileValidationErrors),
    fetchProfile,
    saveProfile,
    checkUsernameAvailability
  }
}
