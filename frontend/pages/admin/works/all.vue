<template>
  <div class="ym-works-all-page space-y-7" :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'">
    <WorksIndexHeaderSummary
      :locale="currentLocale"
      :can-create="canCreateWork"
      :summary="summary"
      :loading="(authPending || loading) && summary === null"
      :updating="loading && summary !== null"
      :warning="summaryWarning"
    />

    <section v-if="authPending" class="ym-works-all-access-state" role="status" aria-live="polite">
      <span class="ym-works-all-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-all-access-state is-forbidden" role="status">
      <span class="ym-works-all-access-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <WorksIndexFilters
        :locale="currentLocale"
        :model-value="filters"
        :applied="appliedFilters"
        :loading="loading"
        :error="filterError"
        @apply="applyCompactFilters"
        @reset="resetFilters"
        @remove="removeCompactFilter"
      />

      <p v-if="selectionMessage" class="ym-works-all-selection-message" role="status">{{ selectionMessage }}</p>
      <p v-if="bulkRefreshWarning" class="ym-works-all-selection-message is-warning" role="alert">
        {{ bulkRefreshWarning }} <button type="button" @click="retryBulkRefresh">{{ copy.retry }}</button>
      </p>

      <WorksTaxonomyBulkSelectionBar
        v-if="canManageBulkTaxonomy"
        :selected-count="selectedCount"
        :current-page-selected-count="currentPageSelectedCount"
        :max-selection="MAX_BULK_SELECTION"
        :locale="currentLocale"
        :can-assign-category="canBulkAssignCategory"
        :can-assign-tags="canBulkAssignTags"
        @open="openBulkAssignment"
        @clear="clearBulkSelection"
      />

      <WorksIndexSmartList
        :locale="currentLocale"
        :items="items"
        :pagination="pagination"
        :summary="summary"
        :summary-warning="summaryWarning"
        :loading="loading"
        :updating="loading && summary !== null"
        :error="error"
        :metric="selectedMetric"
        :date-field="selectedDateField"
        :sort="appliedFilters.sort"
        :direction="appliedFilters.direction"
        :can-select="canManageBulkTaxonomy"
        :selected-ids="selectedWorkIds"
        :selection-at-limit="selectionAtLimit"
        :can-view-details="canViewDetails"
        :can-manage-taxonomy="canManageIndividualTaxonomy"
        :can-create="canCreateWork"
        :can-edit-work="canEditWork"
        @retry="fetchWorks"
        @reset="resetFilters"
        @toggle-page="toggleCurrentPage"
        @toggle-work="toggleWork"
        @metric-change="selectMetric"
        @date-change="selectDateField"
        @direction-change="toggleDynamicSortDirection"
        @details="openDetails"
        @taxonomy="openTaxonomyAssignment"
        @page="changePage"
      />
    </template>

    <WorksDetailsDrawer
      :open="drawerOpen"
      :locale="currentLocale"
      :selected-title="selectedWorkTitle"
      :detail="detail"
      :loading="detailLoading"
      :error="detailError"
      :can-edit="canEditSelectedDetail"
      :can-manage-taxonomy="canManageIndividualTaxonomy && Boolean(assignmentWorkForDetail)"
      @close="closeDetails"
      @retry="retrySelectedDetails"
      @edit="editSelectedWork"
      @manage-taxonomy="openTaxonomyFromDetails"
    />

    <WorksTaxonomyAssignmentDrawer
      :open="assignmentOpen"
      :work="assignmentWork"
      :locale="currentLocale"
      :can-update-category="canUpdateAssignedCategory"
      :can-update-tags="canUpdateAssignedTags"
      :permission-revision="assignmentPermissionRevision"
      :return-to-details="taxonomyReturnToDetails"
      @close="closeTaxonomyAssignment"
      @changed="handleTaxonomyAssignmentChanged"
      @authorization-error="handleTaxonomyAuthorizationError"
    />

    <WorksTaxonomyBulkAssignmentDrawer
      :open="bulkAssignmentOpen"
      :works="sortedSelectedWorks"
      :locale="currentLocale"
      :can-assign-category="canBulkAssignCategory"
      :can-assign-tags="canBulkAssignTags"
      :permission-revision="bulkPermissionRevision"
      @close="closeBulkAssignment"
      @changed="handleBulkAssignmentChanged"
      @authorization-error="handleBulkAuthorizationError"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import WorksIndexFilters from '~/components/works/index/WorksIndexFilters.vue'
import WorksIndexHeaderSummary from '~/components/works/index/WorksIndexHeaderSummary.vue'
import WorksIndexSmartList from '~/components/works/index/WorksIndexSmartList.vue'
import WorksDetailsDrawer from '~/components/works/drawers/WorksDetailsDrawer.vue'
import WorksTaxonomyAssignmentDrawer from '~/components/works/taxonomy/WorksTaxonomyAssignmentDrawer.vue'
import WorksTaxonomyBulkAssignmentDrawer from '~/components/works/taxonomy/WorksTaxonomyBulkAssignmentDrawer.vue'
import WorksTaxonomyBulkSelectionBar from '~/components/works/taxonomy/WorksTaxonomyBulkSelectionBar.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = 'hidden' | 'public'
type BooleanFilter = '' | '1' | '0'
type PageSize = 15 | 25 | 50
type SortDirection = 'asc' | 'desc'
type MetricKey = 'views_count' | 'likes_count' | 'reports_count'
type DateSortKey = 'created_at' | 'updated_at' | 'submitted_at' | 'reviewed_at' | 'approved_at' | 'published_at' | 'rejected_at' | 'hidden_at' | 'archived_at'
type WorkSortKey = DateSortKey | 'title' | 'status' | MetricKey

interface UserReference {
  id: number
  name: string
}

