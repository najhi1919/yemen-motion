<template>
  <section class="ym-chart-card" :style="{ '--ym-line-color': lineColor }">
    <div class="ym-chart-card__head">
      <div>
        <h3>{{ title }}</h3>
        <p v-if="subtitle">{{ subtitle }}</p>
      </div>
      <slot name="actions" />
    </div>

    <div
      class="ym-chart-stage"
      :style="type === 'bar' ? { minHeight: '380px', height: 'auto' } : { height: height + 'px' }"
      @mouseleave="hideTooltip"
    >
      <div v-if="type === 'bar'" class="ym-chart-bars">
        <div
          v-for="item in activeSections"
          :key="item.key"
          class="ym-chart-column-block"
          :style="{ width: `calc(100% / ${activeSections.length})`, '--ym-bar-color': item.color }"
        >
          <span class="ym-chart-top-value">{{ formatValue(item.value) }}</span>

          <div
            class="ym-chart-bar-container"
            @mouseenter="showBarTooltip($event, item, 0)"
            @mousemove="showBarTooltip($event, item, 0)"
          >
            <div class="ym-bar-pill-svg" :style="{ height: item.percentage + '%' }" />
          </div>

          <span class="ym-chart-bottom-label">{{ item.label }}</span>
        </div>
      </div>

      <svg v-else :viewBox="`0 0 ${width} ${height}`" class="ym-chart-svg" preserveAspectRatio="none">
        <defs>
          <linearGradient :id="gradientId" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" :stop-color="lineColor" stop-opacity="0.34" />
            <stop offset="100%" :stop-color="lineColor" stop-opacity="0" />
          </linearGradient>
        </defs>

        <line
          v-for="y in gridLines"
          :key="'grid-' + y"
          :x1="chartPadding"
          :x2="width - chartPadding"
          :y1="y"
          :y2="y"
          stroke="currentColor"
          stroke-width="0.8"
          class="ym-chart-grid"
          stroke-dasharray="5 8"
        />

        <g>
          <path v-if="areaPath" :d="areaPath" :fill="`url(#${gradientId})`" />
          <path
            v-if="linePath"
            :d="linePath"
            fill="none"
            :stroke="lineColor"
            stroke-width="4"
            stroke-linecap="round"
            stroke-linejoin="round"
            class="ym-chart-line"
          />
          <g v-for="(dot, i) in dataPoints" :key="i">
            <circle :cx="dot.x" :cy="dot.y" r="10" fill="transparent" @mouseenter="showPointTooltip($event, i)" @mousemove="showPointTooltip($event, i)" />
            <circle :cx="dot.x" :cy="dot.y" r="5.5" :fill="lineColor" stroke="white" stroke-width="2.2" class="ym-chart-dot" />
          </g>
          <text
            v-if="lastPoint"
            :x="Math.min(width - 42, lastPoint.x + 14)"
            :y="Math.max(22, lastPoint.y - 12)"
            class="ym-chart-value"
          >
            {{ formatValue(lastValue) }}
          </text>
        </g>
      </svg>

      <div v-if="visibleLabels.length" class="ym-chart-labels">
        <span v-for="label in visibleLabels" :key="label">{{ label }}</span>
      </div>

      <transition name="ym-tooltip">
        <div
          v-if="tooltip.visible"
          class="ym-chart-tooltip"
          :style="{ left: tooltip.x + 'px', top: tooltip.y + 'px', '--tooltip-color': tooltip.color }"
        >
          <span class="ym-chart-tooltip__eyebrow">
            <i />
            {{ tooltip.section }}
          </span>
          <strong>{{ tooltip.detail }}</strong>
          <span>{{ periodLabel }} · {{ totalLabel }}: {{ formatValue(tooltip.total) }}</span>
          <small>{{ detailLabel }}</small>
        </div>
      </transition>
    </div>

    <div v-if="legendItems.length" class="ym-chart-legend">
      <span v-for="item in legendItems" :key="item.key">
        <i :style="{ background: item.color }" />
        {{ item.label }}
      </span>
    </div>
  </section>
</template>

<script setup lang="ts">
interface BreakdownItem {
  label: string
  value: number
}

interface BarItem {
  key: string
  label: string
  value: number
  color: string
  breakdown?: BreakdownItem[]
}

interface ActiveSection extends BarItem {
  percentage: number
}

