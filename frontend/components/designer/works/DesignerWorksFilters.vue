<script setup lang="ts">
import type { DesignerWorkGroup, DesignerWorksSummary } from '~/types/designer-work'

const props = defineProps<{
  query: string
  group: DesignerWorkGroup
  summary: DesignerWorksSummary
}>()

const emit = defineEmits<{
  search: [value: string]
  group: [value: DesignerWorkGroup]
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
  <section class="space-y-4 rounded-[18px] border border-[rgba(17,17,17,0.1)] bg-white p-4 shadow-[0_5px_18px_rgba(17,17,17,0.035)] sm:p-5">
    <div>
      <label for="designer-works-search" class="mb-2 block text-sm font-bold text-[#333333]">
        البحث في أعمالي
      </label>
      <div class="relative">
        <input
          id="designer-works-search"
          v-model="searchValue"
          type="search"
          autocomplete="off"
          placeholder="ابحث بالعنوان أوالملخص"
          class="min-h-12 w-full rounded-xl border border-[rgba(17,17,17,0.16)] bg-white px-4 pl-12 text-[#151515] outline-none transition placeholder:text-[#888888] focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 motion-reduce:transition-none"
        >
        <button
          v-if="searchValue"
          type="button"
          aria-label="مسح البحث"
          class="absolute left-1.5 top-1/2 inline-flex min-h-11 min-w-11 -translate-y-1/2 items-center justify-center rounded-lg text-xl text-[#666666] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          @click="searchValue = ''"
        >
          ×
        </button>
      </div>
    </div>

    <div class="-mx-1 overflow-x-auto px-1 [scrollbar-width:thin]" role="group" aria-label="تصفية الأعمال حسب الحالة">
      <div class="flex w-max min-w-full gap-2 pb-1">
        <button
          v-for="item in groups"
          :key="item.value"
          type="button"
          :aria-pressed="group === item.value"
          class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
          :class="group === item.value
            ? 'border-[#E21D1D] bg-[#E21D1D] text-white'
            : 'border-[rgba(17,17,17,0.12)] bg-white text-[#444444] hover:border-[#E21D1D]'"
          @click="emit('group', item.value)"
        >
          {{ item.label }}
          <span
            class="rounded-full px-2 py-0.5 text-xs"
            :class="group === item.value ? 'bg-white/20 text-white' : 'bg-[#F2F2F2] text-[#555555]'"
          >
            {{ summary[item.count] }}
          </span>
        </button>
      </div>
    </div>
  </section>
</template>
