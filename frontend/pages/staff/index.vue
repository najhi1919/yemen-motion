<template>
  <div class="ym-staff-page">
    <section class="ym-staff-hero">
      <div class="ym-staff-hero__glow" />
      <div class="ym-staff-hero__orb ym-staff-hero__orb-one" />
      <div class="ym-staff-hero__orb ym-staff-hero__orb-two" />
      <div class="ym-staff-hero__grid" aria-hidden="true" />
      <div class="ym-staff-hero-content">
        <div class="flex min-w-0 items-center gap-5">
          <div class="ym-staff-avatar">
            <img
              :src="heroAvatar"
              :alt="auth.user?.name || copy.fallbackName"
              :class="auth.user?.avatar ? 'h-full w-full object-cover' : 'h-full w-full object-contain p-3'"
            />
          </div>
          <div class="min-w-0">
            <p class="ym-staff-kicker">{{ copy.greeting }}</p>
            <h2 class="ym-staff-title">{{ auth.user?.name || copy.fallbackName }}</h2>
            <p class="ym-staff-copy">{{ copy.heroCopy }}</p>
          </div>
        </div>
        <div class="ym-staff-focus">
          <span>{{ copy.todayFocus }}</span>
          <strong>{{ copy.reviewQueue }}</strong>
          <small>{{ copy.placeholder }}</small>
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

    <section class="ym-staff-metrics grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
      <DashboardMetricCard
        v-for="card in visibleKpis"
        :key="card.key"
        :label="card.label[currentLocale]"
        :value="card.value"
        :subtitle="card.subtitle[currentLocale]"
        :trend="card.trend"
        :color="card.color"
        :icon="card.icon"
        :locale="currentLocale"
        :trend-label="copy.trendLabel"
      />
    </section>

    <section class="ym-staff-operations grid grid-cols-1 gap-5 xl:grid-cols-3">
      <div class="xl:col-span-2">
        <DashboardActivityFeed :title="copy.activityTitle" :items="visibleActivities" :empty-label="copy.emptyActivity">
          <template #actions>
            <button class="ym-inline-action" :aria-label="copy.viewAll">{{ copy.viewAll }}</button>
          </template>
        </DashboardActivityFeed>
      </div>
      <aside class="ym-staff-panel">
        <h3>{{ copy.tasksTitle }}</h3>
        <div class="space-y-3">
          <article v-for="task in tasks" :key="task.label[currentLocale]" class="ym-task-row">
            <span :style="{ background: task.color }" />
            <div>
              <strong>{{ task.label[currentLocale] }}</strong>
              <small>{{ task.priority[currentLocale] }}</small>
            </div>
          </article>
        </div>
      </aside>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'staff' })

type Locale = 'ar' | 'en'

type DashboardLocalizedLabel = {
  ar: string
  en: string
}

type DashboardOverviewCard = {
  key: string
  label?: DashboardLocalizedLabel
  value?: number
  change?: number
  trend?: 'up' | 'down' | 'neutral' | string
  section?: string
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
  cards: DashboardOverviewCard[]
  activities: DashboardOverviewActivity[]
}

type DashboardOverviewResponse = {
  success: boolean
  data: DashboardOverviewData | null
  message?: string
  errors?: Record<string, string[]> | null
}

const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const { apiFetch } = useApiClient()

