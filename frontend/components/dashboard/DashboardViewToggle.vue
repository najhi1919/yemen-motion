<template>
  <div class="ym-control-group">
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="ym-control-pill has-tooltip"
      :class="modelValue === item.key ? 'is-active' : ''"
      :data-tooltip="item.tooltip"
      :aria-label="item.tooltip"
      :title="item.tooltip"
      @click="$emit('update:modelValue', item.key)"
    >
      <span v-html="item.icon" />
      {{ item.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
type ViewMode = 'all' | 'cards' | 'charts'

const props = defineProps<{
  modelValue: ViewMode
  locale?: 'ar' | 'en'
}>()

defineEmits<{
  'update:modelValue': [value: ViewMode]
}>()

const icon = (d: string) => `<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">${d}</svg>`
const labels = {
  ar: { all: 'الكل', cards: 'البطاقات', charts: 'الرسوم' },
  en: { all: 'All', cards: 'Cards', charts: 'Charts' }
}

const tooltips = {
  ar: { all: 'عرض البطاقات والرسوم', cards: 'عرض البطاقات فقط', charts: 'عرض الرسوم فقط' },
  en: { all: 'Show cards and charts', cards: 'Show cards only', charts: 'Show charts only' }
}

const icons = {
  all: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h7v7H4V5Zm9 0h7v7h-7V5ZM4 14h7v5H4v-5Zm9 0h7v5h-7v-5Z" />'),
  cards: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />'),
  charts: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16v-5m4 5V8m4 8v-7" />')
}

const items = computed(() => {
  const locale = props.locale || 'ar'
  return (['all', 'cards', 'charts'] as ViewMode[]).map(key => ({
    key,
    label: labels[locale][key],
    icon: icons[key],
    tooltip: tooltips[locale][key]
  }))
})
</script>

<style scoped>
.ym-control-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  border: 1px solid var(--ym-card-border);
  border-radius: 20px;
  background: var(--ym-control-bg);
  padding: 0.35rem;
}

.ym-control-pill {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  gap: 0.45rem;
  border-radius: 15px;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 900;
  padding: 0 1rem;
  transition: transform 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease;
}

.ym-control-pill:hover,
.ym-control-pill.is-active {
  background: rgba(99, 102, 241, 0.18);
  color: var(--ym-text);
  box-shadow: 0 12px 24px rgba(99, 102, 241, 0.14);
  transform: translateY(-1px);
}

.has-tooltip {
  position: relative;
}

.has-tooltip::after {
  position: absolute;
  inset-inline-start: 50%;
  bottom: calc(100% + 9px);
  z-index: 90;
  width: max-content;
  border: 1px solid var(--ym-shell-border);
  border-radius: 9px;
  background: var(--ym-tooltip-bg);
  color: var(--ym-text);
  content: attr(data-tooltip);
  font-size: 13px;
  font-weight: 850;
  opacity: 0;
  padding: 0.42rem 0.6rem;
  pointer-events: none;
  transform: translate(-50%, 5px);
  transition: opacity 140ms ease 220ms, transform 140ms ease 220ms;
  white-space: nowrap;
}

.has-tooltip:hover::after,
.has-tooltip:focus-visible::after {
  opacity: 1;
  transform: translate(-50%, 0);
}
</style>
