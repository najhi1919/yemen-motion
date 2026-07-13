<template>
  <div class="ym-analytics-page space-y-7">
    <section class="ym-analytics-hero">
      <div class="ym-analytics-hero__glow is-one" />
      <div class="ym-analytics-hero__glow is-two" />

      <div class="ym-analytics-hero__content">
        <div>
          <div class="ym-analytics-chips">
            <span class="ym-analytics-chip is-brand">Yemen Motion</span>
            <span class="ym-analytics-chip is-live">{{ copy.liveAnalytics }}</span>
          </div>
          <p class="ym-analytics-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-analytics-description">{{ copy.description }}</p>
        </div>

        <div class="ym-analytics-hero__summary">
          <span>{{ copy.currentPeriodUsers }}</span>
          <strong>{{ formatNumber(analytics?.summary.current_period_users ?? 0) }}</strong>
          <small>{{ copy.comparedWithPrevious }}</small>
        </div>
      </div>
    </section>

    <section v-if="authPending" class="ym-analytics-access-state" role="status" aria-live="polite">
      <span class="ym-analytics-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section v-else-if="forbidden" class="ym-analytics-access-state" role="status">
      <span class="ym-analytics-access-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-analytics-notice" role="note">
        <span>{{ copy.aggregated }}</span>
        <p>{{ copy.notice }}</p>
      </aside>

      <section class="ym-analytics-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button type="button" class="ym-analytics-button is-secondary" :disabled="loading" @click="resetFilters">
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-analytics-filter-grid" @submit.prevent="applyFilters">
          <label>
            <span>{{ copy.from }}</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label>
            <span>{{ copy.to }}</span>
            <input v-model="filters.to" type="date" />
          </label>

          <label>
            <span>{{ copy.role }}</span>
            <input v-model.trim="filters.role" type="text" maxlength="80" placeholder="client" />
          </label>

          <label>
            <span>{{ copy.period }}</span>
            <select v-model="filters.period">
              <option value="day">{{ copy.day }}</option>
              <option value="week">{{ copy.week }}</option>
              <option value="month">{{ copy.month }}</option>
              <option value="year">{{ copy.year }}</option>
            </select>
          </label>

          <div class="ym-analytics-filter-actions">
            <button type="submit" class="ym-analytics-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="loading" class="ym-analytics-result-card ym-analytics-state" role="status" aria-live="polite">
        <span class="ym-analytics-spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </section>

      <section v-else-if="error" class="ym-analytics-result-card ym-analytics-state is-error" role="alert">
        <p>{{ error }}</p>
        <button type="button" class="ym-analytics-button is-secondary" @click="fetchUserAnalytics">
          {{ copy.retry }}
        </button>
      </section>

      <template v-else-if="analytics">
        <section class="ym-analytics-summary-grid">
          <article
            v-for="card in summaryCards"
            :key="card.key"
            class="ym-analytics-summary-card"
            :class="card.tone"
            :style="{ '--analytics-accent': card.color }"
          >
            <span>{{ card.label }}</span>
            <strong dir="auto">{{ card.value }}</strong>
            <small>{{ card.subtitle }}</small>
          </article>
        </section>

        <aside v-if="isEmpty" class="ym-analytics-empty" role="status">
          <span aria-hidden="true">0</span>
          <div>
            <h2>{{ copy.emptyTitle }}</h2>
            <p>{{ copy.emptyCopy }}</p>
          </div>
        </aside>

        <section class="ym-analytics-comparison-card">
          <header>
            <div>
              <h2>{{ copy.comparisonTitle }}</h2>
              <p>{{ copy.comparisonCopy }}</p>
            </div>
            <span>{{ periodLabel }}</span>
          </header>

          <div class="ym-analytics-comparison-grid">
            <article class="is-current">
              <span>{{ copy.currentRange }}</span>
              <strong dir="ltr">{{ formatDateRange(analytics.comparison.current_from, analytics.comparison.current_to) }}</strong>
            </article>
            <article class="is-previous">
              <span>{{ copy.previousRange }}</span>
              <strong dir="ltr">{{ formatDateRange(analytics.comparison.previous_from, analytics.comparison.previous_to) }}</strong>
            </article>
            <article>
              <span>{{ copy.role }}</span>
              <code v-if="analytics.comparison.role" dir="ltr">{{ analytics.comparison.role }}</code>
              <strong v-else>{{ copy.allRoles }}</strong>
            </article>
            <article>
              <span>{{ copy.period }}</span>
              <strong>{{ periodLabel }}</strong>
            </article>
          </div>
        </section>

        <section class="ym-analytics-data-grid">
          <article class="ym-analytics-data-card is-trend">
            <header>
              <div>
                <h2>{{ copy.trendTitle }}</h2>
                <p>{{ copy.trendCopy }}</p>
              </div>
              <span>{{ analytics.trend.length }}</span>
            </header>

            <div v-if="analytics.trend.length" class="ym-analytics-table-wrap">
              <table class="ym-analytics-table">
                <thead>
                  <tr>
                    <th>{{ copy.timeBucket }}</th>
                    <th>{{ copy.count }}</th>
                    <th>{{ copy.cumulative }}</th>
                    <th class="is-visual">{{ copy.distribution }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in analytics.trend" :key="item.period">
                    <td><code dir="ltr">{{ item.period }}</code></td>
                    <td><strong>{{ formatNumber(item.count) }}</strong></td>
                    <td><strong>{{ formatNumber(item.cumulative_count) }}</strong></td>
                    <td class="is-visual">
                      <span class="ym-analytics-bar" aria-hidden="true">
                        <i :style="{ width: relativeWidth(item.count, trendMaximum) }" />
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p v-else class="ym-analytics-inline-empty">{{ copy.noTrend }}</p>
          </article>

          <article class="ym-analytics-data-card is-role-mix">
            <header>
              <div>
                <h2>{{ copy.roleMixTitle }}</h2>
                <p>{{ copy.roleMixCopy }}</p>
              </div>
              <span>{{ analytics.role_mix.length }}</span>
            </header>

            <div v-if="analytics.role_mix.length" class="ym-analytics-role-list">
              <div v-for="item in analytics.role_mix" :key="item.role" class="ym-analytics-role-row">
                <div class="ym-analytics-role-row__head">
                  <code dir="ltr">{{ item.role }}</code>
                  <span>
                    <strong>{{ formatNumber(item.count) }}</strong>
                    <small>{{ formatRate(item.percentage) }}</small>
                  </span>
                </div>
                <span class="ym-analytics-role-track" aria-hidden="true">
                  <i :style="{ width: percentageWidth(item.percentage) }" />
                </span>
              </div>
            </div>

            <p v-else class="ym-analytics-inline-empty">{{ copy.noRoleMix }}</p>
          </article>
        </section>

        <footer class="ym-analytics-generated">
          <span>{{ copy.generatedAt }}</span>
          <time :datetime="analytics.generated_at">{{ formatDateTime(analytics.generated_at) }}</time>
        </footer>
      </template>

      <section v-else class="ym-analytics-result-card ym-analytics-state" role="status">
        <span class="ym-analytics-spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
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
type AnalyticsPeriod = 'day' | 'week' | 'month' | 'year'

type UserAnalyticsSummary = {
  current_period_users: number
  previous_period_users: number
  absolute_change: number
  percentage_change: number | null
  verified_rate: number
  unverified_rate: number
}

type UserAnalyticsTrendItem = {
  period: string
  count: number
  cumulative_count: number
}

type UserAnalyticsRoleMixItem = {
  role: string
  count: number
  percentage: number
}

type UserAnalyticsData = {
  summary: UserAnalyticsSummary
  trend: UserAnalyticsTrendItem[]
  role_mix: UserAnalyticsRoleMixItem[]
  comparison: {
    current_from: string
    current_to: string
    previous_from: string
    previous_to: string
    period: AnalyticsPeriod
    role: string | null
  }
  filters: {
    from: string | null
    to: string | null
    role: string | null
    period: AnalyticsPeriod
  }
  generated_at: string
}

type UserAnalyticsResponse = {
  success: boolean
  data: UserAnalyticsData
  message?: string
  errors?: Record<string, string[]> | null
}

type UserAnalyticsFilters = {
  from: string
  to: string
  role: string
  period: AnalyticsPeriod
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    liveAnalytics: 'بيانات حقيقية',
    kicker: 'اتجاهات النمو',
    title: 'التحليلات / Analytics',
    description: 'تحليلات تجميعية لنمو المستخدمين ومقارنة الفترة الحالية بالفترة السابقة.',
    currentPeriodUsers: 'مستخدمو الفترة الحالية',
    comparedWithPrevious: 'مقارنة مباشرة مع الفترة السابقة',
    aggregated: 'Aggregated',
    notice: 'تعرض الصفحة عدادات ونسبًا وفترات وأدوارًا نظامية فقط، دون قوائم مستخدمين أو بيانات شخصية.',
    authLoadingTitle: 'جارٍ التحقق من صلاحية التحليلات',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل تحميل بيانات التحليل.',
    forbiddenTitle: 'الوصول إلى التحليلات غير متاح',
    forbiddenCopy: 'هذه التحليلات محصورة على المدير الأعلى. لم تتم محاولة تحميل البيانات لهذا الحساب.',
    filtersTitle: 'فلاتر تحليلات المستخدمين',
    filtersCopy: 'حدّد نطاق التسجيل والدور والتجميع الزمني ثم طبّق الفلاتر.',
    from: 'من تاريخ',
    to: 'إلى تاريخ',
    role: 'الدور',
    period: 'التجميع الزمني',
    day: 'يومي',
    week: 'أسبوعي',
    month: 'شهري',
    year: 'سنوي',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    loading: 'يتم تحميل تحليلات المستخدمين...',
    retry: 'إعادة المحاولة',
    genericError: 'تعذر تحميل تحليلات المستخدمين. حاول مرة أخرى.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من الدور والتواريخ والفترة.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    currentUsers: 'الفترة الحالية',
    currentUsersHint: 'التسجيلات ضمن المدى الحالي',
    previousUsers: 'الفترة السابقة',
    previousUsersHint: 'مدى سابق مساوٍ زمنيًا',
    absoluteChange: 'التغيّر المطلق',
    absoluteChangeHint: 'الفرق في عدد التسجيلات',
    percentageChange: 'التغيّر النسبي',
    percentageChangeHint: 'مقارنة بالفترة السابقة',
    verifiedRate: 'نسبة الحسابات المتحققة',
    verifiedRateHint: 'من تسجيلات الفترة الحالية',
    unverifiedRate: 'نسبة الحسابات غير المتحققة',
    unverifiedRateHint: 'من تسجيلات الفترة الحالية',
    notAvailable: 'غير قابل للحساب',
    comparisonTitle: 'مقارنة الفترات',
    comparisonCopy: 'المدى الحالي والمدى السابق المتساوي معه في عدد الأيام.',
    currentRange: 'المدى الحالي',
    previousRange: 'المدى السابق',
    allRoles: 'كل الأدوار',
    trendTitle: 'اتجاه التسجيلات',
    trendCopy: 'التسجيلات الجديدة والعدد التراكمي ضمن المدى الحالي.',
    noTrend: 'لا توجد نقاط اتجاه ضمن الفلاتر الحالية.',
    roleMixTitle: 'مزيج الأدوار',
    roleMixCopy: 'عدد ونسبة كل دور ضمن مستخدمي الفترة الحالية.',
    noRoleMix: 'لا توجد بيانات أدوار متاحة.',
    timeBucket: 'الفترة',
    count: 'العدد',
    cumulative: 'التراكمي',
    distribution: 'التوزيع',
    emptyTitle: 'لا توجد تسجيلات في الفترة الحالية',
    emptyCopy: 'غيّر التواريخ أو الدور لعرض اتجاهات تجميعية أخرى.',
    generatedAt: 'تم إنشاء التحليل في'
  },
  en: {
    liveAnalytics: 'Live data',
    kicker: 'Growth trends',
    title: 'Analytics / التحليلات',
    description: 'Aggregated user growth analytics comparing the current period with the previous period.',
    currentPeriodUsers: 'Current-period users',
    comparedWithPrevious: 'Directly compared with the previous period',
    aggregated: 'Aggregated',
    notice: 'This page shows counts, rates, periods, and system roles only, without user lists or personal data.',
    authLoadingTitle: 'Checking analytics access',
    authLoadingCopy: 'Waiting for the user session to initialize before loading analytics data.',
    forbiddenTitle: 'Analytics access is unavailable',
    forbiddenCopy: 'These analytics are restricted to super-admin. No data request was made for this account.',
    filtersTitle: 'Users analytics filters',
    filtersCopy: 'Select a registration range, role, and time grouping, then apply the filters.',
    from: 'From',
    to: 'To',
    role: 'Role',
    period: 'Time grouping',
    day: 'Day',
    week: 'Week',
    month: 'Month',
    year: 'Year',
    apply: 'Apply filters',
    reset: 'Reset',
    loading: 'Loading users analytics...',
    retry: 'Retry',
    genericError: 'Could not load users analytics. Try again.',
    validationError: 'The filters could not be applied. Check the role, dates, and period.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    currentUsers: 'Current period',
    currentUsersHint: 'Registrations within the current range',
    previousUsers: 'Previous period',
    previousUsersHint: 'An equally sized preceding range',
    absoluteChange: 'Absolute change',
    absoluteChangeHint: 'Difference in registration count',
    percentageChange: 'Percentage change',
    percentageChangeHint: 'Compared with the previous period',
    verifiedRate: 'Verified account rate',
    verifiedRateHint: 'Of current-period registrations',
    unverifiedRate: 'Unverified account rate',
    unverifiedRateHint: 'Of current-period registrations',
    notAvailable: 'N/A',
    comparisonTitle: 'Period comparison',
    comparisonCopy: 'The current range and the preceding range with the same number of days.',
    currentRange: 'Current range',
    previousRange: 'Previous range',
    allRoles: 'All roles',
    trendTitle: 'Registration trend',
    trendCopy: 'New registrations and cumulative count within the current range.',
    noTrend: 'No trend points match the current filters.',
    roleMixTitle: 'Role mix',
    roleMixCopy: 'Count and percentage for each role among current-period users.',
    noRoleMix: 'No role data is available.',
    timeBucket: 'Period',
    count: 'Count',
    cumulative: 'Cumulative',
    distribution: 'Distribution',
    emptyTitle: 'No registrations in the current period',
    emptyCopy: 'Change the dates or role to view other aggregated trends.',
    generatedAt: 'Analytics generated at'
  }
}

