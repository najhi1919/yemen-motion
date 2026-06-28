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
/* ===== سطح البطاقة: Premium glass depth ===== */
.ym-metric-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 88%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -1px 0 rgba(15, 23, 42, 0.06);
  padding: 1.55rem;
  transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
}

/* شريط علوي ملون يحدد هوية القسم — gradient أنعم */
.ym-metric-card::before {
  position: absolute;
  inset-inline-start: 0;
  top: 0;
  height: 3px;
  width: 52%;
  border-end-end-radius: 999px;
  background: linear-gradient(90deg, var(--metric-color), color-mix(in srgb, var(--metric-color) 40%, transparent));
  content: "";
}

/* لمعة علوية داخلية — تعطي عمق زجاجي */
.ym-metric-card::after {
  position: absolute;
  inset: 1px;
  inset-block-end: auto;
  height: 50%;
  border-radius: 23px 23px 0 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent);
  content: "";
  pointer-events: none;
}

/* hover: lift + glow لوني + حد ملون */
.ym-metric-card:hover {
  border-color: color-mix(in srgb, var(--metric-color) 48%, transparent);
  box-shadow:
    0 28px 68px rgba(2, 6, 23, 0.24),
    0 0 40px color-mix(in srgb, var(--metric-color) 16%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.22),
    inset 0 -1px 0 rgba(15, 23, 42, 0.06);
  transform: translateY(-3px);
}

@media (prefers-reduced-motion: reduce) {
  .ym-metric-card:hover { transform: none; }
}

/* توهّج لوني خلفي خفيف — عمق ديناميكي حسب لون القسم */
.ym-metric-card__shine {
  position: absolute;
  inset: -30% -20% auto auto;
  height: 9rem;
  width: 9rem;
  border-radius: 999px;
  background: var(--metric-color);
  filter: blur(54px);
  opacity: 0.12;
}

.ym-metric-card:hover .ym-metric-card__shine {
  opacity: 0.18;
}

/* ===== الرأس: label + value + icon ===== */
.ym-metric-card__head {
  position: relative;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.ym-metric-card__label {
  color: var(--ym-muted);
  font-size: 15.5px;
  font-weight: 850;
  letter-spacing: 0.01em;
  line-height: 1.4;
  margin: 0 0 0.5rem;
}

/* الرقم الرئيسي: أكبر وأوضح مع accent لوني خفيف */
.ym-metric-card__value {
  color: var(--ym-text);
  font-size: clamp(2.4rem, 3.1vw, 2.85rem);
  font-weight: 900;
  line-height: 1;
  letter-spacing: -0.02em;
}

/* ===== أيقونة القسم: حاوية فخمة ===== */
.ym-metric-card__icon {
  display: grid;
  height: 68px;
  width: 68px;
  flex: 0 0 68px;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--metric-color) 32%, transparent);
  border-radius: 20px;
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--metric-color) 18%, transparent), color-mix(in srgb, var(--metric-color) 8%, transparent));
  box-shadow:
    0 14px 34px color-mix(in srgb, var(--metric-color) 14%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  font-size: 34px;
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-metric-card:hover .ym-metric-card__icon {
  transform: scale(1.04);
  box-shadow:
    0 18px 40px color-mix(in srgb, var(--metric-color) 22%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

/* ===== الوصف الثانوي ===== */
.ym-metric-card__subtitle {
  position: relative;
  color: var(--ym-muted);
  font-size: 14.5px;
  font-weight: 820;
  line-height: 1.6;
  margin: 0.75rem 0 0;
}

/* ===== الترند / الاتجاه ===== */
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
  font-weight: 900;
}

.ym-metric-card__trend small {
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 820;
}

/* ===== Light Mode — بطاقات أغنى وأوضح بدون haze ===== */
.ym-dashboard-light .ym-metric-card {
  border-color: color-mix(in srgb, var(--ym-card-border) 88%, rgba(109, 40, 217, 0.2));
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 85%, rgba(255, 255, 255, 0.12)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    inset 0 1px 0 rgba(255, 255, 255, 0.5),
    inset 0 -1px 0 rgba(109, 40, 217, 0.04);
}

.ym-dashboard-light .ym-metric-card::after {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.18), transparent);
}

.ym-dashboard-light .ym-metric-card:hover {
  border-color: color-mix(in srgb, var(--metric-color) 52%, transparent);
  box-shadow:
    0 28px 68px rgba(76, 29, 149, 0.16),
    0 0 40px color-mix(in srgb, var(--metric-color) 12%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.6),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06);
}

.ym-dashboard-light .ym-metric-card__shine {
  opacity: 0.1;
}

.ym-dashboard-light .ym-metric-card:hover .ym-metric-card__shine {
  opacity: 0.14;
}

.ym-dashboard-light .ym-metric-card__icon {
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--metric-color) 22%, transparent), color-mix(in srgb, var(--metric-color) 10%, transparent));
  box-shadow:
    0 14px 34px color-mix(in srgb, var(--metric-color) 12%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.3);
}

.ym-dashboard-light .ym-metric-card:hover .ym-metric-card__icon {
  box-shadow:
    0 18px 40px color-mix(in srgb, var(--metric-color) 18%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.38);
}

.ym-dashboard-light .ym-metric-card__value {
  letter-spacing: -0.025em;
}

.ym-dashboard-light .ym-metric-card__trend {
  border-top-color: color-mix(in srgb, var(--ym-soft-border) 80%, rgba(109, 40, 217, 0.12));
}
</style>
