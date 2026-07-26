<template>
  <div class="activity-page" :dir="direction">
    <section class="activity-hero">
      <div class="hero-copy">
        <nav class="breadcrumb" :aria-label="t.breadcrumb">
          <span>{{ t.admin }}</span><i>/</i><span>{{ t.works }}</span><i>/</i><strong>{{ t.pageTitle }}</strong>
        </nav>
        <span class="read-only-chip">◷ {{ t.readOnly }}</span>
        <h1>{{ t.pageTitle }}</h1>
        <p>{{ mode === 'audit' ? t.auditDescription : t.lifecycleDescription }}</p>
      </div>
      <div class="hero-total">
        <span>{{ t.totalEvents }}</span>
        <strong dir="ltr">{{ number(summaryTotal) }}</strong>
        <small>{{ t.currentFilters }}</small>
      </div>
    </section>

    <nav class="source-tabs" role="tablist" :aria-label="t.source" @keydown="onTabsKeydown">
      <button
        type="button"
        role="tab"
        :tabindex="mode === 'audit' ? 0 : -1"
        :aria-selected="mode === 'audit'"
        @click="switchSource('audit')"
      >
        <strong>{{ t.audit }}</strong><small>{{ t.auditTabHint }}</small>
      </button>
      <button
        type="button"
        role="tab"
        :tabindex="mode === 'lifecycle' ? 0 : -1"
        :aria-selected="mode === 'lifecycle'"
        @click="switchSource('lifecycle')"
      >
        <strong>{{ t.lifecycle }}</strong><small>{{ t.lifecycleTabHint }}</small>
      </button>
    </nav>

    <section v-if="authPending" class="surface state" role="status" aria-live="polite">
      <span class="spinner" /><h2>{{ t.authPending }}</h2>
    </section>
    <section v-else-if="forbidden" class="surface state error-state" role="alert">
      <b>!</b><h2>{{ t.forbidden }}</h2><p>{{ t.forbiddenHint }}</p>
    </section>

    <template v-else>
      <aside class="source-bar" role="note">
        <div>
          <strong>{{ mode === 'audit' ? t.auditSourceLabel : t.lifecycleSourceLabel }}</strong>
          <WorksIndexInfoPopover :label="t.sourceDetails" :hint="t.sourceDetails" :close-label="t.close">
            <template #trigger><span class="info-button" :title="t.sourceDetails" aria-hidden="true">i</span></template>
            <dl class="info-list">
              <div><dt>{{ t.sourceType }}</dt><dd>{{ mode === 'audit' ? t.auditSourceLabel : t.lifecycleSourceLabel }}</dd></div>
              <div><dt>{{ t.technicalSource }}</dt><dd dir="ltr">{{ sourceName }}</dd></div>
            </dl>
          </WorksIndexInfoPopover>
          <span class="source-notice">{{ mode === 'audit' ? t.auditNotice : t.lifecycleNotice }}</span>
        </div>
      </aside>

      <section v-if="summaryCards.length" class="summary-strip" :aria-label="t.summary">
        <article v-for="card in summaryCards" :key="card.key" :class="{ alert: card.alert }" :title="card.hint" tabindex="0" :aria-label="`${card.label}: ${number(card.value)}. ${card.hint}`">
          <span>{{ card.label }}</span><strong dir="ltr">{{ number(card.value) }}</strong><small>{{ card.hint }}</small>
        </article>
        <WorksIndexInfoPopover :label="t.summaryDetails" :hint="t.summaryDetails" :close-label="t.close">
          <template #trigger><span class="summary-details">{{ t.summaryDetails }}</span></template>
          <dl class="info-list summary-list">
            <div v-for="detail in summaryDetails" :key="detail.key"><dt>{{ detail.label }}</dt><dd dir="ltr">{{ number(detail.value) }}</dd></div>
          </dl>
        </WorksIndexInfoPopover>
      </section>

      <section class="surface filters">
        <header>
          <h2>{{ t.filters }}</h2>
          <span v-if="loading && currentItems.length" class="refreshing" role="status"><i class="spinner" />{{ t.refreshing }}</span>
        </header>

        <form v-if="mode === 'audit'" @submit.prevent="applyFilters">
          <div class="basic-filters audit-basic">
            <label class="search"><span>{{ t.search }}</span><input v-model.trim="auditFilters.q" type="search" minlength="2" maxlength="80" :placeholder="t.auditSearch" /></label>
            <label><span>{{ t.eventGroup }}</span><select v-model="auditFilters.event_group" @change="syncAuditEventType(); applyBasicFilters()"><option value="">{{ t.all }}</option><option v-for="group in eventCatalog.groups" :key="group.key" :value="group.key">{{ catalogLabel(group) }}</option></select></label>
            <label><span>{{ t.eventType }}</span><select v-model="auditFilters.event_type" @change="applyBasicFilters"><option value="">{{ t.all }}</option><option v-for="event in visibleCatalogEvents" :key="event.event_type" :value="event.event_type">{{ catalogLabel(event) }}</option></select></label>
            <label><span>{{ t.outcome }}</span><input v-model.trim="auditFilters.outcome" type="text" maxlength="50" @change="applyBasicFilters" /></label>
          </div>
          <div class="filter-actions">
            <details class="advanced">
              <summary>{{ t.advanced }} <b v-if="advancedFilterCount" dir="ltr">{{ number(advancedFilterCount) }}</b></summary>
              <div class="advanced-panel">
                <label><span>{{ t.actorId }}</span><input v-model="auditFilters.actor_id" type="number" min="1" inputmode="numeric" /></label>
                <label><span>{{ t.targetType }}</span><select v-model="auditFilters.target_type"><option value="">{{ t.all }}</option><option v-for="target in targetTypes" :key="target" :value="target">{{ targetLabel(target) }}</option></select></label>
                <label><span>{{ t.targetId }}</span><input v-model="auditFilters.target_id" type="number" min="1" inputmode="numeric" /></label>
                <label><span>{{ t.workId }}</span><input v-model="auditFilters.work_id" type="number" min="1" inputmode="numeric" /></label>
                <label><span>{{ t.from }}</span><input v-model="auditFilters.from" type="date" /></label>
                <label><span>{{ t.to }}</span><input v-model="auditFilters.to" type="date" /></label>
                <label><span>{{ t.sort }}</span><select v-model="auditFilters.sort"><option v-for="option in auditSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                <label><span>{{ t.direction }}</span><select v-model="auditFilters.direction"><option value="desc">{{ t.desc }}</option><option value="asc">{{ t.asc }}</option></select></label>
                <label><span>{{ t.perPage }}</span><select v-model.number="auditFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
                <button class="button primary" type="submit" :disabled="loading">{{ t.apply }}</button>
              </div>
            </details>
            <button class="button secondary" type="button" :disabled="loading" @click="resetFilters">{{ t.reset }}</button>
          </div>
        </form>

        <form v-else @submit.prevent="applyFilters">
          <div class="basic-filters lifecycle-basic">
            <label class="search"><span>{{ t.search }}</span><input v-model.trim="lifecycleFilters.q" type="search" minlength="2" maxlength="80" :placeholder="t.lifecycleSearch" /></label>
            <label><span>{{ t.eventType }}</span><select v-model="lifecycleFilters.event_type" @change="applyBasicFilters"><option value="">{{ t.all }}</option><option v-for="event in lifecycleEvents" :key="event" :value="event">{{ lifecycleEventLabel(event) }}</option></select></label>
            <label><span>{{ t.status }}</span><select v-model="lifecycleFilters.status" @change="applyBasicFilters"><option value="">{{ t.all }}</option><option v-for="status in workStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option></select></label>
            <label><span>{{ t.visibility }}</span><select v-model="lifecycleFilters.visibility_status" @change="applyBasicFilters"><option value="">{{ t.all }}</option><option value="public">{{ t.public }}</option><option value="hidden">{{ t.hidden }}</option></select></label>
          </div>
          <div class="filter-actions">
            <details class="advanced">
              <summary>{{ t.advanced }} <b v-if="advancedFilterCount" dir="ltr">{{ number(advancedFilterCount) }}</b></summary>
              <div class="advanced-panel">
                <label><span>{{ t.media }}</span><input v-model.trim="lifecycleFilters.media_type" type="text" maxlength="40" dir="ltr" /></label>
                <label><span>{{ t.designerId }}</span><input v-model="lifecycleFilters.designer_id" type="number" min="1" /></label>
                <label><span>{{ t.reviewerId }}</span><input v-model="lifecycleFilters.reviewer_id" type="number" min="1" /></label>
                <label><span>{{ t.categoryId }}</span><input v-model="lifecycleFilters.category_id" type="number" min="1" /></label>
                <label><span>{{ t.reported }}</span><select v-model="lifecycleFilters.reported"><option value="">{{ t.all }}</option><option value="1">{{ t.yes }}</option><option value="0">{{ t.no }}</option></select></label>
                <label><span>{{ t.promoted }}</span><select v-model="lifecycleFilters.promoted"><option value="">{{ t.all }}</option><option value="1">{{ t.yes }}</option><option value="0">{{ t.no }}</option></select></label>
                <label><span>{{ t.from }}</span><input v-model="lifecycleFilters.from" type="date" /></label>
                <label><span>{{ t.to }}</span><input v-model="lifecycleFilters.to" type="date" /></label>
                <label><span>{{ t.sort }}</span><select v-model="lifecycleFilters.sort"><option v-for="option in lifecycleSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
                <label><span>{{ t.direction }}</span><select v-model="lifecycleFilters.direction"><option value="desc">{{ t.desc }}</option><option value="asc">{{ t.asc }}</option></select></label>
                <label><span>{{ t.perPage }}</span><select v-model.number="lifecycleFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
                <button class="button primary" type="submit" :disabled="loading">{{ t.apply }}</button>
              </div>
            </details>
            <button class="button secondary" type="button" :disabled="loading" @click="resetFilters">{{ t.reset }}</button>
          </div>
        </form>

        <div v-if="activeFilterChips.length" class="active-filters" :aria-label="t.activeFilters">
          <button v-for="chip in activeFilterChips" :key="chip.key" type="button" @click="removeFilter(chip.key)">
            {{ chip.label }}: <span dir="ltr">{{ chip.value }}</span><b aria-hidden="true">×</b>
          </button>
        </div>
        <p v-if="filterError" class="validation" role="alert">{{ filterError }}</p>
      </section>

      <section class="surface table-card" aria-live="polite">
        <header>
          <div><h2>{{ mode === 'audit' ? t.auditEvents : t.lifecycleEvents }}</h2><p>{{ mode === 'audit' ? t.auditTableHint : t.lifecycleTableHint }}</p></div>
          <strong dir="ltr">{{ number(currentPagination.current_page) }} / {{ number(currentPagination.last_page) }}</strong>
        </header>
        <div v-if="loading && !currentItems.length" class="state" role="status"><span class="spinner" /><h3>{{ t.loading }}</h3></div>
        <div v-else-if="error && !currentItems.length" class="state error-state" role="alert"><b>!</b><h3>{{ t.loadFailed }}</h3><p>{{ error }}</p><button class="button secondary" type="button" @click="fetchActivity">{{ t.retry }}</button></div>
        <div v-else-if="currentLoaded && currentItems.length === 0" class="state" role="status"><b dir="ltr">0</b><h3>{{ t.empty }}</h3></div>

        <WorksActivityAuditTable
          v-else-if="mode === 'audit' && auditItems.length"
          :items="auditItems"
          :locale="currentLocale"
          :groups="eventCatalog.groups"
          :events="eventCatalog.events"
          :sort="appliedAuditFilters.sort"
          :direction="appliedAuditFilters.direction"
          :current-page="currentPagination.current_page"
          :per-page="currentPagination.per_page"
          @sort="changeAuditSort"
          @details="openAuditDrawer"
        />
        <WorksActivityLifecycleSmartList
          v-else-if="mode === 'lifecycle' && lifecycleItems.length"
          :items="lifecycleItems"
          :locale="currentLocale"
          :sort="appliedLifecycleFilters.sort"
          :direction="appliedLifecycleFilters.direction"
          :current-page="currentPagination.current_page"
          :per-page="currentPagination.per_page"
          @sort="changeLifecycleSort"
          @details="openLifecycleDrawer"
        />
        <p v-if="error && currentItems.length" class="refresh-error" role="alert">{{ error }} <button type="button" @click="fetchActivity">{{ t.retry }}</button></p>

        <footer v-if="currentLoaded && !error" class="pagination">
          <div><span>{{ t.totalEvents }}</span><strong dir="ltr">{{ number(currentPagination.total) }}</strong><small><b dir="ltr">{{ number(currentItems.length) }}</b> {{ t.visibleNow }}</small></div>
          <nav :aria-label="t.pagination">
            <button class="button secondary" :disabled="loading || currentPagination.current_page <= 1" @click="changePage(currentPagination.current_page - 1)">{{ t.previous }}</button>
            <span>{{ t.page }} <b dir="ltr">{{ number(currentPagination.current_page) }} / {{ number(currentPagination.last_page) }}</b></span>
            <button class="button secondary" :disabled="loading || currentPagination.current_page >= currentPagination.last_page" @click="changePage(currentPagination.current_page + 1)">{{ t.next }}</button>
          </nav>
        </footer>
      </section>
    </template>

    <WorksActivityAuditDrawer
      :open="auditDrawerOpen"
      :item="selectedAuditItem"
      :definition="selectedAuditDefinition"
      :locale="currentLocale"
      :can-open-work="canViewWorkDetails"
      :return-focus="auditReturnFocus"
      @close="closeAuditDrawer"
    />
    <WorksActivityLifecycleDrawer
      :open="lifecycleDrawerOpen"
      :item="selectedLifecycleItem"
      :locale="currentLocale"
      @close="closeLifecycleDrawer"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import WorksActivityAuditDrawer from '~/components/works/activity/WorksActivityAuditDrawer.vue'
