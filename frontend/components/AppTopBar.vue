<template>
  <header class="ym-topbar sticky top-0 z-[45] px-5 pt-5">
    <div class="ym-topbar-shell">
      <div class="ym-topbar-heading">
        <p class="ym-topbar-kicker">{{ copy.kicker }}</p>
        <h1 class="ym-topbar-title">
          <slot name="title">{{ pageTitle }}</slot>
        </h1>
      </div>

      <div class="ym-topbar-actions">
        <label
          class="ym-search has-tooltip"
          :data-tooltip="copy.searchTooltip"
          :aria-label="copy.searchTooltip"
          :title="copy.searchTooltip"
        >
          <svg class="h-7 w-7 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.2-5.2M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z" />
          </svg>
          <input v-model="searchQuery" type="text" :placeholder="copy.search" />
        </label>

        <div ref="notificationsRoot" class="relative">
          <button
            ref="notificationsButton"
            type="button"
            class="ym-action-button has-tooltip"
            :class="isNotificationsOpen ? 'is-active' : ''"
            :aria-label="copy.notifications"
            :data-tooltip="copy.notifications"
            :title="copy.notifications"
            @click="toggleNotifications"
          >
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18 10a6 6 0 1 0-12 0c0 7-2 7-2 7h16s-2 0-2-7Zm-8 10h4" />
            </svg>
            <span v-if="unreadCount" class="ym-action-dot" />
          </button>

          <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
          >
            <section v-if="isNotificationsOpen" ref="notificationsPanel" class="ym-popover ym-notifications-panel">
              <div class="ym-popover-head">
                <div>
                  <p>{{ copy.notifications }}</p>
                  <span>{{ copy.notificationsHint }}</span>
                </div>
                <strong>{{ filteredNotifications.length }}</strong>
              </div>

              <div class="ym-notification-filters">
                <button
                  v-for="filter in notificationFilters"
                  :key="filter.key"
                  type="button"
                  :class="activeNotificationFilter === filter.key ? 'is-active' : ''"
                  :aria-label="filter.label"
                  :title="filter.label"
                  @click="activeNotificationFilter = filter.key"
                >
                  {{ filter.label }}
                </button>
              </div>

              <div class="ym-notifications-list">
                <article v-for="item in filteredNotifications" :key="item.id" class="ym-notification-item" :class="item.read ? 'is-read' : 'is-unread'">
                  <span class="ym-notification-icon" :style="{ color: item.color }">{{ item.icon }}</span>
                  <span class="min-w-0 flex-1">
                    <span class="ym-notification-title-row">
                      <strong>{{ item.title[currentLocale] }}</strong>
                      <b v-if="item.important">{{ copy.important }}</b>
                    </span>
                    <small>{{ item.description[currentLocale] }}</small>
                    <em>{{ item.time[currentLocale] }} · {{ item.read ? copy.read : copy.unread }}</em>
                  </span>
                </article>
              </div>
              <button type="button" class="ym-popover-more">{{ copy.viewAll }}</button>
            </section>
          </transition>
        </div>

        <div ref="accountRoot" class="relative">
          <button
            ref="accountButton"
            type="button"
            class="ym-action-button has-tooltip"
            :class="isAccountMenuOpen ? 'is-active' : ''"
            :aria-label="copy.accountMenu"
            :data-tooltip="copy.accountMenu"
            :title="copy.accountMenu"
            @click="toggleAccountMenu"
          >
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10.3 4.3 11 2h2l.7 2.3c.5.2 1 .4 1.4.7l2.1-1.1 1.4 1.4-1.1 2.1c.3.4.5.9.7 1.4L20.5 9v2l-2.3.7c-.2.5-.4 1-.7 1.4l1.1 2.1-1.4 1.4-2.1-1.1c-.4.3-.9.5-1.4.7L13 18.5h-2l-.7-2.3c-.5-.2-1-.4-1.4-.7l-2.1 1.1-1.4-1.4 1.1-2.1c-.3-.4-.5-.9-.7-1.4L3.5 11V9l2.3-.7c.2-.5.4-1 .7-1.4L5.4 4.8l1.4-1.4L8.9 4.5c.4-.3.9-.5 1.4-.7Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 13.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" />
            </svg>
          </button>

          <transition
            enter-active-class="transition ease-out duration-150"
            enter-from-class="opacity-0 translate-y-2 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-2 scale-95"
          >
            <section v-if="isAccountMenuOpen" ref="accountPanel" class="ym-popover ym-account-panel">
              <div class="ym-popover-head">
                <div>
                  <p>{{ copy.accountMenu }}</p>
                  <span>{{ copy.accountHint }}</span>
                </div>
              </div>
              <nav class="ym-account-list">
                <NuxtLink v-for="item in accountItems" :key="item.key" :to="item.path" :title="item.label" @click="isAccountMenuOpen = false">
                  <span v-html="item.icon" />
                  {{ item.label }}
                </NuxtLink>
                <button type="button" class="ym-account-logout" @click="logout">
                  <span v-html="logoutIcon" />
                  {{ copy.logout }}
                </button>
              </nav>
            </section>
          </transition>
        </div>

        <button
          type="button"
          class="ym-action-button has-tooltip"
          :aria-label="copy.themeTooltip"
          :data-tooltip="copy.themeTooltip"
          :title="copy.themeTooltip"
          @click="toggleTheme"
        >
          <svg v-if="dashboardTheme === 'dark'" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.5m6.4.1-1.8 1.8M21 12h-2.5m-.1 6.4-1.8-1.8M12 18.5V21m-4.6-4.4-1.8 1.8M5.5 12H3m4.4-6.4L5.6 3.8M15.5 12a3.5 3.5 0 1 1-7 0 3.5 3.5 0 0 1 7 0Z" />
          </svg>
          <svg v-else class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 14.6A8.5 8.5 0 0 1 9.4 3a7.3 7.3 0 1 0 11.6 11.6Z" />
          </svg>
        </button>

        <button
          type="button"
          class="ym-segment-button has-tooltip"
          :aria-label="copy.languageTooltip"
          :data-tooltip="copy.languageTooltip"
          :title="copy.languageTooltip"
          @click="toggleLocale"
        >
          <span>{{ currentLocale === 'ar' ? 'AR' : 'EN' }}</span>
          <strong>{{ currentLocale === 'ar' ? 'English' : 'العربية' }}</strong>
        </button>
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/authStore'

