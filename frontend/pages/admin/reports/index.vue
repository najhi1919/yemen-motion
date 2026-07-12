<template>
  <div class="ym-reports-page space-y-7">
    <section class="ym-reports-hero">
      <div class="ym-reports-hero__glow is-one" />
      <div class="ym-reports-hero__glow is-two" />

      <div class="ym-reports-hero__content">
        <div>
          <div class="ym-reports-chips">
            <span class="ym-reports-chip is-brand">Yemen Motion</span>
            <span class="ym-reports-chip is-live">{{ copy.liveReport }}</span>
          </div>
          <p class="ym-reports-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-reports-description">{{ copy.description }}</p>
        </div>

        <div class="ym-reports-hero__summary">
          <span>{{ copy.totalUsers }}</span>
          <strong>{{ formatNumber(report?.summary.total_users ?? 0) }}</strong>
          <small>{{ copy.aggregatedOnly }}</small>
        </div>
      </div>
    </section>

    <section v-if="authPending" class="ym-reports-access-state" role="status" aria-live="polite">
      <span class="ym-reports-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section v-else-if="forbidden" class="ym-reports-access-state" role="status">
      <span class="ym-reports-access-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-reports-notice" role="note">
        <span>{{ copy.aggregated }}</span>
        <p>{{ copy.notice }}</p>
      </aside>

      <section class="ym-reports-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button type="button" class="ym-reports-button is-secondary" :disabled="loading" @click="resetFilters">
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-reports-filter-grid" @submit.prevent="applyFilters">
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

          <div class="ym-reports-filter-actions">
            <button type="submit" class="ym-reports-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>
      </section>

      <section v-if="loading" class="ym-reports-result-card ym-reports-state" role="status" aria-live="polite">
        <span class="ym-reports-spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </section>

      <section v-else-if="error" class="ym-reports-result-card ym-reports-state is-error" role="alert">
        <p>{{ error }}</p>
        <button type="button" class="ym-reports-button is-secondary" @click="fetchUserReport">
          {{ copy.retry }}
        </button>
      </section>

      <template v-else-if="report">
        <section class="ym-reports-summary-grid">
          <article
            v-for="card in summaryCards"
            :key="card.key"
            class="ym-reports-summary-card"
            :style="{ '--report-accent': card.color }"
          >
            <span>{{ card.label }}</span>
            <strong>{{ formatNumber(card.value) }}</strong>
            <small>{{ card.subtitle }}</small>
          </article>
        </section>

        <aside v-if="isEmpty" class="ym-reports-empty" role="status">
          <span aria-hidden="true">0</span>
          <div>
            <h2>{{ copy.emptyTitle }}</h2>
            <p>{{ copy.emptyCopy }}</p>
          </div>
        </aside>

        <section class="ym-reports-data-grid">
          <article class="ym-reports-data-card">
            <header>
              <div>
                <h2>{{ copy.rolesTitle }}</h2>
                <p>{{ copy.rolesCopy }}</p>
              </div>
              <span>{{ report.role_breakdown.length }}</span>
            </header>

            <div v-if="report.role_breakdown.length" class="ym-reports-role-list">
              <div v-for="item in report.role_breakdown" :key="item.role" class="ym-reports-role-row">
                <div>
                  <code dir="ltr">{{ item.role }}</code>
                  <strong>{{ formatNumber(item.count) }}</strong>
                </div>
                <span class="ym-reports-track" aria-hidden="true">
                  <i :style="{ width: percentage(item.count, roleMaximum) }" />
                </span>
              </div>
            </div>

            <p v-else class="ym-reports-inline-empty">{{ copy.noRoles }}</p>
          </article>

          <article class="ym-reports-data-card">
            <header>
              <div>
                <h2>{{ copy.seriesTitle }}</h2>
                <p>{{ copy.seriesCopy }}</p>
              </div>
              <span>{{ periodLabel }}</span>
            </header>

            <div v-if="report.registrations_series.length" class="ym-reports-series-wrap">
              <table class="ym-reports-series-table">
                <thead>
                  <tr>
                    <th>{{ copy.timeBucket }}</th>
                    <th>{{ copy.registrations }}</th>
                    <th class="is-visual">{{ copy.distribution }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in report.registrations_series" :key="item.period">
                    <td><code dir="ltr">{{ item.period }}</code></td>
                    <td><strong>{{ formatNumber(item.count) }}</strong></td>
                    <td class="is-visual">
                      <span class="ym-reports-series-bar" aria-hidden="true">
                        <i :style="{ width: percentage(item.count, seriesMaximum) }" />
                      </span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <p v-else class="ym-reports-inline-empty">{{ copy.noSeries }}</p>
          </article>
        </section>

        <footer class="ym-reports-generated">
          <span>{{ copy.generatedAt }}</span>
          <time :datetime="report.generated_at">{{ formatDateTime(report.generated_at) }}</time>
        </footer>
      </template>

      <section v-else class="ym-reports-result-card ym-reports-state" role="status">
        <span class="ym-reports-spinner" aria-hidden="true" />
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
type ReportPeriod = 'day' | 'week' | 'month' | 'year'

type UserReportSummary = {
  total_users: number
  users_in_range: number
  verified_users: number
  unverified_users: number
}

type RoleBreakdownItem = {
  role: string
  count: number
}

type RegistrationSeriesItem = {
  period: string
  count: number
}

type UserReportData = {
  summary: UserReportSummary
  role_breakdown: RoleBreakdownItem[]
  registrations_series: RegistrationSeriesItem[]
  filters: {
    from: string | null
    to: string | null
    role: string | null
    period: ReportPeriod
  }
  generated_at: string
}

type UserReportResponse = {
  success: boolean
  data: UserReportData
  message?: string
  errors?: Record<string, string[]> | null
}

type UserReportFilters = {
  from: string
  to: string
  role: string
  period: ReportPeriod
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    liveReport: 'بيانات حقيقية',
    kicker: 'الرؤى التشغيلية',
    title: 'التقارير / Reports',
    description: 'تقرير مستخدمين تجميعي أولي مبني على البيانات الحقيقية المسجلة في النظام.',
    totalUsers: 'إجمالي المستخدمين',
    aggregatedOnly: 'مؤشرات تجميعية آمنة',
    aggregated: 'Aggregated',
    notice: 'يعرض التقرير عدادات وفترات وأدوارًا نظامية فقط، دون قوائم مستخدمين أو بيانات شخصية.',
    authLoadingTitle: 'جارٍ التحقق من صلاحية التقارير',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل تحميل بيانات التقرير.',
    forbiddenTitle: 'الوصول إلى التقارير غير متاح',
    forbiddenCopy: 'هذا التقرير محصور على المدير الأعلى. لم تتم محاولة تحميل البيانات لهذا الحساب.',
    filtersTitle: 'فلاتر تقرير المستخدمين',
    filtersCopy: 'حدّد نطاق التسجيل والدور والفترة الزمنية ثم طبّق الفلاتر.',
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
    loading: 'يتم تحميل تقرير المستخدمين...',
    retry: 'إعادة المحاولة',
    genericError: 'تعذر تحميل تقرير المستخدمين. حاول مرة أخرى.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من الدور والتواريخ والفترة.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    totalUsersCard: 'إجمالي المستخدمين',
    totalUsersHint: 'ضمن الدور المحدد إن وُجد',
    usersInRange: 'المستخدمون ضمن النطاق',
    usersInRangeHint: 'وفق تاريخ التسجيل',
    verifiedUsers: 'الحسابات المتحققة',
    verifiedUsersHint: 'ضمن النطاق الحالي',
    unverifiedUsers: 'الحسابات غير المتحققة',
    unverifiedUsersHint: 'ضمن النطاق الحالي',
    emptyTitle: 'لا توجد تسجيلات ضمن النطاق',
    emptyCopy: 'غيّر التواريخ أو الدور لعرض بيانات تجميعية أخرى.',
    rolesTitle: 'توزيع الأدوار',
    rolesCopy: 'عدد الحسابات لكل دور ضمن النطاق الحالي.',
    noRoles: 'لا توجد بيانات أدوار متاحة.',
    seriesTitle: 'سلسلة التسجيلات',
    seriesCopy: 'حركة إنشاء الحسابات وفق التجميع الزمني المحدد.',
    noSeries: 'لا توجد تسجيلات زمنية ضمن الفلاتر الحالية.',
    timeBucket: 'الفترة',
    registrations: 'التسجيلات',
    distribution: 'التوزيع',
    generatedAt: 'تم إنشاء التقرير في'
  },
  en: {
    liveReport: 'Live data',
    kicker: 'Operational insights',
    title: 'Reports / التقارير',
    description: 'An initial aggregated users report built from real system data.',
    totalUsers: 'Total users',
    aggregatedOnly: 'Safe aggregated metrics',
    aggregated: 'Aggregated',
    notice: 'This report shows counts, periods, and system roles only, without user lists or personal data.',
    authLoadingTitle: 'Checking report access',
    authLoadingCopy: 'Waiting for the user session to initialize before loading report data.',
    forbiddenTitle: 'Report access is unavailable',
    forbiddenCopy: 'This report is restricted to super-admin. No data request was made for this account.',
    filtersTitle: 'Users report filters',
    filtersCopy: 'Select a registration range, role, and grouping period, then apply the filters.',
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
    loading: 'Loading users report...',
    retry: 'Retry',
    genericError: 'Could not load the users report. Try again.',
    validationError: 'The filters could not be applied. Check the role, dates, and period.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    totalUsersCard: 'Total users',
    totalUsersHint: 'Within the selected role, if any',
    usersInRange: 'Users in range',
    usersInRangeHint: 'By registration date',
    verifiedUsers: 'Verified accounts',
    verifiedUsersHint: 'Within the current range',
    unverifiedUsers: 'Unverified accounts',
    unverifiedUsersHint: 'Within the current range',
    emptyTitle: 'No registrations in range',
    emptyCopy: 'Change the dates or role to view other aggregated data.',
    rolesTitle: 'Role breakdown',
    rolesCopy: 'Account count per role within the current range.',
    noRoles: 'No role data is available.',
    seriesTitle: 'Registration series',
    seriesCopy: 'Account creation activity by the selected time grouping.',
    noSeries: 'No registration periods match the current filters.',
    timeBucket: 'Period',
    registrations: 'Registrations',
    distribution: 'Distribution',
    generatedAt: 'Report generated at'
  }
}

