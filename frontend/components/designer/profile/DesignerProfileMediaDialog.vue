<script setup lang="ts">
import type { DesignerProfile } from '~/types/designer-profile'

const props = defineProps<{
  open: boolean
  mode: 'avatar' | 'cover'
  profile: DesignerProfile
  busy: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  upload: [file: File]
  delete: []
  saveFocal: [x: number, y: number]
}>()

const dialog = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const selectedFile = ref<File | null>(null)
const previewUrl = ref<string | null>(null)
const localError = ref<string | null>(null)
const focalX = ref(50)
const focalY = ref(50)
let returnFocus: HTMLElement | null = null

const title = computed(() =>
  props.mode === 'avatar' ? 'الصورة الشخصية' : 'غلاف المصمم',
)

const currentUrl = computed(() =>
  props.mode === 'avatar'
    ? props.profile.identity_media.avatar_url
    : props.profile.identity_media.cover_url,
)

const displayedUrl = computed(() => previewUrl.value || currentUrl.value)
const hasCurrentMedia = computed(() => Boolean(currentUrl.value))
const coverPosition = computed(() => `${focalX.value}% ${focalY.value}%`)

const revokePreview = () => {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

const resetState = () => {
  revokePreview()
  selectedFile.value = null
  localError.value = null
  focalX.value = props.profile.identity_media.cover_focal_point.x
  focalY.value = props.profile.identity_media.cover_focal_point.y
}

const requestClose = () => {
  if (!props.busy) {
    emit('close')
  }
}

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    requestClose()
  }
}

const onFileChange = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]

  if (!file) {
    return
  }

  const acceptedTypes = ['image/jpeg', 'image/png', 'image/webp']
  const maxBytes = props.mode === 'avatar' ? 4 * 1024 * 1024 : 8 * 1024 * 1024

  if (!acceptedTypes.includes(file.type)) {
    localError.value = 'اختر صورة بصيغة JPG أوPNG أوWebP.'
    input.value = ''
    return
  }

  if (file.size > maxBytes) {
    localError.value = props.mode === 'avatar'
      ? 'يجب ألا يتجاوز حجم الصورة الشخصية 4MB.'
      : 'يجب ألا يتجاوز حجم الغلاف 8MB.'
    input.value = ''
    return
  }

  revokePreview()
  selectedFile.value = file
  previewUrl.value = URL.createObjectURL(file)
  localError.value = null
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      returnFocus = document.activeElement as HTMLElement | null
      resetState()
      document.body.style.overflow = 'hidden'
      await nextTick()
      closeButton.value?.focus()
      document.addEventListener('keydown', onKeydown)
      return
    }

    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
    resetState()
    await nextTick()
    returnFocus?.focus()
  },
)

