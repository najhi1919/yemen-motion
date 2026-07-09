<template>
  <aside
    :class="[
      'ym-sidebar flex flex-col overflow-hidden transition-all duration-300 ease-out',
      props.theme === 'light' ? 'ym-sidebar--light' : 'ym-sidebar--dark',
      props.collapsed ? 'ym-sidebar--collapsed w-[96px]' : 'ym-sidebar--expanded w-72',
      currentLocale === 'ar' ? 'ym-sidebar--right' : 'ym-sidebar--left'
    ]"
  >
    <div class="relative p-4">
      <button
        type="button"
        class="ym-sidebar-brand group"
        :class="props.collapsed ? 'is-collapsed' : ''"
        :aria-label="copy.toggle"
        @click="$emit('toggle')"
      >
        <span class="ym-sidebar-logo" :class="props.collapsed ? 'is-collapsed' : ''">
          <img src="/logo.svg" alt="Yemen Motion" class="ym-sidebar-logo-img" />
        </span>
        <span v-if="!props.collapsed" class="ym-sidebar-name">
          <img src="/name.svg" alt="Yemen Motion" class="ym-sidebar-name-img" />
        </span>
      </button>
    </div>

    <div v-if="!props.collapsed" class="mx-4 mb-3">
      <div class="ym-role-badge">
        {{ roleLabel }}
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto overflow-x-hidden px-4 pb-4 custom-scrollbar">
      <div class="space-y-1.5">
        <template v-for="item in visibleItems" :key="item.path || item.separator">
          <div v-if="item.separator" class="px-3 pt-5 pb-1">
            <p v-if="!props.collapsed" class="ym-sidebar-section-label">
              {{ item.separator }}
            </p>
            <div v-else class="ym-sidebar-section-line" />
          </div>

          <NuxtLink
            v-else
            :to="item.path"
            :class="[
              'ym-sidebar-link group',
              props.collapsed ? 'justify-center px-2' : 'px-4',
              isActive(item.path) ? 'is-active' : ''
            ]"
            :aria-label="item.label"
            @mouseenter="showSidebarTooltip($event, item.label)"
            @focus="showSidebarTooltip($event, item.label)"
            @mouseleave="hideSidebarTooltip"
            @blur="hideSidebarTooltip"
          >
            <span class="ym-sidebar-link__glow" />
            <span class="ym-sidebar-icon" v-html="item.icon" />
            <span v-if="!props.collapsed" class="ym-sidebar-label">{{ item.label }}</span>
            <span v-if="!props.collapsed && item.badge" class="ym-sidebar-badge">{{ item.badge }}</span>
          </NuxtLink>
        </template>
      </div>
    </nav>

    <div class="ym-sidebar-bottom p-4">
      <div v-if="!props.collapsed" class="ym-sidebar-footer">Yemen Motion</div>
      <div v-else class="ym-sidebar-footer-mark" aria-hidden="true">YM</div>
    </div>
  </aside>

  <Teleport to="body">
    <div
      v-if="props.collapsed && sidebarTooltip.visible"
      class="ym-floating-tooltip ym-sidebar-floating-tooltip"
      :class="`is-${sidebarTooltip.placement}`"
      :style="{ top: `${sidebarTooltip.top}px`, left: `${sidebarTooltip.left}px` }"
      role="tooltip"
    >
      {{ sidebarTooltip.label }}
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useAuthStore } from '~/stores/authStore'

const props = withDefaults(defineProps<{
  collapsed?: boolean
  theme?: 'dark' | 'light'
}>(), {
  collapsed: false,
  theme: 'dark'
})
defineEmits<{ toggle: [] }>()

const auth = useAuthStore()
const route = useRoute()
const currentLocale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')

type FloatingTooltipPlacement = 'left' | 'right'

const sidebarTooltip = reactive({
  visible: false,
  label: '',
  top: 0,
  left: 0,
  placement: 'left' as FloatingTooltipPlacement
})