const props = withDefaults(defineProps<{
  title: string
  subtitle?: string
  labels?: string[]
  data?: number[]
  bars?: BarItem[]
  type?: 'line' | 'bar'
  lineColor?: string
  height?: number
  width?: number
  periodLabel?: string
  totalLabel?: string
  detailLabel?: string
}>(), {
  labels: () => [],
  data: () => [],
  bars: () => [],
  type: 'line',
  lineColor: '#6366f1',
  height: 250,
  width: 680,
  periodLabel: 'Period',
  totalLabel: 'Total',
  detailLabel: 'Static demo breakdown'
})

const chartPadding = 28
const tooltip = reactive({
  visible: false,
  x: 0,
  y: 0,
  section: '',
  detail: '',
  total: 0,
  color: '#6366f1'
})

const gradientId = computed(() => `chart-gradient-${safeId(props.title)}`)

const gridLines = computed(() => {
  const count = 5
  return Array.from({ length: count - 1 }, (_, i) => chartPadding + ((props.height - 62) / count) * (i + 1))
})

const dataPoints = computed(() => {
  if (!props.data.length) return []
  const max = Math.max(...props.data)
  const min = Math.min(...props.data)
  const range = max - min || 1
  const denominator = Math.max(props.data.length - 1, 1)

  return props.data.map((value, index) => ({
    x: chartPadding + (index / denominator) * (props.width - chartPadding * 2),
    y: chartPadding + ((max - value) / range) * (props.height - 78)
  }))
})

const linePath = computed(() => dataPoints.value.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' '))

const areaPath = computed(() => {
  if (!dataPoints.value.length) return ''
  const first = dataPoints.value[0]
  const last = dataPoints.value[dataPoints.value.length - 1]
  return `${linePath.value} L ${last.x} ${props.height - 34} L ${first.x} ${props.height - 34} Z`
})

const lastPoint = computed(() => dataPoints.value[dataPoints.value.length - 1])
const lastValue = computed(() => props.data[props.data.length - 1] || 0)

const activeSections = computed<ActiveSection[]>(() => {
  if (!props.bars.length) return []
  const max = Math.max(...props.bars.map(item => item.value), 1)

  return props.bars.map(item => ({
    ...item,
    percentage: Math.max(7, (item.value / max) * 100)
  }))
})

const visibleLabels = computed(() => {
  if (props.type === 'bar') return []
  if (!props.labels.length) return []
  const step = Math.ceil(props.labels.length / 6)
  return props.labels.filter((_, index) => index % step === 0)
})

const legendItems = computed(() => {
  if (props.type === 'bar') return props.bars.map(item => ({ key: item.key, label: item.label, color: item.color }))
  return props.title ? [{ key: props.title, label: props.title, color: props.lineColor }] : []
})

function safeId(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9\u0600-\u06FF]/g, '-').replace(/-+/g, '-')
}

function positionTooltip(event: MouseEvent): void {
  const target = event.currentTarget as Element
  const stage = target.closest('.ym-chart-stage')
  const bounds = (stage || target).getBoundingClientRect()
  const tooltipWidth = 224
  const tooltipHeight = 108
  const rawX = event.clientX - bounds.left + 14
  const rawY = event.clientY - bounds.top - 34
  tooltip.x = Math.max(12, Math.min(rawX, bounds.width - tooltipWidth - 12))
  tooltip.y = Math.max(12, Math.min(rawY, bounds.height - tooltipHeight - 12))
}

function showBarTooltip(event: MouseEvent, bar: ActiveSection, zoneIndex: number): void {
  const detail = bar.breakdown?.[zoneIndex] || { label: bar.label, value: bar.value }
  positionTooltip(event)
  tooltip.visible = true
  tooltip.section = bar.label
  tooltip.detail = `${detail.label} — ${formatValue(detail.value)}`
  tooltip.total = bar.value
  tooltip.color = bar.color
}

function showPointTooltip(event: MouseEvent, index: number): void {
  positionTooltip(event)
  tooltip.visible = true
  tooltip.section = props.title
  tooltip.detail = `${props.labels[index] || props.title} — ${formatValue(props.data[index] || 0)}`
  tooltip.total = props.data[index] || 0
  tooltip.color = props.lineColor
}

function hideTooltip(): void {
  tooltip.visible = false
}

