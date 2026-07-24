<template>
  <span
    class="ym-floating-overlay"
    @pointerenter="handleTriggerEnter"
    @pointerleave="handleTriggerLeave"
  >
    <button
      ref="anchorRef"
      type="button"
      class="ym-floating-overlay__trigger"
      :class="triggerClass"
      :aria-label="triggerAriaLabel || label"
      :aria-describedby="open && !activeInteractive ? overlayId : undefined"
      :aria-expanded="interactive ? activeInteractive : undefined"
      :aria-controls="interactive ? overlayId : undefined"
      @focus="handleFocus"
      @blur="handleBlur"
      @click="handleClick"
      @keydown.enter.prevent="handleKeyboardOpen"
      @keydown.space.prevent="handleKeyboardOpen"
      @keydown.esc.prevent="close(true)"
    >
      <slot name="trigger" />
    </button>

    <Teleport to="body">
      <Transition name="ym-floating">
        <section
          v-if="open"
          :id="overlayId"
          ref="overlayRef"
          class="ym-floating-overlay__surface"
          :class="[
            activeInteractive ? 'is-interactive' : 'is-tooltip',
            placement === 'above' ? 'is-above' : 'is-below',
            dashboardTheme === 'light' ? 'is-light' : 'is-dark',
            { 'is-positioned': positioned }
          ]"
          :style="overlayStyle"
          :role="activeInteractive ? 'dialog' : 'tooltip'"
          :aria-label="activeInteractive ? label : undefined"
          :tabindex="activeInteractive ? -1 : undefined"
          @pointerenter="handleOverlayEnter"
          @pointerleave="handleOverlayLeave"
          @keydown.esc.prevent="close(true)"
        >
          <header v-if="activeInteractive" class="ym-floating-overlay__header">
            <strong>{{ label }}</strong>
            <button type="button" :aria-label="closeLabel" @click="close(true)">×</button>
          </header>
          <div class="ym-floating-overlay__content">
            <slot v-if="activeInteractive" />
            <template v-else>{{ description || label }}</template>
          </div>
          <span class="ym-floating-overlay__arrow" aria-hidden="true" />
        </section>
      </Transition>
    </Teleport>
  </span>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  label: string
  description?: string
  closeLabel?: string
  triggerAriaLabel?: string
  triggerClass?: string
  interactive?: boolean
}>(), {
  description: '',
  closeLabel: 'Close',
  triggerAriaLabel: '',
  triggerClass: '',
  interactive: false
})

const emit = defineEmits<{ activate: [] }>()
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const instance = getCurrentInstance()
const overlayId = `ym-works-floating-overlay-${instance?.uid ?? Math.random().toString(36).slice(2)}`
const anchorRef = ref<HTMLButtonElement | null>(null)
const overlayRef = ref<HTMLElement | null>(null)
const open = ref(false)
const pinned = ref(false)
const positioned = ref(false)
const activeInteractive = computed(() => props.interactive && pinned.value)
const placement = ref<'above' | 'below'>('above')
const position = ref({ left: 12, top: 12, arrowLeft: 20 })
const overlayStyle = computed(() => ({
  left: `${position.value.left}px`,
  top: `${position.value.top}px`,
  '--ym-overlay-arrow-left': `${position.value.arrowLeft}px`
}))

let openTimer: ReturnType<typeof setTimeout> | null = null
let closeTimer: ReturnType<typeof setTimeout> | null = null
let frame = 0
let transitionFrame = 0
let resizeObserver: ResizeObserver | null = null

function clearTimers(): void {
  if (openTimer) clearTimeout(openTimer)
  if (closeTimer) clearTimeout(closeTimer)
  openTimer = null
  closeTimer = null
}

function cancelClose(): void {
  if (closeTimer) clearTimeout(closeTimer)
  closeTimer = null
}

function schedulePosition(): void {
  if (!open.value || typeof window === 'undefined') return
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(positionOverlay)
}

function positionOverlay(): void {
  const anchor = anchorRef.value
  const overlay = overlayRef.value
  if (!anchor || !overlay || typeof window === 'undefined') return

  const viewportMargin = 12
  const gap = 10
  const anchorRect = anchor.getBoundingClientRect()
  const overlayRect = overlay.getBoundingClientRect()
  const width = Math.min(overlayRect.width, window.innerWidth - viewportMargin * 2)
  const height = Math.min(overlayRect.height, window.innerHeight - viewportMargin * 2)
  const roomAbove = anchorRect.top - viewportMargin
  const roomBelow = window.innerHeight - anchorRect.bottom - viewportMargin
  const placeAbove = roomAbove >= height + gap || roomAbove >= roomBelow
  const idealLeft = anchorRect.left + anchorRect.width / 2 - width / 2
  const left = Math.min(
    Math.max(viewportMargin, idealLeft),
    Math.max(viewportMargin, window.innerWidth - width - viewportMargin)
  )
  const top = placeAbove
    ? Math.max(viewportMargin, anchorRect.top - height - gap)
    : Math.min(window.innerHeight - height - viewportMargin, anchorRect.bottom + gap)
  const arrowLeft = Math.min(Math.max(16, anchorRect.left + anchorRect.width / 2 - left), width - 16)

  placement.value = placeAbove ? 'above' : 'below'
  position.value = { left, top, arrowLeft }
  positioned.value = true
}

