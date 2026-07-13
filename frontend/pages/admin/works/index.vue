<template>
  <div class="ym-works-page space-y-7">
    <section class="ym-works-hero">
      <div class="ym-works-hero__glow is-one" />
      <div class="ym-works-hero__glow is-two" />
      <div class="ym-works-hero__grid" aria-hidden="true" />

      <div class="ym-works-hero__content">
        <div>
          <div class="ym-works-chips">
            <span class="ym-works-chip is-brand">Yemen Motion</span>
            <span class="ym-works-chip is-live">{{ copy.liveData }}</span>
          </div>
          <p class="ym-works-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-works-description">{{ copy.description }}</p>
        </div>

        <div class="ym-works-hero__summary">
          <span>{{ copy.totalWorks }}</span>
          <strong>{{ formatNumber(overview?.summary.total ?? 0) }}</strong>
          <small>{{ copy.operationalOverview }}</small>
        </div>
      </div>
    </section>

    <section v-if="authPending" class="ym-works-access-state" role="status" aria-live="polite">
      <span class="ym-works-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-access-state" role="status">
      <span class="ym-works-access-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-works-notice" role="note">
        <span>{{ copy.overviewOnly }}</span>
        <p>{{ copy.notice }}</p>
      </aside>

      <section class="ym-works-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-works-button is-secondary"
            :disabled="loading"
            :title="copy.resetHint"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </header>

        <div class="ym-works-period-control">
          <span>{{ copy.period }}</span>
          <div class="ym-works-period-options" role="group" :aria-label="copy.period">
            <button
              v-for="option in periodOptions"
              :key="option.value"
              type="button"
              :class="filters.period === option.value ? 'is-active' : ''"
              :aria-pressed="filters.period === option.value"
              :title="option.hint"
              :disabled="loading"
              @click="selectPeriod(option.value)"
            >
              {{ option.label }}
            </button>
          </div>
        </div>

        <form class="ym-works-date-filter" @submit.prevent="applyFilters">
          <label :title="copy.fromHint">
            <span>{{ copy.from }}</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label :title="copy.toHint">
            <span>{{ copy.to }}</span>
            <input v-model="filters.to" type="date" />
          </label>

          <div class="ym-works-filter-actions">
            <button type="submit" class="ym-works-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>

        <div class="ym-works-filter-foot">
          <p>{{ copy.searchLater }}</p>
          <span v-if="overview">
            {{ copy.activeRange }}
            <code dir="ltr">{{ overview.filters.from }} — {{ overview.filters.to }}</code>
          </span>
        </div>

        <p v-if="filterError" class="ym-works-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section v-if="loading" class="ym-works-result-card ym-works-state" role="status" aria-live="polite">
        <span class="ym-works-spinner" aria-hidden="true" />
        <h2>{{ copy.loadingTitle }}</h2>
        <p>{{ copy.loadingCopy }}</p>
      </section>

      <section v-else-if="error" class="ym-works-result-card ym-works-state is-error" role="alert">
        <span class="ym-works-state__icon" aria-hidden="true">!</span>
        <h2>{{ copy.errorTitle }}</h2>
        <p>{{ error }}</p>
        <button type="button" class="ym-works-button is-secondary" @click="fetchWorksOverview">
          {{ copy.retry }}
        </button>
      </section>

      <template v-else-if="overview">
        <section class="ym-works-metrics-grid" :aria-label="copy.metricsTitle">
          <article
            v-for="card in summaryCards"
            :key="card.key"
            class="ym-works-metric-card"
            :class="card.tone"
            :style="{ '--works-accent': card.color }"
          >
            <span class="ym-works-metric-card__label">
              <i aria-hidden="true" />
              {{ card.label }}
            </span>
            <strong>{{ formatNumber(card.value) }}</strong>
            <small>{{ card.hint }}</small>
            <code v-if="card.code" dir="ltr">{{ card.code }}</code>
          </article>
        </section>

        <aside v-if="isEmpty" class="ym-works-empty" role="status">
          <span aria-hidden="true">0</span>
          <div>
            <h2>{{ copy.emptyTitle }}</h2>
            <p>{{ copy.emptyCopy }}</p>
          </div>
        </aside>

        <section class="ym-works-operational-grid">
          <article class="ym-works-data-card">
            <header>
              <div>
                <h2>{{ copy.visibilityTitle }}</h2>
                <p>{{ copy.visibilityCopy }}</p>
              </div>
              <span>{{ formatNumber(visibilityTotal) }}</span>
            </header>

            <div class="ym-works-visibility-track" aria-hidden="true">
              <i class="is-public" :style="{ width: visibilityWidth(overview.visibility.public) }" />
              <i class="is-hidden" :style="{ width: visibilityWidth(overview.visibility.hidden) }" />
            </div>

            <div class="ym-works-visibility-list">
              <article>
                <span><i class="is-public" />{{ copy.public }}</span>
                <strong>{{ formatNumber(overview.visibility.public) }}</strong>
                <small>{{ visibilityRate(overview.visibility.public) }}</small>
              </article>
              <article>
                <span><i class="is-hidden" />{{ copy.hiddenVisibility }}</span>
                <strong>{{ formatNumber(overview.visibility.hidden) }}</strong>
                <small>{{ visibilityRate(overview.visibility.hidden) }}</small>
              </article>
            </div>
          </article>

          <article class="ym-works-data-card">
            <header>
              <div>
                <h2>{{ copy.reviewQueueTitle }}</h2>
                <p>{{ copy.reviewQueueCopy }}</p>
              </div>
              <span>{{ formatNumber(reviewQueueTotal) }}</span>
            </header>

            <div class="ym-works-review-grid">
              <article
                v-for="item in reviewQueueItems"
                :key="item.key"
                :class="item.tone"
                :style="{ '--queue-accent': item.color }"
              >
                <span>{{ item.label }}</span>
                <strong>{{ formatNumber(item.value) }}</strong>
                <small>{{ item.hint }}</small>
              </article>
            </div>
          </article>
        </section>

        <WorksSvgSeriesChart
          :title="copy.seriesTitle"
          :subtitle="copy.seriesCopy"
          :series="overview.series"
          :labels="chartLabels"
          :locale="currentLocale"
        />

        <section class="ym-works-counters-card">
          <header>
            <div>
              <h2>{{ copy.countersTitle }}</h2>
              <p>{{ copy.countersCopy }}</p>
            </div>
            <span>{{ copy.allWorks }}</span>
          </header>

          <div class="ym-works-counters-grid">
            <article
              v-for="counter in topCounters"
              :key="counter.key"
              :style="{ '--counter-accent': counter.color }"
            >
              <span class="ym-works-counter-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="counter.icon" />
                </svg>
              </span>
              <div>
                <span>{{ counter.label }}</span>
                <strong>{{ formatNumber(counter.value) }}</strong>
                <small>{{ counter.hint }}</small>
              </div>
            </article>
          </div>
        </section>

        <footer class="ym-works-generated">
          <span>{{ copy.generatedAt }}</span>
          <time :datetime="overview.generated_at">{{ formatDateTime(overview.generated_at) }}</time>
        </footer>
      </template>

      <section v-else class="ym-works-result-card ym-works-state" role="status">
        <span class="ym-works-spinner" aria-hidden="true" />
        <p>{{ copy.loadingCopy }}</p>
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type WorksPeriod = 'day' | 'week' | 'month' | 'year'

