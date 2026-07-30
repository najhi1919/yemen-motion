import type {
  DesignerWorkPresentationCurrent,
  DesignerWorkPresentationForm,
  DesignerWorkPresentationShowResponse,
  DesignerWorkPresentationState,
  DesignerWorkPresentationUpdateResponse,
} from '~/types/designer-work-presentation'
import type { DesignerWorkCoverDisplayMode } from '~/types/designer-work'

const defaultForm = (): DesignerWorkPresentationForm => ({
  cover_display_mode: 'fill',
  focal_x: 50,
  focal_y: 50,
})

export const useDesignerWorkPresentation = () => {
  const { apiFetch } = useApiClient()
  const current = ref<DesignerWorkPresentationCurrent | null>(null)
  const state = ref<DesignerWorkPresentationState>({
    editable: false,
    available_modes: ['fill', 'fit'],
  })
  const form = reactive<DesignerWorkPresentationForm>(defaultForm())
  const snapshot = ref<DesignerWorkPresentationForm>(defaultForm())
  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)
  const success = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})
  const activeWorkId = ref<number | null>(null)

  const dirty = computed(() =>
    form.cover_display_mode !== snapshot.value.cover_display_mode
    || form.focal_x !== snapshot.value.focal_x
    || form.focal_y !== snapshot.value.focal_y,
  )
  const editable = computed(() => state.value.editable)

  const applyResponse = (data: DesignerWorkPresentationShowResponse['data']) => {
    current.value = data.work
    state.value = data.presentation_state
    const next: DesignerWorkPresentationForm = {
      cover_display_mode: data.work.cover_display_mode,
      focal_x: data.work.cover_focal_point.x,
      focal_y: data.work.cover_focal_point.y,
    }
    Object.assign(form, next)
    snapshot.value = { ...next }
  }

  const handleFailure = (requestError: any) => {
    const status = requestError?.response?.status || requestError?.statusCode
    const data = requestError?.data || requestError?.response?._data
    if (status === 422) {
      validationErrors.value = data?.errors || {}
      error.value = validationErrors.value.cover_display_mode?.[0]
        || validationErrors.value['cover_focal_point.x']?.[0]
        || validationErrors.value['cover_focal_point.y']?.[0]
        || validationErrors.value.cover_focal_point?.[0]
        || 'تعذر حفظ طريقة عرض الغلاف.'
      return
    }
    if (status === 409) {
      state.value = { ...state.value, editable: false }
      error.value = 'لم يعد العمل قابلًا للتعديل في حالته الحالية.'
      return
    }
    error.value = status === 404
      ? 'العمل غير موجود.'
      : 'تعذر حفظ طريقة عرض الغلاف.'
  }

  const fetchPresentation = async (workId: number): Promise<boolean> => {
    activeWorkId.value = workId
    loading.value = true
    error.value = null
    try {
      const response = await apiFetch<DesignerWorkPresentationShowResponse>(
        `/designer/works/${workId}/presentation`,
      )
      applyResponse(response.data)
      return true
    } catch (requestError) {
      handleFailure(requestError)
      return false
    } finally {
      loading.value = false
    }
  }

  const save = async (): Promise<boolean> => {
    if (!activeWorkId.value || !dirty.value || saving.value || !editable.value) return false
    saving.value = true
    error.value = null
    success.value = null
    validationErrors.value = {}
    try {
      const response = await apiFetch<DesignerWorkPresentationUpdateResponse>(
        `/designer/works/${activeWorkId.value}/presentation`,
        {
          method: 'PATCH',
          body: {
            cover_display_mode: form.cover_display_mode,
            cover_focal_point: { x: form.focal_x, y: form.focal_y },
          },
        },
      )
      applyResponse(response.data)
      success.value = response.message || 'تم حفظ طريقة عرض الغلاف.'
      return true
    } catch (requestError) {
      handleFailure(requestError)
      return false
    } finally {
      saving.value = false
    }
  }

  const reset = () => {
    Object.assign(form, snapshot.value)
    error.value = null
    success.value = null
    validationErrors.value = {}
  }
  const setDisplayMode = (mode: DesignerWorkCoverDisplayMode) => {
    if (state.value.available_modes.includes(mode)) form.cover_display_mode = mode
    success.value = null
  }
  const setFocalPoint = (x: number, y: number) => {
    form.focal_x = Math.round(Math.max(0, Math.min(100, x)))
    form.focal_y = Math.round(Math.max(0, Math.min(100, y)))
    success.value = null
  }
  const resetFocalPoint = () => setFocalPoint(50, 50)

  return {
    current: readonly(current),
    state: readonly(state),
    form,
    loading: readonly(loading),
    saving: readonly(saving),
    error: readonly(error),
    success: readonly(success),
    validationErrors: readonly(validationErrors),
    dirty,
    editable,
    fetchPresentation,
    save,
    reset,
    setDisplayMode,
    setFocalPoint,
    resetFocalPoint,
  }
}
