<script setup lang="ts">
import type {
  DesignerWorkAuthoringDraft,
  DesignerWorkMediaType,
} from '~/types/designer-work-authoring'

defineProps<{
  draft: DesignerWorkAuthoringDraft
  allowedMediaTypes: readonly DesignerWorkMediaType[]
  errors: Readonly<Record<string, string[]>>
  editable: boolean
  saving: boolean
  dirty: boolean
  createMode: boolean
  success: string | null
  generalError: string | null
}>()

defineEmits<{
  save: []
  reset: []
  cancel: []
}>()

const mediaOptions = [
  { value: 'image', label: 'صورة' },
  { value: 'video', label: 'فيديو' },
  { value: 'gallery', label: 'معرض صور' },
]
</script>

<template>
  <form
    novalidate
    class="overflow-hidden rounded-[18px] border border-[rgba(17,17,17,0.1)] bg-white shadow-[0_6px_20px_rgba(17,17,17,0.04)]"
    :aria-busy="saving"
    @submit.prevent="$emit('save')"
  >
    <section class="space-y-5 p-4 sm:p-6">
      <div>
        <h2 class="text-xl font-extrabold text-[#151515]">
          البيانات الأساسية
        </h2>
      </div>

      <label class="block" for="designer-work-title">
        <span class="mb-2 block text-sm font-bold text-[#333333]">عنوان العمل</span>
        <input
          id="designer-work-title"
          v-model="draft.title"
          type="text"
          maxlength="160"
          required
          aria-required="true"
          :disabled="!editable || saving"
          :aria-invalid="Boolean(errors.title)"
          :aria-describedby="errors.title ? 'designer-work-title-error designer-work-title-count' : 'designer-work-title-count'"
          :class="{ '!border-[#E21D1D]': Boolean(errors.title) }"
          class="min-h-12 w-full rounded-xl border border-[rgba(17,17,17,0.16)] px-4 text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
        >
        <span id="designer-work-title-count" class="mt-1 block text-left text-xs text-[#777777]" dir="ltr">
          {{ draft.title.length }} / 160
        </span>
        <span v-if="errors.title" id="designer-work-title-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
          {{ errors.title[0] }}
        </span>
      </label>

      <label class="block" for="designer-work-summary">
        <span class="mb-2 block text-sm font-bold text-[#333333]">الملخص</span>
        <textarea
          id="designer-work-summary"
          v-model="draft.summary"
          rows="3"
          maxlength="1000"
          :disabled="!editable || saving"
          :aria-invalid="Boolean(errors.summary)"
          :aria-describedby="errors.summary ? 'designer-work-summary-error designer-work-summary-count' : 'designer-work-summary-count'"
          :class="{ '!border-[#E21D1D]': Boolean(errors.summary) }"
          class="w-full rounded-xl border border-[rgba(17,17,17,0.16)] px-4 py-3 text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
        />
        <span id="designer-work-summary-count" class="mt-1 block text-left text-xs text-[#777777]" dir="ltr">
          {{ draft.summary.length }} / 1000
        </span>
        <span v-if="errors.summary" id="designer-work-summary-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
          {{ errors.summary[0] }}
        </span>
      </label>

      <label class="block" for="designer-work-description">
        <span class="mb-2 block text-sm font-bold text-[#333333]">الوصف</span>
        <textarea
          id="designer-work-description"
          v-model="draft.description"
          rows="7"
          maxlength="30000"
          :disabled="!editable || saving"
          :aria-invalid="Boolean(errors.description)"
          :aria-describedby="errors.description ? 'designer-work-description-error' : undefined"
          :class="{ '!border-[#E21D1D]': Boolean(errors.description) }"
          class="w-full rounded-xl border border-[rgba(17,17,17,0.16)] px-4 py-3 text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
        />
        <span v-if="errors.description" id="designer-work-description-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
          {{ errors.description[0] }}
        </span>
      </label>

      <label class="block" for="designer-work-media-type">
        <span class="mb-2 block text-sm font-bold text-[#333333]">نوع العمل</span>
        <select
          id="designer-work-media-type"
          v-model="draft.media_type"
          :disabled="!editable || saving"
          :aria-invalid="Boolean(errors.media_type)"
          :aria-describedby="errors.media_type ? 'designer-work-media-type-error' : undefined"
          :class="{ '!border-[#E21D1D]': Boolean(errors.media_type) }"
          class="min-h-12 w-full rounded-xl border border-[rgba(17,17,17,0.16)] bg-white px-4 text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
        >
          <option value="">غير محدد</option>
          <option
            v-for="option in mediaOptions.filter(item => allowedMediaTypes.includes(item.value as DesignerWorkMediaType))"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <span v-if="errors.media_type" id="designer-work-media-type-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
          {{ errors.media_type[0] }}
        </span>
      </label>

      <div class="grid gap-5 sm:grid-cols-2">
        <label class="block" for="designer-work-price">
          <span class="mb-2 block text-sm font-bold text-[#333333]">السعر</span>
          <input
            id="designer-work-price"
            v-model="draft.price_amount"
            type="text"
            inputmode="decimal"
            dir="ltr"
            :disabled="!editable || saving"
            :aria-invalid="Boolean(errors.price_amount)"
            :aria-describedby="errors.price_amount ? 'designer-work-price-error' : undefined"
            :class="{ '!border-[#E21D1D]': Boolean(errors.price_amount) }"
            class="min-h-12 w-full rounded-xl border border-[rgba(17,17,17,0.16)] px-4 text-left text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
          >
          <span v-if="errors.price_amount" id="designer-work-price-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
            {{ errors.price_amount[0] }}
          </span>
        </label>

        <label class="block" for="designer-work-delivery">
          <span class="mb-2 block text-sm font-bold text-[#333333]">مدة التسليم بالأيام</span>
          <input
            id="designer-work-delivery"
            v-model="draft.delivery_days"
            type="text"
            inputmode="numeric"
            dir="ltr"
            :disabled="!editable || saving"
            :aria-invalid="Boolean(errors.delivery_days)"
            :aria-describedby="errors.delivery_days ? 'designer-work-delivery-error' : undefined"
            :class="{ '!border-[#E21D1D]': Boolean(errors.delivery_days) }"
            class="min-h-12 w-full rounded-xl border border-[rgba(17,17,17,0.16)] px-4 text-left text-[#151515] outline-none focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100 disabled:bg-neutral-100"
          >
          <span v-if="errors.delivery_days" id="designer-work-delivery-error" class="mt-1 block text-sm text-[#B81414]" role="alert">
            {{ errors.delivery_days[0] }}
          </span>
        </label>
      </div>
    </section>

    <footer class="border-t border-[rgba(17,17,17,0.09)] bg-[#FCFCFC] p-4 sm:px-6">
      <p v-if="generalError" class="mb-3 text-sm font-semibold text-[#B81414]" role="alert">
        {{ generalError }}
      </p>
      <p v-if="success" class="mb-3 text-sm font-semibold text-emerald-700" aria-live="polite">
        {{ success }}
      </p>
      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[rgba(17,17,17,0.14)] bg-white px-5 text-sm font-bold text-[#333333] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          :disabled="saving"
          @click="createMode ? $emit('cancel') : $emit('reset')"
        >
          {{ createMode ? 'إلغاء' : 'تراجع عن التغييرات' }}
        </button>
        <button
          type="submit"
          class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          :disabled="saving || !editable || (!createMode && !dirty)"
        >
          {{ saving ? 'جارٍ الحفظ…' : 'حفظ البيانات' }}
        </button>
      </div>
    </footer>
  </form>
</template>
