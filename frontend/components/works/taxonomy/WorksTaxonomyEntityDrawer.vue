<template>
  <AdminDialogShell
    :open="open"
    :busy="loading"
    :title-id="titleId"
    :description-id="descriptionId"
    :locale="locale"
    size="form"
    @close="requestClose"
  >
    <template #header>
      <div class="ym-entity-header">
        <div>
          <span>{{ mode === 'create' ? text.createEyebrow : text.editEyebrow }}</span>
          <h2 :id="titleId">{{ mode === 'create' ? text.createTitle : text.editTitle }}</h2>
          <p :id="descriptionId">{{ mode === 'create' ? text.createDescription : text.editDescription }}</p>
        </div>
        <button
          type="button"
          class="ym-entity-close"
          :aria-label="text.close"
          :disabled="loading"
          @click="requestClose"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </template>

    <form :id="formId" class="ym-entity-form" @submit.prevent="submit">
      <label for="ym-entity-name-ar">
        <span>{{ text.nameAr }} <b>{{ text.required }}</b></span>
        <input
          id="ym-entity-name-ar"
          v-model.trim="form.name_ar"
          data-dialog-initial
          type="text"
          minlength="2"
          maxlength="120"
          required
          :aria-invalid="fieldErrors.name_ar ? 'true' : undefined"
          :aria-describedby="fieldErrors.name_ar ? 'ym-entity-name-ar-error' : undefined"
        />
        <small v-if="fieldErrors.name_ar" id="ym-entity-name-ar-error" role="alert">
          {{ fieldErrors.name_ar[0] }}
        </small>
      </label>

      <label for="ym-entity-name-en">
        <span>{{ text.nameEn }} <b>{{ text.required }}</b></span>
        <input
          id="ym-entity-name-en"
          v-model.trim="form.name_en"
          type="text"
          minlength="2"
          maxlength="120"
          dir="ltr"
          required
          :aria-invalid="fieldErrors.name_en ? 'true' : undefined"
          :aria-describedby="fieldErrors.name_en ? 'ym-entity-name-en-error' : undefined"
        />
        <small v-if="fieldErrors.name_en" id="ym-entity-name-en-error" role="alert">
          {{ fieldErrors.name_en[0] }}
        </small>
      </label>

      <label v-if="mode === 'create'" for="ym-entity-slug">
        <span>{{ text.slug }} <b>{{ text.required }}</b></span>
        <input
          id="ym-entity-slug"
          v-model.trim="form.slug"
          type="text"
          minlength="2"
          maxlength="160"
          pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
          dir="ltr"
          required
          autocomplete="off"
          :aria-invalid="fieldErrors.slug ? 'true' : undefined"
          :aria-describedby="fieldErrors.slug ? 'ym-entity-slug-hint ym-entity-slug-error' : 'ym-entity-slug-hint'"
        />
        <em id="ym-entity-slug-hint">{{ text.slugHint }}</em>
        <small v-if="fieldErrors.slug" id="ym-entity-slug-error" role="alert">
          {{ fieldErrors.slug[0] }}
        </small>
      </label>

      <div v-else class="ym-entity-readonly">
        <span>{{ text.slug }}</span>
        <code dir="ltr">{{ entity?.slug }}</code>
      </div>

      <label for="ym-entity-sort-order">
        <span>{{ text.sortOrder }} <b>{{ text.required }}</b></span>
        <input
          id="ym-entity-sort-order"
          v-model.number="form.sort_order"
          type="number"
          min="0"
          max="2147483647"
          step="1"
          inputmode="numeric"
          dir="ltr"
          required
          :aria-invalid="fieldErrors.sort_order ? 'true' : undefined"
          :aria-describedby="fieldErrors.sort_order ? 'ym-entity-sort-error' : undefined"
        />
        <small v-if="fieldErrors.sort_order" id="ym-entity-sort-error" role="alert">
          {{ fieldErrors.sort_order[0] }}
        </small>
      </label>

      <dl v-if="mode === 'edit' && entity" class="ym-entity-context">
        <div>
          <dt>{{ text.state }}</dt>
          <dd>{{ entity.is_active ? text.active : text.disabled }}</dd>
        </div>
        <div>
          <dt>{{ text.worksCount }}</dt>
          <dd>{{ formatYmNumber(entity.works_count, locale) }}</dd>
        </div>
      </dl>

      <p v-if="error" class="ym-entity-error" role="alert">{{ error }}</p>
    </form>

    <template #footer>
      <p class="ym-entity-save-state" role="status" aria-live="polite">
        {{ loading ? text.saving : text.requiredHint }}
      </p>
      <button
        type="button"
        class="ym-entity-button is-secondary"
        :disabled="loading"
        @click="requestClose"
      >
        {{ text.cancel }}
      </button>
      <button
        type="submit"
        :form="formId"
        class="ym-entity-button is-primary"
        :disabled="loading"
      >
        <span v-if="loading" class="ym-mini-spinner" aria-hidden="true" />
        {{ loading ? text.saving : text.save }}
      </button>
    </template>
  </AdminDialogShell>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import AdminDialogShell from '~/components/admin/visual/AdminDialogShell.vue'
