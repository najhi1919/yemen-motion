<template>
  <WorksIndexFloatingOverlay
    interactive
    :label="label"
    :description="hint"
    :close-label="closeLabel"
    :trigger-aria-label="label"
    trigger-class="ym-info-popover__trigger"
  >
    <template #trigger>
      <slot name="trigger" />
    </template>
    <slot />
  </WorksIndexFloatingOverlay>
</template>

<script setup lang="ts">
import WorksIndexFloatingOverlay from './WorksIndexFloatingOverlay.vue'

defineProps<{
  label: string
  hint?: string
  closeLabel: string
}>()
</script>

<style scoped>
:deep(.ym-floating-overlay) { position: relative; display: inline-flex; min-width: 0; }
:deep(.ym-info-popover__trigger) { width: 100%; min-width: 0; }
</style>