function trackLayoutTransition(): void {
  if (!open.value || typeof window === 'undefined') return
  cancelAnimationFrame(transitionFrame)
  const startedAt = performance.now()
  const tick = (): void => {
    schedulePosition()
    if (performance.now() - startedAt < 260 && open.value) {
      transitionFrame = requestAnimationFrame(tick)
    }
  }
  transitionFrame = requestAnimationFrame(tick)
}

function handleOutside(event: PointerEvent): void {
  const target = event.target as Node | null
  if (anchorRef.value?.contains(target) || overlayRef.value?.contains(target)) return
  close(false)
}

function handleOtherOverlay(event: Event): void {
  const activeId = (event as CustomEvent<string>).detail
  if (activeId !== overlayId) close(false)
}

async function show(immediate = false, pin = false): Promise<void> {
  if (openTimer) clearTimeout(openTimer)
  openTimer = null
  cancelClose()
  if (pin) pinned.value = true
  if (open.value) {
    await nextTick()
    schedulePosition()
    return
  }

  const reveal = async (): Promise<void> => {
    openTimer = null
    positioned.value = false
    open.value = true
    document.dispatchEvent(new CustomEvent('ym:works-index-overlay-open', { detail: overlayId }))
    await nextTick()
    positionOverlay()
    if ('ResizeObserver' in window) {
      resizeObserver = new ResizeObserver(schedulePosition)
      if (anchorRef.value) resizeObserver.observe(anchorRef.value)
      if (overlayRef.value) resizeObserver.observe(overlayRef.value)
    }
    document.addEventListener('pointerdown', handleOutside)
    window.addEventListener('resize', schedulePosition)
    window.addEventListener('scroll', schedulePosition, true)
    document.addEventListener('transitionrun', trackLayoutTransition, true)
    document.addEventListener('transitionend', schedulePosition, true)
  }

  if (immediate) {
    await reveal()
    return
  }
  openTimer = setTimeout(() => void reveal(), 120)
}

function close(restoreFocus: boolean): void {
  clearTimers()
  pinned.value = false
  if (!open.value) return
  open.value = false
  positioned.value = false
  cancelAnimationFrame(frame)
  cancelAnimationFrame(transitionFrame)
  resizeObserver?.disconnect()
  resizeObserver = null
  document.removeEventListener('pointerdown', handleOutside)
  window.removeEventListener('resize', schedulePosition)
  window.removeEventListener('scroll', schedulePosition, true)
  document.removeEventListener('transitionrun', trackLayoutTransition, true)
  document.removeEventListener('transitionend', schedulePosition, true)
  if (restoreFocus) nextTick(() => anchorRef.value?.focus())
}

function scheduleClose(): void {
  if (pinned.value) return
  if (openTimer) clearTimeout(openTimer)
  openTimer = null
  cancelClose()
  closeTimer = setTimeout(() => close(false), 100)
}

function handleTriggerEnter(): void {
  void show(false)
}

function handleTriggerLeave(): void {
  scheduleClose()
}

function handleOverlayEnter(): void {
  if (props.interactive) cancelClose()
}

function handleOverlayLeave(): void {
  if (props.interactive) scheduleClose()
}

function handleFocus(): void {
  void show(false)
}

function handleBlur(event: FocusEvent): void {
  const next = event.relatedTarget as Node | null
  if (props.interactive && overlayRef.value?.contains(next)) return
  scheduleClose()
}

function handleClick(): void {
  if (props.interactive) {
    if (open.value && pinned.value) {
      close(true)
      return
    }
    void show(true, true).then(() => overlayRef.value?.focus())
    return
  }
  void show(true)
  emit('activate')
}

function handleKeyboardOpen(): void {
  if (props.interactive) {
    void show(true, true).then(() => overlayRef.value?.focus())
    return
  }
  void show(true)
  emit('activate')
}

function handleForcedClose(): void {
  close(false)
}

watch(() => [props.label, props.description, dashboardTheme.value], () => nextTick(schedulePosition))
onMounted(() => {
  document.addEventListener('ym:works-index-overlay-open', handleOtherOverlay)
  document.addEventListener('ym:works-index-overlays-close', handleForcedClose)
})

onBeforeUnmount(() => {
  close(false)
  document.removeEventListener('ym:works-index-overlay-open', handleOtherOverlay)
  document.removeEventListener('ym:works-index-overlays-close', handleForcedClose)
})
</script>

