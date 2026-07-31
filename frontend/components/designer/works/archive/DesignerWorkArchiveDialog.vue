<script setup lang="ts">
import type { DesignerWork, DesignerWorkLifecycleAction } from '~/types/designer-work'

const props = defineProps<{
  open: boolean
  work: DesignerWork | null
  action: DesignerWorkLifecycleAction
  busy: boolean
  error: string | null
}>()

const emit = defineEmits<{
  confirm: []
  close: []
}>()

const dialog = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)
let returnFocus: HTMLElement | null = null
const titleId = 'designer-work-archive-dialog-title'

const title = computed(() => props.action === 'archive' ? 'أرشفة العمل؟' : 'استعادة العمل؟')
const nextStatus = computed(() => props.action === 'archive'
  ? 'مؤرشف'
  : statusLabels[props.work?.archive_state.restore_target_status ?? 'draft'] ?? 'مسودة')
const description = computed(() => {
  if (props.action === 'archive') {
    return 'سيُنقل العمل إلى الأرشيف دون حذف ملفاته أوبياناته، ويمكنك استعادته لاحقًا.'
  }

  if (props.work?.archive_state.restore_target_status === 'published') {
    return 'سيعود العمل إلى حالة النشر وظهوره السابق.'
  }

  if (props.work?.archive_state.restore_target_status === 'hidden') {
    return 'سيعود العمل إلى قائمة الأعمال المخفية.'
  }

  return 'سيعود العمل إلى المسودات لتتمكن من تعديله.'
})

const statusLabels: Record<string, string> = {
  draft: 'مسودة',
  changes_requested: 'يحتاج تعديلًا',
  published: 'منشور',
  rejected: 'مرفوض',
  hidden: 'مخفي',
  archived: 'مؤرشف',
}

const close = () => {
  if (!props.busy) emit('close')
}

const focusableElements = (): HTMLElement[] => {
  if (!dialog.value) return []

  return Array.from(dialog.value.querySelectorAll<HTMLElement>(
    'button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
  ))
}

const onKeydown = (event: KeyboardEvent) => {
  if (!props.open) return

  if (event.key === 'Escape' && !props.busy) {
    event.preventDefault()
    close()
    return
  }

  if (event.key !== 'Tab') return
  const elements = focusableElements()
  if (elements.length === 0) {
    event.preventDefault()
    dialog.value?.focus()
    return
  }

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

watch(() => props.open, async open => {
  if (open) {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
    await nextTick()
    cancelButton.value?.focus()
    document.addEventListener('keydown', onKeydown)
  } else {
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    returnFocus?.focus()
    returnFocus = null
  }
})

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open && work"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-black/45 p-4"
      @click.self="close"
    >
      <section
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        class="w-full max-w-lg rounded-[22px] border border-[var(--ym-d-border)] bg-white p-5 shadow-2xl sm:p-6"
      >
        <div class="flex items-start gap-3">
          <span
            class="grid h-11 w-11 shrink-0 place-items-center rounded-xl"
            :class="action === 'archive' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'"
            aria-hidden="true"
          >
            <svg v-if="action === 'archive'" viewBox="0 0 20 20" fill="none" class="h-5 w-5">
              <path d="M4 6.5h12v9H4v-9Zm-1-3h14v3H3v-3Zm5 6h4" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
            </svg>
            <svg v-else viewBox="0 0 20 20" fill="none" class="h-5 w-5">
              <path d="M5 6V3L2.5 5.5 5 8V6a6 6 0 1 1-1 7" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </span>
          <div class="min-w-0">
            <h2 :id="titleId" class="text-xl font-black text-[var(--ym-d-text)]">
              {{ title }}
            </h2>
            <p class="mt-2 text-sm leading-7 text-[var(--ym-d-muted)]">
              {{ description }}
            </p>
          </div>
        </div>

        <p
          v-if="action === 'archive' && work.status === 'published'"
          class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-bold leading-6 text-amber-950"
        >
          هذا العمل منشور وسيختفي من العرض العام فور أرشفته.
        </p>
        <p
          v-if="action === 'archive' && work.status === 'hidden'"
          class="mt-4 rounded-xl border border-neutral-300 bg-neutral-100 p-3 text-sm font-bold leading-6 text-neutral-800"
        >
          سيُزال من قائمة الأعمال المغلقة وينتقل إلى الأرشيف.
        </p>

        <dl class="mt-5 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-3 rounded-xl bg-[var(--ym-d-surface-muted)] p-4 text-sm">
          <dt class="font-bold text-[var(--ym-d-muted)]">العمل</dt>
          <dd class="min-w-0 break-words font-extrabold text-[var(--ym-d-text)]" dir="auto">{{ work.title }}</dd>
          <dt class="font-bold text-[var(--ym-d-muted)]">الرمز</dt>
          <dd class="font-bold text-[var(--ym-d-text)]"><bdi dir="ltr">{{ work.public_code }}</bdi></dd>
          <dt class="font-bold text-[var(--ym-d-muted)]">الحالة الحالية</dt>
          <dd class="font-bold text-[var(--ym-d-text)]">{{ statusLabels[work.status] ?? work.status }}</dd>
          <dt class="font-bold text-[var(--ym-d-muted)]">الحالة التالية</dt>
          <dd class="font-bold text-[var(--ym-d-text)]">{{ nextStatus }}</dd>
        </dl>

        <p v-if="error" role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-[#8F1111]">
          {{ error }}
        </p>

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
          <button
            ref="cancelButton"
            type="button"
            :disabled="busy"
            class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-5 text-sm font-bold text-[var(--ym-d-charcoal)] transition duration-200 hover:bg-neutral-50 disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
            @click="close"
          >
            إلغاء
          </button>
          <button
            type="button"
            :disabled="busy"
            class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-extrabold transition duration-200 disabled:cursor-wait disabled:opacity-60 focus-visible:outline-none focus-visible:ring-4 motion-reduce:transition-none"
            :class="action === 'archive'
              ? 'border border-amber-400 bg-amber-400 text-amber-950 hover:bg-amber-300 focus-visible:ring-amber-200'
              : 'border border-emerald-700 bg-emerald-700 text-white hover:bg-emerald-800 focus-visible:ring-emerald-200'"
            @click="emit('confirm')"
          >
            {{ busy ? 'جارٍ التنفيذ…' : action === 'archive' ? 'تأكيد الأرشفة' : 'تأكيد الاستعادة' }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