defineProps<{ subtitle?: string }>()

type Locale = 'ar' | 'en'
type NotificationFilter = 'all' | 'important' | 'latest' | 'unread' | 'read'

const route = useRoute()
const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const isNotificationsOpen = ref(false)
const isAccountMenuOpen = ref(false)
const activeNotificationFilter = ref<NotificationFilter>('all')
const searchQuery = ref('')

const notificationsRoot = ref<HTMLElement | null>(null)
const accountRoot = ref<HTMLElement | null>(null)
const notificationsButton = ref<HTMLElement | null>(null)
const accountButton = ref<HTMLElement | null>(null)
const notificationsPanel = ref<HTMLElement | null>(null)
const accountPanel = ref<HTMLElement | null>(null)

const dictionary = {
  ar: {
    kicker: 'مركز قيادة يمن موشن',
    search: 'ابحث في الطلبات، المستخدمين، البلاغات، التذاكر...',
    searchTooltip: 'البحث في المنصة',
    notifications: 'الإشعارات',
    notificationsHint: 'تنبيهات تشغيلية مصنفة',
    viewAll: 'عرض جميع الإشعارات',
    theme: 'تبديل المظهر',
    themeTooltip: 'تغيير المظهر',
    languageTooltip: 'تغيير اللغة',
    accountMenu: 'إعدادات الحساب',
    accountHint: 'روابط إعدادات placeholder',
    important: 'مهم',
    read: 'مقروءة',
    unread: 'غير مقروءة',
    logout: 'تسجيل الخروج',
    filters: { all: 'الكل', important: 'الأهم', latest: 'الأحدث', unread: 'غير مقروءة', read: 'مقروءة' },
    account: {
      profile: 'الملف الشخصي',
      account: 'إعدادات الحساب',
      password: 'تغيير كلمة المرور',
      security: 'الأمان والجلسات',
      notificationPrefs: 'تفضيلات الإشعارات',
      appearancePrefs: 'تفضيلات الواجهة',
      social: 'وسائل التواصل',
      support: 'المساعدة والدعم'
    },
    titles: {
      '/admin': 'لوحة التحكم الإدارية',
      '/admin/users': 'إدارة المستخدمين',
      '/admin/staff': 'إدارة الموظفين',
      '/admin/roles': 'الأدوار والصلاحيات',
      '/admin/works': 'إدارة الأعمال',
      '/admin/orders': 'إدارة الطلبات',
      '/admin/bookings': 'الحجوزات',
      '/admin/contests': 'المسابقات',
      '/admin/reports': 'التقارير',
      '/admin/analytics': 'التحليلات',
      '/admin/notifications': 'الإشعارات',
      '/admin/support': 'الدعم',
      '/staff': 'لوحة فريق العمل',
      '/staff/content': 'مراجعة المحتوى',
      '/staff/reports': 'تقارير الفريق'
    }
  },
  en: {
    kicker: 'Yemen Motion Command Center',
    search: 'Search orders, users, reports, tickets...',
    searchTooltip: 'Search the platform',
    notifications: 'Notifications',
    notificationsHint: 'Filtered operational alerts',
    viewAll: 'View all notifications',
    theme: 'Toggle theme',
    themeTooltip: 'Change theme',
    languageTooltip: 'Change language',
    accountMenu: 'Account Settings',
    accountHint: 'Placeholder settings links',
    important: 'Important',
    read: 'Read',
    unread: 'Unread',
    logout: 'Logout',
    filters: { all: 'All', important: 'Important', latest: 'Latest', unread: 'Unread', read: 'Read' },
    account: {
      profile: 'Profile',
      account: 'Account Settings',
      password: 'Change Password',
      security: 'Security & Sessions',
      notificationPrefs: 'Notification Preferences',
      appearancePrefs: 'Appearance Preferences',
      social: 'Social Links',
      support: 'Help & Support'
    },
    titles: {
      '/admin': 'Admin Dashboard',
      '/admin/users': 'User Management',
      '/admin/staff': 'Staff Management',
      '/admin/roles': 'Roles & Permissions',
      '/admin/works': 'Works Management',
      '/admin/orders': 'Orders Management',
      '/admin/bookings': 'Bookings',
      '/admin/contests': 'Contests',
      '/admin/reports': 'Reports',
      '/admin/analytics': 'Analytics',
      '/admin/notifications': 'Notifications',
      '/admin/support': 'Support',
      '/staff': 'Staff Dashboard',
      '/staff/content': 'Content Review',
      '/staff/reports': 'Staff Reports'
    }
  }
}

