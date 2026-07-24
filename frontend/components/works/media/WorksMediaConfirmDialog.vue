<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="ym-media-confirm"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      :aria-describedby="descriptionId"
      @mousedown.self="requestClose"
    >
      <section ref="panel" class="ym-media-confirm__panel" tabindex="-1" :aria-busy="busy">
        <div class="ym-media-confirm__icon" aria-hidden="true">!</div>
        <h2 :id="titleId">{{ copy.title }}</h2>
        <p :id="descriptionId">{{ copy.description }}</p>
        <div v-if="item" class="ym-media-confirm__item">
          <img v-if="previewUrl && item.kind === 'image'" :src="previewUrl" alt="" />
          <span v-else aria-hidden="true">{{ item.kind === 'video' ? '▶' : '▧' }}</span>
          <strong>{{ item.original_name }}</strong>
        </div>
        <div class="ym-media-confirm__actions">
          <button ref="cancelButton" type="button" :disabled="busy" @click="$emit('cancel')">
            {{ copy.keep }}
          </button>
          <button type="button" class="is-danger" :disabled="busy" @click="$emit('confirm')">
            {{ busy ? copy.removing : copy.remove }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps<{
  open: boolean
  busy: boolean
  locale: 'ar' | 'en'
  item: { id: number; original_name: string; kind: 'image' | 'video' } | null
  previewUrl: string
  returnFocusTo: HTMLElement | null
}>()

const emit = defineEmits<{
  cancel: []
  confirm: []
}>()

const panel = ref<HTMLElement | null>(null)
const cancelButton = ref<HTMLButtonElement | null>(null)
const titleId = 'ym-media-confirm-title'
const descriptionId = 'ym-media-confirm-description'
let previousBodyOverflow = ''

const copy = computed(() => props.locale === 'ar' ? {
  title: 'إزالة الوسيط من العمل؟',
  description: 'سيختفي هذا الوسيط من العمل وفق سياسة الحذف الحالية.',
  keep: 'إبقاء الوسيط',
  remove: 'إزالة من العمل',
  removing: 'جارٍ الإزالة…'
} : {
  title: 'Remove media from the work?',
  description: 'This media will disappear from the work under the current deletion policy.',
  keep: 'Keep media',
  remove: 'Remove from work',
  removing: 'Removing…'
})

watch(() => props.open, async open => {
  if (!import.meta.client) return
  if (open) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    cancelButton.value?.focus()
  } else {
    restorePage()
    nextTick(() => props.returnFocusTo?.focus())
  }
})

function requestClose() {
  if (!props.busy) emit('cancel')
}

function onKeydown(event: KeyboardEvent) {
  if (!props.open) return
  if (event.key === 'Escape') {
    if (!props.busy) {
      event.preventDefault()
      emit('cancel')
    }
    return
  }
  if (event.key !== 'Tab' || !panel.value) return
  const focusable = [...panel.value.querySelectorAll<HTMLElement>('button,[href],[tabindex]:not([tabindex="-1"])')]
    .filter(element => !element.hasAttribute('disabled'))
  if (!focusable.length) {
    event.preventDefault()
    panel.value.focus()
    return
  }
  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function restorePage() {
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = previousBodyOverflow
}

onBeforeUnmount(() => {
  if (import.meta.client && props.open) restorePage()
})
</script>

<style scoped>
.ym-media-confirm{position:fixed;inset:0;z-index:1750;display:grid;place-items:center;padding:18px;background:rgba(2,6,23,.68);backdrop-filter:blur(5px)}.ym-media-confirm__panel{display:grid;justify-items:center;width:min(100%,460px);padding:26px;border:1px solid rgba(244,63,94,.36);border-radius:19px;background:linear-gradient(155deg,rgba(15,23,42,.98),rgba(30,24,54,.98));color:#f8fafc;box-shadow:0 28px 80px rgba(0,0,0,.48);text-align:center}.ym-media-confirm__icon{display:grid;place-items:center;width:48px;height:48px;border-radius:15px;background:rgba(244,63,94,.14);color:#fb7185;font-size:25px;font-weight:900}.ym-media-confirm h2{margin:14px 0 7px;font-size:20px}.ym-media-confirm p{margin:0;color:#cbd5e1;font-size:14px;line-height:1.65}.ym-media-confirm__item{display:flex;align-items:center;gap:10px;width:100%;margin-top:18px;padding:10px;border:1px solid rgba(148,163,184,.17);border-radius:12px;background:rgba(2,6,23,.34);text-align:start}.ym-media-confirm__item img,.ym-media-confirm__item>span{width:54px;height:42px;flex:0 0 auto;border-radius:8px;object-fit:cover;background:#020617}.ym-media-confirm__item>span{display:grid;place-items:center;color:#a78bfa}.ym-media-confirm__item strong{min-width:0;overflow-wrap:anywhere;font-size:13.5px}.ym-media-confirm__actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;width:100%;margin-top:20px}.ym-media-confirm__actions button{min-height:44px;border:1px solid rgba(148,163,184,.28);border-radius:11px;background:rgba(148,163,184,.08);color:#f8fafc;font-weight:850}.ym-media-confirm__actions .is-danger{border-color:rgba(244,63,94,.48);background:linear-gradient(135deg,#e11d48,#f43f5e)}.ym-media-confirm__actions button:focus-visible{outline:3px solid rgba(34,211,238,.4);outline-offset:3px}.ym-media-confirm__actions button:disabled{opacity:.5;cursor:not-allowed}@media(max-width:520px){.ym-media-confirm{align-items:end;padding:0}.ym-media-confirm__panel{width:100%;border-radius:20px 20px 0 0;padding:24px 18px calc(24px + env(safe-area-inset-bottom))}}@media(max-width:360px){.ym-media-confirm__actions{grid-template-columns:1fr}}@media(prefers-reduced-motion:reduce){.ym-media-confirm{backdrop-filter:none}}
</style>
