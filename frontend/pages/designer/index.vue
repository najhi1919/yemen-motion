<script setup lang="ts">
import type { ComponentPublicInstance } from 'vue'
import DesignerProfileFeaturedWorksDrawer from '~/components/designer/profile/DesignerProfileFeaturedWorksDrawer.vue'
import DesignerProfileFeaturedWorksPanel from '~/components/designer/profile/DesignerProfileFeaturedWorksPanel.vue'
import DesignerProfileOverview from '~/components/designer/profile/DesignerProfileOverview.vue'
import DesignerProfilePreviewDrawer from '~/components/designer/profile/DesignerProfilePreviewDrawer.vue'
import DesignerProfileProfessionalDrawer from '~/components/designer/profile/DesignerProfileProfessionalDrawer.vue'
import DesignerProfileOrganizationDeleteDialog from '~/components/designer/profile/DesignerProfileOrganizationDeleteDialog.vue'
import DesignerProfileOrganizationDrawer from '~/components/designer/profile/DesignerProfileOrganizationDrawer.vue'
import DesignerProfileOrganizationPanel from '~/components/designer/profile/DesignerProfileOrganizationPanel.vue'
import DesignerProfileProfessionalOverview from '~/components/designer/profile/DesignerProfileProfessionalOverview.vue'
import DesignerProfilePublicationConfirmDialog from '~/components/designer/profile/DesignerProfilePublicationConfirmDialog.vue'
import DesignerProfilePublicationPanel from '~/components/designer/profile/DesignerProfilePublicationPanel.vue'
import DesignerProfileSetupDrawer from '~/components/designer/profile/DesignerProfileSetupDrawer.vue'
import type { DesignerProfile, DesignerProfilePayload } from '~/types/designer-profile'
import type { DesignerProfileProfessionalPayload } from '~/types/designer-profile-professional'
import type { DesignerProfileOrganizationInput } from '~/types/designer-profile-organization'

definePageMeta({
  layout: 'designer'
})

const {
  profileState,
  loading,
  saving,
  error,
  validationErrors,
  fetchProfile,
  saveProfile,
  checkUsernameAvailability
} = useDesignerProfile()

const {
  professionalState,
  loading: professionalLoading,
  saving: professionalSaving,
  error: professionalError,
  validationErrors: professionalValidationErrors,
  fetchProfessional,
  saveProfessional,
  clearError: clearProfessionalError,
} = useDesignerProfileProfessional()

const {
  organizationState,
  logoUrl: organizationLogoUrl,
  loading: organizationLoading,
  saving: organizationSaving,
  error: organizationError,
  validationErrors: organizationValidationErrors,
  fetchOrganization,
  saveOrganization,
  deleteOrganization,
  uploadLogo: uploadOrganizationLogo,
  deleteLogo: deleteOrganizationLogo,
  clearError: clearOrganizationError,
  disposeLogoUrl: disposeOrganizationLogoUrl
} = useDesignerProfileOrganization()

const {
  publicationState,
  previewState,
  publicationLoading,
  previewLoading,
  publicationSaving,
  publicationError,
  previewError,
  fetchPublication,
  fetchPreview,
  publishProfile,
  hideProfile,
  clearPreview,
  clearErrors: clearPublicationErrors,
} = useDesignerProfilePublication()

const {
  featuredWorksState,
  loading: featuredWorksLoading,
  saving: featuredWorksSaving,
  error: featuredWorksError,
  validationErrors: featuredWorksValidationErrors,
  coverUrls: featuredWorksCoverUrls,
  fetchFeaturedWorks,
  saveFeaturedWorks,
  clearError: clearFeaturedWorksError,
  disposeCoverUrls: disposeFeaturedWorksCoverUrls,
} = useDesignerProfileFeaturedWorks()

const drawerOpen = ref(false)
const drawerSession = ref(0)
const successMessage = ref<string | null>(null)
/** رسالة النجاح الجزئي تظهر داخل Drawer (PUT نجح + Logo فشل) */
const organizationNotice = ref<string | null>(null)
const professionalDrawerOpen = ref(false)
const organizationDrawerOpen = ref(false)
const organizationDeleteDialogOpen = ref(false)
const featuredWorksDrawerOpen = ref(false)
const previewDrawerOpen = ref(false)
const confirmationOpen = ref(false)
const confirmationAction = ref<'publish' | 'hide'>('publish')
const confirmationRepublish = ref(false)
const publicationSuccessMessage = ref<string | null>(null)
const profileOverview = ref<ComponentPublicInstance | null>(null)
const authStore = useAuthStore()
let successTimer: number | null = null
let publicationSuccessTimer: number | null = null
let mediaObserver: MutationObserver | null = null

