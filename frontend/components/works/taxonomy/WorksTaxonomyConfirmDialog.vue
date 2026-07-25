<template>
  <AdminDialogShell
    :open="open && entity !== null"
    :busy="loading"
    :title-id="titleId"
    :description-id="descriptionId"
    :locale="locale"
    size="compact"
    @close="requestClose"
  >
    <template #header>
      <div class="ym-confirm-header">
        <div>
          <span>{{ text.eyebrow }}</span>
          <h2 :id="titleId">{{ text.title }}</h2>
          <p :id="descriptionId">{{ text.description }}</p>
        </div>
        <button
          type="button"
          :aria-label="text.close"
          :disabled="loading"
          @click="requestClose"
        >
          <span aria-hidden="true">×</span>
        </button>
      </div>
    </template>

    <template v-if="entity">
      <div class="ym-confirm-identity">
        <strong>{{ locale === 'ar' ? entity.name_ar : entity.name_en }}</strong>
        <code dir="ltr">{{ entity.slug }}</code>
        <small>{{ text.works }}: {{ formatYmNumber(entity.works_count, locale) }}</small>
      </div>
      <p class="ym-confirm-warning">{{ text.warning }}</p>
      <p v-if="error" class="ym-confirm-error" role="alert">{{ error }}</p>
    </template>

    <template #footer>
      <button
        type="button"
        data-dialog-initial
        class="ym-confirm-button is-secondary"
        :disabled="loading"
        @click="requestClose"
      >
        {{ text.cancel }}
      </button>
      <button
        type="button"
        class="ym-confirm-button is-danger"
        :disabled="loading"
        @click="emit('confirm')"
      >
        {{ loading ? text.working : text.confirm }}
      </button>
    </template>
  </AdminDialogShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AdminDialogShell from '~/components/admin/visual/AdminDialogShell.vue'
import { formatYmNumber } from '~/utils/ymFormatting'

interface Entity {
  id: number
  name_ar: string
  name_en: string
  slug: string
  works_count: number
}

const props = defineProps<{
  open: boolean
  entity: Entity | null
  entityType: 'category' | 'tag'
  locale: 'ar' | 'en'
  loading: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  confirm: []
}>()

const titleId = `ym-disable-${props.entityType}-title`
const descriptionId = `ym-disable-${props.entityType}-description`
const text = computed(() => props.locale === 'ar'
  ? {
      eyebrow: 'تأكيد التعطيل',
      title: props.entityType === 'category' ? 'تعطيل التصنيف' : 'تعطيل الوسم',
      description: 'راجع السجل قبل تنفيذ الإجراء.',
      close: 'إغلاق حوار التعطيل',
      works: 'الأعمال المرتبطة',
      warning: props.entityType === 'category'
        ? 'ستبقى الأعمال مرتبطة بهذا التصنيف. لن يُحذف التصنيف أو أي عمل.'
        : 'ستبقى إسنادات الأعمال الحالية مرتبطة بهذا الوسم. لن يُحذف الوسم أو تُفصل الأعمال.',
      cancel: 'إبقاء السجل',
      confirm: 'تعطيل',
      working: 'جارٍ التعطيل…'
    }
  : {
      eyebrow: 'Disable confirmation',
      title: props.entityType === 'category' ? 'Disable category' : 'Disable tag',
      description: 'Review the record before applying this action.',
      close: 'Close disable dialog',
      works: 'Linked works',
      warning: props.entityType === 'category'
        ? 'Works remain linked to this category. Neither the category nor works are deleted.'
        : 'Existing work assignments remain linked to this tag. Neither the tag nor assignments are deleted.',
      cancel: 'Keep record',
      confirm: 'Disable',
      working: 'Disabling…'
    })

function requestClose(): void {
  if (!props.loading) emit('close')
}
</script>

<style scoped>
.ym-confirm-header {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.ym-confirm-header > div {
  min-width: 0;
}

.ym-confirm-header > div > span {
  color: var(--ym-dialog-warning);
  font-size: 12px;
  font-weight: 900;
}

.ym-confirm-header h2 {
  margin: 5px 0 2px;
  color: var(--ym-dialog-text);
  font-size: 24px;
  line-height: 1.25;
}

.ym-confirm-header p {
  margin: 0;
  color: var(--ym-dialog-muted);
  font-size: 13px;
}

.ym-confirm-header button {
  display: grid;
  width: 44px;
  height: 44px;
  flex: 0 0 44px;
  place-items: center;
  border: 1px solid var(--ym-dialog-border-soft);
  border-radius: 13px;
  color: var(--ym-dialog-text);
  background: var(--ym-dialog-control);
}

.ym-confirm-header button span {
  font-size: 24px;
  line-height: 1;
}

.ym-confirm-identity {
  display: grid;
  min-width: 0;
  gap: 6px;
  border: 1px solid var(--ym-dialog-border-soft);
  border-radius: 16px;
  padding: 15px;
  background: var(--ym-dialog-control);
}

.ym-confirm-identity code {
  overflow: hidden;
  color: var(--ym-dialog-accent-strong);
  text-align: start;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-confirm-identity small,
.ym-confirm-warning {
  color: var(--ym-dialog-muted);
  font-size: 13px;
  line-height: 1.65;
}

.ym-confirm-warning {
  margin: 16px 0 0;
}

.ym-confirm-error {
  margin: 12px 0 0;
  color: var(--ym-dialog-danger);
  font-size: 13px;
  font-weight: 800;
}

.ym-confirm-button {
  min-height: 44px;
  border-radius: 12px;
  padding: 0 17px;
  font: inherit;
  font-size: 14px;
  font-weight: 900;
}

.ym-confirm-button.is-secondary {
  border: 1px solid var(--ym-dialog-border-soft);
  color: var(--ym-dialog-text);
  background: var(--ym-dialog-control);
}

.ym-confirm-button.is-danger {
  border: 1px solid #e11d48;
  color: #fff;
  background: linear-gradient(135deg, #e11d48, #f43f5e);
}

.ym-confirm-button:focus-visible,
.ym-confirm-header button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(244, 63, 94, .24);
}

.ym-confirm-button:disabled,
.ym-confirm-header button:disabled {
  cursor: not-allowed;
  opacity: .52;
}
</style>