onBeforeUnmount(() => {
  revokePreview()
  document.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-50 bg-black/55 sm:flex sm:items-center sm:justify-center sm:p-6"
      @mousedown.self="requestClose"
    >
      <section
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`designer-media-title-${mode}`"
        class="flex h-full w-full flex-col overflow-hidden bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-2xl sm:rounded-3xl"
      >
        <header class="flex shrink-0 items-center justify-between border-b border-neutral-200 px-5 py-4 sm:px-6">
          <div>
            <h2
              :id="`designer-media-title-${mode}`"
              class="text-xl font-bold text-neutral-950"
            >
              {{ title }}
            </h2>
            <p class="mt-1 text-[15px] text-neutral-600">
              راجع المعاينة ثم احفظ التغيير صراحة.
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-2xl text-neutral-700 transition hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E21D1D] motion-reduce:transition-none"
            aria-label="إغلاق نافذة الوسائط"
            :disabled="busy"
            @click="requestClose"
          >
            ×
          </button>
        </header>

        <div class="flex-1 overflow-y-auto px-5 py-6 sm:px-6">
          <div
            v-if="mode === 'avatar'"
            class="mx-auto h-48 w-48 overflow-hidden rounded-full border border-neutral-200 bg-neutral-100"
          >
            <img
              v-if="displayedUrl"
              :src="displayedUrl"
              :alt="`معاينة الصورة الشخصية لـ${profile.display_name}`"
              class="h-full w-full object-cover"
            >
            <img
              v-else
              src="/logo.svg"
              alt=""
              class="m-auto mt-14 h-20 w-20 opacity-20"
            >
          </div>

          <div
            v-else
            class="aspect-[16/6] overflow-hidden rounded-2xl border border-neutral-200 bg-neutral-100"
          >
            <img
              v-if="displayedUrl"
              :src="displayedUrl"
              alt=""
              class="h-full w-full object-cover"
              :style="{ objectPosition: coverPosition }"
            >
            <img
              v-else
              src="/logo.svg"
              alt=""
              class="m-auto mt-10 h-20 w-20 opacity-20"
            >
          </div>

          <label class="mt-6 block">
            <span class="mb-2 block text-[15px] font-semibold text-neutral-900">
              اختر {{ mode === 'avatar' ? 'صورة شخصية' : 'صورة غلاف' }}
            </span>
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
              class="block min-h-11 w-full rounded-xl border border-neutral-300 bg-white p-2 text-[15px] text-neutral-800 file:ml-3 file:rounded-lg file:border-0 file:bg-neutral-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E21D1D]"
              :disabled="busy"
              @change="onFileChange"
            >
          </label>

          <div v-if="mode === 'cover' && displayedUrl" class="mt-7 space-y-5 rounded-2xl bg-neutral-50 p-4">
            <div>
              <div class="mb-2 flex items-center justify-between gap-4">
                <label for="cover-focal-x" class="text-[15px] font-semibold text-neutral-900">
                  الموضع الأفقي
                </label>
                <span class="text-sm text-neutral-600">{{ focalX }}%</span>
              </div>
              <input
                id="cover-focal-x"
                v-model.number="focalX"
                type="range"
                min="0"
                max="100"
                class="w-full accent-[#E21D1D]"
                :disabled="busy"
              >
            </div>
            <div>
              <div class="mb-2 flex items-center justify-between gap-4">
                <label for="cover-focal-y" class="text-[15px] font-semibold text-neutral-900">
                  الموضع الرأسي
                </label>
                <span class="text-sm text-neutral-600">{{ focalY }}%</span>
              </div>
              <input
                id="cover-focal-y"
                v-model.number="focalY"
                type="range"
                min="0"
                max="100"
                class="w-full accent-[#E21D1D]"
                :disabled="busy"
              >
            </div>
            <button
              v-if="hasCurrentMedia"
              type="button"
              class="inline-flex min-h-11 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-semibold text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E21D1D]"
              :disabled="busy"
              @click="emit('saveFocal', focalX, focalY)"
            >
              حفظ موضع الغلاف
            </button>
          </div>

          <p
            v-if="localError || error"
            role="alert"
            class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-[15px] text-[#B42318]"
          >
            {{ localError || error }}
          </p>

          <button
            v-if="hasCurrentMedia"
            type="button"
            class="mt-6 inline-flex min-h-11 items-center justify-center rounded-xl px-3 text-sm font-semibold text-[#B42318] underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#B42318]"
            :disabled="busy"
            @click="emit('delete')"
          >
            حذف {{ mode === 'avatar' ? 'الصورة الشخصية' : 'الغلاف' }}
          </button>
        </div>

        <footer class="flex shrink-0 flex-wrap items-center gap-3 border-t border-neutral-200 bg-white px-5 py-4 sm:px-6">
          <button
            type="button"
            class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white transition hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E21D1D] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none"
            :disabled="busy || !selectedFile"
            @click="selectedFile && emit('upload', selectedFile)"
          >
            {{ busy ? 'جارٍ الحفظ…' : 'رفع وحفظ' }}
          </button>
          <button
            type="button"
            class="inline-flex min-h-12 items-center justify-center rounded-xl border border-neutral-300 bg-white px-5 text-sm font-semibold text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#E21D1D]"
            :disabled="busy"
            @click="requestClose"
          >
            إلغاء
          </button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>
