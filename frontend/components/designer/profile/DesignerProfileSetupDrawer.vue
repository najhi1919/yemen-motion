<script setup lang="ts">
import type {
  DesignerAvailability,
  DesignerProfile,
  DesignerProfilePayload,
  UsernameAvailability
} from '~/types/designer-profile'

const props = defineProps<{
  open: boolean
  profile: DesignerProfile | null
  username: string | null
  saving: boolean
  error: string | null
  validationErrors: Record<string, string[]>
  checkUsername: (username: string) => Promise<UsernameAvailability>
}>()

const emit = defineEmits<{
  close: []
  save: [payload: DesignerProfilePayload]
}>()

const usernameField = ref<HTMLInputElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const formScrollContainer = ref<HTMLElement | null>(null)
const showUnsavedWarning = ref(false)
const availability = ref<UsernameAvailability | null>(null)
const availabilityChecking = ref(false)
let availabilityTimer: ReturnType<typeof setTimeout> | null = null
let previousBodyOverflow = ''

const form = reactive<DesignerProfilePayload>({
  username: null,
  display_name: '',
  professional_title: null,
  primary_specialty: null,
  bio: null,
  availability: 'unavailable'
})

const initialSnapshot = ref('')
const normalizedUsername = computed(() => (form.username || '').trim().toLowerCase())
const bioLength = computed(() => form.bio?.length || 0)
const isDirty = computed(() => JSON.stringify(form) !== initialSnapshot.value)
const canSave = computed(() => {
  if (props.saving || form.display_name.trim().length < 2 || bioLength.value > 800) {
    return false
  }

  if (props.username !== null) {
    return true
  }

  return normalizedUsername.value.length >= 4
    && !availabilityChecking.value
    && availability.value?.available === true
})

function fillForm(): void {
  form.username = props.username
  form.display_name = props.profile?.display_name || ''
  form.professional_title = props.profile?.professional_title || null
  form.primary_specialty = props.profile?.primary_specialty || null
  form.bio = props.profile?.bio || null
  form.availability = props.profile?.availability || 'unavailable'
  availability.value = props.username
    ? { available: true, normalized: props.username, reason: null }
    : null
  showUnsavedWarning.value = false
  initialSnapshot.value = JSON.stringify(form)
}

function fieldError(field: string): string | null {
  return props.validationErrors[field]?.[0] || null
}

function normalizeEditableUsername(event: Event): void {
  if (props.username !== null) {
    return
  }

  const input = event.target as HTMLInputElement
  const normalized = input.value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_-]/g, '')

  input.value = normalized
  form.username = normalized
}

function requestClose(): void {
  if (isDirty.value) {
    showUnsavedWarning.value = true
    return
  }

  emit('close')
}

function discardAndClose(): void {
  fillForm()
  emit('close')
}

function submit(): void {
  if (!canSave.value) {
    return
  }

  emit('save', {
    username: props.username === null ? normalizedUsername.value : undefined,
    display_name: form.display_name.trim(),
    professional_title: form.professional_title?.trim() || null,
    primary_specialty: form.primary_specialty?.trim() || null,
    bio: form.bio?.trim() || null,
    availability: form.availability as DesignerAvailability
  })
}

async function runAvailabilityCheck(value: string): Promise<void> {
  availabilityChecking.value = true

  try {
    const result = await props.checkUsername(value)

    if (normalizedUsername.value === value) {
      availability.value = result
    }
  } catch {
    if (normalizedUsername.value === value) {
      availability.value = { available: false, normalized: value, reason: 'invalid' }
    }
  } finally {
    if (normalizedUsername.value === value) {
      availabilityChecking.value = false
    }
  }
}

function onEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.open) {
    requestClose()
  }
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      fillForm()

      if (import.meta.client) {
        previousBodyOverflow = document.body.style.overflow
        document.body.style.overflow = 'hidden'
      }

      await nextTick()
      if (formScrollContainer.value) {
        formScrollContainer.value.scrollTop = 0
      }

      const initialFocus = props.username === null
        ? usernameField.value
        : closeButton.value
      initialFocus?.focus({ preventScroll: true })
      return
    }

    if (import.meta.client) {
      document.body.style.overflow = previousBodyOverflow
    }
  },
  { immediate: true }
)

watch(
  () => form.username,
  (value) => {
    if (props.username !== null) {
      return
    }

    if (availabilityTimer) {
      clearTimeout(availabilityTimer)
    }

    availability.value = null
    const normalized = (value || '').trim().toLowerCase()

    if (normalized.length < 4) {
      availabilityChecking.value = false
      return
    }

    availabilityChecking.value = true
    availabilityTimer = setTimeout(() => {
      void runAvailabilityCheck(normalized)
    }, 350)
  }
)

