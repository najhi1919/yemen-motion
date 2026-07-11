<template>
  <div class="ym-dashboard-page space-y-7">
    <section class="ym-hero-card ym-admin-hero">
      <div class="ym-hero-orb ym-hero-orb-one" />
      <div class="ym-hero-orb ym-hero-orb-two" />
      <div class="ym-hero-orb ym-hero-orb-three" />
      <div class="ym-hero-grid" aria-hidden="true" />
      <div class="ym-hero-content">
        <div class="flex min-w-0 items-center gap-5">
          <div class="ym-hero-avatar">
            <img
              :src="heroAvatar"
              :alt="auth.user?.name || copy.fallbackName"
              :class="auth.user?.avatar ? 'h-full w-full object-cover' : 'h-full w-full object-contain p-3'"
            />
          </div>
          <div class="min-w-0">
            <div class="ym-hero-chips">
              <span class="ym-hero-chip ym-hero-chip--brand">
                <i class="ym-hero-chip-dot" aria-hidden="true" />
                {{ copy.brandChip }}
              </span>
              <span class="ym-hero-chip ym-hero-chip--status">
                <i class="ym-hero-chip-dot ym-hero-chip-dot--live" aria-hidden="true" />
                {{ copy.statusChip }}
              </span>
            </div>
            <p class="ym-hero-kicker">{{ copy.greeting }}</p>
            <h2 class="ym-hero-title">{{ auth.user?.name || copy.fallbackName }}</h2>
            <p class="ym-hero-copy">{{ copy.heroCopy }}</p>
          </div>
        </div>
        <div class="ym-hero-summary">
          <span>{{ copy.activeScope }}</span>
          <strong>{{ selectedSectionsLabel }}</strong>
          <small>{{ copy.periodLabel }}: {{ periodLabel }}</small>
        </div>
      </div>
    </section>

    <aside class="ym-prototype-notice" role="note">
      <span class="ym-prototype-notice__badge">{{ copy.prototypeBadge }}</span>
      <div class="min-w-0">
        <p>{{ copy.prototypeNotice }}</p>
        <small
          v-if="overviewStatusMessage"
          class="ym-prototype-notice__status"
          :class="dashboardOverviewError ? 'is-error' : ''"
        >
          {{ overviewStatusMessage }}
        </small>
      </div>
    </aside>

    <section
      ref="controlPanelRef"
      class="ym-control-panel"
      @mouseover="showDelegatedControlTooltip"
      @focusin="showDelegatedControlTooltip"
      @mouseout="hideDelegatedControlTooltip"
      @focusout="hideDelegatedControlTooltip"
    >
      <div class="ym-control-panel-content ym-control-panel-head">
        <h3>{{ copy.controlsTitle }}</h3>
        <p>{{ copy.controlsSubtitle }}</p>
      </div>
      <div class="ym-controls-row ym-control-panel-content">
        <div class="ym-control-block">
          <span>{{ copy.periodControl }}</span>
          <DashboardPeriodFilter v-model="period" :locale="currentLocale" />
        </div>
        <div class="ym-control-block">
          <span>{{ copy.viewControl }}</span>
          <DashboardViewToggle v-model="viewMode" :locale="currentLocale" />
        </div>
        <div class="ym-control-block">
          <span>{{ copy.chartControl }}</span>
          <div class="ym-control-group">
            <button
              type="button"
              class="ym-control-pill"
              :class="chartMode === 'individual' ? 'is-active' : ''"
              :aria-label="copy.individualTooltip"
              @click="chartMode = 'individual'"
            >
              {{ copy.individual }}
            </button>
            <button
              type="button"
              class="ym-control-pill"
              :class="chartMode === 'combined' ? 'is-active' : ''"
              :aria-label="copy.combinedTooltip"
              @click="chartMode = 'combined'"
            >
              {{ copy.combined }}
            </button>
          </div>
        </div>
      </div>
      <div class="ym-control-block ym-control-block--sections ym-control-panel-content">
        <span>{{ copy.sectionControl }}</span>
        <DashboardSectionFilter
          :model-value="selectedSections"
          :sections="localizedSections"
          :locale="currentLocale"
          @toggle-all="toggleAllSections"
          @toggle-section="toggleSection"
        />
      </div>
    </section>

    <section v-if="viewMode === 'all' || viewMode === 'cards'" class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
      <DashboardMetricCard
        v-for="card in visibleCards"
        :key="card.key"
        :label="card.label"
        :value="card.value"
        :subtitle="card.subtitle"
        :trend="card.trend"
        :color="card.color"
        :icon="card.icon"
        :locale="currentLocale"
        :trend-label="copy.trendLabel"
        :period-label="periodLabel"
        :tooltip-description="card.tooltipDescription"
        :tooltip-labels="{
          value: copy.tooltipValue,
          period: copy.tooltipPeriod
        }"
      />
    </section>

    <section v-if="viewMode === 'all' || viewMode === 'charts'" class="space-y-5">
      <div class="ym-section-title">
        <h3>{{ copy.chartTitle }}</h3>
        <p>{{ chartMode === 'combined' ? copy.combinedHint : copy.individualHint }}</p>
      </div>

      <DashboardSvgChart
        v-if="chartMode === 'combined'"
        :title="copy.combinedChart"
        :subtitle="copy.combinedHint"
        type="bar"
        :bars="combinedBars"
        :height="310"
        :period-label="periodLabel"
        :period-title="copy.tooltipPeriod"
        :time-point-label="copy.tooltipTimePoint"
        :bucket-value-label="bucketValueLabel"
        :cumulative-value-label="copy.tooltipCumulativeToPoint"
      />

      <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <DashboardSvgChart
          v-for="section in activeSectionModels"
          :key="section.key"
          :title="section.label[currentLocale]"
          :subtitle="copy.individualHint"
          :labels="sectionBreakdown(section).map(item => item.label)"
          :data="sectionBreakdown(section).map(item => item.value)"
          :line-color="section.color"
          :height="260"
          :period-label="periodLabel"
          :period-title="copy.tooltipPeriod"
          :time-point-label="copy.tooltipTimePoint"
          :bucket-value-label="bucketValueLabel"
          :cumulative-value-label="copy.tooltipCumulativeToPoint"
        />
      </div>
    </section>

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-3">
      <div class="xl:col-span-2">
        <DashboardActivityFeed :title="copy.activityTitle" :items="localizedActivities" :empty-label="copy.emptyActivity" />
      </div>
      <aside class="ym-side-panel">
        <h3>{{ copy.quickTitle }}</h3>
        <div class="space-y-3">
          <div v-for="item in quickStats" :key="item.label" class="ym-side-row">
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
          </div>
        </div>
      </aside>
    </section>
  </div>

  <Teleport to="body">
    <div
      v-if="controlTooltip.visible"
      class="ym-floating-tooltip ym-control-floating-tooltip"
      :style="{ top: `${controlTooltip.top}px`, left: `${controlTooltip.left}px` }"
      role="tooltip"
    >
      {{ controlTooltip.label }}
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type Period = 'day' | 'week' | 'month' | 'year'
type ViewMode = 'all' | 'cards' | 'charts'
type ChartMode = 'individual' | 'combined'
type ControlTooltipPlacement = 'bottom'
type ActivityTone = 'success' | 'info' | 'warning' | 'error'
type QuickMetricKey = 'users' | 'staff' | 'roles' | 'permissions' | 'access'

