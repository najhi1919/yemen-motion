<script setup lang="ts">
import type { DeepReadonly } from 'vue'
import type {
  DesignerProfileFeaturedWork,
  DesignerProfileFeaturedWorksEnvelope,
} from '~/types/designer-profile-featured-works'

type FeaturedWorkView =
  DeepReadonly<DesignerProfileFeaturedWork>

type FeaturedWorksView =
  DeepReadonly<DesignerProfileFeaturedWorksEnvelope>

const props = defineProps<{
  open: boolean
  state: FeaturedWorksView | null
  saving: boolean
  error: string | null
  validationErrors:
    Readonly<Record<string, readonly string[]>>
  coverUrls: Readonly<Record<number, string>>
}>()

const emit = defineEmits<{
  close: []
  save: [workIds: number[]]
}>()

const drawer = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const search = ref('')
const selectedIds = ref<number[]>([])
const localError = ref<string | null>(null)
const liveMessage = ref('')
let returnFocus: HTMLElement | null = null
let previousBodyOverflow = ''

const focusableSelector = [
  'button:not([disabled])',
  'input:not([disabled])',
  '[href]',
  '[tabindex]:not([tabindex="-1"])',
].join(', ')

const serverSelectedIds = computed(
  () => props.state?.selected.map(work => work.id) ?? [],
)

const workMap = computed(
  () => new Map<number, FeaturedWorkView>(
    (props.state?.eligible ?? []).map(work => [work.id, work]),
  ),
)

const selectedWorks = computed<FeaturedWorkView[]>(
  () => selectedIds.value
    .map(id => workMap.value.get(id))
    .filter(
      (work): work is FeaturedWorkView =>
        work !== undefined,
    ),
)

const filteredAvailableWorks = computed(() => {
  const selected = new Set(selectedIds.value)
  const query = search.value.trim().toLocaleLowerCase('ar')

  return (props.state?.eligible ?? []).filter(work => {
    if (selected.has(work.id)) return false
    if (!query) return true

    return [
      work.title,
      work.public_code,
      work.category?.name_ar,
      work.category?.name_en,
    ]
      .filter(Boolean)
      .some(value =>
        String(value).toLocaleLowerCase('ar').includes(query),
      )
  })
})

const arraysEqual = (left: number[], right: number[]) =>
  left.length === right.length
  && left.every((value, index) => value === right[index])

const isDirty = computed(
  () => !arraysEqual(selectedIds.value, serverSelectedIds.value),
)

const maximumReached = computed(
  () => selectedIds.value.length >= (props.state?.limit ?? 6),
)

const validationMessage = computed(
  () => props.validationErrors.work_ids?.[0]
    || props.validationErrors['work_ids.0']?.[0]
    || null,
)

const coverSource = (
  work: FeaturedWorkView,
): string | null => {
  const mediaId = work.cover_media?.id

  return mediaId ? props.coverUrls[mediaId] || null : null
}

const resetFromState = () => {
  selectedIds.value = [...serverSelectedIds.value]
  search.value = ''
  localError.value = null
  liveMessage.value = ''
}

const selectWork = (work: FeaturedWorkView) => {
  if (maximumReached.value) {
    localError.value = `لا يمكن اختيار أكثر من ${props.state?.limit ?? 6} أعمال.`
    return
  }

  selectedIds.value.push(work.id)
  localError.value = null
  liveMessage.value = `أُضيف ${work.title} إلى الأعمال المميزة.`
}

const removeWork = (work: FeaturedWorkView) => {
  selectedIds.value = selectedIds.value.filter(id => id !== work.id)
  localError.value = null
  liveMessage.value = `أُزيل ${work.title} من الأعمال المميزة.`
}

const moveWork = (index: number, direction: -1 | 1) => {
  const target = index + direction

  if (target < 0 || target >= selectedIds.value.length) return

  const next = [...selectedIds.value]
  const currentId = next[index]
  const targetId = next[target]

  if (currentId === undefined || targetId === undefined) return

  next[index] = targetId
  next[target] = currentId
  selectedIds.value = next

  const work = workMap.value.get(currentId)

  liveMessage.value = work
    ? `نُقل ${work.title} ${direction === -1 ? 'للأعلى' : 'للأسفل'}.`
    : 'تغيّر ترتيب الأعمال المميزة.'
}

