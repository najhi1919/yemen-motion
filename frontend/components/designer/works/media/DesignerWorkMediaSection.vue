<script setup lang="ts">
import DesignerWorkMediaCard from './DesignerWorkMediaCard.vue'
import DesignerWorkMediaConfirmDialog from './DesignerWorkMediaConfirmDialog.vue'
import DesignerWorkMediaPreviewDialog from './DesignerWorkMediaPreviewDialog.vue'
import DesignerWorkVideoCoverDialog from './DesignerWorkVideoCoverDialog.vue'
import type { useDesignerWorkMedia } from '~/composables/useDesignerWorkMedia'
import type { DesignerWorkMedia } from '~/types/designer-work-media'

const props = defineProps<{
  manager: ReturnType<typeof useDesignerWorkMedia>
}>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const dragging = ref(false)
const pendingDelete = ref<DesignerWorkMedia | null>(null)
const deleteOpener = ref<HTMLElement | null>(null)

const accept = computed(() => props.manager.mediaPolicy.value.allowed_mime_types.join(','))
const uploadDisabled = computed(() =>
  !props.manager.editable.value
  || props.manager.uploading.value
  || props.manager.counts.value.remaining === 0
  || !props.manager.work.value?.media_type,
)
const allowedTypes = computed(() => {
  const names: Record<string, string> = {
    'image/jpeg': 'JPEG',
    'image/png': 'PNG',
    'image/webp': 'WEBP',
    'video/mp4': 'MP4',
    'video/webm': 'WEBM',
  }
  return props.manager.mediaPolicy.value.allowed_mime_types.map(type => names[type] || type).join('، ')
})
const maxSize = computed(() => {
  const kb = props.manager.mediaPolicy.value.effective_max_file_size_kb
    ?? props.manager.mediaPolicy.value.effective_limits.max_file_size_kb
  if (kb === null || kb === undefined) return ''
  return kb < 1024 ? `${kb.toLocaleString('ar-YE')} ك.ب` : `${(kb / 1024).toLocaleString('ar-YE', { maximumFractionDigits: 1 })} م.ب`
})
const fileError = computed(() => props.manager.validationErrors.value.file?.[0] || '')

const selectFile = (file: File | null) => {
  selectedFile.value = file
}

const onInput = (event: Event) => {
  selectFile((event.target as HTMLInputElement).files?.[0] || null)
}

const onDrop = (event: DragEvent) => {
  dragging.value = false
  if (uploadDisabled.value) return
  selectFile(event.dataTransfer?.files?.[0] || null)
}

const upload = async () => {
  if (!selectedFile.value) return
  if (await props.manager.uploadMedia(selectedFile.value)) {
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
  }
}

const requestDelete = (item: DesignerWorkMedia, event: MouseEvent) => {
  pendingDelete.value = item
  deleteOpener.value = event.currentTarget as HTMLElement
}

const openPreview = (item: DesignerWorkMedia, event: MouseEvent) => {
  props.manager.openPreview(item, event.currentTarget as HTMLElement)
}

const openVideoCover = (item: DesignerWorkMedia, event: MouseEvent) => {
  props.manager.openVideoCover(item, event.currentTarget as HTMLElement)
}

const confirmDelete = async () => {
  if (!pendingDelete.value) return
  if (await props.manager.deleteMedia(pendingDelete.value.id)) pendingDelete.value = null
}
</script>