interface WorksOverviewSummary {
  total: number
  submitted: number
  in_review: number
  changes_requested: number
  approved: number
  published: number
  rejected: number
  hidden: number
  archived: number
  featured: number
  pinned: number
  reported: number
}

interface WorksSeriesPoint {
  label: string
  submitted: number
  published: number
  rejected: number
}

interface WorksOverviewData {
  summary: WorksOverviewSummary
  visibility: {
    public: number
    hidden: number
  }
  review_queue: {
    pending: number
    in_review: number
    changes_requested: number
    overdue: number
  }
  series: WorksSeriesPoint[]
  top_counters: {
    views: number
    likes: number
    reports: number
  }
  filters: {
    period: WorksPeriod
    from: string
    to: string
  }
  generated_at: string
}

interface WorksOverviewResponse {
  success: boolean
  data: WorksOverviewData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface WorksOverviewFilters {
  period: WorksPeriod
  from: string
  to: string
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    liveData: 'بيانات حقيقية',
    kicker: 'إدارة المحتوى الإبداعي',
    title: 'إدارة الأعمال / Works',
    description: 'نظرة تشغيلية واضحة على دورة الأعمال والمراجعة والظهور والتفاعل، مبنية على البيانات المسجلة فعليًا.',
    totalWorks: 'إجمالي الأعمال',
    operationalOverview: 'مؤشرات تجميعية آمنة',
    authLoadingTitle: 'جارٍ التحقق من صلاحية إدارة الأعمال',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل تحميل النظرة العامة.',
    forbiddenTitle: 'الوصول إلى إدارة الأعمال غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب صلاحيات النظرة العامة للأعمال. لم تتم محاولة تحميل البيانات.',
    overviewOnly: 'نظرة عامة',
    notice: 'تعرض الصفحة مؤشرات تجميعية فقط. البحث التفصيلي والإجراءات الفردية ستكون ضمن قسم كل الأعمال.',
    filtersTitle: 'نطاق النظرة العامة',
    filtersCopy: 'اختر التجميع الزمني أو حدّد مدى مخصصًا لتحديث حركة الإرسال والنشر والرفض.',
    period: 'الفترة',
    day: 'اليوم',
    week: 'الأسبوع',
    month: 'الشهر',
    year: 'السنة',
    dayHint: 'عرض حركة اليوم الحالي',
    weekHint: 'عرض حركة الأسبوع الحالي',
    monthHint: 'عرض حركة الشهر الحالي',
    yearHint: 'عرض حركة السنة الحالية',
    from: 'من تاريخ',
    to: 'إلى تاريخ',
    fromHint: 'بداية المدى المخصص',
    toHint: 'نهاية المدى المخصص',
    apply: 'تطبيق المدى',
    reset: 'إعادة الضبط',
    resetHint: 'العودة إلى الشهر الحالي ومسح التاريخين',
    activeRange: 'النطاق المعروض:',
    searchLater: 'سيُخصص البحث الكامل للأعمال عند بناء صفحة «كل الأعمال».',
    incompleteDateRange: 'حدّد تاريخ البداية والنهاية معًا لتطبيق مدى مخصص.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من الفترة والتواريخ وحد المدى المسموح.',
    loadingTitle: 'جارٍ تجهيز مؤشرات الأعمال',
    loadingCopy: 'يتم تحميل التجميعات الآمنة من نظام الأعمال...',
    errorTitle: 'تعذر تحميل النظرة العامة',
    genericError: 'حدث خطأ أثناء تحميل مؤشرات الأعمال. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    metricsTitle: 'مؤشرات حالة الأعمال',
    total: 'إجمالي الأعمال',
    totalHint: 'كل الأعمال المسجلة',
    submitted: 'قيد المراجعة',
    submittedHint: 'بانتظار بدء المراجعة',
    inReview: 'تحت المراجعة',
    inReviewHint: 'تتم مراجعتها حاليًا',
    changesRequested: 'طلبات التعديل',
    changesRequestedHint: 'بانتظار تعديلات المصمم',
    approved: 'معتمدة',
    approvedHint: 'اجتازت قرار الاعتماد',
    published: 'منشورة',
    publishedHint: 'ظاهرة ضمن المحتوى العام',
    rejected: 'مرفوضة',
    rejectedHint: 'انتهت بقرار رفض',
    hiddenStatus: 'مخفية',
    hiddenStatusHint: 'مخفية بإجراء إداري',
    archived: 'مؤرشفة',
    archivedHint: 'محفوظة خارج الدورة النشطة',
    featured: 'مميزة',
    featuredHint: 'تحمل علامة التمييز',
    pinned: 'مثبتة',
    pinnedHint: 'مثبتة في ترتيب الظهور',
    reported: 'عليها بلاغات',
    reportedHint: 'تملك بلاغًا واحدًا على الأقل',
    overdue: 'متأخرة عن المراجعة',
    overdueHint: 'تجاوزت مهلة 48 ساعة',
    emptyTitle: 'لا توجد أعمال مسجلة بعد',
    emptyCopy: 'ستظهر مؤشرات دورة الأعمال هنا تلقائيًا عند توفر بيانات حقيقية.',
    visibilityTitle: 'الظهور العام',
    visibilityCopy: 'توزيع الأعمال بحسب حالة الظهور الحالية.',
    public: 'عام',
    hiddenVisibility: 'مخفي',
    reviewQueueTitle: 'طابور المراجعة',
    reviewQueueCopy: 'الحالات التي تحتاج متابعة فريق المحتوى.',
    pending: 'بانتظار المراجعة',
    pendingHint: 'أعمال مرسلة لم تبدأ مراجعتها',
    queueInReview: 'قيد التنفيذ',
    queueInReviewHint: 'أعمال داخل المراجعة',
    queueChanges: 'تعديلات مطلوبة',
    queueChangesHint: 'تنتظر استجابة المصمم',
    queueOverdue: 'متأخرة',
    queueOverdueHint: 'أكثر من 48 ساعة دون حسم',
    seriesTitle: 'حركة دورة الأعمال',
    seriesCopy: 'الإرسال والنشر والرفض ضمن النطاق الزمني المحدد، مرتبة زمنيًا من اليسار إلى اليمين.',
    seriesSubmitted: 'مرسلة',
    seriesPublished: 'منشورة',
    seriesRejected: 'مرفوضة',
    seriesPoints: 'نقاط زمنية',
    seriesEmpty: 'لا توجد حركة إرسال أو نشر أو رفض ضمن المدى الحالي.',
    seriesAria: 'رسم زمني لحركة إرسال الأعمال ونشرها ورفضها',
    countersTitle: 'مؤشرات التفاعل والبلاغات',
    countersCopy: 'إجماليات تراكمية على جميع الأعمال المسجلة.',
    allWorks: 'جميع الأعمال',
    views: 'المشاهدات',
    viewsHint: 'إجمالي مرات المشاهدة',
    likes: 'الإعجابات',
    likesHint: 'إجمالي التفاعلات الإيجابية',
    reports: 'البلاغات',
    reportsHint: 'إجمالي البلاغات المسجلة',
    generatedAt: 'تم تحديث النظرة العامة في'
  },
  en: {
    liveData: 'Live data',
    kicker: 'Creative content management',
    title: 'Works / إدارة الأعمال',
    description: 'A clear operational view of the works lifecycle, review, visibility, and engagement, based on real recorded data.',
    totalWorks: 'Total works',
    operationalOverview: 'Safe aggregate metrics',
    authLoadingTitle: 'Checking works access',
    authLoadingCopy: 'Waiting for the user session to initialize before loading the overview.',
    forbiddenTitle: 'Works access is unavailable',
    forbiddenCopy: 'This account lacks works overview access. No data request was made.',
    overviewOnly: 'Overview',
    notice: 'This page shows aggregate metrics only. Detailed search and item actions will live in All Works.',
    filtersTitle: 'Overview range',
    filtersCopy: 'Choose a time grouping or set a custom range to refresh submission, publishing, and rejection activity.',
    period: 'Period',
    day: 'Today',
    week: 'Week',
    month: 'Month',
    year: 'Year',
    dayHint: 'Show activity for today',
    weekHint: 'Show activity for the current week',
    monthHint: 'Show activity for the current month',
    yearHint: 'Show activity for the current year',
    from: 'From',
    to: 'To',
    fromHint: 'Custom range start',
    toHint: 'Custom range end',
    apply: 'Apply range',
    reset: 'Reset',
    resetHint: 'Return to the current month and clear both dates',
    activeRange: 'Displayed range:',
    searchLater: 'Full works search will be introduced with the All Works page.',
    incompleteDateRange: 'Select both start and end dates to apply a custom range.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    validationError: 'The filters could not be applied. Check the period, dates, and maximum range.',
    loadingTitle: 'Preparing works metrics',
    loadingCopy: 'Loading safe aggregates from the works system...',
    errorTitle: 'Could not load the overview',
    genericError: 'An error occurred while loading works metrics. Try again.',
    retry: 'Retry',
    metricsTitle: 'Works status metrics',
    total: 'Total works',
    totalHint: 'All recorded works',
    submitted: 'Pending review',
    submittedHint: 'Waiting for review to start',
    inReview: 'In review',
    inReviewHint: 'Currently being reviewed',
    changesRequested: 'Changes requested',
    changesRequestedHint: 'Waiting for designer updates',
    approved: 'Approved',
    approvedHint: 'Passed the approval decision',
    published: 'Published',
    publishedHint: 'Visible in public content',
    rejected: 'Rejected',
    rejectedHint: 'Closed with a rejection decision',
    hiddenStatus: 'Hidden',
    hiddenStatusHint: 'Hidden by an administrative action',
    archived: 'Archived',
    archivedHint: 'Stored outside the active lifecycle',
    featured: 'Featured',
    featuredHint: 'Marked as featured',
    pinned: 'Pinned',
    pinnedHint: 'Pinned in visibility ordering',
    reported: 'Reported',
    reportedHint: 'Has at least one report',
    overdue: 'Review overdue',
    overdueHint: 'Exceeded the 48-hour review window',
    emptyTitle: 'No works have been recorded yet',
    emptyCopy: 'Works lifecycle metrics will appear here automatically when real data is available.',
    visibilityTitle: 'Public visibility',
    visibilityCopy: 'Works distribution by current visibility status.',
    public: 'Public',
    hiddenVisibility: 'Hidden',
    reviewQueueTitle: 'Review queue',
    reviewQueueCopy: 'States requiring attention from the content team.',
    pending: 'Pending review',
    pendingHint: 'Submitted works not yet reviewed',
    queueInReview: 'In progress',
    queueInReviewHint: 'Works currently in review',
    queueChanges: 'Changes requested',
    queueChangesHint: 'Waiting for a designer response',
    queueOverdue: 'Overdue',
    queueOverdueHint: 'Unresolved for more than 48 hours',
    seriesTitle: 'Works lifecycle activity',
    seriesCopy: 'Submissions, publishing, and rejections in the selected range, ordered left to right.',
    seriesSubmitted: 'Submitted',
    seriesPublished: 'Published',
    seriesRejected: 'Rejected',
    seriesPoints: 'time points',
    seriesEmpty: 'No submission, publishing, or rejection activity exists in the current range.',
    seriesAria: 'Time series of work submissions, publishing, and rejections',
    countersTitle: 'Engagement and reports',
    countersCopy: 'Cumulative totals across all recorded works.',
    allWorks: 'All works',
    views: 'Views',
    viewsHint: 'Total view count',
    likes: 'Likes',
    likesHint: 'Total positive interactions',
    reports: 'Reports',
    reportsHint: 'Total submitted reports',
    generatedAt: 'Overview updated at'
  }
} as const

