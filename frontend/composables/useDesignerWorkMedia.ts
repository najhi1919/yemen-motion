import type {
  DesignerMediaWork,
  DesignerWorkMedia,
  DesignerWorkMediaCounts,
  DesignerWorkMediaIndexResponse,
  DesignerWorkMediaMutationResponse,
  DesignerWorkMediaPolicy,
  DesignerWorkMediaPreview,
  DesignerWorkVideoCoverDialogState,
} from '~/types/designer-work-media'

const emptyPolicy = (): DesignerWorkMediaPolicy => ({
  source: 'work_settings',
  settings_version: 0,
  work_media_type: null,
  allowed_media_types: [],
  allowed_file_kinds: [],
  allowed_mime_types: [],
  configured_limits: { max_items: null, max_file_size_kb: null },
  effective_limits: { max_items: null, max_file_size_kb: null },
  enforcement: {},
})

export const useDesignerWorkMedia = (workId: number) => {
  const { apiFetch } = useApiClient()
  const work = ref<DesignerMediaWork | null>(null)
  const media = ref<DesignerWorkMedia[]>([])
  const mediaPolicy = ref<DesignerWorkMediaPolicy>(emptyPolicy())
  const counts = ref<DesignerWorkMediaCounts>({ active: 0, remaining: null })
  const editable = ref(false)
  const loading = ref(false)
  const uploading = ref(false)
  const deletingId = ref<number | null>(null)
  const ordering = ref(false)
  const covering = ref(false)
  const retryingId = ref<number | null>(null)
  const error = ref<string | null>(null)
  const notFound = ref(false)
  const validationErrors = ref<Record<string, string[]>>({})
  const message = ref<string | null>(null)
  const imageObjectUrls = ref<Record<number, string>>({})
  const posterObjectUrls = ref<Record<number, string>>({})
  const videoObjectUrl = ref<string | null>(null)
  const preview = ref<DesignerWorkMediaPreview | null>(null)
  const videoCoverItem = ref<DesignerWorkVideoCoverDialogState['item']>(null)
  const videoCoverVideoUrl = ref<DesignerWorkVideoCoverDialogState['video_url']>(null)
  const videoCoverLoading = ref(false)
  const videoCoverSaving = ref(false)
  const videoCoverError = ref<string | null>(null)
  const videoCoverOpener = ref<DesignerWorkVideoCoverDialogState['opener']>(null)
  const presentationCover = computed(() => {
    const coverId = work.value?.cover_media_id
    if (!coverId) return null
    const item = media.value.find(mediaItem => mediaItem.id === coverId)
    if (!item) return null

    return {
      id: item.id,
      kind: item.kind,
      processing_status: item.processing_status,
      url: item.kind === 'image'
        ? imageObjectUrls.value[item.id] || null
        : posterObjectUrls.value[item.id] || null,
    }
  })
  let pollTimer: ReturnType<typeof setTimeout> | null = null

  const revokeUrl = (url?: string | null) => {
    if (import.meta.client && url) URL.revokeObjectURL(url)
  }

  const revokeMediaUrls = (id?: number) => {
    if (id !== undefined) {
      revokeUrl(imageObjectUrls.value[id])
      revokeUrl(posterObjectUrls.value[id])
      const images = { ...imageObjectUrls.value }
      const posters = { ...posterObjectUrls.value }
      delete images[id]
      delete posters[id]
      imageObjectUrls.value = images
      posterObjectUrls.value = posters
      return
    }
    Object.values(imageObjectUrls.value).forEach(revokeUrl)
    Object.values(posterObjectUrls.value).forEach(revokeUrl)
    imageObjectUrls.value = {}
    posterObjectUrls.value = {}
  }

  const resolveCardMedia = async (items: DesignerWorkMedia[]) => {
    if (!import.meta.client) return
    revokeMediaUrls()
    const images: Record<number, string> = {}
    const posters: Record<number, string> = {}
    await Promise.all(items.map(async item => {
      const endpoint = item.kind === 'image' ? item.content_url : item.poster_url
      if (!endpoint) return
      try {
        const blob = await apiFetch<Blob>(endpoint, { responseType: 'blob' })
        const url = URL.createObjectURL(blob)
        if (item.kind === 'image') images[item.id] = url
        else posters[item.id] = url
      } catch {
        // يعرض المكوّن العنصر البديل دون تعطيل القائمة.
      }
    }))
    imageObjectUrls.value = images
    posterObjectUrls.value = posters
    if (preview.value?.item.kind === 'image') {
      preview.value.url = images[preview.value.item.id] || ''
    }
  }

  const applyIndex = async (data: DesignerWorkMediaIndexResponse['data']) => {
    const openVideoCoverId = videoCoverItem.value?.id
    work.value = data.work
    media.value = data.media
    if (openVideoCoverId !== undefined) {
      const refreshedVideo = data.media.find(item => item.id === openVideoCoverId)
      if (refreshedVideo) videoCoverItem.value = refreshedVideo
    }
    mediaPolicy.value = data.media_policy
    counts.value = data.counts
    editable.value = data.media_state.editable
    await resolveCardMedia(data.media)
    syncPolling()
  }

  const handleFailure = async (requestError: any, itemOperation = false) => {
    const status = requestError?.response?.status || requestError?.statusCode
    const data = requestError?.data || requestError?.response?._data
    if (status === 422) {
      validationErrors.value = data?.errors || {}
      error.value = validationErrors.value.time_ms?.[0]
        || validationErrors.value.file?.[0]
        || validationErrors.value.media_ids?.[0]
        || validationErrors.value.cover_media_id?.[0]
        || 'تعذر تنفيذ عملية الوسائط.'
      return
    }
    if (status === 409) {
      if (data?.data?.reason === 'work_state_not_editable') {
        editable.value = false
        error.value = 'لم يعد العمل قابلًا لتعديل الوسائط في حالته الحالية.'
      } else if (data?.data?.reason === 'media_type_required') {
        error.value = 'حدد نوع العمل في البيانات الأساسية واحفظه قبل رفع الوسائط.'
      } else {
        error.value = data?.message || 'تعذر تنفيذ عملية الوسائط.'
      }
      return
    }
    if (status === 404) {
      if (itemOperation) {
        error.value = 'لم يعد هذا الوسيط متاحًا.'
        await fetchMedia()
      } else {
        notFound.value = true
        error.value = 'العمل غير موجود.'
      }
      return
    }
    error.value = 'تعذر تنفيذ عملية الوسائط.'
  }

  const fetchMedia = async () => {
    loading.value = media.value.length === 0
    error.value = null
    notFound.value = false
    try {
      const response = await apiFetch<DesignerWorkMediaIndexResponse>(
        `/designer/works/${workId}/media`,
      )
      await applyIndex(response.data)
    } catch (requestError) {
      await handleFailure(requestError)
    } finally {
      loading.value = false
    }
  }

  const uploadMedia = async (file: File): Promise<boolean> => {
    uploading.value = true
    error.value = null
    message.value = null
    validationErrors.value = {}
    const body = new FormData()
    body.append('file', file)
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<unknown>>(
        `/designer/works/${workId}/media`,
        {
          method: 'POST',
          body,
        },
      )
      message.value = response.message
      await fetchMedia()
      return true
    } catch (requestError) {
      await handleFailure(requestError)
      return false
    } finally {
      uploading.value = false
    }
  }

  const deleteMedia = async (id: number): Promise<boolean> => {
    deletingId.value = id
    error.value = null
    message.value = null
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<{
        counts: DesignerWorkMediaCounts
        cover_cleared: boolean
      }>>(`/designer/works/${workId}/media/${id}`, { method: 'DELETE' })
      revokeMediaUrls(id)
      media.value = media.value.filter(item => item.id !== id)
      counts.value = response.data.counts
      if (response.data.cover_cleared && work.value) work.value.cover_media_id = null
      message.value = response.message
      syncPolling()
      return true
    } catch (requestError) {
      await handleFailure(requestError, true)
      return false
    } finally {
      deletingId.value = null
    }
  }

  const reorderMedia = async (from: number, to: number): Promise<boolean> => {
    if (to < 0 || to >= media.value.length || from === to) return false
    const before = media.value.map(item => ({ ...item }))
    const next = [...media.value]
    const [moved] = next.splice(from, 1)
    if (!moved) return false
    next.splice(to, 0, moved)
    media.value = next
    ordering.value = true
    error.value = null
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<{
        work: DesignerMediaWork
        media: DesignerWorkMedia[]
        media_policy: DesignerWorkMediaPolicy
        counts: DesignerWorkMediaCounts
      }>>(`/designer/works/${workId}/media/order`, {
        method: 'PATCH',
        body: { media_ids: next.map(item => item.id) },
      })
      work.value = response.data.work
      media.value = response.data.media
      mediaPolicy.value = response.data.media_policy
      counts.value = response.data.counts
      message.value = response.message
      return true
    } catch (requestError) {
      media.value = before
      await handleFailure(requestError, true)
      return false
    } finally {
      ordering.value = false
    }
  }

  const updateCover = async (coverId: number | null): Promise<boolean> => {
    covering.value = true
    error.value = null
    message.value = null
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<{
        work: DesignerMediaWork
        media: DesignerWorkMedia[]
        media_policy: DesignerWorkMediaPolicy
        counts: DesignerWorkMediaCounts
      }>>(`/designer/works/${workId}/media/cover`, {
        method: 'PATCH',
        body: { cover_media_id: coverId },
      })
      work.value = response.data.work
      media.value = response.data.media
      if (videoCoverItem.value) {
        const refreshedVideo = response.data.media.find(
          item => item.id === videoCoverItem.value?.id,
        )
        if (refreshedVideo) videoCoverItem.value = refreshedVideo
      }
      mediaPolicy.value = response.data.media_policy
      counts.value = response.data.counts
      message.value = response.message
      return true
    } catch (requestError) {
      await handleFailure(requestError, coverId !== null)
      return false
    } finally {
      covering.value = false
    }
  }

  const retryProcessing = async (id: number): Promise<boolean> => {
    retryingId.value = id
    error.value = null
    message.value = null
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<{
        media: DesignerWorkMedia
      }>>(`/designer/works/${workId}/media/${id}/retry-processing`, { method: 'POST' })
      const index = media.value.findIndex(item => item.id === id)
      if (index >= 0) media.value[index] = response.data.media
      message.value = response.message
      syncPolling()
      return true
    } catch (requestError) {
      await handleFailure(requestError, true)
      return false
    } finally {
      retryingId.value = null
    }
  }

  const openPreview = async (item: DesignerWorkMedia, opener: HTMLElement | null = null) => {
    closePreview(false)
    preview.value = { item, url: '', loading: item.kind === 'video', opener }
    if (item.kind === 'image') {
      preview.value.url = imageObjectUrls.value[item.id] || ''
      preview.value.loading = false
      return
    }
    try {
      const blob = await apiFetch<Blob>(item.content_url, { responseType: 'blob' })
      videoObjectUrl.value = URL.createObjectURL(blob)
      if (preview.value?.item.id === item.id) preview.value.url = videoObjectUrl.value
    } catch {
      error.value = 'تعذر تنفيذ عملية الوسائط.'
    } finally {
      if (preview.value?.item.id === item.id) preview.value.loading = false
    }
  }

  const closePreview = (restoreFocus = true) => {
    const opener = preview.value?.opener
    preview.value = null
    revokeUrl(videoObjectUrl.value)
    videoObjectUrl.value = null
    if (restoreFocus && import.meta.client) nextTick(() => opener?.focus())
  }

  const closeVideoCover = (restoreFocus = true) => {
    const opener = videoCoverOpener.value
    videoCoverItem.value = null
    videoCoverLoading.value = false
    videoCoverError.value = null
    videoCoverOpener.value = null
    revokeUrl(videoCoverVideoUrl.value)
    videoCoverVideoUrl.value = null
    if (restoreFocus && import.meta.client) nextTick(() => opener?.focus())
  }

  const openVideoCover = async (
    item: DesignerWorkMedia,
    opener: HTMLElement | null = null,
  ) => {
    if (item.kind !== 'video' || item.processing_status !== 'ready') return
    closeVideoCover(false)
    videoCoverItem.value = item
    videoCoverOpener.value = opener
    videoCoverLoading.value = true
    videoCoverError.value = null
    message.value = null
    try {
      const blob = await apiFetch<Blob>(item.content_url, { responseType: 'blob' })
      const url = URL.createObjectURL(blob)
      if (videoCoverItem.value?.id === item.id) {
        videoCoverVideoUrl.value = url
      } else {
        revokeUrl(url)
      }
    } catch {
      videoCoverError.value = 'تعذر تحميل الفيديو الخاص لإدارة الغلاف.'
    } finally {
      if (videoCoverItem.value?.id === item.id) videoCoverLoading.value = false
    }
  }

  const handleVideoCoverFailure = async (requestError: unknown) => {
    await handleFailure(requestError, true)
    videoCoverError.value = validationErrors.value.time_ms?.[0]
      || validationErrors.value.file?.[0]
      || error.value
      || 'تعذر تحديث غلاف الفيديو.'
  }

  const useCurrentVideoPoster = async (mediaId: number): Promise<boolean> => {
    videoCoverSaving.value = true
    videoCoverError.value = null
    message.value = null
    validationErrors.value = {}
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<unknown>>(
        `/designer/works/${workId}/media/${mediaId}/video-cover/current`,
        { method: 'PATCH' },
      )
      message.value = response.message
      await fetchMedia()
      return true
    } catch (requestError) {
      await handleVideoCoverFailure(requestError)
      return false
    } finally {
      videoCoverSaving.value = false
    }
  }

  const selectVideoCoverFrame = async (
    mediaId: number,
    timeMs: number,
  ): Promise<boolean> => {
    videoCoverSaving.value = true
    videoCoverError.value = null
    message.value = null
    validationErrors.value = {}
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<unknown>>(
        `/designer/works/${workId}/media/${mediaId}/video-cover/frame`,
        {
          method: 'PATCH',
          body: { time_ms: timeMs },
        },
      )
      message.value = response.message
      await fetchMedia()
      return true
    } catch (requestError) {
      await handleVideoCoverFailure(requestError)
      return false
    } finally {
      videoCoverSaving.value = false
    }
  }

  const uploadVideoCover = async (
    mediaId: number,
    file: File,
  ): Promise<boolean> => {
    videoCoverSaving.value = true
    videoCoverError.value = null
    message.value = null
    validationErrors.value = {}
    const body = new FormData()
    body.append('file', file)
    try {
      const response = await apiFetch<DesignerWorkMediaMutationResponse<unknown>>(
        `/designer/works/${workId}/media/${mediaId}/video-cover/upload`,
        {
          method: 'POST',
          body,
        },
      )
      message.value = response.message
      await fetchMedia()
      return true
    } catch (requestError) {
      await handleVideoCoverFailure(requestError)
      return false
    } finally {
      videoCoverSaving.value = false
    }
  }

  const stopPolling = () => {
    if (pollTimer) clearTimeout(pollTimer)
    pollTimer = null
  }

  const syncPolling = () => {
    stopPolling()
    if (!import.meta.client || document.hidden
      || !media.value.some(item => item.processing_status === 'pending')) return
    pollTimer = setTimeout(async () => {
      pollTimer = null
      await fetchMedia()
    }, 3000)
  }

  const onVisibilityChange = () => {
    if (document.hidden) stopPolling()
    else syncPolling()
  }

  onMounted(async () => {
    document.addEventListener('visibilitychange', onVisibilityChange)
    await resolveCardMedia(media.value)
    syncPolling()
  })
  onBeforeUnmount(() => {
    stopPolling()
    document.removeEventListener('visibilitychange', onVisibilityChange)
    closePreview(false)
    closeVideoCover(false)
    revokeMediaUrls()
  })

  return {
    work: readonly(work),
    media: readonly(media),
    mediaPolicy: readonly(mediaPolicy),
    counts: readonly(counts),
    editable: readonly(editable),
    loading: readonly(loading),
    uploading: readonly(uploading),
    deletingId: readonly(deletingId),
    ordering: readonly(ordering),
    covering: readonly(covering),
    retryingId: readonly(retryingId),
    error: readonly(error),
    notFound: readonly(notFound),
    validationErrors: readonly(validationErrors),
    message: readonly(message),
    preview: readonly(preview),
    imageObjectUrls: readonly(imageObjectUrls),
    posterObjectUrls: readonly(posterObjectUrls),
    videoObjectUrl: readonly(videoObjectUrl),
    videoCoverItem: readonly(videoCoverItem),
    videoCoverVideoUrl: readonly(videoCoverVideoUrl),
    videoCoverLoading: readonly(videoCoverLoading),
    videoCoverSaving: readonly(videoCoverSaving),
    videoCoverError: readonly(videoCoverError),
    videoCoverOpener: readonly(videoCoverOpener),
    presentationCover,
    fetchMedia,
    uploadMedia,
    deleteMedia,
    reorderMedia,
    updateCover,
    retryProcessing,
    openPreview,
    closePreview,
    openVideoCover,
    closeVideoCover,
    useCurrentVideoPoster,
    selectVideoCoverFrame,
    uploadVideoCover,
  }
}