import WorksActivityAuditTable from '~/components/works/activity/WorksActivityAuditTable.vue'
import WorksActivityLifecycleDrawer from '~/components/works/activity/WorksActivityLifecycleDrawer.vue'
import WorksActivityLifecycleSmartList from '~/components/works/activity/WorksActivityLifecycleSmartList.vue'
import WorksIndexInfoPopover from '~/components/works/index/WorksIndexInfoPopover.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import { formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

definePageMeta({
  layout: 'admin',
  alias: ['/admin/works/log']
})
type Locale = 'ar' | 'en'
type ActivitySourceMode = 'audit' | 'lifecycle'
type SortDirection = 'asc' | 'desc'
type PageSize = 15 | 25 | 50
type AuditSortKey = 'event_at' | 'audit_event_id' | 'event_type' | 'actor_name' | 'work_id' | 'work_title'
type LifecycleSortKey = 'event_at' | 'work_id' | 'title' | 'status' | 'reports_count'
type LifecycleEventType = 'created' | 'updated' | 'submitted' | 'reviewed' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'

interface UserReference { id: number; name: string }
interface LifecycleActivityItem {
  id: string; work_id: number; event_type: LifecycleEventType; event_label: string; event_at: string; title: string; slug: string; status: WorkStatus
  visibility_status: 'public' | 'hidden'; media_type: string | null; designer: UserReference | null; reviewer: UserReference | null; category_id: number | null
  reports_count: number; views_count: number; likes_count: number
  activity_flags: { is_review_event: boolean; is_visibility_event: boolean; is_reported: boolean; is_promoted: boolean; needs_attention: boolean }
}
interface AuditActivityItem {
  id: string; source: string; audit_event_id: number; event_type: string; event_key: string; event_group: string; event_label_ar: string; event_label_en: string
  event_at: string; severity: string | null; action: string | null; outcome: string | null
  actor: { id: number | null; name: string; role: string | null } | null; target: { type: string; id: number | null; scope: string }
  work: { id: number; title: string; slug: string; status: string; visibility_status: string; media_type: string | null } | null
  activity_flags: { requires_work: boolean; needs_attention: boolean; actor_missing: boolean; work_missing: boolean }
}
interface LifecycleActivitySummary { total_events: number; unique_works: number; created_events: number; updated_events: number; submitted_events: number; reviewed_events: number; approved_events: number; published_events: number; rejected_events: number; hidden_events: number; archived_events: number; review_events: number; visibility_events: number; reported_events: number; promoted_events: number }
interface AuditActivitySummary { total_events: number; unique_works: number; review_events: number; visibility_events: number; report_events: number; taxonomy_events: number; taxonomy_assignment_events: number; attention_events: number }
interface LifecycleFilters { q: string; event_type: '' | LifecycleEventType; status: '' | WorkStatus; visibility_status: '' | 'public' | 'hidden'; media_type: string; designer_id: string; reviewer_id: string; category_id: string; reported: '' | '1' | '0'; promoted: '' | '1' | '0'; from: string; to: string; sort: LifecycleSortKey; direction: SortDirection; per_page: PageSize }
interface AuditFilters { q: string; event_type: string; event_group: string; actor_id: string; target_type: string; target_id: string; work_id: string; outcome: string; from: string; to: string; sort: AuditSortKey; direction: SortDirection; per_page: PageSize }
interface ActivitySource { source: string; mode: ActivitySourceMode; dedicated_log_available: boolean; legacy_source_available?: boolean; reason?: string }
interface EventCatalogGroup { key: string; label_ar: string; label_en: string }
interface EventCatalogEvent { event_type: string; event_key: string; event_group: string; label_ar: string; label_en: string; target_scope: string; requires_work: boolean; needs_attention: boolean }
interface EventCatalog { groups: EventCatalogGroup[]; events: EventCatalogEvent[] }
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }
interface ApiResponse<TItem, TSummary> { success: boolean; data: { items: TItem[]; pagination: Pagination; summary: TSummary; filters: Record<string, unknown>; activity_source: ActivitySource; event_catalog?: EventCatalog } | null; message?: string; errors?: Record<string, string[]> | null }

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const direction = computed(() => currentLocale.value === 'ar' ? 'rtl' : 'ltr')
const mode = ref<ActivitySourceMode>('audit')
const lifecycleItems = ref<LifecycleActivityItem[]>([])
const auditItems = ref<AuditActivityItem[]>([])
const lifecycleSummary = ref<LifecycleActivitySummary | null>(null)
const auditSummary = ref<AuditActivitySummary | null>(null)
const activitySource = ref<ActivitySource | null>(null)
const eventCatalog = reactive<EventCatalog>({ groups: [], events: [] })
const paginations = reactive<Record<ActivitySourceMode, Pagination>>({
  audit: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
  lifecycle: { current_page: 1, per_page: 15, total: 0, last_page: 1 },
})
const pages = reactive<Record<ActivitySourceMode, number>>({ audit: 1, lifecycle: 1 })
const loaded = reactive<Record<ActivitySourceMode, boolean>>({ audit: false, lifecycle: false })
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)
const serverForbidden = ref(false)
const lifecycleDrawerOpen = ref(false)
const selectedLifecycleItem = ref<LifecycleActivityItem | null>(null)
const auditDrawerOpen = ref(false)
const selectedAuditItem = ref<AuditActivityItem | null>(null)
const auditReturnFocus = ref<HTMLElement | null>(null)
let mounted = false
let requestRevision = 0
let accessRevision = 0
let loadedAuthorizationSignature: string | null = null
let auditSearchTimer: ReturnType<typeof setTimeout> | undefined
let lifecycleSearchTimer: ReturnType<typeof setTimeout> | undefined