type DashboardLocalizedLabel = {
  ar: string
  en: string
}

type DashboardOverviewSection = {
  key: string
  label: DashboardLocalizedLabel
  icon?: string
  color?: string
  permission?: string | null
  is_active?: boolean
}

type DashboardOverviewCard = {
  key: string
  label: DashboardLocalizedLabel
  value: number
  change?: number
  trend?: 'up' | 'down' | 'neutral' | string
  section?: string
}

type DashboardOverviewChart = {
  key: string
  type?: string
  section?: string
  points?: Array<{
    label: string
    value: number
  }>
}

type DashboardOverviewActivity = {
  id?: string | number
  key?: string
  label?: DashboardLocalizedLabel
  title?: string
  description?: string
  time?: string
  icon?: string
  link?: string | null
}

type DashboardOverviewData = {
  role: string
  period: string
  sections: DashboardOverviewSection[]
  cards: DashboardOverviewCard[]
  charts: DashboardOverviewChart[]
  activities: DashboardOverviewActivity[]
}

type DashboardOverviewResponse = {
  success: boolean
  data: DashboardOverviewData | null
  message?: string
  errors?: Record<string, string[]> | null
  meta?: {
    periods?: Period[]
    selected_period?: Period
  }
}

type DashboardSectionModel = {
  key: string
  color: string
  icon: string
  label: DashboardLocalizedLabel
  subtitle: DashboardLocalizedLabel
  base: number
  trend: number
  series: Record<Period, number[]>
  apiPoints?: Array<{
    label: string
    value: number
  }>
}

const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const period = ref<Period>('month')
const viewMode = ref<ViewMode>('all')
const chartMode = ref<ChartMode>('individual')
const dashboardOverview = ref<DashboardOverviewData | null>(null)
const dashboardOverviewLoading = ref(false)
const dashboardOverviewError = ref<string | null>(null)
const hasSyncedOverviewSections = ref(false)
const controlPanelRef = ref<HTMLElement | null>(null)
const controlTooltip = reactive({
  visible: false,
  label: '',
  top: 0,
  left: 0,
  placement: 'bottom' as ControlTooltipPlacement
})

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    statusChip: 'نظام الإدارة',
    greeting: 'مرحباً بك في مركز التحكم',
    fallbackName: 'مدير المنصة',
    heroCopy: 'نظرة تشغيلية واضحة على الطلبات، المستخدمين، الإيرادات، البلاغات، وأداء الفريق.',
    activeScope: 'النطاق النشط',
    periodLabel: 'الفترة',
    prototypeBadge: 'بيانات حقيقية جزئية',
    prototypeNotice: 'تعرض هذه اللوحة مؤشرات حقيقية من API للوحدات المبنية حاليًا مثل المستخدمين والفريق والأدوار والصلاحيات، بينما تبقى الأقسام غير المبنية بعد بقيمة صفر أو حالة قيد البناء.',
    controlsTitle: 'عناصر العرض التفاعلية',
    controlsSubtitle: 'غيّر الفترة، الأقسام، ونمط العرض لعرض مؤشرات Dashboard المتاحة من API.',
    periodControl: 'الفترة',
    viewControl: 'طريقة العرض',
    chartControl: 'نمط الرسم',
    sectionControl: 'الأقسام',
    individual: 'رسوم منفصلة',
    combined: 'رسم مجمّع',
    individualTooltip: 'عرض رسم مستقل لكل قسم',
    combinedTooltip: 'عرض الأقسام في رسم مجمّع',
    chartTitle: 'الرسوم والتحليلات',
    individualHint: 'كل قسم يظهر بخط مستقل حسب الفترة المختارة.',
    combinedHint: 'مقارنة أعمدة واحدة للأقسام المختارة.',
    combinedChart: 'مقارنة الأقسام المختارة',
    activityTitle: 'النشاط الأخير',
    emptyActivity: 'لا يوجد نشاط حديث',
    quickTitle: 'ملخص تنفيذي',
    trendLabel: 'مقارنة بالفترة السابقة',
    tooltipValue: 'القيمة',
    tooltipPeriod: 'الفترة',
    tooltipTimePoint: 'النقطة الزمنية',
    tooltipCumulativeToPoint: 'الإجمالي حتى هذه النقطة',
    tooltipMetricDescription: 'يعرض هذا المؤشر عدد {section} ضمن الفترة المختارة.',
    tooltipBucketByPeriod: {
      day: 'في هذه الساعة',
      week: 'في هذا اليوم',
      month: 'في هذا اليوم',
      year: 'في هذا الشهر'
    },
    allSections: 'كل الأقسام',
    periodNames: { day: 'اليوم', week: 'الأسبوع', month: 'الشهر', year: 'السنة' }
  },
  en: {
    brandChip: 'Yemen Motion',
    statusChip: 'Admin System',
    greeting: 'Welcome to the command center',
    fallbackName: 'Platform Admin',
    heroCopy: 'A clear operational view across orders, users, revenue, reports, and team performance.',
    activeScope: 'Active scope',
    periodLabel: 'Period',
    prototypeBadge: 'Partial live data',
    prototypeNotice: 'This dashboard now shows live API metrics for implemented modules such as users, staff, roles, and permissions. Unimplemented operational sections remain zero or marked as under construction.',
    controlsTitle: 'Interactive display controls',
    controlsSubtitle: 'Change the period, sections, and view mode to inspect available API-backed dashboard metrics.',
    periodControl: 'Period',
    viewControl: 'View mode',
    chartControl: 'Chart mode',
    sectionControl: 'Sections',
    individual: 'Individual',
    combined: 'Combined',
    individualTooltip: 'Show a separate chart for each section',
    combinedTooltip: 'Show sections in one combined chart',
    chartTitle: 'Charts & analytics',
    individualHint: 'Each selected section appears as an individual SVG line chart.',
    combinedHint: 'One SVG bar chart compares selected sections.',
    combinedChart: 'Selected sections comparison',
    activityTitle: 'Recent activity',
    emptyActivity: 'No recent activity',
    quickTitle: 'Executive summary',
    trendLabel: 'vs previous period',
    tooltipValue: 'Value',
    tooltipPeriod: 'Period',
    tooltipTimePoint: 'Time point',
    tooltipCumulativeToPoint: 'Total through this point',
    tooltipMetricDescription: 'This metric shows the number of {section} within the selected period.',
    tooltipBucketByPeriod: {
      day: 'This hour',
      week: 'This day',
      month: 'This day',
      year: 'This month'
    },
    allSections: 'All sections',
    periodNames: { day: 'Day', week: 'Week', month: 'Month', year: 'Year' }
  }
}

// يحدد هذا الترتيب شكل العرض فقط، ولا يضيف أي مؤشر لم تُعِده البطاقات المصرح بها.
const quickMetricKeys: QuickMetricKey[] = ['users', 'staff', 'roles', 'permissions', 'access']

