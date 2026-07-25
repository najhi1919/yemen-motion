<template>
  <section class="ym-media-preview" :aria-busy="busy">
    <div class="ym-media-preview__stage">
      <img
        v-if="item.kind === 'image' && previewUrl"
        :src="previewUrl"
        :alt="`${copy.preview} ${item.original_name}`"
      />
      <video
        v-else-if="item.kind === 'video' && previewUrl"
        :src="previewUrl"
        controls
        preload="metadata"
      />
      <div v-else class="ym-media-preview__fallback">
        <span aria-hidden="true">{{ previewError ? '!' : item.kind === 'video' ? '▶' : '▧' }}</span>
        <strong>{{ previewError ? copy.failed : copy.loading }}</strong>
        <button v-if="previewError" type="button" @click="$emit('retry')">
          {{ copy.retry }}
        </button>
      </div>
      <span v-if="item.is_cover" class="ym-media-preview__cover">{{ copy.currentCover }}</span>
      <span class="ym-media-preview__kind">{{ kindLabel }}</span>
    </div>

    <div class="ym-media-preview__identity">
      <div>
        <p>{{ copy.selected }}</p>
          <h3 dir="auto" :title="item.original_name">{{ item.original_name }}</h3>
      </div>
      <button
        ref="enlargeButton"
        type="button"
        class="ym-media-preview__enlarge"
        :disabled="!previewUrl"
        @click="openLightbox"
      >
        {{ copy.enlarge }}
      </button>
    </div>

    <section
      v-if="item.kind === 'video' && item.processing_status !== 'ready'"
      class="ym-media-processing"
      :class="`is-${item.processing_status}`"
      :aria-labelledby="processingTitleId"
    >
      <header>
        <div>
          <p>{{ copy.backgroundProcessing }}</p>
          <h4 :id="processingTitleId">{{ processingStageLabel }}</h4>
          <span>{{ processingDescription }}</span>
        </div>
        <strong>{{ formatYmNumber(processingProgress, locale) }}%</strong>
      </header>
      <div
        class="ym-media-processing__bar"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="processingProgress"
        :aria-label="processingStageLabel"
      >
        <i :style="{ inlineSize: `${processingProgress}%` }" />
      </div>
      <div class="ym-media-processing__timing" role="status" aria-live="polite">
        <span v-if="elapsedSeconds !== null">
          {{ copy.elapsed }}: {{ formatRuntime(elapsedSeconds) }}
        </span>
        <span v-if="processingProgress === 0">
          {{ copy.etaUnavailable }}
        </span>
        <span v-else-if="etaSeconds !== null">
          {{ copy.etaApproximate }} {{ formatRuntime(etaSeconds) }}
        </span>
      </div>
      <ol>
        <li
          v-for="stage in processingStages"
          :key="stage.key"
          :class="stageState(stage.progress)"
        >
          <span aria-hidden="true">{{ stageState(stage.progress) === 'is-complete' ? '✓' : '•' }}</span>
          {{ stage.label }}
        </li>
      </ol>
      <p v-if="pendingStartDelayed" class="ym-media-processing__stalled">
        {{ copy.notStarted }}
      </p>
      <p v-else-if="item.processing_status === 'failed'" class="ym-media-processing__stalled">
        {{ copy.stalled }}
      </p>
      <button
        v-if="item.can_retry_processing && editable"
        type="button"
        class="ym-media-processing__retry"
        :disabled="busy"
        @click="$emit('retryProcessing')"
      >
        {{ copy.retryProcessing }}
      </button>
    </section>

    <dl class="ym-media-preview__meta">
      <div><dt>{{ copy.type }}</dt><dd dir="ltr">{{ item.mime_type }}</dd></div>
      <div><dt>{{ copy.size }}</dt><dd>{{ formatSize(item.size_bytes) }}</dd></div>
      <div v-if="dimensions"><dt>{{ copy.dimensions }}</dt><dd dir="ltr">{{ dimensions }}</dd></div>
      <div v-if="item.duration_ms !== null"><dt>{{ copy.duration }}</dt><dd>{{ duration }}</dd></div>
      <div><dt>{{ copy.uploaded }}</dt><dd class="is-date">{{ formatYmDateTime(item.created_at, locale) }}</dd></div>
      <div><dt>{{ copy.position }}</dt><dd>{{ formatYmNumber(index + 1, locale) }} / {{ formatYmNumber(total, locale) }}</dd></div>
      <div><dt>{{ copy.processing }}</dt><dd>{{ processingLabel }}</dd></div>
      <div v-if="item.kind === 'image'"><dt>{{ copy.cover }}</dt><dd>{{ item.is_cover ? copy.yes : copy.no }}</dd></div>
    </dl>

    <div v-if="editable" class="ym-media-preview__actions">
      <button
        v-if="canSetCover"
        type="button"
        class="is-cover"
        :disabled="busy"
        @click="$emit('setCover')"
      >
        {{ copy.setCover }}
      </button>
      <button
        v-if="item.is_cover && canClearCover"
        type="button"
        class="is-secondary"
        :disabled="busy"
        @click="$emit('clearCover')"
      >
        {{ copy.clearCover }}
      </button>
      <button
        v-if="canReorder"
        type="button"
        class="is-secondary"
        :disabled="index === 0 || busy"
        @click="$emit('move', -1)"
      >
        {{ copy.up }}
      </button>
      <button
        v-if="canReorder"
        type="button"
        class="is-secondary"
        :disabled="index === total - 1 || busy"
        @click="$emit('move', 1)"
      >
        {{ copy.down }}
      </button>
      <button
        type="button"
        class="is-danger"
        :disabled="busy"
        @click="requestRemove"
      >
        {{ copy.remove }}
      </button>
    </div>

    <aside v-if="coverNotice" class="ym-media-preview__no-cover" role="note">
      {{ coverNotice }}
    </aside>

    <Teleport to="body">
      <div
        v-if="lightboxOpen"
        class="ym-media-lightbox"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="lightboxTitleId"
        @mousedown.self="closeLightbox"
      >
        <div ref="lightboxPanel" class="ym-media-lightbox__panel" tabindex="-1">
          <header>
            <h2 :id="lightboxTitleId" dir="auto" :title="item.original_name">{{ item.original_name }}</h2>
            <button type="button" :aria-label="copy.close" @click="closeLightbox">×</button>
          </header>
          <img v-if="item.kind === 'image'" :src="previewUrl" :alt="item.original_name" />
          <video v-else :src="previewUrl" controls preload="metadata" />
        </div>
      </div>
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { formatYmDateTime, formatYmNumber } from '~/utils/ymFormatting'

