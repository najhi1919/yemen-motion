<template>
  <div
    class="ym-authoring"
    :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
    :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
  >
    <section class="ym-authoring__hero">
      <div>
        <p>Yemen Motion · {{ mode === 'create' ? copy.createKicker : copy.editKicker }}</p>
        <h1>{{ mode === 'create' ? copy.createTitle : copy.editTitle }}</h1>
        <span>{{ copy.heroCopy }}</span>
      </div>
      <NuxtLink to="/admin/works/all">{{ copy.back }}</NuxtLink>
    </section>

    <section v-if="loading" class="ym-authoring__state" role="status" aria-live="polite">
      <span class="ym-authoring__spinner" aria-hidden="true" />
      <h2>{{ copy.loading }}</h2>
      <p>{{ copy.loadingCopy }}</p>
    </section>

    <section v-else-if="accessError" class="ym-authoring__state is-error" role="alert">
      <strong>{{ accessError.status }}</strong>
      <h2>{{ accessError.title }}</h2>
      <p>{{ accessError.message }}</p>
      <button v-if="accessError.status !== 403" type="button" @click="loadWorkspace">{{ copy.retry }}</button>
    </section>

    <template v-else>
      <section v-if="mode === 'edit' && work" class="ym-authoring__status">
        <div><span>{{ copy.status }}</span><strong>{{ statusLabel(work.status) }}</strong></div>
        <div><span>{{ copy.slug }}</span><code dir="ltr">{{ work.slug }}</code></div>
        <div><span>{{ copy.lastUpdate }}</span><time>{{ formatDate(work.updated_at) }}</time></div>
      </section>

      <aside v-if="work?.status === 'changes_requested'" class="ym-authoring__changes" role="note">
        <strong>{{ copy.changesRequested }}</strong>
        <p>{{ work.change_request_notes || copy.noChangeNotes }}</p>
      </aside>

      <aside v-if="mode === 'edit' && !editable" class="ym-authoring__readonly" role="alert">
        <strong>{{ copy.readonlyTitle }}</strong>
        <p>{{ copy.readonlyCopy }}</p>
      </aside>

      <aside v-if="conflict" class="ym-authoring__conflict" role="alert">
        <div>
          <strong>{{ copy.conflictTitle }}</strong>
          <p>{{ conflict }}</p>
        </div>
        <button type="button" @click="reloadFromServer">{{ copy.reload }}</button>
      </aside>

      <div v-if="liveMessage" class="ym-authoring__live" :class="`is-${liveTone}`" aria-live="polite">
        {{ liveMessage }}
      </div>

      <section class="ym-authoring__card">
        <header>
          <div><p>{{ copy.dataKicker }}</p><h2>{{ copy.dataTitle }}</h2></div>
          <span>{{ dirtyFields.length }} {{ copy.pendingFields }}</span>
        </header>

        <div class="ym-authoring__form">
          <label class="is-wide">
            <span>{{ copy.fieldTitle }} *</span>
            <input
              v-model="draft.title"
              type="text"
              maxlength="160"
              required
              :disabled="!canEditBasic"
              :aria-invalid="Boolean(fieldErrors.title)"
            />
            <em v-if="fieldErrors.title">{{ fieldErrors.title }}</em>
          </label>

          <label class="is-wide">
            <span>{{ copy.summary }}</span>
            <textarea v-model="draft.summary" rows="3" maxlength="1000" :disabled="!canEditBasic" />
            <em v-if="fieldErrors.summary">{{ fieldErrors.summary }}</em>
          </label>

          <label class="is-wide">
            <span>{{ copy.description }}</span>
            <textarea v-model="draft.description" rows="9" maxlength="30000" :disabled="!canEditBasic" />
            <em v-if="fieldErrors.description">{{ fieldErrors.description }}</em>
          </label>

          <label>
            <span>{{ copy.mediaType }}</span>
            <select
              v-model="draft.media_type"
              :disabled="!canEditMediaType || mediaState.media.length > 0"
              :aria-describedby="mediaState.media.length > 0 ? 'ym-media-type-lock' : undefined"
            >
              <option :value="null">{{ copy.notSelected }}</option>
              <option v-for="type in policy.allowed_media_types" :key="type" :value="type">
                {{ mediaTypeLabel(type) }}
              </option>
            </select>
            <small v-if="mediaState.media.length > 0" id="ym-media-type-lock">
              {{ copy.mediaTypeLocked }}
            </small>
            <em v-if="fieldErrors.media_type">{{ fieldErrors.media_type }}</em>
          </label>

          <label v-if="fieldAccess.can_update_pricing">
            <span>{{ copy.price }}</span>
            <input v-model="draft.price_amount" type="text" inputmode="decimal" dir="ltr" :disabled="!editable" />
            <em v-if="fieldErrors.price_amount">{{ fieldErrors.price_amount }}</em>
          </label>

          <label v-if="fieldAccess.can_update_delivery">
            <span>{{ copy.delivery }}</span>
            <input v-model="draft.delivery_days" type="text" inputmode="numeric" dir="ltr" :disabled="!editable" />
            <em v-if="fieldErrors.delivery_days">{{ fieldErrors.delivery_days }}</em>
          </label>

          <div v-if="fieldAccess.can_update_designer" class="ym-authoring__designer">
            <label>
              <span>{{ copy.designer }}</span>
              <input
                v-model="designerQuery"
                type="search"
                autocomplete="off"
                :placeholder="copy.designerSearch"
                :disabled="!editable"
                @input="queueDesignerSearch"
              />
            </label>
            <div v-if="selectedDesigner" class="ym-authoring__designer-current">
              <span>{{ selectedDesigner.name }}</span>
              <button type="button" :disabled="!editable" @click="clearDesigner">{{ copy.clear }}</button>
            </div>
            <div v-if="designerLoading" class="ym-authoring__designer-state">{{ copy.searching }}</div>
            <div v-else-if="designerSearchError" class="ym-authoring__designer-state is-error">{{ designerSearchError }}</div>
            <ul v-else-if="designerOptions.length">
              <li v-for="designer in designerOptions" :key="designer.id">
                <button type="button" :disabled="!editable" @click="selectDesigner(designer)">
                  {{ designer.name }}
                </button>
              </li>
            </ul>
            <small v-else-if="designerQuery.trim().length >= 2">{{ copy.noDesigners }}</small>
            <em v-if="fieldErrors.designer_id">{{ fieldErrors.designer_id }}</em>
          </div>

          <label v-if="fieldAccess.can_update_private_notes" class="is-wide">
            <span>{{ copy.internalNotes }}</span>
            <textarea v-model="draft.internal_notes" rows="5" maxlength="10000" :disabled="!editable" />
            <em v-if="fieldErrors.internal_notes">{{ fieldErrors.internal_notes }}</em>
          </label>
        </div>

        <footer>
          <button type="button" class="is-secondary" :disabled="saving || !isDirty" @click="resetDraft">
            {{ copy.cancelChanges }}
          </button>
          <button type="button" :disabled="saving || !canSave" @click="save">
            {{ saving ? copy.saving : mode === 'create' ? copy.create : copy.save }}
          </button>
        </footer>
      </section>

      <section v-if="mode === 'edit' && (fieldAccess.can_assign_category || fieldAccess.can_assign_tags)" class="ym-authoring__card">
        <header><div><p>{{ copy.taxonomyKicker }}</p><h2>{{ copy.taxonomyTitle }}</h2></div></header>

        <div class="ym-authoring__taxonomy">
          <label v-if="fieldAccess.can_assign_category">
            <span>{{ copy.category }}</span>
            <select v-model="selectedCategoryId" :disabled="!editable || taxonomySaving">
              <option :value="null">{{ copy.removeCategory }}</option>
              <option v-for="item in categoryOptions" :key="item.id" :value="item.id">{{ taxonomyName(item) }}</option>
            </select>
            <button type="button" :disabled="!editable || taxonomySaving || selectedCategoryId === work?.category_id" @click="saveCategory">
              {{ copy.saveCategory }}
            </button>
          </label>

          <fieldset v-if="fieldAccess.can_assign_tags" :disabled="!editable || taxonomySaving">
            <legend>{{ copy.tags }}</legend>
            <label v-for="item in tagOptions" :key="item.id">
              <input
                v-model="selectedTagIds"
                type="checkbox"
                :value="item.id"
                :disabled="!item.is_active && !selectedTagIds.includes(item.id)"
              />
              <span>{{ taxonomyName(item) }}</span>
            </label>
            <button type="button" :disabled="!editable || taxonomySaving || !tagsDirty" @click="saveTags">
              {{ copy.saveTags }}
            </button>
          </fieldset>
        </div>
        <p v-if="taxonomyError" class="ym-authoring__field-error" role="alert">{{ taxonomyError }}</p>
      </section>

      <section v-if="mode === 'edit' && work && mediaState.policy" class="ym-authoring__card is-media">
        <WorksMediaManager
          :work="mediaWork"
          :media="mediaState.media"
          :media-policy="mediaState.policy"
          :counts="mediaState.counts"
          :can-update-media="fieldAccess.can_update_media"
          :editable="editable"
          :locale="currentLocale"
          @state-change="applyMediaState"
          @order-dirty-change="mediaOrderDirty = $event"
        />
      </section>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import WorksMediaManager from '~/components/works/authoring/WorksMediaManager.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