const quickMetricLabels: Record<Locale, Record<QuickMetricKey, string>> = {
  ar: {
    users: 'المستخدمون',
    staff: 'الفريق',
    roles: 'الأدوار',
    permissions: 'الصلاحيات',
    access: 'إدارة الوصول'
  },
  en: {
    users: 'Users',
    staff: 'Staff',
    roles: 'Roles',
    permissions: 'Permissions',
    access: 'Access'
  }
}

const metricTooltipSubjects: Record<Locale, Record<string, string>> = {
  ar: {
    overview: 'المستخدمين والأدوار والصلاحيات',
    users: 'المستخدمين',
    staff: 'أعضاء الفريق',
    roles: 'الأدوار',
    permissions: 'الصلاحيات',
    access: 'الأدوار والصلاحيات',
    orders: 'الطلبات',
    works: 'الأعمال',
    contests: 'المسابقات',
    wallet: 'معاملات المحفظة',
    reports: 'التقارير',
    activities_feed: 'النشاطات'
  },
  en: {
    overview: 'users, roles, and permissions',
    users: 'users',
    staff: 'staff members',
    roles: 'roles',
    permissions: 'permissions',
    access: 'roles and permissions',
    orders: 'orders',
    works: 'works',
    contests: 'contests',
    wallet: 'wallet transactions',
    reports: 'reports',
    activities_feed: 'activities'
  }
}

