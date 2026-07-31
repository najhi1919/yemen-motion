<script setup lang="ts">
import type { DesignerWorkGroup, DesignerWorksSummary } from '~/types/designer-work'

const props = defineProps<{
  query: string
  group: DesignerWorkGroup
  summary: DesignerWorksSummary
  view: 'grid' | 'list'
}>()

const emit = defineEmits<{
  search: [value: string]
  group: [value: DesignerWorkGroup]
  view: [value: 'grid' | 'list']
}>()

const searchValue = ref(props.query)
let searchTimer: ReturnType<typeof setTimeout> | null = null
const groups: Array<{ value: DesignerWorkGroup, label: string, count: keyof DesignerWorksSummary }> = [
  { value: 'all', label: 'الكل', count: 'total' },
  { value: 'draft', label: 'المسودات', count: 'draft' },
  { value: 'review', label: 'المراجعة', count: 'review' },
  { value: 'changes', label: 'تحتاج تعديلًا', count: 'changes' },
  { value: 'published', label: 'المنشورة', count: 'published' },
  { value: 'closed', label: 'المغلقة', count: 'closed' },
  { value: 'archived', label: 'المؤرشفة', count: 'archived' },
]

watch(() => props.query, value => {
  searchValue.value = value
})

watch(searchValue, value => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    const normalized = value.trim()
    if (normalized.length !== 1) emit('search', normalized)
  }, 350)
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<template>
  <section class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 shadow-[var(--ym-d-shadow-sm)] sm:p-5">
    <div class="mb-4 flex items-center gap-3">
      <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--ym-d-charcoal)] text-white" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
          <path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
      </span>
      <div>
        <p class="text-sm font-extrabold text-[var(--ym-d-text)]">
          تصفية الاستوديو
        </p>
        <p class="mt-0.5 text-xs text-[var(--ym-d-muted)]">
          ابحث أو اختر حالة العمل
        </p>
      </div>
    </div>

    <div>
      <label for="designer-works-search" class="sr-only">
        البحث في أعمالي
      </label>
      <div class="relative">
        <span class="pointer-events-none absolute right-3 top-1/2 grid h-8 w-8 -translate-y-1/2 place-items-center text-[var(--ym-d-red-strong)]" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]">
            <circle cx="10.5" cy="10.5" r="5.75" stroke="currentColor" stroke-width="1.8" />
            <path d="m15 15 4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </span>
        <input
          id="designer-works-search"
          v-model="searchValue"
          type="search"
          autocomplete="off"
          placeholder="ابحث بالعنوان أوالرمز أوالتصنيف أوالوسوم"
          class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-page)] py-2 pl-12 pr-14 text-base text-[var(--ym-d-text)] outline-none transition duration-200 placeholder:text-[var(--ym-d-muted)] focus:border-[var(--ym-d-red)] focus:bg-white focus:ring-4 focus:ring-[var(--ym-d-focus)] motion-reduce:transition-none sm:text-sm"
        >
        <button
          v-if="searchValue"
          type="button"
          aria-label="مسح البحث"
          class="absolute left-1 top-1/2 inline-flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-lg text-xl text-[var(--ym-d-muted)] hover:text-[var(--ym-d-charcoal)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          @click="searchValue = ''"
        >
          ×
        </button>
      </div>
    </div>

    <div class="ym-designer-filter-scroll -mx-1 mt-4 overflow-x-auto px-1 [scrollbar-width:thin]" role="group" aria-label="تصفية الأعمال حسب الحالة">
      <div class="flex w-max min-w-full gap-2 pb-2">
        <button
          v-for="item in groups"
          :key="item.value"
          type="button"
          :aria-pressed="group === item.value"
          class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
          :class="group === item.value
            ? item.value === 'review'
              ? 'border-amber-300 bg-amber-100 text-amber-900 shadow-sm'
              : item.value === 'published'
                ? 'border-emerald-300 bg-emerald-100 text-emerald-900 shadow-sm'
                : item.value === 'draft' || item.value === 'closed' || item.value === 'archived'
                  ? 'border-neutral-300 bg-neutral-200 text-neutral-900 shadow-sm'
                  : 'border-[var(--ym-d-red)] bg-[var(--ym-d-red)] text-white shadow-sm'
            : 'border-[var(--ym-d-border)] bg-[var(--ym-d-page)] text-[var(--ym-d-charcoal)] hover:border-[var(--ym-d-border-strong)] hover:bg-white'"
          @click="emit('group', item.value)"
        >
          {{ item.label }}
          <span
            class="inline-flex min-h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs"
            :class="group === item.value
              ? item.value === 'review'
                ? 'bg-amber-900/10 text-amber-950'
                : item.value === 'published'
                  ? 'bg-emerald-900/10 text-emerald-950'
                  : item.value === 'draft' || item.value === 'closed' || item.value === 'archived'
                    ? 'bg-black/10 text-neutral-900'
                    : 'bg-white/20 text-white'
              : 'bg-white text-[var(--ym-d-muted)]'"
          >
            <bdi>{{ summary[item.count] }}</bdi>
          </span>
        </button>
      </div>
    </div>

    <div class="mt-2 flex items-center justify-between gap-3 border-t border-[var(--ym-d-border)] pt-4">
      <span class="text-sm font-bold text-[var(--ym-d-muted)]">
        طريقة العرض
      </span>
      <div class="ym-works-view-toggle inline-flex rounded-xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-muted)] p-1" role="group" aria-label="طريقة عرض الأعمال">
        <button
          type="button"
          class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
          :class="view === 'grid'
            ? 'border-[var(--ym-d-red-border)] bg-[var(--ym-d-red-soft)] text-[var(--ym-d-red-strong)]'
            : 'border-transparent bg-white text-[var(--ym-d-charcoal)]'"
          :aria-pressed="view === 'grid'"
          aria-label="عرض الأعمال في شبكة"
          @click="emit('view', 'grid')"
        >
          <svg viewBox="0 0 20 20" fill="none" class="h-[18px] w-[18px]" aria-hidden="true">
            <rect x="3" y="3" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6" />
            <rect x="12" y="3" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6" />
            <rect x="3" y="12" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6" />
            <rect x="12" y="12" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6" />
          </svg>
          شبكة
        </button>
        <button
          type="button"
          class="inline-flex min-h-11 items-center gap-2 rounded-lg border px-3 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
          :class="view === 'list'
            ? 'border-[var(--ym-d-red-border)] bg-[var(--ym-d-red-soft)] text-[var(--ym-d-red-strong)]'
            : 'border-transparent bg-white text-[var(--ym-d-charcoal)]'"
          :aria-pressed="view === 'list'"
          aria-label="عرض الأعمال في قائمة"
          @click="emit('view', 'list')"
        >
          <svg viewBox="0 0 20 20" fill="none" class="h-[18px] w-[18px]" aria-hidden="true">
            <path d="M7 5h10M7 10h10M7 15h10M3 5h.01M3 10h.01M3 15h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          قائمة
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ym-designer-filter-scroll {
  scrollbar-color: var(--ym-d-border-strong) transparent;
}
</style>