const t = {
  ar: {
    toggle: 'طي أو فتح القائمة',
    adminRole: 'مسؤول النظام',
    staffRole: 'عضو الفريق',
    menu: 'القائمة',
    admin: 'الإدارة',
    content: 'المحتوى',
    insights: 'الرؤى',
    ops: 'التشغيل',
    home: 'الرئيسية',
    users: 'المستخدمون',
    staff: 'الموظفون',
    roles: 'الأدوار والصلاحيات',
    permissions: 'الصلاحيات',
    works: 'الأعمال',
    orders: 'الطلبات',
    bookings: 'الحجوزات',
    contests: 'المسابقات',
    wallet: 'المحفظة',
    reports: 'التقارير',
    analytics: 'التحليلات',
    notifications: 'الإشعارات',
    flags: 'البلاغات',
    support: 'الدعم',
    review: 'مراجعة المحتوى',
    tasks: 'المهام'
  },
  en: {
    toggle: 'Collapse or expand sidebar',
    adminRole: 'System Admin',
    staffRole: 'Team Member',
    menu: 'Menu',
    admin: 'Administration',
    content: 'Content',
    insights: 'Insights',
    ops: 'Operations',
    home: 'Home',
    users: 'Users',
    staff: 'Staff',
    roles: 'Roles & Permissions',
    permissions: 'Permissions',
    works: 'Works',
    orders: 'Orders',
    bookings: 'Bookings',
    contests: 'Contests',
    wallet: 'Wallet',
    reports: 'Reports',
    analytics: 'Analytics',
    notifications: 'Notifications',
    flags: 'Reports/Flags',
    support: 'Support',
    review: 'Content Review',
    tasks: 'Tasks'
  }
}

const copy = computed(() => t[currentLocale.value])
const isSuperAdmin = computed(() => auth.role === 'super-admin')
const isAdmin = computed(() => ['super-admin', 'admin'].includes(auth.role || ''))
const isStaff = computed(() => auth.role === 'staff')
const hasPermission = (permission: string) => isSuperAdmin.value || auth.permissions.includes(permission)

const roleLabel = computed(() => {
  if (isAdmin.value) return copy.value.adminRole
  if (isStaff.value) return copy.value.staffRole
  return auth.role || ''
})

const icon = (path: string) => `<svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">${path}</svg>`
const icons = {
  home: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 12 4l9 7.5M5.5 10.5V20h13v-9.5M9.5 20v-5h5v5" />'),
  users: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M16 19.5c0-2.1-2.7-3.8-6-3.8s-6 1.7-6 3.8M10 12.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8.5 5.5c1.5-.5 2.5-1.5 2.5-2.8 0-1.8-1.8-3.2-4.2-3.6m-.8-7a3 3 0 0 1 0 5.8" />'),
  briefcase: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7m-9.5 4.5h13M5 7h14a1.5 1.5 0 0 1 1.5 1.5v9A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-9A1.5 1.5 0 0 1 5 7Z" />'),
  shield: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 20 6v5.5c0 4.2-3 7.8-8 9.5-5-1.7-8-5.3-8-9.5V6l8-3Zm-3 9 2 2 4-5" />'),
  folder: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M3.5 7.5h6l2 2h9v8A2.5 2.5 0 0 1 18 20H6a2.5 2.5 0 0 1-2.5-2.5v-10Z" />'),
  cart: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 5h2l1.5 10h9.5l2-7H7M9 20h.01M17 20h.01" />'),
  calendar: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M7 4v3m10-3v3M4.5 9.5h15M6 6h12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z" />'),
  trophy: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8v4.5a4 4 0 0 1-8 0V4Zm0 2H4.5C4.5 9 6 11 8.4 11.5M16 6h3.5c0 3-1.5 5-3.9 5.5M12 13v4m-4 3h8" />'),
  wallet: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 7.5A2.5 2.5 0 0 1 6.5 5H18v4H6.5A2.5 2.5 0 0 1 4 6.5v11A2.5 2.5 0 0 0 6.5 20H20v-8H6.5A2.5 2.5 0 0 1 4 9.5Zm13 8h.01" />'),
  chart: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m0 14h16M8 16v-5m4 5V8m4 8v-7" />'),
  bell: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M18 10a6 6 0 1 0-12 0c0 7-2 7-2 7h16s-2 0-2-7Zm-8 10h4" />'),
  flag: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M5 21V4m0 0h11l-1.5 4L16 12H5" />'),
  support: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M5 12a7 7 0 0 1 14 0v5a2 2 0 0 1-2 2h-2v-6h4M5 12v5a2 2 0 0 0 2 2h2v-6H5Zm6 8h2" />'),
  eye: icon('<path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Zm9.5 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />')
}