const copy = computed(() => copyMap[currentLocale.value])
const authPending = computed(() => !authStore.isInitialized)
const hasAnalyticsAccess = computed(() => (
  authStore.isInitialized
  && authStore.isAuthenticated
  && authStore.role === 'super-admin'
))
const analytics = ref<UserAnalyticsData | null>(null)
const loading = ref(false)
const forbidden = ref(false)
const error = ref<string | null>(null)
const filters = reactive<UserAnalyticsFilters>({
  from: '',
  to: '',
  role: '',
  period: 'day'
})
let pageMounted = false
let loadedForCurrentAuthorization = false
let accessRevision = 0
let requestRevision = 0

const summaryCards = computed(() => {
  const summary = analytics.value?.summary
  const absoluteChange = summary?.absolute_change ?? 0
  const percentageChange = summary?.percentage_change

  return [
    {
      key: 'current_period_users',
      label: copy.value.currentUsers,
      value: formatNumber(summary?.current_period_users ?? 0),
      subtitle: copy.value.currentUsersHint,
      color: '#38bdf8',
      tone: ''
    },
    {
      key: 'previous_period_users',
      label: copy.value.previousUsers,
      value: formatNumber(summary?.previous_period_users ?? 0),
      subtitle: copy.value.previousUsersHint,
      color: '#8b5cf6',
      tone: ''
    },
    {
      key: 'absolute_change',
      label: copy.value.absoluteChange,
      value: formatSignedNumber(absoluteChange),
      subtitle: copy.value.absoluteChangeHint,
      color: absoluteChange < 0 ? '#ef4444' : '#10b981',
      tone: changeTone(absoluteChange)
    },
    {
      key: 'percentage_change',
      label: copy.value.percentageChange,
      value: percentageChange === null || percentageChange === undefined
        ? copy.value.notAvailable
        : formatSignedRate(percentageChange),
      subtitle: copy.value.percentageChangeHint,
      color: percentageChange === null || percentageChange === undefined
        ? '#94a3b8'
        : percentageChange < 0 ? '#ef4444' : '#10b981',
      tone: percentageChange === null || percentageChange === undefined ? 'is-neutral' : changeTone(percentageChange)
    },
    {
      key: 'verified_rate',
      label: copy.value.verifiedRate,
      value: formatRate(summary?.verified_rate ?? 0),
      subtitle: copy.value.verifiedRateHint,
      color: '#10b981',
      tone: ''
    },
    {
      key: 'unverified_rate',
      label: copy.value.unverifiedRate,
      value: formatRate(summary?.unverified_rate ?? 0),
      subtitle: copy.value.unverifiedRateHint,
      color: '#f59e0b',
      tone: ''
    }
  ]
})
const isEmpty = computed(() => (
  analytics.value !== null
  && analytics.value.summary.current_period_users === 0
  && analytics.value.trend.length === 0
))
const trendMaximum = computed(() => Math.max(1, ...((analytics.value?.trend ?? []).map(item => item.count))))
const periodLabel = computed(() => copy.value[analytics.value?.comparison.period ?? filters.period])