function formatValue(value: number): string {
  if (value >= 1000000) return `${(value / 1000000).toFixed(1)}M`
  if (value >= 1000) return `${(value / 1000).toFixed(1)}K`
  return String(value)
}
</script>

<style scoped>
.ym-chart-card {
  position: relative;
  isolation: isolate;
  border: 1px solid var(--ym-card-border);
  border-radius: 26px;
  background:
    radial-gradient(circle at 12% 0%, rgba(244, 114, 182, 0.13), transparent 18rem),
    radial-gradient(circle at 88% 6%, rgba(56, 189, 248, 0.13), transparent 20rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.07)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 22px 54px color-mix(in srgb, var(--ym-line-color) 10%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  padding: clamp(1.2rem, 2vw, 1.6rem);
  transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
}

.ym-chart-card::before {
  position: absolute;
  inset-inline-start: 1.45rem;
  top: 0;
  height: 3px;
  width: min(18rem, calc(100% - 2.9rem));
  border-end-end-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(90deg, #6366f1, #ec4899 48%, #38bdf8);
  box-shadow: 0 0 28px rgba(129, 140, 248, 0.28);
  content: "";
  pointer-events: none;
  z-index: 0;
}

.ym-chart-card::after {
  position: absolute;
  inset: 1px;
  inset-block-end: auto;
  height: 52%;
  border-radius: 25px 25px 0 0;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.09), transparent 72%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.09), transparent 36%);
  content: "";
  pointer-events: none;
  z-index: 0;
}

.ym-chart-card > * {
  position: relative;
  z-index: 1;
}

.ym-chart-card:hover {
  border-color: color-mix(in srgb, var(--ym-line-color) 36%, var(--ym-card-border));
  box-shadow:
    0 30px 72px rgba(2, 6, 23, 0.27),
    0 0 42px color-mix(in srgb, var(--ym-line-color) 13%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.2),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  transform: translateY(-2px);
}

.ym-chart-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 82%, rgba(129, 140, 248, 0.18));
  margin-bottom: 1.1rem;
  padding-bottom: 1rem;
}

.ym-chart-card__head h3 {
  color: var(--ym-text);
  font-size: clamp(20px, 2vw, 23px);
  font-weight: 950;
  line-height: 1.25;
  margin: 0;
  text-wrap: balance;
}

.ym-chart-card__head p {
  color: var(--ym-muted);
  font-size: 14.5px;
  font-weight: 820;
  line-height: 1.6;
  margin: 0.35rem 0 0;
  max-width: 44rem;
}

.ym-chart-stage {
  position: relative;
  overflow: visible;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background:
    linear-gradient(var(--ym-chart-grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--ym-chart-grid) 1px, transparent 1px),
    radial-gradient(circle at 50% 0%, color-mix(in srgb, #818cf8 14%, transparent), transparent 58%),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 92%, rgba(255, 255, 255, 0.04)), color-mix(in srgb, var(--ym-control-bg) 78%, transparent));
  background-position: center;
  background-size: 100% 25%, 12.5% 100%, auto, auto;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08),
    0 14px 34px rgba(2, 6, 23, 0.1);
  padding-bottom: 1.8rem;
}

.ym-chart-stage::before {
  position: absolute;
  inset: 0.75rem 0.9rem auto;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  content: "";
  pointer-events: none;
}

.ym-chart-svg {
  display: block;
  height: 100%;
  width: 100%;
  overflow: visible;
}

.ym-chart-bars {
  display: flex !important;
  flex-direction: row-reverse !important;
  justify-content: space-between !important;
  align-items: flex-end !important;
  width: 100% !important;
  height: auto !important;
  min-height: 380px !important;
  padding: 2.25rem 1rem 0.65rem 1rem !important;
  overflow: visible !important;
  box-sizing: border-box !important;
}

.ym-chart-column-block {
  display: flex !important;
  flex-direction: column !important;
  align-items: center !important;
  justify-content: flex-end !important;
  min-width: 0;
  overflow: visible !important;
  text-align: center !important;
  transition: transform 180ms ease;
}

.ym-chart-top-value {
  color: var(--ym-text) !important;
  font-size: 13.5px !important;
  font-weight: 900 !important;
  line-height: 1.35 !important;
  max-width: 100%;
  overflow: visible !important;
  white-space: nowrap !important;
}

.ym-chart-bar-container {
  width: 100% !important;
  height: clamp(170px, 44vh, 330px) !important;
  display: flex !important;
  align-items: flex-end !important;
  justify-content: center !important;
  margin: 0.65rem 0 0 !important;
  overflow: visible !important;
  cursor: crosshair;
}

.ym-bar-pill-svg {
  position: relative;
  width: min(48px, 80%) !important;
  min-height: 12px;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--ym-bar-color) 54%, transparent) !important;
  border-bottom: none !important;
  border-radius: 14px 14px 2px 2px !important;
  background:
    linear-gradient(90deg, rgba(255, 255, 255, 0.22), transparent 28%, rgba(255, 255, 255, 0.1) 50%, transparent 76%),
    linear-gradient(
      180deg,
      color-mix(in srgb, var(--ym-bar-color) 96%, white 8%) 0%,
      color-mix(in srgb, var(--ym-bar-color) 86%, white 9%) 42%,
      color-mix(in srgb, var(--ym-bar-color) 42%, transparent) 100%
    );
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.34),
    inset 0 -12px 20px color-mix(in srgb, var(--ym-bar-color) 20%, transparent),
    0 16px 26px color-mix(in srgb, var(--ym-bar-color) 18%, transparent);
  opacity: 0.98;
  transform-origin: center bottom;
  transition: transform 220ms cubic-bezier(0.16, 1, 0.3, 1), filter 220ms ease, box-shadow 220ms ease;
}