const copy = computed(() => copyMap[currentLocale.value])
const authPending = computed(() => !authStore.isInitialized)
const hasReportAccess = computed(() => (
  authStore.isInitialized
  && authStore.isAuthenticated
  && authStore.role === 'super-admin'
))
const report = ref<UserReportData | null>(null)
const loading = ref(false)
const forbidden = ref(false)
const error = ref<string | null>(null)
const filters = reactive<UserReportFilters>({
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
  const summary = report.value?.summary

  return [
    {
      key: 'total_users',
      label: copy.value.totalUsersCard,
      value: summary?.total_users ?? 0,
      subtitle: copy.value.totalUsersHint,
      color: '#8b5cf6'
    },
    {
      key: 'users_in_range',
      label: copy.value.usersInRange,
      value: summary?.users_in_range ?? 0,
      subtitle: copy.value.usersInRangeHint,
      color: '#38bdf8'
    },
    {
      key: 'verified_users',
      label: copy.value.verifiedUsers,
      value: summary?.verified_users ?? 0,
      subtitle: copy.value.verifiedUsersHint,
      color: '#10b981'
    },
    {
      key: 'unverified_users',
      label: copy.value.unverifiedUsers,
      value: summary?.unverified_users ?? 0,
      subtitle: copy.value.unverifiedUsersHint,
      color: '#f59e0b'
    }
  ]
})
const isEmpty = computed(() => (
  report.value !== null
  && report.value.summary.users_in_range === 0
  && report.value.registrations_series.length === 0
))
const roleMaximum = computed(() => Math.max(1, ...((report.value?.role_breakdown ?? []).map(item => item.count))))
const seriesMaximum = computed(() => Math.max(1, ...((report.value?.registrations_series ?? []).map(item => item.count))))
const periodLabel = computed(() => copy.value[report.value?.filters.period ?? filters.period])

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

