<template>
  <div class="ym-designer-studio ym-designer-canvas-art min-h-screen text-[var(--ym-d-text)]" dir="rtl">
    <a
      href="#designer-studio-content"
      class="fixed right-4 top-3 z-40 -translate-y-24 rounded-xl bg-[var(--ym-d-red)] px-4 py-3 font-bold text-white shadow-[var(--ym-d-shadow-md)] transition-transform duration-200 focus:translate-y-0 focus:outline-none focus:ring-4 focus:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
    >
      الانتقال إلى محتوى مساحة المصمم
    </a>

    <header class="ym-designer-studio-header ym-designer-studio-header-light sticky top-0 z-30 text-[var(--ym-d-charcoal)]">
      <div class="mx-auto flex min-h-[76px] max-w-7xl items-center justify-between gap-3 px-3 min-[481px]:gap-4 min-[481px]:px-4 sm:px-6 lg:px-8">
        <div class="relative z-10 flex min-w-0 shrink-0 items-center">
          <NuxtLink
            to="/designer"
            class="ym-designer-brand-lockup flex min-h-11 min-w-0 shrink items-center gap-2.5 rounded-xl focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
            aria-label="العودة إلى مساحة المصمم"
          >
            <span class="grid h-11 w-11 shrink-0 place-items-center">
              <img src="/logo.svg" alt="" class="h-11 w-11 scale-[1.08] object-contain">
            </span>
            <span class="flex min-w-0 flex-col justify-center">
              <img src="/name.svg" alt="Yemen Motion" class="h-[19px] w-auto max-w-28 object-contain object-right min-[480px]:h-[21px] sm:h-[25px] sm:max-w-36">
              <span class="mt-1 hidden text-[11px] font-bold leading-none text-[var(--ym-d-charcoal)] opacity-70 min-[480px]:block">
                استوديو المصمم
              </span>
            </span>
          </NuxtLink>
        </div>

        <div class="ym-designer-account-cluster relative z-10 flex min-w-0 items-center justify-end gap-2 sm:gap-3">
          <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white bg-[var(--ym-d-surface-muted)] text-sm font-black text-[var(--ym-d-charcoal)] shadow-sm ring-1 ring-[var(--ym-d-red)]">
            <img
              v-if="designerAvatarObjectUrl"
              :src="designerAvatarObjectUrl"
              :alt="`الصورة الشخصية لـ${authStore.user?.name || 'مصمم Yemen Motion'}`"
              class="h-full w-full object-cover"
            >
            <span v-else-if="designerAccountInitial" aria-hidden="true">
              {{ designerAccountInitial }}
            </span>
            <svg
              v-else
              viewBox="0 0 24 24"
              fill="none"
              class="h-5 w-5"
              aria-hidden="true"
            >
              <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm7 8a7 7 0 0 0-14 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            </svg>
          </span>
          <span class="hidden min-w-0 max-w-48 text-[15px] font-semibold text-[var(--ym-d-charcoal)] md:block">
            <bdi dir="auto" class="block truncate text-start">
              {{ authStore.user?.name || 'مصمم Yemen Motion' }}
            </bdi>
          </span>
          <span class="group relative shrink-0">
            <button
              type="button"
              class="ym-designer-logout-control inline-flex h-11 w-11 min-w-11 items-center justify-center rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] text-white transition duration-200 hover:border-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none"
              :disabled="authStore.isLoading"
              aria-label="تسجيل الخروج"
              aria-describedby="designer-logout-tooltip"
              title="تسجيل الخروج"
              @click="logout"
            >
              <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5" aria-hidden="true">
                <path d="M14 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2v-3M10 12h11m0 0-3-3m3 3-3 3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <span class="sr-only">تسجيل الخروج</span>
            </button>
            <span
              id="designer-logout-tooltip"
              role="tooltip"
              class="pointer-events-none absolute left-0 top-full z-40 mt-2 whitespace-nowrap rounded-lg bg-[var(--ym-d-charcoal)] px-2.5 py-1.5 text-xs font-bold text-white opacity-0 shadow-lg transition duration-200 group-hover:translate-y-0 group-hover:opacity-100 group-focus-within:translate-y-0 group-focus-within:opacity-100 motion-reduce:transform-none motion-reduce:transition-none"
            >
              تسجيل الخروج
            </span>
          </span>
        </div>
      </div>
    </header>

    <nav
      aria-label="تنقل مساحة المصمم"
      class="relative z-20 border-b border-[var(--ym-d-border)] bg-white/95 px-4 sm:px-6"
    >
      <div class="mx-auto grid max-w-7xl grid-cols-2 gap-1 py-2 sm:flex sm:justify-start">
        <NuxtLink
          to="/designer"
          exact-active-class="ym-designer-nav-active"
          class="ym-designer-nav-link inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
        >
          الملف
        </NuxtLink>
        <NuxtLink
          to="/designer/works"
          active-class="ym-designer-nav-active"
          class="ym-designer-nav-link inline-flex min-h-11 items-center justify-center rounded-xl px-5 text-sm font-bold transition duration-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
        >
          الأعمال
        </NuxtLink>
      </div>
    </nav>

    <main id="designer-studio-content" class="relative z-10 min-h-[calc(100vh-7.5rem)]">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()
