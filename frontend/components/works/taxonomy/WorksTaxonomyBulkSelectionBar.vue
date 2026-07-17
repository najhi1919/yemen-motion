<template>
  <aside
    v-if="selectedCount > 0"
    class="ym-bulk-selection"
    :dir="locale === 'ar' ? 'rtl' : 'ltr'"
    aria-live="polite"
  >
    <div class="ym-bulk-selection__summary">
      <strong>{{ text.selected(selectedCount) }}</strong>
      <span>{{ text.current(currentPageSelectedCount) }}</span>
      <span>{{ text.limit(maxSelection) }}</span>
      <small v-if="selectedCount > currentPageSelectedCount">{{ text.otherPages }}</small>
    </div>
    <div class="ym-bulk-selection__actions">
      <button
        v-if="canAssignCategory || canAssignTags"
        type="button"
        class="is-primary"
        @click="$emit('open')"
      >
        {{ text.manage }}
      </button>
      <button type="button" class="is-secondary" @click="$emit('clear')">
        {{ text.clear }}
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  selectedCount: number
  currentPageSelectedCount: number
  maxSelection?: number
  locale: 'ar' | 'en'
  canAssignCategory: boolean
  canAssignTags: boolean
}>(), {
  maxSelection: 100
})

defineEmits<{
  open: []
  clear: []
}>()

const copies = {
  ar: {
    selected: (count: number) => `${count} عمل محدد`,
    current: (count: number) => `${count} من الصفحة الحالية`,
    limit: (count: number) => `الحد الأقصى ${count}`,
    otherPages: 'يتضمن التحديد أعمالًا من صفحات أخرى.',
    manage: 'إدارة جماعية',
    clear: 'مسح التحديد'
  },
  en: {
    selected: (count: number) => `${count} works selected`,
    current: (count: number) => `${count} from this page`,
    limit: (count: number) => `Maximum ${count}`,
    otherPages: 'The selection includes works from other pages.',
    manage: 'Bulk manage',
    clear: 'Clear selection'
  }
}

const text = computed(() => copies[props.locale])
</script>

<style scoped>
.ym-bulk-selection {
  position: sticky;
  bottom: 16px;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin: 18px;
  padding: 16px 18px;
  border: 1px solid color-mix(in srgb, var(--ym-primary, #8b5cf6) 48%, var(--ym-card-border));
  border-radius: 20px;
  color: var(--ym-text);
  background: var(--ym-dropdown-bg, #0f172a);
  box-shadow: 0 18px 45px rgba(15, 23, 42, 0.28);
  backdrop-filter: blur(16px);
}
.ym-bulk-selection__summary,
.ym-bulk-selection__actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 9px 14px;
}
.ym-bulk-selection__summary strong { font-size: 1rem; }
.ym-bulk-selection__summary span,
.ym-bulk-selection__summary small { color: var(--ym-muted); }
.ym-bulk-selection__summary small {
  width: 100%;
  color: #f59e0b;
}
.ym-bulk-selection button {
  min-height: 42px;
  padding: 0 16px;
  border: 1px solid var(--ym-card-border);
  border-radius: 13px;
  font-weight: 800;
  cursor: pointer;
}
.ym-bulk-selection button:focus-visible {
  outline: 3px solid color-mix(in srgb, #8b5cf6 45%, transparent);
  outline-offset: 2px;
}
.ym-bulk-selection .is-primary {
  border-color: transparent;
  color: #fff;
  background: linear-gradient(135deg, #7c3aed, #2563eb);
}
.ym-bulk-selection .is-secondary {
  color: var(--ym-text);
  background: var(--ym-input-bg, rgba(255, 255, 255, 0.06));
}
@media (max-width: 720px) {
  .ym-bulk-selection {
    position: relative;
    bottom: auto;
    align-items: stretch;
    flex-direction: column;
    margin: 14px;
  }
  .ym-bulk-selection__actions button { flex: 1; }
}
</style>