const sectionModels: DashboardSectionModel[] = [
  { key: 'users', color: '#10b981', icon: '●', label: { ar: 'المستخدمون', en: 'Users' }, subtitle: { ar: 'من API عند توفر الاتصال', en: 'API-backed when available' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'staff', color: '#06b6d4', icon: '◆', label: { ar: 'الفريق', en: 'Staff' }, subtitle: { ar: 'من API عند توفر الاتصال', en: 'API-backed when available' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'roles', color: '#8b5cf6', icon: '◈', label: { ar: 'الأدوار', en: 'Roles' }, subtitle: { ar: 'من API عند توفر الاتصال', en: 'API-backed when available' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'permissions', color: '#0ea5e9', icon: '◉', label: { ar: 'الصلاحيات', en: 'Permissions' }, subtitle: { ar: 'من API عند توفر الاتصال', en: 'API-backed when available' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'access', color: '#f97316', icon: '▣', label: { ar: 'إدارة الوصول', en: 'Access Management' }, subtitle: { ar: 'أدوار وصلاحيات من API', en: 'API roles and permissions' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'orders', color: '#6366f1', icon: '◈', label: { ar: 'الطلبات', en: 'Orders' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'works', color: '#3b82f6', icon: '▰', label: { ar: 'الأعمال', en: 'Works' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'contests', color: '#8b5cf6', icon: '★', label: { ar: 'المسابقات', en: 'Contests' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'wallet', color: '#22c55e', icon: '$', label: { ar: 'المحفظة', en: 'Wallet' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'reports', color: '#14b8a6', icon: '▤', label: { ar: 'التقارير', en: 'Reports' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } },
  { key: 'activities_feed', color: '#0ea5e9', icon: '●', label: { ar: 'النشاطات', en: 'Activities' }, subtitle: { ar: 'قيد البناء', en: 'Under construction' }, base: 0, trend: 0, series: { day: [0], week: [0], month: [0], year: [0] } }
]

const selectedSections = ref<string[]>(sectionModels.map(section => section.key))

const copy = computed(() => copyMap[currentLocale.value])
const periodLabel = computed(() => copy.value.periodNames[period.value])
const bucketValueLabel = computed(() => copy.value.tooltipBucketByPeriod[period.value])
const overviewStatusMessage = computed(() => {
  if (dashboardOverviewLoading.value) {
    return currentLocale.value === 'ar' ? 'يتم تحديث بيانات لوحة التحكم من API...' : 'Updating dashboard data from the API...'
  }

  if (dashboardOverviewError.value) return dashboardOverviewError.value

  return ''
})

// تمنع هذه الفلترة تكرار "نظرة عامة" عندما يتوفر مؤشر المستخدمين المكافئ.
// إذا لم يصل مؤشر المستخدمين، تبقى "نظرة عامة" كخيار احتياطي للمستخدم محدود الصلاحيات.
function withoutDuplicateOverview<T extends { key: string; section?: string }>(items: T[]): T[] {
  const hasUsers = items.some(item => (item.section || item.key) === 'users')
  if (!hasUsers) return items

  return items.filter(item => (item.section || item.key) !== 'overview')
}

const dashboardOverviewSections = computed(() => withoutDuplicateOverview(
  dashboardOverview.value?.sections?.filter(section => section.is_active !== false) || []
))
const dashboardOverviewCards = computed(() => withoutDuplicateOverview(dashboardOverview.value?.cards || []))
const dashboardOverviewCharts = computed(() => withoutDuplicateOverview(dashboardOverview.value?.charts || []))
const dashboardOverviewActivities = computed(() => dashboardOverview.value?.activities || [])

const fallbackSectionByKey = computed(() => new Map(sectionModels.map(section => [section.key, section])))
const overviewCardBySection = computed(() => new Map(dashboardOverviewCards.value.map(card => [card.section || card.key, card])))
const overviewChartBySection = computed(() => new Map(dashboardOverviewCharts.value.map(chart => [chart.section || chart.key, chart])))

const dashboardSections = computed<DashboardSectionModel[]>(() => {
  if (!dashboardOverviewSections.value.length) return sectionModels

  return dashboardOverviewSections.value.map((section) => {
    const fallback = fallbackSectionByKey.value.get(section.key)
    const card = overviewCardBySection.value.get(section.key)
    const chart = overviewChartBySection.value.get(section.key)
    const apiPoints = normalizeChartPoints(chart?.points)
    const base = Number(card?.value ?? apiPoints[apiPoints.length - 1]?.value ?? fallback?.base ?? 0)

    return {
      key: section.key,
      color: section.color || fallback?.color || '#6366f1',
      icon: normalizeSectionIcon(section.icon, fallback?.icon),
      label: normalizeLabel(section.label, fallback?.label, section.key),
      subtitle: fallback?.subtitle || normalizeLabel(card?.label, section.label, section.key),
      base,
      trend: normalizeTrend(card),
      series: buildSeries(apiPoints, fallback?.series, base),
      apiPoints: apiPoints.length ? apiPoints : undefined
    }
  })
})

const localizedSections = computed(() => dashboardSections.value.map(section => ({ key: section.key, color: section.color, icon: section.icon, label: section.label[currentLocale.value] })))
const activeSectionModels = computed(() => dashboardSections.value.filter(section => selectedSections.value.includes(section.key)))

const selectedSectionsLabel = computed(() => {
  if (selectedSections.value.length === dashboardSections.value.length) return copy.value.allSections
  return activeSectionModels.value.map(section => section.label[currentLocale.value]).join(currentLocale.value === 'ar' ? '، ' : ', ')
})

const periodMultiplier = computed(() => ({ day: 0.12, week: 0.38, month: 1, year: 8.4 })[period.value])

const visibleCards = computed(() => activeSectionModels.value.map(section => ({
  key: section.key,
  label: section.label[currentLocale.value],
  value: section.apiPoints?.length ? section.base : Math.round(section.base * periodMultiplier.value),
  subtitle: section.subtitle[currentLocale.value],
  trend: section.trend,
  color: section.color,
  icon: section.icon,
  tooltipDescription: copy.value.tooltipMetricDescription.replace(
    '{section}',
    metricTooltipSubjects[currentLocale.value][section.key] || section.label[currentLocale.value]
  )
})))

const periodLabels = computed(() => {
  const labels = {
    ar: {
      day: ['09:00', '11:00', '13:00', '15:00', '17:00', '18:00'],
      week: ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'],
      month: ['الأسبوع 1', 'الأسبوع 2', 'الأسبوع 3', 'الأسبوع 4'],
      year: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
    },
    en: {
      day: ['09:00', '11:00', '13:00', '15:00', '17:00', '18:00'],
      week: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
      month: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
      year: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
    }
  }
  return labels[currentLocale.value][period.value]
})

const periodBreakdownProfiles: Record<Period, number[]> = {
  day: [0.09, 0.16, 0.28, 0.42, 0.68, 0.92],
  week: [0.54, 0.62, 0.76, 0.71, 0.84, 0.93, 0.58],
  month: [0.22, 0.48, 0.74, 0.96],
  year: [0.31, 0.38, 0.44, 0.52, 0.59, 0.67, 0.73, 0.82, 0.77, 0.88, 0.94, 1]
}

function sectionBreakdown(section: DashboardSectionModel) {
  if (section.apiPoints?.length) return section.apiPoints

  const source = section.series[period.value]
  const total = source[source.length - 1]
  return periodLabels.value.map((label, index) => ({
    label,
    value: Math.max(0, Math.round(total * periodBreakdownProfiles[period.value][index]))
  }))
}

const combinedBars = computed(() => activeSectionModels.value.map(section => ({
  key: section.key,
  label: section.label[currentLocale.value],
  value: section.series[period.value][section.series[period.value].length - 1],
  color: section.color,
  breakdown: sectionBreakdown(section)
})))

const fallbackLocalizedActivities = computed(() => {
  const items = {
    ar: [
      { icon: '●', title: 'لا توجد أنشطة تشغيلية متاحة من API بعد.', description: 'سيظهر النشاط هنا عند توفر مصدره من API.', time: '', type: 'info' as const }
    ],
    en: [
      { icon: '●', title: 'No operational API activities are available yet.', description: 'Activity will appear here once its API source is available.', time: '', type: 'info' as const }
    ]
  }
  return items[currentLocale.value]
})

const localizedActivities = computed(() => {
  if (!dashboardOverviewActivities.value.length) return fallbackLocalizedActivities.value

  return dashboardOverviewActivities.value.map(activity => ({
    icon: activity.icon || '●',
    title: activity.title || activity.label?.[currentLocale.value] || activity.label?.ar || activity.key || String(activity.id || ''),
    description: activity.description || (currentLocale.value === 'ar' ? 'نشاط من بيانات لوحة التحكم' : 'Dashboard activity'),
    time: activity.time || '',
    type: 'info' as ActivityTone
  }))
})

const quickStats = computed(() => {
  const numberLocale = currentLocale.value === 'ar' ? 'ar-SA' : 'en-US'
  const cards = new Map(dashboardOverviewCards.value.map(card => [card.key, card]))

  // تعتمد الفلترة على البطاقات التي أعادها الخادم بعد تطبيق الصلاحيات الدقيقة.
  // البطاقة الغائبة لا تظهر في الملخص، ولا تُستبدل بقيمة صفرية وهمية.
  return quickMetricKeys.flatMap((key) => {
    const card = cards.get(key)
    if (!card) return []

    return [{
      label: quickMetricLabels[currentLocale.value][key],
      value: Number(card.value).toLocaleString(numberLocale)
    }]
  })
})

const heroAvatar = computed(() => auth.user?.avatar || '/logo.svg')

function toggleAllSections() {
  selectedSections.value = selectedSections.value.length === dashboardSections.value.length ? [] : dashboardSections.value.map(section => section.key)
}

function toggleSection(key: string) {
  if (selectedSections.value.includes(key)) {
    selectedSections.value = selectedSections.value.filter(section => section !== key)
  } else {
    selectedSections.value = [...selectedSections.value, key]
  }

  if (!selectedSections.value.length) {
    selectedSections.value = [key]
  }
}

function showDelegatedControlTooltip(event: MouseEvent | FocusEvent): void {
  const target = event.target as HTMLElement | null
  const button = target?.closest('button[aria-label]') as HTMLElement | null
  if (!button || !controlPanelRef.value?.contains(button)) return

  const label = button.getAttribute('aria-label')
  if (!label) return

  const rect = button.getBoundingClientRect()

  controlTooltip.visible = true
  controlTooltip.label = label
  controlTooltip.top = rect.bottom + 8
  controlTooltip.left = rect.left + rect.width / 2
}

function hideDelegatedControlTooltip(event: MouseEvent | FocusEvent): void {
  const target = event.target as HTMLElement | null
  const button = target?.closest('button[aria-label]') as HTMLElement | null
  if (!button || !controlPanelRef.value?.contains(button)) return

  const relatedTarget = event instanceof MouseEvent
    ? event.relatedTarget as Node | null
    : (event as FocusEvent).relatedTarget as Node | null

  if (relatedTarget && button.contains(relatedTarget)) return

  controlTooltip.visible = false
}

function normalizeLabel(label: DashboardLocalizedLabel | undefined, fallback: DashboardLocalizedLabel | undefined, key: string): DashboardLocalizedLabel {
  return {
    ar: label?.ar || fallback?.ar || key,
    en: label?.en || fallback?.en || key
  }
}

function normalizeSectionIcon(icon: string | undefined, fallback: string | undefined): string {
  const iconMap: Record<string, string> = {
    'briefcase': '▰',
    'chart-bar': '▤',
    'clipboard-check': '✓',
    'flag': '▲',
    'key': '◉',
    'rectangle-group': '●',
    'shield-check': '◈',
    'shopping-cart': '◈',
    'trophy': '★',
    'user-group': '●',
    'users': '◆',
    'wallet': '$'
  }

  return (icon && iconMap[icon]) || fallback || '●'
}

function normalizeTrend(card: DashboardOverviewCard | undefined): number {
  const change = Number(card?.change ?? 0)
  if (card?.trend === 'down') return -Math.abs(change)
  if (card?.trend === 'up') return Math.abs(change)
  return change
}

function normalizeChartPoints(points: DashboardOverviewChart['points'] | undefined): Array<{ label: string; value: number }> {
  if (!points?.length) return []

  return points
    .map(point => ({
      label: point.label,
      value: Number(point.value)
    }))
    .filter(point => point.label && Number.isFinite(point.value))
}

function buildSeries(
  points: Array<{ label: string; value: number }>,
  fallback: Record<Period, number[]> | undefined,
  base: number
): Record<Period, number[]> {
  if (!points.length) {
    return fallback || {
      day: [base],
      week: [base],
      month: [base],
      year: [base]
    }
  }

  const values = points.map(point => point.value)
  return {
    day: values,
    week: values,
    month: values,
    year: values
  }
}

async function fetchDashboardOverview(): Promise<void> {
  const { apiFetch } = useApiClient()

  dashboardOverviewLoading.value = true
  dashboardOverviewError.value = null

  try {
    const response = await apiFetch<DashboardOverviewResponse>('/dashboard/overview', {
      query: { period: period.value }
    })

    dashboardOverview.value = response.success ? response.data : null

    if (dashboardOverview.value?.sections?.length && !hasSyncedOverviewSections.value) {
      selectedSections.value = withoutDuplicateOverview(
        dashboardOverview.value.sections.filter(section => section.is_active !== false)
      )
        .map(section => section.key)
      hasSyncedOverviewSections.value = true
    }
  } catch {
    dashboardOverview.value = null
    dashboardOverviewError.value = currentLocale.value === 'ar'
      ? 'تعذر تحميل بيانات Dashboard من API. يتم عرض بيانات احتياطية للواجهة فقط.'
      : 'Could not load dashboard data from the API. Showing visual fallback data only.'
  } finally {
    dashboardOverviewLoading.value = false
  }
}

onMounted(() => {
  void fetchDashboardOverview()
})

watch(period, () => {
  void fetchDashboardOverview()
})
</script>

<style scoped>
.ym-dashboard-page {
  position: relative;
}

.ym-prototype-notice {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 20px;
  background: rgba(245, 158, 11, 0.09);
  padding: 0.9rem 1rem;
  color: var(--ym-text);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.ym-prototype-notice__badge {
  flex: 0 0 auto;
  border: 1px solid rgba(245, 158, 11, 0.38);
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.16);
  padding: 0.35rem 0.7rem;
  color: #f59e0b;
  font-size: 13px;
  font-weight: 950;
}

.ym-prototype-notice p {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
}

@media (max-width: 640px) {
  .ym-prototype-notice {
    align-items: flex-start;
    flex-direction: column;
  }
}

.ym-hero-card,
.ym-side-panel {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 28px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.ym-control-panel {
  position: relative;
  overflow: visible;
  border: 1px solid color-mix(in srgb, var(--ym-card-border) 88%, rgba(129, 140, 248, 0.16));
  border-radius: 28px;
  background:
    radial-gradient(circle at 10% 0%, rgba(236, 72, 153, 0.1), transparent 18rem),
    radial-gradient(circle at 92% 12%, rgba(56, 189, 248, 0.1), transparent 20rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 44px rgba(2, 6, 23, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  isolation: isolate;
  transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
}

.ym-control-panel::before {
  position: absolute;
  inset-inline: 1.4rem;
  top: 0;
  height: 3px;
  border-end-end-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(90deg, #6366f1, #ec4899 46%, #38bdf8);
  box-shadow: 0 0 24px rgba(129, 140, 248, 0.22);
  content: "";
  pointer-events: none;
}

.ym-control-panel::after {
  position: absolute;
  inset: 1px;
  inset-block-end: auto;
  height: 54%;
  border-radius: 27px 27px 0 0;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 70%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.08), transparent 34%);
  content: "";
  pointer-events: none;
  z-index: 0;
}

.ym-control-panel:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 70%, rgba(129, 140, 248, 0.34));
  box-shadow:
    0 28px 68px rgba(2, 6, 23, 0.2),
    0 0 36px rgba(129, 140, 248, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.2),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  transform: translateY(-1px);
}

.ym-hero-card {
  padding: clamp(1.35rem, 3vw, 2.25rem);
}

.ym-admin-hero {
  border-color: rgba(255, 255, 255, 0.22);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 15rem),
    radial-gradient(circle at 85% 8%, rgba(129, 140, 248, 0.38), transparent 19rem),
    radial-gradient(circle at 95% 92%, rgba(190, 0, 1, 0.32), transparent 22rem),
    linear-gradient(135deg, rgba(67, 56, 202, 0.98), rgba(124, 58, 237, 0.92) 48%, rgba(190, 0, 1, 0.78));
  box-shadow:
    0 34px 80px rgba(49, 46, 129, 0.34),
    0 14px 32px rgba(2, 6, 23, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16),
    inset 0 0 0 1px rgba(255, 255, 255, 0.04);
}

.ym-admin-hero::before {
  position: absolute;
  inset: 1px;
  border-radius: 27px;
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0.16), transparent 34%),
    linear-gradient(290deg, rgba(255, 255, 255, 0.1), transparent 42%);
  content: "";
  pointer-events: none;
}

.ym-admin-hero::after {
  position: absolute;
  inset-inline: 7%;
  top: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.78), transparent);
  content: "";
  pointer-events: none;
}

/* شبكة خفيفة جدًا تعطي عمقًا تقنيًا دون ازدحام */
.ym-hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 48px 48px;
  background-position: center;
  -webkit-mask-image: radial-gradient(circle at 30% 30%, #000 0%, transparent 72%);
  mask-image: radial-gradient(circle at 30% 30%, #000 0%, transparent 72%);
  opacity: 0.5;
  pointer-events: none;
}

.ym-hero-content {
  position: relative;
  z-index: 10;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

@media (min-width: 1024px) {
  .ym-hero-content {
    align-items: center;
    flex-direction: row;
    justify-content: space-between;
  }
}

.ym-hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(44px);
  opacity: 0.3;
}

.ym-hero-orb-one {
  top: -6rem;
  inset-inline-end: -4rem;
  height: 16rem;
  width: 16rem;
  background: rgba(255, 255, 255, 0.32);
}

.ym-hero-orb-two {
  bottom: -6rem;
  inset-inline-start: 20%;
  height: 18rem;
  width: 18rem;
  background: rgba(56, 189, 248, 0.22);
}

.ym-hero-orb-three {
  top: 30%;
  inset-inline-start: -5rem;
  height: 14rem;
  width: 14rem;
  background: rgba(244, 114, 182, 0.26);
  opacity: 0.24;
}

/* Status chips أنيقة — تسميات مستخرجة من الصفحة */
.ym-hero-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin: 0 0 0.6rem;
}

.ym-hero-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 6px 16px rgba(15, 23, 42, 0.14);
  color: #fff;
  font-size: 12.5px;
  font-weight: 850;
  letter-spacing: 0.01em;
  padding: 0.28rem 0.7rem;
  backdrop-filter: blur(8px);
}

.ym-hero-chip--brand {
  background: rgba(255, 255, 255, 0.2);
}

.ym-hero-chip--status {
  background: rgba(34, 197, 94, 0.22);
  border-color: rgba(134, 239, 172, 0.42);
}

.ym-hero-chip-dot {
  height: 7px;
  width: 7px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.85);
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.7);
}

.ym-hero-chip-dot--live {
  background: #4ade80;
  box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.28), 0 0 12px rgba(74, 222, 128, 0.7);
  animation: ym-hero-pulse 2.4s ease-in-out infinite;
}

