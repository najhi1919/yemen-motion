<template>
  <div
    class="ym-taxonomy-page ym-admin-page"
    data-admin-accent="taxonomy"
    :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
  >
    <AdminPageHero
      :breadcrumbs="heroBreadcrumbs"
      :breadcrumb-label="copy.title"
      :eyebrow="copy.kicker"
      :badge="actionBadge"
      :title="copy.title"
      :description="copy.heroDescription"
    >
      <template #icon>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M4 5h7v6H4zM13 5h7v6h-7zM4 13h7v6H4zM13 13h7v6h-7z" />
          <path d="M7.5 8h.01M16.5 8h.01M7.5 16h.01M16.5 16h.01" />
        </svg>
      </template>
    </AdminPageHero>

    <section
      v-if="authPending"
      class="ym-taxonomy-access-state"
      role="status"
      aria-live="polite"
    >
      <span class="ym-taxonomy-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section
      v-else-if="forbidden"
      class="ym-taxonomy-access-state is-forbidden"
      role="status"
    >
      <span class="ym-taxonomy-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <nav class="ym-taxonomy-tabs" :aria-label="copy.tabsLabel">
        <button type="button" :class="{ 'is-active': activeTab === 'overview' }" :aria-current="activeTab === 'overview' ? 'page' : undefined" @click="activeTab = 'overview'">{{ copy.overviewTab }}</button>
        <button v-if="canViewCategories" type="button" :class="{ 'is-active': activeTab === 'categories' }" :aria-current="activeTab === 'categories' ? 'page' : undefined" @click="activeTab = 'categories'">{{ copy.categoriesTab }}</button>
        <button v-if="canViewTags" type="button" :class="{ 'is-active': activeTab === 'tags' }" :aria-current="activeTab === 'tags' ? 'page' : undefined" @click="activeTab = 'tags'">{{ copy.tagsTab }}</button>
      </nav>

      <div v-show="activeTab === 'overview'" class="ym-taxonomy-tab-panel">
      <AdminPolicyBar
        v-if="categorySupport && tagSupport"
        :items="policyItems"
        :aria-label="copy.supportLabel"
        :close-label="copy.close"
      />

      <AdminMetricStrip
        v-if="summary"
        :items="primarySummaryCards"
        locale="en"
        :aria-label="copy.summaryLabel"
        :loading="loading && !hasLoaded"
        :updating="loading && hasLoaded"
      />

      <section v-if="summary" class="ym-taxonomy-secondary-summary" :aria-label="copy.secondarySummaryLabel">
        <span v-for="item in secondarySummaryItems" :key="item.key">
          {{ item.label }} <strong>{{ formatNumber(item.value) }}</strong>
        </span>
      </section>

      <aside
        v-if="tagSupport && !tagSupport.available"
        class="ym-taxonomy-tag-support"
        role="note"
      >
        <span class="ym-taxonomy-tag-support__icon" aria-hidden="true">i</span>
        <div>
          <strong>{{ copy.tagsUnavailableTitle }}</strong>
          <p>{{ tagSupport.reason }}</p>
          <small>{{ copy.tagsUnavailableCopy }}</small>
        </div>
      </aside>

      <section class="ym-taxonomy-filter-card">
        <form class="ym-taxonomy-filter-grid" @submit.prevent="applyFilters">
          <label class="is-search">
            <span>{{ copy.search }}</span>
            <input
              v-model.trim="filters.q"
              type="search"
              minlength="1"
              maxlength="80"
              :placeholder="copy.searchPlaceholder"
              autocomplete="off"
            />
          </label>

          <label>
            <span>{{ copy.status }}</span>
            <select v-model="filters.status">
              <option value="">{{ copy.all }}</option>
              <option
                v-for="option in statusOptions"
                :key="option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.visibility }}</span>
            <select v-model="filters.visibility_status">
              <option value="">{{ copy.all }}</option>
              <option value="public">{{ copy.publicVisibility }}</option>
              <option value="hidden">{{ copy.hiddenVisibility }}</option>
            </select>
          </label>

          <label>
            <span>{{ copy.mediaType }}</span>
            <select v-model="filters.media_type">
              <option value="">{{ copy.all }}</option>
              <option value="image">{{ copy.image }}</option>
              <option value="video">{{ copy.video }}</option>
            </select>
          </label>

          <label>
            <span>{{ copy.linkFilter }}</span>
            <select v-model="filters.only_uncategorized">
              <option
                v-for="option in booleanOptions"
                :key="'uncategorized-' + option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <div class="ym-taxonomy-filter-toolbar">
            <button type="button" class="ym-taxonomy-advanced-toggle" :class="{ 'is-open': showAdvancedFilters }" @click="showAdvancedFilters = !showAdvancedFilters">
              <span aria-hidden="true">⌁</span>
              {{ copy.advancedFilters }}
              <b v-if="activeAdvancedFiltersCount">{{ activeAdvancedFiltersCount }}</b>
            </button>
            <div class="ym-taxonomy-filter-actions">
            <button
              type="submit"
              class="ym-taxonomy-button is-primary"
              :disabled="loading"
            >
              {{ copy.apply }}
            </button>
            <button
              type="button"
              class="ym-taxonomy-button is-secondary"
              :disabled="loading"
              :title="copy.resetHint"
              @click="resetFilters"
            >
              {{ copy.reset }}
            </button>
            </div>
          </div>

          <div v-show="showAdvancedFilters" class="ym-taxonomy-filter-grid is-advanced">
            <label><span>{{ copy.categoryId }}</span><input v-model="filters.category_id" type="number" step="1" inputmode="numeric" dir="ltr" /></label>
            <label><span>{{ copy.onlyReported }}</span><select v-model="filters.only_reported"><option v-for="option in booleanOptions" :key="'reported-' + option.value" :value="option.value">{{ option.label }}</option></select></label>
            <label><span>{{ copy.onlyPromoted }}</span><select v-model="filters.only_promoted"><option v-for="option in booleanOptions" :key="'promoted-' + option.value" :value="option.value">{{ option.label }}</option></select></label>
            <label><span>{{ copy.from }}</span><input v-model="filters.from" type="date" /></label>
            <label><span>{{ copy.to }}</span><input v-model="filters.to" type="date" /></label>
            <label><span>{{ copy.sortLabel }}</span><select v-model="filters.sort"><option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
            <label><span>{{ copy.directionLabel }}</span><select v-model="filters.direction"><option value="desc">{{ copy.descending }}</option><option value="asc">{{ copy.ascending }}</option></select></label>
            <label><span>{{ copy.perPage }}</span><select v-model.number="filters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          </div>
        </form>

        <p v-if="filterError" class="ym-taxonomy-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <WorksTaxonomyOverviewSmartList
        :locale="currentLocale"
        :items="items"
        :pagination="pagination"
        :loading="loading"
        :has-loaded="hasLoaded"
        :error="error"
        :metric="engagementMetric"
        :sort="appliedFilters.sort"
        :direction="appliedFilters.direction"
        @retry="fetchTaxonomy"
        @reset="resetFilters"
        @page="changePage"
        @metric-change="changeEngagementMetric"
        @details="openSummary"
      />
      </div>

      <WorksTaxonomyCatalogPanel
        v-if="canViewCategories"
        v-show="activeTab === 'categories'"
        entity-type="category"
        :locale="currentLocale"
        :active="activeTab === 'categories'"
        :can-create="canCreateCategories"
        :can-update="canUpdateCategories"
        :can-disable="canDisableCategories"
        :permission-revision="authorizationSignature"
        @changed="refreshOverviewAfterAction"
        @authorization-error="handleCatalogAuthorizationError"
      />
      <WorksTaxonomyCatalogPanel
        v-if="canViewTags"
        v-show="activeTab === 'tags'"
        entity-type="tag"
        :locale="currentLocale"
        :active="activeTab === 'tags'"
        :can-create="canCreateTags"
        :can-update="canUpdateTags"
        :can-disable="canDisableTags"
        :can-merge="canMergeTags"
        :permission-revision="authorizationSignature"
        @changed="refreshOverviewAfterAction"
        @authorization-error="handleCatalogAuthorizationError"
      />
    </template>

    <WorksTaxonomyOverviewDetailsDrawer
      :open="activeTab === 'overview' && drawerOpen"
      :locale="currentLocale"
      :bucket="selectedBucket"
      @close="closeSummary"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import AdminMetricStrip from '~/components/admin/visual/AdminMetricStrip.vue'
import AdminPageHero from '~/components/admin/visual/AdminPageHero.vue'
import AdminPolicyBar from '~/components/admin/visual/AdminPolicyBar.vue'
import WorksTaxonomyCatalogPanel from '~/components/works/taxonomy/WorksTaxonomyCatalogPanel.vue'
import WorksTaxonomyOverviewDetailsDrawer from '~/components/works/taxonomy/WorksTaxonomyOverviewDetailsDrawer.vue'
import WorksTaxonomyOverviewSmartList from '~/components/works/taxonomy/WorksTaxonomyOverviewSmartList.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import { formatYmNumber } from '~/utils/ymFormatting'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = '' | 'public' | 'hidden'
type BooleanFilter = '' | '1' | '0'
type SortDirection = 'asc' | 'desc'
type PageSize = 15 | 25 | 50
type TaxonomySortKey = 'works_count' | 'category_id' | 'latest_work_at' | 'reported_count' | 'published_count' | 'hidden_count' | 'total_reports' | 'total_views' | 'total_likes'
interface TaxonomyFlags {
  uncategorized: boolean
  has_reports: boolean
  has_published: boolean
  has_hidden: boolean
  is_promoted: boolean
  needs_attention: boolean
}

