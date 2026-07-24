<template>
  <WorksDrawerShell
    :open="open"
    :locale="locale"
    size="details"
    title-id="ym-works-details-drawer-title"
    :close-label="text.close"
    @request-close="$emit('close')"
  >
    <template #header>
      <span class="ym-details-head__eyebrow">{{ text.eyebrow }}</span>
      <h2 id="ym-works-details-drawer-title">{{ text.title }}</h2>
      <strong class="ym-details-head__work" :dir="textDirection(detail?.work.title || selectedTitle)">
        {{ detail?.work.title || selectedTitle || text.loadingTitle }}
      </strong>
      <div v-if="detail" class="ym-details-head__meta">
        <span :class="`is-${detail.work.status}`">{{ statusLabel(detail.work.status) }}</span>
        <span>{{ mediaLabel(detail.work.media_type) }}</span>
      </div>
    </template>

    <div v-if="loading" class="ym-details-state" role="status" aria-live="polite">
      <span class="ym-details-spinner" aria-hidden="true" />
      <h3>{{ text.loadingTitle }}</h3>
      <p>{{ text.loadingCopy }}</p>
      <div class="ym-details-skeleton" aria-hidden="true"><i /><i /><i /></div>
    </div>

    <div v-else-if="error" class="ym-details-state is-error" role="alert">
      <span class="ym-details-state__icon" aria-hidden="true">!</span>
      <h3>{{ text.errorTitle }}</h3>
      <p>{{ error }}</p>
      <button type="button" class="ym-details-button is-secondary" @click="$emit('retry')">{{ text.retry }}</button>
    </div>

    <div v-else-if="detail" class="ym-details-content">
      <section class="ym-details-identity">
        <div class="ym-details-badges">
          <span :class="`is-status-${detail.work.status}`">{{ statusLabel(detail.work.status) }}</span>
          <span>{{ visibilityLabel(detail.work.visibility_status) }}</span>
          <span>{{ mediaLabel(detail.work.media_type) }}</span>
        </div>
        <h3 :dir="textDirection(detail.work.title)">{{ detail.work.title }}</h3>
        <code dir="ltr">{{ detail.work.slug }}</code>
        <p v-if="detail.work.summary" :dir="textDirection(detail.work.summary)">{{ detail.work.summary }}</p>
        <div v-else class="ym-details-empty">{{ text.noSummary }}</div>
      </section>

      <section class="ym-details-metrics" :aria-label="text.metrics">
        <article class="is-views">
          <span>{{ text.views }}</span>
          <strong>{{ formatNumber(detail.work.views_count) }}</strong>
        </article>
        <article class="is-likes">
          <span>{{ text.likes }}</span>
          <strong>{{ formatNumber(detail.work.likes_count) }}</strong>
        </article>
        <article class="is-reports">
          <span>{{ text.reports }}</span>
          <strong>{{ formatNumber(detail.work.reports_count) }}</strong>
        </article>
      </section>

      <section class="ym-details-section">
        <header><h3>{{ text.basic }}</h3></header>
        <dl class="ym-details-facts">
          <div><dt>{{ text.price }}</dt><dd dir="ltr">{{ displayValue(detail.work.price_amount) }}</dd></div>
          <div><dt>{{ text.delivery }}</dt><dd>{{ detail.work.delivery_days === null ? '—' : formatNumber(detail.work.delivery_days) }}</dd></div>
          <div><dt>{{ text.mediaType }}</dt><dd>{{ mediaLabel(detail.work.media_type) }}</dd></div>
          <div><dt>{{ text.featured }}</dt><dd>{{ booleanLabel(detail.work.is_featured) }}</dd></div>
          <div><dt>{{ text.pinned }}</dt><dd>{{ booleanLabel(detail.work.is_pinned) }}</dd></div>
        </dl>
      </section>

      <section class="ym-details-section">
        <header><h3>{{ text.team }}</h3></header>
        <dl v-if="detail.field_access.can_view_designer" class="ym-details-facts">
          <div><dt>{{ text.designer }}</dt><dd>{{ detail.relations.designer?.name || text.unassigned }}</dd></div>
          <div><dt>{{ text.reviewer }}</dt><dd>{{ detail.relations.reviewer?.name || text.unassigned }}</dd></div>
        </dl>
        <p v-else class="ym-details-unavailable">{{ text.permissionUnavailable }}</p>
      </section>

      <section class="ym-details-section is-taxonomy">
        <header>
          <div><h3>{{ text.taxonomy }}</h3><p>{{ text.taxonomyCopy }}</p></div>
          <button v-if="canManageTaxonomy" type="button" class="ym-details-button is-secondary" @click="$emit('manageTaxonomy')">
            {{ text.manageTaxonomy }}
          </button>
        </header>
        <div class="ym-details-taxonomy">
          <div>
            <span>{{ text.category }}</span>
            <template v-if="detail.taxonomy.category">
              <strong>{{ taxonomyName(detail.taxonomy.category) }}</strong>
              <small :class="detail.taxonomy.category.is_active ? 'is-active' : 'is-disabled'">
                {{ detail.taxonomy.category.is_active ? text.active : text.disabled }}
              </small>
              <code dir="ltr">{{ detail.taxonomy.category.slug }}</code>
            </template>
            <strong v-else-if="detail.taxonomy.category_tracking?.is_legacy_unmapped" class="is-disabled">{{ text.legacy }}</strong>
            <strong v-else-if="detail.taxonomy.category_tracking?.is_uncategorized">{{ text.uncategorized }}</strong>
            <p v-else>{{ text.permissionUnavailable }}</p>
          </div>
          <div>
            <span>{{ text.tags }}</span>
            <div v-if="detail.taxonomy.tags?.length" class="ym-details-tags">
              <span v-for="tag in detail.taxonomy.tags" :key="tag.id" :class="{ 'is-disabled': !tag.is_active }">
                {{ taxonomyName(tag) }}<small v-if="!tag.is_active">{{ text.disabled }}</small>
              </span>
            </div>
            <p v-else-if="detail.taxonomy.tags !== null">{{ text.noTags }}</p>
            <p v-else>{{ text.permissionUnavailable }}</p>
          </div>
        </div>
      </section>

      <section class="ym-details-section">
        <header><h3>{{ text.media }}</h3></header>
        <dl v-if="detail.media" class="ym-details-facts">
          <div><dt>{{ text.mediaAvailable }}</dt><dd>{{ detail.media.has_media ? text.yes : text.no }}</dd></div>
          <div><dt>{{ text.mediaType }}</dt><dd>{{ mediaLabel(detail.media.media_type) }}</dd></div>
        </dl>
        <p v-else class="ym-details-unavailable">{{ text.permissionUnavailable }}</p>
      </section>

      <section class="ym-details-section">
        <header><h3>{{ text.lifecycle }}</h3></header>
        <ol class="ym-details-timeline">
          <li v-for="item in lifecycleItems" :key="item.key" :class="{ 'is-pending': !item.value }">
            <span aria-hidden="true" />
            <div><strong>{{ item.label }}</strong><time v-if="item.value" :datetime="item.value">{{ formatDateTime(item.value) }}</time><small v-else>{{ text.notYet }}</small></div>
          </li>
        </ol>
      </section>

      <section v-if="detail.field_access.can_view_private_notes" class="ym-details-section is-private">
        <header><h3>{{ text.privateNotes }}</h3><p>{{ text.privateCopy }}</p></header>
        <dl v-if="privateNoteItems.length" class="ym-details-notes">
          <div v-for="item in privateNoteItems" :key="item.key"><dt>{{ item.label }}</dt><dd :dir="textDirection(item.value)">{{ item.value }}</dd></div>
        </dl>
        <div v-else class="ym-details-empty">{{ text.noPrivateNotes }}</div>
      </section>

      <details class="ym-details-access">
        <summary>{{ text.accessTitle }}</summary>
        <dl>
          <div><dt>{{ text.accessTeam }}</dt><dd>{{ accessLabel(detail.field_access.can_view_designer) }}</dd></div>
          <div><dt>{{ text.accessMedia }}</dt><dd>{{ accessLabel(detail.field_access.can_view_media) }}</dd></div>
          <div><dt>{{ text.accessMetadata }}</dt><dd>{{ accessLabel(detail.field_access.can_view_metadata) }}</dd></div>
          <div><dt>{{ text.accessPrivate }}</dt><dd>{{ accessLabel(detail.field_access.can_view_private_notes) }}</dd></div>
        </dl>
      </details>
    </div>

    <template v-if="detail" #footer>
      <div class="ym-details-actions">
        <button v-if="canEdit" type="button" class="ym-details-button is-primary" @click="$emit('edit')">{{ text.edit }}</button>
        <button v-if="canManageTaxonomy" type="button" class="ym-details-button is-secondary" @click="$emit('manageTaxonomy')">{{ text.manageTaxonomy }}</button>
        <button type="button" class="ym-details-button is-quiet" @click="$emit('close')">{{ text.close }}</button>
      </div>
    </template>
  </WorksDrawerShell>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import WorksDrawerShell from './WorksDrawerShell.vue'
import { formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

interface Entity { id: number; name_ar: string; name_en: string; slug: string; is_active: boolean }
interface DetailData {
  work: {
    id: number; title: string; slug: string; summary: string | null; status: string; visibility_status: string
    media_type: string | null; price_amount: string | null; delivery_days: number | null; category_id: number | null
    is_featured: boolean; is_pinned: boolean; reports_count: number; views_count: number; likes_count: number
    submitted_at: string | null; reviewed_at: string | null; approved_at: string | null; published_at: string | null
    rejected_at: string | null; hidden_at: string | null; archived_at: string | null; updated_at: string | null; created_at: string | null
  }
  taxonomy: { category: Entity | null; category_tracking: { is_legacy_unmapped: boolean; is_uncategorized: boolean } | null; tags: Entity[] | null }
  relations: { designer: { id: number; name: string } | null; reviewer: { id: number; name: string } | null }
  media: { media_type: string | null; has_media: boolean } | null
  private_notes: { internal_notes: string | null; rejection_reason: string | null; change_request_notes: string | null } | null
  field_access: { can_view_designer: boolean; can_view_media: boolean; can_view_metadata: boolean; can_view_private_notes: boolean }
}

const props = defineProps<{
  open: boolean
  locale: 'ar' | 'en'
  selectedTitle: string
  detail: DetailData | null
  loading: boolean
  error: string | null
  canEdit: boolean
  canManageTaxonomy: boolean
}>()
defineEmits<{ close: []; retry: []; edit: []; manageTaxonomy: [] }>()

const copies = {
  ar: {
    eyebrow: 'مساحة تفاصيل العمل', title: 'تفاصيل العمل', close: 'إغلاق التفاصيل', loadingTitle: 'جارٍ تحميل التفاصيل', loadingCopy: 'يتم جلب الحقول المتاحة لهذا الحساب…', errorTitle: 'تعذر تحميل تفاصيل العمل', retry: 'إعادة المحاولة',
    noSummary: 'لا يوجد ملخص مسجل لهذا العمل.', metrics: 'مؤشرات العمل', views: 'المشاهدات', likes: 'الإعجابات', reports: 'البلاغات', basic: 'البيانات الأساسية', price: 'السعر', delivery: 'مدة التسليم', mediaType: 'نوع الوسائط', featured: 'مميز', pinned: 'مثبت',
    team: 'الفريق', designer: 'المصمم', reviewer: 'المراجع', unassigned: 'غير معيّن', permissionUnavailable: 'غير متاح حسب الصلاحية.', taxonomy: 'التصنيف والوسوم', taxonomyCopy: 'الحالة المرتبطة حاليًا بالعمل.', manageTaxonomy: 'إدارة التصنيف والوسوم', category: 'التصنيف', tags: 'الوسوم', active: 'فعال', disabled: 'معطل', legacy: 'تصنيف قديم غير مربوط', uncategorized: 'غير مصنف', noTags: 'لا توجد وسوم',
    media: 'ملخص الوسائط', mediaAvailable: 'وجود الوسائط', yes: 'نعم', no: 'لا', lifecycle: 'دورة الحياة', notYet: 'لم يحدث بعد', privateNotes: 'الملاحظات الخاصة', privateCopy: 'معلومات داخلية تظهر حسب الصلاحية.', internalNotes: 'الملاحظات الداخلية', rejectionReason: 'سبب الرفض', changeRequestNotes: 'ملاحظات طلب التعديلات', noPrivateNotes: 'لا توجد ملاحظات داخلية مسجلة.',
    accessTitle: 'صلاحيات عرض هذه التفاصيل', accessTeam: 'المصمم والمراجع', accessMedia: 'الوسائط', accessMetadata: 'Metadata', accessPrivate: 'الملاحظات الخاصة', available: 'متاح', unavailable: 'غير متاح', edit: 'تحرير العمل',
    image: 'صورة', video: 'فيديو', gallery: 'معرض صور', unknownMedia: 'غير محدد', public: 'عام', hidden: 'مخفي', yesValue: 'نعم', noValue: 'لا',
    submitted: 'تاريخ الإرسال', reviewed: 'تاريخ المراجعة', approved: 'تاريخ الاعتماد', published: 'تاريخ النشر', rejected: 'تاريخ الرفض', hiddenAt: 'تاريخ الإخفاء', archived: 'تاريخ الأرشفة', created: 'تاريخ الإنشاء', updated: 'آخر تحديث'
  },
  en: {
    eyebrow: 'Work detail workspace', title: 'Work details', close: 'Close details', loadingTitle: 'Loading details', loadingCopy: 'Fetching the fields available to this account…', errorTitle: 'Could not load work details', retry: 'Retry',
    noSummary: 'No summary has been recorded for this work.', metrics: 'Work metrics', views: 'Views', likes: 'Likes', reports: 'Reports', basic: 'Basic details', price: 'Price', delivery: 'Delivery time', mediaType: 'Media type', featured: 'Featured', pinned: 'Pinned',
    team: 'Team', designer: 'Designer', reviewer: 'Reviewer', unassigned: 'Unassigned', permissionUnavailable: 'Unavailable for this permission scope.', taxonomy: 'Category and tags', taxonomyCopy: 'The taxonomy currently linked to this work.', manageTaxonomy: 'Manage category and tags', category: 'Category', tags: 'Tags', active: 'Active', disabled: 'Disabled', legacy: 'Unmapped legacy category', uncategorized: 'Uncategorized', noTags: 'No tags',
    media: 'Media summary', mediaAvailable: 'Media available', yes: 'Yes', no: 'No', lifecycle: 'Lifecycle', notYet: 'Not yet', privateNotes: 'Private notes', privateCopy: 'Internal information shown according to permission.', internalNotes: 'Internal notes', rejectionReason: 'Rejection reason', changeRequestNotes: 'Change request notes', noPrivateNotes: 'No internal notes have been recorded.',
    accessTitle: 'Permissions for viewing these details', accessTeam: 'Designer and reviewer', accessMedia: 'Media', accessMetadata: 'Metadata', accessPrivate: 'Private notes', available: 'Available', unavailable: 'Unavailable', edit: 'Edit work',
    image: 'Image', video: 'Video', gallery: 'Gallery', unknownMedia: 'Unspecified', public: 'Public', hidden: 'Hidden', yesValue: 'Yes', noValue: 'No',
    submitted: 'Submitted at', reviewed: 'Reviewed at', approved: 'Approved at', published: 'Published at', rejected: 'Rejected at', hiddenAt: 'Hidden at', archived: 'Archived at', created: 'Created at', updated: 'Updated at'
  }
} as const
const text = computed(() => copies[props.locale])
const lifecycleItems = computed(() => {
  const work = props.detail?.work
  return [
    { key: 'submitted', label: text.value.submitted, value: work?.submitted_at ?? null },
    { key: 'reviewed', label: text.value.reviewed, value: work?.reviewed_at ?? null },
    { key: 'approved', label: text.value.approved, value: work?.approved_at ?? null },
    { key: 'published', label: text.value.published, value: work?.published_at ?? null },
    { key: 'rejected', label: text.value.rejected, value: work?.rejected_at ?? null },
    { key: 'hidden', label: text.value.hiddenAt, value: work?.hidden_at ?? null },
    { key: 'archived', label: text.value.archived, value: work?.archived_at ?? null },
    { key: 'created', label: text.value.created, value: work?.created_at ?? null },
    { key: 'updated', label: text.value.updated, value: work?.updated_at ?? null }
  ]
})
const privateNoteItems = computed(() => {
  const notes = props.detail?.private_notes
  if (!notes) return []
  const items = [
    { key: 'internal', label: text.value.internalNotes, value: notes.internal_notes },
    { key: 'rejection', label: text.value.rejectionReason, value: notes.rejection_reason },
    { key: 'changes', label: text.value.changeRequestNotes, value: notes.change_request_notes }
  ]
  return items.flatMap(item => item.value ? [{ ...item, value: item.value }] : [])
})
const formatNumber = (value: number) => formatYmNumber(value, props.locale)
const formatDateTime = (value: string) => formatYmDateTime(value, props.locale)
const displayValue = (value: string | null) => value ? toLatinDigits(value) : '—'
const booleanLabel = (value: boolean) => value ? text.value.yesValue : text.value.noValue
const accessLabel = (value: boolean) => value ? text.value.available : text.value.unavailable
const textDirection = (value: string) => /[\u0600-\u06FF]/.test(value) ? 'rtl' : 'ltr'
const taxonomyName = (entity: Entity) => props.locale === 'ar' ? entity.name_ar : entity.name_en
const mediaLabel = (value: string | null) => value === 'image' ? text.value.image : value === 'video' ? text.value.video : value === 'gallery' ? text.value.gallery : text.value.unknownMedia
const visibilityLabel = (value: string) => value === 'public' ? text.value.public : text.value.hidden
function statusLabel(value: string): string {
  const ar: Record<string, string> = { draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' }
  const en: Record<string, string> = { draft: 'Draft', submitted: 'Submitted', in_review: 'In review', changes_requested: 'Changes requested', approved: 'Approved', published: 'Published', rejected: 'Rejected', hidden: 'Hidden', archived: 'Archived' }
  return (props.locale === 'ar' ? ar : en)[value] || value
}
</script>

<style scoped>
.ym-details-head__eyebrow { color: var(--ym-drawer-electric); font-size: 12.5px; font-weight: 850; }
.ym-details-head__work { overflow-wrap: anywhere; color: var(--ym-drawer-text); font-size: 17px; line-height: 1.45; }
h2 { margin: 0; color: var(--ym-drawer-text); font-size: clamp(24px, 3vw, 29px); font-weight: 900; line-height: 1.2; }
.ym-details-head__meta, .ym-details-badges { display: flex; flex-wrap: wrap; gap: 7px; }
.ym-details-head__meta span, .ym-details-badges span { border: 1px solid var(--ym-drawer-border); border-radius: 999px; padding: 4px 8px; color: var(--ym-drawer-muted); background: var(--ym-drawer-control); font-size: 12.5px; font-weight: 750; }
.ym-details-head__meta .is-published, .ym-details-badges .is-status-published { color: var(--ym-drawer-emerald); }
.ym-details-head__meta .is-rejected, .ym-details-badges .is-status-rejected { color: var(--ym-drawer-rose); }
.ym-details-head__meta .is-submitted, .ym-details-head__meta .is-in_review, .ym-details-head__meta .is-changes_requested { color: var(--ym-drawer-amber); }
.ym-details-content { display: grid; gap: 16px; }
.ym-details-identity, .ym-details-section { border: 1px solid var(--ym-drawer-soft-border); border-radius: 18px; padding: 19px 20px; background: var(--ym-drawer-card); box-shadow: inset 0 1px 0 color-mix(in srgb, #fff 8%, transparent), 0 12px 28px rgba(2, 6, 23, .06); }
.ym-details-identity { display: grid; gap: 9px; }
.ym-details-identity h3 { margin: 0; color: var(--ym-drawer-text); font-size: 21px; line-height: 1.4; }
.ym-details-identity code { width: fit-content; color: var(--ym-drawer-electric); font-size: 12.5px; overflow-wrap: anywhere; }
.ym-details-identity p { margin: 0; color: var(--ym-drawer-muted); font-size: 14.5px; line-height: 1.75; }
.ym-details-empty, .ym-details-unavailable { border-radius: 12px; padding: 11px 12px; color: var(--ym-drawer-muted); background: var(--ym-drawer-control); font-size: 13.5px; }
.ym-details-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
.ym-details-metrics article { --metric: var(--ym-drawer-cyan); display: grid; min-height: 74px; align-content: center; gap: 4px; border: 1px solid color-mix(in srgb, var(--metric) 30%, var(--ym-drawer-soft-border)); border-radius: 15px; padding: 12px 14px; background: linear-gradient(135deg, color-mix(in srgb, var(--metric) 11%, transparent), var(--ym-drawer-card)); }
.ym-details-metrics article.is-likes { --metric: var(--ym-drawer-magenta); }.ym-details-metrics article.is-reports { --metric: var(--ym-drawer-rose); }
.ym-details-metrics span { color: var(--ym-drawer-muted); font-size: 12.5px; font-weight: 750; }
.ym-details-metrics strong { direction: ltr; unicode-bidi: isolate; color: var(--metric); font-size: 23px; font-variant-numeric: tabular-nums; }
.ym-details-section > header { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 12px; }
.ym-details-section h3 { margin: 0; color: var(--ym-drawer-text); font-size: 18px; line-height: 1.35; }
.ym-details-section header p { margin: 4px 0 0; color: var(--ym-drawer-muted); font-size: 13px; line-height: 1.55; }
.ym-details-facts { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 0; }
.ym-details-facts > div { display: grid; gap: 4px; border-block-end: 1px solid var(--ym-drawer-soft-border); padding: 11px 4px; }
.ym-details-facts dt, .ym-details-notes dt { color: var(--ym-drawer-muted); font-size: 12.5px; }
.ym-details-facts dd, .ym-details-notes dd { margin: 0; color: var(--ym-drawer-text); font-size: 14.5px; font-weight: 700; overflow-wrap: anywhere; }
.ym-details-taxonomy { display: grid; gap: 14px; }
.ym-details-taxonomy > div { display: grid; gap: 6px; border-block-start: 1px solid var(--ym-drawer-soft-border); padding-block-start: 13px; }
.ym-details-taxonomy > div > span { color: var(--ym-drawer-muted); font-size: 12.5px; font-weight: 750; }
.ym-details-taxonomy strong { font-size: 15px; }.ym-details-taxonomy code { width: fit-content; color: var(--ym-drawer-electric); font-size: 12.5px; }.ym-details-taxonomy small.is-active { color: var(--ym-drawer-emerald); }.ym-details-taxonomy small.is-disabled, .ym-details-taxonomy strong.is-disabled { color: var(--ym-drawer-amber); }
.ym-details-taxonomy p { margin: 0; color: var(--ym-drawer-muted); font-size: 13.5px; }
.ym-details-tags { display: flex; flex-wrap: wrap; gap: 7px; }
.ym-details-tags > span { display: inline-flex; align-items: center; gap: 5px; border: 1px solid color-mix(in srgb, var(--ym-drawer-electric) 28%, var(--ym-drawer-soft-border)); border-radius: 999px; padding: 6px 9px; font-size: 13px; }
.ym-details-tags > span.is-disabled { color: var(--ym-drawer-amber); border-color: color-mix(in srgb, var(--ym-drawer-amber) 34%, transparent); }.ym-details-tags small { font-size: 12px; }
.ym-details-timeline { display: grid; gap: 0; margin: 0; padding: 0; list-style: none; }
.ym-details-timeline li { position: relative; display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; min-height: 55px; }
.ym-details-timeline li::after { position: absolute; inset-block: 17px -3px; inset-inline-start: 6px; width: 1px; background: var(--ym-drawer-soft-border); content: ''; }.ym-details-timeline li:last-child::after { display: none; }
.ym-details-timeline li > span { position: relative; z-index: 1; width: 13px; height: 13px; border: 3px solid var(--ym-drawer-surface); border-radius: 50%; margin-block-start: 3px; background: var(--ym-drawer-electric); box-shadow: 0 0 10px color-mix(in srgb, var(--ym-drawer-electric) 30%, transparent); }
.ym-details-timeline li.is-pending > span { background: var(--ym-drawer-muted); box-shadow: none; }
.ym-details-timeline li > div { display: grid; gap: 3px; padding-block-end: 13px; }.ym-details-timeline strong { font-size: 13.5px; }.ym-details-timeline time { direction: ltr; unicode-bidi: isolate; width: fit-content; color: var(--ym-drawer-text); font-size: 14px; font-weight: 700; font-variant-numeric: tabular-nums; }.ym-details-timeline small { color: var(--ym-drawer-muted); font-size: 12.5px; }
.ym-details-section.is-private { border-color: color-mix(in srgb, var(--ym-drawer-amber) 26%, var(--ym-drawer-soft-border)); background: linear-gradient(145deg, color-mix(in srgb, var(--ym-drawer-amber) 7%, transparent), var(--ym-drawer-card)); }
.ym-details-notes { display: grid; gap: 10px; margin: 0; }.ym-details-notes > div { display: grid; gap: 5px; border-block-start: 1px solid var(--ym-drawer-soft-border); padding-block-start: 10px; }.ym-details-notes dd { font-weight: 500; line-height: 1.65; }
.ym-details-access { border: 1px solid var(--ym-drawer-soft-border); border-radius: 15px; background: var(--ym-drawer-control); }
.ym-details-access summary { padding: 13px 15px; color: var(--ym-drawer-text); font-size: 14px; font-weight: 800; cursor: pointer; }.ym-details-access summary:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 40%, transparent); outline-offset: 2px; }
.ym-details-access dl { display: grid; gap: 8px; border-block-start: 1px solid var(--ym-drawer-soft-border); margin: 0; padding: 12px 15px; }.ym-details-access dl > div { display: flex; justify-content: space-between; gap: 12px; }.ym-details-access dt { color: var(--ym-drawer-muted); font-size: 13px; }.ym-details-access dd { margin: 0; font-size: 13px; font-weight: 750; }
.ym-details-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; }
.ym-details-button { min-height: 42px; border: 1px solid var(--ym-drawer-border); border-radius: 12px; padding: 0 14px; color: var(--ym-drawer-text); background: var(--ym-drawer-control); font-size: 13.5px; font-weight: 800; cursor: pointer; transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease; }
.ym-details-button.is-primary { border-color: transparent; color: #fff; background: linear-gradient(135deg, var(--ym-drawer-violet), var(--ym-drawer-magenta)); box-shadow: 0 9px 20px color-mix(in srgb, var(--ym-drawer-violet) 22%, transparent); }.ym-details-button.is-secondary { border-color: color-mix(in srgb, var(--ym-drawer-electric) 36%, var(--ym-drawer-border)); }.ym-details-button.is-quiet { color: var(--ym-drawer-muted); }
.ym-details-button:hover { transform: translateY(-1px); border-color: var(--ym-drawer-electric); }.ym-details-button:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 42%, transparent); outline-offset: 2px; }
.ym-details-state { display: grid; min-height: 440px; place-items: center; align-content: center; gap: 10px; text-align: center; }.ym-details-state h3 { margin: 0; font-size: 19px; }.ym-details-state p { max-width: 430px; margin: 0; color: var(--ym-drawer-muted); font-size: 14px; }.ym-details-state__icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 50%; color: var(--ym-drawer-rose); background: color-mix(in srgb, var(--ym-drawer-rose) 12%, transparent); font-size: 22px; font-weight: 900; }
.ym-details-spinner { width: 36px; height: 36px; border: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 18%, transparent); border-top-color: var(--ym-drawer-electric); border-radius: 50%; }
.ym-details-skeleton { display: grid; width: min(100%, 440px); gap: 9px; margin-top: 12px; }.ym-details-skeleton i { height: 54px; border-radius: 14px; background: linear-gradient(90deg, var(--ym-drawer-control), color-mix(in srgb, var(--ym-drawer-electric) 8%, var(--ym-drawer-control)), var(--ym-drawer-control)); }.ym-details-skeleton i:nth-child(2) { height: 90px; }
@media (max-width: 640px) {
  .ym-details-identity, .ym-details-section { border-radius: 16px; padding: 16px; }
  .ym-details-metrics { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }.ym-details-metrics article { min-height: 68px; padding: 9px; }.ym-details-metrics strong { font-size: 20px; }
  .ym-details-facts { grid-template-columns: 1fr; }.ym-details-section > header { flex-direction: column; }.ym-details-actions { display: grid; grid-template-columns: 1fr; }.ym-details-button { min-height: 44px; }
}
@media (prefers-reduced-motion: reduce) { .ym-details-button { transition: none; }.ym-details-button:hover { transform: none; } }
</style>
