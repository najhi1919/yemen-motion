<template>
  <Teleport to="body">
    <Transition name="ym-works-drawer">
      <div
        v-if="open"
        ref="overlayRef"
        class="ym-works-drawer-overlay"
        :class="[
          locale === 'ar' ? 'is-rtl' : 'is-ltr',
          dashboardTheme === 'light' ? 'is-light' : 'is-dark'
        ]"
        @pointerdown.self="requestClose('backdrop')"
      >
        <section
          ref="panelRef"
          class="ym-works-drawer"
          :class="`is-${size}`"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          :aria-busy="busy || undefined"
          :dir="locale === 'ar' ? 'rtl' : 'ltr'"
          tabindex="-1"
          @keydown="handleKeydown"
        >
          <header class="ym-works-drawer__header">
            <div class="ym-works-drawer__heading">
              <slot name="header" />
              <span v-if="unsaved" class="ym-works-drawer__unsaved" role="status">
                {{ unsavedLabel }}
              </span>
            </div>
            <button
              ref="closeButtonRef"
              type="button"
              class="ym-works-drawer__close"
              :aria-label="closeLabel"
              :title="closeLabel"
              :disabled="busy"
              @click="requestClose('button')"
            >
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 6 12 12M18 6 6 18" />
              </svg>
            </button>
          </header>

          <main class="ym-works-drawer__main">
            <slot />
          </main>

          <footer v-if="$slots.footer" class="ym-works-drawer__footer">
            <slot name="footer" />
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script lang="ts">
let pageLockCount = 0
let storedBodyOverflow = ''
let storedBodyPaddingInlineEnd = ''
const storedInertState = new Map<HTMLElement, { inert: boolean; ariaHidden: string | null }>()

function acquirePageLock(activeOverlay: HTMLElement | null): void {
  if (pageLockCount === 0) {
    const scrollbarGap = window.innerWidth - document.documentElement.clientWidth
    storedBodyOverflow = document.body.style.overflow
    storedBodyPaddingInlineEnd = document.body.style.paddingInlineEnd
    document.body.style.overflow = 'hidden'
    if (scrollbarGap > 0) document.body.style.paddingInlineEnd = `${scrollbarGap}px`

    storedInertState.clear()
    for (const child of [...document.body.children]) {
      if (!(child instanceof HTMLElement) || child === activeOverlay) continue
      storedInertState.set(child, {
        inert: child.inert,
        ariaHidden: child.getAttribute('aria-hidden')
      })
      child.inert = true
      child.setAttribute('aria-hidden', 'true')
    }
  }
  pageLockCount += 1
}

function releasePageLock(): void {
  pageLockCount = Math.max(0, pageLockCount - 1)
  if (pageLockCount !== 0) return
  document.body.style.overflow = storedBodyOverflow
  document.body.style.paddingInlineEnd = storedBodyPaddingInlineEnd
  for (const [element, state] of storedInertState) {
    element.inert = state.inert
    if (state.ariaHidden === null) element.removeAttribute('aria-hidden')
    else element.setAttribute('aria-hidden', state.ariaHidden)
  }
  storedInertState.clear()
}
</script>

<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = withDefaults(defineProps<{
  open: boolean
  locale: 'ar' | 'en'
  size: 'details' | 'taxonomy'
  titleId: string
  closeLabel: string
  busy?: boolean
  unsaved?: boolean
  unsavedLabel?: string
}>(), {
  busy: false,
  unsaved: false,
  unsavedLabel: ''
})

const emit = defineEmits<{
  requestClose: [reason: 'button' | 'backdrop' | 'escape']
}>()

const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const overlayRef = ref<HTMLElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const closeButtonRef = ref<HTMLButtonElement | null>(null)
let opener: HTMLElement | null = null
let ownsPageLock = false

function focusableElements(): HTMLElement[] {
  if (!panelRef.value) return []
  return [...panelRef.value.querySelectorAll<HTMLElement>(
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), details > summary, [tabindex]:not([tabindex="-1"])'
  )].filter(element => !element.hasAttribute('hidden') && element.getClientRects().length > 0)
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    requestClose('escape')
    return
  }
  if (event.key !== 'Tab') return

  const focusable = focusableElements()
  if (!focusable.length) {
    event.preventDefault()
    panelRef.value?.focus()
    return
  }
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  const active = document.activeElement
  if (event.shiftKey && (active === first || active === panelRef.value)) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first?.focus()
  }
}

