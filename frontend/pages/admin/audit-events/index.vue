<template>
  <div class="ym-audit-page space-y-7">
    <section class="ym-audit-hero">
      <div class="ym-audit-hero__glow is-one" />
      <div class="ym-audit-hero__glow is-two" />

      <div class="ym-audit-hero__content">
        <div>
          <div class="ym-audit-chips">
            <span class="ym-audit-chip is-brand">Yemen Motion</span>
            <span class="ym-audit-chip is-readonly">{{ copy.readonly }}</span>
          </div>
          <p class="ym-audit-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-audit-description">{{ copy.description }}</p>
        </div>

        <div class="ym-audit-hero__summary">
          <span>{{ copy.totalEvents }}</span>
          <strong>{{ pagination.total }}</strong>
          <small>{{ copy.internalRecords }}</small>
        </div>
      </div>
    </section>

    <section v-if="forbidden" class="ym-audit-access-state" role="status">
      <span class="ym-audit-access-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-audit-notice" role="note">
        <span>{{ copy.readonly }}</span>
        <p>{{ copy.notice }}</p>
      </aside>

      <section class="ym-audit-summary-grid">
        <article v-for="card in summaryCards" :key="card.label" class="ym-audit-summary-card" :style="{ '--audit-accent': card.color }">
          <span>{{ card.label }}</span>
          <strong>{{ card.value }}</strong>
          <small>{{ card.subtitle }}</small>
        </article>
      </section>

      <section class="ym-audit-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button type="button" class="ym-audit-button is-secondary" @click="resetFilters">
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-audit-filter-grid" @submit.prevent="applyFilters">
          <label>
            <span>{{ copy.eventType }}</span>
            <input v-model.trim="filters.event_type" type="text" maxlength="120" placeholder="role.updated" />
          </label>

          <label>
            <span>{{ copy.category }}</span>
            <input v-model.trim="filters.category" type="text" maxlength="80" placeholder="roles" />
          </label>

          <label>
            <span>{{ copy.severity }}</span>
            <select v-model="filters.severity">
              <option value="">{{ copy.all }}</option>
              <option value="info">info</option>
              <option value="notice">notice</option>
              <option value="warning">warning</option>
              <option value="critical">critical</option>
            </select>
          </label>

          <label>
            <span>{{ copy.outcome }}</span>
            <select v-model="filters.outcome">
              <option value="">{{ copy.all }}</option>
              <option value="success">success</option>
              <option value="failed">failed</option>
              <option value="denied">denied</option>
            </select>
          </label>

          <label>
            <span>{{ copy.actorId }}</span>
            <input v-model="filters.actor_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.targetType }}</span>
            <input v-model.trim="filters.target_type" type="text" maxlength="80" placeholder="user" />
          </label>

          <label>
            <span>{{ copy.targetId }}</span>
            <input v-model="filters.target_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.from }}</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label>
            <span>{{ copy.to }}</span>
            <input v-model="filters.to" type="date" />
          </label>

          <label>
            <span>{{ copy.perPage }}</span>
            <select v-model.number="filters.per_page">
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </label>

          <div class="ym-audit-filter-actions">
            <button type="submit" class="ym-audit-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>
      </section>

      <section class="ym-audit-table-card">
        <header class="ym-audit-table-card__head">
          <div>
            <h2>{{ copy.tableTitle }}</h2>
            <p>{{ copy.tableCopy }}</p>
          </div>
          <span>{{ copy.currentCount }}: {{ events.length }}</span>
        </header>

        <div v-if="loading" class="ym-audit-state">
          <span class="ym-audit-spinner" aria-hidden="true" />
          <p>{{ copy.loading }}</p>
        </div>

        <div v-else-if="error" class="ym-audit-state is-error" role="alert">
          <p>{{ error }}</p>
          <button type="button" class="ym-audit-button is-secondary" @click="fetchAuditEvents">
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="events.length === 0" class="ym-audit-state">
          <p>{{ copy.empty }}</p>
        </div>

        <div v-else class="ym-audit-table-wrap">
          <table class="ym-audit-table">
            <thead>
              <tr>
                <th>{{ copy.event }}</th>
                <th>{{ copy.classification }}</th>
                <th>{{ copy.actor }}</th>
                <th>{{ copy.target }}</th>
                <th>{{ copy.action }}</th>
                <th>{{ copy.occurredAt }}</th>
                <th>{{ copy.metadata }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="event in events" :key="event.id">
                <td>
                  <code class="ym-audit-event-type" dir="ltr">{{ event.event_type }}</code>
                  <small>#{{ event.id }}</small>
                </td>
                <td>
                  <span class="ym-audit-category" dir="ltr">{{ event.category }}</span>
                  <div class="ym-audit-badges">
                    <span class="ym-audit-badge" :class="severityClass(event.severity)">{{ event.severity }}</span>
                    <span class="ym-audit-badge" :class="outcomeClass(event.outcome)">{{ event.outcome }}</span>
                  </div>
                </td>
                <td>
                  <strong dir="ltr">{{ displayValue(event.actor_role) }}</strong>
                  <small dir="ltr">{{ event.actor_type || '—' }} #{{ event.actor_id ?? '—' }}</small>
                </td>
                <td>
                  <strong dir="ltr">{{ displayValue(event.target_type) }}</strong>
                  <small dir="ltr">#{{ event.target_id ?? '—' }}</small>
                </td>
                <td>
                  <code class="ym-audit-action" dir="ltr">{{ displayValue(event.action) }}</code>
                </td>
                <td>
                  <time :datetime="event.occurred_at || undefined">{{ formatDate(event.occurred_at) }}</time>
                </td>
                <td>
                  <details class="ym-audit-metadata">
                    <summary>{{ copy.viewMetadata }}</summary>
                    <pre dir="ltr">{{ formatMetadata(event.metadata) }}</pre>
                  </details>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer v-if="!loading && pagination.last_page > 1" class="ym-audit-pagination">
          <button
            type="button"
            class="ym-audit-button is-secondary"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            {{ copy.previous }}
          </button>
          <span>{{ copy.pageInfo(pagination.current_page, pagination.last_page) }}</span>
          <button
            type="button"
            class="ym-audit-button is-secondary"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            {{ copy.next }}
          </button>
        </footer>
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
type PageSize = 15 | 25 | 50

type AuditEvent = {
  id: number
  event_type: string
  category: string
  severity: string
  actor_type: string | null
  actor_id: number | null
  actor_role: string | null
  target_type: string | null
  target_id: number | null
  action: string | null
  outcome: string
  ip_address: string | null
  user_agent: string | null
  request_id: string | null
  correlation_id: string | null
  metadata: Record<string, unknown> | unknown[] | null
  occurred_at: string | null
  created_at: string | null
}

type PaginatedAuditEvents = {
  data: AuditEvent[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  next_page_url: string | null
  prev_page_url: string | null
}

type AuditEventsResponse = {
  success: boolean
  data: PaginatedAuditEvents
  message?: string
  errors?: Record<string, string[]> | null
}

type AuditFilters = {
  event_type: string
  category: string
  severity: string
  outcome: string
  actor_id: string
  target_type: string
  target_id: string
  from: string
  to: string
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonly: 'قراءة فقط',
    kicker: 'الرقابة الداخلية',
    title: 'سجل التدقيق / Audit Events',
    description: 'عرض آمن ومنظم للأحداث الإدارية المسجلة، متاح للمدير الأعلى فقط.',
    totalEvents: 'إجمالي الأحداث',
    internalRecords: 'سجلات داخلية محمية',
    notice: 'لا توفر هذه الصفحة تعديل السجلات أو حذفها أو تصديرها، ولا ترتبط بالتقارير أو التحليلات.',
    forbiddenTitle: 'الوصول إلى سجل التدقيق غير متاح',
    forbiddenCopy: 'هذه الصفحة محصورة على المدير الأعلى. لم تتم محاولة تحميل أي سجلات لهذا الحساب.',
    filtersTitle: 'فلاتر السجل',
    filtersCopy: 'استخدم الحقول المسموحة في واجهة القراءة لتضييق النتائج.',
    eventType: 'نوع الحدث',
    category: 'التصنيف',
    severity: 'الأهمية',
    outcome: 'النتيجة',
    actorId: 'معرّف المنفذ',
    targetType: 'نوع الهدف',
    targetId: 'معرّف الهدف',
    from: 'من تاريخ',
    to: 'إلى تاريخ',
    perPage: 'لكل صفحة',
    all: 'الكل',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    tableTitle: 'الأحداث المسجلة',
    tableCopy: 'أحدث الأحداث أولًا، وفق ترتيب الخادم.',
    currentCount: 'في الصفحة الحالية',
    loading: 'يتم تحميل سجلات التدقيق...',
    empty: 'لا توجد أحداث مطابقة للفلاتر الحالية.',
    genericError: 'تعذر تحميل سجلات التدقيق. حاول مرة أخرى.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من القيم والتواريخ.',
    retry: 'إعادة المحاولة',
    event: 'الحدث',
    classification: 'التصنيف والحالة',
    actor: 'المنفذ',
    target: 'الهدف',
    action: 'الإجراء',
    occurredAt: 'وقت الحدث',
    metadata: 'البيانات الوصفية',
    viewMetadata: 'عرض JSON',
    currentPage: 'الصفحة الحالية',
    currentRows: 'نتائج الصفحة',
    liveData: 'من API القراءة',
    paginationState: 'حالة التصفح',
    previous: 'السابق',
    next: 'التالي',
    pageInfo: (page: number, last: number) => `الصفحة ${page} من ${last}`,
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.'
  },
  en: {
    readonly: 'Read-only',
    kicker: 'Internal oversight',
    title: 'Audit Events / سجل التدقيق',
    description: 'A safe, structured view of recorded administrative events, available to super-admin only.',
    totalEvents: 'Total events',
    internalRecords: 'Protected internal records',
    notice: 'This page does not edit, delete, or export events and is not connected to Reports or Analytics.',
    forbiddenTitle: 'Audit access is unavailable',
    forbiddenCopy: 'This page is restricted to super-admin. No audit data request was made for this account.',
    filtersTitle: 'Audit filters',
    filtersCopy: 'Use the read API allowlisted fields to narrow the results.',
    eventType: 'Event type',
    category: 'Category',
    severity: 'Severity',
    outcome: 'Outcome',
    actorId: 'Actor ID',
    targetType: 'Target type',
    targetId: 'Target ID',
    from: 'From',
    to: 'To',
    perPage: 'Per page',
    all: 'All',
    apply: 'Apply filters',
    reset: 'Reset',
    tableTitle: 'Recorded events',
    tableCopy: 'Newest events first, following server ordering.',
    currentCount: 'Current page',
    loading: 'Loading audit events...',
    empty: 'No events match the current filters.',
    genericError: 'Could not load audit events. Try again.',
    validationError: 'The filters could not be applied. Check values and dates.',
    retry: 'Retry',
    event: 'Event',
    classification: 'Classification & state',
    actor: 'Actor',
    target: 'Target',
    action: 'Action',
    occurredAt: 'Occurred at',
    metadata: 'Metadata',
    viewMetadata: 'View JSON',
    currentPage: 'Current page',
    currentRows: 'Page results',
    liveData: 'From the read API',
    paginationState: 'Pagination state',
    previous: 'Previous',
    next: 'Next',
    pageInfo: (page: number, last: number) => `Page ${page} of ${last}`,
    invalidDateRange: 'The end date must be the same as or after the start date.'
  }
}

const copy = computed(() => copyMap[currentLocale.value])
const events = ref<AuditEvent[]>([])
const loading = ref(false)
const forbidden = ref(false)
const error = ref<string | null>(null)
const page = ref(1)
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})
const filters = reactive<AuditFilters>({
  event_type: '',
  category: '',
  severity: '',
  outcome: '',
  actor_id: '',
  target_type: '',
  target_id: '',
  from: '',
  to: '',
  per_page: 15
})

