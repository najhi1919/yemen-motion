<script setup lang="ts">
import type { DesignerProfile } from '~/types/designer-profile'

const props = defineProps<{
  profile: DesignerProfile
}>()

const emit = defineEmits<{
  avatarSource: [source: string | null]
}>()

const designerHeaderAvatarSource = useState<string | null>(
  'ym-designer-header-avatar-source',
  () => null,
)
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
    designerHeaderAvatarSource.value = url || null
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
    class="overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] shadow-[var(--ym-d-shadow-sm)]"
    aria-labelledby="identity-media-title"
  >
    <h2 id="identity-media-title" class="sr-only">
      الصورة الشخصية وغلاف المصمم
    </h2>

    <div class="relative h-[170px] overflow-hidden border-t-4 border-[var(--ym-d-red)] bg-[var(--ym-d-surface-muted)] sm:h-[220px] lg:h-[240px]">
      <img
        v-if="coverObjectUrl"
        :src="coverObjectUrl"
        alt=""
        class="h-full w-full object-cover"
        :style="{ objectPosition: coverPosition }"
      >
      <div
        v-else
        class="flex h-full items-center justify-center bg-gradient-to-bl from-[var(--ym-d-surface-muted)] via-[var(--ym-d-surface-warm)] to-white"
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
        class="absolute left-4 top-4 inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-4 text-sm font-bold text-white transition hover:border-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none sm:left-5 sm:top-5"
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

    <div class="ym-profile-identity-band relative border-t-2 border-[var(--ym-d-red)] bg-[var(--ym-d-charcoal)] px-5 pb-4 pt-[68px] text-white sm:px-7 sm:pb-5 md:pt-4">
      <div class="ym-profile-avatar-overlap absolute -top-14 right-5 z-30 sm:-top-16 sm:right-7">
        <div class="h-28 w-28 overflow-hidden rounded-full border-4 border-white bg-[var(--ym-d-surface-muted)] shadow-[0_0_0_3px_var(--ym-d-red-border),0_8px_22px_rgba(17,17,17,0.22)] sm:h-32 sm:w-32">
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

      <div class="flex min-h-24 flex-col justify-center gap-3 md:pr-[156px]">
        <div class="min-w-0">
          <p class="break-words text-2xl font-extrabold leading-tight text-white sm:text-3xl">
            {{ currentProfile.display_name }}
          </p>
          <p
            v-if="currentProfile.professional_title"
            class="mt-1.5 break-words text-[15px] font-medium text-white/75 sm:text-base"
          >
            {{ currentProfile.professional_title }}
          </p>
          <div class="ym-profile-identity-actions mt-3">
            <button
              type="button"
              class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-5 text-sm font-bold text-white transition hover:border-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none md:w-auto"
              @click="openDialog('avatar')"
            >
              {{ avatarObjectUrl ? 'تغيير الصورة' : 'إضافة صورة شخصية' }}
            </button>
          </div>
        </div>
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

<style scoped>
.ym-profile-identity-band {
  isolation: isolate;
  background:
    linear-gradient(118deg, transparent 62%, rgba(255, 255, 255, 0.04) 62% 62.7%, transparent 62.7%),
    linear-gradient(62deg, transparent 83%, rgba(226, 29, 29, 0.08) 83% 84%, transparent 84%),
    radial-gradient(circle at 8% 100%, rgba(226, 29, 29, 0.08), transparent 28%),
    var(--ym-d-charcoal);
  overflow: visible;
}

.ym-profile-avatar-overlap {
  pointer-events: none;
}

.ym-profile-avatar-overlap > * {
  pointer-events: auto;
}
</style>
