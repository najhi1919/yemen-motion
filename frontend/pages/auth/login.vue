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
          <form class="ym-auth-form" aria-describedby="login-status" @submit.prevent="submitLogin">
            <div>
              <h1 id="login-title" class="ym-auth-form__title">تسجيل الدخول</h1>
              <p class="ym-auth-form__subtitle">ادخل إلى مساحة Yemen Motion الإبداعية</p>
            </div>

            <p v-if="message" id="login-status" class="ym-auth-alert" :class="messageClass" role="alert" aria-live="polite">
              {{ message }}
            </p>

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
                  autocomplete="current-password"
                  required
                  aria-label="كلمة المرور"
                  :aria-invalid="Boolean(errors.password)"
                  :aria-describedby="errors.password ? 'password-error' : undefined"
                  placeholder="••••••••"
                >
                <button type="button" class="ym-password-toggle" @click="togglePasswordVisibility" :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'">
                  <svg viewBox="0 0 24 24" width="1.2rem" height="1.2rem" fill="currentColor" aria-hidden="true"><path d="M12 4.5c-7 0-11 7.5-11 7.5s4 7.5 11 7.5 11-7.5 11-7.5-4-7.5-11-7.5zm0 13c-3.04 0-5.5-2.46-5.5-5.5S8.96 6.5 12 6.5s5.5 2.46 5.5 5.5-2.46 5.5-5.5 5.5zm0-9a3.5 3.5 0 100 7 3.5 3.5 0 000-7z"/></svg>
                </button>
              </div>
              <span v-if="errors.password" id="password-error" class="ym-auth-alert ym-auth-alert--error" role="alert">{{ errors.password }}</span>
            </div>

            <div class="ym-auth-links">
              <NuxtLink to="/auth/forgot-password" class="ym-auth-link">نسيت كلمة المرور؟</NuxtLink>
              <NuxtLink to="/auth/register" class="ym-auth-link">إنشاء حساب</NuxtLink>
            </div>

            <button ref="submitButton" class="ym-auth-submit" type="submit" :disabled="isLoading" aria-label="تسجيل الدخول إلى المنصة">
              <span v-if="isLoading">جاري الدخول...</span>
              <span v-else>دخول</span>
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
  title: 'تسجيل الدخول | Yemen Motion',
  meta: [
    { name: 'description', content: 'تسجيل الدخول إلى منصة Yemen Motion للإنتاج الإبداعي والموشن جرافيك.' }
  ]
})

type MotionState = 'idle' | 'hover' | 'success' | 'error' | 'loading'

const router = useRouter()
const { successAnimation, errorShake, buttonRipple, pageTransition } = useLoginAnimations()
const pageRoot = ref<HTMLElement | null>(null)
const submitButton = ref<HTMLElement | null>(null)
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const showPassword = ref(false)
const motionState = ref<MotionState>('idle')
const showEnhancements = ref(false)
const showCreativeElements = ref(false)
const message = ref('')
const errors = reactive<{ email?: string; password?: string }>({})
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
  errors.email = ''
  errors.password = ''
  if (!email.value) errors.email = 'البريد الإلكتروني مطلوب.'
  else if (!/^\S+@\S+\.\S+$/.test(email.value)) errors.email = 'صيغة البريد الإلكتروني غير صحيحة.'
  if (!password.value) errors.password = 'كلمة المرور مطلوبة.'
  else if (password.value.length < 6) errors.password = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.'
  return !errors.email && !errors.password
}

async function submitLogin() {
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
    const response = await authStore.login({
      email: email.value,
      password: password.value
    })

    isLoading.value = false
    motionState.value = 'success'
    message.value = response?.message || 'تم تسجيل الدخول بنجاح. يتم تجهيز لوحة التحكم...'
    successAnimation([pageRoot.value, submitButton.value])

    const routeMap: Record<string, string> = {
      admin: '/admin',
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
      if (errorData.errors.email) {
        errors.email = Array.isArray(errorData.errors.email) ? errorData.errors.email[0] : errorData.errors.email
      }
      if (errorData.errors.password) {
        errors.password = Array.isArray(errorData.errors.password) ? errorData.errors.password[0] : errorData.errors.password
      }
    }
    message.value = errorData?.message || err?.message || 'بيانات الدخول غير صحيحة.'
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