<template>
  <section
    class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 text-[var(--ym-d-text)] shadow-[var(--ym-d-shadow-sm)] sm:p-6"
    aria-labelledby="designer-work-media-title"
    :aria-busy="manager.loading.value || manager.uploading.value"
  >
    <header class="flex flex-col gap-4 border-b border-[rgba(17,17,17,0.09)] pb-5 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[var(--ym-d-charcoal)] text-white" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
            <rect x="4" y="5" width="16" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7" />
            <path d="m7 16 3.5-4 2.5 2.5 2-2 2 3.5M16.5 9h.01" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <div>
          <p class="text-xs font-extrabold text-[var(--ym-d-red-strong)]">مكتبة العمل</p>
          <h2 id="designer-work-media-title" class="mt-1 text-xl font-extrabold">
            وسائط العمل
          </h2>
          <p class="mt-1 text-sm text-[var(--ym-d-muted)]">
            أضف ملفات العمل، رتّبها، وحدد صورة الغلاف.
          </p>
        </div>
      </div>
      <button
        v-if="manager.work.value?.cover_media_id"
        type="button"
        class="min-h-11 self-start rounded-xl border border-[var(--ym-d-red-border)] bg-white px-4 text-sm font-bold text-[var(--ym-d-red-strong)] transition hover:bg-[var(--ym-d-red-soft)] disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
        :disabled="!manager.editable.value || manager.covering.value"
        @click="manager.updateCover(null)"
      >
        إزالة الغلاف
      </button>
    </header>

    <p v-if="manager.message.value" class="mt-4 text-sm font-bold text-emerald-700" aria-live="polite">
      {{ manager.message.value }}
    </p>
    <p v-if="manager.error.value" class="mt-4 text-sm font-bold text-[#B81414]" role="alert">
      {{ manager.error.value }}
    </p>

    <div v-if="manager.loading.value" class="mt-5 grid gap-4 sm:grid-cols-2" role="status" aria-label="جارٍ تحميل الوسائط">
      <div v-for="item in 2" :key="item" class="h-72 animate-pulse rounded-2xl bg-neutral-200 motion-reduce:animate-none" />
    </div>

    <template v-else-if="!manager.notFound.value">
      <div
        v-if="!manager.work.value?.media_type"
        class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900"
      >
        حدد نوع العمل في البيانات الأساسية واحفظه قبل رفع الوسائط.
      </div>
      <div
        v-else-if="!manager.editable.value"
        class="mt-5 rounded-xl border border-neutral-200 bg-neutral-50 p-4 text-sm font-semibold text-[#555555]"
      >
        القائمة متاحة للعرض فقط في حالة العمل الحالية.
      </div>

      <div
        class="mt-5 rounded-2xl border border-dashed p-4"
        :class="[
          dragging ? 'border-[var(--ym-d-red)] bg-[var(--ym-d-red-soft)]' : 'border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-warm)]',
          uploadDisabled ? 'opacity-65' : '',
        ]"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
      >
        <label
          for="designer-work-media-file"
          class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl text-center focus-within:ring-4 focus-within:ring-red-200"
          :class="{ 'cursor-not-allowed': uploadDisabled }"
        >
          <span class="font-extrabold">{{ selectedFile ? selectedFile.name : 'اختر ملفًا أواسحبه هنا' }}</span>
          <span v-if="selectedFile" class="mt-1 text-sm text-[#666666]">
            {{ (selectedFile.size / 1024).toLocaleString('ar-YE', { maximumFractionDigits: 1 }) }} ك.ب
          </span>
          <span v-else class="mt-1 text-sm text-[#666666]">ملف واحد في كل مرة</span>
          <input
            id="designer-work-media-file"
            ref="fileInput"
            type="file"
            class="sr-only"
            :accept="accept"
            :disabled="uploadDisabled"
            :aria-invalid="Boolean(fileError)"
            :aria-describedby="fileError ? 'designer-work-media-file-error' : 'designer-work-media-help'"
            @change="onInput"
          >
        </label>
        <div id="designer-work-media-help" class="mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-[#666666]">
          <span v-if="allowedTypes">الأنواع المسموحة: {{ allowedTypes }}</span>
          <span v-if="maxSize">الحجم الأقصى: {{ maxSize }}</span>
          <span>المستخدم: {{ manager.counts.value.active.toLocaleString('ar-YE') }}</span>
          <span v-if="manager.counts.value.remaining !== null">
            المتبقي: {{ manager.counts.value.remaining.toLocaleString('ar-YE') }}
          </span>
        </div>
        <p v-if="fileError" id="designer-work-media-file-error" class="mt-3 text-center text-sm font-bold text-[#B81414]" role="alert">
          {{ fileError }}
        </p>
        <button
          type="button"
          class="mx-auto mt-4 flex min-h-11 items-center justify-center rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white transition hover:bg-[var(--ym-d-red-strong)] disabled:cursor-not-allowed disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="uploadDisabled || !selectedFile"
          @click="upload"
        >
          {{ manager.uploading.value ? 'جارٍ الرفع…' : 'رفع الوسيط' }}
        </button>
      </div>

      <div v-if="manager.media.value.length" class="mt-6 grid gap-4 sm:grid-cols-2">
        <DesignerWorkMediaCard
          v-for="(item, index) in manager.media.value"
          :key="item.id"
          :item="item"
          :index="index"
          :total="manager.media.value.length"
          :image-url="manager.imageObjectUrls.value[item.id] || ''"
          :poster-url="manager.posterObjectUrls.value[item.id] || ''"
          :editable="manager.editable.value"
          :ordering="manager.ordering.value"
          :covering="manager.covering.value"
          :deleting="manager.deletingId.value === item.id"
          :retrying="manager.retryingId.value === item.id"
          @preview="openPreview(item, $event)"
          @video-cover="openVideoCover(item, $event)"
          @move="manager.reorderMedia(index, index + $event)"
          @cover="manager.updateCover(item.id)"
          @remove="requestDelete(item, $event)"
          @retry="manager.retryProcessing(item.id)"
        />
      </div>
      <div v-else class="relative mt-6 overflow-hidden rounded-2xl border border-[var(--ym-d-red-border)] bg-[var(--ym-d-surface-warm)] p-8 text-center">
        <span class="absolute inset-y-0 right-0 w-1 bg-[var(--ym-d-red)]" aria-hidden="true" />
        <img src="/logo.svg" alt="" class="mx-auto h-12 w-12 opacity-15">
        <p class="mt-3 font-bold">لا توجد وسائط مضافة</p>
      </div>
    </template>

    <p v-else class="mt-5 rounded-xl bg-neutral-100 p-5 text-center font-bold" role="alert">
      العمل غير موجود.
    </p>

    <DesignerWorkMediaConfirmDialog
      :open="Boolean(pendingDelete)"
      :item="pendingDelete"
      :busy="manager.deletingId.value !== null"
      :return-focus-to="deleteOpener"
      @cancel="pendingDelete = null"
      @confirm="confirmDelete"
    />
    <DesignerWorkMediaPreviewDialog
      :preview="manager.preview.value"
      @close="manager.closePreview()"
    />
    <DesignerWorkVideoCoverDialog :manager="manager" />
  </section>
</template>
