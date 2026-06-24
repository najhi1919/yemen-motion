<template>
  <main class="ym-auth-page" @mousemove="handleMouseMove">
    <ClientOnly v-if="showEnhancements">
      <LazyParticleBackground :mouse-x="mouse.x" :mouse-y="mouse.y" :state="motionState" />
      <LazyAnimatedLights :mouse-x="mouse.x" :mouse-y="mouse.y" :state="motionState" />
    </ClientOnly>
    <ClientOnly v-if="showCreativeElements">
      <LazyFloatingCreativeElements :mouse-x="mouse.x" :mouse-y="mouse.y" />
    </ClientOnly>

    <div ref="pageRoot" class="ym-auth-page__content">
      <div class="ym-auth-stack">
        <div class="ym-auth-brand">
          <MotionLogo
            :state="motionState"
            :mouse-x="mouse.x"
            :mouse-y="mouse.y"
            @hover="setLogoHover"
          />
          <MotionName :state="motionState" :mouse-x="mouse.x" :mouse-y="mouse.y" />
        </div>

        <GlassLoginCard :state="motionState">
          <form class="ym-auth-form" aria-describedby="register-status" @submit.prevent="submitRegister">
            <div>
              <h1 id="register-title" class="ym-auth-form__title">إنشاء حساب</h1>
              <p class="ym-auth-form__subtitle">انضم إلى منصة Yemen Motion الإبداعية</p>
            </div>

            <p v-if="message" id="register-status" class="ym-auth-alert" :class="messageClass" role="alert" aria-live="polite">
              {{ message }}
            </p>

            <div class="ym-auth-field">
              <label for="name">الاسم</label>
              <input
                id="name"
                v-model.trim="name"
                class="ym-auth-input"
                name="name"
                type="text"
                autocomplete="name"
                required
                aria-label="الاسم"
                :aria-invalid="Boolean(errors.name)"
                :aria-describedby="errors.name ? 'name-error' : undefined"
                placeholder="الاسم الكامل"
              >
              <span v-if="errors.name" id="name-error" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.name }}</span>
            </div>

            <div class="ym-auth-field">
              <label for="email">البريد الإلكتروني</label>
              <input
                id="email"
                v-model.trim="email"
                class="ym-auth-input"
                name="email"
                type="email"
                autocomplete="email"
                inputmode="email"
                required
                aria-label="البريد الإلكتروني"
                :aria-invalid="Boolean(errors.email)"
                :aria-describedby="errors.email ? 'email-error' : undefined"
                placeholder="name@example.com"
              >
              <span v-if="errors.email" id="email-error" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.email }}</span>
            </div>

            <div class="ym-auth-field">
              <label for="password">كلمة المرور</label>
              <div class="ym-auth-input-wrapper">
                <input
                  id="password"
                  v-model="password"
                  class="ym-auth-input"
                  name="password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                  aria-label="كلمة المرور"
                  :aria-invalid="Boolean(errors.password)"
                  :aria-describedby="errors.password ? 'password-error' : undefined"
                  placeholder="8 أحرف على الأقل"
                >
                <button type="button" class="ym-password-toggle" @click="togglePasswordVisibility" :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'">
                  <svg viewBox="0 0 24 24" width="1.2rem" height="1.2rem" fill="currentColor" aria-hidden="true"><path d="M12 4.5c-7 0-11 7.5-11 7.5s4 7.5 11 7.5 11-7.5 11-7.5-4-7.5-11-7.5zm0 13c-3.04 0-5.5-2.46-5.5-5.5S8.96 6.5 12 6.5s5.5 2.46 5.5 5.5-2.46 5.5-5.5 5.5zm0-9a3.5 3.5 0 100 7 3.5 3.5 0 000-7z"/></svg>
                </button>
              </div>
              <span v-if="errors.password" id="password-error" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.password }}</span>
            </div>

            <div class="ym-auth-field">
              <label for="password_confirmation">تأكيد كلمة المرور</label>
              <input
                id="password_confirmation"
                v-model="passwordConfirmation"
                class="ym-auth-input"
                name="password_confirmation"
                :type="showPassword ? 'text' : 'password'"
                autocomplete="new-password"
                required
                aria-label="تأكيد كلمة المرور"
                :aria-invalid="Boolean(errors.password_confirmation)"
                :aria-describedby="errors.password_confirmation ? 'confirm-error' : undefined"
                placeholder="أعد إدخال كلمة المرور"
              >
              <span v-if="errors.password_confirmation" id="confirm-error" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.password_confirmation }}</span>
            </div>

            <div class="ym-auth-field">
              <label>نوع الحساب</label>
              <div class="ym-auth-role-group">
                <label class="ym-auth-role-option" :class="{ 'ym-auth-role-option--selected': role === 'client' }">
                  <input v-model="role" type="radio" name="role" value="client" class="ym-auth-role-input">
                  <span>عميل</span>
                </label>
                <label class="ym-auth-role-option" :class="{ 'ym-auth-role-option--selected': role === 'designer' }">
                  <input v-model="role" type="radio" name="role" value="designer" class="ym-auth-role-input">
                  <span>مصمم</span>
                </label>
              </div>
              <span v-if="errors.role" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.role }}</span>
            </div>

            <div class="ym-auth-links">
              <NuxtLink to="/auth/login" class="ym-auth-link">لديك حساب بالفعل؟ تسجيل الدخول</NuxtLink>
            </div>

            <button ref="submitButton" class="ym-auth-submit" type="submit" :disabled="isLoading" aria-label="إنشاء الحساب">
              <span v-if="isLoading">جاري إنشاء الحساب...</span>
              <span v-else>إنشاء حساب</span>
            </button>
          </form>
        </GlassLoginCard>
      </div>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useLoginAnimations } from '~/composables/useLoginAnimations'