interface SafeCategory {
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

interface TaxonomyBucket {
  category_id: number | null
  label: string
  category: SafeCategory | null
  category_tracking: CategoryTracking
  works_count: number
  published_count: number
  hidden_count: number
  review_queue_count: number
  reported_count: number
  featured_count: number
  pinned_count: number
  total_reports: number
  total_views: number
  total_likes: number
  latest_work_at: string | null
  taxonomy_flags: TaxonomyFlags
}

interface TaxonomyPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface TaxonomySummary {
  total_categories: number
  categorized_categories: number
  uncategorized_buckets: number
  total_works: number
  categorized_works: number
  uncategorized_works: number
  reported_categories: number
  promoted_categories: number
  published_categories: number
  hidden_categories: number
  total_reports: number
  total_views: number
  total_likes: number
  catalog_categories_total: number
  active_catalog_categories: number
  disabled_catalog_categories: number
  used_catalog_categories: number
  unused_catalog_categories: number
  legacy_unmapped_category_ids: number
  works_with_legacy_unmapped_category: number
  tags_total: number
  active_tags: number
  disabled_tags: number
  used_tags: number
  unused_tags: number
  tag_assignments_total: number
}

interface TagSupport {
  available: boolean
  reason: string
  catalog_source: string
  assignments_source: string
}

interface CategorySupport {
  available: boolean
  catalog_source: string
  work_reference: string
  foreign_key_enforced: boolean
  legacy_unmapped_values_possible: boolean
  mapping_complete: boolean
  reason: string
}

interface TaxonomyData {
  items: TaxonomyBucket[]
  pagination: TaxonomyPagination
  summary: TaxonomySummary
  filters: Record<string, unknown>
  category_support: CategorySupport
  tag_support: TagSupport
}

interface TaxonomyResponse {
  success: boolean
  data: TaxonomyData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface TaxonomyFilters {
  q: string
  category_id: string
  status: '' | WorkStatus
  visibility_status: VisibilityStatus
  media_type: string
  only_uncategorized: BooleanFilter
  only_reported: BooleanFilter
  only_promoted: BooleanFilter
  from: string
  to: string
  sort: TaxonomySortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonly: 'قراءة حسب الصلاحيات',
    directManagement: 'إدارة مباشرة',
    tabsLabel: 'أقسام إدارة التصنيفات والوسوم',
    overviewTab: 'النظرة العامة',
    categoriesTab: 'كتالوج التصنيفات',
    tagsTab: 'كتالوج الوسوم',
    kicker: 'إدارة تصنيفات الأعمال',
    title: 'التصنيفات والوسوم',
    heroDescription: 'إدارة التجميعات وكتالوجات التصنيفات والوسوم من محطة إدارية واحدة.',
    descriptionBefore: 'مركز إدارة كتالوجات التصنيف والوسوم مع نظرة التجميعات المبنية من قيمة',
    descriptionAfter: 'الحالية في الأعمال، دون عرض صفوف الأعمال المفردة.',
    totalCategories: 'إجمالي التجميعات',
    categoryBuckets: 'تجميعات التصنيف الحالية',
    worksInScope: 'عملًا ضمن النطاق',
    authLoadingTitle: 'جارٍ التحقق من صلاحية التصنيفات',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى التصنيفات والوسوم غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب الصلاحيات المطلوبة لقراءة تصنيفات الأعمال. لم تتم محاولة تحميل البيانات.',
    managementNoticeTitle: 'الإجراءات تظهر حسب صلاحيات الحساب',
    managementNotice: 'استخدم التبويبات لإدارة الكتالوجات. إسناد التصنيفات والوسوم إلى الأعمال سيبقى ضمن صفحة كل الأعمال في المهمة التالية.',
    readOnlyNoticeTitle: 'وصول للقراءة حسب الصلاحيات',
    readOnlyNotice: 'يمكن لهذا الحساب قراءة الأقسام المصرح بها، ولا تظهر له أسماء أو أزرار الإجراءات غير المصرح بها.',
    summaryLabel: 'ملخص تصنيفات الأعمال المطابقة للفلاتر',
    secondarySummaryLabel: 'مؤشرات التصنيفات الثانوية',
    summaryTotalCategories: 'إجمالي التجميعات',
    summaryTotalCategoriesHint: 'كل تجميعات التصنيف المطابقة',
    categorizedCategories: 'تصنيفات معرّفة',
    categorizedCategoriesHint: 'تجميعات لها معرّف تصنيف',
    uncategorizedBuckets: 'تجميعات غير مصنفة',
    uncategorizedBucketsHint: 'تجميعات تحتاج إلى ترتيب',
    totalWorks: 'إجمالي الأعمال',
    totalWorksHint: 'الأعمال الداخلة في التجميعات',
    categorizedWorks: 'أعمال مصنفة',
    categorizedWorksHint: 'أعمال مرتبطة بمعرّف تصنيف',
    uncategorizedWorks: 'أعمال غير مصنفة',
    uncategorizedWorksHint: 'أعمال دون معرّف تصنيف',
    reportedCategories: 'تصنيفات عليها بلاغات',
    reportedCategoriesHint: 'تجميعات تحتوي أعمالًا مبلّغًا عنها',
    promotedCategories: 'تصنيفات مروّجة',
    promotedCategoriesHint: 'تجميعات فيها أعمال مميزة أو مثبتة',
    publishedCategories: 'تصنيفات فيها منشور',
    publishedCategoriesHint: 'تجميعات تحتوي أعمالًا منشورة',
    hiddenCategories: 'تصنيفات فيها مخفي',
    hiddenCategoriesHint: 'تجميعات تحتوي أعمالًا مخفية',
    totalReports: 'مجموع البلاغات',
    totalReportsHint: 'كل البلاغات ضمن النطاق',
    totalViews: 'مجموع المشاهدات',
    totalViewsHint: 'كل المشاهدات ضمن النطاق',
    totalLikes: 'مجموع الإعجابات',
    totalLikesHint: 'كل الإعجابات ضمن النطاق',
    catalogCategories: 'سجلات كتالوج التصنيفات',
    catalogCategoriesHint: 'إجمالي التصنيفات المعرفة في الكتالوج',
    legacyCategoryIds: 'قيم قديمة غير مربوطة',
    legacyCategoryIdsHint: 'معرفات لا يقابلها سجل كتالوج',
    unusedLabel: 'تصنيفات غير مستخدمة',
    catalogTags: 'وسوم الكتالوج',
    catalogTagsHint: 'إجمالي الوسوم الفعالة والمعطلة',
    tagAssignments: 'إسنادات الوسوم',
    tagAssignmentsHint: 'إجمالي صفوف إسناد الوسوم الحالية',
    categorySupportTitle: 'دعم كتالوج التصنيفات',
    supportLabel: 'حالة دعم كتالوجات التصنيفات والوسوم',
    tagSupportTitle: 'دعم كتالوج الوسوم',
    mappingComplete: 'كل القيم الحالية مرتبطة بسجلات كتالوج.',
    mappingHasLegacy: 'توجد قيم قديمة غير مربوطة ويجري تمييزها بوضوح.',
    supportAvailable: 'واجهة الكتالوج والإسنادات متاحة.',
    supportUnavailable: 'تعذر التحقق من دعم الوسوم.',
    permissionsPolicyTitle: 'صلاحيات الإدارة',
    legacyPolicyTitle: 'ارتباط التصنيفات',
    tagsPolicyTitle: 'دعم الوسوم',
    mappingCompleteShort: 'الارتباط مكتمل',
    mappingHasLegacyShort: 'توجد قيم قديمة تحتاج معالجة',
    tagsAvailableShort: 'الكتالوج والإسنادات متاحان',
    tagsUnavailableTitle: 'تعذر التحقق من دعم الوسوم',
    tagsUnavailableCopy: 'أعد تحميل النظرة العامة للتحقق من حالة كتالوج الوسوم.',
    noActivity: 'لا يوجد نشاط',
    filtersTitle: 'بحث وفلاتر التصنيفات',
    filtersCopy: 'ضيّق التجميعات باستخدام معاملات التصنيفات المعتمدة فقط.',
    search: 'البحث',
    searchPlaceholder: 'اسم التجميع أو غير مصنف',
    searchHint: 'بحد أقصى 80 حرفًا، ويطابق تسمية التجميع فقط.',
    categoryId: 'معرّف التصنيف',
    status: 'حالة العمل',
    visibility: 'حالة الظهور',
    mediaType: 'نوع الوسائط',
    image: 'صورة',
    video: 'فيديو',
    linkFilter: 'حالة الارتباط',
    onlyUncategorized: 'غير مصنف فقط',
    onlyReported: 'عليه بلاغات فقط',
    onlyPromoted: 'مروّج فقط',
    from: 'آخر تحديث من',
    to: 'آخر تحديث إلى',
    updatedRangeHint: 'يُطبّق على آخر تحديث للأعمال.',
    perPage: 'لكل صفحة',
    all: 'الكل',
    yes: 'نعم',
    no: 'لا',
    publicVisibility: 'عام',
    hiddenVisibility: 'مخفي',
    apply: 'تطبيق',
    advancedFilters: 'فلاتر متقدمة',
    sortLabel: 'الفرز',
    directionLabel: 'الاتجاه',
    ascending: 'تصاعدي',
    descending: 'تنازلي',
    reset: 'إعادة ضبط',
    resetHint: 'مسح الفلاتر واستعادة الفرز الافتراضي',
    invalidCategoryId: 'معرّف التصنيف يجب أن يكون عددًا صحيحًا.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من القيم والتواريخ المدخلة.',
    tableTitle: 'تجميعات التصنيفات',
    tableCopy: 'كل صف يمثل تجميعًا محسوبًا، ويمكن فتح ملخصه دون طلب بيانات إضافية.',
    currentPage: 'الصفحة الحالية',
    loadingTitle: 'جارٍ تحميل تجميعات التصنيفات',
    loadingCopy: 'يتم جلب التجميعات الآمنة وفق الفلاتر الحالية...',
    preparingCopy: 'جارٍ تجهيز حالة الصفحة...',
    errorTitle: 'تعذر تحميل تجميعات التصنيفات',
    genericError: 'حدث خطأ أثناء تحميل التصنيفات. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    emptyTitle: 'لا توجد تجميعات مطابقة',
    emptyCopy: 'لا توجد تجميعات ضمن نطاق الفلاتر الحالي. جرّب تعديل الفلاتر أو إعادة ضبطها.',
    category: 'التصنيف',
    workDistribution: 'توزيع الأعمال',
    reviewAndRisk: 'المراجعة والمخاطر',
    visibilityAndPromotion: 'الظهور والترويج',
    engagement: 'التفاعل',
    linkAndIndicators: 'الارتباط والمؤشرات',
    totalShort: 'الإجمالي',
    reviewShort: 'المراجعة',
    reportedShort: 'أعمال مبلّغ عنها',
    totalReportsShort: 'البلاغات',
    viewsShort: 'مشاهدات',
    likesShort: 'إعجابات',
    noPromotionSignals: 'دون مؤشرات ترويج',
    updatingResults: 'جارٍ تحديث النتائج…',
    worksCount: 'عدد الأعمال',
    publishedCount: 'منشورة',
    hiddenCount: 'مخفية',
    reviewQueueCount: 'ضمن طابور المراجعة',
    reportedCount: 'عليها بلاغات',
    featuredCount: 'مميزة',
    pinnedCount: 'مثبتة',
    latestWorkAt: 'آخر تحديث',
    uncategorizedFlag: 'غير مصنف',
    hasReportsFlag: 'لديه بلاغات',
    hasPublishedFlag: 'لديه منشور',
    hasHiddenFlag: 'لديه مخفي',
    promotedFlag: 'مروّج',
    needsAttentionFlag: 'يحتاج انتباه',
    readAction: 'التفاصيل',
    additionalIndicators: (count: string) => `${count} مؤشرات إضافية`,
    uncategorized: 'غير مصنف',
    legacyUnmapped: 'قيمة قديمة غير مربوطة',
    legacyShort: 'قيمة قديمة',
    categoryActive: 'تصنيف فعال',
    categoryDisabled: 'تصنيف معطل',
    catalogLinked: 'مرتبط بسجل كتالوج',
    linkState: 'حالة الربط',
    arabicName: 'الاسم العربي',
    englishName: 'الاسم الإنجليزي',
    slugLabel: 'المعرّف النصي',
    categoryState: 'حالة التصنيف',
    supportReason: 'سبب دعم القيم القديمة',
    legacyReason: 'قد توجد قيم تصنيف قديمة لا يقابلها تصنيف حالي في الكتالوج.',
    uncategorizedHint: 'تجميع يحتاج إلى ترتيب تصنيفي',
    categorizedHint: 'تجميع مبني من معرّف التصنيف الحالي',
    classified: 'مصنف',
    reportsPresent: 'عليه بلاغات',
    reportsAbsent: 'دون بلاغات',
    publishedPresent: 'لديه منشور',
    publishedAbsent: 'دون منشور',
    hiddenPresent: 'لديه مخفي',
    hiddenAbsent: 'دون مخفي',
    promoted: 'مروّج',
    notPromoted: 'غير مروّج',
    attentionNeeded: 'يحتاج انتباه',
    stable: 'مستقر',
    viewCategorySummary: 'عرض ملخص التصنيف',
    openSummaryFor: (label: string) => 'عرض ملخص ' + label,
    paginationTotal: 'إجمالي التجميعات',
    visibleNow: 'تجميع ظاهر الآن',
    paginationLabel: 'التنقل بين صفحات تجميعات التصنيفات',
    previous: 'السابق',
    next: 'التالي',
    pageOf: (page: string, last: string) => 'الصفحة ' + page + ' من ' + last,
    drawerReadonly: 'ملخص للقراءة فقط',
    close: 'إغلاق الملخص',
    drawerCopy: 'هذا الملخص مبني بالكامل من بيانات التجميع المحدد ولا يحمّل أي بيانات إضافية.',
    bucketIdentity: 'هوية التجميع',
    bucketCounts: 'مؤشرات التجميع',
    taxonomyFlags: 'علامات التصنيف',
    taxonomyFlagsCopy: 'تعكس هذه العلامات حالة التجميع المحسوبة في استجابة الخادم.'
  },
  en: {
    readonly: 'Permission-based reading',
    directManagement: 'Direct management',
    tabsLabel: 'Taxonomy management sections',
    overviewTab: 'Overview',
    categoriesTab: 'Category catalog',
    tagsTab: 'Tag catalog',
    kicker: 'Works taxonomy management',
    title: 'Categories and Tags',
    heroDescription: 'Manage buckets and category and tag catalogs from one administrative station.',
    descriptionBefore: 'Manage category and tag catalogs alongside buckets derived from the current',
    descriptionAfter: 'value on works, without displaying individual work rows.',
    totalCategories: 'Total buckets',
    categoryBuckets: 'Current category buckets',
    worksInScope: 'works in scope',
    authLoadingTitle: 'Checking taxonomy access',
    authLoadingCopy: 'Waiting for user-session initialization before making any data request.',
    forbiddenTitle: 'Categories and tags access is unavailable',
    forbiddenCopy: 'This account lacks the permissions required to read works taxonomy. No data request was made.',
    managementNoticeTitle: 'Actions follow account permissions',
    managementNotice: 'Use the tabs to manage catalogs. Assigning taxonomy to works remains planned for the All Works page in the next task.',
    readOnlyNoticeTitle: 'Permission-based read access',
    readOnlyNotice: 'This account can read authorized sections; unauthorized action names and controls remain hidden.',
    summaryLabel: 'Summary of works taxonomy matching the filters',
    secondarySummaryLabel: 'Secondary taxonomy indicators',
    summaryTotalCategories: 'Total buckets',
    summaryTotalCategoriesHint: 'All matching category buckets',
    categorizedCategories: 'Categorized buckets',
    categorizedCategoriesHint: 'Buckets with a category identifier',
    uncategorizedBuckets: 'Uncategorized buckets',
    uncategorizedBucketsHint: 'Buckets that need organization',
    totalWorks: 'Total works',
    totalWorksHint: 'Works included in the buckets',
    categorizedWorks: 'Categorized works',
    categorizedWorksHint: 'Works linked to a category identifier',
    uncategorizedWorks: 'Uncategorized works',
    uncategorizedWorksHint: 'Works without a category identifier',
    reportedCategories: 'Reported categories',
    reportedCategoriesHint: 'Buckets containing reported works',
    promotedCategories: 'Promoted categories',
    promotedCategoriesHint: 'Buckets with featured or pinned works',
    publishedCategories: 'Categories with published works',
    publishedCategoriesHint: 'Buckets containing published works',
    hiddenCategories: 'Categories with hidden works',
    hiddenCategoriesHint: 'Buckets containing hidden works',
    totalReports: 'Total reports',
    totalReportsHint: 'All reports in the current scope',
    totalViews: 'Total views',
    totalViewsHint: 'All views in the current scope',
    totalLikes: 'Total likes',
    totalLikesHint: 'All likes in the current scope',
    catalogCategories: 'Category catalog records',
    catalogCategoriesHint: 'All categories defined in the catalog',
    legacyCategoryIds: 'Unmapped legacy values',
    legacyCategoryIdsHint: 'Identifiers without a catalog record',
    unusedLabel: 'Unused categories',
    catalogTags: 'Catalog tags',
    catalogTagsHint: 'All active and disabled tags',
    tagAssignments: 'Tag assignments',
    tagAssignmentsHint: 'All current tag assignment rows',
    categorySupportTitle: 'Category catalog support',
    supportLabel: 'Category and tag catalog support status',
    tagSupportTitle: 'Tag catalog support',
    mappingComplete: 'All current values map to catalog records.',
    mappingHasLegacy: 'Unmapped legacy values exist and are identified separately.',
    supportAvailable: 'Catalog and assignment APIs are available.',
    supportUnavailable: 'Tag support could not be verified.',
    permissionsPolicyTitle: 'Management permissions',
    legacyPolicyTitle: 'Category linkage',
    tagsPolicyTitle: 'Tag support',
    mappingCompleteShort: 'Linkage is complete',
    mappingHasLegacyShort: 'Legacy values need attention',
    tagsAvailableShort: 'Catalog and assignments are available',
    tagsUnavailableTitle: 'Could not verify tag support',
    tagsUnavailableCopy: 'Reload the overview to verify the tag catalog status.',
    noActivity: 'No activity',
    filtersTitle: 'Taxonomy search and filters',
    filtersCopy: 'Narrow the buckets using only the approved taxonomy parameters.',
    search: 'Search',
    searchPlaceholder: 'Bucket label or Uncategorized',
    searchHint: 'Up to 80 characters and matched against the bucket label only.',
    categoryId: 'category_id',
    status: 'Work status',
    visibility: 'Visibility',
    mediaType: 'Media type',
    image: 'Image',
    video: 'Video',
    linkFilter: 'Link state',
    onlyUncategorized: 'Only uncategorized',
    onlyReported: 'Only reported',
    onlyPromoted: 'Only promoted',
    from: 'Updated from',
    to: 'Updated to',
    updatedRangeHint: 'Applied to the works update time.',
    perPage: 'Per page',
    all: 'All',
    yes: 'Yes',
    no: 'No',
    publicVisibility: 'Public',
    hiddenVisibility: 'Hidden',
    apply: 'Apply',
    advancedFilters: 'Advanced filters',
    sortLabel: 'Sort',
    directionLabel: 'Direction',
    ascending: 'Ascending',
    descending: 'Descending',
    reset: 'Reset',
    resetHint: 'Clear filters and restore default sorting',
    invalidCategoryId: 'The category identifier must be an integer.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    validationError: 'The filters could not be applied. Check the entered values and dates.',
    tableTitle: 'Category buckets',
    tableCopy: 'Each row is a computed bucket whose summary opens without another data request.',
    currentPage: 'Current page',
    loadingTitle: 'Loading category buckets',
    loadingCopy: 'Fetching safe buckets with the current filters...',
    preparingCopy: 'Preparing the page state...',
    errorTitle: 'Could not load category buckets',
    genericError: 'An error occurred while loading taxonomy. Try again.',
    retry: 'Retry',
    emptyTitle: 'No matching buckets',
    emptyCopy: 'No buckets match the current filters. Change or reset the filters.',
    category: 'Category',
    workDistribution: 'Work distribution',
    reviewAndRisk: 'Review and risk',
    visibilityAndPromotion: 'Visibility and promotion',
    engagement: 'Engagement',
    linkAndIndicators: 'Link and indicators',
    totalShort: 'Total',
    reviewShort: 'Review',
    reportedShort: 'Reported works',
    totalReportsShort: 'Reports',
    viewsShort: 'Views',
    likesShort: 'Likes',
    noPromotionSignals: 'No promotion signals',
    updatingResults: 'Updating results…',
    worksCount: 'Works',
    publishedCount: 'Published',
    hiddenCount: 'Hidden',
    reviewQueueCount: 'Review queue',
    reportedCount: 'Reported works',
    featuredCount: 'Featured',
    pinnedCount: 'Pinned',
    latestWorkAt: 'Latest update',
    uncategorizedFlag: 'Uncategorized',
    hasReportsFlag: 'Has reports',
    hasPublishedFlag: 'Has published',
    hasHiddenFlag: 'Has hidden',
    promotedFlag: 'Promoted',
    needsAttentionFlag: 'Needs attention',
    readAction: 'Details',
    additionalIndicators: (count: string) => `${count} additional indicators`,
    uncategorized: 'Uncategorized',
    legacyUnmapped: 'Unmapped legacy value',
    legacyShort: 'Legacy value',
    categoryActive: 'Active category',
    categoryDisabled: 'Disabled category',
    catalogLinked: 'Linked to catalog record',
    linkState: 'Link state',
    arabicName: 'Arabic name',
    englishName: 'English name',
    slugLabel: 'Slug',
    categoryState: 'Category state',
    supportReason: 'Legacy support reason',
    legacyReason: 'Some legacy category values may not match a current catalog category.',
    uncategorizedHint: 'A bucket that needs taxonomy organization',
    categorizedHint: 'A bucket derived from the current category identifier',
    classified: 'Categorized',
    reportsPresent: 'Has reports',
    reportsAbsent: 'No reports',
    publishedPresent: 'Has published',
    publishedAbsent: 'No published',
    hiddenPresent: 'Has hidden',
    hiddenAbsent: 'No hidden',
    promoted: 'Promoted',
    notPromoted: 'Not promoted',
    attentionNeeded: 'Needs attention',
    stable: 'Stable',
    viewCategorySummary: 'View category summary',
    openSummaryFor: (label: string) => 'View summary for ' + label,
    paginationTotal: 'Total buckets',
    visibleNow: 'buckets visible now',
    paginationLabel: 'Category bucket pagination',
    previous: 'Previous',
    next: 'Next',
    pageOf: (page: string, last: string) => 'Page ' + page + ' of ' + last,
    drawerReadonly: 'Read-only summary',
    close: 'Close summary',
    drawerCopy: 'This summary uses only the selected bucket data and does not load anything else.',
    bucketIdentity: 'Bucket identity',
    bucketCounts: 'Bucket metrics',
    taxonomyFlags: 'Taxonomy flags',
    taxonomyFlagsCopy: 'These flags reflect the computed bucket state returned by the server.'
  }
} as const

const copy = computed(() => copyMap[currentLocale.value])
const authPending = computed(() => !authStore.isInitialized)
const isSuperAdmin = computed(() => authStore.isSuperAdmin)
const isInternalRole = computed(() => ['admin', 'staff'].includes(authStore.role || ''))
const hasPermission = (permission: string): boolean => authStore.can(permission)
const hasTaxonomyAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (isSuperAdmin.value) return true
  if (!isInternalRole.value) return false