const designerHeaderAvatarSource = useState<string | null>(
  'ym-designer-header-avatar-source',
  () => null,
)
const designerHeaderProfileAttempted = useState<boolean>(
  'ym-designer-header-profile-attempted',
  () => false,
)
const designerAvatarObjectUrl = ref<string | null>(null)
const designerAccountInitial = computed(() => {
  const name = authStore.user?.name?.trim()
  return name ? Array.from(name)[0] || null : null
})
const { profileState, fetchProfile } = useDesignerProfile()
const { loadMedia } = useDesignerProfileMedia()
let avatarLoadSequence = 0

function revokeDesignerAvatarObjectUrl(): void {
  if (designerAvatarObjectUrl.value) {
    URL.revokeObjectURL(designerAvatarObjectUrl.value)
    designerAvatarObjectUrl.value = null
  }
}

async function loadDesignerAvatar(sourceUrl: string | null): Promise<void> {
  if (!import.meta.client) {
    return
  }

  const sequence = ++avatarLoadSequence
  revokeDesignerAvatarObjectUrl()

  if (!sourceUrl) {
    return
  }

  try {
    const blob = await loadMedia(sourceUrl)

    if (
      sequence !== avatarLoadSequence
      || designerHeaderAvatarSource.value !== sourceUrl
    ) {
      return
    }

    const objectUrl = URL.createObjectURL(blob)

    if (
      sequence !== avatarLoadSequence
      || designerHeaderAvatarSource.value !== sourceUrl
    ) {
      URL.revokeObjectURL(objectUrl)
      return
    }

    designerAvatarObjectUrl.value = objectUrl
  } catch {
    if (sequence === avatarLoadSequence) {
      designerAvatarObjectUrl.value = null
    }
  }
}

watch(designerHeaderAvatarSource, sourceUrl => {
  void loadDesignerAvatar(sourceUrl)
}, { immediate: true })

onMounted(async () => {
  if (!authStore.isInitialized) {
    await authStore.hydrateAuth()
  }

  if (
    !authStore.hasRole('designer')
    || designerHeaderAvatarSource.value
    || designerHeaderProfileAttempted.value
  ) {
    return
  }

  try {
    if (!profileState.value) {
      await fetchProfile()
    }

    designerHeaderAvatarSource.value
      = profileState.value?.profile?.identity_media.avatar_url || null
  } catch {
    designerHeaderAvatarSource.value = null
  } finally {
    designerHeaderProfileAttempted.value = true
  }
})

onBeforeUnmount(() => {
  avatarLoadSequence += 1
  revokeDesignerAvatarObjectUrl()
})

async function logout(): Promise<void> {
  await authStore.logout()
  await navigateTo('/auth/login')
}
</script>

<style>
.ym-designer-studio,
.ym-designer-portal {
  --ym-d-charcoal: #111111;
  --ym-d-charcoal-soft: #1B1B1B;
  --ym-d-charcoal-raised: #242424;
  --ym-d-red: #E21D1D;
  --ym-d-red-strong: #C91414;
  --ym-d-page: #FCFCFC;
  --ym-d-surface: #FFFFFF;
  --ym-d-text: #151515;
  --ym-d-muted: #666666;
  --ym-d-border: rgba(17, 17, 17, 0.10);
  --ym-d-border-strong: rgba(17, 17, 17, 0.17);
  --ym-d-shadow-sm: 0 8px 26px rgba(17, 17, 17, 0.055);
  --ym-d-shadow-md: 0 18px 48px rgba(17, 17, 17, 0.10);
  --ym-d-focus: rgba(226, 29, 29, 0.24);
  --ym-d-red-soft: rgba(226, 29, 29, 0.08);
  --ym-d-red-border: rgba(226, 29, 29, 0.22);
  --ym-d-surface-muted: #F7F6F5;
  --ym-d-surface-warm: #FFFCFB;
}