function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    maximumFractionDigits: 2
  }).format(value)
}

function formatSignedNumber(value: number): string {
  const formatted = formatNumber(Math.abs(value))
  if (value > 0) return `+${formatted}`
  if (value < 0) return `-${formatted}`
  return formatted
}

function formatRate(value: number): string {
  return `${formatNumber(value)}%`
}

function formatSignedRate(value: number): string {
  if (value > 0) return `+${formatNumber(value)}%`
  if (value < 0) return `-${formatNumber(Math.abs(value))}%`
  return `${formatNumber(value)}%`
}

function changeTone(value: number): string {
  if (value > 0) return 'is-positive'
  if (value < 0) return 'is-negative'
  return 'is-neutral'
}

function formatDateOnly(value: string): string {
  const date = new Date(`${value}T00:00:00`)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium'
  }).format(date)
}

function formatDateRange(from: string, to: string): string {
  return `${formatDateOnly(from)} — ${formatDateOnly(to)}`
}

function formatDateTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

function relativeWidth(value: number, maximum: number): string {
  if (value <= 0) return '0%'
  return `${Math.max(6, Math.min(100, (value / maximum) * 100))}%`
}

function percentageWidth(value: number): string {
  if (value <= 0) return '0%'
  return `${Math.max(6, Math.min(100, value))}%`
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

function buildEndpoint(): string {
  const params = new URLSearchParams()

  // نرسل قائمة الفلاتر المعتمدة فقط، ولا نضيف القيم النصية الفارغة إلى query.
  if (filters.from) params.set('from', filters.from)
  if (filters.to) params.set('to', filters.to)
  if (filters.role.trim()) params.set('role', filters.role.trim())
  params.set('period', filters.period)

  return `/admin/analytics/users?${params.toString()}`
}

async function fetchUserAnalytics(): Promise<void> {
  if (!authStore.isInitialized) return

  if (!hasAnalyticsAccess.value) {
    forbidden.value = true
    return
  }

  if (filters.from && filters.to && filters.to < filters.from) {
    error.value = copy.value.invalidDateRange
    return
  }

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++requestRevision
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<UserAnalyticsResponse>(buildEndpoint())

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasAnalyticsAccess.value
    ) {
      return
    }

    analytics.value = response.data
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasAnalyticsAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 403) {
      forbidden.value = true
      analytics.value = null
      return
    }

    error.value = status === 422 ? copy.value.validationError : copy.value.genericError
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === requestRevision) {
      loading.value = false
    }
  }
}