interface SafeTaxonomyEntity {
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

interface WorkTaxonomySnapshot {
  category: SafeTaxonomyEntity | null
  category_tracking: CategoryTracking | null
  tags: SafeTaxonomyEntity[] | null
}

interface TaxonomyAccess {
  can_view_category: boolean
  can_view_tags: boolean
}

interface WorkListItem {
  id: number
  title: string
  slug: string
  summary: string | null
  status: WorkStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  price_amount: string | null
  delivery_days: number | null
  designer: UserReference | null
  reviewer: UserReference | null
  category_id: number | null
  is_featured: boolean
  is_pinned: boolean
  reports_count: number
  views_count: number
  likes_count: number
  submitted_at: string | null
  reviewed_at: string | null
  approved_at: string | null
  published_at: string | null
  rejected_at: string | null
  hidden_at: string | null
  archived_at: string | null
  updated_at: string | null
  created_at: string | null
  taxonomy: WorkTaxonomySnapshot
}

interface WorksPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface WorksSummary {
  total_filtered: number
  visible_on_page: number
  published_filtered: number
  review_cycle_filtered: number
  reported_filtered: number
}

interface WorksIndexData {
  items: WorkListItem[]
  pagination: WorksPagination
  summary: WorksSummary | null
  filters: Record<string, unknown>
  taxonomy_access: TaxonomyAccess
}

interface WorksIndexResponse {
  success: boolean
  data: WorksIndexData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface BulkSummary {
  requested: number
  changed: number
  unchanged: number
}

interface BulkCategoryItem {
  work_id: number
  previous_category_id: number | null
  category_id: number | null
  changed: boolean
}

interface BulkTagsItem {
  work_id: number
  previous_tag_ids: number[]
  tag_ids: number[]
  added_tag_ids: number[]
  removed_tag_ids: number[]
  changed: boolean
}

interface BulkAssignmentChanged {
  section: 'category' | 'tags'
  changed: boolean
  summary: BulkSummary
  items: BulkCategoryItem[] | BulkTagsItem[]
  category?: SafeTaxonomyEntity | null
  tags?: SafeTaxonomyEntity[]
}

interface WorkDetailBase {
  id: number
  title: string
  slug: string
  summary: string | null
  status: WorkStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  price_amount: string | null
  delivery_days: number | null
  category_id: number | null
  is_featured: boolean
  is_pinned: boolean
  reports_count: number
  views_count: number
  likes_count: number
  submitted_at: string | null
  reviewed_at: string | null
  approved_at: string | null
  published_at: string | null
  rejected_at: string | null
  hidden_at: string | null
  archived_at: string | null
  updated_at: string | null
  created_at: string | null
}

interface WorkDetailData {
  work: WorkDetailBase
  taxonomy: WorkTaxonomySnapshot
  relations: {
    designer: UserReference | null
    reviewer: UserReference | null
  }
  media: {
    media_type: string | null
    has_media: boolean
  } | null
  private_notes: {
    internal_notes: string | null
    rejection_reason: string | null
    change_request_notes: string | null
  } | null
  field_access: {
    can_view_designer: boolean
    can_view_media: boolean
    can_view_metadata: boolean
    can_view_private_notes: boolean
  }
  taxonomy_access: TaxonomyAccess
}

interface WorkDetailResponse {
  success: boolean
  data: WorkDetailData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface WorksFilters {
  q: string
  status: '' | WorkStatus
  visibility_status: '' | VisibilityStatus
  media_type: string
  is_featured: BooleanFilter
  is_pinned: BooleanFilter
  reported: BooleanFilter
  from: string
  to: string
  sort: WorkSortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const route = useRoute()
const router = useRouter()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const metricKeys: MetricKey[] = ['views_count', 'likes_count', 'reports_count']
const dateSortKeys: DateSortKey[] = ['created_at', 'updated_at', 'submitted_at', 'reviewed_at', 'approved_at', 'published_at', 'rejected_at', 'hidden_at', 'archived_at']

function validQueryChoice<T extends string>(value: unknown, choices: T[], fallback: T): T {
  const normalized = Array.isArray(value) ? value[0] : value
  return typeof normalized === 'string' && choices.includes(normalized as T) ? normalized as T : fallback
}

const selectedMetric = ref<MetricKey>(validQueryChoice(route.query.metric, metricKeys, 'views_count'))
const selectedDateField = ref<DateSortKey>(validQueryChoice(route.query.date_field, dateSortKeys, 'updated_at'))
const initialSort = validQueryChoice<WorkSortKey>(
  route.query.sort,
  ['title', 'status', ...metricKeys, ...dateSortKeys],
  selectedDateField.value
)
const initialDirection = validQueryChoice<SortDirection>(route.query.direction, ['asc', 'desc'], 'desc')

const copyMap = {
  ar: {
    individualAndBulkAvailable: 'إسناد فردي وجماعي',
    bulkAvailable: 'إسناد جماعي متاح',
    individualAvailable: 'إسناد فردي متاح',
    permissionRead: 'قراءة حسب الصلاحيات',
    kicker: 'إدارة المحتوى الإبداعي',
    title: 'كل الأعمال',
    managementDescription: 'قائمة إدارية آمنة تعرض التصنيف والوسوم الحالية، وتتيح التحديد والإسناد الفردي أو الجماعي حسب صلاحيات الحساب.',
    bulkDescription: 'قائمة إدارية آمنة تعرض التصنيف والوسوم الحالية، وتتيح تحديد الأعمال والإسناد الجماعي حسب صلاحيات الحساب.',
    individualDescription: 'قائمة إدارية آمنة تعرض التصنيف والوسوم الحالية، وتتيح الإسناد الفردي حسب صلاحيات الحساب.',
    readOnlyDescription: 'قائمة إدارية آمنة تعرض بيانات الأعمال والتصنيف والوسوم التي يسمح الحساب بقراءتها.',
    totalWorks: 'إجمالي النتائج',
    safeRecords: 'سجلات آمنة من API الأعمال',
    authLoadingTitle: 'جارٍ التحقق من صلاحية قائمة الأعمال',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى كل الأعمال غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب الصلاحيات المطلوبة لقراءة قائمة الأعمال. لم تتم محاولة تحميل البيانات.',
    managementNotice: 'تظهر أدوات تحديد الأعمال والإسناد الفردي أو الجماعي حسب الصلاحيات المتاحة. بقية إجراءات دورة العمل خارج هذه الصفحة.',
    bulkNotice: 'تظهر أدوات تحديد الأعمال والإسناد الجماعي حسب الصلاحيات المتاحة. بقية إجراءات دورة العمل خارج هذه الصفحة.',
    individualNotice: 'يظهر الإسناد الفردي حسب الصلاحيات المتاحة. بقية إجراءات دورة العمل خارج هذه الصفحة.',
    readOnlyNotice: 'يعرض هذا الحساب البيانات المصرح بقراءتها فقط، ولا تظهر له أدوات غير مصرح بها.',
    pageSummary: 'ملخص نتائج صفحة الأعمال الحالية',
    total: 'إجمالي النتائج',
    totalHint: 'كل النتائج المطابقة للفلاتر',
    visibleItems: 'الظاهر في الصفحة',
    visibleItemsHint: 'عدد الصفوف المحملة حاليًا',
    publishedCurrent: 'منشورة حاليًا',
    publishedCurrentHint: 'ضمن عناصر الصفحة الحالية',
    reviewCurrent: 'ضمن دورة المراجعة',
    reviewCurrentHint: 'مرسلة أو تحت المراجعة أو بانتظار تعديل',
    reportedCurrent: 'عليها بلاغات',
    reportedCurrentHint: 'ضمن عناصر الصفحة الحالية',
    filtersTitle: 'البحث والفلاتر',
    filtersCopy: 'استخدم الحقول المعتمدة فقط لتضييق القائمة، ثم طبّق الفلاتر.',
    search: 'البحث',
    searchPlaceholder: 'العنوان أو المعرّف النصي أو الملخص',
    searchHint: 'حرفان على الأقل، وبحد أقصى 80 حرفًا.',
    status: 'الحالة',
    visibility: 'الظهور',
    mediaType: 'نوع الوسائط',
    featured: 'مميز',
    pinned: 'مثبت',
    reported: 'عليه بلاغات',
    from: 'من تاريخ الإنشاء',
    to: 'إلى تاريخ الإنشاء',
    perPage: 'لكل صفحة',
    all: 'الكل',
    yes: 'نعم',
    no: 'لا',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    resetHint: 'مسح الفلاتر وإعادة الفرز الافتراضي',
    searchTooShort: 'نص البحث يجب أن يكون فارغًا أو يحتوي حرفين على الأقل.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من البحث والقيم والتواريخ.',
    tableTitle: 'قائمة الأعمال',
    managementTableCopy: 'اقرأ الحالة الحالية، واستخدم التفاصيل أو الإسناد الفردي أو الجماعي وفق صلاحيات الحساب.',
    bulkTableCopy: 'اقرأ الحالة الحالية، وحدد الأعمال لإدارتها جماعيًا وفق صلاحيات الحساب.',
    individualTableCopy: 'اقرأ الحالة الحالية، واستخدم التفاصيل أو الإسناد الفردي وفق صلاحيات الحساب.',
    readOnlyTableCopy: 'اقرأ بيانات الأعمال والتصنيف والوسوم التي يسمح بها نطاق الحساب.',
    currentPage: 'الصفحة الحالية',
    loadingTitle: 'جارٍ تحميل الأعمال',
    loadingCopy: 'يتم جلب القائمة الآمنة وفق الفلاتر الحالية...',
    errorTitle: 'تعذر تحميل قائمة الأعمال',
    genericError: 'حدث خطأ أثناء تحميل الأعمال. حاول مرة أخرى.',
    updateError: 'تعذر تحديث النتائج. ما زالت آخر بيانات ناجحة معروضة.',
    summaryUnavailable: 'الملخص غير متاح حاليًا.',
    summaryMismatch: 'تعذر اعتماد ملخص النتائج لعدم اتساقه مع القائمة.',
    retry: 'إعادة المحاولة',
    emptyTitle: 'لا توجد أعمال مطابقة',
    emptyCopy: 'جرّب تعديل الفلاتر أو إعادة ضبطها لعرض نتائج أخرى.',
    workTitle: 'العنوان',
    selectCurrentPage: 'تحديد أعمال الصفحة الحالية',
    selectWork: (title: string) => `تحديد العمل ${title}`,
    selectionLimitReached: 'وصل التحديد إلى الحد الأقصى: 100 عمل.',
    selectionPageWouldExceed: 'لا يمكن تحديد الصفحة الحالية لأن الإجمالي سيتجاوز الحد الأقصى 100. لم يتغير التحديد.',
    refreshAfterBulkFailed: 'نجحت العملية الجماعية، لكن تعذر تحديث قائمة الصفحة الحالية. يمكنك إعادة المحاولة.',
    designer: 'المصمم',
    reviewer: 'المراجع',
    category: 'التصنيف',
    taxonomy: 'التصنيف والوسوم',
    tags: 'الوسوم',
    activeTaxonomy: 'فعال',
    disabledTaxonomy: 'معطل',
    uncategorized: 'غير مصنف',
    legacyUnmapped: 'قيمة قديمة غير مربوطة',
    taxonomyUnavailable: 'غير متاح حسب الصلاحية',
    tagsUnavailable: 'غير متاحة حسب الصلاحية',
    noTags: 'لا توجد وسوم',
    reports: 'البلاغات',
    views: 'المشاهدات',
    likes: 'الإعجابات',
    submittedAt: 'تاريخ الإرسال',
    publishedAt: 'تاريخ النشر',
    createdAt: 'تاريخ الإنشاء',
    updatedAt: 'آخر تحديث',
    actions: 'الإجراءات',
    createWork: 'إنشاء عمل جديد',
    editWork: 'تحرير العمل',
    viewDetails: 'عرض التفاصيل',
    viewDetailsHint: 'فتح تفاصيل العمل الآمنة',
    detailsPermissionRequired: 'تحتاج صلاحية عرض تفاصيل الأعمال',
    manageTaxonomy: 'إدارة التصنيف والوسوم',
    manageTaxonomyFor: (title: string) => `إدارة التصنيف والوسوم للعمل ${title}`,
    paginationTotal: 'إجمالي النتائج',
    visibleNow: 'عنصر ظاهر الآن',
    paginationLabel: 'التنقل بين صفحات الأعمال',
    previous: 'السابق',
    next: 'التالي',
    pageOf: (page: number, last: number) => `الصفحة ${page} من ${last}`,
    detailsTitle: 'تفاصيل العمل',
    detailReadonly: 'تفاصيل للقراءة فقط',
    close: 'إغلاق التفاصيل',
    detailsLoadingTitle: 'جارٍ تحميل التفاصيل',
    detailsLoadingCopy: 'يتم جلب الحقول المسموحة لهذا الحساب...',
    detailsErrorTitle: 'تعذر تحميل تفاصيل العمل',
    detailsGenericError: 'حدث خطأ أثناء تحميل التفاصيل. حاول مرة أخرى.',
    detailsForbidden: 'تفاصيل هذا العمل غير متاحة حسب صلاحيات الحساب.',
    detailsNotFound: 'لم يعد هذا العمل موجودًا أو لم يعد متاحًا.',
    noSummary: 'لا يوجد ملخص مسجل لهذا العمل.',
    detailTaxonomyCopy: 'الحالة الحالية كما أعادها الخادم، بما في ذلك العناصر المعطلة المرتبطة قديمًا.',
    accessIndicators: 'نطاق الحقول المتاح',
    accessIndicatorsCopy: 'تعكس هذه المؤشرات الصلاحيات التي طبّقها الخادم على استجابة التفاصيل.',
    canViewDesigner: 'المصمم والمراجع',
    canViewMedia: 'بيانات الوسائط',
    canViewMetadata: 'صلاحية metadata',
    canViewPrivateNotes: 'الملاحظات الخاصة',
    allowed: 'متاح',
    unavailable: 'غير متاح',
    basicDetails: 'البيانات الأساسية',
    priceAmount: 'القيمة السعرية',
    deliveryDays: 'مدة التسليم بالأيام',
    people: 'المصمم والمراجع',
    notLinked: 'غير مرتبط',
    relationsUnavailable: 'المصمم والمراجع غير متاحين حسب الصلاحية.',
    media: 'الوسائط',
    mediaPresent: 'توجد وسائط مسجلة',
    mediaAbsent: 'لا توجد وسائط مسجلة',
    mediaUnavailable: 'بيانات الوسائط غير متاحة حسب الصلاحية.',
    lifecycle: 'التسلسل الزمني',
    reviewedAt: 'تاريخ المراجعة',
    approvedAt: 'تاريخ الاعتماد',
    rejectedAt: 'تاريخ الرفض',
    hiddenAt: 'تاريخ الإخفاء',
    archivedAt: 'تاريخ الأرشفة',
    privateNotes: 'الملاحظات الخاصة',
    privateNotesCopy: 'لا تظهر محتويات هذا القسم إلا عندما يسمح الخادم بذلك.',
    internalNotes: 'الملاحظات الداخلية',
    rejectionReason: 'سبب الرفض',
    changeRequestNotes: 'ملاحظات طلب التعديل',
    privateNotesUnavailable: 'الملاحظات الخاصة غير متاحة حسب الصلاحية.',
    publicVisibility: 'عام',
    hiddenVisibility: 'مخفي'
  },
  en: {
    individualAndBulkAvailable: 'Individual and bulk assignment',
    bulkAvailable: 'Bulk assignment available',
    individualAvailable: 'Individual assignment available',
    permissionRead: 'Permission-based read',
    kicker: 'Creative content management',
    title: 'All Works',
    managementDescription: 'A safe administrative list showing current categories and tags, with selection and individual or bulk assignment according to permissions.',
    bulkDescription: 'A safe administrative list showing current categories and tags, with work selection and bulk assignment according to permissions.',
    individualDescription: 'A safe administrative list showing current categories and tags, with individual assignment according to permissions.',
    readOnlyDescription: 'A safe administrative list showing work data, categories, and tags that this account may read.',
    totalWorks: 'Total results',
    safeRecords: 'Safe records from the Works API',
    authLoadingTitle: 'Checking works list access',
    authLoadingCopy: 'Waiting for the user session to initialize before requesting data.',
    forbiddenTitle: 'All Works access is unavailable',
    forbiddenCopy: 'This account lacks the required permissions to read the works list. No data request was made.',
    managementNotice: 'Work selection and individual or bulk assignment appear according to permissions. Other lifecycle actions remain outside this page.',
    bulkNotice: 'Work selection and bulk assignment appear according to permissions. Other lifecycle actions remain outside this page.',
    individualNotice: 'Individual assignment appears according to permissions. Other lifecycle actions remain outside this page.',
    readOnlyNotice: 'This account sees only data it may read; unauthorized tools are not shown.',
    pageSummary: 'Current works page summary',
    total: 'Total results',
    totalHint: 'All results matching the filters',
    visibleItems: 'Visible on page',
    visibleItemsHint: 'Rows currently loaded',
    publishedCurrent: 'Published now',
    publishedCurrentHint: 'Within the current page items',
    reviewCurrent: 'In review flow',
    reviewCurrentHint: 'Submitted, in review, or awaiting changes',
    reportedCurrent: 'Reported',
    reportedCurrentHint: 'Within the current page items',
    filtersTitle: 'Search and filters',
    filtersCopy: 'Use only the allowlisted fields to narrow the list, then apply the filters.',
    search: 'Search',
    searchPlaceholder: 'Title, slug, or summary',
    searchHint: 'At least 2 and at most 80 characters.',
    status: 'Status',
    visibility: 'Visibility',
    mediaType: 'Media type',
    featured: 'Featured',
    pinned: 'Pinned',
    reported: 'Reported',
    from: 'Created from',
    to: 'Created to',
    perPage: 'Per page',
    all: 'All',
    yes: 'Yes',
    no: 'No',
    apply: 'Apply filters',
    reset: 'Reset',
    resetHint: 'Clear filters and restore default sorting',
    searchTooShort: 'Search must be empty or contain at least two characters.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    validationError: 'The filters could not be applied. Check the search, values, and dates.',
    tableTitle: 'Works list',
    managementTableCopy: 'Read current state, then use details or individual or bulk assignment according to account permissions.',
    bulkTableCopy: 'Read current state and select works for bulk management according to account permissions.',
    individualTableCopy: 'Read current state, then use details or individual assignment according to account permissions.',
    readOnlyTableCopy: 'Read work, category, and tag data available to this account.',
    currentPage: 'Current page',
    loadingTitle: 'Loading works',
    loadingCopy: 'Fetching the safe list using the current filters...',
    errorTitle: 'Could not load works',
    genericError: 'An error occurred while loading works. Try again.',
    updateError: 'Could not update the results. The last successful data is still displayed.',
    summaryUnavailable: 'The results summary is currently unavailable.',
    summaryMismatch: 'The results summary could not be used because it does not match the list.',
    retry: 'Retry',
    emptyTitle: 'No matching works',
    emptyCopy: 'Change or reset the filters to see other results.',
    workTitle: 'Title',
    selectCurrentPage: 'Select works on the current page',
    selectWork: (title: string) => `Select work ${title}`,
    selectionLimitReached: 'The selection has reached the maximum of 100 works.',
    selectionPageWouldExceed: 'The current page cannot be selected because the total would exceed 100. The selection was not changed.',
    refreshAfterBulkFailed: 'The bulk operation succeeded, but the current list could not be refreshed. You can retry.',
    designer: 'Designer',
    reviewer: 'Reviewer',
    category: 'Category',
    taxonomy: 'Category and tags',
    tags: 'Tags',
    activeTaxonomy: 'Active',
    disabledTaxonomy: 'Disabled',
    uncategorized: 'Uncategorized',
    legacyUnmapped: 'Unmapped legacy value',
    taxonomyUnavailable: 'Unavailable for this permission scope',
    tagsUnavailable: 'Unavailable for this permission scope',
    noTags: 'No tags',
    reports: 'Reports',
    views: 'Views',
    likes: 'Likes',
    submittedAt: 'Submitted at',
    publishedAt: 'Published at',
    createdAt: 'Created at',
    updatedAt: 'Updated at',
    actions: 'Actions',
    createWork: 'Create new work',
    editWork: 'Edit work',
    viewDetails: 'View details',
    viewDetailsHint: 'Open safe work details',
    detailsPermissionRequired: 'Work detail permission is required',
    manageTaxonomy: 'Manage category and tags',
    manageTaxonomyFor: (title: string) => `Manage category and tags for ${title}`,
    paginationTotal: 'Total results',
    visibleNow: 'items visible now',
    paginationLabel: 'Works pagination',
    previous: 'Previous',
    next: 'Next',
    pageOf: (page: number, last: number) => `Page ${page} of ${last}`,
    detailsTitle: 'Work details',
    detailReadonly: 'Read-only details',
    close: 'Close details',
    detailsLoadingTitle: 'Loading details',
    detailsLoadingCopy: 'Fetching the fields allowed for this account...',
    detailsErrorTitle: 'Could not load work details',
    detailsGenericError: 'An error occurred while loading details. Try again.',
    detailsForbidden: 'This work detail is unavailable for the current account permissions.',
    detailsNotFound: 'This work no longer exists or is no longer available.',
    noSummary: 'No summary has been recorded for this work.',
    detailTaxonomyCopy: 'The current server-provided state, including previously linked disabled entities.',
    accessIndicators: 'Available field scope',
    accessIndicatorsCopy: 'These indicators reflect the permissions enforced by the server response.',
    canViewDesigner: 'Designer and reviewer',
    canViewMedia: 'Media data',
    canViewMetadata: 'Metadata permission',
    canViewPrivateNotes: 'Private notes',
    allowed: 'Available',
    unavailable: 'Unavailable',
    basicDetails: 'Basic details',
    priceAmount: 'Price amount',
    deliveryDays: 'Delivery days',
    people: 'Designer and reviewer',
    notLinked: 'Not linked',
    relationsUnavailable: 'Designer and reviewer are unavailable for this permission scope.',
    media: 'Media',
    mediaPresent: 'Media is recorded',
    mediaAbsent: 'No media is recorded',
    mediaUnavailable: 'Media data is unavailable for this permission scope.',
    lifecycle: 'Lifecycle',
    reviewedAt: 'Reviewed at',
    approvedAt: 'Approved at',
    rejectedAt: 'Rejected at',
    hiddenAt: 'Hidden at',
    archivedAt: 'Archived at',
    privateNotes: 'Private notes',
    privateNotesCopy: 'This section only reveals content when the server allows it.',
    internalNotes: 'Internal notes',
    rejectionReason: 'Rejection reason',
    changeRequestNotes: 'Change request notes',
    privateNotesUnavailable: 'Private notes are unavailable for this permission scope.',
    publicVisibility: 'Public',
    hiddenVisibility: 'Hidden'
  }
} as const

const copy = computed(() => copyMap[currentLocale.value])
const authPending = computed(() => !authStore.isInitialized)
const hasWorksListAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.all.view')
    && authStore.permissions.includes('admin.works.list')
})
const taxonomyAccess = reactive<TaxonomyAccess>({
  can_view_category: false,
  can_view_tags: false
})
const isSuperAdmin = computed(() => authStore.role === 'super-admin')
const updatePermissions = [
  'admin.works.update.basic',
  'admin.works.update.media',
  'admin.works.update.pricing',
  'admin.works.update.delivery',
  'admin.works.update.designer',
  'admin.works.update.private_notes'
] as const
const canCreateWork = computed(() => (
  isSuperAdmin.value
  || (
    ['admin', 'staff'].includes(authStore.role || '')
    && authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.create')
  )
))
function canEditWork(work: WorkListItem): boolean {
  if (!['draft', 'changes_requested'].includes(work.status)) return false
  if (isSuperAdmin.value) return true
  return ['admin', 'staff'].includes(authStore.role || '')
    && authStore.permissions.includes('admin.works.access')
    && (
      updatePermissions.some(permission => authStore.permissions.includes(permission))
      || canUpdateAssignedCategory.value
      || canUpdateAssignedTags.value
    )
}
const canViewDetails = computed(() => (
  hasWorksListAccess.value
  && (
    authStore.role === 'super-admin'
    || authStore.permissions.includes('admin.works.detail.view')
  )
))
const canViewAssignedCategory = computed(() => taxonomyAccess.can_view_category)
const canViewAssignedTags = computed(() => taxonomyAccess.can_view_tags)
const canUpdateAssignedCategory = computed(() => (
  isSuperAdmin.value
  || (
    canViewAssignedCategory.value
    && authStore.permissions.includes('admin.works.update.category')
  )
))
const canUpdateAssignedTags = computed(() => (
  isSuperAdmin.value
  || (
    canViewAssignedTags.value
    && authStore.permissions.includes('admin.works.update.tags')
  )
))
const canManageIndividualTaxonomy = computed(() => (
  canUpdateAssignedCategory.value || canUpdateAssignedTags.value
))
const canBulkAssignCategory = computed(() => (
  isSuperAdmin.value
  || (
    canViewAssignedCategory.value
    && authStore.permissions.includes('admin.works.taxonomy.bulk_assign')
    && authStore.permissions.includes('admin.works.bulk.category_update')
  )
))
const canBulkAssignTags = computed(() => (
  isSuperAdmin.value
  || (
    canViewAssignedTags.value
    && authStore.permissions.includes('admin.works.taxonomy.bulk_assign')
    && authStore.permissions.includes('admin.works.bulk.tags_update')
  )
))
const canManageBulkTaxonomy = computed(() => canBulkAssignCategory.value || canBulkAssignTags.value)
const managementBadge = computed(() => {
  if (canManageIndividualTaxonomy.value && canManageBulkTaxonomy.value) return copy.value.individualAndBulkAvailable
  if (canManageBulkTaxonomy.value) return copy.value.bulkAvailable
  if (canManageIndividualTaxonomy.value) return copy.value.individualAvailable
  return copy.value.permissionRead
})
const taxonomyDescription = computed(() => {
  if (canManageIndividualTaxonomy.value && canManageBulkTaxonomy.value) return copy.value.managementDescription
  if (canManageBulkTaxonomy.value) return copy.value.bulkDescription
  if (canManageIndividualTaxonomy.value) return copy.value.individualDescription
  return copy.value.readOnlyDescription
})
const taxonomyNotice = computed(() => {
  if (canManageIndividualTaxonomy.value && canManageBulkTaxonomy.value) return copy.value.managementNotice
  if (canManageBulkTaxonomy.value) return copy.value.bulkNotice
  if (canManageIndividualTaxonomy.value) return copy.value.individualNotice
  return copy.value.readOnlyNotice
})
const taxonomyTableCopy = computed(() => {
  if (canManageIndividualTaxonomy.value && canManageBulkTaxonomy.value) return copy.value.managementTableCopy
  if (canManageBulkTaxonomy.value) return copy.value.bulkTableCopy
  if (canManageIndividualTaxonomy.value) return copy.value.individualTableCopy
  return copy.value.readOnlyTableCopy
})
const serverForbidden = ref(false)
const forbidden = computed(() => (
  authStore.isInitialized && (!hasWorksListAccess.value || serverForbidden.value)
))

const items = ref<WorkListItem[]>([])
const summary = ref<WorksSummary | null>(null)
const summaryWarning = ref<string | null>(null)
const pagination = reactive<WorksPagination>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
function defaultFilters(): WorksFilters {
  return {
    q: '',
    status: '',
    visibility_status: '',
    media_type: '',
    is_featured: '',
    is_pinned: '',
    reported: '',
    from: '',
    to: '',
    sort: initialSort,
    direction: initialDirection,
    per_page: 15
  }
}

const filters = reactive<WorksFilters>(defaultFilters())
const appliedFilters = reactive<WorksFilters>(defaultFilters())
const page = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)

type ActiveDrawer = 'details' | 'taxonomy' | null
const activeDrawer = ref<ActiveDrawer>(null)
const drawerOpen = computed(() => activeDrawer.value === 'details')
const assignmentOpen = computed(() => activeDrawer.value === 'taxonomy')
const selectedWorkId = ref<number | null>(null)
const selectedWorkTitle = ref('')
const detail = ref<WorkDetailData | null>(null)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const assignmentWork = ref<WorkListItem | null>(null)
const taxonomyReturnToDetails = ref(false)
const MAX_BULK_SELECTION = 100
const selectedWorks = ref<Map<number, WorkListItem>>(new Map())
const currentPageCheckbox = ref<HTMLInputElement | null>(null)
const selectionMessage = ref('')
const bulkRefreshWarning = ref('')
const bulkAssignmentOpen = ref(false)

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let listRequestRevision = 0
let detailRequestRevision = 0
let handledDeepLinkWork: string | null = null

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
  [...authStore.permissions].sort().join(',')
].join('|'))
const assignmentPermissionRevision = computed(() => [
  authorizationSignature.value,
  taxonomyAccess.can_view_category ? 'category-visible' : 'category-hidden',
  taxonomyAccess.can_view_tags ? 'tags-visible' : 'tags-hidden'
].join('|'))
const bulkPermissionRevision = computed(() => [
  authorizationSignature.value,
  taxonomyAccess.can_view_category ? 'category-visible' : 'category-hidden',
  taxonomyAccess.can_view_tags ? 'tags-visible' : 'tags-hidden',
  canBulkAssignCategory.value ? 'bulk-category-allowed' : 'bulk-category-denied',
  canBulkAssignTags.value ? 'bulk-tags-allowed' : 'bulk-tags-denied'
].join('|'))
const selectedCount = computed(() => selectedWorks.value.size)
const selectedWorkIds = computed(() => [...selectedWorks.value.keys()])
const currentPageSelectedCount = computed(() => items.value.filter(work => selectedWorks.value.has(work.id)).length)
const allCurrentPageSelected = computed(() => items.value.length > 0 && currentPageSelectedCount.value === items.value.length)
const selectionAtLimit = computed(() => selectedCount.value >= MAX_BULK_SELECTION)
const currentPageUnselectedCount = computed(() => items.value.length - currentPageSelectedCount.value)
const canSelectCurrentPage = computed(() => selectedCount.value + currentPageUnselectedCount.value <= MAX_BULK_SELECTION)
const sortedSelectedWorks = computed(() => [...selectedWorks.value.values()].sort((a, b) => a.id - b.id))
const assignmentWorkForDetail = computed(() => {
  if (!detail.value) return null
  const listed = items.value.find(work => work.id === detail.value?.work.id)
  if (listed) return listed
  const work = detail.value.work
  return {
    ...work,
    designer: detail.value.relations.designer,
    reviewer: detail.value.relations.reviewer,
    taxonomy: detail.value.taxonomy
  } satisfies WorkListItem
})
const canEditSelectedDetail = computed(() => {
  const work = assignmentWorkForDetail.value
  return work ? canEditWork(work) : false
})

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