@keyframes ym-hero-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%      { opacity: 0.55; transform: scale(0.82); }
}

@media (prefers-reduced-motion: reduce) {
  .ym-hero-chip-dot--live { animation: none; }
}

.ym-hero-avatar {
  display: grid;
  height: 92px;
  width: 92px;
  flex: 0 0 92px;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.38);
  border-radius: 26px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.26), rgba(255, 255, 255, 0.14)),
    rgba(255, 255, 255, 0.18);
  box-shadow:
    0 28px 56px rgba(15, 23, 42, 0.28),
    inset 0 1px 0 rgba(255, 255, 255, 0.36),
    inset 0 -1px 0 rgba(15, 23, 42, 0.12);
  color: #fff;
  font-size: 2rem;
  font-weight: 950;
}

.ym-hero-kicker,
.ym-hero-copy,
.ym-hero-summary small,
.ym-hero-summary span {
  color: rgba(255, 255, 255, 0.92);
  font-weight: 850;
}

.ym-hero-kicker {
  font-size: 14.5px;
  margin: 0 0 0.3rem;
  letter-spacing: 0.01em;
}

.ym-hero-title {
  color: #fff;
  font-size: clamp(2.1rem, 3.4vw, 2.75rem);
  font-weight: 950;
  line-height: 1.06;
  margin: 0;
  text-shadow: 0 2px 16px rgba(49, 46, 129, 0.35);
}

