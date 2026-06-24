<template>
  <section
    ref="card"
    class="ym-glass-card"
    :class="[`is-${state}`, { 'is-tilting': isTilting }]"
    aria-labelledby="login-title"
    @mousemove="handleMove"
    @mouseleave="resetTilt"
  >
    <div class="ym-glass-card__reflection" />
    <div class="ym-glass-card__border" />
    <div class="ym-glass-card__content">
      <slot />
    </div>
  </section>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useLoginAnimations } from '~/composables/useLoginAnimations'

type MotionState = 'idle' | 'hover' | 'success' | 'error' | 'loading'

const props = withDefaults(defineProps<{ state?: MotionState }>(), { state: 'idle' })
const card = ref<HTMLElement | null>(null)
const isTilting = ref(false)
const { errorShake, prefersReducedMotion } = useLoginAnimations()

function handleMove(event: MouseEvent) {
  if (!card.value || prefersReducedMotion()) return
  const rect = card.value.getBoundingClientRect()
  const x = (event.clientX - rect.left) / rect.width - 0.5
  const y = (event.clientY - rect.top) / rect.height - 0.5
  isTilting.value = true
  card.value.style.setProperty('--tilt-x', `${-y * 10}deg`)
  card.value.style.setProperty('--tilt-y', `${x * 12}deg`)
  card.value.style.setProperty('--shine-x', `${(x + 0.5) * 100}%`)
  card.value.style.setProperty('--shine-y', `${(y + 0.5) * 100}%`)
}

function resetTilt() {
  if (!card.value) return
  isTilting.value = false
  card.value.style.setProperty('--tilt-x', '0deg')
  card.value.style.setProperty('--tilt-y', '0deg')
  card.value.style.setProperty('--shine-x', '50%')
  card.value.style.setProperty('--shine-y', '50%')
}

watch(() => props.state, (state) => {
  if (state === 'error') errorShake(card.value)
})
</script>
