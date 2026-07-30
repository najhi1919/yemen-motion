<script setup lang="ts">
import type { DesignerWork, DesignerWorksMeta } from '~/types/designer-work'
import DesignerWorkCard from './DesignerWorkCard.vue'
import DesignerWorksList from './DesignerWorksList.vue'

defineProps<{
  works: readonly DesignerWork[]
  meta: DesignerWorksMeta
  coverUrls: Readonly<Record<number, string>>
  loading: boolean
  updating: boolean
  error: boolean
  filtered: boolean
  view: 'grid' | 'list'
}>()

defineEmits<{
  retry: []
  reset: []
  page: [value: number]
}>()
</script>

<template>
  <section :aria-busy="loading || updating">
    <div v-if="loading" class="grid grid-cols-1 items-stretch gap-4 md:grid-cols-2 md:gap-5 xl:grid-cols-3 xl:gap-6">
      <div
        v-for="index in 6"
        :key="index"
        class="h-full min-w-0 overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] shadow-[var(--ym-d-shadow-sm)]"
        aria-hidden="true"
      >
        <div class="aspect-video animate-pulse bg-neutral-200 motion-reduce:animate-none" />
        <div class="space-y-3 p-5">
          <div class="h-7 w-3/4 animate-pulse rounded-lg bg-neutral-200 motion-reduce:animate-none" />
          <div class="h-4 w-full animate-pulse rounded bg-neutral-100 motion-reduce:animate-none" />
          <div class="h-4 w-2/3 animate-pulse rounded bg-neutral-100 motion-reduce:animate-none" />
          <div class="h-11 w-full animate-pulse rounded-xl bg-neutral-200 motion-reduce:animate-none" />
        </div>
      </div>
    </div>

    <div v-else-if="error" role="alert" class="overflow-hidden rounded-[20px] border border-red-200 bg-[var(--ym-d-surface)] shadow-[var(--ym-d-shadow-sm)]">
      <div class="flex">
        <span class="w-2 shrink-0 bg-[var(--ym-d-red)]" aria-hidden="true" />
        <div class="flex-1 p-5 sm:flex sm:items-center sm:justify-between sm:gap-5">
          <div>
            <h2 class="font-extrabold text-[#8F1111]">
              تعذر تحميل الأعمال
            </h2>
            <p class="mt-1 text-sm text-[var(--ym-d-muted)]">
              تحقق من الاتصال ثم حاول مرة أخرى.
            </p>
          </div>
          <button
            type="button"
            class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--ym-d-red)] px-5 text-sm font-bold text-white transition duration-200 hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none sm:mt-0"
            @click="$emit('retry')"
          >
            إعادة المحاولة
          </button>
        </div>
      </div>
    </div>

    <div
      v-else-if="works.length === 0"
      class="ym-studio-empty relative overflow-hidden rounded-[22px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] px-5 py-12 text-center shadow-[var(--ym-d-shadow-sm)] sm:py-14"
    >
      <span class="absolute inset-y-0 right-0 w-2 bg-[var(--ym-d-charcoal)]" aria-hidden="true" />
      <span class="relative mx-auto grid h-20 w-20 place-items-center rounded-2xl border border-[var(--ym-d-border)] bg-white shadow-lg">
        <img src="/logo.svg" alt="" class="h-14 w-14 opacity-70">
      </span>
      <h2 class="relative mt-5 text-xl font-black text-[var(--ym-d-text)]">
        {{ filtered ? 'لا توجد أعمال مطابقة للبحث أو الفلاتر الحالية' : 'لم تضف أي أعمال بعد' }}
      </h2>
      <p v-if="!filtered" class="relative mt-2 text-[var(--ym-d-muted)]">
        أضف عملك الأول لتبدأ في بناء مجموعتك وإدارة وسائطها.
      </p>
      <NuxtLink
        v-if="!filtered"
        to="/designer/works/create"
        class="relative mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-[var(--ym-d-red)] px-5 text-sm font-extrabold text-white transition duration-200 hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
      >
        + إضافة أول عمل
        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
          <path d="M12.5 4.5 7 10l5.5 5.5M7.5 10H17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </NuxtLink>
      <button
        v-else
        type="button"
        class="relative mt-5 inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-charcoal)] bg-white px-5 text-sm font-bold text-[var(--ym-d-charcoal)] transition duration-200 hover:bg-[var(--ym-d-charcoal)] hover:text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
        @click="$emit('reset')"
      >
        إعادة ضبط البحث والفلاتر
      </button>
    </div>

    <template v-else>
      <DesignerWorksList
        v-if="view === 'list'"
        :works="works"
        :cover-urls="coverUrls"
        class="transition-opacity duration-200 motion-reduce:transition-none"
        :class="updating ? 'opacity-55' : 'opacity-100'"
      />
      <div
        v-else
        class="grid grid-cols-1 items-stretch gap-4 transition-opacity duration-200 motion-reduce:transition-none md:grid-cols-2 md:gap-5 xl:grid-cols-3 xl:gap-6"
        :class="updating ? 'opacity-55' : 'opacity-100'"
      >
        <DesignerWorkCard
          v-for="work in works"
          :key="work.id"
          :work="work"
          :cover-url="work.cover_media ? coverUrls[work.cover_media.id] : undefined"
          class="h-full min-w-0"
        />
      </div>

      <nav v-if="meta.last_page > 1" class="mt-7 flex flex-wrap items-center justify-center gap-3" aria-label="صفحات الأعمال">
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-red)] bg-white px-4 font-bold text-[var(--ym-d-red-strong)] transition duration-200 hover:bg-[var(--ym-d-red)] hover:text-white disabled:cursor-not-allowed disabled:border-[var(--ym-d-border)] disabled:bg-white disabled:text-[var(--ym-d-muted)] disabled:opacity-55 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
          :disabled="meta.current_page <= 1 || updating"
          @click="$emit('page', meta.current_page - 1)"
        >
          السابق
        </button>
        <span class="rounded-xl border border-[var(--ym-d-border)] bg-white px-4 py-2.5 text-sm font-bold text-[var(--ym-d-muted)]">
          <bdi>{{ meta.current_page }}</bdi> من <bdi>{{ meta.last_page }}</bdi>
        </span>
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-4 font-bold text-white transition duration-200 hover:bg-[var(--ym-d-red-strong)] disabled:cursor-not-allowed disabled:border-[var(--ym-d-border)] disabled:bg-white disabled:text-[var(--ym-d-muted)] disabled:opacity-55 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
          :disabled="meta.current_page >= meta.last_page || updating"
          @click="$emit('page', meta.current_page + 1)"
        >
          التالي
        </button>
      </nav>
    </template>
  </section>
</template>

<style scoped>
.ym-studio-empty::before,
.ym-studio-empty::after {
  position: absolute;
  pointer-events: none;
  content: "";
}

.ym-studio-empty::before {
  top: -6rem;
  left: -5rem;
  width: 14rem;
  height: 14rem;
  border: 1px solid var(--ym-d-border);
  transform: rotate(32deg);
}

.ym-studio-empty::after {
  right: 12%;
  bottom: -5rem;
  width: 11rem;
  height: 11rem;
  border-radius: 999px;
  opacity: 0.08;
  background: var(--ym-d-red);
}
</style>