function validSummary(
  value: WorksSummary | null | undefined,
  nextItems: WorkListItem[],
  nextPagination: WorksPagination
): WorksSummary | null {
  if (!value || typeof value !== 'object') {
    summaryWarning.value = copy.value.summaryUnavailable
    if (import.meta.dev) console.warn('[works-index] Summary is missing from the list response.')
    return null
  }

  const fields: Array<keyof WorksSummary> = [
    'total_filtered',
    'visible_on_page',
    'published_filtered',
    'review_cycle_filtered',
    'reported_filtered'
  ]
  const numeric = fields.every(field => Number.isInteger(value[field]) && value[field] >= 0)
  const consistent = value.total_filtered === nextPagination.total
    && value.visible_on_page === nextItems.length

  if (!numeric || !consistent) {
    summaryWarning.value = copy.value.summaryMismatch
    if (import.meta.dev) console.warn('[works-index] Summary consistency guard rejected the list summary.')
    return null
  }

  summaryWarning.value = null
  return value
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

  return labels[status]?.[currentLocale.value] ?? status
}

function errorStatus(requestError: unknown): number | null {
  if (!requestError || typeof requestError !== 'object') return null

  if ('response' in requestError && typeof (requestError as { response?: { status?: unknown } }).response?.status === 'number') {
    return (requestError as { response: { status: number } }).response.status
  }

  if ('statusCode' in requestError && typeof (requestError as { statusCode?: unknown }).statusCode === 'number') {
    return (requestError as { statusCode: number }).statusCode
  }

  if ('status' in requestError && typeof (requestError as { status?: unknown }).status === 'number') {
    return (requestError as { status: number }).status
  }

  return null
}

