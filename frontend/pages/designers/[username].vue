<script setup lang="ts">
import PublicDesignerHero from '~/components/public/designer/PublicDesignerHero.vue'
import PublicDesignerProfessionalSections from '~/components/public/designer/PublicDesignerProfessionalSections.vue'
import PublicDesignerWorksGrid from '~/components/public/designer/PublicDesignerWorksGrid.vue'

definePageMeta({ layout: 'public' })

type PublicProfileSection = 'works' | 'intro' | 'expertise' | 'tools-languages'

const route = useRoute()
const usernameParam = route.params.username
const username = Array.isArray(usernameParam) ? usernameParam[0] || '' : String(usernameParam || '')

const {
  profile,
  pending,
  error,
  errorStatus,
  retry,
} = await usePublicDesignerProfile(username)

function openNotFoundError(): void {
  showError(createError({
    statusCode: 404,
    statusMessage: 'الصفحة غير موجودة',
    message: 'تعذر العثور على ملف مصمم عام بهذا الاسم.',
  }))
}

watch(errorStatus, (status) => {
  if (status === 404) {
    openNotFoundError()
  }
}, { immediate: true })

const retrying = ref(false)
const activeMobileSection = ref<PublicProfileSection>('works')

async function retryProfile(): Promise<void> {
  retrying.value = true
  try {
    await retry()
    if (errorStatus.value === 404) {
      openNotFoundError()
    }
  } finally {
    retrying.value = false
  }
}

useSeoMeta({
  title: () => profile.value?.seo.title,
  description: () => profile.value?.seo.description,
  ogTitle: () => profile.value?.seo.title,
  ogDescription: () => profile.value?.seo.description,
  ogType: () => profile.value?.seo.type,
  ogImage: () => profile.value?.seo.image_url || undefined,
  twitterCard: 'summary_large_image',
  twitterTitle: () => profile.value?.seo.title,
  twitterDescription: () => profile.value?.seo.description,
  twitterImage: () => profile.value?.seo.image_url || undefined,
})

useHead(() => ({
  link: profile.value
    ? [{ rel: 'canonical', href: profile.value.seo.canonical_path }]
    : [],
}))
</script>

<template>
  <main id="public-profile-content" class="public-profile-main">
    <div class="profile-page-container">
      <div v-if="pending && !profile" class="profile-loading" aria-busy="true" aria-label="جارٍ تحميل ملف المصمم">
        <div class="profile-loading-cover" />
        <div class="profile-loading-identity">
          <span class="profile-loading-avatar" />
          <span class="profile-loading-copy" />
        </div>
        <div class="profile-loading-body">
          <span />
          <span />
        </div>
      </div>

      <section v-else-if="error && !profile && errorStatus !== 404" class="profile-error" role="alert">
        <h1 class="profile-error-title">تعذر تحميل ملف المصمم</h1>
        <p class="profile-error-description">حدث خطأ مؤقت. حاول مرة أخرى بعد قليل.</p>
        <button
          type="button"
          class="mt-6 inline-flex min-h-12 items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white transition-colors hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:bg-neutral-300 motion-reduce:transition-none"
          :disabled="retrying"
          @click="retryProfile"
        >
          {{ retrying ? 'جارٍ إعادة المحاولة…' : 'إعادة المحاولة' }}
        </button>
      </section>

      <template v-else-if="profile">
        <PublicDesignerHero
          v-model:active-mobile-section="activeMobileSection"
          :identity="profile.identity"
          :professional="profile.professional"
        />
        <div class="profile-body">
          <PublicDesignerProfessionalSections
            :class="[
              'profile-professional-column',
              { 'profile-professional-column-active': activeMobileSection !== 'works' },
            ]"
            :identity="profile.identity"
            :professional="profile.professional"
            :organization="profile.organization"
            :active-mobile-section="activeMobileSection"
          />
          <div
            id="profile-panel-works"
            role="tabpanel"
            aria-labelledby="profile-tab-works"
            :class="[
              'profile-works-column profile-mobile-panel',
              { 'profile-mobile-panel-active': activeMobileSection === 'works' },
            ]"
          >
            <div class="profile-works-stack">
              <PublicDesignerWorksGrid
                v-if="profile.featured_works.total > 0"
                featured
                :identity="profile.identity"
                :works="profile.featured_works.items"
                :total="profile.featured_works.total"
              />

              <PublicDesignerWorksGrid
                v-if="
                  profile.works.total > 0
                    || profile.featured_works.total === 0
                "
                :identity="profile.identity"
                :works="profile.works.items"
                :total="profile.works.total"
              />
            </div>
          </div>
        </div>
      </template>
    </div>
  </main>
</template>

