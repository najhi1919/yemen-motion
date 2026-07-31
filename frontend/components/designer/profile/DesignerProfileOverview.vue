<script setup lang="ts">
import type { BasicCompletion, DesignerProfile } from '~/types/designer-profile'

const props = defineProps<{
  profile: DesignerProfile
  username: string | null
  completion: BasicCompletion
}>()

defineEmits<{
  edit: []
}>()

const previewAvatarSource = ref<string | null>(null)
const previewAvatarFailed = ref(false)

const setPreviewAvatarSource = (source: string | null) => {
  previewAvatarSource.value = source
  previewAvatarFailed.value = false
}

const availabilityLabel = computed(() => {
  const labels = {
    available: 'متاح للعمل',
    partially_available: 'متاح جزئيًا',
    unavailable: 'غير متاح حاليًا'
  }

  return labels[props.profile.availability]
})

const availabilityClass = computed(() => {
  if (props.profile.availability === 'available') {
    return 'bg-emerald-50 text-emerald-800 ring-emerald-200'
  }

  if (props.profile.availability === 'partially_available') {
    return 'bg-amber-50 text-amber-800 ring-amber-200'
  }

  return 'bg-neutral-100 text-neutral-700 ring-neutral-200'
})

const publicationLabel = computed(() => ({
  draft: 'مسودة غير منشورة',
  published: 'منشور',
  hidden: 'مخفي',
})[props.profile.publication_status])

const publicationClass = computed(() => {
  if (props.profile.publication_status === 'published') {
    return 'bg-emerald-50 text-emerald-800 ring-emerald-200'
  }

  if (props.profile.publication_status === 'hidden') {
    return 'bg-amber-50 text-amber-800 ring-amber-200'
  }

  return 'bg-red-50 text-[var(--ym-d-red-strong)] ring-red-200'
})
</script>