function percentage(value: number, maximum: number): string {
  if (value <= 0) return '0%'
  return `${Math.max(6, Math.min(100, (value / maximum) * 100))}%`
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

  // نرسل قائمة الفلاتر المعتمدة فقط، ونتجاهل القيم النصية الفارغة كليًا.
  if (filters.from) params.set('from', filters.from)
  if (filters.to) params.set('to', filters.to)
  if (filters.role.trim()) params.set('role', filters.role.trim())
  params.set('period', filters.period)

  return `/admin/reports/users?${params.toString()}`
}

async function fetchUserReport(): Promise<void> {
  if (!authStore.isInitialized) return

  if (!hasReportAccess.value) {
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
    const response = await apiFetch<UserReportResponse>(buildEndpoint())

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasReportAccess.value
    ) {
      return
    }

    report.value = response.data
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasReportAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 403) {
      forbidden.value = true
      report.value = null
      return
    }

    error.value = status === 422 ? copy.value.validationError : copy.value.genericError
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === requestRevision) {
      loading.value = false
    }
  }
}

function clearReportState(): void {
  requestRevision += 1
  report.value = null
  error.value = null
  loading.value = false
}

function syncReportAccessState(): void {
  if (!pageMounted) return

  // نؤخر قرار المنع والتحميل معًا حتى تصبح حالة المصادقة مكتملة وواضحة.
  if (!authStore.isInitialized) {
    forbidden.value = false
    loadedForCurrentAuthorization = false
    clearReportState()
    return
  }

  if (!hasReportAccess.value) {
    forbidden.value = true
    loadedForCurrentAuthorization = false
    clearReportState()
    return
  }

  forbidden.value = false

  if (loadedForCurrentAuthorization) return

  loadedForCurrentAuthorization = true
  void fetchUserReport()
}

