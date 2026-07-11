<template>
  <article
    class="ym-metric-card group"
    :style="{ '--metric-color': color || '#6366f1' }"
    tabindex="0"
    :aria-label="tooltipAriaLabel"
    :aria-describedby="tooltipVisible ? tooltipId : undefined"
    @mouseenter="showTooltip"
    @mouseleave="hideTooltip"
    @focus="showTooltip"
    @blur="hideTooltip"
    @keydown.esc="hideTooltip"
  >
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

  <Teleport to="body">
    <transition name="ym-metric-tooltip">
      <div
        v-if="tooltipVisible"
        :id="tooltipId"
        class="ym-metric-tooltip"
        :style="{
          top: `${tooltipPosition.top}px`,
          left: `${tooltipPosition.left}px`,
          width: `${tooltipPosition.width}px`,
          '--metric-tooltip-color': color || '#6366f1',
          '--ym-text': tooltipTheme.text,
          '--ym-muted': tooltipTheme.muted,
          '--ym-tooltip-bg': tooltipTheme.background,
          '--ym-shell-border': tooltipTheme.border
        }"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
        role="tooltip"
      >
        <span class="ym-metric-tooltip__eyebrow">
          <i />
          {{ label }}
        </span>
        <strong>{{ tooltipLabels.value }}: {{ displayValue }}</strong>
        <dl>
          <div>
            <dt>{{ tooltipLabels.period }}</dt>
            <dd>{{ periodLabel }}</dd>
          </div>
        </dl>
        <p class="ym-metric-tooltip__description">{{ resolvedTooltipDescription }}</p>
      </div>
    </transition>
  </Teleport>
</template>

<script setup lang="ts">
interface MetricTooltipLabels {
  value: string
  period: string
}

const props = defineProps<{
  label: string
  value: number
  icon: string
  subtitle?: string
  trend?: number
  color?: string
  locale?: 'ar' | 'en'
  trendLabel?: string
  periodLabel?: string
  tooltipDescription?: string
  tooltipLabels?: MetricTooltipLabels
}>()

const locale = computed(() => props.locale || 'ar')
const trendLabel = computed(() => props.trendLabel || (locale.value === 'ar' ? 'من الفترة السابقة' : 'vs previous period'))
const tooltipId = useId()
const tooltipVisible = ref(false)
const tooltipPosition = reactive({ top: 0, left: 0, width: 300 })
const tooltipTheme = reactive({
  text: '#f0f6ff',
  muted: 'rgba(226, 232, 240, 0.92)',
  background: 'rgba(8, 14, 30, 0.94)',
  border: 'rgba(148, 163, 184, 0.3)'
})

const tooltipLabels = computed<MetricTooltipLabels>(() => props.tooltipLabels || (locale.value === 'ar'
  ? { value: 'القيمة', period: 'الفترة' }
  : { value: 'Value', period: 'Period' }))

const periodLabel = computed(() => props.periodLabel || (locale.value === 'ar' ? 'الفترة الحالية' : 'Current period'))
const resolvedTooltipDescription = computed(() => props.tooltipDescription || (locale.value === 'ar'
  ? 'يعرض هذا المؤشر القيمة ضمن الفترة المختارة.'
  : 'This metric shows the value within the selected period.'))

const displayValue = computed(() => {
  const numberLocale = locale.value === 'ar' ? 'ar-SA' : 'en-US'
  if (props.value >= 1000000) return (props.value / 1000000).toFixed(1) + 'M'
  if (props.value >= 1000) return (props.value / 1000).toFixed(1) + 'K'
  return props.value.toLocaleString(numberLocale)
})

const tooltipAriaLabel = computed(() => {
  const details = [
    props.label,
    `${tooltipLabels.value.value}: ${displayValue.value}`,
    `${tooltipLabels.value.period}: ${periodLabel.value}`,
    resolvedTooltipDescription.value
  ]

  return details.join('. ')
})