function clearAnalyticsState(): void {
  requestRevision += 1
  analytics.value = null
  error.value = null
  loading.value = false
}

function syncAnalyticsAccessState(): void {
  if (!pageMounted) return

  // نؤخر قرار المنع والتحميل معًا حتى تصبح حالة المصادقة مكتملة وواضحة.
  if (!authStore.isInitialized) {
    forbidden.value = false
    loadedForCurrentAuthorization = false
    clearAnalyticsState()
    return
  }

  if (!hasAnalyticsAccess.value) {
    forbidden.value = true
    loadedForCurrentAuthorization = false
    clearAnalyticsState()
    return
  }

  forbidden.value = false

  if (loadedForCurrentAuthorization) return

  loadedForCurrentAuthorization = true
  void fetchUserAnalytics()
}

function applyFilters(): void {
  void fetchUserAnalytics()
}

function resetFilters(): void {
  Object.assign(filters, {
    from: '',
    to: '',
    role: '',
    period: 'day'
  })
  void fetchUserAnalytics()
}

watch(
  () => [authStore.isInitialized, authStore.isAuthenticated, authStore.role] as const,
  () => {
    accessRevision += 1
    syncAnalyticsAccessState()
  },
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncAnalyticsAccessState()
})
</script>

<style scoped>
.ym-analytics-page {
  color: var(--ym-text);
}