interface MediaItem {
  id: number
  kind: 'image' | 'video'
  original_name: string
  mime_type: string
  size_bytes: number
  width: number | null
  height: number | null
  duration_ms: number | null
  processing_status: 'pending' | 'ready' | 'failed'
  processing_stage: 'queued' | 'validating' | 'probing' | 'extracting_metadata' | 'generating_poster' | 'finalizing' | 'ready' | 'failed'
  processing_progress: number
  processing_started_at: string | null
  processing_completed_at: string | null
  processing_attempts: number
  processing_message: string
  can_retry_processing: boolean
  is_cover: boolean
  created_at: string | null
}

const props = defineProps<{
  item: MediaItem
  previewUrl: string
  previewError: boolean
  index: number
  total: number
  locale: 'ar' | 'en'
  editable: boolean
  busy: boolean
  hasCover: boolean
  canClearCover: boolean
  canReorder: boolean
}>()

const emit = defineEmits<{
  setCover: []
  clearCover: []
  move: [delta: number]
  remove: [anchor: HTMLElement]
  retry: []
  retryProcessing: []
}>()

const lightboxOpen = ref(false)
const lightboxPanel = ref<HTMLElement | null>(null)
const enlargeButton = ref<HTMLButtonElement | null>(null)
const lightboxTitleId = 'ym-media-lightbox-title'
const processingTitleId = 'ym-media-processing-title'
const clockNow = ref(0)
const progressSamples = ref<Array<{ attempt: number; at: number; progress: number }>>([])
let previousBodyOverflow = ''
let clockTimer: ReturnType<typeof setInterval> | null = null

