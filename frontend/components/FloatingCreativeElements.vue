<template>
  <div
    class="ym-floating-creatives"
    :class="{ 'is-ready': ready, 'is-weak-device': weakDevice }"
    aria-hidden="true"
  >
    <div
      v-for="element in visibleElements"
      :key="element.id"
      :ref="(node) => setItemRef(element.id, node as HTMLElement | null)"
      class="ym-creative-item"
      :class="[`ym-creative-item--${element.kind}`, `ym-creative-item--${element.side}`]"
      :style="itemStyle(element)"
    >
      <svg v-if="element.kind === 'stylus'" viewBox="0 0 120 220" class="ym-creative-svg">
        <path d="M62 12 86 28 35 197 13 211 19 185Z" fill="url(#penBody)" />
        <path d="M36 196 19 185 13 211Z" fill="#dbeafe" />
        <path d="M75 60 91 69" stroke="#38bdf8" stroke-width="8" stroke-linecap="round" />
        <defs><linearGradient id="penBody" x1="18" x2="91" y1="202" y2="20"><stop stop-color="#111827"/><stop offset=".55" stop-color="#475569"/><stop offset="1" stop-color="#fb7185"/></linearGradient></defs>
      </svg>

      <svg v-else-if="element.kind === 'pen-tool'" viewBox="0 0 180 130" class="ym-creative-svg">
        <rect x="14" y="12" width="152" height="106" rx="24" class="tile" />
        <path d="M88 34 115 86 89 102 61 86Z" fill="#f8fafc" opacity=".9" />
        <path d="M88 34v43" stroke="#0f172a" stroke-width="5" stroke-linecap="round" />
        <circle cx="88" cy="77" r="7" fill="#fb7185" />
        <path d="M39 66C58 24 124 24 141 66" fill="none" stroke="#fb7185" stroke-width="3" />
        <path d="M39 66h-17M141 66h17" stroke="#93c5fd" stroke-width="3" />
        <circle cx="22" cy="66" r="5" fill="#fb7185"/><circle cx="158" cy="66" r="5" fill="#38bdf8"/>
      </svg>

      <svg v-else-if="element.kind === 'swatches'" viewBox="0 0 160 130" class="ym-creative-svg">
        <g transform="translate(16 16) rotate(-10 64 50)">
          <rect v-for="(color, index) in swatches" :key="color" :x="index * 17" :y="index * 3" width="38" height="92" rx="9" :fill="color" opacity=".86" />
          <circle cx="24" cy="83" r="7" fill="#0f172a" />
        </g>
      </svg>

      <svg v-else-if="element.kind === 'layers'" viewBox="0 0 150 130" class="ym-creative-svg">
        <rect x="28" y="24" width="88" height="52" rx="12" fill="#fb7185" opacity=".72" />
        <rect x="38" y="42" width="88" height="52" rx="12" fill="#a855f7" opacity=".78" />
        <rect x="48" y="60" width="88" height="52" rx="12" fill="#38bdf8" opacity=".84" />
      </svg>

      <svg v-else-if="element.kind === 'brush'" viewBox="0 0 180 120" class="ym-creative-svg">
        <path d="M34 86C44 101 70 103 82 82 70 86 58 77 50 68c-8 7-16 11-16 18Z" fill="#ef4444" />
        <path d="M74 71 150 25" stroke="#64748b" stroke-width="12" stroke-linecap="round" />
        <path d="M68 78 145 31" stroke="#f8fafc" stroke-width="5" stroke-linecap="round" opacity=".5" />
        <path d="M27 95c16 10 37 11 56-2" fill="none" stroke="#fb7185" stroke-width="4" opacity=".9" />
      </svg>

      <svg v-else-if="element.kind === 'palette'" viewBox="0 0 150 130" class="ym-creative-svg">
        <path d="M78 16c39 0 62 23 58 53-3 20-18 35-36 35h-9c-7 0-9 10-18 10-33 0-60-21-60-49 0-29 27-49 65-49Z" fill="#172033" stroke="#475569" stroke-width="3" />
        <circle cx="47" cy="55" r="11" fill="#ef4444"/><circle cx="74" cy="42" r="10" fill="#a855f7"/><circle cx="101" cy="58" r="10" fill="#38bdf8"/><circle cx="66" cy="82" r="11" fill="#22c55e"/>
        <ellipse cx="98" cy="86" rx="14" ry="9" fill="#050816" />
      </svg>

      <svg v-else-if="element.kind === 'eyedropper'" viewBox="0 0 130 150" class="ym-creative-svg">
        <path d="M81 21c9-9 24 6 15 15L74 58 59 43Z" fill="#64748b" />
        <path d="M61 51 31 98c-5 8-2 19 7 23 8 4 18 0 22-8l23-51Z" fill="#334155" />
        <path d="M39 118c-11 9-19 15-24 10-4-5 4-13 14-23Z" fill="#38bdf8" />
        <path d="M29 132c9 5 17-2 19-10" stroke="#38bdf8" stroke-width="4" stroke-linecap="round" />
      </svg>

      <svg v-else-if="element.kind === 'crop'" viewBox="0 0 130 130" class="ym-creative-svg">
        <rect x="20" y="22" width="90" height="86" rx="18" class="tile" />
        <path d="M42 34v55h55M35 45h55v55" fill="none" stroke="#fb7185" stroke-width="6" stroke-linecap="round" />
        <path d="M42 73h43M65 45v43" stroke="#93c5fd" stroke-width="3" stroke-dasharray="8 8" />
      </svg>

      <svg v-else-if="element.kind === 'film'" viewBox="0 0 240 120" class="ym-creative-svg ym-creative-svg--wide">
        <path d="M16 27c56 20 93-23 153-2 25 9 42 10 55 6v62c-46 12-82-15-131-3-33 8-56 15-77 4Z" fill="#0f172a" stroke="#38bdf8" stroke-width="3" opacity=".92" />
        <g fill="#334155"><rect v-for="index in 10" :key="index" :x="20 + index * 19" y="35" width="9" height="10" rx="2"/><rect v-for="index in 10" :key="`b-${index}`" :x="20 + index * 19" y="78" width="9" height="10" rx="2"/></g>
        <rect x="53" y="47" width="38" height="28" rx="5" fill="#1e3a8a"/><rect x="99" y="43" width="44" height="34" rx="5" fill="#7f1d1d"/><rect x="151" y="48" width="40" height="30" rx="5" fill="#1e3a8a"/>
      </svg>

      <svg v-else-if="element.kind === 'timeline'" viewBox="0 0 190 135" class="ym-creative-svg">
        <rect x="12" y="16" width="166" height="104" rx="22" class="tile" />
        <path d="m79 43 31 20-31 20Z" fill="#bfdbfe" />
        <path d="M34 92h120M34 104h98" stroke="#334155" stroke-width="8" stroke-linecap="round" />
        <path d="M34 92h74M67 104h56" stroke="#ef4444" stroke-width="5" stroke-linecap="round" />
        <circle cx="93" cy="92" r="6" fill="#fb7185" />
      </svg>

      <svg v-else-if="element.kind === 'clapper'" viewBox="0 0 170 145" class="ym-creative-svg">
        <path d="M35 45h105v78H35z" fill="#101827" stroke="#475569" stroke-width="3" />
        <path d="M28 32 134 15l9 31L37 63Z" fill="#1f2937" stroke="#64748b" stroke-width="3" />
        <path d="M39 30 55 49M72 24 88 43M105 18l16 19" stroke="#f8fafc" stroke-width="8" />
        <path d="M49 78h76M49 94h44M104 94h21" stroke="#ef4444" stroke-width="4" stroke-linecap="round" />
      </svg>

      <svg v-else-if="element.kind === 'aperture'" viewBox="0 0 140 130" class="ym-creative-svg">
        <rect x="17" y="13" width="106" height="104" rx="24" class="tile" />
        <circle cx="70" cy="65" r="37" fill="#0f172a" stroke="#38bdf8" stroke-width="3" />
        <path v-for="index in 6" :key="index" :d="aperturePath()" :transform="`rotate(${index * 60} 70 65)`" fill="#38bdf8" opacity=".75" />
        <circle cx="70" cy="65" r="13" fill="#101827" />
      </svg>

      <svg v-else-if="element.kind === 'fx'" viewBox="0 0 130 110" class="ym-creative-svg">
        <rect x="18" y="16" width="94" height="78" rx="20" class="tile" />
        <path d="M37 72c15-35 28-33 45 0M43 49h44" stroke="#fb7185" stroke-width="7" stroke-linecap="round" fill="none" />
        <path d="M81 73 103 43M101 73 82 43" stroke="#38bdf8" stroke-width="6" stroke-linecap="round" />
      </svg>

      <svg v-else-if="element.kind === 'graph'" viewBox="0 0 140 120" class="ym-creative-svg">
        <rect x="18" y="12" width="104" height="96" rx="20" class="tile" />
        <path d="M39 84C54 44 75 94 101 35" fill="none" stroke="#93c5fd" stroke-width="5" />
        <path d="M38 33v54h69" stroke="#334155" stroke-width="3" />
        <circle cx="39" cy="84" r="5" fill="#fb7185"/><circle cx="69" cy="66" r="5" fill="#a855f7"/><circle cx="101" cy="35" r="5" fill="#38bdf8"/>
      </svg>

      <span v-else class="ym-creative-bubble" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