function validateFilters(): boolean {
  filterError.value = null
  const query = filters.q.trim()

  if (query.length === 1) {
    filterError.value = copy.value.searchTooShort
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

  // نضيف مفاتيح قائمة السماح فقط، ولا نرسل أي قيمة فارغة أو بيانات داخلية.
  const optionalFilters: Array<[string, string]> = [
    ['q', appliedFilters.q.trim()],
    ['status', appliedFilters.status],
    ['visibility_status', appliedFilters.visibility_status],
    ['media_type', appliedFilters.media_type.trim()],
    ['is_featured', appliedFilters.is_featured],
    ['is_pinned', appliedFilters.is_pinned],
    ['reported', appliedFilters.reported],
    ['from', appliedFilters.from],
    ['to', appliedFilters.to]
  ]

  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }

  return query
}

function cloneWorkSnapshot(work: WorkListItem): WorkListItem {
  return {
    ...work,
    taxonomy: {
      category: work.taxonomy.category ? { ...work.taxonomy.category } : null,
      category_tracking: work.taxonomy.category_tracking ? { ...work.taxonomy.category_tracking } : null,
      tags: work.taxonomy.tags ? work.taxonomy.tags.map(tag => ({ ...tag })) : work.taxonomy.tags
    }
  }
}

function isWorkSelected(workId: number): boolean {
  return selectedWorks.value.has(workId)
}

