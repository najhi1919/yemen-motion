<script setup lang="ts">
import type { DesignerWork } from '~/types/designer-work'
import { formatYmDate } from '~/utils/ymFormatting'

const props = defineProps<{ work: DesignerWork, coverUrl?: string }>()
const coverFailed = ref(false)

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
}

const mediaLabels: Record<string, string> = {
  image: 'صورة',
  video: 'فيديو',
  gallery: 'معرض صور',
}
</script>

<template>
  <article class="min-w-0 overflow-hidden rounded-[18px] border border-[rgba(17,17,17,0.11)] bg-white shadow-[0_6px_20px_rgba(17,17,17,0.045)]">
    <div class="relative aspect-[16/10] overflow-hidden bg-[#F5F5F5]">
      <img
        v-if="coverUrl && !coverFailed && work.cover_media?.processing_status === 'ready'"
        :src="coverUrl"
        :alt="work.title"
        class="h-full w-full object-cover object-center"
        @error="coverFailed = true"
      >
      <div v-else class="flex h-full w-full items-center justify-center">
        <img src="/logo.svg" alt="" class="h-16 w-16 opacity-[0.09]">
      </div>
      <span
        v-if="work.cover_media && work.cover_media.processing_status !== 'ready'"
        class="absolute bottom-3 right-3 rounded-full bg-[#111111]/80 px-3 py-1 text-xs font-bold text-white"
      >
        قيد المعالجة
      </span>
    </div>

    <div class="p-5">
      <span
        class="inline-flex rounded-full px-3 py-1 text-xs font-bold"
        :class="statusClasses[work.status] || 'bg-neutral-100 text-neutral-700'"
      >
        {{ statusLabels[work.status] || work.status }}
      </span>
      <h2 class="mt-3 break-words text-xl font-extrabold leading-snug text-[#151515]" dir="auto">
        {{ work.title }}
      </h2>
      <p v-if="work.summary" class="mt-2 line-clamp-2 break-words text-sm leading-6 text-[#666666]">
        {{ work.summary }}
      </p>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[rgba(17,17,17,0.08)] pt-4 text-sm text-[#666666]">
        <span>{{ mediaLabels[work.media_type] || work.media_type }}</span>
        <time :datetime="work.updated_at">
          آخر تحديث {{ formatYmDate(work.updated_at, 'ar') }}
        </time>
      </div>
      <NuxtLink
        v-if="work.status === 'draft' || work.status === 'changes_requested'"
        :to="`/designer/works/${work.id}/edit`"
        class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-[#E21D1D] px-4 text-sm font-bold text-[#B81414] transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
      >
        تعديل العمل
      </NuxtLink>
    </div>
  </article>
</template>
