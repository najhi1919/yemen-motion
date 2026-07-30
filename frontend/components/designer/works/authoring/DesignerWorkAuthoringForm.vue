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
    class="space-y-5"
    :aria-busy="saving"
    @submit.prevent="$emit('save')"
  >
    <section class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 shadow-[var(--ym-d-shadow-sm)] sm:p-6">
      <div class="mb-6 flex items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--ym-d-charcoal)] text-white" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
            <path d="M6 5h12M6 10h12M6 15h8M6 19h5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
        </span>
        <div>
          <p class="text-xs font-extrabold text-[var(--ym-d-red-strong)]">بيانات العرض</p>
          <h2 class="mt-0.5 text-xl font-extrabold text-[var(--ym-d-text)]">محتوى العمل</h2>
        </div>
      </div>

      <div class="space-y-5">
        <label class="block" for="designer-work-title">
          <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">عنوان العمل</span>
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
            :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.title) }"
            class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
          >
          <span id="designer-work-title-count" class="mt-1 block text-left text-xs text-[var(--ym-d-muted)]" dir="ltr">
            {{ draft.title.length }} / 160
          </span>
          <span v-if="errors.title" id="designer-work-title-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.title[0] }}</span>
        </label>

        <label class="block" for="designer-work-summary">
          <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">الملخص</span>
          <textarea
            id="designer-work-summary"
            v-model="draft.summary"
            rows="3"
            maxlength="1000"
            :disabled="!editable || saving"
            :aria-invalid="Boolean(errors.summary)"
            :aria-describedby="errors.summary ? 'designer-work-summary-error designer-work-summary-count' : 'designer-work-summary-count'"
            :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.summary) }"
            class="w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 py-3 text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
          />
          <span id="designer-work-summary-count" class="mt-1 block text-left text-xs text-[var(--ym-d-muted)]" dir="ltr">
            {{ draft.summary.length }} / 1000
          </span>
          <span v-if="errors.summary" id="designer-work-summary-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.summary[0] }}</span>
        </label>

        <label class="block" for="designer-work-description">
          <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">الوصف</span>
          <textarea
            id="designer-work-description"
            v-model="draft.description"
            rows="7"
            maxlength="30000"
            :disabled="!editable || saving"
            :aria-invalid="Boolean(errors.description)"
            :aria-describedby="errors.description ? 'designer-work-description-error' : undefined"
            :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.description) }"
            class="w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 py-3 text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
          />
          <span v-if="errors.description" id="designer-work-description-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.description[0] }}</span>
        </label>
      </div>
    </section>

    <section class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 shadow-[var(--ym-d-shadow-sm)] sm:p-6">
      <div class="mb-6 flex items-center gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[var(--ym-d-charcoal)] text-white" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
            <path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
            <circle cx="12" cy="12" r="3.5" stroke="currentColor" stroke-width="1.7" />
          </svg>
        </span>
        <div>
          <p class="text-xs font-extrabold text-[var(--ym-d-red-strong)]">تفاصيل التسليم</p>
          <h2 class="mt-0.5 text-xl font-extrabold text-[var(--ym-d-text)]">إعدادات التنفيذ</h2>
        </div>
      </div>

      <div class="space-y-5">
        <label class="block" for="designer-work-media-type">
          <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">نوع العمل</span>
          <select
            id="designer-work-media-type"
            v-model="draft.media_type"
            :disabled="!editable || saving"
            :aria-invalid="Boolean(errors.media_type)"
            :aria-describedby="errors.media_type ? 'designer-work-media-type-error' : undefined"
            :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.media_type) }"
            class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
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
          <span v-if="errors.media_type" id="designer-work-media-type-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.media_type[0] }}</span>
        </label>

        <div class="grid gap-5 sm:grid-cols-2">
          <label class="block" for="designer-work-price">
            <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">السعر</span>
            <input
              id="designer-work-price"
              v-model="draft.price_amount"
              type="text"
              inputmode="decimal"
              dir="ltr"
              :disabled="!editable || saving"
              :aria-invalid="Boolean(errors.price_amount)"
              :aria-describedby="errors.price_amount ? 'designer-work-price-error' : undefined"
              :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.price_amount) }"
              class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 text-left text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
            >
            <span v-if="errors.price_amount" id="designer-work-price-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.price_amount[0] }}</span>
          </label>

          <label class="block" for="designer-work-delivery">
            <span class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">مدة التسليم بالأيام</span>
            <input
              id="designer-work-delivery"
              v-model="draft.delivery_days"
              type="text"
              inputmode="numeric"
              dir="ltr"
              :disabled="!editable || saving"
              :aria-invalid="Boolean(errors.delivery_days)"
              :aria-describedby="errors.delivery_days ? 'designer-work-delivery-error' : undefined"
              :class="{ '!border-[var(--ym-d-red)]': Boolean(errors.delivery_days) }"
              class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)] px-4 text-left text-[var(--ym-d-text)] outline-none transition focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-[var(--ym-d-surface-muted)]"
            >
            <span v-if="errors.delivery_days" id="designer-work-delivery-error" class="mt-1 block text-sm text-[#B81414]" role="alert">{{ errors.delivery_days[0] }}</span>
          </label>
        </div>
      </div>
    </section>

    <footer class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 shadow-[var(--ym-d-shadow-sm)] sm:px-6">
      <p v-if="generalError" class="mb-3 text-sm font-semibold text-[#B81414]" role="alert">
        {{ generalError }}
      </p>
      <p v-if="success" class="mb-3 text-sm font-semibold text-emerald-700" aria-live="polite">
        {{ success }}
      </p>
      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-5 text-sm font-bold text-[var(--ym-d-charcoal)] hover:bg-[var(--ym-d-surface-muted)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="saving"
          @click="createMode ? $emit('cancel') : $emit('reset')"
        >
          {{ createMode ? 'إلغاء' : 'تراجع عن التغييرات' }}
        </button>
        <button
          type="submit"
          class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[var(--ym-d-red)] px-6 text-sm font-bold text-white transition hover:bg-[var(--ym-d-red-strong)] disabled:cursor-not-allowed disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="saving || !editable || (!createMode && !dirty)"
        >
          {{ saving ? 'جارٍ الحفظ…' : 'حفظ البيانات' }}
        </button>
      </div>
    </footer>
  </form>
</template>
