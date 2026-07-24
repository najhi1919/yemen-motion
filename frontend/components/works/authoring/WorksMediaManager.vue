<template>
  <section
    class="ym-media-manager"
    :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
    aria-labelledby="ym-media-manager-title"
  >
    <header class="ym-media-manager__head">
      <div class="ym-media-manager__heading">
        <span class="ym-media-manager__head-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M4 7.5h16v11H4zM8 7.5l1.4-2h5.2l1.4 2M8 12l2.2 2.2L13.5 11l3.5 4" />
          </svg>
        </span>
        <div>
          <p>{{ text.kicker }}</p>
          <h2 id="ym-media-manager-title">{{ text.title }}</h2>
          <span>{{ text.copy }}</span>
        </div>
      </div>

      <div class="ym-media-manager__capacity">
        <div>
          <strong>{{ capacityLabel }}</strong>
          <span>{{ remainingLabel }}</span>
        </div>
        <div
          v-if="capacityPercent !== null"
          class="ym-media-manager__progress"
          role="progressbar"
          :aria-valuenow="capacityPercent"
          aria-valuemin="0"
          aria-valuemax="100"
          :aria-label="text.capacity"
        >
          <i :style="{ inlineSize: `${capacityPercent}%` }" />
        </div>
        <small>{{ expectedKindLabel }} · {{ coverStateLabel }}</small>
      </div>
    </header>

    <div
      v-if="message"
      class="ym-media-manager__message"
      :class="`is-${messageTone}`"
      :role="messageTone === 'error' ? 'alert' : 'status'"
      aria-live="polite"
    >
      <span>{{ message }}</span>
      <button v-if="showReload" type="button" @click="reloadMedia">
        {{ text.reload }}
      </button>
    </div>

    <aside v-if="readonlyReason" class="ym-media-manager__readonly" role="note">
      <strong>{{ text.readonlyTitle }}</strong>
      <span>{{ readonlyReason }}</span>
    </aside>

    <WorksMediaUploadZone
      v-if="canUpdateMedia"
      ref="uploadZone"
      :locale="locale"
      :accept="accept"
      :accepted-label="allowedTypesLabel"
      :max-size-label="maxSizeLabel"
      :remaining="counts.remaining"
      :selected-file="selectedFile"
      :uploading="uploading"
      :disabled="uploadSelectionDisabled"
      :can-upload="canUpload"
      :disabled-reason="uploadDisabledReason"
      :action-reason="uploadActionReason"
      :error="visibleFileError"
      @select="selectFile"
      @upload="upload"
    />

    <div v-if="localMedia.length === 0" class="ym-media-manager__empty">
      <span aria-hidden="true">▧</span>
      <strong>{{ text.empty }}</strong>
      <p>{{ uploadSelectionDisabled ? uploadDisabledReason : text.emptyCopy }}</p>
    </div>

    <template v-else>
        <div class="ym-media-manager__console" :class="{ 'is-single-media': singleMediaMode }">
        <aside
          v-if="!singleMediaMode"
          class="ym-media-manager__gallery-panel"
          aria-labelledby="ym-media-gallery-title"
        >
          <div class="ym-media-manager__panel-head">
            <div>
              <p>{{ text.library }}</p>
              <h3 id="ym-media-gallery-title">{{ text.files }}</h3>
            </div>
            <span>{{ formatYmNumber(localMedia.length, locale) }}</span>
          </div>
          <WorksMediaGallery
            :items="localMedia"
            :selected-id="selectedMediaId"
            :preview-urls="previewUrls"
            :preview-errors="previewErrors"
            :dragged-id="draggedId"
            :can-organize="canOrganize"
            :busy="busy"
            :locale="locale"
            @select="selectedMediaId = $event"
            @move="move"
            @drag-start="startDrag"
            @drop="dropOn"
            @drag-end="draggedId = null"
          />
        </aside>

        <section class="ym-media-manager__preview-panel" :aria-label="text.previewPanel">
          <WorksMediaGallery
            v-if="singleMediaMode"
            class="ym-media-manager__single-summary"
            :items="localMedia"
            :selected-id="selectedMediaId"
            :preview-urls="previewUrls"
            :preview-errors="previewErrors"
            :dragged-id="null"
            :can-organize="false"
            :busy="busy"
            :locale="locale"
            @select="selectedMediaId = $event"
          />
          <WorksMediaPreview
            v-if="selectedMedia"
            :item="selectedMedia"
            :preview-url="previewUrls[selectedMedia.id] || ''"
            :preview-error="Boolean(previewErrors[selectedMedia.id])"
            :index="selectedIndex"
            :total="localMedia.length"
            :locale="locale"
            :editable="!actionsDisabled"
            :busy="busy"
            :has-cover="hasCover"
            :can-clear-cover="selectedMedia.is_cover"
            :can-reorder="canOrganize"
            @set-cover="updateCover(selectedMedia.id)"
            @clear-cover="updateCover(null)"
            @move="move(selectedIndex, $event)"
            @remove="openRemoveDialog(selectedMedia, $event)"
            @retry="retryPreview(selectedMedia)"
          />
        </section>
      </div>

      <footer v-if="canOrganize" class="ym-media-manager__order">
        <div>
          <strong>{{ orderStatusLabel }}</strong>
          <span>{{ text.orderHelp }}</span>
        </div>
        <div class="ym-media-manager__order-actions">
          <button type="button" :disabled="!orderDirty || busy" @click="cancelOrder">
            {{ text.cancelOrder }}
          </button>
          <button type="button" class="is-primary" :disabled="!orderDirty || busy" @click="saveOrder">
            {{ ordering ? text.saving : text.saveOrder }}
          </button>
        </div>
      </footer>
    </template>

    <WorksMediaConfirmDialog
      :open="Boolean(pendingRemoval)"
      :busy="deletingId !== null"
      :locale="locale"
      :item="pendingRemoval"
      :preview-url="pendingRemoval ? previewUrls[pendingRemoval.id] || '' : ''"
      :return-focus-to="removeDialogAnchor"
      @cancel="closeRemoveDialog"
      @confirm="confirmRemoval"
    />
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import WorksMediaConfirmDialog from '~/components/works/media/WorksMediaConfirmDialog.vue'
import WorksMediaGallery from '~/components/works/media/WorksMediaGallery.vue'
import WorksMediaPreview from '~/components/works/media/WorksMediaPreview.vue'
import WorksMediaUploadZone from '~/components/works/media/WorksMediaUploadZone.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import { formatYmNumber } from '~/utils/ymFormatting'