const synchronizedProfile = computed<DesignerProfile | null>(() => {
  const profile = profileState.value?.profile
  const publication = publicationState.value?.publication

  if (!profile) return null
  if (!publication) return profile

  return {
    ...profile,
    publication_status: publication.status,
    published_at: publication.published_at,
  }
})

function openProfileEditor(): void {
  drawerSession.value += 1
  drawerOpen.value = true
}

async function loadProfile(): Promise<void> {
  try {
    const loaded = await fetchProfile()

    if (loaded.profile) {
      await Promise.allSettled([
        fetchProfessional(),
        fetchOrganization(),
        fetchPublication(),
        fetchFeaturedWorks(),
      ])
      await nextTick()
      observeMediaSuccess()
    }
  } catch {
    // The composable exposes the reader-facing error state.
  }
}

async function handleSave(payload: DesignerProfilePayload): Promise<void> {
  try {
    const saved = await saveProfile(payload)

    if (saved.profile) {
      await Promise.allSettled([
        fetchProfessional(),
        fetchOrganization(),
        fetchPublication(),
        fetchFeaturedWorks(),
      ])
    }

    drawerOpen.value = false
    successMessage.value = 'تم حفظ بيانات ملفك بنجاح.'

    if (successTimer) window.clearTimeout(successTimer)

    successTimer = window.setTimeout(() => {
      successMessage.value = null
    }, 3200)
  } catch {
    // Keep the drawer and form values open for correction.
  }
}

function openProfessionalEditor(): void {
  clearProfessionalError()
  professionalDrawerOpen.value = true
}

async function handleProfessionalSave(payload: DesignerProfileProfessionalPayload): Promise<void> {
  try {
    await saveProfessional(payload)
    await fetchProfile()
    await Promise.allSettled([
      fetchPublication(),
      fetchFeaturedWorks(),
    ])

    professionalDrawerOpen.value = false
    successMessage.value = 'تم حفظ بياناتك المهنية بنجاح.'

    if (successTimer) window.clearTimeout(successTimer)

    successTimer = window.setTimeout(() => {
      successMessage.value = null
    }, 3200)
  } catch {
    // Keep the professional drawer open with the safe API error.
  }
}

function openOrganizationEditor(): void {
  clearOrganizationError()
  organizationNotice.value = null
  organizationDrawerOpen.value = true
}

async function handleOrganizationSave(
  input: DesignerProfileOrganizationInput,
  logoAction: { type: 'upload' | 'delete' | 'none', file?: File }
): Promise<void> {
  // مسح notice السابقة
  organizationNotice.value = null

  // Version token من State الحالية — Drawer لا يملكه
  const currentToken = organizationState.value?.updated_at ?? null

  let updatedToken: string
  try {
    updatedToken = await saveOrganization({
      ...input,
      expected_updated_at: currentToken,
    })
  } catch {
    // PUT فشل — error مكشوف عبر composable، Drawer يبقى مفتوحاً
    return
  }

  // PUT نجح — الآن نحاول Logo
  let logoSuccess = true
  try {
    if (logoAction.type === 'upload' && logoAction.file) {
      // token من نتيجة PUT مباشرة — ليس من State القديمة
      await uploadOrganizationLogo(logoAction.file, updatedToken)
    } else if (logoAction.type === 'delete') {
      await deleteOrganizationLogo(updatedToken)
    }
  } catch {
    logoSuccess = false
  }

  if (!logoSuccess) {
    // Partial success: Drawer يبقى مفتوحاً، File/intent تبقى كما هي
    organizationNotice.value = 'تم حفظ بيانات المنشأة، لكن تعذر تحديث الشعار. حاول مرة أخرى.'
    // تحديث publication في الخلفية دون إغلاق الـDrawer
    void Promise.allSettled([fetchPublication(), fetchFeaturedWorks()])
    return
  }

  // نجاح كامل
  organizationNotice.value = null
  await fetchOrganization()
  organizationDrawerOpen.value = false

  void Promise.allSettled([fetchPublication(), fetchFeaturedWorks()])

  successMessage.value = 'تم حفظ بيانات المنشأة بنجاح.'
  if (successTimer) window.clearTimeout(successTimer)
  successTimer = window.setTimeout(() => {
    successMessage.value = null
  }, 4200)
}

function openOrganizationDeleteDialog(): void {
  organizationDeleteDialogOpen.value = true
}

