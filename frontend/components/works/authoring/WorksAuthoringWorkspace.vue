<template>
  <div
    class="ym-authoring"
    :class="dashboardTheme === 'light' ? 'is-light' : 'is-dark'"
    :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
    @keydown.esc="reloadConfirmation = false"
  >
    <WorksAuthoringHeader :mode="mode" :locale="currentLocale" :work="work" />

    <section v-if="loading" class="ym-authoring__state" role="status" aria-live="polite">
      <span class="ym-authoring__state-icon" aria-hidden="true">◇</span>
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
      <WorksAuthoringStepper v-if="mode === 'create'" :locale="currentLocale" />

      <section v-if="mode === 'edit' && work" class="ym-authoring__status" :aria-label="copy.editState">
        <span><b>{{ copy.status }}</b>{{ statusLabel(work.status) }}</span>
        <span><b>{{ copy.lastUpdate }}</b><time dir="ltr">{{ formatDate(work.updated_at) }}</time></span>
        <span><b>{{ copy.slug }}</b><code dir="ltr">{{ work.slug }}</code></span>
        <span><b>{{ copy.pendingFields }}</b>{{ formatCount(dirtyFields.length) }}</span>
      </section>

      <aside v-if="work?.status === 'changes_requested'" class="ym-authoring__notice is-changes" role="alert">
        <span aria-hidden="true">!</span>
        <div><strong>{{ copy.changesRequested }}</strong><p>{{ work.change_request_notes || copy.noChangeNotes }}</p></div>
      </aside>

      <aside v-if="mode === 'edit' && !editable" class="ym-authoring__notice is-readonly" role="alert">
        <span aria-hidden="true">◇</span>
        <div><strong>{{ copy.readonlyTitle }}</strong><p>{{ copy.readonlyCopy }}</p></div>
      </aside>

      <aside v-if="conflict" class="ym-authoring__notice is-conflict" role="alert">
        <span aria-hidden="true">!</span>
        <div>
          <strong>{{ copy.conflictTitle }}</strong>
          <p>{{ conflict }}</p>
          <small>{{ copy.conflictWarning }}</small>
        </div>
        <div class="ym-authoring__conflict-actions">
          <template v-if="reloadConfirmation">
            <button type="button" class="is-secondary" @click="reloadConfirmation = false">{{ copy.keepEditing }}</button>
            <button type="button" class="is-danger" @click="reloadFromServer">{{ copy.confirmReload }}</button>
          </template>
          <button v-else type="button" @click="reloadConfirmation = true">{{ copy.reload }}</button>
        </div>
      </aside>

      <div v-if="liveMessage" class="ym-authoring__live" :class="`is-${liveTone}`" aria-live="polite">
        {{ liveMessage }}
      </div>

      <WorksAuthoringSectionNav
        v-if="sectionNavItems.length > 1"
        :items="sectionNavItems"
        :active-id="activeSection"
        @navigate="navigateSection"
      />

      <main class="ym-authoring__workspace">
        <div id="authoring-basic" class="ym-authoring__section-stack" data-authoring-section>
          <section class="ym-authoring__card">
            <header class="ym-authoring__section-head">
              <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" /></svg></span>
              <div><p>{{ copy.contentKicker }}</p><h2>{{ copy.contentTitle }}</h2><small>{{ copy.contentCopy }}</small></div>
            </header>

            <div class="ym-authoring__form is-single">
              <label for="ym-work-title">
                <span class="ym-authoring__field-label"><b>{{ copy.fieldTitle }}</b><i>{{ copy.required }}</i></span>
                <input
                  id="ym-work-title"
                  v-model="draft.title"
                  type="text"
                  maxlength="160"
                  required
                  :disabled="!canEditBasic"
                  :aria-invalid="Boolean(fieldErrors.title)"
                  :aria-describedby="fieldErrors.title ? 'ym-work-title-error ym-work-title-count' : 'ym-work-title-count'"
                />
                <small id="ym-work-title-count" class="ym-authoring__counter" dir="ltr">{{ formatCount(draft.title.length) }} / 160</small>
                <em v-if="fieldErrors.title" id="ym-work-title-error" role="alert">{{ fieldErrors.title }}</em>
              </label>

              <label for="ym-work-summary">
                <span class="ym-authoring__field-label"><b>{{ copy.summary }}</b><i class="is-optional">{{ copy.optional }}</i></span>
                <small>{{ copy.summaryHelp }}</small>
                <textarea id="ym-work-summary" v-model="draft.summary" rows="3" maxlength="1000" :disabled="!canEditBasic" :aria-invalid="Boolean(fieldErrors.summary)" :aria-describedby="fieldErrors.summary ? 'ym-work-summary-error ym-work-summary-count' : 'ym-work-summary-count'" />
                <small id="ym-work-summary-count" class="ym-authoring__counter" dir="ltr">{{ formatCount(draft.summary.length) }} / 1000</small>
                <em v-if="fieldErrors.summary" id="ym-work-summary-error" role="alert">{{ fieldErrors.summary }}</em>
              </label>

              <label for="ym-work-description">
                <span class="ym-authoring__field-label"><b>{{ copy.description }}</b><i class="is-optional">{{ copy.optional }}</i></span>
                <textarea id="ym-work-description" v-model="draft.description" rows="6" maxlength="30000" :disabled="!canEditBasic" :aria-invalid="Boolean(fieldErrors.description)" :aria-describedby="fieldErrors.description ? 'ym-work-description-error ym-work-description-count' : 'ym-work-description-count'" />
                <small id="ym-work-description-count" class="ym-authoring__counter" dir="ltr">{{ formatCount(draft.description.length) }} / 30000</small>
                <em v-if="fieldErrors.description" id="ym-work-description-error" role="alert">{{ fieldErrors.description }}</em>
              </label>
            </div>
          </section>

          <section v-if="showExecutionSection" class="ym-authoring__card">
            <header class="ym-authoring__section-head">
              <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 7h16M7 4v6M17 4v6M6 14h4M14 14h4M6 18h7" /></svg></span>
              <div><p>{{ copy.executionKicker }}</p><h2>{{ copy.executionTitle }}</h2><small>{{ copy.executionCopy }}</small></div>
            </header>
            <div class="ym-authoring__form">
              <label for="ym-work-media-type">
                <span class="ym-authoring__field-label"><b>{{ copy.mediaType }}</b><i class="is-optional">{{ copy.optional }}</i></span>
                <select id="ym-work-media-type" v-model="draft.media_type" :disabled="!canEditMediaType || mediaState.media.length > 0" :aria-invalid="Boolean(fieldErrors.media_type)" :aria-describedby="[mediaState.media.length > 0 ? 'ym-media-type-lock' : '', fieldErrors.media_type ? 'ym-media-type-error' : ''].filter(Boolean).join(' ') || undefined">
                  <option :value="null">{{ copy.notSelected }}</option>
                  <option v-for="type in policy.allowed_media_types" :key="type" :value="type">{{ mediaTypeLabel(type) }}</option>
                </select>
                <small v-if="mediaState.media.length > 0" id="ym-media-type-lock">{{ copy.mediaTypeLocked }}</small>
                <em v-if="fieldErrors.media_type" id="ym-media-type-error" role="alert">{{ fieldErrors.media_type }}</em>
              </label>

              <label v-if="fieldAccess.can_update_pricing" for="ym-work-price">
                <span class="ym-authoring__field-label"><b>{{ copy.price }}</b><i class="is-optional">{{ copy.optional }}</i></span>
                <input id="ym-work-price" :value="draft.price_amount" type="text" inputmode="decimal" dir="ltr" lang="en" :disabled="!editable" :aria-invalid="Boolean(fieldErrors.price_amount)" :aria-describedby="fieldErrors.price_amount ? 'ym-work-price-error' : undefined" @input="normalizeNumericField('price_amount', $event)" />
                <em v-if="fieldErrors.price_amount" id="ym-work-price-error" role="alert">{{ fieldErrors.price_amount }}</em>
              </label>

              <label v-if="fieldAccess.can_update_delivery" for="ym-work-delivery">
                <span class="ym-authoring__field-label"><b>{{ copy.delivery }}</b><i class="is-optional">{{ copy.optional }}</i></span>
                <input id="ym-work-delivery" :value="draft.delivery_days" type="text" inputmode="numeric" dir="ltr" lang="en" :disabled="!editable" :aria-invalid="Boolean(fieldErrors.delivery_days)" :aria-describedby="fieldErrors.delivery_days ? 'ym-delivery-help ym-work-delivery-error' : 'ym-delivery-help'" @input="normalizeNumericField('delivery_days', $event)" />
                <small id="ym-delivery-help">{{ copy.daysHelp }}</small>
                <em v-if="fieldErrors.delivery_days" id="ym-work-delivery-error" role="alert">{{ fieldErrors.delivery_days }}</em>
              </label>
            </div>
          </section>

          <section v-if="fieldAccess.can_update_designer" class="ym-authoring__card">
            <header class="ym-authoring__section-head">
              <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM17 8h4M19 6v4" /></svg></span>
              <div><p>{{ copy.assignmentKicker }}</p><h2>{{ copy.assignmentTitle }}</h2><small>{{ copy.assignmentCopy }}</small></div>
            </header>
            <div class="ym-authoring__designer">
              <div v-if="selectedDesigner" class="ym-authoring__designer-current">
                <span aria-hidden="true">{{ selectedDesigner.name.slice(0, 1) }}</span>
                <div><strong>{{ selectedDesigner.name }}</strong><small>{{ copy.designerRole }}</small></div>
                <button type="button" :disabled="!editable" @click="clearDesigner">{{ copy.clear }}</button>
              </div>
              <div v-else class="ym-authoring__designer-empty"><span aria-hidden="true">◇</span><div><strong>{{ copy.noSelectedDesigner }}</strong><small>{{ copy.designerOptional }}</small></div></div>
              <label for="ym-designer-search">
                <span>{{ copy.designerSearch }}</span>
                <input id="ym-designer-search" v-model="designerQuery" type="search" autocomplete="off" :disabled="!editable" @input="queueDesignerSearch" />
              </label>
              <div v-if="designerLoading" class="ym-authoring__designer-state" role="status">{{ copy.searching }}</div>
              <div v-else-if="designerSearchError" class="ym-authoring__designer-state is-error" role="alert">{{ designerSearchError }}</div>
              <ul v-else-if="designerOptions.length && designerQuery.trim().length >= 2" :aria-label="copy.designerResults">
                <li v-for="designer in designerOptions" :key="designer.id">
                  <button type="button" :disabled="!editable" @click="selectDesigner(designer)"><span aria-hidden="true">{{ designer.name.slice(0, 1) }}</span>{{ designer.name }}</button>
                </li>
              </ul>
              <small v-else-if="designerQuery.trim().length >= 2">{{ copy.noDesigners }}</small>
              <em v-if="fieldErrors.designer_id" role="alert">{{ fieldErrors.designer_id }}</em>
            </div>
          </section>

          <section v-if="fieldAccess.can_update_private_notes" class="ym-authoring__card is-private">
            <header class="ym-authoring__section-head">
              <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h5" /></svg></span>
              <div><p>{{ copy.adminKicker }}</p><h2>{{ copy.adminTitle }}</h2><small>{{ copy.adminCopy }}</small></div>
            </header>
            <label class="ym-authoring__single-field" for="ym-work-internal-notes">
              <span class="ym-authoring__field-label"><b>{{ copy.internalNotes }}</b><i class="is-optional">{{ copy.optional }}</i></span>
              <small>{{ copy.internalHelp }}</small>
              <textarea id="ym-work-internal-notes" v-model="draft.internal_notes" rows="5" maxlength="10000" :disabled="!editable" :aria-invalid="Boolean(fieldErrors.internal_notes)" :aria-describedby="fieldErrors.internal_notes ? 'ym-work-notes-error ym-work-notes-count' : 'ym-work-notes-count'" />
              <small id="ym-work-notes-count" class="ym-authoring__counter" dir="ltr">{{ formatCount(draft.internal_notes.length) }} / 10000</small>
              <em v-if="fieldErrors.internal_notes" id="ym-work-notes-error" role="alert">{{ fieldErrors.internal_notes }}</em>
            </label>
          </section>
        </div>

        <section v-if="showTaxonomySection" id="authoring-taxonomy" class="ym-authoring__card" data-authoring-section :aria-busy="taxonomySaving || undefined">
          <header class="ym-authoring__section-head">
            <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 13 11 22l-9-9V4h9l9 9ZM7 8h.01" /></svg></span>
            <div><p>{{ copy.taxonomyKicker }}</p><h2>{{ copy.taxonomyTitle }}</h2><small>{{ copy.taxonomyCopy }}</small></div>
          </header>
          <div class="ym-authoring__taxonomy">
            <div v-if="fieldAccess.can_assign_category" class="ym-authoring__taxonomy-group">
              <header><div><span>{{ copy.category }}</span><strong>{{ selectedCategoryName }}</strong></div><small>{{ selectedCategoryId === null ? copy.noCategory : copy.categoryCurrent }}</small></header>
              <label for="ym-work-category">{{ copy.chooseCategory }}</label>
              <select id="ym-work-category" v-model="selectedCategoryId" :disabled="!editable || taxonomySaving">
                <option :value="null">{{ copy.removeCategory }}</option>
                <option v-for="item in categoryOptions" :key="item.id" :value="item.id">{{ taxonomyName(item) }}</option>
              </select>
              <button type="button" :disabled="!editable || taxonomySaving || !categoryDirty" @click="saveCategory">{{ taxonomySaving ? copy.saving : copy.saveCategory }}</button>
            </div>

            <fieldset v-if="fieldAccess.can_assign_tags" class="ym-authoring__taxonomy-group" :disabled="!editable || taxonomySaving">
              <legend>{{ copy.tags }}</legend>
              <div class="ym-authoring__tags-summary"><strong>{{ formatCount(selectedTagIds.length) }}</strong><span>{{ selectedTagIds.length ? copy.selectedTags : copy.noTags }}</span></div>
              <div class="ym-authoring__tag-options">
                <label v-for="item in tagOptions" :key="item.id" :class="{ 'is-selected': selectedTagIds.includes(item.id), 'is-disabled': !item.is_active }">
                  <input v-model="selectedTagIds" type="checkbox" :value="item.id" :disabled="!item.is_active && !selectedTagIds.includes(item.id)" />
                  <span>{{ taxonomyName(item) }}</span>
                </label>
              </div>
              <button type="button" :disabled="!editable || taxonomySaving || !tagsDirty" @click="saveTags">{{ taxonomySaving ? copy.saving : copy.saveTags }}</button>
            </fieldset>
          </div>
          <p v-if="taxonomyError" class="ym-authoring__field-error" role="alert">{{ taxonomyError }}</p>
        </section>

        <section v-if="mode === 'edit' && work && mediaState.policy" id="authoring-media" class="ym-authoring__card is-media" data-authoring-section>
          <header class="ym-authoring__section-head">
            <span aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 5h16v14H4zM8 10a2 2 0 1 0 0-4M4 16l5-5 4 4 2-2 5 5" /></svg></span>
            <div><p>{{ copy.mediaKicker }}</p><h2>{{ copy.mediaTitle }}</h2><small>{{ copy.mediaCopy }}</small></div>
          </header>
          <div class="ym-authoring__media-shell">
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
          </div>
        </section>
      </main>

      <WorksAuthoringActionBar
        :mode="mode"
        :locale="currentLocale"
        :saving="saving"
        :dirty="isDirty"
        :dirty-count="dirtyFields.length"
        :can-save="canSave"
        :readonly="mode === 'edit' && !editable"
        :conflict="Boolean(conflict)"
        :pending-elsewhere="categoryDirty || tagsDirty || mediaOrderDirty"
        :tone="actionTone"
        @save="save"
        @reset="resetDraft"
        @back="router.push('/admin/works/all')"
      />
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import WorksAuthoringActionBar from '~/components/works/authoring/WorksAuthoringActionBar.vue'
import WorksAuthoringHeader from '~/components/works/authoring/WorksAuthoringHeader.vue'
import WorksAuthoringSectionNav from '~/components/works/authoring/WorksAuthoringSectionNav.vue'
import WorksAuthoringStepper from '~/components/works/authoring/WorksAuthoringStepper.vue'
import WorksMediaManager from '~/components/works/authoring/WorksMediaManager.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import { formatYmDateTime, formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

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
const reloadConfirmation = ref(false)
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
const activeSection = ref('authoring-basic')
const mediaState = reactive<{ media: MediaItem[]; policy: MediaPolicy | null; counts: Counts }>({
  media: [],
  policy: null,
  counts: { active: 0, remaining: null }
})
let sectionObserver: IntersectionObserver | null = null

