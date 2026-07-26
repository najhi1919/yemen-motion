<template>
  <Teleport to="body">
    <div
      class="ym-review-workspace-backdrop"
      :class="[dashboardTheme === 'light' ? 'is-light' : 'is-dark', `is-${section}`]"
      :dir="locale === 'ar' ? 'rtl' : 'ltr'"
      @mousedown.self="$emit('close')"
      @keydown="trapFocus"
    >
      <section
        ref="panel"
        class="ym-review-workspace"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
      >
        <header class="ym-review-workspace__head">
          <div class="ym-review-workspace__heading">
            <span class="ym-review-workspace__readonly">{{ text.readonly }}</span>
            <h2 :id="titleId" dir="auto">{{ detail?.work.title || title }}</h2>
            <div v-if="detail" class="ym-review-workspace__head-meta">
              <span>{{ mediaTypeLabel(detail.work.media_type) }}</span>
              <span>{{ statusLabel(detail.work.status) }}</span>
              <span>{{ detail.relations.reviewer ? text.assigned : text.unassigned }}</span>
              <span class="ym-admin-latin">#{{ formatNumber(detail.work.id) }}</span>
            </div>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="ym-review-workspace__close"
            :aria-label="text.close"
            @click="$emit('close')"
          >
            ×
          </button>
        </header>

        <div v-if="loading" class="ym-review-workspace__state" role="status" aria-live="polite">
          <span class="ym-review-workspace__skeleton is-preview" />
          <span class="ym-review-workspace__skeleton" />
          <span class="ym-review-workspace__skeleton is-short" />
          <strong>{{ text.loading }}</strong>
        </div>

        <div v-else-if="error" class="ym-review-workspace__state is-error" role="alert">
          <span aria-hidden="true">!</span>
          <strong>{{ text.loadFailed }}</strong>
          <p>{{ error }}</p>
          <button type="button" class="ym-admin-button" @click="$emit('retry')">{{ text.retry }}</button>
        </div>

        <div v-else-if="detail" class="ym-review-workspace__scroll">
          <div class="ym-review-workspace__grid">
            <aside class="ym-review-preview">
              <header>
                <div><span>{{ text.previewEyebrow }}</span><h3>{{ text.preview }}</h3></div>
                <small v-if="selectedMedia" class="ym-admin-latin">
                  {{ formatNumber(selectedIndex + 1) }} / {{ formatNumber(media.length) }}
                </small>
              </header>

              <div class="ym-review-preview__stage">
                <div v-if="mediaLoading" class="ym-review-preview__loading" role="status">
                  <span class="ym-review-workspace__skeleton is-preview" />
                  <strong>{{ text.mediaLoading }}</strong>
                </div>
                <div v-else-if="mediaError" class="ym-review-preview__message is-error" role="alert">
                  <span aria-hidden="true">!</span>
                  <strong>{{ mediaError }}</strong>
                  <button type="button" class="ym-admin-button" @click="loadMedia">{{ text.retry }}</button>
                </div>
                <div v-else-if="!selectedMedia" class="ym-review-preview__message">
                  <span aria-hidden="true">◇</span>
                  <strong>{{ text.noMedia }}</strong>
                  <p>{{ text.noMediaCopy }}</p>
                </div>
                <div
                  v-else-if="selectedMedia.processing_status === 'pending'"
                  class="ym-review-preview__processing"
                  role="status"
                  aria-live="polite"
                >
                  <span aria-hidden="true">◌</span>
                  <strong>{{ stageLabel(selectedMedia.processing_stage) }}</strong>
                  <p>{{ selectedMedia.processing_message || text.processing }}</p>
                  <div
                    role="progressbar"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    :aria-valuenow="selectedMedia.processing_progress"
                  >
                    <i :style="{ inlineSize: `${selectedMedia.processing_progress}%` }" />
                  </div>
                  <b>{{ formatNumber(selectedMedia.processing_progress) }}%</b>
                </div>
                <div v-else-if="selectedMedia.processing_status === 'failed'" class="ym-review-preview__message is-error">
                  <span aria-hidden="true">!</span>
                  <strong>{{ text.processingFailed }}</strong>
                  <p>{{ selectedMedia.processing_message || text.processingFailedCopy }}</p>
                </div>
                <div v-else-if="previewErrors[selectedMedia.id]" class="ym-review-preview__message is-error">
                  <span aria-hidden="true">!</span>
                  <strong>{{ text.previewFailed }}</strong>
                  <button type="button" class="ym-admin-button" @click="retryPreview(selectedMedia)">{{ text.retryPreview }}</button>
                </div>
                <img
                  v-else-if="selectedMedia.kind === 'image' && previewUrls[selectedMedia.id]"
                  :src="previewUrls[selectedMedia.id]"
                  :alt="selectedMedia.original_name"
                  :style="{ transform: `scale(${zoom})` }"
                />
                <video
                  v-else-if="selectedMedia.kind === 'video' && previewUrls[selectedMedia.id]"
                  :src="previewUrls[selectedMedia.id]"
                  :poster="posterUrls[selectedMedia.id]"
                  controls
                  preload="metadata"
                />
                <div v-else class="ym-review-preview__loading" role="status">
                  <span class="ym-review-workspace__skeleton is-preview" />
                  <strong>{{ text.previewLoading }}</strong>
                </div>
              </div>

              <div v-if="selectedMedia" class="ym-review-preview__toolbar">
                <div v-if="selectedMedia.kind === 'image' && selectedMedia.processing_status === 'ready'">
                  <button type="button" :aria-label="text.zoomOut" :disabled="zoom <= 1" @click="zoom = Math.max(1, zoom - .25)">−</button>
                  <span class="ym-admin-latin">{{ formatNumber(Math.round(zoom * 100)) }}%</span>
                  <button type="button" :aria-label="text.zoomIn" :disabled="zoom >= 2" @click="zoom = Math.min(2, zoom + .25)">+</button>
                </div>
                <dl>
                  <div v-if="dimensions(selectedMedia)"><dt>{{ text.dimensions }}</dt><dd class="ym-admin-latin">{{ dimensions(selectedMedia) }}</dd></div>
                  <div v-if="selectedMedia.duration_ms !== null"><dt>{{ text.duration }}</dt><dd>{{ duration(selectedMedia.duration_ms) }}</dd></div>
                </dl>
              </div>

              <div v-if="media.length > 1" class="ym-review-preview__navigation">
                <button type="button" :aria-label="text.previous" @click="selectRelative(-1)">‹</button>
                <div class="ym-review-preview__thumbs">
                  <button
                    v-for="item in media"
                    :key="item.id"
                    type="button"
                    :class="{ 'is-selected': item.id === selectedMediaId }"
                    :aria-label="item.original_name"
                    :aria-current="item.id === selectedMediaId ? 'true' : undefined"
                    @click="selectMedia(item.id)"
                  >
                    <img v-if="thumbnailUrl(item)" :src="thumbnailUrl(item)" alt="" />
                    <span v-else aria-hidden="true">{{ item.kind === 'video' ? '▶' : '▧' }}</span>
                  </button>
                </div>
                <button type="button" :aria-label="text.next" @click="selectRelative(1)">›</button>
              </div>

              <p v-if="selectedMedia" class="ym-review-preview__filename" dir="auto" :title="selectedMedia.original_name">
                {{ selectedMedia.original_name }}
              </p>
            </aside>

            <main class="ym-review-inspector">
              <section class="ym-review-inspector__section">
                <header><span>{{ text.summaryEyebrow }}</span><h3>{{ text.summary }}</h3></header>
                <p v-if="contextNote" class="ym-review-inspector__context-note">
                  <strong>{{ text.visibilityNotice }}</strong>
                  {{ contextNote }}
                </p>
                <h4 dir="auto">{{ detail.work.title }}</h4>
                <p v-if="detail.work.summary" dir="auto">{{ detail.work.summary }}</p>
                <p v-else class="is-muted">{{ text.noSummary }}</p>
                <dl class="ym-review-inspector__summary">
                  <div><dt>{{ text.status }}</dt><dd>{{ statusLabel(detail.work.status) }}</dd></div>
                  <div v-if="detail.work.submitted_at"><dt>{{ text.submittedAt }}</dt><dd>{{ formatDate(detail.work.submitted_at) }}</dd></div>
                  <div v-if="detail.field_access.can_view_designer"><dt>{{ text.designer }}</dt><dd dir="auto">{{ detail.relations.designer?.name || text.unassigned }}</dd></div>
                  <div><dt>{{ text.reviewer }}</dt><dd dir="auto">{{ detail.relations.reviewer?.name || text.unassigned }}</dd></div>
                  <div v-if="detail.work.category_id !== null"><dt>{{ text.category }}</dt><dd class="ym-admin-latin">#{{ formatNumber(detail.work.category_id) }}</dd></div>
                </dl>
              </section>

              <section class="ym-review-inspector__section">
                <header><span>{{ text.quickEyebrow }}</span><h3>{{ text.quickFacts }}</h3></header>
                <dl class="ym-review-inspector__facts">
                  <div><dt>{{ text.mediaType }}</dt><dd>{{ mediaTypeLabel(detail.work.media_type) }}</dd></div>
                  <div v-if="detail.work.price_amount !== null"><dt>{{ text.price }}</dt><dd class="ym-admin-latin">{{ detail.work.price_amount }}</dd></div>
                  <div v-if="detail.work.delivery_days !== null"><dt>{{ text.delivery }}</dt><dd>{{ formatNumber(detail.work.delivery_days) }}</dd></div>
                  <div v-if="selectedMedia && dimensions(selectedMedia)"><dt>{{ text.dimensions }}</dt><dd class="ym-admin-latin">{{ dimensions(selectedMedia) }}</dd></div>
                  <div v-if="selectedMedia?.duration_ms !== null && selectedMedia?.duration_ms !== undefined"><dt>{{ text.duration }}</dt><dd>{{ duration(selectedMedia.duration_ms) }}</dd></div>
                  <div><dt>{{ text.featured }}</dt><dd>{{ yesNo(detail.work.is_featured) }}</dd></div>
                  <div><dt>{{ text.pinned }}</dt><dd>{{ yesNo(detail.work.is_pinned) }}</dd></div>
                </dl>
              </section>

              <section class="ym-review-inspector__section">
                <header><span>{{ text.notesEyebrow }}</span><h3>{{ text.notes }}</h3></header>
                <dl v-if="notes.length" class="ym-review-inspector__notes">
                  <div v-for="note in notes" :key="note.key"><dt>{{ note.label }}</dt><dd dir="auto">{{ note.value }}</dd></div>
                </dl>
                <p v-else class="is-muted">{{ text.noNotes }}</p>
              </section>

              <details class="ym-review-inspector__disclosure">
                <summary>{{ text.timeline }}</summary>
                <ol class="ym-review-inspector__timeline">
                  <li v-for="event in timeline" :key="event.key">
                    <span aria-hidden="true">✓</span>
                    <div><strong>{{ event.label }}</strong><time :datetime="event.value">{{ formatDate(event.value) }}</time></div>
                  </li>
                </ol>
              </details>

              <details class="ym-review-inspector__disclosure">
                <summary>{{ text.metrics }}</summary>
                <dl class="ym-review-inspector__facts">
                  <div><dt>{{ text.views }}</dt><dd>{{ formatNumber(detail.work.views_count) }}</dd></div>
                  <div><dt>{{ text.likes }}</dt><dd>{{ formatNumber(detail.work.likes_count) }}</dd></div>
                  <div><dt>{{ text.reports }}</dt><dd>{{ formatNumber(detail.work.reports_count) }}</dd></div>
                </dl>
              </details>

              <details class="ym-review-inspector__disclosure">
                <summary>{{ text.permissions }}</summary>
                <dl class="ym-review-inspector__permissions">
                  <div v-for="permission in permissions" :key="permission.label">
                    <dt>{{ permission.label }}</dt><dd>{{ permission.allowed ? text.allowed : text.unavailable }}</dd>
                  </div>
                </dl>
              </details>
            </main>
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { formatYmDateTime, formatYmNumber } from '~/utils/ymFormatting'