type Mode = 'create' | 'edit'
type Locale = 'ar' | 'en'
type NullableString = string | null
interface FieldAccess {
  can_create: boolean
  can_update_basic: boolean
  can_update_media: boolean
  can_update_pricing: boolean
  can_update_delivery: boolean
  can_update_designer: boolean
  can_update_private_notes: boolean
  can_assign_category: boolean
  can_assign_tags: boolean
}
interface AuthoringPolicy {
  source: string
  settings_version: number
  allowed_media_types: string[]
  media_limits: { max_items: number | null; max_file_size_kb: number | null }
  enforcement: Record<string, boolean>
}
interface Designer { id: number; name: string }
interface WorkData {
  id: number
  title: string
  slug: string
  summary: NullableString
  description: NullableString
  status: string
  visibility_status: string
  media_type: NullableString
  price_amount: NullableString
  delivery_days: number | null
  designer_id: number | null
  category_id: number | null
  tag_ids: number[]
  cover_media_id: number | null
  internal_notes?: NullableString
  change_request_notes?: NullableString
  created_at: NullableString
  updated_at: NullableString
}
interface Draft {
  title: string
  summary: string
  description: string
  media_type: string | null
  price_amount: string
  delivery_days: string
  designer_id: number | null
  internal_notes: string
}
interface TaxonomyEntity {
  id: number
  name_ar: string
  name_en: string
  slug: string
  is_active: boolean
}
interface MediaItem {
  id: number; kind: 'image' | 'video'; original_name: string; mime_type: string; extension: string
  size_bytes: number; size_kb: number; position: number; width: number | null; height: number | null
  duration_ms: number | null; processing_status: 'pending' | 'ready' | 'failed'; is_cover: boolean
  uploaded_by: Designer | null; created_at: string | null; updated_at: string | null; content_endpoint: string
}
interface MediaPolicy {
  source: string; settings_version: number; work_media_type: string | null; allowed_media_types: string[]
  allowed_file_kinds: string[]; allowed_mime_types: string[]
  configured_limits: { max_items: number | null; max_file_size_kb: number | null }
  effective_limits: { max_items: number | null; max_file_size_kb: number | null }
  enforcement: Record<string, boolean>
}
interface Counts { active: number; remaining: number | null }
interface ApiResponse<T> { success: boolean; data: T; message?: string; errors?: Record<string, string[]> | null }

