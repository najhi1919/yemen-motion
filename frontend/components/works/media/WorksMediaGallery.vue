<template>
  <div class="ym-media-gallery" role="listbox" :aria-label="copy.label">
    <article
      v-for="(item, index) in items"
      :key="item.id"
      class="ym-media-thumb"
      :class="{ 'is-selected': item.id === selectedId, 'is-cover': item.is_cover, 'is-dragging': draggedId === item.id }"
      role="presentation"
      :draggable="canOrganize && !busy"
      @dragstart="$emit('dragStart', item.id)"
      @dragover.prevent
      @drop="$emit('drop', item.id)"
      @dragend="$emit('dragEnd')"
    >
      <button
        class="ym-media-thumb__select"
        type="button"
        role="option"
        :aria-selected="item.id === selectedId"
        :aria-current="item.id === selectedId ? 'true' : undefined"
        @click="$emit('select', item.id)"
      >
        <span class="ym-media-thumb__visual">
          <img
            v-if="item.kind === 'image' && previewUrls[item.id]"
            :src="previewUrls[item.id]"
            :alt="''"
          />
          <video
            v-else-if="item.kind === 'video' && previewUrls[item.id]"
            :src="previewUrls[item.id]"
            muted
            preload="metadata"
            aria-hidden="true"
          />
          <span v-else class="ym-media-thumb__placeholder" aria-hidden="true">
            {{ previewErrors[item.id] ? '!' : item.kind === 'video' ? '▶' : '▧' }}
          </span>
          <b class="ym-media-thumb__position">{{ formatYmNumber(index + 1, locale) }}</b>
          <em v-if="item.is_cover">{{ copy.cover }}</em>
        </span>
        <span class="ym-media-thumb__details">
            <strong dir="auto" :title="item.original_name">{{ item.original_name }}</strong>
          <small>{{ kindLabel(item.kind) }} · {{ formatSize(item.size_bytes) }}</small>
          <small>{{ processingLabel(item.processing_status, Boolean(previewUrls[item.id])) }}</small>
        </span>
      </button>

      <div v-if="canOrganize" class="ym-media-thumb__order" :aria-label="copy.order">
        <span aria-hidden="true" class="ym-media-thumb__handle">⠿</span>
        <button
          type="button"
          :aria-label="copy.up"
          :title="copy.up"
          :disabled="index === 0 || busy"
          @click="$emit('move', index, -1)"
        >↑</button>
        <button
          type="button"
          :aria-label="copy.down"
          :title="copy.down"
          :disabled="index === items.length - 1 || busy"
          @click="$emit('move', index, 1)"
        >↓</button>
      </div>
    </article>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'

interface MediaItem {
  id: number
  kind: 'image' | 'video'
  original_name: string
  size_bytes: number
  processing_status: 'pending' | 'ready' | 'failed'
  is_cover: boolean
}

const props = defineProps<{
  items: MediaItem[]
  selectedId: number | null
  previewUrls: Record<number, string>
  previewErrors: Record<number, boolean>
  draggedId: number | null
  canOrganize: boolean
  busy: boolean
  locale: 'ar' | 'en'
}>()

defineEmits<{
  select: [id: number]
  move: [index: number, delta: number]
  dragStart: [id: number]
  drop: [id: number]
  dragEnd: []
}>()

