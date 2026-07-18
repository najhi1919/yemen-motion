<template>
  <div class="page space-y-7" :dir="direction">
    <section class="card hero">
      <div>
        <span class="chip">{{ t.readOnly }}</span>
        <p>{{ mode === 'audit' ? t.auditKicker : t.lifecycleKicker }}</p>
        <h1>{{ t.pageTitle }}</h1>
        <p>{{ mode === 'audit' ? t.auditDescription : t.lifecycleDescription }}</p>
      </div>
      <div class="hero-total"><span>{{ t.totalEvents }}</span><strong>{{ number(summaryTotal) }}</strong><small>{{ t.currentFilters }}</small></div>
    </section>

    <nav class="source-switcher" role="tablist" :aria-label="t.source">
      <button type="button" role="tab" :aria-selected="mode === 'audit'" :aria-pressed="mode === 'audit'" :disabled="loading" @click="switchSource('audit')">
        <strong>{{ t.audit }}</strong><small>Operational audit</small>
      </button>
      <button type="button" role="tab" :aria-selected="mode === 'lifecycle'" :aria-pressed="mode === 'lifecycle'" :disabled="loading" @click="switchSource('lifecycle')">
        <strong>{{ t.lifecycle }}</strong><small>Work lifecycle</small>
      </button>
    </nav>

    <section v-if="authPending" class="card state" role="status" aria-live="polite"><span class="spinner" /><h2>{{ t.authPending }}</h2></section>
    <section v-else-if="forbidden" class="card state error-state" role="alert"><b>!</b><h2>{{ t.forbidden }}</h2><p>{{ t.forbiddenHint }}</p></section>

    <template v-else>
      <aside class="source-note" role="note">
        <strong>{{ t.source }}: <code dir="ltr">{{ activitySource?.source ?? (mode === 'audit' ? 'audit_events' : 'work_lifecycle_timestamps') }}</code></strong>
        <p>{{ mode === 'audit' ? t.auditNotice : t.lifecycleNotice }}</p>
      </aside>

      <section v-if="summaryCards.length" class="summary-grid" :aria-label="t.summary">
        <article v-for="card in summaryCards" :key="card.key" :class="`tone-${card.tone}`">
          <span>{{ card.label }}</span><strong>{{ number(card.value) }}</strong><small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="card filters">
        <header><div><h2>{{ t.filters }}</h2><p>{{ t.filtersHint }}</p></div><button class="button secondary" type="button" :disabled="loading" @click="resetFilters">{{ t.reset }}</button></header>

        <form v-if="mode === 'audit'" class="filter-grid" @submit.prevent="applyFilters">
          <label class="search"><span>{{ t.search }}</span><input v-model.trim="auditFilters.q" type="search" minlength="2" maxlength="80" :placeholder="t.auditSearch" /></label>
          <label><span>{{ t.eventGroup }}</span><select v-model="auditFilters.event_group" @change="syncAuditEventType"><option value="">{{ t.all }}</option><option v-for="group in eventCatalog.groups" :key="group.key" :value="group.key">{{ catalogLabel(group) }}</option></select></label>
          <label><span>{{ t.eventType }}</span><select v-model="auditFilters.event_type"><option value="">{{ t.all }}</option><option v-for="event in visibleCatalogEvents" :key="event.event_type" :value="event.event_type">{{ catalogLabel(event) }}</option></select></label>
          <label><span>{{ t.actorId }}</span><input v-model="auditFilters.actor_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ t.targetType }}</span><select v-model="auditFilters.target_type"><option value="">{{ t.all }}</option><option v-for="target in targetTypes" :key="target" :value="target">{{ target }}</option></select></label>
          <label><span>{{ t.targetId }}</span><input v-model="auditFilters.target_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ t.workId }}</span><input v-model="auditFilters.work_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ t.outcome }}</span><input v-model.trim="auditFilters.outcome" type="text" maxlength="50" /></label>
          <label><span>{{ t.from }}</span><input v-model="auditFilters.from" type="date" /></label>
          <label><span>{{ t.to }}</span><input v-model="auditFilters.to" type="date" /></label>
          <label><span>{{ t.sort }}</span><select v-model="auditFilters.sort"><option v-for="option in auditSortOptions" :key="option" :value="option">{{ option }}</option></select></label>
          <label><span>{{ t.direction }}</span><select v-model="auditFilters.direction"><option value="desc">{{ t.desc }}</option><option value="asc">{{ t.asc }}</option></select></label>
          <label><span>{{ t.perPage }}</span><select v-model.number="auditFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <button class="button primary" type="submit" :disabled="loading">{{ t.apply }}</button>
        </form>

        <form v-else class="filter-grid" @submit.prevent="applyFilters">
          <label class="search"><span>{{ t.search }}</span><input v-model.trim="lifecycleFilters.q" type="search" minlength="2" maxlength="80" :placeholder="t.lifecycleSearch" /></label>
          <label><span>{{ t.eventType }}</span><select v-model="lifecycleFilters.event_type"><option value="">{{ t.all }}</option><option v-for="event in lifecycleEvents" :key="event" :value="event">{{ lifecycleEventLabel(event) }}</option></select></label>
          <label><span>{{ t.status }}</span><select v-model="lifecycleFilters.status"><option value="">{{ t.all }}</option><option v-for="status in workStatuses" :key="status" :value="status">{{ statusLabel(status) }}</option></select></label>
          <label><span>{{ t.visibility }}</span><select v-model="lifecycleFilters.visibility_status"><option value="">{{ t.all }}</option><option value="public">{{ t.public }}</option><option value="hidden">{{ t.hidden }}</option></select></label>
          <label><span>{{ t.media }}</span><input v-model.trim="lifecycleFilters.media_type" type="text" maxlength="40" dir="ltr" /></label>
          <label><span>{{ t.designerId }}</span><input v-model="lifecycleFilters.designer_id" type="number" min="1" /></label>
          <label><span>{{ t.reviewerId }}</span><input v-model="lifecycleFilters.reviewer_id" type="number" min="1" /></label>
          <label><span>{{ t.categoryId }}</span><input v-model="lifecycleFilters.category_id" type="number" min="1" /></label>
          <label><span>{{ t.reported }}</span><select v-model="lifecycleFilters.reported"><option value="">{{ t.all }}</option><option value="1">{{ t.yes }}</option><option value="0">{{ t.no }}</option></select></label>
          <label><span>{{ t.promoted }}</span><select v-model="lifecycleFilters.promoted"><option value="">{{ t.all }}</option><option value="1">{{ t.yes }}</option><option value="0">{{ t.no }}</option></select></label>
          <label><span>{{ t.from }}</span><input v-model="lifecycleFilters.from" type="date" /></label>
          <label><span>{{ t.to }}</span><input v-model="lifecycleFilters.to" type="date" /></label>
          <label><span>{{ t.sort }}</span><select v-model="lifecycleFilters.sort"><option v-for="option in lifecycleSortOptions" :key="option" :value="option">{{ option }}</option></select></label>
          <label><span>{{ t.direction }}</span><select v-model="lifecycleFilters.direction"><option value="desc">{{ t.desc }}</option><option value="asc">{{ t.asc }}</option></select></label>
          <label><span>{{ t.perPage }}</span><select v-model.number="lifecycleFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <button class="button primary" type="submit" :disabled="loading">{{ t.apply }}</button>
        </form>
        <p v-if="filterError" class="validation" role="alert">{{ filterError }}</p>
      </section>

      <section class="card table-card" aria-live="polite">
        <header><div><h2>{{ mode === 'audit' ? t.auditEvents : t.lifecycleEvents }}</h2><p>{{ mode === 'audit' ? t.auditTableHint : t.lifecycleTableHint }}</p></div><strong>{{ number(pagination.current_page) }} / {{ number(pagination.last_page) }}</strong></header>
        <div v-if="loading" class="state" role="status"><span class="spinner" /><h3>{{ t.loading }}</h3></div>
        <div v-else-if="error" class="state error-state" role="alert"><b>!</b><h3>{{ t.loadFailed }}</h3><p>{{ error }}</p><button class="button secondary" type="button" @click="fetchActivity">{{ t.retry }}</button></div>
        <div v-else-if="hasLoaded && currentItems.length === 0" class="state" role="status"><b>0</b><h3>{{ t.empty }}</h3></div>

        <WorksActivityAuditTable
          v-else-if="mode === 'audit' && auditItems.length"
          :items="auditItems" :locale="currentLocale" :groups="eventCatalog.groups" :events="eventCatalog.events"
          :sort="appliedAuditFilters.sort" :direction="appliedAuditFilters.direction"
          @sort="changeAuditSort" @details="openAuditDrawer"
        />

        <div v-else-if="mode === 'lifecycle' && lifecycleItems.length" class="lifecycle-wrap">
          <table class="lifecycle-table">
            <thead><tr><th>{{ t.eventType }}</th><th><button type="button" @click="changeLifecycleSort('event_at')">{{ t.time }}</button></th><th><button type="button" @click="changeLifecycleSort('work_id')">{{ t.workId }}</button></th><th><button type="button" @click="changeLifecycleSort('title')">{{ t.workTitle }}</button></th><th>slug</th><th><button type="button" @click="changeLifecycleSort('status')">{{ t.status }}</button></th><th>{{ t.visibility }}</th><th>{{ t.media }}</th><th>{{ t.designer }}</th><th>{{ t.reviewer }}</th><th>{{ t.categoryId }}</th><th><button type="button" @click="changeLifecycleSort('reports_count')">{{ t.reported }}</button></th><th>{{ t.views }}</th><th>{{ t.likes }}</th><th>{{ t.reviewFlag }}</th><th>{{ t.visibilityFlag }}</th><th>{{ t.reportedFlag }}</th><th>{{ t.promotedFlag }}</th><th>{{ t.attention }}</th><th>{{ t.details }}</th></tr></thead>
            <tbody><tr v-for="item in lifecycleItems" :key="item.id">
              <td><strong>{{ item.event_label }}</strong><code dir="ltr">{{ item.event_type }}</code></td>
              <td>{{ dateTime(item.event_at) }}</td><td dir="ltr">#{{ item.work_id }}</td><td>{{ item.title }}</td><td><code dir="ltr">{{ item.slug }}</code></td>
              <td>{{ statusLabel(item.status) }}</td><td>{{ visibilityLabel(item.visibility_status) }}</td><td dir="ltr">{{ item.media_type ?? '—' }}</td>
              <td>{{ person(item.designer) }}</td><td>{{ person(item.reviewer) }}</td><td dir="ltr">{{ item.category_id ?? '—' }}</td>
              <td>{{ number(item.reports_count) }}</td><td>{{ number(item.views_count) }}</td><td>{{ number(item.likes_count) }}</td>
              <td>{{ item.activity_flags.is_review_event ? t.yes : t.no }}</td><td>{{ item.activity_flags.is_visibility_event ? t.yes : t.no }}</td><td>{{ item.activity_flags.is_reported ? t.yes : t.no }}</td><td>{{ item.activity_flags.is_promoted ? t.yes : t.no }}</td><td>{{ item.activity_flags.needs_attention ? t.yes : t.no }}</td>
              <td><button class="details-button" type="button" @click="openLifecycleDrawer(item)">{{ t.viewSummary }}</button></td>
            </tr></tbody>
          </table>
        </div>

        <footer v-if="hasLoaded && !error" class="pagination">
          <div><span>{{ t.totalEvents }}</span><strong>{{ number(pagination.total) }}</strong><small>{{ number(currentItems.length) }} {{ t.visibleNow }}</small></div>
          <nav :aria-label="t.pagination"><button class="button secondary" :disabled="loading || pagination.current_page <= 1" @click="changePage(pagination.current_page - 1)">{{ t.previous }}</button><span>{{ t.page }} {{ number(pagination.current_page) }} / {{ number(pagination.last_page) }}</span><button class="button secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changePage(pagination.current_page + 1)">{{ t.next }}</button></nav>
        </footer>
      </section>
    </template>

    <WorksActivityAuditDrawer :open="auditDrawerOpen" :item="selectedAuditItem" :definition="selectedAuditDefinition" :locale="currentLocale" :can-open-work="canViewWorkDetails" :return-focus="auditReturnFocus" @close="closeAuditDrawer" />

    <Teleport to="body">
      <div
        v-if="lifecycleDrawerOpen && selectedLifecycleItem"
        class="legacy-dialog-root"
        :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
        :dir="direction"
      >
        <button class="legacy-overlay" type="button" :aria-label="t.close" @click="closeLifecycleDrawer" />
        <section class="legacy-drawer" role="dialog" aria-modal="true" aria-labelledby="ym-works-lifecycle-drawer-title">
          <header><div><span>{{ t.lifecycleSummary }}</span><h2 id="ym-works-lifecycle-drawer-title">{{ selectedLifecycleItem.event_label }}</h2></div><button type="button" :aria-label="t.close" @click="closeLifecycleDrawer">×</button></header>
          <dl>
            <div><dt>ID</dt><dd dir="ltr">{{ selectedLifecycleItem.id }}</dd></div><div><dt>{{ t.workId }}</dt><dd dir="ltr">{{ selectedLifecycleItem.work_id }}</dd></div>
            <div><dt>{{ t.eventType }}</dt><dd dir="ltr">{{ selectedLifecycleItem.event_type }}</dd></div><div><dt>{{ t.time }}</dt><dd>{{ dateTime(selectedLifecycleItem.event_at) }}</dd></div>
            <div><dt>{{ t.workTitle }}</dt><dd>{{ selectedLifecycleItem.title }}</dd></div><div><dt>slug</dt><dd dir="ltr">{{ selectedLifecycleItem.slug }}</dd></div>
            <div><dt>{{ t.status }}</dt><dd>{{ statusLabel(selectedLifecycleItem.status) }}</dd></div><div><dt>{{ t.visibility }}</dt><dd>{{ visibilityLabel(selectedLifecycleItem.visibility_status) }}</dd></div>
            <div><dt>{{ t.media }}</dt><dd dir="ltr">{{ selectedLifecycleItem.media_type ?? '—' }}</dd></div><div><dt>{{ t.categoryId }}</dt><dd dir="ltr">{{ selectedLifecycleItem.category_id ?? '—' }}</dd></div>
            <div><dt>{{ t.designer }}</dt><dd>{{ person(selectedLifecycleItem.designer) }}</dd></div><div><dt>{{ t.reviewer }}</dt><dd>{{ person(selectedLifecycleItem.reviewer) }}</dd></div>
            <div><dt>{{ t.reported }}</dt><dd>{{ number(selectedLifecycleItem.reports_count) }}</dd></div><div><dt>{{ t.views }}</dt><dd>{{ number(selectedLifecycleItem.views_count) }}</dd></div>
            <div><dt>{{ t.likes }}</dt><dd>{{ number(selectedLifecycleItem.likes_count) }}</dd></div><div><dt>{{ t.reviewFlag }}</dt><dd>{{ selectedLifecycleItem.activity_flags.is_review_event ? t.yes : t.no }}</dd></div>
            <div><dt>{{ t.visibilityFlag }}</dt><dd>{{ selectedLifecycleItem.activity_flags.is_visibility_event ? t.yes : t.no }}</dd></div><div><dt>{{ t.reportedFlag }}</dt><dd>{{ selectedLifecycleItem.activity_flags.is_reported ? t.yes : t.no }}</dd></div>
            <div><dt>{{ t.promotedFlag }}</dt><dd>{{ selectedLifecycleItem.activity_flags.is_promoted ? t.yes : t.no }}</dd></div><div><dt>{{ t.attention }}</dt><dd>{{ selectedLifecycleItem.activity_flags.needs_attention ? t.yes : t.no }}</dd></div>
          </dl>
        </section>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import WorksActivityAuditDrawer from '~/components/works/activity/WorksActivityAuditDrawer.vue'
