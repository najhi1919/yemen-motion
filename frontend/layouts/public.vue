<script setup lang="ts">
import PublicSiteHeader from '~/components/public/PublicSiteHeader.vue'
</script>

<template>
  <div class="public-site-shell min-h-dvh">
    <PublicSiteHeader />
    <slot />
  </div>
</template>

<style>
.public-site-shell {
  --ym-page-bg: #f7f8fa;
  --ym-surface: #ffffff;
  --ym-surface-soft: #fcfcfc;
  --ym-charcoal: #1a1a1d;
  --ym-charcoal-soft: #25262b;
  --ym-feature-dark: #101114;
  --ym-red: #e21d1d;
  --ym-red-hover: #c91818;
  --ym-text: #151515;
  --ym-muted: #6b7280;
  --ym-border: #e5e7eb;
  --ym-border-strong: #d7d9de;
  --ym-red-soft: rgba(226, 29, 29, 0.08);
  --ym-shadow: 0 10px 28px rgba(17, 17, 17, 0.06);
  min-height: 100dvh;
  background: var(--ym-page-bg);
  color: var(--ym-text);
  font-family: "IBM Plex Sans Arabic", "IBM Plex Sans", sans-serif;
}
</style>
