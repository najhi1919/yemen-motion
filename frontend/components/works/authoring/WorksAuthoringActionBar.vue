<template>
  <aside
    class="ym-authoring-actions"
    :class="[
      `is-${tone}`,
      { 'is-create-ready': mode === 'create' && canSave, 'is-create-waiting': mode === 'create' && !canSave }
    ]"
    :aria-busy="saving || undefined"
  >
    <div aria-live="polite">
      <span class="ym-authoring-actions__signal" aria-hidden="true" />
      <div><strong>{{ statusTitle }}</strong><small v-if="statusCopy">{{ statusCopy }}</small></div>
    </div>
    <div class="ym-authoring-actions__buttons">
      <button v-if="mode === 'edit'" type="button" class="is-secondary" :disabled="saving || !dirty || readonly" @click="$emit('reset')">{{ text.discard }}</button>
      <button v-else type="button" class="is-secondary" :disabled="saving" @click="$emit('back')">{{ text.cancel }}</button>
      <button type="button" class="is-primary" :disabled="saving || !canSave" @click="$emit('save')">
        {{ saving ? text.saving : mode === 'create' ? text.create : text.save }}
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'
const props = defineProps<{
  mode: 'create' | 'edit'; locale: 'ar' | 'en'; saving: boolean; dirty: boolean
  dirtyCount: number; canSave: boolean; readonly: boolean; conflict: boolean
  pendingElsewhere: boolean
  tone: 'idle' | 'dirty' | 'saving' | 'success' | 'error' | 'conflict' | 'readonly'
}>()
defineEmits<{ save: []; reset: []; back: [] }>()
const text = computed(() => props.locale === 'ar' ? {
  discard:'إلغاء التغييرات',cancel:'إلغاء والعودة',saving:'جارٍ الحفظ…',create:'إنشاء المسودة',save:'حفظ التغييرات',
  readonly:'قراءة فقط',readonlyCopy:'حالة العمل الحالية لا تسمح بحفظ البيانات.',
  conflict:'تعارض مع نسخة أحدث',conflictCopy:'راجع رسالة التعارض قبل محاولة الحفظ مجددًا.',
  sectionPending:'بيانات العمل محفوظة',sectionPendingCopy:'توجد تغييرات مستقلة في التصنيف أوالوسائط وتحتاج حفظًا من قسمها.',
  saved:'تم الحفظ',savedCopy:'بيانات العمل مطابقة لأحدث نسخة محفوظة.',
  createReady:'جاهز لإنشاء المسودة',createMissing:'ينقصك عنوان العمل',createMissingCopy:'اكتب عنوان العمل لتفعيل إنشاء المسودة.',
  dirty:'تغييرات غير محفوظة',clean:'لا توجد تغييرات',cleanCopy:'بيانات العمل مطابقة لآخر نسخة محفوظة.',
  dirtyCopy:(count:string)=>`لديك ${count} تغييرات غير محفوظة.`
} : {
  discard:'Discard changes',cancel:'Cancel and return',saving:'Saving…',create:'Create draft',save:'Save changes',
  readonly:'Read only',readonlyCopy:'The current work state does not allow saving data.',
  conflict:'Newer version conflict',conflictCopy:'Review the conflict message before saving again.',
  sectionPending:'Work data is saved',sectionPendingCopy:'Independent taxonomy or media changes still need saving in their section.',
  saved:'Saved',savedCopy:'Work data matches the latest saved version.',
  createReady:'Ready to create the draft',createMissing:'The work title is missing',createMissingCopy:'Enter a work title to enable draft creation.',
  dirty:'Unsaved changes',clean:'No changes',cleanCopy:'Work data matches the latest saved version.',
  dirtyCopy:(count:string)=>`You have ${count} unsaved changes.`
})
const statusTitle = computed(() => {
  if (props.saving) return text.value.saving
  if (props.readonly) return text.value.readonly
  if (props.conflict) return text.value.conflict
  if (props.mode === 'create') return props.canSave ? text.value.createReady : text.value.createMissing
  if (props.dirty) return text.value.dirty
  if (props.pendingElsewhere) return text.value.sectionPending
  if (props.tone === 'success') return text.value.saved
  return text.value.clean
})
const statusCopy = computed(() => {
  if (props.readonly) return text.value.readonlyCopy
  if (props.conflict) return text.value.conflictCopy
  if (props.mode === 'create') return props.canSave ? '' : text.value.createMissingCopy
  if (props.dirty) return text.value.dirtyCopy(formatYmNumber(props.dirtyCount, props.locale))
  if (props.pendingElsewhere) return text.value.sectionPendingCopy
  if (props.tone === 'success') return text.value.savedCopy
  return text.value.cleanCopy
})
</script>