const copyMap = {
  ar: {
    greeting: 'مرحباً بك في مساحة الفريق',
    fallbackName: 'عضو الفريق',
    heroCopy: 'واجهة مراجعة وتشغيل واضحة للمحتوى، البلاغات، وتذاكر الدعم اليومية.',
    todayFocus: 'تركيز اليوم',
    reviewQueue: 'المراجعة والدعم',
    placeholder: 'بيانات تجريبية ثابتة لهذه المرحلة',
    prototypeBadge: 'بيانات تجريبية',
    prototypeNotice: 'بيانات تجريبية للمعاينة البصرية فقط — سيتم استبدالها لاحقًا بإحصاءات حقيقية من API.',
    activityTitle: 'آخر نشاطات الفريق',
    emptyActivity: 'لا يوجد نشاط حديث',
    viewAll: 'عرض الكل',
    tasksTitle: 'مهام اليوم',
    trendLabel: 'مقارنة بالفترة السابقة'
  },
  en: {
    greeting: 'Welcome to the team workspace',
    fallbackName: 'Team Member',
    heroCopy: 'A clear review and operations surface for content, reports, and daily support tickets.',
    todayFocus: 'Today focus',
    reviewQueue: 'Review & support',
    placeholder: 'Static placeholder data for this phase',
    prototypeBadge: 'Prototype',
    prototypeNotice: 'Demo data for visual preview only — it will be replaced later with real API statistics.',
    activityTitle: 'Latest team activity',
    emptyActivity: 'No recent activity',
    viewAll: 'View all',
    tasksTitle: 'Today tasks',
    trendLabel: 'vs previous period'
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const heroAvatar = computed(() => auth.user?.avatar || '/logo.svg')

const fallbackKpis = [
  { key: 'review', value: 47, trend: -5.2, color: '#f59e0b', icon: '◉', label: { ar: 'محتوى للمراجعة', en: 'Pending review' }, subtitle: { ar: 'عنصر معلّق', en: 'Items waiting' } },
  { key: 'done', value: 23, trend: 12, color: '#10b981', icon: '✓', label: { ar: 'تمت المراجعة اليوم', en: 'Reviewed today' }, subtitle: { ar: 'عنصر مكتمل', en: 'Completed items' } },
  { key: 'flags', value: 8, trend: -2.1, color: '#ef4444', icon: '!', label: { ar: 'بلاغات نشطة', en: 'Active flags' }, subtitle: { ar: 'بلاغ يتطلب مراجعة', en: 'Need review' } },
  { key: 'response', value: 98, trend: 1.5, color: '#0ea5e9', icon: '↯', label: { ar: 'معدل الاستجابة', en: 'Response rate' }, subtitle: { ar: 'نسبة مئوية', en: 'Percent score' } }
]

const fallbackActivities = computed(() => {
  const items = {
    ar: [
      { icon: '▰', title: 'مشروع جديد يحتاج مراجعة', description: 'تصميم هوية بصرية مقدم من عميل جديد', time: 'منذ 10 دقائق', type: 'info' as const },
      { icon: '✓', title: 'تمت الموافقة على محتوى', description: 'فيديو ترويجي اجتاز معايير النشر', time: 'منذ 25 دقيقة', type: 'success' as const },
      { icon: '!', title: 'محتوى مخالف تم التعامل معه', description: 'تم إخطار المالك بالإجراء المطلوب', time: 'منذ 45 دقيقة', type: 'error' as const },
      { icon: '?', title: 'رسالة دعم جديدة', description: 'عميل يطلب توضيح حالة طلب', time: 'منذ ساعة', type: 'warning' as const }
    ],
    en: [
      { icon: '▰', title: 'New project needs review', description: 'Brand identity submitted by a new client', time: '10 minutes ago', type: 'info' as const },
      { icon: '✓', title: 'Content approved', description: 'Promo video passed publishing standards', time: '25 minutes ago', type: 'success' as const },
      { icon: '!', title: 'Flagged content handled', description: 'Owner was notified about required action', time: '45 minutes ago', type: 'error' as const },
      { icon: '?', title: 'New support message', description: 'Client asks about order status', time: '1 hour ago', type: 'warning' as const }
    ]
  }
  return items[currentLocale.value]
})

const dashboardOverview = ref<DashboardOverviewData | null>(null)
const dashboardOverviewLoading = ref(false)
const dashboardOverviewError = ref<string | null>(null)
const overviewPeriod = 'month'

const dashboardOverviewCards = computed(() => dashboardOverview.value?.cards || [])

const visibleKpis = computed(() => {
  if (!dashboardOverviewCards.value.length) return fallbackKpis

  return dashboardOverviewCards.value.slice(0, 4).map((card, index) => {
    const fallback = fallbackKpis[index] || fallbackKpis[0]
    const change = Number(card.change ?? 0)
    const trend = card.trend === 'down'
      ? -Math.abs(change)
      : card.trend === 'up'
        ? Math.abs(change)
        : change

    return {
      key: card.section || card.key || fallback.key,
      value: Number(card.value ?? fallback.value),
      trend,
      color: fallback.color,
      icon: fallback.icon,
      label: {
        ar: card.label?.ar || fallback.label.ar,
        en: card.label?.en || fallback.label.en
      },
      subtitle: fallback.subtitle
    }
  })
})

const dashboardOverviewActivities = computed(() => dashboardOverview.value?.activities || [])

const visibleActivities = computed(() => {
  if (!dashboardOverviewActivities.value.length) return fallbackActivities.value

  return dashboardOverviewActivities.value.map(activity => ({
    icon: activity.icon || '▰',
    title: activity.title || activity.label?.[currentLocale.value] || activity.label?.ar || activity.key || String(activity.id || ''),
    description: activity.description || (currentLocale.value === 'ar' ? 'نشاط من بيانات لوحة الفريق' : 'Team dashboard activity'),
    time: activity.time || '',
    type: 'info' as const
  }))
})

const overviewStatusMessage = computed(() => {
  if (dashboardOverviewLoading.value) {
    return currentLocale.value === 'ar'
      ? 'يتم تحديث بيانات لوحة الفريق من API...'
      : 'Updating team dashboard data from the API...'
  }

  if (dashboardOverviewError.value) return dashboardOverviewError.value

  return ''
})

async function fetchDashboardOverview(): Promise<void> {
  dashboardOverviewLoading.value = true
  dashboardOverviewError.value = null

  try {
    const response = await apiFetch<DashboardOverviewResponse>('/dashboard/overview', {
      query: { period: overviewPeriod }
    })

    dashboardOverview.value = response.success ? response.data : null
  } catch {
    dashboardOverview.value = null
    dashboardOverviewError.value = currentLocale.value === 'ar'
      ? 'تعذر جلب بيانات لوحة الفريق، يتم عرض البيانات الاحتياطية.'
      : 'Could not load team dashboard data. Showing fallback data.'
  } finally {
    dashboardOverviewLoading.value = false
  }
}

onMounted(() => {
  void fetchDashboardOverview()
})

const tasks = [
  { color: '#f59e0b', label: { ar: 'مراجعة 12 تصميم معلّق', en: 'Review 12 pending designs' }, priority: { ar: 'الأولوية: عالية', en: 'Priority: High' } },
  { color: '#0ea5e9', label: { ar: 'الرد على 5 رسائل دعم', en: 'Reply to 5 support messages' }, priority: { ar: 'الأولوية: متوسطة', en: 'Priority: Medium' } },
  { color: '#ef4444', label: { ar: 'مراجعة بلاغ محتوى #89', en: 'Review content flag #89' }, priority: { ar: 'الأولوية: عالية', en: 'Priority: High' } },
  { color: '#94a3b8', label: { ar: 'تحديث ملاحظات الإعدادات', en: 'Update settings notes' }, priority: { ar: 'الأولوية: منخفضة', en: 'Priority: Low' } }
]
</script>

<style scoped>
.ym-staff-page {
  display: grid;
  gap: clamp(1.25rem, 2.4vw, 1.75rem);
  position: relative;
  border-radius: 32px;
  isolation: isolate;
}

.ym-prototype-notice {
  position: relative;
  display: flex;
  align-items: center;
  gap: 0.9rem;
  overflow: hidden;
  border: 1px solid rgba(245, 158, 11, 0.34);
  border-radius: 20px;
  background:
    linear-gradient(180deg, rgba(245, 158, 11, 0.11), rgba(245, 158, 11, 0.07)),
    color-mix(in srgb, var(--ym-control-bg) 62%, transparent);
  padding: 0.9rem 1rem;
  color: var(--ym-text);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.12),
    0 12px 28px rgba(245, 158, 11, 0.07);
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

.ym-prototype-notice__status {
  display: block;
  margin-top: 0.25rem;
  color: color-mix(in srgb, var(--ym-muted) 86%, #f59e0b);
  font-size: 12.5px;
  font-weight: 850;
  line-height: 1.6;
}

.ym-prototype-notice__status.is-error {
  color: #ef4444;
}

@media (max-width: 640px) {
  .ym-prototype-notice {
    align-items: flex-start;
    flex-direction: column;
  }
}

.ym-staff-hero,
.ym-staff-panel {
  position: relative;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--ym-card-border) 88%, rgba(45, 212, 191, 0.16));
  border-radius: 28px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 16px 40px rgba(2, 6, 23, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
}

.ym-staff-hero {
  border-color: rgba(255, 255, 255, 0.19);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 15rem),
    radial-gradient(circle at 84% 8%, rgba(45, 212, 191, 0.28), transparent 18rem),
    radial-gradient(circle at 96% 92%, rgba(99, 102, 241, 0.24), transparent 22rem),
    linear-gradient(135deg, rgba(4, 120, 87, 0.98), rgba(13, 148, 136, 0.92) 48%, rgba(14, 165, 233, 0.76));
  box-shadow:
    0 34px 80px rgba(6, 95, 70, 0.28),
    0 14px 32px rgba(2, 6, 23, 0.18),
    inset 0 1px 0 rgba(255, 255, 255, 0.3),
    inset 0 -1px 0 rgba(15, 23, 42, 0.14),
    inset 0 0 0 1px rgba(255, 255, 255, 0.04);
  padding: clamp(1.35rem, 3vw, 2.25rem);
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-staff-hero::before {
  position: absolute;
  inset: 1px;
  border-radius: 27px;
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0.13), transparent 34%),
    linear-gradient(290deg, rgba(209, 250, 229, 0.1), transparent 43%);
  content: "";
  pointer-events: none;
}