interface MediaWork {
  id: number
  status: string
  media_type: string | null
  cover_media_id: number | null
}
interface MediaItem {
  id: number
  kind: 'image' | 'video'
  original_name: string
  mime_type: string
  extension: string
  size_bytes: number
  size_kb: number
  position: number
  width: number | null
  height: number | null
  duration_ms: number | null
  processing_status: 'pending' | 'ready' | 'failed'
  is_cover: boolean
  uploaded_by: { id: number; name: string } | null
  created_at: string | null
  updated_at: string | null
  content_endpoint: string
}
interface MediaPolicy {
  source: string
  settings_version: number
  work_media_type: string | null
  allowed_media_types: string[]
  allowed_file_kinds: string[]
  allowed_mime_types: string[]
  configured_limits: { max_items: number | null; max_file_size_kb: number | null }
  effective_limits: { max_items: number | null; max_file_size_kb: number | null }
  effective_max_file_size_kb?: number | null
  enforcement: Record<string, boolean>
}
interface Counts { active: number; remaining: number | null }
interface ApiResponse<T> { success: boolean; data: T; message?: string; errors?: Record<string, string[]> | null }
interface OrganizationData {
  work: MediaWork
  media: MediaItem[]
  media_policy: MediaPolicy
  counts: Counts
  changed: boolean
}
interface UploadZoneRef {
  focusInput: () => void
  openPicker: () => void
  reset: () => void
}

const props = defineProps<{
  work: MediaWork
  media: MediaItem[]
  mediaPolicy: MediaPolicy
  counts: Counts
  canUpdateMedia: boolean
  editable: boolean
  locale: 'ar' | 'en'
}>()
const emit = defineEmits<{
  stateChange: [payload: { work?: MediaWork; media: MediaItem[]; media_policy?: MediaPolicy; counts: Counts }]
  orderDirtyChange: [dirty: boolean]
}>()

const { apiFetch, tokenCookie } = useApiClient()
const authStore = useAuthStore()
const config = useRuntimeConfig()
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const baseUrl = (config.public.apiBaseUrl as string) || 'http://127.0.0.1:8000/api'
const apiOrigin = baseUrl.replace(/\/api\/?$/, '')
const localMedia = ref<MediaItem[]>([])
const serverOrder = ref<number[]>([])
const selectedMediaId = ref<number | null>(null)
const selectedFile = ref<File | null>(null)
const uploadZone = ref<UploadZoneRef | null>(null)
const uploading = ref(false)
const ordering = ref(false)
const covering = ref(false)
const deletingId = ref<number | null>(null)
const pendingRemoval = ref<MediaItem | null>(null)
const removeDialogAnchor = ref<HTMLElement | null>(null)
const draggedId = ref<number | null>(null)
const previewUrls = ref<Record<number, string>>({})
const previewErrors = ref<Record<number, boolean>>({})
const previewSignatures = ref<Record<number, string>>({})
const previewRevision = ref(0)
const previewControllers = new Map<number, AbortController>()
const fileError = ref('')
const message = ref('')
const messageTone = ref<'success' | 'error' | 'info'>('info')
const authorizationLost = ref(false)
const showReload = ref(false)
const extensionsByMime: Record<string, string[]> = {
  'image/jpeg': ['jpg', 'jpeg'],
  'image/png': ['png'],
  'image/webp': ['webp'],
  'video/mp4': ['mp4'],
  'video/webm': ['webm'],
  'video/quicktime': ['mov', 'qt']
}

