<template>
  <header class="ym-admin-hero ym-admin-surface">
    <div class="ym-admin-hero__glow" aria-hidden="true" />
    <nav :aria-label="breadcrumbLabel">
      <template v-for="(item, index) in breadcrumbs" :key="`${item}-${index}`">
        <span>{{ item }}</span>
        <i v-if="index < breadcrumbs.length - 1" aria-hidden="true">/</i>
      </template>
    </nav>
    <div class="ym-admin-hero__body">
      <span class="ym-admin-hero__icon" aria-hidden="true"><slot name="icon">◇</slot></span>
      <div>
        <div class="ym-admin-hero__eyebrow">
          <span>{{ eyebrow }}</span>
          <b>{{ badge }}</b>
        </div>
        <h1>{{ title }}</h1>
        <p>{{ description }}</p>
      </div>
      <div v-if="$slots.actions" class="ym-admin-hero__actions"><slot name="actions" /></div>
    </div>
  </header>
</template>

<script setup lang="ts">
defineProps<{
  breadcrumbs: string[]
  breadcrumbLabel: string
  eyebrow: string
  badge: string
  title: string
  description: string
}>()
</script>

<style scoped>
.ym-admin-hero{position:relative;display:grid;gap:8px;overflow:hidden;padding:16px 20px;border-color:color-mix(in srgb,var(--ym-admin-section-accent) 20%,var(--ym-admin-border-strong))}.ym-admin-hero::before{content:"";position:absolute;inset-block-start:0;inset-inline:18px;height:2px;background:linear-gradient(90deg,transparent,var(--ym-admin-section-accent),color-mix(in srgb,var(--ym-admin-section-highlight,#67e8f9) 38%,var(--ym-admin-section-accent-secondary)),transparent)}.ym-admin-hero__glow{position:absolute;inset-block-start:-110px;inset-inline-start:2%;width:380px;height:200px;border-radius:50%;background:radial-gradient(circle,color-mix(in srgb,var(--ym-admin-section-accent) 16%,transparent),transparent 68%);pointer-events:none}.ym-admin-hero nav{position:relative;display:flex;flex-wrap:wrap;align-items:center;gap:7px;color:color-mix(in srgb,var(--ym-admin-muted) 88%,var(--ym-admin-text) 12%);font-size:12.5px;font-weight:800}.ym-admin-hero nav i{font-style:normal;opacity:.55}.ym-admin-hero__body{position:relative;display:grid;grid-template-columns:50px minmax(0,1fr) auto;align-items:center;gap:15px}.ym-admin-hero__icon{display:grid;width:50px;height:50px;place-items:center;border:1px solid color-mix(in srgb,var(--ym-admin-section-accent) 38%,transparent);border-radius:15px;color:color-mix(in srgb,var(--ym-admin-section-accent) 82%,var(--ym-admin-section-highlight,#67e8f9) 18%);background:linear-gradient(145deg,color-mix(in srgb,var(--ym-admin-section-accent) 15%,transparent),color-mix(in srgb,var(--ym-admin-section-accent-secondary) 8%,transparent));box-shadow:0 9px 25px color-mix(in srgb,var(--ym-admin-section-accent-strong) 13%,transparent);font-size:24px}.ym-admin-hero__icon :deep(svg){width:28px;height:28px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.9}.ym-admin-hero__eyebrow{display:flex;flex-wrap:wrap;align-items:center;gap:8px;color:var(--ym-admin-section-accent);font-size:12px;font-weight:900}.ym-admin-hero__eyebrow b{border:1px solid color-mix(in srgb,var(--ym-admin-section-accent) 30%,transparent);border-radius:999px;padding:4px 9px;color:color-mix(in srgb,var(--ym-admin-section-accent) 78%,var(--ym-admin-text) 22%);background:var(--ym-admin-section-accent-soft);font-size:11.5px}.ym-admin-hero h1{margin:2px 0;color:var(--ym-admin-text);background:linear-gradient(100deg,var(--ym-admin-text) 12%,var(--ym-admin-section-accent) 66%,var(--ym-admin-section-accent-secondary));background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:clamp(30px,3vw,42px);font-weight:950;line-height:1.22;padding-block:.08em}.ym-admin-hero p{max-width:800px;margin:0;color:color-mix(in srgb,var(--ym-admin-muted) 88%,var(--ym-admin-text) 12%);font-size:14px;line-height:1.6}.ym-admin-hero__actions{display:flex;align-items:center;gap:8px}@media(max-width:700px){.ym-admin-hero{padding:15px}.ym-admin-hero__body{grid-template-columns:46px minmax(0,1fr);gap:12px}.ym-admin-hero__icon{width:46px;height:46px}.ym-admin-hero__actions{grid-column:1/-1}.ym-admin-hero h1{font-size:29px}.ym-admin-hero p{font-size:13.5px}}@media(max-width:420px){.ym-admin-hero__body{grid-template-columns:1fr}.ym-admin-hero__icon{width:44px;height:44px}}
</style>
