<template>
  <div class="ym-taxonomy-catalog-results" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-busy="loading">
    <p v-if="loading" class="ym-taxonomy-catalog-refresh" role="status">{{ text.refreshing }}</p>

    <div class="ym-taxonomy-catalog-table-wrap">
      <table class="ym-taxonomy-catalog-table">
        <colgroup>
          <col class="is-sequence" />
          <col class="is-identity" />
          <col class="is-state" />
          <col class="is-order" />
          <col class="is-usage" />
          <col class="is-date" />
          <col class="is-actions" />
        </colgroup>
        <thead>
          <tr>
            <th class="is-sequence" scope="col">#</th>
            <th class="is-identity" scope="col">{{ text.identity }}</th>
            <th class="is-state" scope="col">{{ text.state }}</th>
            <th class="is-order" scope="col">{{ text.sortOrder }}</th>
            <th class="is-usage" scope="col">{{ text.worksAndUsage }}</th>
            <th class="is-date" scope="col">{{ text.updated }}</th>
            <th class="is-actions" scope="col">{{ text.actions }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(item, index) in items" :key="item.id" :aria-label="text.rowLabel(number(sequence(index)))">
            <td class="is-sequence" dir="ltr">{{ number(sequence(index)) }}</td>
            <td class="is-identity">
              <WorksIndexInfoPopover :label="text.identityInfo" :hint="text.openInfo" :close-label="text.close">
                <template #trigger>
                  <span class="ym-catalog-identity">
                    <strong>{{ safeText(item.name_ar) }}</strong>
                    <small dir="ltr">{{ safeText(item.name_en) }}</small>
                  </span>
                </template>
                <dl class="ym-catalog-popover-list">
                  <div><dt>{{ text.nameAr }}</dt><dd>{{ safeText(item.name_ar) }}</dd></div>
                  <div><dt>{{ text.nameEn }}</dt><dd dir="ltr">{{ safeText(item.name_en) }}</dd></div>
                  <div><dt>{{ text.textIdentifier }}</dt><dd dir="ltr">{{ safeText(item.slug) }}</dd></div>
                  <div><dt>{{ text.identifier }}</dt><dd dir="ltr">#{{ number(item.id) }}</dd></div>
                  <div><dt>{{ text.recordType }}</dt><dd>{{ text.entityLabel }}</dd></div>
                </dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-state">
              <WorksIndexInfoPopover :label="text.stateInfo" :hint="text.openInfo" :close-label="text.close">
                <template #trigger>
                  <span class="ym-catalog-badge" :class="item.is_active ? 'is-active' : 'is-disabled'">
                    {{ item.is_active ? text.active : text.disabled }}
                  </span>
                </template>
                <dl class="ym-catalog-popover-list">
                  <div><dt>{{ text.state }}</dt><dd>{{ item.is_active ? text.active : text.disabled }}</dd></div>
                  <div v-if="item.disabled_at"><dt>{{ text.disabledAt }}</dt><dd><time :datetime="item.disabled_at">{{ dateTime(item.disabled_at) }}</time></dd></div>
                  <div><dt>{{ text.created }}</dt><dd><time :datetime="item.created_at">{{ dateTime(item.created_at) }}</time></dd></div>
                  <div><dt>{{ text.updated }}</dt><dd><time :datetime="item.updated_at">{{ dateTime(item.updated_at) }}</time></dd></div>
                </dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-order">
              <WorksIndexInfoPopover :label="text.orderInfo" :hint="text.openInfo" :close-label="text.close">
                <template #trigger><strong class="ym-catalog-order" dir="ltr">{{ number(item.sort_order) }}</strong></template>
                <dl class="ym-catalog-popover-list">
                  <div><dt>{{ text.sortOrder }}</dt><dd>{{ number(item.sort_order) }}</dd></div>
                  <div><dt>{{ text.identifier }}</dt><dd dir="ltr">#{{ number(item.id) }}</dd></div>
                </dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-usage">
              <WorksIndexInfoPopover :label="text.usageInfo" :hint="text.openInfo" :close-label="text.close">
                <template #trigger>
                  <span class="ym-catalog-usage">
                    <strong><span>{{ text.works }}:</span><span dir="ltr">{{ number(item.works_count) }}</span></strong>
                    <small>{{ item.works_count > 0 ? text.used : text.unused }}</small>
                  </span>
                </template>
                <dl class="ym-catalog-popover-list">
                  <div><dt>{{ text.works }}</dt><dd>{{ number(item.works_count) }}</dd></div>
                  <div><dt>{{ text.usage }}</dt><dd>{{ item.works_count > 0 ? text.used : text.unused }}</dd></div>
                </dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-date">
              <WorksIndexInfoPopover :label="text.dateInfo" :hint="text.openInfo" :close-label="text.close">
                <template #trigger><time :datetime="item.updated_at">{{ date(item.updated_at) }}</time></template>
                <dl class="ym-catalog-popover-list">
                  <div><dt>{{ text.created }}</dt><dd><time :datetime="item.created_at">{{ dateTime(item.created_at) }}</time></dd></div>
                  <div><dt>{{ text.updated }}</dt><dd><time :datetime="item.updated_at">{{ dateTime(item.updated_at) }}</time></dd></div>
                </dl>
              </WorksIndexInfoPopover>
            </td>
            <td class="is-actions">
              <div class="ym-catalog-actions">
                <WorksIndexFloatingOverlay v-if="canUpdate" :label="text.edit" :description="text.edit" :trigger-aria-label="text.editAria(displayName(item))" @activate="$emit('edit', item)">
                  <template #trigger><span class="ym-catalog-action is-edit" aria-hidden="true">✎</span></template>
                </WorksIndexFloatingOverlay>
                <WorksIndexFloatingOverlay v-if="canDisable && item.is_active" :label="text.disable" :description="text.disable" :trigger-aria-label="text.disableAria(displayName(item))" @activate="$emit('disable', item)">
                  <template #trigger><span class="ym-catalog-action is-disable" aria-hidden="true">⊘</span></template>
                </WorksIndexFloatingOverlay>
                <span v-if="!canUpdate && (!canDisable || !item.is_active)" class="ym-catalog-readonly">{{ text.readOnly }}</span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="ym-catalog-mobile">
      <article v-for="(item, index) in items" :key="item.id">
        <header>
          <span class="ym-catalog-row-number" :aria-label="text.rowLabel(number(sequence(index)))" dir="ltr">#{{ number(sequence(index)) }}</span>
          <div><strong>{{ safeText(item.name_ar) }}</strong><small dir="ltr">{{ safeText(item.name_en) }}</small></div>
          <span class="ym-catalog-badge" :class="item.is_active ? 'is-active' : 'is-disabled'">{{ item.is_active ? text.active : text.disabled }}</span>
        </header>
        <dl>
          <div><dt>{{ text.sortOrder }}</dt><dd>{{ number(item.sort_order) }}</dd></div>
          <div><dt>{{ text.works }}</dt><dd>{{ number(item.works_count) }}</dd></div>
          <div><dt>{{ text.updated }}</dt><dd><time :datetime="item.updated_at">{{ date(item.updated_at) }}</time></dd></div>
        </dl>
        <footer>
          <WorksIndexInfoPopover :label="text.moreInfo" :hint="text.openInfo" :close-label="text.close">
            <template #trigger><span class="ym-catalog-more">{{ text.moreInfo }}</span></template>
            <dl class="ym-catalog-popover-list">
              <div><dt>{{ text.textIdentifier }}</dt><dd dir="ltr">{{ safeText(item.slug) }}</dd></div>
              <div><dt>{{ text.identifier }}</dt><dd dir="ltr">#{{ number(item.id) }}</dd></div>
              <div><dt>{{ text.created }}</dt><dd>{{ dateTime(item.created_at) }}</dd></div>
              <div><dt>{{ text.usage }}</dt><dd>{{ item.works_count > 0 ? text.used : text.unused }}</dd></div>
            </dl>
          </WorksIndexInfoPopover>
          <div class="ym-catalog-actions">
            <WorksIndexFloatingOverlay v-if="canUpdate" :label="text.edit" :description="text.edit" :trigger-aria-label="text.editAria(displayName(item))" @activate="$emit('edit', item)">
              <template #trigger><span class="ym-catalog-action is-edit" aria-hidden="true">✎</span></template>
            </WorksIndexFloatingOverlay>
            <WorksIndexFloatingOverlay v-if="canDisable && item.is_active" :label="text.disable" :description="text.disable" :trigger-aria-label="text.disableAria(displayName(item))" @activate="$emit('disable', item)">
              <template #trigger><span class="ym-catalog-action is-disable" aria-hidden="true">⊘</span></template>
            </WorksIndexFloatingOverlay>
          </div>
        </footer>
      </article>
    </div>

    <footer class="ym-catalog-pagination">
      <div><span>{{ text.total }}:</span> <strong dir="ltr">{{ number(pagination.total) }}</strong></div>
      <nav :aria-label="text.pagination">
        <button type="button" :disabled="loading || pagination.current_page <= 1" @click="$emit('page', pagination.current_page - 1)">{{ text.previous }}</button>
        <span>{{ pageLabel }}</span>
        <button type="button" :disabled="loading || pagination.current_page >= pagination.last_page" @click="$emit('page', pagination.current_page + 1)">{{ text.next }}</button>
      </nav>
    </footer>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WorksIndexFloatingOverlay from '~/components/works/index/WorksIndexFloatingOverlay.vue'
import WorksIndexInfoPopover from '~/components/works/index/WorksIndexInfoPopover.vue'
import { formatYmDate, formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

type EntityType = 'category' | 'tag'
interface Item { id:number; name_ar:string; name_en:string; slug:string; disabled_at:string|null; is_active:boolean; sort_order:number; works_count:number; created_at:string; updated_at:string }
interface Pagination { current_page:number; per_page:number; total:number; last_page:number }

const props = defineProps<{ items:Item[]; pagination:Pagination; loading:boolean; locale:'ar'|'en'; entityType:EntityType; canUpdate:boolean; canDisable:boolean }>()
defineEmits<{ edit:[item:Item]; disable:[item:Item]; page:[page:number] }>()

const copies = {
  ar: { identity:'الهوية', state:'الحالة', sortOrder:'الترتيب', worksAndUsage:'الأعمال والاستخدام', updated:'آخر تحديث', actions:'الإجراءات', identityInfo:'تفاصيل الهوية', stateInfo:'تفاصيل الحالة', orderInfo:'تفاصيل ترتيب العرض', usageInfo:'تفاصيل الاستخدام', dateInfo:'تفاصيل التاريخ', openInfo:'عرض المعلومات', close:'إغلاق', nameAr:'الاسم العربي', nameEn:'الاسم الإنجليزي', textIdentifier:'المعرّف النصي', identifier:'رقم التعريف', recordType:'نوع السجل', active:'فعال', disabled:'معطل', disabledAt:'تاريخ التعطيل', created:'الإنشاء', works:'الأعمال', usage:'الاستخدام', used:'مستخدم', unused:'غير مستخدم', edit:'تعديل', disable:'تعطيل', readOnly:'للقراءة فقط', moreInfo:'معلومات إضافية', noActivity:'لا يوجد نشاط', refreshing:'جارٍ تحديث النتائج…', total:'الإجمالي', pagination:'ترقيم صفحات الكتالوج', previous:'السابق', next:'التالي', rowLabel:(value:string)=>`الصف رقم ${value}`, editAria:(name:string)=>`تعديل ${name}`, disableAria:(name:string)=>`تعطيل ${name}` },
  en: { identity:'Identity', state:'State', sortOrder:'Order', worksAndUsage:'Works and usage', updated:'Last updated', actions:'Actions', identityInfo:'Identity details', stateInfo:'State details', orderInfo:'Display order details', usageInfo:'Usage details', dateInfo:'Date details', openInfo:'Open information', close:'Close', nameAr:'Arabic name', nameEn:'English name', textIdentifier:'Text identifier', identifier:'Identifier', recordType:'Record type', active:'Active', disabled:'Disabled', disabledAt:'Disabled at', created:'Created', works:'Works', usage:'Usage', used:'Used', unused:'Unused', edit:'Edit', disable:'Disable', readOnly:'Read only', moreInfo:'More information', noActivity:'No activity', refreshing:'Refreshing results…', total:'Total', pagination:'Catalog pagination', previous:'Previous', next:'Next', rowLabel:(value:string)=>`Row ${value}`, editAria:(name:string)=>`Edit ${name}`, disableAria:(name:string)=>`Disable ${name}` }
} as const
const text = computed(() => ({ ...copies[props.locale], entityLabel: props.entityType === 'category' ? (props.locale === 'ar' ? 'تصنيف' : 'Category') : (props.locale === 'ar' ? 'وسم' : 'Tag') }))
const pageLabel = computed(() => props.locale === 'ar' ? `الصفحة ${number(props.pagination.current_page)} من ${number(props.pagination.last_page)}` : `Page ${number(props.pagination.current_page)} of ${number(props.pagination.last_page)}`)

function number(value:number) { return formatYmNumber(Number.isFinite(value) ? value : 0, props.locale) }
function safeText(value:string|number) { return toLatinDigits(value) }
function sequence(index:number) { return ((props.pagination.current_page - 1) * props.pagination.per_page) + index + 1 }
function displayName(item:Item) { return props.locale === 'ar' ? safeText(item.name_ar) : safeText(item.name_en) }
function date(value:string|null) { const formatted = formatYmDate(value, props.locale); return formatted === '—' ? text.value.noActivity : formatted }
function dateTime(value:string|null) { const formatted = formatYmDateTime(value, props.locale); return formatted === '—' ? text.value.noActivity : formatted }
</script>

<style scoped>
.ym-taxonomy-catalog-results{position:relative;min-width:0;color:var(--ym-text)}
.ym-taxonomy-catalog-refresh{position:absolute;z-index:2;inset-block-start:8px;inset-inline-end:10px;margin:0;border-radius:999px;padding:5px 9px;color:#fff;background:#0e7490;font-size:11px;font-weight:850}
.ym-taxonomy-catalog-table-wrap{width:100%;min-width:0;overflow-x:auto}
.ym-taxonomy-catalog-table{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:0}
.ym-taxonomy-catalog-table col.is-sequence,.ym-taxonomy-catalog-table th.is-sequence,.ym-taxonomy-catalog-table td.is-sequence{width:42px;min-width:42px;max-width:42px;padding-inline:4px;text-align:center;vertical-align:middle;white-space:nowrap;font-variant-numeric:tabular-nums}
.ym-taxonomy-catalog-table col.is-identity{width:auto}.ym-taxonomy-catalog-table col.is-state{width:130px}.ym-taxonomy-catalog-table col.is-order{width:110px}.ym-taxonomy-catalog-table col.is-usage{width:190px}.ym-taxonomy-catalog-table col.is-date{width:190px}.ym-taxonomy-catalog-table col.is-actions{width:104px}
.ym-taxonomy-catalog-table th,.ym-taxonomy-catalog-table td{box-sizing:border-box;height:56px;border:0;border-bottom:1px solid var(--ym-card-border);padding:9px 10px;text-align:start;vertical-align:middle;background:transparent;font-size:13px}
.ym-taxonomy-catalog-table thead{background:color-mix(in srgb,var(--ym-dropdown-bg) 95%,#0891b2 5%)}.ym-taxonomy-catalog-table th{height:50px;color:var(--ym-text);font-size:12.5px;font-weight:850;text-align:center}.ym-taxonomy-catalog-table th:not(:last-child),.ym-taxonomy-catalog-table td:not(:last-child){border-inline-end:1px solid color-mix(in srgb,var(--ym-card-border) 28%,transparent)}.ym-taxonomy-catalog-table thead th:first-child{border-start-start-radius:12px}.ym-taxonomy-catalog-table thead th:last-child{border-start-end-radius:12px}
.ym-taxonomy-catalog-table tbody tr{background:color-mix(in srgb,var(--ym-control-bg) 96%,transparent);transition:background-color .16s ease}.ym-taxonomy-catalog-table tbody tr:hover{background:color-mix(in srgb,var(--ym-control-bg) 91%,#0e7490 9%)}.ym-taxonomy-catalog-table td.is-state,.ym-taxonomy-catalog-table td.is-order,.ym-taxonomy-catalog-table td.is-date,.ym-taxonomy-catalog-table td.is-actions{text-align:center}
.ym-taxonomy-catalog-table td :deep(.ym-floating-overlay){width:100%}.ym-taxonomy-catalog-table td.is-actions :deep(.ym-floating-overlay){width:auto}
.ym-catalog-identity,.ym-catalog-usage{display:grid;min-width:0;gap:4px}.ym-catalog-identity strong,.ym-catalog-identity small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ym-catalog-identity strong{color:var(--ym-text);font-size:14px;font-weight:850}.ym-catalog-identity small,.ym-catalog-usage small{color:color-mix(in srgb,var(--ym-muted) 78%,var(--ym-text) 22%);font-size:12px}.ym-catalog-usage strong{display:flex;align-items:baseline;justify-content:center;gap:4px;color:var(--ym-text);font-size:13px}.ym-catalog-order{color:var(--ym-text);font-size:16px;font-weight:800;font-variant-numeric:tabular-nums}.ym-taxonomy-catalog-table time,.ym-catalog-popover-list time{direction:ltr;unicode-bidi:isolate;color:var(--ym-text);font-weight:750;font-variant-numeric:tabular-nums;white-space:nowrap}
.ym-catalog-badge{display:inline-flex;min-height:26px;align-items:center;border:1px solid var(--ym-control-border);border-radius:999px;padding:3px 8px;color:var(--ym-text);font-size:11.5px;font-weight:850}.ym-catalog-badge.is-active{border-color:rgba(15,118,110,.38);background:rgba(15,118,110,.12)}.ym-catalog-badge.is-disabled{border-color:rgba(217,119,6,.4);background:rgba(217,119,6,.12)}
.ym-catalog-popover-list{display:grid;gap:8px;margin:0}.ym-catalog-popover-list>div{display:grid;gap:3px;border-bottom:1px solid var(--ym-card-border);padding-bottom:8px}.ym-catalog-popover-list>div:last-child{border-bottom:0;padding-bottom:0}.ym-catalog-popover-list dt{color:var(--ym-muted);font-size:12px}.ym-catalog-popover-list dd{margin:0;color:var(--ym-text);font-size:13px;font-weight:700;line-height:1.5;overflow-wrap:anywhere}
.ym-catalog-actions{display:inline-flex;align-items:center;justify-content:center;gap:6px}.ym-catalog-action{display:grid;width:38px;height:38px;place-items:center;border:1px solid var(--ym-control-border);border-radius:10px;color:var(--ym-text);background:var(--ym-input-bg);font-size:17px}.ym-catalog-action.is-edit{color:#0e7490}.ym-catalog-action.is-disable{color:#e11d48}.ym-catalog-readonly{color:var(--ym-muted);font-size:11.5px}
.ym-catalog-mobile{display:none;gap:9px;padding:9px}.ym-catalog-mobile article{display:grid;gap:10px;border:1px solid var(--ym-card-border);border-radius:15px;padding:12px;background:var(--ym-control-bg)}.ym-catalog-mobile header,.ym-catalog-mobile footer{display:flex;align-items:center;justify-content:space-between;gap:8px}.ym-catalog-mobile header>div{display:grid;min-width:0;gap:3px;flex:1}.ym-catalog-mobile header strong,.ym-catalog-mobile header small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ym-catalog-row-number{display:grid;width:26px;height:26px;place-items:center;border-radius:8px;color:var(--ym-text);background:var(--ym-input-bg);font-size:12px;font-weight:700}.ym-catalog-mobile dl{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin:0}.ym-catalog-mobile dl>div{border-radius:10px;padding:8px;background:color-mix(in srgb,var(--ym-card-bg) 62%,transparent)}.ym-catalog-mobile dt{color:var(--ym-muted);font-size:11.5px}.ym-catalog-mobile dd{margin:3px 0 0;color:var(--ym-text);font-size:13px;font-weight:750}.ym-catalog-mobile footer{border-top:1px solid var(--ym-card-border);padding-top:9px}.ym-catalog-more{display:inline-flex;min-height:38px;align-items:center;border-radius:10px;padding:0 10px;color:#0e7490;background:color-mix(in srgb,#0e7490 10%,transparent);font-size:13px;font-weight:850}
.ym-catalog-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;color:var(--ym-muted);font-size:13px}.ym-catalog-pagination strong{color:var(--ym-text)}.ym-catalog-pagination nav{display:flex;align-items:center;gap:8px}.ym-catalog-pagination button{min-height:38px;border:1px solid var(--ym-control-border);border-radius:10px;padding:0 12px;color:var(--ym-text);background:var(--ym-control-bg);font-weight:850}.ym-catalog-pagination button:focus-visible{outline:3px solid color-mix(in srgb,#0e7490 26%,transparent);outline-offset:2px}
@media(max-width:900px){.ym-taxonomy-catalog-table-wrap{display:none}.ym-catalog-mobile{display:grid}}@media(max-width:600px){.ym-catalog-mobile dl{grid-template-columns:1fr}.ym-catalog-mobile footer,.ym-catalog-pagination{align-items:stretch;flex-direction:column}.ym-catalog-pagination nav{justify-content:space-between}}
</style>