const notifications = [
  {
    id: 'order-review',
    icon: '●',
    color: '#818cf8',
    read: false,
    important: true,
    order: 1,
    title: { ar: 'طلب جديد يحتاج مراجعة', en: 'New order needs review' },
    description: { ar: 'هوية بصرية لعميل جديد دخلت قائمة الاعتماد.', en: 'Brand identity for a new client entered approval.' },
    time: { ar: 'منذ 5 دقائق', en: '5 minutes ago' }
  },
  {
    id: 'flagged-content',
    icon: '!',
    color: '#f59e0b',
    read: false,
    important: true,
    order: 2,
    title: { ar: 'بلاغ محتوى جديد', en: 'New content flag' },
    description: { ar: 'عنصر ينتظر قرار فريق المراجعة.', en: 'Item waiting for review-team decision.' },
    time: { ar: 'منذ 15 دقيقة', en: '15 minutes ago' }
  },
  {
    id: 'wallet-approval',
    icon: '$',
    color: '#10b981',
    read: true,
    important: false,
    order: 3,
    title: { ar: 'عملية محفظة تنتظر الاعتماد', en: 'Wallet action awaiting approval' },
    description: { ar: 'طلب سحب أرباح تجريبي يحتاج مراجعة مالية.', en: 'Sample payout request needs finance review.' },
    time: { ar: 'منذ ساعة', en: '1 hour ago' }
  },
  {
    id: 'support-thread',
    icon: '?',
    color: '#0ea5e9',
    read: true,
    important: false,
    order: 4,
    title: { ar: 'تذكرة دعم تم تحديثها', en: 'Support ticket updated' },
    description: { ar: 'عميل أضاف رد جديد على طلب سابق.', en: 'Client added a new reply to an existing ticket.' },
    time: { ar: 'منذ ساعتين', en: '2 hours ago' }
  }
]

