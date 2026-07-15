<template>
  <div class="ym-works-activity-page space-y-7" dir="rtl">
    <section class="ym-works-activity-hero">
      <div class="ym-works-activity-hero__glow is-one" />
      <div class="ym-works-activity-hero__glow is-two" />
      <div class="ym-works-activity-hero__grid" aria-hidden="true" />

      <div class="ym-works-activity-hero__content">
        <div>
          <div class="ym-works-activity-chips">
            <span class="ym-works-activity-chip is-brand">Yemen Motion</span>
            <span class="ym-works-activity-chip is-readonly">قراءة تنظيمية فقط</span>
          </div>
          <p class="ym-works-activity-kicker">متابعة دورة حياة الأعمال</p>
          <h1>سجل الأعمال</h1>
          <p class="ym-works-activity-description">
            قراءة تنظيمية لأحداث دورة حياة الأعمال، مرتبة حسب وقت الحدث ومهيأة للبحث والتصفية.
          </p>
        </div>

        <div class="ym-works-activity-hero__summary">
          <span>إجمالي الأحداث</span>
          <strong>{{ formatNumber(summary?.total_events ?? 0) }}</strong>
          <small>ضمن الفلاتر الحالية</small>
        </div>
      </div>
    </section>

    <section v-if="authPending" class="ym-works-activity-access-state" role="status" aria-live="polite">
      <span class="ym-works-activity-spinner" aria-hidden="true" />
      <h2>جارٍ التحقق من صلاحية سجل الأعمال</h2>
      <p>ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-activity-access-state is-forbidden" role="status">
      <span class="ym-works-activity-state__icon" aria-hidden="true">!</span>
      <h2>الوصول إلى سجل الأعمال غير متاح</h2>
      <p>لا يملك هذا الحساب الصلاحيات المطلوبة. لم تتم محاولة تحميل بيانات الصفحة.</p>
    </section>

    <template v-else>
      <section class="ym-works-activity-notices" aria-label="ملاحظات سجل الأعمال">
        <aside class="ym-works-activity-notice" role="note">
          <span>مصدر السجل</span>
          <p>هذا السجل مشتق من تواريخ دورة حياة الأعمال، وليس من جدول سجل مستقل.</p>
        </aside>
        <aside class="ym-works-activity-notice is-restriction" role="note">
          <span>للقراءة فقط</span>
          <p>لا توجد إجراءات اعتماد أو رفض أو نشر أو إخفاء أو أرشفة أو استعادة في هذه المرحلة.</p>
        </aside>
      </section>

      <aside
        v-if="activitySource && !activitySource.dedicated_log_available"
        class="ym-works-activity-source"
        role="note"
      >
        <span class="ym-works-activity-source__icon" aria-hidden="true">i</span>
        <div>
          <h2>السجل المستقل غير متاح حاليًا</h2>
          <dl>
            <div>
              <dt>المصدر</dt>
              <dd><code dir="ltr">{{ activitySource.source }}</code></dd>
            </div>
            <div>
              <dt>السبب</dt>
              <dd>{{ activitySource.reason }}</dd>
            </div>
          </dl>
        </div>
      </aside>

      <section v-if="summary" class="ym-works-activity-summary-grid" aria-label="ملخص سجل الأعمال">
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="ym-works-activity-summary-card"
          :class="card.tone"
          :style="{ '--activity-accent': card.color }"
        >
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-works-activity-filter-card">
        <header>
          <div>
            <h2>البحث والفلاتر</h2>
            <p>ضيّق سجل الأحداث باستخدام معاملات القائمة المعتمدة فقط.</p>
          </div>
          <button
            type="button"
            class="ym-works-activity-button is-secondary"
            :disabled="loading"
            @click="resetFilters"
          >
            إعادة ضبط
          </button>
        </header>

        <form class="ym-works-activity-filter-grid" @submit.prevent="applyFilters">
          <label class="is-search">
            <span>البحث</span>
            <input
              v-model.trim="filters.q"
              type="search"
              minlength="2"
              maxlength="80"
              placeholder="العنوان أو slug"
              autocomplete="off"
            />
            <small>حرفان على الأقل، وبحد أقصى 80 حرفًا.</small>
          </label>

          <label>
            <span>نوع الحدث</span>
            <select v-model="filters.event_type">
              <option value="">الكل</option>
              <option v-for="option in eventOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>الحالة</span>
            <select v-model="filters.status">
              <option value="">الكل</option>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>الظهور</span>
            <select v-model="filters.visibility_status">
              <option value="">الكل</option>
              <option value="public">عام</option>
              <option value="hidden">مخفي</option>
            </select>
          </label>

          <label>
            <span>نوع الوسائط</span>
            <input v-model.trim="filters.media_type" type="text" maxlength="40" placeholder="image" dir="ltr" />
          </label>

          <label>
            <span>معرّف المصمم</span>
            <input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>معرّف المراجع</span>
            <input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>معرّف التصنيف</span>
            <input v-model="filters.category_id" type="number" inputmode="numeric" />
          </label>

          <label>
            <span>عليه بلاغات</span>
            <select v-model="filters.reported">
              <option v-for="option in booleanOptions" :key="`reported-${option.value}`" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>مروّج</span>
            <select v-model="filters.promoted">
              <option v-for="option in booleanOptions" :key="`promoted-${option.value}`" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>من وقت الحدث</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label>
            <span>إلى وقت الحدث</span>
            <input v-model="filters.to" type="date" />
          </label>

          <label>
            <span>لكل صفحة</span>
            <select v-model.number="filters.per_page">
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </label>

          <div class="ym-works-activity-filter-actions">
            <button type="submit" class="ym-works-activity-button is-primary" :disabled="loading">
              تطبيق
            </button>
          </div>
        </form>

        <p v-if="filterError" class="ym-works-activity-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-works-activity-table-card">
        <header class="ym-works-activity-table-card__head">
          <div>
            <h2>أحداث سجل الأعمال</h2>
            <p>اضغط على رؤوس الأعمدة القابلة للفرز، وافتح ملخص الحدث من إجراء القراءة الوحيد.</p>
          </div>
          <div class="ym-works-activity-table-state">
            <span>الصفحة الحالية</span>
            <strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong>
          </div>
        </header>

        <div v-if="loading" class="ym-works-activity-state" role="status" aria-live="polite">
          <span class="ym-works-activity-spinner" aria-hidden="true" />
          <h3>جارٍ تحميل سجل الأعمال</h3>
          <p>يتم جلب الأحداث وفق الفلاتر الحالية.</p>
        </div>

        <div v-else-if="error" class="ym-works-activity-state is-error" role="alert">
          <span class="ym-works-activity-state__icon" aria-hidden="true">!</span>
          <h3>تعذر تحميل سجل الأعمال</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-activity-button is-secondary" @click="fetchActivity">
            إعادة المحاولة
          </button>
        </div>

        <div v-else-if="hasLoaded && items.length === 0" class="ym-works-activity-state" role="status">
          <span class="ym-works-activity-empty-icon" aria-hidden="true">0</span>
          <h3>لا توجد أحداث مطابقة</h3>
          <p>جرّب تعديل الفلاتر أو إعادة ضبطها لعرض أحداث أخرى.</p>
        </div>

        <div v-else-if="items.length > 0" class="ym-works-activity-table-wrap">
          <table class="ym-works-activity-table">
            <thead>
              <tr>
                <th>نوع الحدث</th>
                <th>تسمية الحدث</th>
                <th>
                  <button type="button" class="ym-works-activity-sort" @click="changeSort('event_at')">
                    وقت الحدث <span aria-hidden="true">{{ sortIndicator('event_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-activity-sort" @click="changeSort('work_id')">
                    رقم العمل <span aria-hidden="true">{{ sortIndicator('work_id') }}</span>
                  </button>
                </th>
                <th class="is-title">
                  <button type="button" class="ym-works-activity-sort" @click="changeSort('title')">
                    العنوان <span aria-hidden="true">{{ sortIndicator('title') }}</span>
                  </button>
                </th>
                <th>slug</th>
                <th>
                  <button type="button" class="ym-works-activity-sort" @click="changeSort('status')">
                    الحالة <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                  </button>
                </th>
                <th>الظهور</th>
                <th>نوع الوسائط</th>
                <th>المصمم</th>
                <th>المراجع</th>
                <th>التصنيف</th>
                <th>
                  <button type="button" class="ym-works-activity-sort" @click="changeSort('reports_count')">
                    البلاغات <span aria-hidden="true">{{ sortIndicator('reports_count') }}</span>
                  </button>
                </th>
                <th>المشاهدات</th>
                <th>الإعجابات</th>
                <th>حدث مراجعة</th>
                <th>حدث ظهور</th>
                <th>عليه بلاغات</th>
                <th>مروّج</th>
                <th>يحتاج انتباه</th>
                <th class="is-action">إجراء القراءة</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="event in items" :key="event.id" :class="{ 'needs-attention': event.activity_flags.needs_attention }">
                <td>
                  <span class="ym-works-activity-badge is-event" :class="eventClass(event.event_type)">
                    {{ eventTypeLabel(event.event_type) }}
                  </span>
                </td>
                <td><strong class="ym-works-activity-event-label">{{ event.event_label }}</strong></td>
                <td><time :datetime="event.event_at">{{ formatDateTime(event.event_at) }}</time></td>
                <td><code dir="ltr">#{{ event.work_id }}</code></td>
                <td class="is-title"><strong :dir="textDirection(event.title)">{{ event.title }}</strong></td>
                <td><code class="ym-works-activity-slug" dir="ltr">{{ event.slug }}</code></td>
                <td>
                  <span class="ym-works-activity-badge" :class="statusClass(event.status)">
                    {{ statusLabel(event.status) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-activity-badge" :class="visibilityClass(event.visibility_status)">
                    {{ visibilityLabel(event.visibility_status) }}
                  </span>
                </td>
                <td><code dir="ltr">{{ displayValue(event.media_type) }}</code></td>
                <td><PersonReference :person="event.designer" /></td>
                <td><PersonReference :person="event.reviewer" /></td>
                <td><span dir="ltr">{{ event.category_id ?? '—' }}</span></td>
                <td>
                  <span class="ym-works-activity-count" :class="{ 'is-alert': event.reports_count > 0 }">
                    {{ formatNumber(event.reports_count) }}
                  </span>
                </td>
                <td><span class="ym-works-activity-count">{{ formatNumber(event.views_count) }}</span></td>
                <td><span class="ym-works-activity-count">{{ formatNumber(event.likes_count) }}</span></td>
                <td><FlagBadge :active="event.activity_flags.is_review_event" tone="review" /></td>
                <td><FlagBadge :active="event.activity_flags.is_visibility_event" tone="visibility" /></td>
                <td><FlagBadge :active="event.activity_flags.is_reported" tone="reported" /></td>
                <td><FlagBadge :active="event.activity_flags.is_promoted" tone="promoted" /></td>
                <td><FlagBadge :active="event.activity_flags.needs_attention" tone="attention" /></td>
                <td class="is-action">
                  <button type="button" class="ym-works-activity-details-button" @click="openSummary(event)">
                    عرض ملخص الحدث
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer v-if="hasLoaded && !error" class="ym-works-activity-pagination">
          <div>
            <span>إجمالي الأحداث</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} عنصر ظاهر الآن</small>
          </div>
          <nav aria-label="التنقل بين صفحات سجل الأعمال">
            <button
              type="button"
              class="ym-works-activity-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              السابق
            </button>
            <span>الصفحة {{ formatNumber(pagination.current_page) }} من {{ formatNumber(pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-works-activity-button is-secondary"
              :disabled="loading || pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
            >
              التالي
            </button>
          </nav>
        </footer>
      </section>
    </template>

    <div v-if="drawerOpen && selectedEvent" class="ym-activity-detail-backdrop" @click.self="closeSummary">
      <section class="ym-activity-detail-drawer" role="dialog" aria-modal="true" aria-labelledby="ym-activity-detail-title">
        <header class="ym-activity-detail-drawer__head">
          <div>
            <span>ملخص محلي للقراءة فقط</span>
            <h2 id="ym-activity-detail-title">ملخص الحدث</h2>
            <code dir="ltr">{{ selectedEvent.id }}</code>
          </div>
          <button type="button" class="ym-activity-detail-drawer__close" aria-label="إغلاق الملخص" @click="closeSummary">×</button>
        </header>

        <div class="ym-activity-detail-content">
          <section class="ym-activity-detail-intro">
            <div>
              <span class="ym-works-activity-badge is-event" :class="eventClass(selectedEvent.event_type)">
                {{ eventTypeLabel(selectedEvent.event_type) }}
              </span>
              <span class="ym-works-activity-badge" :class="statusClass(selectedEvent.status)">
                {{ statusLabel(selectedEvent.status) }}
              </span>
              <span class="ym-works-activity-badge" :class="visibilityClass(selectedEvent.visibility_status)">
                {{ visibilityLabel(selectedEvent.visibility_status) }}
              </span>
            </div>
            <h3 :dir="textDirection(selectedEvent.title)">{{ selectedEvent.title }}</h3>
            <code dir="ltr">{{ selectedEvent.slug }}</code>
          </section>

          <section class="ym-activity-detail-section">
            <h3>بيانات الحدث والعمل</h3>
            <dl class="ym-activity-detail-grid">
              <div><dt>معرّف الحدث</dt><dd><code dir="ltr">{{ selectedEvent.id }}</code></dd></div>
              <div><dt>رقم العمل</dt><dd dir="ltr">#{{ selectedEvent.work_id }}</dd></div>
              <div><dt>نوع الحدث</dt><dd><code dir="ltr">{{ selectedEvent.event_type }}</code></dd></div>
              <div><dt>تسمية الحدث</dt><dd>{{ selectedEvent.event_label }}</dd></div>
              <div><dt>وقت الحدث</dt><dd><time :datetime="selectedEvent.event_at">{{ formatDateTime(selectedEvent.event_at) }}</time></dd></div>
              <div><dt>الحالة</dt><dd>{{ statusLabel(selectedEvent.status) }}</dd></div>
              <div><dt>الظهور</dt><dd>{{ visibilityLabel(selectedEvent.visibility_status) }}</dd></div>
              <div><dt>نوع الوسائط</dt><dd><code dir="ltr">{{ displayValue(selectedEvent.media_type) }}</code></dd></div>
              <div>
                <dt>التصنيف</dt>
                <dd v-if="selectedEvent.category_id !== null" dir="ltr">{{ selectedEvent.category_id }}</dd>
                <dd v-else>غير مصنف</dd>
              </div>
              <div><dt>البلاغات</dt><dd>{{ formatNumber(selectedEvent.reports_count) }}</dd></div>
              <div><dt>المشاهدات</dt><dd>{{ formatNumber(selectedEvent.views_count) }}</dd></div>
              <div><dt>الإعجابات</dt><dd>{{ formatNumber(selectedEvent.likes_count) }}</dd></div>
            </dl>
          </section>

          <section class="ym-activity-detail-section">
            <h3>الارتباطات</h3>
            <div class="ym-activity-detail-people">
              <article>
                <span>المصمم</span>
                <template v-if="selectedEvent.designer">
                  <strong :dir="textDirection(selectedEvent.designer.name)">{{ selectedEvent.designer.name }}</strong>
                  <small dir="ltr">#{{ selectedEvent.designer.id }}</small>
                </template>
                <strong v-else>غير مرتبط</strong>
              </article>
              <article>
                <span>المراجع</span>
                <template v-if="selectedEvent.reviewer">
                  <strong :dir="textDirection(selectedEvent.reviewer.name)">{{ selectedEvent.reviewer.name }}</strong>
                  <small dir="ltr">#{{ selectedEvent.reviewer.id }}</small>
                </template>
                <strong v-else>غير مرتبط</strong>
              </article>
            </div>
          </section>

          <section class="ym-activity-detail-section">
            <h3>مؤشرات النشاط</h3>
            <div class="ym-activity-detail-flags">
              <span><small>حدث مراجعة</small><FlagBadge :active="selectedEvent.activity_flags.is_review_event" tone="review" /></span>
              <span><small>حدث ظهور</small><FlagBadge :active="selectedEvent.activity_flags.is_visibility_event" tone="visibility" /></span>
              <span><small>عليه بلاغات</small><FlagBadge :active="selectedEvent.activity_flags.is_reported" tone="reported" /></span>
              <span><small>مروّج</small><FlagBadge :active="selectedEvent.activity_flags.is_promoted" tone="promoted" /></span>
              <span><small>يحتاج انتباه</small><FlagBadge :active="selectedEvent.activity_flags.needs_attention" tone="attention" /></span>
            </div>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, reactive, ref, watch, type PropType } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type EventType = 'created' | 'updated' | 'submitted' | 'reviewed' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = 'public' | 'hidden'
type BooleanFilter = '' | '1' | '0'
type PageSize = 15 | 25 | 50
type SortDirection = 'asc' | 'desc'
type ActivitySortKey = 'event_at' | 'work_id' | 'title' | 'status' | 'reports_count'
type FlagTone = 'review' | 'visibility' | 'reported' | 'promoted' | 'attention'

interface UserReference {
  id: number
  name: string
}

interface ActivityFlags {
  is_review_event: boolean
  is_visibility_event: boolean
  is_reported: boolean
  is_promoted: boolean
  needs_attention: boolean
}

interface ActivityItem {
  id: string
  work_id: number
  event_type: EventType
  event_label: string
  event_at: string
  title: string
  slug: string
  status: WorkStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  designer: UserReference | null
  reviewer: UserReference | null
  category_id: number | null
  reports_count: number
  views_count: number
  likes_count: number
  activity_flags: ActivityFlags
}

interface ActivitySummary {
  total_events: number
  unique_works: number
  created_events: number
  updated_events: number
  submitted_events: number
  reviewed_events: number
  approved_events: number
  published_events: number
  rejected_events: number
  hidden_events: number
  archived_events: number
  review_events: number
  visibility_events: number
  reported_events: number
  promoted_events: number
}

interface ActivitySource {
  dedicated_log_available: boolean
  source: string
  reason: string
}

interface ActivityPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface ActivityData {
  items: ActivityItem[]
  pagination: ActivityPagination
  summary: ActivitySummary
  filters: Record<string, unknown>
  activity_source: ActivitySource
}

interface ActivityResponse {
  success: boolean
  data: ActivityData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface ActivityFilters {
  q: string
  event_type: '' | EventType
  status: '' | WorkStatus
  visibility_status: '' | VisibilityStatus
  media_type: string
  designer_id: string
  reviewer_id: string
  category_id: string
  reported: BooleanFilter
  promoted: BooleanFilter
  from: string
  to: string
  sort: ActivitySortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')

const PersonReference = defineComponent({
  props: { person: { type: Object as PropType<UserReference | null>, default: null } },
  setup(props) {
    return () => props.person
      ? h('span', { class: 'ym-works-activity-person' }, [
          h('strong', { dir: textDirection(props.person.name) }, props.person.name),
          h('small', { dir: 'ltr' }, `#${props.person.id}`)
        ])
      : h('span', 'غير مرتبط')
  }
})

const FlagBadge = defineComponent({
  props: {
    active: { type: Boolean, required: true },
    tone: { type: String as () => FlagTone, required: true }
  },
  setup(props) {
    return () => h('span', {
      class: ['ym-works-activity-flag', `is-${props.tone}`, props.active ? 'is-active' : 'is-inactive']
    }, props.active ? 'نعم' : 'لا')
  }
})

const authPending = computed(() => !authStore.isInitialized)
const hasActivityAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.activity.view')
    && authStore.permissions.includes('admin.works.activity.list')
})
const serverForbidden = ref(false)
const forbidden = computed(() => authStore.isInitialized && (!hasActivityAccess.value || serverForbidden.value))

const items = ref<ActivityItem[]>([])
const summary = ref<ActivitySummary | null>(null)
const activitySource = ref<ActivitySource | null>(null)
const pagination = reactive<ActivityPagination>({ current_page: 1, per_page: 15, total: 0, last_page: 1 })
const page = ref(1)
const loading = ref(false)
const hasLoaded = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)
const drawerOpen = ref(false)
const selectedEvent = ref<ActivityItem | null>(null)

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

function defaultFilters(): ActivityFilters {
  return {
    q: '', event_type: '', status: '', visibility_status: '', media_type: '', designer_id: '', reviewer_id: '',
    category_id: '', reported: '', promoted: '', from: '', to: '', sort: 'event_at', direction: 'desc', per_page: 15
  }
}

const filters = reactive<ActivityFilters>(defaultFilters())
const appliedFilters = reactive<ActivityFilters>(defaultFilters())

const eventOptions = (['created', 'updated', 'submitted', 'reviewed', 'approved', 'published', 'rejected', 'hidden', 'archived'] as EventType[])
  .map(value => ({ value, label: eventTypeLabel(value) }))
const statusOptions = (['draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'published', 'rejected', 'hidden', 'archived'] as WorkStatus[])
  .map(value => ({ value, label: statusLabel(value) }))
const booleanOptions: Array<{ value: BooleanFilter; label: string }> = [
  { value: '', label: 'الكل' }, { value: '1', label: 'نعم' }, { value: '0', label: 'لا' }
]

const summaryCards = computed(() => {
  const data = summary.value
  if (!data) return []

  return [
    { key: 'total_events', label: 'إجمالي الأحداث', value: data.total_events, hint: 'كل الأحداث المطابقة', color: '#8b5cf6', tone: '' },
    { key: 'unique_works', label: 'الأعمال الفريدة', value: data.unique_works, hint: 'عدد الأعمال المختلفة', color: '#38bdf8', tone: '' },
    { key: 'created_events', label: 'أحداث الإنشاء', value: data.created_events, hint: 'إنشاء العمل', color: '#22c55e', tone: '' },
    { key: 'updated_events', label: 'أحداث التحديث', value: data.updated_events, hint: 'تحديث العمل', color: '#60a5fa', tone: '' },
    { key: 'submitted_events', label: 'أحداث الإرسال', value: data.submitted_events, hint: 'إرسال للمراجعة', color: '#06b6d4', tone: '' },
    { key: 'reviewed_events', label: 'أحداث المراجعة', value: data.reviewed_events, hint: 'مراجعة العمل', color: '#a78bfa', tone: '' },
    { key: 'approved_events', label: 'أحداث الاعتماد', value: data.approved_events, hint: 'اعتماد العمل', color: '#10b981', tone: '' },
    { key: 'published_events', label: 'أحداث النشر', value: data.published_events, hint: 'نشر العمل', color: '#34d399', tone: '' },
    { key: 'rejected_events', label: 'أحداث الرفض', value: data.rejected_events, hint: 'تحتاج انتباهًا', color: '#f43f5e', tone: 'is-alert' },
    { key: 'hidden_events', label: 'أحداث الإخفاء', value: data.hidden_events, hint: 'تغيير الظهور', color: '#fb7185', tone: 'is-alert' },
    { key: 'archived_events', label: 'أحداث الأرشفة', value: data.archived_events, hint: 'تحتاج انتباهًا', color: '#f97316', tone: 'is-alert' },
    { key: 'review_events', label: 'أحداث مسار المراجعة', value: data.review_events, hint: 'مجموعة أحداث المراجعة', color: '#c084fc', tone: 'is-review' },
    { key: 'visibility_events', label: 'أحداث الظهور', value: data.visibility_events, hint: 'مجموعة أحداث الظهور', color: '#2dd4bf', tone: 'is-visibility' },
    { key: 'reported_events', label: 'أحداث عليها بلاغات', value: data.reported_events, hint: 'مرتبطة بأعمال مبلّغ عنها', color: '#ef4444', tone: 'is-alert' },
    { key: 'promoted_events', label: 'أحداث لأعمال مروّجة', value: data.promoted_events, hint: 'مميزة أو مثبتة', color: '#f59e0b', tone: 'is-promoted' }
  ]
})

function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(value)
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium', timeStyle: 'short'
  }).format(date)
}

function textDirection(value: string | null | undefined): 'rtl' | 'ltr' {
  return /[\u0600-\u06FF]/.test(String(value ?? '')) ? 'rtl' : 'ltr'
}

function displayValue(value: string | null): string {
  return value === null || value.trim() === '' ? '—' : value
}

function eventTypeLabel(value: EventType): string {
  return ({ created: 'إنشاء', updated: 'تحديث', submitted: 'إرسال', reviewed: 'مراجعة', approved: 'اعتماد', published: 'نشر', rejected: 'رفض', hidden: 'إخفاء', archived: 'أرشفة' })[value]
}

function statusLabel(value: WorkStatus): string {
  return ({ draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' })[value]
}

function visibilityLabel(value: VisibilityStatus): string {
  return value === 'public' ? 'عام' : 'مخفي'
}

function eventClass(value: EventType): string { return `is-${value.replaceAll('_', '-')}` }
function statusClass(value: WorkStatus): string { return `is-${value.replaceAll('_', '-')}` }
function visibilityClass(value: VisibilityStatus): string { return `is-${value}` }

function sortIndicator(key: ActivitySortKey): string {
  if (appliedFilters.sort !== key) return '↕'
  return appliedFilters.direction === 'asc' ? '↑' : '↓'
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
  const query = filters.q.trim()
  if (query.length === 1) {
    filterError.value = 'نص البحث يجب أن يكون فارغًا أو يحتوي حرفين على الأقل.'
    return false
  }
  if (filters.from && filters.to && filters.to < filters.from) {
    filterError.value = 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.'
    return false
  }

  const identifiers = [filters.designer_id, filters.reviewer_id, filters.category_id]
  if (identifiers.some((value) => value.trim() !== '' && (!Number.isInteger(Number(value)) || Number(value) < 1))) {
    filterError.value = 'معرّفات المصمم والمراجع والتصنيف يجب أن تكون أعدادًا صحيحة موجبة.'
    return false
  }
  return true
}

function buildListQuery(): Record<string, string | number> {
  const query: Record<string, string | number> = {
    sort: appliedFilters.sort,
    direction: appliedFilters.direction,
    page: page.value,
    per_page: appliedFilters.per_page
  }
  const optionalFilters: Array<[string, string]> = [
    ['q', appliedFilters.q.trim()], ['event_type', appliedFilters.event_type], ['status', appliedFilters.status],
    ['visibility_status', appliedFilters.visibility_status], ['media_type', appliedFilters.media_type.trim()],
    ['designer_id', appliedFilters.designer_id.trim()], ['reviewer_id', appliedFilters.reviewer_id.trim()],
    ['category_id', appliedFilters.category_id.trim()], ['reported', appliedFilters.reported],
    ['promoted', appliedFilters.promoted], ['from', appliedFilters.from], ['to', appliedFilters.to]
  ]
  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }
  return query
}

async function fetchActivity(): Promise<void> {
  if (!authStore.isInitialized || !hasActivityAccess.value) return
  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++requestRevision
  loading.value = true
  error.value = null
  filterError.value = null

  try {
    const response = await apiFetch<ActivityResponse>('/admin/works/activity', { query: buildListQuery() })
    if (requestAccessRevision !== accessRevision || currentRequestRevision !== requestRevision || !hasActivityAccess.value) return
    if (!response.success || !response.data) {
      clearActivityData()
      error.value = 'حدث خطأ أثناء تحميل سجل الأعمال. حاول مرة أخرى.'
      return
    }
    items.value = response.data.items
    summary.value = response.data.summary
    activitySource.value = response.data.activity_source
    Object.assign(pagination, response.data.pagination)
    page.value = response.data.pagination.current_page
    hasLoaded.value = true
    serverForbidden.value = false
  } catch (requestError: unknown) {
    if (requestAccessRevision !== accessRevision || currentRequestRevision !== requestRevision || !hasActivityAccess.value) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) {
      serverForbidden.value = true
      clearActivityData()
      closeSummary()
      return
    }
    if (status === 422) {
      filterError.value = 'تعذر تطبيق الفلاتر. تحقق من البحث والقيم والتواريخ.'
      return
    }
    error.value = 'حدث خطأ أثناء تحميل سجل الأعمال. حاول مرة أخرى.'
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === requestRevision) loading.value = false
  }
}

function applyFilters(): void {
  if (!validateFilters()) return
  Object.assign(appliedFilters, filters)
  page.value = 1
  closeSummary()
  void fetchActivity()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
  closeSummary()
  void fetchActivity()
}

function changeSort(key: ActivitySortKey): void {
  if (appliedFilters.sort === key) appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'
  else {
    appliedFilters.sort = key
    appliedFilters.direction = ['title', 'status', 'work_id'].includes(key) ? 'asc' : 'desc'
  }
  filters.sort = appliedFilters.sort
  filters.direction = appliedFilters.direction
  page.value = 1
  closeSummary()
  void fetchActivity()
}

function changePage(nextPage: number): void {
  if (nextPage < 1 || nextPage > pagination.last_page || nextPage === pagination.current_page || loading.value) return
  page.value = nextPage
  closeSummary()
  void fetchActivity()
}

function openSummary(event: ActivityItem): void {
  selectedEvent.value = event
  drawerOpen.value = true
}

function closeSummary(): void {
  drawerOpen.value = false
  selectedEvent.value = null
}

function clearActivityData(): void {
  items.value = []
  summary.value = null
  activitySource.value = null
  Object.assign(pagination, { current_page: 1, per_page: appliedFilters.per_page, total: 0, last_page: 1 })
  page.value = 1
  hasLoaded.value = false
}

function clearPageState(): void {
  requestRevision += 1
  clearActivityData()
  loading.value = false
  error.value = null
  filterError.value = null
  closeSummary()
}

function syncActivityAccessState(): void {
  if (!pageMounted) return
  accessRevision += 1
  serverForbidden.value = false
  closeSummary()
  if (!authStore.isInitialized || !hasActivityAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }
  if (loadedAuthorizationSignature === authorizationSignature.value) return
  loadedAuthorizationSignature = authorizationSignature.value
  void fetchActivity()
}

watch(authorizationSignature, () => syncActivityAccessState(), { flush: 'post' })
onMounted(() => {
  pageMounted = true
  syncActivityAccessState()
})
</script>

<style scoped>
.ym-works-activity-page { color: var(--ym-text); }
.ym-works-activity-hero,
.ym-works-activity-filter-card,
.ym-works-activity-table-card,
.ym-works-activity-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}
.ym-works-activity-hero { padding: clamp(1.25rem, 3vw, 2rem); }
.ym-works-activity-hero::before { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(139, 92, 246, 0.17), transparent 46%); content: ''; pointer-events: none; }
.ym-works-activity-hero__grid { position: absolute; inset: 0; background: linear-gradient(rgba(148,163,184,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,.045) 1px,transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom,black,transparent 86%); pointer-events: none; }
.ym-works-activity-hero__glow { position: absolute; width: 19rem; height: 19rem; border-radius: 999px; filter: blur(18px); opacity: .24; pointer-events: none; }
.ym-works-activity-hero__glow.is-one { inset-block-start: -10rem; inset-inline-start: -5rem; background: #8b5cf6; }
.ym-works-activity-hero__glow.is-two { inset-block-end: -11rem; inset-inline-end: -4rem; background: #06b6d4; }
.ym-works-activity-hero__content { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
.ym-works-activity-chips { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1rem; }
.ym-works-activity-chip { border: 1px solid var(--ym-soft-border); border-radius: 999px; background: var(--ym-control-bg); color: var(--ym-muted); font-size: 12px; font-weight: 950; padding: .42rem .72rem; }
.ym-works-activity-chip.is-brand { color: #fbbf24; }
.ym-works-activity-chip.is-readonly { color: #a78bfa; }
.ym-works-activity-kicker { color: var(--ym-muted); font-size: 14px; font-weight: 900; margin: 0 0 .3rem; }
.ym-works-activity-hero h1 { color: var(--ym-text); font-size: clamp(2rem,4.5vw,3.45rem); font-weight: 950; line-height: 1.1; margin: 0; }
.ym-works-activity-description { max-width: 58rem; color: var(--ym-muted); font-size: 15px; font-weight: 800; line-height: 1.8; margin: .8rem 0 0; }
.ym-works-activity-hero__summary { display: grid; min-width: min(100%,220px); border: 1px solid var(--ym-soft-border); border-radius: 24px; background: var(--ym-control-bg); padding: 1rem; }
.ym-works-activity-hero__summary span,.ym-works-activity-hero__summary small { color: var(--ym-muted); font-size: 12px; font-weight: 850; }
.ym-works-activity-hero__summary strong { color: var(--ym-text); font-size: 2rem; font-weight: 950; }
.ym-works-activity-notices { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
.ym-works-activity-notice { display: flex; align-items: center; gap: .85rem; border: 1px solid var(--ym-soft-border); border-radius: 22px; background: var(--ym-control-bg); padding: 1rem 1.15rem; }
.ym-works-activity-notice > span { flex: 0 0 auto; border-radius: 999px; background: rgba(139,92,246,.14); color: #a78bfa; font-size: 12px; font-weight: 950; padding: .38rem .7rem; }
.ym-works-activity-notice.is-restriction > span { background: rgba(245,158,11,.13); color: #fbbf24; }
.ym-works-activity-notice p { color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.7; margin: 0; }
.ym-works-activity-source { display: flex; align-items: flex-start; gap: 1rem; border: 1px solid rgba(56,189,248,.34); border-radius: 22px; background: linear-gradient(135deg,rgba(56,189,248,.1),transparent),var(--ym-card-bg); padding: 1.1rem; }
.ym-works-activity-source__icon { display: grid; flex: 0 0 auto; width: 2.4rem; height: 2.4rem; place-items: center; border-radius: 999px; background: rgba(56,189,248,.16); color: #38bdf8; font-weight: 950; }
.ym-works-activity-source h2 { color: var(--ym-text); font-size: 1.05rem; font-weight: 950; margin: 0 0 .7rem; }
.ym-works-activity-source dl { display: grid; gap: .45rem; margin: 0; }
.ym-works-activity-source dl div { display: flex; align-items: baseline; gap: .55rem; }
.ym-works-activity-source dt { color: #38bdf8; font-size: 11px; font-weight: 950; }
.ym-works-activity-source dd { color: var(--ym-muted); font-size: 12px; font-weight: 800; line-height: 1.7; margin: 0; }
.ym-works-activity-summary-grid { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: 1rem; }
.ym-works-activity-summary-card { border: 1px solid var(--ym-soft-border); border-radius: 22px; background: linear-gradient(135deg,color-mix(in srgb,var(--activity-accent) 16%,transparent),transparent 52%),var(--ym-card-bg); box-shadow: var(--ym-card-shadow); padding: .95rem; }
.ym-works-activity-summary-card.is-alert { border-color: color-mix(in srgb,var(--activity-accent) 36%,var(--ym-soft-border)); }
.ym-works-activity-summary-card span,.ym-works-activity-summary-card small { display: block; color: var(--ym-muted); font-size: 11px; font-weight: 850; }
.ym-works-activity-summary-card strong { display: block; color: var(--ym-text); font-size: 1.75rem; font-weight: 950; margin: .3rem 0; }
.ym-works-activity-filter-card,.ym-works-activity-table-card { padding: clamp(1rem,2.4vw,1.45rem); }
.ym-works-activity-filter-card > header,.ym-works-activity-table-card__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.ym-works-activity-filter-card h2,.ym-works-activity-table-card h2,.ym-works-activity-access-state h2 { color: var(--ym-text); font-size: 1.25rem; font-weight: 950; margin: 0; }
.ym-works-activity-filter-card header p,.ym-works-activity-table-card__head p,.ym-works-activity-access-state p { color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.7; margin: .3rem 0 0; }
.ym-works-activity-filter-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .9rem; }
.ym-works-activity-filter-grid label { display: grid; align-content: start; gap: .42rem; }
.ym-works-activity-filter-grid label.is-search { grid-column: span 2; }
.ym-works-activity-filter-grid label > span { color: var(--ym-muted); font-size: 12px; font-weight: 900; }
.ym-works-activity-filter-grid label > small { color: var(--ym-muted); font-size: 10px; font-weight: 750; }
.ym-works-activity-filter-grid input,.ym-works-activity-filter-grid select { width: 100%; min-height: 45px; border: 1px solid var(--ym-control-border); border-radius: 14px; outline: none; background: var(--ym-control-bg); color: var(--ym-text); font-size: 13px; font-weight: 800; padding: .7rem .8rem; }
.ym-works-activity-filter-grid input:focus,.ym-works-activity-filter-grid select:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,.14); }
.ym-works-activity-filter-grid select option { background: var(--ym-dropdown-bg); color: var(--ym-text); }
.ym-works-activity-filter-actions { display: flex; align-items: flex-end; }
.ym-works-activity-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border: 1px solid transparent; border-radius: 14px; font-size: 13px; font-weight: 950; padding: .7rem 1rem; transition: transform 160ms ease,opacity 160ms ease; }
.ym-works-activity-button.is-primary { min-width: 130px; background: linear-gradient(135deg,#8b5cf6,#6d28d9); color: #fff; box-shadow: 0 12px 28px rgba(139,92,246,.22); }
.ym-works-activity-button.is-secondary { border-color: var(--ym-control-border); background: var(--ym-control-bg); color: var(--ym-text); }
.ym-works-activity-button:hover:not(:disabled) { transform: translateY(-1px); }
.ym-works-activity-button:disabled { cursor: not-allowed; opacity: .5; }
.ym-works-activity-filter-error { border: 1px solid rgba(244,63,94,.34); border-radius: 15px; background: rgba(244,63,94,.1); color: #fb7185; font-size: 12px; font-weight: 850; margin: 1rem 0 0; padding: .75rem .85rem; }
.ym-works-activity-table-card__head { align-items: center; }
.ym-works-activity-table-state { display: grid; min-width: 130px; border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-control-bg); padding: .65rem .8rem; }
.ym-works-activity-table-state span { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-works-activity-table-state strong { color: var(--ym-text); font-size: 14px; font-weight: 950; }
.ym-works-activity-table-wrap { overflow-x: auto; border: 1px solid var(--ym-soft-border); border-radius: 20px; scrollbar-color: rgba(148,163,184,.55) transparent; }
.ym-works-activity-table { width: 100%; min-width: 3000px; border-collapse: collapse; background: color-mix(in srgb,var(--ym-card-bg) 88%,transparent); }
.ym-works-activity-table th,.ym-works-activity-table td { border-bottom: 1px solid var(--ym-soft-border); color: var(--ym-muted); font-size: 12px; padding: .82rem .7rem; text-align: start; vertical-align: middle; }
.ym-works-activity-table th { position: sticky; top: 0; z-index: 2; background: var(--ym-dropdown-bg); color: var(--ym-text); font-weight: 950; white-space: nowrap; }
.ym-works-activity-table tbody tr { transition: background 150ms ease; }
.ym-works-activity-table tbody tr:hover { background: var(--ym-row-hover); }
.ym-works-activity-table tbody tr.needs-attention { background: rgba(244,63,94,.035); }
.ym-works-activity-table tbody tr:last-child td { border-bottom: 0; }
.ym-works-activity-table th.is-title,.ym-works-activity-table td.is-title { width: 250px; min-width: 250px; }
.ym-works-activity-table td.is-title strong,.ym-works-activity-person strong,.ym-works-activity-person small { display: block; }
.ym-works-activity-table td.is-title strong,.ym-works-activity-event-label { color: var(--ym-text); font-size: 12px; font-weight: 950; }
.ym-works-activity-slug { display: inline-block; max-width: 190px; overflow-wrap: anywhere; color: #fbbf24; font-size: 10px; }
.ym-works-activity-sort { display: inline-flex; align-items: center; gap: .42rem; border: 0; background: transparent; color: inherit; font: inherit; padding: 0; }
.ym-works-activity-sort span { display: inline-grid; width: 1.35rem; height: 1.35rem; place-items: center; border-radius: 7px; background: rgba(139,92,246,.14); color: #c4b5fd; }
.ym-works-activity-badge,.ym-works-activity-flag { display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--ym-soft-border); border-radius: 999px; background: var(--ym-control-bg); color: var(--ym-muted); font-size: 10px; font-weight: 950; padding: .34rem .58rem; white-space: nowrap; }
.ym-works-activity-badge.is-created { border-color: rgba(34,197,94,.35); background: rgba(34,197,94,.12); color: #4ade80; }
.ym-works-activity-badge.is-updated { border-color: rgba(96,165,250,.35); background: rgba(96,165,250,.12); color: #60a5fa; }
.ym-works-activity-badge.is-submitted,.ym-works-activity-badge.is-in-review { border-color: rgba(6,182,212,.35); background: rgba(6,182,212,.12); color: #22d3ee; }
.ym-works-activity-badge.is-reviewed { border-color: rgba(167,139,250,.38); background: rgba(167,139,250,.12); color: #c4b5fd; }
.ym-works-activity-badge.is-approved,.ym-works-activity-badge.is-published,.ym-works-activity-badge.is-public { border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.12); color: #34d399; }
.ym-works-activity-badge.is-rejected,.ym-works-activity-badge.is-hidden,.ym-works-activity-badge.is-archived { border-color: rgba(244,63,94,.38); background: rgba(244,63,94,.13); color: #fb7185; }
.ym-works-activity-badge.is-draft { color: #cbd5e1; }
.ym-works-activity-badge.is-changes-requested { border-color: rgba(245,158,11,.38); background: rgba(245,158,11,.12); color: #fbbf24; }
.ym-works-activity-person { min-width: 120px; }
.ym-works-activity-person strong { color: var(--ym-text); font-size: 11px; font-weight: 900; }
.ym-works-activity-person small { color: var(--ym-muted); font-size: 9px; margin-top: .18rem; }
.ym-works-activity-count { display: inline-grid; min-width: 2.2rem; min-height: 2rem; place-items: center; border-radius: 10px; background: var(--ym-control-bg); color: var(--ym-text); font-weight: 950; padding: .2rem .45rem; }
.ym-works-activity-count.is-alert { background: rgba(244,63,94,.13); color: #fb7185; }
.ym-works-activity-flag.is-inactive { opacity: .58; }
.ym-works-activity-flag.is-review.is-active { border-color: rgba(167,139,250,.38); background: rgba(167,139,250,.13); color: #c4b5fd; }
.ym-works-activity-flag.is-visibility.is-active { border-color: rgba(45,212,191,.38); background: rgba(45,212,191,.13); color: #5eead4; }
.ym-works-activity-flag.is-reported.is-active,.ym-works-activity-flag.is-attention.is-active { border-color: rgba(244,63,94,.38); background: rgba(244,63,94,.13); color: #fb7185; }
.ym-works-activity-flag.is-promoted.is-active { border-color: rgba(245,158,11,.38); background: rgba(245,158,11,.13); color: #fbbf24; }
.ym-works-activity-table time { display: inline-block; min-width: 125px; font-size: 10px; line-height: 1.5; }
.ym-works-activity-table th.is-action,.ym-works-activity-table td.is-action { position: sticky; inset-inline-end: 0; z-index: 1; min-width: 155px; background: var(--ym-dropdown-bg); }
.ym-works-activity-table th.is-action { z-index: 3; }
.ym-works-activity-details-button { width: 100%; min-height: 38px; border: 1px solid rgba(139,92,246,.4); border-radius: 12px; background: rgba(139,92,246,.12); color: #c4b5fd; font-size: 11px; font-weight: 950; padding: .55rem .7rem; }
.ym-works-activity-state,.ym-works-activity-access-state { display: grid; min-height: 240px; place-items: center; align-content: center; gap: .7rem; color: var(--ym-muted); padding: 2rem; text-align: center; }
.ym-works-activity-state h3 { color: var(--ym-text); font-size: 1.1rem; font-weight: 950; margin: 0; }
.ym-works-activity-state p { max-width: 34rem; color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.7; margin: 0; }
.ym-works-activity-state.is-error,.ym-works-activity-access-state.is-forbidden { color: #fb7185; }
.ym-works-activity-state__icon,.ym-works-activity-empty-icon { display: grid; width: 3rem; height: 3rem; place-items: center; border-radius: 999px; background: rgba(244,63,94,.13); color: #fb7185; font-size: 1.1rem; font-weight: 950; }
.ym-works-activity-empty-icon { background: rgba(148,163,184,.13); color: var(--ym-muted); }
.ym-works-activity-spinner { width: 2.35rem; height: 2.35rem; border: 3px solid rgba(139,92,246,.2); border-top-color: #8b5cf6; border-radius: 999px; animation: ym-works-activity-spin 760ms linear infinite; }
.ym-works-activity-pagination { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1rem; }
.ym-works-activity-pagination > div { display: flex; align-items: baseline; gap: .45rem; color: var(--ym-muted); font-size: 12px; font-weight: 850; }
.ym-works-activity-pagination > div strong { color: var(--ym-text); font-size: 1.1rem; font-weight: 950; }
.ym-works-activity-pagination nav { display: flex; align-items: center; gap: .75rem; }
.ym-works-activity-pagination nav span { color: var(--ym-muted); font-size: 12px; font-weight: 900; }
.ym-activity-detail-backdrop { position: fixed; inset: 0; z-index: 120; display: flex; justify-content: flex-end; background: rgba(2,6,23,.68); backdrop-filter: blur(6px); }
.ym-activity-detail-drawer { width: min(680px,100%); height: 100dvh; overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-dropdown-bg); box-shadow: -24px 0 64px rgba(2,6,23,.38); color: var(--ym-text); }
.ym-activity-detail-drawer__head { position: sticky; top: 0; z-index: 4; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--ym-soft-border); background: color-mix(in srgb,var(--ym-dropdown-bg) 92%,transparent); backdrop-filter: blur(18px); padding: 1.2rem 1.35rem; }
.ym-activity-detail-drawer__head span,.ym-activity-detail-drawer__head code { display: block; color: var(--ym-muted); font-size: 11px; font-weight: 850; }
.ym-activity-detail-drawer__head h2 { color: var(--ym-text); font-size: 1.35rem; font-weight: 950; margin: .2rem 0; }
.ym-activity-detail-drawer__close { display: grid; flex: 0 0 auto; width: 42px; height: 42px; place-items: center; border: 1px solid var(--ym-control-border); border-radius: 14px; background: var(--ym-control-bg); color: var(--ym-text); font-size: 1.45rem; }
.ym-activity-detail-content { display: grid; gap: 1rem; padding: 1.25rem; }
.ym-activity-detail-intro,.ym-activity-detail-section { border: 1px solid var(--ym-soft-border); border-radius: 22px; background: var(--ym-card-bg); padding: 1rem; }
.ym-activity-detail-intro > div { display: flex; flex-wrap: wrap; gap: .45rem; }
.ym-activity-detail-intro h3 { color: var(--ym-text); font-size: 1.35rem; font-weight: 950; margin: .8rem 0 .25rem; }
.ym-activity-detail-intro code { color: #fbbf24; font-size: 11px; overflow-wrap: anywhere; }
.ym-activity-detail-section > h3 { color: var(--ym-text); font-size: 1rem; font-weight: 950; margin: 0 0 .8rem; }
.ym-activity-detail-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .65rem; margin: 0; }
.ym-activity-detail-grid > div,.ym-activity-detail-people article,.ym-activity-detail-flags > span { min-width: 0; border: 1px solid var(--ym-soft-border); border-radius: 15px; background: var(--ym-control-bg); padding: .7rem; }
.ym-activity-detail-grid dt,.ym-activity-detail-people span,.ym-activity-detail-flags small { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-activity-detail-grid dd { color: var(--ym-text); font-size: 12px; font-weight: 900; line-height: 1.65; margin: .3rem 0 0; overflow-wrap: anywhere; }
.ym-activity-detail-people { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .65rem; }
.ym-activity-detail-people strong,.ym-activity-detail-people small { display: block; }
.ym-activity-detail-people strong { color: var(--ym-text); font-size: 12px; font-weight: 950; margin-top: .3rem; }
.ym-activity-detail-people small { color: var(--ym-muted); font-size: 10px; margin-top: .18rem; }
.ym-activity-detail-flags { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .65rem; }
.ym-activity-detail-flags > span { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
@keyframes ym-works-activity-spin { to { transform: rotate(360deg); } }
@media (max-width: 1280px) { .ym-works-activity-summary-grid { grid-template-columns: repeat(3,minmax(0,1fr)); } .ym-works-activity-filter-grid { grid-template-columns: repeat(3,minmax(0,1fr)); } }
@media (max-width: 900px) { .ym-works-activity-hero__content,.ym-works-activity-filter-card > header,.ym-works-activity-table-card__head,.ym-works-activity-pagination { align-items: stretch; flex-direction: column; } .ym-works-activity-hero__summary { min-width: 0; } .ym-works-activity-summary-grid,.ym-works-activity-filter-grid,.ym-works-activity-notices { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-activity-pagination nav { justify-content: space-between; } }
@media (max-width: 640px) { .ym-works-activity-hero,.ym-works-activity-filter-card,.ym-works-activity-table-card,.ym-works-activity-access-state { border-radius: 22px; } .ym-works-activity-hero h1 { font-size: 2rem; } .ym-works-activity-summary-grid,.ym-works-activity-filter-grid,.ym-works-activity-notices,.ym-activity-detail-grid,.ym-activity-detail-people,.ym-activity-detail-flags { grid-template-columns: 1fr; } .ym-works-activity-filter-grid label.is-search { grid-column: auto; } .ym-works-activity-filter-actions,.ym-works-activity-filter-actions .ym-works-activity-button { width: 100%; } .ym-works-activity-pagination nav { display: grid; grid-template-columns: 1fr; text-align: center; } .ym-activity-detail-drawer__head,.ym-activity-detail-content { padding-inline: 1rem; } }
@media (prefers-reduced-motion: reduce) { .ym-works-activity-spinner { animation-duration: 1.8s; } }
</style>
