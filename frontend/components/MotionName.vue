<template>
  <div
    ref="root"
    class="ym-motion-name"
    :class="`is-${state}`"
    role="img"
    aria-label="Yemen Motion brand name"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
  >
    <div ref="svgHost" class="ym-motion-name__svg" v-html="svgMarkup" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useInlineSvg } from '~/composables/useInlineSvg'
import { useLoginAnimations } from '~/composables/useLoginAnimations'

type MotionState = 'idle' | 'hover' | 'success' | 'error' | 'loading'

const props = withDefaults(defineProps<{
  state?: MotionState
  mouseX?: number
  mouseY?: number
}>(), {
  state: 'idle',
  mouseX: 0,
  mouseY: 0
})

const root = ref<HTMLElement | null>(null)
const svgHost = ref<HTMLElement | null>(null)
const svgMarkup = ref('')
const hovered = ref(false)
const effectsReady = ref(false)
const { loadSvg } = useInlineSvg()
const { prefersReducedMotion } = useLoginAnimations()

function letterTargets() {
  const groups = Array.from(svgHost.value?.querySelectorAll('g') || []) as SVGGElement[]
  return groups.length > 1 ? groups : Array.from(svgHost.value?.querySelectorAll('path') || []) as SVGPathElement[]
}

async function runStateAnimation(state: MotionState) {
  const letters = letterTargets()
  if (!letters.length) return
  if (!effectsReady.value && (state === 'idle' || state === 'hover')) return
  const { gsap } = await import('gsap')
  gsap.killTweensOf(letters)

  if (state === 'success') {
    gsap.fromTo(letters, { y: 12, opacity: 0.6 }, { y: 0, opacity: 1, duration: prefersReducedMotion() ? 0.12 : 0.52, stagger: 0.025, ease: 'back.out(1.6)' })
    gsap.to(letters, { fill: '#22c55e', duration: 0.32, stagger: 0.015 })
    return
  }

  if (state === 'error') {
    gsap.fromTo(letters, { x: -4 }, { x: 4, duration: prefersReducedMotion() ? 0.08 : 0.065, repeat: prefersReducedMotion() ? 0 : 4, yoyo: true, stagger: 0.01 })
    gsap.to(letters, { fill: '#ef4444', duration: 0.18, yoyo: true, repeat: 2 })
    return
  }

  if (state === 'hover' || hovered.value) {
    gsap.to(letters, { y: (index) => (index % 2 === 0 ? -5 : 4), rotate: (index) => (index % 3) - 1, duration: 0.32, stagger: 0.01, ease: 'power2.out' })
    return
  }

  gsap.to(letters, { x: 0, y: 0, rotate: 0, fill: '#be0001', duration: 0.32, ease: 'power2.out' })
}

watch(() => props.state, runStateAnimation)
watch(hovered, () => runStateAnimation(hovered.value ? 'hover' : props.state))
watch([() => props.mouseX, () => props.mouseY], ([x, y]) => {
  if (!root.value || prefersReducedMotion()) return
  if (!effectsReady.value) return
  root.value.style.transform = `perspective(900px) translate3d(${x * 14}px, ${y * 8}px, 0) rotateX(${-y * 6}deg) rotateY(${x * 10}deg)`
})

onMounted(async () => {
  svgMarkup.value = await loadSvg('/name.svg')
  window.setTimeout(() => { effectsReady.value = true }, 1000)
})
</script>
