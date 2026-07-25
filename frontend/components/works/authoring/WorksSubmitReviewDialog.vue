<template>
  <Teleport to="body">
    <Transition name="ym-submit-dialog">
      <div v-if="open" class="ym-submit-dialog" @mousedown.self="requestClose">
        <section
          ref="dialog"
          role="dialog"
          aria-modal="true"
          aria-labelledby="ym-submit-dialog-title"
          aria-describedby="ym-submit-dialog-copy"
          :dir="locale === 'ar' ? 'rtl' : 'ltr'"
          @keydown="trapFocus"
        >
          <header>
            <span aria-hidden="true">✓</span>
            <div><p>{{ text.kicker }}</p><h2 id="ym-submit-dialog-title">{{ text.title }}</h2></div>
            <button ref="closeButton" type="button" :disabled="busy" :aria-label="text.cancel" @click="requestClose">×</button>
          </header>
          <main>
            <p id="ym-submit-dialog-copy">{{ text.copy }}</p>
            <dl>
              <div><dt>{{ text.work }}</dt><dd>{{ workTitle }}</dd></div>
              <div><dt>{{ text.warnings }}</dt><dd dir="ltr">{{ formatYmNumber(warningsCount, locale) }}</dd></div>
              <div><dt>{{ text.media }}</dt><dd>{{ mediaReady ? text.ready : text.review }}</dd></div>
              <div><dt>{{ text.category }}</dt><dd>{{ categoryReady ? text.ready : text.review }}</dd></div>
              <div v-if="resubmission && reviewerName"><dt>{{ text.reviewer }}</dt><dd>{{ reviewerName }}</dd></div>
            </dl>
          </main>
          <footer>
            <button type="button" :disabled="busy" @click="requestClose">{{ text.cancel }}</button>
            <button type="button" class="is-primary" :disabled="busy" @click="$emit('confirm')">
              {{ busy ? text.submitting : text.confirm }}
            </button>
          </footer>
        </section>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'
const props = defineProps<{
  open:boolean; busy:boolean; locale:'ar'|'en'; workTitle:string; warningsCount:number
  mediaReady:boolean; categoryReady:boolean; resubmission:boolean; reviewerName?:string|null
}>()
const emit = defineEmits<{ close:[]; confirm:[] }>()
const dialog = ref<HTMLElement|null>(null)
const closeButton = ref<HTMLButtonElement|null>(null)
let previousFocus: HTMLElement|null = null
let previousBodyOverflow = ''
const text = computed(() => props.locale === 'ar' ? {
  kicker:'تأكيد الانتقال',title:'إرسال العمل للمراجعة؟',
  copy:'بعد الإرسال لن تتمكن من تعديل بيانات العمل حتى يعيده المراجع بطلب تعديلات.',
  work:'العمل',warnings:'التوصيات',media:'الوسائط',category:'التصنيف',reviewer:'المراجع الحالي',
  ready:'جاهز',review:'يحتاج مراجعة',cancel:'إلغاء',confirm:'تأكيد الإرسال',submitting:'جارٍ الإرسال…'
} : {
  kicker:'Confirm transition',title:'Submit work for review?',
  copy:'After submission, you cannot edit the work until a reviewer requests changes.',
  work:'Work',warnings:'Recommendations',media:'Media',category:'Category',reviewer:'Current reviewer',
  ready:'Ready',review:'Needs review',cancel:'Cancel',confirm:'Confirm submission',submitting:'Submitting…'
})
watch(() => props.open, async open => {
  if (open) {
    previousFocus = document.activeElement as HTMLElement
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    await nextTick()
    closeButton.value?.focus()
  } else {
    document.body.style.overflow = previousBodyOverflow
    if (previousFocus?.isConnected) previousFocus.focus()
  }
})
onBeforeUnmount(() => { document.body.style.overflow = previousBodyOverflow })
function requestClose(){ if (!props.busy) emit('close') }
function trapFocus(event:KeyboardEvent){
  if (event.key === 'Escape') { event.preventDefault(); requestClose(); return }
  if (event.key !== 'Tab' || !dialog.value) return
  const nodes=[...dialog.value.querySelectorAll<HTMLElement>('button:not(:disabled),a[href],[tabindex]:not([tabindex=\"-1\"])')]
  if (!nodes.length) return
  const first=nodes[0]!,last=nodes[nodes.length-1]!
  if (event.shiftKey && document.activeElement===first){event.preventDefault();last.focus()}
  else if(!event.shiftKey && document.activeElement===last){event.preventDefault();first.focus()}
}
</script>