  return authStore.canAll([
    'admin.works.access',
    'admin.works.taxonomy.view'
  ])
})
const canViewCategories = computed(() => hasTaxonomyAccess.value && hasPermission('admin.works.taxonomy.categories.view'))
const canCreateCategories = computed(() => canViewCategories.value && hasPermission('admin.works.taxonomy.categories.create'))
const canUpdateCategories = computed(() => canViewCategories.value && hasPermission('admin.works.taxonomy.categories.update'))
const canDisableCategories = computed(() => canViewCategories.value && hasPermission('admin.works.taxonomy.categories.disable'))
const canViewTags = computed(() => hasTaxonomyAccess.value && hasPermission('admin.works.taxonomy.tags.view'))
const canCreateTags = computed(() => canViewTags.value && hasPermission('admin.works.taxonomy.tags.create'))
const canUpdateTags = computed(() => canViewTags.value && hasPermission('admin.works.taxonomy.tags.update'))
const canDisableTags = computed(() => canViewTags.value && hasPermission('admin.works.taxonomy.tags.disable'))
const canMergeTags = computed(() => canViewTags.value && hasPermission('admin.works.taxonomy.merge_tags'))
const hasAnyAction = computed(() => canCreateCategories.value || canUpdateCategories.value || canDisableCategories.value || canCreateTags.value || canUpdateTags.value || canDisableTags.value || canMergeTags.value)
const actionBadge = computed(() => hasAnyAction.value ? copy.value.directManagement : copy.value.readonly)
const activeTab = ref<'overview' | 'categories' | 'tags'>('overview')
const serverForbidden = ref(false)
const forbidden = computed(() => (
  authStore.isInitialized && (!hasTaxonomyAccess.value || serverForbidden.value)
))