function applyFilters(): void {
  void fetchUserReport()
}

function resetFilters(): void {
  Object.assign(filters, {
    from: '',
    to: '',
    role: '',
    period: 'day'
  })
  void fetchUserReport()
}

watch(
  () => [authStore.isInitialized, authStore.isAuthenticated, authStore.role] as const,
  () => {
    accessRevision += 1
    syncReportAccessState()
  },
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncReportAccessState()
})
</script>

<style scoped>
.ym-reports-page {
  color: var(--ym-text);
}

.ym-reports-hero,
.ym-reports-filter-card,
.ym-reports-result-card,
.ym-reports-data-card,
.ym-reports-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-reports-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-reports-hero::before {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(14, 165, 233, 0.17), transparent 46%),
    repeating-linear-gradient(90deg, transparent 0 42px, rgba(148, 163, 184, 0.04) 43px 44px);
  content: '';
  pointer-events: none;
}

.ym-reports-hero__glow {
  position: absolute;
  width: 18rem;
  height: 18rem;
  border-radius: 999px;
  filter: blur(14px);
  opacity: 0.25;
  pointer-events: none;
}

.ym-reports-hero__glow.is-one {
  inset-block-start: -9rem;
  inset-inline-start: -5rem;
  background: #0ea5e9;
}

.ym-reports-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #8b5cf6;
}

.ym-reports-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-reports-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-reports-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-reports-chip.is-brand {
  color: #a78bfa;
}

.ym-reports-chip.is-live {
  color: #38bdf8;
}

.ym-reports-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-reports-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-reports-description {
  max-width: 54rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-reports-hero__summary {
  display: grid;
  min-width: min(100%, 220px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-reports-hero__summary span,
.ym-reports-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-reports-hero__summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
}

.ym-reports-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  padding: 1rem 1.15rem;
}

.ym-reports-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-reports-notice p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-reports-filter-card,
.ym-reports-result-card,
.ym-reports-data-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-reports-filter-card > header,
.ym-reports-data-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-reports-filter-card h2,
.ym-reports-data-card h2,
.ym-reports-access-state h2,
.ym-reports-empty h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-reports-filter-card p,
.ym-reports-data-card header p,
.ym-reports-access-state p,
.ym-reports-empty p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-reports-filter-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-reports-filter-grid label {
  display: grid;
  gap: 0.45rem;
}

.ym-reports-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-reports-filter-grid input,
.ym-reports-filter-grid select {
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

.ym-reports-filter-grid input:focus,
.ym-reports-filter-grid select:focus {
  border-color: #38bdf8;
  box-shadow: 0 0 0 3px color-mix(in srgb, #38bdf8 18%, transparent);
}

.ym-reports-filter-actions {
  display: flex;
  align-items: end;
}

.ym-reports-button {
  min-height: 42px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  padding: 0.6rem 0.9rem;
  transition: transform 160ms ease, opacity 160ms ease, border-color 160ms ease;
}

.ym-reports-button.is-primary {
  width: 100%;
  border-color: rgba(14, 165, 233, 0.55);
  background: linear-gradient(135deg, #0284c7, #4f46e5);
  color: #fff;
}

.ym-reports-button.is-secondary {
  background: var(--ym-control-bg);
}

.ym-reports-button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #38bdf8;
}

.ym-reports-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-reports-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.ym-reports-summary-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--report-accent) 15%, transparent), transparent 48%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1.1rem;
}

