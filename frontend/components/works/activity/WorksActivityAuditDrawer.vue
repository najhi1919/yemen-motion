<template>
  <Teleport to="body">
    <div
      v-if="open && item"
      class="audit-dialog-root"
      :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
      :dir="direction"
    >
      <button class="audit-overlay" type="button" :aria-label="t.close" @click="emit('close')" />
      <section class="drawer" role="dialog" aria-modal="true" :aria-labelledby="titleId">
        <header>
          <div><span>{{ t.readOnly }}</span><h2 :id="titleId">{{ eventLabel }}</h2><code dir="ltr">{{ item.id }}</code></div>
          <button ref="closeButton" type="button" :aria-label="t.close" @click="emit('close')">×</button>
        </header>
        <div class="content">
          <section>
            <h3>{{ t.event }}</h3>
            <dl>
              <div><dt>{{ t.id }}</dt><dd dir="ltr">{{ item.id }}</dd></div>
              <div><dt>{{ t.auditId }}</dt><dd dir="ltr">{{ item.audit_event_id }}</dd></div>
              <div><dt>{{ t.type }}</dt><dd><code dir="ltr">{{ item.event_type }}</code></dd></div>
              <div><dt>{{ t.group }}</dt><dd dir="ltr">{{ item.event_group }}</dd></div>
              <div><dt>{{ t.time }}</dt><dd><time :datetime="item.event_at">{{ formatDateTime(item.event_at) }}</time></dd></div>
              <div><dt>{{ t.action }}</dt><dd dir="ltr">{{ value(item.action) }}</dd></div>
              <div><dt>{{ t.outcome }}</dt><dd dir="ltr">{{ value(item.outcome) }}</dd></div>
              <div><dt>{{ t.severity }}</dt><dd dir="ltr">{{ value(item.severity) }}</dd></div>
            </dl>
          </section>
          <section>
            <h3>{{ t.actor }}</h3>
            <dl>
              <div><dt>{{ t.id }}</dt><dd dir="ltr">{{ item.actor?.id ?? '—' }}</dd></div>
              <div><dt>{{ t.name }}</dt><dd>{{ item.actor?.name ?? t.unavailable }}</dd></div>
              <div><dt>{{ t.role }}</dt><dd dir="ltr">{{ item.actor?.role ?? '—' }}</dd></div>
              <div><dt>{{ t.actorMissing }}</dt><dd>{{ yesNo(item.activity_flags.actor_missing) }}</dd></div>
            </dl>
          </section>
          <section>
            <h3>{{ t.target }}</h3>
            <dl>
              <div><dt>{{ t.type }}</dt><dd dir="ltr">{{ item.target.type }}</dd></div>
              <div><dt>{{ t.id }}</dt><dd dir="ltr">{{ item.target.id ?? '—' }}</dd></div>
              <div><dt>{{ t.scope }}</dt><dd dir="ltr">{{ definition?.target_scope ?? item.target.scope }}</dd></div>
            </dl>
          </section>
          <section>
            <h3>{{ t.work }}</h3>
            <dl>
              <div><dt>{{ t.id }}</dt><dd dir="ltr">{{ item.work?.id ?? '—' }}</dd></div>
              <div><dt>{{ t.title }}</dt><dd>{{ item.work?.title ?? t.unavailable }}</dd></div>
              <div><dt>slug</dt><dd><code dir="ltr">{{ item.work?.slug ?? '—' }}</code></dd></div>
              <div><dt>{{ t.status }}</dt><dd dir="ltr">{{ item.work?.status ?? '—' }}</dd></div>
              <div><dt>{{ t.visibility }}</dt><dd dir="ltr">{{ item.work?.visibility_status ?? '—' }}</dd></div>
              <div><dt>{{ t.media }}</dt><dd dir="ltr">{{ item.work?.media_type ?? '—' }}</dd></div>
              <div><dt>{{ t.workMissing }}</dt><dd>{{ yesNo(item.activity_flags.work_missing) }}</dd></div>
            </dl>
            <NuxtLink v-if="canOpenWork && item.work" class="open-work" :to="`/admin/works/all?work=${item.work.id}`">{{ t.openWork }}</NuxtLink>
          </section>
          <section>
            <h3>{{ t.flags }}</h3>
            <dl>
              <div><dt>{{ t.requiresWork }}</dt><dd>{{ yesNo(item.activity_flags.requires_work) }}</dd></div>
              <div><dt>{{ t.needsAttention }}</dt><dd>{{ yesNo(item.activity_flags.needs_attention) }}</dd></div>
              <div><dt>{{ t.actorMissing }}</dt><dd>{{ yesNo(item.activity_flags.actor_missing) }}</dd></div>
              <div><dt>{{ t.workMissing }}</dt><dd>{{ yesNo(item.activity_flags.work_missing) }}</dd></div>
            </dl>
          </section>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