const copy = computed(() => dictionary[currentLocale.value])
const pageTitle = computed(() => copy.value.titles[route.path as keyof typeof copy.value.titles] || copy.value.titles['/admin'])
const unreadCount = computed(() => notifications.filter(item => !item.read).length)

const notificationFilters = computed(() => (Object.keys(copy.value.filters) as NotificationFilter[]).map(key => ({
  key,
  label: copy.value.filters[key]
})))

const filteredNotifications = computed(() => {
  const list = [...notifications]
  if (activeNotificationFilter.value === 'important') return list.filter(item => item.important)
  if (activeNotificationFilter.value === 'unread') return list.filter(item => !item.read)
  if (activeNotificationFilter.value === 'read') return list.filter(item => item.read)
  if (activeNotificationFilter.value === 'latest') return list.sort((a, b) => a.order - b.order).slice(0, 3)
  return list
})

const accountBase = computed(() => {
  if (route.path.startsWith('/admin')) return '/admin'
  if (route.path.startsWith('/staff')) return '/staff'
  return ''
})

const icon = (d: string) => `<svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">${d}</svg>`
const accountIcons = {
  profile: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8c0-3-3.1-5.5-7-5.5S5 17 5 20" />'),
  account: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h10" />'),
  password: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0 1 10 0v3M6 11h12v9H6v-9Zm6 4h.01" />'),
  security: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 20 6v5.5c0 4.2-3 7.8-8 9.5-5-1.7-8-5.3-8-9.5V6l8-3Z" />'),
  bell: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M18 10a6 6 0 1 0-12 0c0 7-2 7-2 7h16s-2 0-2-7Zm-8 10h4" />'),
  appearance: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m0-18a9 9 0 0 1 0 18 9 9 0 0 1 0-18Z" />'),
  social: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M8 12a4 4 0 0 1 4-4h2m2 0h1a4 4 0 0 1 0 8h-2m-6 0H7a4 4 0 0 1 0-8h1" />'),
  support: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M5 12a7 7 0 0 1 14 0v5a2 2 0 0 1-2 2h-2v-6h4M5 12v5a2 2 0 0 0 2 2h2v-6H5Zm6 8h2" />')
}
const logoutIcon = icon('<path stroke-linecap="round" stroke-linejoin="round" d="M15 17 20 12l-5-5M20 12H8m4 8H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6" />')

const accountItems = computed(() => {
  const c = copy.value.account
  const base = accountBase.value || ''
  const safeBase = base || '/account'
  return [
    { key: 'profile', label: c.profile, path: `${safeBase}/profile`, icon: accountIcons.profile },
    { key: 'account', label: c.account, path: `${safeBase}/account`, icon: accountIcons.account },
    { key: 'password', label: c.password, path: `${safeBase}/account`, icon: accountIcons.password },
    { key: 'security', label: c.security, path: `${safeBase}/security`, icon: accountIcons.security },
    { key: 'notificationPrefs', label: c.notificationPrefs, path: `${safeBase}/notifications/preferences`, icon: accountIcons.bell },
    { key: 'appearancePrefs', label: c.appearancePrefs, path: `${safeBase}/account`, icon: accountIcons.appearance },
    { key: 'social', label: c.social, path: `${safeBase}/account`, icon: accountIcons.social },
    { key: 'support', label: c.support, path: `${safeBase}/support`, icon: accountIcons.support }
  ]
})