function replaceSelection(next: Map<number, WorkListItem>): void {
  selectedWorks.value = next
}

function toggleWork(work: WorkListItem): void {
  if (!canManageBulkTaxonomy.value) return
  const next = new Map(selectedWorks.value)
  if (next.has(work.id)) {
    next.delete(work.id)
    selectionMessage.value = ''
  } else {
    if (next.size >= MAX_BULK_SELECTION) {
      selectionMessage.value = copy.value.selectionLimitReached
      return
    }
    next.set(work.id, cloneWorkSnapshot(work))
    selectionMessage.value = next.size >= MAX_BULK_SELECTION ? copy.value.selectionLimitReached : ''
  }
  replaceSelection(next)
}

function toggleCurrentPage(): void {
  if (!canManageBulkTaxonomy.value || !items.value.length) return
  const next = new Map(selectedWorks.value)
  if (allCurrentPageSelected.value) {
    for (const work of items.value) next.delete(work.id)
    selectionMessage.value = ''
    replaceSelection(next)
    return
  }
  if (!canSelectCurrentPage.value) {
    selectionMessage.value = copy.value.selectionPageWouldExceed
    return
  }
  for (const work of items.value) next.set(work.id, cloneWorkSnapshot(work))
  selectionMessage.value = next.size >= MAX_BULK_SELECTION ? copy.value.selectionLimitReached : ''
  replaceSelection(next)
}

function clearBulkSelection(): void {
  replaceSelection(new Map())
  selectionMessage.value = ''
  bulkRefreshWarning.value = ''
  closeBulkAssignment()
}

function refreshSelectedSnapshots(visibleWorks: WorkListItem[]): void {
  if (!selectedWorks.value.size) return
  const next = new Map(selectedWorks.value)
  let changed = false
  for (const work of visibleWorks) {
    if (!next.has(work.id)) continue
    next.set(work.id, cloneWorkSnapshot(work))
    changed = true
  }
  if (changed) replaceSelection(next)
}

async function fetchWorks(): Promise<boolean> {
  if (!authStore.isInitialized || !hasWorksListAccess.value) return false

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++listRequestRevision
  loading.value = true
  error.value = null
  if (import.meta.client) {
    document.dispatchEvent(new CustomEvent('ym:works-index-overlays-close'))
  }

  try {
    const response = await apiFetch<WorksIndexResponse>('/admin/works', {
      query: buildListQuery()
    })

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasWorksListAccess.value
    ) {
      return false
    }

    if (!response.success || !response.data) {
      error.value = summary.value === null ? copy.value.genericError : copy.value.updateError
      return false
    }

    const nextItems = response.data.items
    const nextPagination = response.data.pagination
    const nextSummary = validSummary(response.data.summary, nextItems, nextPagination)

    items.value = nextItems
    Object.assign(pagination, nextPagination)
    summary.value = nextSummary
    Object.assign(taxonomyAccess, response.data.taxonomy_access)
    refreshSelectedSnapshots(nextItems)
    page.value = nextPagination.current_page
    if (assignmentOpen.value && assignmentWork.value) {
      const refreshedWork = nextItems.find(work => work.id === assignmentWork.value?.id)
      if (refreshedWork) assignmentWork.value = refreshedWork
    }
    serverForbidden.value = false
    return true
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasWorksListAccess.value
    ) {
      return false
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      items.value = []
      summary.value = null
      summaryWarning.value = null
      Object.assign(pagination, {
        current_page: 1,
        per_page: appliedFilters.per_page,
        total: 0,
        last_page: 1
      })
      Object.assign(taxonomyAccess, {
        can_view_category: false,
        can_view_tags: false
      })
      closeTaxonomyAssignment(false)
      clearBulkSelection()
      return false
    }

    if (status === 422) {
      filterError.value = copy.value.validationError
      if (summary.value !== null) error.value = copy.value.updateError
      return false
    }

    error.value = summary.value === null ? copy.value.genericError : copy.value.updateError
    return false
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === listRequestRevision) {
      loading.value = false
    }
  }
}

function applyFilters(): void {
  if (!validateFilters()) return

  Object.assign(appliedFilters, filters)
  page.value = 1
  void fetchWorks()
}

function applyCompactFilters(nextFilters: Partial<WorksFilters>): void {
  Object.assign(filters, nextFilters)
  applyFilters()
}

function removeCompactFilter(key: 'q' | 'status' | 'visibility_status' | 'media_type' | 'is_featured' | 'is_pinned' | 'reported' | 'from' | 'to'): void {
  if (key === 'from' || key === 'to') {
    Object.assign(filters, { from: '', to: '' })
    Object.assign(appliedFilters, { from: '', to: '' })
  } else {
    Object.assign(filters, { [key]: '' })
    Object.assign(appliedFilters, { [key]: '' })
  }
  page.value = 1
  void fetchWorks()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
  void fetchWorks()
}

function updateViewQuery(): void {
  const query = {
    ...route.query,
    metric: selectedMetric.value,
    date_field: selectedDateField.value,
    sort: appliedFilters.sort,
    direction: appliedFilters.direction
  }
  delete query.designer_id
  delete query.reviewer_id
  delete query.category_id
  void router.replace({ query })
}

function selectMetric(metric: MetricKey): void {
  selectedMetric.value = metric
  filters.sort = metric
  filters.direction = 'desc'
  appliedFilters.sort = metric
  appliedFilters.direction = 'desc'
  page.value = 1
  updateViewQuery()
  void fetchWorks()
}

function selectDateField(dateField: DateSortKey): void {
  selectedDateField.value = dateField
  filters.sort = dateField
  filters.direction = 'desc'
  appliedFilters.sort = dateField
  appliedFilters.direction = 'desc'
  page.value = 1
  updateViewQuery()
  void fetchWorks()
}

function toggleDynamicSortDirection(kind: 'metric' | 'date'): void {
  const sort = kind === 'metric' ? selectedMetric.value : selectedDateField.value
  const direction = appliedFilters.sort === sort && appliedFilters.direction === 'desc' ? 'asc' : 'desc'
  filters.sort = sort
  filters.direction = direction
  appliedFilters.sort = sort
  appliedFilters.direction = direction
  page.value = 1
  updateViewQuery()
  void fetchWorks()
}

function changeSort(key: WorkSortKey): void {
  if (filters.sort === key) {
    filters.direction = filters.direction === 'asc' ? 'desc' : 'asc'
  } else {
    filters.sort = key
    filters.direction = ['title', 'status'].includes(key) ? 'asc' : 'desc'
  }

  appliedFilters.sort = filters.sort
  appliedFilters.direction = filters.direction
  page.value = 1
  void fetchWorks()
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
  void fetchWorks()
}

function openDetails(work: WorkListItem): void {
  if (!canViewDetails.value) return

  closeBulkAssignment()
  closeTaxonomyAssignment(false)
  activeDrawer.value = 'details'
  selectedWorkId.value = work.id
  selectedWorkTitle.value = work.title
  detail.value = null
  detailError.value = null
  void fetchWorkDetails(work.id)
}

function deepLinkWorkId(): number | null {
  const raw = Array.isArray(route.query.work) ? route.query.work[0] : route.query.work
  if (typeof raw !== 'string' || !/^[1-9]\d*$/.test(raw)) return null
  const workId = Number(raw)
  return Number.isSafeInteger(workId) ? workId : null
}

function openDeepLinkedWork(): void {
  const workId = deepLinkWorkId()
  if (workId === null || !canViewDetails.value) return
  const identity = String(workId)
  if (handledDeepLinkWork === identity) return

  handledDeepLinkWork = identity
  closeBulkAssignment()
  closeTaxonomyAssignment(false)
  activeDrawer.value = 'details'
  selectedWorkId.value = workId
  selectedWorkTitle.value = ''
  detail.value = null
  detailError.value = null
  void fetchWorkDetails(workId)
}

function openTaxonomyAssignment(work: WorkListItem): void {
  if (!canManageIndividualTaxonomy.value) return
  closeBulkAssignment()
  taxonomyReturnToDetails.value = false
  assignmentWork.value = work
  activeDrawer.value = 'taxonomy'
}

function openTaxonomyFromDetails(): void {
  const work = assignmentWorkForDetail.value
  if (!work || !canManageIndividualTaxonomy.value) return
  taxonomyReturnToDetails.value = true
  assignmentWork.value = work
  activeDrawer.value = 'taxonomy'
}

function closeTaxonomyAssignment(returnToDetails = true): void {
  const shouldReturn = returnToDetails
    && taxonomyReturnToDetails.value
    && selectedWorkId.value !== null
    && canViewDetails.value
  assignmentWork.value = null
  taxonomyReturnToDetails.value = false
  activeDrawer.value = shouldReturn ? 'details' : null
}

function openBulkAssignment(): void {
  if (
    !canManageBulkTaxonomy.value
    || selectedCount.value < 1
    || selectedCount.value > MAX_BULK_SELECTION
  ) {
    return
  }
  closeTaxonomyAssignment(false)
  closeDetails()
  bulkRefreshWarning.value = ''
  bulkAssignmentOpen.value = true
}

function closeBulkAssignment(): void {
  bulkAssignmentOpen.value = false
}

async function handleBulkAssignmentChanged(payload: BulkAssignmentChanged): Promise<void> {
  const next = new Map(selectedWorks.value)
  if (payload.section === 'category') {
    const category = payload.category ?? null
    for (const item of payload.items as BulkCategoryItem[]) {
      const work = next.get(item.work_id)
      if (!work) continue
      work.category_id = item.category_id
      work.taxonomy.category = category ? { ...category } : null
      work.taxonomy.category_tracking = {
        catalog_record_exists: category !== null,
        is_legacy_unmapped: false,
        is_uncategorized: item.category_id === null
      }
      next.set(item.work_id, cloneWorkSnapshot(work))
    }
  } else {
    const tags = (payload.tags ?? []).map(tag => ({ ...tag }))
    for (const item of payload.items as BulkTagsItem[]) {
      const work = next.get(item.work_id)
      if (!work) continue
      work.taxonomy.tags = tags.map(tag => ({ ...tag }))
      next.set(item.work_id, cloneWorkSnapshot(work))
    }
  }
  replaceSelection(next)

  for (const work of items.value) {
    const selected = next.get(work.id)
    if (!selected) continue
    work.category_id = selected.category_id
    work.taxonomy = cloneWorkSnapshot(selected).taxonomy
  }

  bulkRefreshWarning.value = ''
  const refreshed = await fetchWorks()
  if (!refreshed) bulkRefreshWarning.value = copy.value.refreshAfterBulkFailed
}

