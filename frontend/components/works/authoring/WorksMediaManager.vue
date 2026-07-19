<template>
  <section
    class="ym-media-manager"
    :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
    aria-labelledby="ym-media-manager-title"
  >
    <header class="ym-media-manager__head">
      <div>
        <p>{{ text.kicker }}</p>
        <h2 id="ym-media-manager-title">{{ text.title }}</h2>
        <span>{{ text.copy }}</span>
      </div>
      <dl>
        <div><dt>{{ text.used }}</dt><dd>{{ counts.active }}</dd></div>
        <div><dt>{{ text.remaining }}</dt><dd>{{ counts.remaining ?? text.unlimited }}</dd></div>
      </dl>
    </header>

    <div v-if="message" class="ym-media-manager__message" :class="`is-${messageTone}`" aria-live="polite">
      {{ message }}
      <button v-if="showReload" type="button" @click="reloadMedia">
        {{ props.locale === 'ar' ? 'إعادة تحميل الوسائط' : 'Reload media' }}
      </button>
    </div>

    <form v-if="canUpdateMedia" class="ym-media-manager__upload" @submit.prevent="upload">
      <label>
        <span>{{ text.file }}</span>
        <input
          ref="fileInput"
          type="file"
          name="file"
          :accept="accept"
          :disabled="actionsDisabled || uploading || counts.remaining === 0"
          :aria-invalid="Boolean(fileError)"
          @change="selectFile"
        />
        <small>
          {{ text.oneFile }}
          <template v-if="mediaPolicy.effective_limits.max_file_size_kb !== null">
            {{ text.limit }} {{ formatSize(mediaPolicy.effective_limits.max_file_size_kb * 1024) }}.
          </template>
        </small>
        <em v-if="fileError" role="alert">{{ fileError }}</em>
      </label>
      <button type="submit" :disabled="actionsDisabled || uploading || !selectedFile || counts.remaining === 0">
        {{ uploading ? text.uploading : text.upload }}
      </button>
    </form>

    <aside v-if="!editable" class="ym-media-manager__readonly" role="note">
      {{ text.readonly }}
    </aside>

    <div v-if="localMedia.length === 0" class="ym-media-manager__empty">
      <strong>{{ text.empty }}</strong>
      <p>{{ text.emptyCopy }}</p>
    </div>

    <template v-else>
      <div class="ym-media-manager__grid" aria-live="polite">
        <article
          v-for="(item, index) in localMedia"
          :key="item.id"
          class="ym-media-card"
          :class="{ 'is-cover': item.is_cover, 'is-dragging': draggedId === item.id }"
          :draggable="canOrganize"
          @dragstart="startDrag(item.id)"
          @dragover.prevent
          @drop="dropOn(item.id)"
          @dragend="draggedId = null"
        >
          <div class="ym-media-card__preview">
            <img
              v-if="item.kind === 'image' && previewUrls[item.id]"
              :src="previewUrls[item.id]"
              :alt="`${text.preview} ${item.original_name}`"
            />
            <video
              v-else-if="item.kind === 'video' && previewUrls[item.id]"
              :src="previewUrls[item.id]"
              controls
              preload="metadata"
            />
            <div v-else>
              <span aria-hidden="true">{{ previewErrors[item.id] ? '!' : '…' }}</span>
              <small>{{ previewErrors[item.id] ? text.previewFailed : text.previewLoading }}</small>
            </div>
            <b v-if="item.is_cover">{{ text.currentCover }}</b>
          </div>

          <div class="ym-media-card__body">
            <strong>{{ item.original_name }}</strong>
            <span>{{ item.mime_type }} · {{ formatSize(item.size_bytes) }}</span>
            <small>{{ text.position }} {{ index + 1 }} · {{ processingLabel(item.processing_status) }}</small>
          </div>

          <div v-if="canOrganize" class="ym-media-card__move" aria-label="تغيير ترتيب الوسيط">
            <button type="button" :disabled="index === 0 || busy" @click="move(index, -1)">{{ text.up }}</button>
            <button type="button" :disabled="index === localMedia.length - 1 || busy" @click="move(index, 1)">{{ text.down }}</button>
          </div>

          <div v-if="canUpdateMedia && editable" class="ym-media-card__actions">
            <button
              v-if="item.kind === 'image' && item.processing_status === 'ready' && !item.is_cover"
              type="button"
              :disabled="busy || actionsDisabled"
              @click="updateCover(item.id)"
            >
              {{ text.setCover }}
            </button>
            <button type="button" class="is-danger" :disabled="busy || actionsDisabled" @click="remove(item)">
              {{ text.delete }}
            </button>
          </div>
        </article>
      </div>

      <footer v-if="canOrganize" class="ym-media-manager__order">
        <span>{{ orderChangedCount }} {{ text.pendingOrder }}</span>
        <div>
          <button type="button" :disabled="!orderDirty || busy" @click="cancelOrder">{{ text.cancelOrder }}</button>
          <button type="button" :disabled="!orderDirty || busy" @click="saveOrder">
            {{ ordering ? text.saving : text.saveOrder }}
          </button>
        </div>
      </footer>

      <button
        v-if="work.cover_media_id !== null && canUpdateMedia && editable"
        type="button"
        class="ym-media-manager__clear-cover"
        :disabled="busy || actionsDisabled"
        @click="updateCover(null)"
      >
        {{ text.clearCover }}
      </button>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

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
const selectedFile = ref<File | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const ordering = ref(false)
const covering = ref(false)
const deletingId = ref<number | null>(null)
const draggedId = ref<number | null>(null)
const previewUrls = ref<Record<number, string>>({})
const previewErrors = ref<Record<number, boolean>>({})
const previewRevision = ref(0)
const fileError = ref('')
const message = ref('')
const messageTone = ref<'success' | 'error' | 'info'>('info')
const authorizationLost = ref(false)
const showReload = ref(false)
const text = computed(() => props.locale === 'ar' ? {
  kicker:'وسائط خاصة ومحمية',title:'إدارة وسائط العمل',copy:'تُجلب المعاينات عبر جلسة الإدارة، ولا تُعرض مسارات التخزين.',
  used:'المستخدم',remaining:'المتبقي',unlimited:'دون حد',file:'ملف الوسيط',oneFile:'ملف واحد لكل طلب.',limit:'الحد',
  uploading:'جارٍ الرفع…',upload:'رفع الملف',readonly:'حالة العمل الحالية تسمح بعرض الوسائط فقط، ولا تسمح بتنظيمها أوتعديلها.',
  empty:'لا توجد وسائط فعالة',emptyCopy:'ارفع أول ملف بعد اختيار نمط الوسائط المناسب.',preview:'معاينة',previewFailed:'تعذرت المعاينة',
  previewLoading:'جارٍ تحميل المعاينة',currentCover:'الغلاف الحالي',position:'الموضع',up:'أعلى',down:'أسفل',setCover:'تعيين كغلاف',
  delete:'حذف منطقي',pendingOrder:'تغييرات ترتيب معلقة',cancelOrder:'إلغاء الترتيب',saving:'جارٍ الحفظ…',saveOrder:'حفظ الترتيب',
  clearCover:'إزالة الغلاف الحالي'
} : {
  kicker:'Private protected media',title:'Work media manager',copy:'Previews are fetched through the admin session; storage paths are never exposed.',
  used:'Used',remaining:'Remaining',unlimited:'Unlimited',file:'Media file',oneFile:'One file per request.',limit:'Limit',
  uploading:'Uploading…',upload:'Upload file',readonly:'The current work state allows media viewing only, not organization or changes.',
  empty:'No active media',emptyCopy:'Upload the first file after selecting the appropriate media type.',preview:'Preview',previewFailed:'Preview failed',
  previewLoading:'Loading preview',currentCover:'Current cover',position:'Position',up:'Up',down:'Down',setCover:'Set as cover',
  delete:'Soft delete',pendingOrder:'pending order changes',cancelOrder:'Cancel order',saving:'Saving…',saveOrder:'Save order',
  clearCover:'Remove current cover'
})