function showTooltip(event: MouseEvent | FocusEvent): void {
  const target = event.currentTarget as HTMLElement | null
  if (!target) return

  const bounds = target.getBoundingClientRect()
  const targetStyles = window.getComputedStyle(target)
  const viewportPadding = 12
  const width = Math.min(300, window.innerWidth - viewportPadding * 2)
  const estimatedHeight = 170
  const left = Math.max(
    viewportPadding,
    Math.min(bounds.left + bounds.width / 2 - width / 2, window.innerWidth - width - viewportPadding)
  )
  const top = window.innerHeight - bounds.bottom >= estimatedHeight + viewportPadding
    ? bounds.bottom + 10
    : Math.max(viewportPadding, bounds.top - estimatedHeight - 10)

  tooltipPosition.top = top
  tooltipPosition.left = left
  tooltipPosition.width = width
  tooltipTheme.text = targetStyles.getPropertyValue('--ym-text').trim() || tooltipTheme.text
  tooltipTheme.muted = targetStyles.getPropertyValue('--ym-muted').trim() || tooltipTheme.muted
  tooltipTheme.background = targetStyles.getPropertyValue('--ym-tooltip-bg').trim() || tooltipTheme.background
  tooltipTheme.border = targetStyles.getPropertyValue('--ym-shell-border').trim() || tooltipTheme.border
  tooltipVisible.value = true
}

function hideTooltip(): void {
  tooltipVisible.value = false
}
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

.ym-metric-card:focus-visible {
  border-color: color-mix(in srgb, var(--metric-color) 58%, transparent);
  outline: 3px solid color-mix(in srgb, var(--metric-color) 34%, transparent);
  outline-offset: 3px;
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

.ym-metric-tooltip {
  --metric-tooltip-color: #6366f1;
  position: fixed;
  z-index: 10050;
  display: grid;
  gap: 0.5rem;
  border: 1px solid color-mix(in srgb, var(--metric-tooltip-color) 52%, var(--ym-shell-border));
  border-radius: 16px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-tooltip-bg) 86%, rgba(255, 255, 255, 0.08)), var(--ym-tooltip-bg)),
    var(--ym-tooltip-bg);
  box-shadow:
    0 22px 48px rgba(2, 6, 23, 0.34),
    0 0 24px color-mix(in srgb, var(--metric-tooltip-color) 16%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  color: var(--ym-text);
  box-sizing: border-box;
  padding: 0.85rem 0.95rem;
  pointer-events: none;
  backdrop-filter: blur(20px) saturate(150%);
}

.ym-metric-tooltip__eyebrow {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
}

.ym-metric-tooltip__eyebrow i {
  height: 9px;
  width: 9px;
  flex: 0 0 9px;
  border-radius: 999px;
  background: var(--metric-tooltip-color);
  box-shadow: 0 0 12px var(--metric-tooltip-color);
}

.ym-metric-tooltip > strong {
  color: var(--ym-text);
  font-size: 16px;
  font-weight: 950;
}

.ym-metric-tooltip dl {
  display: grid;
  gap: 0.38rem;
  margin: 0;
}

.ym-metric-tooltip dl > div {
  display: grid;
  grid-template-columns: minmax(5.5rem, auto) minmax(0, 1fr);
  align-items: start;
  gap: 0.65rem;
}

.ym-metric-tooltip dt,
.ym-metric-tooltip dd {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.45;
}

.ym-metric-tooltip dt {
  color: var(--ym-muted);
  font-weight: 820;
}

.ym-metric-tooltip dd {
  color: var(--ym-text);
  font-weight: 900;
  text-align: end;
  overflow-wrap: anywhere;
}

.ym-metric-tooltip__description {
  border-top: 1px solid color-mix(in srgb, var(--metric-tooltip-color) 24%, var(--ym-shell-border));
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 820;
  line-height: 1.55;
  margin: 0;
  padding-top: 0.5rem;
}

.ym-metric-tooltip-enter-active,
.ym-metric-tooltip-leave-active {
  transition: opacity 120ms ease, transform 120ms ease;
}

.ym-metric-tooltip-enter-from,
.ym-metric-tooltip-leave-to {
  opacity: 0;
  transform: translateY(5px) scale(0.97);
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
