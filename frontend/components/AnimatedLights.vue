<template>
  <div class="ym-animated-lights" :class="[`is-${state}`, { 'is-ready': ready }]" aria-hidden="true">
    <span class="ym-animated-lights__beam ym-animated-lights__beam--one" :style="beamStyle(0.14)" />
    <span class="ym-animated-lights__beam ym-animated-lights__beam--two" :style="beamStyle(-0.1)" />
    <span class="ym-animated-lights__beam ym-animated-lights__beam--three" :style="beamStyle(0.08)" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'

const props = withDefaults(defineProps<{
  mouseX?: number
  mouseY?: number
  state?: 'idle' | 'hover' | 'success' | 'error' | 'loading'
}>(), {
  mouseX: 0,
  mouseY: 0,
  state: 'idle'
})

const ready = ref(false)

onMounted(() => {
  window.setTimeout(() => { ready.value = true }, 1000)
})

function beamStyle(depth: number) {
  return {
    transform: `translate3d(${props.mouseX * depth * 100}px, ${props.mouseY * depth * 100}px, 0)`
  }
}
</script>