definePageMeta({ layout: false })

useHead({
  title: 'إنشاء حساب | Yemen Motion',
  meta: [
    { name: 'description', content: 'إنشاء حساب جديد على منصة Yemen Motion للإنتاج الإبداعي والموشن جرافيك.' }
  ]
})

type MotionState = 'idle' | 'hover' | 'success' | 'error' | 'loading'

const router = useRouter()
const { successAnimation, errorShake, buttonRipple, pageTransition } = useLoginAnimations()
const pageRoot = ref<HTMLElement | null>(null)
const submitButton = ref<HTMLElement | null>(null)
const name = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const role = ref<'client' | 'designer'>('client')
const isLoading = ref(false)
const showPassword = ref(false)
const motionState = ref<MotionState>('idle')
const showEnhancements = ref(false)
const showCreativeElements = ref(false)
const message = ref('')
const errors = reactive<{ name?: string; email?: string; password?: string; password_confirmation?: string; role?: string }>({})
const mouse = reactive({ x: 0, y: 0 })

const messageClass = computed(() => motionState.value === 'success' ? 'ym-auth-alert--success' : 'ym-auth-alert--error')

function handleMouseMove(event: MouseEvent) {
  const target = event.currentTarget as HTMLElement
  const rect = target.getBoundingClientRect()
  mouse.x = ((event.clientX - rect.left) / rect.width - 0.5) * 2
  mouse.y = ((event.clientY - rect.top) / rect.height - 0.5) * 2
  showCreativeElements.value = true
  showEnhancements.value = true
}

function setLogoHover(value: boolean) {
  if (isLoading.value || motionState.value === 'success' || motionState.value === 'error') return
  motionState.value = value ? 'hover' : 'idle'
}

function togglePasswordVisibility() {
  showPassword.value = !showPassword.value
}

function validate() {
  errors.name = ''
  errors.email = ''
  errors.password = ''
  errors.password_confirmation = ''
  errors.role = ''
  if (!name.value) errors.name = 'الاسم مطلوب.'
  if (!email.value) errors.email = 'البريد الإلكتروني مطلوب.'
  else if (!/^\S+@\S+\.\S+$/.test(email.value)) errors.email = 'صيغة البريد الإلكتروني غير صحيحة.'
  if (!password.value) errors.password = 'كلمة المرور مطلوبة.'
  else if (password.value.length < 8) errors.password = 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.'
  if (!passwordConfirmation.value) errors.password_confirmation = 'تأكيد كلمة المرور مطلوب.'
  else if (password.value !== passwordConfirmation.value) errors.password_confirmation = 'كلمتا المرور غير متطابقتين.'
  return !errors.name && !errors.email && !errors.password && !errors.password_confirmation
}

async function submitRegister() {
  message.value = ''
  buttonRipple(submitButton.value)

  if (!validate()) {
    motionState.value = 'error'
    message.value = 'يرجى تصحيح الحقول المطلوبة.'
    errorShake(pageRoot.value)
    window.setTimeout(() => { motionState.value = 'idle' }, 900)
    return
  }

  isLoading.value = true
  motionState.value = 'loading'

  try {
    const authStore = useAuthStore()
    const response = await authStore.register({
      name: name.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
      role: role.value
    })

    isLoading.value = false
    motionState.value = 'success'
    message.value = response?.message || 'تم إنشاء الحساب بنجاح. يتم تجهيز لوحة التحكم...'
    successAnimation([pageRoot.value, submitButton.value])

    const routeMap: Record<string, string> = {
      designer: '/designer',
      client: '/'
    }
    const redirectPath = routeMap[authStore.role || ''] || '/'
    await pageTransition(pageRoot.value)
    await router.push(redirectPath)
  } catch (error) {
    isLoading.value = false
    motionState.value = 'error'

    const err = error as any
    const errorData = err?.data || err?.response?._data
    if (errorData?.errors) {
      if (errorData.errors.name) {
        errors.name = Array.isArray(errorData.errors.name) ? errorData.errors.name[0] : errorData.errors.name
      }
      if (errorData.errors.email) {
        errors.email = Array.isArray(errorData.errors.email) ? errorData.errors.email[0] : errorData.errors.email
      }
      if (errorData.errors.password) {
        errors.password = Array.isArray(errorData.errors.password) ? errorData.errors.password[0] : errorData.errors.password
      }
      if (errorData.errors.password_confirmation) {
        errors.password_confirmation = Array.isArray(errorData.errors.password_confirmation) ? errorData.errors.password_confirmation[0] : errorData.errors.password_confirmation
      }
      if (errorData.errors.role) {
        errors.role = Array.isArray(errorData.errors.role) ? errorData.errors.role[0] : errorData.errors.role
      }
    }
    message.value = errorData?.message || err?.message || 'حدث خطأ أثناء إنشاء الحساب.'
    errorShake(pageRoot.value)
    window.setTimeout(() => { motionState.value = 'idle' }, 900)
  }
}

onMounted(() => {
  window.setTimeout(() => {
    showCreativeElements.value = true
    showEnhancements.value = true
  }, 8000)
})
</script>

<style scoped>
.ym-auth-role-group {
  display: flex;
  gap: 0.75rem;
}

.ym-auth-role-option {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.625rem 1rem;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 0.5rem;
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.7);
}

.ym-auth-role-option:hover {
  border-color: rgba(255, 255, 255, 0.3);
  color: rgba(255, 255, 255, 0.9);
}

.ym-auth-role-option--selected {
  border-color: rgba(99, 102, 241, 0.6);
  background: rgba(99, 102, 241, 0.1);
  color: white;
}

.ym-auth-role-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}
</style>
