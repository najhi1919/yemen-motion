<script setup lang="ts">
import type { DesignerWorkMediaPreview } from '~/types/designer-work-media'

const props = defineProps<{ preview: DesignerWorkMediaPreview | null }>()
const emit = defineEmits<{ close: [] }>()
const panel = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let previousOverflow = ''

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    emit('close')
    return
  }
  if (event.key !== 'Tab' || !panel.value) return
  const controls = [...panel.value.querySelectorAll<HTMLElement>('button,video[controls]')]
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

watch(() => props.preview, async value => {
  if (!import.meta.client) return
  if (value) {
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    closeButton.value?.focus()
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = previousOverflow
  }
})

onBeforeUnmount(() => {
  if (!import.meta.client) return
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = previousOverflow
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="preview"
      class="fixed inset-0 z-50 grid place-items-center bg-black/85 p-3 sm:p-6"
      role="dialog"
      aria-modal="true"
      aria-labelledby="designer-media-preview-title"
      @mousedown.self="$emit('close')"
    >
      <section ref="panel" class="flex max-h-[92dvh] w-full max-w-5xl flex-col overflow-hidden rounded-[18px] bg-[#111111] text-white">
        <header class="flex items-center justify-between gap-4 border-b border-white/15 p-3 sm:px-5">
          <h2 id="designer-media-preview-title" class="min-w-0 truncate font-bold" dir="auto">
            {{ preview.item.original_name }}
          </h2>
          <button
            ref="closeButton"
            type="button"
            aria-label="إغلاق المعاينة"
            class="grid min-h-11 min-w-11 place-items-center rounded-xl border border-white/25 text-xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-300"
            @click="$emit('close')"
          >
            ×
          </button>
        </header>
        <div class="grid min-h-[260px] flex-1 place-items-center overflow-hidden p-4">
          <p v-if="preview.loading" role="status">
            جارٍ تحميل المعاينة…
          </p>
          <img
            v-else-if="preview.item.kind === 'image' && preview.url"
            :src="preview.url"
            :alt="preview.item.original_name"
            class="max-h-[75dvh] max-w-full object-contain"
          >
          <video
            v-else-if="preview.item.kind === 'video' && preview.url"
            :src="preview.url"
            controls
            preload="metadata"
            class="max-h-[75dvh] max-w-full object-contain"
          />
          <p v-else class="text-neutral-300">
            تعذر عرض المعاينة.
          </p>
        </div>
      </section>
    </div>
  </Teleport>
</template>
