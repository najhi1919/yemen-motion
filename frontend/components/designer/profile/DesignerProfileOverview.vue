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
</script>

<template>
  <div class="space-y-6">
    <DesignerProfileIdentityMedia
      :profile="profile"
      @avatar-source="setPreviewAvatarSource"
    />
    <div class="grid items-stretch gap-5 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-6">
      <section class="flex min-w-0 flex-col rounded-[20px] border border-[rgba(17,17,17,0.11)] bg-white p-6 shadow-[0_8px_24px_rgba(17,17,17,0.05)] sm:p-8">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span
              class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
              :class="availabilityClass"
            >
              {{ availabilityLabel }}
            </span>
            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-[#C91414]">
              مسودة غير منشورة
            </span>
          </div>

          <div class="mt-5 min-w-0">
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

        <div class="mt-6 border-t border-[rgba(17,17,17,0.09)] pt-5">
          <p class="text-[15px] font-semibold text-[#666666]">
            التخصص الرئيسي
          </p>
          <p class="mt-1 break-words font-bold text-[#151515]">
            {{ profile.primary_specialty || 'لم يحدد بعد' }}
          </p>
        </div>

        <div class="mt-6">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[15px] font-semibold text-[#666666]">
              اكتمال البيانات الأساسية
            </p>
            <span class="text-sm font-extrabold text-[#C91414]">
              {{ completion.percentage }}%
            </span>
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
              class="h-full rounded-full bg-[#E21D1D] transition-[width] duration-300 motion-reduce:transition-none"
              :style="{ width: `${completion.percentage}%` }"
            />
          </div>
          <p class="mt-2 text-[15px] text-[#666666]">
            {{ completion.completed }} من {{ completion.total }} عناصر مكتملة
          </p>
        </div>

        <button
          type="button"
          class="mt-7 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white transition hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none sm:w-fit"
          @click="$emit('edit')"
        >
          تعديل البيانات
        </button>
      </section>

      <aside class="flex min-h-full min-w-0 flex-col rounded-[20px] bg-[#111111] p-6 text-white shadow-[0_8px_24px_rgba(17,17,17,0.08)] sm:p-7">
        <p class="text-[15px] font-bold text-neutral-300">
          معاينة الملف
        </p>

        <div class="flex flex-1 flex-col items-center justify-center py-5 text-center sm:py-7">
          <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border-2 border-white/20 bg-white/10">
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
              class="h-9 w-9 brightness-0 invert opacity-55"
            >
          </div>

          <div class="mt-5 h-1 w-10 rounded-full bg-[#E21D1D]" aria-hidden="true" />
          <h3 class="mt-4 max-w-full break-words text-2xl font-extrabold leading-tight text-white">
            {{ profile.display_name }}
          </h3>
          <p class="mt-2 max-w-full break-words text-[15px] font-medium text-neutral-200">
            {{ profile.professional_title || 'لم يحدد المسمى المهني' }}
          </p>
          <p class="mt-3 max-w-full break-words text-[15px] font-semibold text-neutral-300">
            {{ profile.primary_specialty || 'لم يحدد التخصص الرئيسي' }}
          </p>
        </div>
      </aside>
    </div>
  </div>
</template>