function toggleNotifications() {
  isNotificationsOpen.value = !isNotificationsOpen.value
  if (isNotificationsOpen.value) isAccountMenuOpen.value = false
}

function toggleAccountMenu() {
  isAccountMenuOpen.value = !isAccountMenuOpen.value
  if (isAccountMenuOpen.value) isNotificationsOpen.value = false
}

function toggleLocale() {
  currentLocale.value = currentLocale.value === 'ar' ? 'en' : 'ar'
}

function toggleTheme() {
  dashboardTheme.value = dashboardTheme.value === 'dark' ? 'light' : 'dark'
}

function logout() {
  isAccountMenuOpen.value = false
  auth.logout()
}

function closeMenusForTarget(target: EventTarget | null) {
  if (!(target instanceof Node)) return
  const insideNotifications = notificationsRoot.value?.contains(target)
  const insideAccount = accountRoot.value?.contains(target)
  if (!insideNotifications) isNotificationsOpen.value = false
  if (!insideAccount) isAccountMenuOpen.value = false
}

function closeMenusForEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    isNotificationsOpen.value = false
    isAccountMenuOpen.value = false
  }
}

const handlePointerDown = (event: PointerEvent) => closeMenusForTarget(event.target)
const handleKeyDown = (event: KeyboardEvent) => closeMenusForEscape(event)

onMounted(() => {
  document.addEventListener('pointerdown', handlePointerDown)
  document.addEventListener('keydown', handleKeyDown)
})

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', handlePointerDown)
  document.removeEventListener('keydown', handleKeyDown)
})
</script>

<style scoped>
.ym-topbar {
  overflow: visible;
  isolation: isolate;
}

.ym-topbar-shell {
  position: relative;
  display: grid;
  grid-template-columns: minmax(12rem, 1fr) auto;
  min-height: 92px;
  align-items: center;
  gap: 1rem;
  overflow: visible;
  border: 1px solid var(--ym-shell-border);
  border-radius: 30px;
  background: var(--ym-shell-surface);
  box-shadow: var(--ym-shell-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.18);
  padding: 1rem 1.1rem;
  backdrop-filter: blur(30px) saturate(155%);
}

.ym-topbar-shell::before {
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background:
    linear-gradient(120deg, rgba(129, 140, 248, 0.24), transparent 34%, rgba(190, 0, 1, 0.12)),
    radial-gradient(circle at 85% 0%, rgba(255, 255, 255, 0.14), transparent 18rem);
  content: "";
  pointer-events: none;
}

.ym-topbar-shell::after {
  position: absolute;
  inset-inline: 12%;
  bottom: -1px;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(129, 140, 248, 0.78), rgba(190, 0, 1, 0.5), transparent);
  content: "";
  pointer-events: none;
}

.ym-topbar-heading,
.ym-topbar-actions {
  position: relative;
  z-index: 1;
}

.ym-topbar-heading {
  min-width: 0;
}

.ym-topbar-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 950;
  margin: 0 0 0.18rem;
}

.ym-topbar-title {
  color: var(--ym-text);
  font-size: clamp(1.75rem, 2.2vw, 2.08rem);
  font-weight: 950;
  line-height: 1.08;
  margin: 0;
}

.ym-topbar-actions {
  display: flex;
  align-items: center;
  gap: 0.72rem;
}