import WorksActivityAuditTable from '~/components/works/activity/WorksActivityAuditTable.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })
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
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const direction = computed(() => currentLocale.value === 'ar' ? 'rtl' : 'ltr')
const mode = ref<ActivitySourceMode>('audit')
const lifecycleItems = ref<LifecycleActivityItem[]>([])
const auditItems = ref<AuditActivityItem[]>([])
const lifecycleSummary = ref<LifecycleActivitySummary | null>(null)
const auditSummary = ref<AuditActivitySummary | null>(null)
const activitySource = ref<ActivitySource | null>(null)
const eventCatalog = reactive<EventCatalog>({ groups: [], events: [] })
const pagination = reactive<Pagination>({ current_page: 1, per_page: 15, total: 0, last_page: 1 })
const page = ref(1)
const loading = ref(false)
const hasLoaded = ref(false)
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
let lifecyclePreviousOverflow = ''

const copyMap = {
  ar: { readOnly: 'قراءة تنظيمية فقط', pageTitle: 'سجل الأعمال', auditKicker: 'سجل التدقيق التشغيلي', lifecycleKicker: 'متابعة دورة حياة الأعمال', auditDescription: 'سجل التدقيق الحقيقي لإجراءات المراجعة والظهور والبلاغات والتصنيف والإسناد.', lifecycleDescription: 'عرض تاريخي مشتق من تواريخ دورة حياة العمل للتوافق والتحليل.', totalEvents: 'إجمالي الأحداث', currentFilters: 'ضمن الفلاتر الحالية', audit: 'السجل التشغيلي', lifecycle: 'دورة حياة الأعمال', source: 'مصدر السجل', authPending: 'جارٍ التحقق من صلاحية سجل الأعمال', forbidden: 'الوصول إلى سجل الأعمال غير متاح', forbiddenHint: 'لا يملك هذا الحساب الصلاحيات المطلوبة.', auditNotice: 'يعرض أحداث التدقيق الحقيقية للقراءة فقط؛ وتبقى الإجراءات في أقسامها المختصة.', lifecycleNotice: 'يعرض تواريخ دورة حياة الأعمال التاريخية للتوافق والتحليل.', summary: 'ملخص سجل الأعمال', filters: 'البحث والفلاتر', filtersHint: 'تتغير الفلاتر بحسب مصدر السجل ولا تنتقل بين المصدرين.', reset: 'إعادة ضبط', search: 'البحث', auditSearch: 'نوع الحدث أو الإجراء أو الفاعل أو العمل', lifecycleSearch: 'العنوان أو slug', eventGroup: 'مجموعة الحدث', eventType: 'نوع الحدث', actorId: 'معرّف الفاعل', targetType: 'نوع الهدف', targetId: 'معرّف الهدف', workId: 'معرّف العمل', outcome: 'النتيجة', from: 'من تاريخ', to: 'إلى تاريخ', sort: 'الفرز', direction: 'الاتجاه', perPage: 'لكل صفحة', all: 'الكل', desc: 'تنازلي', asc: 'تصاعدي', apply: 'تطبيق', status: 'الحالة', visibility: 'الظهور', media: 'نوع الوسائط', designerId: 'معرّف المصمم', reviewerId: 'معرّف المراجع', categoryId: 'معرّف التصنيف', reported: 'البلاغات', promoted: 'مروّج', public: 'عام', hidden: 'مخفي', yes: 'نعم', no: 'لا', auditEvents: 'أحداث السجل التشغيلي', lifecycleEvents: 'أحداث دورة حياة الأعمال', auditTableHint: 'تفاصيل آمنة من عقد التدقيق دون بيانات خام.', lifecycleTableHint: 'القائمة التاريخية القديمة محفوظة بعقدها وفلاترها.', loading: 'جارٍ تحميل سجل الأعمال', loadFailed: 'تعذر تحميل سجل الأعمال', retry: 'إعادة المحاولة', empty: 'لا توجد أحداث مطابقة', time: 'وقت الحدث', workTitle: 'العنوان', designer: 'المصمم', reviewer: 'المراجع', views: 'المشاهدات', likes: 'الإعجابات', reviewFlag: 'حدث مراجعة', visibilityFlag: 'حدث ظهور', reportedFlag: 'عليه بلاغات', promotedFlag: 'مروّج', attention: 'يحتاج انتباهًا', details: 'التفاصيل', viewSummary: 'عرض ملخص الحدث', visibleNow: 'عنصر ظاهر الآن', pagination: 'التنقل بين الصفحات', previous: 'السابق', next: 'التالي', page: 'الصفحة', lifecycleSummary: 'ملخص دورة حياة للقراءة فقط', close: 'إغلاق', validation: 'تحقق من قيم الفلاتر والتواريخ.', genericError: 'حدث خطأ أثناء تحميل سجل الأعمال.' },
  en: { readOnly: 'Read-only operational view', pageTitle: 'Works activity', auditKicker: 'Operational audit log', lifecycleKicker: 'Work lifecycle tracking', auditDescription: 'The real audit log for review, visibility, reports, taxonomy, and assignment actions.', lifecycleDescription: 'A historical view derived from work lifecycle timestamps for compatibility and analysis.', totalEvents: 'Total events', currentFilters: 'Within current filters', audit: 'Operational audit', lifecycle: 'Work lifecycle', source: 'Activity source', authPending: 'Checking works activity access', forbidden: 'Works activity is unavailable', forbiddenHint: 'This account lacks the required permissions.', auditNotice: 'Shows real audit events as read-only; actions remain in their dedicated sections.', lifecycleNotice: 'Shows historical work lifecycle timestamps for compatibility and analysis.', summary: 'Works activity summary', filters: 'Search and filters', filtersHint: 'Filters are source-specific and never cross between modes.', reset: 'Reset', search: 'Search', auditSearch: 'Event type, action, actor, or work', lifecycleSearch: 'Title or slug', eventGroup: 'Event group', eventType: 'Event type', actorId: 'Actor ID', targetType: 'Target type', targetId: 'Target ID', workId: 'Work ID', outcome: 'Outcome', from: 'From', to: 'To', sort: 'Sort', direction: 'Direction', perPage: 'Per page', all: 'All', desc: 'Descending', asc: 'Ascending', apply: 'Apply', status: 'Status', visibility: 'Visibility', media: 'Media type', designerId: 'Designer ID', reviewerId: 'Reviewer ID', categoryId: 'Category ID', reported: 'Reports', promoted: 'Promoted', public: 'Public', hidden: 'Hidden', yes: 'Yes', no: 'No', auditEvents: 'Operational audit events', lifecycleEvents: 'Work lifecycle events', auditTableHint: 'Safe details from the audit contract without raw data.', lifecycleTableHint: 'The legacy list remains available with its contract and filters.', loading: 'Loading works activity', loadFailed: 'Could not load works activity', retry: 'Retry', empty: 'No matching events', time: 'Event time', workTitle: 'Title', designer: 'Designer', reviewer: 'Reviewer', views: 'Views', likes: 'Likes', reviewFlag: 'Review event', visibilityFlag: 'Visibility event', reportedFlag: 'Reported', promotedFlag: 'Promoted', attention: 'Needs attention', details: 'Details', viewSummary: 'View event summary', visibleNow: 'items currently visible', pagination: 'Activity pagination', previous: 'Previous', next: 'Next', page: 'Page', lifecycleSummary: 'Read-only lifecycle summary', close: 'Close', validation: 'Check filter values and dates.', genericError: 'An error occurred while loading works activity.' }
} as const
const t = computed(() => copyMap[currentLocale.value])

