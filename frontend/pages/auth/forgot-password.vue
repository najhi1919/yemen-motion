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
          <form class="ym-auth-form" aria-describedby="forgot-status" @submit.prevent="submitForgot">
            <div>
              <h1 id="forgot-title" class="ym-auth-form__title">نسيت كلمة المرور</h1>
              <p class="ym-auth-form__subtitle">أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة التعيين</p>
            </div>

            <p v-if="message" id="forgot-status" class="ym-auth-alert" :class="messageClass" role="alert" aria-live="polite">
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

            <div class="ym-auth-links">
              <NuxtLink to="/auth/login" class="ym-auth-link">العودة إلى تسجيل الدخول</NuxtLink>
              <NuxtLink to="/auth/register" class="ym-auth-link">إنشاء حساب</NuxtLink>
            </div>

            <button ref="submitButton" class="ym-auth-submit" type="submit" :disabled="isLoading" aria-label="إرسال رابط استعادة كلمة المرور">
              <span v-if="isLoading">جاري الإرسال...</span>
              <span v-else>إرسال الرابط</span>
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
  title: 'نسيت كلمة المرور | Yemen Motion',
  meta: [
    { name: 'description', content: 'استعادة كلمة المرور على منصة Yemen Motion.' }
  ]
})

type MotionState = 'idle' | 'hover' | 'success' | 'error' | 'loading'

const { successAnimation, errorShake, buttonRipple } = useLoginAnimations()
const pageRoot = ref<HTMLElement | null>(null)
const submitButton = ref<HTMLElement | null>(null)
const email = ref('')
const isLoading = ref(false)
const showPassword = ref(false)
const motionState = ref<MotionState>('idle')
const showEnhancements = ref(false)
const showCreativeElements = ref(false)
const message = ref('')
const errors = reactive<{ email?: string }>({})
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

function validate() {
  errors.email = ''
  if (!email.value) errors.email = 'البريد الإلكتروني مطلوب.'
  else if (!/^\S+@\S+\.\S+$/.test(email.value)) errors.email = 'صيغة البريد الإلكتروني غير صحيحة.'
  return !errors.email
}

async function submitForgot() {
  message.value = ''
  buttonRipple(submitButton.value)

  if (!validate()) {
    motionState.value = 'error'
    message.value = 'يرجى إدخال بريد إلكتروني صحيح.'
    errorShake(pageRoot.value)
    window.setTimeout(() => { motionState.value = 'idle' }, 900)
    return
  }

  isLoading.value = true
  motionState.value = 'loading'

  try {
    const authStore = useAuthStore()
    const response = await authStore.forgotPassword(email.value)

    isLoading.value = false
    motionState.value = 'success'
    message.value = response?.message || 'إذا كان البريد مسجلاً لدينا، فسيتم إرسال رابط استعادة كلمة المرور إلى بريدك الإلكتروني.'
    successAnimation([pageRoot.value, submitButton.value])
  } catch (error) {
    isLoading.value = false
    motionState.value = 'error'

    const err = error as any
    const errorData = err?.data || err?.response?._data
    if (errorData?.errors) {
      if (errorData.errors.email) {
        errors.email = Array.isArray(errorData.errors.email) ? errorData.errors.email[0] : errorData.errors.email
      }
    }
    message.value = errorData?.message || err?.message || 'حدث خطأ أثناء الإرسال. يرجى المحاولة لاحقاً.'
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