const items = ref<TaxonomyBucket[]>([])
const pagination = reactive<TaxonomyPagination>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
const summary = ref<TaxonomySummary | null>(null)
const tagSupport = ref<TagSupport | null>(null)
const categorySupport = ref<CategorySupport | null>(null)

function defaultFilters(): TaxonomyFilters {
  return {
    q: '',
    category_id: '',
    status: '',
    visibility_status: '',
    media_type: '',
    only_uncategorized: '',
    only_reported: '',
    only_promoted: '',
    from: '',
    to: '',
    sort: 'works_count',
    direction: 'desc',
    per_page: 15
  }
}

const filters = reactive<TaxonomyFilters>(defaultFilters())
const appliedFilters = reactive<TaxonomyFilters>(defaultFilters())
const engagementMetric = ref<'total_views' | 'total_likes' | 'total_reports'>('total_views')
const page = ref(1)
const loading = ref(false)
const hasLoaded = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)

const drawerOpen = ref(false)
const selectedBucket = ref<TaxonomyBucket | null>(null)

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let requestRevision = 0
let searchTimer: ReturnType<typeof setTimeout> | null = null
const showAdvancedFilters = ref(false)

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
  isSuperAdmin.value ? 'super' : 'standard',
  [...authStore.permissions].sort().join(',')
].join('|'))

const statusOptions = computed(() => [
  { value: 'draft' as const, label: statusLabel('draft') },
  { value: 'submitted' as const, label: statusLabel('submitted') },
  { value: 'in_review' as const, label: statusLabel('in_review') },
  { value: 'changes_requested' as const, label: statusLabel('changes_requested') },
  { value: 'approved' as const, label: statusLabel('approved') },
  { value: 'published' as const, label: statusLabel('published') },
  { value: 'rejected' as const, label: statusLabel('rejected') },
  { value: 'hidden' as const, label: statusLabel('hidden') },
  { value: 'archived' as const, label: statusLabel('archived') }
])

const booleanOptions = computed(() => [
  { value: '' as const, label: copy.value.all },
  { value: '1' as const, label: copy.value.yes },
  { value: '0' as const, label: copy.value.no }
])

const heroBreadcrumbs = computed(() => currentLocale.value === 'ar'
  ? ['الإدارة', 'الأعمال', 'التصنيفات والوسوم']
  : ['Admin', 'Works', 'Categories and Tags'])

const policyItems = computed(() => [
  {
    key: 'permissions',
    title: copy.value.permissionsPolicyTitle,
    state: actionBadge.value,
    description: hasAnyAction.value ? copy.value.managementNotice : copy.value.readOnlyNotice,
    icon: '⌁',
    tone: hasAnyAction.value ? 'success' as const : 'neutral' as const
  },
  {
    key: 'category-link',
    title: copy.value.legacyPolicyTitle,
    state: categorySupport.value?.mapping_complete ? copy.value.mappingCompleteShort : copy.value.mappingHasLegacyShort,
    description: categorySupport.value?.mapping_complete ? copy.value.mappingComplete : copy.value.mappingHasLegacy,
    icon: '◇',
    tone: categorySupport.value?.mapping_complete ? 'success' as const : 'warning' as const
  },
  {
    key: 'tag-support',
    title: copy.value.tagsPolicyTitle,
    state: tagSupport.value?.available ? copy.value.tagsAvailableShort : copy.value.supportUnavailable,
    description: tagSupport.value?.available ? copy.value.supportAvailable : (tagSupport.value?.reason || copy.value.tagsUnavailableCopy),
    icon: '#',
    tone: tagSupport.value?.available ? 'info' as const : 'warning' as const
  }
])

const primarySummaryCards = computed(() => {
  const current = summary.value

  return [
    { key: 'catalog_categories_total', label: copy.value.catalogCategories, description: copy.value.catalogCategoriesHint, value: current?.catalog_categories_total ?? 0, tone: 'violet' as const, icon: '◇' },
    { key: 'active_catalog_categories', label: copy.value.categoryActive, description: copy.value.mappingComplete, value: current?.active_catalog_categories ?? 0, tone: 'emerald' as const, icon: '✓' },
    { key: 'categorized_works', label: copy.value.categorizedWorks, description: copy.value.categorizedWorksHint, value: current?.categorized_works ?? 0, tone: 'cyan' as const, icon: '▦' },
    { key: 'uncategorized_works', label: copy.value.uncategorizedWorks, description: copy.value.uncategorizedWorksHint, value: current?.uncategorized_works ?? 0, tone: 'amber' as const, icon: '!' },
    { key: 'reported_categories', label: copy.value.reportedCategories, description: copy.value.reportedCategoriesHint, value: current?.reported_categories ?? 0, tone: 'rose' as const, icon: '⚑' },
    { key: 'tags_total', label: copy.value.catalogTags, description: copy.value.catalogTagsHint, value: current?.tags_total ?? 0, tone: 'indigo' as const, icon: '#' }
  ]
})

