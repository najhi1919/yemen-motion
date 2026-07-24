<template>
  <span ref="rootRef" class="ym-filter-popover">
    <button
      ref="triggerRef"
      type="button"
      class="ym-filter-popover__trigger"
      :class="{ 'is-active': active }"
      :title="tooltip"
      :aria-label="ariaLabel"
      :aria-expanded="open"
      :aria-controls="panelId"
      :disabled="disabled"
      @click="toggle"
      @keydown.esc.prevent="close(true)"
    >
      <span>{{ label }}</span>
      <b>{{ summary }}</b>
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5" /></svg>
    </button>

    <Teleport to="body">
      <Transition name="ym-filter">
        <div v-if="open" class="ym-filter-popover__mobile-backdrop" @click.self="close(true)">
          <section
            :id="panelId"
            ref="panelRef"
            class="ym-filter-popover__panel"
            :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
            :style="panelStyle"
            role="dialog"
            :aria-modal="mobile ? 'true' : undefined"
            :aria-label="ariaLabel"
            tabindex="-1"
            @keydown.esc.prevent="close(true)"
          >
            <header>
              <strong>{{ label }}</strong>
              <button type="button" :aria-label="closeLabel" :title="closeLabel" @click="close(true)">×</button>
            </header>
            <div class="ym-filter-popover__body">
              <slot :close="() => close(true)" />
            </div>
          </section>
        </div>
      </Transition>
    </Teleport>
  </span>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref } from 'vue'

defineProps<{
  label: string
  summary: string
  ariaLabel: string
  tooltip: string
  closeLabel: string
  active?: boolean
  disabled?: boolean
}>()

const open = ref(false)
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const mobile = ref(false)
const rootRef = ref<HTMLElement | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const panelRef = ref<HTMLElement | null>(null)
const position = ref({ left: 16, top: 16 })
const panelId = `ym-filter-popover-${Math.random().toString(36).slice(2)}`
const panelStyle = computed(() => mobile.value ? undefined : {
  left: `${position.value.left}px`,
  top: `${position.value.top}px`
})

function positionPanel(): void {
  if (typeof window === 'undefined' || !triggerRef.value) return
  mobile.value = window.matchMedia('(max-width: 640px)').matches
  if (mobile.value) return
  const rect = triggerRef.value.getBoundingClientRect()
  const width = Math.min(340, window.innerWidth - 24)
  position.value = {
    left: Math.min(Math.max(12, rect.left), window.innerWidth - width - 12),
    top: Math.max(12, Math.min(rect.bottom + 8, window.innerHeight - 400))
  }
}

function outside(event: PointerEvent): void {
  const target = event.target as Node | null
  if (rootRef.value?.contains(target) || panelRef.value?.contains(target)) return
  close(false)
}

function viewport(): void {
  if (open.value) positionPanel()
}

async function show(): Promise<void> {
  if (open.value) return
  open.value = true
  await nextTick()
  positionPanel()
  panelRef.value?.focus()
  document.addEventListener('pointerdown', outside)
  window.addEventListener('resize', viewport)
  window.addEventListener('scroll', viewport, true)
}

function close(restoreFocus: boolean): void {
  if (!open.value) return
  open.value = false
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', viewport)
  window.removeEventListener('scroll', viewport, true)
  if (restoreFocus) nextTick(() => triggerRef.value?.focus())
}

function toggle(): void {
  if (open.value) close(true)
  else void show()
}

onBeforeUnmount(() => close(false))
</script>