const copy = computed(() => props.locale === 'ar' ? {
  preview: 'معاينة',
  failed: 'تعذرت معاينة هذا الوسيط',
  loading: 'جارٍ تحميل المعاينة المحمية',
  currentCover: 'الغلاف الحالي',
  selected: 'الوسيط المحدد',
  enlarge: 'تكبير المعاينة',
  type: 'نوع الملف',
  size: 'الحجم',
  dimensions: 'الأبعاد',
  duration: 'المدة',
  uploaded: 'تاريخ الرفع',
  position: 'الترتيب',
  processing: 'المعالجة',
  cover: 'الغلاف',
  yes: 'نعم، الغلاف الحالي',
  no: 'ليس غلافًا',
  setCover: 'تعيين كغلاف',
  clearCover: 'إزالة الغلاف',
  up: 'تحريك إلى أعلى',
  down: 'تحريك إلى أسفل',
  remove: 'إزالة من العمل',
  noCover: 'لا يوجد غلاف محدد لهذا العمل.',
  coverReadyOnly: 'الغلاف متاح للصور الجاهزة فقط.',
  retry: 'إعادة تحميل المعاينة',
  backgroundProcessing: 'المعالجة مستمرة في الخلفية',
  retryProcessing: 'إعادة محاولة المعالجة',
  elapsed: 'الوقت المنقضي',
  etaUnavailable: 'الوقت المتبقي غير متاح حتى تبدأ المعالجة.',
  etaApproximate: 'الوقت المتبقي نحو',
  notStarted: 'لم تبدأ المعالجة بعد؛ خدمة المعالجة قد تكون غير متاحة.',
  stalled: 'توقفت المعالجة قبل اكتمالها. أعد المحاولة، أوأزل الملف وارفع نسخة أخرى.',
  close: 'إغلاق المعاينة المكبرة'
} : {
  preview: 'Preview',
  failed: 'This media could not be previewed',
  loading: 'Loading protected preview',
  currentCover: 'Current cover',
  selected: 'Selected media',
  enlarge: 'Enlarge preview',
  type: 'File type',
  size: 'Size',
  dimensions: 'Dimensions',
  duration: 'Duration',
  uploaded: 'Uploaded',
  position: 'Order',
  processing: 'Processing',
  cover: 'Cover',
  yes: 'Yes, current cover',
  no: 'Not a cover',
  setCover: 'Set as cover',
  clearCover: 'Remove cover',
  up: 'Move up',
  down: 'Move down',
  remove: 'Remove from work',
  noCover: 'No cover is selected for this work.',
  coverReadyOnly: 'A cover is available for ready images only.',
  retry: 'Retry preview',
  backgroundProcessing: 'Processing continues in the background',
  retryProcessing: 'Retry processing',
  elapsed: 'Elapsed',
  etaUnavailable: 'Remaining time is unavailable until processing starts.',
  etaApproximate: 'Approximately',
  notStarted: 'Processing has not started yet; the processing service may be unavailable.',
  stalled: 'Processing stopped before completion. Retry, or remove the file and upload another copy.',
  close: 'Close enlarged preview'
})

const canSetCover = computed(() => props.item.kind === 'image'
  && props.item.processing_status === 'ready'
  && !props.item.is_cover)
const kindLabel = computed(() => props.locale === 'ar'
  ? props.item.kind === 'image' ? 'صورة' : 'فيديو'
  : props.item.kind === 'image' ? 'Image' : 'Video')
const dimensions = computed(() => props.item.width && props.item.height
  ? `${formatYmNumber(props.item.width, props.locale)} × ${formatYmNumber(props.item.height, props.locale)}`
  : '')
