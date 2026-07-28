<script setup lang="ts">
import type { DesignerProfile } from '~/types/designer-profile'

const props = defineProps<{
  profile: DesignerProfile
}>()

const emit = defineEmits<{
  avatarSource: [source: string | null]
}>()

const currentProfile = ref(props.profile)
const dialogOpen = ref(false)
const dialogMode = ref<'avatar' | 'cover'>('avatar')
const feedback = ref<string | null>(null)

const {
  pending,
  error,
  uploadAvatar,
  deleteAvatar,
  uploadCover,
  deleteCover,
  updateCoverFocalPoint,
  loadMedia,
} = useDesignerProfileMedia()

const avatarObjectUrl = ref<string | null>(null)
const coverObjectUrl = ref<string | null>(null)

const revokeMediaUrl = (url: string | null) => {
  if (url) {
    URL.revokeObjectURL(url)
  }
}

const refreshMediaObjectUrl = async (
  source: string | null,
  target: Ref<string | null>,
) => {
  if (!import.meta.client) {
    return
  }

  revokeMediaUrl(target.value)
  target.value = null

  if (!source) {
    return
  }

  try {
    target.value = URL.createObjectURL(await loadMedia(source))
  } catch {
    target.value = null
  }
}

watch(
  () => props.profile,
  profile => {
    currentProfile.value = profile
  },
)

watch(
  () => currentProfile.value.identity_media.avatar_url,
  async url => {
    await refreshMediaObjectUrl(url, avatarObjectUrl)
    emit('avatarSource', avatarObjectUrl.value)
  },
  { immediate: true },
)

watch(
  () => currentProfile.value.identity_media.cover_url,
  url => refreshMediaObjectUrl(url, coverObjectUrl),
  { immediate: true },
)

onBeforeUnmount(() => {
  emit('avatarSource', null)
  revokeMediaUrl(avatarObjectUrl.value)
  revokeMediaUrl(coverObjectUrl.value)
})

const coverPosition = computed(() => {
  const point = currentProfile.value.identity_media.cover_focal_point
  return `${point.x}% ${point.y}%`
})

const dialogProfile = computed<DesignerProfile>(() => ({
  ...currentProfile.value,
  identity_media: {
    ...currentProfile.value.identity_media,
    avatar_url: avatarObjectUrl.value,
    cover_url: coverObjectUrl.value,
  },
}))

const openDialog = (mode: 'avatar' | 'cover') => {
  dialogMode.value = mode
  feedback.value = null
  dialogOpen.value = true
}

const applyResult = (profile: DesignerProfile, message: string) => {
  currentProfile.value = profile
  dialogOpen.value = false
  feedback.value = message
}

const handleUpload = async (file: File) => {
  try {
    const profile = dialogMode.value === 'avatar'
      ? await uploadAvatar(file)
      : await uploadCover(file)
    applyResult(profile, 'تم حفظ الوسائط بنجاح.')
  } catch {
    // يبقى الحوار مفتوحًا ويعرض الخطأ الصادر من composable.
  }
}

const handleDelete = async () => {
  try {
    const profile = dialogMode.value === 'avatar'
      ? await deleteAvatar()
      : await deleteCover()
    applyResult(profile, 'تم حذف الوسائط.')
  } catch {
    // يبقى الحوار مفتوحًا ويعرض الخطأ الصادر من composable.
  }
}

const handleFocalSave = async (x: number, y: number) => {
  try {
    const profile = await updateCoverFocalPoint(x, y)
    applyResult(profile, 'تم حفظ موضع الغلاف.')
  } catch {
    // يبقى الحوار مفتوحًا ويعرض الخطأ الصادر من composable.
  }
}
</script>