.ym-staff-hero::after {
  position: absolute;
  inset-inline: 7%;
  top: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(209, 250, 229, 0.72), transparent);
  content: "";
  pointer-events: none;
}

.ym-staff-hero:hover {
  box-shadow:
    0 38px 88px rgba(6, 95, 70, 0.32),
    0 16px 36px rgba(2, 6, 23, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.33),
    inset 0 -1px 0 rgba(15, 23, 42, 0.14),
    inset 0 0 0 1px rgba(255, 255, 255, 0.05);
  transform: translateY(-2px);
}

.ym-staff-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

@media (min-width: 1024px) {
  .ym-staff-hero-content {
    align-items: center;
    flex-direction: row;
    justify-content: space-between;
  }
}

.ym-staff-hero__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-position: center;
  background-size: 48px 48px;
  -webkit-mask-image: radial-gradient(circle at 30% 30%, #000 0%, transparent 72%);
  mask-image: radial-gradient(circle at 30% 30%, #000 0%, transparent 72%);
  opacity: 0.42;
  pointer-events: none;
}

.ym-staff-hero__glow {
  position: absolute;
  inset-inline-end: -5rem;
  top: -5rem;
  height: 18rem;
  width: 18rem;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.28);
  filter: blur(44px);
  opacity: 0.32;
}

