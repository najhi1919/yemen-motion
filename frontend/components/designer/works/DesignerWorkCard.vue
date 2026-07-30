<script setup lang="ts">
import type { DesignerWork } from '~/types/designer-work'
import { formatYmDate } from '~/utils/ymFormatting'

const props = withDefaults(defineProps<{
  work: DesignerWork
  coverUrl?: string
  variant?: 'grid' | 'list'
}>(), {
  variant: 'grid',
})
const coverFailed = ref(false)
const isList = computed(() => props.variant === 'list')

watch(() => props.coverUrl, () => {
  coverFailed.value = false
})

const statusLabels: Record<string, string> = {
  draft: 'مسودة',
  submitted: 'مرسل للمراجعة',
  in_review: 'قيد المراجعة',
  changes_requested: 'يحتاج تعديلًا',
  approved: 'معتمد',
  published: 'منشور',
  rejected: 'مرفوض',
  hidden: 'مخفي',
  archived: 'مؤرشف',
  closed: 'مغلق',
}

const statusClasses: Record<string, string> = {
  draft: 'bg-neutral-100 text-neutral-700',
  submitted: 'bg-amber-50 text-amber-800',
  in_review: 'bg-amber-50 text-amber-800',
  changes_requested: 'bg-red-50 text-[#B81414]',
  approved: 'bg-emerald-50 text-emerald-800',
  published: 'bg-emerald-50 text-emerald-800',
  rejected: 'bg-neutral-200 text-neutral-700',
  hidden: 'bg-neutral-200 text-neutral-700',
  archived: 'bg-neutral-200 text-neutral-700',
  closed: 'bg-neutral-800 text-white',
}

const mediaLabels: Record<string, string> = {
  image: 'صورة',
  video: 'فيديو',
  gallery: 'معرض صور',
}
</script>

