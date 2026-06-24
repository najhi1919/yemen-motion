<template>
  <div ref="container" class="ym-particle-background" aria-hidden="true">
    <canvas ref="canvas" class="ym-particle-background__canvas" />
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useLoginAnimations } from '~/composables/useLoginAnimations'
import { useParticles } from '~/composables/useParticles'
import { useThreeScene } from '~/composables/useThreeScene'

const props = withDefaults(defineProps<{
  mouseX?: number
  mouseY?: number
  state?: 'idle' | 'hover' | 'success' | 'error' | 'loading'
}>(), {
  mouseX: 0,
  mouseY: 0,
  state: 'idle'
})

const canvas = ref<HTMLCanvasElement | null>(null)
const container = ref<HTMLElement | null>(null)
const { prefersReducedMotion } = useLoginAnimations()
const threeScene = useThreeScene()
let particles: ReturnType<typeof useParticles> | null = null
let observer: IntersectionObserver | null = null
let lazyTimer = 0

function start() {
  if (!canvas.value) return
  threeScene.create({
    canvas: canvas.value,
    reducedMotion: prefersReducedMotion(),
    onReady: ({ THREE, scene }) => {
      particles = useParticles({ THREE, scene, reducedMotion: prefersReducedMotion() })
      particles.setMouse(props.mouseX, props.mouseY, props.state === 'success' ? 'attract' : 'repel')
    },
    onFrame: (_delta, elapsed) => particles?.update(elapsed)
  })
}

watch([() => props.mouseX, () => props.mouseY, () => props.state], ([x, y, state]) => {
  particles?.setMouse(x, y, state === 'success' ? 'attract' : 'repel')
})

onMounted(() => {
  if (!container.value || prefersReducedMotion()) return
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting) {
      const requestIdle = window.requestIdleCallback || ((callback: IdleRequestCallback) => window.setTimeout(() => callback({ didTimeout: false, timeRemaining: () => 0 }), 1000))
      lazyTimer = requestIdle(start) as number
      observer?.disconnect()
    }
  })
  observer.observe(container.value)
})

onBeforeUnmount(() => {
  if (lazyTimer) window.clearTimeout(lazyTimer)
  observer?.disconnect()
  particles?.dispose()
  threeScene.destroy()
})
</script>