.ym-chart-bottom-label {
  color: var(--ym-text) !important;
  font-size: 14px !important;
  font-weight: 800 !important;
  line-height: 1.4 !important;
  margin-top: 0.85rem !important;
  max-width: 100%;
  overflow: visible !important;
  text-overflow: clip;
  white-space: nowrap !important;
}

.ym-chart-column-block:hover .ym-bar-pill-svg,
.ym-chart-column-block:hover .ym-chart-bar-container > div {
  transform: scaleY(1.03) scaleX(1.02) !important;
  filter: drop-shadow(0 0 18px color-mix(in srgb, var(--ym-bar-color) 34%, transparent)) !important;
}

.ym-chart-grid {
  color: var(--ym-chart-grid);
  opacity: 0.82;
}

.ym-chart-value {
  fill: var(--ym-text);
  font-size: 14.5px;
  font-weight: 950;
  paint-order: stroke;
  stroke: var(--ym-chart-value-stroke);
  stroke-width: 5.5px;
}

.ym-chart-line {
  filter: drop-shadow(0 8px 12px color-mix(in srgb, var(--ym-line-color) 24%, transparent));
}

.ym-chart-dot {
  pointer-events: none;
  filter:
    drop-shadow(0 0 10px color-mix(in srgb, var(--ym-line-color) 30%, transparent))
    drop-shadow(0 5px 8px rgba(15, 23, 42, 0.22));
}

.ym-chart-labels {
  position: absolute;
  inset-inline: 1.2rem;
  bottom: 0.5rem;
  display: flex;
  justify-content: space-between;
  gap: 0.45rem;
}

.ym-chart-labels span {
  min-width: 0;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-chart-tooltip {
  --tooltip-color: #6366f1;
  position: absolute;
  z-index: 12;
  display: grid;
  width: 212px;
  gap: 0.28rem;
  border: 1px solid color-mix(in srgb, var(--tooltip-color) 52%, var(--ym-shell-border));
  border-radius: 17px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-tooltip-bg) 86%, rgba(255, 255, 255, 0.08)), var(--ym-tooltip-bg)),
    var(--ym-tooltip-bg);
  box-shadow:
    0 22px 48px rgba(2, 6, 23, 0.34),
    0 0 24px color-mix(in srgb, var(--tooltip-color) 16%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  color: var(--ym-text);
  padding: 0.75rem 0.85rem;
  pointer-events: none;
  backdrop-filter: blur(20px) saturate(150%);
}

.ym-chart-tooltip__eyebrow {
  display: flex;
  align-items: center;
  gap: 0.42rem;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 900;
}

.ym-chart-tooltip__eyebrow i {
  height: 9px;
  width: 9px;
  border-radius: 999px;
  background: var(--tooltip-color);
  box-shadow: 0 0 12px var(--tooltip-color);
}

.ym-chart-tooltip strong {
  color: var(--ym-text);
  font-size: 15px;
  font-weight: 950;
}

