<template>
  <section class="ym-taxonomy-smart-list" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-busy="loading">
    <p v-if="loading && items.length" class="ym-taxonomy-smart-list__update" role="status">{{ text.updating }}</p>
    <p v-if="error && items.length" class="ym-taxonomy-smart-list__update is-error" role="alert">
      {{ error }} <button type="button" @click="$emit('retry')">{{ text.retry }}</button>
    </p>
    <div v-if="loading && !hasLoaded" class="ym-taxonomy-smart-list__state" role="status">
      <span class="ym-taxonomy-smart-list__spinner" aria-hidden="true" />
      <strong>{{ text.loading }}</strong>
    </div>
    <div v-else-if="error && !items.length" class="ym-taxonomy-smart-list__state is-error" role="alert">
      <strong>{{ text.error }}</strong>
      <p>{{ error }}</p>
      <button type="button" @click="$emit('retry')">{{ text.retry }}</button>
    </div>
    <div v-else-if="hasLoaded && !items.length" class="ym-taxonomy-smart-list__state">
      <strong>{{ text.empty }}</strong>
      <p>{{ text.emptyCopy }}</p>
      <button type="button" @click="$emit('reset')">{{ text.reset }}</button>
    </div>
    <template v-else-if="hasLoaded">
      <div class="ym-taxonomy-smart-list__desktop">
        <table class="ym-taxonomy-smart-table is-overview">
          <colgroup>
            <col class="is-sequence" />
            <col class="is-category" />
            <col class="is-distribution" />
            <col class="is-risk" />
            <col class="is-promotion" />
            <col class="is-engagement" />
            <col class="is-tracking" />
            <col class="is-date" />
            <col class="is-actions" />
          </colgroup>
          <thead>
            <tr>
              <th class="is-sequence" scope="col">#</th>
              <th class="is-category">{{ text.category }}</th>
              <th class="is-distribution">{{ text.distribution }}</th>
              <th class="is-risk">{{ text.risk }}</th>
              <th class="is-promotion">{{ text.promotion }}</th>
              <th class="is-engagement">
                <span class="ym-taxonomy-smart-list__head-title">
                  {{ text.engagement }}
                  <i v-if="sort === metric" aria-hidden="true">{{ direction === 'asc' ? '↑' : '↓' }}</i>
                </span>
                <label class="ym-taxonomy-smart-list__head-control">
                  <span class="sr-only">{{ text.metric }}</span>
                  <select :value="metric" :aria-label="text.metric" @change="changeMetric">
                    <option value="total_views">{{ text.views }}</option>
                    <option value="total_likes">{{ text.likes }}</option>
                    <option value="total_reports">{{ text.reports }}</option>
                  </select>
                </label>
              </th>
              <th class="is-tracking">{{ text.tracking }}</th>
              <th class="is-date">{{ text.lastActivity }}</th>
              <th class="is-actions">{{ text.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(bucket, index) in items"
              :key="bucket.category_id ?? 'uncategorized'"
              :class="{ 'needs-attention': bucket.taxonomy_flags.needs_attention }"
              :aria-label="text.rowLabel(number(rowNumber(index)))"
            >
              <td class="is-sequence" dir="ltr">{{ number(rowNumber(index)) }}</td>
              <td class="is-category">
                <WorksIndexInfoPopover :label="text.categoryInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__category">
                      <strong>{{ safeText(displayName(bucket)) }}</strong>
                      <small>{{ categoryState(bucket) }}</small>
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.category }}</dt><dd>{{ safeText(displayName(bucket)) }}</dd></div>
                    <div><dt>{{ text.textIdentifier }}</dt><dd dir="ltr">{{ safeText(bucket.category?.slug || text.none) }}</dd></div>
                    <div><dt>{{ text.identifier }}</dt><dd dir="ltr">{{ bucket.category_id === null ? text.none : `#${number(bucket.category_id)}` }}</dd></div>
                    <div><dt>{{ text.state }}</dt><dd>{{ categoryState(bucket) }}</dd></div>
                    <div><dt>{{ text.catalogLink }}</dt><dd>{{ linkState(bucket) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-distribution">
                <WorksIndexInfoPopover :label="text.distributionInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__count">
                      <strong dir="ltr">{{ number(bucket.works_count) }}</strong>
                      <small>{{ text.works }}</small>
                      <em v-if="bucket.published_count > 0">{{ text.published }}: <span dir="ltr">{{ number(bucket.published_count) }}</span></em>
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.total }}</dt><dd>{{ number(bucket.works_count) }}</dd></div>
                    <div><dt>{{ text.published }}</dt><dd>{{ number(bucket.published_count) }}</dd></div>
                    <div><dt>{{ text.hidden }}</dt><dd>{{ number(bucket.hidden_count) }}</dd></div>
                    <div><dt>{{ text.inReview }}</dt><dd>{{ number(bucket.review_queue_count) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-risk">
                <WorksIndexInfoPopover :label="text.riskInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__risk">
                      <b :class="riskState(bucket).tone">{{ riskState(bucket).label }}</b>
                      <small v-if="riskState(bucket).count > 0">{{ text.reports }}: <span dir="ltr">{{ number(riskState(bucket).count) }}</span></small>
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.reportedWorks }}</dt><dd>{{ number(bucket.reported_count) }}</dd></div>
                    <div><dt>{{ text.totalReports }}</dt><dd>{{ number(bucket.total_reports) }}</dd></div>
                    <div><dt>{{ text.inReview }}</dt><dd>{{ number(bucket.review_queue_count) }}</dd></div>
                    <div><dt>{{ text.needsAttention }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.needs_attention) }}</dd></div>
                    <div><dt>{{ text.attentionReason }}</dt><dd>{{ attentionReason(bucket) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-promotion">
                <WorksIndexInfoPopover :label="text.promotionInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__promotion">
                      <strong>{{ bucket.taxonomy_flags.has_published ? text.hasPublished : text.noPublished }}</strong>
                      <small>
                        <i v-if="bucket.featured_count > 0" :aria-label="text.featured">★</i>
                        <i v-if="bucket.pinned_count > 0" :aria-label="text.pinned">📌</i>
                        <span v-if="bucket.featured_count === 0 && bucket.pinned_count === 0">{{ text.noPromotion }}</span>
                      </small>
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.published }}</dt><dd>{{ number(bucket.published_count) }}</dd></div>
                    <div><dt>{{ text.hidden }}</dt><dd>{{ number(bucket.hidden_count) }}</dd></div>
                    <div><dt>{{ text.featured }}</dt><dd>{{ number(bucket.featured_count) }}</dd></div>
                    <div><dt>{{ text.pinned }}</dt><dd>{{ number(bucket.pinned_count) }}</dd></div>
                    <div><dt>{{ text.hasPublished }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_published) }}</dd></div>
                    <div><dt>{{ text.hasHidden }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_hidden) }}</dd></div>
                    <div><dt>{{ text.promoted }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.is_promoted) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-engagement">
                <WorksIndexInfoPopover :label="text.engagementInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__number" :class="{ 'is-alert': metric === 'total_reports' && bucket.total_reports > 0 }">
                      {{ number(bucket[metric]) }}
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.views }}</dt><dd>{{ number(bucket.total_views) }}</dd></div>
                    <div><dt>{{ text.likes }}</dt><dd>{{ number(bucket.total_likes) }}</dd></div>
                    <div :class="{ 'is-alert': bucket.total_reports > 0 }"><dt>{{ text.reports }}</dt><dd>{{ number(bucket.total_reports) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-tracking">
                <WorksIndexInfoPopover :label="text.trackingInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__tracking">
                      <strong>{{ linkState(bucket) }}</strong>
                      <small v-if="trackingExtraCount(bucket) > 0" dir="ltr">+{{ number(trackingExtraCount(bucket)) }}</small>
                    </span>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.catalogLink }}</dt><dd>{{ linkState(bucket) }}</dd></div>
                    <div><dt>{{ text.uncategorized }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.uncategorized) }}</dd></div>
                    <div><dt>{{ text.legacy }}</dt><dd>{{ yesNo(bucket.category_tracking.is_legacy_unmapped) }}</dd></div>
                    <div><dt>{{ text.hasPublished }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_published) }}</dd></div>
                    <div><dt>{{ text.hasHidden }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.has_hidden) }}</dd></div>
                    <div><dt>{{ text.promoted }}</dt><dd>{{ yesNo(bucket.taxonomy_flags.is_promoted) }}</dd></div>
                    <div><dt>{{ text.featured }}</dt><dd>{{ number(bucket.featured_count) }}</dd></div>
                    <div><dt>{{ text.pinned }}</dt><dd>{{ number(bucket.pinned_count) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-date">
                <WorksIndexInfoPopover :label="text.dateInfo" :hint="text.openInfo" :close-label="text.close">
                  <template #trigger>
                    <time class="ym-taxonomy-smart-list__date" :datetime="bucket.latest_work_at || undefined">{{ shortDate(bucket.latest_work_at) }}</time>
                  </template>
                  <dl class="ym-smart-list__popover-list">
                    <div><dt>{{ text.lastActivity }}</dt><dd class="is-date-value">{{ fullDate(bucket.latest_work_at) }}</dd></div>
                  </dl>
                </WorksIndexInfoPopover>
              </td>
              <td class="is-actions">
                <WorksIndexFloatingOverlay :label="text.viewSummary" :description="text.viewSummary" :trigger-aria-label="text.openSummaryFor(safeText(displayName(bucket)))" @activate="$emit('details', bucket)">
                  <template #trigger>
                    <span class="ym-taxonomy-smart-list__action" aria-hidden="true">
                      <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                    </span>
                  </template>
                </WorksIndexFloatingOverlay>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ym-taxonomy-smart-list__mobile">
        <article v-for="(bucket, index) in items" :key="bucket.category_id ?? 'uncategorized'">
          <header>
            <span class="ym-taxonomy-smart-list__row-number" :aria-label="text.rowLabel(number(rowNumber(index)))" dir="ltr">#{{ number(rowNumber(index)) }}</span>
            <div><strong>{{ safeText(displayName(bucket)) }}</strong><small>{{ categoryState(bucket) }}</small></div>
            <span :class="{ 'is-alert': bucket.taxonomy_flags.needs_attention }">{{ riskState(bucket).label }}</span>
          </header>
          <div class="ym-taxonomy-smart-list__mobile-meta">
            <span>{{ linkState(bucket) }}</span>
            <time :datetime="bucket.latest_work_at || undefined">{{ shortDate(bucket.latest_work_at) }}</time>
          </div>
          <dl>
            <div><dt>{{ text.distribution }}</dt><dd>{{ text.works }}: {{ number(bucket.works_count) }}</dd></div>
            <div><dt>{{ text.risk }}</dt><dd>{{ riskState(bucket).label }}</dd></div>
            <div><dt>{{ metricLabel }}</dt><dd class="is-number">{{ number(bucket[metric]) }}</dd></div>
            <div><dt>{{ text.promotion }}</dt><dd>{{ bucket.taxonomy_flags.has_published ? text.hasPublished : text.noPublished }}</dd></div>
          </dl>
          <footer>
            <WorksIndexInfoPopover :label="text.moreInfo" :hint="text.openInfo" :close-label="text.close">
              <template #trigger><span class="ym-taxonomy-smart-list__more">{{ text.moreInfo }}</span></template>
              <dl class="ym-smart-list__popover-list">
                <div><dt>{{ text.total }}</dt><dd>{{ number(bucket.works_count) }}</dd></div>
                <div><dt>{{ text.published }}</dt><dd>{{ number(bucket.published_count) }}</dd></div>
                <div><dt>{{ text.hidden }}</dt><dd>{{ number(bucket.hidden_count) }}</dd></div>
                <div><dt>{{ text.inReview }}</dt><dd>{{ number(bucket.review_queue_count) }}</dd></div>
                <div><dt>{{ text.views }}</dt><dd>{{ number(bucket.total_views) }}</dd></div>
                <div><dt>{{ text.likes }}</dt><dd>{{ number(bucket.total_likes) }}</dd></div>
                <div><dt>{{ text.reports }}</dt><dd>{{ number(bucket.total_reports) }}</dd></div>
                <div><dt>{{ text.lastActivity }}</dt><dd class="is-date-value">{{ fullDate(bucket.latest_work_at) }}</dd></div>
              </dl>
            </WorksIndexInfoPopover>
            <button type="button" class="ym-taxonomy-smart-list__mobile-details" :aria-label="text.openSummaryFor(safeText(displayName(bucket)))" @click="$emit('details', bucket)">
              {{ text.details }}
            </button>
          </footer>
        </article>
      </div>

      <footer class="ym-taxonomy-smart-list__pagination">
        <span>{{ text.showing(number(items.length), number(pagination.total)) }}</span>
        <nav :aria-label="text.pagination">
          <button type="button" :disabled="loading || pagination.current_page <= 1" @click="$emit('page', pagination.current_page - 1)">{{ text.previous }}</button>
          <b>{{ text.pageOf(number(pagination.current_page), number(pagination.last_page)) }}</b>
          <button type="button" :disabled="loading || pagination.current_page >= pagination.last_page" @click="$emit('page', pagination.current_page + 1)">{{ text.next }}</button>
        </nav>
      </footer>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WorksIndexFloatingOverlay from '~/components/works/index/WorksIndexFloatingOverlay.vue'
import WorksIndexInfoPopover from '~/components/works/index/WorksIndexInfoPopover.vue'
import { formatYmDate, formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

type Locale = 'ar' | 'en'
type MetricKey = 'total_views' | 'total_likes' | 'total_reports'
interface TaxonomyFlags { uncategorized:boolean;has_reports:boolean;has_published:boolean;has_hidden:boolean;is_promoted:boolean;needs_attention:boolean }
interface Bucket { category_id:number|null;label:string;category:{name_ar:string;name_en:string;slug:string;is_active:boolean}|null;category_tracking:{catalog_record_exists:boolean;is_legacy_unmapped:boolean;is_uncategorized:boolean};works_count:number;published_count:number;hidden_count:number;review_queue_count:number;reported_count:number;featured_count:number;pinned_count:number;total_reports:number;total_views:number;total_likes:number;latest_work_at:string|null;taxonomy_flags:TaxonomyFlags }
const props=defineProps<{locale:Locale;items:Bucket[];pagination:{current_page:number;per_page:number;total:number;last_page:number};loading:boolean;hasLoaded:boolean;error:string|null;metric:MetricKey;sort:string;direction:'asc'|'desc'}>()
const copies={
  ar:{updating:'جارٍ تحديث النتائج…',loading:'جارٍ تحميل التجميعات',error:'تعذر تحميل التجميعات',retry:'إعادة المحاولة',empty:'لا توجد تجميعات مطابقة',emptyCopy:'غيّر الفلاتر أو أعد ضبطها.',reset:'إعادة ضبط الفلاتر',category:'التصنيف',distribution:'توزيع الأعمال',risk:'المراجعة والمخاطر',promotion:'الظهور والترويج',engagement:'التفاعل',metric:'مؤشر التفاعل',tracking:'الارتباط والمؤشرات',lastActivity:'آخر نشاط',actions:'الإجراءات',views:'المشاهدات',likes:'الإعجابات',reports:'البلاغات',categoryInfo:'معلومات التصنيف',distributionInfo:'تفاصيل توزيع الأعمال',riskInfo:'تفاصيل المراجعة والمخاطر',promotionInfo:'تفاصيل الظهور والترويج',engagementInfo:'تفاصيل التفاعل',trackingInfo:'تفاصيل الارتباط والمؤشرات',dateInfo:'تفاصيل آخر نشاط',openInfo:'عرض المعلومات',close:'إغلاق',textIdentifier:'المعرّف النصي',identifier:'رقم التعريف',state:'الحالة',catalogLink:'الارتباط بالكتالوج',none:'غير متوفر',active:'تصنيف فعال',disabled:'تصنيف معطل',uncategorized:'غير مصنف',legacy:'قيمة قديمة غير مرتبطة',linked:'مرتبط',works:'الأعمال',total:'الإجمالي',published:'منشورة',hidden:'مخفية',inReview:'ضمن المراجعة',reportedWorks:'أعمال مبلّغ عنها',totalReports:'مجموع البلاغات',needsAttention:'يحتاج انتباهًا',attentionReason:'سبب الانتباه',noRisks:'لا توجد مخاطر',hasReports:'توجد بلاغات',hasReview:'ضمن المراجعة',hasPublished:'لديه منشور',noPublished:'لا توجد أعمال منشورة',hasHidden:'لديه مخفي',featured:'مميزة',pinned:'مثبتة',promoted:'مروّج',noPromotion:'دون ترويج',yes:'نعم',no:'لا',multipleReasons:'عدة مؤشرات تتطلب الانتباه',reportsReason:'توجد أعمال مبلّغ عنها',uncategorizedReason:'التجميع غير مصنف',hiddenReason:'توجد أعمال مخفية',stable:'مستقر',viewSummary:'عرض تفاصيل التجميع',details:'التفاصيل',moreInfo:'معلومات إضافية',pagination:'التنقل بين صفحات التجميعات',previous:'السابق',next:'التالي',showing:(visible:string,total:string)=>`عرض ${visible} من أصل ${total}`,pageOf:(page:string,last:string)=>`الصفحة ${page} من ${last}`,openSummaryFor:(name:string)=>`عرض تفاصيل التجميع ${name}`,rowLabel:(number:string)=>`الصف رقم ${number}`},
  en:{updating:'Updating results…',loading:'Loading buckets',error:'Could not load buckets',retry:'Retry',empty:'No matching buckets',emptyCopy:'Change or reset the filters.',reset:'Reset filters',category:'Category',distribution:'Work distribution',risk:'Review and risk',promotion:'Visibility and promotion',engagement:'Engagement',metric:'Engagement metric',tracking:'Link and indicators',lastActivity:'Last activity',actions:'Actions',views:'Views',likes:'Likes',reports:'Reports',categoryInfo:'Category information',distributionInfo:'Work distribution details',riskInfo:'Review and risk details',promotionInfo:'Visibility and promotion details',engagementInfo:'Engagement details',trackingInfo:'Link and indicator details',dateInfo:'Last activity details',openInfo:'Open information',close:'Close',textIdentifier:'Text identifier',identifier:'Identifier',state:'State',catalogLink:'Catalog link',none:'Unavailable',active:'Active category',disabled:'Disabled category',uncategorized:'Uncategorized',legacy:'Unlinked legacy value',linked:'Linked',works:'works',total:'Total',published:'Published',hidden:'Hidden',inReview:'In review',reportedWorks:'Reported works',totalReports:'Total reports',needsAttention:'Needs attention',attentionReason:'Attention reason',noRisks:'No risks',hasReports:'Reports exist',hasReview:'In review',hasPublished:'Has published',noPublished:'No published works',hasHidden:'Has hidden',featured:'Featured',pinned:'Pinned',promoted:'Promoted',noPromotion:'No promotion',yes:'Yes',no:'No',multipleReasons:'Multiple indicators need attention',reportsReason:'Reported works exist',uncategorizedReason:'Bucket is uncategorized',hiddenReason:'Hidden works exist',stable:'Stable',viewSummary:'View bucket details',details:'Details',moreInfo:'More information',pagination:'Bucket pagination',previous:'Previous',next:'Next',showing:(visible:string,total:string)=>`Showing ${visible} of ${total}`,pageOf:(page:string,last:string)=>`Page ${page} of ${last}`,openSummaryFor:(name:string)=>`View details for ${name}`,rowLabel:(number:string)=>`Row ${number}`}
} as const
const text=computed(()=>copies[props.locale])
const metricLabel=computed(()=>({total_views:text.value.views,total_likes:text.value.likes,total_reports:text.value.reports})[props.metric])
function changeMetric(event:Event){const value=(event.target as HTMLSelectElement).value as MetricKey;if(['total_views','total_likes','total_reports'].includes(value))emitMetric(value)}
const emit=defineEmits<{retry:[];reset:[];page:[page:number];metricChange:[metric:MetricKey];details:[bucket:Bucket]}>()
function emitMetric(value:MetricKey){emit('metricChange',value)}
function number(value:number){return formatYmNumber(Number.isFinite(value)?value:0,props.locale)}
function safeText(value:string|number){return toLatinDigits(value)}
function shortDate(value:string|null){return value?formatYmDate(value,props.locale):(props.locale==='ar'?'لا يوجد نشاط':'No activity')}
function fullDate(value:string|null){return value?formatYmDateTime(value,props.locale):(props.locale==='ar'?'لا يوجد نشاط':'No activity')}
function displayName(bucket:Bucket){if(bucket.category)return props.locale==='ar'?bucket.category.name_ar:bucket.category.name_en;const label=toLatinDigits(bucket.label);if(props.locale==='ar'&&bucket.category_tracking.is_legacy_unmapped&&!label.startsWith('تصنيف قديم'))return label.replace(/^تصنيف(?=\s*#)/,'تصنيف قديم');return label}
function categoryState(bucket:Bucket){if(bucket.category_tracking.is_legacy_unmapped)return text.value.legacy;if(bucket.category_tracking.is_uncategorized)return text.value.uncategorized;return bucket.category?.is_active?text.value.active:text.value.disabled}
function linkState(bucket:Bucket){if(bucket.category_tracking.is_legacy_unmapped)return text.value.legacy;if(bucket.category_tracking.is_uncategorized)return text.value.uncategorized;return text.value.linked}
function yesNo(value:boolean){return value?text.value.yes:text.value.no}
function riskState(bucket:Bucket){if(bucket.taxonomy_flags.needs_attention)return{label:text.value.needsAttention,tone:'is-alert',count:bucket.total_reports};if(bucket.total_reports>0)return{label:text.value.hasReports,tone:'is-warning',count:bucket.total_reports};if(bucket.review_queue_count>0)return{label:text.value.hasReview,tone:'is-warning',count:0};return{label:text.value.noRisks,tone:'is-stable',count:0}}
function attentionReason(bucket:Bucket){const reasons=[bucket.taxonomy_flags.has_reports&&text.value.reportsReason,bucket.taxonomy_flags.uncategorized&&text.value.uncategorizedReason,bucket.taxonomy_flags.has_hidden&&text.value.hiddenReason].filter(Boolean) as string[];return reasons.length>1?text.value.multipleReasons:(reasons[0]||text.value.stable)}
function trackingExtraCount(bucket:Bucket){return [bucket.taxonomy_flags.has_published,bucket.taxonomy_flags.has_hidden,bucket.taxonomy_flags.is_promoted,bucket.featured_count>0,bucket.pinned_count>0].filter(Boolean).length}
function rowNumber(index:number){return((props.pagination.current_page-1)*props.pagination.per_page)+index+1}
</script>

<style scoped>
.ym-taxonomy-smart-list{border:1px solid color-mix(in srgb,var(--ym-card-border) 82%,#0f766e 18%);border-radius:18px;padding:7px;background:linear-gradient(145deg,color-mix(in srgb,var(--ym-card-bg) 97%,#0f766e 3%),var(--ym-card-bg));box-shadow:0 16px 38px rgba(2,6,23,.08)}.ym-taxonomy-smart-list__desktop{width:100%;min-width:0;overflow:hidden;border-radius:15px}.ym-taxonomy-smart-list table{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:0 7px}.ym-taxonomy-smart-list col.is-category{width:18%}.ym-taxonomy-smart-list col.is-distribution{width:12%}.ym-taxonomy-smart-list col.is-risk{width:14%}.ym-taxonomy-smart-list col.is-promotion{width:15%}.ym-taxonomy-smart-list col.is-engagement{width:12%}.ym-taxonomy-smart-list col.is-tracking{width:13%}.ym-taxonomy-smart-list col.is-date{width:10%}.ym-taxonomy-smart-list col.is-actions{width:6%}.ym-taxonomy-smart-list th,.ym-taxonomy-smart-list td{box-sizing:border-box;min-width:0;padding:9px 8px;text-align:start;vertical-align:middle}.ym-taxonomy-smart-list th{height:58px;border-block:1px solid color-mix(in srgb,var(--ym-card-border) 70%,#0891b2 30%);color:var(--ym-text);background:linear-gradient(115deg,color-mix(in srgb,var(--ym-dropdown-bg) 94%,#0f766e 6%),color-mix(in srgb,var(--ym-dropdown-bg) 96%,#0891b2 4%));font-size:13px;font-weight:850}.ym-taxonomy-smart-list th:first-child{border-start-start-radius:13px}.ym-taxonomy-smart-list th:last-child{border-start-end-radius:13px}.ym-taxonomy-smart-list td{height:80px;border-block:1px solid color-mix(in srgb,var(--ym-card-border) 84%,#0f766e 16%);color:var(--ym-muted);background:color-mix(in srgb,var(--ym-control-bg) 95%,transparent);font-size:13px}.ym-taxonomy-smart-list th:not(:last-child),.ym-taxonomy-smart-list td:not(:last-child){border-inline-end:1px solid color-mix(in srgb,var(--ym-card-border) 88%,#0f766e 12%)}.ym-taxonomy-smart-list tbody td:first-child{border-inline-start:3px solid #64748b;border-start-start-radius:12px;border-end-start-radius:12px}.ym-taxonomy-smart-list tbody tr.needs-attention td:first-child{border-inline-start-color:#f59e0b}.ym-taxonomy-smart-list tbody td:last-child{border-start-end-radius:12px;border-end-end-radius:12px}.ym-taxonomy-smart-list tbody tr:hover td{border-block-color:color-mix(in srgb,#0f766e 34%,var(--ym-card-border));background:color-mix(in srgb,var(--ym-control-bg) 92%,#0f766e 8%)}.ym-taxonomy-smart-list th.is-engagement,.ym-taxonomy-smart-list td.is-engagement,.ym-taxonomy-smart-list th.is-date,.ym-taxonomy-smart-list td.is-date,.ym-taxonomy-smart-list th.is-actions,.ym-taxonomy-smart-list td.is-actions{text-align:center}.ym-taxonomy-smart-list td :deep(.ym-floating-overlay){width:100%}.ym-taxonomy-smart-list td.is-actions{position:relative}.ym-taxonomy-smart-list__head-title{display:block;margin-bottom:3px;color:var(--ym-muted);font-size:11.5px}.ym-taxonomy-smart-list__head-control{display:block;border:1px solid var(--ym-control-border);border-radius:9px;background:var(--ym-input-bg)}.ym-taxonomy-smart-list__head-control select{width:100%;min-height:30px;border:0;padding:0 5px;color:var(--ym-text);background:transparent;font-size:11.5px;font-weight:800}.ym-taxonomy-smart-list__category,.ym-taxonomy-smart-list__count,.ym-taxonomy-smart-list__risk,.ym-taxonomy-smart-list__promotion,.ym-taxonomy-smart-list__tracking{display:grid;min-width:0;gap:4px}.ym-taxonomy-smart-list__category strong,.ym-taxonomy-smart-list__promotion strong,.ym-taxonomy-smart-list__tracking strong{overflow:hidden;color:var(--ym-text);font-size:14px;font-weight:850;text-overflow:ellipsis;white-space:nowrap}.ym-taxonomy-smart-list__category small,.ym-taxonomy-smart-list__promotion small,.ym-taxonomy-smart-list__tracking small{color:var(--ym-muted);font-size:12px}.ym-taxonomy-smart-list__count strong{direction:ltr;color:var(--ym-text);font-size:20px;font-weight:950;font-variant-numeric:tabular-nums}.ym-taxonomy-smart-list__count small{font-size:12px}.ym-taxonomy-smart-list__count em{color:#0e7490;font-size:11.5px;font-style:normal}.ym-taxonomy-smart-list__risk b{width:max-content;max-width:100%;border-radius:999px;padding:4px 8px;font-size:11.5px;white-space:nowrap}.ym-taxonomy-smart-list__risk b.is-alert{color:#fff1f2;background:#9f1239}.ym-taxonomy-smart-list__risk b.is-warning{color:#fffbeb;background:#92400e}.ym-taxonomy-smart-list__risk b.is-stable{color:var(--ym-muted);background:color-mix(in srgb,#64748b 12%,transparent)}.ym-taxonomy-smart-list__risk small{font-size:11.5px}.ym-taxonomy-smart-list__promotion small{display:flex;align-items:center;gap:6px}.ym-taxonomy-smart-list__promotion i{color:#d97706;font-style:normal}.ym-taxonomy-smart-list__number{display:inline-grid;min-width:52px;min-height:38px;direction:ltr;unicode-bidi:isolate;place-items:center;border:1px solid var(--ym-control-border);border-radius:10px;color:var(--ym-text);background:var(--ym-input-bg);font-size:16px;font-weight:900;font-variant-numeric:tabular-nums}.ym-taxonomy-smart-list__number.is-alert{border-color:#fb7185;color:#e11d48;background:color-mix(in srgb,#e11d48 9%,transparent)}.ym-taxonomy-smart-list__tracking small{width:max-content;border-radius:999px;padding:3px 7px;color:var(--ym-text);background:color-mix(in srgb,#64748b 13%,transparent);font-weight:900}.ym-taxonomy-smart-list__date,.ym-taxonomy-smart-list .is-date-value{direction:ltr;unicode-bidi:isolate;color:var(--ym-text);font-size:13px;font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap}.ym-smart-list__popover-list{display:grid;gap:8px;margin:0}.ym-smart-list__popover-list>div{display:grid;gap:3px;border-bottom:1px solid var(--ym-card-border);padding-bottom:8px}.ym-smart-list__popover-list>div:last-child{border-bottom:0;padding-bottom:0}.ym-smart-list__popover-list dt{color:var(--ym-muted);font-size:12px}.ym-smart-list__popover-list dd{margin:0;color:var(--ym-text);font-size:13px;line-height:1.55;overflow-wrap:anywhere}.ym-smart-list__popover-list .is-alert dd{color:#fb7185}.ym-taxonomy-smart-list__action{display:grid;width:38px;height:38px;place-items:center;border:1px solid var(--ym-control-border);border-radius:10px;color:#ecfeff;background:#0e7490}.ym-taxonomy-smart-list__action svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8}.ym-taxonomy-smart-list__action-hit{position:absolute;inset:0;width:100%;border:0;background:transparent;cursor:pointer}.ym-taxonomy-smart-list__state{display:grid;min-height:220px;place-items:center;align-content:center;gap:9px;text-align:center}.ym-taxonomy-smart-list__state p{margin:0;color:var(--ym-muted)}.ym-taxonomy-smart-list__state button,.ym-taxonomy-smart-list__pagination button,.ym-taxonomy-smart-list__mobile-details{min-height:38px;border:1px solid var(--ym-control-border);border-radius:10px;padding:0 12px;color:var(--ym-text);background:var(--ym-input-bg)}.ym-taxonomy-smart-list__spinner{width:32px;height:32px;border:3px solid color-mix(in srgb,#0f766e 18%,transparent);border-top-color:#0f766e;border-radius:50%;animation:spin .8s linear infinite}.ym-taxonomy-smart-list__update{border-radius:10px;margin:0 0 7px;padding:7px 9px;color:#0f766e;background:color-mix(in srgb,#0f766e 9%,transparent);font-size:12px;font-weight:850}.ym-taxonomy-smart-list__pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--ym-card-border);margin-top:8px;padding:10px 5px 3px;color:var(--ym-muted);font-size:13px}.ym-taxonomy-smart-list__pagination nav{display:flex;align-items:center;gap:8px}.ym-taxonomy-smart-list__pagination b{direction:ltr;unicode-bidi:isolate;color:var(--ym-text)}.ym-taxonomy-smart-list__mobile{display:none;gap:10px}.ym-taxonomy-smart-list__mobile article{display:grid;gap:10px;border:1px solid var(--ym-card-border);border-radius:15px;padding:13px;background:var(--ym-control-bg)}.ym-taxonomy-smart-list__mobile header,.ym-taxonomy-smart-list__mobile-meta,.ym-taxonomy-smart-list__mobile footer{display:flex;align-items:center;justify-content:space-between;gap:9px}.ym-taxonomy-smart-list__mobile header>div{display:grid;gap:3px}.ym-taxonomy-smart-list__mobile header strong{color:var(--ym-text);font-size:15px}.ym-taxonomy-smart-list__mobile header small,.ym-taxonomy-smart-list__mobile-meta{color:var(--ym-muted);font-size:12px}.ym-taxonomy-smart-list__mobile header>span{border-radius:999px;padding:4px 8px;color:var(--ym-muted);background:color-mix(in srgb,#64748b 12%,transparent);font-size:11.5px}.ym-taxonomy-smart-list__mobile header>span.is-alert{color:#fff1f2;background:#9f1239}.ym-taxonomy-smart-list__mobile dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:0}.ym-taxonomy-smart-list__mobile dl>div{border-radius:10px;padding:8px;background:color-mix(in srgb,var(--ym-card-bg) 62%,transparent)}.ym-taxonomy-smart-list__mobile dt{color:var(--ym-muted);font-size:11.5px}.ym-taxonomy-smart-list__mobile dd{margin:3px 0 0;color:var(--ym-text);font-size:13px}.ym-taxonomy-smart-list__mobile dd.is-number{font-size:16px;font-weight:900}.ym-taxonomy-smart-list__mobile footer{border-top:1px solid var(--ym-card-border);padding-top:9px}.ym-taxonomy-smart-list__more{display:inline-flex;min-height:40px;align-items:center;border-radius:10px;padding:0 11px;color:#0f766e;background:color-mix(in srgb,#0f766e 10%,transparent);font-size:13px;font-weight:850}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:1100px){.ym-taxonomy-smart-list__desktop{display:none}.ym-taxonomy-smart-list__mobile{display:grid}}@media(max-width:600px){.ym-taxonomy-smart-list{padding:7px}.ym-taxonomy-smart-list__mobile dl{grid-template-columns:1fr}.ym-taxonomy-smart-list__mobile footer,.ym-taxonomy-smart-list__pagination{align-items:stretch;flex-direction:column}.ym-taxonomy-smart-list__pagination nav{justify-content:space-between}}@media(prefers-reduced-motion:reduce){.ym-taxonomy-smart-list__spinner{animation:none}}
.ym-taxonomy-smart-list__update.is-error{color:#e11d48;background:color-mix(in srgb,#e11d48 9%,transparent)}.ym-taxonomy-smart-list__update button{border:0;padding:2px 6px;color:inherit;background:transparent;font-weight:900;text-decoration:underline}.ym-taxonomy-smart-list button:focus-visible,.ym-taxonomy-smart-list select:focus-visible{outline:3px solid color-mix(in srgb,#0f766e 38%,transparent);outline-offset:2px}
.ym-taxonomy-smart-list td{border-block-color:var(--ym-card-border)}.ym-taxonomy-smart-list th:not(:last-child),.ym-taxonomy-smart-list td:not(:last-child){border-inline-end-color:color-mix(in srgb,var(--ym-card-border) 55%,transparent)}.ym-taxonomy-smart-list tbody tr.needs-attention td{background:color-mix(in srgb,var(--ym-control-bg) 96%,#f59e0b 4%)}.ym-taxonomy-smart-list tbody tr:hover td{border-block-color:color-mix(in srgb,var(--ym-card-border) 72%,var(--ym-text) 28%);background:color-mix(in srgb,var(--ym-control-bg) 96%,var(--ym-card-bg) 4%)}.ym-taxonomy-smart-list__head-title{display:flex;min-height:18px;align-items:center;justify-content:center;gap:5px;margin-bottom:2px;color:var(--ym-text);font-size:12px;font-weight:850}.ym-taxonomy-smart-list__head-title i{color:#0891b2;font-size:13px;font-style:normal}
</style>
<style src="../../../assets/css/works-taxonomy-smart-table.css"></style>