.ym-search {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  width: clamp(300px, 34vw, 500px);
  border: 1px solid var(--ym-control-border);
  border-radius: 20px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  padding: 0 1.05rem;
  min-height: 56px;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 14px 32px rgba(2, 6, 23, 0.08);
  transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.ym-search:focus-within {
  border-color: rgba(129, 140, 248, 0.78);
  box-shadow: 0 0 0 4px rgba(129, 140, 248, 0.16), 0 18px 42px rgba(79, 70, 229, 0.18);
  transform: translateY(-1px);
}

.ym-search input {
  width: 100%;
  min-width: 0;
  border: 0;
  background: transparent;
  color: var(--ym-text);
  font-size: 15px;
  font-weight: 800;
  letter-spacing: normal;
  line-height: 1.4;
  outline: none;
  overflow: hidden;
  text-rendering: optimizeLegibility;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-search input::placeholder {
  color: var(--ym-muted);
}

.ym-action-button,
.ym-segment-button {
  min-height: 54px;
  border: 1px solid var(--ym-control-border);
  border-radius: 18px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  box-shadow: 0 14px 32px rgba(15, 23, 42, 0.12), inset 0 1px 0 rgba(255, 255, 255, 0.12);
  transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
}

.ym-action-button {
  position: relative;
  display: grid;
  width: 56px;
  place-items: center;
}

.ym-segment-button {
  display: grid;
  align-content: center;
  min-width: 94px;
  padding: 0 0.9rem;
  text-align: center;
}

.ym-segment-button span {
  font-size: 15px;
  font-weight: 950;
  line-height: 1;
}

.ym-segment-button strong {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 850;
  line-height: 1.35;
}

.ym-action-button:hover,
.ym-action-button.is-active,
.ym-segment-button:hover {
  transform: translateY(-2px);
  border-color: rgba(129, 140, 248, 0.62);
  box-shadow: 0 20px 42px rgba(79, 70, 229, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.17);
}

.has-tooltip {
  position: relative;
}

.has-tooltip::after {
  position: absolute;
  inset-inline-start: 50%;
  top: calc(100% + 10px);
  z-index: 110;
  width: max-content;
  max-width: 220px;
  border: 1px solid var(--ym-shell-border);
  border-radius: 10px;
  background: var(--ym-tooltip-bg);
  box-shadow: 0 12px 30px rgba(2, 6, 23, 0.26);
  color: var(--ym-text);
  content: attr(data-tooltip);
  font-size: 13px;
  font-weight: 850;
  line-height: 1.4;
  opacity: 0;
  padding: 0.45rem 0.65rem;
  pointer-events: none;
  text-align: center;
  transform: translate(-50%, -5px);
  transition: opacity 140ms ease 240ms, transform 140ms ease 240ms;
  white-space: nowrap;
}

.has-tooltip:hover::after,
.has-tooltip:focus-visible::after,
.has-tooltip:focus-within::after {
  opacity: 1;
  transform: translate(-50%, 0);
}

.ym-search.has-tooltip::after {
  inset-inline-start: auto;
  inset-inline-end: 1rem;
  transform: translateY(-5px);
}

.ym-search.has-tooltip:hover::after,
.ym-search.has-tooltip:focus-within::after {
  transform: translateY(0);
}

.ym-action-dot {
  position: absolute;
  inset-block-start: 12px;
  inset-inline-start: 13px;
  height: 10px;
  width: 10px;
  border-radius: 999px;
  background: #f43f5e;
  box-shadow: 0 0 0 4px rgba(244, 63, 94, 0.15), 0 0 18px rgba(244, 63, 94, 0.7);
}

.ym-popover {
  position: absolute;
  inset-block-start: calc(100% + 12px);
  inset-inline-end: 0;
  z-index: 95;
  overflow: hidden;
  border: 1px solid var(--ym-shell-border);
  border-radius: 22px;
  background: var(--ym-dropdown-bg);
  box-shadow: 0 32px 90px rgba(2, 6, 23, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.13);
  color: var(--ym-text);
  backdrop-filter: blur(28px) saturate(155%);
}

.ym-notifications-panel {
  width: min(92vw, 420px);
  max-height: min(520px, calc(100vh - 140px));
}

.ym-account-panel {
  width: min(92vw, 340px);
  max-height: min(500px, calc(100vh - 140px));
  overflow-y: auto;
}

.ym-popover-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid var(--ym-soft-border);
  padding: 0.92rem 1rem;
}

.ym-popover-head p,
.ym-notification-item strong {
  margin: 0;
  color: var(--ym-text);
  font-size: 16px;
  font-weight: 950;
}

.ym-popover-head span,
.ym-notification-item small,
.ym-notification-item em {
  display: block;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-style: normal;
  font-weight: 820;
  line-height: 1.5;
  margin-top: 0.2rem;
}

.ym-popover-head strong {
  display: grid;
  height: 38px;
  width: 38px;
  place-items: center;
  border-radius: 14px;
  background: rgba(99, 102, 241, 0.16);
  color: #818cf8;
  font-size: 15px;
}

.ym-notification-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  border-bottom: 1px solid var(--ym-soft-border);
  padding: 0.75rem 0.9rem;
}

