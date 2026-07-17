<template>
  <Teleport to="body">
    <div
      v-if="open && works.length"
      class="ym-bulk-overlay"
      :class="[dashboardTheme === 'light' ? 'is-light' : 'is-dark', locale === 'ar' ? 'is-rtl' : 'is-ltr']"
      role="presentation"
      @click.self="requestClose"
    >
      <section
        ref="drawerRef"
        class="ym-bulk-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ym-bulk-assignment-title"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
        tabindex="-1"
        @keydown.esc="requestClose"
      >
        <header class="ym-bulk-head">
          <div>
            <span>{{ text.eyebrow }}</span>
            <h2 id="ym-bulk-assignment-title">{{ text.title }}</h2>
            <p>{{ text.count(works.length) }} · {{ text.limit }}</p>
          </div>
          <button type="button" :aria-label="text.close" :disabled="mutationBusy" @click="requestClose">×</button>
        </header>

        <div class="ym-bulk-live" aria-live="polite">
          <p v-if="liveMessage" class="is-success">{{ liveMessage }}</p>
          <p v-if="globalError" class="is-error">{{ globalError }}</p>
        </div>

        <section class="ym-bulk-works">
          <header>
            <strong>{{ text.selectedWorks }}</strong>
            <button v-if="works.length > 10" type="button" @click="showAllWorks = !showAllWorks">
              {{ showAllWorks ? text.showLess : text.showAll }}
            </button>
          </header>
          <ul :class="{ 'is-expanded': showAllWorks }">
            <li v-for="work in visibleWorks" :key="work.id">
              <strong>{{ work.title }}</strong>
              <code dir="ltr">#{{ work.id }} · {{ work.slug }}</code>
            </li>
          </ul>
          <p v-if="!showAllWorks && works.length > 10">{{ text.moreWorks(works.length - 10) }}</p>
        </section>

        <nav class="ym-bulk-tabs" :aria-label="text.sections">
          <button
            v-if="canAssignCategory"
            type="button"
            :class="{ 'is-active': activeTab === 'category' }"
            @click="activateTab('category')"
          >
            {{ text.categoryTab }}
          </button>
          <button
            v-if="canAssignTags"
            type="button"
            :class="{ 'is-active': activeTab === 'tags' }"
            @click="activateTab('tags')"
          >
            {{ text.tagsTab }}
          </button>
        </nav>

        <main>
          <section v-if="activeTab === 'category' && canAssignCategory" class="ym-bulk-section">
            <header>
              <h3>{{ text.categoryTitle }}</h3>
              <p>{{ text.categoryCopy }}</p>
            </header>

            <div class="ym-bulk-distribution">
              <h4>{{ text.currentDistribution }}</h4>
              <article v-for="item in categoryDistribution" :key="item.key">
                <span>
                  <strong>{{ item.label }}</strong>
                  <code v-if="item.slug" dir="ltr">{{ item.slug }}</code>
                  <small v-if="item.state">{{ item.state }}</small>
                </span>
                <b>{{ item.count }}</b>
              </article>
            </div>

            <label class="ym-bulk-search">
              <span>{{ text.searchCategories }}</span>
              <input v-model.trim="categoryQuery" type="search" maxlength="80" autocomplete="off" />
            </label>
            <p v-if="categoryQuery.length === 1" class="ym-bulk-hint">{{ text.twoChars }}</p>
            <div v-if="categoryCatalogLoading" class="ym-bulk-state" role="status">{{ text.searching }}</div>
            <p v-else-if="categoryCatalogError" class="ym-bulk-error" role="alert">
              {{ categoryCatalogError }}
              <button type="button" @click="searchCategories">{{ text.retry }}</button>
            </p>
            <div v-else class="ym-bulk-options">
              <label :class="{ 'is-selected': categoryTarget === 'remove' }">
                <input
                  type="radio"
                  name="bulk-category"
                  value="remove"
                  :checked="categoryTarget === 'remove'"
                  @change="selectCategoryTarget('remove')"
                />
                <span><strong>{{ text.removeCategory }}</strong><small>{{ text.removeCategoryHint }}</small></span>
              </label>
              <label
                v-for="category in categoryResults"
                :key="category.id"
                :class="{ 'is-selected': categoryTarget === category.id }"
              >
                <input
                  type="radio"
                  name="bulk-category"
                  :value="category.id"
                  :checked="categoryTarget === category.id"
                  @change="selectCategoryTarget(category.id)"
                />
                <span><strong>{{ name(category) }}</strong><code dir="ltr">{{ category.slug }}</code></span>
              </label>
              <p v-if="!categoryResults.length">{{ text.noResults }}</p>
            </div>
            <p v-if="categoryFieldError" class="ym-bulk-error" role="alert">{{ categoryFieldError }}</p>

            <section v-if="categoryConfirming" class="ym-bulk-confirm">
              <h4>{{ text.confirmCategory }}</h4>
              <dl>
                <div><dt>{{ text.works }}</dt><dd>{{ works.length }}</dd></div>
                <div><dt>{{ text.target }}</dt><dd>{{ categoryTargetLabel }}</dd></div>
                <div><dt>{{ text.expectedChanged }}</dt><dd>{{ categoryExpected.changed }}</dd></div>
                <div><dt>{{ text.expectedUnchanged }}</dt><dd>{{ categoryExpected.unchanged }}</dd></div>
              </dl>
              <p class="is-warning">{{ text.categoryReplacementWarning }}</p>
              <div class="ym-bulk-confirm__actions">
                <button type="button" class="is-secondary" :disabled="categoryMutationLoading" @click="categoryConfirming = false">{{ text.back }}</button>
                <button type="button" class="is-primary" :disabled="categoryMutationLoading" @click="submitCategory">
                  {{ categoryMutationLoading ? text.saving : text.confirm }}
                </button>
              </div>
            </section>
            <footer v-else>
              <button
                type="button"
                class="is-primary"
                :disabled="categoryTarget === null || categoryExpected.changed === 0 || categoryMutationLoading || !canAssignCategory"
                @click="categoryConfirming = true"
              >
                {{ text.review }}
              </button>
            </footer>

            <ResultSummary v-if="categoryResult" :result="categoryResult" :locale="locale" section="category" />
          </section>

          <section v-if="activeTab === 'tags' && canAssignTags" class="ym-bulk-section">
            <header>
              <h3>{{ text.tagsTitle }}</h3>
              <p>{{ text.tagsCopy }}</p>
            </header>

            <div class="ym-bulk-mixed">
              <h4>{{ text.commonTags }}</h4>
              <p v-if="!commonTags.length">{{ text.none }}</p>
              <div v-else class="ym-bulk-chips">
                <span
                  v-for="tag in commonTags"
                  :key="tag.entity.id"
                  class="ym-bulk-chip-readonly"
                  :class="{ 'is-disabled': !tag.entity.is_active }"
                >
                  {{ name(tag.entity) }}
                  <small v-if="!tag.entity.is_active">{{ text.disabled }}</small>
                </span>
              </div>
              <h4>{{ text.mixedTags }}</h4>
              <p v-if="!mixedTags.length">{{ text.none }}</p>
              <article v-for="tag in mixedTags" :key="tag.entity.id">
                <span>
                  <strong>{{ name(tag.entity) }}</strong>
                  <small>{{ text.presentIn(tag.count, works.length) }} · {{ tag.entity.is_active ? text.active : text.disabled }}</small>
                  <em v-if="!tag.entity.is_active">{{ text.mixedDisabledWarning }}</em>
                </span>
                <button
                  v-if="tag.entity.is_active && !selectedTagIds.has(tag.entity.id)"
                  type="button"
                  :disabled="selectedTags.length >= 50 || tagMutationLoading"
                  @click="addTag(tag.entity)"
                >
                  {{ text.addToAll }}
                </button>
              </article>
            </div>

            <div class="ym-bulk-selected">
              <header><strong>{{ text.unifiedSet }}</strong><b dir="ltr">{{ selectedTags.length }} / 50</b></header>
              <div v-if="selectedTags.length" class="ym-bulk-chips">
                <button
                  v-for="tag in selectedTags"
                  :key="tag.id"
                  type="button"
                  :class="{ 'is-disabled': !tag.is_active }"
                  :aria-label="text.removeTag(name(tag))"
                  :disabled="tagMutationLoading"
                  @click="removeTag(tag.id)"
                >
                  {{ name(tag) }}
                  <small v-if="!tag.is_active">{{ text.disabledCommon }}</small>
                  <span aria-hidden="true">×</span>
                </button>
              </div>
              <p v-else>{{ text.emptyUnifiedSet }}</p>
            </div>

            <label class="ym-bulk-search">
              <span>{{ text.searchTags }}</span>
              <input v-model.trim="tagQuery" type="search" maxlength="80" autocomplete="off" />
            </label>
            <p v-if="tagQuery.length === 1" class="ym-bulk-hint">{{ text.twoChars }}</p>
            <div v-if="tagCatalogLoading" class="ym-bulk-state" role="status">{{ text.searching }}</div>
            <p v-else-if="tagCatalogError" class="ym-bulk-error" role="alert">
              {{ tagCatalogError }}
              <button type="button" @click="searchTags">{{ text.retry }}</button>
            </p>
            <div v-else class="ym-bulk-tag-results">
              <article v-for="tag in availableTagResults" :key="tag.id">
                <span><strong>{{ name(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code></span>
                <button type="button" :disabled="selectedTags.length >= 50" @click="addTag(tag)">{{ text.add }}</button>
              </article>
              <p v-if="!availableTagResults.length">{{ text.noResults }}</p>
            </div>
            <p v-if="tagFieldError" class="ym-bulk-error" role="alert">{{ tagFieldError }}</p>

            <section v-if="tagsConfirming" class="ym-bulk-confirm">
              <h4>{{ text.confirmTags }}</h4>
              <dl>
                <div><dt>{{ text.works }}</dt><dd>{{ works.length }}</dd></div>
                <div><dt>{{ text.finalTagsCount }}</dt><dd>{{ selectedTags.length }}</dd></div>
                <div><dt>{{ text.expectedChanged }}</dt><dd>{{ tagsExpected.changed }}</dd></div>
                <div><dt>{{ text.expectedUnchanged }}</dt><dd>{{ tagsExpected.unchanged }}</dd></div>
                <div><dt>{{ text.mixedRemoved }}</dt><dd>{{ mixedTagsRemoved }}</dd></div>
              </dl>
              <p>{{ selectedTags.length ? selectedTags.map(name).join(locale === 'ar' ? '، ' : ', ') : text.noFinalTags }}</p>
              <p class="is-warning">{{ text.tagsReplacementWarning }}</p>
              <label class="ym-bulk-ack">
                <input v-model="tagsAcknowledged" type="checkbox" />
                <span>{{ text.acknowledge }}</span>
              </label>
              <div class="ym-bulk-confirm__actions">
                <button type="button" class="is-secondary" :disabled="tagMutationLoading" @click="cancelTagsConfirm">{{ text.back }}</button>
                <button type="button" class="is-primary" :disabled="!tagsAcknowledged || tagMutationLoading" @click="submitTags">
                  {{ tagMutationLoading ? text.saving : text.confirm }}
                </button>
              </div>
            </section>
            <footer v-else>
              <button
                type="button"
                class="is-primary"
                :disabled="tagsExpected.changed === 0 || selectedTags.length > 50 || tagMutationLoading || !canAssignTags"
                @click="tagsConfirming = true"
              >
                {{ text.review }}
              </button>
            </footer>

            <ResultSummary v-if="tagsResult" :result="tagsResult" :locale="locale" section="tags" />
          </section>
        </main>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, nextTick, onUnmounted, ref, watch } from 'vue'
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
interface BulkWork {
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
interface Summary { requested: number; changed: number; unchanged: number }
interface CategoryItem { work_id: number; previous_category_id: number | null; category_id: number | null; changed: boolean }
interface TagItem { work_id: number; previous_tag_ids: number[]; tag_ids: number[]; added_tag_ids: number[]; removed_tag_ids: number[]; changed: boolean }
interface BulkChangedPayload {
  section: 'category' | 'tags'
  changed: boolean
  summary: Summary
  items: CategoryItem[] | TagItem[]
  category?: SafeEntity | null
  tags?: SafeEntity[]
}
interface CatalogResponse { success: boolean; data: { items: SafeEntity[] } | null; message?: string }
interface CategoryResponse {
  success: boolean
  data: { items: CategoryItem[]; category: SafeEntity | null; summary: Summary; changed: boolean } | null
  message?: string
}
interface TagsResponse {
  success: boolean
  data: { items: TagItem[]; tags: SafeEntity[]; summary: Summary; changed: boolean } | null
  message?: string
}
interface DisplayResult extends Summary { message: string }

const props = defineProps<{
  open: boolean
  works: BulkWork[]
  locale: 'ar' | 'en'
  canAssignCategory: boolean
  canAssignTags: boolean
  permissionRevision: string
}>()
const emit = defineEmits<{
  close: []
  changed: [payload: BulkChangedPayload]
  authorizationError: []
}>()
const { apiFetch } = useApiClient()
const dashboardTheme = useState<'dark' | 'light'>('ym-dashboard-theme', () => 'dark')
const drawerRef = ref<HTMLElement | null>(null)
const activeTab = ref<'category' | 'tags'>('category')
const showAllWorks = ref(false)
const categoryQuery = ref('')
const tagQuery = ref('')
const categoryResults = ref<SafeEntity[]>([])
const tagResults = ref<SafeEntity[]>([])
const categoryTarget = ref<number | 'remove' | null>(null)
const selectedTags = ref<SafeEntity[]>([])
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
const liveMessageSection = ref<'category' | 'tags' | null>(null)
const categoryConfirming = ref(false)
const tagsConfirming = ref(false)
const tagsAcknowledged = ref(false)
const categoryResult = ref<DisplayResult | null>(null)
const tagsResult = ref<DisplayResult | null>(null)
let drawerRevision = 0
let categorySearchRevision = 0
let tagSearchRevision = 0
let categoryMutationRevision = 0
let tagMutationRevision = 0
let categoryTimer: ReturnType<typeof setTimeout> | null = null
let tagTimer: ReturnType<typeof setTimeout> | null = null
let resetting = false

const copies = {
  ar: {
    eyebrow: 'إسناد آمن حتى 100 عمل', title: 'إدارة جماعية للتصنيف والوسوم', close: 'إغلاق واجهة الإدارة الجماعية',
    count: (n: number) => `${n} عمل محدد`, limit: 'الحد الأقصى 100', selectedWorks: 'الأعمال المحددة', showAll: 'عرض القائمة الكاملة', showLess: 'عرض المختصر', moreWorks: (n: number) => `+${n} أعمال أخرى`,
    sections: 'أقسام الإسناد الجماعي', categoryTab: 'التصنيف', tagsTab: 'الوسوم',
    categoryTitle: 'استبدال تصنيف الأعمال', categoryCopy: 'اختر هدفًا صريحًا؛ سيطبق التصنيف نفسه على كل الأعمال المحددة.', currentDistribution: 'توزيع الحالة الحالية',
    searchCategories: 'البحث في التصنيفات الفعالة', twoChars: 'اكتب حرفين على الأقل للبحث.', searching: 'جارٍ البحث…', retry: 'إعادة المحاولة', noResults: 'لا توجد نتائج متاحة.',
    removeCategory: 'إزالة التصنيف / غير مصنف', removeCategoryHint: 'تطبيق حالة غير مصنف على كل الأعمال.', uncategorized: 'غير مصنف', legacy: 'Legacy غير مربوط', unavailable: 'غير متاح حسب الصلاحية', active: 'فعال', disabled: 'معطل',
    confirmCategory: 'تأكيد استبدال التصنيف', works: 'الأعمال', target: 'الهدف', expectedChanged: 'المتوقع تغيّره', expectedUnchanged: 'المتوقع بقاؤه', categoryReplacementWarning: 'سيُستبدل التصنيف الحالي لكل الأعمال المحددة بالهدف أعلاه.',
    review: 'مراجعة وتأكيد', back: 'العودة للتحرير', confirm: 'تأكيد التنفيذ', saving: 'جارٍ التنفيذ…',
    tagsTitle: 'توحيد مجموعة الوسوم', tagsCopy: 'هذه عملية Replacement كاملة تضع المجموعة نفسها على جميع الأعمال المحددة.', commonTags: 'وسوم مشتركة بين جميع الأعمال', mixedTags: 'وسوم مختلطة', none: 'لا يوجد.',
    presentIn: (n: number, total: number) => `موجود في ${n} من ${total}`, mixedDisabledWarning: 'هذا الوسم المعطل موجود في بعض الأعمال فقط وسيُزال منها عند حفظ المجموعة الموحدة.', addToAll: 'إضافته إلى جميع الأعمال',
    unifiedSet: 'المجموعة الموحدة النهائية', disabledCommon: 'معطل مشترك', emptyUnifiedSet: 'المجموعة فارغة؛ الحفظ سيزيل جميع الوسوم.', searchTags: 'البحث في الوسوم الفعالة', add: 'إضافة', removeTag: (value: string) => `إزالة الوسم ${value}`,
    confirmTags: 'تأكيد استبدال مجموعة الوسوم', finalTagsCount: 'العدد النهائي للوسوم', mixedRemoved: 'وسوم مختلطة ستُزال من بعض الأعمال', noFinalTags: 'لا توجد وسوم في المجموعة النهائية.',
    tagsReplacementWarning: 'Replacement تستبدل مجموعة الوسوم كاملة وتطبق المجموعة النهائية نفسها على جميع الأعمال المحددة.', acknowledge: 'أفهم أن هذه العملية ستستبدل مجموعة الوسوم كاملة لجميع الأعمال المحددة.',
    generic: 'تعذر إكمال الطلب. حاول مرة أخرى.'
  },
  en: {
    eyebrow: 'Safe assignment for up to 100 works', title: 'Bulk category and tag management', close: 'Close bulk management',
    count: (n: number) => `${n} works selected`, limit: 'Maximum 100', selectedWorks: 'Selected works', showAll: 'Show full list', showLess: 'Show less', moreWorks: (n: number) => `+${n} more works`,
    sections: 'Bulk assignment sections', categoryTab: 'Category', tagsTab: 'Tags',
    categoryTitle: 'Replace work categories', categoryCopy: 'Choose an explicit target; the same category is applied to every selected work.', currentDistribution: 'Current-state distribution',
    searchCategories: 'Search active categories', twoChars: 'Enter at least two characters to search.', searching: 'Searching…', retry: 'Retry', noResults: 'No available results.',
    removeCategory: 'Remove category / Uncategorized', removeCategoryHint: 'Make every work uncategorized.', uncategorized: 'Uncategorized', legacy: 'Unmapped legacy', unavailable: 'Unavailable for this permission scope', active: 'Active', disabled: 'Disabled',
    confirmCategory: 'Confirm category replacement', works: 'Works', target: 'Target', expectedChanged: 'Expected to change', expectedUnchanged: 'Expected unchanged', categoryReplacementWarning: 'The current category on every selected work will be replaced with the target above.',
    review: 'Review and confirm', back: 'Back to editor', confirm: 'Confirm operation', saving: 'Applying…',
    tagsTitle: 'Unify tag set', tagsCopy: 'This is a full replacement that applies the same set to every selected work.', commonTags: 'Tags common to every work', mixedTags: 'Mixed tags', none: 'None.',
    presentIn: (n: number, total: number) => `Present in ${n} of ${total}`, mixedDisabledWarning: 'This disabled tag exists on only some works and will be removed from them when the unified set is saved.', addToAll: 'Add to every work',
    unifiedSet: 'Final unified set', disabledCommon: 'Common disabled tag', emptyUnifiedSet: 'The set is empty; saving removes all tags.', searchTags: 'Search active tags', add: 'Add', removeTag: (value: string) => `Remove tag ${value}`,
    confirmTags: 'Confirm tag-set replacement', finalTagsCount: 'Final tag count', mixedRemoved: 'Mixed tags removed from some works', noFinalTags: 'No tags in the final set.',
    tagsReplacementWarning: 'Replacement overwrites the complete tag set and applies the same final set to every selected work.', acknowledge: 'I understand that this operation replaces the complete tag set for every selected work.',
    generic: 'Could not complete the request. Try again.'
  }
}
const text = computed(() => copies[props.locale])
const mutationBusy = computed(() => categoryMutationLoading.value || tagMutationLoading.value)
const visibleWorks = computed(() => props.works.slice(0, showAllWorks.value ? props.works.length : 10))
const workIds = computed(() => [...new Set(props.works.map(work => work.id))].sort((a, b) => a - b))
const worksIdentity = computed(() => workIds.value.join(','))
const selectedTagIds = computed(() => new Set(selectedTags.value.map(tag => tag.id)))

const tagAnalysis = computed(() => {
  const records = new Map<number, { entity: SafeEntity; count: number }>()
  for (const work of props.works) {
    for (const tag of work.taxonomy.tags ?? []) {
      const current = records.get(tag.id)
      records.set(tag.id, { entity: tag, count: (current?.count ?? 0) + 1 })
    }
  }
  return [...records.values()].sort((a, b) => a.entity.sort_order - b.entity.sort_order || a.entity.id - b.entity.id)
})
const commonTags = computed(() => tagAnalysis.value.filter(tag => tag.count === props.works.length))
const mixedTags = computed(() => tagAnalysis.value.filter(tag => tag.count < props.works.length))
const availableTagResults = computed(() => tagResults.value.filter(tag => tag.is_active && !selectedTagIds.value.has(tag.id)))
const mixedTagsRemoved = computed(() => mixedTags.value.filter(tag => !selectedTagIds.value.has(tag.entity.id)).length)

const categoryDistribution = computed(() => {
  const records = new Map<string, { key: string; label: string; slug: string; state: string; count: number }>()
  for (const work of props.works) {
    const category = work.taxonomy.category
    const tracking = work.taxonomy.category_tracking
    let item: { key: string; label: string; slug: string; state: string }
    if (category) item = { key: `category-${category.id}`, label: name(category), slug: category.slug, state: category.is_active ? text.value.active : text.value.disabled }
    else if (tracking?.is_legacy_unmapped) item = { key: `legacy-${work.category_id}`, label: text.value.legacy, slug: `#${work.category_id}`, state: '' }
    else if (tracking?.is_uncategorized) item = { key: 'uncategorized', label: text.value.uncategorized, slug: '', state: '' }
    else item = { key: 'unavailable', label: text.value.unavailable, slug: '', state: '' }
    const current = records.get(item.key)
    records.set(item.key, { ...item, count: (current?.count ?? 0) + 1 })
  }
  return [...records.values()]
})
const targetCategoryId = computed<number | null | undefined>(() => categoryTarget.value === null ? undefined : categoryTarget.value === 'remove' ? null : categoryTarget.value)
const categoryExpected = computed(() => {
  if (targetCategoryId.value === undefined) return { changed: 0, unchanged: props.works.length }
  const changed = props.works.filter(work => work.category_id !== targetCategoryId.value).length
  return { changed, unchanged: props.works.length - changed }
})
const categoryTargetLabel = computed(() => {
  if (categoryTarget.value === 'remove') return text.value.uncategorized
  return name(categoryResults.value.find(item => item.id === categoryTarget.value) ?? props.works.flatMap(work => work.taxonomy.category ? [work.taxonomy.category] : []).find(item => item.id === categoryTarget.value) ?? { name_ar: '', name_en: '' } as SafeEntity)
})
const tagsExpected = computed(() => {
  const target = [...selectedTagIds.value].sort((a, b) => a - b)
  const changed = props.works.filter((work) => {
    const current = [...new Set((work.taxonomy.tags ?? []).map(tag => tag.id))].sort((a, b) => a - b)
    return current.length !== target.length || current.some((id, index) => id !== target[index])
  }).length
  return { changed, unchanged: props.works.length - changed }
})

const ResultSummary = defineComponent({
  props: {
    result: { type: Object as () => DisplayResult, required: true },
    locale: { type: String as () => 'ar' | 'en', required: true },
    section: { type: String as () => 'category' | 'tags', required: true }
  },
  setup(componentProps) {
    return () => h('section', { class: 'ym-bulk-result', role: 'status' }, [
      h('h4', componentProps.locale === 'ar'
        ? `نتيجة ${componentProps.section === 'category' ? 'التصنيف' : 'الوسوم'}`
        : `${componentProps.section === 'category' ? 'Category' : 'Tags'} result`),
      h('dl', [
        h('div', [h('dt', componentProps.locale === 'ar' ? 'المطلوب' : 'Requested'), h('dd', componentProps.result.requested)]),
        h('div', [h('dt', componentProps.locale === 'ar' ? 'المتغير' : 'Changed'), h('dd', componentProps.result.changed)]),
        h('div', [h('dt', componentProps.locale === 'ar' ? 'غير المتغير' : 'Unchanged'), h('dd', componentProps.result.unchanged)])
      ]),
      h('p', componentProps.result.message)
    ])
  }
})

watch(() => props.open, async (open) => {
  drawerRevision++
  invalidateRequests()
  if (!open) return
  resetDrawer()
  if (!props.canAssignCategory && !props.canAssignTags) return authorizeOut()
  activeTab.value = props.canAssignCategory ? 'category' : 'tags'
  initializeTags()
  if (props.canAssignCategory) void searchCategories()
  if (props.canAssignTags) void searchTags()
  await nextTick()
  drawerRef.value?.focus()
})
watch(worksIdentity, () => {
  if (!props.open) return
  initializeTags()
  tagsConfirming.value = false
  tagsAcknowledged.value = false
  tagsResult.value = null
  tagFieldError.value = null
  clearLiveMessage('tags')
})
watch(() => props.permissionRevision, async () => {
  drawerRevision++
  invalidateRequests()
  if (!props.open) return
  if (!props.canAssignCategory) {
    categoryResults.value = []
    categoryQuery.value = ''
    categoryTarget.value = null
    categoryConfirming.value = false
    if (activeTab.value === 'category') activeTab.value = 'tags'
  }
  if (!props.canAssignTags) {
    tagResults.value = []
    tagQuery.value = ''
    selectedTags.value = []
    tagsConfirming.value = false
    if (activeTab.value === 'tags') activeTab.value = 'category'
  }
  if (!props.canAssignCategory && !props.canAssignTags) return authorizeOut()
  if (props.canAssignCategory) void searchCategories()
  if (props.canAssignTags) {
    initializeTags()
    void searchTags()
  }
  await nextTick()
  drawerRef.value?.focus()
})
watch(categoryQuery, query => {
  if (resetting) return
  if (typeof categoryTarget.value === 'number') categoryTarget.value = null
  categoryConfirming.value = false
  categoryResult.value = null
  categoryFieldError.value = null
  clearLiveMessage('category')
  scheduleSearch('category', query)
})
watch(tagQuery, query => { if (!resetting) scheduleSearch('tag', query) })
onUnmounted(() => {
  drawerRevision++
  invalidateRequests()
})

function resetDrawer() {
  resetting = true
  showAllWorks.value = false
  categoryQuery.value = ''
  tagQuery.value = ''
  categoryResults.value = []
  tagResults.value = []
  categoryTarget.value = null
  categoryCatalogError.value = null
  tagCatalogError.value = null
  categoryFieldError.value = null
  tagFieldError.value = null
  globalError.value = null
  liveMessage.value = ''
  liveMessageSection.value = null
  categoryConfirming.value = false
  tagsConfirming.value = false
  tagsAcknowledged.value = false
  categoryResult.value = null
  tagsResult.value = null
  resetting = false
}
function initializeTags() {
  selectedTags.value = commonTags.value.map(item => item.entity)
}
function selectCategoryTarget(target: number | 'remove'): void {
  categoryTarget.value = target
  categoryResult.value = null
  categoryFieldError.value = null
  categoryConfirming.value = false
  clearLiveMessage('category')
}
function clearLiveMessage(section: 'category' | 'tags'): void {
  if (liveMessageSection.value !== section) return
  liveMessage.value = ''
  liveMessageSection.value = null
}
function activateTab(tab: 'category' | 'tags') {
  if (mutationBusy.value) return
  activeTab.value = tab
}
function scheduleSearch(kind: 'category' | 'tag', raw: string) {
  if (!props.open) return
  const oldTimer = kind === 'category' ? categoryTimer : tagTimer
  if (oldTimer) clearTimeout(oldTimer)
  const query = raw.trim()
  if (query.length === 1) {
    if (kind === 'category') {
      categorySearchRevision++
      categoryResults.value = []
      categoryCatalogLoading.value = false
    } else {
      tagSearchRevision++
      tagResults.value = []
      tagCatalogLoading.value = false
    }
    return
  }
  const timer = setTimeout(() => kind === 'category' ? void searchCategories() : void searchTags(), 300)
  if (kind === 'category') categoryTimer = timer
  else tagTimer = timer
}
function catalogQuery(raw: string) {
  const query: Record<string, string | number> = { state: 'active', sort: 'sort_order', direction: 'asc', page: 1, per_page: 50 }
  const value = raw.trim()
  if (value.length >= 2) query.q = value
  return query
}
async function searchCategories() {
  if (!props.open || !props.canAssignCategory || categoryQuery.value.trim().length === 1) return
  const currentDrawer = drawerRevision
  const currentSearch = ++categorySearchRevision
  categoryCatalogLoading.value = true
  categoryCatalogError.value = null
  try {
    const response = await apiFetch<CatalogResponse>('/admin/works/taxonomy/categories', { query: catalogQuery(categoryQuery.value) })
    if (!currentSearchIs('category', currentDrawer, currentSearch)) return
    if (!response.success || !response.data) throw new Error('invalid')
    categoryResults.value = response.data.items.filter(item => item.is_active)
  } catch (error: unknown) {
    if (!currentSearchIs('category', currentDrawer, currentSearch)) return
    if (isAuthorizationError(error)) return authorizeOut()
    categoryCatalogError.value = serverMessage(error) || text.value.generic
  } finally {
    if (currentSearchIs('category', currentDrawer, currentSearch)) categoryCatalogLoading.value = false
  }
}
async function searchTags() {
  if (!props.open || !props.canAssignTags || tagQuery.value.trim().length === 1) return
  const currentDrawer = drawerRevision
  const currentSearch = ++tagSearchRevision
  tagCatalogLoading.value = true
  tagCatalogError.value = null
  try {
    const response = await apiFetch<CatalogResponse>('/admin/works/taxonomy/tags', { query: catalogQuery(tagQuery.value) })
    if (!currentSearchIs('tag', currentDrawer, currentSearch)) return
    if (!response.success || !response.data) throw new Error('invalid')
    tagResults.value = response.data.items.filter(item => item.is_active)
  } catch (error: unknown) {
    if (!currentSearchIs('tag', currentDrawer, currentSearch)) return
    if (isAuthorizationError(error)) return authorizeOut()
    tagCatalogError.value = serverMessage(error) || text.value.generic
  } finally {
    if (currentSearchIs('tag', currentDrawer, currentSearch)) tagCatalogLoading.value = false
  }
}
function currentSearchIs(kind: 'category' | 'tag', drawer: number, search: number) {
  return props.open && drawer === drawerRevision && search === (kind === 'category' ? categorySearchRevision : tagSearchRevision)
}
async function submitCategory() {
  if (!props.open || !props.canAssignCategory || targetCategoryId.value === undefined || categoryExpected.value.changed === 0 || categoryMutationLoading.value) return
  const currentDrawer = drawerRevision
  const currentMutation = ++categoryMutationRevision
  categoryMutationLoading.value = true
  categoryFieldError.value = null
  globalError.value = null
  try {
    const response = await apiFetch<CategoryResponse>('/admin/works/taxonomy/assign/category', {
      method: 'PATCH',
      body: { work_ids: workIds.value, category_id: targetCategoryId.value }
    })
    if (!response.success || !response.data) throw new Error('invalid')
    if (!currentMutationIs('category', currentDrawer, currentMutation)) return
    categoryResult.value = { ...response.data.summary, message: response.message || '' }
    liveMessage.value = response.message || ''
    liveMessageSection.value = 'category'
    categoryConfirming.value = false
    emit('changed', { section: 'category', ...response.data })
  } catch (error: unknown) {
    if (!currentMutationIs('category', currentDrawer, currentMutation)) return
    if (isAuthorizationError(error)) return authorizeOut()
    if (errorStatus(error) === 422) categoryFieldError.value = fieldErrors(error, ['work_ids', 'work_ids.', 'category_id'])
    else globalError.value = serverMessage(error) || text.value.generic
  } finally {
    if (currentMutationIs('category', currentDrawer, currentMutation)) categoryMutationLoading.value = false
  }
}
async function submitTags() {
  if (!props.open || !props.canAssignTags || !tagsAcknowledged.value || tagsExpected.value.changed === 0 || tagMutationLoading.value || selectedTags.value.length > 50) return
  const currentDrawer = drawerRevision
  const currentMutation = ++tagMutationRevision
  tagMutationLoading.value = true
  tagFieldError.value = null
  globalError.value = null
  try {
    const response = await apiFetch<TagsResponse>('/admin/works/taxonomy/assign/tags', {
      method: 'PATCH',
      body: { work_ids: workIds.value, tag_ids: [...selectedTagIds.value].sort((a, b) => a - b) }
    })
    if (!response.success || !response.data) throw new Error('invalid')
    if (!currentMutationIs('tag', currentDrawer, currentMutation)) return
    selectedTags.value = [...response.data.tags]
    tagsResult.value = { ...response.data.summary, message: response.message || '' }
    liveMessage.value = response.message || ''
    liveMessageSection.value = 'tags'
    tagsConfirming.value = false
    tagsAcknowledged.value = false
    emit('changed', { section: 'tags', ...response.data })
  } catch (error: unknown) {
    if (!currentMutationIs('tag', currentDrawer, currentMutation)) return
    if (isAuthorizationError(error)) return authorizeOut()
    if (errorStatus(error) === 422) tagFieldError.value = fieldErrors(error, ['work_ids', 'work_ids.', 'tag_ids', 'tag_ids.'])
    else globalError.value = serverMessage(error) || text.value.generic
  } finally {
    if (currentMutationIs('tag', currentDrawer, currentMutation)) tagMutationLoading.value = false
  }
}
function currentMutationIs(kind: 'category' | 'tag', drawer: number, mutation: number) {
  return props.open && drawer === drawerRevision && mutation === (kind === 'category' ? categoryMutationRevision : tagMutationRevision)
}
function addTag(tag: SafeEntity) {
  if (!props.canAssignTags || !tag.is_active || selectedTags.value.length >= 50 || selectedTagIds.value.has(tag.id)) return
  selectedTags.value.push(tag)
  tagsResult.value = null
  tagFieldError.value = null
}
function removeTag(id: number) {
  if (!props.canAssignTags || tagMutationLoading.value) return
  selectedTags.value = selectedTags.value.filter(tag => tag.id !== id)
  tagsResult.value = null
  tagFieldError.value = null
}
function cancelTagsConfirm() {
  tagsConfirming.value = false
  tagsAcknowledged.value = false
}
function name(entity: SafeEntity) {
  return props.locale === 'ar' ? entity.name_ar : entity.name_en
}
function requestClose() {
  if (mutationBusy.value) return
  drawerRevision++
  invalidateRequests()
  emit('close')
}
function authorizeOut() {
  categoryResults.value = []
  tagResults.value = []
  selectedTags.value = []
  invalidateRequests()
  emit('authorizationError')
  emit('close')
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
  const value = errorData(error)?.message
  return typeof value === 'string' ? value : null
}
function fieldErrors(error: unknown, keys: string[]): string {
  const errors = errorData(error)?.errors
  if (!errors || typeof errors !== 'object') return serverMessage(error) || text.value.generic
  const record = errors as Record<string, string[]>
  const messages: string[] = []
  for (const [key, values] of Object.entries(record)) {
    if (keys.some(allowed => allowed.endsWith('.') ? key.startsWith(allowed) : key === allowed)) messages.push(...values)
  }
  return messages.length ? [...new Set(messages)].join(' ') : serverMessage(error) || text.value.generic
}
</script>

<style scoped>
.ym-bulk-overlay {
  --bulk-bg: #101827;
  --bulk-card: #182235;
  --bulk-soft: #202c42;
  --bulk-border: rgba(148, 163, 184, .24);
  --bulk-text: #f8fafc;
  --bulk-muted: #a8b3c5;
  position: fixed;
  inset: 0;
  z-index: 12000;
  isolation: isolate;
  display: flex;
  background: rgba(2, 6, 23, .72);
  backdrop-filter: blur(7px);
}
.ym-bulk-overlay.is-light {
  --bulk-bg: #f8fafc;
  --bulk-card: #fff;
  --bulk-soft: #eef2f7;
  --bulk-border: rgba(71, 85, 105, .22);
  --bulk-text: #172033;
  --bulk-muted: #64748b;
  background: rgba(15, 23, 42, .48);
}
.ym-bulk-overlay.is-rtl { justify-content: flex-start; }
.ym-bulk-overlay.is-ltr { justify-content: flex-end; }
.ym-bulk-drawer {
  width: min(720px, 96vw);
  height: 100%;
  overflow-y: auto;
  color: var(--bulk-text);
  background: var(--bulk-bg);
  box-shadow: 0 0 70px rgba(0, 0, 0, .45);
}
.ym-bulk-drawer:focus { outline: none; }
.ym-bulk-drawer button:focus-visible,
.ym-bulk-drawer input:focus-visible { outline: 3px solid rgba(139, 92, 246, .48); outline-offset: 2px; }
.ym-bulk-head {
  position: sticky;
  top: 0;
  z-index: 4;
  display: flex;
  justify-content: space-between;
  gap: 16px;
  padding: 24px;
  border-bottom: 1px solid var(--bulk-border);
  background: var(--bulk-bg);
}
.ym-bulk-head span { color: #8b5cf6; font-size: .78rem; font-weight: 900; text-transform: uppercase; }
.ym-bulk-head h2 { margin: 5px 0; font-size: clamp(1.35rem, 3vw, 2rem); }
.ym-bulk-head p { margin: 0; color: var(--bulk-muted); }
.ym-bulk-head > button {
  width: 42px; height: 42px; flex: 0 0 auto; border: 1px solid var(--bulk-border); border-radius: 13px;
  color: var(--bulk-text); background: var(--bulk-soft); font-size: 1.5rem; cursor: pointer;
}
.ym-bulk-live { padding: 0 24px; }
.ym-bulk-live p, .ym-bulk-error { padding: 11px 13px; border-radius: 12px; }
.ym-bulk-live .is-success { color: #059669; background: rgba(16, 185, 129, .12); }
.ym-bulk-live .is-error, .ym-bulk-error { color: #ef4444; background: rgba(239, 68, 68, .11); }
.ym-bulk-works, .ym-bulk-section { margin: 20px 24px; padding: 18px; border: 1px solid var(--bulk-border); border-radius: 20px; background: var(--bulk-card); }
.ym-bulk-works header, .ym-bulk-selected header { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.ym-bulk-works header button, .ym-bulk-error button { border: 0; color: #8b5cf6; background: none; font-weight: 800; cursor: pointer; }
.ym-bulk-works ul { max-height: 260px; overflow: hidden; margin: 12px 0; padding: 0; list-style: none; }
.ym-bulk-works ul.is-expanded { overflow-y: auto; }
.ym-bulk-works li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid var(--bulk-border); }
.ym-bulk-works code, .ym-bulk-options code, .ym-bulk-tag-results code { color: var(--bulk-muted); }
.ym-bulk-works > p { margin: 8px 0 0; color: var(--bulk-muted); }
.ym-bulk-tabs { display: flex; gap: 8px; padding: 0 24px; }
.ym-bulk-tabs button { flex: 1; min-height: 46px; border: 1px solid var(--bulk-border); border-radius: 14px; color: var(--bulk-muted); background: var(--bulk-soft); font-weight: 900; cursor: pointer; }
.ym-bulk-tabs button.is-active { color: #fff; border-color: transparent; background: linear-gradient(135deg, #7c3aed, #2563eb); }
.ym-bulk-section > header h3 { margin: 0 0 5px; }
.ym-bulk-section > header p { margin: 0; color: var(--bulk-muted); }
.ym-bulk-distribution, .ym-bulk-mixed, .ym-bulk-selected, .ym-bulk-confirm, .ym-bulk-result { margin-top: 18px; padding: 15px; border-radius: 16px; background: var(--bulk-soft); }
.ym-bulk-distribution h4, .ym-bulk-mixed h4, .ym-bulk-confirm h4, .ym-bulk-result h4 { margin: 0 0 10px; }
.ym-bulk-distribution article, .ym-bulk-mixed article, .ym-bulk-tag-results article { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-top: 1px solid var(--bulk-border); }
.ym-bulk-distribution article span, .ym-bulk-mixed article span, .ym-bulk-tag-results article span { display: grid; gap: 3px; }
.ym-bulk-distribution small, .ym-bulk-mixed small { color: var(--bulk-muted); }
.ym-bulk-mixed em { color: #f59e0b; font-size: .78rem; font-style: normal; }
.ym-bulk-mixed article button, .ym-bulk-tag-results button { padding: 8px 11px; border: 1px solid var(--bulk-border); border-radius: 10px; color: var(--bulk-text); background: var(--bulk-card); cursor: pointer; }
.ym-bulk-search { display: grid; gap: 8px; margin-top: 18px; font-weight: 800; }
.ym-bulk-search input { min-height: 46px; padding: 0 13px; border: 1px solid var(--bulk-border); border-radius: 13px; color: var(--bulk-text); background: var(--bulk-soft); }
.ym-bulk-hint, .ym-bulk-state { color: var(--bulk-muted); }
.ym-bulk-options { display: grid; gap: 9px; margin-top: 12px; max-height: 270px; overflow-y: auto; }
.ym-bulk-options label { display: flex; gap: 11px; padding: 12px; border: 1px solid var(--bulk-border); border-radius: 13px; cursor: pointer; }
.ym-bulk-options label.is-selected { border-color: #8b5cf6; background: rgba(139, 92, 246, .1); }
.ym-bulk-options label span { display: grid; gap: 3px; }
.ym-bulk-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.ym-bulk-chips button,
.ym-bulk-chip-readonly { display: inline-flex; align-items: center; gap: 6px; padding: 8px 10px; border: 1px solid rgba(139, 92, 246, .38); border-radius: 999px; color: var(--bulk-text); background: rgba(139, 92, 246, .12); }
.ym-bulk-chips button { cursor: pointer; }
.ym-bulk-chips button.is-disabled,
.ym-bulk-chip-readonly.is-disabled { border-color: rgba(245, 158, 11, .5); background: rgba(245, 158, 11, .1); }
.ym-bulk-chips small { color: #f59e0b; }
.ym-bulk-section footer { display: flex; justify-content: flex-end; margin-top: 18px; }
.ym-bulk-section .is-primary, .ym-bulk-section .is-secondary { min-height: 44px; padding: 0 17px; border-radius: 12px; font-weight: 900; cursor: pointer; }
.ym-bulk-section .is-primary { border: 0; color: #fff; background: linear-gradient(135deg, #7c3aed, #2563eb); }
.ym-bulk-section .is-secondary { border: 1px solid var(--bulk-border); color: var(--bulk-text); background: var(--bulk-card); }
.ym-bulk-section button:disabled { opacity: .48; cursor: not-allowed; }
.ym-bulk-confirm dl, .ym-bulk-result dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
.ym-bulk-confirm dl div, .ym-bulk-result dl div { padding: 10px; border-radius: 10px; background: var(--bulk-card); }
.ym-bulk-confirm dt, .ym-bulk-result dt { color: var(--bulk-muted); font-size: .78rem; }
.ym-bulk-confirm dd, .ym-bulk-result dd { margin: 4px 0 0; font-weight: 900; }
.ym-bulk-confirm .is-warning { color: #f59e0b; }
.ym-bulk-confirm__actions { display: flex; justify-content: flex-end; gap: 9px; margin-top: 14px; }
.ym-bulk-ack { display: flex; align-items: flex-start; gap: 10px; padding: 12px; border: 1px solid rgba(245, 158, 11, .38); border-radius: 12px; }
.ym-bulk-result { border: 1px solid rgba(16, 185, 129, .35); }
.ym-bulk-result p { color: #059669; }
@media (max-width: 600px) {
  .ym-bulk-drawer { width: 100vw; }
  .ym-bulk-head, .ym-bulk-works, .ym-bulk-section { padding: 16px; }
  .ym-bulk-works, .ym-bulk-section { margin: 14px 12px; }
  .ym-bulk-tabs { padding: 0 12px; }
  .ym-bulk-works li { align-items: flex-start; flex-direction: column; }
  .ym-bulk-confirm dl, .ym-bulk-result dl { grid-template-columns: 1fr; }
}
</style>