.ym-reports-summary-card span,
.ym-reports-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-reports-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-reports-empty {
  display: flex;
  align-items: center;
  gap: 1rem;
  border: 1px solid color-mix(in srgb, #f59e0b 35%, var(--ym-soft-border));
  border-radius: 22px;
  background: color-mix(in srgb, #f59e0b 9%, var(--ym-control-bg));
  padding: 1rem 1.15rem;
}

.ym-reports-empty > span {
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

.ym-reports-data-grid {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.4fr);
  gap: 1rem;
}

.ym-reports-data-card > header > span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
  padding: 0.45rem 0.75rem;
}

.ym-reports-role-list {
  display: grid;
  gap: 0.95rem;
}

.ym-reports-role-row > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.45rem;
}

.ym-reports-role-row code,
.ym-reports-series-table code {
  color: #38bdf8;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  font-weight: 850;
}

.ym-reports-role-row strong,
.ym-reports-series-table strong {
  color: var(--ym-text);
  font-weight: 950;
}

.ym-reports-track,
.ym-reports-series-bar {
  display: block;
  overflow: hidden;
  height: 0.5rem;
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-muted) 12%, transparent);
}

.ym-reports-track i,
.ym-reports-series-bar i {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #38bdf8, #8b5cf6);
}

.ym-reports-series-wrap {
  overflow-x: auto;
}

.ym-reports-series-table {
  width: 100%;
  min-width: 520px;
  border-collapse: collapse;
}

.ym-reports-series-table th,
.ym-reports-series-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-text);
  font-size: 13px;
  padding: 0.8rem 0.65rem;
  text-align: start;
}

.ym-reports-series-table th {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
}

.ym-reports-series-table .is-visual {
  width: 52%;
}

.ym-reports-series-bar {
  min-width: 9rem;
}

.ym-reports-inline-empty {
  display: grid;
  min-height: 11rem;
  place-items: center;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 850;
  text-align: center;
}

.ym-reports-generated {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-reports-generated time {
  color: var(--ym-text);
  font-weight: 900;
}

.ym-reports-state {
  display: grid;
  min-height: 13rem;
  place-items: center;
  align-content: center;
  gap: 0.8rem;
  color: var(--ym-muted);
  font-weight: 850;
  text-align: center;
}

.ym-reports-state.is-error {
  color: #f87171;
}

.ym-reports-spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 22%, transparent);
  border-top-color: #38bdf8;
  border-radius: 999px;
  animation: ym-reports-spin 800ms linear infinite;
}

.ym-reports-access-state {
  display: grid;
  min-height: 23rem;
  place-items: center;
  align-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.ym-reports-access-state__icon {
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

@keyframes ym-reports-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1180px) {
  .ym-reports-summary-grid,
  .ym-reports-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-reports-filter-actions {
    align-items: stretch;
  }

  .ym-reports-data-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 760px) {
  .ym-reports-hero__content,
  .ym-reports-filter-card > header,
  .ym-reports-data-card > header,
  .ym-reports-generated {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-reports-summary-grid,
  .ym-reports-filter-grid {
    grid-template-columns: 1fr;
  }

  .ym-reports-notice,
  .ym-reports-empty {
    align-items: flex-start;
  }
}

@media (max-width: 560px) {
  .ym-reports-series-table .is-visual {
    display: none;
  }

  .ym-reports-series-table {
    min-width: 100%;
  }
}
</style>