interface Person { id: number; name: string }
interface Detail {
  work: {
    id: number; title: string; slug: string; summary: string | null; status: string
    visibility_status: string; media_type: string | null; price_amount: string | null
    delivery_days: number | null; category_id: number | null; is_featured: boolean
    is_pinned: boolean; reports_count: number; views_count: number; likes_count: number
    submitted_at: string | null; reviewed_at: string | null; approved_at: string | null
    published_at: string | null; rejected_at: string | null; hidden_at: string | null
    archived_at: string | null; updated_at: string | null; created_at: string | null
  }
  relations: { designer: Person | null; reviewer: Person | null }
  media: { media_type: string | null; has_media: boolean } | null
  private_notes: { internal_notes: string | null; rejection_reason: string | null; change_request_notes: string | null } | null
  field_access: { can_view_designer: boolean; can_view_media: boolean; can_view_metadata: boolean; can_view_private_notes: boolean }
}
interface MediaItem {
  id: number; kind: 'image' | 'video'; original_name: string; width: number | null; height: number | null
  duration_ms: number | null; position: number; processing_status: 'pending' | 'ready' | 'failed'
  processing_stage: string; processing_progress: number; processing_message: string
  content_endpoint: string; poster_endpoint: string | null
}
interface ApiResponse<T> { success: boolean; data: T; message?: string }