const props = defineProps<{ mode: Mode; workId?: number }>()
const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const router = useRouter()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const loading = ref(true)
const saving = ref(false)
const taxonomySaving = ref(false)
const designerLoading = ref(false)
const designerSearchError = ref('')
const accessError = ref<{ status: number; title: string; message: string } | null>(null)
const conflict = ref('')
const liveMessage = ref('')
const liveTone = ref<'success' | 'error' | 'info'>('info')
const fieldErrors = reactive<Record<string, string>>({})
const work = ref<WorkData | null>(null)
const serverSnapshot = ref<Draft | null>(null)
const draft = reactive<Draft>(emptyDraft())
const fieldAccess = reactive<FieldAccess>(emptyAccess())
const policy = reactive<AuthoringPolicy>(emptyPolicy())
const editable = ref(props.mode === 'create')
const selectedDesigner = ref<Designer | null>(null)
const designerOptions = ref<Designer[]>([])
const designerQuery = ref('')
const designerTimer = ref<ReturnType<typeof setTimeout> | null>(null)
const categories = ref<TaxonomyEntity[]>([])
const tags = ref<TaxonomyEntity[]>([])
const selectedCategoryId = ref<number | null>(null)
const selectedTagIds = ref<number[]>([])
const taxonomyError = ref('')
const mediaOrderDirty = ref(false)
const mediaState = reactive<{ media: MediaItem[]; policy: MediaPolicy | null; counts: Counts }>({
  media: [],
  policy: null,
  counts: { active: 0, remaining: null }
})

const copyMap = {
  ar: {
    createKicker:'إنشاء مسودة',editKicker:'تحرير مسودة',createTitle:'إنشاء عمل جديد',editTitle:'مساحة تأليف العمل',
    heroCopy:'حرّر البيانات المصرح بها، ثم أدر التصنيف والوسائط من العقود الآمنة الحالية.',back:'العودة إلى كل الأعمال',
    loading:'جارٍ تجهيز مساحة التأليف',loadingCopy:'يتم التحقق من الصلاحيات وتحميل أحدث نسخة من الخادم.',retry:'إعادة المحاولة',
    status:'الحالة',slug:'المعرّف النصي',lastUpdate:'آخر تحديث',changesRequested:'تعديلات مطلوبة',noChangeNotes:'لم يرفق المراجع نصًا إضافيًا.',
    readonlyTitle:'وضع القراءة فقط',readonlyCopy:'حالة العمل الحالية لا تسمح بالتحرير. بقيت البيانات والوسائط متاحة للقراءة.',
    conflictTitle:'تعارض مع حالة الخادم',reload:'إعادة تحميل نسخة الخادم',dataKicker:'بيانات المسودة',dataTitle:'المحتوى والإسناد',pendingFields:'حقول متغيرة',
    fieldTitle:'العنوان',summary:'الملخص',description:'الوصف',mediaType:'نمط الوسائط',notSelected:'غير محدد',mediaTypeLocked:'احذف جميع الوسائط الفعالة قبل تغيير النمط.',
    price:'السعر',delivery:'مدة التسليم بالأيام',designer:'المصمم',designerSearch:'ابحث باسم المصمم',clear:'مسح',searching:'جارٍ البحث…',noDesigners:'لا توجد نتائج.',
    internalNotes:'ملاحظات داخلية',cancelChanges:'إلغاء التغييرات',saving:'جارٍ الحفظ…',create:'إنشاء المسودة',save:'حفظ البيانات',
    taxonomyKicker:'التصنيف الحالي',taxonomyTitle:'التصنيف والوسوم',category:'التصنيف',removeCategory:'دون تصنيف',saveCategory:'حفظ التصنيف',
    tags:'الوسوم النشطة',saveTags:'حفظ الوسوم'
  },
  en: {
    createKicker:'Create draft',editKicker:'Edit draft',createTitle:'Create a new work',editTitle:'Work authoring workspace',
    heroCopy:'Edit authorized fields, then manage taxonomy and media through the current safe contracts.',back:'Back to all works',
    loading:'Preparing the workspace',loadingCopy:'Checking permissions and loading the latest server snapshot.',retry:'Retry',
    status:'Status',slug:'Slug',lastUpdate:'Last update',changesRequested:'Changes requested',noChangeNotes:'No additional reviewer note was provided.',
    readonlyTitle:'Read-only mode',readonlyCopy:'The current work state does not allow editing. Data and media remain readable.',
    conflictTitle:'Server state conflict',reload:'Reload server version',dataKicker:'Draft data',dataTitle:'Content and assignment',pendingFields:'changed fields',
    fieldTitle:'Title',summary:'Summary',description:'Description',mediaType:'Media type',notSelected:'Not selected',mediaTypeLocked:'Delete all active media before changing the type.',
    price:'Price',delivery:'Delivery days',designer:'Designer',designerSearch:'Search designer by name',clear:'Clear',searching:'Searching…',noDesigners:'No results.',
    internalNotes:'Internal notes',cancelChanges:'Discard changes',saving:'Saving…',create:'Create draft',save:'Save data',
    taxonomyKicker:'Current taxonomy',taxonomyTitle:'Category and tags',category:'Category',removeCategory:'Uncategorized',saveCategory:'Save category',
    tags:'Active tags',saveTags:'Save tags'
  }
} as const
const copy = computed(() => copyMap[currentLocale.value])

