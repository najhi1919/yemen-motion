<template>
  <WorksDrawerShell
    :open="open && Boolean(work)"
    :locale="locale"
    size="taxonomy"
    title-id="ym-assignment-title"
    :close-label="text.close"
    :busy="mutationBusy"
    :unsaved="hasUnsavedChanges"
    :unsaved-label="text.unsaved"
    @request-close="requestClose"
  >
    <template #header>
      <button v-if="returnToDetails" type="button" class="ym-assignment-back" @click="requestClose">
        <span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span>{{ text.backToDetails }}
      </button>
      <span class="ym-assignment-eyebrow">{{ text.eyebrow }}</span>
      <h2 id="ym-assignment-title">{{ text.title }}</h2>
      <strong v-if="work" class="ym-assignment-work">{{ work.title }}</strong>
      <div v-if="work" class="ym-assignment-head-meta">
        <span v-if="work.status">{{ statusLabel(work.status) }}</span>
        <span v-if="work.media_type">{{ mediaLabel(work.media_type) }}</span>
        <code dir="ltr">{{ work.slug }}</code>
      </div>
    </template>

    <div class="ym-assignment-live" aria-live="polite">
      <p v-if="liveMessage" class="is-success">{{ liveMessage }}</p>
      <p v-if="globalError" class="is-error">{{ globalError }}</p>
    </div>

    <div class="ym-assignment-content">
      <section v-if="currentTracking !== null" class="ym-assignment-section">
        <header>
          <div><span>{{ text.categoryEyebrow }}</span><h3>{{ text.categoryTitle }}</h3><p>{{ canUpdateCategory ? text.categoryCopy : text.readOnlyCopy }}</p></div>
          <button v-if="canUpdateCategory && !categoryEditing" type="button" class="is-edit" @click="startCategoryEditing">{{ text.edit }}</button>
          <b v-else-if="!canUpdateCategory">{{ text.readOnly }}</b>
        </header>

        <div class="ym-assignment-current">
          <span>{{ text.currentCategory }}</span>
          <template v-if="currentCategory">
            <strong>{{ name(currentCategory) }}</strong>
            <small :class="currentCategory.is_active ? 'is-active' : 'is-disabled'">{{ currentCategory.is_active ? text.active : text.disabled }}</small>
            <details><summary>{{ text.secondaryInfo }}</summary><code dir="ltr">{{ currentCategory.slug }}</code></details>
            <p v-if="!currentCategory.is_active">{{ text.disabledCategoryHint }}</p>
          </template>
          <template v-else-if="currentTracking.is_legacy_unmapped">
            <strong class="is-warning">{{ text.legacy }}</strong>
            <p>{{ text.legacyHint }}</p>
          </template>
          <div v-else class="ym-assignment-empty-state"><span aria-hidden="true">◇</span><strong>{{ text.uncategorized }}</strong></div>
        </div>

        <template v-if="canUpdateCategory && categoryEditing">
          <label class="ym-assignment-search">
            <span>{{ text.searchCategory }}</span>
            <input v-model.trim="categoryQuery" type="search" maxlength="80" autocomplete="off" />
          </label>
          <p v-if="categoryQuery.length === 1" class="ym-assignment-hint">{{ text.twoChars }}</p>
          <div v-if="categoryCatalogLoading" class="ym-assignment-state" role="status">{{ text.searching }}</div>
          <p v-else-if="categoryCatalogError" class="ym-assignment-error" role="alert">{{ categoryCatalogError }} <button type="button" @click="searchCategories">{{ text.retry }}</button></p>
          <div v-else class="ym-assignment-options">
            <label class="ym-assignment-option" :class="{ 'is-selected': selectedCategoryId === null }">
              <input v-model="selectedCategoryId" type="radio" name="assignment-category" :value="null" />
              <span><strong>{{ text.removeCategory }}</strong><small>{{ text.removeCategoryHint }}</small></span>
            </label>
            <label v-for="category in categoryResults" :key="category.id" class="ym-assignment-option" :class="{ 'is-selected': selectedCategoryId === category.id }">
              <input v-model="selectedCategoryId" type="radio" name="assignment-category" :value="category.id" />
              <span><strong>{{ name(category) }}</strong><code dir="ltr">{{ category.slug }}</code></span>
            </label>
            <p v-if="categoryResults.length === 0" class="ym-assignment-empty">{{ text.noResults }}</p>
          </div>
          <p v-if="categoryFieldError" class="ym-assignment-error" role="alert">{{ categoryFieldError }}</p>
        </template>
      </section>

      <section v-if="currentTags !== null" class="ym-assignment-section">
        <header>
          <div><span>{{ text.tagsEyebrow }}</span><h3>{{ text.tagsTitle }}</h3><p>{{ canUpdateTags ? text.tagsCopy : text.readOnlyCopy }}</p></div>
          <button v-if="canUpdateTags && !tagsEditing" type="button" class="is-edit" @click="startTagsEditing">{{ text.edit }}</button>
          <b v-else-if="!canUpdateTags">{{ text.readOnly }}</b>
        </header>

        <div class="ym-assignment-selected">
          <div><span>{{ text.selectedTags }}</span><strong dir="ltr">{{ formatCount(selectedTags.length) }} / 50</strong></div>
          <div v-if="selectedTags.length" class="ym-assignment-chips">
            <template v-for="tag in selectedTags" :key="tag.id">
              <button v-if="canUpdateTags && tagsEditing" type="button" :class="{ 'is-disabled': !tag.is_active }" :disabled="tagMutationLoading" :aria-label="text.removeTag(name(tag))" @click="removeTag(tag.id)">
                {{ name(tag) }}<small v-if="!tag.is_active">{{ text.disabled }}</small><span aria-hidden="true">×</span>
              </button>
              <span v-else class="ym-assignment-chip-readonly" :class="{ 'is-disabled': !tag.is_active }">{{ name(tag) }}<small v-if="!tag.is_active">{{ text.disabled }}</small></span>
            </template>
          </div>
          <div v-else class="ym-assignment-empty-state"><span aria-hidden="true">◇</span><strong>{{ text.noSelectedTags }}</strong></div>
          <small v-if="selectedTags.some(tag => !tag.is_active)">{{ text.disabledTagsHint }}</small>
        </div>

        <template v-if="canUpdateTags && tagsEditing">
          <label class="ym-assignment-search">
            <span>{{ text.searchTags }}</span>
            <input v-model.trim="tagQuery" type="search" maxlength="80" autocomplete="off" />
          </label>
          <p v-if="tagQuery.length === 1" class="ym-assignment-hint">{{ text.twoChars }}</p>
          <div v-if="tagCatalogLoading" class="ym-assignment-state" role="status">{{ text.searching }}</div>
          <p v-else-if="tagCatalogError" class="ym-assignment-error" role="alert">{{ tagCatalogError }} <button type="button" @click="searchTags">{{ text.retry }}</button></p>
          <div v-else class="ym-assignment-tag-results">
            <article v-for="tag in availableTagResults" :key="tag.id">
              <span><strong>{{ name(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code></span>
              <button type="button" :disabled="selectedTags.length >= 50" @click="addTag(tag)">{{ text.add }}</button>
            </article>
            <p v-if="availableTagResults.length === 0" class="ym-assignment-empty">{{ text.noResults }}</p>
          </div>
          <p v-if="tagFieldError" class="ym-assignment-error" role="alert">{{ tagFieldError }}</p>
        </template>
      </section>
    </div>

    <div
      v-if="discardConfirmation"
      class="ym-assignment-confirm"
      role="alertdialog"
      aria-modal="true"
      :aria-labelledby="'ym-assignment-confirm-title'"
      @keydown.tab="trapConfirmFocus"
      @keydown.esc.stop.prevent="discardConfirmation = false"
    >
      <div>
        <span aria-hidden="true">!</span>
        <h3 id="ym-assignment-confirm-title">{{ text.unsavedTitle }}</h3>
        <p>{{ text.unsavedCopy }}</p>
        <footer>
          <button ref="continueButtonRef" type="button" class="is-secondary" @click="discardConfirmation = false">{{ text.continueEditing }}</button>
          <button ref="discardButtonRef" type="button" class="is-danger" @click="discardAndClose">{{ text.discard }}</button>
        </footer>
      </div>
    </div>

    <template v-if="(categoryEditing || tagsEditing) && !discardConfirmation" #footer>
      <div class="ym-assignment-footer">
        <button v-if="categoryEditing" type="button" class="is-primary" :disabled="!categoryChanged || categoryMutationLoading || !canUpdateCategory" @click="saveCategory">
          {{ categoryMutationLoading ? text.saving : text.saveCategory }}
        </button>
        <button v-if="tagsEditing" type="button" class="is-primary" :disabled="!tagsChanged || tagMutationLoading || selectedTags.length > 50 || !canUpdateTags" @click="saveTags">
          {{ tagMutationLoading ? text.saving : text.saveTags }}
        </button>
        <button type="button" class="is-secondary" :disabled="mutationBusy" @click="cancelChanges">{{ text.cancelChanges }}</button>
      </div>
    </template>
  </WorksDrawerShell>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import WorksDrawerShell from '~/components/works/drawers/WorksDrawerShell.vue'
import { useApiClient } from '~/composables/useApiClient'
import { formatYmNumber } from '~/utils/ymFormatting'

interface SafeEntity {
  id: number
  name_ar: string
  name_en: string
  slug: string
  disabled_at: string | null
  is_active: boolean
  sort_order: number
}
interface CategoryTracking {
  catalog_record_exists: boolean
  is_legacy_unmapped: boolean
  is_uncategorized: boolean
}
interface AssignmentWork {
  id: number
  title: string
  slug: string
  status?: string
  media_type?: string | null
  category_id: number | null
  taxonomy: {
    category: SafeEntity | null
    category_tracking: CategoryTracking | null
    tags: SafeEntity[] | null
  }
}
interface CatalogResponse {
  success: boolean
  data: { items: SafeEntity[] } | null
  message?: string
}
interface CategoryMutationResponse {
  success: boolean
  data: {
    work: {
      id: number
      previous_category_id: number | null
      category_id: number | null
      category: SafeEntity | null
    }
    changed: boolean
  } | null
  message?: string
}
interface TagsMutationResponse {
  success: boolean
  data: {
    work: {
      id: number
      previous_tag_ids: number[]
      tag_ids: number[]
      added_tag_ids: number[]
      removed_tag_ids: number[]
      tags: SafeEntity[]
    }
    changed: boolean
  } | null
  message?: string
}

const props = defineProps<{
  open: boolean
  work: AssignmentWork | null
  locale: 'ar' | 'en'
  canUpdateCategory: boolean
  canUpdateTags: boolean
  permissionRevision: string
  returnToDetails?: boolean
}>()
const emit = defineEmits<{
  close: []
  changed: [payload: {
    work_id: number
    section: 'category' | 'tags'
    changed: boolean
    category_id?: number | null
    category?: SafeEntity | null
    category_tracking?: CategoryTracking | null
    tags?: SafeEntity[]
  }]
  authorizationError: []
}>()
const { apiFetch } = useApiClient()
const currentCategory = ref<SafeEntity | null>(null)
const currentTracking = ref<CategoryTracking | null>(null)
const currentTags = ref<SafeEntity[] | null>(null)
const originalCategoryId = ref<number | null>(null)
const selectedCategoryId = ref<number | null>(null)
const originalTagIds = ref<number[]>([])
const selectedTags = ref<SafeEntity[]>([])
const categoryQuery = ref('')
const tagQuery = ref('')
const categoryEditing = ref(false)
const tagsEditing = ref(false)
const categoryResults = ref<SafeEntity[]>([])
const tagResults = ref<SafeEntity[]>([])
const categoryCatalogLoading = ref(false)
const tagCatalogLoading = ref(false)
const categoryMutationLoading = ref(false)
const tagMutationLoading = ref(false)
const categoryCatalogError = ref<string | null>(null)
const tagCatalogError = ref<string | null>(null)
const categoryFieldError = ref<string | null>(null)
const tagFieldError = ref<string | null>(null)
const globalError = ref<string | null>(null)
const liveMessage = ref('')
const discardConfirmation = ref(false)
const continueButtonRef = ref<HTMLButtonElement | null>(null)
const discardButtonRef = ref<HTMLButtonElement | null>(null)
let drawerRevision = 0
let categorySearchRevision = 0
let tagSearchRevision = 0
let categoryMutationRevision = 0
let tagMutationRevision = 0
let categoryTimer: ReturnType<typeof setTimeout> | null = null
let tagTimer: ReturnType<typeof setTimeout> | null = null
let resettingSearch = false

const copies = {
  ar: {
    eyebrow: 'إدارة بنية العمل', title: 'إدارة التصنيف والوسوم', close: 'إغلاق لوحة التصنيف والوسوم', backToDetails: 'العودة إلى التفاصيل', unsaved: 'تغييرات غير محفوظة',
    categoryEyebrow: 'تصنيف العمل', categoryTitle: 'التصنيف الحالي', categoryCopy: 'اختر تصنيفًا فعالًا أو أزل التصنيف.', tagsEyebrow: 'وسوم العمل', tagsTitle: 'مجموعة الوسوم', tagsCopy: 'الحفظ يستبدل مجموعة الوسوم كاملة بالحالة المحددة.', readOnlyCopy: 'هذا القسم متاح للقراءة فقط.', edit: 'تعديل', readOnly: 'قراءة فقط',
    currentCategory: 'الحالة الحالية', active: 'فعال', disabled: 'معطل', disabledCategoryHint: 'هذا تصنيف قديم معطل؛ يمكن استبداله أو إزالته ولا يظهر كهدف جديد.', legacy: 'قيمة قديمة غير مربوطة', legacyHint: 'اختر تصنيفًا فعالًا أو أزل هذه القيمة القديمة.', uncategorized: 'غير مصنف',
    searchCategory: 'البحث في التصنيفات الفعالة', searchTags: 'البحث في الوسوم الفعالة', twoChars: 'اكتب حرفين على الأقل للبحث.', searching: 'جارٍ البحث…', retry: 'إعادة المحاولة', noResults: 'لا توجد نتائج متاحة.', removeCategory: 'إزالة التصنيف / غير مصنف', removeCategoryHint: 'يحفظ category_id بقيمة null.', saveCategory: 'حفظ التصنيف', saving: 'جارٍ الحفظ…',
    selectedTags: 'الوسوم المحددة', noSelectedTags: 'لا توجد وسوم محددة؛ الحفظ سيمسح الإسنادات.', disabledTagsHint: 'الوسوم المعطلة مرتبطة مسبقًا: يمكن إبقاؤها أو إزالتها، ولا يمكن إضافتها من البحث.', removeTag: (name: string) => `إزالة الوسم ${name}`, add: 'إضافة', saveTags: 'حفظ مجموعة الوسوم',
    generic: 'تعذر إكمال الطلب. حاول مرة أخرى.', secondaryInfo: 'معلومات ثانوية', cancelChanges: 'إلغاء التغييرات',
    unsavedTitle: 'لديك تغييرات غير محفوظة', unsavedCopy: 'يمكنك متابعة التعديل أو تجاهل التغييرات الحالية.', continueEditing: 'متابعة التعديل', discard: 'تجاهل التغييرات',
    image: 'صورة', video: 'فيديو', gallery: 'معرض صور', unknownMedia: 'غير محدد'
  },
  en: {
    eyebrow: 'Work structure management', title: 'Manage category and tags', close: 'Close category and tags drawer', backToDetails: 'Back to details', unsaved: 'Unsaved changes',
    categoryEyebrow: 'Work category', categoryTitle: 'Current category', categoryCopy: 'Choose an active category or remove the category.', tagsEyebrow: 'Work tags', tagsTitle: 'Tag set', tagsCopy: 'Saving replaces the complete tag set with the selected state.', readOnlyCopy: 'This section is read-only.', edit: 'Edit', readOnly: 'Read only',
    currentCategory: 'Current state', active: 'Active', disabled: 'Disabled', disabledCategoryHint: 'This is a previously linked disabled category; replace or remove it. It is not offered as a new target.', legacy: 'Unmapped legacy value', legacyHint: 'Choose an active category or remove this legacy value.', uncategorized: 'Uncategorized',
    searchCategory: 'Search active categories', searchTags: 'Search active tags', twoChars: 'Enter at least two characters to search.', searching: 'Searching…', retry: 'Retry', noResults: 'No available results.', removeCategory: 'Remove category / Uncategorized', removeCategoryHint: 'Saves category_id as null.', saveCategory: 'Save category', saving: 'Saving…',
    selectedTags: 'Selected tags', noSelectedTags: 'No tags selected; saving clears all assignments.', disabledTagsHint: 'Disabled tags were previously linked: keep or remove them, but they cannot be added from search.', removeTag: (name: string) => `Remove tag ${name}`, add: 'Add', saveTags: 'Save tag set',
    generic: 'Could not complete the request. Try again.', secondaryInfo: 'Secondary information', cancelChanges: 'Cancel changes',
    unsavedTitle: 'You have unsaved changes', unsavedCopy: 'Continue editing or discard the current changes.', continueEditing: 'Continue editing', discard: 'Discard changes',
    image: 'Image', video: 'Video', gallery: 'Gallery', unknownMedia: 'Unspecified'
  }
}
const text = computed(() => copies[props.locale])
const mutationBusy = computed(() => categoryMutationLoading.value || tagMutationLoading.value)
const categoryChanged = computed(() => selectedCategoryId.value !== originalCategoryId.value)
const tagsChanged = computed(() => {
  const selected = selectedTags.value.map(tag => tag.id).sort((a, b) => a - b)
  return selected.length !== originalTagIds.value.length || selected.some((id, index) => id !== originalTagIds.value[index])
})
const availableTagResults = computed(() => {
  const selected = new Set(selectedTags.value.map(tag => tag.id))
  return tagResults.value.filter(tag => tag.is_active && !selected.has(tag.id))
})
const hasUnsavedChanges = computed(() => categoryChanged.value || tagsChanged.value)

watch(() => props.open, async (open) => {
  drawerRevision++
  if (!open) {
    invalidateRequests()
    return
  }
  resetSearchState()
  discardConfirmation.value = false
  categoryEditing.value = false
  tagsEditing.value = false
  resetFromWork(true)
})
watch(() => props.work, () => {
  if (props.open && !mutationBusy.value) resetFromWork(false)
})
watch(() => props.permissionRevision, async () => {
  drawerRevision++
  invalidateRequests()
  if (!props.open) return
  resetFromWork(false)
  if (!props.canUpdateCategory && !props.canUpdateTags) {
    emit('close')
    return
  }
  if (!props.canUpdateCategory) {
    categoryEditing.value = false
    categoryResults.value = []
  } else if (categoryEditing.value) void searchCategories()
  if (!props.canUpdateTags) {
    tagsEditing.value = false
    tagResults.value = []
  } else if (tagsEditing.value) void searchTags()
})
watch(categoryQuery, query => { if (!resettingSearch) scheduleSearch('category', query) }, { flush: 'sync' })
watch(tagQuery, query => { if (!resettingSearch) scheduleSearch('tag', query) }, { flush: 'sync' })
watch(discardConfirmation, async (open) => {
  if (!open) return
  await nextTick()
  continueButtonRef.value?.focus()
})
onUnmounted(() => {
  drawerRevision++
  invalidateRequests()
})

function resetFromWork(clearMessage: boolean) {
  const taxonomy = props.work?.taxonomy
  currentCategory.value = taxonomy?.category ?? null
  currentTracking.value = taxonomy?.category_tracking ?? null
  currentTags.value = taxonomy?.tags ? [...taxonomy.tags] : taxonomy?.tags ?? null
  originalCategoryId.value = props.work?.category_id ?? null
  selectedCategoryId.value = originalCategoryId.value
  selectedTags.value = taxonomy?.tags ? [...taxonomy.tags] : []
  originalTagIds.value = sortedIds(selectedTags.value)
  categoryFieldError.value = null
  tagFieldError.value = null
  globalError.value = null
  if (clearMessage) liveMessage.value = ''
}
function resetSearchState() {
  resettingSearch = true
  if (categoryTimer) clearTimeout(categoryTimer)
  if (tagTimer) clearTimeout(tagTimer)
  categoryTimer = null
  tagTimer = null
  categoryQuery.value = ''
  tagQuery.value = ''
  categoryResults.value = []
  tagResults.value = []
  categoryCatalogError.value = null
  tagCatalogError.value = null
  resettingSearch = false
}
function invalidateRequests() {
  categorySearchRevision++
  tagSearchRevision++
  categoryMutationRevision++
  tagMutationRevision++
  categoryCatalogLoading.value = false
  tagCatalogLoading.value = false
  categoryMutationLoading.value = false
  tagMutationLoading.value = false
  if (categoryTimer) clearTimeout(categoryTimer)
  if (tagTimer) clearTimeout(tagTimer)
  categoryTimer = null
  tagTimer = null
}
function scheduleSearch(kind: 'category' | 'tag', rawQuery: string) {
  if (!props.open) return
  if (kind === 'category' && !categoryEditing.value) return
  if (kind === 'tag' && !tagsEditing.value) return
  const query = rawQuery.trim()
  const timer = kind === 'category' ? categoryTimer : tagTimer
  if (timer) clearTimeout(timer)
  if (query.length === 1) {
    if (kind === 'category') {
      categorySearchRevision++
      categoryCatalogLoading.value = false
      categoryCatalogError.value = null
      categoryResults.value = []
    } else {
      tagSearchRevision++
      tagCatalogLoading.value = false
      tagCatalogError.value = null
      tagResults.value = []
    }
    return
  }
  const nextTimer = setTimeout(() => {
    if (kind === 'category') void searchCategories()
    else void searchTags()
  }, 300)
  if (kind === 'category') categoryTimer = nextTimer
  else tagTimer = nextTimer
}
async function searchCategories() {
  if (!props.open || !props.canUpdateCategory || !categoryEditing.value || categoryQuery.value.trim().length === 1) return
  const currentDrawer = drawerRevision
  const currentSearch = ++categorySearchRevision
  categoryCatalogLoading.value = true
  categoryCatalogError.value = null
  try {
    const query = catalogQuery(categoryQuery.value)
    const response = await apiFetch<CatalogResponse>('/admin/works/taxonomy/categories', { query })
    if (!isCurrentSearch('category', currentDrawer, currentSearch)) return
    if (!response.success || !response.data) throw new Error('invalid')
    categoryResults.value = response.data.items.filter(item => item.is_active)
  } catch (error: unknown) {
    if (!isCurrentSearch('category', currentDrawer, currentSearch)) return
    if (isAuthorizationError(error)) return handleAuthorizationError()
    categoryCatalogError.value = serverMessage(error) || text.value.generic
  } finally {
    if (isCurrentSearch('category', currentDrawer, currentSearch)) categoryCatalogLoading.value = false
  }
}
async function searchTags() {
  if (!props.open || !props.canUpdateTags || !tagsEditing.value || tagQuery.value.trim().length === 1) return
  const currentDrawer = drawerRevision
  const currentSearch = ++tagSearchRevision
  tagCatalogLoading.value = true
  tagCatalogError.value = null
  try {
    const query = catalogQuery(tagQuery.value)
    const response = await apiFetch<CatalogResponse>('/admin/works/taxonomy/tags', { query })
    if (!isCurrentSearch('tag', currentDrawer, currentSearch)) return
    if (!response.success || !response.data) throw new Error('invalid')
    tagResults.value = response.data.items.filter(item => item.is_active)
  } catch (error: unknown) {
    if (!isCurrentSearch('tag', currentDrawer, currentSearch)) return
    if (isAuthorizationError(error)) return handleAuthorizationError()
    tagCatalogError.value = serverMessage(error) || text.value.generic
  } finally {
    if (isCurrentSearch('tag', currentDrawer, currentSearch)) tagCatalogLoading.value = false
  }
}
function catalogQuery(rawQuery: string): Record<string, string | number> {
  const query: Record<string, string | number> = {
    state: 'active',
    sort: 'sort_order',
    direction: 'asc',
    page: 1,
    per_page: 50
  }
  const value = rawQuery.trim()
  if (value.length >= 2) query.q = value
  return query
}
function isCurrentSearch(kind: 'category' | 'tag', currentDrawer: number, currentSearch: number) {
  return props.open
    && currentDrawer === drawerRevision
    && currentSearch === (kind === 'category' ? categorySearchRevision : tagSearchRevision)
}
async function saveCategory() {
  if (!props.work || !props.canUpdateCategory || !categoryChanged.value || categoryMutationLoading.value) return
  const currentDrawer = drawerRevision
  const currentMutation = ++categoryMutationRevision
  categoryMutationLoading.value = true
  categoryFieldError.value = null
  globalError.value = null
  try {
    const response = await apiFetch<CategoryMutationResponse>(`/admin/works/${props.work.id}/taxonomy/category`, {
      method: 'PATCH',
      body: { category_id: selectedCategoryId.value }
    })
    if (!response.success || !response.data) throw new Error('invalid')
    if (!isCurrentMutation('category', currentDrawer, currentMutation)) return
    currentCategory.value = response.data.work.category
    originalCategoryId.value = response.data.work.category_id
    selectedCategoryId.value = response.data.work.category_id
    currentTracking.value = {
      catalog_record_exists: response.data.work.category !== null,
      is_legacy_unmapped: false,
      is_uncategorized: response.data.work.category_id === null
    }
    categoryEditing.value = false
    categoryResults.value = []
    categoryQuery.value = ''
    liveMessage.value = response.message || ''
    emit('changed', {
      work_id: props.work.id,
      section: 'category',
      changed: response.data.changed,
      category_id: response.data.work.category_id,
      category: response.data.work.category,
      category_tracking: currentTracking.value
    })
  } catch (error: unknown) {
    if (!isCurrentMutation('category', currentDrawer, currentMutation)) return
    if (isAuthorizationError(error)) return handleAuthorizationError()
    if (errorStatus(error) === 422) categoryFieldError.value = fieldError(error, ['category_id'])
    else globalError.value = serverMessage(error) || text.value.generic
  } finally {
    if (isCurrentMutation('category', currentDrawer, currentMutation)) categoryMutationLoading.value = false
  }
}
async function saveTags() {
  if (!props.work || !props.canUpdateTags || !tagsChanged.value || tagMutationLoading.value || selectedTags.value.length > 50) return
  const currentDrawer = drawerRevision
  const currentMutation = ++tagMutationRevision
  tagMutationLoading.value = true
  tagFieldError.value = null
  globalError.value = null
  try {
    const response = await apiFetch<TagsMutationResponse>(`/admin/works/${props.work.id}/taxonomy/tags`, {
      method: 'PATCH',
      body: { tag_ids: sortedIds(selectedTags.value) }
    })
    if (!response.success || !response.data) throw new Error('invalid')
    if (!isCurrentMutation('tag', currentDrawer, currentMutation)) return
    currentTags.value = [...response.data.work.tags]
    selectedTags.value = [...response.data.work.tags]
    originalTagIds.value = [...response.data.work.tag_ids].sort((a, b) => a - b)
    tagsEditing.value = false
    tagResults.value = []
    tagQuery.value = ''
    liveMessage.value = response.message || ''
    emit('changed', {
      work_id: props.work.id,
      section: 'tags',
      changed: response.data.changed,
      tags: response.data.work.tags
    })
  } catch (error: unknown) {
    if (!isCurrentMutation('tag', currentDrawer, currentMutation)) return
    if (isAuthorizationError(error)) return handleAuthorizationError()
    if (errorStatus(error) === 422) tagFieldError.value = fieldError(error, ['tag_ids', 'tag_ids.0'])
    else globalError.value = serverMessage(error) || text.value.generic
  } finally {
    if (isCurrentMutation('tag', currentDrawer, currentMutation)) tagMutationLoading.value = false
  }
}
function isCurrentMutation(kind: 'category' | 'tag', currentDrawer: number, currentMutation: number) {
  return props.open
    && currentDrawer === drawerRevision
    && currentMutation === (kind === 'category' ? categoryMutationRevision : tagMutationRevision)
}
function startCategoryEditing() {
  if (!props.canUpdateCategory || categoryEditing.value) return
  categoryEditing.value = true
  void searchCategories()
}
function startTagsEditing() {
  if (!props.canUpdateTags || tagsEditing.value) return
  tagsEditing.value = true
  void searchTags()
}
function addTag(tag: SafeEntity) {
  if (!props.canUpdateTags || !tag.is_active || selectedTags.value.length >= 50) return
  if (!selectedTags.value.some(item => item.id === tag.id)) selectedTags.value.push(tag)
  tagFieldError.value = null
}
function removeTag(id: number) {
  if (!props.canUpdateTags || !tagsEditing.value || tagMutationLoading.value) return
  selectedTags.value = selectedTags.value.filter(tag => tag.id !== id)
  tagFieldError.value = null
}
function sortedIds(tags: SafeEntity[]) {
  return tags.map(tag => tag.id).sort((a, b) => a - b)
}
function requestClose(_reason?: 'button' | 'backdrop' | 'escape') {
  if (mutationBusy.value) return
  if (hasUnsavedChanges.value) {
    discardConfirmation.value = true
    return
  }
  finalizeClose()
}
function finalizeClose() {
  drawerRevision++
  invalidateRequests()
  discardConfirmation.value = false
  emit('close')
}
function discardAndClose() {
  cancelChanges()
  finalizeClose()
}
function cancelChanges() {
  selectedCategoryId.value = originalCategoryId.value
  selectedTags.value = currentTags.value ? [...currentTags.value] : []
  categoryEditing.value = false
  tagsEditing.value = false
  resetSearchState()
  categoryFieldError.value = null
  tagFieldError.value = null
  discardConfirmation.value = false
}
function trapConfirmFocus(event: KeyboardEvent) {
  if (event.shiftKey && document.activeElement === continueButtonRef.value) {
    event.preventDefault()
    discardButtonRef.value?.focus()
  } else if (!event.shiftKey && document.activeElement === discardButtonRef.value) {
    event.preventDefault()
    continueButtonRef.value?.focus()
  }
}
function handleAuthorizationError() {
  categoryResults.value = []
  tagResults.value = []
  invalidateRequests()
  emit('authorizationError')
  emit('close')
}
function name(entity: SafeEntity) {
  return props.locale === 'ar' ? entity.name_ar : entity.name_en
}
function formatCount(value: number) {
  return formatYmNumber(value, props.locale)
}
function mediaLabel(value: string | null | undefined) {
  return value === 'image' ? text.value.image : value === 'video' ? text.value.video : value === 'gallery' ? text.value.gallery : text.value.unknownMedia
}
function statusLabel(value: string) {
  const ar: Record<string, string> = { draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' }
  const en: Record<string, string> = { draft: 'Draft', submitted: 'Submitted', in_review: 'In review', changes_requested: 'Changes requested', approved: 'Approved', published: 'Published', rejected: 'Rejected', hidden: 'Hidden', archived: 'Archived' }
  return (props.locale === 'ar' ? ar : en)[value] || value
}
function errorStatus(error: unknown): number | null {
  if (!error || typeof error !== 'object') return null
  const item = error as { status?: number; statusCode?: number; response?: { status?: number } }
  return item.response?.status ?? item.statusCode ?? item.status ?? null
}
function isAuthorizationError(error: unknown) {
  return [401, 403].includes(errorStatus(error) ?? 0)
}
function errorData(error: unknown): Record<string, unknown> | null {
  if (!error || typeof error !== 'object') return null
  const item = error as { data?: unknown; response?: { _data?: unknown } }
  const data = item.data ?? item.response?._data
  return data && typeof data === 'object' ? data as Record<string, unknown> : null
}
function serverMessage(error: unknown): string | null {
  const message = errorData(error)?.message
  return typeof message === 'string' ? message : null
}
function fieldError(error: unknown, keys: string[]): string {
  const errors = errorData(error)?.errors
  if (!errors || typeof errors !== 'object') return serverMessage(error) || text.value.generic
  const record = errors as Record<string, string[]>
  for (const key of keys) {
    if (record[key]?.[0]) return record[key][0]
  }
  const nestedKey = Object.keys(record).find(key => keys[0] === 'tag_ids' && key.startsWith('tag_ids.'))
  return (nestedKey ? record[nestedKey]?.[0] : null) || serverMessage(error) || text.value.generic
}
</script>

<style scoped>
.ym-assignment-eyebrow { color: var(--ym-drawer-electric); font-size: 12.5px; font-weight: 850; }
h2 { margin: 0; color: var(--ym-drawer-text); font-size: clamp(24px, 3vw, 28px); font-weight: 900; line-height: 1.2; }
.ym-assignment-work { color: var(--ym-drawer-text); font-size: 17px; line-height: 1.4; overflow-wrap: anywhere; }
.ym-assignment-head-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 7px; }
.ym-assignment-head-meta span, .ym-assignment-head-meta code { border-radius: 999px; padding: 4px 8px; color: var(--ym-drawer-muted); background: var(--ym-drawer-control); font-size: 12.5px; }
.ym-assignment-head-meta code { color: var(--ym-drawer-electric); }
.ym-assignment-back { display: inline-flex; width: fit-content; min-height: 34px; align-items: center; gap: 6px; border: 0; padding: 0; color: var(--ym-drawer-electric); background: transparent; font-size: 13px; font-weight: 800; cursor: pointer; }
.ym-assignment-back:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 40%, transparent); outline-offset: 2px; }
.ym-assignment-live { min-height: 0; }.ym-assignment-live p { border-radius: 12px; margin: 0 0 12px; padding: 10px 12px; font-size: 13px; font-weight: 800; }.is-success { color: var(--ym-drawer-emerald); background: color-mix(in srgb, var(--ym-drawer-emerald) 10%, transparent); }.is-error, .ym-assignment-error { color: var(--ym-drawer-rose); }
.ym-assignment-content { display: grid; gap: 16px; }
.ym-assignment-section { border: 1px solid var(--ym-drawer-soft-border); border-radius: 18px; padding: 19px 20px; background: var(--ym-drawer-card); box-shadow: inset 0 1px 0 color-mix(in srgb, #fff 8%, transparent), 0 12px 28px rgba(2, 6, 23, .06); }
.ym-assignment-section > header { display: flex; justify-content: space-between; gap: 14px; }.ym-assignment-section > header span { color: var(--ym-drawer-electric); font-size: 12.5px; font-weight: 850; }.ym-assignment-section h3 { margin: 3px 0; color: var(--ym-drawer-text); font-size: 18px; }.ym-assignment-section header p { margin: 0; color: var(--ym-drawer-muted); font-size: 13px; line-height: 1.55; }.ym-assignment-section > header > b { align-self: flex-start; border-radius: 999px; padding: 5px 8px; color: var(--ym-drawer-muted); background: var(--ym-drawer-control); font-size: 12.5px; }.ym-assignment-section .is-edit { align-self: flex-start; min-height: 38px; border: 1px solid color-mix(in srgb, var(--ym-drawer-electric) 36%, var(--ym-drawer-border)); border-radius: 11px; padding: 0 11px; color: var(--ym-drawer-electric); background: color-mix(in srgb, var(--ym-drawer-electric) 9%, transparent); font-size: 13px; font-weight: 800; cursor: pointer; }
.ym-assignment-current, .ym-assignment-selected { display: grid; gap: 7px; border-block-start: 1px solid var(--ym-drawer-soft-border); margin-top: 15px; padding-top: 14px; }.ym-assignment-current > span, .ym-assignment-selected > div:first-child > span { color: var(--ym-drawer-muted); font-size: 12.5px; font-weight: 750; }.ym-assignment-current > strong { font-size: 15px; }.ym-assignment-current small { width: fit-content; font-size: 12.5px; }.ym-assignment-current details { width: fit-content; color: var(--ym-drawer-muted); font-size: 12.5px; }.ym-assignment-current details code { display: block; margin-top: 5px; color: var(--ym-drawer-electric); }.is-active { color: var(--ym-drawer-emerald); }.is-disabled, .is-warning { color: var(--ym-drawer-amber); }.ym-assignment-current p, .ym-assignment-selected > small { margin: 0; color: var(--ym-drawer-muted); font-size: 13px; line-height: 1.6; }
.ym-assignment-empty-state { display: flex; align-items: center; gap: 9px; border-radius: 13px; padding: 11px; color: var(--ym-drawer-muted); background: var(--ym-drawer-control); }.ym-assignment-empty-state > span { font-size: 20px; }.ym-assignment-empty-state strong { font-size: 13.5px; }
.ym-assignment-search { display: grid; gap: 6px; margin-top: 15px; }.ym-assignment-search span { color: var(--ym-drawer-muted); font-size: 12.5px; font-weight: 750; }.ym-assignment-search input { min-height: 44px; border: 1px solid var(--ym-drawer-border); border-radius: 12px; outline: none; padding: 0 12px; color: var(--ym-drawer-text); background: var(--ym-drawer-control); font-size: 14px; }.ym-assignment-search input:focus { border-color: var(--ym-drawer-electric); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ym-drawer-electric) 20%, transparent); }.ym-assignment-hint { color: var(--ym-drawer-muted); font-size: 12.5px; }.ym-assignment-state, .ym-assignment-empty { padding: 16px; color: var(--ym-drawer-muted); text-align: center; font-size: 13.5px; }.ym-assignment-error { font-size: 13px; font-weight: 750; }.ym-assignment-error button { border: 0; color: var(--ym-drawer-electric); background: transparent; font-weight: 800; }
.ym-assignment-options, .ym-assignment-tag-results { display: grid; max-height: 290px; gap: 7px; overflow: auto; margin-top: 10px; overscroll-behavior: contain; }.ym-assignment-option { display: flex; gap: 9px; border: 1px solid var(--ym-drawer-soft-border); border-radius: 13px; padding: 11px; cursor: pointer; }.ym-assignment-option.is-selected { border-color: var(--ym-drawer-electric); background: color-mix(in srgb, var(--ym-drawer-electric) 8%, transparent); }.ym-assignment-option > span { display: grid; gap: 3px; }.ym-assignment-option strong { font-size: 13.5px; }.ym-assignment-option code { color: var(--ym-drawer-electric); font-size: 12.5px; }.ym-assignment-option small { color: var(--ym-drawer-muted); font-size: 12.5px; }
.ym-assignment-selected > div:first-child { display: flex; justify-content: space-between; gap: 12px; }.ym-assignment-selected > div:first-child strong { color: var(--ym-drawer-text); font-size: 14px; font-variant-numeric: tabular-nums; }.ym-assignment-chips { display: flex; flex-wrap: wrap; gap: 7px; }.ym-assignment-chips button, .ym-assignment-chip-readonly { display: inline-flex; min-height: 34px; align-items: center; gap: 5px; border: 1px solid color-mix(in srgb, var(--ym-drawer-electric) 30%, var(--ym-drawer-border)); border-radius: 999px; padding: 5px 9px; color: var(--ym-drawer-text); background: color-mix(in srgb, var(--ym-drawer-electric) 9%, transparent); font-size: 13px; }.ym-assignment-chips button.is-disabled, .ym-assignment-chip-readonly.is-disabled { border-color: color-mix(in srgb, var(--ym-drawer-amber) 35%, transparent); color: var(--ym-drawer-amber); background: color-mix(in srgb, var(--ym-drawer-amber) 9%, transparent); }.ym-assignment-chips small { color: var(--ym-drawer-amber); font-size: 12px; }
.ym-assignment-tag-results article { display: flex; align-items: center; justify-content: space-between; gap: 10px; border: 1px solid var(--ym-drawer-soft-border); border-radius: 13px; padding: 11px; }.ym-assignment-tag-results article > span { display: grid; min-width: 0; gap: 3px; }.ym-assignment-tag-results strong { font-size: 13.5px; }.ym-assignment-tag-results code { color: var(--ym-drawer-electric); font-size: 12.5px; overflow-wrap: anywhere; }.ym-assignment-tag-results button { min-height: 36px; border: 1px solid var(--ym-drawer-border); border-radius: 10px; padding: 0 10px; color: var(--ym-drawer-text); background: var(--ym-drawer-control); }
.ym-assignment-footer { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 9px; }.ym-assignment-footer button { min-height: 42px; border-radius: 12px; padding: 0 14px; font-size: 13.5px; font-weight: 800; cursor: pointer; }.ym-assignment-footer .is-primary { border: 0; color: #fff; background: linear-gradient(135deg, var(--ym-drawer-violet), var(--ym-drawer-magenta)); }.ym-assignment-footer .is-secondary { border: 1px solid var(--ym-drawer-border); color: var(--ym-drawer-text); background: var(--ym-drawer-control); }
button:disabled { cursor: not-allowed; opacity: .5; }button:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-drawer-electric) 42%, transparent); outline-offset: 2px; }
.ym-assignment-confirm { position: absolute; z-index: 5; inset: 0; display: grid; place-items: center; padding: 18px; background: rgba(2, 6, 23, .56); backdrop-filter: blur(3px); }.ym-assignment-confirm > div { width: min(100%, 390px); border: 1px solid var(--ym-drawer-border); border-radius: 18px; padding: 20px; color: var(--ym-drawer-text); background: var(--ym-drawer-surface-strong); box-shadow: 0 22px 54px rgba(2, 6, 23, .35); }.ym-assignment-confirm > div > span { display: grid; width: 38px; height: 38px; place-items: center; border-radius: 50%; color: var(--ym-drawer-amber); background: color-mix(in srgb, var(--ym-drawer-amber) 12%, transparent); font-weight: 900; }.ym-assignment-confirm h3 { margin: 10px 0 4px; font-size: 18px; }.ym-assignment-confirm p { margin: 0; color: var(--ym-drawer-muted); font-size: 13.5px; line-height: 1.6; }.ym-assignment-confirm footer { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }.ym-assignment-confirm button { min-height: 42px; border-radius: 11px; padding: 0 12px; font-weight: 800; }.ym-assignment-confirm .is-secondary { border: 1px solid var(--ym-drawer-border); color: var(--ym-drawer-text); background: var(--ym-drawer-control); }.ym-assignment-confirm .is-danger { border: 1px solid color-mix(in srgb, var(--ym-drawer-rose) 45%, transparent); color: #fff; background: var(--ym-drawer-rose); }
@media (max-width: 640px) { .ym-assignment-section { padding: 16px; }.ym-assignment-section > header { display: grid; }.ym-assignment-footer { display: grid; grid-template-columns: 1fr; }.ym-assignment-footer button, .ym-assignment-confirm button { min-height: 44px; }.ym-assignment-confirm footer { display: grid; }.ym-assignment-head-meta code { max-width: 100%; overflow-wrap: anywhere; } }
</style>
