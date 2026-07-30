<script setup lang="ts">
import type { DesignerWorkMedia } from '~/types/designer-work-media'

const props = defineProps<{
  open: boolean
  item: DesignerWorkMedia | null
  busy: boolean
  returnFocusTo: HTMLElement | null
}>()

const emit = defineEmits<{ cancel: []; confirm: [] }>()
const panel = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)
let previousOverflow = ''

const close = () => {
  if (!props.busy) emit('cancel')
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
    return
  }
  if (event.key !== 'Tab' || !panel.value) return
  const controls = [...panel.value.querySelectorAll<HTMLElement>('button:not([disabled])')]
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

const restore = () => {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = previousOverflow
}

watch(() => props.open, async open => {
  if (!import.meta.client) return
  if (open) {
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    cancelButton.value?.focus()
  } else {
    restore()
    nextTick(() => props.returnFocusTo?.focus())
  }
})

onBeforeUnmount(() => {
  if (import.meta.client && props.open) restore()
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 grid place-items-center bg-black/65 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="designer-media-delete-title"
      aria-describedby="designer-media-delete-description"
      @mousedown.self="close"
    >
      <section ref="panel" tabindex="-1" class="w-full max-w-md rounded-[18px] bg-white p-6 text-[#151515]">
        <h2 id="designer-media-delete-title" class="text-xl font-extrabold">
          حذف وسيط العمل؟
        </h2>
        <p id="designer-media-delete-description" class="mt-3 text-sm leading-7 text-[#555555]">
          سيُزال الوسيط من العمل ولن يظهر ضمن ملفاته.
          <span v-if="item?.is_cover" class="mt-1 block font-bold text-[#B81414]">
            سيُزال أيضًا تعيينه كغلاف للعمل.
          </span>
        </p>
        <p v-if="item" class="mt-4 truncate rounded-xl bg-neutral-100 p-3 text-sm font-bold" dir="auto">
          {{ item.original_name }}
        </p>
        <div class="mt-6 grid grid-cols-2 gap-3">
          <button
            ref="cancelButton"
            type="button"
            class="min-h-11 rounded-xl border border-[rgba(17,17,17,0.16)] font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
            :disabled="busy"
            @click="$emit('cancel')"
          >
            إلغاء
          </button>
          <button
            type="button"
            class="min-h-11 rounded-xl bg-[#E21D1D] font-bold text-white disabled:opacity-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
            :disabled="busy"
            @click="$emit('confirm')"
          >
            {{ busy ? 'جارٍ الحذف…' : 'حذف الوسيط' }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
