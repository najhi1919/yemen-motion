<template>
  <Teleport to="body">
    <div
      v-if="open && work"
      class="ym-assignment-overlay"
      :class="[
        dashboardTheme === 'light' ? 'is-light' : 'is-dark',
        locale === 'ar' ? 'is-rtl' : 'is-ltr'
      ]"
      role="presentation"
      @click.self="requestClose"
    >
      <section
        ref="drawerRef"
        class="ym-assignment-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ym-assignment-title"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
        tabindex="-1"
        @keydown.esc="requestClose"
      >
        <header class="ym-assignment-head">
          <div>
            <span>{{ text.eyebrow }}</span>
            <h2 id="ym-assignment-title">{{ text.title }}</h2>
            <strong>{{ work.title }}</strong>
            <code dir="ltr">#{{ work.id }} · {{ work.slug }}</code>
          </div>
          <button type="button" :aria-label="text.close" :disabled="mutationBusy" @click="requestClose">×</button>
        </header>

        <div class="ym-assignment-live" aria-live="polite">
          <p v-if="liveMessage" class="is-success">{{ liveMessage }}</p>
          <p v-if="globalError" class="is-error">{{ globalError }}</p>
        </div>

        <main>
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
                <code dir="ltr">{{ currentCategory.slug }}</code>
                <small :class="currentCategory.is_active ? 'is-active' : 'is-disabled'">
                  {{ currentCategory.is_active ? text.active : text.disabled }}
                </small>
                <p v-if="!currentCategory.is_active">{{ text.disabledCategoryHint }}</p>
              </template>
              <template v-else-if="currentTracking.is_legacy_unmapped">
                <strong class="is-warning">{{ text.legacy }}</strong>
                <code dir="ltr">#{{ originalCategoryId }}</code>
                <p>{{ text.legacyHint }}</p>
              </template>
              <strong v-else class="is-muted">{{ text.uncategorized }}</strong>
            </div>

            <template v-if="canUpdateCategory && categoryEditing">
              <label class="ym-assignment-search">
                <span>{{ text.searchCategory }}</span>
                <input v-model.trim="categoryQuery" type="search" maxlength="80" autocomplete="off" />
              </label>
              <p v-if="categoryQuery.length === 1" class="ym-assignment-hint">{{ text.twoChars }}</p>
              <div v-if="categoryCatalogLoading" class="ym-assignment-state" role="status">{{ text.searching }}</div>
              <p v-else-if="categoryCatalogError" class="ym-assignment-error" role="alert">
                {{ categoryCatalogError }}
                <button type="button" @click="searchCategories">{{ text.retry }}</button>
              </p>
              <div v-else class="ym-assignment-options">
                <label class="ym-assignment-option" :class="{ 'is-selected': selectedCategoryId === null }">
                  <input v-model="selectedCategoryId" type="radio" name="assignment-category" :value="null" />
                  <span><strong>{{ text.removeCategory }}</strong><small>{{ text.removeCategoryHint }}</small></span>
                </label>
                <label
                  v-for="category in categoryResults"
                  :key="category.id"
                  class="ym-assignment-option"
                  :class="{ 'is-selected': selectedCategoryId === category.id }"
                >
                  <input v-model="selectedCategoryId" type="radio" name="assignment-category" :value="category.id" />
                  <span><strong>{{ name(category) }}</strong><code dir="ltr">{{ category.slug }}</code></span>
                </label>
                <p v-if="categoryResults.length === 0" class="ym-assignment-empty">{{ text.noResults }}</p>
              </div>
              <p v-if="categoryFieldError" class="ym-assignment-error" role="alert">{{ categoryFieldError }}</p>
              <footer>
                <button
                  type="button"
                  class="is-primary"
                  :disabled="!categoryChanged || categoryMutationLoading || !canUpdateCategory"
                  @click="saveCategory"
                >
                  {{ categoryMutationLoading ? text.saving : text.saveCategory }}
                </button>
              </footer>
            </template>
          </section>

          <section v-if="currentTags !== null" class="ym-assignment-section">
            <header>
              <div><span>{{ text.tagsEyebrow }}</span><h3>{{ text.tagsTitle }}</h3><p>{{ canUpdateTags ? text.tagsCopy : text.readOnlyCopy }}</p></div>
              <button v-if="canUpdateTags && !tagsEditing" type="button" class="is-edit" @click="startTagsEditing">{{ text.edit }}</button>
              <b v-else-if="!canUpdateTags">{{ text.readOnly }}</b>
            </header>

            <div class="ym-assignment-selected">
              <div><span>{{ text.selectedTags }}</span><strong dir="ltr">{{ selectedTags.length }} / 50</strong></div>
              <div v-if="selectedTags.length" class="ym-assignment-chips">
                <template v-for="tag in selectedTags" :key="tag.id">
                  <button
                    v-if="canUpdateTags && tagsEditing"
                    type="button"
                    :class="{ 'is-disabled': !tag.is_active }"
                    :disabled="tagMutationLoading"
                    :aria-label="text.removeTag(name(tag))"
                    @click="removeTag(tag.id)"
                  >
                    {{ name(tag) }}
                    <small v-if="!tag.is_active">{{ text.disabled }}</small>
                    <span aria-hidden="true">×</span>
                  </button>
                  <span
                    v-else
                    class="ym-assignment-chip-readonly"
                    :class="{ 'is-disabled': !tag.is_active }"
                  >
                    {{ name(tag) }}
                    <small v-if="!tag.is_active">{{ text.disabled }}</small>
                  </span>
                </template>
              </div>
              <p v-else>{{ text.noSelectedTags }}</p>
              <small v-if="selectedTags.some(tag => !tag.is_active)">{{ text.disabledTagsHint }}</small>
            </div>

            <template v-if="canUpdateTags && tagsEditing">
              <label class="ym-assignment-search">
                <span>{{ text.searchTags }}</span>
                <input v-model.trim="tagQuery" type="search" maxlength="80" autocomplete="off" />
              </label>
              <p v-if="tagQuery.length === 1" class="ym-assignment-hint">{{ text.twoChars }}</p>
              <div v-if="tagCatalogLoading" class="ym-assignment-state" role="status">{{ text.searching }}</div>
              <p v-else-if="tagCatalogError" class="ym-assignment-error" role="alert">
                {{ tagCatalogError }}
                <button type="button" @click="searchTags">{{ text.retry }}</button>
              </p>
              <div v-else class="ym-assignment-tag-results">
                <article v-for="tag in availableTagResults" :key="tag.id">
                  <span><strong>{{ name(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code></span>
                  <button type="button" :disabled="selectedTags.length >= 50" @click="addTag(tag)">{{ text.add }}</button>
                </article>
                <p v-if="availableTagResults.length === 0" class="ym-assignment-empty">{{ text.noResults }}</p>
              </div>
              <p v-if="tagFieldError" class="ym-assignment-error" role="alert">{{ tagFieldError }}</p>
              <footer>
                <button
                  type="button"
                  class="is-primary"
                  :disabled="!tagsChanged || tagMutationLoading || selectedTags.length > 50 || !canUpdateTags"
                  @click="saveTags"
                >
                  {{ tagMutationLoading ? text.saving : text.saveTags }}
                </button>
              </footer>
            </template>
          </section>
        </main>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'

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
}>()
const emit = defineEmits<{
  close: []
  changed: [payload: { work_id: number; section: 'category' | 'tags'; changed: boolean }]
  authorizationError: []
}>()
const { apiFetch } = useApiClient()
const dashboardTheme = useState<'dark' | 'light'>(
  'ym-dashboard-theme',
  () => 'dark'
)
const drawerRef = ref<HTMLElement | null>(null)
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
    eyebrow: 'إسناد فردي آمن', title: 'إدارة التصنيف والوسوم', close: 'إغلاق واجهة الإسناد',
    categoryEyebrow: 'تصنيف العمل', categoryTitle: 'التصنيف الحالي', categoryCopy: 'اختر تصنيفًا فعالًا أو أزل التصنيف.', tagsEyebrow: 'وسوم العمل', tagsTitle: 'مجموعة الوسوم', tagsCopy: 'الحفظ يستبدل مجموعة الوسوم كاملة بالحالة المحددة.', readOnlyCopy: 'هذا القسم متاح للقراءة فقط.', edit: 'تعديل', readOnly: 'قراءة فقط',
    currentCategory: 'الحالة الحالية', active: 'فعال', disabled: 'معطل', disabledCategoryHint: 'هذا تصنيف قديم معطل؛ يمكن استبداله أو إزالته ولا يظهر كهدف جديد.', legacy: 'قيمة قديمة غير مربوطة', legacyHint: 'اختر تصنيفًا فعالًا أو أزل هذه القيمة القديمة.', uncategorized: 'غير مصنف',
    searchCategory: 'البحث في التصنيفات الفعالة', searchTags: 'البحث في الوسوم الفعالة', twoChars: 'اكتب حرفين على الأقل للبحث.', searching: 'جارٍ البحث…', retry: 'إعادة المحاولة', noResults: 'لا توجد نتائج متاحة.', removeCategory: 'إزالة التصنيف / غير مصنف', removeCategoryHint: 'يحفظ category_id بقيمة null.', saveCategory: 'حفظ التصنيف', saving: 'جارٍ الحفظ…',
    selectedTags: 'الوسوم المحددة', noSelectedTags: 'لا توجد وسوم محددة؛ الحفظ سيمسح الإسنادات.', disabledTagsHint: 'الوسوم المعطلة مرتبطة مسبقًا: يمكن إبقاؤها أو إزالتها، ولا يمكن إضافتها من البحث.', removeTag: (name: string) => `إزالة الوسم ${name}`, add: 'إضافة', saveTags: 'حفظ مجموعة الوسوم',
    generic: 'تعذر إكمال الطلب. حاول مرة أخرى.'
  },
  en: {
    eyebrow: 'Safe individual assignment', title: 'Manage category and tags', close: 'Close assignment drawer',
    categoryEyebrow: 'Work category', categoryTitle: 'Current category', categoryCopy: 'Choose an active category or remove the category.', tagsEyebrow: 'Work tags', tagsTitle: 'Tag set', tagsCopy: 'Saving replaces the complete tag set with the selected state.', readOnlyCopy: 'This section is read-only.', edit: 'Edit', readOnly: 'Read only',
    currentCategory: 'Current state', active: 'Active', disabled: 'Disabled', disabledCategoryHint: 'This is a previously linked disabled category; replace or remove it. It is not offered as a new target.', legacy: 'Unmapped legacy value', legacyHint: 'Choose an active category or remove this legacy value.', uncategorized: 'Uncategorized',
    searchCategory: 'Search active categories', searchTags: 'Search active tags', twoChars: 'Enter at least two characters to search.', searching: 'Searching…', retry: 'Retry', noResults: 'No available results.', removeCategory: 'Remove category / Uncategorized', removeCategoryHint: 'Saves category_id as null.', saveCategory: 'Save category', saving: 'Saving…',
    selectedTags: 'Selected tags', noSelectedTags: 'No tags selected; saving clears all assignments.', disabledTagsHint: 'Disabled tags were previously linked: keep or remove them, but they cannot be added from search.', removeTag: (name: string) => `Remove tag ${name}`, add: 'Add', saveTags: 'Save tag set',
    generic: 'Could not complete the request. Try again.'
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

watch(() => props.open, async (open) => {
  drawerRevision++
  if (!open) {
    invalidateRequests()
    return
  }
  resetSearchState()
  categoryEditing.value = false
  tagsEditing.value = false
  resetFromWork(true)
  await nextTick()
  drawerRef.value?.focus()
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
  await nextTick()
  drawerRef.value?.focus()
})
watch(categoryQuery, query => { if (!resettingSearch) scheduleSearch('category', query) }, { flush: 'sync' })
watch(tagQuery, query => { if (!resettingSearch) scheduleSearch('tag', query) }, { flush: 'sync' })
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
    liveMessage.value = response.message || ''
    emit('changed', { work_id: props.work.id, section: 'category', changed: response.data.changed })
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
    liveMessage.value = response.message || ''
    emit('changed', { work_id: props.work.id, section: 'tags', changed: response.data.changed })
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
function requestClose() {
  if (mutationBusy.value) return
  drawerRevision++
  invalidateRequests()
  emit('close')
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
.ym-assignment-overlay{position:fixed;inset:0;z-index:12000;display:flex;background:rgba(2,6,23,.68)}
.ym-assignment-overlay.is-rtl{justify-content:flex-start}
.ym-assignment-overlay.is-ltr{justify-content:flex-end}
.ym-assignment-overlay.is-dark{
  --ym-text:#f0f6ff;
  --ym-muted:rgba(226,232,240,.92);
  --ym-control-bg:rgba(15,23,42,.92);
  --ym-control-border:rgba(148,163,184,.3);
  --ym-card-bg:linear-gradient(145deg,rgba(15,23,42,.98),rgba(30,41,59,.96));
  --ym-card-border:rgba(148,163,184,.28);
  --ym-soft-border:rgba(148,163,184,.18);
  --ym-dropdown-bg:#0f172a;
  color-scheme:dark;
}
.ym-assignment-overlay.is-light{
  --ym-text:#171126;
  --ym-muted:rgba(45,36,64,.9);
  --ym-control-bg:rgba(250,247,255,.98);
  --ym-control-border:rgba(109,40,217,.36);
  --ym-card-bg:linear-gradient(145deg,#fff 0%,#f2eaff 100%);
  --ym-card-border:rgba(109,40,217,.34);
  --ym-soft-border:rgba(91,33,182,.24);
  --ym-dropdown-bg:#fff;
  color-scheme:light;
}
.ym-assignment-drawer{isolation:isolate;width:min(100%,720px);height:100dvh;overflow:auto;outline:none;border-inline-start:1px solid var(--ym-card-border);background:var(--ym-dropdown-bg,#0f172a);color:var(--ym-text)}
.ym-assignment-overlay.is-rtl .ym-assignment-drawer{box-shadow:24px 0 64px rgba(2,6,23,.38)}
.ym-assignment-overlay.is-ltr .ym-assignment-drawer{box-shadow:-24px 0 64px rgba(2,6,23,.38)}
.ym-assignment-head{position:sticky;top:0;z-index:3;display:flex;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--ym-soft-border);background:var(--ym-dropdown-bg,#0f172a);padding:1.15rem 1.25rem}.ym-assignment-head>div{display:grid;gap:.2rem}.ym-assignment-head span{color:#8b5cf6;font-size:11px;font-weight:950}.ym-assignment-head h2{font-size:1.45rem;margin:0}.ym-assignment-head strong{font-size:13px}.ym-assignment-head code{color:var(--ym-muted);font-size:10px;overflow-wrap:anywhere}.ym-assignment-head>button{width:42px;height:42px;border:1px solid var(--ym-control-border);border-radius:13px;background:var(--ym-control-bg);color:var(--ym-text);font-size:1.4rem}
.ym-assignment-live{min-height:2rem;padding:0 1.25rem}.ym-assignment-live p{border-radius:12px;font-size:12px;font-weight:900;padding:.65rem}.is-success{background:rgba(16,185,129,.1);color:#10b981}.is-error,.ym-assignment-error{color:#fb7185}
main{display:grid;gap:1rem;padding:0 1.25rem 1.25rem}.ym-assignment-section{border:1px solid var(--ym-soft-border);border-radius:22px;background:var(--ym-card-bg);padding:1rem}.ym-assignment-section>header{display:flex;justify-content:space-between;gap:1rem}.ym-assignment-section>header span{color:#8b5cf6;font-size:10px;font-weight:950}.ym-assignment-section h3{font-size:1.1rem;margin:.2rem 0}.ym-assignment-section header p{color:var(--ym-muted);font-size:11px;margin:0}.ym-assignment-section>header>b{align-self:flex-start;border-radius:999px;background:var(--ym-control-bg);color:var(--ym-muted);font-size:10px;padding:.35rem .55rem}.ym-assignment-section>header>.is-edit{align-self:flex-start;border:1px solid rgba(139,92,246,.35);border-radius:11px;background:rgba(139,92,246,.1);color:#a78bfa;font-size:11px;font-weight:900;padding:.5rem .7rem}
.ym-assignment-current,.ym-assignment-selected{display:grid;gap:.35rem;border:1px solid var(--ym-soft-border);border-radius:16px;background:var(--ym-control-bg);margin-top:1rem;padding:.8rem}.ym-assignment-current>span,.ym-assignment-selected>div:first-child>span{color:var(--ym-muted);font-size:10px;font-weight:900}.ym-assignment-current code{color:#8b5cf6}.ym-assignment-current small{width:max-content;border-radius:999px;padding:.25rem .45rem}.is-active{color:#10b981}.is-disabled{color:#f59e0b}.is-warning,.is-legacy{color:#f59e0b}.is-muted{color:var(--ym-muted)}.ym-assignment-current p,.ym-assignment-selected p,.ym-assignment-selected>small{color:var(--ym-muted);font-size:11px;line-height:1.6;margin:0}
.ym-assignment-search{display:grid;gap:.35rem;margin-top:1rem}.ym-assignment-search span{color:var(--ym-muted);font-size:11px;font-weight:900}.ym-assignment-search input{min-height:44px;border:1px solid var(--ym-control-border);border-radius:13px;outline:none;background:var(--ym-control-bg);color:var(--ym-text);padding:.65rem}.ym-assignment-search input:focus,button:focus-visible{box-shadow:0 0 0 3px rgba(139,92,246,.2)}.ym-assignment-hint{color:var(--ym-muted);font-size:10px}.ym-assignment-state,.ym-assignment-empty{text-align:center;color:var(--ym-muted);padding:1rem}.ym-assignment-error{font-size:11px;font-weight:850}.ym-assignment-error button{border:0;background:transparent;color:#8b5cf6;font-weight:900}
.ym-assignment-options,.ym-assignment-tag-results{display:grid;gap:.5rem;max-height:290px;overflow:auto;margin-top:.7rem}.ym-assignment-option{display:flex;gap:.6rem;border:1px solid var(--ym-soft-border);border-radius:14px;padding:.7rem;cursor:pointer}.ym-assignment-option.is-selected{border-color:#8b5cf6;background:rgba(139,92,246,.08)}.ym-assignment-option>span{display:grid;gap:.15rem}.ym-assignment-option code{color:#8b5cf6}.ym-assignment-option small{color:var(--ym-muted)}
.ym-assignment-selected>div:first-child{display:flex;justify-content:space-between}.ym-assignment-chips{display:flex;flex-wrap:wrap;gap:.45rem}.ym-assignment-chips button,.ym-assignment-chip-readonly{display:inline-flex;align-items:center;gap:.35rem;border:1px solid rgba(139,92,246,.3);border-radius:999px;background:rgba(139,92,246,.1);color:var(--ym-text);padding:.42rem .65rem}.ym-assignment-chips button.is-disabled,.ym-assignment-chip-readonly.is-disabled{border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.1)}.ym-assignment-chips small{color:#f59e0b}
.ym-assignment-tag-results article{display:flex;align-items:center;justify-content:space-between;gap:.7rem;border:1px solid var(--ym-soft-border);border-radius:14px;padding:.7rem}.ym-assignment-tag-results article>span{display:grid;gap:.15rem}.ym-assignment-tag-results code{color:#8b5cf6}.ym-assignment-tag-results button{border:1px solid var(--ym-control-border);border-radius:11px;background:var(--ym-control-bg);color:var(--ym-text);padding:.45rem .65rem}
.ym-assignment-section>footer{display:flex;justify-content:flex-end;border-top:1px solid var(--ym-soft-border);margin-top:1rem;padding-top:1rem}.ym-assignment-section>footer button{min-height:42px;border-radius:12px;padding:.6rem .85rem;font-weight:900}.is-primary{border:1px solid #7c3aed;background:#7c3aed;color:#fff}button:disabled{cursor:not-allowed;opacity:.5}
@media(max-width:640px){.ym-assignment-section>header{display:grid}.ym-assignment-head,main{padding-inline:1rem}}
</style>