const requestClose = () => {
  if (props.saving) return

  if (
    isDirty.value
    && import.meta.client
    && !window.confirm('لديك تغييرات غير محفوظة. هل تريد إغلاق النافذة دون حفظها؟')
  ) {
    return
  }

  emit('close')
}

const submit = () => {
  if (!isDirty.value || props.saving) return
  emit('save', [...selectedIds.value])
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    requestClose()
    return
  }

  if (event.key !== 'Tab' || !drawer.value) return

  const elements = Array.from(
    drawer.value.querySelectorAll<HTMLElement>(focusableSelector),
  )

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
    if (!import.meta.client) return

    if (open) {
      returnFocus = document.activeElement as HTMLElement | null
      previousBodyOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      resetFromState()
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

watch(
  () => props.state?.expected_updated_at,
  () => {
    if (props.open) {
      resetFromState()
    }
  },
)

onBeforeUnmount(() => {
  if (!import.meta.client) return

  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = previousBodyOverflow
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200 motion-reduce:transition-none"
      leave-active-class="transition-opacity duration-150 motion-reduce:transition-none"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="ym-designer-portal fixed inset-0 z-50 bg-black/55"
        @mousedown.self="requestClose"
      >
        <section
          ref="drawer"
          role="dialog"
          aria-modal="true"
          aria-labelledby="featured-works-drawer-title"
          aria-describedby="featured-works-drawer-description"
          class="absolute inset-y-0 left-0 flex h-full w-full max-w-3xl flex-col overflow-hidden bg-white shadow-2xl"
        >
          <header class="flex shrink-0 items-start justify-between gap-4 border-b-2 border-[var(--ym-d-red)] bg-[var(--ym-d-charcoal)] px-5 py-5 text-white sm:px-7">
            <div class="min-w-0">
              <h2
                id="featured-works-drawer-title"
                class="text-xl font-black text-white sm:text-2xl"
              >
                إدارة الأعمال المميزة
              </h2>
              <p
                id="featured-works-drawer-description"
                class="mt-1.5 max-w-2xl text-sm leading-6 text-white/70"
              >
                اختر ما يصل إلى
                <bdi dir="ltr">{{ state?.limit ?? 6 }}</bdi>
                أعمال، ثم استخدم أزرار التحريك لتحديد ترتيب ظهورها.
              </p>
            </div>

            <button
              ref="closeButton"
              type="button"
              class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/25 text-2xl text-white transition hover:border-[var(--ym-d-red)] hover:bg-[var(--ym-d-red)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
              aria-label="إغلاق إدارة الأعمال المميزة"
              :disabled="saving"
              @click="requestClose"
            >
              ×
            </button>
          </header>

          <div class="flex-1 overflow-y-auto px-5 py-6 sm:px-7">
            <div
              class="flex flex-col gap-3 rounded-2xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-muted)] p-4 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <p class="font-black text-[var(--ym-d-text)]">
                  المختار حاليًا
                </p>
                <p class="mt-1 text-sm text-[var(--ym-d-muted)]">
                  ترتيب القائمة أدناه هو ترتيب العرض العام.
                </p>
              </div>
              <bdi
                dir="ltr"
                class="w-fit rounded-full bg-white px-3 py-1.5 text-sm font-black text-[var(--ym-d-red-strong)] ring-1 ring-inset ring-[var(--ym-d-red-border)]"
                role="status"
              >
                {{ selectedIds.length }}/{{ state?.limit ?? 6 }}
              </bdi>
            </div>

            <p
              class="sr-only"
              aria-live="polite"
              aria-atomic="true"
            >
              {{ liveMessage }}
            </p>

            <p
              v-if="error"
              class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold leading-6 text-[#8F1111]"
              role="alert"
            >
              {{ error }}
            </p>

            <p
              v-if="localError || validationMessage"
              class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-bold leading-6 text-amber-900"
              role="alert"
            >
              {{ localError || validationMessage }}
            </p>

            <section
              class="mt-6"
              aria-labelledby="selected-featured-works-title"
            >
              <div class="flex flex-wrap items-center justify-between gap-3">
                <h3
                  id="selected-featured-works-title"
                  class="text-lg font-black text-[var(--ym-d-text)]"
                >
                  الترتيب المختار
                </h3>
                <span class="text-xs font-bold text-[var(--ym-d-muted)]">
                  التحريك متاح بلوحة المفاتيح
                </span>
              </div>

              <ol
                v-if="selectedWorks.length"
                class="mt-4 space-y-3"
                aria-label="الأعمال المميزة المرتبة"
              >
                <li
                  v-for="(work, index) in selectedWorks"
                  :key="work.id"
                  class="grid min-w-0 gap-3 rounded-2xl border border-[var(--ym-d-border)] bg-white p-3 sm:grid-cols-[96px_minmax(0,1fr)_auto] sm:items-center"
                >
                  <div class="relative aspect-[16/10] overflow-hidden rounded-xl bg-[var(--ym-d-surface-muted)]">
                    <img
                      v-if="coverSource(work)"
                      :src="coverSource(work) || undefined"
                      :alt="`غلاف ${work.title}`"
                      class="h-full w-full object-cover"
                      :style="{
                        objectPosition:
                          `${work.cover_presentation.focal_point.x}% ${work.cover_presentation.focal_point.y}%`,
                      }"
                    >
                    <span
                      v-else
                      class="flex h-full items-center justify-center text-xs font-black text-neutral-400"
                      aria-hidden="true"
                    >
                      YM
                    </span>
                    <bdi
                      dir="ltr"
                      class="absolute right-2 top-2 rounded-full bg-black/75 px-2 py-0.5 text-xs font-black text-white"
                    >
                      {{ index + 1 }}
                    </bdi>
                  </div>

                  <div class="min-w-0">
                    <h4
                      dir="auto"
                      class="break-words font-extrabold text-[var(--ym-d-text)]"
                    >
                      {{ work.title }}
                    </h4>
                    <p class="mt-1 text-xs font-bold text-[var(--ym-d-muted)]">
                      {{ work.category?.name_ar || 'دون تصنيف' }}
                      ·
                      <bdi dir="ltr">{{ work.public_code }}</bdi>
                    </p>
                  </div>

                  <div class="grid grid-cols-3 gap-2 sm:grid-cols-1">
                    <button
                      type="button"
                      class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-3 text-sm font-bold text-[var(--ym-d-charcoal)] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
                      :aria-label="`تحريك ${work.title} للأعلى`"
                      :disabled="saving || index === 0"
                      @click="moveWork(index, -1)"
                    >
                      للأعلى
                    </button>
                    <button
                      type="button"
                      class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-3 text-sm font-bold text-[var(--ym-d-charcoal)] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
                      :aria-label="`تحريك ${work.title} للأسفل`"
                      :disabled="saving || index === selectedWorks.length - 1"
                      @click="moveWork(index, 1)"
                    >
                      للأسفل
                    </button>
                    <button
                      type="button"
                      class="min-h-11 rounded-xl border border-red-200 bg-white px-3 text-sm font-bold text-[#B81414] disabled:opacity-40 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
                      :aria-label="`إزالة ${work.title} من الأعمال المميزة`"
                      :disabled="saving"
                      @click="removeWork(work)"
                    >
                      إزالة
                    </button>
                  </div>
                </li>
              </ol>

              <div
                v-else
                class="mt-4 rounded-2xl border border-dashed border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-muted)] p-6 text-center"
              >
                <p class="font-black text-[var(--ym-d-text)]">
                  القائمة فارغة
                </p>
                <p class="mt-2 text-sm text-[var(--ym-d-muted)]">
                  اختر أعمالًا من القائمة التالية.
                </p>
              </div>
            </section>

            <section
              class="mt-8 border-t border-[var(--ym-d-border)] pt-6"
              aria-labelledby="eligible-featured-works-title"
            >
              <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h3
                    id="eligible-featured-works-title"
                    class="text-lg font-black text-[var(--ym-d-text)]"
                  >
                    الأعمال المؤهلة
                  </h3>
                  <p class="mt-1 text-sm text-[var(--ym-d-muted)]">
                    تظهر هنا أعمالك المنشورة والعامة فقط.
                  </p>
                </div>

                <label class="block w-full sm:max-w-xs">
                  <span class="mb-1.5 block text-sm font-bold text-[var(--ym-d-text)]">
                    بحث
                  </span>
                  <input
                    v-model="search"
                    type="search"
                    class="min-h-11 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-4 text-sm text-[var(--ym-d-text)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
                    placeholder="العنوان أوالرمز أوالتصنيف"
                  >
                </label>
              </div>

              <div
                v-if="filteredAvailableWorks.length"
                class="mt-4 grid gap-3 sm:grid-cols-2"
              >
                <article
                  v-for="work in filteredAvailableWorks"
                  :key="work.id"
                  class="flex min-w-0 flex-col overflow-hidden rounded-2xl border border-[var(--ym-d-border)] bg-white"
                >
                  <div class="aspect-[16/8] overflow-hidden bg-[var(--ym-d-surface-muted)]">
                    <img
                      v-if="coverSource(work)"
                      :src="coverSource(work) || undefined"
                      :alt="`غلاف ${work.title}`"
                      class="h-full w-full object-cover"
                      :style="{
                        objectPosition:
                          `${work.cover_presentation.focal_point.x}% ${work.cover_presentation.focal_point.y}%`,
                      }"
                    >
                    <span
                      v-else
                      class="flex h-full items-center justify-center text-sm font-black text-neutral-400"
                      aria-hidden="true"
                    >
                      YM
                    </span>
                  </div>

                  <div class="flex flex-1 flex-col p-4">
                    <h4
                      dir="auto"
                      class="break-words font-extrabold text-[var(--ym-d-text)]"
                    >
                      {{ work.title }}
                    </h4>
                    <p class="mt-1 text-xs font-bold text-[var(--ym-d-muted)]">
                      {{ work.category?.name_ar || 'دون تصنيف' }}
                      ·
                      <bdi dir="ltr">{{ work.public_code }}</bdi>
                    </p>

                    <button
                      type="button"
                      class="mt-4 min-h-11 w-full rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red-soft)] px-4 text-sm font-bold text-[var(--ym-d-red-strong)] transition hover:bg-[var(--ym-d-red)] hover:text-white disabled:cursor-not-allowed disabled:border-neutral-200 disabled:bg-neutral-100 disabled:text-neutral-400 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
                      :aria-label="`إضافة ${work.title} إلى الأعمال المميزة`"
                      :disabled="saving || maximumReached"
                      @click="selectWork(work)"
                    >
                      {{ maximumReached ? 'اكتمل الحد الأعلى' : 'إضافة إلى المميز' }}
                    </button>
                  </div>
                </article>
              </div>

              <div
                v-else
                class="mt-4 rounded-2xl border border-dashed border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-muted)] p-6 text-center"
              >
                <p class="font-black text-[var(--ym-d-text)]">
                  لا توجد نتائج متاحة
                </p>
                <p class="mt-2 text-sm leading-6 text-[var(--ym-d-muted)]">
                  قد تكون جميع الأعمال المؤهلة مختارة، أوأن البحث لا يطابق أي عمل.
                </p>
              </div>
            </section>
          </div>

          <footer class="shrink-0 border-t border-[var(--ym-d-border)] bg-white px-5 py-4 sm:px-7">
            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p
                class="text-sm font-bold"
                :class="isDirty
                  ? 'text-amber-800'
                  : 'text-[var(--ym-d-muted)]'"
              >
                {{ isDirty
                  ? 'لديك تغييرات غير محفوظة.'
                  : 'القائمة مطابقة لأحدث نسخة محفوظة.' }}
              </p>

              <div class="flex flex-col-reverse gap-3 sm:flex-row">
                <button
                  type="button"
                  class="min-h-12 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-6 font-bold text-[var(--ym-d-charcoal)] hover:bg-[var(--ym-d-surface-muted)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
                  :disabled="saving"
                  @click="requestClose"
                >
                  إلغاء
                </button>
                <button
                  type="button"
                  class="min-h-12 rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white transition hover:bg-[var(--ym-d-red-strong)] disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
                  :disabled="saving || !isDirty"
                  @click="submit"
                >
                  {{ saving ? 'جارٍ الحفظ…' : 'حفظ الترتيب' }}
                </button>
              </div>
            </div>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>