.ym-analytics-hero,
.ym-analytics-filter-card,
.ym-analytics-result-card,
.ym-analytics-comparison-card,
.ym-analytics-data-card,
.ym-analytics-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-analytics-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-analytics-hero::before {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(16, 185, 129, 0.16), transparent 46%),
    repeating-linear-gradient(90deg, transparent 0 42px, rgba(148, 163, 184, 0.04) 43px 44px);
  content: '';
  pointer-events: none;
}

.ym-analytics-hero__glow {
  position: absolute;
  width: 18rem;
  height: 18rem;
  border-radius: 999px;
  filter: blur(14px);
  opacity: 0.25;
  pointer-events: none;
}

.ym-analytics-hero__glow.is-one {
  inset-block-start: -9rem;
  inset-inline-start: -5rem;
  background: #10b981;
}

.ym-analytics-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #0ea5e9;
}

.ym-analytics-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-analytics-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-analytics-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-analytics-chip.is-brand {
  color: #a78bfa;
}

.ym-analytics-chip.is-live {
  color: #10b981;
}

.ym-analytics-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-analytics-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-analytics-description {
  max-width: 55rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-analytics-hero__summary {
  display: grid;
  min-width: min(100%, 225px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-analytics-hero__summary span,
.ym-analytics-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-analytics-hero__summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
}

.ym-analytics-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  padding: 1rem 1.15rem;
}

.ym-analytics-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #10b981 18%, transparent);
  color: #10b981;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-analytics-notice p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-analytics-filter-card,
.ym-analytics-result-card,
.ym-analytics-comparison-card,
.ym-analytics-data-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-analytics-filter-card > header,
.ym-analytics-comparison-card > header,
.ym-analytics-data-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-analytics-filter-card h2,
.ym-analytics-comparison-card h2,
.ym-analytics-data-card h2,
.ym-analytics-access-state h2,
.ym-analytics-empty h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-analytics-filter-card p,
.ym-analytics-comparison-card header p,
.ym-analytics-data-card header p,
.ym-analytics-access-state p,
.ym-analytics-empty p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-analytics-filter-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-analytics-filter-grid label {
  display: grid;
  gap: 0.45rem;
}

.ym-analytics-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-analytics-filter-grid input,
.ym-analytics-filter-grid select {
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

.ym-analytics-filter-grid input:focus,
.ym-analytics-filter-grid select:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px color-mix(in srgb, #10b981 18%, transparent);
}

.ym-analytics-filter-actions {
  display: flex;
  align-items: end;
}

.ym-analytics-button {
  min-height: 42px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  padding: 0.6rem 0.9rem;
  transition: transform 160ms ease, opacity 160ms ease, border-color 160ms ease;
}

.ym-analytics-button.is-primary {
  width: 100%;
  border-color: rgba(16, 185, 129, 0.55);
  background: linear-gradient(135deg, #059669, #0284c7);
  color: #fff;
}

.ym-analytics-button.is-secondary {
  background: var(--ym-control-bg);
}

.ym-analytics-button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #10b981;
}

.ym-analytics-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-analytics-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.ym-analytics-summary-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--analytics-accent) 15%, transparent), transparent 48%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1.1rem;
}