.ym-designer-studio {
  position: relative;
  min-height: 100%;
  isolation: isolate;
  overflow-x: clip;
  background: var(--ym-d-page);
}

.ym-designer-canvas-art::before,
.ym-designer-canvas-art::after {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  content: "";
}

.ym-designer-canvas-art::before {
  background:
    radial-gradient(ellipse at 92% 6%, rgba(226, 29, 29, 0.045), transparent 42%),
    radial-gradient(ellipse at 6% 72%, rgba(17, 17, 17, 0.03), transparent 46%);
}

.ym-designer-canvas-art::after {
  background:
    radial-gradient(ellipse at 70% 48%, rgba(226, 29, 29, 0.022), transparent 48%),
    radial-gradient(ellipse at 28% 100%, rgba(17, 17, 17, 0.02), transparent 44%);
}

.ym-designer-studio-header-light {
  overflow: visible;
  border-bottom: 2px solid var(--ym-d-red);
  background: rgba(255, 255, 255, 0.97);
  box-shadow: 0 8px 22px rgba(17, 17, 17, 0.07);
}

.ym-designer-studio-header-light::before,
.ym-designer-studio-header-light::after {
  position: absolute;
  pointer-events: none;
  content: "";
}

.ym-designer-studio-header-light::before {
  inset: 0;
  background:
    linear-gradient(118deg, transparent 66%, rgba(17, 17, 17, 0.028) 66% 66.35%, transparent 66.35%),
    linear-gradient(64deg, transparent 82%, rgba(226, 29, 29, 0.045) 82% 82.4%, transparent 82.4%);
}

.ym-designer-studio-header-light::after {
  top: -4.25rem;
  left: 5%;
  width: 9rem;
  height: 9rem;
  border: 1px solid rgba(226, 29, 29, 0.045);
  border-radius: 999px;
}

.ym-designer-title-card {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 24px;
  color: white;
  background: var(--ym-d-charcoal);
  box-shadow: var(--ym-d-shadow-md);
}

.ym-designer-title-card::before,
.ym-designer-title-card::after {
  position: absolute;
  z-index: 0;
  pointer-events: none;
  content: "";
}

.ym-designer-title-card::before {
  inset: 0;
  opacity: 0.7;
  background:
    linear-gradient(118deg, transparent 62%, rgba(255, 255, 255, 0.045) 62% 62.7%, transparent 62.7%),
    linear-gradient(62deg, transparent 80%, rgba(226, 29, 29, 0.075) 80% 80.8%, transparent 80.8%);
}

.ym-designer-title-card::after {
  left: -5rem;
  bottom: -7rem;
  width: 17rem;
  height: 17rem;
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 999px;
  box-shadow: 0 0 70px rgba(226, 29, 29, 0.075);
}

.ym-designer-title-card > * {
  position: relative;
  z-index: 1;
}

.ym-designer-nav-link {
  position: relative;
  color: var(--ym-d-charcoal);
}

.ym-designer-nav-link:hover {
  background: rgba(17, 17, 17, 0.055);
}

.ym-designer-nav-active {
  border: 1px solid var(--ym-d-red-border);
  color: var(--ym-d-red-strong);
  background: var(--ym-d-red-soft);
}

.ym-designer-nav-active::after {
  position: absolute;
  right: 50%;
  bottom: 4px;
  width: 18px;
  height: 2px;
  border-radius: 999px;
  content: "";
  transform: translateX(50%);
  background: var(--ym-d-red);
}

@media (max-width: 389px) {
  .ym-designer-brand-lockup {
    gap: 0.375rem;
  }

  .ym-designer-brand-lockup > span:first-child {
    width: 2.5rem;
    height: 2.5rem;
  }

  .ym-designer-brand-lockup > span:first-child img {
    width: 2.5rem;
    height: 2.5rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-designer-studio *,
  .ym-designer-studio *::before,
  .ym-designer-studio *::after {
    scroll-behavior: auto !important;
  }
}
</style>
