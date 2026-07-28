<script setup lang="ts">
import type { DesignerWork, DesignerWorksMeta } from '~/types/designer-work'
import DesignerWorkCard from './DesignerWorkCard.vue'

defineProps<{
  works: readonly DesignerWork[]
  meta: DesignerWorksMeta
  coverUrls: Readonly<Record<number, string>>
  loading: boolean
  updating: boolean
  error: boolean
  filtered: boolean
}>()

defineEmits<{
  retry: []
  reset: []
  page: [value: number]
}>()
</script>

<template>
  <section :aria-busy="loading || updating">
    <div v-if="loading" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="index in 6"
        :key="index"
        class="overflow-hidden rounded-[18px] border border-neutral-200 bg-white"
        aria-hidden="true"
      >
        <div class="aspect-[16/10] animate-pulse bg-neutral-200 motion-reduce:animate-none" />
        <div class="space-y-3 p-5">
          <div class="h-6 w-20 animate-pulse rounded-full bg-neutral-200 motion-reduce:animate-none" />
          <div class="h-6 w-3/4 animate-pulse rounded bg-neutral-200 motion-reduce:animate-none" />
          <div class="h-4 w-full animate-pulse rounded bg-neutral-100 motion-reduce:animate-none" />
        </div>
      </div>
    </div>

    <div v-else-if="error" role="alert" class="rounded-[18px] border border-red-200 bg-red-50 p-5 text-[#8F1111]">
      <h2 class="font-extrabold">
        تعذر تحميل الأعمال
      </h2>
      <button
        type="button"
        class="mt-3 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#E21D1D] px-5 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        @click="$emit('retry')"
      >
        إعادة المحاولة
      </button>
    </div>

    <div
      v-else-if="works.length === 0"
      class="rounded-[18px] border border-[rgba(17,17,17,0.1)] bg-white px-5 py-12 text-center"
    >
      <img src="/logo.svg" alt="" class="mx-auto h-14 w-14 opacity-10">
      <h2 class="mt-4 text-xl font-extrabold text-[#151515]">
        {{ filtered ? 'لا توجد نتائج مطابقة' : 'لا توجد أعمال بعد' }}
      </h2>
      <p v-if="!filtered" class="mt-2 text-[#666666]">
        ستظهر مسوداتك وأعمالك هنا.
      </p>
      <button
        v-else
        type="button"
        class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-[#E21D1D] px-5 text-sm font-bold text-[#B81414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        @click="$emit('reset')"
      >
        إعادة ضبط البحث والفلاتر
      </button>
    </div>

    <template v-else>
      <div
        class="grid gap-5 transition-opacity motion-reduce:transition-none sm:grid-cols-2 xl:grid-cols-3"
        :class="updating ? 'opacity-55' : 'opacity-100'"
      >
        <DesignerWorkCard
          v-for="work in works"
          :key="work.id"
          :work="work"
          :cover-url="work.cover_media ? coverUrls[work.cover_media.id] : undefined"
        />
      </div>

      <nav v-if="meta.last_page > 1" class="mt-6 flex items-center justify-center gap-3" aria-label="صفحات الأعمال">
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[rgba(17,17,17,0.14)] bg-white px-4 font-bold text-[#333333] disabled:cursor-not-allowed disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          :disabled="meta.current_page <= 1 || updating"
          @click="$emit('page', meta.current_page - 1)"
        >
          السابق
        </button>
        <span class="text-sm font-bold text-[#555555]">
          {{ meta.current_page }} من {{ meta.last_page }}
        </span>
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[rgba(17,17,17,0.14)] bg-white px-4 font-bold text-[#333333] disabled:cursor-not-allowed disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          :disabled="meta.current_page >= meta.last_page || updating"
          @click="$emit('page', meta.current_page + 1)"
        >
          التالي
        </button>
      </nav>
    </template>
  </section>
</template>