const props = defineProps<{
  workId: number
  title: string
  detail: Detail | null
  loading: boolean
  error: string | null
  locale: 'ar' | 'en'
  contextNote?: string | null
  section?: 'review' | 'visibility'
}>()
const emit = defineEmits<{ close: []; retry: [] }>()

const { apiFetch, tokenCookie } = useApiClient()
const config = useRuntimeConfig()
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const baseUrl = (config.public.apiBaseUrl as string) || 'http://127.0.0.1:8000/api'
const apiOrigin = baseUrl.replace(/\/api\/?$/, '')
const panel = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const media = ref<MediaItem[]>([])
const mediaLoading = ref(false)
const mediaError = ref('')
const selectedMediaId = ref<number | null>(null)
const previewUrls = ref<Record<number, string>>({})
const posterUrls = ref<Record<number, string>>({})
const previewErrors = ref<Record<number, boolean>>({})
const zoom = ref(1)
const controllers = new Map<string, AbortController>()
const titleId = `ym-review-workspace-title-${props.workId}`
const section = computed(() => props.section ?? 'review')
const contextNote = computed(() => props.contextNote ?? null)
let mediaRevision = 0
let previousBodyOverflow = ''

const text = computed(() => props.locale === 'ar' ? {
  readonly: 'تفاصيل للقراءة فقط', close: 'إغلاق تفاصيل العمل', assigned: 'مسند', unassigned: 'غير مسند',
  loading: 'جارٍ تحميل تفاصيل العمل…', loadFailed: 'تعذر تحميل التفاصيل', retry: 'إعادة المحاولة',
  previewEyebrow: 'المحتوى المحمي', preview: 'معاينة العمل', mediaLoading: 'جارٍ تحميل الوسائط المحمية…',
  noMedia: 'لا توجد وسائط للمعاينة', noMediaCopy: 'لم يُسجل وسيط فعال لهذا العمل.', processing: 'المعالجة مستمرة في الخلفية.',
  processingFailed: 'تعذرت معالجة الوسيط', processingFailedCopy: 'أزل الملف أوارفع نسخة أخرى من مساحة التأليف.',
  previewFailed: 'تعذر تحميل المعاينة المحمية', retryPreview: 'إعادة تحميل المعاينة', previewLoading: 'جارٍ تجهيز المعاينة…',
  zoomIn: 'تكبير الصورة', zoomOut: 'تصغير الصورة', dimensions: 'الأبعاد', duration: 'المدة', previous: 'الوسيط السابق', next: 'الوسيط التالي',
  summaryEyebrow: 'الأولوية', summary: 'ملخص المراجعة', noSummary: 'لا يوجد ملخص مسجل.', status: 'الحالة',
  visibilityNotice: 'تنبيه الظهور:',
  submittedAt: 'أرسل للمراجعة', designer: 'المصمم', reviewer: 'المراجع', category: 'التصنيف',
  quickEyebrow: 'نظرة سريعة', quickFacts: 'حقائق سريعة', mediaType: 'نوع الوسائط', price: 'السعر',
  delivery: 'مدة التسليم', featured: 'مميز', pinned: 'مثبت', notesEyebrow: 'السياق', notes: 'ملاحظات المراجعة',
  internalNotes: 'ملاحظات خاصة', rejectionReason: 'سبب الرفض', changeRequestNotes: 'طلبات التعديل',
  noNotes: 'لا توجد ملاحظات مراجعة مسجلة.', timeline: 'التسلسل الزمني الكامل', metrics: 'المؤشرات العامة',
  permissions: 'صلاحيات عرض التفاصيل', views: 'المشاهدات', likes: 'الإعجابات', reports: 'البلاغات',
  canViewDesigner: 'بيانات المصمم', canViewMedia: 'الوسائط', canViewMetadata: 'البيانات الوصفية',
  canViewPrivateNotes: 'الملاحظات الخاصة', allowed: 'متاح', unavailable: 'غير متاح',
  created: 'الإنشاء', submitted: 'الإرسال للمراجعة', reviewStarted: 'بدء المراجعة', approved: 'الاعتماد',
  rejected: 'الرفض', published: 'النشر', hidden: 'الإخفاء', archived: 'الأرشفة', updated: 'آخر تحديث',
  yes: 'نعم', no: 'لا', image: 'صورة', video: 'فيديو', gallery: 'معرض صور', unknown: 'غير محدد'
} : {
  readonly: 'Read-only details', close: 'Close work details', assigned: 'Assigned', unassigned: 'Unassigned',
  loading: 'Loading work details…', loadFailed: 'Could not load details', retry: 'Retry',
  previewEyebrow: 'Protected content', preview: 'Work preview', mediaLoading: 'Loading protected media…',
  noMedia: 'No media to preview', noMediaCopy: 'No active media is recorded for this work.', processing: 'Processing continues in the background.',
  processingFailed: 'Media processing failed', processingFailedCopy: 'Remove the file or upload another copy from authoring.',
  previewFailed: 'Protected preview could not be loaded', retryPreview: 'Reload preview', previewLoading: 'Preparing preview…',
  zoomIn: 'Zoom image in', zoomOut: 'Zoom image out', dimensions: 'Dimensions', duration: 'Duration', previous: 'Previous media', next: 'Next media',
  summaryEyebrow: 'Priority', summary: 'Review summary', noSummary: 'No summary is recorded.', status: 'Status',
  visibilityNotice: 'Visibility notice:',
  submittedAt: 'Submitted for review', designer: 'Designer', reviewer: 'Reviewer', category: 'Category',
  quickEyebrow: 'At a glance', quickFacts: 'Quick facts', mediaType: 'Media type', price: 'Price',
  delivery: 'Delivery time', featured: 'Featured', pinned: 'Pinned', notesEyebrow: 'Context', notes: 'Review notes',
  internalNotes: 'Private notes', rejectionReason: 'Rejection reason', changeRequestNotes: 'Change requests',
  noNotes: 'No review notes are recorded.', timeline: 'Full timeline', metrics: 'Public indicators',
  permissions: 'Detail viewing permissions', views: 'Views', likes: 'Likes', reports: 'Reports',
  canViewDesigner: 'Designer data', canViewMedia: 'Media', canViewMetadata: 'Metadata',
  canViewPrivateNotes: 'Private notes', allowed: 'Available', unavailable: 'Unavailable',
  created: 'Created', submitted: 'Submitted for review', reviewStarted: 'Review started', approved: 'Approved',
  rejected: 'Rejected', published: 'Published', hidden: 'Hidden', archived: 'Archived', updated: 'Last updated',
  yes: 'Yes', no: 'No', image: 'Image', video: 'Video', gallery: 'Gallery', unknown: 'Not specified'
})