function handleBulkAuthorizationError(): void {
  closeBulkAssignment()
  clearBulkSelection()
  if (authStore.isAuthenticated) void authStore.fetchUser()
}

function retryBulkRefresh(): void {
  bulkRefreshWarning.value = ''
  void fetchWorks().then((refreshed) => {
    if (!refreshed) bulkRefreshWarning.value = copy.value.refreshAfterBulkFailed
  })
}

async function handleTaxonomyAssignmentChanged(payload: {
  work_id: number
  section: 'category' | 'tags'
  changed: boolean
  category_id?: number | null
  category?: SafeTaxonomyEntity | null
  category_tracking?: CategoryTracking | null
  tags?: SafeTaxonomyEntity[]
}): Promise<void> {
  if (!payload.changed) return

  if (detail.value && selectedWorkId.value === payload.work_id) {
    if (payload.section === 'category' && 'category_id' in payload) {
      detail.value.work.category_id = payload.category_id ?? null
      detail.value.taxonomy.category = payload.category ? { ...payload.category } : null
      detail.value.taxonomy.category_tracking = payload.category_tracking
        ? { ...payload.category_tracking }
        : null
    }
    if (payload.section === 'tags' && payload.tags) {
      detail.value.taxonomy.tags = payload.tags.map(tag => ({ ...tag }))
    }
  }

  await fetchWorks()
  if (detail.value && selectedWorkId.value === payload.work_id) {
    const refreshed = items.value.find(work => work.id === payload.work_id)
    if (refreshed) {
      detail.value.work.category_id = refreshed.category_id
      detail.value.taxonomy = {
        category: refreshed.taxonomy.category ? { ...refreshed.taxonomy.category } : null,
        category_tracking: refreshed.taxonomy.category_tracking ? { ...refreshed.taxonomy.category_tracking } : null,
        tags: refreshed.taxonomy.tags?.map(tag => ({ ...tag })) ?? refreshed.taxonomy.tags
      }
    }
  }
}

function handleTaxonomyAuthorizationError(): void {
  closeTaxonomyAssignment(false)
  if (authStore.isAuthenticated) void authStore.fetchUser()
}

async function fetchWorkDetails(workId: number): Promise<void> {
  if (!canViewDetails.value || !drawerOpen.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++detailRequestRevision
  detailLoading.value = true
  detailError.value = null
  detail.value = null

  try {
    const response = await apiFetch<WorkDetailResponse>(`/admin/works/${workId}`)

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== detailRequestRevision
      || selectedWorkId.value !== workId
      || !drawerOpen.value
      || !canViewDetails.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      detailError.value = copy.value.detailsGenericError
      return
    }

    detail.value = response.data
    selectedWorkTitle.value = response.data.work.title
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== detailRequestRevision
      || selectedWorkId.value !== workId
      || !drawerOpen.value
    ) {
      return
    }

    const status = errorStatus(requestError)
    if (status === 401 || status === 403) {
      detailError.value = copy.value.detailsForbidden
      return
    }
    if (status === 404) {
      detailError.value = copy.value.detailsNotFound
      return
    }

    detailError.value = copy.value.detailsGenericError
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === detailRequestRevision) {
      detailLoading.value = false
    }
  }
}

function closeDetails(removeDeepLink = true): void {
  detailRequestRevision += 1
  activeDrawer.value = null
  taxonomyReturnToDetails.value = false
  assignmentWork.value = null
  selectedWorkId.value = null
  selectedWorkTitle.value = ''
  detail.value = null
  detailError.value = null
  detailLoading.value = false

  if (removeDeepLink && route.query.work !== undefined) {
    const query = { ...route.query }
    delete query.work
    handledDeepLinkWork = null
    void router.replace({ query })
  }
}

function editSelectedWork(): void {
  const workId = selectedWorkId.value
  if (workId === null || !canEditSelectedDetail.value) return
  closeDetails()
  void router.push(`/admin/works/${workId}/edit`)
}

function retrySelectedDetails(): void {
  if (selectedWorkId.value === null) return

  void fetchWorkDetails(selectedWorkId.value)
}

function clearPageState(): void {
  listRequestRevision += 1
  items.value = []
  summary.value = null
  summaryWarning.value = null
  Object.assign(pagination, {
    current_page: 1,
    per_page: appliedFilters.per_page,
    total: 0,
    last_page: 1
  })
  page.value = 1
  loading.value = false
  error.value = null
  filterError.value = null
  Object.assign(taxonomyAccess, {
    can_view_category: false,
    can_view_tags: false
  })
  closeTaxonomyAssignment(false)
  clearBulkSelection()
  closeDetails(false)
}

function syncWorksAccessState(): void {
  if (!pageMounted) return

  accessRevision += 1
  serverForbidden.value = false

  // نؤخر قرار المنع والتحميل حتى تكتمل تهيئة المصادقة والصلاحيات الدقيقة.
  if (!authStore.isInitialized) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (!hasWorksListAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchWorks().then((loaded) => {
    if (loaded) openDeepLinkedWork()
  })
}

watch(
  authorizationSignature,
  () => {
    if (!isSuperAdmin.value) {
      const hasTaxonomyView = authStore.permissions.includes('admin.works.taxonomy.view')
      if (!hasTaxonomyView || !authStore.permissions.includes('admin.works.taxonomy.categories.view')) {
        taxonomyAccess.can_view_category = false
        for (const work of items.value) {
          work.taxonomy.category = null
          work.taxonomy.category_tracking = null
        }
        if (detail.value) {
          detail.value.taxonomy.category = null
          detail.value.taxonomy.category_tracking = null
          detail.value.taxonomy_access.can_view_category = false
        }
      }
      if (!hasTaxonomyView || !authStore.permissions.includes('admin.works.taxonomy.tags.view')) {
        taxonomyAccess.can_view_tags = false
        for (const work of items.value) work.taxonomy.tags = null
        if (detail.value) {
          detail.value.taxonomy.tags = null
          detail.value.taxonomy_access.can_view_tags = false
        }
      }
    }
    if (!canManageIndividualTaxonomy.value) closeTaxonomyAssignment()
    syncWorksAccessState()
  },
  { flush: 'post' }
)

watch(
  [allCurrentPageSelected, currentPageSelectedCount],
  async ([allSelected, selectedOnPage]) => {
    await nextTick()
    if (currentPageCheckbox.value) {
      currentPageCheckbox.value.indeterminate = !allSelected && selectedOnPage > 0
    }
  },
  { flush: 'post' }
)

watch(
  [canBulkAssignCategory, canBulkAssignTags],
  ([canCategory, canTags], [couldCategory, couldTags]) => {
    if (!canCategory && !canTags && (couldCategory || couldTags || selectedCount.value > 0)) {
      clearBulkSelection()
    }
  },
  { flush: 'post' }
)

watch(
  () => route.query.work,
  (work) => {
    if (work === undefined) {
      handledDeepLinkWork = null
      return
    }
    if (pageMounted && hasWorksListAccess.value) openDeepLinkedWork()
  },
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  updateViewQuery()
  syncWorksAccessState()
})
</script>

<style scoped>
.ym-works-all-page {
  --ym-violet: #7c3aed;
  --ym-violet-electric: #8b5cf6;
  --ym-magenta: #ec4899;
  --ym-cyan: #22d3ee;
  --ym-emerald: #10b981;
  --ym-amber: #f59e0b;
  --ym-rose: #f43f5e;
  --ym-input-bg: var(--ym-control-bg);
  color: var(--ym-text);
}

:global(.ym-dashboard-light) .ym-works-all-page {
  --ym-card-bg: linear-gradient(145deg, rgba(255, 255, 255, 0.84), rgba(245, 239, 255, 0.72));
  --ym-control-bg: rgba(255, 255, 255, 0.72);
  --ym-input-bg: rgba(255, 255, 255, 0.84);
  --ym-card-border: rgba(124, 58, 237, 0.24);
  --ym-control-border: rgba(124, 58, 237, 0.28);
  --ym-card-shadow: 0 18px 44px rgba(76, 29, 149, 0.14), 0 4px 12px rgba(91, 33, 182, 0.06);
}

:global(.ym-dashboard-dark) .ym-works-all-page {
  --ym-card-bg: linear-gradient(145deg, rgba(10, 18, 38, 0.82), rgba(23, 34, 58, 0.7));
  --ym-control-bg: rgba(15, 23, 42, 0.66);
  --ym-input-bg: rgba(15, 23, 42, 0.76);
  --ym-card-border: rgba(139, 92, 246, 0.24);
  --ym-control-border: rgba(148, 163, 184, 0.28);
  --ym-muted: rgba(226, 232, 240, 0.88);
  --ym-card-shadow: 0 22px 54px rgba(2, 6, 23, 0.34), 0 0 26px rgba(124, 58, 237, 0.05);
}

.ym-works-all-page > :deep(*) {
  scrollbar-color: color-mix(in srgb, var(--ym-violet-electric) 45%, transparent) transparent;
}

.ym-work-detail-content time,
.ym-work-detail-content code,
.ym-work-detail-content [dir='ltr'] {
  direction: ltr;
  unicode-bidi: isolate;
  font-variant-numeric: tabular-nums;
}

