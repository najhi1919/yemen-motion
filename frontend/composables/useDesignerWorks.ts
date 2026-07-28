import type {
  DesignerWork,
  DesignerWorkDirection,
  DesignerWorkGroup,
  DesignerWorksMeta,
  DesignerWorksResponse,
  DesignerWorksSummary,
  DesignerWorkSort,
} from '~/types/designer-work'

const emptySummary = (): DesignerWorksSummary => ({
  total: 0,
  draft: 0,
  review: 0,
  changes: 0,
  published: 0,
  closed: 0,
})

export const useDesignerWorks = () => {
  const { apiFetch } = useApiClient()
  const works = ref<DesignerWork[]>([])
  const summary = ref<DesignerWorksSummary>(emptySummary())
  const meta = ref<DesignerWorksMeta>({
    current_page: 1,
    last_page: 1,
    per_page: 12,
    total: 0,
    from: null,
    to: null,
  })
  const filters = reactive<{
    q: string
    group: DesignerWorkGroup
    sort: DesignerWorkSort
    direction: DesignerWorkDirection
    page: number
    per_page: 12 | 18 | 24
  }>({
    q: '',
    group: 'all',
    sort: 'updated_at',
    direction: 'desc',
    page: 1,
    per_page: 12,
  })
  const loading = ref(false)
  const updating = ref(false)
  const error = ref(false)
  const coverUrls = ref<Record<number, string>>({})

  const revokeCoverUrls = () => {
    if (import.meta.client) {
      Object.values(coverUrls.value).forEach(url => URL.revokeObjectURL(url))
    }
    coverUrls.value = {}
  }

  const resolveCovers = async (items: DesignerWork[]) => {
    if (!import.meta.client) return

    const resolved: Record<number, string> = {}
    await Promise.all(items.map(async work => {
      const cover = work.cover_media
      if (!cover || cover.processing_status !== 'ready' || resolved[cover.id]) return

      const source = cover.kind === 'video' ? cover.poster_url : cover.content_url
      if (!source) return

      try {
        const blob = await apiFetch<Blob>(source, { responseType: 'blob' })
        resolved[cover.id] = URL.createObjectURL(blob)
      } catch {
        // فشل الغلاف لا يمنع عرض بقية الصفحة.
      }
    }))

    coverUrls.value = resolved
  }

  const fetchWorks = async () => {
    const hasResults = works.value.length > 0
    loading.value = !hasResults
    updating.value = hasResults
    error.value = false

    try {
      const response = await apiFetch<DesignerWorksResponse>('/designer/works', {
        query: {
          q: filters.q || undefined,
          group: filters.group,
          sort: filters.sort,
          direction: filters.direction,
          page: filters.page,
          per_page: filters.per_page,
        },
      })

      revokeCoverUrls()
      works.value = response.data
      summary.value = response.summary
      meta.value = response.meta
      await resolveCovers(response.data)
    } catch {
      error.value = true
    } finally {
      loading.value = false
      updating.value = false
    }
  }

  const resetFilters = () => {
    filters.q = ''
    filters.group = 'all'
    filters.sort = 'updated_at'
    filters.direction = 'desc'
    filters.page = 1
  }

  onBeforeUnmount(revokeCoverUrls)

  return {
    works: readonly(works),
    summary: readonly(summary),
    meta: readonly(meta),
    filters,
    loading: readonly(loading),
    updating: readonly(updating),
    error: readonly(error),
    coverUrls: readonly(coverUrls),
    fetchWorks,
    resetFilters,
  }
}