const selectedMedia = computed(() => media.value.find(item => item.id === selectedMediaId.value) ?? null)
const selectedIndex = computed(() => media.value.findIndex(item => item.id === selectedMediaId.value))
const notes = computed(() => {
  if (!props.detail?.private_notes) return []
  return [
    { key: 'internal', label: text.value.internalNotes, value: props.detail.private_notes.internal_notes },
    { key: 'changes', label: text.value.changeRequestNotes, value: props.detail.private_notes.change_request_notes },
    { key: 'rejection', label: text.value.rejectionReason, value: props.detail.private_notes.rejection_reason },
  ].filter((item): item is { key: string; label: string; value: string } => Boolean(item.value?.trim()))
})
const timeline = computed(() => {
  const work = props.detail?.work
  if (!work) return []
  return [
    { key: 'created', label: text.value.created, value: work.created_at },
    { key: 'submitted', label: text.value.submitted, value: work.submitted_at },
    { key: 'reviewed', label: text.value.reviewStarted, value: work.reviewed_at },
    { key: 'approved', label: text.value.approved, value: work.approved_at },
    { key: 'rejected', label: text.value.rejected, value: work.rejected_at },
    { key: 'published', label: text.value.published, value: work.published_at },
    { key: 'hidden', label: text.value.hidden, value: work.hidden_at },
    { key: 'archived', label: text.value.archived, value: work.archived_at },
    { key: 'updated', label: text.value.updated, value: work.updated_at },
  ].filter((item): item is { key: string; label: string; value: string } => Boolean(item.value))
})
const permissions = computed(() => props.detail ? [
  { label: text.value.canViewDesigner, allowed: props.detail.field_access.can_view_designer },
  { label: text.value.canViewMedia, allowed: props.detail.field_access.can_view_media },
  { label: text.value.canViewMetadata, allowed: props.detail.field_access.can_view_metadata },
  { label: text.value.canViewPrivateNotes, allowed: props.detail.field_access.can_view_private_notes },
] : [])

