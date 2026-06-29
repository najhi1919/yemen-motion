<template>
  <div
    :class="[
      'ym-dashboard-shell text-[16px] transition-colors duration-300',
      dashboardTheme === 'dark' ? 'ym-dashboard-dark dark' : 'ym-dashboard-light',
      currentLocale === 'ar' ? 'ym-dashboard-rtl' : 'ym-dashboard-ltr',
      sidebarCollapsed ? 'is-sidebar-collapsed' : 'is-sidebar-expanded'
    ]"
    :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
  >
    <BackgroundWatermark />
    <button
      type="button"
      class="ym-mobile-sidebar-toggle"
      :aria-label="mobileSidebarLabel"
      @click="sidebarCollapsed = !sidebarCollapsed"
    >
      <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
      </svg>
    </button>
    <button
      v-if="!sidebarCollapsed"
      type="button"
      class="ym-mobile-sidebar-backdrop"
      :aria-label="mobileSidebarLabel"
      @click="sidebarCollapsed = true"
    />
    <AppSidebar
      :collapsed="sidebarCollapsed"
      :theme="dashboardTheme"
      @toggle="sidebarCollapsed = !sidebarCollapsed"
    />

    <div class="ym-dashboard-content relative z-10 transition-all duration-300">
      <AppTopBar />

      <main class="ym-dashboard-main relative z-10 p-5 md:p-7">
        <slot />
      </main>
    </div>

  </div>
</template>

<script setup lang="ts">
const sidebarCollapsed = ref(false)
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const currentLocale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')
let mobileSidebarQuery: MediaQueryList | null = null
const mobileSidebarLabel = computed(() => currentLocale.value === 'ar' ? 'فتح أو إغلاق القائمة' : 'Open or close sidebar')

const syncMobileSidebar = () => {
  if (mobileSidebarQuery?.matches) {
    sidebarCollapsed.value = true
  }
}

onMounted(() => {
  mobileSidebarQuery = window.matchMedia('(max-width: 768px)')
  syncMobileSidebar()
  mobileSidebarQuery.addEventListener('change', syncMobileSidebar)
})

onBeforeUnmount(() => {
  mobileSidebarQuery?.removeEventListener('change', syncMobileSidebar)
})
</script>