const allItems = computed(() => {
  const c = copy.value
  const items: Array<{ path?: string; label?: string; icon?: string; badge?: string; separator?: string }> = []
  const addSection = (
    separator: string,
    sectionItems: Array<{ path: string; label: string; icon: string; badge?: string }>
  ) => {
    if (sectionItems.length === 0) return

    items.push({ separator }, ...sectionItems)
  }

  if (isAdmin.value) {
    addSection(c.menu, [
      ...(hasPermission('dashboard.overview.view') ? [{ path: '/admin', label: c.home, icon: icons.home }] : [])
    ])

    addSection(c.admin, [
      ...(hasPermission('admin.users.view') ? [{ path: '/admin/users', label: c.users, icon: icons.users }] : []),
      ...(isSuperAdmin.value ? [{ path: '/admin/staff', label: c.staff, icon: icons.briefcase }] : []),
      ...(hasPermission('admin.roles.view') ? [{ path: '/admin/roles', label: c.roles, icon: icons.shield }] : []),
      ...(hasPermission('admin.permissions.view') ? [{ path: '/admin/permissions', label: c.permissions, icon: icons.shield }] : [])
    ])

    addSection(c.content, isSuperAdmin.value ? [
      { path: '/admin/works', label: c.works, icon: icons.folder },
      { path: '/admin/orders', label: c.orders, icon: icons.cart, badge: '3' },
      { path: '/admin/bookings', label: c.bookings, icon: icons.calendar },
      { path: '/admin/contests', label: c.contests, icon: icons.trophy }
    ] : [])

    addSection(c.insights, isSuperAdmin.value ? [
      { path: '/admin/wallet', label: c.wallet, icon: icons.wallet },
      { path: '/admin/reports', label: c.reports, icon: icons.chart },
      { path: '/admin/analytics', label: c.analytics, icon: icons.chart },
      { path: '/admin/notifications', label: c.notifications, icon: icons.bell },
      { path: '/admin/flags', label: c.flags, icon: icons.flag },
      { path: '/admin/support', label: c.support, icon: icons.support }
    ] : [])
  } else if (isStaff.value) {
    addSection(c.menu, [
      ...(hasPermission('dashboard.overview.view') ? [{ path: '/staff', label: c.home, icon: icons.home }] : [])
    ])
  }

  return items
})

const visibleItems = computed(() => allItems.value)

function isActive(path?: string): boolean {
  if (!path) return false
  if (path === '/admin' || path === '/staff') return route.path === path
  return route.path.startsWith(path)
}

function showSidebarTooltip(event: MouseEvent | FocusEvent, label: string): void {
  if (!props.collapsed) return

  const target = event.currentTarget as HTMLElement | null
  if (!target) return

  const rect = target.getBoundingClientRect()
  const isRtl = currentLocale.value === 'ar'

  sidebarTooltip.visible = true
  sidebarTooltip.label = label
  sidebarTooltip.top = rect.top + rect.height / 2

  if (isRtl) {
    sidebarTooltip.left = rect.left - 12
    sidebarTooltip.placement = 'left'
  } else {
    sidebarTooltip.left = rect.right + 12
    sidebarTooltip.placement = 'right'
  }
}