.ym-works-all-page :deep(.ym-compact-header),
.ym-works-all-page :deep(.ym-summary-strip),
.ym-works-all-page :deep(.ym-index-filters),
.ym-works-all-page :deep(.ym-smart-list) {
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.ym-works-all-hero,
.ym-works-all-filter-card,
.ym-works-all-table-card,
.ym-works-all-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-works-all-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-works-all-hero::before {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), transparent 44%);
  content: '';
  pointer-events: none;
}

.ym-works-all-hero__grid {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(148, 163, 184, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: linear-gradient(to bottom, black, transparent 86%);
  pointer-events: none;
}

.ym-works-all-hero__glow {
  position: absolute;
  width: 19rem;
  height: 19rem;
  border-radius: 999px;
  filter: blur(18px);
  opacity: 0.24;
  pointer-events: none;
}

.ym-works-all-hero__glow.is-one {
  inset-block-start: -10rem;
  inset-inline-start: -5rem;
  background: #f59e0b;
}

.ym-works-all-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #8b5cf6;
}

.ym-works-all-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-works-all-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-works-all-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-works-all-chip.is-brand {
  color: #fbbf24;
}

.ym-works-all-chip.is-readonly {
  color: #a78bfa;
}

.ym-works-all-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-works-all-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-works-all-description {
  max-width: 58rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-works-all-hero__summary {
  display: grid;
  min-width: min(100%, 220px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-works-all-hero__summary span,
.ym-works-all-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-all-hero__summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
}

.ym-works-all-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  padding: 1rem 1.15rem;
}

.ym-works-all-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #a78bfa 18%, transparent);
  color: #a78bfa;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-works-all-notice p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-all-summary-grid {
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 1rem;
}

.ym-works-all-summary-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--works-all-accent) 17%, transparent), transparent 50%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1rem;
}

.ym-works-all-summary-card span,
.ym-works-all-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-all-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-works-all-filter-card,
.ym-works-all-table-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-works-all-filter-card > header,
.ym-works-all-table-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-works-all-filter-card h2,
.ym-works-all-table-card h2,
.ym-works-all-access-state h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-all-filter-card header p,
.ym-works-all-table-card__head p,
.ym-works-all-access-state p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-works-all-filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-works-all-filter-grid label {
  display: grid;
  align-content: start;
  gap: 0.42rem;
}

.ym-works-all-filter-grid label.is-search {
  grid-column: span 2;
}

.ym-works-all-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-all-filter-grid label > small {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 750;
}