const copyMap = {
  ar: {
    loading:'جارٍ تجهيز مساحة التأليف',loadingCopy:'يتم التحقق من الصلاحيات وتحميل أحدث نسخة من الخادم.',retry:'إعادة المحاولة',
    status:'الحالة',slug:'المعرّف النصي',lastUpdate:'آخر تحديث',editState:'حالة تحرير العمل',changesRequested:'يحتاج تعديلات',noChangeNotes:'لم يرفق المراجع نصًا إضافيًا.',
    readonlyTitle:'وضع القراءة فقط',readonlyCopy:'حالة العمل الحالية لا تسمح بالتحرير. بقيت البيانات والوسائط متاحة للقراءة.',
    conflictTitle:'تعارض مع حالة الخادم',conflictWarning:'تحميل نسخة الخادم سيستبدل تغييرات نموذج البيانات المحلية.',reload:'تحميل نسخة الخادم',keepEditing:'متابعة التعديل',confirmReload:'استبدال التغييرات المحلية',
    pendingFields:'التغييرات غير المحفوظة',
    contentKicker:'المحتوى الأساسي',contentTitle:'هوية العمل ومحتواه',contentCopy:'اكتب المحتوى الذي يعرّف العمل في القوائم وصفحة العرض.',
    executionKicker:'إعدادات التنفيذ',executionTitle:'التسليم ونوع الوسائط',executionCopy:'حدد شكل التنفيذ والتكلفة والمدة المتوقعة.',
    assignmentKicker:'الإسناد',assignmentTitle:'مصمم العمل',assignmentCopy:'ابحث عن المصمم ثم ثبّت اختياره بصورة واضحة.',
    adminKicker:'معلومات إدارية',adminTitle:'الملاحظات الداخلية',adminCopy:'هذا القسم داخلي ومتاح فقط وفق صلاحيات الحساب.',
    fieldTitle:'العنوان',required:'مطلوب',optional:'اختياري',summary:'الملخص',summaryHelp:'يظهر في القوائم والبطاقات المختصرة.',description:'الوصف',mediaType:'نمط الوسائط',notSelected:'غير محدد',mediaTypeLocked:'احذف جميع الوسائط الفعالة قبل تغيير النمط.',
    price:'السعر',delivery:'مدة التسليم',daysHelp:'بالأيام.',designerSearch:'البحث عن مصمم',designerResults:'نتائج البحث عن المصممين',designerRole:'مصمم العمل',designerOptional:'يمكن حفظ العمل دون إسناد مصمم.',noSelectedDesigner:'لا يوجد مصمم محدد',clear:'إزالة',searching:'جارٍ البحث…',noDesigners:'لا توجد نتائج.',
    internalNotes:'ملاحظات داخلية',internalHelp:'معلومات داخلية لا تظهر للعامة.',saving:'جارٍ الحفظ…',
    taxonomyKicker:'تنظيم العمل',taxonomyTitle:'التصنيف والوسوم',taxonomyCopy:'تُحفظ هذه الإسنادات بصورة مستقلة عن بيانات العمل الأساسية.',category:'التصنيف',categoryCurrent:'التصنيف المحدد حاليًا',noCategory:'لا يوجد تصنيف مرتبط',chooseCategory:'اختيار التصنيف',removeCategory:'دون تصنيف',saveCategory:'حفظ التصنيف',
    tags:'الوسوم',selectedTags:'وسوم محددة',noTags:'لا توجد وسوم محددة',saveTags:'حفظ الوسوم',
    mediaKicker:'وسائط العمل',mediaTitle:'الوسائط والغلاف',mediaCopy:'إدارة مستقلة للرفع والغلاف والترتيب وفق العقود الحالية.',
    navBasic:'البيانات الأساسية',navTaxonomy:'التصنيف والوسوم',navMedia:'الوسائط'
  },
  en: {
    loading:'Preparing the workspace',loadingCopy:'Checking permissions and loading the latest server snapshot.',retry:'Retry',
    status:'Status',slug:'Slug',lastUpdate:'Last update',editState:'Work edit status',changesRequested:'Needs changes',noChangeNotes:'No additional reviewer note was provided.',
    readonlyTitle:'Read-only mode',readonlyCopy:'The current work state does not allow editing. Data and media remain readable.',
    conflictTitle:'Server state conflict',conflictWarning:'Loading the server version will replace local work-data changes.',reload:'Load server version',keepEditing:'Keep editing',confirmReload:'Replace local changes',
    pendingFields:'Unsaved changes',
    contentKicker:'Core content',contentTitle:'Work identity and content',contentCopy:'Write the content that identifies the work in listings and its detail view.',
    executionKicker:'Execution settings',executionTitle:'Delivery and media type',executionCopy:'Define the execution format, cost, and expected delivery time.',
    assignmentKicker:'Assignment',assignmentTitle:'Work designer',assignmentCopy:'Search for the designer and confirm the selection clearly.',
    adminKicker:'Administrative information',adminTitle:'Internal notes',adminCopy:'This section is internal and permission-scoped.',
    fieldTitle:'Title',required:'Required',optional:'Optional',summary:'Summary',summaryHelp:'Shown in lists and compact cards.',description:'Description',mediaType:'Media type',notSelected:'Not selected',mediaTypeLocked:'Delete all active media before changing the type.',
    price:'Price',delivery:'Delivery time',daysHelp:'In days.',designerSearch:'Search designers',designerResults:'Designer search results',designerRole:'Work designer',designerOptional:'The work can be saved without assigning a designer.',noSelectedDesigner:'No designer selected',clear:'Remove',searching:'Searching…',noDesigners:'No results.',
    internalNotes:'Internal notes',internalHelp:'Internal information that is not shown publicly.',saving:'Saving…',
    taxonomyKicker:'Work organization',taxonomyTitle:'Category and tags',taxonomyCopy:'These assignments are saved independently from the core work data.',category:'Category',categoryCurrent:'Currently selected category',noCategory:'No category assigned',chooseCategory:'Choose category',removeCategory:'Uncategorized',saveCategory:'Save category',
    tags:'Tags',selectedTags:'Selected tags',noTags:'No tags selected',saveTags:'Save tags',
    mediaKicker:'Work media',mediaTitle:'Media and cover',mediaCopy:'Independent upload, cover, and ordering management through the existing contracts.',
    navBasic:'Basic data',navTaxonomy:'Category and tags',navMedia:'Media'
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
const showExecutionSection = computed(() => (
  policy.allowed_media_types.length > 0
  || fieldAccess.can_update_pricing
  || fieldAccess.can_update_delivery
))
const showTaxonomySection = computed(() => (
  props.mode === 'edit'
  && (fieldAccess.can_assign_category || fieldAccess.can_assign_tags)
))
const showMediaSection = computed(() => (
  props.mode === 'edit' && work.value !== null && mediaState.policy !== null
))
const sectionNavItems = computed(() => [
  { id: 'authoring-basic', label: copy.value.navBasic, icon: '◇' },
  ...(showTaxonomySection.value ? [{ id: 'authoring-taxonomy', label: copy.value.navTaxonomy, icon: '#' }] : []),
  ...(showMediaSection.value ? [{ id: 'authoring-media', label: copy.value.navMedia, icon: '▣' }] : [])
])
const selectedCategoryName = computed(() => {
  if (selectedCategoryId.value === null) return copy.value.noCategory
  const item = categoryOptions.value.find(category => category.id === selectedCategoryId.value)
  return item ? taxonomyName(item) : copy.value.noCategory
})
const actionTone = computed<'idle' | 'dirty' | 'saving' | 'success' | 'error' | 'conflict' | 'readonly'>(() => {
  if (saving.value) return 'saving'
  if (props.mode === 'edit' && !editable.value) return 'readonly'
  if (conflict.value) return 'conflict'
  if (isDirty.value || categoryDirty.value || tagsDirty.value || mediaOrderDirty.value) return 'dirty'
  if (liveMessage.value && liveTone.value === 'success') return 'success'
  if (liveMessage.value && liveTone.value === 'error') return 'error'
  return 'idle'
})

onMounted(async () => {
  if (!authStore.isInitialized) await authStore.hydrateAuth()
  await loadWorkspace()
  if (import.meta.client) window.addEventListener('beforeunload', beforeUnload)
})
onBeforeUnmount(() => {
  if (designerTimer.value) clearTimeout(designerTimer.value)
  sectionObserver?.disconnect()
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
  reloadConfirmation.value = false
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
    await nextTick()
    setupSectionObserver()
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
  reloadConfirmation.value = false
  conflict.value = ''
  await loadWorkspace()
}

function navigateSection(id: string) {
  const target = document.getElementById(id)
  if (!target) return
  activeSection.value = id
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches
  target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' })
}

function setupSectionObserver() {
  sectionObserver?.disconnect()
  if (!import.meta.client || !('IntersectionObserver' in window)) return
  const sections = [...document.querySelectorAll<HTMLElement>('[data-authoring-section]')]
  if (!sections.length) return
  sectionObserver = new IntersectionObserver((entries) => {
    const visible = entries
      .filter(entry => entry.isIntersecting)
      .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0]
    if (visible?.target.id) activeSection.value = visible.target.id
  }, { rootMargin: '-18% 0px -62% 0px', threshold: [0.05, 0.2, 0.45] })
  sections.forEach(section => sectionObserver?.observe(section))
}

function normalizeNumericField(field: 'price_amount' | 'delivery_days', event: Event) {
  const input = event.target as HTMLInputElement
  const value = toLatinDigits(input.value)
  if (input.value !== value) input.value = value
  draft[field] = value
}

function setDraft(source: Partial<WorkData>) {
  Object.assign(draft, {
    title: source.title || '',
    summary: source.summary || '',
    description: source.description || '',
    media_type: source.media_type ?? null,
    price_amount: source.price_amount === null || source.price_amount === undefined ? '' : toLatinDigits(source.price_amount),
    delivery_days: source.delivery_days === null || source.delivery_days === undefined ? '' : toLatinDigits(source.delivery_days),
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
  return formatYmDateTime(value, currentLocale.value)
}
function formatCount(value: number): string {
  return formatYmNumber(value, currentLocale.value)
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

<style scoped>
.ym-authoring{
  --aw-violet:#7c3aed;--aw-electric:#8b5cf6;--aw-magenta:#ec4899;--aw-cyan:#22d3ee;
  --aw-emerald:#10b981;--aw-amber:#f59e0b;--aw-rose:#f43f5e;
  --aw-text:#f7f9ff;--aw-muted:rgba(226,232,240,.91);--aw-kicker:#fbbf24;
  --aw-surface:linear-gradient(145deg,rgba(12,21,42,.86),rgba(28,40,66,.72));
  --aw-surface-strong:rgba(10,18,38,.97);--aw-control:rgba(25,35,58,.82);
  --aw-field:rgba(8,16,34,.76);--aw-field-border:rgba(167,139,250,.34);--aw-placeholder:rgba(203,213,225,.58);
  --aw-border:rgba(139,92,246,.3);--aw-soft-border:rgba(148,163,184,.21);
  --aw-highlight:rgba(255,255,255,.09);--aw-shadow:0 20px 50px rgba(2,6,23,.3);--aw-soft-shadow:0 12px 30px rgba(2,6,23,.15);
  display:grid;min-width:0;gap:18px;overflow-x:clip;padding-block-end:18px;color:var(--aw-text)
}
.ym-authoring.is-light{
  --aw-text:#1b122b;--aw-muted:#554866;--aw-kicker:#8a4b00;
  --aw-surface:linear-gradient(145deg,rgba(255,255,255,.9),rgba(244,240,253,.8));
  --aw-surface-strong:rgba(253,251,255,.96);--aw-control:rgba(237,232,248,.82);
  --aw-field:rgba(255,255,255,.84);--aw-field-border:rgba(109,40,217,.31);--aw-placeholder:#756a83;
  --aw-border:rgba(109,40,217,.28);--aw-soft-border:rgba(91,33,182,.18);
  --aw-highlight:rgba(255,255,255,.76);--aw-shadow:0 18px 42px rgba(76,29,149,.13),0 4px 12px rgba(91,33,182,.06);--aw-soft-shadow:0 10px 26px rgba(76,29,149,.09)
}
.ym-authoring__state{
  display:grid;min-height:330px;place-items:center;align-content:center;gap:9px;
  border:1px solid var(--aw-border);border-radius:20px;padding:24px;color:var(--aw-text);
  background:var(--aw-surface);box-shadow:var(--aw-shadow);text-align:center
}
.ym-authoring__state-icon{display:grid;width:44px;height:44px;place-items:center;border-radius:14px;color:var(--aw-electric);background:color-mix(in srgb,var(--aw-electric) 12%,transparent);font-size:22px}
.ym-authoring__state h2{margin:0;font-size:20px}.ym-authoring__state p{max-width:520px;margin:0;color:var(--aw-muted);font-size:14px;line-height:1.65}
.ym-authoring__state.is-error strong{color:var(--aw-rose);font-size:28px}.ym-authoring__state button{min-height:44px;border:1px solid var(--aw-border);border-radius:12px;padding:0 15px;color:var(--aw-text);background:var(--aw-control);font-weight:800}
.ym-authoring__status{
  display:flex;flex-wrap:wrap;gap:7px;border:1px solid var(--aw-border);border-radius:15px;
  padding:8px;background:var(--aw-surface)
}
.ym-authoring__status>span{display:inline-flex;min-height:35px;align-items:center;gap:7px;border-radius:10px;padding:5px 10px;color:var(--aw-text);background:var(--aw-control);font-size:12.5px}
.ym-authoring__status b{color:var(--aw-muted);font-size:12px}.ym-authoring__status code,.ym-authoring__status time{color:var(--aw-electric);font-variant-numeric:tabular-nums}
.ym-authoring__notice{
  display:flex;align-items:flex-start;gap:11px;border:1px solid color-mix(in srgb,var(--notice) 34%,transparent);
  border-radius:16px;padding:13px 15px;background:color-mix(in srgb,var(--notice) 9%,var(--aw-control))
}
.ym-authoring__notice>span{display:grid;flex:0 0 auto;width:32px;height:32px;place-items:center;border-radius:10px;color:var(--notice);background:color-mix(in srgb,var(--notice) 12%,transparent);font-weight:900}
.ym-authoring__notice>div{min-width:0}.ym-authoring__notice strong{color:var(--aw-text);font-size:14px}.ym-authoring__notice p{margin:3px 0 0;color:var(--aw-muted);font-size:13.5px;line-height:1.6}.ym-authoring__notice small{display:block;margin-top:5px;color:var(--notice);font-size:12.5px}
.ym-authoring__notice.is-changes{--notice:var(--aw-amber)}.ym-authoring__notice.is-readonly{--notice:var(--aw-cyan)}.ym-authoring__notice.is-conflict{--notice:var(--aw-rose);align-items:center}.ym-authoring__notice.is-conflict>div:nth-child(2){flex:1}
.ym-authoring__conflict-actions{display:flex;flex:0 0 auto;gap:7px}.ym-authoring__conflict-actions button{min-height:42px;border:1px solid color-mix(in srgb,var(--aw-rose) 40%,var(--aw-border));border-radius:11px;padding:0 12px;color:var(--aw-text);background:var(--aw-control);font-weight:800}.ym-authoring__conflict-actions .is-danger{color:#fff;background:var(--aw-rose)}.ym-authoring__conflict-actions .is-secondary{border-color:var(--aw-border)}
.ym-authoring__live{border:1px solid var(--aw-border);border-radius:13px;padding:11px 13px;background:var(--aw-control);font-size:13.5px;font-weight:750}.ym-authoring__live.is-success{color:var(--aw-emerald)}.ym-authoring__live.is-error{color:var(--aw-rose)}
.ym-authoring__workspace,.ym-authoring__section-stack{display:grid;min-width:0;gap:20px}.ym-authoring__workspace{padding-block-end:calc(112px + env(safe-area-inset-bottom))}
[data-authoring-section]{scroll-margin-block-start:86px}
.ym-authoring__card{
  display:grid;min-width:0;gap:20px;border:1px solid var(--aw-border);border-radius:19px;
  padding:clamp(20px,2.3vw,24px);color:var(--aw-text);background:var(--aw-surface);
  box-shadow:var(--aw-shadow),inset 0 1px 0 var(--aw-highlight)
}
.ym-authoring__section-stack>.ym-authoring__card,.ym-authoring__workspace>[data-authoring-section]{animation:ym-authoring-enter .2s ease both}
.ym-authoring__card.is-private{border-color:color-mix(in srgb,var(--aw-amber) 26%,var(--aw-border));background:linear-gradient(145deg,color-mix(in srgb,var(--aw-amber) 6%,transparent),var(--aw-surface-strong))}
.ym-authoring__card>.ym-authoring__section-head{display:flex;align-items:flex-start;justify-content:flex-start;gap:13px}.ym-authoring__section-head>span{display:grid;flex:0 0 auto;width:40px;height:40px;place-items:center;border-radius:12px;color:var(--aw-electric);background:color-mix(in srgb,var(--aw-electric) 11%,transparent)}.ym-authoring__section-head svg{width:20px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.ym-authoring__card .ym-authoring__section-head p{margin:0;color:var(--aw-kicker);font-size:12.5px;font-weight:850}.ym-authoring__section-head h2{margin:3px 0;color:var(--aw-text);font-size:clamp(18px,2vw,20px);line-height:1.4}.ym-authoring__section-head small{color:var(--aw-muted);font-size:13.5px;line-height:1.6}
.ym-authoring__form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:17px}.ym-authoring__form.is-single{grid-template-columns:1fr}
.ym-authoring__form>label,.ym-authoring__single-field,.ym-authoring__designer>label{position:relative;display:grid;align-content:start;gap:7px;min-width:0}
.ym-authoring__field-label{display:flex;align-items:center;justify-content:space-between;gap:12px}.ym-authoring__field-label b,.ym-authoring__designer label>span{font-size:14px}.ym-authoring__field-label i{border-radius:999px;padding:3px 7px;color:var(--aw-rose);background:color-mix(in srgb,var(--aw-rose) 9%,transparent);font-size:12px;font-style:normal;font-weight:750}.ym-authoring__field-label i.is-optional{color:var(--aw-muted);background:var(--aw-control)}
.ym-authoring input,.ym-authoring textarea,.ym-authoring select{
  width:100%;min-height:46px;border:1px solid var(--aw-border);border-radius:11px;outline:0;
  padding:10px 12px;color:var(--aw-text);background:var(--aw-field);font-family:inherit;font-size:15px;
  border-color:var(--aw-field-border);
  transition:border-color .16s ease,box-shadow .16s ease
}
.ym-authoring input::placeholder,.ym-authoring textarea::placeholder{color:var(--aw-placeholder);opacity:1}
.ym-authoring textarea{resize:vertical;line-height:1.7}.ym-authoring textarea[rows="3"]{min-height:108px}.ym-authoring textarea[rows="6"]{min-height:168px}.ym-authoring textarea[rows="5"]{min-height:132px}
.ym-authoring input:focus,.ym-authoring textarea:focus,.ym-authoring select:focus{border-color:var(--aw-electric);box-shadow:0 0 0 3px color-mix(in srgb,var(--aw-electric) 18%,transparent)}
.ym-authoring input:focus-visible,.ym-authoring textarea:focus-visible,.ym-authoring select:focus-visible,.ym-authoring button:focus-visible{outline:3px solid color-mix(in srgb,var(--aw-electric) 38%,transparent);outline-offset:2px}
.ym-authoring input:disabled,.ym-authoring textarea:disabled,.ym-authoring select:disabled{cursor:not-allowed;opacity:.64}
.ym-authoring label>small,.ym-authoring__single-field>small{color:var(--aw-muted);font-size:13px;line-height:1.55}.ym-authoring label em,.ym-authoring__single-field em,.ym-authoring__designer>em{color:var(--aw-rose);font-size:12.5px;font-style:normal}
.ym-authoring__counter{direction:ltr;unicode-bidi:isolate;justify-self:end;font-size:12.75px;font-variant-numeric:tabular-nums;font-weight:750}
.ym-authoring__designer{display:grid;gap:12px}.ym-authoring__designer-current,.ym-authoring__designer-empty{display:flex;align-items:center;gap:11px;border:1px solid color-mix(in srgb,var(--aw-emerald) 28%,var(--aw-border));border-radius:15px;padding:11px 12px;background:color-mix(in srgb,var(--aw-emerald) 7%,var(--aw-control))}.ym-authoring__designer-current>span,.ym-authoring__designer-empty>span{display:grid;width:38px;height:38px;place-items:center;border-radius:12px;color:var(--aw-emerald);background:color-mix(in srgb,var(--aw-emerald) 12%,transparent);font-weight:900}.ym-authoring__designer-current>div,.ym-authoring__designer-empty>div{display:grid;flex:1;gap:2px}.ym-authoring__designer-current strong,.ym-authoring__designer-empty strong{font-size:14px}.ym-authoring__designer-current small,.ym-authoring__designer-empty small{color:var(--aw-muted);font-size:12.5px}.ym-authoring__designer-current button{min-height:38px;border:1px solid var(--aw-border);border-radius:10px;padding:0 10px;color:var(--aw-text);background:var(--aw-control);font-weight:750}.ym-authoring__designer-empty{border-color:var(--aw-soft-border);background:var(--aw-control)}
.ym-authoring__designer ul{display:grid;max-height:230px;gap:6px;overflow:auto;border:1px solid var(--aw-border);border-radius:13px;margin:0;padding:7px;background:var(--aw-surface-strong);list-style:none}.ym-authoring__designer li button{display:flex;width:100%;min-height:42px;align-items:center;gap:9px;border:0;border-radius:9px;padding:6px 9px;color:var(--aw-text);background:transparent;text-align:start;font-weight:750}.ym-authoring__designer li button:hover{background:var(--aw-control)}.ym-authoring__designer li button span{display:grid;width:28px;height:28px;place-items:center;border-radius:8px;color:var(--aw-electric);background:color-mix(in srgb,var(--aw-electric) 10%,transparent)}.ym-authoring__designer-state{border-radius:11px;padding:10px;color:var(--aw-muted);background:var(--aw-control);font-size:13px}.ym-authoring__designer-state.is-error{color:var(--aw-rose)}
.ym-authoring__taxonomy{display:grid;grid-template-columns:minmax(230px,.8fr) minmax(280px,1.2fr);gap:14px}.ym-authoring__taxonomy-group{display:grid;align-content:start;gap:11px;min-width:0;border:1px solid var(--aw-soft-border);border-radius:16px;margin:0;padding:15px;background:var(--aw-control)}.ym-authoring__taxonomy-group>header{display:grid;gap:3px}.ym-authoring__taxonomy-group>header>div{display:flex;justify-content:space-between;gap:10px}.ym-authoring__taxonomy-group>header span,.ym-authoring__taxonomy-group>label,.ym-authoring__taxonomy-group legend{color:var(--aw-muted);font-size:12.5px;font-weight:750}.ym-authoring__taxonomy-group>header strong{font-size:14px}.ym-authoring__taxonomy-group>header small{color:var(--aw-muted);font-size:12px}.ym-authoring__taxonomy-group>button{min-height:42px;border:0;border-radius:11px;color:#fff;background:linear-gradient(135deg,var(--aw-violet),var(--aw-magenta));font-weight:850}.ym-authoring__taxonomy-group>button:disabled{cursor:not-allowed;opacity:.48}.ym-authoring__tags-summary{display:flex;align-items:center;gap:8px}.ym-authoring__tags-summary strong{direction:ltr;color:var(--aw-electric);font-size:20px;font-variant-numeric:tabular-nums}.ym-authoring__tags-summary span{color:var(--aw-muted);font-size:12.5px}.ym-authoring__tag-options{display:flex;max-height:270px;flex-wrap:wrap;align-content:flex-start;gap:7px;overflow:auto}.ym-authoring__tag-options label{display:inline-flex;min-height:36px;align-items:center;gap:6px;border:1px solid var(--aw-soft-border);border-radius:999px;padding:5px 9px;color:var(--aw-muted);background:var(--aw-surface);font-size:12.5px;cursor:pointer}.ym-authoring__tag-options label.is-selected{border-color:color-mix(in srgb,var(--aw-electric) 45%,transparent);color:var(--aw-text);background:color-mix(in srgb,var(--aw-electric) 10%,var(--aw-surface-strong))}.ym-authoring__tag-options label.is-disabled{color:var(--aw-amber)}.ym-authoring__tag-options input{width:16px;min-height:16px;margin:0}.ym-authoring__field-error{margin:0;color:var(--aw-rose);font-size:13px;font-weight:750}
.ym-authoring__card.is-media{padding:20px}.ym-authoring__media-shell{min-width:0;border-block-start:1px solid var(--aw-soft-border);padding-block:20px 28px}
.ym-authoring.is-light .ym-authoring__card,.ym-authoring.is-light .ym-authoring__state{background:var(--aw-surface);box-shadow:var(--aw-shadow),inset 0 1px 0 var(--aw-highlight)}
.ym-authoring.is-light input,.ym-authoring.is-light textarea,.ym-authoring.is-light select{border-color:var(--aw-field-border);color:var(--aw-text);background:var(--aw-field)}
.ym-authoring.is-light .ym-authoring__live.is-success{border-color:rgba(5,150,105,.3);color:#047857;background:rgba(16,185,129,.08)}
.ym-authoring.is-light .ym-authoring__live.is-error,.ym-authoring.is-light .ym-authoring__field-error{color:#be123c}
button{transition:transform .16s ease,border-color .16s ease,box-shadow .16s ease}button:hover:not(:disabled){transform:translateY(-1px)}
@keyframes ym-authoring-enter{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
@media(max-width:900px){.ym-authoring__taxonomy{grid-template-columns:1fr}.ym-authoring__workspace{padding-block-end:calc(124px + env(safe-area-inset-bottom))}}
@media(max-width:700px){.ym-authoring{gap:15px}.ym-authoring__form{grid-template-columns:1fr}.ym-authoring__card{border-radius:16px;padding:19px 16px}.ym-authoring__card>.ym-authoring__section-head{align-items:flex-start;flex-direction:row}.ym-authoring__status>span{flex:1 1 calc(50% - 7px)}.ym-authoring__notice.is-conflict{align-items:stretch;flex-direction:column}.ym-authoring__conflict-actions{display:grid;grid-template-columns:1fr}.ym-authoring__conflict-actions button{min-height:44px}.ym-authoring__workspace{padding-block-end:calc(164px + env(safe-area-inset-bottom))}.ym-authoring__designer-current{align-items:flex-start;flex-wrap:wrap}.ym-authoring__designer-current button{min-height:44px}.ym-authoring__card.is-media{padding:18px 15px}.ym-authoring__media-shell{padding-block-end:34px}}
@media(max-width:440px){.ym-authoring__status>span{flex-basis:100%}.ym-authoring__section-head>span{width:38px;height:38px}}
@media(prefers-reduced-motion:reduce){.ym-authoring__section-stack>.ym-authoring__card,.ym-authoring__workspace>[data-authoring-section]{animation:none}.ym-authoring input,.ym-authoring textarea,.ym-authoring select,button{scroll-behavior:auto;transition:none}button:hover:not(:disabled){transform:none}}
</style>