<style scoped>
.public-profile-main {
  min-height: calc(100dvh - 60px);
  overflow-x: clip;
  background:
    radial-gradient(circle at 14% 7%, rgba(226, 29, 29, 0.055), transparent 25rem),
    radial-gradient(circle at 88% 46%, rgba(26, 26, 29, 0.038), transparent 20rem),
    linear-gradient(rgba(26, 26, 29, 0.014) 1px, transparent 1px),
    linear-gradient(90deg, rgba(26, 26, 29, 0.014) 1px, transparent 1px),
    var(--ym-page-bg);
  background-size: auto, auto, 44px 44px, 44px 44px, auto;
  color: var(--ym-text);
}

.profile-page-container {
  width: 100%;
  max-width: 1180px;
  margin-inline: auto;
  padding: 24px 20px 64px;
  box-sizing: border-box;
}

.profile-body {
  display: grid;
  grid-template-columns: 340px minmax(0, 1fr);
  gap: 24px;
  align-items: start;
  margin-top: 20px;
}

.profile-works-stack {
  display: grid;
  min-width: 0;
  gap: 20px;
}

.profile-error {
  max-width: 640px;
  margin: 56px auto;
  padding: 48px 24px;
  border: 1px solid rgba(226, 29, 29, 0.28);
  border-radius: 18px;
  background: var(--ym-surface);
  box-shadow: var(--ym-shadow);
  text-align: center;
}

.profile-error-title {
  margin: 0;
  color: var(--ym-charcoal);
  font-size: 24px;
  font-weight: 800;
}

.profile-error-description {
  max-width: 32rem;
  margin: 12px auto 0;
  color: var(--ym-muted);
  line-height: 1.8;
}

.profile-loading-cover {
  min-height: 260px;
  max-height: 420px;
  aspect-ratio: 2.65 / 1;
  border-radius: 20px 20px 0 0;
  background: #e9ebef;
  animation: profile-pulse 1.5s ease-in-out infinite;
}

.profile-loading-identity {
  display: flex;
  min-height: 170px;
  align-items: center;
  gap: 24px;
  padding-inline: 32px;
  border: 1px solid var(--ym-border);
  border-top: 0;
  border-radius: 0 0 20px 20px;
  background: var(--ym-surface);
}

.profile-loading-avatar {
  width: 120px;
  height: 120px;
  flex-shrink: 0;
  border-radius: 50%;
  background: #e5e7eb;
  animation: profile-pulse 1.5s ease-in-out infinite;
}

.profile-loading-copy {
  width: min(380px, 55%);
  height: 72px;
  border-radius: 8px;
  background: #eceef1;
  animation: profile-pulse 1.5s ease-in-out infinite;
}

.profile-loading-body {
  display: grid;
  grid-template-columns: 340px minmax(0, 1fr);
  gap: 24px;
  margin-top: 20px;
}

.profile-loading-body span {
  min-height: 280px;
  border: 1px solid var(--ym-border);
  border-radius: 16px;
  background: var(--ym-surface);
  animation: profile-pulse 1.5s ease-in-out infinite;
}

.public-profile-main :deep(.profile-tab) {
  transition: color 180ms ease, background-color 180ms ease;
}

.public-profile-main :deep(.profile-tab:hover) {
  background: var(--ym-red-soft);
}

.public-profile-main :deep(.profile-tab::after) {
  transition: opacity 180ms ease;
}

@keyframes profile-pulse {
  50% { opacity: 0.55; }
}

@media (max-width: 899px) {
  .profile-body,
  .profile-loading-body {
    grid-template-columns: minmax(0, 1fr);
    gap: 16px;
  }

  .profile-works-column {
    order: 1;
  }

  .profile-professional-column {
    order: 2;
    display: none;
  }

  .profile-professional-column-active {
    display: block;
  }

  .profile-mobile-panel {
    display: none;
  }

  .profile-mobile-panel-active {
    display: block;
  }
}

@media (max-width: 699px) {
  .public-profile-main {
    min-height: calc(100dvh - 56px);
  }

  .profile-page-container {
    padding: 0 16px 32px;
  }

  .profile-body,
  .profile-loading-body {
    gap: 12px;
    margin-top: 12px;
  }

  .profile-loading-cover {
    width: calc(100% + 32px);
    min-height: 180px;
    margin-inline: -16px;
    aspect-ratio: 1.9 / 1;
    border-radius: 0;
  }

  .profile-loading-identity {
    width: calc(100% + 32px);
    min-height: 220px;
    flex-direction: column;
    justify-content: center;
    margin-inline: -16px;
    padding: 16px;
    border-inline: 0;
    border-radius: 0;
  }

  .profile-loading-avatar {
    width: 96px;
    height: 96px;
  }

  .profile-loading-copy {
    width: 70%;
    height: 60px;
  }

  .profile-loading-body span {
    border-radius: 10px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .public-profile-main :deep(.profile-tab),
  .public-profile-main :deep(.profile-tab::after) {
    transition: none;
  }

  .profile-loading-cover,
  .profile-loading-avatar,
  .profile-loading-copy,
  .profile-loading-body span {
    animation: none;
  }
}
</style>
