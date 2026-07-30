<script setup lang="ts">
import type { useDesignerWorkMedia } from '~/composables/useDesignerWorkMedia'

type DraftSource =
  | 'current_poster'
  | 'video_frame'
  | 'uploaded_image'
  | null

const props = defineProps<{
  manager: ReturnType<typeof useDesignerWorkMedia>
}>()

const panel = ref<HTMLElement | null>(null)
const bodyScroll = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const video = ref<HTMLVideoElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const currentTimeMs = ref(0)
const durationMs = ref(0)
const draftSource = ref<DraftSource>(null)
const draftPreviewUrl = ref('')
const draftPreviewOwned = ref(false)
const draftTimeMs = ref<number | null>(null)
const draftError = ref<string | null>(null)
const savedMessage = ref<string | null>(null)
const serverErrorVisible = ref(true)
let previousOverflow = ''
let captureSequence = 0
let firstFrameSeekPending = false

const item = computed(() => props.manager.videoCoverItem.value)
const open = computed(() => item.value !== null)
const busy = computed(() =>
  props.manager.videoCoverSaving.value || props.manager.covering.value,
)
const posterUrl = computed(() => item.value
  ? props.manager.posterObjectUrls.value[item.value.id] || ''
  : '',
)
const displayedPreviewUrl = computed(() => draftPreviewUrl.value || posterUrl.value)
const visibleError = computed(() => draftError.value
  || (serverErrorVisible.value ? props.manager.videoCoverError.value : null),
)
const saveDisabled = computed(() => {
  if (!draftSource.value || busy.value) return true
  if (draftSource.value === 'video_frame') return draftTimeMs.value === null
  if (draftSource.value === 'uploaded_image') return selectedFile.value === null
  return false
})

const formatTime = (milliseconds: number | null) => {
  const seconds = Math.max(0, Math.floor((milliseconds || 0) / 1000))
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
}

const syncVideoTime = () => {
  if (!video.value) return
  currentTimeMs.value = Math.max(0, Math.round(video.value.currentTime * 1000))
  durationMs.value = Number.isFinite(video.value.duration)
    ? Math.max(0, Math.round(video.value.duration * 1000))
    : item.value?.duration_ms || 0
}

const replaceDraftPreview = (url: string, owned: boolean) => {
  if (
    import.meta.client
    && draftPreviewOwned.value
    && draftPreviewUrl.value
    && draftPreviewUrl.value !== url
  ) {
    URL.revokeObjectURL(draftPreviewUrl.value)
  }
  draftPreviewUrl.value = url
  draftPreviewOwned.value = owned
}

const clearDraftPreview = () => {
  captureSequence += 1
  if (import.meta.client && draftPreviewOwned.value && draftPreviewUrl.value) {
    URL.revokeObjectURL(draftPreviewUrl.value)
  }
  draftPreviewUrl.value = ''
  draftPreviewOwned.value = false
}