function hideSidebarTooltip(): void {
  sidebarTooltip.visible = false
}
</script>

<style scoped>
.ym-sidebar {
  position: fixed;
  top: 0;
  bottom: 0;
  z-index: 50;
  height: 100dvh;
  color: rgba(241, 245, 249, 0.92);
  will-change: width;
}

.ym-sidebar--collapsed {
  z-index: 9999;
}

.ym-sidebar--right {
  right: 0;
}

.ym-sidebar--left {
  left: 0;
}

.ym-sidebar--dark {
  background:
    radial-gradient(circle at 50% -8%, rgba(99, 102, 241, 0.34), transparent 24rem),
    linear-gradient(180deg, rgba(8, 13, 29, 0.98), rgba(15, 23, 42, 0.96) 54%, rgba(10, 15, 28, 0.98));
  border-inline-start: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 0 62px rgba(2, 6, 23, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.09);
  backdrop-filter: blur(28px) saturate(140%);
}

.ym-sidebar--light {
  background:
    radial-gradient(circle at 50% -6%, rgba(168, 85, 247, 0.2), transparent 23rem),
    linear-gradient(180deg, rgba(253, 250, 255, 0.98), rgba(244, 235, 255, 0.97) 52%, rgba(235, 225, 252, 0.98));
  border-inline-start: 1px solid rgba(126, 34, 206, 0.2);
  color: #28173a;
  box-shadow: 0 0 54px rgba(126, 34, 206, 0.17), inset 0 1px 0 rgba(255, 255, 255, 0.94);
  backdrop-filter: blur(28px) saturate(135%);
}

.ym-sidebar-brand {
  position: relative;
  display: flex;
  min-height: 74px;
  width: 100%;
  align-items: center;
  gap: 0.85rem;
  overflow: visible;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 22px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.13), rgba(255, 255, 255, 0.04));
  padding: 0.75rem;
  box-shadow: 0 18px 38px rgba(0, 0, 0, 0.24), inset 0 1px 0 rgba(255, 255, 255, 0.16);
  transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.ym-sidebar-brand.is-collapsed {
  display: flex !important;
  justify-content: center !important;
  align-items: center !important;
  width: 64px !important;
  height: 64px !important;
  min-height: 64px !important;
  margin: 1.5rem auto !important;

  /* مربع منحن الزوايا (Squircle) مطابق للصورة تماماً */
  border-radius: 22px !important;
  position: relative !important;
  overflow: hidden !important;
  padding: 0 !important;

  /* تدرج خطي زجاجي بلوري من الرمادي (يمين) إلى الأبيض (يسار) مطابق للوحة المفتوحة */
  background: linear-gradient(to left, rgba(148, 163, 184, 0.22), rgba(255, 255, 255, 0.14)) !important;
  border: 1px solid rgba(255, 255, 255, 0.28) !important;
  backdrop-filter: blur(24px) !important;

  /* ظلال ناعمة جداً وخفيفة لإبراز الحواف الزجاجية فقط وبدون أي نيون داكن */
  box-shadow:
    0 10px 25px -5px rgba(0, 0, 0, 0.05),
    inset 0 1px 1px rgba(255, 255, 255, 0.3) !important;

  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

/* إلغاء وإزالة تأثير الـ Specular Sheen المضاف سابقاً بالكامل */
.ym-sidebar-brand.is-collapsed::before {
  display: none !important;
  content: none !important;
}

.ym-sidebar--light .ym-sidebar-brand:not(.is-collapsed) {
  border-color: rgba(99, 102, 241, 0.2);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.92), rgba(237, 233, 254, 0.68));
  box-shadow: 0 18px 38px rgba(99, 102, 241, 0.13), inset 0 1px 0 rgba(255, 255, 255, 0.84);
}