.ym-analytics-summary-card span,
.ym-analytics-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-analytics-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 1.85rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-analytics-summary-card.is-positive strong {
  color: #10b981;
}

.ym-analytics-summary-card.is-negative strong {
  color: #f87171;
}

.ym-analytics-summary-card.is-neutral strong {
  color: var(--ym-muted);
}

.ym-analytics-empty {
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid color-mix(in srgb, #f59e0b 35%, var(--ym-soft-border));
  border-radius: 22px;
  background: color-mix(in srgb, #f59e0b 9%, var(--ym-control-bg));
  padding: 1rem 1.15rem;
}

.ym-analytics-empty > span {
  display: grid;
  flex: 0 0 auto;
  width: 3rem;
  height: 3rem;
  place-items: center;
  border-radius: 999px;
  background: color-mix(in srgb, #f59e0b 18%, transparent);
  color: #f59e0b;
  font-weight: 950;
}

.ym-analytics-comparison-card > header > span,
.ym-analytics-data-card > header > span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
  padding: 0.45rem 0.75rem;
}

.ym-analytics-comparison-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.8rem;
}

.ym-analytics-comparison-grid article {
  display: grid;
  gap: 0.4rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: var(--ym-control-bg);
  padding: 0.9rem;
}

.ym-analytics-comparison-grid article.is-current {
  border-color: color-mix(in srgb, #10b981 36%, var(--ym-soft-border));
}

.ym-analytics-comparison-grid article.is-previous {
  border-color: color-mix(in srgb, #8b5cf6 36%, var(--ym-soft-border));
}

.ym-analytics-comparison-grid span {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 900;
}

.ym-analytics-comparison-grid strong,
.ym-analytics-comparison-grid code {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  overflow-wrap: anywhere;
}

.ym-analytics-comparison-grid code,
.ym-analytics-table code,
.ym-analytics-role-row code {
  color: #38bdf8;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

.ym-analytics-data-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(0, 0.8fr);
  gap: 1rem;
}

.ym-analytics-table-wrap {
  overflow-x: auto;
}

.ym-analytics-table {
  width: 100%;
  min-width: 650px;
  border-collapse: collapse;
}

.ym-analytics-table th,
.ym-analytics-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-text);
  font-size: 13px;
  padding: 0.8rem 0.65rem;
  text-align: start;
}

.ym-analytics-table th {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
}

.ym-analytics-table strong {
  font-weight: 950;
}

.ym-analytics-table .is-visual {
  width: 42%;
}

.ym-analytics-bar,
.ym-analytics-role-track {
  display: block;
  overflow: hidden;
  height: 0.5rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-muted) 12%, transparent);
}