.ym-chart-tooltip > span:not(.ym-chart-tooltip__eyebrow) {
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 820;
}

.ym-chart-tooltip small {
  color: color-mix(in srgb, var(--tooltip-color) 72%, var(--ym-text));
  font-size: 13.5px;
  font-weight: 900;
}

.ym-tooltip-enter-active,
.ym-tooltip-leave-active {
  transition: opacity 120ms ease, transform 120ms ease;
}

.ym-tooltip-enter-from,
.ym-tooltip-leave-to {
  opacity: 0;
  transform: translateY(5px) scale(0.97);
}

.ym-chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-top: 1rem;
}

.ym-chart-legend span {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 80%, rgba(129, 140, 248, 0.14));
  border-radius: 999px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 88%, rgba(255, 255, 255, 0.05)), var(--ym-control-bg));
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 900;
  padding: 0.52rem 0.8rem;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.ym-chart-legend i {
  height: 0.72rem;
  width: 0.72rem;
  border-radius: 999px;
  box-shadow: 0 0 14px currentColor;
}

:global(.ym-dashboard-light) .ym-chart-card {
  border-color: color-mix(in srgb, var(--ym-card-border) 88%, rgba(109, 40, 217, 0.18));
  background:
    radial-gradient(circle at 12% 0%, rgba(236, 72, 153, 0.09), transparent 18rem),
    radial-gradient(circle at 88% 6%, rgba(14, 165, 233, 0.1), transparent 20rem),
    linear-gradient(180deg, rgba(255, 255, 255, 0.92), rgba(248, 244, 255, 0.96)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 44px color-mix(in srgb, var(--ym-line-color) 8%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.62),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06);
}

:global(.ym-dashboard-light) .ym-chart-card::after {
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.24), transparent 68%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.16), transparent 36%);
}

:global(.ym-dashboard-light) .ym-chart-card:hover {
  border-color: color-mix(in srgb, var(--ym-line-color) 42%, var(--ym-card-border));
  box-shadow:
    0 30px 72px rgba(76, 29, 149, 0.17),
    0 0 36px color-mix(in srgb, var(--ym-line-color) 10%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.68),
    inset 0 -1px 0 rgba(109, 40, 217, 0.07);
}

:global(.ym-dashboard-light) .ym-chart-card__head {
  border-bottom-color: color-mix(in srgb, var(--ym-soft-border) 86%, rgba(109, 40, 217, 0.12));
}

:global(.ym-dashboard-light) .ym-chart-stage {
  border-color: color-mix(in srgb, var(--ym-soft-border) 84%, rgba(109, 40, 217, 0.12));
  background:
    linear-gradient(var(--ym-chart-grid) 1px, transparent 1px),
    linear-gradient(90deg, var(--ym-chart-grid) 1px, transparent 1px),
    radial-gradient(circle at 50% 0%, rgba(129, 140, 248, 0.12), transparent 58%),
    linear-gradient(180deg, rgba(255, 255, 255, 0.76), rgba(246, 240, 255, 0.9));
  background-position: center;
  background-size: 100% 25%, 12.5% 100%, auto, auto;
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.46),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06),
    0 14px 34px rgba(76, 29, 149, 0.08);
}

:global(.ym-dashboard-light) .ym-chart-stage::before {
  background: linear-gradient(90deg, transparent, rgba(109, 40, 217, 0.18), transparent);
}

:global(.ym-dashboard-light) .ym-bar-pill-svg {
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.46),
    inset 0 -12px 20px color-mix(in srgb, var(--ym-bar-color) 17%, transparent),
    0 14px 24px color-mix(in srgb, var(--ym-bar-color) 14%, transparent);
}

:global(.ym-dashboard-light) .ym-chart-tooltip {
  box-shadow:
    0 22px 46px rgba(76, 29, 149, 0.18),
    0 0 22px color-mix(in srgb, var(--tooltip-color) 12%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.52);
  backdrop-filter: blur(10px) saturate(128%);
}

:global(.ym-dashboard-light) .ym-chart-legend span {
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.76), rgba(248, 244, 255, 0.9));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.48);
}

@media (prefers-reduced-motion: reduce) {
  .ym-chart-card:hover,
  .ym-chart-column-block:hover .ym-bar-pill-svg,
  .ym-chart-column-block:hover .ym-chart-bar-container > div {
    transform: none !important;
  }
}
</style>
