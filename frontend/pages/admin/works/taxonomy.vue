<template>
  <div
    class="ym-taxonomy-page space-y-7"
    :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'"
  >
    <section class="ym-taxonomy-hero">
      <div class="ym-taxonomy-hero__glow is-one" />
      <div class="ym-taxonomy-hero__glow is-two" />
      <div class="ym-taxonomy-hero__grid" aria-hidden="true" />

      <div class="ym-taxonomy-hero__content">
        <div>
          <div class="ym-taxonomy-chips">
            <span class="ym-taxonomy-chip is-brand">Yemen Motion</span>
            <span class="ym-taxonomy-chip is-readonly">{{ copy.readonly }}</span>
          </div>
          <p class="ym-taxonomy-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-taxonomy-description">
            {{ copy.descriptionBefore }}
            <code dir="ltr">category_id</code>
            {{ copy.descriptionAfter }}
          </p>
        </div>

        <div class="ym-taxonomy-hero__summary">
          <span>{{ copy.totalCategories }}</span>
          <strong>{{ summary ? formatNumber(summary.total_categories) : '—' }}</strong>
          <small v-if="summary">
            {{ formatNumber(summary.total_works) }} {{ copy.worksInScope }}
          </small>
          <small v-else>{{ copy.categoryBuckets }}</small>
        </div>
      </div>
    </section>

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
      <aside class="ym-taxonomy-notice" role="note">
        <span>{{ copy.readonly }}</span>
        <div>
          <strong>{{ copy.noticeTitle }}</strong>
          <p>{{ copy.notice }}</p>
        </div>
      </aside>

      <section
        v-if="summary"
        class="ym-taxonomy-summary-grid"
        :aria-label="copy.summaryLabel"
      >
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="ym-taxonomy-summary-card"
          :class="{
            'is-alert': ['uncategorized_buckets', 'uncategorized_works', 'reported_categories', 'hidden_categories', 'total_reports'].includes(card.key) && card.value > 0,
            'is-promoted': card.key === 'promoted_categories' && card.value > 0
          }"
          :style="{ '--taxonomy-accent': card.color }"
        >
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
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
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-taxonomy-button is-secondary"
            :disabled="loading"
            :title="copy.resetHint"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </header>

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
            <small>{{ copy.searchHint }}</small>
          </label>

          <label>
            <span>{{ copy.categoryId }}</span>
            <input
              v-model="filters.category_id"
              type="number"
              step="1"
              inputmode="numeric"
              dir="ltr"
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
            <input
              v-model.trim="filters.media_type"
              type="text"
              maxlength="40"
              placeholder="image"
              dir="ltr"
            />
          </label>

          <label>
            <span>{{ copy.onlyUncategorized }}</span>
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

          <label>
            <span>{{ copy.onlyReported }}</span>
            <select v-model="filters.only_reported">
              <option
                v-for="option in booleanOptions"
                :key="'reported-' + option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.onlyPromoted }}</span>
            <select v-model="filters.only_promoted">
              <option
                v-for="option in booleanOptions"
                :key="'promoted-' + option.value"
                :value="option.value"
              >
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.from }}</span>
            <input v-model="filters.from" type="date" />
            <small>{{ copy.updatedRangeHint }}</small>
          </label>

          <label>
            <span>{{ copy.to }}</span>
            <input v-model="filters.to" type="date" />
            <small>{{ copy.updatedRangeHint }}</small>
          </label>

          <label>
            <span>{{ copy.perPage }}</span>
            <select v-model.number="filters.per_page">
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </label>

          <div class="ym-taxonomy-filter-actions">
            <button
              type="submit"
              class="ym-taxonomy-button is-primary"
              :disabled="loading"
            >
              {{ copy.apply }}
            </button>
          </div>
        </form>

        <p v-if="filterError" class="ym-taxonomy-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-taxonomy-table-card">
        <header class="ym-taxonomy-table-card__head">
          <div>
            <h2>{{ copy.tableTitle }}</h2>
            <p>{{ copy.tableCopy }}</p>
          </div>
          <div class="ym-taxonomy-table-state">
            <span>{{ copy.currentPage }}</span>
            <strong>
              {{ formatNumber(pagination.current_page) }} /
              {{ formatNumber(pagination.last_page) }}
            </strong>
          </div>
        </header>

        <div
          v-if="loading"
          class="ym-taxonomy-state"
          role="status"
          aria-live="polite"
        >
          <span class="ym-taxonomy-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-taxonomy-state is-error" role="alert">
          <span class="ym-taxonomy-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button
            type="button"
            class="ym-taxonomy-button is-secondary"
            @click="fetchTaxonomy"
          >
            {{ copy.retry }}
          </button>
        </div>

        <div
          v-else-if="hasLoaded && items.length === 0"
          class="ym-taxonomy-state"
          role="status"
        >
          <span class="ym-taxonomy-empty-icon" aria-hidden="true">0</span>
          <h3>{{ copy.emptyTitle }}</h3>
          <p>{{ copy.emptyCopy }}</p>
        </div>

        <div v-else-if="hasLoaded" class="ym-taxonomy-table-wrap">
          <table class="ym-taxonomy-table">
            <thead>
              <tr>
                <th class="is-label">{{ copy.category }}</th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('category_id')"
                  >
                    {{ copy.categoryId }}
                    <span aria-hidden="true">{{ sortIndicator('category_id') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('works_count')"
                  >
                    {{ copy.worksCount }}
                    <span aria-hidden="true">{{ sortIndicator('works_count') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('published_count')"
                  >
                    {{ copy.publishedCount }}
                    <span aria-hidden="true">{{ sortIndicator('published_count') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('hidden_count')"
                  >
                    {{ copy.hiddenCount }}
                    <span aria-hidden="true">{{ sortIndicator('hidden_count') }}</span>
                  </button>
                </th>
                <th>{{ copy.reviewQueueCount }}</th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('reported_count')"
                  >
                    {{ copy.reportedCount }}
                    <span aria-hidden="true">{{ sortIndicator('reported_count') }}</span>
                  </button>
                </th>
                <th>{{ copy.featuredCount }}</th>
                <th>{{ copy.pinnedCount }}</th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('total_reports')"
                  >
                    {{ copy.totalReports }}
                    <span aria-hidden="true">{{ sortIndicator('total_reports') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('total_views')"
                  >
                    {{ copy.totalViews }}
                    <span aria-hidden="true">{{ sortIndicator('total_views') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('total_likes')"
                  >
                    {{ copy.totalLikes }}
                    <span aria-hidden="true">{{ sortIndicator('total_likes') }}</span>
                  </button>
                </th>
                <th>
                  <button
                    type="button"
                    class="ym-taxonomy-sort"
                    @click="changeSort('latest_work_at')"
                  >
                    {{ copy.latestWorkAt }}
                    <span aria-hidden="true">{{ sortIndicator('latest_work_at') }}</span>
                  </button>
                </th>
                <th>{{ copy.uncategorizedFlag }}</th>
                <th>{{ copy.hasReportsFlag }}</th>
                <th>{{ copy.hasPublishedFlag }}</th>
                <th>{{ copy.hasHiddenFlag }}</th>
                <th>{{ copy.promotedFlag }}</th>
                <th>{{ copy.needsAttentionFlag }}</th>
                <th class="is-action">{{ copy.readAction }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="bucket in items"
                :key="bucket.category_id === null ? 'uncategorized' : bucket.category_id"
                :class="{
                  'is-uncategorized-row': bucket.taxonomy_flags.uncategorized,
                  'needs-attention-row': bucket.taxonomy_flags.needs_attention
                }"
              >
                <td class="is-label">
                  <strong>{{ bucket.label }}</strong>
                  <small>
                    {{
                      bucket.taxonomy_flags.uncategorized
                        ? copy.uncategorizedHint
                        : copy.categorizedHint
                    }}
                  </small>
                </td>
                <td>
                  <code v-if="bucket.category_id !== null" dir="ltr">
                    #{{ bucket.category_id }}
                  </code>
                  <span v-else class="ym-taxonomy-flag is-uncategorized">
                    {{ copy.uncategorized }}
                  </span>
                </td>
                <td><span class="ym-taxonomy-count is-strong">{{ formatNumber(bucket.works_count) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.published_count) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.hidden_count) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.review_queue_count) }}</span></td>
                <td><span class="ym-taxonomy-count is-alert">{{ formatNumber(bucket.reported_count) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.featured_count) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.pinned_count) }}</span></td>
                <td><span class="ym-taxonomy-count is-alert">{{ formatNumber(bucket.total_reports) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.total_views) }}</span></td>
                <td><span class="ym-taxonomy-count">{{ formatNumber(bucket.total_likes) }}</span></td>
                <td>
                  <time :datetime="bucket.latest_work_at || undefined">
                    {{ formatDateTime(bucket.latest_work_at) }}
                  </time>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('uncategorized', bucket.taxonomy_flags.uncategorized)"
                  >
                    {{ flagLabel('uncategorized', bucket.taxonomy_flags.uncategorized) }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('has_reports', bucket.taxonomy_flags.has_reports)"
                  >
                    {{ flagLabel('has_reports', bucket.taxonomy_flags.has_reports) }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('has_published', bucket.taxonomy_flags.has_published)"
                  >
                    {{ flagLabel('has_published', bucket.taxonomy_flags.has_published) }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('has_hidden', bucket.taxonomy_flags.has_hidden)"
                  >
                    {{ flagLabel('has_hidden', bucket.taxonomy_flags.has_hidden) }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('is_promoted', bucket.taxonomy_flags.is_promoted)"
                  >
                    {{ flagLabel('is_promoted', bucket.taxonomy_flags.is_promoted) }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-taxonomy-flag"
                    :class="flagClass('needs_attention', bucket.taxonomy_flags.needs_attention)"
                  >
                    {{ flagLabel('needs_attention', bucket.taxonomy_flags.needs_attention) }}
                  </span>
                </td>
                <td class="is-action">
                  <button
                    type="button"
                    class="ym-taxonomy-details-button"
                    :aria-label="copy.openSummaryFor(bucket.label)"
                    @click="openSummary(bucket)"
                  >
                    {{ copy.viewCategorySummary }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-else
          class="ym-taxonomy-state"
          role="status"
          aria-live="polite"
        >
          <span class="ym-taxonomy-spinner" aria-hidden="true" />
          <p>{{ copy.preparingCopy }}</p>
        </div>

        <footer v-if="hasLoaded && !error" class="ym-taxonomy-pagination">
          <div>
            <span>{{ copy.paginationTotal }}</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small>
          </div>
          <nav :aria-label="copy.paginationLabel">
            <button
              type="button"
              class="ym-taxonomy-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              {{ copy.previous }}
            </button>
            <span>{{ copy.pageOf(pagination.current_page, pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-taxonomy-button is-secondary"
              :disabled="loading || pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
            >
              {{ copy.next }}
            </button>
          </nav>
        </footer>
      </section>
    </template>

    <div
      v-if="drawerOpen && selectedBucket"
      class="ym-taxonomy-detail-backdrop"
      role="presentation"
      @click.self="closeSummary"
    >
      <section
        class="ym-taxonomy-detail-drawer"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="drawerTitleId"
        tabindex="-1"
        @keydown.esc="closeSummary"
      >
        <header class="ym-taxonomy-detail-drawer__head">
          <div>
            <span>{{ copy.drawerReadonly }}</span>
            <h2 :id="drawerTitleId">{{ selectedBucket.label }}</h2>
            <code v-if="selectedBucket.category_id !== null" dir="ltr">
              #{{ selectedBucket.category_id }}
            </code>
            <small v-else>{{ copy.uncategorized }}</small>
          </div>
          <button
            type="button"
            class="ym-taxonomy-detail-drawer__close"
            :aria-label="copy.close"
            :title="copy.close"
            @click="closeSummary"
          >
            ×
          </button>
        </header>

        <div class="ym-taxonomy-detail-content">
          <section
            class="ym-taxonomy-detail-intro"
            :class="{ 'is-attention': selectedBucket.taxonomy_flags.needs_attention }"
          >
            <div>
              <span
                v-for="flag in selectedFlagItems"
                :key="flag.key"
                class="ym-taxonomy-flag"
                :class="flagClass(flag.key, flag.active)"
              >
                {{ flag.stateLabel }}
              </span>
            </div>
            <h3>{{ selectedBucket.label }}</h3>
            <p>{{ copy.drawerCopy }}</p>
          </section>

          <section class="ym-taxonomy-detail-section">
            <header><h3>{{ copy.bucketIdentity }}</h3></header>
            <dl class="ym-taxonomy-detail-grid">
              <div>
                <dt>{{ copy.category }}</dt>
                <dd>{{ selectedBucket.label }}</dd>
              </div>
              <div>
                <dt>{{ copy.categoryId }}</dt>
                <dd v-if="selectedBucket.category_id !== null" dir="ltr">
                  #{{ selectedBucket.category_id }}
                </dd>
                <dd v-else>{{ copy.uncategorized }}</dd>
              </div>
              <div>
                <dt>{{ copy.latestWorkAt }}</dt>
                <dd>
                  <time :datetime="selectedBucket.latest_work_at || undefined">
                    {{ formatDateTime(selectedBucket.latest_work_at) }}
                  </time>
                </dd>
              </div>
            </dl>
          </section>

          <section class="ym-taxonomy-detail-section">
            <header><h3>{{ copy.bucketCounts }}</h3></header>
            <dl class="ym-taxonomy-detail-grid is-counts">
              <div v-for="metric in selectedMetricItems" :key="metric.key">
                <dt>{{ metric.label }}</dt>
                <dd>{{ formatNumber(metric.value) }}</dd>
              </div>
            </dl>
          </section>

          <section class="ym-taxonomy-detail-section">
            <header>
              <h3>{{ copy.taxonomyFlags }}</h3>
              <p>{{ copy.taxonomyFlagsCopy }}</p>
            </header>
            <div class="ym-taxonomy-detail-flags">
              <span
                v-for="flag in selectedFlagItems"
                :key="'detail-' + flag.key"
                :class="flag.active ? 'is-active' : 'is-inactive'"
              >
                {{ flag.label }}
                <strong>{{ flag.stateLabel }}</strong>
              </span>
            </div>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = '' | 'public' | 'hidden'
type BooleanFilter = '' | '1' | '0'
type SortDirection = 'asc' | 'desc'
type PageSize = 15 | 25 | 50
type TaxonomySortKey = 'works_count' | 'category_id' | 'latest_work_at' | 'reported_count' | 'published_count' | 'hidden_count' | 'total_reports' | 'total_views' | 'total_likes'
type TaxonomyFlagKey = keyof TaxonomyFlags

interface TaxonomyFlags {
  uncategorized: boolean
  has_reports: boolean
  has_published: boolean
  has_hidden: boolean
  is_promoted: boolean
  needs_attention: boolean
}

interface TaxonomyBucket {
  category_id: number | null
  label: string
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
}

interface TagSupport {
  available: boolean
  reason: string
}

interface TaxonomyData {
  items: TaxonomyBucket[]
  pagination: TaxonomyPagination
  summary: TaxonomySummary
  filters: Record<string, unknown>
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
    readonly: 'قراءة تنظيمية فقط',
    kicker: 'إدارة تصنيفات الأعمال',
    title: 'التصنيفات والوسوم',
    descriptionBefore: 'قراءة تنظيمية لتجميعات التصنيف المبنية من قيمة',
    descriptionAfter: 'الحالية في الأعمال، دون عرض صفوف الأعمال المفردة.',
    totalCategories: 'إجمالي التجميعات',
    categoryBuckets: 'تجميعات التصنيف الحالية',
    worksInScope: 'عملًا ضمن النطاق',
    authLoadingTitle: 'جارٍ التحقق من صلاحية التصنيفات',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى التصنيفات والوسوم غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب الصلاحيات المطلوبة لقراءة تصنيفات الأعمال. لم تتم محاولة تحميل البيانات.',
    noticeTitle: 'لا توجد إجراءات تنفيذية في هذه المرحلة',
    notice: 'هذه الصفحة للقراءة والتنظيم فقط؛ لا تتضمن إنشاء أو تعديل أو دمج أو تعطيل أو تعيين جماعي أو حذف التصنيفات والوسوم.',
    summaryLabel: 'ملخص تصنيفات الأعمال المطابقة للفلاتر',
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
    tagsUnavailableTitle: 'دعم الوسوم غير متاح حاليًا',
    tagsUnavailableCopy: 'لن تظهر وسوم وهمية أو أدوات لإضافة الوسوم أو دمجها.',
    filtersTitle: 'بحث وفلاتر التصنيفات',
    filtersCopy: 'ضيّق التجميعات باستخدام معاملات التصنيفات المعتمدة فقط.',
    search: 'البحث',
    searchPlaceholder: 'اسم التجميع أو غير مصنف',
    searchHint: 'بحد أقصى 80 حرفًا، ويطابق تسمية التجميع فقط.',
    categoryId: 'category_id',
    status: 'حالة العمل',
    visibility: 'حالة الظهور',
    mediaType: 'نوع الوسائط',
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
    readAction: 'إجراء القراءة',
    uncategorized: 'غير مصنف',
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
    pageOf: (page: number, last: number) => 'الصفحة ' + page + ' من ' + last,
    drawerReadonly: 'ملخص للقراءة فقط',
    close: 'إغلاق الملخص',
    drawerCopy: 'هذا الملخص مبني بالكامل من بيانات التجميع المحدد ولا يحمّل أي بيانات إضافية.',
    bucketIdentity: 'هوية التجميع',
    bucketCounts: 'مؤشرات التجميع',
    taxonomyFlags: 'علامات التصنيف',
    taxonomyFlagsCopy: 'تعكس هذه العلامات حالة التجميع المحسوبة في استجابة الخادم.'
  },
  en: {
    readonly: 'Organizational read only',
    kicker: 'Works taxonomy management',
    title: 'Categories and Tags',
    descriptionBefore: 'An organizational view of category buckets derived from the current',
    descriptionAfter: 'value on works, without displaying individual work rows.',
    totalCategories: 'Total buckets',
    categoryBuckets: 'Current category buckets',
    worksInScope: 'works in scope',
    authLoadingTitle: 'Checking taxonomy access',
    authLoadingCopy: 'Waiting for user-session initialization before making any data request.',
    forbiddenTitle: 'Categories and tags access is unavailable',
    forbiddenCopy: 'This account lacks the permissions required to read works taxonomy. No data request was made.',
    noticeTitle: 'No operational actions are available at this stage',
    notice: 'This page is read-only and does not create, edit, merge, disable, bulk assign, or delete categories or tags.',
    summaryLabel: 'Summary of works taxonomy matching the filters',
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
    tagsUnavailableTitle: 'Tag support is currently unavailable',
    tagsUnavailableCopy: 'No placeholder tags or tag create and merge tools are shown.',
    filtersTitle: 'Taxonomy search and filters',
    filtersCopy: 'Narrow the buckets using only the approved taxonomy parameters.',
    search: 'Search',
    searchPlaceholder: 'Bucket label or Uncategorized',
    searchHint: 'Up to 80 characters and matched against the bucket label only.',
    categoryId: 'category_id',
    status: 'Work status',
    visibility: 'Visibility',
    mediaType: 'Media type',
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
    readAction: 'Read action',
    uncategorized: 'Uncategorized',
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
    pageOf: (page: number, last: number) => 'Page ' + page + ' of ' + last,
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
const hasTaxonomyAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.taxonomy.view')
    && authStore.permissions.includes('admin.works.taxonomy.categories.view')
})
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
const page = ref(1)
const loading = ref(false)
const hasLoaded = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)

const drawerOpen = ref(false)
const selectedBucket = ref<TaxonomyBucket | null>(null)
const drawerTitleId = 'ym-taxonomy-bucket-summary-title'

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let requestRevision = 0

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
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

const summaryCards = computed(() => {
  const current = summary.value

  return [
    { key: 'total_categories', label: copy.value.summaryTotalCategories, value: current?.total_categories ?? 0, hint: copy.value.summaryTotalCategoriesHint, color: '#8b5cf6' },
    { key: 'categorized_categories', label: copy.value.categorizedCategories, value: current?.categorized_categories ?? 0, hint: copy.value.categorizedCategoriesHint, color: '#38bdf8' },
    { key: 'uncategorized_buckets', label: copy.value.uncategorizedBuckets, value: current?.uncategorized_buckets ?? 0, hint: copy.value.uncategorizedBucketsHint, color: '#f97316' },
    { key: 'total_works', label: copy.value.totalWorks, value: current?.total_works ?? 0, hint: copy.value.totalWorksHint, color: '#6366f1' },
    { key: 'categorized_works', label: copy.value.categorizedWorks, value: current?.categorized_works ?? 0, hint: copy.value.categorizedWorksHint, color: '#14b8a6' },
    { key: 'uncategorized_works', label: copy.value.uncategorizedWorks, value: current?.uncategorized_works ?? 0, hint: copy.value.uncategorizedWorksHint, color: '#fb923c' },
    { key: 'reported_categories', label: copy.value.reportedCategories, value: current?.reported_categories ?? 0, hint: copy.value.reportedCategoriesHint, color: '#f43f5e' },
    { key: 'promoted_categories', label: copy.value.promotedCategories, value: current?.promoted_categories ?? 0, hint: copy.value.promotedCategoriesHint, color: '#d946ef' },
    { key: 'published_categories', label: copy.value.publishedCategories, value: current?.published_categories ?? 0, hint: copy.value.publishedCategoriesHint, color: '#10b981' },
    { key: 'hidden_categories', label: copy.value.hiddenCategories, value: current?.hidden_categories ?? 0, hint: copy.value.hiddenCategoriesHint, color: '#64748b' },
    { key: 'total_reports', label: copy.value.totalReports, value: current?.total_reports ?? 0, hint: copy.value.totalReportsHint, color: '#e11d48' },
    { key: 'total_views', label: copy.value.totalViews, value: current?.total_views ?? 0, hint: copy.value.totalViewsHint, color: '#0ea5e9' },
    { key: 'total_likes', label: copy.value.totalLikes, value: current?.total_likes ?? 0, hint: copy.value.totalLikesHint, color: '#ec4899' }
  ]
})

const selectedMetricItems = computed(() => {
  const bucket = selectedBucket.value
  if (!bucket) return []

  return [
    { key: 'works_count', label: copy.value.worksCount, value: bucket.works_count },
    { key: 'published_count', label: copy.value.publishedCount, value: bucket.published_count },
    { key: 'hidden_count', label: copy.value.hiddenCount, value: bucket.hidden_count },
    { key: 'review_queue_count', label: copy.value.reviewQueueCount, value: bucket.review_queue_count },
    { key: 'reported_count', label: copy.value.reportedCount, value: bucket.reported_count },
    { key: 'featured_count', label: copy.value.featuredCount, value: bucket.featured_count },
    { key: 'pinned_count', label: copy.value.pinnedCount, value: bucket.pinned_count },
    { key: 'total_reports', label: copy.value.totalReports, value: bucket.total_reports },
    { key: 'total_views', label: copy.value.totalViews, value: bucket.total_views },
    { key: 'total_likes', label: copy.value.totalLikes, value: bucket.total_likes }
  ]
})

const selectedFlagItems = computed(() => {
  const bucket = selectedBucket.value
  if (!bucket) return []

  const definitions: Array<{ key: TaxonomyFlagKey; label: string }> = [
    { key: 'uncategorized', label: copy.value.uncategorizedFlag },
    { key: 'has_reports', label: copy.value.hasReportsFlag },
    { key: 'has_published', label: copy.value.hasPublishedFlag },
    { key: 'has_hidden', label: copy.value.hasHiddenFlag },
    { key: 'is_promoted', label: copy.value.promotedFlag },
    { key: 'needs_attention', label: copy.value.needsAttentionFlag }
  ]

  return definitions.map(definition => ({
    ...definition,
    active: bucket.taxonomy_flags[definition.key],
    stateLabel: flagLabel(definition.key, bucket.taxonomy_flags[definition.key])
  }))
})

function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(value)
}

function formatDateTime(value: string | null): string {
  if (!value) return '—'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
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

function flagLabel(key: TaxonomyFlagKey, active: boolean): string {
  const labels: Record<TaxonomyFlagKey, [string, string]> = {
    uncategorized: [copy.value.classified, copy.value.uncategorized],
    has_reports: [copy.value.reportsAbsent, copy.value.reportsPresent],
    has_published: [copy.value.publishedAbsent, copy.value.publishedPresent],
    has_hidden: [copy.value.hiddenAbsent, copy.value.hiddenPresent],
    is_promoted: [copy.value.notPromoted, copy.value.promoted],
    needs_attention: [copy.value.stable, copy.value.attentionNeeded]
  }

  return labels[key][active ? 1 : 0]
}

function flagClass(key: TaxonomyFlagKey, active: boolean): string {
  if (!active) return 'is-neutral'

  const classes: Record<TaxonomyFlagKey, string> = {
    uncategorized: 'is-uncategorized',
    has_reports: 'is-reported',
    has_published: 'is-published',
    has_hidden: 'is-hidden',
    is_promoted: 'is-promoted',
    needs_attention: 'is-attention'
  }

  return classes[key]
}

function sortIndicator(key: TaxonomySortKey): string {
  if (appliedFilters.sort !== key) return '↕'
  return appliedFilters.direction === 'asc' ? '↑' : '↓'
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
      filterError.value = copy.value.validationError
      return
    }

    error.value = copy.value.genericError
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
  page.value = 1
  closeSummary()
  void fetchTaxonomy()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
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
  selectedBucket.value = bucket
  drawerOpen.value = true
}

function closeSummary(): void {
  drawerOpen.value = false
  selectedBucket.value = null
}

function clearTaxonomyData(): void {
  items.value = []
  summary.value = null
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
  () => syncTaxonomyAccessState(),
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncTaxonomyAccessState()
})
</script>

<style scoped>
.ym-taxonomy-page {
  color: var(--ym-text);
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
</style>