const duration = computed(() => {
  if (props.item.duration_ms === null) return '—'
  const seconds = props.item.duration_ms / 1000
  return props.locale === 'ar'
    ? `${formatYmNumber(seconds, props.locale, { maximumFractionDigits: 1 })} ثانية`
    : `${formatYmNumber(seconds, props.locale, { maximumFractionDigits: 1 })} sec`
})
const coverNotice = computed(() => {
  if (props.item.kind !== 'image') return ''
  if (props.item.processing_status !== 'ready') return copy.value.coverReadyOnly
  return props.hasCover ? '' : copy.value.noCover
})
const processingLabel = computed(() => {
  if (props.locale === 'en') {
    if (props.item.processing_status === 'ready') return 'Ready'
    if (props.item.processing_status === 'pending') {
      return props.previewUrl ? 'Processing — initial preview available' : 'Processing'
    }
    return 'Failed'
  }
  if (props.item.processing_status === 'ready') return 'جاهز'
  if (props.item.processing_status === 'pending') {
    return props.previewUrl ? 'قيد المعالجة — المعاينة الأولية متاحة' : 'قيد المعالجة'
  }
  return 'فشلت المعالجة'
})
const processingProgress = computed(() => Math.max(0, Math.min(100, Number(props.item.processing_progress) || 0)))
const pendingStartDelayed = computed(() => {
  if (props.item.processing_status !== 'pending' || processingProgress.value !== 0 || clockNow.value === 0) {
    return false
  }
  const queuedAt = Date.parse(props.item.processing_started_at ?? props.item.created_at ?? '')
  return Number.isFinite(queuedAt) && clockNow.value - queuedAt >= 120_000
})
const elapsedSeconds = computed(() => {
  if (!props.item.processing_started_at || clockNow.value === 0) return null
  const startedAt = Date.parse(props.item.processing_started_at)
  if (!Number.isFinite(startedAt)) return null
  const finishedAt = props.item.processing_completed_at
    ? Date.parse(props.item.processing_completed_at)
    : clockNow.value
  if (!Number.isFinite(finishedAt) || finishedAt < startedAt) return null
  return Math.floor((finishedAt - startedAt) / 1000)
})
const etaSeconds = computed(() => {
  if (props.item.processing_status !== 'pending' || processingProgress.value <= 0) return null
  const samples = progressSamples.value
  if (samples.length < 2 || clockNow.value === 0) return null
  const previous = samples[samples.length - 2]!
  const current = samples[samples.length - 1]!
  if (current.attempt !== previous.attempt || current.progress <= previous.progress) return null
  if (clockNow.value - current.at > 8_000) return null
  const elapsedMs = current.at - previous.at
  if (elapsedMs <= 0) return null
  const estimate = Math.ceil(((100 - current.progress) * elapsedMs) / ((current.progress - previous.progress) * 1000))
  return estimate > 0 && estimate <= 86_400 ? estimate : null
})
const stageCopies = computed(() => props.locale === 'ar' ? {
  queued: 'في طابور المعالجة',
  validating: 'التحقق من الملف',
  probing: 'قراءة بيانات الفيديو',
  extracting_metadata: 'حفظ بيانات الفيديو',
  generating_poster: 'إنشاء صورة المعاينة',
  finalizing: 'إنهاء المعالجة',
  ready: 'اكتملت المعالجة',
  failed: 'فشلت المعالجة'
} : {
  queued: 'Queued',
  validating: 'Validating file',
  probing: 'Reading video metadata',
  extracting_metadata: 'Saving video metadata',
  generating_poster: 'Generating preview image',
  finalizing: 'Finalizing processing',
  ready: 'Processing complete',
  failed: 'Processing failed'
})
const processingStageLabel = computed(() => stageCopies.value[props.item.processing_stage] ?? processingLabel.value)
const processingDescription = computed(() => {
  if (props.item.processing_status === 'failed') {
    return props.locale === 'ar'
      ? 'تعذر إكمال معالجة الفيديو. لم تُعرض تفاصيل النظام حفاظًا على الأمان.'
      : 'Video processing could not be completed. System details are hidden for security.'
  }
  return props.locale === 'ar'
    ? 'يمكنك متابعة العمل، وستتحدث الجاهزية تلقائيًا بعد اكتمال المعالجة.'
    : 'You can continue working; readiness updates automatically when processing completes.'
})
const processingStages = computed(() => [
  { key: 'queued', progress: 0, label: stageCopies.value.queued },
  { key: 'validating', progress: 5, label: stageCopies.value.validating },
  { key: 'probing', progress: 20, label: stageCopies.value.probing },
  { key: 'extracting_metadata', progress: 45, label: stageCopies.value.extracting_metadata },
  { key: 'generating_poster', progress: 65, label: stageCopies.value.generating_poster },
  { key: 'finalizing', progress: 90, label: stageCopies.value.finalizing }
])

