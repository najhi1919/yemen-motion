<template>
  <div class="ym-staff-page space-y-7">
    <section class="ym-staff-hero">
      <div class="ym-staff-hero__glow" />
      <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
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
      <p>{{ copy.prototypeNotice }}</p>
    </aside>

    <section class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
      <DashboardMetricCard
        v-for="card in kpis"
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

    <section class="grid grid-cols-1 gap-5 xl:grid-cols-3">
      <div class="xl:col-span-2">
        <DashboardActivityFeed :title="copy.activityTitle" :items="activities" :empty-label="copy.emptyActivity">
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

const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

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

const kpis = [
  { key: 'review', value: 47, trend: -5.2, color: '#f59e0b', icon: '◉', label: { ar: 'محتوى للمراجعة', en: 'Pending review' }, subtitle: { ar: 'عنصر معلّق', en: 'Items waiting' } },
  { key: 'done', value: 23, trend: 12, color: '#10b981', icon: '✓', label: { ar: 'تمت المراجعة اليوم', en: 'Reviewed today' }, subtitle: { ar: 'عنصر مكتمل', en: 'Completed items' } },
  { key: 'flags', value: 8, trend: -2.1, color: '#ef4444', icon: '!', label: { ar: 'بلاغات نشطة', en: 'Active flags' }, subtitle: { ar: 'بلاغ يتطلب مراجعة', en: 'Need review' } },
  { key: 'response', value: 98, trend: 1.5, color: '#0ea5e9', icon: '↯', label: { ar: 'معدل الاستجابة', en: 'Response rate' }, subtitle: { ar: 'نسبة مئوية', en: 'Percent score' } }
]

const activities = computed(() => {
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

const tasks = [
  { color: '#f59e0b', label: { ar: 'مراجعة 12 تصميم معلّق', en: 'Review 12 pending designs' }, priority: { ar: 'الأولوية: عالية', en: 'Priority: High' } },
  { color: '#0ea5e9', label: { ar: 'الرد على 5 رسائل دعم', en: 'Reply to 5 support messages' }, priority: { ar: 'الأولوية: متوسطة', en: 'Priority: Medium' } },
  { color: '#ef4444', label: { ar: 'مراجعة بلاغ محتوى #89', en: 'Review content flag #89' }, priority: { ar: 'الأولوية: عالية', en: 'Priority: High' } },
  { color: '#94a3b8', label: { ar: 'تحديث ملاحظات الإعدادات', en: 'Update settings notes' }, priority: { ar: 'الأولوية: منخفضة', en: 'Priority: Low' } }
]
</script>

<style scoped>
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

.ym-staff-hero,
.ym-staff-panel {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 28px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.ym-staff-hero {
  border-color: rgba(255, 255, 255, 0.19);
  background:
    radial-gradient(circle at 20% 18%, rgba(255, 255, 255, 0.2), transparent 15rem),
    radial-gradient(circle at 84% 8%, rgba(45, 212, 191, 0.26), transparent 18rem),
    linear-gradient(135deg, rgba(4, 120, 87, 0.96), rgba(13, 148, 136, 0.9) 50%, rgba(14, 165, 233, 0.7));
  box-shadow:
    0 28px 68px rgba(6, 95, 70, 0.24),
    0 12px 28px rgba(2, 6, 23, 0.15),
    inset 0 1px 0 rgba(255, 255, 255, 0.27),
    inset 0 -1px 0 rgba(15, 23, 42, 0.13);
  padding: clamp(1.35rem, 3vw, 2.25rem);
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

.ym-staff-avatar {
  display: grid;
  height: 86px;
  width: 86px;
  flex: 0 0 86px;
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
  font-size: 15px;
  margin: 0 0 0.25rem;
}

.ym-staff-title {
  color: #fff;
  font-size: clamp(2.1rem, 3.4vw, 2.75rem);
  font-weight: 950;
  line-height: 1.05;
  margin: 0;
}

.ym-staff-copy {
  font-size: 16px;
  line-height: 1.7;
  margin: 0.55rem 0 0;
}

.ym-staff-focus {
  min-width: min(100%, 250px);
  border: 1px solid rgba(255, 255, 255, 0.24);
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.17), rgba(255, 255, 255, 0.1)),
    rgba(255, 255, 255, 0.11);
  box-shadow:
    0 18px 42px rgba(6, 78, 59, 0.15),
    inset 0 1px 0 rgba(255, 255, 255, 0.28);
  padding: 1rem;
  backdrop-filter: blur(12px);
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
  color: #38bdf8;
  font-size: 14px;
  font-weight: 950;
}

.ym-staff-panel {
  padding: 1.25rem;
}

.ym-staff-panel h3 {
  color: var(--ym-text);
  font-size: 22px;
  font-weight: 950;
  margin: 0 0 1rem;
}

.ym-task-row {
  display: flex;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: var(--ym-control-bg);
  padding: 1rem;
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease;
}

.ym-task-row:hover {
  border-color: var(--ym-card-border);
  background: var(--ym-row-hover);
  transform: translateY(-1px);
}

.ym-task-row > span {
  height: 13px;
  width: 13px;
  flex: 0 0 13px;
  border-radius: 999px;
  margin-top: 0.32rem;
  box-shadow: 0 0 18px currentColor;
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
</style>
