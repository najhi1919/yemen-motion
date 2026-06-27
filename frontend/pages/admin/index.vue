<template>
  <div class="ym-dashboard-page space-y-7">
    <section class="ym-hero-card ym-admin-hero">
      <div class="ym-hero-orb ym-hero-orb-one" />
      <div class="ym-hero-orb ym-hero-orb-two" />
      <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="flex min-w-0 items-center gap-5">
          <div class="ym-hero-avatar">
            <img
              :src="heroAvatar"
              :alt="auth.user?.name || copy.fallbackName"
              :class="auth.user?.avatar ? 'h-full w-full object-cover' : 'h-full w-full object-contain p-3'"
            />
          </div>
          <div class="min-w-0">
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
      <p>{{ copy.prototypeNotice }}</p>
    </aside>

    <section
      ref="controlPanelRef"
      class="ym-control-panel"
      @mouseover="showDelegatedControlTooltip"
      @focusin="showDelegatedControlTooltip"
      @mouseout="hideDelegatedControlTooltip"
      @focusout="hideDelegatedControlTooltip"
    >
      <div class="ym-control-panel-content">
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
      <div class="ym-control-block ym-control-panel-content">
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
        :total-label="copy.tooltipTotal"
        :detail-label="copy.tooltipDemo"
      />

      <div v-else class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <DashboardSvgChart
          v-for="section in activeSectionModels"
          :key="section.key"
          :title="section.label[currentLocale]"
          :subtitle="copy.individualHint"
          :labels="periodLabels"
          :data="sectionBreakdown(section).map(item => item.value)"
          :line-color="section.color"
          :height="260"
          :period-label="periodLabel"
          :total-label="copy.tooltipTotal"
          :detail-label="copy.tooltipDemo"
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

const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const period = ref<Period>('month')
const viewMode = ref<ViewMode>('all')
const chartMode = ref<ChartMode>('individual')
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
    greeting: 'مرحباً بك في مركز التحكم',
    fallbackName: 'مدير المنصة',
    heroCopy: 'نظرة تشغيلية واضحة على الطلبات، المستخدمين، الإيرادات، البلاغات، وأداء الفريق.',
    activeScope: 'النطاق النشط',
    periodLabel: 'الفترة',
    prototypeBadge: 'بيانات تجريبية',
    prototypeNotice: 'بيانات تجريبية للمعاينة البصرية فقط — سيتم استبدالها لاحقًا بإحصاءات حقيقية من API.',
    controlsTitle: 'عناصر العرض التفاعلية',
    controlsSubtitle: 'غيّر الفترة، الأقسام، ونمط العرض لتحديث البيانات التجريبية فوراً.',
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
    tooltipTotal: 'الإجمالي',
    tooltipDemo: 'تفصيل تجريبي ثابت حسب موضع المؤشر',
    allSections: 'كل الأقسام',
    periodNames: { day: 'اليوم', week: 'الأسبوع', month: 'الشهر', year: 'السنة' },
    quick: [
      ['معدل إتمام الطلبات', '94.2%'],
      ['متوسط التسليم', '3.2 يوم'],
      ['رضا العملاء', '4.8/5'],
      ['بلاغات مفتوحة', '12']
    ]
  },
  en: {
    greeting: 'Welcome to the command center',
    fallbackName: 'Platform Admin',
    heroCopy: 'A clear operational view across orders, users, revenue, reports, and team performance.',
    activeScope: 'Active scope',
    periodLabel: 'Period',
    prototypeBadge: 'Prototype',
    prototypeNotice: 'Demo data for visual preview only — it will be replaced later with real API statistics.',
    controlsTitle: 'Interactive display controls',
    controlsSubtitle: 'Change period, sections, and view mode to update placeholder data immediately.',
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
    tooltipTotal: 'Total',
    tooltipDemo: 'Static demo detail by pointer position',
    allSections: 'All sections',
    periodNames: { day: 'Day', week: 'Week', month: 'Month', year: 'Year' },
    quick: [
      ['Order completion rate', '94.2%'],
      ['Average delivery', '3.2 days'],
      ['Client satisfaction', '4.8/5'],
      ['Open reports', '12']
    ]
  }
}