type CreativeKind = 'stylus' | 'pen-tool' | 'swatches' | 'layers' | 'brush' | 'palette' | 'eyedropper' | 'crop' | 'film' | 'timeline' | 'clapper' | 'aperture' | 'fx' | 'graph' | 'bubble'

interface CreativeElement {
  id: string
  kind: CreativeKind
  side: 'left' | 'right' | 'ambient'
  x: number
  y: number
  size: number
  depth: number
  rotate: number
  opacity: number
  blur?: number
  mobile?: boolean
  tablet?: boolean
  floatX: number
  floatY: number
  driftX: number
  driftY: number
  rotateSpeed: number
  phase: number
  duration: number
  hoverRadius: number
  repelStrength: number
  orbit?: number
}

defineProps<{ mouseX?: number; mouseY?: number }>()

const ready = ref(false)
const weakDevice = ref(false)
const reducedMotion = ref(false)
const swatches = ['#be123c', '#ef4444', '#a855f7', '#38bdf8', '#0f172a']

const elements: CreativeElement[] = [
  { id: 'stylus', kind: 'stylus', side: 'left', x: 9, y: 12, size: 150, depth: 0.36, rotate: -26, opacity: 0.82, floatX: 14, floatY: 20, driftX: -8, driftY: 6, rotateSpeed: 0.8, phase: 1.3, duration: 13, hoverRadius: 140, repelStrength: 18 },
  { id: 'pen-tool', kind: 'pen-tool', side: 'left', x: 24, y: 12, size: 134, depth: 0.25, rotate: 7, opacity: 0.72, tablet: true, floatX: -10, floatY: 14, driftX: 5, driftY: -9, rotateSpeed: -0.5, phase: 2.1, duration: 16, hoverRadius: 125, repelStrength: 15 },
  { id: 'swatches', kind: 'swatches', side: 'left', x: 8, y: 38, size: 140, depth: 0.3, rotate: -10, opacity: 0.78, tablet: true, mobile: true, floatX: 8, floatY: 12, driftX: -6, driftY: -4, rotateSpeed: 1.1, phase: 0.6, duration: 18, hoverRadius: 130, repelStrength: 16 },
  { id: 'layers', kind: 'layers', side: 'left', x: 25, y: 37, size: 118, depth: 0.16, rotate: 5, opacity: 0.64, tablet: true, floatX: -6, floatY: 18, driftX: 7, driftY: 4, rotateSpeed: 0.45, phase: 2.7, duration: 15, hoverRadius: 112, repelStrength: 12 },
  { id: 'brush', kind: 'brush', side: 'left', x: 15, y: 63, size: 150, depth: 0.34, rotate: -20, opacity: 0.78, floatX: 18, floatY: 11, driftX: 9, driftY: -8, rotateSpeed: -0.7, phase: 3.4, duration: 12, hoverRadius: 145, repelStrength: 20 },
  { id: 'palette', kind: 'palette', side: 'left', x: 17, y: 74, size: 130, depth: 0.22, rotate: 9, opacity: 0.68, tablet: true, floatX: -9, floatY: 21, driftX: -4, driftY: 7, rotateSpeed: 0.35, phase: 1.9, duration: 17, hoverRadius: 120, repelStrength: 14 },
  { id: 'eyedropper', kind: 'eyedropper', side: 'left', x: 28, y: 64, size: 110, depth: 0.28, rotate: 12, opacity: 0.72, floatX: 12, floatY: 17, driftX: -7, driftY: 5, rotateSpeed: -0.9, phase: 4.1, duration: 14, hoverRadius: 110, repelStrength: 16 },
  { id: 'crop', kind: 'crop', side: 'left', x: 10, y: 84, size: 105, depth: 0.14, rotate: -4, opacity: 0.58, mobile: true, floatX: -7, floatY: 10, driftX: 5, driftY: 8, rotateSpeed: 0.55, phase: 0.2, duration: 19, hoverRadius: 100, repelStrength: 10 },
  { id: 'film', kind: 'film', side: 'right', x: 70, y: 14, size: 225, depth: 0.24, rotate: 8, opacity: 0.72, tablet: true, mobile: true, floatX: -18, floatY: 13, driftX: 10, driftY: -6, rotateSpeed: -0.35, phase: 1.1, duration: 20, hoverRadius: 175, repelStrength: 20 },
  { id: 'timeline', kind: 'timeline', side: 'right', x: 70, y: 47, size: 150, depth: 0.3, rotate: -5, opacity: 0.75, tablet: true, floatX: 11, floatY: 16, driftX: -9, driftY: -5, rotateSpeed: 0.42, phase: 3.0, duration: 15, hoverRadius: 145, repelStrength: 18 },
  { id: 'clapper', kind: 'clapper', side: 'right', x: 80, y: 61, size: 140, depth: 0.34, rotate: 8, opacity: 0.74, tablet: true, floatX: -12, floatY: 18, driftX: 8, driftY: 6, rotateSpeed: 0.95, phase: 2.2, duration: 13, hoverRadius: 140, repelStrength: 18 },
  { id: 'aperture', kind: 'aperture', side: 'right', x: 85, y: 14, size: 118, depth: 0.2, rotate: 12, opacity: 0.68, tablet: true, mobile: true, floatX: 7, floatY: 14, driftX: -5, driftY: 9, rotateSpeed: 1.3, phase: 4.6, duration: 18, hoverRadius: 115, repelStrength: 12 },
  { id: 'fx', kind: 'fx', side: 'right', x: 68, y: 79, size: 105, depth: 0.18, rotate: 11, opacity: 0.62, floatX: 10, floatY: 9, driftX: -6, driftY: -7, rotateSpeed: -0.6, phase: 5.1, duration: 16, hoverRadius: 105, repelStrength: 12 },
  { id: 'graph', kind: 'graph', side: 'right', x: 80, y: 82, size: 112, depth: 0.26, rotate: -8, opacity: 0.68, tablet: true, floatX: -9, floatY: 13, driftX: 7, driftY: -4, rotateSpeed: 0.68, phase: 2.9, duration: 17, hoverRadius: 115, repelStrength: 14 },
  { id: 'bubble-1', kind: 'bubble', side: 'ambient', x: 18, y: 51, size: 28, depth: 0.1, rotate: 0, opacity: 0.58, mobile: true, floatX: 22, floatY: 28, driftX: -13, driftY: 12, rotateSpeed: 0.3, phase: 1.6, duration: 22, hoverRadius: 70, repelStrength: 9 },
  { id: 'bubble-2', kind: 'bubble', side: 'ambient', x: 73, y: 33, size: 24, depth: 0.12, rotate: 0, opacity: 0.5, floatX: -18, floatY: 22, driftX: 12, driftY: -10, rotateSpeed: -0.4, phase: 3.8, duration: 24, hoverRadius: 68, repelStrength: 8 },
  { id: 'bubble-3', kind: 'bubble', side: 'ambient', x: 93, y: 79, size: 32, depth: 0.1, rotate: 0, opacity: 0.48, mobile: true, floatX: 16, floatY: 26, driftX: -10, driftY: -14, rotateSpeed: 0.35, phase: 0.8, duration: 21, hoverRadius: 72, repelStrength: 9 },
  { id: 'bubble-4', kind: 'bubble', side: 'ambient', x: 31, y: 86, size: 22, depth: 0.08, rotate: 0, opacity: 0.42, floatX: -14, floatY: 18, driftX: 8, driftY: 11, rotateSpeed: -0.2, phase: 4.9, duration: 25, hoverRadius: 64, repelStrength: 7 }
]

