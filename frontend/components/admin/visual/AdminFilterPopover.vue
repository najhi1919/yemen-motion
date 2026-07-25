<template>
  <span ref="root" class="ym-admin-filter-popover">
    <button
      ref="trigger"
      type="button"
      class="ym-admin-filter-popover__trigger ym-admin-focusable"
      :class="{ 'is-active': active }"
      :aria-label="ariaLabel"
      :aria-expanded="open"
      :aria-controls="id"
      :disabled="disabled"
      @click="toggle"
      @keydown.esc.prevent="close(true)"
    >
      <span aria-hidden="true">{{ icon }}</span>
      <b>{{ label }}</b>
      <small>{{ summary }}</small>
      <i aria-hidden="true">⌄</i>
    </button>
    <Teleport to="body">
      <Transition name="ym-admin-filter">
        <div v-if="open" class="ym-admin-filter-popover__backdrop" @click.self="close(true)">
          <section
            :id="id"
            ref="panel"
            class="ym-admin-filter-popover__panel"
            :class="theme === 'light' ? 'is-light' : 'is-dark'"
            :style="panelStyle"
            role="dialog"
            :aria-modal="mobile ? 'true' : undefined"
            :aria-label="ariaLabel"
            tabindex="-1"
            @keydown.esc.prevent="close(true)"
          >
            <header><strong>{{ label }}</strong><button type="button" :aria-label="closeLabel" @click="close(true)">×</button></header>
            <div><slot :close="() => close(true)" /></div>
          </section>
        </div>
      </Transition>
    </Teleport>
  </span>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'

defineProps<{
  label: string
  summary: string
  icon: string
  ariaLabel: string
  closeLabel: string
  active?: boolean
  disabled?: boolean
}>()

const theme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const root = ref<HTMLElement | null>(null)
const trigger = ref<HTMLButtonElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const open = ref(false)
const mobile = ref(false)
const point = ref({ left: 12, top: 12 })
const id = `ym-admin-filter-${getCurrentInstance()?.uid ?? 'panel'}`
const panelStyle = computed(() => mobile.value ? undefined : {
  left: `${point.value.left}px`,
  top: `${point.value.top}px`
})

let frame = 0

function position(): void {
  if (!trigger.value || !panel.value || typeof window === 'undefined') return
  mobile.value = matchMedia('(max-width: 640px)').matches
  if (mobile.value) return
  const rect = trigger.value.getBoundingClientRect()
  const margin = 12
  const gap = 8
  const panelRect = panel.value.getBoundingClientRect()
  const width = Math.min(panelRect.width, innerWidth - margin * 2)
  const height = Math.min(panelRect.height, innerHeight - margin * 2)
  const rtl = trigger.value.closest<HTMLElement>('[dir]')?.dir === 'rtl'
  const preferredLeft = rtl ? rect.right - width : rect.left
  const fitsBelow = rect.bottom + gap + height <= innerHeight - margin
  const preferredTop = fitsBelow ? rect.bottom + gap : rect.top - height - gap
  point.value = {
    left: Math.min(Math.max(margin, preferredLeft), innerWidth - width - margin),
    top: Math.min(Math.max(margin, preferredTop), innerHeight - height - margin)
  }
}

function schedulePosition(): void {
  cancelAnimationFrame(frame)
  frame = requestAnimationFrame(position)
}