.ym-sidebar-brand:not(.is-collapsed):hover {
  transform: translateY(-1px);
  border-color: rgba(129, 140, 248, 0.55);
  box-shadow: 0 22px 45px rgba(79, 70, 229, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.ym-sidebar-logo {
  display: grid;
  aspect-ratio: 1 / 1;
  height: 58px;
  width: 58px;
  flex: 0 0 58px;
  place-items: center;
  border-radius: 20px;
  background: radial-gradient(circle at 35% 20%, rgba(255, 255, 255, 0.36), rgba(99, 102, 241, 0.18) 45%, rgba(15, 23, 42, 0.22));
  overflow: visible;
  padding: 0.38rem;
  filter: drop-shadow(0 0 18px rgba(239, 68, 68, 0.34));
}

.ym-sidebar-logo.is-collapsed {
  width: 44px !important;
  height: 44px !important;
  object-fit: contain !important;
  background: transparent !important;
  padding: 0 !important;
  margin: 0 auto !important;
  z-index: 1 !important;

  filter: none !important;

  /* معالجة التمركز: الرفع للأعلى 3px والتحريك لليمين 2px في بيئة RTL */
  position: relative !important;
  top: -3px !important;
  right: -2px !important;

  transition: transform 0.3s ease;
}

/* الحفاظ على الحركة التفاعلية الرشيقة مع دمج قيم الإزاحة المرجعية */
.ym-sidebar-brand.is-collapsed:hover .ym-sidebar-logo.is-collapsed {
  transform: scale(1.04) rotate(2deg);
}

.ym-sidebar--light .ym-sidebar-logo:not(.is-collapsed) {
  background: radial-gradient(circle at 35% 20%, rgba(255, 255, 255, 0.92), rgba(221, 214, 254, 0.76) 46%, rgba(129, 140, 248, 0.14));
  filter: drop-shadow(0 0 18px rgba(124, 58, 237, 0.22));
}

.ym-sidebar-logo-img,
.ym-sidebar-name-img {
  display: block;
  max-height: 100%;
  max-width: 100%;
  object-fit: contain;
}

.ym-sidebar-logo-img {
  height: 100%;
  width: 100%;
}

.ym-sidebar-name {
  display: block;
  height: 38px;
  min-width: 0;
  width: 166px;
  filter: drop-shadow(0 0 12px rgba(255, 255, 255, 0.13));
}

.ym-sidebar-name-img {
  height: 100%;
  width: 100%;
}

.ym-role-badge,
.ym-sidebar-footer {
  border: 1px solid rgba(129, 140, 248, 0.24);
  border-radius: 16px;
  background: rgba(99, 102, 241, 0.12);
  color: rgba(255, 255, 255, 0.82);
  font-size: 14px;
  font-weight: 900;
  padding: 0.65rem 0.9rem;
  text-align: center;
}

.ym-sidebar--light .ym-role-badge,
.ym-sidebar--light .ym-sidebar-footer {
  border-color: rgba(99, 102, 241, 0.2);
  background: rgba(99, 102, 241, 0.1);
  color: rgba(30, 41, 59, 0.86);
}

.ym-sidebar-link {
  position: relative;
  display: flex;
  min-height: 58px;
  align-items: center;
  gap: 0.9rem;
  overflow: hidden;
  border: 1px solid transparent;
  border-radius: 18px;
  color: rgba(241, 245, 249, 0.88);
  font-weight: 850;
  transition: transform 180ms ease, background 180ms ease, color 180ms ease, border-color 180ms ease;
}

.ym-sidebar--light .ym-sidebar-link {
  color: rgba(40, 23, 58, 0.88);
}

.ym-sidebar-link:hover,
.ym-sidebar-link.is-active {
  transform: translateX(-2px);
  border-color: rgba(129, 140, 248, 0.28);
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.24), rgba(190, 0, 1, 0.12));
  color: #fff;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 14px 30px rgba(2, 6, 23, 0.18);
}

