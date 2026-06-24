<template>
  <div class="ym-section-filter">
    <button
      type="button"
      class="ym-section-chip has-tooltip"
      :class="isAllActive ? 'is-active' : ''"
      :data-tooltip="allTooltip"
      :aria-label="allTooltip"
      :title="allTooltip"
      @click="$emit('toggle-all')"
    >
      <span class="ym-section-all-icon">✓</span>
      {{ allLabel }}
    </button>
    <button
      v-for="section in sections"
      :key="section.key"
      type="button"
      class="ym-section-chip has-tooltip"
      :class="modelValue.includes(section.key) ? 'is-active' : ''"
      :style="{ '--section-color': section.color }"
      :data-tooltip="sectionTooltip(section.label)"
      :aria-label="sectionTooltip(section.label)"
      :title="sectionTooltip(section.label)"
      @click="$emit('toggle-section', section.key)"
    >
      <span v-if="section.icon" class="ym-section-symbol">{{ section.icon }}</span>
      <span class="ym-section-dot" />
      {{ section.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
export interface DashboardSectionOption {
  key: string
  label: string
  color: string
  icon?: string
}

const props = defineProps<{
  modelValue: string[]
  sections: DashboardSectionOption[]
  locale?: 'ar' | 'en'
}>()

defineEmits<{
  'toggle-all': []
  'toggle-section': [key: string]
}>()

const isAllActive = computed(() => props.modelValue.length === props.sections.length)
const allLabel = computed(() => props.locale === 'en' ? 'All' : 'الجميع')
const allTooltip = computed(() => props.locale === 'en' ? 'Show all sections' : 'عرض جميع الأقسام')

function sectionTooltip(label: string): string {
  return props.locale === 'en' ? `Show ${label} section` : `عرض قسم ${label}`
}
</script>

<style scoped>
.ym-section-filter {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  max-height: 164px;
  overflow-y: auto;
  padding: 0.12rem 0.12rem 0.35rem;
  scrollbar-color: rgba(129, 140, 248, 0.34) transparent;
  scrollbar-width: thin;
}

.ym-section-chip {
  --section-color: #6366f1;
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  gap: 0.5rem;
  border: 1px solid var(--ym-card-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 900;
  padding: 0 1rem;
  transition: transform 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
}

.ym-section-filter::-webkit-scrollbar {
  width: 5px;
}

.ym-section-filter::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: rgba(129, 140, 248, 0.3);
}

.ym-section-chip:hover,
.ym-section-chip.is-active {
  border-color: color-mix(in srgb, var(--section-color) 52%, transparent);
  background: color-mix(in srgb, var(--section-color) 18%, transparent);
  color: var(--ym-text);
  box-shadow: 0 14px 28px color-mix(in srgb, var(--section-color) 16%, transparent);
  transform: translateY(-1px);
}

.ym-section-dot {
  height: 0.68rem;
  width: 0.68rem;
  border-radius: 999px;
  background: var(--section-color);
  box-shadow: 0 0 15px color-mix(in srgb, var(--section-color) 60%, transparent);
}

.ym-section-symbol,
.ym-section-all-icon {
  display: grid;
  height: 22px;
  min-width: 22px;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, var(--section-color) 16%, transparent);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-section-all-icon {
  --section-color: #6366f1;
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
  max-width: 220px;
  border: 1px solid var(--ym-shell-border);
  border-radius: 9px;
  background: var(--ym-tooltip-bg);
  color: var(--ym-text);
  content: attr(data-tooltip);
  font-size: 13px;
  font-weight: 850;
  line-height: 1.4;
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