const secondarySummaryItems = computed(() => {
  const current = summary.value
  return [
    { key: 'disabled', label: copy.value.categoryDisabled, value: current?.disabled_catalog_categories ?? 0 },
    { key: 'unused', label: copy.value.unusedLabel, value: current?.unused_catalog_categories ?? 0 },
    { key: 'legacy', label: copy.value.legacyCategoryIds, value: current?.legacy_unmapped_category_ids ?? 0 },
    { key: 'assignments', label: copy.value.tagAssignments, value: current?.tag_assignments_total ?? 0 },
    { key: 'views', label: copy.value.totalViews, value: current?.total_views ?? 0 },
    { key: 'likes', label: copy.value.totalLikes, value: current?.total_likes ?? 0 }
  ]
})

const activeAdvancedFiltersCount = computed(() => [
  filters.category_id,
  filters.only_reported,
  filters.only_promoted,
  filters.from,
  filters.to,
  filters.sort !== 'works_count' ? filters.sort : '',
  filters.direction !== 'desc' ? filters.direction : '',
  filters.per_page !== 15 ? String(filters.per_page) : ''
].filter(Boolean).length)

const sortOptions = computed(() => [
  { value: 'works_count' as const, label: copy.value.worksCount },
  { value: 'category_id' as const, label: copy.value.categoryId },
  { value: 'latest_work_at' as const, label: copy.value.latestWorkAt },
  { value: 'reported_count' as const, label: copy.value.reportedCount },
  { value: 'published_count' as const, label: copy.value.publishedCount },
  { value: 'hidden_count' as const, label: copy.value.hiddenCount },
  { value: 'total_reports' as const, label: copy.value.totalReports },
  { value: 'total_views' as const, label: copy.value.totalViews },
  { value: 'total_likes' as const, label: copy.value.totalLikes }
])

function formatNumber(value: number): string {
  return formatYmNumber(Number.isFinite(value) ? value : 0, currentLocale.value)
}

function statusLabel(status: WorkStatus): string {
  const labels: Record<WorkStatus, { ar: string; en: string }> = {
    draft: { ar: 'مسودة', en: 'Draft' },
    submitted: { ar: 'قيد المراجعة', en: 'Submitted' },
    in_review: { ar: 'تحت المراجعة', en: 'In review' },
    changes_requested: { ar: 'تعديلات مطلوبة', en: 'Changes requested' },
    approved: { ar: 'معتمد', en: 'Approved' },
    published: { ar: 'منشور', en: 'Published' },
    rejected: { ar: 'مرفوض', en: 'Rejected' },
    hidden: { ar: 'مخفي', en: 'Hidden' },
    archived: { ar: 'مؤرشف', en: 'Archived' }
  }

  return labels[status][currentLocale.value]
}

function errorStatus(requestError: unknown): number | null {
  if (!requestError || typeof requestError !== 'object') return null

  if (
    'response' in requestError
    && typeof (requestError as { response?: { status?: unknown } }).response?.status === 'number'
  ) {
    return (requestError as { response: { status: number } }).response.status
  }

  if (
    'statusCode' in requestError
    && typeof (requestError as { statusCode?: unknown }).statusCode === 'number'
  ) {
    return (requestError as { statusCode: number }).statusCode
  }

  if (
    'status' in requestError
    && typeof (requestError as { status?: unknown }).status === 'number'
  ) {
    return (requestError as { status: number }).status
  }

  return null
}

function requestErrorMessage(requestError: unknown): string | null {
  if (!requestError || typeof requestError !== 'object') return null
  const candidate = requestError as { data?: unknown; response?: { _data?: unknown } }
  const data = candidate.data ?? candidate.response?._data
  if (!data || typeof data !== 'object') return null
  const message = (data as { message?: unknown }).message
  return typeof message === 'string' ? message : null
}

function validateFilters(): boolean {
  filterError.value = null
  const categoryId = filters.category_id.trim()

  if (categoryId !== '' && !Number.isInteger(Number(categoryId))) {
    filterError.value = copy.value.invalidCategoryId
    return false
  }

  if (filters.from && filters.to && filters.to < filters.from) {
    filterError.value = copy.value.invalidDateRange
    return false
  }

  return true
}

function buildListQuery(): Record<string, string | number> {
  const query: Record<string, string | number> = {
    sort: appliedFilters.sort,
    direction: appliedFilters.direction,
    page: page.value,
    per_page: appliedFilters.per_page
  }

  const optionalFilters: Array<[string, string]> = [
    ['q', appliedFilters.q.trim()],
    ['category_id', appliedFilters.category_id.trim()],
    ['status', appliedFilters.status],
    ['visibility_status', appliedFilters.visibility_status],
    ['media_type', appliedFilters.media_type.trim()],
    ['only_uncategorized', appliedFilters.only_uncategorized],
    ['only_reported', appliedFilters.only_reported],
    ['only_promoted', appliedFilters.only_promoted],
    ['from', appliedFilters.from],
    ['to', appliedFilters.to]
  ]

  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }

  return query
}

async function fetchTaxonomy(): Promise<void> {
  if (!authStore.isInitialized || !hasTaxonomyAccess.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++requestRevision
  loading.value = true
  error.value = null
  filterError.value = null

  try {
    const response = await apiFetch<TaxonomyResponse>('/admin/works/taxonomy', {
      query: buildListQuery()
    })

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasTaxonomyAccess.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      clearTaxonomyData()
      error.value = copy.value.genericError
      return
    }

    items.value = response.data.items
    Object.assign(pagination, response.data.pagination)
    summary.value = response.data.summary
    categorySupport.value = response.data.category_support
    tagSupport.value = response.data.tag_support
    page.value = response.data.pagination.current_page
    hasLoaded.value = true
    serverForbidden.value = false
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== requestRevision
      || !hasTaxonomyAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      clearTaxonomyData()
      closeSummary()
      return
    }

    if (status === 422) {
      filterError.value = requestErrorMessage(requestError) || copy.value.validationError
      return
    }

    error.value = requestErrorMessage(requestError) || copy.value.genericError
  } finally {
    if (
      requestAccessRevision === accessRevision
      && currentRequestRevision === requestRevision
    ) {
      loading.value = false
    }
  }
}

function applyFilters(): void {
  if (!validateFilters()) return

  Object.assign(appliedFilters, filters)
  if (['total_views', 'total_likes', 'total_reports'].includes(appliedFilters.sort)) {
    engagementMetric.value = appliedFilters.sort as 'total_views' | 'total_likes' | 'total_reports'
  }
  page.value = 1
  closeSummary()
  void fetchTaxonomy()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  engagementMetric.value = 'total_views'
  page.value = 1
  filterError.value = null
  closeSummary()
  void fetchTaxonomy()
}

function changeSort(key: TaxonomySortKey): void {
  if (appliedFilters.sort === key) {
    appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'
  } else {
    appliedFilters.sort = key
    appliedFilters.direction = key === 'category_id' ? 'asc' : 'desc'
  }

  filters.sort = appliedFilters.sort
  filters.direction = appliedFilters.direction
  page.value = 1
  closeSummary()
  void fetchTaxonomy()
}

function changeEngagementMetric(metric: 'total_views' | 'total_likes' | 'total_reports'): void {
  engagementMetric.value = metric
  filters.sort = metric
  appliedFilters.sort = metric
  page.value = 1
  closeSummary()
  void fetchTaxonomy()
}

function changePage(nextPage: number): void {
  if (
    nextPage < 1
    || nextPage > pagination.last_page
    || nextPage === pagination.current_page
    || loading.value
  ) {
    return
  }

  page.value = nextPage
  closeSummary()
  void fetchTaxonomy()
}

function openSummary(bucket: TaxonomyBucket): void {
  document.dispatchEvent(new CustomEvent('ym:works-index-overlays-close'))
  selectedBucket.value = bucket
  drawerOpen.value = true
}

function refreshOverviewAfterAction(): void {
  if (hasTaxonomyAccess.value) void fetchTaxonomy()
}

function handleCatalogAuthorizationError(): void {
  activeTab.value = 'overview'
  closeSummary()
  if (authStore.isAuthenticated) void authStore.fetchUser()
}

function closeSummary(): void {
  drawerOpen.value = false
}

function clearTaxonomyData(): void {
  items.value = []
  summary.value = null
  categorySupport.value = null
  tagSupport.value = null
  Object.assign(pagination, {
    current_page: 1,
    per_page: appliedFilters.per_page,
    total: 0,
    last_page: 1
  })
  page.value = 1
  hasLoaded.value = false
}

function clearPageState(): void {
  requestRevision += 1
  clearTaxonomyData()
  loading.value = false
  error.value = null
  filterError.value = null
  closeSummary()
}

function syncTaxonomyAccessState(): void {
  if (!pageMounted) return

  accessRevision += 1
  serverForbidden.value = false
  closeSummary()

  if (!authStore.isInitialized) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (!hasTaxonomyAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchTaxonomy()
}

watch(
  authorizationSignature,
  () => {
    if (activeTab.value === 'categories' && !canViewCategories.value) activeTab.value = 'overview'
    if (activeTab.value === 'tags' && !canViewTags.value) activeTab.value = 'overview'
    closeSummary()
    syncTaxonomyAccessState()
  },
  { flush: 'post' }
)

watch(activeTab, () => closeSummary())
watch(
  () => filters.q,
  (query) => {
    if (searchTimer) clearTimeout(searchTimer)
    const normalized = query.trim()
    if (normalized === appliedFilters.q) return
    searchTimer = setTimeout(() => {
      appliedFilters.q = normalized
      page.value = 1
      closeSummary()
      void fetchTaxonomy()
    }, 325)
  }
)

onMounted(() => {
  pageMounted = true
  syncTaxonomyAccessState()
})

onBeforeUnmount(() => {
  if (searchTimer) clearTimeout(searchTimer)
  requestRevision += 1
})
</script>

<style scoped>
.ym-taxonomy-page {
  position: relative;
  isolation: isolate;
  color: var(--ym-text);
}

.ym-taxonomy-page::before {
  position: fixed;
  z-index: 10;
  inset-block-start: 0;
  inset-inline: 0;
  height: var(--ym-admin-topbar-height, 72px);
  pointer-events: none;
  background: var(--ym-page-bg, var(--ym-dropdown-bg));
  content: '';
}

.ym-taxonomy-tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  border: 1px solid var(--ym-card-border);
  border-radius: 20px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 0.55rem;
}