import { formatYmNumber } from '~/utils/ymFormatting'

interface CatalogEntity {
  id: number
  name_ar: string
  name_en: string
  slug: string
  is_active: boolean
  sort_order: number
  works_count: number
}

const props = defineProps<{
  open: boolean
  mode: 'create' | 'edit'
  entity: CatalogEntity | null
  entityType: 'category' | 'tag'
  locale: 'ar' | 'en'
  loading: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()

const emit = defineEmits<{
  close: []
  submit: [payload: { name_ar: string; name_en: string; slug?: string; sort_order: number }]
}>()

const titleId = `ym-taxonomy-${props.entityType}-dialog-title`
const descriptionId = `ym-taxonomy-${props.entityType}-dialog-description`
const formId = `ym-taxonomy-${props.entityType}-form`
const form = reactive({ name_ar: '', name_en: '', slug: '', sort_order: 0 })

const copies = {
  ar: {
    createEyebrow: 'سجل جديد',
    editEyebrow: 'تعديل',
    createTitle: props.entityType === 'category' ? 'إنشاء تصنيف' : 'إنشاء وسم',
    editTitle: props.entityType === 'category' ? 'تعديل التصنيف' : 'تعديل الوسم',
    createDescription: props.entityType === 'category'
      ? 'أدخل بيانات التصنيف الجديد، ثم احفظه ليظهر في الكتالوج.'
      : 'أدخل بيانات الوسم الجديد، ثم احفظه ليظهر في الكتالوج.',
    editDescription: props.entityType === 'category'
      ? 'حدّث بيانات التصنيف الحالية دون تغيير معرّفه الثابت.'
      : 'حدّث بيانات الوسم الحالية دون تغيير معرّفه الثابت.',
    close: 'إغلاق الحوار',
    nameAr: 'الاسم العربي',
    nameEn: 'الاسم الإنجليزي',
    slug: 'Slug',
    slugHint: 'ثابت بعد الإنشاء ولا يُولّد تلقائيًا.',
    sortOrder: 'ترتيب العرض',
    state: 'الحالة الحالية',
    worksCount: 'عدد الأعمال',
    active: 'فعال',
    disabled: 'معطل',
    required: 'مطلوب',
    requiredHint: 'جميع الحقول المعروضة مطلوبة.',
    cancel: 'إلغاء',
    save: 'حفظ',
    saving: 'جارٍ الحفظ…'
  },
  en: {
    createEyebrow: 'New record',
    editEyebrow: 'Edit',
    createTitle: props.entityType === 'category' ? 'Create category' : 'Create tag',
    editTitle: props.entityType === 'category' ? 'Edit category' : 'Edit tag',
    createDescription: props.entityType === 'category'
      ? 'Enter the new category details, then save it to the catalog.'
      : 'Enter the new tag details, then save it to the catalog.',
    editDescription: props.entityType === 'category'
      ? 'Update the current category without changing its stable identifier.'
      : 'Update the current tag without changing its stable identifier.',
    close: 'Close dialog',
    nameAr: 'Arabic name',
    nameEn: 'English name',
    slug: 'Slug',
    slugHint: 'Immutable after creation and never generated automatically.',
    sortOrder: 'Sort order',
    state: 'Current state',
    worksCount: 'Works count',
    active: 'Active',
    disabled: 'Disabled',
    required: 'Required',
    requiredHint: 'All displayed fields are required.',
    cancel: 'Cancel',
    save: 'Save',
    saving: 'Saving…'
  }
}

const text = computed(() => copies[props.locale])

watch(() => props.open, (open) => {
  if (!open) return

  form.name_ar = props.entity?.name_ar ?? ''
  form.name_en = props.entity?.name_en ?? ''
  form.slug = ''
  form.sort_order = props.entity?.sort_order ?? 0
})

function requestClose(): void {
  if (!props.loading) emit('close')
}

function submit(): void {
  if (props.loading) return

  const payload: { name_ar: string; name_en: string; slug?: string; sort_order: number } = {
    name_ar: form.name_ar,
    name_en: form.name_en,
    sort_order: Number(form.sort_order) || 0
  }

  if (props.mode === 'create') payload.slug = form.slug
  emit('submit', payload)
}
</script>

<style scoped>
.ym-entity-header {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.ym-entity-header > div {
  min-width: 0;
}

.ym-entity-header > div > span {
  display: inline-flex;
  border: 1px solid rgba(139, 92, 246, .3);
  border-radius: 999px;
  padding: 4px 9px;
  color: var(--ym-dialog-accent);
  background: rgba(124, 58, 237, .1);
  font-size: 12px;
  font-weight: 900;
}

.ym-entity-header h2 {
  margin: 8px 0 3px;
  color: var(--ym-dialog-text);
  font-size: clamp(22px, 3vw, 28px);
  line-height: 1.25;
}

.ym-entity-header p {
  max-width: 470px;
  margin: 0;
  color: var(--ym-dialog-muted);
  font-size: 13px;
  line-height: 1.65;
}

.ym-entity-close {
  display: grid;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  place-items: center;
  border: 1px solid var(--ym-dialog-border-soft);
  border-radius: 13px;
  color: var(--ym-dialog-text);
  background: var(--ym-dialog-control);
  cursor: pointer;
}

.ym-entity-close span {
  font-size: 24px;
  line-height: 1;
}

.ym-entity-form {
  display: grid;
  gap: 16px;
}

.ym-entity-form label,
.ym-entity-readonly {
  display: grid;
  min-width: 0;
  gap: 7px;
}

.ym-entity-form label > span,
.ym-entity-readonly > span {
  color: var(--ym-dialog-text);
  font-size: 14px;
  font-weight: 850;
}

.ym-entity-form label > span b {
  margin-inline-start: 5px;
  color: var(--ym-dialog-accent-strong);
  font-size: 12px;
}

.ym-entity-form input,
.ym-entity-readonly code {
  width: 100%;
  min-width: 0;
  min-height: 46px;
  box-sizing: border-box;
  border: 1px solid var(--ym-dialog-border-soft);
  border-radius: 12px;
  outline: none;
  padding: 11px 13px;
  color: var(--ym-dialog-text);
  -webkit-text-fill-color: currentColor;
  background: var(--ym-dialog-control);
  font: inherit;
  font-size: 14px;
}

.ym-entity-form input:focus {
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, .2);
}

.ym-entity-form input[aria-invalid="true"] {
  border-color: rgba(244, 63, 94, .68);
}

.ym-entity-form em {
  color: var(--ym-dialog-muted);
  font-size: 12.5px;
  font-style: normal;
  line-height: 1.55;
}

.ym-entity-form small,
.ym-entity-error {
  margin: 0;
  color: var(--ym-dialog-danger);
  font-size: 12.5px;
  font-weight: 800;
  line-height: 1.55;
}

.ym-entity-context {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}

.ym-entity-context div {
  min-width: 0;
  border: 1px solid var(--ym-dialog-border-soft);
  border-radius: 14px;
  padding: 12px;
  background: color-mix(in srgb, var(--ym-dialog-control) 82%, transparent);
}

.ym-entity-context dt {
  color: var(--ym-dialog-muted);
  font-size: 12.5px;
}

.ym-entity-context dd {
  margin: 4px 0 0;
  color: var(--ym-dialog-text);
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.ym-entity-save-state {
  min-width: 0;
  flex: 1 1 auto;
  margin: 0;
  color: var(--ym-dialog-muted);
  font-size: 12.5px;
}

.ym-entity-button {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border-radius: 12px;
  padding: 0 17px;
  font: inherit;
  font-size: 14px;
  font-weight: 900;
  cursor: pointer;
}

.ym-entity-button.is-secondary {
  border: 1px solid var(--ym-dialog-border-soft);
  color: var(--ym-dialog-text);
  background: var(--ym-dialog-control);
}

.ym-entity-button.is-primary {
  border: 1px solid transparent;
  color: #fff;
  background: linear-gradient(135deg, #7c3aed, #ec4899);
  box-shadow: 0 12px 28px rgba(124, 58, 237, .24);
}

.ym-entity-button:focus-visible,
.ym-entity-close:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, .28);
}

.ym-entity-button:disabled,
.ym-entity-close:disabled {
  cursor: not-allowed;
  opacity: .52;
}

.ym-mini-spinner {
  display: inline-block;
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, .42);
  border-top-color: #fff;
  border-radius: 50%;
  animation: ym-entity-spin .7s linear infinite;
}

@keyframes ym-entity-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 520px) {
  .ym-entity-context {
    grid-template-columns: 1fr;
  }

  .ym-entity-save-state {
    flex-basis: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-mini-spinner {
    animation-duration: 1.4s;
  }
}
</style>
