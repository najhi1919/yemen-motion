<template>
  <WorksDrawerShell
    :open="open"
    :locale="locale"
    size="details"
    title-id="ym-taxonomy-overview-details-title"
    :close-label="text.close"
    @request-close="$emit('close')"
  >
    <template #header>
      <template v-if="bucket">
        <span class="ym-taxonomy-drawer__eyebrow">{{ text.eyebrow }}</span>
        <h2 id="ym-taxonomy-overview-details-title">{{ safeText(displayName) }}</h2>
        <div class="ym-taxonomy-drawer__head-meta">
          <span :class="headerState.tone">{{ headerState.label }}</span>
        </div>
      </template>
    </template>

    <div v-if="bucket" class="ym-taxonomy-drawer__content">
      <section>
        <h3>{{ text.identity }}</h3>
        <dl class="ym-taxonomy-drawer__facts">
          <div><dt>{{ text.category }}</dt><dd>{{ safeText(displayName) }}</dd></div>
          <div><dt>{{ text.textIdentifier }}</dt><dd dir="ltr">{{ safeText(bucket.category?.slug || text.none) }}</dd></div>
          <div><dt>{{ text.identifier }}</dt><dd dir="ltr">{{ bucket.category_id === null ? text.none : `#${number(bucket.category_id)}` }}</dd></div>
          <div><dt>{{ text.catalogState }}</dt><dd>{{ bucket.category_tracking.catalog_record_exists ? text.available : text.unavailable }}</dd></div>
        </dl>
        <dl class="ym-taxonomy-drawer__facts is-followup">
          <div><dt>{{ text.linkState }}</dt><dd>{{ linkState }}</dd></div>
          <div v-if="bucket.category_tracking.is_legacy_unmapped" class="is-wide"><dt>{{ text.legacyReasonLabel }}</dt><dd>{{ text.legacyReason }}</dd></div>
        </dl>
      </section>

      <section>
        <h3>{{ text.distribution }}</h3>
        <dl class="ym-taxonomy-drawer__facts is-metrics">
          <div><dt>{{ text.total }}</dt><dd>{{ number(bucket.works_count) }}</dd></div>
          <div><dt>{{ text.published }}</dt><dd>{{ number(bucket.published_count) }}</dd></div>
          <div><dt>{{ text.hidden }}</dt><dd>{{ number(bucket.hidden_count) }}</dd></div>
          <div><dt>{{ text.inReview }}</dt><dd>{{ number(bucket.review_queue_count) }}</dd></div>
        </dl>
      </section>

      <section :class="{ 'is-warning': bucket.taxonomy_flags.needs_attention }">
        <h3>{{ text.risk }}</h3>
        <dl class="ym-taxonomy-drawer__facts">
          <div><dt>{{ text.reportedWorks }}</dt><dd>{{ number(bucket.reported_count) }}</dd></div>
          <div><dt>{{ text.totalReports }}</dt><dd>{{ number(bucket.total_reports) }}</dd></div>
          <div><dt>{{ text.needsAttention }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.needs_attention) }}</dd></div>
          <div><dt>{{ text.attentionReason }}</dt><dd>{{ attentionReason }}</dd></div>
        </dl>
      </section>

      <section>
        <h3>{{ text.promotion }}</h3>
        <dl class="ym-taxonomy-drawer__facts">
          <div><dt>{{ text.hasPublished }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_published) }}</dd></div>
          <div><dt>{{ text.hasHidden }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_hidden) }}</dd></div>
          <div><dt>{{ text.featured }}</dt><dd>{{ number(bucket.featured_count) }}</dd></div>
          <div><dt>{{ text.pinned }}</dt><dd>{{ number(bucket.pinned_count) }}</dd></div>
        </dl>
        <dl class="ym-taxonomy-drawer__facts is-followup">
          <div><dt>{{ text.promoted }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.is_promoted) }}</dd></div>
        </dl>
      </section>

      <section>
        <h3>{{ text.engagement }}</h3>
        <dl class="ym-taxonomy-drawer__facts is-metrics">
          <div><dt>{{ text.views }}</dt><dd>{{ number(bucket.total_views) }}</dd></div>
          <div><dt>{{ text.likes }}</dt><dd>{{ number(bucket.total_likes) }}</dd></div>
          <div><dt>{{ text.reports }}</dt><dd>{{ number(bucket.total_reports) }}</dd></div>
          <div>
            <dt>{{ text.lastActivity }}</dt>
            <dd><time :datetime="bucket.latest_work_at || undefined">{{ dateTime(bucket.latest_work_at) }}</time></dd>
          </div>
        </dl>
      </section>
    </div>

    <template #footer>
      <div class="ym-taxonomy-drawer__actions">
        <button type="button" @click="$emit('close')">{{ text.close }}</button>
      </div>
    </template>
  </WorksDrawerShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WorksDrawerShell from '~/components/works/drawers/WorksDrawerShell.vue'
import { formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

type Locale = 'ar' | 'en'
interface TaxonomyFlags { uncategorized:boolean;has_reports:boolean;has_published:boolean;has_hidden:boolean;is_promoted:boolean;needs_attention:boolean }
interface Bucket {
  category_id:number|null
  label:string
  category:{name_ar:string;name_en:string;slug:string;is_active:boolean}|null
  category_tracking:{catalog_record_exists:boolean;is_legacy_unmapped:boolean;is_uncategorized:boolean}
  works_count:number
  published_count:number
  hidden_count:number
  review_queue_count:number
  reported_count:number
  featured_count:number
  pinned_count:number
  total_reports:number
  total_views:number
  total_likes:number
  latest_work_at:string|null
  taxonomy_flags:TaxonomyFlags
}

const props = defineProps<{ open:boolean;locale:Locale;bucket:Bucket|null }>()
defineEmits<{ close:[] }>()

const copies = {
  ar: {
    eyebrow:'تفاصيل تجميع التصنيف',close:'إغلاق',identity:'الهوية والارتباط',distribution:'توزيع الأعمال',risk:'المراجعة والمخاطر',promotion:'الظهور والترويج',engagement:'التفاعل والنشاط',
    category:'اسم التصنيف',textIdentifier:'المعرّف النصي',identifier:'رقم التعريف',catalogState:'حالة الكتالوج',linkState:'حالة الارتباط',legacyReasonLabel:'سبب القيمة القديمة',legacyReason:'لا يقابل هذه القيمة سجل حالي في كتالوج التصنيفات.',
    available:'متاح',unavailable:'غير متاح',linked:'مرتبط',uncategorized:'غير مصنف',legacy:'قيمة قديمة غير مرتبطة',active:'فعال',disabled:'معطل',none:'غير متوفر',
    total:'الإجمالي',published:'المنشورة',hidden:'المخفية',inReview:'ضمن المراجعة',reportedWorks:'الأعمال المبلّغ عنها',totalReports:'مجموع البلاغات',needsAttention:'يحتاج انتباهًا',attentionReason:'سبب التنبيه',
    hasPublished:'لديه منشور',hasHidden:'لديه مخفي',featured:'المميزة',pinned:'المثبتة',promoted:'مروّج',views:'المشاهدات',likes:'الإعجابات',reports:'البلاغات',lastActivity:'آخر نشاط',
    yes:'نعم',no:'لا',multipleReasons:'عدة مؤشرات تتطلب الانتباه',reportsReason:'توجد أعمال مبلّغ عنها',uncategorizedReason:'التجميع غير مصنف',hiddenReason:'توجد أعمال مخفية',stable:'لا توجد مؤشرات خطر',noActivity:'لا يوجد نشاط'
  },
  en: {
    eyebrow:'Category bucket details',close:'Close',identity:'Identity and link',distribution:'Work distribution',risk:'Review and risk',promotion:'Visibility and promotion',engagement:'Engagement and activity',
    category:'Category name',textIdentifier:'Text identifier',identifier:'Identifier',catalogState:'Catalog state',linkState:'Link state',legacyReasonLabel:'Legacy value reason',legacyReason:'This value has no current matching catalog record.',
    available:'Available',unavailable:'Unavailable',linked:'Linked',uncategorized:'Uncategorized',legacy:'Unlinked legacy value',active:'Active',disabled:'Disabled',none:'Unavailable',
    total:'Total',published:'Published',hidden:'Hidden',inReview:'In review',reportedWorks:'Reported works',totalReports:'Total reports',needsAttention:'Needs attention',attentionReason:'Alert reason',
    hasPublished:'Has published',hasHidden:'Has hidden',featured:'Featured',pinned:'Pinned',promoted:'Promoted',views:'Views',likes:'Likes',reports:'Reports',lastActivity:'Last activity',
    yes:'Yes',no:'No',multipleReasons:'Multiple indicators need attention',reportsReason:'Reported works exist',uncategorizedReason:'The bucket is uncategorized',hiddenReason:'Hidden works exist',stable:'No risk indicators',noActivity:'No activity'
  }
} as const

const text = computed(() => copies[props.locale])
const displayName = computed(() => {
  const bucket = props.bucket
  if (!bucket) return ''
  return bucket.category ? (props.locale === 'ar' ? bucket.category.name_ar : bucket.category.name_en) : bucket.label
})
const headerState = computed(() => {
  const bucket = props.bucket
  if (!bucket) return { label: text.value.unavailable, tone: 'is-neutral' }
  if (bucket.category_tracking.is_legacy_unmapped) return { label: text.value.legacy, tone: 'is-warning' }
  if (bucket.category_tracking.is_uncategorized) return { label: text.value.uncategorized, tone: 'is-warning' }
  return bucket.category?.is_active
    ? { label: text.value.active, tone: 'is-active' }
    : { label: text.value.disabled, tone: 'is-neutral' }
})
const linkState = computed(() => {
  const bucket = props.bucket
  if (!bucket) return text.value.unavailable
  if (bucket.category_tracking.is_legacy_unmapped) return text.value.legacy
  if (bucket.category_tracking.is_uncategorized) return text.value.uncategorized
  return text.value.linked
})
const attentionReason = computed(() => {
  const bucket = props.bucket
  if (!bucket) return text.value.stable
  const reasons = [
    bucket.taxonomy_flags.has_reports && text.value.reportsReason,
    bucket.taxonomy_flags.uncategorized && text.value.uncategorizedReason,
    bucket.taxonomy_flags.has_hidden && text.value.hiddenReason
  ].filter(Boolean) as string[]
  return reasons.length > 1 ? text.value.multipleReasons : (reasons[0] || text.value.stable)
})

const safeText = (value:string|number) => toLatinDigits(value)
const number = (value:number) => formatYmNumber(Number.isFinite(value) ? value : 0, props.locale)
const yesNo = (value:boolean) => value ? text.value.yes : text.value.no
const dateTime = (value:string|null) => value ? formatYmDateTime(value, props.locale) : text.value.noActivity
</script>

<style scoped>
.ym-taxonomy-drawer__eyebrow{color:var(--ym-drawer-electric);font-size:12.5px;font-weight:850}
h2{margin:0;color:var(--ym-drawer-text);font-size:clamp(23px,3vw,29px);font-weight:900;line-height:1.25;overflow-wrap:anywhere}
.ym-taxonomy-drawer__head-meta{display:flex;align-items:center;flex-wrap:wrap;gap:7px}
.ym-taxonomy-drawer__head-meta span,.ym-taxonomy-drawer__head-meta code{border:1px solid var(--ym-drawer-border);border-radius:999px;padding:4px 8px;color:var(--ym-drawer-muted);background:var(--ym-drawer-control);font-size:12.5px;font-weight:800}
.ym-taxonomy-drawer__head-meta span.is-active{color:var(--ym-drawer-emerald)}.ym-taxonomy-drawer__head-meta span.is-warning{color:var(--ym-drawer-amber)}
.ym-taxonomy-drawer__content{display:grid;gap:13px}
.ym-taxonomy-drawer__content section{border:1px solid var(--ym-drawer-soft-border);border-radius:17px;padding:14px 18px;background:var(--ym-drawer-card)}
.ym-taxonomy-drawer__content section.is-warning{border-inline-start:3px solid var(--ym-drawer-amber);background:linear-gradient(135deg,color-mix(in srgb,var(--ym-drawer-amber) 6%,transparent),var(--ym-drawer-card))}
.ym-taxonomy-drawer__content h3{margin:0 0 9px;color:var(--ym-drawer-text);font-size:17px;font-weight:900}
.ym-taxonomy-drawer__facts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));margin:0}
.ym-taxonomy-drawer__facts.is-followup{margin-top:0}
.ym-taxonomy-drawer__facts>div{display:grid;gap:4px;border-block-end:1px solid var(--ym-drawer-soft-border);padding:7px 4px}.ym-taxonomy-drawer__facts>div.is-wide{grid-column:1/-1}
.ym-taxonomy-drawer__facts dt{color:var(--ym-drawer-muted);font-size:12.5px}.ym-taxonomy-drawer__facts dd{margin:0;color:var(--ym-drawer-text);font-size:14px;font-weight:750;line-height:1.5;overflow-wrap:anywhere}
.ym-taxonomy-drawer__facts.is-metrics dd{direction:ltr;unicode-bidi:isolate;font-size:16px;font-weight:900;font-variant-numeric:tabular-nums}.ym-taxonomy-drawer__facts time{direction:ltr;unicode-bidi:isolate;white-space:nowrap}
.ym-taxonomy-drawer__actions{display:flex;justify-content:flex-end}.ym-taxonomy-drawer__actions button{min-width:100px;min-height:42px;border:1px solid var(--ym-drawer-border);border-radius:12px;padding:0 15px;color:var(--ym-drawer-text);background:var(--ym-drawer-control);font-size:13.5px;font-weight:850;cursor:pointer}
.ym-taxonomy-drawer__actions button:hover{border-color:var(--ym-drawer-electric)}.ym-taxonomy-drawer__actions button:focus-visible{outline:3px solid color-mix(in srgb,var(--ym-drawer-electric) 42%,transparent);outline-offset:2px}
@media(max-width:640px){.ym-taxonomy-drawer__content section{padding:15px}.ym-taxonomy-drawer__facts{grid-template-columns:1fr}.ym-taxonomy-drawer__actions button{width:100%;min-height:44px}}
</style>