.ym-staff-hero__orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(36px);
  opacity: 0.22;
  pointer-events: none;
}

.ym-staff-hero__orb-one {
  bottom: -5rem;
  inset-inline-start: 18%;
  height: 15rem;
  width: 15rem;
  background: rgba(56, 189, 248, 0.2);
}

.ym-staff-hero__orb-two {
  top: 28%;
  inset-inline-start: -4rem;
  height: 12rem;
  width: 12rem;
  background: rgba(167, 139, 250, 0.18);
}

.ym-staff-avatar {
  display: grid;
  height: 92px;
  width: 92px;
  flex: 0 0 92px;
  place-items: center;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.35);
  border-radius: 26px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.22), rgba(255, 255, 255, 0.12)),
    rgba(255, 255, 255, 0.15);
  box-shadow:
    0 24px 48px rgba(6, 78, 59, 0.22),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(15, 23, 42, 0.1);
  color: #fff;
  font-size: 2rem;
  font-weight: 950;
}

.ym-staff-kicker,
.ym-staff-copy,
.ym-staff-focus span,
.ym-staff-focus small {
  color: rgba(255, 255, 255, 0.9);
  font-weight: 850;
}

.ym-staff-kicker {
  font-size: 14.5px;
  letter-spacing: 0.01em;
  margin: 0 0 0.3rem;
}

.ym-staff-title {
  color: #fff;
  font-size: clamp(2.1rem, 3.4vw, 2.75rem);
  font-weight: 950;
  line-height: 1.05;
  margin: 0;
  text-shadow: 0 2px 16px rgba(6, 95, 70, 0.35);
}

.ym-staff-copy {
  font-size: 15.5px;
  line-height: 1.75;
  margin: 0.5rem 0 0;
  max-width: 52rem;
}

.ym-staff-focus {
  min-width: min(100%, 260px);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.17), rgba(255, 255, 255, 0.1)),
    rgba(255, 255, 255, 0.11);
  box-shadow:
    0 20px 48px rgba(6, 78, 59, 0.18),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  padding: 1.05rem 1.1rem;
  backdrop-filter: blur(10px);
}

.ym-staff-focus strong {
  display: block;
  color: #fff;
  font-size: 18px;
  font-weight: 950;
  line-height: 1.5;
  margin: 0.2rem 0;
}

