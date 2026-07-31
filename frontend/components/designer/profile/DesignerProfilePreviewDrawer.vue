<script setup lang="ts">
import type { DesignerProfilePreview } from '~/types/designer-profile-publication'

const props = defineProps<{
  open: boolean
  preview: DesignerProfilePreview | null
  loading: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  retry: []
}>()

const drawer = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
const avatarObjectUrl = ref<string | null>(null)
const coverObjectUrl = ref<string | null>(null)
const { loadMedia } = useDesignerProfileMedia()
let returnFocus: HTMLElement | null = null
let previousBodyOverflow = ''

const availabilityLabels = {
  available: 'متاح للعمل',
  partially_available: 'متاح جزئيًا',
  unavailable: 'غير متاح حاليًا',
}

const levelLabels: Record<string, string> = {
  beginner: 'مبتدئ',
  intermediate: 'متوسط',
  advanced: 'متقدم',
  expert: 'خبير',
  basic: 'أساسي',
  conversational: 'محادثة',
  professional: 'مهني',
  native: 'لغة أم',
}

const coverPosition = computed(() => {
  const point = props.preview?.identity.cover_focal_point
  return point ? `${point.x}% ${point.y}%` : '50% 50%'
})

const focusableSelector = [
  'button:not([disabled])',
  '[href]',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

const requestClose = () => emit('close')

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    requestClose()
    return
  }

  if (event.key !== 'Tab' || !drawer.value) return
  const elements = Array.from(drawer.value.querySelectorAll<HTMLElement>(focusableSelector))
  if (!elements.length) return
  const first = elements[0]
  const last = elements[elements.length - 1]

  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}

const revokeObjectUrl = (target: Ref<string | null>) => {
  if (target.value) URL.revokeObjectURL(target.value)
  target.value = null
}

const loadProtectedImage = async (source: string | null, target: Ref<string | null>) => {
  revokeObjectUrl(target)
  if (!source || !import.meta.client) return

  try {
    target.value = URL.createObjectURL(await loadMedia(source))
  } catch {
    target.value = null
  }
}

watch(
  () => props.preview?.identity.avatar_url || null,
  source => loadProtectedImage(source, avatarObjectUrl),
)

watch(
  () => props.preview?.identity.cover_url || null,
  source => loadProtectedImage(source, coverObjectUrl),
)