const canEditBasic = computed(() => editable.value && (props.mode === 'create' || fieldAccess.can_update_basic))
const canEditMediaType = computed(() => editable.value && (props.mode === 'create' || fieldAccess.can_update_media))
const isDirty = computed(() => dirtyFields.value.length > 0)
const dirtyFields = computed<(keyof Draft)[]>(() => {
  if (!serverSnapshot.value) return props.mode === 'create' && draft.title.trim() ? ['title'] : []
  return (Object.keys(draft) as (keyof Draft)[]).filter(key => normalized(draft[key]) !== normalized(serverSnapshot.value?.[key]))
})
const canSave = computed(() => {
  if (!editable.value || !draft.title.trim()) return false
  if (props.mode === 'create') return fieldAccess.can_create
  return isDirty.value
})
const tagsDirty = computed(() => {
  const current = [...(work.value?.tag_ids || [])].sort((a,b) => a-b)
  const selected = [...selectedTagIds.value].sort((a,b) => a-b)
  return JSON.stringify(current) !== JSON.stringify(selected)
})
const categoryDirty = computed(() => (
  props.mode === 'edit'
  && work.value !== null
  && selectedCategoryId.value !== work.value.category_id
))
const hasUnsavedChanges = computed(() => (
  isDirty.value || tagsDirty.value || categoryDirty.value || mediaOrderDirty.value
))
const categoryOptions = computed(() => categories.value.filter(
  item => item.is_active || item.id === work.value?.category_id
))
const tagOptions = computed(() => tags.value.filter(
  item => item.is_active || (work.value?.tag_ids || []).includes(item.id)
))
const mediaWork = computed(() => ({
  id: work.value!.id,
  status: work.value!.status,
  media_type: work.value!.media_type,
  cover_media_id: work.value!.cover_media_id
}))

