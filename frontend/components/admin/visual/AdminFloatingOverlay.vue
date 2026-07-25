<template>
  <span class="ym-admin-overlay" @pointerenter="enterTrigger" @pointerleave="leaveTrigger">
    <button
      ref="anchor"
      type="button"
      class="ym-admin-overlay__trigger ym-admin-focusable"
      :class="triggerClass"
      :aria-label="ariaLabel || label"
      :aria-describedby="open && !activeInteractive ? id : undefined"
      :aria-expanded="interactive ? activeInteractive : undefined"
      :aria-controls="interactive ? id : undefined"
      :aria-disabled="disabled || undefined"
      @focus="openSoon"
      @blur="blurTrigger"
      @click="clickTrigger"
      @keydown.enter.prevent="openFromKeyboard"
      @keydown.space.prevent="openFromKeyboard"
      @keydown.esc.prevent="close(true)"
    >
      <slot name="trigger" />
    </button>

    <Teleport to="body">
      <Transition name="ym-admin-overlay">
        <section
          v-if="open"
          :id="id"
          ref="surface"
          class="ym-admin-overlay__surface"
          :class="[
            activeInteractive ? 'is-interactive' : 'is-tooltip',
            placement === 'above' ? 'is-above' : 'is-below',
            theme === 'light' ? 'is-light' : 'is-dark',
            { 'is-positioned': positioned }
          ]"
          :style="style"
          :role="activeInteractive ? 'dialog' : 'tooltip'"
          :aria-label="activeInteractive ? label : undefined"
          :tabindex="activeInteractive ? -1 : undefined"
          @pointerenter="cancelClose"
          @pointerleave="leaveSurface"
          @keydown.esc.prevent="close(true)"
        >
          <header v-if="activeInteractive">
            <strong>{{ label }}</strong>
            <button type="button" :aria-label="closeLabel" @click="close(true)">×</button>
          </header>
          <div>
            <slot v-if="activeInteractive" />
            <template v-else>{{ description || label }}</template>
          </div>
          <i aria-hidden="true" />
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
  ariaLabel?: string
  closeLabel?: string
  triggerClass?: string
  interactive?: boolean
  disabled?: boolean
}>(), {
  description: '',
  ariaLabel: '',
  closeLabel: 'Close',
  triggerClass: '',
  interactive: false,
  disabled: false
})

const emit = defineEmits<{ activate: [] }>()

const instance = getCurrentInstance()
const id = `ym-admin-overlay-${instance?.uid ?? Math.random().toString(36).slice(2)}`
const theme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const anchor = ref<HTMLButtonElement | null>(null)
const surface = ref<HTMLElement | null>(null)
const open = ref(false)
const pinned = ref(false)
const positioned = ref(false)
const placement = ref<'above' | 'below'>('above')
const point = ref({ left: 12, top: 12, arrow: 20 })
const activeInteractive = computed(() => props.interactive && pinned.value)
const style = computed(() => ({
  left: `${point.value.left}px`,
  top: `${point.value.top}px`,
  '--ym-admin-overlay-arrow': `${point.value.arrow}px`
}))

let openTimer: ReturnType<typeof setTimeout> | null = null
let closeTimer: ReturnType<typeof setTimeout> | null = null
let frame = 0
let observer: ResizeObserver | null = null

function position(): void {
  if (!anchor.value || !surface.value || typeof window === 'undefined') return
  const margin = 12
  const gap = 10
  const anchorRect = anchor.value.getBoundingClientRect()
  const surfaceRect = surface.value.getBoundingClientRect()
  const width = Math.min(surfaceRect.width, window.innerWidth - margin * 2)
  const height = Math.min(surfaceRect.height, window.innerHeight - margin * 2)
  const above = anchorRect.top - margin
  const below = window.innerHeight - anchorRect.bottom - margin
  const useAbove = above >= height + gap || above >= below
  const left = Math.min(
    Math.max(margin, anchorRect.left + anchorRect.width / 2 - width / 2),
    Math.max(margin, window.innerWidth - width - margin)
  )
  const top = useAbove
    ? Math.max(margin, anchorRect.top - height - gap)
    : Math.min(window.innerHeight - height - margin, anchorRect.bottom + gap)
  point.value = {
    left,
    top,
    arrow: Math.min(Math.max(16, anchorRect.left + anchorRect.width / 2 - left), width - 16)
  }
  placement.value = useAbove ? 'above' : 'below'
  positioned.value = true
}

function schedulePosition(): void {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(position)
}

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

async function show(immediate = false, pin = false): Promise<void> {
  if (openTimer) clearTimeout(openTimer)
  cancelClose()
  if (pin) pinned.value = true
  const reveal = async (): Promise<void> => {
    openTimer = null
    open.value = true
    positioned.value = false
    document.dispatchEvent(new CustomEvent('ym:admin-overlay-open', { detail: id }))
    await nextTick()
    position()
    if ('ResizeObserver' in window) {
      observer = new ResizeObserver(schedulePosition)
      if (anchor.value) observer.observe(anchor.value)
      if (surface.value) observer.observe(surface.value)
    }
    document.addEventListener('pointerdown', outside)
    window.addEventListener('resize', schedulePosition)
    window.addEventListener('scroll', schedulePosition, true)
  }
  if (open.value) {
    await nextTick()
    schedulePosition()
  } else if (immediate) {
    await reveal()
  } else {
    openTimer = setTimeout(() => void reveal(), 120)
  }
}