const summaryCards = computed(() => [
  { label: copy.value.currentRows, value: events.value.length, subtitle: copy.value.liveData, color: '#38bdf8' },
  { label: copy.value.totalEvents, value: pagination.total, subtitle: copy.value.internalRecords, color: '#8b5cf6' },
  { label: copy.value.currentPage, value: pagination.current_page, subtitle: copy.value.paginationState, color: '#10b981' }
])

watch(filters, () => {
  page.value = 1
}, { deep: true })

function displayValue(value: string | null): string {
  return value || '—'
}

function formatDate(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

function formatMetadata(metadata: AuditEvent['metadata']): string {
  if (metadata === null) return '{}'

  // نحول metadata إلى نص JSON فقط، ويعرضه Vue عبر interpolation دون تفسير HTML.
  try {
    return JSON.stringify(metadata, null, 2) || '{}'
  } catch {
    return '{}'
  }
}

function severityClass(severity: string): string {
  if (['info', 'notice', 'warning', 'critical'].includes(severity)) return `is-${severity}`
  return 'is-neutral'
}

function outcomeClass(outcome: string): string {
  if (outcome === 'success') return 'is-success'
  if (['failed', 'failure'].includes(outcome)) return 'is-failed'
  if (['denied', 'blocked'].includes(outcome)) return 'is-denied'
  return 'is-neutral'
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
  const stringFilters: Array<[string, string]> = [
    ['event_type', filters.event_type],
    ['category', filters.category],
    ['severity', filters.severity],
    ['outcome', filters.outcome],
    ['actor_id', filters.actor_id],
    ['target_type', filters.target_type],
    ['target_id', filters.target_id],
    ['from', filters.from],
    ['to', filters.to]
  ]

  for (const [key, value] of stringFilters) {
    if (value.trim() !== '') params.set(key, value.trim())
  }

  params.set('per_page', String(filters.per_page))
  params.set('page', String(page.value))

  return `/admin/audit-events?${params.toString()}`
}

async function fetchAuditEvents(): Promise<void> {
  // نمنع الطلب من الواجهة مبكرًا، مع بقاء الخادم هو حارس التفويض النهائي.
  if (authStore.role !== 'super-admin') {
    forbidden.value = true
    return
  }

  if (filters.from && filters.to && filters.to < filters.from) {
    error.value = copy.value.invalidDateRange
    return
  }

  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AuditEventsResponse>(buildEndpoint())
    events.value = response.data?.data || []
    pagination.current_page = response.data?.current_page || 1
    pagination.last_page = response.data?.last_page || 1
    pagination.per_page = response.data?.per_page || filters.per_page
    pagination.total = response.data?.total || 0
  } catch (requestError: unknown) {
    const status = errorStatus(requestError)

    if (status === 403) {
      forbidden.value = true
      events.value = []
      return
    }

    error.value = status === 422 ? copy.value.validationError : copy.value.genericError
  } finally {
    loading.value = false
  }
}