onMounted(async () => {
  if (!authStore.isInitialized) await authStore.hydrateAuth()
  await loadWorkspace()
  if (import.meta.client) window.addEventListener('beforeunload', beforeUnload)
})
onBeforeUnmount(() => {
  if (designerTimer.value) clearTimeout(designerTimer.value)
  if (import.meta.client) window.removeEventListener('beforeunload', beforeUnload)
})
onBeforeRouteLeave(() => {
  if (!hasUnsavedChanges.value || !import.meta.client) return true
  return window.confirm(currentLocale.value === 'ar' ? 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟' : 'You have unsaved changes. Leave this page?')
})

async function loadWorkspace() {
  loading.value = true
  accessError.value = null
  conflict.value = ''
  try {
    if (props.mode === 'create') {
      const response = await apiFetch<ApiResponse<{
        field_access: FieldAccess; authoring_policy: AuthoringPolicy; designer_options: Designer[]
      }>>('/admin/works/authoring/options')
      applyOptions(response.data)
      if (!response.data.field_access.can_create) {
        accessError.value = { status: 403, title: 'إنشاء الأعمال غير متاح', message: 'لا يملك الحساب صلاحية إنشاء مسودة عمل.' }
      } else {
        serverSnapshot.value = emptyDraft()
      }
      return
    }

    const [authoringResponse, mediaResponse] = await Promise.all([
      apiFetch<ApiResponse<{
        work: WorkData; designer: Designer | null; field_access: FieldAccess; authoring_policy: AuthoringPolicy
        authoring_state: { editable: boolean; allowed_statuses: string[] }
      }>>(`/admin/works/${props.workId}/authoring`),
      apiFetch<ApiResponse<{
        work: { id:number;status:string;media_type:string|null;cover_media_id:number|null }
        media: MediaItem[]; media_policy: MediaPolicy; counts?: Counts
        field_access: { can_view_media:boolean;can_update_media:boolean }
      }>>(`/admin/works/${props.workId}/media`).catch((error: unknown) => {
        if (statusOf(error) === 403) return null
        throw error
      })
    ])
    work.value = authoringResponse.data.work
    Object.assign(fieldAccess, authoringResponse.data.field_access)
    Object.assign(policy, authoringResponse.data.authoring_policy)
    editable.value = authoringResponse.data.authoring_state.editable
    selectedDesigner.value = authoringResponse.data.designer
    designerOptions.value = authoringResponse.data.designer ? [authoringResponse.data.designer] : []
    setDraft(authoringResponse.data.work)
    if (mediaResponse) {
      mediaState.media = mediaResponse.data.media
      mediaState.policy = mediaResponse.data.media_policy
      mediaState.counts = mediaResponse.data.counts || countsFromPolicy(mediaResponse.data.media_policy, mediaResponse.data.media.length)
    } else {
      mediaState.media = []
      mediaState.policy = null
      mediaState.counts = { active: 0, remaining: null }
    }
    selectedCategoryId.value = work.value.category_id
    selectedTagIds.value = [...work.value.tag_ids]

    const requests: Promise<unknown>[] = [
      loadOptions(),
      ...(fieldAccess.can_assign_category ? [loadTaxonomy('categories')] : []),
      ...(fieldAccess.can_assign_tags ? [loadTaxonomy('tags')] : [])
    ]
    await Promise.all(requests)
  } catch (error: unknown) {
    applyLoadError(error)
  } finally {
    loading.value = false
  }
}

async function loadOptions(query = '') {
  const suffix = query.trim().length >= 2 ? `?q=${encodeURIComponent(query.trim())}&limit=20` : '?limit=20'
  const response = await apiFetch<ApiResponse<{
    field_access: FieldAccess; authoring_policy: AuthoringPolicy; designer_options: Designer[]
  }>>(`/admin/works/authoring/options${suffix}`)
  applyOptions(response.data)
}

function applyOptions(data: { field_access: FieldAccess; authoring_policy: AuthoringPolicy; designer_options: Designer[] }) {
  Object.assign(fieldAccess, data.field_access)
  Object.assign(policy, data.authoring_policy)
  designerOptions.value = mergeDesigner(data.designer_options)
}

async function loadTaxonomy(kind: 'categories' | 'tags') {
  const items: TaxonomyEntity[] = []
  let page = 1
  let lastPage = 1

  do {
    const response = await apiFetch<ApiResponse<{
      items: TaxonomyEntity[]
      pagination: { last_page: number }
    }>>(`/admin/works/taxonomy/${kind}?state=all&sort=sort_order&direction=asc&page=${page}&per_page=50`)
    items.push(...response.data.items)
    lastPage = response.data.pagination.last_page
    page++
  } while (page <= lastPage)

  if (kind === 'categories') categories.value = items
  else tags.value = items
}

async function save() {
  if (!canSave.value || saving.value) return
  saving.value = true
  clearFieldErrors()
  liveMessage.value = ''
  conflict.value = ''
  if (!validateDraft()) {
    saving.value = false
    liveMessage.value = currentLocale.value === 'ar'
      ? 'تحقق من القيم الرقمية قبل الحفظ.'
      : 'Check numeric values before saving.'
    liveTone.value = 'error'
    return
  }
  try {
    const payload = buildPayload()
    const response = await apiFetch<ApiResponse<{
      changed: boolean; work: Partial<WorkData>; field_access: Omit<FieldAccess,'can_create'>; authoring_policy: AuthoringPolicy
    }>>(props.mode === 'create' ? '/admin/works' : `/admin/works/${props.workId}`, {
      method: props.mode === 'create' ? 'POST' : 'PATCH',
      body: payload
    })
    liveMessage.value = response.message || 'تم الحفظ بنجاح.'
    liveTone.value = 'success'
    if (props.mode === 'create') {
      const id = Number(response.data.work.id)
      serverSnapshot.value = { ...draft }
      await router.push(`/admin/works/${id}/edit`)
      return
    }
    work.value = { ...work.value!, ...response.data.work }
    Object.assign(fieldAccess, response.data.field_access)
    Object.assign(policy, response.data.authoring_policy)
    setDraft(work.value)
  } catch (error: unknown) {
    const status = statusOf(error)
    if (status === 422) applyFieldErrors(error)
    else if (status === 409) conflict.value = conflictText(error)
    else if (status === 403) {
      editable.value = false
      accessError.value = { status: 403, title: 'انتهى نطاق التحرير', message: 'لم تعد صلاحيات هذا الحساب تسمح بحفظ هذه الحقول.' }
    } else {
      liveMessage.value = serverMessage(error) || 'تعذر حفظ بيانات العمل.'
      liveTone.value = 'error'
    }
  } finally {
    saving.value = false
  }
}

function buildPayload(): Record<string, unknown> {
  const payload: Record<string, unknown> = {}
  const allowed: Record<keyof Draft, boolean> = {
    title: props.mode === 'create' || fieldAccess.can_update_basic,
    summary: props.mode === 'create' || fieldAccess.can_update_basic,
    description: props.mode === 'create' || fieldAccess.can_update_basic,
    media_type: props.mode === 'create' || fieldAccess.can_update_media,
    price_amount: fieldAccess.can_update_pricing,
    delivery_days: fieldAccess.can_update_delivery,
    designer_id: fieldAccess.can_update_designer,
    internal_notes: fieldAccess.can_update_private_notes
  }
  const fields = props.mode === 'create' ? (Object.keys(draft) as (keyof Draft)[]) : dirtyFields.value
  for (const key of fields) {
    if (!allowed[key]) continue
    if (key === 'media_type' && mediaState.media.length > 0) continue
    const value = draft[key]
    if (key === 'price_amount') payload[key] = value === '' ? null : Number(value)
    else if (key === 'delivery_days') payload[key] = value === '' ? null : Number(value)
    else if (['summary','description','internal_notes'].includes(key)) payload[key] = String(value).trim() === '' ? null : value
    else payload[key] = value
  }
  return payload
}

function resetDraft() {
  if (serverSnapshot.value) Object.assign(draft, serverSnapshot.value)
  selectedDesigner.value = designerOptions.value.find(item => item.id === draft.designer_id) || selectedDesigner.value
  clearFieldErrors()
  conflict.value = ''
}

function validateDraft(): boolean {
  let valid = true

  if (fieldAccess.can_update_pricing && draft.price_amount !== '') {
    const price = Number(draft.price_amount)
    if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(draft.price_amount) || !Number.isFinite(price) || price < 0) {
      fieldErrors.price_amount = currentLocale.value === 'ar'
        ? 'أدخل سعرًا موجبًا بمنزلتين عشريتين كحد أقصى.'
        : 'Enter a non-negative price with at most two decimal places.'
      valid = false
    }
  }

  if (fieldAccess.can_update_delivery && draft.delivery_days !== '') {
    const days = Number(draft.delivery_days)
    if (!/^[0-9]+$/.test(draft.delivery_days) || days < 1 || days > 365) {
      fieldErrors.delivery_days = currentLocale.value === 'ar'
        ? 'أدخل عددًا صحيحًا من 1 إلى 365.'
        : 'Enter an integer from 1 to 365.'
      valid = false
    }
  }

  return valid
}