function close(restore: boolean): void {
  clearTimers()
  pinned.value = false
  if (!open.value) return
  open.value = false
  positioned.value = false
  cancelAnimationFrame(frame)
  observer?.disconnect()
  observer = null
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', schedulePosition)
  window.removeEventListener('scroll', schedulePosition, true)
  if (restore) nextTick(() => anchor.value?.focus())
}

function outside(event: PointerEvent): void {
  const target = event.target as Node | null
  if (anchor.value?.contains(target) || surface.value?.contains(target)) return
  close(false)
}

function openSoon(): void { void show(false) }
function enterTrigger(): void { void show(false) }
function leaveTrigger(): void {
  if (pinned.value) return
  cancelClose()
  closeTimer = setTimeout(() => close(false), 100)
}
function leaveSurface(): void {
  if (!activeInteractive.value) close(false)
}
function blurTrigger(event: FocusEvent): void {
  if (activeInteractive.value && surface.value?.contains(event.relatedTarget as Node | null)) return
  leaveTrigger()
}
function clickTrigger(): void {
  if (props.disabled) {
    void show(true)
    return
  }
  if (props.interactive) {
    if (open.value && pinned.value) close(true)
    else void show(true, true).then(() => surface.value?.focus())
  } else {
    void show(true)
    emit('activate')
  }
}
function openFromKeyboard(): void {
  if (props.disabled) {
    void show(true)
    return
  }
  void show(true, props.interactive).then(() => {
    if (props.interactive) surface.value?.focus()
  })
  if (!props.interactive) emit('activate')
}
function other(event: Event): void {
  if ((event as CustomEvent<string>).detail !== id) close(false)
}

watch(() => [props.label, props.description, theme.value], () => nextTick(schedulePosition))
onMounted(() => document.addEventListener('ym:admin-overlay-open', other))
onBeforeUnmount(() => {
  close(false)
  document.removeEventListener('ym:admin-overlay-open', other)
})
</script>

<style scoped>
.ym-admin-overlay{display:inline-flex;min-width:0}.ym-admin-overlay__trigger{width:100%;min-width:0;border:0;padding:0;color:inherit;background:transparent;font:inherit;text-align:inherit;cursor:pointer}.ym-admin-overlay__trigger[aria-disabled="true"]{cursor:not-allowed;opacity:.5}.ym-admin-overlay__surface{position:fixed;z-index:var(--ym-admin-layer-tooltip,110);box-sizing:border-box;width:max-content;min-width:180px;max-width:min(330px,calc(100vw - 24px));max-height:calc(100dvh - 24px);border:1px solid rgba(139,92,246,.42);border-radius:13px;color:#fff;-webkit-text-fill-color:currentColor;background:rgba(8,15,31,.985);background-clip:border-box;-webkit-background-clip:border-box;box-shadow:0 20px 50px rgba(2,6,23,.42),inset 0 1px rgba(255,255,255,.08);font-size:13px;line-height:1.6;overflow-wrap:anywhere}.ym-admin-overlay__surface.is-light{background:rgba(17,22,35,.99)}.ym-admin-overlay__surface:not(.is-positioned){visibility:hidden}.ym-admin-overlay__surface.is-tooltip{padding:9px 11px;pointer-events:none}.ym-admin-overlay__surface.is-interactive{width:min(330px,calc(100vw - 24px));overflow-y:auto;border-radius:16px}.ym-admin-overlay__surface>header{position:sticky;top:0;display:flex;align-items:center;justify-content:space-between;gap:10px;border-bottom:1px solid rgba(139,92,246,.26);padding:10px 12px;background:inherit}.ym-admin-overlay__surface>header button{display:grid;width:34px;height:34px;place-items:center;border:1px solid rgba(148,163,184,.25);border-radius:10px;color:#fff;background:rgba(30,41,59,.75);font-size:20px}.ym-admin-overlay__surface>div{padding:11px 12px}.ym-admin-overlay__surface.is-tooltip>div{padding:0}.ym-admin-overlay__surface>i{position:absolute;left:calc(var(--ym-admin-overlay-arrow) - 6px);width:12px;height:12px;border:inherit;background:inherit;transform:rotate(45deg)}.ym-admin-overlay__surface.is-above>i{bottom:-7px;border-top:0;border-left:0}.ym-admin-overlay__surface.is-below>i{top:-7px;border-right:0;border-bottom:0}.ym-admin-overlay-enter-active,.ym-admin-overlay-leave-active{transition:opacity .16s ease,transform .16s ease}.ym-admin-overlay-enter-from,.ym-admin-overlay-leave-to{opacity:0;transform:translateY(3px) scale(.985)}@media(prefers-reduced-motion:reduce){.ym-admin-overlay-enter-active,.ym-admin-overlay-leave-active{transition:none}}
.ym-admin-overlay__surface {
  z-index: var(--ym-admin-layer-tooltip, 1350);
}
</style>