const copy = computed(() => copyMap[currentLocale.value])
const authPending = computed(() => !authStore.isInitialized)
const hasWorksOverviewAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.overview.view')
})
const serverForbidden = ref(false)
const forbidden = computed(() => (
  authStore.isInitialized && (!hasWorksOverviewAccess.value || serverForbidden.value)
))
const overview = ref<WorksOverviewData | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)
const filters = reactive<WorksOverviewFilters>({
  period: 'month',
  from: '',
  to: ''
})

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let requestRevision = 0

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
  [...authStore.permissions].sort().join(',')
].join('|'))

const periodOptions = computed(() => [
  { value: 'day' as const, label: copy.value.day, hint: copy.value.dayHint },
  { value: 'week' as const, label: copy.value.week, hint: copy.value.weekHint },
  { value: 'month' as const, label: copy.value.month, hint: copy.value.monthHint },
  { value: 'year' as const, label: copy.value.year, hint: copy.value.yearHint }
])

const summaryCards = computed(() => {
  const summary = overview.value?.summary
  const queue = overview.value?.review_queue

  return [
    { key: 'total', label: copy.value.total, value: summary?.total ?? 0, hint: copy.value.totalHint, color: '#8b5cf6', code: '', tone: '' },
    { key: 'submitted', label: copy.value.submitted, value: summary?.submitted ?? 0, hint: copy.value.submittedHint, color: '#38bdf8', code: 'submitted', tone: '' },
    { key: 'in_review', label: copy.value.inReview, value: summary?.in_review ?? 0, hint: copy.value.inReviewHint, color: '#6366f1', code: 'in_review', tone: '' },
    { key: 'changes_requested', label: copy.value.changesRequested, value: summary?.changes_requested ?? 0, hint: copy.value.changesRequestedHint, color: '#f59e0b', code: 'changes_requested', tone: '' },
    { key: 'approved', label: copy.value.approved, value: summary?.approved ?? 0, hint: copy.value.approvedHint, color: '#14b8a6', code: 'approved', tone: '' },
    { key: 'published', label: copy.value.published, value: summary?.published ?? 0, hint: copy.value.publishedHint, color: '#10b981', code: 'published', tone: '' },
    { key: 'rejected', label: copy.value.rejected, value: summary?.rejected ?? 0, hint: copy.value.rejectedHint, color: '#f43f5e', code: 'rejected', tone: '' },
    { key: 'hidden', label: copy.value.hiddenStatus, value: summary?.hidden ?? 0, hint: copy.value.hiddenStatusHint, color: '#64748b', code: 'hidden', tone: '' },
    { key: 'archived', label: copy.value.archived, value: summary?.archived ?? 0, hint: copy.value.archivedHint, color: '#94a3b8', code: 'archived', tone: '' },
    { key: 'featured', label: copy.value.featured, value: summary?.featured ?? 0, hint: copy.value.featuredHint, color: '#eab308', code: 'featured', tone: '' },
    { key: 'pinned', label: copy.value.pinned, value: summary?.pinned ?? 0, hint: copy.value.pinnedHint, color: '#a855f7', code: 'pinned', tone: '' },
    { key: 'reported', label: copy.value.reported, value: summary?.reported ?? 0, hint: copy.value.reportedHint, color: '#fb7185', code: 'reports_count > 0', tone: '' },
    { key: 'overdue', label: copy.value.overdue, value: queue?.overdue ?? 0, hint: copy.value.overdueHint, color: '#f97316', code: '> 48h', tone: 'is-warning' }
  ]
})

