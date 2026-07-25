<template>
  <Teleport to="body">
    <Transition name="ym-admin-dialog">
      <div
        v-if="open"
        ref="overlayRef"
        class="ym-admin-dialog-overlay"
        :class="[
          theme === 'light' ? 'is-light' : 'is-dark',
          locale === 'ar' ? 'is-rtl' : 'is-ltr'
        ]"
        role="presentation"
        @mousedown.self="requestClose('backdrop')"
        @keydown.esc.stop.prevent="requestClose('escape')"
        @keydown.tab="trapFocus"
      >
        <section
          ref="dialogRef"
          class="ym-admin-dialog"
          :class="`is-${size}`"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :aria-describedby="descriptionId"
          :aria-busy="busy ? 'true' : undefined"
          :dir="locale === 'ar' ? 'rtl' : 'ltr'"
          tabindex="-1"
        >
          <header class="ym-admin-dialog__header">
            <slot name="header" />
          </header>

          <main class="ym-admin-dialog__body">
            <slot />
          </main>

          <footer v-if="slots.footer" class="ym-admin-dialog__footer">
            <slot name="footer" />
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, useSlots, watch } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  busy?: boolean
  titleId: string
  descriptionId?: string
  locale: 'ar' | 'en'
  size?: 'compact' | 'form' | 'wide'
}>(), {
  busy: false,
  descriptionId: undefined,
  size: 'form'
})

const emit = defineEmits<{
  close: [reason: 'backdrop' | 'escape']
}>()

const slots = useSlots()
const theme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const overlayRef = ref<HTMLElement | null>(null)
const dialogRef = ref<HTMLElement | null>(null)
let opener: HTMLElement | null = null
let bodyOverflow = ''
let bodyPaddingRight = ''
let bodyLocked = false
let backgroundStates: Array<{ element: HTMLElement; inert: boolean }> = []

const focusableSelector = [
  '[data-dialog-initial]',
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])'
].join(',')

watch(
  () => props.open,
  async (open) => {
    if (!import.meta.client) return

    if (!open) {
      releaseDialog(true)
      return
    }

    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
    lockBodyScroll()
    await nextTick()
    if (!props.open) return

    makeBackgroundInert()
    document.addEventListener('focusin', keepFocusInside)
    focusInitialElement()
  },
  { immediate: true, flush: 'post' }
)

onBeforeUnmount(() => releaseDialog(true))

function requestClose(reason: 'backdrop' | 'escape'): void {
  if (!props.busy) emit('close', reason)
}

function focusableElements(): HTMLElement[] {
  if (!dialogRef.value) return []

  return Array.from(dialogRef.value.querySelectorAll<HTMLElement>(focusableSelector))
    .filter(element => (
      !element.hasAttribute('disabled')
      && element.getAttribute('aria-hidden') !== 'true'
      && element.getClientRects().length > 0
    ))
}

function focusInitialElement(): void {
  const preferred = dialogRef.value?.querySelector<HTMLElement>('[data-dialog-initial]')
  const target = preferred ?? focusableElements()[0] ?? dialogRef.value
  target?.focus({ preventScroll: true })
}

function trapFocus(event: KeyboardEvent): void {
  const elements = focusableElements()

  if (elements.length === 0) {
    event.preventDefault()
    dialogRef.value?.focus()
    return
  }

  const first = elements[0]
  const last = elements[elements.length - 1]
  const active = document.activeElement

  if (event.shiftKey && (active === first || !dialogRef.value?.contains(active))) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first?.focus()
  }
}

function keepFocusInside(event: FocusEvent): void {
  if (!props.open || !dialogRef.value) return
  if (event.target instanceof Node && dialogRef.value.contains(event.target)) return

  focusInitialElement()
}

function lockBodyScroll(): void {
  if (bodyLocked) return

  const body = document.body
  const scrollbarWidth = Math.max(0, window.innerWidth - document.documentElement.clientWidth)
  const currentPadding = Number.parseFloat(window.getComputedStyle(body).paddingRight) || 0

  bodyOverflow = body.style.overflow
  bodyPaddingRight = body.style.paddingRight
  body.style.overflow = 'hidden'

  if (scrollbarWidth > 0) {
    body.style.paddingRight = `${currentPadding + scrollbarWidth}px`
  }

  bodyLocked = true
}

function makeBackgroundInert(): void {
  const overlay = overlayRef.value
  if (!overlay) return

  backgroundStates = Array.from(document.body.children)
    .filter((element): element is HTMLElement => (
      element instanceof HTMLElement
      && element !== overlay
      && !element.contains(overlay)
    ))
    .map(element => {
      const state = { element, inert: element.inert }
      element.inert = true
      return state
    })
}

function releaseDialog(restoreFocus: boolean): void {
  document.removeEventListener('focusin', keepFocusInside)

  for (const state of backgroundStates) {
    state.element.inert = state.inert
  }
  backgroundStates = []

  if (bodyLocked) {
    document.body.style.overflow = bodyOverflow
    document.body.style.paddingRight = bodyPaddingRight
    bodyLocked = false
  }

  if (restoreFocus) {
    const target = opener
    opener = null
    nextTick(() => {
      if (target?.isConnected) target.focus({ preventScroll: true })
    })
  }
}
</script>