<style scoped>
.ym-floating-overlay { display: inline-flex; min-width: 0; }
.ym-floating-overlay__trigger { width: 100%; min-width: 0; border: 0; padding: 0; color: inherit; background: transparent; font: inherit; text-align: inherit; cursor: pointer; }
.ym-floating-overlay__trigger:focus-visible { outline: 3px solid color-mix(in srgb, #8b5cf6 48%, transparent); outline-offset: 3px; border-radius: 10px; }
.ym-floating-overlay__surface {
  --ym-overlay-background: rgba(15, 23, 42, .985);
  --ym-overlay-border: rgba(139, 92, 246, .42);
  --ym-overlay-text: #fff;
  --ym-overlay-muted: rgba(241, 245, 249, .82);
  --ym-text: var(--ym-overlay-text);
  --ym-muted: var(--ym-overlay-muted);
  --ym-card-border: rgba(139, 92, 246, .3);
  --ym-input-bg: rgba(30, 41, 59, .92);
  position: fixed;
  /* Above page chrome (<= 50), below full-page drawers and dialogs (>= 120). */
  z-index: 110;
  box-sizing: border-box;
  width: max-content;
  min-width: 180px;
  max-width: min(320px, calc(100vw - 24px));
  max-height: calc(100dvh - 24px);
  border: 1px solid var(--ym-overlay-border);
  border-radius: 12px;
  color: var(--ym-overlay-text);
  -webkit-text-fill-color: currentColor;
  background: var(--ym-overlay-background);
  background-clip: border-box;
  -webkit-background-clip: border-box;
  box-shadow: 0 18px 45px rgba(2, 6, 23, .4), inset 0 1px 0 rgba(255, 255, 255, .07);
  font-size: 13px;
  line-height: 1.58;
  overflow-wrap: anywhere;
  pointer-events: auto;
  transform-origin: center bottom;
}
.ym-floating-overlay__surface.is-light {
  --ym-overlay-background: rgba(20, 25, 38, .985);
  --ym-overlay-border: rgba(139, 92, 246, .48);
  --ym-overlay-text: #fff;
  --ym-overlay-muted: rgba(248, 250, 252, .84);
}
.ym-floating-overlay__surface.is-dark {
  --ym-overlay-background: rgba(7, 15, 31, .99);
  --ym-overlay-border: rgba(34, 211, 238, .28);
  --ym-overlay-text: #f8fafc;
  --ym-overlay-muted: rgba(226, 232, 240, .86);
}
.ym-floating-overlay__surface.is-below { transform-origin: center top; }
.ym-floating-overlay__surface:not(.is-positioned) { visibility: hidden; }
.ym-floating-overlay__surface.is-tooltip { padding: 9px 11px; pointer-events: none; }
.ym-floating-overlay__surface.is-interactive { width: min(320px, calc(100vw - 24px)); overflow-y: auto; border-radius: 16px; }
.ym-floating-overlay__header { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 12px; border-bottom: 1px solid var(--ym-card-border); padding: 10px 12px; color: var(--ym-overlay-text); background: var(--ym-overlay-background); }
.ym-floating-overlay__header strong { color: inherit; -webkit-text-fill-color: currentColor; font-size: 14px; }
.ym-floating-overlay__header button { display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid var(--ym-card-border); border-radius: 9px; color: var(--ym-overlay-text); -webkit-text-fill-color: currentColor; background: var(--ym-input-bg); font-size: 19px; cursor: pointer; }
.ym-floating-overlay__header button:focus-visible { outline: 3px solid rgba(139, 92, 246, .52); outline-offset: 2px; }
.ym-floating-overlay__content { color: var(--ym-overlay-text); -webkit-text-fill-color: currentColor; background-clip: border-box; -webkit-background-clip: border-box; }
.is-interactive .ym-floating-overlay__content { padding: 12px; }
.ym-floating-overlay__arrow { position: absolute; left: var(--ym-overlay-arrow-left); width: 10px; height: 10px; border-inline-end: 1px solid var(--ym-overlay-border); border-block-end: 1px solid var(--ym-overlay-border); background: var(--ym-overlay-background); pointer-events: none; }
.is-above .ym-floating-overlay__arrow { bottom: -6px; transform: translateX(-50%) rotate(45deg); }
.is-below .ym-floating-overlay__arrow { top: -6px; transform: translateX(-50%) rotate(225deg); }
.ym-floating-enter-active, .ym-floating-leave-active { transition: opacity .16s ease, transform .16s ease; }
.ym-floating-enter-from, .ym-floating-leave-to { opacity: 0; transform: translateY(2px) scale(.98); }
@media (prefers-reduced-motion: reduce) {
  .ym-floating-enter-active, .ym-floating-leave-active { transition: none; }
}
</style>
