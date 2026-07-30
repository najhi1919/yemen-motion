import type {
  DesignerWorkMetadataCurrent,
  DesignerWorkMetadataShowResponse,
  DesignerWorkMetadataState,
  DesignerWorkMetadataUpdateResponse,
  DesignerWorkTaxonomyOption,
} from '~/types/designer-work-metadata'

export function useDesignerWorkMetadata() {
  const { apiFetch } = useApiClient()
  const current = ref<DesignerWorkMetadataCurrent | null>(null)
  const categories = ref<DesignerWorkTaxonomyOption[]>([])
  const tags = ref<DesignerWorkTaxonomyOption[]>([])
  const state = ref<DesignerWorkMetadataState | null>(null)
  const form = reactive<{ category_id: number | null, tag_ids: number[] }>({
    category_id: null,
    tag_ids: [],
  })
  const snapshot = ref('')
  const loading = ref(false)
  const saving = ref(false)
  const error = ref<string | null>(null)
  const success = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})

  const serializedForm = () => JSON.stringify({
    category_id: form.category_id,
    tag_ids: [...form.tag_ids].sort((left, right) => left - right),
  })
  const dirty = computed(() => snapshot.value !== '' && serializedForm() !== snapshot.value)
  const editable = computed(() => state.value?.editable === true)

  function applyPayload(payload: DesignerWorkMetadataShowResponse['data']): void {
    current.value = payload.work
    categories.value = payload.options.categories
    tags.value = payload.options.tags
    state.value = payload.metadata_state
    form.category_id = payload.work.category_id
    form.tag_ids = [...payload.work.tag_ids]
    snapshot.value = serializedForm()
  }

  function reset(): void {
    if (!current.value) return
    form.category_id = current.value.category_id
    form.tag_ids = [...current.value.tag_ids]
    validationErrors.value = {}
    error.value = null
    success.value = null
  }

  async function fetchMetadata(workId: number): Promise<boolean> {
    loading.value = true
    error.value = null

    try {
      const response = await apiFetch<DesignerWorkMetadataShowResponse>(
        `/designer/works/${workId}/metadata`,
      )
      applyPayload(response.data)
      return true
    } catch {
      error.value = 'تعذر تحميل بيانات التصنيف والوسوم.'
      return false
    } finally {
      loading.value = false
    }
  }

  async function save(): Promise<boolean> {
    if (!current.value || !editable.value || !dirty.value || saving.value) return false
    saving.value = true
    error.value = null
    success.value = null
    validationErrors.value = {}

    try {
      const response = await apiFetch<DesignerWorkMetadataUpdateResponse>(
        `/designer/works/${current.value.id}/metadata`,
        {
          method: 'PATCH',
          body: {
            category_id: form.category_id,
            tag_ids: [...form.tag_ids],
          },
        },
      )
      applyPayload(response.data)
      success.value = 'تم حفظ التصنيف والوسوم.'
      return true
    } catch (caught: unknown) {
      const failure = caught as {
        status?: number
        statusCode?: number
        data?: { message?: string, errors?: Record<string, string[]>, data?: { code?: string } }
      }
      const status = failure.statusCode ?? failure.status
      validationErrors.value = failure.data?.errors || {}

      if (status === 409 || failure.data?.data?.code === 'work_state_not_editable') {
        if (state.value) state.value.editable = false
        error.value = 'لم يعد العمل قابلًا للتعديل في حالته الحالية.'
      } else if (status === 404) {
        error.value = 'العمل غير موجود.'
      } else {
        error.value = failure.data?.message || 'تعذر حفظ التصنيف والوسوم.'
      }

      return false
    } finally {
      saving.value = false
    }
  }

  function toggleTag(tagId: number): void {
    if (form.tag_ids.includes(tagId)) {
      form.tag_ids = form.tag_ids.filter(id => id !== tagId)
      return
    }

    if (form.tag_ids.length < (state.value?.max_tags ?? 10)) {
      form.tag_ids.push(tagId)
    }
  }

  function removeTag(tagId: number): void {
    form.tag_ids = form.tag_ids.filter(id => id !== tagId)
  }

  function beforeUnload(event: BeforeUnloadEvent): void {
    if (!dirty.value) return
    event.preventDefault()
    event.returnValue = ''
  }

  onMounted(() => window.addEventListener('beforeunload', beforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))

  return {
    current,
    categories,
    tags,
    state,
    form,
    snapshot,
    dirty,
    loading,
    saving,
    editable,
    error,
    success,
    validationErrors,
    fetchMetadata,
    reset,
    save,
    toggleTag,
    removeTag,
  }
}