const reviewQueueItems = computed(() => {
  const queue = overview.value?.review_queue

  return [
    { key: 'pending', label: copy.value.pending, value: queue?.pending ?? 0, hint: copy.value.pendingHint, color: '#38bdf8', tone: '' },
    { key: 'in_review', label: copy.value.queueInReview, value: queue?.in_review ?? 0, hint: copy.value.queueInReviewHint, color: '#6366f1', tone: '' },
    { key: 'changes_requested', label: copy.value.queueChanges, value: queue?.changes_requested ?? 0, hint: copy.value.queueChangesHint, color: '#f59e0b', tone: '' },
    { key: 'overdue', label: copy.value.queueOverdue, value: queue?.overdue ?? 0, hint: copy.value.queueOverdueHint, color: '#f97316', tone: 'is-overdue' }
  ]
})

const chartLabels = computed(() => ({
  submitted: copy.value.seriesSubmitted,
  published: copy.value.seriesPublished,
  rejected: copy.value.seriesRejected,
  points: copy.value.seriesPoints,
  empty: copy.value.seriesEmpty,
  chartAria: copy.value.seriesAria
}))

const topCounters = computed(() => {
  const counters = overview.value?.top_counters

  return [
    {
      key: 'views',
      label: copy.value.views,
      value: counters?.views ?? 0,
      hint: copy.value.viewsHint,
      color: '#38bdf8',
      icon: 'M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z'
    },
    {
      key: 'likes',
      label: copy.value.likes,
      value: counters?.likes ?? 0,
      hint: copy.value.likesHint,
      color: '#f43f5e',
      icon: 'M20.8 5.8a5 5 0 0 0-7.1 0L12 7.5l-1.7-1.7a5 5 0 0 0-7.1 7.1L12 21l8.8-8.1a5 5 0 0 0 0-7.1Z'
    },
    {
      key: 'reports',
      label: copy.value.reports,
      value: counters?.reports ?? 0,
      hint: copy.value.reportsHint,
      color: '#f59e0b',
      icon: 'M5 21V4m0 0h11l-1.5 4L16 12H5'
    }
  ]
})