.ym-hero-copy {
  font-size: 15.5px;
  line-height: 1.75;
  margin: 0.5rem 0 0;
  max-width: 56rem;
}

.ym-hero-summary {
  min-width: min(100%, 260px);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.12)),
    rgba(255, 255, 255, 0.14);
  box-shadow:
    0 20px 48px rgba(30, 41, 59, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.08);
  padding: 1.05rem 1.1rem;
  backdrop-filter: blur(14px);
}

.ym-hero-summary strong {
  display: block;
  color: #fff;
  font-size: 18px;
  font-weight: 950;
  line-height: 1.5;
  margin: 0.2rem 0;
}

/* Hover lift خفيف للبطاقة عند التمرير — لا توجد animation مستمرة هنا */
.ym-admin-hero {
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-admin-hero:hover {
  transform: translateY(-2px);
  box-shadow:
    0 38px 88px rgba(49, 46, 129, 0.38),
    0 16px 36px rgba(2, 6, 23, 0.22),
    inset 0 1px 0 rgba(255, 255, 255, 0.34),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16),
    inset 0 0 0 1px rgba(255, 255, 255, 0.05);
}

@media (prefers-reduced-motion: reduce) {
  .ym-admin-hero:hover { transform: none; }
}

/* ===== Light Mode — بطاقة Hero أغنى وأوضح بدون haze أو ضبابية ===== */
.ym-dashboard-light .ym-admin-hero {
  border-color: rgba(109, 40, 217, 0.42);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.5), transparent 14rem),
    radial-gradient(circle at 85% 8%, rgba(109, 40, 217, 0.28), transparent 18rem),
    radial-gradient(circle at 95% 92%, rgba(190, 0, 1, 0.14), transparent 22rem),
    linear-gradient(135deg, rgba(109, 40, 217, 0.96), rgba(147, 51, 234, 0.92) 48%, rgba(219, 39, 119, 0.82));
  box-shadow:
    0 34px 80px rgba(76, 29, 149, 0.22),
    0 14px 32px rgba(15, 23, 42, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.82),
    inset 0 -1px 0 rgba(109, 40, 217, 0.1),
    inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.ym-dashboard-light .ym-admin-hero::before {
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0.32), transparent 34%),
    linear-gradient(290deg, rgba(255, 255, 255, 0.18), transparent 42%);
}

.ym-dashboard-light .ym-admin-hero::after {
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.92), transparent);
}

.ym-dashboard-light .ym-hero-grid {
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
  opacity: 0.35;
}

.ym-dashboard-light .ym-hero-orb-one {
  background: rgba(167, 139, 250, 0.28);
  opacity: 0.22;
}

.ym-dashboard-light .ym-hero-orb-two {
  background: rgba(56, 189, 248, 0.18);
  opacity: 0.18;
}

.ym-dashboard-light .ym-hero-orb-three {
  background: rgba(244, 114, 182, 0.18);
  opacity: 0.16;
}

.ym-dashboard-light .ym-hero-avatar {
  border-color: rgba(255, 255, 255, 0.48);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.34), rgba(255, 255, 255, 0.22)),
    rgba(255, 255, 255, 0.28);
  box-shadow:
    0 28px 56px rgba(76, 29, 149, 0.22),
    inset 0 1px 0 rgba(255, 255, 255, 0.55),
    inset 0 -1px 0 rgba(109, 40, 217, 0.08);
}

.ym-dashboard-light .ym-hero-kicker,
.ym-dashboard-light .ym-hero-copy,
.ym-dashboard-light .ym-hero-summary small,
.ym-dashboard-light .ym-hero-summary span {
  color: rgba(255, 255, 255, 0.96);
}

.ym-dashboard-light .ym-hero-title {
  text-shadow: 0 2px 20px rgba(76, 29, 149, 0.35);
}

.ym-dashboard-light .ym-hero-chip {
  border-color: rgba(255, 255, 255, 0.4);
  background: rgba(255, 255, 255, 0.22);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.45), 0 6px 16px rgba(76, 29, 149, 0.12);
}

.ym-dashboard-light .ym-hero-chip--brand {
  background: rgba(255, 255, 255, 0.32);
}

.ym-dashboard-light .ym-hero-chip--status {
  background: rgba(34, 197, 94, 0.28);
  border-color: rgba(134, 239, 172, 0.52);
}

.ym-dashboard-light .ym-hero-chip-dot {
  background: rgba(255, 255, 255, 0.95);
}

.ym-dashboard-light .ym-hero-summary {
  border-color: rgba(255, 255, 255, 0.36);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.28), rgba(255, 255, 255, 0.18)),
    rgba(255, 255, 255, 0.2);
  box-shadow:
    0 22px 52px rgba(76, 29, 149, 0.16),
    inset 0 1px 0 rgba(255, 255, 255, 0.45),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06);
  backdrop-filter: blur(16px);
}

.ym-dashboard-light .ym-hero-summary strong {
  color: #fff;
}

.ym-dashboard-light .ym-admin-hero:hover {
  box-shadow:
    0 40px 96px rgba(76, 29, 149, 0.26),
    0 16px 36px rgba(15, 23, 42, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.88),
    inset 0 -1px 0 rgba(109, 40, 217, 0.12),
    inset 0 0 0 1px rgba(255, 255, 255, 0.1);
}

.ym-control-panel {
  display: grid;
  min-width: 0;
  gap: 1.15rem;
  overflow: visible;
  padding: clamp(1.2rem, 2vw, 1.55rem);
}

.ym-control-panel-content {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.ym-control-panel-head {
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 82%, rgba(129, 140, 248, 0.16));
  padding-bottom: 1rem;
}

.ym-controls-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: end;
  gap: 1rem;
}

.ym-control-block {
  display: grid;
  min-width: 0;
  gap: 0.65rem;
  justify-items: start;
  overflow: visible;
}

.ym-control-block--sections {
  border-top: 1px solid color-mix(in srgb, var(--ym-soft-border) 74%, rgba(129, 140, 248, 0.12));
  margin-top: 0.1rem;
  padding-top: 1rem;
}

.ym-control-block :deep(.ym-control-group),
.ym-control-block .ym-control-group,
.ym-control-panel :deep(.ym-section-filter) {
  width: fit-content;
  min-width: 0;
  max-width: 100%;
}

