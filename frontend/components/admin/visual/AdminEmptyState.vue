<template>
  <div class="ym-admin-empty" :class="`is-${tone}`" :role="tone === 'error' ? 'alert' : 'status'">
    <span aria-hidden="true">{{ icon }}</span>
    <strong v-if="value !== null && value !== undefined" class="ym-admin-empty__value">{{ value }}</strong>
    <h3>{{ title }}</h3>
    <p>{{ description }}</p>
    <button v-if="actionLabel" type="button" class="ym-admin-button" @click="$emit('action')">{{ actionLabel }}</button>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  icon: string
  title: string
  description: string
  actionLabel?: string
  tone?: 'neutral' | 'error' | 'forbidden'
  value?: string | null
}>(), { actionLabel: '', tone: 'neutral', value: null })
defineEmits<{ action: [] }>()
</script>

<style scoped>
.ym-admin-empty{display:grid;min-height:180px;place-items:center;align-content:center;gap:7px;padding:24px;text-align:center}.ym-admin-empty>span{display:grid;width:44px;height:44px;place-items:center;border-radius:14px;color:var(--ym-admin-muted);background:var(--ym-admin-surface-soft);font-size:20px}.ym-admin-empty__value{color:var(--ym-admin-text);font-size:28px;font-weight:950;line-height:1;font-variant-numeric:tabular-nums}.ym-admin-empty h3{margin:0;color:var(--ym-admin-text);font-size:17px;font-weight:900}.ym-admin-empty p{max-width:520px;margin:0;color:var(--ym-admin-muted);font-size:13px;line-height:1.65}.ym-admin-empty.is-error>span,.ym-admin-empty.is-forbidden>span{color:var(--ym-admin-danger);background:color-mix(in srgb,var(--ym-admin-danger) 10%,transparent)}
</style>
