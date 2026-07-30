<script setup lang="ts">
import type { DesignerWorkMedia } from '~/types/designer-work-media'

const props = defineProps<{
  item: DesignerWorkMedia
  index: number
  total: number
  imageUrl: string
  posterUrl: string
  editable: boolean
  ordering: boolean
  covering: boolean
  deleting: boolean
  retrying: boolean
}>()

defineEmits<{
  preview: [event: MouseEvent]
  videoCover: [event: MouseEvent]
  move: [direction: -1 | 1]
  cover: []
  remove: [event: MouseEvent]
  retry: []
}>()

const previewUrl = computed(() => props.item.kind === 'image' ? props.imageUrl : props.posterUrl)
const formatSize = (bytes: number) => bytes < 1024 * 1024
  ? `${Math.max(1, Math.round(bytes / 1024)).toLocaleString('ar-YE')} ك.ب`
  : `${(bytes / (1024 * 1024)).toLocaleString('ar-YE', { maximumFractionDigits: 1 })} م.ب`
const duration = computed(() => {
  if (props.item.duration_ms === null) return null
  const seconds = Math.round(props.item.duration_ms / 1000)
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`
})
</script>

<template>
  <article
    class="overflow-hidden rounded-2xl border bg-[var(--ym-d-surface)] shadow-[var(--ym-d-shadow-sm)] transition-[border-color,box-shadow] duration-200 motion-reduce:transition-none"
    :class="item.is_cover ? 'border-[var(--ym-d-red)] shadow-[0_10px_28px_rgba(226,29,29,0.10)]' : 'border-[var(--ym-d-border)]'"
  >
    <div class="relative aspect-[16/10] overflow-hidden bg-neutral-100">
      <img
        v-if="previewUrl"
        :src="previewUrl"
        :alt="item.kind === 'image' ? item.original_name : ''"
        class="h-full w-full object-cover"
      >
      <div v-else class="grid h-full place-items-center bg-neutral-100" aria-hidden="true">
        <img src="/logo.svg" alt="" class="h-14 w-14 opacity-15">
      </div>
      <span
        v-if="item.is_cover"
        class="absolute right-3 top-3 rounded-full bg-[#E21D1D] px-3 py-1 text-xs font-bold text-white"
      >
        غلاف العمل
      </span>
      <div
        v-if="item.processing_status === 'pending'"
        class="absolute inset-x-3 bottom-3 rounded-xl bg-black/75 p-3 text-white"
      >
        <div class="mb-2 flex items-center justify-between gap-3 text-xs font-bold">
          <span>جارٍ تجهيز الفيديو</span>
          <bdi>{{ item.processing_progress }}%</bdi>
        </div>
        <div
          role="progressbar"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-valuenow="item.processing_progress"
          class="h-1.5 overflow-hidden rounded-full bg-white/25"
        >
          <span class="block h-full rounded-full bg-[#E21D1D]" :style="{ width: `${item.processing_progress}%` }" />
        </div>
      </div>
    </div>

    <div class="space-y-4 p-4">
      <div class="min-w-0">
        <div class="flex items-center justify-between gap-3">
          <span class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-bold text-[#444444]">
            {{ item.kind === 'image' ? 'صورة' : 'فيديو' }}
          </span>
          <span
            class="text-xs font-bold"
            :class="item.processing_status === 'failed' ? 'text-[#B81414]' : 'text-[#666666]'"
          >
            {{ item.processing_status === 'ready' ? 'جاهز' : item.processing_status === 'failed' ? 'تعذر تجهيز الفيديو' : 'قيد المعالجة' }}
          </span>
        </div>
        <p class="mt-3 truncate font-bold text-[#151515]" dir="auto" :title="item.original_name">
          {{ item.original_name }}
        </p>
        <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-[#686868]">
          <span>{{ formatSize(item.size_bytes) }}</span>
          <bdi v-if="item.width && item.height">{{ item.width }} × {{ item.height }}</bdi>
          <bdi v-if="duration">{{ duration }}</bdi>
        </p>
      </div>

      <div class="grid grid-cols-2 gap-2">
        <button
          v-if="item.processing_status === 'ready'"
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-3 text-sm font-bold text-[var(--ym-d-charcoal)] hover:bg-[var(--ym-d-surface-muted)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          @click="$emit('preview', $event)"
        >
          معاينة
        </button>
        <button
          v-if="item.kind === 'image' && item.processing_status === 'ready' && !item.is_cover && editable"
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-3 text-sm font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="covering"
          @click="$emit('cover')"
        >
          تعيين كغلاف
        </button>
        <button
          v-if="item.kind === 'video' && item.processing_status === 'ready' && editable"
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-3 text-sm font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          @click="$emit('videoCover', $event)"
        >
          إدارة غلاف الفيديو
        </button>
        <button
          v-if="item.processing_status === 'failed' && item.can_retry_processing"
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-3 text-sm font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="!editable || retrying"
          @click="$emit('retry')"
        >
          {{ retrying ? 'جارٍ الإرسال…' : 'إعادة المحاولة' }}
        </button>
        <button
          type="button"
          aria-label="تحريك الوسيط للأعلى"
          class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-3 text-sm font-bold text-[var(--ym-d-charcoal)] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="!editable || ordering || total < 2 || index === 0"
          @click="$emit('move', -1)"
        >
          تحريك للأعلى
        </button>
        <button
          type="button"
          aria-label="تحريك الوسيط للأسفل"
          class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-3 text-sm font-bold text-[var(--ym-d-charcoal)] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="!editable || ordering || total < 2 || index === total - 1"
          @click="$emit('move', 1)"
        >
          تحريك للأسفل
        </button>
        <button
          type="button"
          class="col-span-2 min-h-11 rounded-xl border border-red-200 px-3 text-sm font-bold text-[#B81414] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          :disabled="!editable || deleting"
          @click="$emit('remove', $event)"
        >
          {{ deleting ? 'جارٍ الحذف…' : 'حذف الوسيط' }}
        </button>
      </div>
    </div>
  </article>
</template>