const sectionModels = [
  { key: 'orders', color: '#6366f1', icon: '◈', label: { ar: 'الطلبات', en: 'Orders' }, subtitle: { ar: 'طلبات نشطة', en: 'Active orders' }, base: 342, trend: 8.2, series: { day: [18, 25, 21, 31, 28, 36], week: [92, 130, 118, 145, 160, 176], month: [210, 260, 310, 342, 370, 420], year: [2100, 2450, 2720, 3180, 3640, 4100] } },
  { key: 'bookings', color: '#f59e0b', icon: '▣', label: { ar: 'الحجوزات', en: 'Bookings' }, subtitle: { ar: 'حجوزات استوديو', en: 'Studio bookings' }, base: 88, trend: 5.4, series: { day: [4, 6, 7, 5, 8, 9], week: [28, 34, 31, 44, 52, 61], month: [62, 70, 76, 88, 94, 101], year: [600, 720, 815, 920, 1030, 1180] } },
  { key: 'users', color: '#10b981', icon: '●', label: { ar: 'المستخدمون', en: 'Users' }, subtitle: { ar: 'مستخدمون جدد', en: 'New users' }, base: 12847, trend: 12.5, series: { day: [42, 58, 71, 89, 105, 128], week: [320, 420, 510, 620, 700, 790], month: [420, 580, 710, 890, 1050, 1280], year: [4200, 5800, 7100, 8900, 10500, 12847] } },
  { key: 'staff', color: '#06b6d4', icon: '◆', label: { ar: 'الموظفون', en: 'Staff' }, subtitle: { ar: 'أعضاء نشطون', en: 'Active members' }, base: 64, trend: 3.1, series: { day: [8, 9, 9, 10, 10, 11], week: [42, 44, 46, 50, 56, 64], month: [40, 43, 49, 55, 60, 64], year: [34, 38, 44, 51, 58, 64] } },
  { key: 'works', color: '#3b82f6', icon: '▰', label: { ar: 'الأعمال', en: 'Works' }, subtitle: { ar: 'أعمال منشورة', en: 'Published works' }, base: 2156, trend: 15.7, series: { day: [22, 28, 24, 36, 42, 48], week: [160, 190, 220, 260, 300, 340], month: [780, 900, 1120, 1320, 1640, 2156], year: [8400, 9200, 10100, 11900, 13800, 16000] } },
  { key: 'contests', color: '#8b5cf6', icon: '★', label: { ar: 'المسابقات', en: 'Contests' }, subtitle: { ar: 'مسابقات فعالة', en: 'Live contests' }, base: 19, trend: 2.2, series: { day: [1, 2, 1, 2, 3, 2], week: [7, 9, 10, 12, 15, 19], month: [8, 11, 13, 16, 18, 19], year: [24, 31, 39, 48, 55, 63] } },
  { key: 'wallet', color: '#22c55e', icon: '$', label: { ar: 'المحفظة', en: 'Wallet' }, subtitle: { ar: 'ألف دولار', en: 'USD thousands' }, base: 87500, trend: -3.1, series: { day: [8, 9, 11, 10, 13, 14], week: [32, 44, 50, 61, 72, 80], month: [45, 58, 66, 72, 81, 87], year: [420, 510, 640, 720, 810, 875] } },
  { key: 'reports', color: '#14b8a6', icon: '▤', label: { ar: 'التقارير', en: 'Reports' }, subtitle: { ar: 'تقارير تشغيلية', en: 'Operational reports' }, base: 38, trend: 6.8, series: { day: [2, 4, 4, 5, 6, 7], week: [12, 16, 21, 25, 31, 38], month: [18, 22, 26, 31, 34, 38], year: [120, 160, 210, 250, 310, 380] } },
  { key: 'analytics', color: '#a855f7', icon: '⌁', label: { ar: 'التحليلات', en: 'Analytics' }, subtitle: { ar: 'مؤشرات نشطة', en: 'Active metrics' }, base: 74, trend: 9.4, series: { day: [10, 12, 16, 15, 18, 22], week: [40, 48, 52, 60, 68, 74], month: [46, 50, 58, 64, 70, 74], year: [300, 380, 460, 540, 650, 740] } },
  { key: 'notifications', color: '#f97316', icon: '!', label: { ar: 'الإشعارات', en: 'Notifications' }, subtitle: { ar: 'تنبيهات مرسلة', en: 'Sent alerts' }, base: 512, trend: 4.6, series: { day: [30, 42, 50, 61, 58, 73], week: [120, 180, 240, 310, 420, 512], month: [260, 330, 380, 440, 490, 512], year: [2100, 2800, 3600, 4200, 5000, 6200] } },
  { key: 'flags', color: '#ef4444', icon: '▲', label: { ar: 'البلاغات', en: 'Reports/Flags' }, subtitle: { ar: 'بلاغات مفتوحة', en: 'Open flags' }, base: 27, trend: -7.2, series: { day: [8, 7, 6, 5, 4, 3], week: [42, 39, 36, 32, 30, 27], month: [58, 51, 44, 39, 32, 27], year: [700, 620, 540, 460, 350, 270] } },
  { key: 'support', color: '#0ea5e9', icon: '?', label: { ar: 'الدعم', en: 'Support' }, subtitle: { ar: 'تذاكر مفتوحة', en: 'Open tickets' }, base: 43, trend: -1.8, series: { day: [9, 8, 7, 8, 6, 5], week: [54, 50, 48, 46, 44, 43], month: [70, 63, 59, 52, 48, 43], year: [680, 640, 590, 530, 480, 430] } }
] as const