<template>
  <section
    class="overflow-hidden rounded-[20px] border border-[rgba(17,17,17,0.11)] bg-white shadow-[0_8px_24px_rgba(17,17,17,0.05)]"
    aria-labelledby="identity-media-title"
  >
    <h2 id="identity-media-title" class="sr-only">
      الصورة الشخصية وغلاف المصمم
    </h2>

    <div class="relative h-[170px] overflow-hidden bg-[#F7F7F7] sm:h-[220px] lg:h-[240px]">
      <img
        v-if="coverObjectUrl"
        :src="coverObjectUrl"
        alt=""
        class="h-full w-full object-cover"
        :style="{ objectPosition: coverPosition }"
      >
      <div
        v-else
        class="flex h-full items-center justify-center bg-gradient-to-bl from-[#F3F3F3] via-[#FAFAFA] to-white"
      >
        <img
          src="/logo.svg"
          alt=""
          class="h-20 w-20 opacity-[0.08] sm:h-28 sm:w-28"
        >
      </div>

      <div
        v-if="coverObjectUrl"
        class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-b from-transparent to-[rgba(17,17,17,0.22)]"
      />

      <button
        type="button"
        class="absolute left-4 top-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none sm:left-5 sm:top-5"
        :class="coverObjectUrl
          ? 'border-white/30 bg-[rgba(17,17,17,0.76)] text-white backdrop-blur-sm hover:bg-[rgba(17,17,17,0.9)]'
          : 'border-[#E21D1D] bg-[#E21D1D] text-white hover:bg-[#C91414]'"
        @click="openDialog('cover')"
      >
        <svg
          aria-hidden="true"
          viewBox="0 0 24 24"
          fill="none"
          class="h-4 w-4"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="m16.9 3.7 3.4 3.4L8.4 19H5v-3.4L16.9 3.7Z" />
        </svg>
        {{ coverObjectUrl ? 'تغيير الغلاف' : 'إضافة غلاف' }}
      </button>
    </div>

    <div class="relative px-5 pb-5 pt-[72px] sm:px-7 sm:pb-6 md:pt-5">
      <div class="absolute -top-14 right-5 sm:-top-16 sm:right-7">
        <div class="h-28 w-28 overflow-hidden rounded-full border-4 border-white bg-[#F4F4F4] shadow-[0_6px_18px_rgba(17,17,17,0.13)] sm:h-32 sm:w-32">
          <img
            v-if="avatarObjectUrl"
            :src="avatarObjectUrl"
            :alt="`الصورة الشخصية لـ${currentProfile.display_name}`"
            class="h-full w-full object-cover"
          >
          <div v-else class="flex h-full w-full items-center justify-center">
            <img src="/logo.svg" alt="" class="h-12 w-12 opacity-20">
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-4 md:inline-flex md:min-h-24 md:items-start md:justify-center md:gap-3 md:pr-[148px]">
        <div class="min-w-0">
          <p class="break-words text-2xl font-extrabold leading-tight text-[#151515] sm:text-3xl">
            {{ currentProfile.display_name }}
          </p>
          <p
            v-if="currentProfile.professional_title"
            class="mt-1.5 break-words text-[15px] font-medium text-[#666666] sm:text-base"
          >
            {{ currentProfile.professional_title }}
          </p>
        </div>

        <button
          type="button"
          class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-xl border px-5 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none md:w-auto"
          :class="avatarObjectUrl
            ? 'border-[#E21D1D] bg-white text-[#B81414] hover:bg-[rgba(226,29,29,0.07)]'
            : 'border-[#E21D1D] bg-[#E21D1D] text-white hover:bg-[#C91414]'"
          @click="openDialog('avatar')"
        >
          {{ avatarObjectUrl ? 'تغيير الصورة' : 'إضافة صورة شخصية' }}
        </button>
      </div>

      <p
        v-if="feedback"
        role="status"
        class="mt-4 inline-flex max-w-full items-center gap-2 rounded-xl bg-[#F0FAF3] px-3 py-2 text-sm font-semibold text-[#16803D]"
      >
        <svg
          aria-hidden="true"
          viewBox="0 0 20 20"
          fill="none"
          class="h-4 w-4 shrink-0"
          stroke="currentColor"
          stroke-width="2"
        >
          <path stroke-linecap="round" stroke-linejoin="round" d="m4 10 4 4 8-8" />
        </svg>
        {{ feedback }}
      </p>
    </div>

    <DesignerProfileMediaDialog
      :open="dialogOpen"
      :mode="dialogMode"
      :profile="dialogProfile"
      :busy="pending"
      :error="error"
      @close="dialogOpen = false"
      @upload="handleUpload"
      @delete="handleDelete"
      @save-focal="handleFocalSave"
    />
  </section>
</template>