<template>
  <div class="space-y-6">
    <DesignerProfileIdentityMedia
      :profile="profile"
      @avatar-source="setPreviewAvatarSource"
    />
    <div class="grid items-stretch gap-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6">
      <section class="ym-profile-data-card flex min-w-0 flex-col rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-6 shadow-[var(--ym-d-shadow-sm)] sm:p-7">
        <div class="min-w-0">
          <div class="mb-4 flex items-center gap-2">
            <span class="h-1 w-10 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" />
            <p class="text-sm font-extrabold text-[var(--ym-d-charcoal)]">
              بيانات الملف
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
              :class="availabilityClass"
            >
              {{ availabilityLabel }}
            </span>
            <span
              class="rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
              :class="publicationClass"
            >
              {{ publicationLabel }}
            </span>
          </div>

          <div class="mt-4 min-w-0">
            <h2 class="break-words text-2xl font-extrabold leading-tight text-[#151515] sm:text-3xl">
              {{ profile.display_name }}
            </h2>
            <p class="mt-2 break-words text-base font-semibold text-[#666666]">
              {{ profile.professional_title || 'لم يحدد المسمى المهني' }}
            </p>
            <p class="mt-2 text-right">
              <bdi class="inline-block max-w-full break-all text-[15px] text-[#666666]" dir="ltr">
                @{{ username }}
              </bdi>
            </p>
          </div>
        </div>

        <div class="mt-4 border-t border-[rgba(17,17,17,0.09)] pt-4">
          <p class="text-[15px] font-semibold text-[#666666]">
            التخصص الرئيسي
          </p>
          <p class="mt-1 break-words font-bold text-[#151515]">
            {{ profile.primary_specialty || 'لم يحدد بعد' }}
          </p>
        </div>

        <div v-if="completion.percentage < 100" class="mt-5 rounded-2xl bg-[var(--ym-d-surface-warm)] p-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[15px] font-semibold text-[#666666]">
              اكتمال البيانات الأساسية
            </p>
            <bdi dir="ltr" class="text-sm font-extrabold text-[var(--ym-d-red-strong)]">
              {{ completion.percentage }}%
            </bdi>
          </div>
          <div
            class="mt-3 h-1.5 overflow-hidden rounded-full bg-neutral-200"
            role="progressbar"
            aria-label="اكتمال البيانات الأساسية"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="completion.percentage"
          >
            <div
              class="h-full rounded-full bg-[var(--ym-d-red)] transition-[width] duration-300 motion-reduce:transition-none"
              :style="{ width: `${completion.percentage}%` }"
            />
          </div>
          <p class="mt-2 text-[15px] text-[#666666]">
            <bdi dir="ltr">{{ completion.completed }}</bdi> من <bdi dir="ltr">{{ completion.total }}</bdi> عناصر مكتملة
          </p>
        </div>
        <div v-else class="mt-5 flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center" role="status">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="m5 12 4 4L19 6" />
            </svg>
          </span>
          <div class="min-w-0 flex-1">
            <p class="font-extrabold text-emerald-950">البيانات الأساسية مكتملة</p>
            <p class="mt-0.5 text-sm leading-6 text-emerald-800">جميع بيانات هذا القسم محفوظة ومكتملة.</p>
          </div>
          <bdi dir="ltr" class="w-fit rounded-full border border-emerald-300 bg-white px-3 py-1 text-xs font-extrabold text-emerald-800">100%</bdi>
        </div>

        <div class="mt-5 border-t border-[var(--ym-d-border)] pt-5">
          <button
            type="button"
            class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--ym-d-red)] px-6 text-sm font-bold text-white transition hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none sm:w-fit"
            @click="$emit('edit')"
          >
            تعديل البيانات
          </button>
        </div>
      </section>

      <aside class="ym-profile-preview-card flex min-h-full min-w-0 flex-col overflow-hidden rounded-[20px] bg-[var(--ym-d-charcoal)] text-white shadow-[var(--ym-d-shadow-md)]">
        <div class="relative z-10 shrink-0 px-6 pb-2 pt-5">
          <p class="text-[15px] font-bold text-white/80">معاينة الملف</p>
        </div>

        <div class="relative z-10 flex flex-1 flex-col items-center justify-center px-6 py-6 text-center sm:py-7">
          <div class="flex h-[88px] w-[88px] items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white/10 shadow-[0_0_0_4px_var(--ym-d-red-border)]">
            <img
              v-if="previewAvatarSource && !previewAvatarFailed"
              :src="previewAvatarSource"
              :alt="`الصورة الشخصية لـ${profile.display_name}`"
              class="h-full w-full object-cover"
              @error="previewAvatarFailed = true"
            >
            <img
              v-else
              src="/logo.svg"
              alt=""
              class="h-9 w-9 opacity-60"
            >
          </div>

          <div class="mt-4 h-1 w-10 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" />
          <h3 class="mt-3 max-w-full break-words text-[1.65rem] font-extrabold leading-tight text-white">
            {{ profile.display_name }}
          </h3>
          <bdi class="mt-1 block max-w-full truncate text-sm font-medium text-white/55" dir="ltr">
            @{{ username }}
          </bdi>
          <p class="mt-2 max-w-full break-words text-[15px] font-medium text-neutral-200">
            {{ profile.professional_title || 'لم يحدد المسمى المهني' }}
          </p>
          <p class="mt-2 max-w-full break-words text-[15px] font-semibold text-white/70">
            {{ profile.primary_specialty || 'لم يحدد التخصص الرئيسي' }}
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.ym-profile-preview-card {
  position: relative;
  isolation: isolate;
}

.ym-profile-preview-card::before,
.ym-profile-preview-card::after {
  position: absolute;
  pointer-events: none;
  content: "";
}

.ym-profile-preview-card::before {
  inset: 0;
  background:
    linear-gradient(var(--ym-d-red), var(--ym-d-red)) top right / 40px 3px no-repeat,
    linear-gradient(118deg, transparent 65%, rgba(255, 255, 255, 0.045) 65% 65.8%, transparent 65.8%),
    linear-gradient(62deg, transparent 82%, rgba(226, 29, 29, 0.09) 82% 83%, transparent 83%);
}

.ym-profile-preview-card::after {
  left: -5rem;
  bottom: -6rem;
  width: 14rem;
  height: 14rem;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 999px;
  box-shadow: 0 0 60px rgba(226, 29, 29, 0.09);
}
</style>