const copyMap = {
  ar: {
    breadcrumb: 'مسار الصفحة', admin: 'الإدارة', works: 'الأعمال', readOnly: 'قراءة تنظيمية فقط', pageTitle: 'سجل الأعمال',
    auditDescription: 'تتبّع إجراءات الإدارة والمراجعة والظهور ضمن سجل تشغيلي واضح.', lifecycleDescription: 'استعراض محطات دورة حياة الأعمال وتواريخها في سجل مستقل.',
    totalEvents: 'إجمالي الأحداث', currentFilters: 'ضمن الفلاتر الحالية', audit: 'السجل التشغيلي', lifecycle: 'دورة حياة الأعمال',
    auditTabHint: 'إجراءات الإدارة والمراجعة', lifecycleTabHint: 'المحطات الزمنية للأعمال', source: 'مصدر السجل',
    sourceDetails: 'تفاصيل مصدر السجل', sourceType: 'نوع المصدر', technicalSource: 'المصدر التقني', auditSourceLabel: 'مصدر تدقيق تشغيلي',
    lifecycleSourceLabel: 'مصدر تاريخ دورة الحياة', authPending: 'جارٍ التحقق من صلاحية سجل الأعمال', forbidden: 'الوصول إلى سجل الأعمال غير متاح',
    forbiddenHint: 'لا يملك هذا الحساب الصلاحيات المطلوبة.', auditNotice: 'أحداث تشغيلية للقراءة فقط، وتبقى الإجراءات في محطاتها المختصة.',
    lifecycleNotice: 'تواريخ مستقلة لدورة حياة الأعمال لأغراض المتابعة والتحليل.', summary: 'ملخص سجل الأعمال', summaryDetails: 'تفاصيل الملخص',
    filters: 'الفلاتر', refreshing: 'جارٍ تحديث النتائج', reset: 'إعادة ضبط', search: 'البحث', auditSearch: 'الحدث أو الإجراء أو الفاعل أو العمل',
    lifecycleSearch: 'عنوان العمل أو المعرّف النصي', eventGroup: 'مجموعة الحدث', eventType: 'نوع الحدث', actorId: 'معرّف الفاعل',
    targetType: 'نوع الهدف', targetId: 'معرّف الهدف', workId: 'معرّف العمل', outcome: 'النتيجة', from: 'من تاريخ', to: 'إلى تاريخ',
    sort: 'الفرز', direction: 'الاتجاه', perPage: 'لكل صفحة', all: 'الكل', desc: 'تنازلي', asc: 'تصاعدي', apply: 'تطبيق الفلاتر',
    advanced: 'فلاتر متقدمة', activeFilters: 'الفلاتر النشطة', status: 'الحالة', visibility: 'الظهور', media: 'نوع الوسائط',
    designerId: 'معرّف المصمم', reviewerId: 'معرّف المراجع', categoryId: 'معرّف التصنيف', reported: 'البلاغات', promoted: 'مروّج',
    public: 'عام', hidden: 'مخفي', yes: 'نعم', no: 'لا', auditEvents: 'أحداث السجل التشغيلي', lifecycleEvents: 'أحداث دورة حياة الأعمال',
    auditTableHint: 'ملخصات قابلة للفحص، وتفاصيلها داخل النوافذ المعلوماتية.', lifecycleTableHint: 'محطات زمنية مستقلة دون دمجها مع سجل التدقيق.',
    loading: 'جارٍ تحميل سجل الأعمال', loadFailed: 'تعذر تحميل سجل الأعمال', retry: 'إعادة المحاولة', empty: 'لا توجد أحداث مطابقة',
    visibleNow: 'عنصر ظاهر الآن', pagination: 'التنقل بين الصفحات', previous: 'السابق', next: 'التالي', page: 'الصفحة', close: 'إغلاق',
    validation: 'تحقق من قيم الفلاتر والتواريخ.', genericError: 'حدث خطأ أثناء تحميل سجل الأعمال.',
  },
  en: {
    breadcrumb: 'Breadcrumb', admin: 'Administration', works: 'Works', readOnly: 'Read-only operational view', pageTitle: 'Works activity',
    auditDescription: 'Track administration, review, and visibility actions in a clear operational log.', lifecycleDescription: 'Review independent work lifecycle milestones and dates.',
    totalEvents: 'Total events', currentFilters: 'Within current filters', audit: 'Operational audit', lifecycle: 'Work lifecycle',
    auditTabHint: 'Administration and review actions', lifecycleTabHint: 'Work timeline milestones', source: 'Activity source',
    sourceDetails: 'Activity source details', sourceType: 'Source type', technicalSource: 'Technical source', auditSourceLabel: 'Operational audit source',
    lifecycleSourceLabel: 'Lifecycle history source', authPending: 'Checking works activity access', forbidden: 'Works activity is unavailable',
    forbiddenHint: 'This account lacks the required permissions.', auditNotice: 'Read-only operational events; actions remain in their dedicated stations.',
    lifecycleNotice: 'Independent lifecycle dates for monitoring and analysis.', summary: 'Works activity summary', summaryDetails: 'Summary details',
    filters: 'Filters', refreshing: 'Refreshing results', reset: 'Reset', search: 'Search', auditSearch: 'Event, action, actor, or work',
    lifecycleSearch: 'Work title or text identifier', eventGroup: 'Event group', eventType: 'Event type', actorId: 'Actor ID',
    targetType: 'Target type', targetId: 'Target ID', workId: 'Work ID', outcome: 'Outcome', from: 'From', to: 'To',
    sort: 'Sort', direction: 'Direction', perPage: 'Per page', all: 'All', desc: 'Descending', asc: 'Ascending', apply: 'Apply filters',
    advanced: 'Advanced filters', activeFilters: 'Active filters', status: 'Status', visibility: 'Visibility', media: 'Media type',
    designerId: 'Designer ID', reviewerId: 'Reviewer ID', categoryId: 'Category ID', reported: 'Reports', promoted: 'Promoted',
    public: 'Public', hidden: 'Hidden', yes: 'Yes', no: 'No', auditEvents: 'Operational audit events', lifecycleEvents: 'Work lifecycle events',
    auditTableHint: 'Scannable summaries with details in information popovers.', lifecycleTableHint: 'Independent timeline milestones, separate from audit events.',
    loading: 'Loading works activity', loadFailed: 'Could not load works activity', retry: 'Retry', empty: 'No matching events',
    visibleNow: 'items currently visible', pagination: 'Activity pagination', previous: 'Previous', next: 'Next', page: 'Page', close: 'Close',
    validation: 'Check filter values and dates.', genericError: 'An error occurred while loading works activity.',
  },
} as const
const t = computed(() => copyMap[currentLocale.value])