.ym-inline-action {
  border: 1px solid color-mix(in srgb, #38bdf8 32%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-control-bg) 74%, transparent);
  color: #38bdf8;
  font-size: 14px;
  font-weight: 950;
  padding: 0.45rem 0.75rem;
  transition: transform 160ms ease, background 160ms ease, border-color 160ms ease;
}

.ym-inline-action:hover {
  border-color: color-mix(in srgb, #38bdf8 52%, transparent);
  background: rgba(56, 189, 248, 0.12);
  transform: translateY(-1px);
}

.ym-staff-panel {
  padding: clamp(1.15rem, 2vw, 1.35rem);
  transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
}

.ym-staff-panel::before {
  position: absolute;
  inset-inline-start: 1.25rem;
  top: 0;
  height: 3px;
  width: min(12rem, calc(100% - 2.5rem));
  border-end-end-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(90deg, #10b981, #38bdf8);
  box-shadow: 0 0 22px rgba(45, 212, 191, 0.18);
  content: "";
  pointer-events: none;
}

.ym-staff-panel::after {
  position: absolute;
  inset: 1px;
  inset-block-end: auto;
  height: 50%;
  border-radius: 27px 27px 0 0;
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 70%);
  content: "";
  pointer-events: none;
}

.ym-staff-panel > * {
  position: relative;
  z-index: 1;
}

.ym-staff-panel:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 72%, rgba(45, 212, 191, 0.28));
  box-shadow:
    0 24px 60px rgba(2, 6, 23, 0.18),
    0 0 32px rgba(45, 212, 191, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.2),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  transform: translateY(-1px);
}

.ym-staff-panel h3 {
  color: var(--ym-text);
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 82%, rgba(45, 212, 191, 0.12));
  font-size: clamp(20px, 2vw, 23px);
  font-weight: 950;
  line-height: 1.25;
  margin: 0 0 1rem;
  padding-bottom: 0.9rem;
}

.ym-task-row {
  position: relative;
  display: flex;
  gap: 0.85rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 78%, transparent);
  border-radius: 18px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 86%, rgba(255, 255, 255, 0.04)), var(--ym-control-bg));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 1rem;
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;
}

.ym-task-row:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 80%, rgba(45, 212, 191, 0.18));
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-row-hover) 80%, rgba(45, 212, 191, 0.08)), var(--ym-row-hover));
  box-shadow:
    0 14px 30px rgba(45, 212, 191, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  transform: translateY(-1px);
}

.ym-task-row > span {
  height: 13px;
  width: 13px;
  flex: 0 0 13px;
  border-radius: 999px;
  margin-top: 0.32rem;
  box-shadow: 0 0 0 4px color-mix(in srgb, currentColor 13%, transparent), 0 0 18px currentColor;
}

.ym-task-row strong {
  display: block;
  color: var(--ym-text);
  font-size: 16px;
  font-weight: 950;
}

.ym-task-row small {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 850;
  line-height: 1.55;
}

:global(.ym-dashboard-light .ym-staff-page) {
  background: transparent;
}

:global(.ym-dashboard-light .ym-staff-page .ym-prototype-notice) {
  background:
    linear-gradient(180deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.07)),
    rgba(255, 255, 255, 0.62);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.46),
    0 12px 28px rgba(180, 83, 9, 0.08);
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-hero) {
  border-color: rgba(20, 184, 166, 0.34);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.5), transparent 14rem),
    radial-gradient(circle at 84% 8%, rgba(20, 184, 166, 0.22), transparent 18rem),
    radial-gradient(circle at 96% 92%, rgba(99, 102, 241, 0.14), transparent 22rem),
    linear-gradient(135deg, rgba(4, 120, 87, 0.94), rgba(13, 148, 136, 0.9) 48%, rgba(14, 165, 233, 0.78));
  box-shadow:
    0 34px 80px rgba(6, 95, 70, 0.18),
    0 14px 32px rgba(15, 23, 42, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.72),
    inset 0 -1px 0 rgba(20, 184, 166, 0.1);
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-hero::before) {
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0.28), transparent 34%),
    linear-gradient(290deg, rgba(209, 250, 229, 0.16), transparent 43%);
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-hero__grid) {
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.11) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.11) 1px, transparent 1px);
  opacity: 0.32;
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-hero__glow) {
  filter: blur(34px);
  opacity: 0.2;
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-focus) {
  border-color: rgba(255, 255, 255, 0.36);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.27), rgba(255, 255, 255, 0.17)),
    rgba(255, 255, 255, 0.2);
  box-shadow:
    0 22px 52px rgba(6, 95, 70, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.44),
    inset 0 -1px 0 rgba(20, 184, 166, 0.06);
  backdrop-filter: blur(8px);
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-panel) {
  border-color: color-mix(in srgb, var(--ym-card-border) 88%, rgba(20, 184, 166, 0.16));
  background:
    radial-gradient(circle at 90% 0%, rgba(20, 184, 166, 0.08), transparent 16rem),
    linear-gradient(180deg, rgba(255, 255, 255, 0.88), rgba(248, 244, 255, 0.95)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 42px rgba(76, 29, 149, 0.09),
    inset 0 1px 0 rgba(255, 255, 255, 0.6),
    inset 0 -1px 0 rgba(20, 184, 166, 0.05);
}

:global(.ym-dashboard-light .ym-staff-page .ym-staff-panel::after) {
  background: linear-gradient(180deg, rgba(255, 255, 255, 0.22), transparent 68%);
}

:global(.ym-dashboard-light .ym-staff-page .ym-task-row) {
  border-color: color-mix(in srgb, var(--ym-soft-border) 84%, rgba(20, 184, 166, 0.08));
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.72), rgba(248, 244, 255, 0.88));
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.42);
}