function applyFilters(): void {
  page.value = 1
  void fetchAuditEvents()
}

function resetFilters(): void {
  Object.assign(filters, {
    event_type: '',
    category: '',
    severity: '',
    outcome: '',
    actor_id: '',
    target_type: '',
    target_id: '',
    from: '',
    to: '',
    per_page: 15
  })
  page.value = 1
  void fetchAuditEvents()
}

function changePage(nextPage: number): void {
  if (nextPage < 1 || nextPage > pagination.last_page || nextPage === pagination.current_page) return

  page.value = nextPage
  void fetchAuditEvents()
}

onMounted(() => {
  if (authStore.role !== 'super-admin') {
    forbidden.value = true
    return
  }

  void fetchAuditEvents()
})
</script>

<style scoped>
.ym-audit-page {
  color: var(--ym-text);
}

.ym-audit-hero,
.ym-audit-filter-card,
.ym-audit-table-card,
.ym-audit-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-audit-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-audit-hero::before {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(135deg, rgba(99, 102, 241, 0.16), transparent 45%),
    repeating-linear-gradient(90deg, transparent 0 42px, rgba(148, 163, 184, 0.04) 43px 44px);
  content: '';
  pointer-events: none;
}

.ym-audit-hero__glow {
  position: absolute;
  width: 18rem;
  height: 18rem;
  border-radius: 999px;
  filter: blur(14px);
  opacity: 0.28;
  pointer-events: none;
}