async function reloadFromServer() {
  conflict.value = ''
  await loadWorkspace()
}

function setDraft(source: Partial<WorkData>) {
  Object.assign(draft, {
    title: source.title || '',
    summary: source.summary || '',
    description: source.description || '',
    media_type: source.media_type ?? null,
    price_amount: source.price_amount ?? '',
    delivery_days: source.delivery_days === null || source.delivery_days === undefined ? '' : String(source.delivery_days),
    designer_id: source.designer_id ?? null,
    internal_notes: source.internal_notes || ''
  })
  serverSnapshot.value = { ...draft }
}

function queueDesignerSearch() {
  if (designerTimer.value) clearTimeout(designerTimer.value)
  const query = designerQuery.value.trim()
  if (query.length === 1) {
    designerOptions.value = mergeDesigner([])
    return
  }
  designerTimer.value = setTimeout(() => searchDesigners(query), 300)
}

async function searchDesigners(query: string) {
  designerLoading.value = true
  designerSearchError.value = ''
  try {
    await loadOptions(query)
  } catch (error: unknown) {
    designerSearchError.value = serverMessage(error) || 'تعذر البحث عن المصممين.'
  } finally {
    designerLoading.value = false
  }
}
function selectDesigner(designer: Designer) {
  selectedDesigner.value = designer
  draft.designer_id = designer.id
  designerQuery.value = designer.name
}
function clearDesigner() {
  selectedDesigner.value = null
  draft.designer_id = null
  designerQuery.value = ''
}
function mergeDesigner(items: Designer[]): Designer[] {
  const merged = selectedDesigner.value ? [selectedDesigner.value, ...items] : items
  return [...new Map(merged.map(item => [item.id, item])).values()]
}

async function saveCategory() {
  if (!work.value || taxonomySaving.value) return
  taxonomySaving.value = true
  taxonomyError.value = ''
  try {
    const response = await apiFetch<ApiResponse<{ work: { category_id:number|null }; changed:boolean }>>(
      `/admin/works/${work.value.id}/taxonomy/category`,
      { method:'PATCH', body:{ category_id:selectedCategoryId.value } }
    )
    work.value.category_id = response.data.work.category_id
    liveMessage.value = response.message || 'تم تحديث التصنيف.'
    liveTone.value = 'success'
  } catch (error: unknown) {
    if (statusOf(error) === 403) editable.value = false
    taxonomyError.value = firstAnyFieldError(error) || serverMessage(error) || 'تعذر تحديث التصنيف.'
  } finally { taxonomySaving.value = false }
}
async function saveTags() {
  if (!work.value || taxonomySaving.value) return
  taxonomySaving.value = true
  taxonomyError.value = ''
  try {
    const response = await apiFetch<ApiResponse<{ work:{ tag_ids:number[] }; changed:boolean }>>(
      `/admin/works/${work.value.id}/taxonomy/tags`,
      { method:'PATCH', body:{ tag_ids:[...selectedTagIds.value].sort((a,b)=>a-b) } }
    )
    work.value.tag_ids = response.data.work.tag_ids
    selectedTagIds.value = [...response.data.work.tag_ids]
    liveMessage.value = response.message || 'تم تحديث الوسوم.'
    liveTone.value = 'success'
  } catch (error: unknown) {
    if (statusOf(error) === 403) editable.value = false
    taxonomyError.value = firstAnyFieldError(error) || serverMessage(error) || 'تعذر تحديث الوسوم.'
  } finally { taxonomySaving.value = false }
}

function applyMediaState(payload: { work?: { cover_media_id:number|null }; media:MediaItem[]; media_policy?:MediaPolicy; counts:Counts }) {
  mediaState.media = payload.media
  mediaState.counts = payload.counts
  if (payload.media_policy) mediaState.policy = payload.media_policy
  if (payload.work && work.value) work.value.cover_media_id = payload.work.cover_media_id
}