const text = computed(() => props.locale === 'ar' ? {
  kicker: 'وسائط خاصة ومحمية',
  title: 'إدارة وسائط العمل',
  copy: 'أضف ملفات العمل، وحدد الغلاف، ونظّم الترتيب وفق الصلاحيات المتاحة.',
  capacity: 'نسبة استخدام سعة الوسائط',
  unlimited: 'دون حد',
  remaining: 'متبقٍ',
  noCover: 'دون غلاف',
  coverUnavailable: 'الغلاف للصور فقط',
  coverReady: 'الغلاف محدد',
  image: 'صورة',
  video: 'فيديو',
  mixed: 'صورة أوفيديو',
  unknown: 'نوع الوسائط غير محدد',
  reload: 'إعادة تحميل الوسائط',
  readonlyTitle: 'عرض فقط',
  readonly: 'حالة العمل الحالية تسمح بعرض الوسائط فقط، ولا تسمح بتنظيمها أوتعديلها.',
  unauthorized: 'لم تعد لديك صلاحية تعديل الوسائط. أُوقفت الإجراءات والمعاينات المحمية.',
  empty: 'لا توجد وسائط مضافة',
  emptyCopy: 'ستظهر وسائط العمل هنا بعد رفع أول ملف.',
  library: 'مكتبة العمل',
  files: 'قائمة الوسائط',
  previewPanel: 'معاينة الوسيط المحدد ومعلوماته',
  orderClean: 'ترتيب الوسائط محفوظ',
  orderPending: 'يوجد ترتيب غير محفوظ',
  orderHelp: 'استخدم السحب أوأزرار أعلى وأسفل، ثم احفظ الترتيب.',
  cancelOrder: 'إلغاء التغييرات',
  saving: 'جارٍ حفظ الترتيب…',
  saveOrder: 'حفظ ترتيب الوسائط',
  maxReached: 'اكتمل الحد الأقصى لوسائط هذا العمل.',
  permissionDisabled: 'لا توجد صلاحية لتعديل وسائط هذا العمل.',
  typeRequired: 'اختر نوع الوسائط واحفظ بيانات العمل قبل رفع ملف.',
  uploading: 'جارٍ رفع وسيط؛ انتظر حتى يكتمل الطلب الحالي.',
  types: 'الأنواع المسموحة',
  chooseFile: 'اختر ملفًا للتحقق منه قبل الرفع.',
  invalidType: 'نوع الملف غير مسموح لوسائط هذا العمل.',
  invalidExtension: 'امتداد الملف لا يطابق نوعه المسموح.',
  noFile: 'لم يتم تحديد ملف صالح للرفع.'
} : {
  kicker: 'Private protected media',
  title: 'Work media manager',
  copy: 'Add work files, select a cover, and organize the order within your permissions.',
  capacity: 'Media capacity usage',
  unlimited: 'Unlimited',
  remaining: 'remaining',
  noCover: 'No cover',
  coverUnavailable: 'Cover is for images only',
  coverReady: 'Cover selected',
  image: 'Image',
  video: 'Video',
  mixed: 'Image or video',
  unknown: 'Media type not selected',
  reload: 'Reload media',
  readonlyTitle: 'Read only',
  readonly: 'The current work state allows viewing media only, not organization or changes.',
  unauthorized: 'You no longer have permission to modify media. Actions and protected previews were stopped.',
  empty: 'No media added',
  emptyCopy: 'Work media will appear here after the first file is uploaded.',
  library: 'Work library',
  files: 'Media list',
  previewPanel: 'Selected media preview and information',
  orderClean: 'Media order is saved',
  orderPending: 'There is an unsaved order',
  orderHelp: 'Use drag and drop or the up/down buttons, then save the order.',
  cancelOrder: 'Discard changes',
  saving: 'Saving order…',
  saveOrder: 'Save media order',
  maxReached: 'This work has reached its media limit.',
  permissionDisabled: 'You do not have permission to modify this work media.',
  typeRequired: 'Choose a media type and save the work before uploading.',
  uploading: 'A media upload is in progress; wait for the current request.',
  types: 'Allowed types',
  chooseFile: 'Choose a file to validate before uploading.',
  invalidType: 'The file type is not allowed for this work media.',
  invalidExtension: 'The file extension does not match its allowed type.',
  noFile: 'No valid file has been selected for upload.'
})

const busy = computed(() => uploading.value || ordering.value || covering.value || deletingId.value !== null)
const actionsDisabled = computed(() => !props.editable || !props.canUpdateMedia || authorizationLost.value)
const singleMediaMode = computed(() => props.mediaPolicy.effective_limits.max_items === 1)
const canOrganize = computed(() => !actionsDisabled.value && !singleMediaMode.value && localMedia.value.length > 1)
const accept = computed(() => props.mediaPolicy.allowed_mime_types.join(','))
const selectedMedia = computed(() => localMedia.value.find(item => item.id === selectedMediaId.value) ?? null)
const selectedIndex = computed(() => selectedMedia.value
  ? localMedia.value.findIndex(item => item.id === selectedMedia.value?.id)
  : -1)
const hasCover = computed(() => localMedia.value.some(item => item.is_cover))
const capacityPercent = computed(() => {
  const maximum = props.mediaPolicy.effective_limits.max_items
  if (maximum === null || maximum <= 0) return null
  return Math.min(100, Math.round((props.counts.active / maximum) * 100))
})
const capacityLabel = computed(() => {
  const active = formatYmNumber(props.counts.active, props.locale)
  const maximum = props.mediaPolicy.effective_limits.max_items
  if (maximum === null) {
    return props.locale === 'ar' ? `${active} ملفات مستخدمة` : `${active} files used`
  }
  const max = formatYmNumber(maximum, props.locale)
  return props.locale === 'ar' ? `${active} من ${max} ملفات مستخدمة` : `${active} of ${max} files used`
})
const remainingLabel = computed(() => props.counts.remaining === null
  ? text.value.unlimited
  : `${formatYmNumber(props.counts.remaining, props.locale)} ${text.value.remaining}`)
const expectedKindLabel = computed(() => {
  const kinds = props.mediaPolicy.allowed_file_kinds
  if (kinds.includes('image') && kinds.includes('video')) return text.value.mixed
  if (kinds.includes('image')) return text.value.image
  if (kinds.includes('video')) return text.value.video
  return text.value.unknown
})
const coverStateLabel = computed(() => {
  if (!props.mediaPolicy.allowed_file_kinds.includes('image')) return text.value.coverUnavailable
  return hasCover.value ? text.value.coverReady : text.value.noCover
})
const allowedTypesLabel = computed(() => {
  const extensions = [...new Set(props.mediaPolicy.allowed_mime_types.map(mime => mime.split('/')[1]?.toUpperCase()).filter(Boolean))]
  return extensions.length
    ? `${text.value.types}: ${extensions.join(props.locale === 'ar' ? '، ' : ', ')}`
    : text.value.unknown
})
const maxSizeLabel = computed(() => {
  const maxKb = effectiveMaxFileSizeKb.value
  return maxKb === null ? '' : formatSize(maxKb * 1024)
})
const effectiveMaxFileSizeKb = computed(() =>
  props.mediaPolicy.effective_max_file_size_kb
    ?? props.mediaPolicy.effective_limits.max_file_size_kb
)
const readonlyReason = computed(() => {
  if (authorizationLost.value) return text.value.unauthorized
  if (!props.editable) return text.value.readonly
  if (!props.canUpdateMedia) return text.value.permissionDisabled
  return ''
})
const uploadSelectionDisabled = computed(() => actionsDisabled.value
  || uploading.value
  || props.counts.remaining === 0
  || props.mediaPolicy.allowed_mime_types.length === 0)