.ym-analytics-bar {
  min-width: 8rem;
}

.ym-analytics-bar i,
.ym-analytics-role-track i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #10b981, #38bdf8);
}

.ym-analytics-role-list {
  display: grid;
  gap: 1rem;
}

.ym-analytics-role-row__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.45rem;
}

.ym-analytics-role-row__head > span {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
}

.ym-analytics-role-row__head strong {
  color: var(--ym-text);
  font-weight: 950;
}

.ym-analytics-role-row__head small {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-analytics-inline-empty {
  display: grid;
  min-height: 12rem;
  place-items: center;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 850;
  text-align: center;
}

.ym-analytics-generated {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-analytics-generated time {
  color: var(--ym-text);
  font-weight: 900;
}

.ym-analytics-state {
  display: grid;
  min-height: 13rem;
  place-items: center;
  align-content: center;
  gap: 0.8rem;
  color: var(--ym-muted);
  font-weight: 850;
  text-align: center;
}

.ym-analytics-state.is-error {
  color: #f87171;
}

.ym-analytics-spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 22%, transparent);
  border-top-color: #10b981;
  border-radius: 999px;
  animation: ym-analytics-spin 800ms linear infinite;
}

.ym-analytics-access-state {
  display: grid;
  min-height: 23rem;
  place-items: center;
  align-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.ym-analytics-access-state__icon {
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

@keyframes ym-analytics-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1180px) {
  .ym-analytics-summary-grid,
  .ym-analytics-comparison-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-analytics-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-analytics-filter-actions {
    align-items: stretch;
  }

  .ym-analytics-data-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .ym-analytics-hero__content,
  .ym-analytics-filter-card > header,
  .ym-analytics-comparison-card > header,
  .ym-analytics-data-card > header,
  .ym-analytics-generated {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-analytics-summary-grid,
  .ym-analytics-comparison-grid,
  .ym-analytics-filter-grid {
    grid-template-columns: 1fr;
  }

  .ym-analytics-notice,
  .ym-analytics-empty {
    align-items: flex-start;
  }
}

@media (max-width: 560px) {
  .ym-analytics-table .is-visual {
    display: none;
  }

  .ym-analytics-table {
    min-width: 100%;
  }
}
</style>
