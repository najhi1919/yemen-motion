<script setup lang="ts">
const props = defineProps<{
  open: boolean
  action: 'publish' | 'hide'
  republish: boolean
  saving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  confirm: []
}>()

const dialog = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
let returnFocus: HTMLElement | null = null
let previousBodyOverflow = ''

const title = computed(() => {
  if (props.action === 'hide') return 'إخفاء الملف'
  return props.republish ? 'إعادة نشر الملف' : 'نشر الملف'
})

const description = computed(() => {
  if (props.action === 'hide') {
    return 'سيتوقف ظهور ملفك، لكن بياناتك وحسابك لن تُحذف ويمكنك إعادة نشره لاحقًا.'
  }
  if (props.republish) {
    return 'سيُعاد اعتماد هذه النسخة من ملفك للنشر. راجع المعاينة وإعدادات الظهور قبل المتابعة.'
  }
  return 'سيتم اعتماد هذه النسخة من ملفك للنشر. راجع المعاينة وإعدادات الظهور قبل المتابعة.'
})

const confirmLabel = computed(() => {
  if (props.saving) return props.action === 'hide' ? 'جارٍ الإخفاء…' : 'جارٍ النشر…'
  if (props.action === 'hide') return 'إخفاء الملف'
  return props.republish ? 'تأكيد إعادة النشر' : 'تأكيد النشر'
})

const secondaryLabel = computed(() => props.action === 'hide' ? 'إلغاء' : 'العودة')

const focusableSelector = 'button:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'

const requestClose = () => {
  if (!props.saving) emit('close')
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    requestClose()
    return
  }
  if (event.key !== 'Tab' || !dialog.value) return

  const elements = Array.from(dialog.value.querySelectorAll<HTMLElement>(focusableSelector))
  if (!elements.length) return
  const first = elements[0]
  const last = elements[elements.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}

watch(
  () => props.open,
  async open => {
    if (open) {
      returnFocus = document.activeElement as HTMLElement | null
      previousBodyOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      closeButton.value?.focus()
      return
    }

    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = previousBodyOverflow
    await nextTick()
    returnFocus?.focus()
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  if (import.meta.client) document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[80] flex items-center justify-center bg-black/55 p-4"
      @pointerdown.self="requestClose"
    >
      <section
        ref="dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="designer-publication-confirm-title"
        aria-describedby="designer-publication-confirm-description"
        class="isolate w-full max-w-lg overflow-hidden rounded-3xl bg-[#FCFCFC] shadow-2xl"
      >
        <header class="flex items-start justify-between gap-4 border-b-2 border-[#E21D1D] bg-[#111111] px-5 py-5 text-white sm:px-6">
          <h2 id="designer-publication-confirm-title" class="text-xl font-extrabold text-white">{{ title }}</h2>
          <button
            ref="closeButton"
            type="button"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/30 text-2xl text-white transition-colors hover:border-red-300 hover:bg-[#E21D1D] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none"
            aria-label="إغلاق نافذة التأكيد"
            :disabled="saving"
            @click="requestClose"
          >
            ×
          </button>
        </header>

        <div class="bg-[#FCFCFC] px-5 py-6 sm:px-6">
          <p id="designer-publication-confirm-description" class="leading-8 text-neutral-700">{{ description }}</p>
          <p v-if="error" role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-[#B42318]">
            {{ error }}
          </p>
        </div>

        <footer class="flex flex-col-reverse gap-3 border-t border-neutral-200 bg-[#FCFCFC] px-5 py-4 sm:flex-row sm:px-6">
          <button
            type="button"
            class="inline-flex min-h-12 items-center justify-center rounded-xl border border-neutral-300 bg-white px-5 text-sm font-bold text-neutral-900 transition-colors hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-neutral-300 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none"
            :disabled="saving"
            @click="requestClose"
          >
            {{ secondaryLabel }}
          </button>
          <button
            type="button"
            class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white transition-colors hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-600 motion-reduce:transition-none"
            :disabled="saving"
            @click="emit('confirm')"
          >
            {{ confirmLabel }}
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