<style scoped>
.ym-filter-popover { display: inline-flex; min-width: 0; }
.ym-filter-popover__trigger { display: grid; min-height: 44px; grid-template-columns: auto minmax(0, 1fr) 16px; align-items: center; gap: 6px; border: 1px solid var(--ym-control-border); border-radius: 12px; padding: 0 11px; color: var(--ym-text); background: var(--ym-input-bg); font-size: 13px; cursor: pointer; transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
.ym-filter-popover__trigger > span { color: var(--ym-muted); font-weight: 750; }
.ym-filter-popover__trigger > b { overflow: hidden; font-weight: 850; text-overflow: ellipsis; white-space: nowrap; }
.ym-filter-popover__trigger svg { width: 16px; fill: none; stroke: currentColor; stroke-width: 2; }
.ym-filter-popover__trigger.is-active { border-color: color-mix(in srgb, var(--ym-violet) 48%, var(--ym-control-border)); box-shadow: inset 0 -2px 0 color-mix(in srgb, var(--ym-violet) 48%, transparent); }
.ym-filter-popover__trigger:hover:not(:disabled) { transform: translateY(-1px); border-color: var(--ym-violet-electric); }
.ym-filter-popover__trigger:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-violet-electric) 38%, transparent); outline-offset: 2px; }
.ym-filter-popover__mobile-backdrop { position: fixed; inset: 0; z-index: 310; pointer-events: none; }
.ym-filter-popover__panel { position: fixed; z-index: 311; width: min(340px, calc(100vw - 24px)); max-height: min(390px, calc(100dvh - 24px)); overflow: auto; border: 1px solid var(--ym-card-border); border-radius: 16px; color: var(--ym-text); background: var(--ym-dropdown-bg); box-shadow: 0 24px 60px rgba(2,6,23,.34); pointer-events: auto; transition: opacity .16s ease, transform .16s ease; }
.ym-filter-popover__panel.is-dark { --ym-text: #f0f6ff; --ym-muted: rgba(226,232,240,.88); --ym-card-border: rgba(139,92,246,.26); --ym-control-border: rgba(148,163,184,.28); --ym-input-bg: rgba(15,23,42,.82); --ym-dropdown-bg: rgba(10,18,38,.98); --ym-violet: #7c3aed; --ym-violet-electric: #8b5cf6; --ym-magenta: #ec4899; }
.ym-filter-popover__panel.is-light { --ym-text: #171126; --ym-muted: rgba(45,36,64,.9); --ym-card-border: rgba(124,58,237,.24); --ym-control-border: rgba(124,58,237,.28); --ym-input-bg: rgba(255,255,255,.94); --ym-dropdown-bg: rgba(255,255,255,.99); --ym-violet: #7c3aed; --ym-violet-electric: #8b5cf6; --ym-magenta: #ec4899; }
.ym-filter-popover__panel > header { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--ym-card-border); padding: 10px 12px; background: inherit; }
.ym-filter-popover__panel > header button { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--ym-control-border); border-radius: 10px; color: inherit; background: var(--ym-input-bg); font-size: 20px; cursor: pointer; }
.ym-filter-popover__body { padding: 12px; }
.ym-filter-enter-active, .ym-filter-leave-active { transition: opacity .16s ease; }.ym-filter-enter-active .ym-filter-popover__panel, .ym-filter-leave-active .ym-filter-popover__panel { transition: opacity .16s ease, transform .16s ease; }.ym-filter-enter-from, .ym-filter-leave-to { opacity: 0; }.ym-filter-enter-from .ym-filter-popover__panel, .ym-filter-leave-to .ym-filter-popover__panel { opacity: 0; transform: translateY(-3px) scale(.985); }
@media (max-width: 640px) {
  .ym-filter-popover { width: 100%; }
  .ym-filter-popover__trigger { width: 100%; }
  .ym-filter-popover__mobile-backdrop { display: flex; align-items: flex-end; background: rgba(2,6,23,.44); backdrop-filter: blur(2px); pointer-events: auto; }
  .ym-filter-popover__panel { position: relative; inset: auto !important; width: 100%; max-height: min(72dvh, 620px); border-radius: 20px 20px 0 0; }
}
@media (prefers-reduced-motion: reduce) { .ym-filter-enter-active, .ym-filter-leave-active, .ym-filter-enter-active .ym-filter-popover__panel, .ym-filter-leave-active .ym-filter-popover__panel, .ym-filter-popover__panel, .ym-filter-popover__trigger { transition: none; }.ym-filter-popover__trigger:hover { transform: none; } }
</style>