const formatNumber = (value: number): string => formatYmNumber(value, props.locale)
const formatDate = (value: string | null): string => formatYmDateTime(value, props.locale)
const yesNo = (value: boolean): string => value ? text.value.yes : text.value.no
const mediaTypeLabel = (value: string | null): string => ({
  image: text.value.image, video: text.value.video, gallery: text.value.gallery,
}[value || ''] || text.value.unknown)
const statusLabel = (value: string): string => ({
  draft: props.locale === 'ar' ? 'مسودة' : 'Draft',
  submitted: props.locale === 'ar' ? 'قيد المراجعة' : 'Submitted',
  in_review: props.locale === 'ar' ? 'تحت المراجعة' : 'In review',
  changes_requested: props.locale === 'ar' ? 'تعديلات مطلوبة' : 'Changes requested',
  approved: props.locale === 'ar' ? 'معتمد' : 'Approved',
  published: props.locale === 'ar' ? 'منشور' : 'Published',
  rejected: props.locale === 'ar' ? 'مرفوض' : 'Rejected',
  hidden: props.locale === 'ar' ? 'مخفي' : 'Hidden',
  archived: props.locale === 'ar' ? 'مؤرشف' : 'Archived',
}[value] || value)
const dimensions = (item: MediaItem): string => item.width && item.height
  ? `${formatNumber(item.width)} × ${formatNumber(item.height)}`
  : ''
const duration = (milliseconds: number): string => {
  const seconds = milliseconds / 1000
  return props.locale === 'ar'
    ? `${formatYmNumber(seconds, props.locale, { maximumFractionDigits: 1 })} ثانية`
    : `${formatYmNumber(seconds, props.locale, { maximumFractionDigits: 1 })} sec`
}
const stageLabel = (stage: string): string => ({
  queued: props.locale === 'ar' ? 'في طابور المعالجة' : 'Queued',
  validating: props.locale === 'ar' ? 'التحقق من الملف' : 'Validating',
  probing: props.locale === 'ar' ? 'قراءة بيانات الفيديو' : 'Reading video metadata',
  extracting_metadata: props.locale === 'ar' ? 'حفظ بيانات الفيديو' : 'Saving video metadata',
  generating_poster: props.locale === 'ar' ? 'إنشاء صورة المعاينة' : 'Generating preview',
  finalizing: props.locale === 'ar' ? 'إنهاء المعالجة' : 'Finalizing',
}[stage] || text.value.processing)
const thumbnailUrl = (item: MediaItem): string => (
  item.kind === 'video' ? posterUrls.value[item.id] : previewUrls.value[item.id]
) || ''