type Locale = 'ar' | 'en'
interface AuditActivityItem {
  id: string; source: string; audit_event_id: number; event_type: string; event_key: string; event_group: string
  event_label_ar: string; event_label_en: string; event_at: string; severity: string | null; action: string | null; outcome: string | null
  actor: { id: number | null; name: string; role: string | null } | null
  target: { type: string; id: number | null; scope: string }
  work: { id: number; title: string; slug: string; status: string; visibility_status: string; media_type: string | null } | null
  activity_flags: { requires_work: boolean; needs_attention: boolean; actor_missing: boolean; work_missing: boolean }
}
interface EventCatalogEvent {
  event_type: string; event_key: string; event_group: string; label_ar: string; label_en: string
  target_scope: string; requires_work: boolean; needs_attention: boolean
}
const props = defineProps<{ open: boolean; item: AuditActivityItem | null; definition: EventCatalogEvent | null; locale: Locale; canOpenWork: boolean; returnFocus: HTMLElement | null }>()
const emit = defineEmits<{ close: [] }>()
const titleId = 'ym-works-audit-drawer-title'
const closeButton = ref<HTMLButtonElement | null>(null)
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
let previousOverflow = ''
const direction = computed(() => props.locale === 'ar' ? 'rtl' : 'ltr')
const eventLabel = computed(() => {
  if (props.definition) return props.locale === 'ar' ? props.definition.label_ar : props.definition.label_en
  return props.item ? (props.locale === 'ar' ? props.item.event_label_ar : props.item.event_label_en) : ''
})
const copy = {
  ar: { readOnly: 'تفاصيل آمنة للقراءة فقط', close: 'إغلاق التفاصيل', event: 'تعريف الحدث', id: 'المعرّف', auditId: 'معرّف التدقيق', type: 'النوع', group: 'المجموعة', time: 'الوقت', action: 'الإجراء', outcome: 'النتيجة', severity: 'الشدة', actor: 'الفاعل', name: 'الاسم', role: 'الدور', actorMissing: 'الفاعل مفقود', target: 'الهدف', scope: 'النطاق', work: 'العمل', title: 'العنوان', status: 'الحالة', visibility: 'الظهور', media: 'الوسائط', workMissing: 'العمل مفقود', flags: 'أعلام النشاط', requiresWork: 'يتطلب عملًا', needsAttention: 'يحتاج انتباهًا', openWork: 'فتح تفاصيل العمل', unavailable: 'غير متاح', yes: 'نعم', no: 'لا' },
  en: { readOnly: 'Safe read-only details', close: 'Close details', event: 'Event definition', id: 'ID', auditId: 'Audit event ID', type: 'Type', group: 'Group', time: 'Time', action: 'Action', outcome: 'Outcome', severity: 'Severity', actor: 'Actor', name: 'Name', role: 'Role', actorMissing: 'Actor missing', target: 'Target', scope: 'Scope', work: 'Work', title: 'Title', status: 'Status', visibility: 'Visibility', media: 'Media', workMissing: 'Work missing', flags: 'Activity flags', requiresWork: 'Requires work', needsAttention: 'Needs attention', openWork: 'Open work details', unavailable: 'Unavailable', yes: 'Yes', no: 'No' }
} as const
const t = computed(() => copy[props.locale])
function value(input: string | null): string { return input?.trim() || '—' }
function yesNo(input: boolean): string { return input ? t.value.yes : t.value.no }
function formatDateTime(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(props.locale === 'ar' ? 'ar-YE' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
function onKeydown(event: KeyboardEvent): void { if (event.key === 'Escape') emit('close') }
watch(() => props.open, async (open) => {
  if (open) {
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    closeButton.value?.focus()
  } else {
    document.body.style.overflow = previousOverflow
    document.removeEventListener('keydown', onKeydown)
    await nextTick()
    props.returnFocus?.focus()
  }
})
onBeforeUnmount(() => {
  document.body.style.overflow = previousOverflow
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.audit-dialog-root { position: fixed; inset: 0; z-index: 12000; isolation: isolate; display: flex; justify-content: flex-end; }
.audit-dialog-root.is-dark {
  --ym-text: #f0f6ff;
  --ym-muted: rgba(226, 232, 240, .92);
  --ym-control-bg: rgba(15, 23, 42, .92);
  --ym-card-border: rgba(148, 163, 184, .28);
  --ym-soft-border: rgba(148, 163, 184, .18);
  --ym-dropdown-bg: #0f172a;
  color-scheme: dark;
}
.audit-dialog-root.is-light {
  --ym-text: #171126;
  --ym-muted: rgba(45, 36, 64, .9);
  --ym-control-bg: rgba(250, 247, 255, .98);
  --ym-card-border: rgba(109, 40, 217, .34);
  --ym-soft-border: rgba(91, 33, 182, .24);
  --ym-dropdown-bg: #fff;
  color-scheme: light;
}
.audit-overlay { position: absolute; inset: 0; z-index: 0; border: 0; padding: 0; background: rgba(2, 6, 23, .72); cursor: default; }
.drawer { position: relative; z-index: 1; width: min(680px, 94vw); height: 100%; overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-dropdown-bg); color: var(--ym-text); box-shadow: -24px 0 70px rgba(2, 6, 23, .42); }
.drawer > header { position: sticky; top: 0; z-index: 2; display: flex; justify-content: space-between; gap: 1rem; padding: 1.25rem; border-bottom: 1px solid var(--ym-soft-border); background: var(--ym-dropdown-bg); color: var(--ym-text); }
header span, dt { color: var(--ym-muted); font-size: 12px; font-weight: 900; }
h2 { margin: .25rem 0; font-size: 1.25rem; }
header button { width: 2.5rem; height: 2.5rem; border: 1px solid var(--ym-soft-border); border-radius: 12px; background: var(--ym-control-bg); color: var(--ym-text); font-size: 1.4rem; cursor: pointer; }
.content { display: grid; gap: 1rem; padding: 1.25rem; }
.content section { padding: 1rem; border: 1px solid var(--ym-soft-border); border-radius: 18px; background: var(--ym-control-bg); color: var(--ym-text); }
h3 { margin: 0 0 .8rem; font-size: 1rem; }
dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; margin: 0; }
dl div { min-width: 0; padding: .65rem; border: 1px solid var(--ym-soft-border); border-radius: 12px; background: var(--ym-dropdown-bg); }
dd { margin: .22rem 0 0; overflow-wrap: anywhere; font-weight: 850; }
.open-work { display: inline-flex; margin-block-start: 1rem; padding: .65rem .8rem; border-radius: 11px; background: #8b5cf6; color: #fff; font-weight: 900; text-decoration: none; }
@media (max-width: 640px) { .drawer { width: 97vw; } dl { grid-template-columns: 1fr; } }
</style>
