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
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'
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
}>()

const lightboxOpen = ref(false)
const lightboxPanel = ref<HTMLElement | null>(null)
const enlargeButton = ref<HTMLButtonElement | null>(null)
const lightboxTitleId = 'ym-media-lightbox-title'
let previousBodyOverflow = ''

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

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${formatYmNumber(Math.max(1, Math.round(bytes / 1024)), props.locale)} KB`
  }
  if (bytes < 1024 * 1024 * 1024) {
    return `${formatYmNumber(bytes / (1024 * 1024), props.locale, { maximumFractionDigits: 1 })} MB`
  }
  return `${formatYmNumber(bytes / (1024 * 1024 * 1024), props.locale, { maximumFractionDigits: 2 })} GB`
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

onBeforeUnmount(() => {
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