<style scoped>
.ym-admin-dialog-overlay {
  --ym-dialog-text: #f8fafc;
  --ym-dialog-muted: rgba(226, 232, 240, .78);
  --ym-dialog-surface: linear-gradient(145deg, rgba(10, 17, 36, .985), rgba(25, 34, 58, .975));
  --ym-dialog-surface-strong: rgba(9, 16, 34, .96);
  --ym-dialog-control: rgba(15, 23, 42, .78);
  --ym-dialog-border: rgba(167, 139, 250, .34);
  --ym-dialog-border-soft: rgba(148, 163, 184, .2);
  --ym-dialog-accent: #c4b5fd;
  --ym-dialog-accent-strong: #a78bfa;
  --ym-dialog-success: #34d399;
  --ym-dialog-warning: #fbbf24;
  --ym-dialog-danger: #fb7185;
  position: fixed;
  inset: 0;
  z-index: var(--ym-admin-layer-dialog, 1400);
  display: grid;
  overflow: hidden;
  place-items: center;
  padding: 16px;
  background: rgba(2, 6, 23, .64);
  backdrop-filter: blur(7px);
  -webkit-backdrop-filter: blur(7px);
  overscroll-behavior: contain;
}

.ym-admin-dialog-overlay.is-light {
  --ym-dialog-text: #211833;
  --ym-dialog-muted: rgba(55, 43, 76, .82);
  --ym-dialog-surface: linear-gradient(145deg, rgba(255, 255, 255, .985), rgba(246, 240, 255, .98));
  --ym-dialog-surface-strong: rgba(255, 255, 255, .94);
  --ym-dialog-control: rgba(248, 245, 255, .96);
  --ym-dialog-border: rgba(109, 40, 217, .36);
  --ym-dialog-border-soft: rgba(109, 40, 217, .2);
  --ym-dialog-accent: #6d28d9;
  --ym-dialog-accent-strong: #6d28d9;
  --ym-dialog-success: #047857;
  --ym-dialog-warning: #92400e;
  --ym-dialog-danger: #be123c;
  background: rgba(30, 22, 48, .5);
}

.ym-admin-dialog {
  position: relative;
  display: grid;
  width: min(calc(100vw - 32px), 600px);
  max-height: calc(100dvh - 32px);
  min-width: 0;
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
  border: 1px solid var(--ym-dialog-border);
  border-radius: 22px;
  outline: none;
  color: var(--ym-dialog-text);
  -webkit-text-fill-color: currentColor;
  background: var(--ym-dialog-surface);
  box-shadow:
    0 34px 90px rgba(2, 6, 23, .52),
    0 0 0 1px rgba(255, 255, 255, .04) inset,
    0 0 48px rgba(124, 58, 237, .12);
}

.ym-admin-dialog.is-compact {
  width: min(calc(100vw - 32px), 520px);
}

.ym-admin-dialog.is-wide {
  width: min(calc(100vw - 32px), 980px);
}

.ym-admin-dialog__header,
.ym-admin-dialog__footer {
  position: relative;
  z-index: 1;
  background: var(--ym-dialog-surface-strong);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
}

.ym-admin-dialog__header {
  border-bottom: 1px solid var(--ym-dialog-border-soft);
  padding: 18px 20px;
}

.ym-admin-dialog__header::after {
  position: absolute;
  right: 20px;
  bottom: -1px;
  left: 20px;
  height: 1px;
  background: linear-gradient(90deg, transparent, #8b5cf6, #ec4899, transparent);
  content: "";
  opacity: .62;
}

.ym-admin-dialog__body {
  min-width: 0;
  min-height: 0;
  overflow-x: hidden;
  overflow-y: auto;
  padding: 20px;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
}

.ym-admin-dialog__footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  border-top: 1px solid var(--ym-dialog-border-soft);
  padding: 14px 20px calc(14px + env(safe-area-inset-bottom));
}

.ym-admin-dialog:focus-visible {
  box-shadow:
    0 34px 90px rgba(2, 6, 23, .52),
    0 0 0 3px rgba(139, 92, 246, .32);
}

.ym-admin-dialog-enter-active,
.ym-admin-dialog-leave-active {
  transition: opacity 200ms ease;
}

.ym-admin-dialog-enter-active .ym-admin-dialog,
.ym-admin-dialog-leave-active .ym-admin-dialog {
  transition: transform 200ms ease, opacity 180ms ease;
}

.ym-admin-dialog-enter-from,
.ym-admin-dialog-leave-to {
  opacity: 0;
}

.ym-admin-dialog-enter-from .ym-admin-dialog,
.ym-admin-dialog-leave-to .ym-admin-dialog {
  opacity: 0;
  transform: translateY(8px) scale(.985);
}

@media (max-width: 640px) {
  .ym-admin-dialog-overlay {
    place-items: end center;
    padding: 12px;
  }

  .ym-admin-dialog,
  .ym-admin-dialog.is-compact,
  .ym-admin-dialog.is-wide {
    width: calc(100vw - 24px);
    max-height: calc(100dvh - 24px);
    border-radius: 20px;
  }

  .ym-admin-dialog__header,
  .ym-admin-dialog__body {
    padding: 16px;
  }

  .ym-admin-dialog__footer {
    flex-wrap: wrap;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
  }

  .ym-admin-dialog__footer :deep(button) {
    min-height: 44px;
    flex: 1 1 140px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-admin-dialog-enter-active,
  .ym-admin-dialog-leave-active,
  .ym-admin-dialog-enter-active .ym-admin-dialog,
  .ym-admin-dialog-leave-active .ym-admin-dialog {
    transition: none;
  }
}
</style>