.ym-notification-filters button {
  border: 1px solid var(--ym-control-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  padding: 0.45rem 0.78rem;
  transition: background 160ms ease, color 160ms ease, border-color 160ms ease;
}

.ym-notification-filters button.is-active,
.ym-notification-filters button:hover {
  border-color: rgba(129, 140, 248, 0.54);
  background: rgba(99, 102, 241, 0.18);
  color: var(--ym-text);
}

.ym-notifications-list {
  max-height: 306px;
  overflow-y: auto;
  scrollbar-color: rgba(129, 140, 248, 0.38) transparent;
  scrollbar-width: thin;
}

.ym-notification-item {
  display: flex;
  gap: 0.9rem;
  border-bottom: 1px solid var(--ym-soft-border);
  padding: 0.9rem;
  transition: background 160ms ease;
}

.ym-notification-item:hover {
  background: var(--ym-row-hover);
}

.ym-notification-item.is-unread {
  background: color-mix(in srgb, rgba(99, 102, 241, 0.12) 60%, transparent);
}

.ym-notification-icon {
  display: grid;
  height: 46px;
  width: 46px;
  flex: 0 0 46px;
  place-items: center;
  border-radius: 18px;
  background: var(--ym-control-bg);
  font-size: 21px;
  font-weight: 950;
}

.ym-notification-title-row {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  justify-content: space-between;
}

.ym-notification-title-row b {
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.18);
  color: #f59e0b;
  font-size: 13px;
  font-weight: 950;
  padding: 0.15rem 0.45rem;
}

.ym-popover-more {
  width: 100%;
  border-top: 1px solid var(--ym-soft-border);
  padding: 0.9rem;
  color: #818cf8;
  font-size: 14.5px;
  font-weight: 950;
  transition: background 160ms ease;
}

.ym-popover-more:hover {
  background: var(--ym-row-hover);
}

.ym-account-list {
  display: grid;
  gap: 0.28rem;
  padding: 0.68rem;
}

.ym-account-panel {
  scrollbar-color: rgba(129, 140, 248, 0.38) transparent;
  scrollbar-width: thin;
}

.ym-account-panel::-webkit-scrollbar,
.ym-notifications-list::-webkit-scrollbar {
  width: 5px;
}

.ym-account-panel::-webkit-scrollbar-track,
.ym-notifications-list::-webkit-scrollbar-track {
  background: transparent;
}

.ym-account-panel::-webkit-scrollbar-thumb,
.ym-notifications-list::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: rgba(129, 140, 248, 0.34);
}

.ym-account-list a,
.ym-account-list button {
  display: flex;
  align-items: center;
  gap: 0.7rem;
  border: 1px solid transparent;
  border-radius: 15px;
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 900;
  padding: 0.68rem 0.78rem;
  text-align: start;
  transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
}

.ym-account-list a:hover,
.ym-account-list button:hover {
  border-color: var(--ym-control-border);
  background: var(--ym-row-hover);
  transform: translateY(-1px);
}

.ym-account-logout {
  color: #f43f5e !important;
  border-top: 1px solid var(--ym-soft-border) !important;
  border-radius: 0 0 15px 15px !important;
  margin-top: 0.45rem;
  padding-top: 0.85rem !important;
}

@media (max-width: 1120px) {
  .ym-topbar-shell {
    grid-template-columns: 1fr;
  }

  .ym-topbar-actions {
    flex-wrap: wrap;
  }

  .ym-search {
    width: min(100%, 560px);
  }
}

@media (max-width: 720px) {
  .ym-search {
    width: 100%;
  }
}
</style>