const selectedSections = ref<string[]>(sectionModels.map(section => section.key))

const copy = computed(() => copyMap[currentLocale.value])
const periodLabel = computed(() => copy.value.periodNames[period.value])
const localizedSections = computed(() => sectionModels.map(section => ({ key: section.key, color: section.color, icon: section.icon, label: section.label[currentLocale.value] })))
const activeSectionModels = computed(() => sectionModels.filter(section => selectedSections.value.includes(section.key)))

const selectedSectionsLabel = computed(() => {
  if (selectedSections.value.length === sectionModels.length) return copy.value.allSections
  return activeSectionModels.value.map(section => section.label[currentLocale.value]).join(currentLocale.value === 'ar' ? '، ' : ', ')
})

const periodMultiplier = computed(() => ({ day: 0.12, week: 0.38, month: 1, year: 8.4 })[period.value])

const visibleCards = computed(() => activeSectionModels.value.map(section => ({
  key: section.key,
  label: section.label[currentLocale.value],
  value: Math.round(section.base * periodMultiplier.value),
  subtitle: section.subtitle[currentLocale.value],
  trend: section.trend,
  color: section.color,
  icon: section.icon
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

function sectionBreakdown(section: typeof sectionModels[number]) {
  const source = section.series[period.value]
  const total = source[source.length - 1]
  return periodLabels.value.map((label, index) => ({
    label,
    value: Math.max(1, Math.round(total * periodBreakdownProfiles[period.value][index]))
  }))
}

const combinedBars = computed(() => activeSectionModels.value.map(section => ({
  key: section.key,
  label: section.label[currentLocale.value],
  value: section.series[period.value][section.series[period.value].length - 1],
  color: section.color,
  breakdown: sectionBreakdown(section)
})))

const localizedActivities = computed(() => {
  const items = {
    ar: [
      { icon: '●', title: 'طلب هوية بصرية دخل مرحلة المراجعة', description: 'قسم الطلبات تأثر بالفلاتر الحالية', time: 'منذ 5 دقائق', type: 'info' as const },
      { icon: '▲', title: 'انخفاض البلاغات المفتوحة', description: 'مؤشر جيد لفريق المراجعة', time: 'منذ 18 دقيقة', type: 'success' as const },
      { icon: '$', title: 'عملية محفظة تنتظر الاعتماد', description: 'طلب سحب أرباح تجريبي', time: 'منذ ساعة', type: 'warning' as const }
    ],
    en: [
      { icon: '●', title: 'Branding order entered review', description: 'Orders section responds to current filters', time: '5 minutes ago', type: 'info' as const },
      { icon: '▲', title: 'Open flags are trending down', description: 'A positive review-team signal', time: '18 minutes ago', type: 'success' as const },
      { icon: '$', title: 'Wallet action awaiting approval', description: 'Sample payout request', time: '1 hour ago', type: 'warning' as const }
    ]
  }
  return items[currentLocale.value]
})

const quickStats = computed(() => copy.value.quick.map(([label, value]) => ({ label, value })))

const heroAvatar = computed(() => auth.user?.avatar || '/logo.svg')

function toggleAllSections() {
  selectedSections.value = selectedSections.value.length === sectionModels.length ? [] : sectionModels.map(section => section.key)
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
.ym-control-panel,
.ym-side-panel {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 28px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.ym-hero-card {
  padding: clamp(1.35rem, 3vw, 2.25rem);
}

.ym-admin-hero {
  background:
    radial-gradient(circle at 20% 20%, rgba(255, 255, 255, 0.2), transparent 18rem),
    linear-gradient(135deg, rgba(79, 70, 229, 0.95), rgba(124, 58, 237, 0.88) 50%, rgba(190, 0, 1, 0.7));
}

.ym-hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(44px);
  opacity: 0.38;
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

.ym-hero-avatar {
  display: grid;
  height: 86px;
  width: 86px;
  flex: 0 0 86px;
  place-items: center;
  overflow: hidden;
  border: 2px solid rgba(255, 255, 255, 0.28);
  border-radius: 26px;
  background: rgba(255, 255, 255, 0.16);
  box-shadow: 0 24px 48px rgba(15, 23, 42, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.26);
  color: #fff;
  font-size: 2rem;
  font-weight: 950;
}

.ym-hero-kicker,
.ym-hero-copy,
.ym-hero-summary small,
.ym-hero-summary span {
  color: rgba(255, 255, 255, 0.9);
  font-weight: 850;
}

.ym-hero-kicker {
  font-size: 15px;
  margin: 0 0 0.25rem;
}

.ym-hero-title {
  color: #fff;
  font-size: clamp(2.1rem, 3.4vw, 2.75rem);
  font-weight: 950;
  line-height: 1.05;
  margin: 0;
}

.ym-hero-copy {
  font-size: 16px;
  line-height: 1.7;
  margin: 0.55rem 0 0;
  max-width: 56rem;
}

.ym-hero-summary {
  min-width: min(100%, 260px);
  border: 1px solid rgba(255, 255, 255, 0.18);
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.13);
  padding: 1rem;
  backdrop-filter: blur(18px);
}

.ym-hero-summary strong {
  display: block;
  color: #fff;
  font-size: 18px;
  font-weight: 950;
  line-height: 1.5;
  margin: 0.2rem 0;
}

.ym-control-panel {
  display: grid;
  min-width: 0;
  gap: 1.25rem;
  overflow: visible;
  padding: 1.45rem;
}

.ym-control-panel-content {
  position: relative;
  z-index: 2;
  min-width: 0;
}

.ym-controls-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  align-items: end;
  gap: 1.1rem;
}

.ym-control-block {
  display: grid;
  min-width: 0;
  gap: 0.6rem;
  justify-items: start;
  overflow: visible;
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
  font-size: 15px;
  font-weight: 950;
}

.ym-control-panel h3,
.ym-section-title h3,
.ym-side-panel h3 {
  color: var(--ym-text);
  font-size: 22px;
  font-weight: 950;
  margin: 0;
}

.ym-control-panel p,
.ym-section-title p {
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.6;
  margin: 0.2rem 0 0;
}

@media (max-width: 1180px) {
  .ym-controls-row {
    grid-template-columns: 1fr;
  }
}

.ym-control-group {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  width: fit-content;
  max-width: 100%;
  overflow: visible;
  border: 1px solid var(--ym-card-border);
  border-radius: 20px;
  background: var(--ym-control-bg);
  padding: 0.35rem;
}

.ym-control-pill {
  min-height: 44px;
  border-radius: 15px;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 900;
  padding: 0 1rem;
  transition: transform 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease;
}

.ym-control-pill:hover,
.ym-control-pill.is-active {
  background: rgba(99, 102, 241, 0.18);
  color: var(--ym-text);
  box-shadow: 0 12px 24px rgba(99, 102, 241, 0.14);
  transform: translateY(-1px);
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
</style>