<style scoped>
:global(.ym-submit-dialog){position:fixed;z-index:1650;inset:0;display:grid;place-items:center;padding:18px;background:rgba(2,6,23,.64);backdrop-filter:blur(8px)}
:global(.ym-submit-dialog>section){display:grid;width:min(100%,560px);max-height:min(90dvh,700px);overflow:hidden;border:1px solid rgba(139,92,246,.35);border-radius:20px;color:#f8fafc;background:rgba(15,23,42,.98);box-shadow:0 28px 80px rgba(2,6,23,.48)}
:global(.ym-submit-dialog header),:global(.ym-submit-dialog footer){display:flex;align-items:center;gap:12px;padding:16px 18px}:global(.ym-submit-dialog header){border-block-end:1px solid rgba(148,163,184,.16)}:global(.ym-submit-dialog header>span){display:grid;width:40px;height:40px;place-items:center;border-radius:12px;background:rgba(139,92,246,.18)}:global(.ym-submit-dialog header>div){flex:1}:global(.ym-submit-dialog h2),:global(.ym-submit-dialog header p){margin:0}:global(.ym-submit-dialog header p){color:#c4b5fd;font-size:12px;font-weight:800}:global(.ym-submit-dialog h2){font-size:22px;line-height:1.35}:global(.ym-submit-dialog header button){width:40px;height:40px;border:1px solid rgba(148,163,184,.2);border-radius:11px;color:#fff;background:rgba(255,255,255,.05);font-size:24px}
:global(.ym-submit-dialog main){overflow:auto;padding:18px}:global(.ym-submit-dialog main>p){margin:0 0 16px;color:#cbd5e1;line-height:1.7}:global(.ym-submit-dialog dl){display:grid;gap:1px;border:1px solid rgba(148,163,184,.16);border-radius:14px;overflow:hidden}:global(.ym-submit-dialog dl>div){display:flex;justify-content:space-between;gap:12px;padding:10px 12px;background:rgba(255,255,255,.04)}:global(.ym-submit-dialog dt){color:#94a3b8}:global(.ym-submit-dialog dd){margin:0;font-weight:800}
:global(.ym-submit-dialog footer){justify-content:flex-end;border-block-start:1px solid rgba(148,163,184,.16)}:global(.ym-submit-dialog footer button){min-height:44px;border:1px solid rgba(148,163,184,.22);border-radius:11px;padding:0 16px;color:#fff;background:rgba(255,255,255,.05);font-weight:850}:global(.ym-submit-dialog footer .is-primary){border:0;background:linear-gradient(135deg,#7c3aed,#ec4899)}
:global(.ym-submit-dialog button:focus-visible){outline:3px solid rgba(34,211,238,.5);outline-offset:2px}.ym-submit-dialog-enter-active,.ym-submit-dialog-leave-active{transition:opacity .2s ease}.ym-submit-dialog-enter-from,.ym-submit-dialog-leave-to{opacity:0}
@media(max-width:600px){:global(.ym-submit-dialog){padding:0;align-items:end}:global(.ym-submit-dialog>section){width:100%;max-height:100dvh;border-radius:20px 20px 0 0}}
@media(prefers-reduced-motion:reduce){.ym-submit-dialog-enter-active,.ym-submit-dialog-leave-active{transition:none}}
</style>
