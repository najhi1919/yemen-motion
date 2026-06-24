<template>
  <article class="ym-metric-card group" :style="{ '--metric-color': color || '#6366f1' }">
    <div class="ym-metric-card__shine" />
    <div class="ym-metric-card__head">
      <div>
        <p class="ym-metric-card__label">{{ label }}</p>
        <strong class="ym-metric-card__value">{{ displayValue }}</strong>
      </div>
      <div class="ym-metric-card__icon">
        {{ icon }}
      </div>
    </div>
    <p v-if="subtitle" class="ym-metric-card__subtitle">{{ subtitle }}</p>
    <div v-if="trend !== undefined" class="ym-metric-card__trend">
      <span :class="trend >= 0 ? 'text-emerald-400' : 'text-rose-400'">
        {{ trend >= 0 ? '↑' : '↓' }} {{ Math.abs(trend).toLocaleString(locale === 'ar' ? 'ar-SA' : 'en-US') }}%
      </span>
      <small>{{ trendLabel }}</small>
    </div>
  </article>
</template>

<script setup lang="ts">
const props = defineProps<{
  label: string
  value: number
  icon: string
  subtitle?: string
  trend?: number
  color?: string
  locale?: 'ar' | 'en'
  trendLabel?: string
}>()

const locale = computed(() => props.locale || 'ar')
const trendLabel = computed(() => props.trendLabel || (locale.value === 'ar' ? 'من الفترة السابقة' : 'vs previous period'))

const displayValue = computed(() => {
  const numberLocale = locale.value === 'ar' ? 'ar-SA' : 'en-US'
  if (props.value >= 1000000) return (props.value / 1000000).toFixed(1) + 'M'
  if (props.value >= 1000) return (props.value / 1000).toFixed(1) + 'K'
  return props.value.toLocaleString(numberLocale)
})
</script>

<style scoped>
.ym-metric-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.14);
  padding: 1.55rem;
  transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.ym-metric-card::before {
  position: absolute;
  inset-inline-start: 0;
  top: 0;
  height: 4px;
  width: 44%;
  border-end-end-radius: 999px;
  background: linear-gradient(90deg, var(--metric-color), transparent);
  content: "";
}

.ym-metric-card:hover {
  border-color: color-mix(in srgb, var(--metric-color) 42%, transparent);
  box-shadow: 0 24px 62px rgba(2, 6, 23, 0.22), 0 0 36px color-mix(in srgb, var(--metric-color) 18%, transparent);
  transform: translateY(-4px);
}

.ym-metric-card__shine {
  position: absolute;
  inset: -30% -20% auto auto;
  height: 9rem;
  width: 9rem;
  border-radius: 999px;
  background: var(--metric-color);
  filter: blur(54px);
  opacity: 0.14;
}

.ym-metric-card__head {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.ym-metric-card__label {
  color: var(--ym-muted);
  font-size: 16px;
  font-weight: 900;
  line-height: 1.4;
  margin: 0 0 0.5rem;
}

.ym-metric-card__value {
  color: var(--ym-text);
  font-size: clamp(2.35rem, 3vw, 2.75rem);
  font-weight: 950;
  line-height: 1;
}

.ym-metric-card__icon {
  display: grid;
  height: 68px;
  width: 68px;
  flex: 0 0 68px;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--metric-color) 32%, transparent);
  border-radius: 20px;
  background: color-mix(in srgb, var(--metric-color) 14%, transparent);
  box-shadow: 0 14px 34px color-mix(in srgb, var(--metric-color) 16%, transparent);
  font-size: 34px;
}

.ym-metric-card__subtitle {
  position: relative;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.6;
  margin: 0.75rem 0 0;
}

.ym-metric-card__trend {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.55rem;
  border-top: 1px solid var(--ym-soft-border);
  margin-top: 1rem;
  padding-top: 0.9rem;
}

.ym-metric-card__trend span {
  font-size: 14px;
  font-weight: 950;
}

.ym-metric-card__trend small {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
}
</style>