<template>
  <article
    class="ym-work-management-card group grid min-w-0 overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] shadow-[0_6px_20px_rgba(17,17,17,0.045)] transition-[box-shadow,border-color] duration-200 hover:border-[var(--ym-d-border-strong)] hover:shadow-[var(--ym-d-shadow-sm)] focus-within:border-[var(--ym-d-border-strong)] focus-within:shadow-[var(--ym-d-shadow-sm)] motion-reduce:transition-none"
    :class="isList
      ? 'ym-work-list-row ym-work-mobile-list-row h-auto grid-cols-[112px_minmax(0,1fr)] grid-rows-[auto_auto] sm:min-h-[112px] sm:grid-cols-[112px_minmax(0,1fr)_172px] sm:grid-rows-1 sm:items-stretch'
      : 'h-full grid-rows-[auto_1fr_auto]'"
  >
    <div
      class="relative aspect-video min-w-0 overflow-hidden bg-[var(--ym-d-surface-muted)]"
      :class="isList ? 'self-center' : ''"
    >
      <img
        v-if="coverUrl && !coverFailed && work.cover_media?.processing_status === 'ready'"
        :src="coverUrl"
        :alt="work.title"
        loading="lazy"
        class="h-full w-full object-cover object-center transition-transform duration-200 group-hover:scale-[1.015] group-focus-within:scale-[1.015] motion-reduce:transform-none motion-reduce:transition-none"
        @error="coverFailed = true"
      >
      <div v-else class="ym-work-cover-fallback flex h-full w-full items-center justify-center">
        <span
          class="relative z-10 grid place-items-center rounded-2xl border border-[var(--ym-d-border)] bg-white/80 shadow-sm"
          :class="isList ? 'h-10 w-10 sm:rounded-xl' : 'h-20 w-20'"
        >
          <img src="/logo.svg" alt="" class="opacity-35" :class="isList ? 'h-7 w-7' : 'h-14 w-14'">
        </span>
      </div>

      <span
        class="absolute right-3 top-3 z-10 inline-flex items-center rounded-full border border-white/40 text-xs font-bold shadow-sm"
        :class="[
          statusClasses[work.status] || 'bg-neutral-100 text-neutral-700',
          isList ? 'min-h-6 px-2 text-[10px] sm:min-h-7 sm:px-2.5 sm:text-xs' : 'min-h-8 px-3',
        ]"
      >
        {{ statusLabels[work.status] || work.status }}
      </span>

      <span
        v-if="work.media_type === 'video'"
        class="pointer-events-none absolute left-1/2 top-1/2 z-10 grid -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border border-white/35 bg-[var(--ym-d-charcoal)]/85 text-white shadow-lg"
        :class="isList ? 'h-9 w-9' : 'h-12 w-12'"
        aria-hidden="true"
      >
        <svg viewBox="0 0 24 24" fill="none" class="mr-0.5" :class="isList ? 'h-4 w-4' : 'h-5 w-5'">
          <path d="m9 7 8 5-8 5V7Z" fill="currentColor" />
        </svg>
      </span>

      <span
        v-if="work.cover_media && work.cover_media.processing_status !== 'ready'"
        class="absolute bottom-3 right-3 z-10 rounded-full bg-[var(--ym-d-charcoal)]/90 px-3 py-1 text-xs font-bold text-white"
      >
        قيد المعالجة
      </span>
    </div>

    <div
      class="min-h-0 min-w-0"
      :class="isList ? 'ym-work-list-content flex flex-col justify-center p-3 sm:p-4' : 'flex flex-col p-4 sm:p-5'"
    >
      <div class="min-w-0" :class="isList ? '' : 'flex-1'">
        <span
          class="inline-flex max-w-full items-center rounded-full bg-[var(--ym-d-surface-muted)] font-bold text-[var(--ym-d-charcoal)]"
          :class="isList ? 'min-h-6 px-2 text-[11px] sm:min-h-7 sm:px-3 sm:text-xs' : 'min-h-7 px-3 text-xs'"
          dir="auto"
        >
          {{ mediaLabels[work.media_type] || work.media_type }}
        </span>
        <h2
          class="mt-3 line-clamp-2 min-w-0 break-words font-black text-[var(--ym-d-text)] [overflow-wrap:anywhere]"
          :class="isList
            ? 'min-h-0 text-base leading-6 sm:text-lg sm:leading-[1.625rem]'
            : 'min-h-[3.25rem] text-lg leading-[1.625rem]'"
          dir="auto"
          :title="work.title"
        >
          {{ work.title }}
        </h2>
        <p
          class="mt-2 min-w-0 break-words text-sm leading-6 text-[var(--ym-d-muted)] [overflow-wrap:anywhere]"
          :class="isList ? 'line-clamp-1 sm:min-h-6' : 'line-clamp-2 min-h-12'"
          dir="auto"
        >
          {{ work.summary || 'لا يوجد ملخص' }}
        </p>
      </div>
    </div>

    <footer
      class="ym-work-card-actions flex min-w-0 flex-col gap-3 border-t border-[var(--ym-d-border)]"
      :class="isList
        ? 'ym-work-list-actions col-span-2 flex-row items-center justify-between px-3 pb-3 pt-3 sm:col-span-1 sm:m-0 sm:flex-col sm:items-stretch sm:justify-center sm:border-r sm:border-t-0 sm:p-4'
        : 'mx-4 mb-4 pt-4 min-[440px]:flex-row min-[440px]:items-center min-[440px]:justify-between sm:mx-5 sm:mb-5'"
    >
      <time :datetime="work.updated_at" class="min-w-0 text-xs text-[var(--ym-d-muted)]">
        آخر تحديث {{ formatYmDate(work.updated_at, 'ar') }}
      </time>
      <NuxtLink
        v-if="work.status === 'draft' || work.status === 'changes_requested'"
        :to="`/designer/works/${work.id}/edit`"
        class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-[var(--ym-d-red-border)] bg-white px-4 text-sm font-extrabold text-[var(--ym-d-red-strong)] transition duration-200 hover:bg-[var(--ym-d-red-soft)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
      >
        تعديل العمل
        <svg viewBox="0 0 20 20" fill="none" class="h-4 w-4" aria-hidden="true">
          <path d="M13.8 3.7a1.8 1.8 0 0 1 2.5 0 1.8 1.8 0 0 1 0 2.5L7.2 15.3 3.5 16.5l1.2-3.7 9.1-9.1Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          <path d="m12.3 5.2 2.5 2.5M3.5 16.5h13" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </NuxtLink>
    </footer>
  </article>
</template>

<style scoped>
.ym-work-cover-fallback {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, var(--ym-d-surface-muted), #eeeeec);
}

.ym-work-cover-fallback::before,
.ym-work-cover-fallback::after {
  position: absolute;
  pointer-events: none;
  content: "";
}

.ym-work-cover-fallback::before {
  inset: -45% 45% 20% -20%;
  border: 1px solid var(--ym-d-border-strong);
  transform: rotate(35deg);
}

.ym-work-cover-fallback::after {
  right: -3rem;
  bottom: -4rem;
  width: 12rem;
  height: 12rem;
  border-radius: 999px;
  background: radial-gradient(circle, var(--ym-d-red-border), transparent 68%);
}
</style>