function requestClose(reason: 'button' | 'backdrop' | 'escape'): void {
  if (props.busy) return
  emit('requestClose', reason)
}

function lockPage(): void {
  if (ownsPageLock) return
  acquirePageLock(overlayRef.value)
  ownsPageLock = true
}

function unlockPage(): void {
  if (!ownsPageLock) return
  releasePageLock()
  ownsPageLock = false
}

watch(() => props.open, async (open) => {
  if (!import.meta.client) return
  if (open) {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
    document.dispatchEvent(new CustomEvent('ym:works-index-overlays-close'))
    await nextTick()
    lockPage()
    closeButtonRef.value?.focus()
    return
  }

  unlockPage()
  await nextTick()
  if (!document.querySelector('.ym-works-drawer-overlay') && opener?.isConnected) opener.focus()
  opener = null
}, { immediate: true })

onBeforeUnmount(() => {
  if (!import.meta.client) return
  if (props.open) unlockPage()
  if (!document.querySelector('.ym-works-drawer-overlay') && opener?.isConnected) opener.focus()
})
</script>

<style scoped>
.ym-works-drawer-overlay {
  --ym-drawer-violet: #7c3aed;
  --ym-drawer-electric: #8b5cf6;
  --ym-drawer-magenta: #ec4899;
  --ym-drawer-cyan: #22d3ee;
  --ym-drawer-emerald: #10b981;
  --ym-drawer-amber: #f59e0b;
  --ym-drawer-rose: #f43f5e;
  /* Page chrome and floating overlays stay below 1200; full critical modals stay above it. */
  position: fixed;
  z-index: 1200;
  inset: 0;
  display: flex;
  background: rgba(2, 6, 23, .62);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}