.ym-audit-hero__glow.is-one {
  inset-block-start: -9rem;
  inset-inline-start: -5rem;
  background: #8b5cf6;
}

.ym-audit-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #0ea5e9;
}

.ym-audit-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-audit-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-audit-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-audit-chip.is-brand {
  color: #a78bfa;
}

.ym-audit-chip.is-readonly {
  color: #38bdf8;
}

.ym-audit-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-audit-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-audit-description {
  max-width: 54rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-audit-hero__summary {
  display: grid;
  min-width: min(100%, 220px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-audit-hero__summary span,
.ym-audit-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-audit-hero__summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
}

.ym-audit-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  padding: 1rem 1.15rem;
}

.ym-audit-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-audit-notice p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-audit-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.ym-audit-summary-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--audit-accent) 15%, transparent), transparent 48%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1.1rem;
}

.ym-audit-summary-card span,
.ym-audit-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-audit-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-audit-filter-card,
.ym-audit-table-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-audit-filter-card > header,
.ym-audit-table-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-audit-filter-card h2,
.ym-audit-table-card h2,
.ym-audit-access-state h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-audit-filter-card p,
.ym-audit-table-card__head p,
.ym-audit-access-state p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-audit-filter-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-audit-filter-grid label {
  display: grid;
  gap: 0.45rem;
}