const copy = computed(() => props.locale === 'ar' ? {
  label: 'قائمة وسائط العمل',
  cover: 'الغلاف',
  order: 'تغيير ترتيب الوسيط',
  up: 'تحريك الوسيط إلى أعلى',
  down: 'تحريك الوسيط إلى أسفل'
} : {
  label: 'Work media list',
  cover: 'Cover',
  order: 'Change media order',
  up: 'Move media up',
  down: 'Move media down'
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

function kindLabel(kind: MediaItem['kind']): string {
  if (props.locale === 'en') return kind === 'image' ? 'Image' : 'Video'
  return kind === 'image' ? 'صورة' : 'فيديو'
}

function processingLabel(status: MediaItem['processing_status'], previewAvailable: boolean): string {
  if (props.locale === 'en') {
    if (status === 'ready') return 'Ready'
    if (status === 'pending') return previewAvailable ? 'Processing — initial preview available' : 'Processing'
    return 'Processing failed'
  }
  if (status === 'ready') return 'جاهز'
  if (status === 'pending') return previewAvailable ? 'قيد المعالجة — المعاينة الأولية متاحة' : 'قيد المعالجة'
  return 'فشلت المعالجة'
}
</script>

<style scoped>
.ym-media-gallery{display:grid;gap:10px;align-content:start}.ym-media-thumb{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:8px;border:1px solid rgba(148,163,184,.18);border-radius:14px;background:rgba(15,23,42,.2);transition:border-color .17s ease,box-shadow .17s ease,transform .17s ease}.ym-media-thumb:hover{transform:translateY(-1px);border-color:rgba(139,92,246,.42)}.ym-media-thumb.is-selected{border-color:rgba(34,211,238,.68);box-shadow:0 0 0 2px rgba(34,211,238,.11),0 10px 26px rgba(34,211,238,.08)}.ym-media-thumb.is-cover::after{content:"";position:absolute;inset-block:12px;inset-inline-start:0;width:3px;border-radius:999px;background:#f59e0b}.ym-media-thumb.is-dragging{opacity:.52}.ym-media-thumb__select{display:grid;grid-template-columns:82px minmax(0,1fr);gap:10px;align-items:center;min-width:0;padding:0;border:0;background:none;color:inherit;text-align:start;cursor:pointer}.ym-media-thumb__visual{position:relative;display:grid;place-items:center;aspect-ratio:16/10;overflow:hidden;border-radius:10px;background:#020617}.ym-media-thumb__visual img,.ym-media-thumb__visual video{width:100%;height:100%;object-fit:cover}.ym-media-thumb__placeholder{color:#94a3b8;font-size:22px}.ym-media-thumb__position,.ym-media-thumb__visual em{position:absolute;padding:2px 6px;border-radius:999px;font-size:10px;font-style:normal;line-height:1.5}.ym-media-thumb__position{inset-block-start:5px;inset-inline-end:5px;background:rgba(2,6,23,.82);color:#fff}.ym-media-thumb__visual em{inset-block-end:5px;inset-inline-start:5px;background:#f59e0b;color:#111827;font-weight:900}.ym-media-thumb__details{display:grid;gap:3px;min-width:0}.ym-media-thumb__details strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13.5px}.ym-media-thumb__details small{color:var(--ym-media-muted,#a7b2c7);font-size:11.75px}.ym-media-thumb__order{display:grid;grid-template-columns:28px;gap:4px;align-content:center}.ym-media-thumb__order button{display:grid;place-items:center;width:28px;height:28px;padding:0;border:1px solid rgba(139,92,246,.25);border-radius:8px;background:rgba(139,92,246,.08);color:inherit}.ym-media-thumb__handle{text-align:center;color:#8b5cf6;cursor:grab}.ym-media-thumb button:focus-visible{outline:3px solid rgba(34,211,238,.34);outline-offset:2px}.ym-media-thumb button:disabled{opacity:.38;cursor:not-allowed}:global(.ym-media-manager.is-light) .ym-media-thumb{background:rgba(255,255,255,.72);border-color:rgba(100,116,139,.2)}@media(max-width:980px){.ym-media-gallery{grid-template-columns:repeat(3,minmax(0,1fr))}.ym-media-thumb{grid-template-columns:1fr}.ym-media-thumb__select{grid-template-columns:1fr}.ym-media-thumb__order{grid-template-columns:1fr 32px 32px}.ym-media-thumb__handle{align-self:center}.ym-media-thumb__order button{width:32px;height:32px}.ym-media-thumb__details strong{white-space:normal;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2}}@media(max-width:700px){.ym-media-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:390px){.ym-media-gallery{grid-template-columns:1fr}.ym-media-thumb__select{grid-template-columns:96px minmax(0,1fr)}}@media(prefers-reduced-motion:reduce){.ym-media-thumb{transition:none}.ym-media-thumb:hover{transform:none}}
.ym-media-thumb__details strong[dir="auto"]{unicode-bidi:plaintext;text-align:start}
</style>
