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
  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
    <section class="rounded-3xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
      <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
          <div class="mb-3 flex flex-wrap items-center gap-2">
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

          <h2 class="text-2xl font-extrabold text-[#151515] sm:text-3xl">
            {{ profile.display_name }}
          </h2>
          <p class="mt-2 text-base font-semibold text-neutral-700">
            {{ profile.professional_title || 'لم يحدد المسمى المهني' }}
          </p>
          <p class="mt-1 text-[15px] text-neutral-600" dir="ltr">
            @{{ username }}
          </p>
        </div>

        <button
          type="button"
          class="min-h-11 shrink-0 rounded-xl bg-[#E21D1D] px-5 py-3 text-sm font-bold text-white transition hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          @click="$emit('edit')"
        >
          تعديل البيانات
        </button>
      </div>

      <div class="mt-8 grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-neutral-200 bg-[#FCFCFC] p-4">
          <p class="text-[15px] font-semibold text-neutral-600">
            التخصص الرئيسي
          </p>
          <p class="mt-2 font-bold text-neutral-900">
            {{ profile.primary_specialty || 'لم يحدد بعد' }}
          </p>
        </div>

        <div class="rounded-2xl border border-neutral-200 bg-[#FCFCFC] p-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[15px] font-semibold text-neutral-600">
              اكتمال البيانات الأساسية
            </p>
            <span class="text-sm font-extrabold text-[#C91414]">
              {{ completion.percentage }}%
            </span>
          </div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-neutral-200">
            <div
              class="h-full rounded-full bg-[#E21D1D] transition-[width] duration-300"
              :style="{ width: `${completion.percentage}%` }"
            />
          </div>
          <p class="mt-2 text-[15px] text-neutral-600">
            {{ completion.completed }} من {{ completion.total }} عناصر مكتملة
          </p>
        </div>
      </div>
    </section>

    <aside class="overflow-hidden rounded-3xl bg-[#111111] p-6 text-white shadow-sm sm:p-8">
      <p class="text-[15px] font-bold text-red-200">
        معاينة مختصرة
      </p>
      <h3 class="mt-5 text-2xl font-extrabold">
        {{ profile.display_name }}
      </h3>
      <p class="mt-2 text-[15px] font-medium text-neutral-200">
        {{ profile.professional_title || 'مصمم في Yemen Motion' }}
      </p>
      <p class="mt-5 text-[15px] leading-7 text-neutral-200">
        {{ profile.bio || 'أضف نبذة مهنية لتقديم خبرتك واتجاهك الإبداعي بوضوح.' }}
      </p>
      <div class="mt-6 border-t border-white/10 pt-5">
        <p class="text-[15px] text-neutral-300">
          هذه معاينة داخل مساحة العمل وليست ملفًا عامًا منشورًا.
        </p>
      </div>
    </aside>
  </div>
</template>