function outside(event: PointerEvent): void {
  const target = event.target as Node | null
  if (root.value?.contains(target) || panel.value?.contains(target)) return
  const anotherTrigger = target instanceof Element
    && target.closest('.ym-admin-filter-popover__trigger') !== null
  close(!anotherTrigger)
}
async function show(): Promise<void> {
  document.dispatchEvent(new CustomEvent('ym:admin-filter-open', { detail: id }))
  open.value = true
  await nextTick()
  position()
  panel.value?.focus()
  document.addEventListener('pointerdown', outside)
  window.addEventListener('resize', schedulePosition)
  window.addEventListener('scroll', schedulePosition, true)
}
function close(restore: boolean): void {
  if (!open.value) return
  open.value = false
  cancelAnimationFrame(frame)
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', schedulePosition)
  window.removeEventListener('scroll', schedulePosition, true)
  if (restore) nextTick(() => trigger.value?.focus())
}
function toggle(): void {
  if (open.value) close(true)
  else void show()
}
function closeOther(event: Event): void {
  if ((event as CustomEvent<string>).detail !== id) close(false)
}
onMounted(() => document.addEventListener('ym:admin-filter-open', closeOther))
onBeforeUnmount(() => {
  close(false)
  document.removeEventListener('ym:admin-filter-open', closeOther)
})
</script>

<style scoped>
.ym-admin-filter-popover{display:inline-flex;min-width:0}.ym-admin-filter-popover__trigger{display:grid;grid-template-columns:22px auto minmax(0,1fr) 16px;align-items:center;gap:6px;min-height:44px;min-width:150px;border:1px solid var(--ym-admin-border-strong);border-radius:12px;padding:0 10px;color:var(--ym-admin-text);background:var(--ym-admin-surface-soft);font:inherit;cursor:pointer}.ym-admin-filter-popover__trigger>span{color:var(--ym-admin-accent-electric)}.ym-admin-filter-popover__trigger>b{font-size:12.5px}.ym-admin-filter-popover__trigger>small{overflow:hidden;color:var(--ym-admin-muted);font-size:11.5px;text-overflow:ellipsis;white-space:nowrap}.ym-admin-filter-popover__trigger>i{font-style:normal}.ym-admin-filter-popover__trigger.is-active{border-color:color-mix(in srgb,var(--ym-admin-accent-electric) 52%,var(--ym-admin-border-strong));box-shadow:inset 0 -2px color-mix(in srgb,var(--ym-admin-accent-electric) 42%,transparent)}.ym-admin-filter-popover__backdrop{position:fixed;inset:0;z-index:calc(var(--ym-admin-layer-tooltip,110) + 2);pointer-events:none}.ym-admin-filter-popover__panel{position:fixed;width:min(360px,calc(100vw - 24px));max-height:min(420px,calc(100dvh - 24px));overflow:auto;border:1px solid rgba(139,92,246,.34);border-radius:16px;color:#f8fafc;background:rgba(8,15,31,.99);box-shadow:0 24px 62px rgba(2,6,23,.46);pointer-events:auto}.ym-admin-filter-popover__panel.is-light{color:#172033;background:rgba(255,255,255,.99)}.ym-admin-filter-popover__panel>header{position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(139,92,246,.24);padding:10px 12px;background:inherit}.ym-admin-filter-popover__panel>header button{display:grid;width:34px;height:34px;place-items:center;border:1px solid rgba(139,92,246,.25);border-radius:10px;color:inherit;background:transparent;font-size:20px}.ym-admin-filter-popover__panel>div{padding:12px}.ym-admin-filter-enter-active,.ym-admin-filter-leave-active{transition:opacity .16s ease}.ym-admin-filter-enter-from,.ym-admin-filter-leave-to{opacity:0}@media(max-width:640px){.ym-admin-filter-popover{width:100%}.ym-admin-filter-popover__trigger{width:100%}.ym-admin-filter-popover__backdrop{display:flex;align-items:flex-end;background:rgba(2,6,23,.46);backdrop-filter:blur(2px);pointer-events:auto}.ym-admin-filter-popover__panel{position:relative;inset:auto!important;width:100%;max-height:72dvh;border-radius:20px 20px 0 0}}@media(prefers-reduced-motion:reduce){.ym-admin-filter-enter-active,.ym-admin-filter-leave-active{transition:none}}
.ym-admin-filter-popover__backdrop {
  z-index: 130;
}

@media (max-width: 640px) {
  .ym-admin-filter-popover__panel {
    position: fixed;
    inset: auto 0 0 !important;
  }
}
</style>