const isEmpty = computed(() => overview.value !== null && overview.value.summary.total === 0)
const visibilityTotal = computed(() => (
  (overview.value?.visibility.public ?? 0) + (overview.value?.visibility.hidden ?? 0)
))
const reviewQueueTotal = computed(() => (
  (overview.value?.review_queue.pending ?? 0)
  + (overview.value?.review_queue.in_review ?? 0)
  + (overview.value?.review_queue.changes_requested ?? 0)
))

function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(value)
}

function formatDateTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

function visibilityWidth(value: number): string {
  if (visibilityTotal.value <= 0 || value <= 0) return '0%'
  return `${Math.min(100, (value / visibilityTotal.value) * 100)}%`
}

function visibilityRate(value: number): string {
  if (visibilityTotal.value <= 0) return '0%'
  const percentage = (value / visibilityTotal.value) * 100
  return `${new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    maximumFractionDigits: 1
  }).format(percentage)}%`
}

function errorStatus(requestError: unknown): number | null {
  if (!requestError || typeof requestError !== 'object') return null

  if ('response' in requestError && typeof (requestError as { response?: { status?: unknown } }).response?.status === 'number') {
    return (requestError as { response: { status: number } }).response.status
  }

  if ('statusCode' in requestError && typeof (requestError as { statusCode?: unknown }).statusCode === 'number') {
    return (requestError as { statusCode: number }).statusCode
  }

  if ('status' in requestError && typeof (requestError as { status?: unknown }).status === 'number') {
    return (requestError as { status: number }).status
  }

  return null
}