const authPending = computed(() => !authStore.isInitialized)
const hasAccess = computed(() => authStore.isInitialized && authStore.isAuthenticated && (authStore.role === 'super-admin' || (['admin', 'staff'].includes(authStore.role || '') && ['admin.works.access', 'admin.works.activity.view', 'admin.works.activity.list'].every(permission => authStore.permissions.includes(permission)))))
const forbidden = computed(() => authStore.isInitialized && (!hasAccess.value || serverForbidden.value))
const canViewWorkDetails = computed(() => hasAccess.value && (authStore.role === 'super-admin' || authStore.permissions.includes('admin.works.detail.view')))
const currentItems = computed(() => mode.value === 'audit' ? auditItems.value : lifecycleItems.value)
const currentPagination = computed(() => paginations[mode.value])
const currentLoaded = computed(() => loaded[mode.value])
const summaryTotal = computed(() => mode.value === 'audit' ? auditSummary.value?.total_events ?? 0 : lifecycleSummary.value?.total_events ?? 0)
const sourceName = computed(() => toLatinDigits(activitySource.value?.source ?? (mode.value === 'audit' ? 'audit_events' : 'work_lifecycle_timestamps')))
const authorizationSignature = computed(() => [authStore.isInitialized, authStore.isAuthenticated, authStore.role, [...authStore.permissions].sort().join(',')].join('|'))