<style>
.ym-dashboard-shell {
  --ym-sidebar-width: 288px;
  --ym-sidebar-collapsed-width: 96px;
  --ym-text: #f0f6ff;
  --ym-muted: rgba(226, 232, 240, 0.92);
  --ym-shell-surface: linear-gradient(135deg, rgba(15, 23, 42, 0.88), rgba(30, 41, 59, 0.74));
  --ym-shell-border: rgba(148, 163, 184, 0.3);
  --ym-shell-shadow: 0 30px 82px rgba(2, 6, 23, 0.48);
  --ym-control-bg: rgba(15, 23, 42, 0.72);
  --ym-control-border: rgba(148, 163, 184, 0.3);
  --ym-card-bg: linear-gradient(145deg, rgba(15, 23, 42, 0.9), rgba(30, 41, 59, 0.76));
  --ym-card-border: rgba(148, 163, 184, 0.28);
  --ym-soft-border: rgba(148, 163, 184, 0.18);
  --ym-row-hover: rgba(148, 163, 184, 0.12);
  --ym-dropdown-bg: rgba(15, 23, 42, 0.96);
  --ym-card-shadow: 0 22px 58px rgba(2, 6, 23, 0.25);
  --ym-chart-grid: rgba(148, 163, 184, 0.28);
  --ym-chart-value-stroke: rgba(15, 23, 42, 0.88);
  --ym-tooltip-bg: rgba(8, 14, 30, 0.94);
  --ym-watermark-opacity-logo: 0.11;
  --ym-watermark-opacity-name: 0.09;
  position: relative;
  isolation: isolate;
  height: 100dvh;
  min-height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(circle at 18% 8%, rgba(190, 0, 1, 0.2), transparent 29rem),
    radial-gradient(circle at 86% 12%, rgba(99, 102, 241, 0.28), transparent 32rem),
    linear-gradient(135deg, #050816 0%, #101827 48%, #080d1c 100%);
  background-attachment: fixed;
  color: var(--ym-text);
  -webkit-font-smoothing: antialiased;
  text-rendering: geometricPrecision;
}

.ym-dashboard-light {
  --ym-text: #171126;
  --ym-muted: rgba(45, 36, 64, 0.9);
  --ym-shell-surface: linear-gradient(135deg, rgba(255, 255, 255, 0.98), rgba(232, 221, 255, 0.96));
  --ym-shell-border: rgba(109, 40, 217, 0.38);
  --ym-shell-shadow: 0 28px 72px rgba(76, 29, 149, 0.24);
  --ym-control-bg: rgba(250, 247, 255, 0.96);
  --ym-control-border: rgba(109, 40, 217, 0.36);
  --ym-card-bg: linear-gradient(145deg, #ffffff 0%, #f2eaff 100%);
  --ym-card-border: rgba(109, 40, 217, 0.34);
  --ym-soft-border: rgba(91, 33, 182, 0.24);
  --ym-row-hover: rgba(109, 40, 217, 0.14);
  --ym-dropdown-bg: rgba(255, 255, 255, 0.99);
  --ym-card-shadow: 0 22px 56px rgba(76, 29, 149, 0.2), 0 4px 14px rgba(91, 33, 182, 0.08);
  --ym-chart-grid: rgba(76, 29, 149, 0.28);
  --ym-chart-value-stroke: rgba(255, 255, 255, 0.94);
  --ym-tooltip-bg: rgba(255, 255, 255, 0.99);
  --ym-watermark-opacity-logo: 0.085;
  --ym-watermark-opacity-name: 0.08;
  background:
    radial-gradient(circle at 16% 6%, rgba(190, 0, 1, 0.14), transparent 27rem),
    radial-gradient(circle at 86% 10%, rgba(109, 40, 217, 0.3), transparent 30rem),
    radial-gradient(circle at 52% 92%, rgba(37, 99, 235, 0.14), transparent 34rem),
    linear-gradient(135deg, #eee8ff 0%, #e6eaff 48%, #fff0e5 100%);
}

.ym-dashboard-content {
  position: relative;
  z-index: 10;
  height: 100dvh;
  min-width: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-gutter: stable;
}

.ym-dashboard-rtl .ym-dashboard-content {
  margin-right: var(--ym-sidebar-width);
}

.ym-dashboard-ltr .ym-dashboard-content {
  margin-left: var(--ym-sidebar-width);
}

.ym-dashboard-rtl.is-sidebar-collapsed .ym-dashboard-content {
  margin-right: var(--ym-sidebar-collapsed-width);
}

.ym-dashboard-ltr.is-sidebar-collapsed .ym-dashboard-content {
  margin-left: var(--ym-sidebar-collapsed-width);
}

.ym-dashboard-main {
  position: relative;
  z-index: 10;
}

.ym-dashboard-ltr {
  direction: ltr;
}

.ym-dashboard-rtl {
  direction: rtl;
}

.ym-mobile-sidebar-toggle,
.ym-mobile-sidebar-backdrop {
  display: none;
}

@media (max-width: 768px) {
  .ym-dashboard-content {
    margin-left: 0 !important;
    margin-right: 0 !important;
    width: 100%;
  }

  .ym-dashboard-main {
    min-width: 0;
  }

  .ym-dashboard-shell .ym-sidebar {
    z-index: 70;
  }

  .ym-dashboard-shell .ym-sidebar--right.ym-sidebar--collapsed {
    transform: translateX(100%);
    pointer-events: none;
  }

  .ym-dashboard-shell .ym-sidebar--left.ym-sidebar--collapsed {
    transform: translateX(-100%);
    pointer-events: none;
  }

  .ym-mobile-sidebar-toggle {
    position: fixed;
    inset-block-start: 1rem;
    inset-inline-end: 1rem;
    z-index: 80;
    display: grid;
    height: 52px;
    width: 52px;
    place-items: center;
    border: 1px solid var(--ym-shell-border);
    border-radius: 18px;
    background: var(--ym-shell-surface);
    box-shadow: var(--ym-shell-shadow);
    color: var(--ym-text);
    backdrop-filter: blur(18px) saturate(140%);
  }

  .ym-mobile-sidebar-backdrop {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: block;
    background: rgba(2, 6, 23, 0.42);
    backdrop-filter: blur(2px);
  }
}
</style>