.ym-taxonomy-tabs button {
  min-height: 42px;
  border: 1px solid transparent;
  border-radius: 14px;
  background: transparent;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
  padding: 0.65rem 1rem;
}

.ym-taxonomy-tabs button.is-active {
  border-color: rgba(139, 92, 246, 0.35);
  background: rgba(139, 92, 246, 0.12);
  color: var(--ym-text);
}

.ym-taxonomy-tabs button:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.18);
}

.ym-taxonomy-tab-panel {
  display: grid;
  gap: 1.75rem;
}

.ym-taxonomy-support-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.8rem;
}

.ym-taxonomy-support-grid section {
  display: grid;
  gap: 0.35rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: var(--ym-card-bg);
  padding: 0.9rem;
}

.ym-taxonomy-support-grid span,
.ym-taxonomy-support-grid code {
  color: var(--ym-muted);
  font-size: 11px;
}

.ym-taxonomy-hero,
.ym-taxonomy-filter-card,
.ym-taxonomy-table-card,
.ym-taxonomy-access-state {
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
}

.ym-taxonomy-hero {
  position: relative;
  min-height: 270px;
  overflow: hidden;
  background:
    linear-gradient(135deg, rgba(49, 46, 129, 0.96), rgba(15, 23, 42, 0.96) 52%, rgba(6, 78, 59, 0.92)),
    var(--ym-card-bg);
  color: #fff;
  padding: clamp(1.35rem, 4vw, 2.35rem);
}

.ym-taxonomy-hero__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 32px 32px;
  mask-image: linear-gradient(to bottom, #000, transparent 92%);
  opacity: 0.42;
}

.ym-taxonomy-hero__glow {
  position: absolute;
  width: 230px;
  height: 230px;
  border-radius: 999px;
  filter: blur(14px);
  opacity: 0.34;
}

.ym-taxonomy-hero__glow.is-one {
  inset-block-start: -95px;
  inset-inline-end: 8%;
  background: #8b5cf6;
}

.ym-taxonomy-hero__glow.is-two {
  inset-block-end: -135px;
  inset-inline-start: 12%;
  background: #10b981;
}

.ym-taxonomy-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 200px;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
}

.ym-taxonomy-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.ym-taxonomy-chip {
  display: inline-flex;
  align-items: center;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.82);
  font-size: 11px;
  font-weight: 950;
  padding: 0.4rem 0.72rem;
}

.ym-taxonomy-chip.is-brand {
  border-color: rgba(167, 139, 250, 0.42);
  color: #c4b5fd;
}

.ym-taxonomy-chip.is-readonly {
  border-color: rgba(52, 211, 153, 0.36);
  color: #6ee7b7;
}

.ym-taxonomy-kicker {
  color: #a7f3d0;
  font-size: 12px;
  font-weight: 950;
  letter-spacing: 0.04em;
  margin: 1.1rem 0 0.4rem;
}

.ym-taxonomy-hero h1 {
  max-width: 760px;
  color: #fff;
  font-size: clamp(2.25rem, 5vw, 4rem);
  font-weight: 950;
  letter-spacing: -0.04em;
  line-height: 1.1;
  margin: 0;
}

.ym-taxonomy-description {
  max-width: 760px;
  color: rgba(255, 255, 255, 0.74);
  font-size: 14px;
  font-weight: 750;
  line-height: 1.85;
  margin: 0.9rem 0 0;
}

.ym-taxonomy-description code {
  border-radius: 7px;
  background: rgba(255, 255, 255, 0.1);
  color: #a7f3d0;
  font-size: 0.86em;
  padding: 0.16rem 0.35rem;
}

.ym-taxonomy-hero__summary {
  display: grid;
  flex: 0 0 auto;
  min-width: 190px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 24px;
  background: rgba(15, 23, 42, 0.42);
  backdrop-filter: blur(14px);
  padding: 1rem 1.15rem;
}

.ym-taxonomy-hero__summary span,
.ym-taxonomy-hero__summary small {
  color: rgba(255, 255, 255, 0.68);
  font-size: 11px;
  font-weight: 850;
}

.ym-taxonomy-hero__summary strong {
  color: #fff;
  font-size: 2rem;
  font-weight: 950;
  margin: 0.25rem 0;
}

.ym-taxonomy-notice,
.ym-taxonomy-tag-support {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  border-radius: 22px;
  padding: 1rem 1.15rem;
}

.ym-taxonomy-notice {
  border: 1px solid rgba(245, 158, 11, 0.28);
  background: color-mix(in srgb, #f59e0b 8%, var(--ym-control-bg));
}

.ym-taxonomy-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.14);
  color: #fbbf24;
  font-size: 11px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-taxonomy-notice strong,
.ym-taxonomy-tag-support strong {
  display: block;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-taxonomy-notice p,
.ym-taxonomy-tag-support p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.2rem 0 0;
}

.ym-taxonomy-tag-support {
  border: 1px solid rgba(56, 189, 248, 0.3);
  background: color-mix(in srgb, #38bdf8 8%, var(--ym-control-bg));
}

.ym-taxonomy-tag-support__icon {
  display: grid;
  flex: 0 0 auto;
  width: 2.1rem;
  height: 2.1rem;
  place-items: center;
  border-radius: 999px;
  background: rgba(56, 189, 248, 0.16);
  color: #38bdf8;
  font-size: 13px;
  font-weight: 950;
}

.ym-taxonomy-tag-support small {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 750;
  margin-top: 0.35rem;
}

.ym-taxonomy-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.ym-taxonomy-summary-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--taxonomy-accent) 17%, transparent), transparent 52%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1rem;
}

.ym-taxonomy-summary-card::after {
  position: absolute;
  inset-block: 0;
  inset-inline-start: 0;
  width: 3px;
  background: var(--taxonomy-accent);
  content: '';
  opacity: 0.85;
}

.ym-taxonomy-summary-card.is-alert {
  border-color: rgba(244, 63, 94, 0.34);
}

.ym-taxonomy-summary-card.is-promoted {
  border-color: rgba(217, 70, 239, 0.34);
}

.ym-taxonomy-summary-card span,
.ym-taxonomy-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-taxonomy-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-taxonomy-filter-card,
.ym-taxonomy-table-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-taxonomy-filter-card > header,
.ym-taxonomy-table-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-taxonomy-filter-card h2,
.ym-taxonomy-table-card h2,
.ym-taxonomy-access-state h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-taxonomy-filter-card header p,
.ym-taxonomy-table-card__head p,
.ym-taxonomy-access-state p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-taxonomy-filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-taxonomy-filter-grid label {
  display: grid;
  align-content: start;
  gap: 0.42rem;
}

.ym-taxonomy-filter-grid label.is-search {
  grid-column: span 2;
}

.ym-taxonomy-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-taxonomy-filter-grid label > small {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 750;
}

.ym-taxonomy-filter-grid input,
.ym-taxonomy-filter-grid select {
  width: 100%;
  min-height: 45px;
  border: 1px solid var(--ym-control-border);
  border-radius: 14px;
  outline: none;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 800;
  padding: 0.7rem 0.8rem;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.ym-taxonomy-filter-grid input:focus,
.ym-taxonomy-filter-grid select:focus {
  border-color: #8b5cf6;
  box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.14);
}

.ym-taxonomy-filter-grid select option {
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
}

.ym-taxonomy-filter-actions {
  display: flex;
  align-items: flex-end;
}

.ym-taxonomy-button {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  border: 1px solid transparent;
  border-radius: 14px;
  font-size: 13px;
  font-weight: 950;
  padding: 0.7rem 1rem;
  transition: transform 160ms ease, border-color 160ms ease, opacity 160ms ease;
}

.ym-taxonomy-button.is-primary {
  min-width: 130px;
  background: linear-gradient(135deg, #7c3aed, #059669);
  color: #fff;
  box-shadow: 0 12px 28px rgba(124, 58, 237, 0.2);
}

.ym-taxonomy-button.is-secondary {
  border-color: var(--ym-control-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-taxonomy-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-taxonomy-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-taxonomy-filter-error {
  border: 1px solid rgba(244, 63, 94, 0.34);
  border-radius: 15px;
  background: rgba(244, 63, 94, 0.1);
  color: #fb7185;
  font-size: 12px;
  font-weight: 850;
  margin: 1rem 0 0;
  padding: 0.75rem 0.85rem;
}

.ym-taxonomy-table-card__head {
  align-items: center;
}

.ym-taxonomy-table-state {
  display: grid;
  min-width: 130px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: var(--ym-control-bg);
  padding: 0.65rem 0.8rem;
}

.ym-taxonomy-table-state span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-taxonomy-table-state strong {
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 950;
}

.ym-taxonomy-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 20px;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
}

.ym-taxonomy-table {
  width: 100%;
  min-width: 2750px;
  border-collapse: collapse;
  background: color-mix(in srgb, var(--ym-card-bg) 88%, transparent);
}

.ym-taxonomy-table th,
.ym-taxonomy-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-muted);
  font-size: 12px;
  padding: 0.86rem 0.75rem;
  text-align: start;
  vertical-align: middle;
}

.ym-taxonomy-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
  font-weight: 950;
  white-space: nowrap;
}

.ym-taxonomy-table tbody tr {
  transition: background 150ms ease;
}

.ym-taxonomy-table tbody tr.is-uncategorized-row {
  background: color-mix(in srgb, #f97316 5%, transparent);
}

.ym-taxonomy-table tbody tr.needs-attention-row {
  box-shadow: inset 3px 0 0 rgba(245, 158, 11, 0.72);
}

.ym-taxonomy-table tbody tr:hover {
  background: var(--ym-row-hover);
}

.ym-taxonomy-table tbody tr:last-child td {
  border-bottom: 0;
}

.ym-taxonomy-table th.is-label,
.ym-taxonomy-table td.is-label {
  width: 230px;
  min-width: 230px;
}

.ym-taxonomy-table td.is-label strong,
.ym-taxonomy-table td.is-label small {
  display: block;
}

.ym-taxonomy-table td.is-label strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-taxonomy-table td.is-label small {
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.55;
  margin-top: 0.35rem;
}

.ym-taxonomy-table code {
  color: #a78bfa;
  font-size: 11px;
  font-weight: 900;
}

.ym-taxonomy-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  padding: 0;
}