const lifecycleEvents: LifecycleEventType[] = ['created', 'updated', 'submitted', 'reviewed', 'approved', 'published', 'rejected', 'hidden', 'archived']
const workStatuses: WorkStatus[] = ['draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'published', 'rejected', 'hidden', 'archived']
const targetTypes = ['work', 'work_report', 'work_category', 'work_tag']
const defaultAuditFilters = (): AuditFilters => ({ q: '', event_type: '', event_group: '', actor_id: '', target_type: '', target_id: '', work_id: '', outcome: '', from: '', to: '', sort: 'event_at', direction: 'desc', per_page: 15 })
const defaultLifecycleFilters = (): LifecycleFilters => ({ q: '', event_type: '', status: '', visibility_status: '', media_type: '', designer_id: '', reviewer_id: '', category_id: '', reported: '', promoted: '', from: '', to: '', sort: 'event_at', direction: 'desc', per_page: 15 })
const auditFilters = reactive<AuditFilters>(defaultAuditFilters())
const appliedAuditFilters = reactive<AuditFilters>(defaultAuditFilters())
const lifecycleFilters = reactive<LifecycleFilters>(defaultLifecycleFilters())
const appliedLifecycleFilters = reactive<LifecycleFilters>(defaultLifecycleFilters())
const visibleCatalogEvents = computed(() => eventCatalog.events.filter(event => !auditFilters.event_group || event.event_group === auditFilters.event_group))
const selectedAuditDefinition = computed(() => selectedAuditItem.value ? eventCatalog.events.find(event => event.event_type === selectedAuditItem.value?.event_type) ?? null : null)

const auditSortOptions = computed(() => [
  { value: 'event_at' as const, label: currentLocale.value === 'ar' ? 'وقت الحدث' : 'Event time' },
  { value: 'audit_event_id' as const, label: currentLocale.value === 'ar' ? 'رقم الحدث' : 'Event ID' },
  { value: 'event_type' as const, label: currentLocale.value === 'ar' ? 'نوع الحدث' : 'Event type' },
  { value: 'actor_name' as const, label: currentLocale.value === 'ar' ? 'اسم الفاعل' : 'Actor name' },
  { value: 'work_id' as const, label: currentLocale.value === 'ar' ? 'معرّف العمل' : 'Work ID' },
  { value: 'work_title' as const, label: currentLocale.value === 'ar' ? 'عنوان العمل' : 'Work title' },
])
const lifecycleSortOptions = computed(() => [
  { value: 'event_at' as const, label: currentLocale.value === 'ar' ? 'وقت الحدث' : 'Event time' },
  { value: 'work_id' as const, label: currentLocale.value === 'ar' ? 'معرّف العمل' : 'Work ID' },
  { value: 'title' as const, label: currentLocale.value === 'ar' ? 'عنوان العمل' : 'Work title' },
  { value: 'status' as const, label: currentLocale.value === 'ar' ? 'حالة العمل' : 'Work status' },
  { value: 'reports_count' as const, label: currentLocale.value === 'ar' ? 'عدد البلاغات' : 'Report count' },
])

const summaryCards = computed(() => {
  if (mode.value === 'audit') {
    const s = auditSummary.value
    if (!s) return []
    return [
      { key: 'total', label: t.value.totalEvents, value: s.total_events, hint: t.value.currentFilters },
      { key: 'works', label: currentLocale.value === 'ar' ? 'الأعمال الفريدة' : 'Unique works', value: s.unique_works, hint: currentLocale.value === 'ar' ? 'أعمال مستقلة' : 'Distinct works' },
      { key: 'review', label: currentLocale.value === 'ar' ? 'أحداث المراجعة' : 'Review events', value: s.review_events, hint: currentLocale.value === 'ar' ? 'ضمن مسار المراجعة' : 'Review workflow' },
      { key: 'visibility', label: currentLocale.value === 'ar' ? 'أحداث الظهور' : 'Visibility events', value: s.visibility_events, hint: currentLocale.value === 'ar' ? 'تغييرات الظهور' : 'Visibility changes' },
      { key: 'reports', label: currentLocale.value === 'ar' ? 'أحداث البلاغات' : 'Report events', value: s.report_events, hint: currentLocale.value === 'ar' ? 'مرتبطة بالبلاغات' : 'Report related' },
      { key: 'attention', label: currentLocale.value === 'ar' ? 'تحتاج انتباهًا' : 'Needs attention', value: s.attention_events, hint: currentLocale.value === 'ar' ? 'تحتاج متابعة إدارية' : 'Requires follow-up', alert: true },
    ]
  }
  const s = lifecycleSummary.value
  if (!s) return []
  return [
    { key: 'total', label: t.value.totalEvents, value: s.total_events, hint: t.value.currentFilters },
    { key: 'works', label: currentLocale.value === 'ar' ? 'الأعمال الفريدة' : 'Unique works', value: s.unique_works, hint: currentLocale.value === 'ar' ? 'أعمال مستقلة' : 'Distinct works' },
    { key: 'create-update', label: currentLocale.value === 'ar' ? 'الإنشاء والتحديث' : 'Created and updated', value: s.created_events + s.updated_events, hint: currentLocale.value === 'ar' ? 'بدايات وتغييرات العمل' : 'Work starts and changes' },
    { key: 'review', label: currentLocale.value === 'ar' ? 'أحداث المراجعة' : 'Review events', value: s.review_events, hint: currentLocale.value === 'ar' ? 'ضمن مسار المراجعة' : 'Review workflow' },
    { key: 'publish-reject', label: currentLocale.value === 'ar' ? 'النشر والرفض' : 'Published and rejected', value: s.published_events + s.rejected_events, hint: currentLocale.value === 'ar' ? 'قرارات النشر' : 'Publishing decisions' },
    { key: 'follow-up', label: currentLocale.value === 'ar' ? 'تحتاج متابعة' : 'Needs follow-up', value: s.reported_events, hint: currentLocale.value === 'ar' ? 'أعمال مرتبطة ببلاغات' : 'Reported works', alert: true },
  ]
})
const summaryDetails = computed(() => {
  if (mode.value === 'audit') {
    const s = auditSummary.value
    if (!s) return []
    return [
      { key: 'taxonomy', label: currentLocale.value === 'ar' ? 'أحداث التصنيف' : 'Taxonomy events', value: s.taxonomy_events },
      { key: 'assignment', label: currentLocale.value === 'ar' ? 'أحداث الإسناد' : 'Assignment events', value: s.taxonomy_assignment_events },
    ]
  }
  const s = lifecycleSummary.value
  if (!s) return []
  return [
    ['created', 'created_events'], ['updated', 'updated_events'], ['submitted', 'submitted_events'], ['reviewed', 'reviewed_events'],
    ['approved', 'approved_events'], ['published', 'published_events'], ['rejected', 'rejected_events'], ['hidden', 'hidden_events'],
    ['archived', 'archived_events'], ['review', 'review_events'], ['visibility', 'visibility_events'], ['reported', 'reported_events'], ['promoted', 'promoted_events'],
  ].map(([key, field]) => ({ key, label: lifecycleDetailLabel(key), value: Number(s[field as keyof LifecycleActivitySummary] ?? 0) }))
})

const auditAdvancedKeys: (keyof AuditFilters)[] = ['actor_id', 'target_type', 'target_id', 'work_id', 'from', 'to']
const lifecycleAdvancedKeys: (keyof LifecycleFilters)[] = ['media_type', 'designer_id', 'reviewer_id', 'category_id', 'reported', 'promoted', 'from', 'to']
const advancedFilterCount = computed(() => {
  const filters = mode.value === 'audit' ? appliedAuditFilters : appliedLifecycleFilters
  const keys = mode.value === 'audit' ? auditAdvancedKeys : lifecycleAdvancedKeys
  const values = filters as unknown as Record<string, unknown>
  return keys.filter(key => String(values[key] ?? '').trim() !== '').length
})
const activeFilterChips = computed(() => {
  const filters = mode.value === 'audit' ? appliedAuditFilters : appliedLifecycleFilters
  const defaults = mode.value === 'audit' ? defaultAuditFilters() : defaultLifecycleFilters()
  const defaultValues = defaults as unknown as Record<string, unknown>
  return Object.entries(filters)
    .filter(([key, value]) => !['sort', 'direction', 'per_page'].includes(key) && String(value).trim() !== '' && value !== defaultValues[key])
    .map(([key, value]) => ({ key, label: filterLabel(key), value: filterValueLabel(key, value) }))
})

function catalogLabel(item: EventCatalogGroup | EventCatalogEvent): string { return currentLocale.value === 'ar' ? item.label_ar : item.label_en }
function lifecycleEventLabel(value: LifecycleEventType): string {
  const ar = { created: 'إنشاء', updated: 'تحديث', submitted: 'إرسال', reviewed: 'مراجعة', approved: 'اعتماد', published: 'نشر', rejected: 'رفض', hidden: 'إخفاء', archived: 'أرشفة' }
  return currentLocale.value === 'ar' ? ar[value] : value.replaceAll('_', ' ')
}
function lifecycleDetailLabel(value: string): string {
  const ar: Record<string, string> = { created: 'الإنشاء', updated: 'التحديث', submitted: 'الإرسال', reviewed: 'المراجعة', approved: 'الاعتماد', published: 'النشر', rejected: 'الرفض', hidden: 'الإخفاء', archived: 'الأرشفة', review: 'مسار المراجعة', visibility: 'تغييرات الظهور', reported: 'مرتبطة ببلاغات', promoted: 'مرتبطة بالترويج' }
  return currentLocale.value === 'ar' ? ar[value] ?? value : value.replaceAll('_', ' ')
}
function statusLabel(value: WorkStatus): string {
  const ar = { draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' }
  return currentLocale.value === 'ar' ? ar[value] : value.replaceAll('_', ' ')
}
function targetLabel(value: string): string {
  const ar: Record<string, string> = { work: 'عمل', work_report: 'بلاغ عمل', work_category: 'تصنيف عمل', work_tag: 'وسم عمل' }
  return currentLocale.value === 'ar' ? ar[value] ?? toLatinDigits(value) : toLatinDigits(value.replaceAll('_', ' '))
}
function number(value: number): string { return formatYmNumber(Number.isFinite(value) ? value : 0, currentLocale.value) }
function filterLabel(key: string): string {
  const labels: Record<string, string> = { q: t.value.search, event_group: t.value.eventGroup, event_type: t.value.eventType, outcome: t.value.outcome, actor_id: t.value.actorId, target_type: t.value.targetType, target_id: t.value.targetId, work_id: t.value.workId, status: t.value.status, visibility_status: t.value.visibility, media_type: t.value.media, designer_id: t.value.designerId, reviewer_id: t.value.reviewerId, category_id: t.value.categoryId, reported: t.value.reported, promoted: t.value.promoted, from: t.value.from, to: t.value.to }
  return labels[key] ?? key
}
function filterValueLabel(key: string, value: unknown): string {
  if (key === 'reported' || key === 'promoted') return value === '1' ? t.value.yes : t.value.no
  if (key === 'visibility_status') return value === 'public' ? t.value.public : t.value.hidden
  if (key === 'target_type') return targetLabel(String(value))
  if (key === 'event_group') return eventCatalog.groups.find(group => group.key === value)?.[currentLocale.value === 'ar' ? 'label_ar' : 'label_en'] ?? toLatinDigits(String(value))
  if (key === 'event_type') {
    if (mode.value === 'lifecycle') return lifecycleEventLabel(value as LifecycleEventType)
    const event = eventCatalog.events.find(entry => entry.event_type === value)
    return event ? catalogLabel(event) : toLatinDigits(String(value))
  }
  if (key === 'status') return statusLabel(value as WorkStatus)
  if (key === 'media_type' && value === 'image') return currentLocale.value === 'ar' ? 'صورة' : 'Image'
  if (key === 'media_type' && value === 'video') return currentLocale.value === 'ar' ? 'فيديو' : 'Video'
  return toLatinDigits(String(value))
}
function errorStatus(value: unknown): number | null {
  if (!value || typeof value !== 'object') return null
  const candidate = value as { response?: { status?: unknown; _data?: unknown }; statusCode?: unknown; status?: unknown }
  return typeof candidate.response?.status === 'number' ? candidate.response.status : typeof candidate.statusCode === 'number' ? candidate.statusCode : typeof candidate.status === 'number' ? candidate.status : null
}
function firstValidationMessage(value: unknown): string | null {
  if (!value || typeof value !== 'object') return null
  const responseData = (value as { response?: { _data?: unknown }; data?: unknown }).response?._data ?? (value as { data?: unknown }).data
  if (!responseData || typeof responseData !== 'object') return null
  const errors = (responseData as { errors?: unknown }).errors
  if (!errors || typeof errors !== 'object') return null
  for (const messages of Object.values(errors as Record<string, unknown>)) if (Array.isArray(messages) && typeof messages[0] === 'string') return toLatinDigits(messages[0])
  return null
}
function validFilters(): boolean {
  filterError.value = null
  const filters = mode.value === 'audit' ? auditFilters : lifecycleFilters
  if (filters.q.trim().length === 1 || (filters.from && filters.to && filters.to < filters.from)) { filterError.value = t.value.validation; return false }
  const ids = mode.value === 'audit' ? [auditFilters.actor_id, auditFilters.target_id, auditFilters.work_id] : [lifecycleFilters.designer_id, lifecycleFilters.reviewer_id, lifecycleFilters.category_id]
  if (ids.some(value => value && (!Number.isInteger(Number(value)) || Number(value) < 1))) { filterError.value = t.value.validation; return false }
  return true
}
function query(source: ActivitySourceMode): Record<string, string | number> {
  const filters = source === 'audit' ? appliedAuditFilters : appliedLifecycleFilters
  const result: Record<string, string | number> = { source, page: pages[source], per_page: filters.per_page, sort: filters.sort, direction: filters.direction }
  for (const [key, value] of Object.entries(filters)) if (!['per_page', 'sort', 'direction'].includes(key) && String(value).trim() !== '') result[key] = value
  return result
}
async function fetchActivity(): Promise<void> {
  if (!hasAccess.value) return
  const source = mode.value
  const requestAccess = accessRevision
  const revision = ++requestRevision
  loading.value = true
  error.value = null
  filterError.value = null
  try {
    if (source === 'audit') {
      const response = await apiFetch<ApiResponse<AuditActivityItem, AuditActivitySummary>>('/admin/works/activity', { query: query(source) })
      if (!isCurrent(source, requestAccess, revision)) return
      if (!response.success || !response.data) throw new Error('invalid-response')
      auditItems.value = response.data.items
      auditSummary.value = response.data.summary
      Object.assign(eventCatalog, response.data.event_catalog ?? { groups: [], events: [] })
      acceptData(source, response.data.pagination, response.data.activity_source)
    } else {
      const response = await apiFetch<ApiResponse<LifecycleActivityItem, LifecycleActivitySummary>>('/admin/works/activity', { query: query(source) })
      if (!isCurrent(source, requestAccess, revision)) return
      if (!response.success || !response.data) throw new Error('invalid-response')
      lifecycleItems.value = response.data.items
      lifecycleSummary.value = response.data.summary
      acceptData(source, response.data.pagination, response.data.activity_source)
    }
  } catch (requestError: unknown) {
    if (!isCurrent(source, requestAccess, revision)) return
    const status = errorStatus(requestError)
    if (status === 401) { clearData(); if (authStore.isAuthenticated) void authStore.fetchUser(); return }
    if (status === 403) { serverForbidden.value = true; clearData(); return }
    if (status === 422) { filterError.value = firstValidationMessage(requestError) ?? t.value.validation; return }
    error.value = t.value.genericError
  } finally {
    if (isCurrent(source, requestAccess, revision)) loading.value = false
  }
}
function isCurrent(source: ActivitySourceMode, requestAccess: number, revision: number): boolean { return source === mode.value && requestAccess === accessRevision && revision === requestRevision && hasAccess.value }
function acceptData(source: ActivitySourceMode, next: Pagination, activity: ActivitySource): void { Object.assign(paginations[source], next); pages[source] = next.current_page; activitySource.value = activity; loaded[source] = true; serverForbidden.value = false }
function clearData(): void {
  auditItems.value = []; lifecycleItems.value = []; auditSummary.value = null; lifecycleSummary.value = null; activitySource.value = null
  Object.assign(eventCatalog, { groups: [], events: [] }); Object.assign(paginations.audit, { current_page: 1, per_page: 15, total: 0, last_page: 1 })
  Object.assign(paginations.lifecycle, { current_page: 1, per_page: 15, total: 0, last_page: 1 }); pages.audit = 1; pages.lifecycle = 1; loaded.audit = false; loaded.lifecycle = false
  closeDrawers()
}
function applyFilters(): void {
  if (!validFilters()) return
  if (mode.value === 'audit') Object.assign(appliedAuditFilters, auditFilters); else Object.assign(appliedLifecycleFilters, lifecycleFilters)
  pages[mode.value] = 1; closeDrawers(); void fetchActivity()
}
function applyBasicFilters(): void {
  if (!validFilters()) return
  if (mode.value === 'audit') {
    appliedAuditFilters.event_group = auditFilters.event_group
    appliedAuditFilters.event_type = auditFilters.event_type
    appliedAuditFilters.outcome = auditFilters.outcome
  } else {
    appliedLifecycleFilters.event_type = lifecycleFilters.event_type
    appliedLifecycleFilters.status = lifecycleFilters.status
    appliedLifecycleFilters.visibility_status = lifecycleFilters.visibility_status
  }
  pages[mode.value] = 1
  closeDrawers()
  void fetchActivity()
}
function resetFilters(): void {
  if (mode.value === 'audit') { Object.assign(auditFilters, defaultAuditFilters()); Object.assign(appliedAuditFilters, defaultAuditFilters()) }
  else { Object.assign(lifecycleFilters, defaultLifecycleFilters()); Object.assign(appliedLifecycleFilters, defaultLifecycleFilters()) }
  pages[mode.value] = 1; filterError.value = null; closeDrawers(); void fetchActivity()
}
function removeFilter(key: string): void {
  if (mode.value === 'audit' && key in auditFilters) {
    const defaults = defaultAuditFilters()
    ;(auditFilters as unknown as Record<string, unknown>)[key] = (defaults as unknown as Record<string, unknown>)[key]
    ;(appliedAuditFilters as unknown as Record<string, unknown>)[key] = (defaults as unknown as Record<string, unknown>)[key]
  } else if (mode.value === 'lifecycle' && key in lifecycleFilters) {
    const defaults = defaultLifecycleFilters()
    ;(lifecycleFilters as unknown as Record<string, unknown>)[key] = (defaults as unknown as Record<string, unknown>)[key]
    ;(appliedLifecycleFilters as unknown as Record<string, unknown>)[key] = (defaults as unknown as Record<string, unknown>)[key]
  }
  pages[mode.value] = 1
  void fetchActivity()
}
function switchSource(next: ActivitySourceMode): void {
  if (next === mode.value) return
  requestRevision += 1
  closeDrawers()
  mode.value = next
  error.value = null
  filterError.value = null
  activitySource.value = null
  void fetchActivity()
}
function onTabsKeydown(event: KeyboardEvent): void {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return
  event.preventDefault()
  const tablist = event.currentTarget as HTMLElement | null
  const next = event.key === 'Home' ? 'audit' : event.key === 'End' ? 'lifecycle' : mode.value === 'audit' ? 'lifecycle' : 'audit'
  switchSource(next)
  requestAnimationFrame(() => tablist?.querySelector<HTMLElement>('[aria-selected="true"]')?.focus())
}
function syncAuditEventType(): void { if (auditFilters.event_type && !visibleCatalogEvents.value.some(event => event.event_type === auditFilters.event_type)) auditFilters.event_type = '' }
function changeAuditSort(key: AuditSortKey): void {
  if (appliedAuditFilters.sort === key) appliedAuditFilters.direction = appliedAuditFilters.direction === 'asc' ? 'desc' : 'asc'
  else { appliedAuditFilters.sort = key; appliedAuditFilters.direction = key === 'event_at' ? 'desc' : 'asc' }
  auditFilters.sort = appliedAuditFilters.sort; auditFilters.direction = appliedAuditFilters.direction; pages.audit = 1; closeAuditDrawer(); void fetchActivity()
}
function changeLifecycleSort(key: LifecycleSortKey): void {
  if (appliedLifecycleFilters.sort === key) appliedLifecycleFilters.direction = appliedLifecycleFilters.direction === 'asc' ? 'desc' : 'asc'
  else { appliedLifecycleFilters.sort = key; appliedLifecycleFilters.direction = ['work_id', 'title', 'status'].includes(key) ? 'asc' : 'desc' }
  lifecycleFilters.sort = appliedLifecycleFilters.sort; lifecycleFilters.direction = appliedLifecycleFilters.direction; pages.lifecycle = 1; closeLifecycleDrawer(); void fetchActivity()
}
function changePage(next: number): void {
  const pagination = paginations[mode.value]
  if (loading.value || next < 1 || next > pagination.last_page || next === pagination.current_page) return
  pages[mode.value] = next; closeDrawers(); void fetchActivity()
}
function closeInformationOverlays(): void { if (import.meta.client) document.dispatchEvent(new Event('ym:works-index-overlays-close')) }
function openAuditDrawer(item: AuditActivityItem, trigger: HTMLElement | null): void { closeInformationOverlays(); selectedAuditItem.value = item; auditReturnFocus.value = trigger; auditDrawerOpen.value = true }
function closeAuditDrawer(): void { auditDrawerOpen.value = false; selectedAuditItem.value = null }
function openLifecycleDrawer(item: LifecycleActivityItem): void { closeInformationOverlays(); selectedLifecycleItem.value = item; lifecycleDrawerOpen.value = true }
function closeLifecycleDrawer(): void { lifecycleDrawerOpen.value = false; selectedLifecycleItem.value = null }
function closeDrawers(): void { closeAuditDrawer(); closeLifecycleDrawer() }
function syncAccess(): void {
  if (!mounted) return
  accessRevision += 1; requestRevision += 1; serverForbidden.value = false; closeDrawers()
  if (!hasAccess.value) { loadedAuthorizationSignature = null; clearData(); loading.value = false; return }
  if (loadedAuthorizationSignature === authorizationSignature.value) return
  loadedAuthorizationSignature = authorizationSignature.value; void fetchActivity()
}
function queueSearch(source: ActivitySourceMode, value: string): void {
  const normalized = value.trim()
  const timer = source === 'audit' ? auditSearchTimer : lifecycleSearchTimer
  if (timer) clearTimeout(timer)
  if (normalized.length === 1) return
  const run = () => {
    const draft = source === 'audit' ? auditFilters : lifecycleFilters
    const applied = source === 'audit' ? appliedAuditFilters : appliedLifecycleFilters
    if (draft.q.trim() === applied.q) return
    applied.q = draft.q.trim(); pages[source] = 1
    if (mode.value === source && hasAccess.value) void fetchActivity()
  }
  if (source === 'audit') auditSearchTimer = setTimeout(run, 325)
  else lifecycleSearchTimer = setTimeout(run, 325)
}

watch(() => auditFilters.q, value => queueSearch('audit', value))
watch(() => lifecycleFilters.q, value => queueSearch('lifecycle', value))
watch(authorizationSignature, syncAccess, { flush: 'post' })
onMounted(() => { mounted = true; syncAccess() })
onBeforeUnmount(() => {
  if (auditSearchTimer) clearTimeout(auditSearchTimer)
  if (lifecycleSearchTimer) clearTimeout(lifecycleSearchTimer)
})
</script>

<style scoped>
.refresh-error{display:flex;align-items:center;justify-content:center;gap:8px;margin:0;border-top:1px solid rgba(225,29,72,.24);padding:7px;color:#e11d48;font-size:11px}.refresh-error button{border:0;background:transparent;color:inherit;font-weight:850;cursor:pointer;text-decoration:underline}
.activity-page{display:grid;gap:12px;min-width:0;color:var(--ym-text)}
.surface,.activity-hero,.source-tabs,.source-bar,.summary-strip{border:1px solid var(--ym-card-border);border-radius:20px;background:color-mix(in srgb,var(--ym-card-bg) 96%,transparent);box-shadow:var(--ym-card-shadow)}
.activity-hero{display:flex;min-height:138px;align-items:center;justify-content:space-between;gap:22px;padding:18px 22px;overflow:hidden}
.hero-copy{display:grid;gap:5px;min-width:0}
.breadcrumb{display:flex;align-items:center;gap:6px;color:var(--ym-muted);font-size:11px}
.breadcrumb i{font-style:normal;opacity:.55}
.read-only-chip{width:max-content;border:1px solid color-mix(in srgb,#0e7490 50%,var(--ym-card-border));border-radius:999px;padding:4px 8px;color:color-mix(in srgb,#22d3ee 72%,var(--ym-text));font-size:11px;font-weight:800}
.activity-hero h1{margin:0;font-size:clamp(28px,3vw,36px);line-height:1.1}
.activity-hero p{max-width:720px;margin:0;color:var(--ym-muted);font-size:13px}
.hero-total{display:grid;min-width:145px;place-items:center;border-inline-start:1px solid var(--ym-soft-border);padding-inline:18px}
.hero-total span,.hero-total small{color:var(--ym-muted);font-size:11px}
.hero-total strong{font-size:27px;line-height:1.15}
.source-tabs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:5px;padding:5px}
.source-tabs button{display:grid;gap:2px;border:1px solid transparent;border-radius:14px;padding:8px 12px;background:transparent;color:var(--ym-muted);cursor:pointer}
.source-tabs button[aria-selected=true]{border-color:color-mix(in srgb,#0e7490 45%,var(--ym-card-border));background:var(--ym-control-bg);color:var(--ym-text)}
.source-tabs strong{font-size:13px}
.source-tabs small{font-size:11px}
.source-tabs button:focus-visible,.button:focus-visible,.info-button:focus-visible,.summary-details:focus-visible,.active-filters button:focus-visible{outline:3px solid color-mix(in srgb,#0e7490 36%,transparent);outline-offset:2px}
.source-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 13px}
.source-bar>div{display:flex;align-items:center;gap:9px;min-width:0}
.source-bar strong{font-size:12px}
.source-bar span{overflow:hidden;color:var(--ym-muted);font-size:11.5px;text-overflow:ellipsis;white-space:nowrap}
.info-button{display:grid;width:34px;height:34px;place-items:center;border:1px solid var(--ym-soft-border);border-radius:10px;background:var(--ym-control-bg);color:var(--ym-text);font-weight:900;cursor:pointer}
.info-list{display:grid;gap:8px;margin:0}
.info-list>div{display:grid;gap:2px;border-bottom:1px solid var(--ym-soft-border);padding-bottom:7px}
.info-list>div:last-child{border:0;padding:0}
.info-list dt{color:var(--ym-muted);font-size:11.5px}
.info-list dd{margin:0;color:var(--ym-text);font-size:12.5px;font-weight:700;overflow-wrap:anywhere}
.summary-strip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr)) auto;align-items:stretch;overflow:visible}
.summary-strip article{display:grid;align-content:center;gap:2px;min-width:0;min-height:78px;border-inline-end:1px solid var(--ym-soft-border);padding:9px 11px}
.summary-strip article.alert{box-shadow:inset 0 2px #d97706}
.summary-strip article span,.summary-strip article small{overflow:hidden;color:var(--ym-muted);font-size:10.5px;text-overflow:ellipsis;white-space:nowrap}
.summary-strip article strong{font-size:19px}
.summary-details{height:100%;border:0;padding:8px 11px;background:transparent;color:var(--ym-text);font-size:11px;font-weight:800;cursor:pointer}
.summary-list{min-width:230px}
.filters{position:relative;z-index:2;padding:10px 12px;overflow:visible}
.filters>header{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.filters h2{margin:0;font-size:15px}
.refreshing{display:flex;align-items:center;gap:6px;color:var(--ym-muted);font-size:11px}
.refreshing .spinner{width:15px;height:15px;border-width:2px}
.basic-filters{display:grid;grid-template-columns:minmax(280px,2fr) repeat(3,minmax(145px,1fr));gap:8px}
.filters label{display:grid;gap:3px;min-width:0;color:var(--ym-muted);font-size:10.5px;font-weight:800}
.filters input,.filters select{width:100%;min-width:0;height:38px;box-sizing:border-box;border:1px solid var(--ym-soft-border);border-radius:10px;padding:0 9px;background:var(--ym-control-bg);color:var(--ym-text);font-size:12px}
.filter-actions{display:flex;align-items:center;gap:7px;margin-top:8px}
.advanced{position:relative}
.advanced:not([open]) .advanced-panel{display:none}
.advanced summary{display:flex;height:38px;align-items:center;gap:6px;border:1px solid var(--ym-soft-border);border-radius:10px;padding:0 11px;background:var(--ym-control-bg);font-size:12px;font-weight:800;cursor:pointer;list-style:none}
.advanced summary::-webkit-details-marker{display:none}
.advanced summary b{display:grid;min-width:20px;height:20px;place-items:center;border-radius:999px;background:#0e7490;color:#fff;font-size:10px}
.advanced-panel{position:absolute;z-index:20;inset-inline-start:0;top:calc(100% + 7px);display:grid;width:min(720px,calc(100vw - 72px));grid-template-columns:repeat(3,minmax(0,1fr));gap:9px;border:1px solid var(--ym-card-border);border-radius:16px;padding:13px;background:var(--ym-dropdown-bg,var(--ym-card-bg));box-shadow:0 22px 60px rgba(2,6,23,.28)}
.advanced-panel .button{align-self:end}
.button{min-height:38px;border:1px solid var(--ym-soft-border);border-radius:10px;padding:0 12px;background:var(--ym-control-bg);color:var(--ym-text);font-size:12px;font-weight:850;cursor:pointer}
.button.primary{border-color:#0e7490;background:#0e7490;color:#fff}
.button:disabled{opacity:.58;cursor:not-allowed}
.active-filters{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
.active-filters button{display:flex;align-items:center;gap:4px;border:1px solid var(--ym-soft-border);border-radius:999px;padding:4px 7px;background:var(--ym-control-bg);color:var(--ym-text);font-size:10.5px;cursor:pointer}
.active-filters b{font-size:14px}
.validation{margin:8px 0 0;color:#e11d48;font-size:12px;font-weight:800}
.table-card{overflow:hidden}
.table-card>header{display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid var(--ym-soft-border);padding:10px 13px}
.table-card h2{margin:0;font-size:15px}
.table-card header p{margin:2px 0 0;color:var(--ym-muted);font-size:11px}
.table-card header>strong{font-size:12px}
.state{display:grid;justify-items:center;gap:6px;padding:40px 14px;text-align:center}
.state h2,.state h3,.state p{margin:0}
.error-state{color:#e11d48}
.spinner{width:25px;height:25px;border:3px solid var(--ym-soft-border);border-top-color:#0e7490;border-radius:50%;animation:spin .8s linear infinite}
.pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--ym-soft-border);padding:9px 12px}
.pagination>div{display:grid}
.pagination span,.pagination small{color:var(--ym-muted);font-size:11px}
.pagination strong{font-size:15px}
.pagination nav{display:flex;align-items:center;gap:7px}
.pagination nav span{white-space:nowrap}
@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:1180px){
  .summary-strip{grid-template-columns:repeat(3,minmax(0,1fr))}
  .summary-strip article{border-bottom:1px solid var(--ym-soft-border)}
  .summary-details{min-height:42px}
  .basic-filters{grid-template-columns:repeat(2,minmax(0,1fr))}
  .basic-filters .search{grid-column:span 2}
}
@media(max-width:700px){
  .activity-page{gap:9px}
  .activity-hero,.pagination{align-items:stretch;flex-direction:column}
  .activity-hero{min-height:0;padding:14px}
  .hero-total{grid-template-columns:1fr auto;min-width:0;border-inline-start:0;border-top:1px solid var(--ym-soft-border);padding:9px 0 0}
  .hero-total small{grid-column:1/-1}
  .source-tabs{grid-template-columns:1fr}
  .source-bar>div{display:grid;gap:2px}
  .summary-strip{grid-template-columns:repeat(2,minmax(0,1fr))}
  .basic-filters{grid-template-columns:1fr}
  .basic-filters .search{grid-column:auto}
  .filter-actions{justify-content:space-between}
  .advanced-panel{position:fixed;inset:72px 10px auto;width:auto;max-height:calc(100vh - 90px);grid-template-columns:1fr;overflow:auto}
  .pagination nav{justify-content:space-between}
  .pagination nav .button{padding-inline:9px}
}
@media(max-width:420px){
  .summary-strip{grid-template-columns:1fr}
  .summary-strip article{min-height:58px}
  .pagination nav{flex-wrap:wrap}
}
/* Final visual pass: local contrast, hierarchy, and watermark restraint. */
.activity-page :is(.surface,.activity-hero,.source-tabs,.source-bar,.summary-strip){
  background:color-mix(in srgb,var(--ym-card-bg) 98%,transparent);
  backdrop-filter:blur(18px) saturate(112%)
}
.activity-hero{
  min-height:124px;
  padding:14px 18px
}
.hero-total{
  min-width:150px;
  border:1px solid color-mix(in srgb,#0e7490 30%,var(--ym-card-border));
  border-radius:15px;
  background:color-mix(in srgb,#0e7490 7%,var(--ym-control-bg));
  padding:9px 14px;
  box-shadow:inset 0 1px rgba(255,255,255,.05)
}
.source-tabs button{
  border-color:color-mix(in srgb,var(--ym-soft-border) 60%,transparent);
  background:color-mix(in srgb,var(--ym-control-bg) 45%,transparent)
}
.source-tabs button:hover:not([aria-selected=true]){
  border-color:var(--ym-soft-border);
  background:color-mix(in srgb,var(--ym-control-bg) 72%,transparent);
  color:color-mix(in srgb,var(--ym-muted) 72%,var(--ym-text))
}
.source-tabs button[aria-selected=true]{
  border-color:color-mix(in srgb,#0891b2 58%,var(--ym-card-border));
  background:color-mix(in srgb,#0e7490 12%,var(--ym-control-bg));
  box-shadow:inset 0 2px color-mix(in srgb,#22d3ee 62%,transparent),0 5px 14px rgba(14,116,144,.08)
}
.source-bar{justify-content:flex-start}
.source-bar>div{width:100%}
.source-bar>div :deep(.ym-floating-overlay){flex:0 0 auto}
.source-bar .info-button{width:28px;height:28px;border-color:color-mix(in srgb,#0e7490 38%,var(--ym-soft-border));color:#22b8cf}
.source-notice{min-width:0}
.filters input,.filters select,.advanced summary{
  border-color:color-mix(in srgb,var(--ym-control-border) 72%,#64748b)
}
.table-card :deep(thead){
  background:color-mix(in srgb,var(--ym-table-header-bg) 90%,#0e7490 10%)
}
.table-card :deep(th),
.table-card :deep(td){
  border-bottom-color:color-mix(in srgb,var(--ym-soft-border) 76%,#64748b)
}
:global(body:has(.activity-page) .ym-background-watermark .ym-watermark-logo){opacity:.035}
:global(body:has(.activity-page) .ym-background-watermark .ym-watermark-name){opacity:.024}
:global(html:has(.activity-page)),
:global(body:has(.activity-page)){overflow-x:clip}
:global(.ym-dashboard-light) .activity-page :is(.surface,.activity-hero,.source-tabs,.source-bar,.summary-strip){
  border-color:color-mix(in srgb,var(--ym-control-border) 78%,#64748b);
  background:color-mix(in srgb,var(--ym-card-bg) 94%,rgba(244,249,248,.92));
  backdrop-filter:blur(20px) saturate(108%)
}
:global(.ym-dashboard-light) .summary-strip article{
  border-color:color-mix(in srgb,var(--ym-soft-border) 68%,#94a3b8)
}
:global(.ym-dashboard-light) .summary-strip article span,
:global(.ym-dashboard-light) .summary-strip article small,
:global(.ym-dashboard-light) .source-notice,
:global(.ym-dashboard-light) .table-card header p,
:global(.ym-dashboard-light) .pagination span,
:global(.ym-dashboard-light) .pagination small{
  color:color-mix(in srgb,var(--ym-muted) 68%,#334155)
}
:global(.ym-dashboard-light) .filters input,
:global(.ym-dashboard-light) .filters select,
:global(.ym-dashboard-light) .advanced summary{
  border-color:color-mix(in srgb,var(--ym-control-border) 66%,#64748b);
  background:color-mix(in srgb,var(--ym-control-bg) 94%,#eef6f4)
}
:global(.ym-dashboard-light) .table-card :deep(thead){
  background:color-mix(in srgb,var(--ym-table-header-bg) 78%,#d9ebe8);
  box-shadow:inset 0 -2px color-mix(in srgb,var(--ym-control-border) 68%,#64748b)
}
:global(.ym-dashboard-light) .table-card :deep(th),
:global(.ym-dashboard-light) .table-card :deep(td){
  border-bottom-color:color-mix(in srgb,var(--ym-soft-border) 60%,#94a3b8)
}
:global(.ym-dashboard-light) .table-card :deep(.primary small),
:global(.ym-dashboard-light) .table-card :deep(.is-date small),
:global(.ym-dashboard-light) .table-card :deep(.engagement small){
  color:color-mix(in srgb,var(--ym-muted) 66%,#334155)
}
@media(max-width:700px){
  .activity-hero{padding:12px 14px}
  .hero-total{border:1px solid color-mix(in srgb,#0e7490 30%,var(--ym-card-border));padding:8px 11px}
  .source-bar>div{display:grid;grid-template-columns:auto auto minmax(0,1fr);gap:7px}
  .source-notice{grid-column:1/-1}
}
</style>