const clearFileInput = () => {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const resetDraft = () => {
  clearDraftPreview()
  draftSource.value = null
  draftTimeMs.value = null
  draftError.value = null
  serverErrorVisible.value = false
  firstFrameSeekPending = false
  clearFileInput()
}

const clearSelectionMessages = () => {
  savedMessage.value = null
  draftError.value = null
  serverErrorVisible.value = false
}

const captureCurrentFrame = async (): Promise<void> => {
  if (!import.meta.client || !video.value) return

  const sourceVideo = video.value
  const width = sourceVideo.videoWidth
  const height = sourceVideo.videoHeight
  if (width < 1 || height < 1) {
    draftError.value = 'انتظر حتى تصبح أبعاد الفيديو جاهزة لاختيار اللقطة.'
    return
  }

  clearDraftPreview()
  draftSource.value = null
  draftTimeMs.value = null
  clearFileInput()
  const sequence = ++captureSequence
  const canvas = document.createElement('canvas')
  const targetWidth = Math.min(1280, width)
  const targetHeight = Math.max(1, Math.round(height * (targetWidth / width)))
  canvas.width = targetWidth
  canvas.height = targetHeight
  const context = canvas.getContext('2d')

  if (!context) {
    draftError.value = 'تعذر تجهيز معاينة اللقطة محليًا.'
    return
  }

  try {
    context.drawImage(sourceVideo, 0, 0, targetWidth, targetHeight)
    const blob = await new Promise<Blob | null>(resolve => {
      canvas.toBlob(resolve, 'image/jpeg', 0.9)
    })

    if (sequence !== captureSequence) return
    if (!blob) {
      draftError.value = 'تعذر إنشاء معاينة للّقطة المحددة.'
      return
    }

    const url = URL.createObjectURL(blob)
    if (sequence !== captureSequence) {
      URL.revokeObjectURL(url)
      return
    }

    replaceDraftPreview(url, true)
    draftSource.value = 'video_frame'
    draftTimeMs.value = currentTimeMs.value
    clearFileInput()
    savedMessage.value = null
    draftError.value = null
    serverErrorVisible.value = false
  } catch {
    if (sequence === captureSequence) {
      draftError.value = 'تعذر التقاط اللقطة الحالية من الفيديو.'
    }
  }
}

const onVideoSeeked = async () => {
  firstFrameSeekPending = false
  syncVideoTime()
  await captureCurrentFrame()
}

const onVideoPaused = async () => {
  syncVideoTime()
  if (!video.value || video.value.seeking || firstFrameSeekPending) return
  await captureCurrentFrame()
}

const chooseCurrentPoster = () => {
  if (!posterUrl.value) {
    draftError.value = 'لا تتوفر صورة معاينة حالية للاختيار.'
    return
  }
  clearSelectionMessages()
  replaceDraftPreview(posterUrl.value, false)
  draftSource.value = 'current_poster'
  draftTimeMs.value = null
  clearFileInput()
}

const chooseCurrentFrame = async () => {
  clearSelectionMessages()
  syncVideoTime()
  await captureCurrentFrame()
}

const chooseFirstFrame = async () => {
  if (!video.value) return
  clearSelectionMessages()
  clearDraftPreview()
  draftSource.value = null
  draftTimeMs.value = null
  clearFileInput()
  video.value.pause()
  if (Math.abs(video.value.currentTime) < 0.001) {
    syncVideoTime()
    await captureCurrentFrame()
    return
  }
  firstFrameSeekPending = true
  video.value.currentTime = 0
}

const onFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] || null
  clearSelectionMessages()
  clearDraftPreview()
  draftSource.value = null
  draftTimeMs.value = null
  selectedFile.value = null

  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    input.value = ''
    draftError.value = 'نوع صورة الغلاف غير مدعوم. اختر JPEG أو PNG أو WEBP.'
    return
  }

  const url = URL.createObjectURL(file)
  selectedFile.value = file
  replaceDraftPreview(url, true)
  draftSource.value = 'uploaded_image'
}

const saveDraft = async (): Promise<void> => {
  if (!item.value || saveDisabled.value) return
  serverErrorVisible.value = true
  draftError.value = null
  savedMessage.value = null

  let saved = false
  if (draftSource.value === 'current_poster') {
    saved = await props.manager.useCurrentVideoPoster(item.value.id)
  } else if (draftSource.value === 'video_frame' && draftTimeMs.value !== null) {
    saved = await props.manager.selectVideoCoverFrame(
      item.value.id,
      draftTimeMs.value,
    )
  } else if (draftSource.value === 'uploaded_image' && selectedFile.value) {
    saved = await props.manager.uploadVideoCover(item.value.id, selectedFile.value)
  }

  if (!saved) return
  resetDraft()
  await nextTick()
  close()
}

const removeCover = async () => {
  serverErrorVisible.value = true
  draftError.value = null
  savedMessage.value = null
  if (await props.manager.updateCover(null)) {
    resetDraft()
    savedMessage.value = props.manager.message.value || 'تمت إزالة غلاف العمل بنجاح.'
    await nextTick()
    closeButton.value?.focus()
  }
}

const close = () => {
  if (busy.value) return
  resetDraft()
  savedMessage.value = null
  props.manager.closeVideoCover()
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }
  if (event.key !== 'Tab' || !panel.value) return
  const controls = [...panel.value.querySelectorAll<HTMLElement>(
    'button:not([disabled]),input:not([disabled]),video[controls]',
  )]
  if (!controls.length) return
  const first = controls[0]!
  const last = controls[controls.length - 1]!
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