const authPending = computed(() => !authStore.isInitialized)
const hasAccess = computed(() => authStore.isInitialized && authStore.isAuthenticated && (authStore.role === 'super-admin' || (['admin', 'staff'].includes(authStore.role || '') && ['admin.works.access', 'admin.works.activity.view', 'admin.works.activity.list'].every(permission => authStore.permissions.includes(permission)))))
const forbidden = computed(() => authStore.isInitialized && (!hasAccess.value || serverForbidden.value))
const canViewWorkDetails = computed(() => hasAccess.value && (authStore.role === 'super-admin' || authStore.permissions.includes('admin.works.detail.view')))
const currentItems = computed(() => mode.value === 'audit' ? auditItems.value : lifecycleItems.value)
const summaryTotal = computed(() => mode.value === 'audit' ? auditSummary.value?.total_events ?? 0 : lifecycleSummary.value?.total_events ?? 0)
const authorizationSignature = computed(() => [authStore.isInitialized, authStore.isAuthenticated, authStore.role, [...authStore.permissions].sort().join(',')].join('|'))

const lifecycleEvents: LifecycleEventType[] = ['created', 'updated', 'submitted', 'reviewed', 'approved', 'published', 'rejected', 'hidden', 'archived']
const workStatuses: WorkStatus[] = ['draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'published', 'rejected', 'hidden', 'archived']
const targetTypes = ['work', 'work_report', 'work_category', 'work_tag']
const auditSortOptions: AuditSortKey[] = ['event_at', 'audit_event_id', 'event_type', 'actor_name', 'work_id', 'work_title']
const lifecycleSortOptions: LifecycleSortKey[] = ['event_at', 'work_id', 'title', 'status', 'reports_count']
const defaultAuditFilters = (): AuditFilters => ({ q: '', event_type: '', event_group: '', actor_id: '', target_type: '', target_id: '', work_id: '', outcome: '', from: '', to: '', sort: 'event_at', direction: 'desc', per_page: 15 })
const defaultLifecycleFilters = (): LifecycleFilters => ({ q: '', event_type: '', status: '', visibility_status: '', media_type: '', designer_id: '', reviewer_id: '', category_id: '', reported: '', promoted: '', from: '', to: '', sort: 'event_at', direction: 'desc', per_page: 15 })
const auditFilters = reactive<AuditFilters>(defaultAuditFilters())
const appliedAuditFilters = reactive<AuditFilters>(defaultAuditFilters())
const lifecycleFilters = reactive<LifecycleFilters>(defaultLifecycleFilters())
const appliedLifecycleFilters = reactive<LifecycleFilters>(defaultLifecycleFilters())
const visibleCatalogEvents = computed(() => eventCatalog.events.filter(event => !auditFilters.event_group || event.event_group === auditFilters.event_group))
const selectedAuditDefinition = computed(() => selectedAuditItem.value ? eventCatalog.events.find(event => event.event_type === selectedAuditItem.value?.event_type) ?? null : null)
const summaryCards = computed(() => {
  if (mode.value === 'audit' && auditSummary.value) {
    const s = auditSummary.value
    return [
      ['total', t.value.totalEvents, s.total_events, t.value.currentFilters, 'brand'], ['works', currentLocale.value === 'ar' ? 'الأعمال الفريدة' : 'Unique works', s.unique_works, '', 'info'],
      ['review', currentLocale.value === 'ar' ? 'أحداث المراجعة' : 'Review events', s.review_events, '', 'review'], ['visibility', currentLocale.value === 'ar' ? 'أحداث الظهور' : 'Visibility events', s.visibility_events, '', 'visibility'],
      ['reports', currentLocale.value === 'ar' ? 'أحداث البلاغات' : 'Report events', s.report_events, '', 'alert'], ['taxonomy', currentLocale.value === 'ar' ? 'أحداث التصنيف' : 'Taxonomy events', s.taxonomy_events, '', 'taxonomy'],
      ['assignment', currentLocale.value === 'ar' ? 'أحداث الإسناد' : 'Assignment events', s.taxonomy_assignment_events, '', 'assignment'], ['attention', t.value.attention, s.attention_events, '', 'alert']
    ].map(([key, label, value, hint, tone]) => ({ key: String(key), label: String(label), value: Number(value), hint: String(hint), tone: String(tone) }))
  }
  if (!lifecycleSummary.value) return []
  const s = lifecycleSummary.value
  return [
    ['total', t.value.totalEvents, s.total_events], ['works', currentLocale.value === 'ar' ? 'الأعمال الفريدة' : 'Unique works', s.unique_works],
    ['created', lifecycleEventLabel('created'), s.created_events], ['updated', lifecycleEventLabel('updated'), s.updated_events], ['submitted', lifecycleEventLabel('submitted'), s.submitted_events],
    ['reviewed', lifecycleEventLabel('reviewed'), s.reviewed_events], ['approved', lifecycleEventLabel('approved'), s.approved_events], ['published', lifecycleEventLabel('published'), s.published_events],
    ['rejected', lifecycleEventLabel('rejected'), s.rejected_events], ['hidden', lifecycleEventLabel('hidden'), s.hidden_events], ['archived', lifecycleEventLabel('archived'), s.archived_events],
    ['review', currentLocale.value === 'ar' ? 'مسار المراجعة' : 'Review lifecycle', s.review_events], ['visibility', currentLocale.value === 'ar' ? 'تغييرات الظهور' : 'Visibility lifecycle', s.visibility_events],
    ['reported', currentLocale.value === 'ar' ? 'مرتبطة ببلاغات' : 'Reported works', s.reported_events], ['promoted', currentLocale.value === 'ar' ? 'مرتبطة بالترويج' : 'Promoted works', s.promoted_events]
  ].map(([key, label, value]) => ({ key: String(key), label: String(label), value: Number(value), hint: '', tone: 'lifecycle' }))
})

function catalogLabel(item: EventCatalogGroup | EventCatalogEvent): string { return currentLocale.value === 'ar' ? item.label_ar : item.label_en }
function lifecycleEventLabel(value: LifecycleEventType): string {
  const ar = { created: 'إنشاء', updated: 'تحديث', submitted: 'إرسال', reviewed: 'مراجعة', approved: 'اعتماد', published: 'نشر', rejected: 'رفض', hidden: 'إخفاء', archived: 'أرشفة' }
  return currentLocale.value === 'ar' ? ar[value] : value.replaceAll('_', ' ')
}
function statusLabel(value: WorkStatus): string {
  const ar = { draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' }
  return currentLocale.value === 'ar' ? ar[value] : value.replaceAll('_', ' ')
}
function visibilityLabel(value: 'public' | 'hidden'): string { return value === 'public' ? t.value.public : t.value.hidden }
function number(value: number): string { return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(value) }
function dateTime(value: string): string { const date = new Date(value); return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date) }
function person(value: UserReference | null): string { return value ? `${value.name} #${value.id}` : '—' }
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
  for (const messages of Object.values(errors as Record<string, unknown>)) if (Array.isArray(messages) && typeof messages[0] === 'string') return messages[0]
  return null
}
function validFilters(): boolean {
  filterError.value = null
  const f = mode.value === 'audit' ? auditFilters : lifecycleFilters
  if (f.q.trim().length === 1 || (f.from && f.to && f.to < f.from)) { filterError.value = t.value.validation; return false }
  const ids = mode.value === 'audit' ? [auditFilters.actor_id, auditFilters.target_id, auditFilters.work_id] : [lifecycleFilters.designer_id, lifecycleFilters.reviewer_id, lifecycleFilters.category_id]
  if (ids.some(value => value && (!Number.isInteger(Number(value)) || Number(value) < 1))) { filterError.value = t.value.validation; return false }
  return true
}
function query(): Record<string, string | number> {
  const sourceFilters = mode.value === 'audit' ? appliedAuditFilters : appliedLifecycleFilters
  const result: Record<string, string | number> = { source: mode.value, page: page.value, per_page: sourceFilters.per_page, sort: sourceFilters.sort, direction: sourceFilters.direction }
  for (const [key, value] of Object.entries(sourceFilters)) if (!['per_page', 'sort', 'direction'].includes(key) && String(value).trim() !== '') result[key] = value
  return result
}
async function fetchActivity(): Promise<void> {
  if (!hasAccess.value) return
  const source = mode.value
  const requestAccess = accessRevision
  const revision = ++requestRevision
  loading.value = true; error.value = null; filterError.value = null
  try {
    if (source === 'audit') {
      const response = await apiFetch<ApiResponse<AuditActivityItem, AuditActivitySummary>>('/admin/works/activity', { query: query() })
      if (!isCurrent(source, requestAccess, revision)) return
      if (!response.success || !response.data) throw new Error('invalid-response')
      auditItems.value = response.data.items; auditSummary.value = response.data.summary
      Object.assign(eventCatalog, response.data.event_catalog ?? { groups: [], events: [] })
      acceptData(response.data.pagination, response.data.activity_source)
    } else {
      const response = await apiFetch<ApiResponse<LifecycleActivityItem, LifecycleActivitySummary>>('/admin/works/activity', { query: query() })
      if (!isCurrent(source, requestAccess, revision)) return
      if (!response.success || !response.data) throw new Error('invalid-response')
      lifecycleItems.value = response.data.items; lifecycleSummary.value = response.data.summary
      acceptData(response.data.pagination, response.data.activity_source)
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
function acceptData(next: Pagination, source: ActivitySource): void { Object.assign(pagination, next); page.value = next.current_page; activitySource.value = source; hasLoaded.value = true; serverForbidden.value = false }
function clearData(): void {
  auditItems.value = []; lifecycleItems.value = []; auditSummary.value = null; lifecycleSummary.value = null; activitySource.value = null
  Object.assign(eventCatalog, { groups: [], events: [] }); Object.assign(pagination, { current_page: 1, per_page: 15, total: 0, last_page: 1 }); page.value = 1; hasLoaded.value = false
  closeAuditDrawer(); closeLifecycleDrawer()
}
function applyFilters(): void {
  if (!validFilters()) return
  if (mode.value === 'audit') Object.assign(appliedAuditFilters, auditFilters); else Object.assign(appliedLifecycleFilters, lifecycleFilters)
  page.value = 1; closeDrawers(); void fetchActivity()
}
function resetFilters(): void {
  if (mode.value === 'audit') { Object.assign(auditFilters, defaultAuditFilters()); Object.assign(appliedAuditFilters, defaultAuditFilters()) }
  else { Object.assign(lifecycleFilters, defaultLifecycleFilters()); Object.assign(appliedLifecycleFilters, defaultLifecycleFilters()) }
  page.value = 1; filterError.value = null; closeDrawers(); void fetchActivity()
}
function switchSource(next: ActivitySourceMode): void {
  if (next === mode.value || loading.value) return
  requestRevision += 1; closeDrawers(); mode.value = next; page.value = 1; error.value = null; filterError.value = null; activitySource.value = null; hasLoaded.value = false
  if (next === 'audit') { Object.assign(lifecycleFilters, defaultLifecycleFilters()); Object.assign(appliedLifecycleFilters, defaultLifecycleFilters()); auditItems.value = []; auditSummary.value = null }
  else { Object.assign(auditFilters, defaultAuditFilters()); Object.assign(appliedAuditFilters, defaultAuditFilters()); Object.assign(eventCatalog, { groups: [], events: [] }); lifecycleItems.value = []; lifecycleSummary.value = null }
  void fetchActivity()
}
function syncAuditEventType(): void { if (auditFilters.event_type && !visibleCatalogEvents.value.some(event => event.event_type === auditFilters.event_type)) auditFilters.event_type = '' }
function changeAuditSort(key: AuditSortKey): void {
  if (appliedAuditFilters.sort === key) appliedAuditFilters.direction = appliedAuditFilters.direction === 'asc' ? 'desc' : 'asc'
  else { appliedAuditFilters.sort = key; appliedAuditFilters.direction = key === 'event_at' ? 'desc' : 'asc' }
  auditFilters.sort = appliedAuditFilters.sort; auditFilters.direction = appliedAuditFilters.direction; page.value = 1; closeAuditDrawer(); void fetchActivity()
}
function changeLifecycleSort(key: LifecycleSortKey): void {
  if (appliedLifecycleFilters.sort === key) appliedLifecycleFilters.direction = appliedLifecycleFilters.direction === 'asc' ? 'desc' : 'asc'
  else { appliedLifecycleFilters.sort = key; appliedLifecycleFilters.direction = ['work_id', 'title', 'status'].includes(key) ? 'asc' : 'desc' }
  lifecycleFilters.sort = appliedLifecycleFilters.sort; lifecycleFilters.direction = appliedLifecycleFilters.direction; page.value = 1; closeLifecycleDrawer(); void fetchActivity()
}
function changePage(next: number): void { if (loading.value || next < 1 || next > pagination.last_page || next === pagination.current_page) return; page.value = next; closeDrawers(); void fetchActivity() }
function openAuditDrawer(item: AuditActivityItem, trigger: HTMLElement | null): void { selectedAuditItem.value = item; auditReturnFocus.value = trigger; auditDrawerOpen.value = true }
function closeAuditDrawer(): void { auditDrawerOpen.value = false; selectedAuditItem.value = null }
function openLifecycleDrawer(item: LifecycleActivityItem): void { selectedLifecycleItem.value = item; lifecycleDrawerOpen.value = true }
function closeLifecycleDrawer(): void { lifecycleDrawerOpen.value = false; selectedLifecycleItem.value = null }
function closeDrawers(): void { closeAuditDrawer(); closeLifecycleDrawer() }
function onLifecycleKeydown(event: KeyboardEvent): void { if (event.key === 'Escape') closeLifecycleDrawer() }
function syncAccess(): void {
  if (!mounted) return
  accessRevision += 1; requestRevision += 1; serverForbidden.value = false; closeDrawers()
  if (!hasAccess.value) { loadedAuthorizationSignature = null; clearData(); loading.value = false; return }
  if (loadedAuthorizationSignature === authorizationSignature.value) return
  loadedAuthorizationSignature = authorizationSignature.value; void fetchActivity()
}
watch(authorizationSignature, syncAccess, { flush: 'post' })
watch(lifecycleDrawerOpen, (open) => {
  if (open) {
    lifecyclePreviousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onLifecycleKeydown)
  } else {
    document.body.style.overflow = lifecyclePreviousOverflow
    document.removeEventListener('keydown', onLifecycleKeydown)
  }
})
onMounted(() => { mounted = true; syncAccess() })
onBeforeUnmount(() => {
  document.body.style.overflow = lifecyclePreviousOverflow
  document.removeEventListener('keydown', onLifecycleKeydown)
})
</script>

<style scoped>
.page { color: var(--ym-text); }
.card, .source-switcher, .source-note, .summary-grid article { border: 1px solid var(--ym-card-border); border-radius: 24px; background: var(--ym-card-bg); box-shadow: var(--ym-card-shadow); }
.hero { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: clamp(1.25rem, 3vw, 2rem); overflow: hidden; }
.hero h1 { margin: .2rem 0; font-size: clamp(1.75rem, 4vw, 2.5rem); }
.hero p { color: var(--ym-muted); max-width: 760px; }
.chip { display: inline-flex; padding: .4rem .65rem; border: 1px solid var(--ym-soft-border); border-radius: 999px; color: #a78bfa; font-size: 12px; font-weight: 900; }
.hero-total { min-width: 170px; padding: 1rem; border-radius: 18px; background: var(--ym-control-bg); text-align: center; }
.hero-total span, .hero-total small { display: block; color: var(--ym-muted); }.hero-total strong { display: block; font-size: 2rem; }
.source-switcher { display: grid; grid-template-columns: repeat(2, 1fr); gap: .5rem; padding: .5rem; }
.source-switcher button { display: grid; gap: .2rem; padding: .8rem; border: 1px solid transparent; border-radius: 16px; background: transparent; color: var(--ym-muted); cursor: pointer; }
.source-switcher button[aria-selected="true"] { border-color: rgba(139, 92, 246, .45); background: var(--ym-control-bg); color: var(--ym-text); }
.source-switcher small { direction: ltr; }.source-note { padding: 1rem 1.2rem; }.source-note p { margin: .35rem 0 0; color: var(--ym-muted); }
.summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .8rem; }
.summary-grid article { padding: 1rem; }.summary-grid span, .summary-grid small { display: block; color: var(--ym-muted); }.summary-grid strong { display: block; margin: .25rem 0; font-size: 1.6rem; }
.summary-grid .tone-alert { border-color: rgba(245, 158, 11, .35); }.summary-grid .tone-review { border-color: rgba(139, 92, 246, .35); }.summary-grid .tone-visibility { border-color: rgba(45, 212, 191, .35); }
.filters, .table-card { overflow: hidden; }.filters > header, .table-card > header { display: flex; justify-content: space-between; gap: 1rem; padding: 1.2rem; border-bottom: 1px solid var(--ym-soft-border); }
h2, h3, p { margin-block-start: 0; }.filters header p, .table-card header p { margin: .25rem 0 0; color: var(--ym-muted); }
.filter-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .85rem; padding: 1.2rem; }.filter-grid .search { grid-column: span 2; }
label { display: grid; align-content: start; gap: .35rem; color: var(--ym-muted); font-size: 12px; font-weight: 900; }
input, select { min-width: 0; height: 42px; padding: 0 .7rem; border: 1px solid var(--ym-soft-border); border-radius: 11px; background: var(--ym-control-bg); color: var(--ym-text); }
.button, .details-button { border: 1px solid var(--ym-soft-border); border-radius: 11px; padding: .6rem .8rem; background: var(--ym-control-bg); color: var(--ym-text); cursor: pointer; font-weight: 900; }.button.primary { background: #8b5cf6; color: white; }.button:disabled { opacity: .55; cursor: not-allowed; }
.validation { margin: 0 1.2rem 1.2rem; color: #fb7185; font-weight: 800; }.state { display: grid; justify-items: center; gap: .5rem; padding: 3rem 1rem; text-align: center; }.error-state { color: #fb7185; }
.spinner { width: 28px; height: 28px; border: 3px solid var(--ym-soft-border); border-top-color: #8b5cf6; border-radius: 50%; animation: spin .8s linear infinite; }
.lifecycle-wrap { overflow-x: auto; }.lifecycle-table { width: 100%; min-width: 2200px; border-collapse: collapse; }.lifecycle-table th, .lifecycle-table td { padding: .75rem; border-bottom: 1px solid var(--ym-soft-border); text-align: start; vertical-align: top; }.lifecycle-table th { background: var(--ym-table-header-bg); color: var(--ym-muted); font-size: 12px; }.lifecycle-table th button { border: 0; background: transparent; color: inherit; font: inherit; cursor: pointer; }.lifecycle-table td code, .lifecycle-table td strong { display: block; }
.pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.2rem; }.pagination span, .pagination small { color: var(--ym-muted); }.pagination strong, .pagination small { display: block; }.pagination nav { display: flex; align-items: center; gap: .6rem; }
.legacy-dialog-root { position: fixed; inset: 0; z-index: 12000; isolation: isolate; display: flex; justify-content: flex-end; }
.legacy-dialog-root.is-dark { --ym-text: #f0f6ff; --ym-muted: rgba(226,232,240,.92); --ym-control-bg: rgba(15,23,42,.92); --ym-card-border: rgba(148,163,184,.28); --ym-soft-border: rgba(148,163,184,.18); --ym-dropdown-bg: #0f172a; color-scheme: dark; }
.legacy-dialog-root.is-light { --ym-text: #171126; --ym-muted: rgba(45,36,64,.9); --ym-control-bg: rgba(250,247,255,.98); --ym-card-border: rgba(109,40,217,.34); --ym-soft-border: rgba(91,33,182,.24); --ym-dropdown-bg: #fff; color-scheme: light; }
.legacy-overlay { position: absolute; inset: 0; z-index: 0; border: 0; padding: 0; background: rgba(2,6,23,.72); cursor: default; }
.legacy-drawer { position: relative; z-index: 1; width: min(620px, 94vw); height: 100%; overflow-y: auto; padding: 1.2rem; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-dropdown-bg); color: var(--ym-text); box-shadow: -24px 0 70px rgba(2,6,23,.42); }
.legacy-drawer header { position: sticky; top: -1.2rem; z-index: 2; display: flex; justify-content: space-between; gap: 1rem; margin: -1.2rem -1.2rem 1.2rem; padding: 1.2rem; border-bottom: 1px solid var(--ym-soft-border); background: var(--ym-dropdown-bg); color: var(--ym-text); }
.legacy-drawer header button { width: 40px; height: 40px; border: 1px solid var(--ym-soft-border); border-radius: 10px; background: var(--ym-control-bg); color: var(--ym-text); }
.legacy-drawer dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; }
.legacy-drawer dl div { padding: .8rem; border: 1px solid var(--ym-soft-border); border-radius: 12px; background: var(--ym-control-bg); color: var(--ym-text); }
.legacy-drawer dt { color: var(--ym-muted); }.legacy-drawer dd { margin: .2rem 0 0; overflow-wrap: anywhere; font-weight: 850; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 1050px) { .summary-grid, .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .hero, .pagination { align-items: stretch; flex-direction: column; }.source-switcher { grid-template-columns: 1fr; }.summary-grid, .filter-grid { grid-template-columns: 1fr; }.filter-grid .search { grid-column: auto; }.legacy-drawer dl { grid-template-columns: 1fr; } }
</style>
