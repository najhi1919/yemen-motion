<template>
  <div class="ym-works-review-page space-y-7">
    <section class="ym-works-review-hero">
      <div class="ym-works-review-hero__glow is-one" />
      <div class="ym-works-review-hero__glow is-two" />
      <div class="ym-works-review-hero__grid" aria-hidden="true" />

      <div class="ym-works-review-hero__content">
        <div>
          <div class="ym-works-review-chips">
            <span class="ym-works-review-chip is-brand">Yemen Motion</span>
            <span class="ym-works-review-chip is-readonly">{{ copy.readonly }}</span>
          </div>
          <p class="ym-works-review-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-works-review-description">{{ copy.description }}</p>
        </div>

        <div class="ym-works-review-hero__summary">
          <span>{{ copy.totalRequests }}</span>
          <strong>{{ formatNumber(summary.total) }}</strong>
          <small>{{ copy.safeQueue }}</small>
        </div>
      </div>
    </section>

    <section
      v-if="authPending"
      class="ym-works-review-access-state"
      role="status"
      aria-live="polite"
    >
      <span class="ym-works-review-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section
      v-else-if="forbidden"
      class="ym-works-review-access-state is-forbidden"
      role="status"
    >
      <span class="ym-works-review-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-works-review-notice" role="note">
        <span>{{ copy.readonly }}</span>
        <div>
          <strong>{{ copy.noticeTitle }}</strong>
          <p>{{ copy.notice }}</p>
        </div>
      </aside>

      <section class="ym-works-review-summary-grid" :aria-label="copy.summaryLabel">
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="ym-works-review-summary-card"
          :class="{ 'is-alert': card.key === 'overdue' && card.value > 0 }"
          :style="{ '--review-accent': card.color }"
        >
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-works-review-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-works-review-button is-secondary"
            :disabled="loading"
            :title="copy.resetHint"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-works-review-filter-grid" @submit.prevent="applyFilters">
          <label class="is-search">
            <span>{{ copy.search }}</span>
            <input
              v-model.trim="filters.q"
              type="search"
              minlength="2"
              maxlength="80"
              :placeholder="copy.searchPlaceholder"
              autocomplete="off"
            />
            <small>{{ copy.searchHint }}</small>
          </label>

          <label>
            <span>{{ copy.status }}</span>
            <select v-model="filters.status">
              <option value="">{{ copy.all }}</option>
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
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
            <span>{{ copy.designerId }}</span>
            <input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.reviewerId }}</span>
            <input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.assigned }}</span>
            <select v-model="filters.assigned">
              <option v-for="option in booleanOptions" :key="'assigned-' + option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.overdue }}</span>
            <select v-model="filters.overdue">
              <option v-for="option in booleanOptions" :key="'overdue-' + option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.from }}</span>
            <input v-model="filters.from" type="date" />
          </label>

          <label>
            <span>{{ copy.to }}</span>
            <input v-model="filters.to" type="date" />
          </label>

          <label>
            <span>{{ copy.perPage }}</span>
            <select v-model.number="filters.per_page">
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
          </label>

          <div class="ym-works-review-filter-actions">
            <button type="submit" class="ym-works-review-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>

        <p v-if="filterError" class="ym-works-review-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-works-review-table-card">
        <header class="ym-works-review-table-card__head">
          <div>
            <h2>{{ copy.tableTitle }}</h2>
            <p>{{ copy.tableCopy }}</p>
          </div>
          <div class="ym-works-review-table-state">
            <span>{{ copy.currentPage }}</span>
            <strong>
              {{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}
            </strong>
          </div>
        </header>

        <div v-if="loading" class="ym-works-review-state" role="status" aria-live="polite">
          <span class="ym-works-review-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-works-review-state is-error" role="alert">
          <span class="ym-works-review-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-review-button is-secondary" @click="fetchReviewQueue">
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="items.length === 0" class="ym-works-review-state" role="status">
          <span class="ym-works-review-empty-icon" aria-hidden="true">0</span>
          <h3>{{ copy.emptyTitle }}</h3>
          <p>{{ copy.emptyCopy }}</p>
        </div>

        <div v-else class="ym-works-review-table-wrap">
          <table class="ym-works-review-table">
            <thead>
              <tr>
                <th class="is-title">
                  <button type="button" class="ym-works-review-sort" @click="changeSort('title')">
                    {{ copy.workTitle }}
                    <span aria-hidden="true">{{ sortIndicator('title') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-review-sort" @click="changeSort('status')">
                    {{ copy.status }}
                    <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                  </button>
                </th>
                <th>{{ copy.mediaType }}</th>
                <th>{{ copy.designer }}</th>
                <th>{{ copy.reviewer }}</th>
                <th>{{ copy.assigned }}</th>
                <th>{{ copy.overdue }}</th>
                <th>{{ copy.needsAttention }}</th>
                <th>
                  <button type="button" class="ym-works-review-sort" @click="changeSort('reports_count')">
                    {{ copy.reports }}
                    <span aria-hidden="true">{{ sortIndicator('reports_count') }}</span>
                  </button>
                </th>
                <th>{{ copy.views }}</th>
                <th>{{ copy.likes }}</th>
                <th>
                  <button type="button" class="ym-works-review-sort" @click="changeSort('submitted_at')">
                    {{ copy.submittedAt }}
                    <span aria-hidden="true">{{ sortIndicator('submitted_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-review-sort" @click="changeSort('created_at')">
                    {{ copy.createdAt }}
                    <span aria-hidden="true">{{ sortIndicator('created_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-review-sort" @click="changeSort('updated_at')">
                    {{ copy.updatedAt }}
                    <span aria-hidden="true">{{ sortIndicator('updated_at') }}</span>
                  </button>
                </th>
                <th class="is-action">{{ copy.readAction }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="work in items"
                :key="work.id"
                :class="{ 'needs-attention': work.review_flags.needs_attention }"
              >
                <td class="is-title">
                  <strong :dir="textDirection(work.title)">{{ work.title }}</strong>
                  <code dir="ltr">{{ work.slug }}</code>
                  <small v-if="work.summary" :title="work.summary" :dir="textDirection(work.summary)">
                    {{ truncateText(work.summary, 74) }}
                  </small>
                </td>
                <td>
                  <span class="ym-works-review-badge is-status" :class="statusClass(work.status)">
                    {{ statusLabel(work.status) }}
                  </span>
                </td>
                <td><code dir="ltr">{{ displayValue(work.media_type) }}</code></td>
                <td>
                  <span v-if="work.designer" class="ym-works-review-person">
                    <strong :dir="textDirection(work.designer.name)">{{ work.designer.name }}</strong>
                    <small dir="ltr">#{{ work.designer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span v-if="work.reviewer" class="ym-works-review-person">
                    <strong :dir="textDirection(work.reviewer.name)">{{ work.reviewer.name }}</strong>
                    <small dir="ltr">#{{ work.reviewer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span
                    class="ym-works-review-flag"
                    :class="work.review_flags.assigned ? 'is-assigned' : 'is-neutral'"
                  >
                    {{ work.review_flags.assigned ? copy.assignedYes : copy.assignedNo }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-works-review-flag"
                    :class="work.review_flags.overdue ? 'is-overdue' : 'is-clear'"
                  >
                    {{ work.review_flags.overdue ? copy.overdueYes : copy.overdueNo }}
                  </span>
                </td>
                <td>
                  <span
                    class="ym-works-review-flag"
                    :class="work.review_flags.needs_attention ? 'is-attention' : 'is-clear'"
                  >
                    {{ work.review_flags.needs_attention ? copy.attentionYes : copy.attentionNo }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-review-count" :class="work.reports_count > 0 ? 'is-alert' : ''">
                    {{ formatNumber(work.reports_count) }}
                  </span>
                </td>
                <td><span class="ym-works-review-count">{{ formatNumber(work.views_count) }}</span></td>
                <td><span class="ym-works-review-count">{{ formatNumber(work.likes_count) }}</span></td>
                <td>
                  <time :datetime="work.submitted_at || undefined">
                    {{ formatDateTime(work.submitted_at) }}
                  </time>
                </td>
                <td>
                  <time :datetime="work.created_at || undefined">
                    {{ formatDateTime(work.created_at) }}
                  </time>
                </td>
                <td>
                  <time :datetime="work.updated_at || undefined">
                    {{ formatDateTime(work.updated_at) }}
                  </time>
                </td>
                <td class="is-action">
                  <button
                    type="button"
                    class="ym-works-review-details-button"
                    :disabled="!canViewDetails"
                    :title="canViewDetails ? copy.viewDetailsHint : copy.detailsPermissionRequired"
                    @click="openDetails(work)"
                  >
                    {{ copy.viewDetails }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="ym-works-review-pagination">
          <div>
            <span>{{ copy.paginationTotal }}</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small>
          </div>
          <nav :aria-label="copy.paginationLabel">
            <button
              type="button"
              class="ym-works-review-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              {{ copy.previous }}
            </button>
            <span>{{ copy.pageOf(pagination.current_page, pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-works-review-button is-secondary"
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
      v-if="drawerOpen"
      class="ym-review-detail-backdrop"
      role="presentation"
      @click.self="closeDetails"
    >
      <section
        class="ym-review-detail-drawer"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="drawerTitleId"
      >
        <header class="ym-review-detail-drawer__head">
          <div>
            <span>{{ copy.detailReadonly }}</span>
            <h2 :id="drawerTitleId">{{ selectedWorkTitle || copy.detailsTitle }}</h2>
            <code v-if="selectedWorkId !== null" dir="ltr">#{{ selectedWorkId }}</code>
          </div>
          <button
            type="button"
            class="ym-review-detail-drawer__close"
            :aria-label="copy.close"
            :title="copy.close"
            @click="closeDetails"
          >
            ×
          </button>
        </header>

        <div v-if="detailLoading" class="ym-review-detail-state" role="status" aria-live="polite">
          <span class="ym-works-review-spinner" aria-hidden="true" />
          <h3>{{ copy.detailsLoadingTitle }}</h3>
          <p>{{ copy.detailsLoadingCopy }}</p>
        </div>

        <div v-else-if="detailError" class="ym-review-detail-state is-error" role="alert">
          <span class="ym-works-review-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.detailsErrorTitle }}</h3>
          <p>{{ detailError }}</p>
          <button
            v-if="selectedWorkId !== null"
            type="button"
            class="ym-works-review-button is-secondary"
            @click="retrySelectedDetails"
          >
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="detail" class="ym-review-detail-content">
          <section class="ym-review-detail-intro">
            <div>
              <span class="ym-works-review-badge is-status" :class="statusClass(detail.work.status)">
                {{ statusLabel(detail.work.status) }}
              </span>
              <span class="ym-works-review-badge" :class="visibilityClass(detail.work.visibility_status)">
                {{ visibilityLabel(detail.work.visibility_status) }}
              </span>
            </div>
            <h3 :dir="textDirection(detail.work.title)">{{ detail.work.title }}</h3>
            <code dir="ltr">{{ detail.work.slug }}</code>
            <p v-if="detail.work.summary" :dir="textDirection(detail.work.summary)">
              {{ detail.work.summary }}
            </p>
            <p v-else>{{ copy.noSummary }}</p>
          </section>

          <section class="ym-review-detail-section">
            <header>
              <h3>{{ copy.accessIndicators }}</h3>
              <p>{{ copy.accessIndicatorsCopy }}</p>
            </header>
            <div class="ym-review-detail-access-grid">
              <span :class="detail.field_access.can_view_designer ? 'is-allowed' : 'is-denied'">
                {{ copy.canViewDesigner }}
                <strong>{{ accessLabel(detail.field_access.can_view_designer) }}</strong>
              </span>
              <span :class="detail.field_access.can_view_media ? 'is-allowed' : 'is-denied'">
                {{ copy.canViewMedia }}
                <strong>{{ accessLabel(detail.field_access.can_view_media) }}</strong>
              </span>
              <span :class="detail.field_access.can_view_metadata ? 'is-allowed' : 'is-denied'">
                {{ copy.canViewMetadata }}
                <strong>{{ accessLabel(detail.field_access.can_view_metadata) }}</strong>
              </span>
              <span :class="detail.field_access.can_view_private_notes ? 'is-allowed' : 'is-denied'">
                {{ copy.canViewPrivateNotes }}
                <strong>{{ accessLabel(detail.field_access.can_view_private_notes) }}</strong>
              </span>
            </div>
          </section>

          <section class="ym-review-detail-section">
            <header>
              <h3>{{ copy.basicDetails }}</h3>
            </header>
            <dl class="ym-review-detail-grid">
              <div>
                <dt>{{ copy.priceAmount }}</dt>
                <dd dir="ltr">{{ displayValue(detail.work.price_amount) }}</dd>
              </div>
              <div>
                <dt>{{ copy.deliveryDays }}</dt>
                <dd>{{ detail.work.delivery_days === null ? '—' : formatNumber(detail.work.delivery_days) }}</dd>
              </div>
              <div>
                <dt>{{ copy.categoryId }}</dt>
                <dd dir="ltr">{{ detail.work.category_id ?? '—' }}</dd>
              </div>
              <div>
                <dt>{{ copy.mediaType }}</dt>
                <dd><code dir="ltr">{{ displayValue(detail.work.media_type) }}</code></dd>
              </div>
              <div>
                <dt>{{ copy.featured }}</dt>
                <dd>{{ booleanLabel(detail.work.is_featured) }}</dd>
              </div>
              <div>
                <dt>{{ copy.pinned }}</dt>
                <dd>{{ booleanLabel(detail.work.is_pinned) }}</dd>
              </div>
              <div>
                <dt>{{ copy.reports }}</dt>
                <dd>{{ formatNumber(detail.work.reports_count) }}</dd>
              </div>
              <div>
                <dt>{{ copy.views }}</dt>
                <dd>{{ formatNumber(detail.work.views_count) }}</dd>
              </div>
              <div>
                <dt>{{ copy.likes }}</dt>
                <dd>{{ formatNumber(detail.work.likes_count) }}</dd>
              </div>
            </dl>
          </section>

          <section class="ym-review-detail-section">
            <header>
              <h3>{{ copy.people }}</h3>
            </header>
            <div v-if="detail.field_access.can_view_designer" class="ym-review-detail-people">
              <article>
                <span>{{ copy.designer }}</span>
                <strong v-if="detail.relations.designer" :dir="textDirection(detail.relations.designer.name)">
                  {{ detail.relations.designer.name }}
                </strong>
                <small v-if="detail.relations.designer" dir="ltr">#{{ detail.relations.designer.id }}</small>
                <strong v-else>{{ copy.notLinked }}</strong>
              </article>
              <article>
                <span>{{ copy.reviewer }}</span>
                <strong v-if="detail.relations.reviewer" :dir="textDirection(detail.relations.reviewer.name)">
                  {{ detail.relations.reviewer.name }}
                </strong>
                <small v-if="detail.relations.reviewer" dir="ltr">#{{ detail.relations.reviewer.id }}</small>
                <strong v-else>{{ copy.notLinked }}</strong>
              </article>
            </div>
            <p v-else class="ym-review-detail-unavailable">{{ copy.relationsUnavailable }}</p>
          </section>

          <section class="ym-review-detail-section">
            <header>
              <h3>{{ copy.media }}</h3>
            </header>
            <div v-if="detail.media" class="ym-review-detail-media">
              <span>
                {{ copy.mediaType }}:
                <code dir="ltr">{{ displayValue(detail.media.media_type) }}</code>
              </span>
              <strong :class="detail.media.has_media ? 'is-present' : 'is-absent'">
                {{ detail.media.has_media ? copy.mediaPresent : copy.mediaAbsent }}
              </strong>
            </div>
            <p v-else class="ym-review-detail-unavailable">{{ copy.mediaUnavailable }}</p>
          </section>

          <section class="ym-review-detail-section">
            <header>
              <h3>{{ copy.lifecycle }}</h3>
            </header>
            <dl class="ym-review-detail-grid is-lifecycle">
              <div v-for="item in lifecycleItems" :key="item.key">
                <dt>{{ item.label }}</dt>
                <dd>
                  <time :datetime="item.value || undefined">{{ formatDateTime(item.value) }}</time>
                </dd>
              </div>
            </dl>
          </section>

          <section class="ym-review-detail-section is-private">
            <header>
              <h3>{{ copy.privateNotes }}</h3>
              <p>{{ copy.privateNotesCopy }}</p>
            </header>
            <dl v-if="detail.private_notes" class="ym-review-detail-notes">
              <div>
                <dt>{{ copy.internalNotes }}</dt>
                <dd :dir="textDirection(detail.private_notes.internal_notes)">
                  {{ displayValue(detail.private_notes.internal_notes) }}
                </dd>
              </div>
              <div>
                <dt>{{ copy.rejectionReason }}</dt>
                <dd :dir="textDirection(detail.private_notes.rejection_reason)">
                  {{ displayValue(detail.private_notes.rejection_reason) }}
                </dd>
              </div>
              <div>
                <dt>{{ copy.changeRequestNotes }}</dt>
                <dd :dir="textDirection(detail.private_notes.change_request_notes)">
                  {{ displayValue(detail.private_notes.change_request_notes) }}
                </dd>
              </div>
            </dl>
            <p v-else class="ym-review-detail-unavailable">{{ copy.privateNotesUnavailable }}</p>
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
type ReviewStatus = 'submitted' | 'in_review' | 'changes_requested'
type WorkStatus = 'draft' | ReviewStatus | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = 'hidden' | 'public'
type BooleanFilter = '' | '1' | '0'
type PageSize = 15 | 25 | 50
type SortDirection = 'asc' | 'desc'
type ReviewSortKey = 'submitted_at' | 'updated_at' | 'reports_count' | 'created_at' | 'title' | 'status'

interface UserReference {
  id: number
  name: string
}

interface ReviewFlags {
  assigned: boolean
  overdue: boolean
  needs_attention: boolean
}

interface ReviewQueueItem {
  id: number
  title: string
  slug: string
  summary: string | null
  status: ReviewStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  designer: UserReference | null
  reviewer: UserReference | null
  category_id: number | null
  reports_count: number
  views_count: number
  likes_count: number
  submitted_at: string | null
  reviewed_at: string | null
  updated_at: string | null
  created_at: string | null
  review_flags: ReviewFlags
}

interface ReviewPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface ReviewSummary {
  total: number
  submitted: number
  in_review: number
  changes_requested: number
  assigned: number
  unassigned: number
  overdue: number
  reported: number
}

interface ReviewQueueData {
  items: ReviewQueueItem[]
  pagination: ReviewPagination
  summary: ReviewSummary
  filters: Record<string, unknown>
}

interface ReviewQueueResponse {
  success: boolean
  data: ReviewQueueData | null
  message?: string
  errors?: Record<string, string[]> | null
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
}

interface WorkDetailResponse {
  success: boolean
  data: WorkDetailData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface ReviewFilters {
  q: string
  status: '' | ReviewStatus
  media_type: string
  designer_id: string
  reviewer_id: string
  assigned: BooleanFilter
  overdue: BooleanFilter
  from: string
  to: string
  sort: ReviewSortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonly: 'قراءة وتنظيم فقط',
    kicker: 'إدارة دورة مراجعة الأعمال',
    title: 'طلبات المراجعة',
    description: 'قائمة إدارية آمنة لتنظيم الأعمال المرسلة وتحت المراجعة وطلبات التعديل، مع قراءة التفاصيل المسموحة فقط.',
    totalRequests: 'إجمالي الطلبات',
    safeQueue: 'طلبات مطابقة للفلاتر الحالية',
    authLoadingTitle: 'جارٍ التحقق من صلاحية المراجعة',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى طلبات المراجعة غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب صلاحيات قائمة المراجعة المطلوبة. لم تتم محاولة تحميل البيانات.',
    noticeTitle: 'لا توجد قرارات تنفيذية في هذه الصفحة',
    notice: 'هذه المرحلة للقراءة والتنظيم فقط؛ لا تتضمن بدء المراجعة أو الاعتماد أو الرفض أو طلب التعديل أو النشر أو الإخفاء أو الأرشفة أو الحذف.',
    summaryLabel: 'ملخص طلبات مراجعة الأعمال',
    total: 'الإجمالي',
    totalHint: 'كل الطلبات المطابقة',
    submitted: 'قيد المراجعة',
    submittedHint: 'أعمال مرسلة تنتظر المعالجة',
    inReview: 'تحت المراجعة',
    inReviewHint: 'أعمال أُسندت للمراجعة',
    changesRequested: 'تعديلات مطلوبة',
    changesRequestedHint: 'أعمال تنتظر تعديلات',
    assigned: 'مسندة',
    assignedHint: 'لها مراجع محدد',
    unassigned: 'غير مسندة',
    unassignedHint: 'دون مراجع محدد',
    overdue: 'متأخرة',
    overdueHint: 'تجاوزت مهلة 48 ساعة',
    reported: 'عليها بلاغات',
    reportedHint: 'طلبات تحمل بلاغًا واحدًا أو أكثر',
    filtersTitle: 'بحث وفلاتر المراجعة',
    filtersCopy: 'ضيّق طابور المراجعة باستخدام الحقول الآمنة المعتمدة فقط.',
    search: 'البحث',
    searchPlaceholder: 'العنوان أو المعرّف النصي أو الملخص',
    searchHint: 'حرفان على الأقل، وبحد أقصى 80 حرفًا.',
    status: 'الحالة',
    mediaType: 'نوع الوسائط',
    designerId: 'معرّف المصمم',
    reviewerId: 'معرّف المراجع',
    from: 'أُرسل من',
    to: 'أُرسل إلى',
    perPage: 'لكل صفحة',
    all: 'الكل',
    yes: 'نعم',
    no: 'لا',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    resetHint: 'مسح الفلاتر وإعادة ترتيب الأقدم إرسالًا أولًا',
    searchTooShort: 'نص البحث يجب أن يكون فارغًا أو يحتوي حرفين على الأقل.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    invalidIdentifiers: 'معرّفا المصمم والمراجع يجب أن يكونا عددين صحيحين موجبين.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من البحث والقيم والتواريخ.',
    tableTitle: 'طابور طلبات المراجعة',
    tableCopy: 'رتّب الطلبات من رؤوس الأعمدة، وافتح التفاصيل عبر إجراء القراءة الوحيد.',
    currentPage: 'الصفحة الحالية',
    loadingTitle: 'جارٍ تحميل طلبات المراجعة',
    loadingCopy: 'يتم جلب القائمة الآمنة وفق الفلاتر الحالية...',
    errorTitle: 'تعذر تحميل طلبات المراجعة',
    genericError: 'حدث خطأ أثناء تحميل طابور المراجعة. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    emptyTitle: 'لا توجد طلبات مراجعة مطابقة',
    emptyCopy: 'لا توجد أعمال ضمن نطاق المراجعة الحالي. جرّب تعديل الفلاتر أو إعادة ضبطها.',
    workTitle: 'العنوان',
    designer: 'المصمم',
    reviewer: 'المراجع',
    needsAttention: 'يحتاج انتباه',
    reports: 'البلاغات',
    views: 'المشاهدات',
    likes: 'الإعجابات',
    submittedAt: 'تاريخ الإرسال',
    createdAt: 'تاريخ الإنشاء',
    updatedAt: 'آخر تحديث',
    readAction: 'إجراء القراءة',
    assignedYes: 'مسند',
    assignedNo: 'غير مسند',
    overdueYes: 'متأخر',
    overdueNo: 'ضمن المهلة',
    attentionYes: 'يحتاج انتباه',
    attentionNo: 'مستقر',
    viewDetails: 'عرض التفاصيل',
    viewDetailsHint: 'فتح تفاصيل العمل الآمنة',
    detailsPermissionRequired: 'يتطلب عرض التفاصيل صلاحيات تفاصيل الأعمال',
    paginationTotal: 'إجمالي الطلبات',
    visibleNow: 'طلب ظاهر الآن',
    paginationLabel: 'التنقل بين صفحات طلبات المراجعة',
    previous: 'السابق',
    next: 'التالي',
    pageOf: (page: number, last: number) => 'الصفحة ' + page + ' من ' + last,
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
    accessIndicators: 'نطاق الحقول المتاح',
    accessIndicatorsCopy: 'تعكس هذه المؤشرات الصلاحيات التي طبقها الخادم على استجابة التفاصيل.',
    canViewDesigner: 'المصمم والمراجع',
    canViewMedia: 'بيانات الوسائط',
    canViewMetadata: 'صلاحية metadata',
    canViewPrivateNotes: 'الملاحظات الخاصة',
    allowed: 'متاح',
    unavailable: 'غير متاح',
    basicDetails: 'البيانات الأساسية',
    priceAmount: 'القيمة السعرية',
    deliveryDays: 'مدة التسليم بالأيام',
    categoryId: 'معرّف التصنيف',
    featured: 'مميز',
    pinned: 'مثبت',
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
    publishedAt: 'تاريخ النشر',
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
    readonly: 'Read and organize only',
    kicker: 'Works review workflow',
    title: 'Review Requests',
    description: 'A safe administrative queue for organizing submitted, in-review, and changes-requested works with permission-scoped details.',
    totalRequests: 'Total requests',
    safeQueue: 'Requests matching current filters',
    authLoadingTitle: 'Checking review queue access',
    authLoadingCopy: 'Waiting for the user session to initialize before requesting data.',
    forbiddenTitle: 'Review requests access is unavailable',
    forbiddenCopy: 'This account lacks the required review queue permissions. No data request was made.',
    noticeTitle: 'No review decisions are available here',
    notice: 'This step is limited to reading and organization. It does not start, approve, reject, request changes, publish, hide, archive, restore, or delete.',
    summaryLabel: 'Works review request summary',
    total: 'Total',
    totalHint: 'All matching requests',
    submitted: 'Submitted',
    submittedHint: 'Works waiting for processing',
    inReview: 'In review',
    inReviewHint: 'Works assigned for review',
    changesRequested: 'Changes requested',
    changesRequestedHint: 'Works awaiting changes',
    assigned: 'Assigned',
    assignedHint: 'A reviewer is assigned',
    unassigned: 'Unassigned',
    unassignedHint: 'No reviewer is assigned',
    overdue: 'Overdue',
    overdueHint: 'Past the 48-hour threshold',
    reported: 'Reported',
    reportedHint: 'Requests with one or more reports',
    filtersTitle: 'Review search and filters',
    filtersCopy: 'Narrow the review queue using only approved safe fields.',
    search: 'Search',
    searchPlaceholder: 'Title, slug, or summary',
    searchHint: 'At least 2 and at most 80 characters.',
    status: 'Status',
    mediaType: 'Media type',
    designerId: 'Designer ID',
    reviewerId: 'Reviewer ID',
    from: 'Submitted from',
    to: 'Submitted to',
    perPage: 'Per page',
    all: 'All',
    yes: 'Yes',
    no: 'No',
    apply: 'Apply filters',
    reset: 'Reset',
    resetHint: 'Clear filters and restore oldest submitted first',
    searchTooShort: 'Search must be empty or contain at least two characters.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    invalidIdentifiers: 'Designer and reviewer identifiers must be positive integers.',
    validationError: 'The filters could not be applied. Check the search, values, and dates.',
    tableTitle: 'Review request queue',
    tableCopy: 'Sort from supported headers and open details through the only read action.',
    currentPage: 'Current page',
    loadingTitle: 'Loading review requests',
    loadingCopy: 'Fetching the safe queue using the current filters...',
    errorTitle: 'Could not load review requests',
    genericError: 'An error occurred while loading the review queue. Try again.',
    retry: 'Retry',
    emptyTitle: 'No matching review requests',
    emptyCopy: 'There are no works in the current review scope. Change or reset the filters.',
    workTitle: 'Title',
    designer: 'Designer',
    reviewer: 'Reviewer',
    needsAttention: 'Needs attention',
    reports: 'Reports',
    views: 'Views',
    likes: 'Likes',
    submittedAt: 'Submitted at',
    createdAt: 'Created at',
    updatedAt: 'Updated at',
    readAction: 'Read action',
    assignedYes: 'Assigned',
    assignedNo: 'Unassigned',
    overdueYes: 'Overdue',
    overdueNo: 'On time',
    attentionYes: 'Needs attention',
    attentionNo: 'Stable',
    viewDetails: 'View details',
    viewDetailsHint: 'Open safe work details',
    detailsPermissionRequired: 'Work detail permissions are required',
    paginationTotal: 'Total requests',
    visibleNow: 'requests visible now',
    paginationLabel: 'Review request pagination',
    previous: 'Previous',
    next: 'Next',
    pageOf: (page: number, last: number) => 'Page ' + page + ' of ' + last,
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
    categoryId: 'Category ID',
    featured: 'Featured',
    pinned: 'Pinned',
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
    publishedAt: 'Published at',
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
const hasReviewAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.review.view')
})
const canViewDetails = computed(() => (
  hasReviewAccess.value
  && (
    authStore.role === 'super-admin'
    || (
      authStore.permissions.includes('admin.works.all.view')
      && authStore.permissions.includes('admin.works.detail.view')
    )
  )
))
const serverForbidden = ref(false)
const forbidden = computed(() => (
  authStore.isInitialized && (!hasReviewAccess.value || serverForbidden.value)
))

const items = ref<ReviewQueueItem[]>([])
const pagination = reactive<ReviewPagination>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
const summary = reactive<ReviewSummary>(emptySummary())

function emptySummary(): ReviewSummary {
  return {
    total: 0,
    submitted: 0,
    in_review: 0,
    changes_requested: 0,
    assigned: 0,
    unassigned: 0,
    overdue: 0,
    reported: 0
  }
}

function defaultFilters(): ReviewFilters {
  return {
    q: '',
    status: '',
    media_type: '',
    designer_id: '',
    reviewer_id: '',
    assigned: '',
    overdue: '',
    from: '',
    to: '',
    sort: 'submitted_at',
    direction: 'asc',
    per_page: 15
  }
}

const filters = reactive<ReviewFilters>(defaultFilters())
const appliedFilters = reactive<ReviewFilters>(defaultFilters())
const page = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)

const drawerOpen = ref(false)
const selectedWorkId = ref<number | null>(null)
const selectedWorkTitle = ref('')
const detail = ref<WorkDetailData | null>(null)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)
const drawerTitleId = 'ym-review-work-detail-title'

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let listRequestRevision = 0
let detailRequestRevision = 0

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
  [...authStore.permissions].sort().join(',')
].join('|'))

const statusOptions = computed(() => [
  { value: 'submitted' as const, label: statusLabel('submitted') },
  { value: 'in_review' as const, label: statusLabel('in_review') },
  { value: 'changes_requested' as const, label: statusLabel('changes_requested') }
])

const booleanOptions = computed(() => [
  { value: '' as const, label: copy.value.all },
  { value: '1' as const, label: copy.value.yes },
  { value: '0' as const, label: copy.value.no }
])

const summaryCards = computed(() => [
  { key: 'total', label: copy.value.total, value: summary.total, hint: copy.value.totalHint, color: '#8b5cf6' },
  { key: 'submitted', label: copy.value.submitted, value: summary.submitted, hint: copy.value.submittedHint, color: '#38bdf8' },
  { key: 'in_review', label: copy.value.inReview, value: summary.in_review, hint: copy.value.inReviewHint, color: '#6366f1' },
  { key: 'changes_requested', label: copy.value.changesRequested, value: summary.changes_requested, hint: copy.value.changesRequestedHint, color: '#f59e0b' },
  { key: 'assigned', label: copy.value.assigned, value: summary.assigned, hint: copy.value.assignedHint, color: '#14b8a6' },
  { key: 'unassigned', label: copy.value.unassigned, value: summary.unassigned, hint: copy.value.unassignedHint, color: '#94a3b8' },
  { key: 'overdue', label: copy.value.overdue, value: summary.overdue, hint: copy.value.overdueHint, color: '#f43f5e' },
  { key: 'reported', label: copy.value.reported, value: summary.reported, hint: copy.value.reportedHint, color: '#e879f9' }
])

const lifecycleItems = computed(() => {
  const work = detail.value?.work

  return [
    { key: 'submitted_at', label: copy.value.submittedAt, value: work?.submitted_at ?? null },
    { key: 'reviewed_at', label: copy.value.reviewedAt, value: work?.reviewed_at ?? null },
    { key: 'approved_at', label: copy.value.approvedAt, value: work?.approved_at ?? null },
    { key: 'published_at', label: copy.value.publishedAt, value: work?.published_at ?? null },
    { key: 'rejected_at', label: copy.value.rejectedAt, value: work?.rejected_at ?? null },
    { key: 'hidden_at', label: copy.value.hiddenAt, value: work?.hidden_at ?? null },
    { key: 'archived_at', label: copy.value.archivedAt, value: work?.archived_at ?? null },
    { key: 'created_at', label: copy.value.createdAt, value: work?.created_at ?? null },
    { key: 'updated_at', label: copy.value.updatedAt, value: work?.updated_at ?? null }
  ]
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

function textDirection(value: string | null | undefined): 'rtl' | 'ltr' {
  return /[\u0600-\u06FF]/.test(String(value ?? '')) ? 'rtl' : 'ltr'
}

function truncateText(value: string, limit: number): string {
  const characters = Array.from(value.trim())
  return characters.length <= limit
    ? characters.join('')
    : characters.slice(0, limit).join('') + '…'
}

function displayValue(value: string | null | undefined): string {
  return value === null || value === undefined || value.trim() === '' ? '—' : value
}

function booleanLabel(value: boolean): string {
  return value ? copy.value.yes : copy.value.no
}

function accessLabel(value: boolean): string {
  return value ? copy.value.allowed : copy.value.unavailable
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

function statusClass(status: WorkStatus): string {
  return 'is-' + status.replaceAll('_', '-')
}

function visibilityLabel(status: VisibilityStatus): string {
  return status === 'public' ? copy.value.publicVisibility : copy.value.hiddenVisibility
}

function visibilityClass(status: VisibilityStatus): string {
  return status === 'public' ? 'is-public' : 'is-hidden'
}

function sortIndicator(key: ReviewSortKey): string {
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
  const query = filters.q.trim()

  if (query.length === 1) {
    filterError.value = copy.value.searchTooShort
    return false
  }

  if (filters.from && filters.to && filters.to < filters.from) {
    filterError.value = copy.value.invalidDateRange
    return false
  }

  const identifiers = [filters.designer_id, filters.reviewer_id]
  const hasInvalidIdentifier = identifiers.some((value) => {
    if (value.trim() === '') return false
    const parsed = Number(value)
    return !Number.isInteger(parsed) || parsed < 1
  })

  if (hasInvalidIdentifier) {
    filterError.value = copy.value.invalidIdentifiers
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

  // تُرسل مفاتيح قائمة السماح فقط، وتُحذف القيم الفارغة بالكامل.
  const optionalFilters: Array<[string, string]> = [
    ['q', appliedFilters.q.trim()],
    ['status', appliedFilters.status],
    ['media_type', appliedFilters.media_type.trim()],
    ['designer_id', appliedFilters.designer_id.trim()],
    ['reviewer_id', appliedFilters.reviewer_id.trim()],
    ['assigned', appliedFilters.assigned],
    ['overdue', appliedFilters.overdue],
    ['from', appliedFilters.from],
    ['to', appliedFilters.to]
  ]

  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }

  return query
}

async function fetchReviewQueue(): Promise<void> {
  if (!authStore.isInitialized || !hasReviewAccess.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++listRequestRevision
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<ReviewQueueResponse>('/admin/works/review', {
      query: buildListQuery()
    })

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasReviewAccess.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      items.value = []
      Object.assign(summary, emptySummary())
      error.value = copy.value.genericError
      return
    }

    items.value = response.data.items
    Object.assign(pagination, response.data.pagination)
    Object.assign(summary, response.data.summary)
    page.value = response.data.pagination.current_page
    serverForbidden.value = false
  } catch (requestError: unknown) {
    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasReviewAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      clearQueueData()
      return
    }

    if (status === 422) {
      filterError.value = copy.value.validationError
      return
    }

    error.value = copy.value.genericError
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
  void fetchReviewQueue()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
  void fetchReviewQueue()
}

function changeSort(key: ReviewSortKey): void {
  if (appliedFilters.sort === key) {
    appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'
  } else {
    appliedFilters.sort = key
    appliedFilters.direction = ['title', 'status'].includes(key) ? 'asc' : 'desc'
  }

  filters.sort = appliedFilters.sort
  filters.direction = appliedFilters.direction
  page.value = 1
  void fetchReviewQueue()
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
  void fetchReviewQueue()
}

function openDetails(work: ReviewQueueItem): void {
  if (!canViewDetails.value) return

  drawerOpen.value = true
  selectedWorkId.value = work.id
  selectedWorkTitle.value = work.title
  detail.value = null
  detailError.value = null
  void fetchWorkDetails(work.id)
}

async function fetchWorkDetails(workId: number): Promise<void> {
  if (!canViewDetails.value || !drawerOpen.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++detailRequestRevision
  detailLoading.value = true
  detailError.value = null
  detail.value = null

  try {
    const response = await apiFetch<WorkDetailResponse>('/admin/works/' + workId)

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

function closeDetails(): void {
  detailRequestRevision += 1
  drawerOpen.value = false
  selectedWorkId.value = null
  selectedWorkTitle.value = ''
  detail.value = null
  detailError.value = null
  detailLoading.value = false
}

function retrySelectedDetails(): void {
  if (selectedWorkId.value === null) return

  void fetchWorkDetails(selectedWorkId.value)
}

function clearQueueData(): void {
  items.value = []
  Object.assign(summary, emptySummary())
  Object.assign(pagination, {
    current_page: 1,
    per_page: appliedFilters.per_page,
    total: 0,
    last_page: 1
  })
  page.value = 1
}

function clearPageState(): void {
  listRequestRevision += 1
  clearQueueData()
  loading.value = false
  error.value = null
  filterError.value = null
  closeDetails()
}

function syncReviewAccessState(): void {
  if (!pageMounted) return

  accessRevision += 1
  serverForbidden.value = false
  closeDetails()

  // لا يصدر أي طلب قبل اكتمال المصادقة أو عند فقد صلاحيات صفحة المراجعة.
  if (!authStore.isInitialized) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (!hasReviewAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchReviewQueue()
}

watch(
  authorizationSignature,
  () => syncReviewAccessState(),
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncReviewAccessState()
})
</script>

<style scoped>
.ym-works-review-page {
  color: var(--ym-text);
}

.ym-works-review-hero,
.ym-works-review-filter-card,
.ym-works-review-table-card,
.ym-works-review-access-state {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.ym-works-review-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-works-review-hero::before {
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.18), transparent 46%);
  content: '';
  pointer-events: none;
}

.ym-works-review-hero__grid {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(rgba(148, 163, 184, 0.045) 1px, transparent 1px),
    linear-gradient(90deg, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
  background-size: 44px 44px;
  mask-image: linear-gradient(to bottom, black, transparent 86%);
  pointer-events: none;
}

.ym-works-review-hero__glow {
  position: absolute;
  width: 19rem;
  height: 19rem;
  border-radius: 999px;
  filter: blur(18px);
  opacity: 0.23;
  pointer-events: none;
}

.ym-works-review-hero__glow.is-one {
  inset-block-start: -10rem;
  inset-inline-start: -5rem;
  background: #6366f1;
}

.ym-works-review-hero__glow.is-two {
  inset-block-end: -11rem;
  inset-inline-end: -4rem;
  background: #f59e0b;
}

.ym-works-review-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-works-review-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  margin-bottom: 1rem;
}

.ym-works-review-chip {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 950;
  padding: 0.42rem 0.72rem;
}

.ym-works-review-chip.is-brand {
  color: #818cf8;
}

.ym-works-review-chip.is-readonly {
  color: #fbbf24;
}

.ym-works-review-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 900;
  margin: 0 0 0.3rem;
}

.ym-works-review-hero h1 {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.45rem);
  font-weight: 950;
  line-height: 1.1;
  margin: 0;
}

.ym-works-review-description {
  max-width: 58rem;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  line-height: 1.8;
  margin: 0.8rem 0 0;
}

.ym-works-review-hero__summary {
  display: grid;
  min-width: min(100%, 220px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-works-review-hero__summary span,
.ym-works-review-hero__summary small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-review-hero__summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
}

.ym-works-review-notice {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  border: 1px solid rgba(245, 158, 11, 0.28);
  border-radius: 22px;
  background: color-mix(in srgb, #f59e0b 8%, var(--ym-control-bg));
  padding: 1rem 1.15rem;
}

.ym-works-review-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.14);
  color: #fbbf24;
  font-size: 11px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-works-review-notice strong {
  display: block;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-review-notice p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.2rem 0 0;
}

.ym-works-review-summary-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1rem;
}

.ym-works-review-summary-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--review-accent) 17%, transparent), transparent 52%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1rem;
}

.ym-works-review-summary-card::after {
  position: absolute;
  inset-block: 0;
  inset-inline-start: 0;
  width: 3px;
  background: var(--review-accent);
  content: '';
  opacity: 0.8;
}

.ym-works-review-summary-card.is-alert {
  border-color: rgba(244, 63, 94, 0.35);
}

.ym-works-review-summary-card span,
.ym-works-review-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-review-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-works-review-filter-card,
.ym-works-review-table-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-works-review-filter-card > header,
.ym-works-review-table-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-works-review-filter-card h2,
.ym-works-review-table-card h2,
.ym-works-review-access-state h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-review-filter-card header p,
.ym-works-review-table-card__head p,
.ym-works-review-access-state p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-works-review-filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-works-review-filter-grid label {
  display: grid;
  align-content: start;
  gap: 0.42rem;
}

.ym-works-review-filter-grid label.is-search {
  grid-column: span 2;
}

.ym-works-review-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-review-filter-grid label > small {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 750;
}

.ym-works-review-filter-grid input,
.ym-works-review-filter-grid select {
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

.ym-works-review-filter-grid input:focus,
.ym-works-review-filter-grid select:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.14);
}

.ym-works-review-filter-grid select option {
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
}

.ym-works-review-filter-actions {
  display: flex;
  align-items: flex-end;
}

.ym-works-review-button {
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

.ym-works-review-button.is-primary {
  min-width: 130px;
  background: linear-gradient(135deg, #6366f1, #7c3aed);
  color: #fff;
  box-shadow: 0 12px 28px rgba(99, 102, 241, 0.22);
}

.ym-works-review-button.is-secondary {
  border-color: var(--ym-control-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-works-review-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-works-review-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-works-review-filter-error {
  border: 1px solid rgba(244, 63, 94, 0.34);
  border-radius: 15px;
  background: rgba(244, 63, 94, 0.1);
  color: #fb7185;
  font-size: 12px;
  font-weight: 850;
  margin: 1rem 0 0;
  padding: 0.75rem 0.85rem;
}

.ym-works-review-table-card__head {
  align-items: center;
}

.ym-works-review-table-state {
  display: grid;
  min-width: 130px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: var(--ym-control-bg);
  padding: 0.65rem 0.8rem;
}

.ym-works-review-table-state span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-review-table-state strong {
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 950;
}

.ym-works-review-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 20px;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
}

.ym-works-review-table {
  width: 100%;
  min-width: 2080px;
  border-collapse: collapse;
  background: color-mix(in srgb, var(--ym-card-bg) 88%, transparent);
}

.ym-works-review-table th,
.ym-works-review-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-muted);
  font-size: 12px;
  padding: 0.86rem 0.75rem;
  text-align: start;
  vertical-align: middle;
}

.ym-works-review-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
  font-weight: 950;
  white-space: nowrap;
}

.ym-works-review-table tbody tr {
  transition: background 150ms ease;
}

.ym-works-review-table tbody tr:hover,
.ym-works-review-table tbody tr.needs-attention:hover {
  background: var(--ym-row-hover);
}

.ym-works-review-table tbody tr.needs-attention {
  background: color-mix(in srgb, #f59e0b 4%, transparent);
}

.ym-works-review-table tbody tr:last-child td {
  border-bottom: 0;
}

.ym-works-review-table th.is-title,
.ym-works-review-table td.is-title {
  width: 310px;
  min-width: 310px;
}

.ym-works-review-table td.is-title strong,
.ym-works-review-table td.is-title code,
.ym-works-review-table td.is-title small,
.ym-works-review-person strong,
.ym-works-review-person small {
  display: block;
}

.ym-works-review-table td.is-title strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-review-table td.is-title code {
  color: #818cf8;
  font-size: 10px;
  margin-top: 0.2rem;
  overflow-wrap: anywhere;
}

.ym-works-review-table td.is-title small {
  max-width: 290px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.55;
  margin-top: 0.35rem;
}

.ym-works-review-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  padding: 0;
}

.ym-works-review-sort span {
  display: inline-grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  border-radius: 7px;
  background: rgba(99, 102, 241, 0.13);
  color: #818cf8;
}

.ym-works-review-badge,
.ym-works-review-flag {
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

.ym-works-review-badge.is-submitted,
.ym-works-review-badge.is-in-review {
  border-color: rgba(56, 189, 248, 0.35);
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
}

.ym-works-review-badge.is-changes-requested {
  border-color: rgba(245, 158, 11, 0.38);
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}

.ym-works-review-badge.is-approved,
.ym-works-review-badge.is-published,
.ym-works-review-badge.is-public {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
}

.ym-works-review-badge.is-draft,
.ym-works-review-badge.is-hidden,
.ym-works-review-badge.is-archived {
  border-color: rgba(148, 163, 184, 0.35);
  background: rgba(100, 116, 139, 0.13);
  color: #cbd5e1;
}

.ym-works-review-badge.is-rejected {
  border-color: rgba(244, 63, 94, 0.36);
  background: rgba(244, 63, 94, 0.12);
  color: #fb7185;
}

.ym-works-review-flag.is-assigned {
  border-color: rgba(99, 102, 241, 0.35);
  background: rgba(99, 102, 241, 0.12);
  color: #a5b4fc;
}

.ym-works-review-flag.is-overdue {
  border-color: rgba(244, 63, 94, 0.4);
  background: rgba(244, 63, 94, 0.14);
  color: #fb7185;
}

.ym-works-review-flag.is-attention {
  border-color: rgba(245, 158, 11, 0.4);
  background: rgba(245, 158, 11, 0.13);
  color: #fbbf24;
}

.ym-works-review-flag.is-clear {
  border-color: rgba(16, 185, 129, 0.26);
  background: rgba(16, 185, 129, 0.08);
  color: #6ee7b7;
}

.ym-works-review-flag.is-neutral {
  color: #94a3b8;
}

.ym-works-review-person {
  min-width: 130px;
}

.ym-works-review-person strong {
  color: var(--ym-text);
  font-size: 11px;
  font-weight: 900;
}

.ym-works-review-person small {
  color: var(--ym-muted);
  font-size: 9px;
  margin-top: 0.18rem;
}

.ym-works-review-count {
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

.ym-works-review-count.is-alert {
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
}

.ym-works-review-table time {
  display: inline-block;
  min-width: 125px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.5;
}

.ym-works-review-table th.is-action,
.ym-works-review-table td.is-action {
  position: sticky;
  inset-inline-end: 0;
  z-index: 1;
  min-width: 130px;
  background: var(--ym-dropdown-bg);
}

.ym-works-review-table th.is-action {
  z-index: 3;
}

.ym-works-review-details-button {
  width: 100%;
  min-height: 38px;
  border: 1px solid rgba(99, 102, 241, 0.4);
  border-radius: 12px;
  background: rgba(99, 102, 241, 0.12);
  color: #a5b4fc;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
  transition: background 160ms ease, transform 160ms ease;
}

.ym-works-review-details-button:hover:not(:disabled) {
  background: rgba(99, 102, 241, 0.2);
  transform: translateY(-1px);
}

.ym-works-review-details-button:disabled {
  cursor: not-allowed;
  filter: grayscale(0.6);
  opacity: 0.45;
}

.ym-works-review-state,
.ym-works-review-access-state,
.ym-review-detail-state {
  display: grid;
  min-height: 240px;
  place-items: center;
  align-content: center;
  gap: 0.7rem;
  color: var(--ym-muted);
  padding: 2rem;
  text-align: center;
}

.ym-works-review-state h3,
.ym-review-detail-state h3 {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-review-state p,
.ym-review-detail-state p {
  max-width: 34rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-review-state.is-error,
.ym-review-detail-state.is-error,
.ym-works-review-access-state.is-forbidden {
  color: #fb7185;
}

.ym-works-review-state__icon,
.ym-works-review-empty-icon {
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

.ym-works-review-empty-icon {
  background: rgba(148, 163, 184, 0.13);
  color: var(--ym-muted);
}

.ym-works-review-spinner {
  width: 2.35rem;
  height: 2.35rem;
  border: 3px solid rgba(99, 102, 241, 0.2);
  border-top-color: #818cf8;
  border-radius: 999px;
  animation: ym-works-review-spin 760ms linear infinite;
}

.ym-works-review-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.ym-works-review-pagination > div {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-review-pagination > div strong {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
}

.ym-works-review-pagination nav {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ym-works-review-pagination nav span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-review-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  justify-content: flex-end;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(6px);
}

.ym-review-detail-drawer {
  width: min(660px, 100%);
  height: 100dvh;
  overflow-y: auto;
  border-inline-start: 1px solid var(--ym-card-border);
  background: var(--ym-dropdown-bg);
  box-shadow: -24px 0 64px rgba(2, 6, 23, 0.38);
  color: var(--ym-text);
}

.ym-review-detail-drawer__head {
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

.ym-review-detail-drawer__head span,
.ym-review-detail-drawer__head code {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-review-detail-drawer__head h2 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.35;
  margin: 0.2rem 0;
}

.ym-review-detail-drawer__close {
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

.ym-review-detail-content {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
}

.ym-review-detail-intro,
.ym-review-detail-section {
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-card-bg);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
  padding: 1rem;
}

.ym-review-detail-intro > div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.ym-review-detail-intro h3 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.45;
  margin: 0.8rem 0 0.25rem;
}

.ym-review-detail-intro code {
  color: #818cf8;
  font-size: 11px;
  overflow-wrap: anywhere;
}

.ym-review-detail-intro p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.8;
  margin: 0.75rem 0 0;
}

.ym-review-detail-section > header {
  margin-bottom: 0.8rem;
}

.ym-review-detail-section > header h3 {
  color: var(--ym-text);
  font-size: 1rem;
  font-weight: 950;
  margin: 0;
}

.ym-review-detail-section > header p {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 750;
  line-height: 1.65;
  margin: 0.25rem 0 0;
}

.ym-review-detail-access-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-review-detail-access-grid > span {
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

.ym-review-detail-access-grid > span strong {
  font-size: 12px;
  font-weight: 950;
}

.ym-review-detail-access-grid > span.is-allowed strong {
  color: #34d399;
}

.ym-review-detail-access-grid > span.is-denied strong {
  color: #94a3b8;
}

.ym-review-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
}

.ym-review-detail-grid > div,
.ym-review-detail-people article,
.ym-review-detail-notes > div {
  min-width: 0;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  padding: 0.7rem;
}

.ym-review-detail-grid dt,
.ym-review-detail-notes dt,
.ym-review-detail-people span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-review-detail-grid dd,
.ym-review-detail-notes dd {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 900;
  line-height: 1.65;
  margin: 0.3rem 0 0;
  overflow-wrap: anywhere;
}

.ym-review-detail-grid.is-lifecycle {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ym-review-detail-people {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-review-detail-people strong,
.ym-review-detail-people small {
  display: block;
}

.ym-review-detail-people strong {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
  margin-top: 0.3rem;
}

.ym-review-detail-people small {
  color: var(--ym-muted);
  font-size: 10px;
  margin-top: 0.18rem;
}

.ym-review-detail-media {
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

.ym-review-detail-media strong.is-present {
  color: #34d399;
}

.ym-review-detail-media strong.is-absent {
  color: #94a3b8;
}

.ym-review-detail-notes {
  display: grid;
  gap: 0.65rem;
  margin: 0;
}

.ym-review-detail-section.is-private {
  border-color: color-mix(in srgb, #a78bfa 30%, var(--ym-soft-border));
}

.ym-review-detail-unavailable {
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

@keyframes ym-works-review-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1280px) {
  .ym-works-review-summary-grid,
  .ym-works-review-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .ym-works-review-hero__content,
  .ym-works-review-filter-card > header,
  .ym-works-review-table-card__head,
  .ym-works-review-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-review-hero__summary {
    min-width: 0;
  }

  .ym-works-review-summary-grid,
  .ym-works-review-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-review-pagination nav {
    justify-content: space-between;
  }
}

@media (max-width: 640px) {
  .ym-works-review-page {
    font-size: 14px;
  }

  .ym-works-review-hero,
  .ym-works-review-filter-card,
  .ym-works-review-table-card,
  .ym-works-review-access-state {
    border-radius: 22px;
  }

  .ym-works-review-hero h1 {
    font-size: 2rem;
  }

  .ym-works-review-notice {
    flex-direction: column;
  }

  .ym-works-review-summary-grid,
  .ym-works-review-filter-grid,
  .ym-review-detail-access-grid,
  .ym-review-detail-grid,
  .ym-review-detail-grid.is-lifecycle,
  .ym-review-detail-people {
    grid-template-columns: 1fr;
  }

  .ym-works-review-filter-grid label.is-search {
    grid-column: auto;
  }

  .ym-works-review-filter-actions,
  .ym-works-review-filter-actions .ym-works-review-button {
    width: 100%;
  }

  .ym-works-review-pagination nav {
    display: grid;
    grid-template-columns: 1fr;
    text-align: center;
  }

  .ym-review-detail-drawer__head,
  .ym-review-detail-content {
    padding-inline: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-works-review-spinner {
    animation-duration: 1.8s;
  }

  .ym-works-review-button,
  .ym-works-review-details-button,
  .ym-works-review-table tbody tr {
    transition: none;
  }
}
</style>