watch(
  () => props.open,
  async open => {
    if (open) {
      returnFocus = document.activeElement as HTMLElement | null
      previousBodyOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      document.addEventListener('keydown', onKeydown)
      await nextTick()
      closeButton.value?.focus()
      return
    }

    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = previousBodyOverflow
    revokeObjectUrl(avatarObjectUrl)
    revokeObjectUrl(coverObjectUrl)
    await nextTick()
    returnFocus?.focus()
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  if (import.meta.client) document.body.style.overflow = previousBodyOverflow
  revokeObjectUrl(avatarObjectUrl)
  revokeObjectUrl(coverObjectUrl)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[70] flex justify-end bg-black/55"
      @pointerdown.self="requestClose"
    >
      <section
        ref="drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="designer-preview-title"
        aria-describedby="designer-preview-description"
        class="isolate flex h-dvh w-full max-w-3xl flex-col overflow-hidden bg-[#FCFCFC] shadow-2xl sm:w-[min(92vw,760px)]"
      >
        <header class="relative z-20 flex shrink-0 items-start justify-between gap-4 border-b-2 border-[#E21D1D] bg-[#111111] px-5 py-4 text-white sm:px-7 sm:py-5">
          <div class="min-w-0">
            <h2 id="designer-preview-title" class="text-xl font-extrabold text-white sm:text-2xl">معاينة الملف كزائر</h2>
            <p id="designer-preview-description" class="mt-1 text-sm leading-6 text-white/65">
              هذه معاينة خاصة ولا تعني أن الصفحة العامة أصبحت متاحة بعد.
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/30 text-2xl text-white transition-colors hover:border-red-300 hover:bg-[#E21D1D] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
            aria-label="إغلاق معاينة الملف"
            @click="requestClose"
          >
            ×
          </button>
        </header>

        <div class="flex-1 overflow-y-auto bg-[#FCFCFC]">
          <div v-if="loading" class="space-y-5 p-5 sm:p-7" aria-busy="true" aria-label="جارٍ تحميل معاينة الملف">
            <div class="h-40 animate-pulse rounded-2xl bg-neutral-200 motion-reduce:animate-none" />
            <div class="mx-auto -mt-16 h-28 w-28 animate-pulse rounded-full bg-neutral-300 motion-reduce:animate-none" />
            <div class="mx-auto h-7 w-52 animate-pulse rounded bg-neutral-200 motion-reduce:animate-none" />
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="h-32 animate-pulse rounded-2xl bg-neutral-100 motion-reduce:animate-none" />
              <div class="h-32 animate-pulse rounded-2xl bg-neutral-100 motion-reduce:animate-none" />
            </div>
          </div>

          <div v-else-if="error" class="p-6 sm:p-8">
            <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 font-semibold text-[#B42318]">
              تعذر تحميل معاينة الملف.
            </p>
            <button
              type="button"
              class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl bg-[#111111] px-5 text-sm font-bold text-white transition-colors hover:bg-[#2A2A2A] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-neutral-300 motion-reduce:transition-none"
              @click="emit('retry')"
            >
              إعادة المحاولة
            </button>
          </div>

          <article v-else-if="preview" class="pb-10">
            <div class="relative h-40 overflow-hidden bg-neutral-200 sm:h-52">
              <img
                v-if="coverObjectUrl"
                :src="coverObjectUrl"
                alt=""
                class="h-full w-full object-cover"
                :style="{ objectPosition: coverPosition }"
              >
              <div v-else class="flex h-full items-center justify-center bg-gradient-to-bl from-neutral-100 to-neutral-200">
                <img src="/logo.svg" alt="" class="h-20 w-20 opacity-10">
              </div>
            </div>

            <div class="px-5 sm:px-8">
              <div class="relative -mt-14 flex h-28 w-28 items-center justify-center overflow-hidden rounded-full border-4 border-white bg-neutral-100 shadow-lg sm:-mt-16 sm:h-32 sm:w-32">
                <img v-if="avatarObjectUrl" :src="avatarObjectUrl" :alt="`الصورة الشخصية لـ${preview.identity.display_name}`" class="h-full w-full object-cover">
                <img v-else src="/logo.svg" alt="" class="h-12 w-12 opacity-20">
              </div>

              <div class="mt-5 min-w-0">
                <h3 class="break-words text-3xl font-extrabold leading-tight text-[#111111]">{{ preview.identity.display_name }}</h3>
                <p v-if="preview.identity.professional_title" class="mt-2 break-words font-semibold text-neutral-700">{{ preview.identity.professional_title }}</p>
                <p v-if="preview.identity.primary_specialty" class="mt-1 break-words text-sm text-neutral-600">{{ preview.identity.primary_specialty }}</p>
                <bdi v-if="preview.identity.username" dir="ltr" class="mt-2 block w-fit max-w-full break-all text-sm font-semibold text-[#B42318]">@{{ preview.identity.username }}</bdi>
                <p v-if="preview.identity.bio" class="mt-5 whitespace-pre-line break-words leading-8 text-neutral-700">{{ preview.identity.bio }}</p>
              </div>

              <div class="mt-7 grid gap-4 sm:grid-cols-2">
                <section v-if="preview.professional.sections.availability.visible" class="rounded-2xl border border-neutral-200 bg-white p-5">
                  <h4 class="font-extrabold text-neutral-950">حالة التوفر</h4>
                  <p class="mt-2 text-sm text-neutral-600">{{ availabilityLabels[preview.professional.sections.availability.value] }}</p>
                </section>
                <section v-if="preview.professional.sections.experience.visible" class="rounded-2xl border border-neutral-200 bg-white p-5">
                  <h4 class="font-extrabold text-neutral-950">الخبرة</h4>
                  <p class="mt-2 text-sm text-neutral-600">
                    <template v-if="preview.professional.sections.experience.years_of_experience !== null">
                      <bdi dir="ltr">{{ preview.professional.sections.experience.years_of_experience }}</bdi> سنوات خبرة
                    </template>
                    <template v-else>لم تحدد بعد</template>
                  </p>
                </section>

                <section v-if="preview.professional.sections.specialties.visible" class="rounded-2xl border border-neutral-200 bg-white p-5 sm:col-span-2">
                  <h4 class="font-extrabold text-neutral-950">الخدمات والأساليب</h4>
                  <div class="mt-3 flex flex-wrap gap-2">
                    <span v-for="item in preview.professional.sections.specialties.service" :key="`service-${item.name}`" dir="auto" class="rounded-full bg-red-50 px-3 py-1.5 text-sm font-semibold text-[#B42318]">{{ item.name }}</span>
                    <span v-for="item in preview.professional.sections.specialties.style" :key="`style-${item.name}`" dir="auto" class="rounded-full bg-neutral-100 px-3 py-1.5 text-sm font-semibold text-neutral-700">{{ item.name }}</span>
                  </div>
                </section>

                <template
                  v-for="section in ([
                    { key: 'skills', title: 'المهارات', value: preview.professional.sections.skills },
                    { key: 'tools', title: 'البرامج والأدوات', value: preview.professional.sections.tools },
                    { key: 'languages', title: 'اللغات', value: preview.professional.sections.languages },
                  ] as const)"
                  :key="section.key"
                >
                  <section v-if="section.value.visible" class="rounded-2xl border border-neutral-200 bg-white p-5">
                    <h4 class="font-extrabold text-neutral-950">{{ section.title }}</h4>
                    <ul class="mt-3 space-y-2">
                      <li v-for="item in section.value.items" :key="`${section.key}-${item.name}`" class="flex min-w-0 flex-wrap items-center gap-2 text-sm">
                        <span dir="auto" class="min-w-0 break-words font-semibold text-neutral-800">{{ item.name }}</span>
                        <span class="shrink-0 rounded-full border border-neutral-300 bg-neutral-100 px-2.5 py-1 text-sm font-semibold leading-none text-neutral-700">{{ levelLabels[item.level] || item.level }}</span>
                      </li>
                    </ul>
                  </section>
                </template>

                <section v-if="preview.professional.additional_information?.professional_note" class="rounded-2xl border border-neutral-200 bg-white p-5 sm:col-span-2">
                  <h4 class="font-extrabold text-neutral-950">معلومات مهنية إضافية</h4>
                  <p class="mt-3 whitespace-pre-line break-words leading-7 text-neutral-700">{{ preview.professional.additional_information.professional_note }}</p>
                </section>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </Teleport>
</template>
