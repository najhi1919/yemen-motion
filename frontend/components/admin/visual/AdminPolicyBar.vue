<template>
  <section class="ym-admin-policy-bar ym-admin-surface" :aria-label="ariaLabel">
    <AdminFloatingOverlay
      v-for="item in items"
      :key="item.key"
      :label="item.title"
      :aria-label="`${item.title}: ${item.state}`"
      :close-label="closeLabel"
      interactive
    >
      <template #trigger>
        <span class="ym-admin-policy" :class="`is-${item.tone}`">
          <span aria-hidden="true">{{ item.icon }}</span>
          <div><small>{{ item.title }}</small><strong>{{ item.state }}</strong></div>
          <b aria-hidden="true">›</b>
        </span>
      </template>
      <p>{{ item.description }}</p>
      <small v-if="item.meta">{{ item.meta }}</small>
    </AdminFloatingOverlay>
  </section>
</template>

<script setup lang="ts">
import AdminFloatingOverlay from './AdminFloatingOverlay.vue'

interface PolicyItem {
  key: string
  title: string
  state: string
  description: string
  meta?: string
  icon: string
  tone: 'info' | 'success' | 'warning' | 'neutral'
}

defineProps<{
  items: PolicyItem[]
  ariaLabel: string
  closeLabel: string
}>()
</script>

<style scoped>
.ym-admin-policy-bar{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;padding:11px}.ym-admin-policy-bar :deep(.ym-admin-overlay){display:flex;height:100%}.ym-admin-policy{--policy:var(--ym-admin-section-accent,#6366f1);display:grid;grid-template-columns:38px minmax(0,1fr) 18px;align-items:center;gap:10px;min-height:68px;border:1px solid color-mix(in srgb,var(--policy) 21%,var(--ym-admin-border));border-radius:14px;padding:10px 12px;background:linear-gradient(145deg,color-mix(in srgb,var(--policy) 6%,var(--ym-admin-surface-soft)),var(--ym-admin-surface-soft));box-shadow:inset 0 1px rgba(255,255,255,.07);transition:border-color var(--ym-admin-motion-fast) ease,transform var(--ym-admin-motion-fast) ease,box-shadow var(--ym-admin-motion-fast) ease}.ym-admin-policy:hover{transform:translateY(-1px);border-color:color-mix(in srgb,var(--policy) 42%,var(--ym-admin-border));box-shadow:0 10px 24px color-mix(in srgb,var(--policy) 7%,transparent)}.ym-admin-policy>span{display:grid;width:36px;height:36px;place-items:center;border-radius:11px;color:var(--policy);background:color-mix(in srgb,var(--policy) 11%,transparent);font-size:17px}.ym-admin-policy div{display:grid;min-width:0;gap:2px}.ym-admin-policy small{color:color-mix(in srgb,var(--ym-admin-muted) 88%,var(--ym-admin-text) 12%);font-size:12px;font-weight:800}.ym-admin-policy strong{overflow:hidden;color:var(--ym-admin-text);font-size:13.5px;font-weight:900;line-height:1.4;text-overflow:ellipsis;white-space:nowrap}.ym-admin-policy>b{color:var(--policy);font-size:21px}.ym-admin-policy.is-success{--policy:#10b981}.ym-admin-policy.is-warning{--policy:#f59e0b}.ym-admin-policy.is-neutral{--policy:#94a3b8}.ym-admin-policy-bar :deep(.ym-admin-overlay__surface p){margin:0;color:#fff}.ym-admin-policy-bar :deep(.ym-admin-overlay__surface small){display:block;margin-top:8px;color:rgba(226,232,240,.78)}@media(max-width:900px){.ym-admin-policy-bar{grid-template-columns:1fr}.ym-admin-policy{min-height:62px}}@media(prefers-reduced-motion:reduce){.ym-admin-policy{transition:none}.ym-admin-policy:hover{transform:none}}
</style>
