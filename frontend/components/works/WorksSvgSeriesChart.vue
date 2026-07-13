<template>
  <article class="ym-works-chart-card">
    <header class="ym-works-chart-card__head">
      <div>
        <h2>{{ title }}</h2>
        <p>{{ subtitle }}</p>
      </div>
      <span>{{ formatNumber(series.length) }} {{ labels.points }}</span>
    </header>

    <div v-if="series.length" class="ym-works-chart-scroll">
      <div class="ym-works-chart-stage" dir="ltr" @mouseleave="selectedIndex = null">
        <svg
          :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
          class="ym-works-chart-svg"
          preserveAspectRatio="xMidYMid meet"
          role="img"
          :aria-label="labels.chartAria"
        >
          <g class="ym-works-chart-grid">
            <g v-for="line in gridLines" :key="line.y">
              <line
                :x1="padding.left"
                :x2="chartWidth - padding.right"
                :y1="line.y"
                :y2="line.y"
              />
              <text :x="padding.left - 12" :y="line.y + 4" text-anchor="end">
                {{ formatNumber(line.value) }}
              </text>
            </g>
          </g>

          <g v-for="model in seriesModels" :key="model.key">
            <polyline
              v-if="model.points.length > 1"
              :points="model.polyline"
              fill="none"
              :stroke="model.color"
              stroke-width="4"
              stroke-linecap="round"
              stroke-linejoin="round"
              vector-effect="non-scaling-stroke"
            />

            <circle
              v-for="point in model.points"
              :key="`${model.key}-${point.index}`"
              :cx="point.x"
              :cy="point.y"
              r="5.5"
              :fill="model.color"
              stroke="white"
              stroke-width="2.5"
              vector-effect="non-scaling-stroke"
            />
          </g>

          <g class="ym-works-chart-hit-zones">
            <rect
              v-for="(_, index) in series"
              :key="`hit-${index}`"
              :x="hitZoneX(index)"
              :y="padding.top"
              :width="hitZoneWidth(index)"
              :height="plotHeight"
              fill="transparent"
              tabindex="0"
              focusable="true"
              role="img"
              :aria-label="pointAriaLabel(index)"
              @mouseenter="selectedIndex = index"
              @mousemove="selectedIndex = index"
              @click="selectedIndex = index"
              @focus="selectedIndex = index"
              @blur="selectedIndex = null"
              @keydown.esc="selectedIndex = null"
            />
          </g>

          <g class="ym-works-chart-axis-labels">
            <text
              v-for="index in visibleLabelIndices"
              :key="`label-${index}`"
              :x="xForIndex(index)"
              :y="chartHeight - 18"
              text-anchor="middle"
            >
              {{ series[index]?.label }}
            </text>
          </g>
        </svg>

        <div
          v-if="selectedPoint"
          class="ym-works-chart-tooltip"
          :style="tooltipStyle"
          :dir="locale === 'ar' ? 'rtl' : 'ltr'"
          role="tooltip"
        >
          <strong dir="ltr">{{ selectedPoint.label }}</strong>
          <span v-for="item in tooltipItems" :key="item.key">
            <i :style="{ background: item.color }" />
            {{ item.label }}
            <b>{{ formatNumber(item.value) }}</b>
          </span>
        </div>
      </div>
    </div>

    <div v-else class="ym-works-chart-empty" role="status">
      <span aria-hidden="true">—</span>
      <p>{{ labels.empty }}</p>
    </div>

    <footer class="ym-works-chart-legend" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
      <span v-for="item in legendItems" :key="item.key">
        <i :style="{ background: item.color }" />
        {{ item.label }}
      </span>
    </footer>
  </article>
</template>

<script setup lang="ts">
type Locale = 'ar' | 'en'

interface WorksSeriesPoint {
  label: string
  submitted: number
  published: number
  rejected: number
}

interface WorksChartLabels {
  submitted: string
  published: string
  rejected: string
  points: string
  empty: string
  chartAria: string
}

interface ChartPoint {
  index: number
  x: number
  y: number
}

const props = defineProps<{
  title: string
  subtitle: string
  series: WorksSeriesPoint[]
  labels: WorksChartLabels
  locale: Locale
}>()

const chartWidth = 960
const chartHeight = 350
const padding = {
  top: 28,
  right: 28,
  bottom: 58,
  left: 62
} as const
const plotWidth = chartWidth - padding.left - padding.right
const plotHeight = chartHeight - padding.top - padding.bottom
const selectedIndex = ref<number | null>(null)

const legendItems = computed(() => [
  { key: 'submitted' as const, label: props.labels.submitted, color: '#38bdf8' },
  { key: 'published' as const, label: props.labels.published, color: '#10b981' },
  { key: 'rejected' as const, label: props.labels.rejected, color: '#f43f5e' }
])

const maximumValue = computed(() => {
  const maximum = Math.max(
    0,
    ...props.series.flatMap(item => [item.submitted, item.published, item.rejected])
  )

  if (maximum <= 4) return 4

  const roughStep = maximum / 4
  const magnitude = 10 ** Math.floor(Math.log10(roughStep))
  const step = Math.ceil(roughStep / magnitude) * magnitude

  return step * 4
})

const gridLines = computed(() => Array.from({ length: 5 }, (_, index) => {
  const ratio = index / 4

  return {
    y: padding.top + ratio * plotHeight,
    value: Math.round(maximumValue.value * (1 - ratio))
  }
}))

const seriesModels = computed(() => legendItems.value.map(item => {
  const points: ChartPoint[] = props.series.map((point, index) => ({
    index,
    x: xForIndex(index),
    y: yForValue(point[item.key])
  }))

  return {
    ...item,
    points,
    polyline: points.map(point => `${point.x},${point.y}`).join(' ')
  }
}))