const localFileError = computed(() => validateSelectedFile(selectedFile.value))
const visibleFileError = computed(() => localFileError.value || fileError.value)
const canUpload = computed(() => Boolean(selectedFile.value)
  && !uploadSelectionDisabled.value
  && !visibleFileError.value)
const uploadDisabledReason = computed(() => {
  if (authorizationLost.value || !props.canUpdateMedia) return text.value.permissionDisabled
  if (!props.editable) return text.value.readonly
  if (props.counts.remaining === 0) return text.value.maxReached
  if (!props.mediaPolicy.allowed_mime_types.length) return text.value.typeRequired
  if (uploading.value) return text.value.uploading
  return ''
})
const uploadActionReason = computed(() => {
  if (uploadSelectionDisabled.value || visibleFileError.value) return ''
  return selectedFile.value ? '' : text.value.chooseFile
})
const orderDirty = computed(() => localMedia.value.some((item, index) => item.id !== serverOrder.value[index]))
const orderChangedCount = computed(() => localMedia.value.reduce(
  (count, item, index) => count + (item.id === serverOrder.value[index] ? 0 : 1),
  0
))
const orderStatusLabel = computed(() => orderDirty.value
  ? `${text.value.orderPending} · ${formatYmNumber(orderChangedCount.value, props.locale)}`
  : text.value.orderClean)

watch(orderDirty, dirty => emit('orderDirtyChange', dirty), { immediate: true })

watch(
  () => props.media,
  async media => {
    localMedia.value = media.map(item => ({ ...item }))
    serverOrder.value = media.map(item => item.id)
    if (!media.some(item => item.id === selectedMediaId.value)) {
      selectedMediaId.value = media[0]?.id ?? null
    }
    await refreshPreviews(media)
  },
  { immediate: true, deep: true }
)

function selectFile(file: File | null) {
  selectedFile.value = file
  fileError.value = ''
}

async function upload() {
  if (!selectedFile.value || !canUpload.value) return
  uploading.value = true
  fileError.value = ''
  showReload.value = false
  const form = new FormData()
  form.append('file', selectedFile.value)

  try {
    const response = await $fetch<ApiResponse<{
      media: MediaItem
      media_policy: MediaPolicy
      counts: Counts
    }>>(`${baseUrl}/admin/works/${props.work.id}/media`, {
      method: 'POST',
      body: form,
      headers: authenticatedHeaders()
    })
    const media = [...localMedia.value, response.data.media]
    localMedia.value = media
    serverOrder.value = media.map(item => item.id)
    selectedMediaId.value = response.data.media.id
    selectedFile.value = null
    uploadZone.value?.reset()
    message.value = response.message || localized('تم رفع الوسيط بنجاح.', 'Media uploaded successfully.')
    messageTone.value = 'success'
    emit('stateChange', {
      media,
      media_policy: response.data.media_policy,
      counts: response.data.counts
    })
    await refreshPreviews(media)
  } catch (error: unknown) {
    handleAuth(error)
    const status = statusOf(error)
    showReload.value = status === 409
    if (isUploadSizeError(error)) {
      fileError.value = uploadTooLargeMessage()
    } else if (status === 422) {
      fileError.value = normalizedUploadFieldError(error)
    } else {
      fileError.value = ''
      message.value = uploadSystemError(error)
    }
    messageTone.value = 'error'
  } finally {
    uploading.value = false
  }
}

function validateSelectedFile(file: File | null): string {
  if (!file) return ''
  if (props.counts.remaining === 0) return text.value.maxReached

  const allowedMimeTypes = props.mediaPolicy.allowed_mime_types
  if (!file.type || !allowedMimeTypes.includes(file.type)) {
    return text.value.invalidType
  }

  const extension = file.name.includes('.')
    ? file.name.split('.').pop()?.toLowerCase() || ''
    : ''
  const allowedExtensions = extensionsByMime[file.type] || []
  if (!extension || !allowedExtensions.includes(extension)) {
    return text.value.invalidExtension
  }

  const maxFileSizeKb = effectiveMaxFileSizeKb.value
  if (maxFileSizeKb !== null && file.size > maxFileSizeKb * 1024) {
    return localized(
      `حجم الملف ${formatSize(file.size)}، بينما الحد الأقصى المسموح ${formatSize(maxFileSizeKb * 1024)}.`,
      `The file size is ${formatSize(file.size)}, while the maximum allowed size is ${formatSize(maxFileSizeKb * 1024)}.`
    )
  }

  return ''
}

function isUploadSizeError(error: unknown): boolean {
  if (statusOf(error) === 413) return true
  return /content too large|payload too large|post data is too large|request entity too large/i
    .test(rawErrorText(error))
}