const itemRefs = new Map<string, HTMLElement>()
let frameId = 0
let lastPointer: { x: number; y: number } | null = null

const visibleElements = computed(() => {
  if (weakDevice.value) return elements.filter((element, index) => element.mobile || index % 2 === 0).slice(0, 9)
  return elements
})

function itemStyle(element: CreativeElement) {
  return {
    '--x': `${element.x}vw`,
    '--y': `${element.y}vh`,
    '--size': `${element.size}px`,
    '--depth': element.depth,
    '--r': `${element.rotate}deg`,
    '--o': element.opacity,
    '--b': `${weakDevice.value ? 0 : element.blur || 0}px`,
    '--float-x': `${element.floatX}px`,
    '--float-y': `${element.floatY}px`,
    '--drift-x': `${element.driftX}px`,
    '--drift-y': `${element.driftY}px`,
    '--rotate-speed': `${element.rotateSpeed}deg`,
    '--delay': `${-element.phase}s`,
    '--duration': `${element.duration}s`,
    '--local-x': '0px',
    '--local-y': '0px',
    '--local-r': '0deg',
    '--local-scale': '1',
    '--proximity': '0',
    '--glow': '0rem'
  }
}

function aperturePath() {
  return 'M70 65 l26 -7 a37 37 0 0 0 -10 -19 Z'
}