const restorePage = () => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = previousOverflow
}

watch(open, async isOpen => {
  if (!import.meta.client) return
  if (isOpen) {
    resetDraft()
    savedMessage.value = null
    serverErrorVisible.value = true
    currentTimeMs.value = 0
    durationMs.value = item.value?.duration_ms || 0
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    if (bodyScroll.value) {
      bodyScroll.value.scrollTop = 0
    }
    closeButton.value?.focus({ preventScroll: true })
  } else {
    resetDraft()
    savedMessage.value = null
    restorePage()
  }
})

onBeforeUnmount(() => {
  resetDraft()
  if (import.meta.client && open.value) restorePage()
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="item"
      class="fixed inset-0 z-[60] flex items-center justify-center overflow-hidden bg-black/80 p-3 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="designer-video-cover-title"
      @mousedown.self="close"
    >
      <section
        ref="panel"
        dir="rtl"
        class="flex h-[94dvh] max-h-[900px] w-full max-w-5xl flex-col overflow-hidden rounded-[20px] bg-white text-[#151515] shadow-2xl"
      >
        <header class="flex shrink-0 items-center justify-between gap-4 border-b border-black/10 bg-white px-4 py-3 sm:px-6">
          <div>
            <h2 id="designer-video-cover-title" class="text-xl font-extrabold">
              إدارة غلاف الفيديو
            </h2>
            <p class="mt-1 text-sm text-[#666666]">
              اختر الغلاف وعاينه، ثم احفظه عندما يصبح جاهزًا.
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            aria-label="إغلاق إدارة غلاف الفيديو"
            class="grid min-h-11 min-w-11 place-items-center rounded-xl border border-black/15 text-2xl disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
            :disabled="busy"
            @click="close"
          >
            ×
          </button>
        </header>

        <div ref="bodyScroll" class="min-h-0 flex-1 overflow-y-auto overscroll-contain">
          <div class="grid gap-4 p-4 sm:p-5 md:grid-cols-[minmax(0,1.2fr)_minmax(260px,0.8fr)] md:items-start">
            <div class="space-y-4">
              <div class="aspect-video w-full overflow-hidden rounded-2xl bg-[#111111]">
                <p
                  v-if="manager.videoCoverLoading.value"
                  class="grid h-full min-h-0 place-items-center p-6 text-white"
                  role="status"
                >
                  جارٍ تحميل الفيديو الخاص…
                </p>
                <video
                  v-else-if="manager.videoCoverVideoUrl.value"
                  ref="video"
                  :src="manager.videoCoverVideoUrl.value"
                  controls
                  preload="metadata"
                  class="h-full w-full object-contain"
                  @loadedmetadata="syncVideoTime"
                  @timeupdate="syncVideoTime"
                  @seeked="onVideoSeeked"
                  @pause="onVideoPaused"
                />
                <p v-else class="grid h-full min-h-0 place-items-center p-6 text-neutral-200">
                  تعذر عرض الفيديو.
                </p>
              </div>
              <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-neutral-100 p-3 text-sm font-bold">
                <span>الزمن الحالي: <bdi>{{ formatTime(currentTimeMs) }}</bdi></span>
                <span>المدة: <bdi>{{ formatTime(durationMs || item.duration_ms) }}</bdi></span>
                <span :class="item.is_cover ? 'text-emerald-700' : 'text-[#666666]'">
                  {{ item.is_cover ? 'الفيديو هو غلاف العمل' : 'الفيديو ليس غلاف العمل حاليًا' }}
                </span>
              </div>
            </div>

            <div class="space-y-4">
            <section class="rounded-2xl border border-black/10 p-4">
              <h3 class="font-extrabold">
                معاينة الغلاف المختار
              </h3>
              <img
                v-if="displayedPreviewUrl"
                :src="displayedPreviewUrl"
                alt="معاينة غلاف الفيديو المختار"
                class="mt-3 aspect-video w-full rounded-xl bg-neutral-100 object-cover"
              >
              <p v-else class="mt-3 rounded-xl bg-neutral-100 p-4 text-sm text-[#666666]">
                لا يتوفر غلاف للمعاينة حاليًا.
              </p>
              <p class="mt-3 rounded-lg bg-neutral-100 px-3 py-2 text-sm font-bold text-[#555555]">
                <template v-if="draftSource === 'current_poster'">
                  صورة المعاينة الحالية
                </template>
                <template v-else-if="draftSource === 'video_frame'">
                  لقطة من الفيديو عند <bdi>{{ formatTime(draftTimeMs) }}</bdi>
                </template>
                <template v-else-if="draftSource === 'uploaded_image'">
                  صورة مختارة من الجهاز
                </template>
                <template v-else>
                  الغلاف المحفوظ حاليًا
                </template>
              </p>
              <p
                v-if="draftSource"
                class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-bold text-amber-900"
              >
                لم يُحفظ هذا الغلاف بعد.
              </p>
            </section>

            <section class="rounded-2xl border border-black/10 p-4">
              <h3 class="font-extrabold">
                مصادر الغلاف
              </h3>
              <div class="mt-3 grid gap-2">
                <button
                  type="button"
                  class="min-h-11 rounded-xl border border-[#E21D1D] px-4 font-bold text-[#B81414] disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
                  :disabled="busy || !posterUrl"
                  @click="chooseCurrentPoster"
                >
                  اختيار صورة المعاينة الحالية
                </button>
                <button
                  type="button"
                  class="min-h-11 rounded-xl border border-black/15 px-4 font-bold disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
                  :disabled="busy || !manager.videoCoverVideoUrl.value"
                  @click="chooseCurrentFrame"
                >
                  اختيار اللقطة الحالية
                </button>
                <button
                  type="button"
                  class="min-h-11 rounded-xl border border-black/15 px-4 font-bold disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
                  :disabled="busy || !manager.videoCoverVideoUrl.value"
                  @click="chooseFirstFrame"
                >
                  اختيار اللقطة الأولى
                </button>
              </div>
            </section>

            <section class="rounded-2xl border border-black/10 p-4">
              <h3 class="font-extrabold">
                صورة مستقلة
              </h3>
              <label class="mt-3 block">
                <span class="mb-2 block text-sm font-bold">اختر صورة واحدة</span>
                <input
                  ref="fileInput"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  class="min-h-11 w-full rounded-xl border border-black/15 p-2 text-sm file:ml-3 file:rounded-lg file:border-0 file:bg-neutral-100 file:px-3 file:py-2 file:font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
                  :disabled="busy"
                  @change="onFileChange"
                >
              </label>
              <p v-if="selectedFile" class="mt-2 truncate text-sm font-bold text-[#555555]" dir="auto">
                {{ selectedFile.name }}
              </p>
              <p v-if="draftSource === 'uploaded_image'" class="mt-2 text-sm text-[#666666]">
                ستُرفع الصورة عند حفظ الغلاف.
              </p>
            </section>

            <button
              v-if="item.is_cover"
              type="button"
              class="min-h-11 w-full rounded-xl border border-red-200 px-4 font-bold text-[#B81414] disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
              :disabled="busy"
              @click="removeCover"
            >
              إزالة الغلاف
            </button>

            <p
              v-if="visibleError"
              class="rounded-xl bg-red-50 p-3 text-sm font-bold text-[#B81414]"
              role="alert"
              aria-live="assertive"
            >
              {{ visibleError }}
            </p>
            </div>
          </div>
        </div>

        <footer class="shrink-0 border-t border-black/10 bg-white p-4 sm:px-6">
          <p
            v-if="savedMessage"
            class="mb-3 rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700"
            role="status"
            aria-live="polite"
          >
            {{ savedMessage }}
          </p>
          <div class="grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              class="min-h-11 rounded-xl border border-black/15 px-4 font-bold disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
              :disabled="!draftSource || busy"
              @click="resetDraft"
            >
              التراجع عن الاختيار
            </button>
            <button
              type="button"
              class="min-h-11 rounded-xl bg-[#E21D1D] px-4 font-bold text-white disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
              :disabled="saveDisabled"
              @click="saveDraft"
            >
              {{ manager.videoCoverSaving.value ? 'جارٍ حفظ الغلاف…' : 'حفظ الغلاف' }}
            </button>
          </div>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