.ym-works-drawer-overlay.is-rtl { justify-content: flex-start; }
.ym-works-drawer-overlay.is-ltr { justify-content: flex-end; }
.ym-works-drawer-overlay.is-dark {
  --ym-drawer-text: #f4f7ff;
  --ym-drawer-muted: rgba(226, 232, 240, .86);
  --ym-drawer-surface: rgba(8, 16, 34, .985);
  --ym-drawer-surface-strong: rgba(15, 25, 48, .985);
  --ym-drawer-card: rgba(23, 34, 58, .68);
  --ym-drawer-control: rgba(15, 23, 42, .88);
  --ym-drawer-border: rgba(139, 92, 246, .28);
  --ym-drawer-soft-border: rgba(148, 163, 184, .18);
  color-scheme: dark;
}
.ym-works-drawer-overlay.is-light {
  --ym-drawer-text: #1c1530;
  --ym-drawer-muted: rgba(55, 45, 76, .82);
  --ym-drawer-surface: rgba(252, 250, 255, .985);
  --ym-drawer-surface-strong: rgba(255, 255, 255, .99);
  --ym-drawer-card: rgba(255, 255, 255, .72);
  --ym-drawer-control: rgba(248, 245, 255, .94);
  --ym-drawer-border: rgba(124, 58, 237, .24);
  --ym-drawer-soft-border: rgba(91, 33, 182, .14);
  color-scheme: light;
}
.ym-works-drawer {
  position: relative;
  display: grid;
  height: 100dvh;
  min-width: 0;
  grid-template-rows: auto minmax(0, 1fr) auto;
  overflow: hidden;
  outline: none;
  color: var(--ym-drawer-text);
  background:
    radial-gradient(circle at 18% 0, color-mix(in srgb, var(--ym-drawer-electric) 12%, transparent), transparent 34%),
    var(--ym-drawer-surface);
  box-shadow: 0 0 72px rgba(2, 6, 23, .5);
}
.ym-works-drawer.is-details { width: clamp(640px, 48vw, 760px); }
.ym-works-drawer.is-taxonomy { width: clamp(520px, 38vw, 600px); }
.is-rtl .ym-works-drawer { border-inline-end: 1px solid var(--ym-drawer-border); }
.is-ltr .ym-works-drawer { border-inline-start: 1px solid var(--ym-drawer-border); }
.ym-works-drawer__header {
  position: relative;
  z-index: 2;
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: start;
  gap: 18px;
  border-block-end: 1px solid var(--ym-drawer-border);
  padding: 19px 22px 17px;
  background: linear-gradient(125deg, color-mix(in srgb, var(--ym-drawer-surface-strong) 92%, var(--ym-drawer-violet) 8%), color-mix(in srgb, var(--ym-drawer-surface-strong) 95%, var(--ym-drawer-magenta) 5%));
  box-shadow: inset 0 -1px 0 color-mix(in srgb, var(--ym-drawer-cyan) 13%, transparent), 0 10px 28px rgba(2, 6, 23, .08);
}
.ym-works-drawer__header::after {
  position: absolute;
  inset-block-end: -1px;
  inset-inline: 22px;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--ym-drawer-electric), var(--ym-drawer-magenta), transparent);
  content: '';
  opacity: .65;
}
.ym-works-drawer__heading { display: grid; min-width: 0; gap: 5px; }
.ym-works-drawer__unsaved {
  width: fit-content;
  border-radius: 999px;
  padding: 4px 8px;
  color: var(--ym-drawer-amber);
  background: color-mix(in srgb, var(--ym-drawer-amber) 11%, transparent);
  font-size: 12.5px;
  font-weight: 800;
}
.ym-works-drawer__close {
  display: grid;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid var(--ym-drawer-border);
  border-radius: 12px;
  color: var(--ym-drawer-text);
  background: var(--ym-drawer-control);
  cursor: pointer;
  transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
}
.ym-works-drawer__close svg { width: 19px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
.ym-works-drawer__close:hover:not(:disabled) { transform: translateY(-1px); border-color: var(--ym-drawer-electric); box-shadow: 0 0 18px color-mix(in srgb, var(--ym-drawer-electric) 18%, transparent); }
.ym-works-drawer__close:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 42%, transparent); outline-offset: 2px; }
.ym-works-drawer__main { min-width: 0; overflow-x: hidden; overflow-y: auto; overscroll-behavior: contain; padding: 20px 22px 24px; scrollbar-color: color-mix(in srgb, var(--ym-drawer-electric) 45%, transparent) transparent; }
.ym-works-drawer__footer {
  position: relative;
  z-index: 2;
  border-block-start: 1px solid var(--ym-drawer-border);
  padding: 14px 22px;
  background: color-mix(in srgb, var(--ym-drawer-surface-strong) 96%, transparent);
  box-shadow: 0 -12px 28px rgba(2, 6, 23, .08);
}
.ym-works-drawer-overlay.is-rtl .ym-works-drawer { transform-origin: left center; }
.ym-works-drawer-overlay.is-ltr .ym-works-drawer { transform-origin: right center; }
.ym-works-drawer-enter-active { transition: opacity .22s ease; }
.ym-works-drawer-enter-active .ym-works-drawer { transition: transform .22s ease, opacity .22s ease; }
.ym-works-drawer-leave-active, .ym-works-drawer-leave-active .ym-works-drawer { transition: none; }
.ym-works-drawer-enter-from, .ym-works-drawer-leave-to { opacity: 0; }
.is-rtl.ym-works-drawer-enter-from .ym-works-drawer, .is-rtl.ym-works-drawer-leave-to .ym-works-drawer { opacity: .8; transform: translateX(-18px); }
.is-ltr.ym-works-drawer-enter-from .ym-works-drawer, .is-ltr.ym-works-drawer-leave-to .ym-works-drawer { opacity: .8; transform: translateX(18px); }
@media (max-width: 1024px) {
  .ym-works-drawer.is-details, .ym-works-drawer.is-taxonomy { width: min(88vw, 680px); }
}
@media (max-width: 640px) {
  .ym-works-drawer.is-details, .ym-works-drawer.is-taxonomy { width: 100vw; height: 100dvh; border: 0; border-radius: 0; }
  .ym-works-drawer__header { padding: 16px; }
  .ym-works-drawer__main { padding: 16px 15px 22px; }
  .ym-works-drawer__footer { padding: 12px 15px; }
  .ym-works-drawer__close { width: 44px; height: 44px; }
}
@media (prefers-reduced-motion: reduce) {
  .ym-works-drawer-enter-active, .ym-works-drawer-leave-active,
  .ym-works-drawer-enter-active .ym-works-drawer, .ym-works-drawer-leave-active .ym-works-drawer,
  .ym-works-drawer__close { transition: none; }
}
</style>
