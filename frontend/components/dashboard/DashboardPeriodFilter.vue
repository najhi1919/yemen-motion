<template>
  <div class="ym-control-group">
    <button
      v-for="item in items"
      :key="item.key"
      type="button"
      class="ym-control-pill"
      :class="modelValue === item.key ? 'is-active' : ''"
      :aria-label="item.tooltip"
      @click="$emit('update:modelValue', item.key)"
    >
      {{ item.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
type Period = 'day' | 'week' | 'month' | 'year'

const props = defineProps<{
  modelValue: Period
  locale?: 'ar' | 'en'
}>()

defineEmits<{
  'update:modelValue': [value: Period]
}>()

const labels = {
  ar: { day: 'اليوم', week: 'الأسبوع', month: 'الشهر', year: 'السنة' },
  en: { day: 'Day', week: 'Week', month: 'Month', year: 'Year' }
}

const tooltips = {
  ar: { day: 'تصفية حسب اليوم', week: 'تصفية حسب الأسبوع', month: 'تصفية حسب الشهر', year: 'تصفية حسب السنة' },
  en: { day: 'Filter by day', week: 'Filter by week', month: 'Filter by month', year: 'Filter by year' }
}

const items = computed(() => {
  const locale = props.locale || 'ar'
  return (['day', 'week', 'month', 'year'] as Period[]).map(key => ({
    key,
    label: labels[locale][key],
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
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.ym-control-pill {
  min-height: 44px;
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

</style>