.ym-taxonomy-sort span {
  display: inline-grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  border-radius: 7px;
  background: rgba(139, 92, 246, 0.13);
  color: #a78bfa;
}

.ym-taxonomy-flag {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 950;
  padding: 0.34rem 0.58rem;
  white-space: nowrap;
}

.ym-taxonomy-flag.is-uncategorized {
  border-color: rgba(249, 115, 22, 0.42);
  background: rgba(249, 115, 22, 0.14);
  color: #fb923c;
}

.ym-taxonomy-flag.is-reported {
  border-color: rgba(244, 63, 94, 0.4);
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
}

.ym-taxonomy-flag.is-published {
  border-color: rgba(16, 185, 129, 0.38);
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
}

.ym-taxonomy-flag.is-hidden {
  border-color: rgba(148, 163, 184, 0.38);
  background: rgba(100, 116, 139, 0.14);
  color: #cbd5e1;
}

.ym-taxonomy-flag.is-promoted {
  border-color: rgba(217, 70, 239, 0.4);
  background: rgba(217, 70, 239, 0.13);
  color: #e879f9;
}

.ym-taxonomy-flag.is-attention {
  border-color: rgba(245, 158, 11, 0.44);
  background: rgba(245, 158, 11, 0.14);
  color: #fbbf24;
}

.ym-taxonomy-flag.is-neutral {
  color: #94a3b8;
}

.ym-taxonomy-count {
  display: inline-grid;
  min-width: 2.2rem;
  min-height: 2rem;
  place-items: center;
  border-radius: 10px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-weight: 950;
  padding: 0.2rem 0.45rem;
}

.ym-taxonomy-count.is-strong {
  background: rgba(139, 92, 246, 0.13);
  color: #a78bfa;
}

.ym-taxonomy-count.is-alert:not(:empty) {
  color: #fb7185;
}

.ym-taxonomy-table time {
  display: inline-block;
  min-width: 125px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.5;
}

.ym-taxonomy-table th.is-action,
.ym-taxonomy-table td.is-action {
  position: sticky;
  inset-inline-end: 0;
  z-index: 1;
  min-width: 155px;
  background: var(--ym-dropdown-bg);
}

.ym-taxonomy-table th.is-action {
  z-index: 3;
}

.ym-taxonomy-details-button {
  width: 100%;
  min-height: 38px;
  border: 1px solid rgba(139, 92, 246, 0.42);
  border-radius: 12px;
  background: rgba(139, 92, 246, 0.13);
  color: #c4b5fd;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
  transition: background 160ms ease, transform 160ms ease;
}

.ym-taxonomy-details-button:hover {
  background: rgba(139, 92, 246, 0.22);
  transform: translateY(-1px);
}

.ym-taxonomy-state,
.ym-taxonomy-access-state {
  display: grid;
  min-height: 240px;
  place-items: center;
  align-content: center;
  gap: 0.7rem;
  color: var(--ym-muted);
  padding: 2rem;
  text-align: center;
}

.ym-taxonomy-state h3 {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
  margin: 0;
}

.ym-taxonomy-state p {
  max-width: 34rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-taxonomy-state.is-error,
.ym-taxonomy-access-state.is-forbidden {
  color: #fb7185;
}

.ym-taxonomy-state__icon,
.ym-taxonomy-empty-icon {
  display: grid;
  width: 3rem;
  height: 3rem;
  place-items: center;
  border-radius: 999px;
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
  font-size: 1.1rem;
  font-weight: 950;
}

.ym-taxonomy-empty-icon {
  background: rgba(148, 163, 184, 0.13);
  color: var(--ym-muted);
}

.ym-taxonomy-spinner {
  width: 2.35rem;
  height: 2.35rem;
  border: 3px solid rgba(139, 92, 246, 0.2);
  border-top-color: #a78bfa;
  border-radius: 999px;
  animation: ym-taxonomy-spin 760ms linear infinite;
}

.ym-taxonomy-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.ym-taxonomy-pagination > div {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-taxonomy-pagination > div strong {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
}

.ym-taxonomy-pagination nav {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ym-taxonomy-pagination nav span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-taxonomy-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  justify-content: flex-end;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(6px);
}

.ym-taxonomy-detail-drawer {
  width: min(660px, 100%);
  height: 100dvh;
  overflow-y: auto;
  border-inline-start: 1px solid var(--ym-card-border);
  outline: none;
  background: var(--ym-dropdown-bg);
  box-shadow: -24px 0 64px rgba(2, 6, 23, 0.38);
  color: var(--ym-text);
}

.ym-taxonomy-detail-drawer__head {
  position: sticky;
  top: 0;
  z-index: 4;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid var(--ym-soft-border);
  background: color-mix(in srgb, var(--ym-dropdown-bg) 92%, transparent);
  backdrop-filter: blur(18px);
  padding: 1.2rem 1.35rem;
}

.ym-taxonomy-detail-drawer__head span,
.ym-taxonomy-detail-drawer__head code,
.ym-taxonomy-detail-drawer__head small {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-taxonomy-detail-drawer__head h2 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.35;
  margin: 0.2rem 0;
}

.ym-taxonomy-detail-drawer__close {
  display: grid;
  flex: 0 0 auto;
  width: 42px;
  height: 42px;
  place-items: center;
  border: 1px solid var(--ym-control-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 1.45rem;
  line-height: 1;
}

.ym-taxonomy-detail-content {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
}

.ym-taxonomy-detail-intro,
.ym-taxonomy-detail-section {
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-card-bg);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
  padding: 1rem;
}

.ym-taxonomy-detail-intro.is-attention {
  border-color: rgba(245, 158, 11, 0.38);
}

.ym-taxonomy-detail-intro > div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.ym-taxonomy-detail-intro h3 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.45;
  margin: 0.8rem 0 0.25rem;
}

.ym-taxonomy-detail-intro p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.8;
  margin: 0.65rem 0 0;
}

.ym-taxonomy-detail-section > header {
  margin-bottom: 0.8rem;
}

.ym-taxonomy-detail-section > header h3 {
  color: var(--ym-text);
  font-size: 1rem;
  font-weight: 950;
  margin: 0;
}

.ym-taxonomy-detail-section > header p {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 750;
  line-height: 1.65;
  margin: 0.25rem 0 0;
}

.ym-taxonomy-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
}