function uploadTooLargeMessage(): string {
  const maxFileSizeKb = effectiveMaxFileSizeKb.value
  if (maxFileSizeKb === null) {
    return localized(
      'حجم الملف أكبر من قدرة الخادم الحالية على الاستقبال.',
      "The file exceeds the server's current upload capacity."
    )
  }
  const limit = formatSize(maxFileSizeKb * 1024)
  return localized(
    `حجم الملف أكبر من الحد المسموح للرفع. اختر ملفًا أصغر من ${limit}.`,
    `The file exceeds the maximum upload size of ${limit}.`
  )
}

function normalizedUploadFieldError(error: unknown): string {
  const raw = firstFieldError(error, 'file')
  if (/حجم|size|large|exceed/i.test(raw)) return uploadTooLargeMessage()
  if (/نوع|mime|type|format|امتداد|extension/i.test(raw)) return text.value.invalidType
  return localized(
    'تعذر قبول الملف. تحقق من نوعه وحجمه ثم حاول مرة أخرى.',
    'The file could not be accepted. Check its type and size, then try again.'
  )
}

function uploadSystemError(error: unknown): string {
  const status = statusOf(error)
  if (status === 401) return localized('انتهت جلسة الإدارة. سجّل الدخول مجددًا.', 'Your admin session has expired. Sign in again.')
  if (status === 403) return text.value.permissionDisabled
  if (status === 409) {
    return conflictMessage(error) || localized('تعذر رفع الوسيط بسبب تغير حالة العمل.', 'The media could not be uploaded because the work state changed.')
  }
  return localized('تعذر الاتصال بخدمة رفع الوسائط.', 'Could not connect to the media upload service.')
}

function rawErrorText(error: unknown): string {
  const candidate = (error as any)?.data
    ?? (error as any)?.response?._data
    ?? (error as any)?.message
    ?? ''
  if (typeof candidate === 'string') return candidate.slice(0, 2000)
  try {
    return JSON.stringify(candidate).slice(0, 2000)
  } catch {
    return ''
  }
}

function startDrag(id: number) {
  if (canOrganize.value && !busy.value) draggedId.value = id
}

function dropOn(targetId: number) {
  if (draggedId.value === null || draggedId.value === targetId || busy.value) return
  const from = localMedia.value.findIndex(item => item.id === draggedId.value)
  const to = localMedia.value.findIndex(item => item.id === targetId)
  if (from < 0 || to < 0) return
  const next = [...localMedia.value]
  const [moved] = next.splice(from, 1)
  if (moved) next.splice(to, 0, moved)
  localMedia.value = next
  draggedId.value = null
}

function move(index: number, delta: number) {
  const target = index + delta
  if (target < 0 || target >= localMedia.value.length || busy.value) return
  const next = [...localMedia.value]
  const current = next[index]!
  next[index] = next[target]!
  next[target] = current
  localMedia.value = next
}

function cancelOrder() {
  const byId = new Map(localMedia.value.map(item => [item.id, item]))
  localMedia.value = serverOrder.value.flatMap(id => {
    const item = byId.get(id)
    return item ? [item] : []
  })
  message.value = localized('أُلغي الترتيب المحلي.', 'Local order was discarded.')
  messageTone.value = 'info'
}

async function saveOrder() {
  if (!orderDirty.value || ordering.value) return
  ordering.value = true
  message.value = ''
  showReload.value = false
  try {
    const response = await apiFetch<ApiResponse<OrganizationData>>(
      `/admin/works/${props.work.id}/media/order`,
      { method: 'PATCH', body: { media_ids: localMedia.value.map(item => item.id) } }
    )
    applyOrganization(response.data)
    message.value = response.message || localized(
      response.data.changed ? 'تم حفظ الترتيب.' : 'الترتيب محدث بالفعل.',
      response.data.changed ? 'Order saved.' : 'Order is already up to date.'
    )
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || localized('تعذر حفظ ترتيب الوسائط.', 'Media order could not be saved.')
    messageTone.value = 'error'
  } finally {
    ordering.value = false
  }
}

async function updateCover(coverMediaId: number | null) {
  if (covering.value || actionsDisabled.value) return
  covering.value = true
  message.value = ''
  showReload.value = false
  try {
    const response = await apiFetch<ApiResponse<OrganizationData>>(
      `/admin/works/${props.work.id}/media/cover`,
      { method: 'PATCH', body: { cover_media_id: coverMediaId } }
    )
    applyOrganization(response.data)
    message.value = response.message || localized('تم تحديث الغلاف.', 'Cover updated.')
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || localized('تعذر تحديث الغلاف.', 'Cover update failed.')
    messageTone.value = 'error'
  } finally {
    covering.value = false
  }
}

function openRemoveDialog(item: MediaItem, anchor: HTMLElement) {
  if (deletingId.value !== null || actionsDisabled.value) return
  pendingRemoval.value = item
  removeDialogAnchor.value = anchor
}

function closeRemoveDialog() {
  if (deletingId.value !== null) return
  pendingRemoval.value = null
}

async function confirmRemoval() {
  const item = pendingRemoval.value
  if (!item || deletingId.value !== null || actionsDisabled.value) return
  deletingId.value = item.id
  message.value = ''
  showReload.value = false
  const removedIndex = localMedia.value.findIndex(entry => entry.id === item.id)
  try {
    const response = await apiFetch<ApiResponse<{
      deleted_media_id: number
      cover_cleared: boolean
      counts: Counts
    }>>(`/admin/works/${props.work.id}/media/${item.id}`, { method: 'DELETE' })
    revokePreview(item.id)
    const media = localMedia.value.filter(entry => entry.id !== item.id)
    localMedia.value = media
    serverOrder.value = media.map(entry => entry.id)
    selectedMediaId.value = media[Math.min(Math.max(removedIndex, 0), media.length - 1)]?.id ?? null
    message.value = localized('تمت إزالة الوسيط من العمل.', 'Media was removed from the work.')
    messageTone.value = 'success'
    emit('stateChange', {
      work: response.data.cover_cleared ? { ...props.work, cover_media_id: null } : undefined,
      media,
      counts: response.data.counts
    })
    pendingRemoval.value = null
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || localized('تعذرت إزالة الوسيط.', 'Media could not be removed.')
    messageTone.value = 'error'
  } finally {
    deletingId.value = null
  }
}

