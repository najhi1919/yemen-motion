<template>
  <div
    ref="root"
    class="ym-motion-logo"
    :class="`is-${state}`"
    role="img"
    aria-label="Yemen Motion logo"
    @mouseenter="setHover(true)"
    @mouseleave="setHover(false)"
  >
    <div ref="svgHost" class="ym-motion-logo__svg" v-html="svgMarkup" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { useInlineSvg } from '~/composables/useInlineSvg'
import { useLoginAnimations } from '~/composables/useLoginAnimations'
import { useSvgMorph } from '~/composables/useSvgMorph'

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

const emit = defineEmits<{ hover: [value: boolean] }>()

const root = ref<HTMLElement | null>(null)
const svgHost = ref<HTMLElement | null>(null)
const svgMarkup = ref('')
const effectsReady = ref(false)
const { loadSvg } = useInlineSvg()
const { logoHover, logoLeave, prefersReducedMotion } = useLoginAnimations()

function setHover(value: boolean) {
  emit('hover', value)
}

function getParts() {
  const svg = svgHost.value?.querySelector('svg') || null
  const paths = Array.from(svgHost.value?.querySelectorAll('path') || []) as SVGPathElement[]
  const groups = Array.from(svgHost.value?.querySelectorAll('g') || []) as SVGGElement[]
  const primaryPath = paths[0]
  const accent = groups[1] || paths[1] || primaryPath
  return { svg, primaryPath, accent }
}

async function runStateAnimation(state: MotionState) {
  if (!root.value) return
  if (!effectsReady.value && (state === 'idle' || state === 'hover')) return
  const { primaryPath, accent } = getParts()
  const morph = useSvgMorph(primaryPath)

  if (state === 'hover') {
    await logoHover(root.value)
    return
  }

  if (state === 'loading') {
    const { gsap } = await import('gsap')
    gsap.to(root.value, { rotate: 360, scale: 1.03, duration: prefersReducedMotion() ? 0.2 : 1.8, repeat: -1, ease: 'none' })
    return
  }

  if (state === 'success') {
    const { gsap } = await import('gsap')
    morph.morphToSuccess()
    gsap.to(root.value, { scale: 1.16, duration: prefersReducedMotion() ? 0.12 : 0.26, yoyo: true, repeat: 1, ease: 'power2.out' })
    if (accent) gsap.to(accent, { fill: '#22c55e', duration: 0.24, ease: 'power2.out' })
    return
  }

  if (state === 'error') {
    const { gsap } = await import('gsap')
    morph.morphToError()
    gsap.fromTo(root.value, { x: -8 }, { x: 8, duration: prefersReducedMotion() ? 0.08 : 0.07, repeat: prefersReducedMotion() ? 0 : 6, yoyo: true, ease: 'power1.inOut' })
    if (accent) gsap.to(accent, { fill: '#ef4444', duration: 0.16, yoyo: true, repeat: 3 })
    window.setTimeout(() => morph.morphToIdle(), prefersReducedMotion() ? 140 : 720)
    return
  }

  await logoLeave(root.value)
  if (accent) {
    const { gsap } = await import('gsap')
    gsap.to(accent, { fill: '#c60909', duration: 0.3 })
  }
}

watch(() => props.state, runStateAnimation)
watch([() => props.mouseX, () => props.mouseY], ([x, y]) => {
  if (!root.value || prefersReducedMotion()) return
  if (!effectsReady.value) return
  root.value.style.transform = `translate3d(${x * 10}px, ${y * 8}px, 0) rotateX(${-y * 5}deg) rotateY(${x * 7}deg)`
})

onMounted(async () => {
  svgMarkup.value = await loadSvg('/logo.svg')
  window.setTimeout(() => { effectsReady.value = true }, 1000)
})
</script>