.ym-control-panel :deep(.ym-section-filter) {
  overflow: visible;
}

.ym-control-block > span {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 950;
  letter-spacing: 0.01em;
}

.ym-control-panel h3,
.ym-section-title h3,
.ym-side-panel h3 {
  color: var(--ym-text);
  font-size: clamp(20px, 2vw, 23px);
  font-weight: 950;
  line-height: 1.25;
  margin: 0;
}

.ym-control-panel p,
.ym-section-title p {
  color: var(--ym-muted);
  font-size: 14.5px;
  font-weight: 820;
  line-height: 1.6;
  margin: 0.3rem 0 0;
  max-width: 58rem;
}

@media (max-width: 1180px) {
  .ym-controls-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 1024px) {
  .ym-control-block--sections {
    justify-items: stretch;
  }

  .ym-control-panel :deep(.ym-section-filter) {
    width: 100%;
    max-height: none;
    overflow: visible;
    column-gap: 0.48rem;
    row-gap: 0.48rem;
  }

  .ym-control-panel :deep(.ym-section-chip) {
    min-width: 0;
    max-width: 100%;
    white-space: normal;
    overflow-wrap: anywhere;
  }
}

.ym-control-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.42rem;
  width: fit-content;
  max-width: 100%;
  overflow: visible;
  border: 1px solid color-mix(in srgb, var(--ym-card-border) 84%, rgba(129, 140, 248, 0.12));
  border-radius: 19px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 88%, rgba(255, 255, 255, 0.05)), var(--ym-control-bg));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    0 10px 24px rgba(2, 6, 23, 0.08);
  padding: 0.35rem;
}

.ym-control-pill {
  min-height: 44px;
  border: 1px solid transparent;
  border-radius: 14px;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 900;
  padding: 0 1rem;
  transition: transform 160ms ease, background 160ms ease, border-color 160ms ease, color 160ms ease, box-shadow 160ms ease;
}

.ym-control-pill.is-active {
  border-color: rgba(129, 140, 248, 0.38);
  background:
    linear-gradient(135deg, rgba(99, 102, 241, 0.28), rgba(236, 72, 153, 0.18));
  color: var(--ym-text);
  box-shadow:
    0 12px 24px rgba(99, 102, 241, 0.16),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

.ym-control-pill:hover {
  border-color: rgba(129, 140, 248, 0.28);
  background: rgba(99, 102, 241, 0.14);
  color: var(--ym-text);
  box-shadow: 0 10px 20px rgba(99, 102, 241, 0.12);
  transform: translateY(-1px);
}

.ym-control-pill:focus-visible {
  outline: 2px solid rgba(56, 189, 248, 0.68);
  outline-offset: 3px;
}

.ym-control-panel :deep(.ym-control-group) {
  border-color: color-mix(in srgb, var(--ym-card-border) 84%, rgba(129, 140, 248, 0.12));
  border-radius: 19px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 88%, rgba(255, 255, 255, 0.05)), var(--ym-control-bg));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.1),
    0 10px 24px rgba(2, 6, 23, 0.08);
}

.ym-control-panel :deep(.ym-control-pill) {
  border: 1px solid transparent;
  border-radius: 14px;
}

.ym-control-panel :deep(.ym-control-pill.is-active) {
  border-color: rgba(129, 140, 248, 0.38);
  background:
    linear-gradient(135deg, rgba(99, 102, 241, 0.28), rgba(236, 72, 153, 0.18));
  box-shadow:
    0 12px 24px rgba(99, 102, 241, 0.16),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

.ym-control-panel :deep(.ym-control-pill:hover) {
  border-color: rgba(129, 140, 248, 0.28);
  background: rgba(99, 102, 241, 0.14);
}

.ym-control-panel :deep(.ym-control-pill:focus-visible),
.ym-control-panel :deep(.ym-section-chip:focus-visible) {
  outline: 2px solid rgba(56, 189, 248, 0.68);
  outline-offset: 3px;
}

.ym-control-panel :deep(.ym-section-chip) {
  border-color: color-mix(in srgb, var(--ym-card-border) 80%, rgba(129, 140, 248, 0.1));
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 86%, rgba(255, 255, 255, 0.05)), var(--ym-control-bg));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
}

.ym-control-panel :deep(.ym-section-chip.is-active) {
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--section-color) 26%, transparent), color-mix(in srgb, #6366f1 14%, transparent));
  box-shadow:
    0 12px 26px color-mix(in srgb, var(--section-color) 15%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

:global(.ym-dashboard-light) .ym-control-panel {
  border-color: color-mix(in srgb, var(--ym-card-border) 88%, rgba(109, 40, 217, 0.18));
  background:
    radial-gradient(circle at 10% 0%, rgba(236, 72, 153, 0.08), transparent 18rem),
    radial-gradient(circle at 92% 12%, rgba(14, 165, 233, 0.09), transparent 20rem),
    linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 244, 255, 0.96)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 42px rgba(76, 29, 149, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.62),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06);
}

:global(.ym-dashboard-light) .ym-control-panel::after {
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.22), transparent 68%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.15), transparent 34%);
}

:global(.ym-dashboard-light) .ym-control-panel:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 72%, rgba(109, 40, 217, 0.3));
  box-shadow:
    0 28px 68px rgba(76, 29, 149, 0.15),
    0 0 34px rgba(129, 140, 248, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.68),
    inset 0 -1px 0 rgba(109, 40, 217, 0.07);
}

:global(.ym-dashboard-light) .ym-control-panel-head,
:global(.ym-dashboard-light) .ym-control-block--sections {
  border-color: color-mix(in srgb, var(--ym-soft-border) 84%, rgba(109, 40, 217, 0.12));
}

:global(.ym-dashboard-light) .ym-control-group,
:global(.ym-dashboard-light) .ym-control-panel :deep(.ym-control-group) {
  border-color: color-mix(in srgb, var(--ym-card-border) 86%, rgba(109, 40, 217, 0.12));
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.74), rgba(248, 244, 255, 0.92));
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.46),
    0 10px 22px rgba(76, 29, 149, 0.08);
}

:global(.ym-dashboard-light) .ym-control-pill.is-active,
:global(.ym-dashboard-light) .ym-control-panel :deep(.ym-control-pill.is-active) {
  border-color: rgba(109, 40, 217, 0.36);
  background:
    linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(236, 72, 153, 0.14));
  box-shadow:
    0 12px 24px rgba(109, 40, 217, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.44);
}

:global(.ym-dashboard-light) .ym-control-panel :deep(.ym-section-chip) {
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(248, 244, 255, 0.92));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42);
}

@media (prefers-reduced-motion: reduce) {
  .ym-control-panel:hover,
  .ym-control-pill:hover,
  .ym-control-panel :deep(.ym-control-pill:hover),
  .ym-control-panel :deep(.ym-section-chip:hover) {
    transform: none;
  }
}

.ym-floating-tooltip {
  position: fixed;
  z-index: 2147483647;
  max-width: min(260px, calc(100vw - 24px));
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 12px;
  background: rgba(15, 23, 42, 0.96);
  box-shadow: 0 18px 42px rgba(2, 6, 23, 0.32);
  color: #fff;
  font-size: 12px;
  font-weight: 850;
  line-height: 1.45;
  padding: 0.5rem 0.7rem;
  pointer-events: none;
  white-space: nowrap;
}