const busy = computed(() => uploading.value || ordering.value || covering.value || deletingId.value !== null)
const actionsDisabled = computed(() => !props.editable || !props.canUpdateMedia || authorizationLost.value)
const canOrganize = computed(() => !authorizationLost.value && props.editable && props.canUpdateMedia && localMedia.value.length > 1)
const accept = computed(() => props.mediaPolicy.allowed_mime_types.join(','))
const orderDirty = computed(() => localMedia.value.some((item, index) => item.id !== serverOrder.value[index]))
const orderChangedCount = computed(() => localMedia.value.reduce(
  (count, item, index) => count + (item.id === serverOrder.value[index] ? 0 : 1),
  0
))

watch(orderDirty, dirty => emit('orderDirtyChange', dirty), { immediate: true })

watch(
  () => props.media,
  async media => {
    localMedia.value = media.map(item => ({ ...item }))
    serverOrder.value = media.map(item => item.id)
    await refreshPreviews(media)
  },
  { immediate: true, deep: true }
)

function selectFile(event: Event) {
  const input = event.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
  fileError.value = ''
}

async function upload() {
  if (!selectedFile.value || actionsDisabled.value || uploading.value) return
  uploading.value = true
  fileError.value = ''
  message.value = ''
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
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    message.value = response.message || 'تم رفع الوسيط بنجاح.'
    messageTone.value = 'success'
    emit('stateChange', {
      media,
      media_policy: response.data.media_policy,
      counts: response.data.counts
    })
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    fileError.value = statusOf(error) === 422
      ? firstFieldError(error, 'file') || 'تحقق من نوع الملف وحجمه.'
      : serverMessage(error) || conflictMessage(error) || 'تعذر رفع الوسيط.'
    if (statusOf(error) !== 422) message.value = fileError.value
    messageTone.value = 'error'
  } finally {
    uploading.value = false
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
  const byId = new Map(props.media.map(item => [item.id, item]))
  localMedia.value = serverOrder.value.flatMap(id => {
    const item = byId.get(id)
    return item ? [{ ...item }] : []
  })
  message.value = props.locale === 'ar' ? 'أُلغي الترتيب المحلي.' : 'Local order was discarded.'
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
    message.value = response.message || (response.data.changed ? 'تم حفظ الترتيب.' : 'الترتيب محدث بالفعل.')
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || 'تعذر حفظ ترتيب الوسائط.'
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
    message.value = response.message || 'تم تحديث الغلاف.'
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || 'تعذر تحديث الغلاف.'
    messageTone.value = 'error'
  } finally {
    covering.value = false
  }
}

