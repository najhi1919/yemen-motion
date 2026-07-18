<template>
  <div class="wrap">
    <table>
      <thead>
        <tr>
          <th>
            {{ t.event }}
            <span class="sorts">
              <button type="button" @click="emit('sort', 'event_type')">{{ t.type }} {{ indicator('event_type') }}</button>
              <button type="button" @click="emit('sort', 'audit_event_id')">ID {{ indicator('audit_event_id') }}</button>
            </span>
          </th>
          <th>{{ t.group }}</th>
          <th><button type="button" @click="emit('sort', 'event_at')">{{ t.time }} {{ indicator('event_at') }}</button></th>
          <th><button type="button" @click="emit('sort', 'actor_name')">{{ t.actor }} {{ indicator('actor_name') }}</button></th>
          <th>{{ t.target }}</th>
          <th>
            {{ t.work }}
            <span class="sorts">
              <button type="button" @click="emit('sort', 'work_id')">ID {{ indicator('work_id') }}</button>
              <button type="button" @click="emit('sort', 'work_title')">{{ t.title }} {{ indicator('work_title') }}</button>
            </span>
          </th>
          <th>{{ t.result }}</th>
          <th>{{ t.attention }}</th>
          <th>{{ t.details }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id" :class="{ attention: item.activity_flags.needs_attention }">
          <td>
            <strong :dir="textDirection(label(item))">{{ label(item) }}</strong>
            <code dir="ltr">{{ item.event_type }}</code>
            <small dir="ltr">#{{ item.audit_event_id }}</small>
          </td>
          <td><span class="badge">{{ groupLabel(item.event_group) }}</span></td>
          <td><time :datetime="item.event_at">{{ formatDateTime(item.event_at) }}</time></td>
          <td>
            <template v-if="item.actor">
              <strong :dir="textDirection(item.actor.name)">{{ item.actor.name }}</strong>
              <small dir="ltr">#{{ item.actor.id ?? '—' }} · {{ item.actor.role ?? '—' }}</small>
            </template>
            <span v-else class="missing">{{ t.actorMissing }}</span>
          </td>
          <td>
            <code dir="ltr">{{ item.target.type }}#{{ item.target.id ?? '—' }}</code>
            <small dir="ltr">{{ definition(item)?.target_scope ?? item.target.scope }}</small>
          </td>
          <td>
            <template v-if="item.work">
              <strong :dir="textDirection(item.work.title)">{{ item.work.title }}</strong>
              <code dir="ltr">{{ item.work.slug }}</code>
              <small dir="ltr">#{{ item.work.id }} · {{ item.work.status }} · {{ item.work.visibility_status }}</small>
            </template>
            <span v-else class="missing">{{ item.activity_flags.requires_work || item.activity_flags.work_missing ? t.workMissing : t.generalEvent }}</span>
          </td>
          <td>
            <span class="badge">{{ item.outcome || t.unavailable }}</span>
            <span class="badge severity">{{ item.severity || t.unavailable }}</span>
          </td>
          <td><span class="badge" :class="{ active: item.activity_flags.needs_attention }">{{ item.activity_flags.needs_attention ? t.yes : t.no }}</span></td>
          <td>
            <button type="button" class="details" :aria-label="`${t.openDetails}: ${label(item)}`" @click="open(item, $event)">
              {{ t.openDetails }}
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type Locale = 'ar' | 'en'
type AuditSortKey = 'event_at' | 'audit_event_id' | 'event_type' | 'actor_name' | 'work_id' | 'work_title'
type SortDirection = 'asc' | 'desc'
interface AuditActivityItem {
  id: string; source: string; audit_event_id: number; event_type: string; event_key: string; event_group: string
  event_label_ar: string; event_label_en: string; event_at: string; severity: string | null; action: string | null; outcome: string | null
  actor: { id: number | null; name: string; role: string | null } | null
  target: { type: string; id: number | null; scope: string }
  work: { id: number; title: string; slug: string; status: string; visibility_status: string; media_type: string | null } | null
  activity_flags: { requires_work: boolean; needs_attention: boolean; actor_missing: boolean; work_missing: boolean }
}
interface EventCatalogGroup { key: string; label_ar: string; label_en: string }
interface EventCatalogEvent {
  event_type: string; event_key: string; event_group: string; label_ar: string; label_en: string
  target_scope: string; requires_work: boolean; needs_attention: boolean
}

const props = defineProps<{ items: AuditActivityItem[]; locale: Locale; groups: EventCatalogGroup[]; events: EventCatalogEvent[]; sort: AuditSortKey; direction: SortDirection }>()
const emit = defineEmits<{ sort: [key: AuditSortKey]; details: [item: AuditActivityItem, trigger: HTMLElement | null] }>()
const copy = {
  ar: { event: 'الحدث', type: 'النوع', group: 'المجموعة', time: 'وقت الحدث', actor: 'الفاعل', target: 'الهدف', work: 'العمل', title: 'العنوان', result: 'النتيجة والشدة', attention: 'يحتاج انتباهًا', details: 'التفاصيل', openDetails: 'عرض التفاصيل', actorMissing: 'فاعل غير متاح', workMissing: 'العمل غير متاح', generalEvent: 'حدث عام', unavailable: 'غير متاح', yes: 'نعم', no: 'لا' },
  en: { event: 'Event', type: 'Type', group: 'Group', time: 'Event time', actor: 'Actor', target: 'Target', work: 'Work', title: 'Title', result: 'Outcome and severity', attention: 'Needs attention', details: 'Details', openDetails: 'View details', actorMissing: 'Actor unavailable', workMissing: 'Work unavailable', generalEvent: 'General event', unavailable: 'Unavailable', yes: 'Yes', no: 'No' }
} as const
const t = computed(() => copy[props.locale])

function definition(item: AuditActivityItem): EventCatalogEvent | undefined { return props.events.find(event => event.event_type === item.event_type) }
function label(item: AuditActivityItem): string {
  const event = definition(item)
  return event ? (props.locale === 'ar' ? event.label_ar : event.label_en) : props.locale === 'ar' ? item.event_label_ar : item.event_label_en
}
function groupLabel(key: string): string {
  const group = props.groups.find(item => item.key === key)
  return group ? (props.locale === 'ar' ? group.label_ar : group.label_en) : key
}
function textDirection(value: string): 'rtl' | 'ltr' { return /[\u0600-\u06FF]/.test(value) ? 'rtl' : 'ltr' }
function formatDateTime(value: string): string {
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(props.locale === 'ar' ? 'ar-YE' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
}
function indicator(key: AuditSortKey): string { return props.sort !== key ? '↕' : props.direction === 'asc' ? '↑' : '↓' }
function open(item: AuditActivityItem, event: MouseEvent): void {
  emit('details', item, event.currentTarget instanceof HTMLElement ? event.currentTarget : null)
}
</script>

<style scoped>
.wrap { overflow-x: auto; }
table { width: 100%; min-width: 1380px; border-collapse: collapse; }
th, td { padding: .85rem; border-bottom: 1px solid var(--ym-soft-border); text-align: start; vertical-align: top; }
th { background: var(--ym-table-header-bg); color: var(--ym-muted); font-size: 12px; font-weight: 950; }
th button { border: 0; background: transparent; color: inherit; cursor: pointer; font: inherit; }
td { color: var(--ym-text); font-size: 13px; }
td > strong, td > code, td > small { display: block; margin-block-end: .28rem; }
code, small, .missing { color: var(--ym-muted); }
tr.attention td { background: rgba(245, 158, 11, .045); }
.sorts { display: flex; flex-wrap: wrap; gap: .3rem; margin-block-start: .3rem; }
.sorts button { padding: .2rem .35rem; border-radius: 7px; background: var(--ym-control-bg); }
.badge { display: inline-flex; margin: .1rem; padding: .28rem .48rem; border: 1px solid var(--ym-soft-border); border-radius: 999px; background: var(--ym-control-bg); font-size: 11px; font-weight: 900; }
.badge.active { border-color: rgba(245, 158, 11, .5); color: #f59e0b; }
.severity { color: #38bdf8; }
.missing { font-weight: 800; }
.details { border: 1px solid var(--ym-soft-border); border-radius: 10px; background: var(--ym-control-bg); color: var(--ym-text); padding: .5rem .65rem; cursor: pointer; font-weight: 900; }
</style>