function countsFromPolicy(currentPolicy: MediaPolicy, active: number): Counts {
  const max = currentPolicy.effective_limits.max_items
  return { active, remaining: max === null ? null : Math.max(0, max - active) }
}
function emptyDraft(): Draft {
  return { title:'',summary:'',description:'',media_type:null,price_amount:'',delivery_days:'',designer_id:null,internal_notes:'' }
}
function emptyAccess(): FieldAccess {
  return { can_create:false,can_update_basic:false,can_update_media:false,can_update_pricing:false,can_update_delivery:false,can_update_designer:false,can_update_private_notes:false,can_assign_category:false,can_assign_tags:false }
}
function emptyPolicy(): AuthoringPolicy {
  return { source:'work_settings',settings_version:1,allowed_media_types:[],media_limits:{max_items:null,max_file_size_kb:null},enforcement:{} }
}
function normalized(value: unknown): string {
  return value === null || value === undefined ? '' : String(value)
}
function clearFieldErrors() { for (const key of Object.keys(fieldErrors)) delete fieldErrors[key] }
function applyFieldErrors(error: unknown) {
  const errors = (error as any)?.data?.errors || (error as any)?.response?._data?.errors || {}
  const knownFields = new Set(Object.keys(draft))
  const generalErrors: string[] = []
  for (const [field, messages] of Object.entries(errors)) {
    const message = Array.isArray(messages) ? String(messages[0] || '') : String(messages)
    if (knownFields.has(field)) fieldErrors[field] = message
    else if (message) generalErrors.push(message)
  }
  liveMessage.value = generalErrors.join(' ') || (
    currentLocale.value === 'ar' ? 'تحقق من الحقول المشار إليها.' : 'Check the highlighted fields.'
  )
  liveTone.value = 'error'
}
function firstAnyFieldError(error: unknown): string {
  const errors = (error as any)?.data?.errors || (error as any)?.response?._data?.errors || {}
  const first = Object.values(errors)[0]
  return Array.isArray(first) ? String(first[0] || '') : String(first || '')
}
function statusOf(error: unknown): number {
  return Number((error as any)?.response?.status ?? (error as any)?.statusCode) || 0
}
function serverMessage(error: unknown): string {
  return (error as any)?.data?.message || (error as any)?.response?._data?.message || ''
}
function conflictText(error: unknown): string {
  const data = (error as any)?.data?.data || (error as any)?.response?._data?.data
  return `${serverMessage(error) || 'تعذر الحفظ بسبب حالة العمل الحالية.'}${data?.current_status ? ` (${data.current_status})` : data?.reason ? ` (${data.reason})` : ''}`
}
function applyLoadError(error: unknown) {
  const status = statusOf(error)
  if (status === 401) accessError.value = { status, title:'انتهت الجلسة', message:'أعد تسجيل الدخول. بقيت المسودة المحلية دون مسح.' }
  else if (status === 403) accessError.value = { status, title:'الوصول غير متاح', message:'لا يملك الحساب نطاق التأليف المطلوب.' }
  else if (status === 404) accessError.value = { status, title:'العمل غير موجود', message:'لم يُعثر على العمل المطلوب.' }
  else accessError.value = { status:status || 500, title:'تعذر تحميل مساحة التأليف', message:serverMessage(error) || 'حدث خطأ غير متوقع.' }
}
function taxonomyName(item: TaxonomyEntity): string {
  return currentLocale.value === 'ar' ? item.name_ar : item.name_en
}
function mediaTypeLabel(type: string): string {
  const ar:Record<string,string>={image:'صورة',gallery:'معرض صور',video:'فيديو'}
  const en:Record<string,string>={image:'Image',gallery:'Gallery',video:'Video'}
  return (currentLocale.value === 'ar' ? ar : en)[type] || type
}
function statusLabel(status: string): string {
  const ar:Record<string,string>={draft:'مسودة',changes_requested:'تعديلات مطلوبة',submitted:'مرسل',in_review:'قيد المراجعة',approved:'معتمد',published:'منشور',rejected:'مرفوض',hidden:'مخفي',archived:'مؤرشف'}
  return currentLocale.value === 'ar' ? ar[status] || status : status.replaceAll('_',' ')
}
function formatDate(value: string | null): string {
  if (!value) return '—'
  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en', { dateStyle:'medium',timeStyle:'short' }).format(new Date(value))
}
function beforeUnload(event: BeforeUnloadEvent) {
  if (!hasUnsavedChanges.value) return
  event.preventDefault()
  event.returnValue = ''
}
</script>