function validateFilters(): boolean {
  filterError.value = null

  if (Boolean(filters.from) !== Boolean(filters.to)) {
    filterError.value = copy.value.incompleteDateRange
    return false
  }

  if (filters.from && filters.to && filters.to < filters.from) {
    filterError.value = copy.value.invalidDateRange
    return false
  }

  return true
}

function buildEndpoint(): string {
  const params = new URLSearchParams()

  // نرسل فقط الفلاتر المعتمدة، ولا نضيف التاريخين ما لم يكتمل المدى.
  params.set('period', filters.period)
  if (filters.from && filters.to) {
    params.set('from', filters.from)
    params.set('to', filters.to)
  }

  return `/admin/works/overview?${params.toString()}`
}

async function fetchWorksOverview(): Promise<void> {
  if (!authStore.isInitialized || !hasWorksOverviewAccess.value) return
  if (!validateFilters()) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++requestRevision
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<WorksOverviewResponse>(buildEndpoint())

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasWorksOverviewAccess.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      overview.value = null
      error.value = copy.value.genericError
      return
    }

    overview.value = response.data
    serverForbidden.value = false
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasWorksOverviewAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      overview.value = null
      return
    }

    if (status === 422) {
      filterError.value = copy.value.validationError
      return
    }

    error.value = copy.value.genericError
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === requestRevision) {
      loading.value = false
    }
  }
}

function clearOverviewState(): void {
  requestRevision += 1
  overview.value = null
  error.value = null
  filterError.value = null
  loading.value = false
}

function syncWorksAccessState(): void {
  if (!pageMounted) return

  accessRevision += 1
  serverForbidden.value = false

  // نؤخر قرار المنع والتحميل معًا حتى تكتمل المصادقة والصلاحيات الدقيقة.
  if (!authStore.isInitialized) {
    loadedAuthorizationSignature = null
    clearOverviewState()
    return
  }

  if (!hasWorksOverviewAccess.value) {
    loadedAuthorizationSignature = null
    clearOverviewState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchWorksOverview()
}

function selectPeriod(period: WorksPeriod): void {
  if (filters.period === period) return

  filters.period = period
  void fetchWorksOverview()
}

function applyFilters(): void {
  void fetchWorksOverview()
}

function resetFilters(): void {
  Object.assign(filters, {
    period: 'month',
    from: '',
    to: ''
  })
  filterError.value = null
  void fetchWorksOverview()
}

watch(
  authorizationSignature,
  () => syncWorksAccessState(),
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncWorksAccessState()
})
</script>

<style scoped>
.ym-works-page {
  color: var(--ym-text);
}

.ym-works-hero,
.ym-works-filter-card,
.ym-works-result-card,
.ym-works-data-card,
.ym-works-counters-card,
.ym-works-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-works-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-works-hero::before {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), transparent 44%);
  content: '';
  pointer-events: none;
}

.ym-works-hero__grid {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(148, 163, 184, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: linear-gradient(to bottom, black, transparent 86%);
  pointer-events: none;
}

.ym-works-hero__glow {
  position: absolute;
  width: 19rem;
  height: 19rem;
  border-radius: 999px;
  filter: blur(18px);
  opacity: 0.24;
  pointer-events: none;
}

.ym-works-hero__glow.is-one {
  inset-block-start: -10rem;
  inset-inline-start: -5rem;
  background: #f59e0b;
}

.ym-works-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #38bdf8;
}

.ym-works-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-works-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-works-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-works-chip.is-brand {
  color: #fbbf24;
}

.ym-works-chip.is-live {
  color: #38bdf8;
}