async function handleOrganizationDelete(): Promise<void> {
  const currentOrg = organizationState.value?.organization
  const currentToken = organizationState.value?.updated_at

  if (!currentOrg || !currentToken) {
    organizationDeleteDialogOpen.value = false
    if (!currentOrg) {
      organizationDrawerOpen.value = false
    }
    organizationNotice.value = null
    return
  }

  try {
    await deleteOrganization(currentToken)
    // نجاح: نغلق الاثنين
    organizationDeleteDialogOpen.value = false
    organizationDrawerOpen.value = false
    organizationNotice.value = null

    void Promise.allSettled([fetchPublication(), fetchFeaturedWorks()])

    successMessage.value = 'تم حذف المنشأة بنجاح.'
    if (successTimer) window.clearTimeout(successTimer)
    successTimer = window.setTimeout(() => {
      successMessage.value = null
    }, 3200)
  } catch {
    // فشل: Dialog يبقى مفتوحاً مع error مكشوف عبر organizationError
    // 409: composable عمل resync وحدّث updated_at للمحاولة التالية
  }
}

async function retryFeaturedWorks(): Promise<void> {
  try {
    await fetchFeaturedWorks()
  } catch {
    // The panel exposes the safe API error.
  }
}

function openFeaturedWorksEditor(): void {
  clearFeaturedWorksError()
  featuredWorksDrawerOpen.value = true
}

async function handleFeaturedWorksSave(
  workIds: number[],
): Promise<void> {
  const saved = await saveFeaturedWorks(workIds)

  if (!saved) return

  await Promise.allSettled([
    fetchProfile(),
    fetchProfessional(),
    fetchPublication(),
  ])

  featuredWorksDrawerOpen.value = false
  successMessage.value =
    'تم حفظ الأعمال المميزة وترتيبها بنجاح.'

  if (successTimer) window.clearTimeout(successTimer)

  successTimer = window.setTimeout(() => {
    successMessage.value = null
  }, 3200)
}

function showPublicationSuccess(message: string): void {
  publicationSuccessMessage.value = message
  if (publicationSuccessTimer) window.clearTimeout(publicationSuccessTimer)
  publicationSuccessTimer = window.setTimeout(() => {
    publicationSuccessMessage.value = null
  }, 4200)
}

async function retryPublication(): Promise<void> {
  try { await fetchPublication() } catch { /* Safe error state is shown in the panel. */ }
}

function openAvatarEditor(): void {
  const root = profileOverview.value?.$el as HTMLElement | undefined
  const avatarButton = root?.querySelector<HTMLButtonElement>('.ym-profile-identity-actions button')
  avatarButton?.click()
}

function observeMediaSuccess(): void {
  mediaObserver?.disconnect()
  const root = profileOverview.value?.$el as HTMLElement | undefined
  const identityBand = root?.querySelector<HTMLElement>('.ym-profile-identity-band')
  if (!identityBand) return

  mediaObserver = new MutationObserver(mutations => {
    const hasNewFeedback = mutations.some(mutation =>
      Array.from(mutation.addedNodes).some(node =>
        node instanceof HTMLElement
        && node.matches('[role="status"]')
        && (node.textContent?.includes('تم حفظ الوسائط') || node.textContent?.includes('تم حذف الوسائط')),
      ),
    )

    if (hasNewFeedback) {
      void Promise.allSettled([
        retryPublication(),
        retryFeaturedWorks(),
      ])
    }
  })
  mediaObserver.observe(identityBand, { childList: true, subtree: true })
}

async function openPreview(): Promise<void> {
  clearPublicationErrors()
  clearPreview()
  previewDrawerOpen.value = true
  try { await fetchPreview() } catch { /* Preview drawer owns its error state. */ }
}

async function retryPreview(): Promise<void> {
  try { await fetchPreview() } catch { /* Preview drawer owns its error state. */ }
}

function closePreview(): void {
  previewDrawerOpen.value = false
  clearPreview()
}

function openConfirmation(action: 'publish' | 'hide'): void {
  clearPublicationErrors()
  confirmationAction.value = action
  confirmationRepublish.value = action === 'publish'
    && publicationState.value?.publication.status === 'hidden'
  confirmationOpen.value = true
}

async function confirmPublicationAction(): Promise<void> {
  const expectedUpdatedAt = publicationState.value?.expected_updated_at
  if (!expectedUpdatedAt || publicationSaving.value) return

  const wasRepublish = confirmationRepublish.value
  const succeeded = confirmationAction.value === 'publish'
    ? await publishProfile(expectedUpdatedAt)
    : await hideProfile(expectedUpdatedAt)

  if (!succeeded) return

  await Promise.allSettled([
    fetchFeaturedWorks(),
  ])

  confirmationOpen.value = false

  if (confirmationAction.value === 'hide') {
    showPublicationSuccess('تم إخفاء ملفك بنجاح، ولم تُحذف بياناتك.')
  } else if (wasRepublish) {
    showPublicationSuccess('تمت إعادة نشر ملفك بنجاح.')
  } else {
    showPublicationSuccess('تم نشر حالة ملفك بنجاح.')
  }
}

