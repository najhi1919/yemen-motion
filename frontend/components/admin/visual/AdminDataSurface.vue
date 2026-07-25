<template>
  <section class="ym-admin-data-surface ym-admin-surface" :aria-busy="busy">
    <header>
      <div><span>{{ eyebrow }}</span><h2>{{ title }}</h2><p>{{ description }}</p></div>
      <div v-if="$slots.controls"><slot name="controls" /></div>
    </header>
    <slot />
  </section>
</template>

<script setup lang="ts">
defineProps<{ eyebrow: string; title: string; description: string; busy?: boolean }>()
</script>

<style scoped>
.ym-admin-data-surface{display:grid;gap:16px;border-color:color-mix(in srgb,var(--ym-admin-section-accent) 22%,var(--ym-admin-border-strong));padding:16px}.ym-admin-data-surface>header{display:flex;align-items:center;justify-content:space-between;gap:16px;border-bottom:1px solid color-mix(in srgb,var(--ym-admin-section-accent) 20%,var(--ym-admin-border));padding:2px 2px 14px}.ym-admin-data-surface>header>div:first-child{min-width:0}.ym-admin-data-surface>header span{color:var(--ym-admin-section-accent);font-size:12px;font-weight:900}.ym-admin-data-surface h2{margin:3px 0;color:var(--ym-admin-text);font-size:20px;font-weight:950;line-height:1.35}.ym-admin-data-surface p{margin:0;color:color-mix(in srgb,var(--ym-admin-muted) 88%,var(--ym-admin-text) 12%);font-size:13.5px;line-height:1.6}@media(max-width:760px){.ym-admin-data-surface{padding:14px}.ym-admin-data-surface>header{align-items:stretch;flex-direction:column}}
</style>