.ym-control-floating-tooltip {
  transform: translateX(-50%);
}

.ym-section-title {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
}

.ym-side-panel {
  padding: 1.25rem;
}

.ym-side-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: var(--ym-control-bg);
  padding: 0.9rem;
}

.ym-side-row span {
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 850;
}

.ym-side-row strong {
  color: var(--ym-text);
  font-size: 17px;
  font-weight: 950;
}

@media (max-width: 640px) {
  .ym-dashboard-page.space-y-7 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 1rem;
  }

  .ym-dashboard-page > section.grid,
  .ym-dashboard-page > section.space-y-5,
  .ym-dashboard-page > section.grid > div.grid {
    gap: 1rem;
  }

  .ym-dashboard-page > section.space-y-5 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 1rem;
  }

  .ym-prototype-notice {
    gap: 0.55rem;
    border-radius: 16px;
    padding: 0.72rem 0.8rem;
  }

  .ym-prototype-notice__badge {
    padding: 0.28rem 0.58rem;
    font-size: 12px;
  }

  .ym-prototype-notice p {
    font-size: 13px;
    line-height: 1.55;
  }

  .ym-hero-card,
  .ym-side-panel {
    border-radius: 22px;
  }

  .ym-hero-card {
    padding: 1rem;
  }

  .ym-admin-hero::before {
    border-radius: 21px;
  }

  .ym-hero-grid {
    background-size: 40px 40px;
    opacity: 0.35;
  }

  .ym-hero-content {
    gap: 1rem;
  }

  .ym-hero-content > .flex {
    align-items: flex-start;
    gap: 0.85rem;
  }

  .ym-hero-avatar {
    width: 68px;
    height: 68px;
    flex-basis: 68px;
    border-radius: 20px;
    box-shadow:
      0 16px 34px rgba(15, 23, 42, 0.22),
      inset 0 1px 0 rgba(255, 255, 255, 0.34),
      inset 0 -1px 0 rgba(15, 23, 42, 0.1);
  }

  .ym-hero-chips {
    gap: 0.35rem;
    margin-bottom: 0.45rem;
  }

  .ym-hero-chip {
    gap: 0.3rem;
    padding: 0.22rem 0.52rem;
    font-size: 11.75px;
  }

  .ym-hero-chip-dot {
    width: 6px;
    height: 6px;
  }

  .ym-hero-kicker {
    margin-bottom: 0.2rem;
    font-size: 13px;
  }

  .ym-hero-title {
    font-size: clamp(1.55rem, 8vw, 1.9rem);
    line-height: 1.12;
  }

  .ym-hero-copy {
    margin-top: 0.35rem;
    font-size: 13.5px;
    line-height: 1.55;
  }

  .ym-hero-summary {
    min-width: 0;
    width: 100%;
    border-radius: 16px;
    padding: 0.72rem 0.8rem;
  }

  .ym-hero-summary strong {
    font-size: 15.5px;
    line-height: 1.35;
    margin: 0.12rem 0;
  }

  .ym-hero-orb {
    filter: blur(28px);
    opacity: 0.2;
  }

  .ym-hero-orb-one {
    width: 10rem;
    height: 10rem;
  }

  .ym-hero-orb-two {
    width: 11rem;
    height: 11rem;
  }

  .ym-hero-orb-three {
    width: 9rem;
    height: 9rem;
  }

  .ym-control-panel {
    gap: 0.8rem;
    border-radius: 22px;
    padding: 0.95rem;
  }

  .ym-control-panel::before {
    inset-inline: 0.9rem;
    height: 2px;
  }

  .ym-control-panel::after {
    height: 42%;
    border-radius: 21px 21px 0 0;
  }

  .ym-control-panel-head {
    padding-bottom: 0.72rem;
  }

  .ym-controls-row {
    gap: 0.78rem;
  }

  .ym-control-block {
    gap: 0.45rem;
  }

  .ym-control-block--sections {
    margin-top: 0;
    padding-top: 0.72rem;
  }

  .ym-control-panel :deep(.ym-section-filter) {
    width: 100%;
    max-height: none;
    row-gap: 0.35rem;
    overflow: visible;
  }

  .ym-control-block > span {
    font-size: 12.5px;
  }

  .ym-control-panel h3,
  .ym-section-title h3,
  .ym-side-panel h3 {
    font-size: 18px;
    line-height: 1.22;
  }

  .ym-control-panel p,
  .ym-section-title p {
    font-size: 13px;
    line-height: 1.48;
  }

  .ym-control-group,
  .ym-control-panel :deep(.ym-control-group) {
    gap: 0.3rem;
    border-radius: 15px;
    padding: 0.25rem;
  }

  .ym-control-pill,
  .ym-control-panel :deep(.ym-control-pill) {
    min-height: 38px;
    border-radius: 11px;
    padding: 0 0.72rem;
    font-size: 13.5px;
  }

  .ym-section-title {
    align-items: flex-start;
    flex-direction: column;
    gap: 0.25rem;
  }

  .ym-side-panel {
    padding: 0.95rem;
  }

  .ym-side-panel .space-y-3 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.65rem;
  }

  .ym-side-row {
    gap: 0.7rem;
    border-radius: 13px;
    padding: 0.72rem;
  }

  .ym-side-row span {
    font-size: 13.5px;
  }

  .ym-side-row strong {
    font-size: 15.5px;
  }
}

@media (max-width: 430px) {
  .ym-dashboard-page.space-y-7 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.85rem;
  }

  .ym-hero-card {
    padding: 0.85rem;
  }

  .ym-hero-content {
    gap: 0.85rem;
  }

  .ym-hero-content > .flex {
    gap: 0.7rem;
  }

  .ym-hero-avatar {
    width: 60px;
    height: 60px;
    flex-basis: 60px;
    border-radius: 18px;
  }

  .ym-hero-title {
    font-size: clamp(1.42rem, 7.4vw, 1.72rem);
  }

  .ym-hero-copy {
    font-size: 13px;
    line-height: 1.5;
  }

  .ym-control-panel {
    gap: 0.68rem;
    padding: 0.82rem;
  }

  .ym-control-panel h3,
  .ym-section-title h3,
  .ym-side-panel h3 {
    font-size: 17px;
  }

  .ym-control-panel p,
  .ym-section-title p {
    font-size: 12.75px;
  }

  .ym-control-pill,
  .ym-control-panel :deep(.ym-control-pill) {
    min-height: 36px;
    padding: 0 0.62rem;
    font-size: 13px;
  }

  .ym-control-block--sections {
    padding-bottom: 0.18rem;
  }

  .ym-control-panel :deep(.ym-section-filter) {
    row-gap: 0.32rem;
    padding-bottom: 0.15rem;
  }

  .ym-dashboard-page > section.grid,
  .ym-dashboard-page > section.space-y-5,
  .ym-dashboard-page > section.grid > div.grid {
    gap: 0.85rem;
  }

  .ym-dashboard-page > section.space-y-5 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.85rem;
  }

  .ym-side-panel {
    padding: 0.82rem;
  }
}
</style>