:global(.ym-dashboard-light .ym-staff-page .ym-task-row:hover) {
  box-shadow:
    0 14px 30px rgba(20, 184, 166, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.48);
}

/* ===== Mobile/Tablet density pass (P1-B) ===== */
/* تقليل كثافة الصفحة على التابلت دون لمس desktop */

@media (max-width: 1024px) {
  .ym-staff-page {
    gap: clamp(1rem, 2vw, 1.35rem);
  }

  .ym-staff-hero {
    padding: clamp(1.15rem, 2.4vw, 1.6rem);
  }

  .ym-staff-hero-content {
    gap: 1.15rem;
  }

  .ym-staff-panel {
    padding: clamp(1rem, 2vw, 1.2rem);
  }
}

/* الهاتف: تقليل padding وارتفاع العناصر مع الحفاظ على القراءة */

@media (max-width: 640px) {
  .ym-staff-page {
    gap: 1rem;
  }

  .ym-staff-hero {
    padding: 1.1rem;
  }

  .ym-staff-hero-content {
    gap: 1rem;
  }

  /* تقليل حجم الـ avatar وتباعده على الجوال لفتح مساحة للنص */
  .ym-staff-avatar {
    height: 72px;
    width: 72px;
    flex: 0 0 72px;
    border-radius: 20px;
  }

  /* الترتيب الرأسي للرأس يضغط gap من 1.25rem (gap-5) إلى 0.85rem */
  .ym-staff-hero-content > .flex {
    gap: 0.85rem;
  }

  .ym-staff-title {
    font-size: clamp(1.7rem, 6vw, 2rem);
    line-height: 1.1;
  }

  .ym-staff-copy {
    font-size: 14.5px;
    line-height: 1.65;
    margin-top: 0.4rem;
  }

  .ym-staff-focus {
    min-width: 100%;
    padding: 0.9rem;
  }

  .ym-staff-panel {
    padding: 1rem;
  }

  .ym-staff-panel h3 {
    font-size: 19px;
    margin-bottom: 0.8rem;
    padding-bottom: 0.7rem;
  }

  .ym-task-row {
    gap: 0.75rem;
    padding: 0.85rem;
  }

  .ym-task-row strong {
    font-size: 15px;
  }

  .ym-task-row small {
    font-size: 13.5px;
    line-height: 1.5;
  }
}

/* الجوال الصغير جدًا: منع أي ضغط زائد أو overflow */

@media (max-width: 380px) {
  .ym-staff-page {
    gap: 0.85rem;
  }

  .ym-staff-hero {
    padding: 0.95rem;
  }

  .ym-staff-avatar {
    height: 64px;
    width: 64px;
    flex: 0 0 64px;
    border-radius: 18px;
  }

  .ym-staff-title {
    font-size: 1.55rem;
  }

  .ym-staff-copy {
    font-size: 14px;
  }

  .ym-staff-focus {
    padding: 0.85rem;
  }

  .ym-staff-focus strong {
    font-size: 16px;
  }

  .ym-task-row {
    padding: 0.75rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-staff-hero:hover,
  .ym-inline-action:hover,
  .ym-staff-panel:hover,
  .ym-task-row:hover {
    transform: none;
  }
}
</style>