.ym-works-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-works-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-works-description {
  max-width: 57rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-works-hero__summary {
  display: grid;
  min-width: min(100%, 225px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-works-hero__summary span,
.ym-works-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-hero__summary strong {
  color: var(--ym-text);
  font-size: 2.15rem;
  font-weight: 950;
}

.ym-works-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  padding: 1rem 1.15rem;
}

.ym-works-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-works-notice p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-filter-card,
.ym-works-result-card,
.ym-works-data-card,
.ym-works-counters-card {
  padding: clamp(1rem, 2.5vw, 1.5rem);
}

.ym-works-filter-card > header,
.ym-works-data-card > header,
.ym-works-counters-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-works-filter-card h2,
.ym-works-data-card h2,
.ym-works-counters-card h2,
.ym-works-access-state h2,
.ym-works-state h2,
.ym-works-empty h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-filter-card header p,
.ym-works-data-card header p,
.ym-works-counters-card header p,
.ym-works-access-state p,
.ym-works-state p,
.ym-works-empty p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-works-period-control {
  display: flex;
  align-items: center;
  gap: 1rem;
  border-block: 1px solid var(--ym-soft-border);
  padding-block: 1rem;
}

.ym-works-period-control > span {
  flex: 0 0 auto;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
}

.ym-works-period-options {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.ym-works-period-options button {
  min-width: 5.5rem;
  border: 1px solid var(--ym-control-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.55rem 0.8rem;
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.ym-works-period-options button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #38bdf8;
}

.ym-works-period-options button.is-active {
  border-color: rgba(56, 189, 248, 0.65);
  background: linear-gradient(135deg, rgba(14, 165, 233, 0.25), rgba(99, 102, 241, 0.24));
  color: var(--ym-text);
  box-shadow: 0 8px 20px rgba(14, 165, 233, 0.14);
}

.ym-works-period-options button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.ym-works-date-filter {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr)) minmax(9rem, 0.55fr);
  gap: 0.9rem;
  padding-top: 1rem;
}

.ym-works-date-filter label {
  display: grid;
  gap: 0.45rem;
}

.ym-works-date-filter label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-date-filter input {
  width: 100%;
  min-height: 44px;
  border: 1px solid var(--ym-control-border);
  border-radius: 14px;
  outline: none;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 800;
  padding: 0.65rem 0.75rem;
}

.ym-works-date-filter input:focus {
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px color-mix(in srgb, #38bdf8 18%, transparent);
}

.ym-works-filter-actions {
  display: flex;
  align-items: end;
}

.ym-works-button {
  min-height: 42px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  padding: 0.6rem 0.95rem;
  transition: transform 160ms ease, opacity 160ms ease, border-color 160ms ease;
}

.ym-works-button.is-primary {
  width: 100%;
  border-color: rgba(14, 165, 233, 0.55);
  background: linear-gradient(135deg, #0284c7, #4f46e5);
  color: #fff;
}

.ym-works-button.is-secondary {
  background: var(--ym-control-bg);
}

.ym-works-button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #38bdf8;
}

.ym-works-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-works-filter-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-top: 1px solid var(--ym-soft-border);
  margin-top: 1rem;
  padding-top: 0.85rem;
}

.ym-works-filter-foot p,
.ym-works-filter-foot span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-filter-foot span {
  display: inline-flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
}

.ym-works-filter-foot code {
  color: var(--ym-text);
  font-size: 12px;
}

.ym-works-filter-error {
  border: 1px solid color-mix(in srgb, #f43f5e 45%, var(--ym-soft-border));
  border-radius: 14px;
  background: color-mix(in srgb, #f43f5e 10%, transparent);
  color: #fb7185;
  font-size: 12px;
  font-weight: 900;
  margin: 0.85rem 0 0;
  padding: 0.7rem 0.85rem;
}

.ym-works-metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  gap: 1rem;
}

.ym-works-metric-card {
  position: relative;
  min-height: 172px;
  overflow: hidden;
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--works-accent) 16%, transparent), transparent 52%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1.05rem;
}

.ym-works-metric-card::after {
  position: absolute;
  inset-block-start: -2rem;
  inset-inline-end: -2rem;
  width: 6rem;
  height: 6rem;
  border-radius: 999px;
  background: var(--works-accent);
  content: '';
  filter: blur(25px);
  opacity: 0.14;
  pointer-events: none;
}

.ym-works-metric-card.is-warning {
  border-color: color-mix(in srgb, #f97316 45%, var(--ym-soft-border));
}

.ym-works-metric-card__label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-metric-card__label i {
  width: 0.65rem;
  height: 0.65rem;
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--works-accent);
  box-shadow: 0 0 14px color-mix(in srgb, var(--works-accent) 65%, transparent);
}

.ym-works-metric-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  line-height: 1;
  margin: 0.8rem 0 0.55rem;
}

.ym-works-metric-card small {
  display: block;
  min-height: 2.7rem;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 800;
  line-height: 1.6;
}

.ym-works-metric-card code {
  display: inline-block;
  border-radius: 8px;
  background: color-mix(in srgb, var(--works-accent) 12%, var(--ym-control-bg));
  color: var(--works-accent);
  font-size: 10px;
  font-weight: 800;
  margin-top: 0.55rem;
  padding: 0.25rem 0.42rem;
}

.ym-works-empty {
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid color-mix(in srgb, #38bdf8 35%, var(--ym-soft-border));
  border-radius: 22px;
  background: color-mix(in srgb, #38bdf8 8%, var(--ym-control-bg));
  padding: 1rem 1.15rem;
}

.ym-works-empty > span {
  display: grid;
  width: 3rem;
  height: 3rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-weight: 950;
}

.ym-works-operational-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.35fr);
  gap: 1rem;
}

