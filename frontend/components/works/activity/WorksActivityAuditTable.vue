<template>
  <div class="ym-activity-list" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
    <div class="ym-activity-list__desktop">
      <table>
        <colgroup>
          <col class="is-sequence" /><col class="is-event" /><col class="is-context" /><col class="is-actor" />
          <col class="is-target-work" /><col class="is-date" /><col class="is-flags" /><col class="is-actions" />
        </colgroup>
        <thead><tr>
          <th class="is-sequence">#</th>
          <th class="is-event" :aria-sort="ariaSort('event_type')"><button type="button" @click="$emit('sort','event_type')">{{ t.event }} {{ indicator('event_type') }}</button></th>
          <th class="is-context">{{ t.context }}</th>
          <th class="is-actor" :aria-sort="ariaSort('actor_name')"><button type="button" @click="$emit('sort','actor_name')">{{ t.actor }} {{ indicator('actor_name') }}</button></th>
          <th class="is-target-work" :aria-sort="ariaSort('work_title')"><button type="button" @click="$emit('sort','work_title')">{{ t.targetWork }} {{ indicator('work_title') }}</button></th>
          <th class="is-date" :aria-sort="ariaSort('event_at')"><button type="button" @click="$emit('sort','event_at')">{{ t.time }} {{ indicator('event_at') }}</button></th>
          <th class="is-flags">{{ t.flags }}</th><th class="is-actions">{{ t.actions }}</th>
        </tr></thead>
        <tbody>
          <tr v-for="(item,index) in items" :key="item.id" :class="{ attention:item.activity_flags.needs_attention }">
            <td class="is-sequence" dir="ltr">{{ number(sequence(index)) }}</td>
            <td class="is-event">
              <WorksIndexInfoPopover :label="t.eventInfo" :hint="t.openInfo" :close-label="t.close">
                <template #trigger><span class="primary"><strong>{{ label(item) }}</strong><small>{{ eventHint(item) }}</small></span></template>
                <dl class="popover-list"><div><dt>{{ t.type }}</dt><dd dir="ltr">{{ safe(item.event_type) }}</dd></div><div><dt>{{ t.key }}</dt><dd dir="ltr">{{ safe(item.event_key) }}</dd></div><div><dt>{{ t.auditId }}</dt><dd dir="ltr">{{ number(item.audit_event_id) }}</dd></div><div><dt>{{ t.source }}</dt><dd dir="ltr">{{ safe(item.source) }}</dd></div><div><dt>{{ t.group }}</dt><dd>{{ groupLabel(item.event_group) }}</dd></div><div><dt>{{ t.requiresWork }}</dt><dd>{{ yesNo(item.activity_flags.requires_work) }}</dd></div></dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-context">
              <WorksIndexInfoPopover :label="t.contextInfo" :hint="t.openInfo" :close-label="t.close">
                <template #trigger>
                  <span
                    class="primary is-result"
                    :class="{
                      'is-success': normalized(item.outcome) === 'success',
                      'is-alert': Boolean(item.outcome) && normalized(item.outcome) !== 'success'
                    }"
                  >
                    <strong>{{ outcomeLabel(item.outcome) }}</strong>
                    <small v-if="showTechnicalOutcome(item.outcome)" dir="ltr">{{ value(item.outcome) }}</small>
                  </span>
                </template>
                <dl class="popover-list"><div><dt>{{ t.group }}</dt><dd>{{ groupLabel(item.event_group) }}</dd></div><div><dt>{{ t.action }}</dt><dd dir="ltr">{{ value(item.action) }}</dd></div><div><dt>{{ t.outcome }}</dt><dd dir="ltr">{{ value(item.outcome) }}</dd></div><div><dt>{{ t.severity }}</dt><dd dir="ltr">{{ value(item.severity) }}</dd></div><div><dt>{{ t.scope }}</dt><dd dir="ltr">{{ safe(definition(item)?.target_scope ?? item.target.scope) }}</dd></div></dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-actor">
              <WorksIndexInfoPopover :label="t.actorInfo" :hint="t.openInfo" :close-label="t.close">
                <template #trigger>
                  <span class="primary">
                    <strong>{{ actorName(item) }}</strong>
                    <small>
                      <span v-if="props.locale === 'ar' && isSuperAdmin(item.actor?.role) && showLocalizedActorRole(item)">{{ actorRoleLabel(item.actor?.role) }}</span>
                      <code v-if="item.actor?.role" dir="ltr">{{ technicalRole(item.actor.role) }}</code>
                      <span v-else>{{ item.activity_flags.actor_missing ? t.actorMissing : t.unavailable }}</span>
                    </small>
                  </span>
                </template>
                <dl class="popover-list"><div><dt>{{ t.id }}</dt><dd dir="ltr">{{ item.actor?.id===null||item.actor?.id===undefined?t.unavailable:number(item.actor.id) }}</dd></div><div><dt>{{ t.name }}</dt><dd>{{ item.actor?.name||t.unavailable }}</dd></div><div><dt>{{ t.role }}</dt><dd dir="ltr">{{ item.actor?.role||t.unavailable }}</dd></div><div><dt>{{ t.actorMissing }}</dt><dd>{{ yesNo(item.activity_flags.actor_missing) }}</dd></div></dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-target-work">
              <WorksIndexInfoPopover :label="t.targetInfo" :hint="t.openInfo" :close-label="t.close">
                <template #trigger><span class="primary"><strong>{{ item.work?.title || t.generalEvent }}</strong><small>{{ item.work ? mediaLabel(item.work.media_type) : targetLabel(item.target.type) }}</small></span></template>
                <dl class="popover-list"><div><dt>{{ t.targetType }}</dt><dd dir="ltr">{{ safe(item.target.type) }}</dd></div><div><dt>{{ t.targetId }}</dt><dd dir="ltr">{{ item.target.id===null?t.unavailable:number(item.target.id) }}</dd></div><div><dt>{{ t.scope }}</dt><dd dir="ltr">{{ safe(item.target.scope) }}</dd></div><div><dt>{{ t.workId }}</dt><dd dir="ltr">{{ item.work?number(item.work.id):t.unavailable }}</dd></div><div><dt>{{ t.title }}</dt><dd>{{ item.work?.title||t.unavailable }}</dd></div><div><dt>{{ t.slug }}</dt><dd dir="ltr">{{ safe(item.work?.slug||t.unavailable) }}</dd></div><div><dt>{{ t.status }}</dt><dd dir="ltr">{{ safe(item.work?.status||t.unavailable) }}</dd></div><div><dt>{{ t.visibility }}</dt><dd dir="ltr">{{ safe(item.work?.visibility_status||t.unavailable) }}</dd></div><div><dt>{{ t.media }}</dt><dd>{{ mediaLabel(item.work?.media_type) }}</dd></div><div><dt>{{ t.workMissing }}</dt><dd>{{ yesNo(item.activity_flags.work_missing) }}</dd></div></dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-date"><time :datetime="item.event_at"><strong dir="ltr">{{ date(item.event_at) }}</strong><small dir="ltr">{{ time(item.event_at) }}</small></time></td>
            <td class="is-flags">
              <WorksIndexInfoPopover :label="t.flagsInfo" :hint="t.openInfo" :close-label="t.close">
                <template #trigger><span class="flag-trigger"><i v-if="item.activity_flags.needs_attention" :aria-label="t.attention" :title="t.attention">!</i><i v-if="item.activity_flags.actor_missing||item.activity_flags.work_missing" :aria-label="t.missing" :title="t.missing">?</i><small v-if="!item.activity_flags.needs_attention&&!item.activity_flags.actor_missing&&!item.activity_flags.work_missing">{{ t.clear }}</small></span></template>
                <dl class="popover-list"><div><dt>{{ t.attention }}</dt><dd>{{ yesNo(item.activity_flags.needs_attention) }}</dd></div><div><dt>{{ t.actorMissing }}</dt><dd>{{ yesNo(item.activity_flags.actor_missing) }}</dd></div><div><dt>{{ t.workMissing }}</dt><dd>{{ yesNo(item.activity_flags.work_missing) }}</dd></div><div><dt>{{ t.requiresWork }}</dt><dd>{{ yesNo(item.activity_flags.requires_work) }}</dd></div></dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-actions">
              <button type="button" class="details" :aria-label="`${t.openDetails}: ${label(item)}`" :title="t.openDetails" @click="open(item,$event)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.6-6 9.5-6 9.5 6 9.5 6-3.6 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="ym-activity-list__mobile">
      <article v-for="(item,index) in items" :key="item.id">
        <header><span class="sequence" dir="ltr">#{{ number(sequence(index)) }}</span><div><strong>{{ label(item) }}</strong><small>{{ groupLabel(item.event_group) }}</small></div><b v-if="item.activity_flags.needs_attention">!</b></header>
        <dl><div><dt>{{ t.actor }}</dt><dd>{{ actorName(item) }}</dd></div><div><dt>{{ t.work }}</dt><dd>{{ item.work?.title||t.generalEvent }}</dd></div><div><dt>{{ t.time }}</dt><dd dir="ltr">{{ date(item.event_at) }} · {{ time(item.event_at) }}</dd></div></dl>
        <footer><span>{{ outcomeLabel(item.outcome) }}</span><button type="button" class="details" :aria-label="`${t.openDetails}: ${label(item)}`" :title="t.openDetails" @click="open(item,$event)">{{ t.openDetails }}</button></footer>
      </article>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WorksIndexInfoPopover from '~/components/works/index/WorksIndexInfoPopover.vue'