.ym-audit-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-audit-filter-grid input,
.ym-audit-filter-grid select {
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

.ym-audit-filter-grid input:focus,
.ym-audit-filter-grid select:focus {
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px color-mix(in srgb, #8b5cf6 18%, transparent);
}

.ym-audit-filter-actions {
  display: flex;
  align-items: end;
}

.ym-audit-button {
  min-height: 42px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  padding: 0.6rem 0.9rem;
  transition: transform 160ms ease, opacity 160ms ease, border-color 160ms ease;
}

.ym-audit-button.is-primary {
  width: 100%;
  border-color: rgba(139, 92, 246, 0.55);
  background: linear-gradient(135deg, #7c3aed, #4f46e5);
  color: #fff;
}

.ym-audit-button.is-secondary {
  background: var(--ym-control-bg);
}

.ym-audit-button:hover:not(:disabled) {
  transform: translateY(-1px);
  border-color: #8b5cf6;
}

.ym-audit-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-audit-table-card__head > span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
  padding: 0.45rem 0.75rem;
}

.ym-audit-state {
  display: grid;
  min-height: 11rem;
  place-items: center;
  gap: 0.8rem;
  color: var(--ym-muted);
  font-weight: 850;
  text-align: center;
}

.ym-audit-state.is-error {
  color: #f87171;
}

.ym-audit-spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 22%, transparent);
  border-top-color: #8b5cf6;
  border-radius: 999px;
  animation: ym-audit-spin 800ms linear infinite;
}

.ym-audit-table-wrap {
  overflow-x: auto;
}

.ym-audit-table {
  width: 100%;
  min-width: 1120px;
  border-collapse: separate;
  border-spacing: 0;
}

.ym-audit-table th,
.ym-audit-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-text);
  font-size: 13px;
  padding: 0.9rem 0.75rem;
  text-align: start;
  vertical-align: top;
}

.ym-audit-table th {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  white-space: nowrap;
}

.ym-audit-table td small {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 800;
  margin-top: 0.35rem;
}

.ym-audit-table td strong {
  font-weight: 900;
}

.ym-audit-event-type,
.ym-audit-action,
.ym-audit-category {
  color: #38bdf8;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 12px;
  font-weight: 850;
}

.ym-audit-event-type {
  display: block;
  max-width: 16rem;
  overflow-wrap: anywhere;
}

.ym-audit-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  margin-top: 0.5rem;
}

.ym-audit-badge {
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-muted) 12%, transparent);
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 950;
  padding: 0.28rem 0.48rem;
}

.ym-audit-badge.is-info,
.ym-audit-badge.is-notice {
  background: color-mix(in srgb, #38bdf8 16%, transparent);
  color: #38bdf8;
}

.ym-audit-badge.is-warning,
.ym-audit-badge.is-denied {
  background: color-mix(in srgb, #f59e0b 17%, transparent);
  color: #f59e0b;
}

.ym-audit-badge.is-critical,
.ym-audit-badge.is-failed {
  background: color-mix(in srgb, #ef4444 17%, transparent);
  color: #ef4444;
}

.ym-audit-badge.is-success {
  background: color-mix(in srgb, #10b981 17%, transparent);
  color: #10b981;
}

.ym-audit-metadata summary {
  cursor: pointer;
  color: #a78bfa;
  font-size: 12px;
  font-weight: 900;
}

.ym-audit-metadata pre {
  max-width: 30rem;
  max-height: 15rem;
  overflow: auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--ym-control-bg) 88%, #020617);
  color: var(--ym-text);
  font-size: 11px;
  line-height: 1.65;
  margin: 0.65rem 0 0;
  padding: 0.8rem;
  text-align: left;
  white-space: pre-wrap;
  word-break: break-word;
}

.ym-audit-pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
  padding-top: 1.25rem;
}

.ym-audit-access-state {
  display: grid;
  min-height: 23rem;
  place-items: center;
  align-content: center;
  gap: 0.75rem;
  padding: 2rem;
  text-align: center;
}

.ym-audit-access-state__icon {
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

@keyframes ym-audit-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1180px) {
  .ym-audit-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .ym-audit-hero__content,
  .ym-audit-filter-card > header,
  .ym-audit-table-card__head {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-audit-summary-grid,
  .ym-audit-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .ym-audit-summary-grid,
  .ym-audit-filter-grid {
    grid-template-columns: 1fr;
  }

  .ym-audit-notice,
  .ym-audit-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-audit-pagination .ym-audit-button {
    width: 100%;
  }
}
</style>
