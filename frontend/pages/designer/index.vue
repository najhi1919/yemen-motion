<script setup lang="ts">
import DesignerProfileOverview from '~/components/designer/profile/DesignerProfileOverview.vue'
import DesignerProfileProfessionalDrawer from '~/components/designer/profile/DesignerProfileProfessionalDrawer.vue'
import DesignerProfileProfessionalOverview from '~/components/designer/profile/DesignerProfileProfessionalOverview.vue'
import DesignerProfileSetupDrawer from '~/components/designer/profile/DesignerProfileSetupDrawer.vue'
import type { DesignerProfilePayload } from '~/types/designer-profile'
import type { DesignerProfileProfessionalPayload } from '~/types/designer-profile-professional'

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

const drawerOpen = ref(false)
const drawerSession = ref(0)
const successMessage = ref<string | null>(null)
const professionalDrawerOpen = ref(false)
const authStore = useAuthStore()

function openProfileEditor(): void {
  drawerSession.value += 1
  drawerOpen.value = true
}

async function loadProfile(): Promise<void> {
  try {
    const loaded = await fetchProfile()
    if (loaded.profile) {
      try { await fetchProfessional() } catch { /* Professional failure remains isolated. */ }
    }
  } catch {
    // The composable exposes the reader-facing error state.
  }
}

async function handleSave(payload: DesignerProfilePayload): Promise<void> {
  try {
    const saved = await saveProfile(payload)
    if (saved.profile) {
      try { await fetchProfessional() } catch { /* Basic profile remains usable. */ }
    }
    drawerOpen.value = false
    successMessage.value = 'تم حفظ بيانات ملفك بنجاح.'
    window.setTimeout(() => {
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
    professionalDrawerOpen.value = false
    successMessage.value = 'تم حفظ بياناتك المهنية بنجاح.'
    window.setTimeout(() => { successMessage.value = null }, 3200)
  } catch {
    // Keep the professional drawer open with the safe API error.
  }
}

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
        v-else-if="profileState?.profile"
        :profile="profileState.profile"
        :username="profileState.username"
        :completion="profileState.basic_completion"
        @edit="openProfileEditor"
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
    </div>

    <DesignerProfileSetupDrawer
      :key="drawerSession"
      :open="drawerOpen"
      :profile="profileState?.profile || null"
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
  </main>
</template>