function authenticatedHeaders(): Record<string, string> {
  return tokenCookie.value
    ? { Accept: 'application/octet-stream', Authorization: `Bearer ${tokenCookie.value}` }
    : { Accept: 'application/octet-stream' }
}
function revokeUrls(): void {
  controllers.forEach(controller => controller.abort())
  controllers.clear()
  for (const url of [...Object.values(previewUrls.value), ...Object.values(posterUrls.value)]) {
    URL.revokeObjectURL(url)
  }
  previewUrls.value = {}
  posterUrls.value = {}
  previewErrors.value = {}
}
async function fetchBlob(endpoint: string, key: string): Promise<string> {
  const controller = new AbortController()
  controllers.set(key, controller)
  try {
    const blob = await $fetch<Blob>(`${apiOrigin}${endpoint}`, {
      responseType: 'blob',
      headers: authenticatedHeaders(),
      signal: controller.signal,
    })
    return URL.createObjectURL(blob)
  } finally {
    controllers.delete(key)
  }
}
async function loadAsset(item: MediaItem): Promise<void> {
  if (item.processing_status !== 'ready') return
  if (item.kind === 'image' && !previewUrls.value[item.id]) {
    try {
      const url = await fetchBlob(item.content_endpoint, `content-${item.id}`)
      previewUrls.value = { ...previewUrls.value, [item.id]: url }
    } catch {
      previewErrors.value = { ...previewErrors.value, [item.id]: true }
    }
  }
  if (item.kind === 'video' && item.poster_endpoint && !posterUrls.value[item.id]) {
    try {
      const url = await fetchBlob(item.poster_endpoint, `poster-${item.id}`)
      posterUrls.value = { ...posterUrls.value, [item.id]: url }
    } catch {
      // The video remains available even when its optional poster cannot be loaded.
    }
  }
  if (item.kind === 'video' && item.id === selectedMediaId.value && !previewUrls.value[item.id]) {
    try {
      const url = await fetchBlob(item.content_endpoint, `content-${item.id}`)
      previewUrls.value = { ...previewUrls.value, [item.id]: url }
    } catch {
      previewErrors.value = { ...previewErrors.value, [item.id]: true }
    }
  }
}
async function loadMedia(): Promise<void> {
  if (!props.detail?.field_access.can_view_media) {
    media.value = []
    return
  }
  const revision = ++mediaRevision
  mediaLoading.value = true
  mediaError.value = ''
  revokeUrls()
  try {
    const response = await apiFetch<ApiResponse<{ media: MediaItem[] }>>(`/admin/works/${props.workId}/media`)
    if (revision !== mediaRevision) return
    media.value = [...response.data.media].sort((a, b) => a.position - b.position)
    selectedMediaId.value = media.value[0]?.id ?? null
    await Promise.all(media.value.map(loadAsset))
  } catch {
    if (revision === mediaRevision) {
      media.value = []
      mediaError.value = props.locale === 'ar'
        ? 'تعذر تحميل الوسائط المحمية. تحقق من الصلاحية أوالاتصال ثم أعد المحاولة.'
        : 'Protected media could not be loaded. Check access or connectivity and retry.'
    }
  } finally {
    if (revision === mediaRevision) mediaLoading.value = false
  }
}
function selectMedia(id: number): void {
  selectedMediaId.value = id
  zoom.value = 1
  const item = media.value.find(entry => entry.id === id)
  if (item) void loadAsset(item)
}
function selectRelative(delta: number): void {
  if (!media.value.length) return
  const next = (Math.max(0, selectedIndex.value) + delta + media.value.length) % media.value.length
  selectMedia(media.value[next]!.id)
}
function retryPreview(item: MediaItem): void {
  const previous = previewUrls.value[item.id]
  if (previous) URL.revokeObjectURL(previous)
  const urls = { ...previewUrls.value }
  delete urls[item.id]
  previewUrls.value = urls
  const errors = { ...previewErrors.value }
  delete errors[item.id]
  previewErrors.value = errors
  void loadAsset(item)
}
function trapFocus(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    emit('close')
    return
  }
  if (event.key !== 'Tab' || !panel.value) return
  const focusable = [...panel.value.querySelectorAll<HTMLElement>('button:not(:disabled),select:not(:disabled),a[href],video,[tabindex]:not([tabindex="-1"])')]
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

watch(() => props.detail, value => {
  if (value) void loadMedia()
}, { immediate: true })

onMounted(() => {
  previousBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  nextTick(() => closeButton.value?.focus())
})
onBeforeUnmount(() => {
  mediaRevision += 1
  revokeUrls()
  document.body.style.overflow = previousBodyOverflow
})
</script>

