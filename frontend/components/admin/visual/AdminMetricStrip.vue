<template>
  <section class="ym-admin-metrics" :aria-label="ariaLabel" :aria-busy="loading || updating">
    <AdminFloatingOverlay
      v-for="item in items"
      :key="item.key"
      :label="item.label"
      :description="item.description"
      :aria-label="`${item.label}: ${format(item.value)}. ${item.description}`"
    >
      <template #trigger>
        <span class="ym-admin-metric" :class="`is-${item.tone}`">
          <span aria-hidden="true">{{ item.icon }}</span>
          <div><small>{{ item.label }}</small><strong>{{ loading ? '—' : format(item.value) }}</strong></div>
          <i v-if="updating" aria-hidden="true" />
        </span>
      </template>
    </AdminFloatingOverlay>
  </section>
</template>

<script setup lang="ts">
import AdminFloatingOverlay from './AdminFloatingOverlay.vue'
import { formatYmNumber, type YmLocale } from '~/utils/ymFormatting'

interface MetricItem {
  key: string
  label: string
  description: string
  value: number
  tone: 'violet' | 'cyan' | 'indigo' | 'amber' | 'emerald' | 'neutral' | 'rose' | 'magenta'
  icon: string
}

const props = defineProps<{
  items: MetricItem[]
  locale: YmLocale
  ariaLabel: string
  loading?: boolean
  updating?: boolean
}>()

const format = (value: number): string => formatYmNumber(value, props.locale)
</script>

<style scoped>
.ym-admin-metrics{display:grid;grid-template-columns:repeat(8,minmax(108px,1fr));gap:9px;min-width:0}.ym-admin-metrics :deep(.ym-admin-overlay){display:flex;height:100%}.ym-admin-metric{--metric:var(--ym-admin-section-accent-secondary,#8b5cf6);position:relative;display:grid;grid-template-columns:35px minmax(0,1fr);align-items:center;gap:9px;min-height:70px;overflow:hidden;border:1px solid color-mix(in srgb,var(--metric) 34%,var(--ym-admin-border));border-radius:14px;padding:9px 11px;background:linear-gradient(145deg,color-mix(in srgb,var(--metric) 10%,var(--ym-admin-surface-soft)),var(--ym-admin-surface-soft));box-shadow:inset 0 1px rgba(255,255,255,.08),0 8px 22px rgba(2,6,23,.04);transition:transform var(--ym-admin-motion-fast) ease,border-color var(--ym-admin-motion-fast) ease,box-shadow var(--ym-admin-motion-fast) ease}.ym-admin-metric:hover{transform:translateY(-1px);border-color:color-mix(in srgb,var(--metric) 58%,var(--ym-admin-border));box-shadow:0 10px 24px color-mix(in srgb,var(--metric) 10%,transparent)}.ym-admin-metric>span{display:grid;width:34px;height:34px;place-items:center;border-radius:11px;color:var(--metric);background:color-mix(in srgb,var(--metric) 14%,transparent);font-size:16px}.ym-admin-metric div{display:flex;min-width:0;flex-direction:column-reverse;gap:2px}.ym-admin-metric small{overflow:hidden;color:color-mix(in srgb,var(--ym-admin-muted) 86%,var(--ym-admin-text) 14%);font-size:12px;font-weight:800;line-height:1.3;text-overflow:ellipsis;white-space:nowrap}.ym-admin-metric strong{color:var(--ym-admin-text);font-size:24px;font-weight:950;line-height:1;font-variant-numeric:tabular-nums}.ym-admin-metric>i{position:absolute;inset:0;background:linear-gradient(100deg,transparent,rgba(255,255,255,.08),transparent);opacity:.7}.ym-admin-metric.is-cyan{--metric:#22d3ee}.ym-admin-metric.is-indigo{--metric:#6366f1}.ym-admin-metric.is-amber{--metric:#f59e0b}.ym-admin-metric.is-emerald{--metric:#10b981}.ym-admin-metric.is-neutral{--metric:#94a3b8}.ym-admin-metric.is-rose{--metric:#f43f5e}.ym-admin-metric.is-magenta{--metric:#ec4899}@media(max-width:1280px){.ym-admin-metrics{grid-template-columns:repeat(4,minmax(0,1fr))}}@media(max-width:640px){.ym-admin-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.ym-admin-metric{min-height:64px}.ym-admin-metrics :deep(.ym-admin-overlay:last-child:nth-child(odd)){grid-column:1/-1}}@media(prefers-reduced-motion:reduce){.ym-admin-metric{transition:none}.ym-admin-metric:hover{transform:none}}
.ym-admin-metric {
  border-color: color-mix(in srgb, var(--metric) 24%, var(--ym-admin-border));
  background:
    linear-gradient(145deg, color-mix(in srgb, var(--metric) 7%, var(--ym-admin-surface-soft)), var(--ym-admin-surface-soft));
}

.ym-admin-metric > span {
  background: color-mix(in srgb, var(--metric) 11%, transparent);
}

.ym-admin-metric:hover {
  border-color: color-mix(in srgb, var(--metric) 44%, var(--ym-admin-border));
}
</style>