const visibleLabelIndices = computed(() => {
  if (props.series.length <= 7) {
    return props.series.map((_, index) => index)
  }

  const step = Math.ceil((props.series.length - 1) / 6)
  const indices = props.series
    .map((_, index) => index)
    .filter(index => index % step === 0)
  const lastIndex = props.series.length - 1

  if (indices[indices.length - 1] !== lastIndex) indices.push(lastIndex)

  return indices
})

const selectedPoint = computed(() => (
  selectedIndex.value === null ? null : props.series[selectedIndex.value] ?? null
))

const tooltipItems = computed(() => {
  const point = selectedPoint.value
  if (!point) return []

  return legendItems.value.map(item => ({
    ...item,
    value: point[item.key]
  }))
})

const tooltipStyle = computed(() => {
  const index = selectedIndex.value
  if (index === null) return {}

  const x = xForIndex(index)
  const translate = x > chartWidth * 0.76
    ? '-92%'
    : x < chartWidth * 0.24
      ? '-8%'
      : '-50%'

  return {
    left: `${(x / chartWidth) * 100}%`,
    transform: `translateX(${translate})`
  }
})

function xForIndex(index: number): number {
  if (props.series.length <= 1) return padding.left + plotWidth / 2

  return padding.left + (index / (props.series.length - 1)) * plotWidth
}

function yForValue(value: number): number {
  return padding.top + (1 - value / maximumValue.value) * plotHeight
}

function hitZoneX(index: number): number {
  if (props.series.length <= 1 || index === 0) return padding.left

  const spacing = plotWidth / (props.series.length - 1)
  if (index === props.series.length - 1) return chartWidth - padding.right - spacing / 2

  return xForIndex(index) - spacing / 2
}

function hitZoneWidth(index: number): number {
  if (props.series.length <= 1) return plotWidth

  const spacing = plotWidth / (props.series.length - 1)
  return index === 0 || index === props.series.length - 1 ? spacing / 2 : spacing
}

function pointAriaLabel(index: number): string {
  const point = props.series[index]
  if (!point) return props.labels.chartAria

  return [
    point.label,
    `${props.labels.submitted}: ${formatNumber(point.submitted)}`,
    `${props.labels.published}: ${formatNumber(point.published)}`,
    `${props.labels.rejected}: ${formatNumber(point.rejected)}`
  ].join('. ')
}

function formatNumber(value: number): string {
  return new Intl.NumberFormat(props.locale === 'ar' ? 'ar-YE' : 'en-US').format(value)
}
</script>

<style scoped>
.ym-works-chart-card {
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
  padding: clamp(1rem, 2.5vw, 1.5rem);
}

.ym-works-chart-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.15rem;
}

.ym-works-chart-card__head h2 {
  color: var(--ym-text);
  font-size: 1.3rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-chart-card__head p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.35rem 0 0;
}

.ym-works-chart-card__head > span {
  flex: 0 0 auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
  padding: 0.45rem 0.75rem;
}

.ym-works-chart-scroll {
  overflow-x: auto;
  overscroll-behavior-inline: contain;
}

.ym-works-chart-stage {
  position: relative;
  min-width: 720px;
}

.ym-works-chart-svg {
  display: block;
  width: 100%;
  height: auto;
  color: var(--ym-muted);
}

.ym-works-chart-grid line {
  stroke: currentColor;
  stroke-dasharray: 5 8;
  stroke-opacity: 0.22;
}

.ym-works-chart-grid text,
.ym-works-chart-axis-labels text {
  fill: currentColor;
  font-size: 11px;
  font-weight: 800;
}

.ym-works-chart-hit-zones rect {
  cursor: crosshair;
  outline: none;
}

.ym-works-chart-hit-zones rect:focus {
  stroke: #a78bfa;
  stroke-width: 2;
  stroke-dasharray: 5 6;
}

.ym-works-chart-tooltip {
  position: absolute;
  z-index: 2;
  top: 0.75rem;
  display: grid;
  min-width: 190px;
  gap: 0.42rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: color-mix(in srgb, var(--ym-card-bg) 94%, #0f172a 6%);
  box-shadow: 0 18px 38px rgba(2, 6, 23, 0.28);
  color: var(--ym-text);
  padding: 0.75rem;
  pointer-events: none;
}

.ym-works-chart-tooltip strong {
  border-bottom: 1px solid var(--ym-soft-border);
  font-size: 12px;
  padding-bottom: 0.45rem;
}

.ym-works-chart-tooltip span {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 800;
}

.ym-works-chart-tooltip span i,
.ym-works-chart-legend i {
  width: 0.65rem;
  height: 0.65rem;
  flex: 0 0 auto;
  border-radius: 999px;
}

.ym-works-chart-tooltip span b {
  margin-inline-start: auto;
  color: var(--ym-text);
  font-size: 13px;
}

.ym-works-chart-legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.7rem 1.25rem;
  border-top: 1px solid var(--ym-soft-border);
  margin-top: 0.75rem;
  padding-top: 0.9rem;
}

.ym-works-chart-legend span {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-chart-empty {
  display: grid;
  min-height: 18rem;
  place-items: center;
  align-content: center;
  gap: 0.7rem;
  border: 1px dashed var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  text-align: center;
}

.ym-works-chart-empty span {
  display: grid;
  width: 3rem;
  height: 3rem;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 15%, transparent);
  color: #38bdf8;
  font-weight: 950;
}

.ym-works-chart-empty p {
  max-width: 28rem;
  font-size: 13px;
  font-weight: 850;
  line-height: 1.7;
  margin: 0;
}

@media (max-width: 700px) {
  .ym-works-chart-card__head {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-chart-card__head > span {
    align-self: flex-start;
  }
}
</style>