function setItemRef(id: string, node: HTMLElement | null) {
  if (node) itemRefs.set(id, node)
  else itemRefs.delete(id)
}

function handlePointerMove(event: PointerEvent) {
  if (weakDevice.value || reducedMotion.value) return
  lastPointer = { x: event.clientX, y: event.clientY }
  if (!frameId) frameId = requestAnimationFrame(updateLocalInteraction)
}

function updateLocalInteraction() {
  frameId = 0
  if (!lastPointer) return

  for (const element of visibleElements.value) {
    const node = itemRefs.get(element.id)
    if (!node) continue

    const rect = node.getBoundingClientRect()
    const centerX = rect.left + rect.width / 2
    const centerY = rect.top + rect.height / 2
    const deltaX = lastPointer.x - centerX
    const deltaY = lastPointer.y - centerY
    const distance = Math.hypot(deltaX, deltaY)
    const proximity = Math.max(0, 1 - distance / element.hoverRadius)

    if (proximity <= 0) {
      node.style.setProperty('--local-x', '0px')
      node.style.setProperty('--local-y', '0px')
      node.style.setProperty('--local-r', '0deg')
      node.style.setProperty('--local-scale', '1')
      node.style.setProperty('--proximity', '0')
      node.style.setProperty('--glow', '0rem')
      continue
    }

    const safeDistance = Math.max(distance, 1)
    const awayX = (-deltaX / safeDistance) * element.repelStrength * proximity
    const awayY = (-deltaY / safeDistance) * element.repelStrength * proximity
    const sideOrbit = proximity * (element.orbit ?? element.depth * 18)

    node.style.setProperty('--local-x', `${awayX + (-deltaY / safeDistance) * sideOrbit}px`)
    node.style.setProperty('--local-y', `${awayY + (deltaX / safeDistance) * sideOrbit}px`)
    node.style.setProperty('--local-r', `${element.rotateSpeed * 7 * proximity}deg`)
    node.style.setProperty('--local-scale', `${1 + proximity * 0.07}`)
    node.style.setProperty('--proximity', `${proximity}`)
    node.style.setProperty('--glow', `${0.25 + proximity * 1.1}rem`)
  }
}

onMounted(() => {
  const nav = navigator as Navigator & { deviceMemory?: number }
  weakDevice.value = (nav.deviceMemory || 8) <= 4 || (navigator.hardwareConcurrency || 8) <= 4
  reducedMotion.value = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  const requestIdle = window.requestIdleCallback || ((callback: IdleRequestCallback) => window.setTimeout(() => callback({ didTimeout: false, timeRemaining: () => 0 }), 1200))
  requestIdle(() => {
    window.setTimeout(() => { ready.value = true }, 1200)
  })
  window.addEventListener('pointermove', handlePointerMove, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('pointermove', handlePointerMove)
  if (frameId) cancelAnimationFrame(frameId)
})
</script>