onBeforeUnmount(() => {
  mediaObserver?.disconnect()
  disposeFeaturedWorksCoverUrls()
  disposeOrganizationLogoUrl()
  if (successTimer) window.clearTimeout(successTimer)
  if (publicationSuccessTimer) window.clearTimeout(publicationSuccessTimer)
})

onMounted(async () => {
  if (!authStore.isInitialized) {
    await authStore.hydrateAuth()
  }

  if (authStore.hasRole('designer')) {
    await loadProfile()
  }
})
</script>

<template>
  <main class="min-h-screen px-4 pb-8 pt-7 text-[var(--ym-d-text)] sm:px-6 sm:pb-10 sm:pt-10 lg:px-8" dir="rtl">
    <div class="mx-auto max-w-7xl">
      <div
        v-if="successMessage"
        class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-bold text-emerald-800"
        role="status"
      >
        {{ successMessage }}
      </div>

      <section v-if="loading" aria-label="جارٍ تحميل ملف المصمم" aria-busy="true">
        <div class="grid animate-pulse gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
          <div class="rounded-3xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-8">
            <div class="h-5 w-24 rounded-full bg-neutral-200" />
            <div class="mt-5 h-9 w-56 rounded-lg bg-neutral-200" />
            <div class="mt-3 h-5 w-40 rounded-lg bg-neutral-100" />
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <div class="h-28 rounded-2xl bg-neutral-100" />
              <div class="h-28 rounded-2xl bg-neutral-100" />
            </div>
          </div>
          <div class="h-72 rounded-3xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-muted)]" />
        </div>
      </section>

      <section
        v-else-if="error && !profileState"
        class="rounded-3xl border border-[var(--ym-d-red-border)] bg-[var(--ym-d-surface)] p-8 text-center shadow-[var(--ym-d-shadow-sm)]"
        role="alert"
      >
        <h2 class="text-xl font-extrabold text-[#151515]">تعذر تحميل ملفك</h2>
        <p class="mx-auto mt-3 max-w-lg text-neutral-600">{{ error }}</p>
        <button
          type="button"
          class="mt-6 min-h-12 rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          @click="loadProfile"
        >
          إعادة المحاولة
        </button>
      </section>

      <DesignerProfileOverview
        v-else-if="synchronizedProfile"
        ref="profileOverview"
        :profile="synchronizedProfile"
        :username="profileState.username"
        :completion="profileState.basic_completion"
        @edit="openProfileEditor"
      />

      <DesignerProfilePublicationPanel
        v-if="profileState?.profile"
        :state="publicationState"
        :loading="publicationLoading"
        :error="publicationError"
        :success-message="publicationSuccessMessage"
        @retry="retryPublication"
        @preview="openPreview"
        @publish="openConfirmation('publish')"
        @hide="openConfirmation('hide')"
        @edit-basic="openProfileEditor"
        @edit-avatar="openAvatarEditor"
        @edit-professional="openProfessionalEditor"
      />

      <DesignerProfileProfessionalOverview
        v-if="profileState?.profile"
        :state="professionalState"
        :loading="professionalLoading"
        :error="professionalError"
        @edit="openProfessionalEditor"
        @retry="fetchProfessional"
      />

      <section
        v-else-if="profileState"
        class="ym-designer-title-card"
      >
        <div class="grid lg:grid-cols-[minmax(0,1fr)_360px]">
          <div class="p-7 sm:p-10">
            <span class="inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-white">
              الخطوة الأولى
            </span>
            <h2 class="mt-5 text-2xl font-extrabold text-white sm:text-3xl">أنشئ ملفك المهني الأساسي</h2>
            <p class="mt-4 max-w-2xl leading-8 text-white/70">
              أضف اسمك المهني ومسمّاك وتخصصك ونبذة قصيرة. ستبقى البيانات
              مسودة داخل مساحة عملك، ولن تنشر للعامة في هذه المرحلة.
            </p>
            <div class="mt-6 max-w-xl rounded-2xl border border-white/15 bg-white/[0.07] p-4">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-[15px] font-bold text-white">اكتمال البيانات الأساسية</p>
                  <p class="mt-1 text-[15px] text-white/65">0 من 5 عناصر مكتملة</p>
                </div>
                <span class="text-lg font-extrabold text-white">0%</span>
              </div>
              <div
                class="mt-4 h-2 overflow-hidden rounded-full bg-neutral-200"
                role="progressbar"
                aria-label="اكتمال البيانات الأساسية"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
              >
                <div class="h-full w-0 rounded-full bg-[#E21D1D]" />
              </div>
            </div>
            <button
              type="button"
              class="mt-7 min-h-12 rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
              @click="openProfileEditor"
            >
              إنشاء الملف
            </button>
          </div>
          <div class="relative border-t border-[var(--ym-d-red-border)] bg-[var(--ym-d-red-soft)] p-7 text-[var(--ym-d-text)] sm:p-10 lg:border-r lg:border-t-0">
            <span class="absolute inset-y-0 right-0 hidden w-1 bg-[var(--ym-d-red)] lg:block" aria-hidden="true" />
            <p class="text-sm font-bold text-[var(--ym-d-red-strong)]">بيانات أساسية واضحة</p>
            <ul class="mt-6 space-y-4 text-[15px] leading-7 text-[var(--ym-d-muted)]">
              <li>اسم مستخدم فريد ورابط مهني ثابت.</li>
              <li>هوية مهنية مختصرة وقابلة للتحديث.</li>
              <li>مؤشر يوضح ما اكتمل وما يحتاج إلى إضافة.</li>
            </ul>
          </div>
        </div>
      </section>

      <DesignerProfileOrganizationPanel
        v-if="profileState?.profile"
        :state="organizationState"
        :logo-url="organizationLogoUrl"
        :loading="organizationLoading"
        :error="organizationError"
        @edit="openOrganizationEditor"
        @retry="fetchOrganization"
      />

      <DesignerProfileFeaturedWorksPanel
        v-if="profileState?.profile"
        :state="featuredWorksState"
        :loading="featuredWorksLoading"
        :error="featuredWorksError"
        :cover-urls="featuredWorksCoverUrls"
        @edit="openFeaturedWorksEditor"
        @retry="retryFeaturedWorks"
      />
    </div>

    <DesignerProfileSetupDrawer
      :key="drawerSession"
      :open="drawerOpen"
      :profile="synchronizedProfile"
      :username="profileState?.username || null"
      :saving="saving"
      :error="error"
      :validation-errors="validationErrors"
      :check-username="checkUsernameAvailability"
      @close="drawerOpen = false"
      @save="handleSave"
    />
    <DesignerProfileProfessionalDrawer
      :open="professionalDrawerOpen"
      :professional="professionalState?.professional || null"
      :primary-specialty="profileState?.profile?.primary_specialty || null"
      :saving="professionalSaving"
      :error="professionalError"
      :validation-errors="professionalValidationErrors"
      @close="professionalDrawerOpen = false"
      @save="handleProfessionalSave"
    />
    <DesignerProfileOrganizationDrawer
      :open="organizationDrawerOpen"
      :organization="organizationState?.organization || null"
      :saving="organizationSaving"
      :error="organizationError"
      :validation-errors="organizationValidationErrors"
      :logo-url="organizationLogoUrl"
      :notice="organizationNotice"
      @close="organizationDrawerOpen = false"
      @save="handleOrganizationSave"
      @request-delete="openOrganizationDeleteDialog"
    />
    <DesignerProfileOrganizationDeleteDialog
      :open="organizationDeleteDialogOpen"
      :saving="organizationSaving"
      :error="organizationError"
      @close="organizationDeleteDialogOpen = false"
      @confirm="handleOrganizationDelete"
    />
    <DesignerProfileFeaturedWorksDrawer
      :open="featuredWorksDrawerOpen"
      :state="featuredWorksState"
      :saving="featuredWorksSaving"
      :error="featuredWorksError"
      :validation-errors="featuredWorksValidationErrors"
      :cover-urls="featuredWorksCoverUrls"
      @close="featuredWorksDrawerOpen = false"
      @save="handleFeaturedWorksSave"
    />
    <DesignerProfilePreviewDrawer
      :open="previewDrawerOpen"
      :preview="previewState"
      :loading="previewLoading"
      :error="previewError"
      @close="closePreview"
      @retry="retryPreview"
    />
    <DesignerProfilePublicationConfirmDialog
      :open="confirmationOpen"
      :action="confirmationAction"
      :republish="confirmationRepublish"
      :saving="publicationSaving"
      :error="publicationError"
      @close="confirmationOpen = false"
      @confirm="confirmPublicationAction"
    />
  </main>
</template>