function stageState(stageProgress: number): string {
  if (processingProgress.value > stageProgress) return 'is-complete'
  if (processingProgress.value === stageProgress && props.item.processing_status === 'pending') return 'is-current'
  return 'is-upcoming'
}

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${formatYmNumber(Math.max(1, Math.round(bytes / 1024)), props.locale)} KB`
  }
  if (bytes < 1024 * 1024 * 1024) {
    return `${formatYmNumber(bytes / (1024 * 1024), props.locale, { maximumFractionDigits: 1 })} MB`
  }
  return `${formatYmNumber(bytes / (1024 * 1024 * 1024), props.locale, { maximumFractionDigits: 2 })} GB`
}

function formatRuntime(totalSeconds: number): string {
  const safeSeconds = Math.max(0, Math.floor(totalSeconds))
  const hours = Math.floor(safeSeconds / 3600)
  const minutes = Math.floor((safeSeconds % 3600) / 60)
  const seconds = safeSeconds % 60
  const parts: string[] = []
  if (hours > 0) {
    parts.push(`${formatYmNumber(hours, props.locale)} ${props.locale === 'ar' ? 'س' : 'hr'}`)
  }
  if (minutes > 0 || hours > 0) {
    parts.push(`${formatYmNumber(minutes, props.locale)} ${props.locale === 'ar' ? 'د' : 'min'}`)
  }
  if (hours === 0) {
    parts.push(`${formatYmNumber(seconds, props.locale)} ${props.locale === 'ar' ? 'ث' : 'sec'}`)
  }
  return parts.join(' ')
}

function requestRemove(event: MouseEvent) {
  const anchor = event.currentTarget
  if (anchor instanceof HTMLElement) {
    emit('remove', anchor)
  }
}

async function openLightbox() {
  if (!props.previewUrl) return
  lightboxOpen.value = true
  previousBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  document.addEventListener('keydown', onLightboxKeydown)
  await nextTick()
  lightboxPanel.value?.focus()
}

function closeLightbox() {
  if (!lightboxOpen.value) return
  lightboxOpen.value = false
  document.body.style.overflow = previousBodyOverflow
  document.removeEventListener('keydown', onLightboxKeydown)
  nextTick(() => enlargeButton.value?.focus())
}

function onLightboxKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    closeLightbox()
    return
  }
  if (event.key !== 'Tab' || !lightboxPanel.value) return
  const focusable = [...lightboxPanel.value.querySelectorAll<HTMLElement>('button,[href],video,[tabindex]:not([tabindex="-1"])')]
    .filter(element => !element.hasAttribute('disabled'))
  if (!focusable.length) {
    event.preventDefault()
    lightboxPanel.value.focus()
    return
  }
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(
  () => ({
    attempt: Number(props.item.processing_attempts) || 0,
    id: props.item.id,
    progress: processingProgress.value,
    status: props.item.processing_status,
  }),
  (current, previous) => {
    clockNow.value = Date.now()
    if (
      current.status !== 'pending'
      || (previous && (current.id !== previous.id
        || current.attempt !== previous.attempt
        || current.progress < previous.progress))
    ) {
      progressSamples.value = []
    }
    if (
      current.status === 'pending'
      && current.progress > 0
      && (!previous
        || current.id !== previous.id
        || current.attempt !== previous.attempt
        || current.progress > previous.progress)
    ) {
      progressSamples.value = [
        ...progressSamples.value.slice(-1),
        { attempt: current.attempt, at: clockNow.value, progress: current.progress },
      ]
    }
  },
  { immediate: true },
)

onMounted(() => {
  clockNow.value = Date.now()
  clockTimer = setInterval(() => {
    clockNow.value = Date.now()
  }, 1_000)
})

onBeforeUnmount(() => {
  if (clockTimer !== null) clearInterval(clockTimer)
  document.removeEventListener('keydown', onLightboxKeydown)
  if (lightboxOpen.value) document.body.style.overflow = previousBodyOverflow
})
</script>

<style scoped>
.ym-media-preview{display:grid;gap:16px;min-width:0}.ym-media-preview__stage{position:relative;display:grid;place-items:center;aspect-ratio:16/9;max-height:560px;overflow:hidden;border:1px solid rgba(139,92,246,.26);border-radius:17px;background-color:#020617;background-image:linear-gradient(45deg,rgba(148,163,184,.055) 25%,transparent 25%),linear-gradient(-45deg,rgba(148,163,184,.055) 25%,transparent 25%),linear-gradient(45deg,transparent 75%,rgba(148,163,184,.055) 75%),linear-gradient(-45deg,transparent 75%,rgba(148,163,184,.055) 75%);background-size:24px 24px;background-position:0 0,0 12px,12px -12px,-12px 0}.ym-media-preview__stage img,.ym-media-preview__stage video{width:100%;height:100%;max-height:560px;object-fit:contain}.ym-media-preview__fallback{display:grid;place-items:center;gap:8px;color:#a7b2c7}.ym-media-preview__fallback span{font-size:42px;color:#8b5cf6}.ym-media-preview__fallback button{min-height:40px;padding:0 13px;border:1px solid rgba(139,92,246,.35);border-radius:10px;background:rgba(139,92,246,.1);color:#e2e8f0;font-weight:800}.ym-media-preview__cover,.ym-media-preview__kind{position:absolute;top:12px;padding:5px 9px;border-radius:999px;font-size:11.5px;font-weight:900}.ym-media-preview__cover{inset-inline-start:12px;background:#f59e0b;color:#111827}.ym-media-preview__kind{inset-inline-end:12px;background:rgba(2,6,23,.82);color:#e2e8f0}.ym-media-preview__identity{display:flex;align-items:center;justify-content:space-between;gap:12px}.ym-media-preview__identity p{margin:0 0 3px;color:#22d3ee;font-size:12px;font-weight:900}.ym-media-preview__identity h3{margin:0;overflow-wrap:anywhere;font-size:18px;line-height:1.35}.ym-media-preview__enlarge,.ym-media-preview__actions button{min-height:44px;padding:0 14px;border:1px solid rgba(139,92,246,.3);border-radius:11px;background:rgba(139,92,246,.09);color:inherit;font-weight:800}.ym-media-preview__meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin:0;border-block:1px solid rgba(148,163,184,.15)}.ym-media-preview__meta div{display:grid;grid-template-columns:minmax(92px,.7fr) minmax(0,1.3fr);gap:8px;padding:10px 8px;border-block-end:1px solid rgba(148,163,184,.1)}.ym-media-preview__meta dt{color:var(--ym-media-muted,#a7b2c7);font-size:12.5px}.ym-media-preview__meta dd{margin:0;overflow-wrap:anywhere;font-size:13px;font-weight:700}.ym-media-preview__meta .is-date{direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums}.ym-media-preview__actions{display:flex;flex-wrap:wrap;gap:8px}.ym-media-preview__actions .is-cover{border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.12);color:#fbbf24}.ym-media-preview__actions .is-danger{border-color:rgba(244,63,94,.4);background:rgba(244,63,94,.1);color:#fda4af}.ym-media-preview__actions button:disabled,.ym-media-preview__enlarge:disabled{opacity:.42;cursor:not-allowed}.ym-media-preview button:focus-visible{outline:3px solid rgba(34,211,238,.34);outline-offset:2px}.ym-media-preview__no-cover{padding:10px 12px;border:1px solid rgba(245,158,11,.22);border-radius:11px;background:rgba(245,158,11,.07);color:#fbbf24;font-size:13px}.ym-media-lightbox{position:fixed;inset:0;z-index:1700;display:grid;place-items:center;padding:22px;background:rgba(2,6,23,.82);backdrop-filter:blur(6px)}.ym-media-lightbox__panel{display:grid;grid-template-rows:auto minmax(0,1fr);width:min(94vw,1200px);height:min(92dvh,900px);overflow:hidden;border:1px solid rgba(139,92,246,.45);border-radius:18px;background:#020617;box-shadow:0 30px 90px rgba(0,0,0,.55)}.ym-media-lightbox__panel header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;border-block-end:1px solid rgba(148,163,184,.16)}.ym-media-lightbox__panel h2{margin:0;color:#f8fafc;font-size:15px;overflow-wrap:anywhere}.ym-media-lightbox__panel button{display:grid;place-items:center;width:42px;height:42px;border:1px solid rgba(148,163,184,.25);border-radius:11px;background:rgba(15,23,42,.82);color:#fff;font-size:23px}.ym-media-lightbox__panel img,.ym-media-lightbox__panel video{width:100%;height:100%;min-height:0;object-fit:contain}:global(.ym-media-manager.is-light) .ym-media-preview__meta{border-color:rgba(100,116,139,.18)}:global(.ym-media-manager.is-light) .ym-media-preview__identity h3,:global(.ym-media-manager.is-light) .ym-media-preview__meta dd{color:#172033}@media(max-width:700px){.ym-media-preview__stage{max-height:430px}.ym-media-preview__identity{align-items:stretch;flex-direction:column}.ym-media-preview__meta{grid-template-columns:1fr}.ym-media-preview__actions{display:grid;grid-template-columns:1fr 1fr}.ym-media-preview__actions button{min-height:44px}.ym-media-lightbox{padding:0}.ym-media-lightbox__panel{width:100vw;height:100dvh;border:0;border-radius:0}}@media(max-width:420px){.ym-media-preview__actions{grid-template-columns:1fr}.ym-media-preview__meta div{grid-template-columns:90px minmax(0,1fr)}}@media(prefers-reduced-motion:reduce){.ym-media-lightbox{backdrop-filter:none}}
.ym-media-preview__identity > div{min-width:0;flex:1}
.ym-media-preview__identity h3{max-inline-size:min(100%,620px);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;unicode-bidi:plaintext;text-align:start}
.ym-media-lightbox__panel h2[dir="auto"]{unicode-bidi:plaintext;text-align:start}
</style>

<style scoped>
.ym-media-processing {
  display: grid;
  gap: 13px;
  border: 1px solid rgba(139, 92, 246, .28);
  border-radius: 15px;
  padding: 15px;
  background: rgba(124, 58, 237, .06);
}

.ym-media-processing.is-failed {
  border-color: rgba(244, 63, 94, .34);
  background: rgba(244, 63, 94, .06);
}

.ym-media-processing header {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.ym-media-processing header > div {
  min-width: 0;
}

.ym-media-processing header p,
.ym-media-processing header h4,
.ym-media-processing header span {
  margin: 0;
}

.ym-media-processing header p {
  color: #22d3ee;
  font-size: 12px;
  font-weight: 900;
}

.ym-media-processing header h4 {
  margin-top: 3px;
  font-size: 16px;
}

.ym-media-processing header span {
  display: block;
  margin-top: 4px;
  color: var(--ym-media-muted, #a7b2c7);
  font-size: 12.5px;
  line-height: 1.6;
}

.ym-media-processing header > strong {
  flex: 0 0 auto;
  color: #c4b5fd;
  font-size: 17px;
  font-variant-numeric: tabular-nums;
}

.ym-media-processing__bar {
  height: 8px;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(148, 163, 184, .16);
}

.ym-media-processing__bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #7c3aed, #ec4899, #22d3ee);
  transition: inline-size .2s ease;
}

.ym-media-processing__timing {
  display: flex;
  flex-wrap: wrap;
  gap: 6px 14px;
  color: var(--ym-media-muted, #a7b2c7);
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
}

.ym-media-processing ol {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 7px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ym-media-processing li {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 6px;
  color: var(--ym-media-muted, #a7b2c7);
  font-size: 11.75px;
}

.ym-media-processing li span {
  display: grid;
  width: 18px;
  height: 18px;
  flex: 0 0 18px;
  place-items: center;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 50%;
  font-size: 10px;
}

.ym-media-processing li.is-complete {
  color: #34d399;
}

.ym-media-processing li.is-current {
  color: #c4b5fd;
  font-weight: 850;
}

.ym-media-processing li.is-current span {
  border-color: #8b5cf6;
  background: rgba(139, 92, 246, .18);
}

.ym-media-processing__stalled {
  margin: 0;
  color: #fbbf24;
  font-size: 12.5px;
  line-height: 1.6;
}

.ym-media-processing__retry {
  min-height: 44px;
  justify-self: start;
  border: 1px solid rgba(244, 63, 94, .4);
  border-radius: 11px;
  padding: 0 14px;
  color: #fecdd3;
  background: rgba(244, 63, 94, .1);
  font-weight: 850;
}

:global(.ym-media-manager.is-light) .ym-media-processing header > strong,
:global(.ym-media-manager.is-light) .ym-media-processing li.is-current {
  color: #6d28d9;
}

:global(.ym-media-manager.is-light) .ym-media-processing__stalled {
  color: #92400e;
}

:global(.ym-media-manager.is-light) .ym-media-processing__retry {
  color: #be123c;
}

@media (max-width: 700px) {
  .ym-media-processing ol {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 420px) {
  .ym-media-processing header {
    flex-direction: column;
  }

  .ym-media-processing ol {
    grid-template-columns: 1fr;
  }

  .ym-media-processing__retry {
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-media-processing__bar i {
    transition: none;
  }
}
</style>