<style scoped>
.ym-review-workspace-backdrop{--ym-text:#f0f6ff;--ym-muted:#cbd5e1;--ym-card-bg:#111827;--ym-dropdown-bg:#0f172a;--ym-control-bg:#172033;--ym-control-border:rgba(148,163,184,.34);--ym-card-border:rgba(148,163,184,.3);--ym-soft-border:rgba(148,163,184,.22);position:fixed;inset:0;z-index:var(--ym-admin-layer-detail,1500);display:flex;justify-content:flex-start;overflow:hidden;background:rgba(2,6,23,.7);backdrop-filter:blur(7px)}.ym-review-workspace-backdrop.is-light{--ym-text:#182033;--ym-muted:#59647a;--ym-card-bg:#fff;--ym-dropdown-bg:#fff;--ym-control-bg:#f3f0fa;--ym-control-border:rgba(91,78,132,.26);--ym-card-border:rgba(91,78,132,.22);--ym-soft-border:rgba(91,78,132,.17);background:rgba(30,24,54,.32)}.ym-review-workspace{display:grid;width:clamp(760px,58vw,980px);max-width:100vw;height:100dvh;grid-template-rows:auto minmax(0,1fr);overflow:hidden;border-inline-end:1px solid color-mix(in srgb,var(--ym-admin-section-accent,#6366f1) 24%,var(--ym-card-border));color:var(--ym-text);background:color-mix(in srgb,var(--ym-dropdown-bg) 97%,#6366f1 3%);box-shadow:24px 0 70px rgba(2,6,23,.45)}.ym-review-workspace__head{position:relative;z-index:3;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;border-block-end:1px solid var(--ym-soft-border);padding:14px 18px;background:color-mix(in srgb,var(--ym-dropdown-bg) 94%,transparent);backdrop-filter:blur(16px)}.ym-review-workspace__heading{display:grid;min-width:0;gap:3px}.ym-review-workspace__readonly{width:max-content;border:1px solid color-mix(in srgb,#6366f1 26%,var(--ym-soft-border));border-radius:999px;padding:3px 8px;color:#818cf8;background:color-mix(in srgb,#6366f1 8%,transparent);font-size:11px;font-weight:900}.ym-review-workspace__head h2{max-width:720px;margin:0;overflow:hidden;color:var(--ym-text);font-size:20px;font-weight:950;line-height:1.4;text-overflow:ellipsis;white-space:nowrap}.ym-review-workspace__head-meta{display:flex;flex-wrap:wrap;gap:5px}.ym-review-workspace__head-meta span{border-radius:999px;padding:3px 7px;color:var(--ym-muted);background:var(--ym-control-bg);font-size:11.5px;font-weight:800}.ym-review-workspace__close{display:grid;width:44px;height:44px;flex:0 0 44px;place-items:center;border:1px solid var(--ym-control-border);border-radius:13px;color:var(--ym-text);background:var(--ym-control-bg);font-size:24px}.ym-review-workspace__close:focus-visible,.ym-review-workspace button:focus-visible,.ym-review-workspace summary:focus-visible{outline:3px solid color-mix(in srgb,#6366f1 38%,transparent);outline-offset:2px}.ym-review-workspace__scroll{min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:16px}.ym-review-workspace__grid{display:grid;grid-template-columns:minmax(0,1.32fr) minmax(300px,1fr);align-items:start;gap:16px}.ym-review-preview{position:sticky;top:0;display:grid;min-width:0;gap:12px;border:1px solid var(--ym-soft-border);border-radius:18px;padding:14px;background:color-mix(in srgb,var(--ym-card-bg) 96%,#6366f1 4%);box-shadow:inset 0 1px rgba(255,255,255,.07)}.ym-review-preview>header{display:flex;align-items:center;justify-content:space-between;gap:10px}.ym-review-preview>header span,.ym-review-inspector__section>header span{color:#818cf8;font-size:11px;font-weight:900}.ym-review-preview h3,.ym-review-inspector h3{margin:2px 0 0;color:var(--ym-text);font-size:16px}.ym-review-preview__stage{position:relative;display:grid;min-height:300px;max-height:520px;overflow:hidden;place-items:center;border:1px solid color-mix(in srgb,#6366f1 18%,var(--ym-control-border));border-radius:15px;background:#050914}.ym-review-preview__stage>img,.ym-review-preview__stage>video{width:100%;height:100%;max-height:520px;object-fit:contain;transition:transform .18s ease}.ym-review-preview__loading,.ym-review-preview__message,.ym-review-preview__processing{display:grid;width:100%;min-height:280px;place-items:center;align-content:center;gap:9px;padding:20px;color:#cbd5e1;text-align:center}.ym-review-preview__message>span,.ym-review-preview__processing>span{font-size:34px;color:#818cf8}.ym-review-preview__message p,.ym-review-preview__processing p{max-width:420px;margin:0;color:#94a3b8;font-size:13px;line-height:1.6}.ym-review-preview__message.is-error>span,.ym-review-preview__message.is-error>strong{color:#fb7185}.ym-review-preview__processing>div{width:min(320px,88%);height:8px;overflow:hidden;border-radius:999px;background:rgba(148,163,184,.18)}.ym-review-preview__processing i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#6366f1,#8b5cf6)}.ym-review-preview__processing b{color:#c4b5fd;font-variant-numeric:tabular-nums}.ym-review-workspace__skeleton{display:block;width:min(520px,90%);height:18px;border-radius:8px;background:linear-gradient(100deg,rgba(148,163,184,.1),rgba(148,163,184,.22),rgba(148,163,184,.1));background-size:200% 100%;animation:ym-review-skeleton 1.2s ease infinite}.ym-review-workspace__skeleton.is-preview{width:100%;height:260px}.ym-review-workspace__skeleton.is-short{width:44%}.ym-review-workspace__state{display:grid;min-height:0;overflow-y:auto;place-items:center;align-content:center;gap:12px;padding:24px;text-align:center}.ym-review-workspace__state.is-error>span{font-size:36px;color:#fb7185}.ym-review-workspace__state p{max-width:520px;margin:0;color:var(--ym-muted)}.ym-review-preview__toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px}.ym-review-preview__toolbar>div{display:flex;align-items:center;gap:7px}.ym-review-preview__toolbar button,.ym-review-preview__navigation>button{display:grid;width:38px;height:38px;place-items:center;border:1px solid var(--ym-control-border);border-radius:10px;color:var(--ym-text);background:var(--ym-control-bg);font-size:18px}.ym-review-preview__toolbar dl{display:flex;gap:12px;margin:0}.ym-review-preview__toolbar dl div{display:grid}.ym-review-preview__toolbar dt{color:var(--ym-muted);font-size:10px}.ym-review-preview__toolbar dd{margin:0;color:var(--ym-text);font-size:12px;font-weight:850}.ym-review-preview__navigation{display:grid;grid-template-columns:38px minmax(0,1fr) 38px;align-items:center;gap:8px}.ym-review-preview__thumbs{display:grid;grid-template-columns:repeat(auto-fit,minmax(54px,1fr));gap:7px}.ym-review-preview__thumbs button{display:grid;min-width:0;aspect-ratio:16/10;overflow:hidden;place-items:center;border:1px solid var(--ym-control-border);border-radius:9px;color:var(--ym-muted);background:#080d19}.ym-review-preview__thumbs button.is-selected{border-color:#818cf8;box-shadow:0 0 0 2px color-mix(in srgb,#6366f1 24%,transparent)}.ym-review-preview__thumbs img{width:100%;height:100%;object-fit:cover}.ym-review-preview__filename{margin:0;overflow:hidden;color:var(--ym-muted);font-size:12px;text-align:start;text-overflow:ellipsis;unicode-bidi:plaintext;white-space:nowrap}.ym-review-inspector{display:grid;min-width:0;gap:12px}.ym-review-inspector__section,.ym-review-inspector__disclosure{border:1px solid var(--ym-soft-border);border-radius:15px;padding:13px;background:color-mix(in srgb,var(--ym-card-bg) 97%,#6366f1 3%)}.ym-review-inspector__section>header{margin-block-end:10px}.ym-review-inspector__section h4{margin:0 0 7px;color:var(--ym-text);font-size:16px;line-height:1.5}.ym-review-inspector__section>p{margin:0;color:var(--ym-text);font-size:13.5px;line-height:1.75}.ym-review-inspector .is-muted{color:var(--ym-muted)}.ym-review-inspector__summary,.ym-review-inspector__facts,.ym-review-inspector__notes,.ym-review-inspector__permissions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:11px 0 0}.ym-review-inspector dl>div{min-width:0}.ym-review-inspector dt{color:var(--ym-muted);font-size:11px;font-weight:800}.ym-review-inspector dd{margin:3px 0 0;color:var(--ym-text);font-size:12.5px;font-weight:850;overflow-wrap:anywhere}.ym-review-inspector__facts>div{border-radius:10px;padding:8px;background:var(--ym-control-bg)}.ym-review-inspector__notes{grid-template-columns:1fr}.ym-review-inspector__notes>div{border-inline-start:3px solid #818cf8;padding-inline-start:9px}.ym-review-inspector__notes dd{line-height:1.65}.ym-review-inspector__disclosure{padding:0;overflow:hidden}.ym-review-inspector__disclosure summary{min-height:46px;padding:12px 13px;color:var(--ym-text);font-size:13px;font-weight:900;cursor:pointer}.ym-review-inspector__disclosure[open] summary{border-block-end:1px solid var(--ym-soft-border)}.ym-review-inspector__disclosure>dl,.ym-review-inspector__disclosure>ol{margin:0;padding:12px 13px}.ym-review-inspector__timeline{display:grid;gap:10px;list-style:none}.ym-review-inspector__timeline li{display:grid;grid-template-columns:24px minmax(0,1fr);gap:8px}.ym-review-inspector__timeline li>span{display:grid;width:22px;height:22px;place-items:center;border-radius:50%;color:#a5b4fc;background:color-mix(in srgb,#6366f1 12%,transparent);font-size:10px}.ym-review-inspector__timeline li div{display:grid}.ym-review-inspector__timeline strong{font-size:12.5px}.ym-review-inspector__timeline time{direction:ltr;unicode-bidi:isolate;color:var(--ym-muted);font-size:11.5px}.ym-review-inspector__permissions dd{color:#818cf8}.ym-review-workspace__state .ym-admin-button,.ym-review-preview__message .ym-admin-button{min-height:42px}.ym-review-workspace__head-meta,.ym-review-preview__toolbar,.ym-review-inspector time{font-variant-numeric:tabular-nums}@keyframes ym-review-skeleton{to{background-position:-200% 0}}@media(max-width:1100px){.ym-review-workspace{width:min(88vw,980px)}.ym-review-workspace__grid{grid-template-columns:1fr}.ym-review-preview{position:relative}.ym-review-preview__stage{min-height:340px}}@media(max-width:700px){.ym-review-workspace{width:100vw;max-width:none;border:0}.ym-review-workspace__head{padding:12px max(12px,env(safe-area-inset-right)) 12px max(12px,env(safe-area-inset-left))}.ym-review-workspace__head h2{font-size:18px}.ym-review-workspace__scroll{padding:12px;padding-block-end:max(18px,env(safe-area-inset-bottom))}.ym-review-preview,.ym-review-inspector__section{border-radius:14px}.ym-review-preview__stage{min-height:240px}.ym-review-preview__toolbar{align-items:flex-start;flex-direction:column}.ym-review-inspector__summary,.ym-review-inspector__facts,.ym-review-inspector__permissions{grid-template-columns:1fr}}@media(prefers-reduced-motion:reduce){.ym-review-workspace__skeleton{animation:none}.ym-review-preview__stage>img,.ym-review-preview__stage>video{transition:none}.ym-review-workspace-backdrop{backdrop-filter:none}}
.ym-review-inspector__context-note{border-inline-start:3px solid #f59e0b;border-radius:8px;padding:8px 10px!important;color:var(--ym-muted)!important;background:color-mix(in srgb,#f59e0b 10%,transparent)}
.ym-review-inspector__context-note strong{color:#d97706}
.ym-review-workspace-backdrop.is-visibility{--ym-admin-section-accent:#059669}
.ym-review-workspace-backdrop.is-visibility .ym-review-workspace__readonly,.ym-review-workspace-backdrop.is-visibility .ym-review-preview>header span,.ym-review-workspace-backdrop.is-visibility .ym-review-inspector__section>header span{color:#34d399}
.ym-review-workspace-backdrop.is-visibility .ym-review-workspace{background:color-mix(in srgb,var(--ym-dropdown-bg) 97%,#059669 3%)}
.ym-review-workspace-backdrop.is-visibility .ym-review-preview,.ym-review-workspace-backdrop.is-visibility .ym-review-inspector__section,.ym-review-workspace-backdrop.is-visibility .ym-review-inspector__disclosure{background:color-mix(in srgb,var(--ym-card-bg) 97%,#059669 3%)}
</style>