.ym-works-data-card > header > span,
.ym-works-counters-card > header > span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
  padding: 0.45rem 0.75rem;
}

.ym-works-visibility-track {
  display: flex;
  height: 0.9rem;
  overflow: hidden;
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-muted) 12%, transparent);
}

.ym-works-visibility-track i {
  display: block;
  height: 100%;
  transition: width 220ms ease;
}

.ym-works-visibility-track .is-public {
  background: linear-gradient(90deg, #10b981, #22c55e);
}

.ym-works-visibility-track .is-hidden {
  background: linear-gradient(90deg, #64748b, #94a3b8);
}

.ym-works-visibility-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
  margin-top: 1rem;
}

.ym-works-visibility-list article {
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: var(--ym-control-bg);
  padding: 0.85rem;
}

.ym-works-visibility-list span {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-visibility-list span i {
  width: 0.6rem;
  height: 0.6rem;
  border-radius: 999px;
}

.ym-works-visibility-list span i.is-public {
  background: #10b981;
}

.ym-works-visibility-list span i.is-hidden {
  background: #94a3b8;
}

.ym-works-visibility-list strong {
  display: block;
  color: var(--ym-text);
  font-size: 1.65rem;
  font-weight: 950;
  margin-top: 0.35rem;
}

.ym-works-visibility-list small {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-works-review-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}

.ym-works-review-grid article {
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--queue-accent) 12%, transparent), transparent 58%),
    var(--ym-control-bg);
  padding: 0.9rem;
}

.ym-works-review-grid article.is-overdue {
  border-color: color-mix(in srgb, #f97316 48%, var(--ym-soft-border));
  border-inline-start: 3px solid #f97316;
}

.ym-works-review-grid span,
.ym-works-review-grid small {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-works-review-grid strong {
  display: block;
  color: var(--ym-text);
  font-size: 1.65rem;
  font-weight: 950;
  margin: 0.3rem 0;
}

.ym-works-review-grid article.is-overdue strong {
  color: #f97316;
}

.ym-works-counters-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.ym-works-counters-grid > article {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 21px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--counter-accent) 12%, transparent), transparent 58%),
    var(--ym-control-bg);
  padding: 1rem;
}

.ym-works-counter-icon {
  display: grid;
  width: 3.1rem;
  height: 3.1rem;
  flex: 0 0 auto;
  place-items: center;
  border-radius: 17px;
  background: color-mix(in srgb, var(--counter-accent) 16%, transparent);
  color: var(--counter-accent);
}

.ym-works-counter-icon svg {
  width: 1.55rem;
  height: 1.55rem;
}

.ym-works-counters-grid article > div > span,
.ym-works-counters-grid article small {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-works-counters-grid article strong {
  display: block;
  color: var(--ym-text);
  font-size: 1.75rem;
  font-weight: 950;
  margin: 0.2rem 0;
}

.ym-works-generated {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-generated time {
  color: var(--ym-text);
  font-weight: 900;
}

.ym-works-state {
  display: grid;
  min-height: 15rem;
  place-items: center;
  align-content: center;
  gap: 0.75rem;
  text-align: center;
}

.ym-works-state.is-error p {
  color: #fb7185;
}

.ym-works-state__icon {
  display: grid;
  width: 3.5rem;
  height: 3.5rem;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, #f43f5e 17%, transparent);
  color: #fb7185;
  font-size: 1.35rem;
  font-weight: 950;
}

.ym-works-spinner {
  width: 2.1rem;
  height: 2.1rem;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 22%, transparent);
  border-top-color: #38bdf8;
  border-radius: 999px;
  animation: ym-works-spin 800ms linear infinite;
}

.ym-works-access-state {
  display: grid;
  min-height: 23rem;
  place-items: center;
  align-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.ym-works-access-state__icon {
  display: grid;
  width: 4rem;
  height: 4rem;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, #f59e0b 18%, transparent);
  color: #f59e0b;
  font-size: 1.5rem;
  font-weight: 950;
}

@keyframes ym-works-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1100px) {
  .ym-works-operational-grid {
    grid-template-columns: 1fr;
  }

  .ym-works-counters-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .ym-works-hero__content,
  .ym-works-filter-card > header,
  .ym-works-data-card > header,
  .ym-works-counters-card > header,
  .ym-works-filter-foot,
  .ym-works-generated {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-period-control {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-date-filter,
  .ym-works-counters-grid {
    grid-template-columns: 1fr;
  }

  .ym-works-notice,
  .ym-works-empty {
    align-items: flex-start;
  }

  .ym-works-hero__summary {
    width: 100%;
  }
}

@media (max-width: 520px) {
  .ym-works-metrics-grid,
  .ym-works-review-grid,
  .ym-works-visibility-list {
    grid-template-columns: 1fr;
  }

  .ym-works-period-options {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-period-options button {
    min-width: 0;
  }
}
</style>
