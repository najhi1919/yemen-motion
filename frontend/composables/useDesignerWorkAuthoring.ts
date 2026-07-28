import type {
  DesignerWorkAuthoring,
  DesignerWorkAuthoringDraft,
  DesignerWorkAuthoringResponse,
  DesignerWorkAuthoringShowResponse,
  DesignerWorkMediaType,
} from '~/types/designer-work-authoring'

const emptyDraft = (): DesignerWorkAuthoringDraft => ({
  title: '',
  summary: '',
  description: '',
  media_type: '',
  price_amount: '',
  delivery_days: '',
})

const normalizedDraft = (value: DesignerWorkAuthoringDraft): DesignerWorkAuthoringDraft => ({
  title: value.title.trim(),
  summary: value.summary,
  description: value.description,
  media_type: value.media_type,
  price_amount: value.price_amount.trim(),
  delivery_days: value.delivery_days.trim(),
})

const fromWork = (work: DesignerWorkAuthoring): DesignerWorkAuthoringDraft => ({
  title: work.title,
  summary: work.summary ?? '',
  description: work.description ?? '',
  media_type: work.media_type ?? '',
  price_amount: work.price_amount === null ? '' : String(work.price_amount),
  delivery_days: work.delivery_days === null ? '' : String(work.delivery_days),
})

export const useDesignerWorkAuthoring = () => {
  const { apiFetch } = useApiClient()
  const form = reactive<DesignerWorkAuthoringDraft>(emptyDraft())
  const snapshot = ref<DesignerWorkAuthoringDraft>(emptyDraft())
  const work = ref<DesignerWorkAuthoring | null>(null)
  const allowedMediaTypes = ref<DesignerWorkMediaType[]>(['image', 'video', 'gallery'])
  const editable = ref(true)
  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)
  const notFound = ref(false)
  const conflict = ref(false)
  const validationErrors = ref<Record<string, string[]>>({})
  const success = ref<string | null>(null)

  const dirtyFields = computed(() => {
    const current = normalizedDraft(form)
    const server = normalizedDraft(snapshot.value)
    return (Object.keys(current) as Array<keyof DesignerWorkAuthoringDraft>)
      .filter(key => current[key] !== server[key])
  })
  const dirty = computed(() => dirtyFields.value.length > 0)

  const applySnapshot = (value: DesignerWorkAuthoringDraft) => {
    Object.assign(form, value)
    snapshot.value = { ...value }
  }

  const payloadValue = (key: keyof DesignerWorkAuthoringDraft) => {
    const value = normalizedDraft(form)[key]
    if (key === 'price_amount') return value === '' ? null : Number(value)
    if (key === 'delivery_days') return value === '' ? null : Number(value)
    if (key === 'media_type') return value === '' ? null : value
    if (key === 'summary' || key === 'description') return value === '' ? null : value
    return value
  }

  const clientValidation = (keys: Array<keyof DesignerWorkAuthoringDraft>) => {
    const errors: Record<string, string[]> = {}
    if (keys.includes('price_amount') && form.price_amount !== ''
      && !/^\d+(?:\.\d{1,2})?$/.test(form.price_amount.trim())) {
      errors.price_amount = ['أدخل سعرًا صحيحًا بمنزلتين عشريتين كحد أقصى.']
    }
    if (keys.includes('delivery_days') && form.delivery_days !== ''
      && !/^\d+$/.test(form.delivery_days.trim())) {
      errors.delivery_days = ['أدخل عددًا صحيحًا للأيام.']
    }
    validationErrors.value = errors
    return Object.keys(errors).length === 0
  }

  const handleFailure = (requestError: any) => {
    const status = requestError?.response?.status || requestError?.statusCode
    if (status === 422) {
      validationErrors.value = requestError?.data?.errors || {}
      return
    }
    if (status === 409) {
      conflict.value = true
      editable.value = false
      error.value = 'لم يعد العمل قابلًا للتعديل في حالته الحالية.'
      return
    }
    if (status === 404) {
      notFound.value = true
      return
    }
    error.value = 'تعذر حفظ بيانات العمل.'
  }

  const fetchWork = async (id: number) => {
    loading.value = true
    error.value = null
    notFound.value = false
    try {
      const response = await apiFetch<DesignerWorkAuthoringShowResponse>(
        `/designer/works/${id}/authoring`,
      )
      work.value = response.data.work
      editable.value = response.data.authoring_state.editable
      allowedMediaTypes.value = response.data.authoring_policy.allowed_media_types
      applySnapshot(fromWork(response.data.work))
    } catch (requestError: any) {
      handleFailure(requestError)
    } finally {
      loading.value = false
    }
  }

  const createWork = async (): Promise<DesignerWorkAuthoring | null> => {
    const keys = Object.keys(form) as Array<keyof DesignerWorkAuthoringDraft>
    if (!clientValidation(keys)) return null
    saving.value = true
    error.value = null
    success.value = null
    validationErrors.value = {}
    try {
      const body = Object.fromEntries(keys.map(key => [key, payloadValue(key)]))
      const response = await apiFetch<DesignerWorkAuthoringResponse>('/designer/works', {
        method: 'POST',
        body,
      })
      work.value = response.data.work
      applySnapshot(fromWork(response.data.work))
      success.value = response.message
      return response.data.work
    } catch (requestError: any) {
      handleFailure(requestError)
      return null
    } finally {
      saving.value = false
    }
  }

  const updateWork = async (): Promise<boolean> => {
    if (!work.value || !dirty.value || !editable.value) return false
    const keys = dirtyFields.value
    if (!clientValidation(keys)) return false
    saving.value = true
    error.value = null
    success.value = null
    validationErrors.value = {}
    try {
      const body = Object.fromEntries(keys.map(key => [key, payloadValue(key)]))
      const response = await apiFetch<DesignerWorkAuthoringResponse>(
        `/designer/works/${work.value.id}`,
        { method: 'PATCH', body },
      )
      work.value = response.data.work
      applySnapshot(fromWork(response.data.work))
      success.value = response.message
      return true
    } catch (requestError: any) {
      handleFailure(requestError)
      return false
    } finally {
      saving.value = false
    }
  }

  const reset = () => {
    applySnapshot(snapshot.value)
    validationErrors.value = {}
    error.value = null
    success.value = null
  }

  const beforeUnload = (event: BeforeUnloadEvent) => {
    if (!dirty.value) return
    event.preventDefault()
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', beforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

  return {
    form,
    work: readonly(work),
    allowedMediaTypes: readonly(allowedMediaTypes),
    editable: readonly(editable),
    loading: readonly(loading),
    saving: readonly(saving),
    error: readonly(error),
    notFound: readonly(notFound),
    conflict: readonly(conflict),
    validationErrors: readonly(validationErrors),
    success: readonly(success),
    dirty,
    fetchWork,
    createWork,
    updateWork,
    reset,
  }
}