<style scoped>
.ym-authoring-actions{position:sticky;z-index:20;inset-block-end:max(12px,env(safe-area-inset-bottom));box-sizing:border-box;display:flex;inline-size:100%;max-inline-size:100%;min-width:0;align-items:center;justify-content:space-between;gap:18px;overflow:hidden;border:1px solid color-mix(in srgb,var(--aw-electric) 34%,var(--aw-border));border-radius:18px;padding:12px 14px;background:color-mix(in srgb,var(--aw-surface-strong) 94%,transparent);box-shadow:0 -14px 34px rgba(2,6,23,.16),0 18px 42px rgba(2,6,23,.15),inset 0 1px 0 var(--aw-highlight);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)}
.ym-authoring-actions>div{display:flex;min-width:0;align-items:center;gap:10px}.ym-authoring-actions__signal{flex:0 0 auto;width:10px;height:10px;border-radius:50%;background:var(--aw-muted);box-shadow:0 0 0 5px color-mix(in srgb,var(--aw-muted) 10%,transparent)}
.ym-authoring-actions.is-dirty .ym-authoring-actions__signal,.ym-authoring-actions.is-create-waiting .ym-authoring-actions__signal{background:var(--aw-amber)}.ym-authoring-actions.is-success .ym-authoring-actions__signal,.ym-authoring-actions.is-create-ready .ym-authoring-actions__signal{background:var(--aw-emerald);box-shadow:0 0 0 5px color-mix(in srgb,var(--aw-emerald) 11%,transparent)}.ym-authoring-actions.is-error .ym-authoring-actions__signal,.ym-authoring-actions.is-conflict .ym-authoring-actions__signal{background:var(--aw-rose)}
.ym-authoring-actions>div>div{display:grid;min-width:0;gap:2px}.ym-authoring-actions strong{font-size:14px;line-height:1.4}.ym-authoring-actions.is-create-ready strong{color:var(--aw-emerald)}.ym-authoring-actions small{color:var(--aw-muted);font-size:12.75px;line-height:1.45}.ym-authoring-actions__buttons{display:flex;flex:0 0 auto;gap:8px}.ym-authoring-actions button{min-height:44px;border-radius:12px;padding:0 16px;font-size:13.5px;font-weight:850;cursor:pointer}.ym-authoring-actions button:disabled{cursor:not-allowed;opacity:.48}.is-secondary{border:1px solid var(--aw-border);color:var(--aw-text);background:var(--aw-control)}.is-primary{border:0;color:#fff;background:linear-gradient(135deg,var(--aw-violet),var(--aw-magenta));box-shadow:0 10px 22px color-mix(in srgb,var(--aw-violet) 24%,transparent)}button:focus-visible{outline:3px solid color-mix(in srgb,var(--aw-electric) 42%,transparent);outline-offset:2px}
@media(max-width:700px){.ym-authoring-actions{position:sticky;align-items:stretch;flex-direction:column;gap:10px;padding:11px 12px}.ym-authoring-actions__buttons{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.ym-authoring-actions button{width:100%;padding-inline:10px}}
@media(max-width:380px){.ym-authoring-actions__buttons{grid-template-columns:1fr}}
</style>