<style scoped>
.ym-authoring{display:grid;gap:1.35rem;color:var(--ym-text,#e5e7eb)}.ym-authoring__hero,.ym-authoring__card,.ym-authoring__state{position:relative;border:1px solid rgba(148,163,184,.2);border-radius:26px;background:linear-gradient(145deg,rgba(15,23,42,.85),rgba(30,41,59,.58));box-shadow:0 20px 55px rgba(2,6,23,.18)}.ym-authoring__hero{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:clamp(1.35rem,4vw,2.4rem);overflow:hidden}.ym-authoring__hero:before{content:"";position:absolute;width:260px;height:260px;border-radius:50%;inset-block-start:-170px;inset-inline-end:-70px;background:rgba(245,158,11,.22);filter:blur(20px)}.ym-authoring__hero>*,.ym-authoring__card>*{position:relative}.ym-authoring__hero p,.ym-authoring__card header p{margin:0;color:#f59e0b;font-size:.76rem;font-weight:950;text-transform:uppercase;letter-spacing:.08em}.ym-authoring__hero h1{margin:.35rem 0;font-size:clamp(2rem,5vw,3.2rem)}.ym-authoring__hero span{color:#94a3b8}.ym-authoring__hero a,.ym-authoring button{border:1px solid rgba(245,158,11,.38);border-radius:13px;background:rgba(245,158,11,.12);color:inherit;padding:.7rem 1rem;font-weight:900;text-decoration:none}.ym-authoring button:disabled{opacity:.45;cursor:not-allowed}.ym-authoring input:focus-visible,.ym-authoring textarea:focus-visible,.ym-authoring select:focus-visible,.ym-authoring button:focus-visible,.ym-authoring a:focus-visible{outline:3px solid rgba(245,158,11,.4);outline-offset:2px}.ym-authoring__state{display:grid;place-items:center;min-height:300px;padding:2rem;text-align:center}.ym-authoring__state p{color:#94a3b8}.ym-authoring__state.is-error strong{font-size:2rem;color:#fca5a5}.ym-authoring__spinner{width:34px;height:34px;border:3px solid rgba(148,163,184,.25);border-top-color:#f59e0b;border-radius:50%;animation:spin .8s linear infinite}.ym-authoring__status{display:grid;grid-template-columns:repeat(3,1fr);gap:.8rem}.ym-authoring__status div{display:grid;gap:.25rem;padding:1rem;border:1px solid rgba(148,163,184,.18);border-radius:16px;background:rgba(15,23,42,.45)}.ym-authoring__status span{font-size:.75rem;color:#94a3b8}.ym-authoring__changes,.ym-authoring__readonly,.ym-authoring__conflict,.ym-authoring__live{padding:1rem 1.2rem;border-radius:16px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25)}.ym-authoring__changes p,.ym-authoring__readonly p,.ym-authoring__conflict p{margin:.35rem 0 0}.ym-authoring__readonly{background:rgba(59,130,246,.1);border-color:rgba(59,130,246,.25)}.ym-authoring__conflict{display:flex;justify-content:space-between;align-items:center;gap:1rem;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.25)}.ym-authoring__live.is-success{color:#34d399}.ym-authoring__live.is-error,.ym-authoring__field-error{color:#fca5a5}.ym-authoring__card{padding:clamp(1rem,3vw,1.6rem);display:grid;gap:1.25rem}.ym-authoring__card>header{display:flex;justify-content:space-between;align-items:center;gap:1rem}.ym-authoring__card h2{margin:.25rem 0 0}.ym-authoring__card>header>span{color:#94a3b8}.ym-authoring__form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.ym-authoring__form>label,.ym-authoring__designer,.ym-authoring__designer>label{display:grid;align-content:start;gap:.4rem}.ym-authoring__form .is-wide{grid-column:1/-1}.ym-authoring__form label>span,.ym-authoring__designer label>span,.ym-authoring__taxonomy label>span{font-weight:850}.ym-authoring input,.ym-authoring textarea,.ym-authoring select{width:100%;border:1px solid rgba(148,163,184,.25);border-radius:13px;background:rgba(2,6,23,.32);color:inherit;padding:.75rem}.ym-authoring input:disabled,.ym-authoring textarea:disabled,.ym-authoring select:disabled{opacity:.58;cursor:not-allowed}.ym-authoring label small{color:#fcd34d}.ym-authoring label em,.ym-authoring__designer>em{color:#fca5a5;font-style:normal}.ym-authoring__card>footer{display:flex;justify-content:flex-end;gap:.7rem;border-top:1px solid rgba(148,163,184,.15);padding-top:1rem}.ym-authoring .is-secondary{border-color:rgba(148,163,184,.25);background:rgba(148,163,184,.08)}.ym-authoring__designer{position:relative}.ym-authoring__designer ul{z-index:3;display:grid;gap:.2rem;list-style:none;margin:0;padding:.35rem;border:1px solid rgba(148,163,184,.25);border-radius:13px;background:#0f172a;max-height:220px;overflow:auto}.ym-authoring__designer li button{width:100%;text-align:start;border:0;background:transparent}.ym-authoring__designer-current{display:flex;justify-content:space-between;align-items:center;padding:.55rem .75rem;border-radius:12px;background:rgba(16,185,129,.1)}.ym-authoring__designer-state{color:#94a3b8}.ym-authoring__designer-state.is-error{color:#fca5a5}.ym-authoring__taxonomy{display:grid;grid-template-columns:minmax(220px,.7fr) minmax(280px,1.3fr);gap:1.2rem}.ym-authoring__taxonomy>label,.ym-authoring__taxonomy fieldset{display:grid;align-content:start;gap:.65rem;border:1px solid rgba(148,163,184,.18);border-radius:16px;padding:1rem}.ym-authoring__taxonomy fieldset label{display:flex;align-items:center;gap:.55rem}.ym-authoring__taxonomy fieldset input{width:auto}.ym-authoring__taxonomy legend{font-weight:900;padding-inline:.4rem}.ym-authoring__card.is-media{overflow:hidden}.ym-authoring.is-light{color:#172033}.ym-authoring.is-light .ym-authoring__hero,.ym-authoring.is-light .ym-authoring__card,.ym-authoring.is-light .ym-authoring__state{background:linear-gradient(145deg,rgba(255,255,255,.96),rgba(248,250,252,.92));box-shadow:0 18px 45px rgba(15,23,42,.08)}.ym-authoring.is-light input,.ym-authoring.is-light textarea,.ym-authoring.is-light select{background:#fff;color:#172033}.ym-authoring.is-light .ym-authoring__status div{background:rgba(255,255,255,.82)}.ym-authoring.is-light .ym-authoring__designer ul{background:#fff}@keyframes spin{to{transform:rotate(360deg)}}@media(max-width:760px){.ym-authoring__hero,.ym-authoring__conflict,.ym-authoring__card>header{align-items:stretch;flex-direction:column}.ym-authoring__status,.ym-authoring__form,.ym-authoring__taxonomy{grid-template-columns:1fr}.ym-authoring__form .is-wide{grid-column:auto}.ym-authoring__card>footer{display:grid}.ym-authoring__hero a{text-align:center}}@media(prefers-reduced-motion:reduce){.ym-authoring__spinner{animation-duration:1.8s}}
</style>
