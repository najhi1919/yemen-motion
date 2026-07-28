<script setup lang="ts">
import DesignerProfileOverview from '~/components/designer/profile/DesignerProfileOverview.vue'
import DesignerProfileSetupDrawer from '~/components/designer/profile/DesignerProfileSetupDrawer.vue'
import type { DesignerProfilePayload } from '~/types/designer-profile'

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

const drawerOpen = ref(false)
const drawerSession = ref(0)
const successMessage = ref<string | null>(null)
const authStore = useAuthStore()

function openProfileEditor(): void {
  drawerSession.value += 1
  drawerOpen.value = true
}

async function loadProfile(): Promise<void> {
  try {
    await fetchProfile()
  } catch {
    // The composable exposes the reader-facing error state.
  }
}

async function handleSave(payload: DesignerProfilePayload): Promise<void> {
  try {
    await saveProfile(payload)
    drawerOpen.value = false
    successMessage.value = 'تم حفظ بيانات ملفك بنجاح.'
    window.setTimeout(() => {
      successMessage.value = null
    }, 3200)
  } catch {
    // Keep the drawer and form values open for correction.
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
  <main class="min-h-screen bg-[#FCFCFC] px-4 pb-8 pt-6 text-[#151515] sm:px-6 sm:pb-10 sm:pt-8 lg:px-8" dir="rtl">
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
          <div class="rounded-3xl border border-neutral-200 bg-white p-8">
            <div class="h-5 w-24 rounded-full bg-neutral-200" />
            <div class="mt-5 h-9 w-56 rounded-lg bg-neutral-200" />
            <div class="mt-3 h-5 w-40 rounded-lg bg-neutral-100" />
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
              <div class="h-28 rounded-2xl bg-neutral-100" />
              <div class="h-28 rounded-2xl bg-neutral-100" />
            </div>
          </div>
          <div class="h-72 rounded-3xl bg-[#111111]" />
        </div>
      </section>

      <section
        v-else-if="error && !profileState"
        class="rounded-3xl border border-red-200 bg-white p-8 text-center shadow-sm"
        role="alert"
      >
        <h2 class="text-xl font-extrabold text-[#151515]">تعذر تحميل ملفك</h2>
        <p class="mx-auto mt-3 max-w-lg text-neutral-600">{{ error }}</p>
        <button
          type="button"
          class="mt-6 min-h-12 rounded-xl bg-[#E21D1D] px-6 font-bold text-white hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
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

      <section
        v-else-if="profileState"
        class="overflow-hidden rounded-3xl border border-neutral-200 bg-white shadow-sm"
      >
        <div class="grid lg:grid-cols-[minmax(0,1fr)_360px]">
          <div class="p-7 sm:p-10">
            <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-bold text-[#C91414]">
              الخطوة الأولى
            </span>
            <h2 class="mt-5 text-2xl font-extrabold sm:text-3xl">أنشئ ملفك المهني الأساسي</h2>
            <p class="mt-4 max-w-2xl leading-8 text-neutral-600">
              أضف اسمك المهني ومسمّاك وتخصصك ونبذة قصيرة. ستبقى البيانات
              مسودة داخل مساحة عملك، ولن تنشر للعامة في هذه المرحلة.
            </p>
            <div class="mt-6 max-w-xl rounded-2xl border border-neutral-200 bg-[#FCFCFC] p-4">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-[15px] font-bold text-neutral-800">اكتمال البيانات الأساسية</p>
                  <p class="mt-1 text-[15px] text-neutral-600">0 من 5 عناصر مكتملة</p>
                </div>
                <span class="text-lg font-extrabold text-[#C91414]">0%</span>
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
              class="mt-7 min-h-12 rounded-xl bg-[#E21D1D] px-6 font-bold text-white hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
              @click="openProfileEditor"
            >
              إنشاء الملف
            </button>
          </div>
          <div class="bg-[#111111] p-7 text-white sm:p-10">
            <p class="text-sm font-bold text-red-300">بيانات أساسية واضحة</p>
            <ul class="mt-6 space-y-4 text-[15px] leading-7 text-neutral-200">
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
  </main>
</template>