.ym-taxonomy-detail-grid.is-counts {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ym-taxonomy-detail-grid > div {
  min-width: 0;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  padding: 0.7rem;
}

.ym-taxonomy-detail-grid dt {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-taxonomy-detail-grid dd {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  line-height: 1.65;
  margin: 0.3rem 0 0;
  overflow-wrap: anywhere;
}

.ym-taxonomy-detail-flags {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-taxonomy-detail-flags > span {
  display: grid;
  gap: 0.22rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
  padding: 0.7rem;
}

.ym-taxonomy-detail-flags strong {
  color: #94a3b8;
  font-size: 12px;
  font-weight: 950;
}

.ym-taxonomy-detail-flags > span.is-active strong {
  color: #fbbf24;
}

@keyframes ym-taxonomy-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1280px) {
  .ym-taxonomy-summary-grid,
  .ym-taxonomy-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .ym-taxonomy-hero__content,
  .ym-taxonomy-filter-card > header,
  .ym-taxonomy-table-card__head,
  .ym-taxonomy-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-taxonomy-hero__summary {
    min-width: 0;
  }

  .ym-taxonomy-summary-grid,
  .ym-taxonomy-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-taxonomy-pagination nav {
    justify-content: space-between;
  }
}

@media (max-width: 640px) {
  .ym-taxonomy-page {
    font-size: 14px;
  }

  .ym-taxonomy-hero,
  .ym-taxonomy-filter-card,
  .ym-taxonomy-table-card,
  .ym-taxonomy-access-state {
    border-radius: 22px;
  }

  .ym-taxonomy-hero h1 {
    font-size: 2rem;
  }

  .ym-taxonomy-notice,
  .ym-taxonomy-tag-support {
    flex-direction: column;
  }

  .ym-taxonomy-summary-grid,
  .ym-taxonomy-support-grid,
  .ym-taxonomy-filter-grid,
  .ym-taxonomy-detail-grid,
  .ym-taxonomy-detail-grid.is-counts,
  .ym-taxonomy-detail-flags {
    grid-template-columns: 1fr;
  }

  .ym-taxonomy-filter-grid label.is-search {
    grid-column: auto;
  }

  .ym-taxonomy-filter-actions,
  .ym-taxonomy-filter-actions .ym-taxonomy-button {
    width: 100%;
  }

  .ym-taxonomy-pagination nav {
    display: grid;
    grid-template-columns: 1fr;
    text-align: center;
  }

  .ym-taxonomy-detail-drawer__head,
  .ym-taxonomy-detail-content {
    padding-inline: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-taxonomy-spinner {
    animation-duration: 1.8s;
  }

  .ym-taxonomy-button,
  .ym-taxonomy-details-button,
  .ym-taxonomy-table tbody tr {
    transition: none;
  }
}
.ym-taxonomy-page {
  --ym-admin-section-accent: #0f766e;
  --ym-admin-section-accent-strong: #115e59;
  --ym-admin-section-accent-secondary: #0891b2;
  --ym-admin-section-accent-soft: color-mix(in srgb, #0f766e 10%, transparent);
  display: grid;
  gap: 10px;
}

.ym-taxonomy-tabs {
  min-height: 44px;
  gap: 5px;
  padding: 5px;
  border-radius: 15px;
}

.ym-taxonomy-tabs button {
  min-height: 34px;
  padding: 6px 15px;
  font-size: 13px;
}

.ym-taxonomy-tab-panel {
  display: grid;
  gap: 10px;
}

.ym-taxonomy-secondary-summary {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  gap: 7px;
}

.ym-taxonomy-secondary-summary > span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  min-height: 34px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  padding: 5px 11px;
  color: var(--ym-muted);
  background: color-mix(in srgb, var(--ym-card-bg) 92%, transparent);
  font-size: 12px;
  font-weight: 850;
}

.ym-taxonomy-secondary-summary strong {
  color: var(--ym-text);
  font-size: 13px;
  font-variant-numeric: tabular-nums;
}

.ym-taxonomy-filter-card {
  padding: 7px 9px;
  border-radius: 18px;
}

.ym-taxonomy-filter-card > .ym-taxonomy-filter-grid {
  grid-template-columns: minmax(280px, 2.2fr) repeat(4, minmax(118px, 1fr));
  align-items: end;
  gap: 7px;
}

.ym-taxonomy-filter-card > .ym-taxonomy-filter-grid > .is-search {
  grid-column: auto;
}

.ym-taxonomy-filter-grid label {
  gap: 4px;
}

.ym-taxonomy-filter-grid input,
.ym-taxonomy-filter-grid select {
  min-height: 38px;
  border-radius: 10px;
  padding-block: 7px;
}

.ym-taxonomy-filter-toolbar {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 7px;
  min-height: 38px;
}

.ym-taxonomy-advanced-toggle {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  gap: 7px;
  border: 1px solid var(--ym-control-border);
  border-radius: 11px;
  padding: 7px 11px;
  color: var(--ym-text);
  background: var(--ym-control-bg);
  font-weight: 900;
}

.ym-taxonomy-advanced-toggle.is-open {
  border-color: color-mix(in srgb, #0891b2 55%, var(--ym-control-border));
}

.ym-taxonomy-advanced-toggle b {
  display: grid;
  min-width: 20px;
  height: 20px;
  place-items: center;
  border-radius: 999px;
  color: #fff;
  background: #0f766e;
  font-size: 11px;
}

.ym-taxonomy-filter-actions {
  display: flex;
  gap: 7px;
}

.ym-taxonomy-button {
  min-height: 38px;
  border-radius: 10px;
}

.ym-taxonomy-button.is-primary {
  border-color: #0f766e;
  color: #fff;
  background: #0f766e;
  box-shadow: none;
}

.ym-taxonomy-button.is-primary:hover {
  border-color: #115e59;
  background: #115e59;
}

.ym-taxonomy-button.is-primary:focus-visible {
  box-shadow: 0 0 0 3px color-mix(in srgb, #0f766e 24%, transparent);
}

.ym-taxonomy-filter-grid.is-advanced {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(8, minmax(105px, 1fr));
  gap: 8px;
  border-top: 1px solid var(--ym-soft-border);
  padding-top: 9px;
}

.ym-taxonomy-table-card {
  border-radius: 18px;
  padding: 0;
}

.ym-taxonomy-table-card__head {
  min-height: 40px;
  align-items: center;
  margin: 0;
  padding: 5px 11px;
}

.ym-taxonomy-table-card__head h2 {
  font-size: 1rem;
}

.ym-taxonomy-table-card__head p {
  display: none;
}

.ym-taxonomy-table-wrap {
  position: relative;
  isolation: isolate;
  overflow-x: auto;
}

.ym-taxonomy-updating {
  position: absolute;
  z-index: 2;
  inset-block-start: 7px;
  inset-inline-end: 9px;
  border-radius: 999px;
  padding: 4px 8px;
  color: #fff;
  background: #0f766e;
  font-size: 11px;
  font-weight: 900;
}

.ym-taxonomy-table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
}

.ym-taxonomy-table th:nth-child(1) { width: 18%; }
.ym-taxonomy-table th:nth-child(2) { width: 14%; }
.ym-taxonomy-table th:nth-child(3) { width: 13%; }
.ym-taxonomy-table th:nth-child(4) { width: 14%; }
.ym-taxonomy-table th:nth-child(5) { width: 10%; }
.ym-taxonomy-table th:nth-child(6) { width: 12%; }
.ym-taxonomy-table th:nth-child(7) { width: 13%; }
.ym-taxonomy-table th:nth-child(8) { width: 6%; }

.ym-taxonomy-table th,
.ym-taxonomy-table td {
  padding: 10px 9px;
  white-space: normal;
  vertical-align: middle;
}

.ym-taxonomy-table thead {
  position: static;
}

.ym-taxonomy-table th {
  position: static;
  top: auto;
  z-index: auto;
  color: color-mix(in srgb, var(--ym-muted) 70%, var(--ym-text) 30%);
  background: color-mix(in srgb, var(--ym-control-bg) 92%, var(--ym-card-bg));
  font-size: 12.5px;
  text-align: center;
}

.ym-taxonomy-table th.is-action,
.ym-taxonomy-table td.is-action {
  position: static;
  inset-inline-end: auto;
  z-index: auto;
  min-width: 0;
  background: inherit;
}

.ym-taxonomy-table time {
  min-width: 0;
  color: color-mix(in srgb, var(--ym-muted) 66%, var(--ym-text) 34%);
  font-size: 12.5px;
  font-weight: 900;
  line-height: 1.45;
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}

.ym-taxonomy-table tbody tr {
  height: 98px;
}

.ym-taxonomy-table td.is-label small {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.ym-taxonomy-stat-stack,
.ym-taxonomy-compact-flags {
  display: flex;
  min-width: 0;
  flex-wrap: wrap;
  gap: 4px;
}

.ym-taxonomy-stat-stack > span {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 27px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 9px;
  padding: 4px 6px;
  color: color-mix(in srgb, var(--ym-muted) 78%, var(--ym-text) 22%);
  background: color-mix(in srgb, var(--ym-control-bg) 88%, transparent);
  font-size: 11.5px;
  font-weight: 800;
  line-height: 1.25;
  white-space: nowrap;
}

.ym-taxonomy-stat-stack > span.is-total {
  border-color: color-mix(in srgb, #0891b2 32%, var(--ym-soft-border));
}

.ym-taxonomy-stat-stack > span.is-alert {
  border-color: color-mix(in srgb, #e11d48 38%, var(--ym-soft-border));
  color: color-mix(in srgb, #e11d48 74%, var(--ym-text));
}

.ym-taxonomy-stat-stack strong {
  color: var(--ym-text);
  font-size: 12.5px;
  font-weight: 950;
  font-variant-numeric: tabular-nums;
}

.ym-taxonomy-compact-flags {
  align-items: center;
  align-content: center;
}

.ym-taxonomy-compact-flags small {
  color: var(--ym-muted);
  font-size: 11.5px;
  font-weight: 800;
}

.ym-taxonomy-flag {
  min-height: 25px;
  align-items: center;
  padding: 3px 7px;
  font-size: 11px;
  font-weight: 900;
  line-height: 1.2;
  white-space: nowrap;
}

.ym-taxonomy-compact-flags .ym-taxonomy-flag:not(.is-attention):not(.is-uncategorized) {
  border-color: color-mix(in srgb, var(--ym-soft-border) 82%, #64748b);
  box-shadow: none;
}

.ym-taxonomy-flag.is-attention,
.ym-taxonomy-stat-stack > span.is-alert {
  font-weight: 950;
}

.ym-taxonomy-details-button {
  display: grid;
  width: 40px;
  min-width: 40px;
  height: 40px;
  place-items: center;
  padding: 0;
  border-color: color-mix(in srgb, #0891b2 48%, var(--ym-control-border));
  color: #ecfeff;
  background: #0e7490;
  font-size: 17px;
}

.ym-taxonomy-details-button:hover,
.ym-taxonomy-details-button:focus-visible {
  border-color: #67e8f9;
  background: #155e75;
  box-shadow: 0 0 0 3px color-mix(in srgb, #22d3ee 22%, transparent);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
}

.ym-taxonomy-detail-drawer {
  width: min(720px, 100%);
}

@media (max-width: 1180px) {
  .ym-taxonomy-filter-card > .ym-taxonomy-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .ym-taxonomy-filter-card > .ym-taxonomy-filter-grid > .is-search {
    grid-column: span 2;
  }

  .ym-taxonomy-filter-grid.is-advanced {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 760px) {
  .ym-taxonomy-page {
    gap: 8px;
  }

  .ym-taxonomy-tabs {
    overflow-x: auto;
    flex-wrap: nowrap;
  }

  .ym-taxonomy-tabs button {
    flex: 1 0 auto;
  }

  .ym-taxonomy-filter-card > .ym-taxonomy-filter-grid,
  .ym-taxonomy-filter-grid.is-advanced {
    grid-template-columns: 1fr;
  }

  .ym-taxonomy-filter-card > .ym-taxonomy-filter-grid > .is-search {
    grid-column: auto;
  }

  .ym-taxonomy-filter-toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-taxonomy-filter-actions > button,
  .ym-taxonomy-advanced-toggle {
    flex: 1;
  }

  .ym-taxonomy-table {
    min-width: 0;
  }

  .ym-taxonomy-table thead {
    display: none;
  }

  .ym-taxonomy-table,
  .ym-taxonomy-table tbody,
  .ym-taxonomy-table tr,
  .ym-taxonomy-table td {
    display: block;
    width: 100%;
  }

  .ym-taxonomy-table tbody {
    display: grid;
    gap: 9px;
    padding: 9px;
  }

  .ym-taxonomy-table tbody tr {
    height: auto;
    overflow: hidden;
    border: 1px solid var(--ym-soft-border);
    border-radius: 15px;
    background: color-mix(in srgb, var(--ym-card-bg) 96%, transparent);
  }

  .ym-taxonomy-table td {
    display: grid;
    grid-template-columns: minmax(105px, .7fr) minmax(0, 1.3fr);
    gap: 10px;
    border-bottom: 1px solid var(--ym-soft-border);
    padding: 8px 10px;
  }

  .ym-taxonomy-table td::before {
    content: attr(data-label);
    color: var(--ym-muted);
    font-size: 11px;
    font-weight: 900;
  }

  .ym-taxonomy-table td.is-label::before {
    content: attr(data-label);
  }

  .ym-taxonomy-table td.is-action {
    display: flex;
    justify-content: flex-end;
  }
}
</style>