function applyOrganization(data: OrganizationData) {
  localMedia.value = data.media.map(item => ({ ...item }))
  serverOrder.value = data.media.map(item => item.id)
  if (!data.media.some(item => item.id === selectedMediaId.value)) {
    selectedMediaId.value = data.media[0]?.id ?? null
  }
  emit('stateChange', {
    work: data.work,
    media: data.media,
    media_policy: data.media_policy,
    counts: data.counts
  })
}

async function reloadMedia() {
  showReload.value = false
  try {
    const response = await apiFetch<ApiResponse<{
      work: MediaWork
      media: MediaItem[]
      media_policy: MediaPolicy
      counts?: Counts
    }>>(`/admin/works/${props.work.id}/media`)
    const counts = response.data.counts || {
      active: response.data.media.length,
      remaining: response.data.media_policy.effective_limits.max_items === null
        ? null
        : Math.max(0, response.data.media_policy.effective_limits.max_items - response.data.media.length)
    }
    emit('stateChange', {
      work: response.data.work,
      media: response.data.media,
      media_policy: response.data.media_policy,
      counts
    })
    message.value = response.message || localized('تمت مزامنة الوسائط.', 'Media synchronized.')
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    message.value = serverMessage(error) || localized('تعذرت إعادة التحميل.', 'Reload failed.')
    messageTone.value = 'error'
  }
}

async function refreshPreviews(media: MediaItem[]) {
  if (!import.meta.client || authorizationLost.value) return
  const revision = ++previewRevision.value
  const activeIds = new Set(media.map(item => item.id))

  previewControllers.forEach(controller => controller.abort())
  previewControllers.clear()
  Object.keys(previewUrls.value).map(Number).forEach(id => {
    if (!activeIds.has(id)) revokePreview(id)
  })
  media.forEach(item => {
    const signature = `${item.content_endpoint}|${item.updated_at ?? ''}|${item.processing_status}`
    if (previewUrls.value[item.id] && previewSignatures.value[item.id] !== signature) {
      revokePreview(item.id)
    }
  })
  const nextErrors = { ...previewErrors.value }
  Object.keys(nextErrors).map(Number).forEach(id => {
    if (!activeIds.has(id)) delete nextErrors[id]
  })
  previewErrors.value = nextErrors

  await Promise.all(media.map(async item => {
    if (previewUrls.value[item.id] || previewControllers.has(item.id)) return
    const controller = new AbortController()
    previewControllers.set(item.id, controller)
    try {
      const blob = await $fetch<Blob>(`${apiOrigin}${item.content_endpoint}`, {
        responseType: 'blob',
        headers: authenticatedHeaders(),
        signal: controller.signal
      })
      if (controller.signal.aborted || revision !== previewRevision.value || !activeIds.has(item.id)) return
      const url = URL.createObjectURL(blob)
      if (controller.signal.aborted || revision !== previewRevision.value || !activeIds.has(item.id)) {
        URL.revokeObjectURL(url)
        return
      }
      previewUrls.value = { ...previewUrls.value, [item.id]: url }
      previewSignatures.value = {
        ...previewSignatures.value,
        [item.id]: `${item.content_endpoint}|${item.updated_at ?? ''}|${item.processing_status}`
      }
      const errors = { ...previewErrors.value }
      delete errors[item.id]
      previewErrors.value = errors
    } catch (error: unknown) {
      if (controller.signal.aborted) return
      handleAuth(error)
      if (revision === previewRevision.value) {
        previewErrors.value = { ...previewErrors.value, [item.id]: true }
      }
    } finally {
      if (previewControllers.get(item.id) === controller) previewControllers.delete(item.id)
    }
  }))
}

async function retryPreview(item: MediaItem) {
  revokePreview(item.id)
  await refreshPreviews(localMedia.value)
}

function revokePreview(id: number) {
  previewControllers.get(id)?.abort()
  previewControllers.delete(id)
  const url = previewUrls.value[id]
  if (url) URL.revokeObjectURL(url)
  const urls = { ...previewUrls.value }
  delete urls[id]
  previewUrls.value = urls
  const errors = { ...previewErrors.value }
  delete errors[id]
  previewErrors.value = errors
  const signatures = { ...previewSignatures.value }
  delete signatures[id]
  previewSignatures.value = signatures
}

function revokeAllPreviews() {
  previewRevision.value++
  previewControllers.forEach(controller => controller.abort())
  previewControllers.clear()
  Object.keys(previewUrls.value).map(Number).forEach(revokePreview)
}

function authenticatedHeaders(): Record<string, string> {
  const headers: Record<string, string> = { Accept: 'application/json' }
  if (tokenCookie.value) headers.Authorization = `Bearer ${tokenCookie.value}`
  return headers
}