async function remove(item: MediaItem) {
  if (!import.meta.client || deletingId.value !== null || actionsDisabled.value) return
  const confirmed = window.confirm(props.locale === 'ar'
    ? `هل تريد حذف "${item.original_name}" منطقيًا؟ سيبقى الملف الفيزيائي محفوظًا حسب العقد الحالي.`
    : `Soft-delete "${item.original_name}"? The physical file will be retained under the current contract.`)
  if (!confirmed) return
  deletingId.value = item.id
  message.value = ''
  showReload.value = false
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
    message.value = response.message || 'تم حذف الوسيط منطقيًا.'
    messageTone.value = 'success'
    emit('stateChange', {
      work: response.data.cover_cleared ? { ...props.work, cover_media_id: null } : undefined,
      media,
      counts: response.data.counts
    })
  } catch (error: unknown) {
    handleAuth(error)
    showReload.value = statusOf(error) === 409
    message.value = serverMessage(error) || conflictMessage(error) || 'تعذر حذف الوسيط.'
    messageTone.value = 'error'
  } finally {
    deletingId.value = null
  }
}

function applyOrganization(data: OrganizationData) {
  localMedia.value = data.media.map(item => ({ ...item }))
  serverOrder.value = data.media.map(item => item.id)
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
    message.value = response.message || (props.locale === 'ar' ? 'تمت مزامنة الوسائط.' : 'Media synchronized.')
    messageTone.value = 'success'
  } catch (error: unknown) {
    handleAuth(error)
    message.value = serverMessage(error) || (props.locale === 'ar' ? 'تعذرت إعادة التحميل.' : 'Reload failed.')
    messageTone.value = 'error'
  }
}

async function refreshPreviews(media: MediaItem[]) {
  const revision = ++previewRevision.value
  const activeIds = new Set(media.map(item => item.id))
  Object.keys(previewUrls.value).map(Number).forEach(revokePreview)
  previewErrors.value = {}
  for (const item of media) {
    if (previewUrls.value[item.id]) continue
    try {
      const blob = await $fetch<Blob>(`${apiOrigin}${item.content_endpoint}`, {
        responseType: 'blob',
        headers: authenticatedHeaders()
      })
      if (revision !== previewRevision.value || !activeIds.has(item.id)) continue
      previewUrls.value = { ...previewUrls.value, [item.id]: URL.createObjectURL(blob) }
    } catch (error: unknown) {
      handleAuth(error)
      if (revision === previewRevision.value) {
        previewErrors.value = { ...previewErrors.value, [item.id]: true }
      }
    }
  }
}

function revokePreview(id: number) {
  const url = previewUrls.value[id]
  if (url) URL.revokeObjectURL(url)
  const next = { ...previewUrls.value }
  delete next[id]
  previewUrls.value = next
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
  const reasons: Record<string, string> = {
    work_state_not_editable: 'حالة العمل الحالية لا تسمح بتعديل الوسائط.',
    media_type_required: 'اختر نمط الوسائط واحفظه قبل الرفع.',
    media_type_not_allowed: 'نمط الوسائط غير مسموح في الإعدادات الحالية.',
    media_items_limit_reached: 'بلغ العمل الحد الأقصى للوسائط.'
  }
  return reasons[String(data?.reason)] || ''
}
function handleAuth(error: unknown) {
  if (statusOf(error) === 401) authStore.clearAuth()
  if (statusOf(error) === 403) authorizationLost.value = true
}
function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
function processingLabel(status: MediaItem['processing_status']): string {
  if (props.locale === 'en') return status === 'ready' ? 'Ready' : status === 'pending' ? 'Pending' : 'Failed'
  return status === 'ready' ? 'جاهز' : status === 'pending' ? 'قيد المعالجة' : 'فشل المعالجة'
}