.ym-works-all-filter-grid input,
.ym-works-all-filter-grid select {
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

.ym-works-all-filter-grid input:focus,
.ym-works-all-filter-grid select:focus {
  border-color: #f59e0b;
  box-shadow: 0 0 0 3px color-mix(in srgb, #f59e0b 14%, transparent);
}

.ym-works-all-filter-grid select option {
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
}

.ym-works-all-filter-actions {
  display: flex;
  align-items: flex-end;
}

.ym-works-all-button {
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

.ym-works-all-button.is-primary {
  min-width: 130px;
  background: linear-gradient(135deg, #f59e0b, #ea580c);
  color: #fff;
  box-shadow: 0 12px 28px rgba(245, 158, 11, 0.22);
}

.ym-works-all-button.is-secondary {
  border-color: var(--ym-control-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-works-all-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-works-all-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-works-all-filter-error {
  border: 1px solid rgba(244, 63, 94, 0.34);
  border-radius: 15px;
  background: rgba(244, 63, 94, 0.1);
  color: #fb7185;
  font-size: 12px;
  font-weight: 850;
  margin: 1rem 0 0;
  padding: 0.75rem 0.85rem;
}

.ym-works-all-table-card__head {
  align-items: center;
}

.ym-works-all-table-state {
  display: grid;
  min-width: 130px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: var(--ym-control-bg);
  padding: 0.65rem 0.8rem;
}

.ym-works-all-table-state span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-all-table-state strong {
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 950;
}

.ym-works-all-table-wrap {
  overflow: hidden;
  border: 1px solid var(--ym-soft-border);
  border-radius: 20px;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
}

.ym-works-all-table {
  width: 100%;
  min-width: 0;
  table-layout: fixed;
  border-collapse: collapse;
  background: color-mix(in srgb, var(--ym-card-bg) 88%, transparent);
}

.ym-works-all-table th,
.ym-works-all-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-muted);
  font-size: 12px;
  padding: 0.86rem 0.75rem;
  text-align: start;
  vertical-align: middle;
}

.ym-works-all-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
  font-weight: 950;
  white-space: nowrap;
}

.ym-works-all-table tbody tr {
  transition: background 150ms ease;
}

.ym-works-all-table tbody tr:hover {
  background: var(--ym-row-hover);
}

.ym-works-all-table tbody tr:last-child td {
  border-bottom: 0;
}

.ym-works-all-table th.is-selection,
.ym-works-all-table td.is-selection {
  width: 58px;
  min-width: 58px;
  text-align: center;
}

.ym-works-all-table .is-selection input {
  width: 19px;
  height: 19px;
  accent-color: #8b5cf6;
  cursor: pointer;
}

.ym-works-all-table .is-selection input:disabled {
  cursor: not-allowed;
  opacity: 0.48;
}

.ym-works-all-table .is-selection input:focus-visible {
  outline: 3px solid color-mix(in srgb, #8b5cf6 42%, transparent);
  outline-offset: 3px;
}

.ym-works-all-table th.is-title,
.ym-works-all-table td.is-title {
  width: 310px;
  min-width: 310px;
}

.ym-works-all-table td.is-title strong,
.ym-works-all-table td.is-title code,
.ym-works-all-table td.is-title small,
.ym-works-all-person strong,
.ym-works-all-person small {
  display: block;
}

.ym-works-all-table th.is-taxonomy,
.ym-works-all-table td.is-taxonomy {
  width: 280px;
  min-width: 280px;
}

.ym-work-taxonomy-cell,
.ym-work-taxonomy-category {
  display: grid;
  gap: 0.3rem;
}

.ym-work-taxonomy-category strong {
  color: var(--ym-text);
  font-size: 11px;
  font-weight: 950;
}

.ym-work-taxonomy-category code {
  color: #8b5cf6;
  font-size: 9px;
  overflow-wrap: anywhere;
}

.ym-work-taxonomy-state {
  width: max-content;
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 9px;
  font-weight: 950;
  padding: 0.25rem 0.45rem;
}

.ym-work-taxonomy-state.is-active {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.ym-work-taxonomy-state.is-disabled,
.ym-work-taxonomy-state.is-legacy {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.ym-work-taxonomy-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
  border-top: 1px solid var(--ym-soft-border);
  margin-top: 0.3rem;
  padding-top: 0.45rem;
}

.ym-work-taxonomy-tag,
.ym-work-taxonomy-more {
  border: 1px solid rgba(139, 92, 246, 0.26);
  border-radius: 999px;
  background: rgba(139, 92, 246, 0.08);
  color: var(--ym-text);
  font-size: 9px;
  font-weight: 850;
  padding: 0.25rem 0.4rem;
}

.ym-work-taxonomy-tag.is-disabled {
  border-color: rgba(245, 158, 11, 0.3);
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.ym-work-taxonomy-unavailable,
.ym-work-taxonomy-tags small {
  color: var(--ym-muted);
  font-size: 10px;
}

.ym-works-all-table td.is-title strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-all-table td.is-title code {
  color: #fbbf24;
  font-size: 10px;
  margin-top: 0.2rem;
  overflow-wrap: anywhere;
}

.ym-works-all-table td.is-title small {
  max-width: 290px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.55;
  margin-top: 0.35rem;
}

.ym-works-all-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  padding: 0;
}

.ym-works-all-sort span {
  display: inline-grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  border-radius: 7px;
  background: color-mix(in srgb, #f59e0b 13%, transparent);
  color: #fbbf24;
}

.ym-works-all-badge {
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

.ym-works-all-badge.is-draft,
.ym-works-all-badge.is-hidden,
.ym-works-all-badge.is-archived {
  border-color: rgba(148, 163, 184, 0.35);
  background: rgba(100, 116, 139, 0.13);
  color: #cbd5e1;
}

.ym-works-all-badge.is-submitted,
.ym-works-all-badge.is-in-review {
  border-color: rgba(56, 189, 248, 0.35);
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
}

.ym-works-all-badge.is-changes-requested {
  border-color: rgba(245, 158, 11, 0.38);
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}

.ym-works-all-badge.is-approved,
.ym-works-all-badge.is-published,
.ym-works-all-badge.is-public,
.ym-works-all-badge.is-yes {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
}

.ym-works-all-badge.is-rejected {
  border-color: rgba(244, 63, 94, 0.36);
  background: rgba(244, 63, 94, 0.12);
  color: #fb7185;
}

.ym-works-all-badge.is-no {
  color: var(--ym-muted);
}

.ym-works-all-person {
  min-width: 130px;
}

.ym-works-all-person strong {
  color: var(--ym-text);
  font-size: 11px;
  font-weight: 900;
}

.ym-works-all-person small {
  color: var(--ym-muted);
  font-size: 9px;
  margin-top: 0.18rem;
}

.ym-works-all-count {
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

.ym-works-all-count.is-alert {
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
}

.ym-works-all-table time {
  display: inline-block;
  min-width: 125px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.5;
}

.ym-works-all-table th.is-action,
.ym-works-all-table td.is-action {
  position: sticky;
  inset-inline-end: 0;
  z-index: 1;
  min-width: 130px;
  background: var(--ym-dropdown-bg);
}

.ym-works-all-table th.is-action {
  z-index: 3;
}

.ym-works-all-details-button {
  width: 100%;
  min-height: 38px;
  border: 1px solid rgba(245, 158, 11, 0.4);
  border-radius: 12px;
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
  transition: background 160ms ease, transform 160ms ease;
}

.ym-works-all-create-button {
  display: inline-flex;
  width: fit-content;
  margin-top: 1rem;
  min-height: 42px;
  align-items: center;
  border: 1px solid rgba(245, 158, 11, 0.45);
  border-radius: 13px;
  background: #f59e0b;
  color: #111827;
  font-size: 12px;
  font-weight: 950;
  padding: 0.7rem 1rem;
  text-decoration: none;
}

.ym-works-all-edit-button {
  display: grid;
  min-height: 38px;
  place-items: center;
  border: 1px solid rgba(16, 185, 129, 0.4);
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
  text-decoration: none;
}

.ym-works-all-create-button:focus-visible,
.ym-works-all-edit-button:focus-visible {
  outline: 3px solid rgba(245, 158, 11, 0.4);
  outline-offset: 2px;
}

.ym-works-all-row-actions {
  display: grid;
  gap: 0.45rem;
}

.ym-works-all-taxonomy-button {
  min-height: 38px;
  border: 1px solid rgba(139, 92, 246, 0.4);
  border-radius: 12px;
  background: rgba(139, 92, 246, 0.12);
  color: #a78bfa;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
}

.ym-works-all-taxonomy-button:hover {
  background: rgba(139, 92, 246, 0.2);
}

.ym-works-all-details-button:hover:not(:disabled) {
  background: rgba(245, 158, 11, 0.2);
  transform: translateY(-1px);
}

.ym-works-all-details-button:disabled {
  cursor: not-allowed;
  filter: grayscale(0.6);
  opacity: 0.45;
}

.ym-works-all-state,
.ym-works-all-access-state,
.ym-work-detail-state {
  display: grid;
  min-height: 240px;
  place-items: center;
  align-content: center;
  gap: 0.7rem;
  color: var(--ym-muted);
  padding: 2rem;
  text-align: center;
}

.ym-works-all-state h3,
.ym-work-detail-state h3 {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-all-state p,
.ym-work-detail-state p {
  max-width: 34rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-all-state.is-error,
.ym-work-detail-state.is-error,
.ym-works-all-access-state.is-forbidden {
  color: #fb7185;
}

.ym-works-all-state__icon,
.ym-works-all-access-state__icon,
.ym-works-all-empty-icon {
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

.ym-works-all-empty-icon {
  background: rgba(148, 163, 184, 0.13);
  color: var(--ym-muted);
}

.ym-works-all-spinner {
  width: 2.35rem;
  height: 2.35rem;
  border: 3px solid color-mix(in srgb, #f59e0b 20%, transparent);
  border-top-color: #f59e0b;
  border-radius: 999px;
  animation: ym-works-all-spin 760ms linear infinite;
}

.ym-works-all-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.ym-works-all-selection-message {
  margin: 14px 0 0;
  padding: 11px 13px;
  border: 1px solid rgba(139, 92, 246, 0.34);
  border-radius: 13px;
  color: #a78bfa;
  background: rgba(139, 92, 246, 0.1);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-all-selection-message.is-warning {
  border-color: rgba(245, 158, 11, 0.36);
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.1);
}

.ym-works-all-selection-message button {
  margin-inline-start: 8px;
  border: 0;
  color: inherit;
  background: none;
  font-weight: 950;
  text-decoration: underline;
  cursor: pointer;
}

.ym-works-all-pagination > div {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-all-pagination > div strong {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
}

.ym-works-all-pagination nav {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ym-works-all-pagination nav span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-work-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  justify-content: flex-end;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(6px);
}

.ym-work-detail-drawer {
  width: min(660px, 100%);
  height: 100dvh;
  overflow-y: auto;
  border-inline-start: 1px solid var(--ym-card-border);
  background: var(--ym-dropdown-bg);
  box-shadow: -24px 0 64px rgba(2, 6, 23, 0.38);
  color: var(--ym-text);
}

.ym-work-detail-drawer__head {
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

.ym-work-detail-drawer__head span,
.ym-work-detail-drawer__head code {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-work-detail-drawer__head h2 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.35;
  margin: 0.2rem 0;
}

.ym-work-detail-drawer__close {
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

.ym-work-detail-content {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
}

.ym-work-detail-intro,
.ym-work-detail-section {
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-card-bg);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
  padding: 1rem;
}

.ym-work-detail-intro > div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.ym-work-detail-intro h3 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.45;
  margin: 0.8rem 0 0.25rem;
}

.ym-work-detail-intro code {
  color: #fbbf24;
  font-size: 11px;
  overflow-wrap: anywhere;
}

.ym-work-detail-intro p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.8;
  margin: 0.75rem 0 0;
}

.ym-work-detail-section > header {
  margin-bottom: 0.8rem;
}

.ym-work-detail-section > header h3 {
  color: var(--ym-text);
  font-size: 1rem;
  font-weight: 950;
  margin: 0;
}

.ym-work-detail-section > header p {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 750;
  line-height: 1.65;
  margin: 0.25rem 0 0;
}

.ym-work-detail-taxonomy-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.ym-work-detail-taxonomy {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 0.7rem;
}

.ym-work-detail-taxonomy > article {
  display: grid;
  align-content: start;
  gap: 0.35rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: var(--ym-control-bg);
  padding: 0.8rem;
}

.ym-work-detail-taxonomy > article > span,
.ym-work-detail-taxonomy > article > small,
.ym-work-detail-taxonomy > article > p {
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.55;
  margin: 0;
}

.ym-work-detail-taxonomy > article > strong {
  font-size: 12px;
}

.ym-work-detail-taxonomy > article > code {
  color: #8b5cf6;
  font-size: 10px;
}

.ym-work-detail-taxonomy > article > b {
  width: max-content;
  border-radius: 999px;
  font-size: 10px;
  padding: 0.28rem 0.5rem;
}

.ym-work-detail-taxonomy .is-active {
  background: rgba(16, 185, 129, 0.12);
  color: #10b981;
}

.ym-work-detail-taxonomy .is-disabled,
.ym-work-detail-taxonomy .is-legacy {
  background: rgba(245, 158, 11, 0.12);
  color: #f59e0b;
}

.ym-work-detail-tag-list {
  display: grid;
  gap: 0.45rem;
}

.ym-work-detail-tag-list > span {
  display: grid;
  gap: 0.18rem;
  border: 1px solid rgba(139, 92, 246, 0.24);
  border-radius: 12px;
  padding: 0.55rem;
}

.ym-work-detail-tag-list > span.is-disabled {
  border-color: rgba(245, 158, 11, 0.3);
}

.ym-work-detail-tag-list code,
.ym-work-detail-tag-list small {
  color: var(--ym-muted);
  font-size: 9px;
}

.ym-work-detail-access-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-work-detail-access-grid > span {
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

.ym-work-detail-access-grid > span strong {
  font-size: 12px;
  font-weight: 950;
}

.ym-work-detail-access-grid > span.is-allowed strong {
  color: #34d399;
}

.ym-work-detail-access-grid > span.is-denied strong {
  color: #94a3b8;
}

.ym-work-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
}

.ym-work-detail-grid > div,
.ym-work-detail-people article,
.ym-work-detail-notes > div {
  min-width: 0;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  padding: 0.7rem;
}

.ym-work-detail-grid dt,
.ym-work-detail-notes dt,
.ym-work-detail-people span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-work-detail-grid dd,
.ym-work-detail-notes dd {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 900;
  line-height: 1.65;
  margin: 0.3rem 0 0;
  overflow-wrap: anywhere;
}

.ym-work-detail-grid.is-lifecycle {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ym-work-detail-people {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-work-detail-people strong,
.ym-work-detail-people small {
  display: block;
}

.ym-work-detail-people strong {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
  margin-top: 0.3rem;
}

.ym-work-detail-people small {
  color: var(--ym-muted);
  font-size: 10px;
  margin-top: 0.18rem;
}

.ym-work-detail-media {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
  padding: 0.8rem;
}

.ym-work-detail-media strong.is-present {
  color: #34d399;
}

.ym-work-detail-media strong.is-absent {
  color: #94a3b8;
}

.ym-work-detail-notes {
  display: grid;
  gap: 0.65rem;
  margin: 0;
}

.ym-work-detail-section.is-private {
  border-color: color-mix(in srgb, #a78bfa 30%, var(--ym-soft-border));
}

.ym-work-detail-unavailable {
  border: 1px dashed var(--ym-control-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
  line-height: 1.7;
  margin: 0;
  padding: 0.8rem;
}

@keyframes ym-works-all-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1280px) {
  .ym-works-all-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .ym-works-all-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .ym-works-all-hero__content,
  .ym-works-all-filter-card > header,
  .ym-works-all-table-card__head,
  .ym-works-all-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-all-hero__summary {
    min-width: 0;
  }

  .ym-works-all-summary-grid,
  .ym-works-all-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-all-pagination nav {
    justify-content: space-between;
  }
}

@media (max-width: 640px) {
  .ym-works-all-page {
    font-size: 14px;
  }

  .ym-works-all-hero,
  .ym-works-all-filter-card,
  .ym-works-all-table-card,
  .ym-works-all-access-state {
    border-radius: 22px;
  }

  .ym-works-all-hero h1 {
    font-size: 2rem;
  }

  .ym-works-all-notice {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-works-all-summary-grid,
  .ym-works-all-filter-grid,
  .ym-work-detail-access-grid,
  .ym-work-detail-grid,
  .ym-work-detail-grid.is-lifecycle,
  .ym-work-detail-people,
  .ym-work-detail-taxonomy {
    grid-template-columns: 1fr;
  }

  .ym-work-detail-taxonomy-head {
    display: grid;
  }

  .ym-works-all-filter-grid label.is-search {
    grid-column: auto;
  }

  .ym-works-all-filter-actions,
  .ym-works-all-filter-actions .ym-works-all-button {
    width: 100%;
  }

  .ym-works-all-pagination nav {
    display: grid;
    grid-template-columns: 1fr;
    text-align: center;
  }

  .ym-work-detail-drawer__head,
  .ym-work-detail-content {
    padding-inline: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-works-all-spinner {
    animation-duration: 1.8s;
  }

  .ym-works-all-button,
  .ym-works-all-details-button,
  .ym-works-all-table tbody tr {
    transition: none;
  }
}
</style>