function statusOf(error: unknown): number | null {
  return Number((error as any)?.response?.status ?? (error as any)?.statusCode) || null
}
function serverMessage(error: unknown): string {
  return (error as any)?.data?.message || (error as any)?.response?._data?.message || ''
}
function firstFieldError(error: unknown, field: string): string {
  const errors = (error as any)?.data?.errors || (error as any)?.response?._data?.errors
  const value = errors?.[field]
  return Array.isArray(value) ? String(value[0] || '') : ''
}
function conflictMessage(error: unknown): string {
  if (statusOf(error) !== 409) return ''
  const data = (error as any)?.data?.data || (error as any)?.response?._data?.data
  const reasons: Record<string, string> = props.locale === 'ar' ? {
    work_state_not_editable: 'حالة العمل الحالية لا تسمح بتعديل الوسائط.',
    media_type_required: 'اختر نمط الوسائط واحفظه قبل الرفع.',
    media_type_not_allowed: 'نمط الوسائط غير مسموح في الإعدادات الحالية.',
    media_items_limit_reached: 'بلغ العمل الحد الأقصى للوسائط.'
  } : {
    work_state_not_editable: 'The current work state does not allow media changes.',
    media_type_required: 'Choose and save a media type before uploading.',
    media_type_not_allowed: 'The media type is not allowed by current settings.',
    media_items_limit_reached: 'The work has reached its media limit.'
  }
  return reasons[String(data?.reason)] || ''
}
function handleAuth(error: unknown) {
  const status = statusOf(error)
  if (status === 401) {
    revokeAllPreviews()
    authStore.clearAuth()
  }
  if (status === 403) {
    authorizationLost.value = true
    revokeAllPreviews()
  }
}
function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${formatYmNumber(Math.max(1, Math.round(bytes / 1024)), props.locale)} KB`
  }
  if (bytes < 1024 * 1024 * 1024) {
    return `${formatYmNumber(bytes / (1024 * 1024), props.locale, { maximumFractionDigits: 1 })} MB`
  }
  return `${formatYmNumber(bytes / (1024 * 1024 * 1024), props.locale, { maximumFractionDigits: 2 })} GB`
}
function localized(arabic: string, english: string): string {
  return props.locale === 'ar' ? arabic : english
}

onBeforeUnmount(revokeAllPreviews)
</script>

<style scoped>
.ym-media-manager{--ym-media-violet:#7c3aed;--ym-media-electric:#8b5cf6;--ym-media-magenta:#ec4899;--ym-media-cyan:#22d3ee;--ym-media-emerald:#10b981;--ym-media-amber:#f59e0b;--ym-media-rose:#f43f5e;--ym-media-muted:#a7b2c7;display:grid;gap:18px;min-width:0;color:#eef2ff}.ym-media-manager__head{position:relative;display:flex;align-items:flex-start;justify-content:space-between;gap:20px;overflow:hidden;padding:20px;border:1px solid rgba(139,92,246,.25);border-radius:18px;background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(34,211,238,.045));box-shadow:inset 0 1px rgba(255,255,255,.08),0 18px 45px rgba(2,6,23,.18)}.ym-media-manager__head::before{content:"";position:absolute;inset-block-start:0;inset-inline:18px;height:1px;background:linear-gradient(90deg,transparent,#8b5cf6,#22d3ee,transparent)}.ym-media-manager__heading{display:flex;gap:14px;min-width:0}.ym-media-manager__head-icon{display:grid;place-items:center;width:48px;height:48px;flex:0 0 auto;border:1px solid rgba(139,92,246,.35);border-radius:15px;background:rgba(124,58,237,.13);color:#c4b5fd}.ym-media-manager__head-icon svg{width:27px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.ym-media-manager__heading p,.ym-media-manager__panel-head p{margin:0;color:#22d3ee;font-size:12.5px;font-weight:900}.ym-media-manager__heading h2{margin:3px 0 4px;font-size:clamp(21px,2vw,27px);line-height:1.25}.ym-media-manager__heading>div>span{color:var(--ym-media-muted);font-size:13.5px;line-height:1.65}.ym-media-manager__capacity{display:grid;gap:7px;width:min(310px,34%);min-width:225px;padding:13px;border:1px solid rgba(148,163,184,.15);border-radius:14px;background:rgba(2,6,23,.18)}.ym-media-manager__capacity>div:first-child{display:flex;justify-content:space-between;gap:10px}.ym-media-manager__capacity strong{font-size:14px;font-variant-numeric:tabular-nums}.ym-media-manager__capacity span,.ym-media-manager__capacity small{color:var(--ym-media-muted);font-size:12px}.ym-media-manager__progress{height:7px;overflow:hidden;border-radius:999px;background:rgba(148,163,184,.16)}.ym-media-manager__progress i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#7c3aed,#ec4899,#22d3ee);transition:inline-size .2s ease}.ym-media-manager__message,.ym-media-manager__readonly{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 14px;border:1px solid rgba(59,130,246,.2);border-radius:13px;background:rgba(59,130,246,.09);color:#bfdbfe;font-size:13.5px}.ym-media-manager__message.is-success{border-color:rgba(16,185,129,.25);background:rgba(16,185,129,.08);color:#6ee7b7}.ym-media-manager__message.is-error{border-color:rgba(244,63,94,.28);background:rgba(244,63,94,.08);color:#fda4af}.ym-media-manager__message button{min-height:40px;border:1px solid currentColor;border-radius:10px;background:transparent;color:inherit;font-weight:800}.ym-media-manager__readonly{align-items:flex-start;justify-content:flex-start;border-color:rgba(245,158,11,.25);background:rgba(245,158,11,.08);color:#fcd34d}.ym-media-manager__readonly span{color:var(--ym-media-muted)}.ym-media-manager__empty{display:grid;justify-items:center;gap:8px;padding:32px 20px;border:1px dashed rgba(139,92,246,.28);border-radius:17px;background:rgba(124,58,237,.035);text-align:center}.ym-media-manager__empty>span{font-size:38px;color:#8b5cf6}.ym-media-manager__empty strong{font-size:17px}.ym-media-manager__empty p{max-width:520px;margin:0;color:var(--ym-media-muted);font-size:13.5px}.ym-media-manager__empty button{min-height:44px;margin-top:5px;padding:0 18px;border:1px solid rgba(139,92,246,.36);border-radius:11px;background:linear-gradient(135deg,#7c3aed,#ec4899);color:#fff;font-weight:850}.ym-media-manager__console{display:grid;grid-template-columns:minmax(250px,31%) minmax(0,1fr);gap:16px;align-items:start}.ym-media-manager__gallery-panel,.ym-media-manager__preview-panel{min-width:0;padding:16px;border:1px solid rgba(148,163,184,.17);border-radius:18px;background:rgba(15,23,42,.16);box-shadow:inset 0 1px rgba(255,255,255,.04)}.ym-media-manager__panel-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:13px}.ym-media-manager__panel-head h3{margin:2px 0 0;font-size:16px}.ym-media-manager__panel-head>span{display:grid;place-items:center;min-width:32px;height:32px;border-radius:10px;background:rgba(34,211,238,.1);color:#67e8f9;font-weight:900;font-variant-numeric:tabular-nums}.ym-media-manager__order{position:sticky;bottom:92px;z-index:3;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:14px 16px;border:1px solid rgba(139,92,246,.25);border-radius:15px;background:rgba(15,23,42,.9);box-shadow:0 -12px 34px rgba(2,6,23,.2);backdrop-filter:blur(10px)}.ym-media-manager__order>div:first-child{display:grid;gap:3px}.ym-media-manager__order strong{font-size:13.5px}.ym-media-manager__order span{color:var(--ym-media-muted);font-size:12.5px}.ym-media-manager__order-actions{display:flex;gap:8px}.ym-media-manager__order button{min-height:42px;padding:0 14px;border:1px solid rgba(139,92,246,.3);border-radius:10px;background:rgba(139,92,246,.08);color:inherit;font-weight:800}.ym-media-manager__order button.is-primary{background:linear-gradient(135deg,#7c3aed,#ec4899);color:#fff}.ym-media-manager button:focus-visible{outline:3px solid rgba(34,211,238,.34);outline-offset:3px}.ym-media-manager button:disabled{opacity:.44;cursor:not-allowed}.ym-media-manager.is-light{--ym-media-muted:#526178;color:#172033}.ym-media-manager.is-light .ym-media-manager__head{background:linear-gradient(135deg,rgba(255,255,255,.84),rgba(237,233,254,.7));box-shadow:inset 0 1px #fff,0 18px 45px rgba(76,29,149,.08)}.ym-media-manager.is-light .ym-media-manager__capacity,.ym-media-manager.is-light .ym-media-manager__gallery-panel,.ym-media-manager.is-light .ym-media-manager__preview-panel{background:rgba(255,255,255,.7);border-color:rgba(100,116,139,.2)}.ym-media-manager.is-light .ym-media-manager__order{background:rgba(255,255,255,.9);box-shadow:0 -12px 34px rgba(76,29,149,.1)}.ym-media-manager.is-light .ym-media-manager__message{color:#1d4ed8}.ym-media-manager.is-light .ym-media-manager__message.is-success{color:#047857}.ym-media-manager.is-light .ym-media-manager__message.is-error{color:#be123c}.ym-media-manager.is-light .ym-media-manager__readonly{color:#92400e}@media(max-width:980px){.ym-media-manager__console{grid-template-columns:1fr}.ym-media-manager__gallery-panel{order:0}.ym-media-manager__preview-panel{order:1}}@media(max-width:700px){.ym-media-manager{gap:15px}.ym-media-manager__head{align-items:stretch;flex-direction:column;padding:17px}.ym-media-manager__capacity{width:100%;min-width:0}.ym-media-manager__heading{align-items:flex-start}.ym-media-manager__head-icon{width:43px;height:43px}.ym-media-manager__gallery-panel,.ym-media-manager__preview-panel{padding:13px;border-radius:15px}.ym-media-manager__order{bottom:150px;align-items:stretch;flex-direction:column}.ym-media-manager__order-actions{display:grid;grid-template-columns:1fr 1fr}.ym-media-manager__order button{min-height:44px}}@media(max-width:420px){.ym-media-manager__heading{display:grid}.ym-media-manager__message{align-items:stretch;flex-direction:column}.ym-media-manager__order-actions{grid-template-columns:1fr}}@media(prefers-reduced-motion:reduce){.ym-media-manager *{scroll-behavior:auto!important;transition:none!important}.ym-media-manager__progress i{transition:none}}
.ym-media-manager__console.is-single-media{
  grid-template-columns:minmax(0,1fr);
  justify-items:center;
  inline-size:100%;
}
.ym-media-manager__console.is-single-media > .ym-media-manager__preview-panel{
  grid-column:1 / -1;
  min-inline-size:0;
  inline-size:min(100%,1120px);
  max-inline-size:1120px;
  margin-inline:auto;
}
.ym-media-manager__console.is-single-media :deep(.ym-media-preview){
  min-inline-size:0;
  inline-size:100%;
}
.ym-media-manager__single-summary{
  grid-template-columns:minmax(0,1fr)!important;
  min-inline-size:0;
  inline-size:100%;
  max-inline-size:none;
  margin-block-end:14px;
}
.ym-media-manager__single-summary :deep(.ym-media-thumb){grid-template-columns:minmax(0,1fr);padding:10px}
.ym-media-manager__single-summary :deep(.ym-media-thumb__select){grid-template-columns:96px minmax(0,1fr)}
</style>