onBeforeUnmount(() => {
  previewRevision.value++
  Object.keys(previewUrls.value).map(Number).forEach(revokePreview)
})
</script>

<style scoped>
.ym-media-manager{display:grid;gap:1.2rem}.ym-media-manager__head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.ym-media-manager__head p{margin:0;color:#f59e0b;font-weight:900;font-size:.78rem}.ym-media-manager__head h2{margin:.2rem 0;font-size:1.35rem}.ym-media-manager__head span{color:var(--ym-muted,#94a3b8)}.ym-media-manager__head dl{display:flex;gap:.6rem;margin:0}.ym-media-manager__head dl div{display:grid;min-width:80px;padding:.6rem;border:1px solid rgba(148,163,184,.2);border-radius:14px;text-align:center}.ym-media-manager__head dt{font-size:.72rem;color:#94a3b8}.ym-media-manager__head dd{margin:0;font-weight:900}.ym-media-manager__message,.ym-media-manager__readonly{padding:.8rem 1rem;border-radius:14px;background:rgba(59,130,246,.1);color:#93c5fd}.ym-media-manager__message.is-success{background:rgba(16,185,129,.1);color:#34d399}.ym-media-manager__message.is-error{background:rgba(239,68,68,.1);color:#fca5a5}.ym-media-manager__upload{display:flex;align-items:end;gap:1rem;padding:1rem;border:1px dashed rgba(245,158,11,.4);border-radius:18px}.ym-media-manager__upload label{display:grid;gap:.35rem;flex:1}.ym-media-manager__upload span{font-weight:850}.ym-media-manager__upload small{color:#94a3b8}.ym-media-manager__upload em{color:#fca5a5;font-style:normal}.ym-media-manager button{border:1px solid rgba(245,158,11,.38);border-radius:12px;background:rgba(245,158,11,.12);color:inherit;padding:.65rem .85rem;font-weight:850}.ym-media-manager button:disabled{opacity:.45;cursor:not-allowed}.ym-media-manager button:focus-visible,.ym-media-manager input:focus-visible{outline:3px solid rgba(245,158,11,.42);outline-offset:2px}.ym-media-manager__empty{padding:2rem;text-align:center;border:1px dashed rgba(148,163,184,.25);border-radius:18px}.ym-media-manager__empty p{margin:.35rem 0 0;color:#94a3b8}.ym-media-manager__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}.ym-media-card{overflow:hidden;border:1px solid rgba(148,163,184,.22);border-radius:18px;background:rgba(15,23,42,.16)}.ym-media-card.is-cover{border-color:rgba(245,158,11,.65)}.ym-media-card.is-dragging{opacity:.55}.ym-media-card__preview{position:relative;display:grid;place-items:center;aspect-ratio:16/10;background:#020617;overflow:hidden}.ym-media-card__preview img,.ym-media-card__preview video{width:100%;height:100%;object-fit:contain}.ym-media-card__preview>div{display:grid;place-items:center;color:#94a3b8}.ym-media-card__preview b{position:absolute;inset-block-start:.6rem;inset-inline-start:.6rem;padding:.3rem .55rem;border-radius:999px;background:#f59e0b;color:#111827;font-size:.72rem}.ym-media-card__body{display:grid;gap:.25rem;padding:.8rem}.ym-media-card__body strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ym-media-card__body span,.ym-media-card__body small{color:#94a3b8;font-size:.75rem}.ym-media-card__move,.ym-media-card__actions{display:flex;gap:.45rem;padding:0 .8rem .8rem}.ym-media-card__move button,.ym-media-card__actions button{flex:1;font-size:.75rem}.ym-media-card__actions .is-danger{border-color:rgba(239,68,68,.35);background:rgba(239,68,68,.1);color:#fca5a5}.ym-media-manager__order{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1rem;border-radius:16px;background:rgba(148,163,184,.08)}.ym-media-manager__order div{display:flex;gap:.6rem}.ym-media-manager__clear-cover{justify-self:start}.ym-media-manager__readonly{color:#fcd34d;background:rgba(245,158,11,.1)}.ym-media-manager.is-light .ym-media-card{background:rgba(248,250,252,.9)}.ym-media-manager.is-light .ym-media-manager__message{color:#1d4ed8}.ym-media-manager.is-light .ym-media-manager__readonly{color:#92400e}@media(max-width:640px){.ym-media-manager__head,.ym-media-manager__upload,.ym-media-manager__order{align-items:stretch;flex-direction:column}.ym-media-manager__head dl{width:100%}.ym-media-manager__head dl div{flex:1}.ym-media-manager__order div{display:grid}.ym-media-manager__grid{grid-template-columns:1fr}}
</style>