onMounted(() => window.addEventListener('keydown', onEscape))
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onEscape)

  if (availabilityTimer) {
    clearTimeout(availabilityTimer)
  }

  if (import.meta.client) {
    document.body.style.overflow = previousBodyOverflow
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-if="open"
        class="fixed inset-0 z-50 bg-black/45"
        aria-hidden="true"
        @click="requestClose"
      />
    </Transition>

    <Transition
      enter-active-class="transition-transform duration-300 ease-out"
      enter-from-class="translate-x-full"
      leave-active-class="transition-transform duration-200 ease-in"
      leave-to-class="translate-x-full"
    >
      <section
        v-if="open"
        class="fixed inset-y-0 right-0 z-[60] flex h-full w-full flex-col bg-[#FCFCFC] shadow-2xl sm:max-w-[600px]"
        role="dialog"
        aria-modal="true"
        aria-labelledby="designer-profile-drawer-title"
        dir="rtl"
      >
        <header class="shrink-0 border-b border-neutral-200 bg-white px-5 py-5 sm:px-8">
          <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-bold text-[#C91414]">الملف المهني الأساسي</p>
            <h2 id="designer-profile-drawer-title" class="mt-1 text-xl font-extrabold text-[#151515]">
              {{ profile ? 'تعديل الملف المهني' : 'إنشاء ملف المصمم' }}
            </h2>
            <p v-if="isDirty" class="mt-2 text-[15px] font-semibold text-amber-700">
              لديك تغييرات غير محفوظة
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="flex min-h-11 min-w-11 items-center justify-center rounded-xl border border-neutral-200 bg-white text-xl text-neutral-700 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
            aria-label="إغلاق نافذة إعداد الملف"
            @click="requestClose"
          >
            ×
          </button>
          </div>
        </header>

        <div
          ref="formScrollContainer"
          class="min-h-0 flex-1 overflow-y-auto px-5 py-6 sm:px-8"
        >
          <div
            v-if="showUnsavedWarning"
            class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 p-4"
            role="alert"
          >
            <p class="font-bold text-amber-900">لديك تغييرات غير محفوظة.</p>
            <p class="mt-1 text-[15px] text-amber-800">
              يمكنك متابعة التعديل أوإغلاق النافذة وتجاهل التغييرات.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
              <button
                type="button"
                class="min-h-11 rounded-xl bg-amber-900 px-4 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-amber-200"
                @click="discardAndClose"
              >
                تجاهل وإغلاق
              </button>
              <button
                type="button"
                class="min-h-11 rounded-xl border border-amber-300 bg-white px-4 text-sm font-bold text-amber-900 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-amber-200"
                @click="showUnsavedWarning = false"
              >
                متابعة التعديل
              </button>
            </div>
          </div>

          <div
            v-if="error"
            class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-[#B42318]"
            role="alert"
          >
            {{ error }}
          </div>

          <form id="designer-profile-form" class="space-y-6" @submit.prevent="submit">
            <div v-if="username">
              <p class="mb-2 text-sm font-bold text-neutral-800">
                اسم المستخدم
              </p>
              <div class="rounded-xl border border-neutral-300 bg-neutral-100 px-4 py-3">
                <bdi dir="ltr" class="block text-base font-bold text-neutral-800">
                  {{ username.toLowerCase() }}
                </bdi>
                <span class="mt-1 block text-sm text-neutral-600 sm:text-[15px]" dir="ltr">
                  /designers/{{ username.toLowerCase() }}
                </span>
              </div>
              <p class="mt-2 text-sm leading-6 text-neutral-600 sm:text-[15px]">
                لا يمكن تغيير اسم المستخدم من هذه الشاشة. سيكون تغييره من إعدادات الحساب لاحقًا.
              </p>
            </div>

            <div v-else>
              <label for="designer-username" class="mb-2 block text-sm font-bold text-neutral-800">
                اسم المستخدم
              </label>
              <input
                id="designer-username"
                ref="usernameField"
                :value="form.username || ''"
                type="text"
                dir="ltr"
                autocomplete="username"
                :aria-invalid="Boolean(fieldError('username'))"
                class="min-h-12 w-full rounded-xl border border-neutral-300 bg-white px-4 text-base text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
                @input="normalizeEditableUsername"
              >
              <p class="mt-2 text-sm leading-6 text-neutral-600 sm:text-[15px]">
                <template v-if="normalizedUsername.length < 4">
                  استخدم 4 أحرف على الأقل من الحروف اللاتينية والأرقام والفواصل المسموحة.
                </template>
                <template v-else-if="availabilityChecking">
                  جارٍ التحقق من الإتاحة…
                </template>
                <template v-else-if="availability?.available">
                  <span class="font-bold text-emerald-700">الاسم متاح:</span>
                  <span dir="ltr"> /designers/{{ availability.normalized }}</span>
                </template>
                <template v-else-if="availability">
                  <span class="font-bold text-[#B42318]">الاسم غير متاح.</span>
                  <span v-if="availability.normalized" dir="ltr">@{{ availability.normalized }}</span>
                </template>
                <template v-else>سيظهر رابطك بعد التحقق من الاسم.</template>
              </p>
              <p v-if="fieldError('username')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('username') }}
              </p>
            </div>

            <div>
              <label for="designer-display-name" class="mb-2 block text-sm font-bold text-neutral-800">
                الاسم المهني الظاهر
              </label>
              <input
                id="designer-display-name"
                v-model="form.display_name"
                type="text"
                maxlength="120"
                autocomplete="name"
                :aria-invalid="Boolean(fieldError('display_name'))"
                class="min-h-12 w-full rounded-xl border border-neutral-300 bg-white px-4 text-base text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
              >
              <p v-if="fieldError('display_name')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('display_name') }}
              </p>
            </div>

            <div>
              <label for="designer-title" class="mb-2 block text-sm font-bold text-neutral-800">
                المسمى المهني
              </label>
              <input
                id="designer-title"
                v-model="form.professional_title"
                type="text"
                maxlength="160"
                class="min-h-12 w-full rounded-xl border border-neutral-300 bg-white px-4 text-base text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
              >
              <p v-if="fieldError('professional_title')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('professional_title') }}
              </p>
            </div>

            <div>
              <label for="designer-specialty" class="mb-2 block text-sm font-bold text-neutral-800">
                التخصص الرئيسي
              </label>
              <input
                id="designer-specialty"
                v-model="form.primary_specialty"
                type="text"
                maxlength="120"
                class="min-h-12 w-full rounded-xl border border-neutral-300 bg-white px-4 text-base text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
              >
              <p v-if="fieldError('primary_specialty')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('primary_specialty') }}
              </p>
            </div>

            <div>
              <div class="mb-2 flex items-center justify-between gap-3">
                <label for="designer-bio" class="text-sm font-bold text-neutral-800">النبذة المهنية</label>
                <span class="text-sm text-neutral-600 sm:text-[15px]" dir="ltr">{{ bioLength }}/800</span>
              </div>
              <textarea
                id="designer-bio"
                v-model="form.bio"
                rows="6"
                maxlength="800"
                class="w-full resize-y rounded-xl border border-neutral-300 bg-white px-4 py-3 text-base leading-7 text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
              />
              <p v-if="fieldError('bio')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('bio') }}
              </p>
            </div>

            <div>
              <label for="designer-availability" class="mb-2 block text-sm font-bold text-neutral-800">
                حالة التوفر
              </label>
              <select
                id="designer-availability"
                v-model="form.availability"
                class="min-h-12 w-full rounded-xl border border-neutral-300 bg-white px-4 text-base text-neutral-900 outline-none transition focus:border-[#E21D1D] focus:ring-4 focus:ring-red-100"
              >
                <option value="available">متاح للعمل</option>
                <option value="partially_available">متاح جزئيًا</option>
                <option value="unavailable">غير متاح حاليًا</option>
              </select>
              <p v-if="fieldError('availability')" class="mt-2 text-sm font-semibold text-[#B42318]">
                {{ fieldError('availability') }}
              </p>
            </div>
          </form>
        </div>

        <footer class="shrink-0 border-t border-neutral-200 bg-white px-5 py-4 sm:px-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:justify-start">
            <button
              type="submit"
              form="designer-profile-form"
              :disabled="!canSave"
              class="min-h-12 min-w-36 rounded-xl bg-[#E21D1D] px-6 font-bold text-white transition hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-600"
            >
              {{ saving ? 'جارٍ الحفظ…' : 'حفظ البيانات' }}
            </button>
            <button
              type="button"
              class="min-h-12 rounded-xl border border-neutral-300 bg-white px-6 font-bold text-neutral-800 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-neutral-200"
              @click="requestClose"
            >
              إلغاء
            </button>
          </div>
        </footer>
      </section>
    </Transition>
  </Teleport>
</template>