import { formatYmDate, formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'
type Locale='ar'|'en';type AuditSortKey='event_at'|'audit_event_id'|'event_type'|'actor_name'|'work_id'|'work_title';type SortDirection='asc'|'desc'
interface AuditActivityItem{id:string;source:string;audit_event_id:number;event_type:string;event_key:string;event_group:string;event_label_ar:string;event_label_en:string;event_at:string;severity:string|null;action:string|null;outcome:string|null;actor:{id:number|null;name:string;role:string|null}|null;target:{type:string;id:number|null;scope:string};work:{id:number;title:string;slug:string;status:string;visibility_status:string;media_type:string|null}|null;activity_flags:{requires_work:boolean;needs_attention:boolean;actor_missing:boolean;work_missing:boolean}}
interface EventCatalogGroup{key:string;label_ar:string;label_en:string}interface EventCatalogEvent{event_type:string;event_key:string;event_group:string;label_ar:string;label_en:string;target_scope:string;requires_work:boolean;needs_attention:boolean}
const props=defineProps<{items:AuditActivityItem[];locale:Locale;groups:EventCatalogGroup[];events:EventCatalogEvent[];sort:AuditSortKey;direction:SortDirection;currentPage:number;perPage:number}>()
const emit=defineEmits<{sort:[key:AuditSortKey];details:[item:AuditActivityItem,trigger:HTMLElement|null]}>()
const copy={ar:{event:'الحدث',context:'السياق والنتيجة',actor:'الفاعل',targetWork:'الهدف والعمل',time:'وقت الحدث',flags:'المؤشرات',actions:'الإجراءات',eventInfo:'تفاصيل الحدث',contextInfo:'تفاصيل السياق',actorInfo:'تفاصيل الفاعل',targetInfo:'تفاصيل الهدف والعمل',flagsInfo:'تفاصيل المؤشرات',openInfo:'عرض المعلومات',close:'إغلاق',type:'نوع الحدث',key:'مفتاح الحدث',auditId:'رقم التدقيق',source:'المصدر',group:'المجموعة',requiresWork:'يتطلب عملًا',action:'الإجراء',outcome:'النتيجة',severity:'الشدة',scope:'النطاق',id:'المعرّف',name:'الاسم',role:'الدور',actorMissing:'الفاعل مفقود',targetType:'نوع الهدف',targetId:'معرّف الهدف',workId:'معرّف العمل',title:'العنوان',slug:'المعرّف النصي',status:'الحالة',visibility:'الظهور',media:'الوسائط',workMissing:'العمل مفقود',attention:'يحتاج انتباهًا',missing:'بيانات مفقودة',clear:'سليم',openDetails:'عرض التفاصيل',unavailable:'غير متاح',generalEvent:'حدث عام',work:'العمل',yes:'نعم',no:'لا'},en:{event:'Event',context:'Context and result',actor:'Actor',targetWork:'Target and work',time:'Event time',flags:'Flags',actions:'Actions',eventInfo:'Event details',contextInfo:'Context details',actorInfo:'Actor details',targetInfo:'Target and work details',flagsInfo:'Flag details',openInfo:'Open information',close:'Close',type:'Event type',key:'Event key',auditId:'Audit ID',source:'Source',group:'Group',requiresWork:'Requires work',action:'Action',outcome:'Outcome',severity:'Severity',scope:'Scope',id:'ID',name:'Name',role:'Role',actorMissing:'Actor missing',targetType:'Target type',targetId:'Target ID',workId:'Work ID',title:'Title',slug:'Text identifier',status:'Status',visibility:'Visibility',media:'Media',workMissing:'Work missing',attention:'Needs attention',missing:'Missing data',clear:'Clear',openDetails:'View details',unavailable:'Unavailable',generalEvent:'General event',work:'Work',yes:'Yes',no:'No'}} as const
const t=computed(()=>copy[props.locale])
function definition(i:AuditActivityItem){return props.events.find(e=>e.event_type===i.event_type)}function label(i:AuditActivityItem){const e=definition(i);return toLatinDigits(e?(props.locale==='ar'?e.label_ar:e.label_en):(props.locale==='ar'?i.event_label_ar:i.event_label_en))}function groupLabel(k:string){const g=props.groups.find(i=>i.key===k);return g?(props.locale==='ar'?g.label_ar:g.label_en):toLatinDigits(k)}function eventHint(i:AuditActivityItem){return groupLabel(i.event_group)}function number(v:number){return formatYmNumber(Number.isFinite(v)?v:0,props.locale)}function sequence(i:number){return((props.currentPage-1)*props.perPage)+i+1}function safe(v:string|number){return toLatinDigits(v)}function value(v:string|null){return v?.trim()?safe(v):t.value.unavailable}function yesNo(v:boolean){return v?t.value.yes:t.value.no}function normalized(v:string|null|undefined){return v?.trim().toLowerCase().replaceAll('_','-')||''}function outcomeLabel(v:string|null){if(props.locale==='ar'&&normalized(v)==='success')return'ناجح';return value(v)}function showTechnicalOutcome(v:string|null){return props.locale==='ar'&&normalized(v)==='success'}function isSuperAdmin(v:string|null|undefined){return normalized(v).replaceAll(' ','-')==='super-admin'}function actorName(i:AuditActivityItem){if(!i.actor)return t.value.unavailable;return props.locale==='ar'&&isSuperAdmin(i.actor.name)?'مدير النظام':safe(i.actor.name)}function actorRoleLabel(v:string|null|undefined){return props.locale==='ar'&&isSuperAdmin(v)?'مدير النظام':v?safe(v):t.value.unavailable}function showLocalizedActorRole(i:AuditActivityItem){return Boolean(i.actor?.role)&&!(props.locale==='ar'&&isSuperAdmin(i.actor?.name)&&isSuperAdmin(i.actor.role))}function technicalRole(v:string){return isSuperAdmin(v)?'super-admin':safe(v)}function date(v:string){return formatYmDate(v,props.locale)}function time(v:string){return formatYmDateTime(v,props.locale).split('·').at(-1)?.trim()||t.value.unavailable}function mediaLabel(v:string|null|undefined){if(v==='image')return props.locale==='ar'?'صورة':'Image';if(v==='video')return props.locale==='ar'?'فيديو':'Video';return v?safe(v):t.value.unavailable}function targetLabel(v:string){return props.locale==='ar'?'هدف إداري':safe(v)}function indicator(k:AuditSortKey){return props.sort!==k?'↕':props.direction==='asc'?'↑':'↓'}function ariaSort(k:AuditSortKey){return props.sort!==k?'none':props.direction==='asc'?'ascending':'descending'}function open(i:AuditActivityItem,e:MouseEvent){emit('details',i,e.currentTarget instanceof HTMLElement?e.currentTarget:null)}
</script>

<style scoped>
.ym-activity-list{min-width:0}.ym-activity-list__desktop{overflow:hidden}.ym-activity-list table{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:0}.ym-activity-list col.is-sequence{width:42px}.ym-activity-list col.is-event{width:20%}.ym-activity-list col.is-context{width:14%}.ym-activity-list col.is-actor{width:14%}.ym-activity-list col.is-target-work{width:auto}.ym-activity-list col.is-date{width:145px}.ym-activity-list col.is-flags{width:110px}.ym-activity-list col.is-actions{width:58px}.ym-activity-list th,.ym-activity-list td{box-sizing:border-box;height:72px;border:0;border-bottom:1px solid var(--ym-soft-border);padding:8px;text-align:start;vertical-align:middle;background:transparent;font-size:13px}.ym-activity-list thead{background:var(--ym-table-header-bg)}.ym-activity-list th{height:50px;color:var(--ym-text);font-size:12px;font-weight:850;text-align:center}.ym-activity-list th button{border:0;background:transparent;color:inherit;font:inherit;cursor:pointer}.ym-activity-list th:not(:last-child),.ym-activity-list td:not(:last-child){border-inline-end:1px solid color-mix(in srgb,var(--ym-card-border) 25%,transparent)}.ym-activity-list tbody tr{background:color-mix(in srgb,var(--ym-control-bg) 94%,transparent)}.ym-activity-list tbody tr:hover{background:color-mix(in srgb,var(--ym-control-bg) 88%,#0e7490 12%)}.ym-activity-list tbody tr.attention td.is-event{box-shadow:inset 2px 0 #f59e0b}.ym-activity-list .is-sequence{width:42px;min-width:42px;max-width:42px;padding-inline:4px;text-align:center;font-weight:600;font-variant-numeric:tabular-nums}.ym-activity-list .is-date,.ym-activity-list .is-flags,.ym-activity-list .is-actions{text-align:center}.ym-activity-list td :deep(.ym-floating-overlay){width:100%}.primary{display:grid;min-width:0;gap:3px}.primary strong,.primary small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.primary strong{color:var(--ym-text);font-size:13.5px}.primary small{color:var(--ym-muted);font-size:11.5px}.badge{width:max-content;max-width:100%;overflow:hidden;border:1px solid var(--ym-soft-border);border-radius:999px;padding:3px 7px;color:var(--ym-text);background:var(--ym-control-bg);font-size:11px;text-overflow:ellipsis;white-space:nowrap}.is-date time{display:grid;gap:2px}.is-date strong{color:var(--ym-text);font-size:12.5px}.is-date small{color:var(--ym-muted);font-size:11.5px}.flag-trigger{display:flex;align-items:center;justify-content:center;gap:5px}.flag-trigger i{display:grid;width:25px;height:25px;place-items:center;border-radius:50%;color:#fff;background:#9f1239;font-style:normal;font-weight:900}.flag-trigger i+ i{background:#92400e}.flag-trigger small{color:var(--ym-muted)}.details{display:grid;width:38px;height:38px;margin:auto;place-items:center;border:1px solid var(--ym-control-border);border-radius:10px;color:#fff;background:#0e7490;font-size:18px;cursor:pointer}.details:focus-visible,th button:focus-visible{outline:3px solid color-mix(in srgb,#0e7490 35%,transparent);outline-offset:2px}.popover-list{display:grid;gap:7px;margin:0}.popover-list>div{display:grid;gap:2px;border-bottom:1px solid var(--ym-soft-border);padding-bottom:7px}.popover-list>div:last-child{border:0;padding:0}.popover-list dt{color:var(--ym-muted);font-size:11.5px}.popover-list dd{margin:0;color:var(--ym-text);font-size:12.5px;overflow-wrap:anywhere}.ym-activity-list__mobile{display:none;gap:9px}.ym-activity-list__mobile article{display:grid;gap:9px;border:1px solid var(--ym-card-border);border-radius:14px;padding:11px;background:var(--ym-control-bg)}.ym-activity-list__mobile header,.ym-activity-list__mobile footer{display:flex;align-items:center;justify-content:space-between;gap:8px}.ym-activity-list__mobile header>div{display:grid;min-width:0;flex:1}.ym-activity-list__mobile header strong,.ym-activity-list__mobile header small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sequence{display:grid;width:26px;height:26px;place-items:center;border-radius:8px;background:var(--ym-input-bg);font-size:11px;font-weight:700}.ym-activity-list__mobile header>b{display:grid;width:25px;height:25px;place-items:center;border-radius:50%;color:#fff;background:#9f1239}.ym-activity-list__mobile dl{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin:0}.ym-activity-list__mobile dt{color:var(--ym-muted);font-size:11px}.ym-activity-list__mobile dd{margin:2px 0 0;color:var(--ym-text);font-size:12px}.ym-activity-list__mobile footer{border-top:1px solid var(--ym-soft-border);padding-top:8px}@media(max-width:900px){.ym-activity-list__desktop{display:none}.ym-activity-list__mobile{display:grid}}@media(max-width:600px){.ym-activity-list__mobile dl{grid-template-columns:1fr}}
</style>
<style scoped>
.primary small code{margin-inline-start:4px;color:color-mix(in srgb,var(--ym-muted) 82%,#94a3b8);font-size:10.5px;font-weight:650}
.is-result.is-success strong{color:#34d399}
.is-result.is-alert strong{color:#fb7185}
.details svg{width:19px;height:19px;fill:none;stroke:currentColor;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.details{transition:border-color 150ms ease,background 150ms ease,box-shadow 150ms ease,transform 150ms ease}
.details:hover{border-color:#22d3ee;background:#0c829f;box-shadow:0 5px 14px rgba(14,116,144,.24);transform:translateY(-1px)}
:global(.ym-dashboard-light) .ym-activity-list thead{background:color-mix(in srgb,var(--ym-table-header-bg) 72%,#d9ebe8)}
:global(.ym-dashboard-light) .ym-activity-list th{border-bottom-color:color-mix(in srgb,var(--ym-control-border) 65%,#64748b)}
:global(.ym-dashboard-light) .ym-activity-list td{border-bottom-color:color-mix(in srgb,var(--ym-soft-border) 58%,#94a3b8)}
:global(.ym-dashboard-light) .primary small,
:global(.ym-dashboard-light) .is-date small,
:global(.ym-dashboard-light) .flag-trigger small{color:color-mix(in srgb,var(--ym-muted) 64%,#334155)}
.ym-activity-list__mobile .details{width:auto;padding-inline:10px;font-size:12px}
</style>