.ym-sidebar--light .ym-sidebar-link:hover {
  border-color: rgba(126, 34, 206, 0.24);
  background: linear-gradient(135deg, rgba(124, 58, 237, 0.14), rgba(192, 38, 211, 0.09), rgba(255, 255, 255, 0.76));
  color: #2e1644;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.84), 0 12px 26px rgba(126, 34, 206, 0.12);
}

.ym-sidebar--light .ym-sidebar-link.is-active {
  border-color: rgba(124, 58, 237, 0.46);
  background: linear-gradient(135deg, #6d28d9, #9333ea 58%, #c026d3);
  color: #fff;
  box-shadow: 0 14px 30px rgba(124, 58, 237, 0.28), inset 0 1px 0 rgba(255, 255, 255, 0.24);
}

.ym-sidebar--collapsed .ym-sidebar-link {
  min-height: 60px;
  border-radius: 20px;
}

.ym-sidebar--collapsed .ym-sidebar-icon {
  transform: scale(1.08);
}

.ym-sidebar-link.is-active .ym-sidebar-link__glow {
  opacity: 1;
}

.ym-sidebar-link__glow {
  position: absolute;
  inset: -40% auto -40% -20%;
  width: 42px;
  background: rgba(99, 102, 241, 0.85);
  filter: blur(22px);
  opacity: 0;
  transition: opacity 180ms ease;
}

.ym-sidebar-icon {
  position: relative;
  z-index: 1;
  display: grid;
  place-items: center;
  color: currentColor;
}

.ym-sidebar-label {
  position: relative;
  z-index: 1;
  flex: 1;
  min-width: 0;
  font-size: 16px;
  line-height: 1.35;
}

.ym-sidebar-section-label {
  color: rgba(226, 232, 240, 0.76);
  font-size: 14px;
  font-weight: 950;
  letter-spacing: 0.12em;
  line-height: 1.35;
  margin: 0;
}

.ym-sidebar-section-line {
  height: 1px;
  width: 38px;
  margin-inline: auto;
  border-radius: 999px;
  background: rgba(226, 232, 240, 0.22);
}

.ym-sidebar--light .ym-sidebar-section-label {
  color: rgba(55, 32, 76, 0.76);
}

.ym-sidebar--light .ym-sidebar-section-line {
  background: rgba(99, 102, 241, 0.22);
}

.ym-sidebar-badge {
  position: relative;
  z-index: 1;
  border-radius: 999px;
  background: rgba(244, 63, 94, 0.95);
  color: #fff;
  font-size: 13.5px;
  font-weight: 900;
  padding: 0.14rem 0.48rem;
}

.ym-sidebar-bottom {
  border-top: 1px solid rgba(148, 163, 184, 0.12);
  background: linear-gradient(180deg, transparent, rgba(2, 6, 23, 0.14));
}

.ym-sidebar--light .ym-sidebar-bottom {
  border-top-color: rgba(91, 33, 182, 0.13);
  background: linear-gradient(180deg, transparent, rgba(124, 58, 237, 0.07));
}

.ym-sidebar-footer-mark {
  display: grid;
  height: 46px;
  width: 46px;
  margin-inline: auto;
  place-items: center;
  border: 1px solid rgba(129, 140, 248, 0.3);
  border-radius: 16px;
  background: rgba(99, 102, 241, 0.14);
  color: rgba(255, 255, 255, 0.86);
  font-size: 13px;
  font-weight: 950;
  letter-spacing: 0.04em;
}

.ym-sidebar--light .ym-sidebar-footer-mark {
  border-color: rgba(91, 33, 182, 0.24);
  background: rgba(124, 58, 237, 0.1);
  color: #4c1d95;
}

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.14); border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.28); }
.ym-sidebar--light .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(91, 33, 182, 0.22); }

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

.ym-sidebar-floating-tooltip.is-left {
  transform: translate(-100%, -50%);
}

.ym-sidebar-floating-tooltip.is-right {
  transform: translate(0, -50%);
}
</style>
