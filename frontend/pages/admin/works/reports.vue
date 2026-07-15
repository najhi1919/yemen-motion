<template>
  <div class="ym-works-reports-page space-y-7">
    <section class="ym-works-reports-hero">
      <div class="ym-works-reports-hero__glow is-one" />
      <div class="ym-works-reports-hero__glow is-two" />
      <div class="ym-works-reports-hero__grid" aria-hidden="true" />

      <div class="ym-works-reports-hero__content">
        <div>
          <div class="ym-works-reports-chips">
            <span class="ym-works-reports-chip is-brand">Yemen Motion</span>
            <span class="ym-works-reports-chip is-readonly">{{ copy.readonly }}</span>
          </div>
          <p class="ym-works-reports-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-works-reports-description">{{ copy.description }}</p>
        </div>

        <div class="ym-works-reports-hero__summary">
          <span>{{ copy.totalWorks }}</span>
          <strong>{{ formatNumber(summary.total) }}</strong>
          <small>{{ copy.filteredScope }}</small>
        </div>
      </div>
    </section>

    <section
      v-if="authPending"
      class="ym-works-reports-access-state"
      role="status"
      aria-live="polite"
    >
      <span class="ym-works-reports-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section
      v-else-if="forbidden"
      class="ym-works-reports-access-state is-forbidden"
      role="status"
    >
      <span class="ym-works-reports-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-works-reports-notice" role="note">
        <span>{{ copy.readonly }}</span>
        <div>
          <strong>{{ copy.noticeTitle }}</strong>
          <p>{{ copy.notice }}</p>
        </div>
      </aside>

      <section class="ym-works-reports-summary-grid" :aria-label="copy.summaryLabel">
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="ym-works-reports-summary-card"
          :class="{
            'is-alert': ['high_reports', 'total_reports'].includes(card.key) && card.value > 0,
            'is-risk': card.key === 'public_reported' && card.value > 0
          }"
          :style="{ '--reports-accent': card.color }"
        >
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-works-reports-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-works-reports-button is-secondary"
            :disabled="loading"
            :title="copy.resetHint"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-works-reports-filter-grid" @submit.prevent="applyFilters">
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
            <span>{{ copy.designerId }}</span>
            <input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.reviewerId }}</span>
            <input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.categoryId }}</span>
            <input v-model="filters.category_id" type="number" min="1" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.featuredLabel }}</span>
            <select v-model="filters.is_featured">
              <option v-for="option in booleanOptions" :key="'featured-' + option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.pinnedLabel }}</span>
            <select v-model="filters.is_pinned">
              <option v-for="option in booleanOptions" :key="'pinned-' + option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.minimumReports }}</span>
            <input
              v-model="filters.min_reports"
              type="number"
              min="1"
              max="100000"
              inputmode="numeric"
            />
            <small>{{ copy.minimumReportsHint }}</small>
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

          <div class="ym-works-reports-filter-actions">
            <button type="submit" class="ym-works-reports-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>

        <p v-if="filterError" class="ym-works-reports-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-works-reports-table-card">
        <header class="ym-works-reports-table-card__head">
          <div>
            <h2>{{ copy.tableTitle }}</h2>
            <p>{{ copy.tableCopy }}</p>
          </div>
          <div class="ym-works-reports-table-state">
            <span>{{ copy.currentPage }}</span>
            <strong>
              {{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}
            </strong>
          </div>
        </header>

        <div v-if="loading" class="ym-works-reports-state" role="status" aria-live="polite">
          <span class="ym-works-reports-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-works-reports-state is-error" role="alert">
          <span class="ym-works-reports-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-reports-button is-secondary" @click="fetchReportedWorks">
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="items.length === 0" class="ym-works-reports-state" role="status">
          <span class="ym-works-reports-empty-icon" aria-hidden="true">0</span>
          <h3>{{ copy.emptyTitle }}</h3>
          <p>{{ copy.emptyCopy }}</p>
        </div>

        <div v-else class="ym-works-reports-table-wrap">
          <table class="ym-works-reports-table">
            <thead>
              <tr>
                <th class="is-title">
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('title')">
                    {{ copy.workTitle }}
                    <span aria-hidden="true">{{ sortIndicator('title') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('status')">
                    {{ copy.status }}
                    <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                  </button>
                </th>
                <th>{{ copy.visibility }}</th>
                <th>{{ copy.mediaType }}</th>
                <th>{{ copy.designer }}</th>
                <th>{{ copy.reviewer }}</th>
                <th>{{ copy.category }}</th>
                <th>{{ copy.featuredLabel }}</th>
                <th>{{ copy.pinnedLabel }}</th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('reports_count')">
                    {{ copy.reports }}
                    <span aria-hidden="true">{{ sortIndicator('reports_count') }}</span>
                  </button>
                </th>
                <th>{{ copy.highReportsFlag }}</th>
                <th>{{ copy.visibilityRiskFlag }}</th>
                <th>{{ copy.needsAttentionFlag }}</th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('views_count')">
                    {{ copy.views }}
                    <span aria-hidden="true">{{ sortIndicator('views_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('likes_count')">
                    {{ copy.likes }}
                    <span aria-hidden="true">{{ sortIndicator('likes_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('submitted_at')">
                    {{ copy.submittedAt }}
                    <span aria-hidden="true">{{ sortIndicator('submitted_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('published_at')">
                    {{ copy.publishedAt }}
                    <span aria-hidden="true">{{ sortIndicator('published_at') }}</span>
                  </button>
                </th>
                <th>{{ copy.hiddenAt }}</th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('created_at')">
                    {{ copy.createdAt }}
                    <span aria-hidden="true">{{ sortIndicator('created_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-reports-sort" @click="changeSort('updated_at')">
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
                :class="{
                  'is-high-reports-row': work.report_flags.high_reports,
                  'needs-attention-row': work.report_flags.needs_attention
                }"
              >
                <td class="is-title">
                  <strong :dir="textDirection(work.title)">{{ work.title }}</strong>
                  <code dir="ltr">{{ work.slug }}</code>
                  <small v-if="work.summary" :title="work.summary" :dir="textDirection(work.summary)">
                    {{ truncateText(work.summary, 74) }}
                  </small>
                </td>
                <td>
                  <span class="ym-works-reports-badge is-status" :class="statusClass(work.status)">
                    {{ statusLabel(work.status) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-badge" :class="visibilityClass(work.visibility_status)">
                    {{ visibilityLabel(work.visibility_status) }}
                  </span>
                </td>
                <td><code dir="ltr">{{ displayValue(work.media_type) }}</code></td>
                <td>
                  <span v-if="work.designer" class="ym-works-reports-person">
                    <strong :dir="textDirection(work.designer.name)">{{ work.designer.name }}</strong>
                    <small dir="ltr">#{{ work.designer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span v-if="work.reviewer" class="ym-works-reports-person">
                    <strong :dir="textDirection(work.reviewer.name)">{{ work.reviewer.name }}</strong>
                    <small dir="ltr">#{{ work.reviewer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td><code dir="ltr">{{ work.category_id ?? '—' }}</code></td>
                <td>
                  <span class="ym-works-reports-flag" :class="work.is_featured ? 'is-featured' : 'is-neutral'">
                    {{ booleanLabel(work.is_featured) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-flag" :class="work.is_pinned ? 'is-pinned' : 'is-neutral'">
                    {{ booleanLabel(work.is_pinned) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-count is-alert">
                    {{ formatNumber(work.reports_count) }}
                  </span>
                  <span class="ym-works-reports-flag is-reported">
                    {{ flagLabel('reported', work.report_flags.has_reports) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-flag" :class="flagClass('high', work.report_flags.high_reports)">
                    {{ flagLabel('high', work.report_flags.high_reports) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-flag" :class="flagClass('risk', work.report_flags.visibility_risk)">
                    {{ flagLabel('risk', work.report_flags.visibility_risk) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-reports-flag" :class="flagClass('attention', work.report_flags.needs_attention)">
                    {{ flagLabel('attention', work.report_flags.needs_attention) }}
                  </span>
                </td>
                <td><span class="ym-works-reports-count">{{ formatNumber(work.views_count) }}</span></td>
                <td><span class="ym-works-reports-count">{{ formatNumber(work.likes_count) }}</span></td>
                <td><time :datetime="work.submitted_at || undefined">{{ formatDateTime(work.submitted_at) }}</time></td>
                <td><time :datetime="work.published_at || undefined">{{ formatDateTime(work.published_at) }}</time></td>
                <td><time :datetime="work.hidden_at || undefined">{{ formatDateTime(work.hidden_at) }}</time></td>
                <td><time :datetime="work.created_at || undefined">{{ formatDateTime(work.created_at) }}</time></td>
                <td><time :datetime="work.updated_at || undefined">{{ formatDateTime(work.updated_at) }}</time></td>
                <td class="is-action">
                  <button
                    type="button"
                    class="ym-works-reports-details-button"
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

        <footer class="ym-works-reports-pagination">
          <div>
            <span>{{ copy.paginationTotal }}</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small>
          </div>
          <nav :aria-label="copy.paginationLabel">
            <button
              type="button"
              class="ym-works-reports-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              {{ copy.previous }}
            </button>
            <span>{{ copy.pageOf(pagination.current_page, pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-works-reports-button is-secondary"
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
      class="ym-reports-detail-backdrop"
      role="presentation"
      @click.self="closeDetails"
    >
      <section
        class="ym-reports-detail-drawer"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="drawerTitleId"
      >
        <header class="ym-reports-detail-drawer__head">
          <div>
            <span>{{ copy.detailReadonly }}</span>
            <h2 :id="drawerTitleId">{{ selectedWorkTitle || copy.detailsTitle }}</h2>
            <code v-if="selectedWorkId !== null" dir="ltr">#{{ selectedWorkId }}</code>
          </div>
          <button
            type="button"
            class="ym-reports-detail-drawer__close"
            :aria-label="copy.close"
            :title="copy.close"
            @click="closeDetails"
          >
            ×
          </button>
        </header>

        <div v-if="detailLoading" class="ym-reports-detail-state" role="status" aria-live="polite">
          <span class="ym-works-reports-spinner" aria-hidden="true" />
          <h3>{{ copy.detailsLoadingTitle }}</h3>
          <p>{{ copy.detailsLoadingCopy }}</p>
        </div>

        <div v-else-if="detailError" class="ym-reports-detail-state is-error" role="alert">
          <span class="ym-works-reports-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.detailsErrorTitle }}</h3>
          <p>{{ detailError }}</p>
          <button
            v-if="selectedWorkId !== null"
            type="button"
            class="ym-works-reports-button is-secondary"
            @click="retrySelectedDetails"
          >
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="detail" class="ym-reports-detail-content">
          <section class="ym-reports-detail-intro">
            <div>
              <span class="ym-works-reports-badge is-status" :class="statusClass(detail.work.status)">
                {{ statusLabel(detail.work.status) }}
              </span>
              <span class="ym-works-reports-badge" :class="visibilityClass(detail.work.visibility_status)">
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

          <section class="ym-reports-detail-section">
            <header>
              <h3>{{ copy.accessIndicators }}</h3>
              <p>{{ copy.accessIndicatorsCopy }}</p>
            </header>
            <div class="ym-reports-detail-access-grid">
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

          <section class="ym-reports-detail-section">
            <header><h3>{{ copy.basicDetails }}</h3></header>
            <dl class="ym-reports-detail-grid">
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
                <dt>{{ copy.featuredLabel }}</dt>
                <dd>{{ booleanLabel(detail.work.is_featured) }}</dd>
              </div>
              <div>
                <dt>{{ copy.pinnedLabel }}</dt>
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

          <section class="ym-reports-detail-section">
            <header><h3>{{ copy.people }}</h3></header>
            <div v-if="detail.field_access.can_view_designer" class="ym-reports-detail-people">
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
            <p v-else class="ym-reports-detail-unavailable">{{ copy.relationsUnavailable }}</p>
          </section>

          <section class="ym-reports-detail-section">
            <header><h3>{{ copy.media }}</h3></header>
            <div v-if="detail.media" class="ym-reports-detail-media">
              <span>
                {{ copy.mediaType }}:
                <code dir="ltr">{{ displayValue(detail.media.media_type) }}</code>
              </span>
              <strong :class="detail.media.has_media ? 'is-present' : 'is-absent'">
                {{ detail.media.has_media ? copy.mediaPresent : copy.mediaAbsent }}
              </strong>
            </div>
            <p v-else class="ym-reports-detail-unavailable">{{ copy.mediaUnavailable }}</p>
          </section>

          <section class="ym-reports-detail-section">
            <header><h3>{{ copy.lifecycle }}</h3></header>
            <dl class="ym-reports-detail-grid is-lifecycle">
              <div v-for="item in lifecycleItems" :key="item.key">
                <dt>{{ item.label }}</dt>
                <dd><time :datetime="item.value || undefined">{{ formatDateTime(item.value) }}</time></dd>
              </div>
            </dl>
          </section>

          <section class="ym-reports-detail-section is-private">
            <header>
              <h3>{{ copy.privateNotes }}</h3>
              <p>{{ copy.privateNotesCopy }}</p>
            </header>
            <dl v-if="detail.private_notes" class="ym-reports-detail-notes">
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
            <p v-else class="ym-reports-detail-unavailable">{{ copy.privateNotesUnavailable }}</p>
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
type VisibilityStatus = 'hidden' | 'public'
type BooleanFilter = '' | '1' | '0'
type PageSize = 15 | 25 | 50
type SortDirection = 'asc' | 'desc'
type ReportsSortKey = 'reports_count' | 'updated_at' | 'created_at' | 'submitted_at' | 'published_at' | 'title' | 'status' | 'views_count' | 'likes_count'
type ReportFlagKey = 'reported' | 'high' | 'risk' | 'attention'

interface UserReference {
  id: number
  name: string
}

interface ReportFlags {
  has_reports: boolean
  high_reports: boolean
  visibility_risk: boolean
  needs_attention: boolean
}

interface ReportedWorkItem {
  id: number
  title: string
  slug: string
  summary: string | null
  status: WorkStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  designer: UserReference | null
  reviewer: UserReference | null
  category_id: number | null
  is_featured: boolean
  is_pinned: boolean
  reports_count: number
  views_count: number
  likes_count: number
  submitted_at: string | null
  published_at: string | null
  hidden_at: string | null
  updated_at: string | null
  created_at: string | null
  report_flags: ReportFlags
}

interface ReportsPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface ReportsSummary {
  total: number
  reported: number
  high_reports: number
  public_reported: number
  hidden_reported: number
  published_reported: number
  review_queue_reported: number
  featured_reported: number
  pinned_reported: number
  total_reports: number
}

interface ReportsData {
  items: ReportedWorkItem[]
  pagination: ReportsPagination
  summary: ReportsSummary
  filters: Record<string, unknown>
}

interface ReportsResponse {
  success: boolean
  data: ReportsData | null
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

interface ReportsFilters {
  q: string
  status: '' | WorkStatus
  visibility_status: '' | VisibilityStatus
  media_type: string
  designer_id: string
  reviewer_id: string
  category_id: string
  min_reports: string
  is_featured: BooleanFilter
  is_pinned: BooleanFilter
  from: string
  to: string
  sort: ReportsSortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonly: 'قراءة وتنظيم فقط',
    kicker: 'إدارة بلاغات الأعمال ومؤشرات المخاطر',
    title: 'البلاغات والمخالفات',
    description: 'قائمة إدارية آمنة لقراءة وتنظيم الأعمال التي عليها بلاغات، مع مؤشرات واضحة للمخاطر والتفاصيل المسموحة فقط.',
    totalWorks: 'الأعمال المبلّغ عنها',
    filteredScope: 'أعمال مطابقة للفلاتر الحالية',
    authLoadingTitle: 'جارٍ التحقق من صلاحية البلاغات',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى البلاغات والمخالفات غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب صلاحيات القسم المطلوبة. لم تتم محاولة تحميل البيانات.',
    noticeTitle: 'لا توجد إجراءات تنفيذية في هذه الصفحة',
    notice: 'هذه المرحلة للقراءة والتنظيم فقط؛ لا تتضمن مراجعة البلاغ أو رفضه أو إخفاء العمل أو استعادته أو أرشفته أو طلب تعديل أو نشر أو حذف.',
    summaryLabel: 'ملخص بلاغات الأعمال',
    total: 'إجمالي الأعمال',
    totalHint: 'كل الأعمال المطابقة',
    reportedSummary: 'الأعمال المبلّغ عنها',
    reportedHint: 'كلها تحمل بلاغًا واحدًا أو أكثر',
    highReports: 'بلاغات مرتفعة',
    highReportsHint: 'خمسة بلاغات أو أكثر',
    publicReported: 'عامة ومبلّغ عنها',
    publicReportedHint: 'لا تزال ظاهرة للعامة',
    hiddenReported: 'مخفية ومبلّغ عنها',
    hiddenReportedHint: 'ظهورها أو حالتها مخفية',
    publishedReported: 'منشورة ومبلّغ عنها',
    publishedReportedHint: 'حالتها الحالية منشورة',
    reviewQueueReported: 'ضمن طابور المراجعة',
    reviewQueueReportedHint: 'مرسلة أو تحت المراجعة أو تطلب تعديلًا',
    featuredReported: 'مميزة ومبلّغ عنها',
    featuredReportedHint: 'تحمل علامة التمييز',
    pinnedReported: 'مثبتة ومبلّغ عنها',
    pinnedReportedHint: 'تحمل علامة التثبيت',
    totalReports: 'مجموع البلاغات',
    totalReportsHint: 'مجموع العدادات في النتائج المطابقة',
    filtersTitle: 'بحث وفلاتر البلاغات',
    filtersCopy: 'ضيّق قائمة الأعمال المبلّغ عنها عبر الحقول الآمنة التي يدعمها الخادم فقط.',
    search: 'البحث',
    searchPlaceholder: 'العنوان أو المعرّف النصي أو الملخص',
    searchHint: 'حرفان على الأقل، وبحد أقصى 80 حرفًا.',
    status: 'الحالة',
    visibility: 'الظهور',
    mediaType: 'نوع الوسائط',
    designerId: 'معرّف المصمم',
    reviewerId: 'معرّف المراجع',
    categoryId: 'معرّف التصنيف',
    minimumReports: 'الحد الأدنى للبلاغات',
    minimumReportsHint: 'من 1 إلى 100000.',
    from: 'حُدّث من',
    to: 'حُدّث إلى',
    updatedRangeHint: 'يطبق على تاريخ آخر تحديث.',
    perPage: 'لكل صفحة',
    all: 'الكل',
    yes: 'نعم',
    no: 'لا',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    resetHint: 'مسح الفلاتر والعودة إلى بلاغ واحد والترتيب الأعلى بلاغات أولًا',
    searchTooShort: 'نص البحث يجب أن يكون فارغًا أو يحتوي حرفين على الأقل.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    invalidIdentifiers: 'معرّفات المصمم والمراجع والتصنيف يجب أن تكون أعدادًا صحيحة موجبة.',
    invalidMinimumReports: 'الحد الأدنى للبلاغات يجب أن يكون عددًا صحيحًا بين 1 و100000.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من البحث والقيم والتواريخ.',
    tableTitle: 'قائمة بلاغات الأعمال',
    tableCopy: 'رتّب النتائج من رؤوس الأعمدة، وافتح التفاصيل عبر إجراء القراءة الوحيد.',
    currentPage: 'الصفحة الحالية',
    loadingTitle: 'جارٍ تحميل قائمة البلاغات',
    loadingCopy: 'يتم جلب القائمة الآمنة وفق الفلاتر الحالية...',
    errorTitle: 'تعذر تحميل قائمة البلاغات',
    genericError: 'حدث خطأ أثناء تحميل قائمة بلاغات الأعمال. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    emptyTitle: 'لا توجد أعمال مبلّغ عنها مطابقة',
    emptyCopy: 'لا توجد أعمال تحقق حد البلاغات والفلاتر الحالية. جرّب تعديل الفلاتر أو إعادة ضبطها.',
    workTitle: 'العنوان',
    designer: 'المصمم',
    reviewer: 'المراجع',
    category: 'التصنيف',
    highReportsFlag: 'بلاغات عالية',
    visibilityRiskFlag: 'خطر ظهور',
    needsAttentionFlag: 'يحتاج انتباه',
    reportedYes: 'عليه بلاغات',
    reportedNo: 'دون بلاغات',
    highYes: 'مرتفعة',
    highNo: 'دون الحد العالي',
    riskYes: 'خطر ظهور',
    riskNo: 'دون خطر ظهور',
    attentionYes: 'يحتاج انتباه',
    attentionNo: 'لا يحتاج انتباه',
    reports: 'عدد البلاغات',
    views: 'المشاهدات',
    likes: 'الإعجابات',
    publishedAt: 'تاريخ النشر',
    hiddenAt: 'تاريخ الإخفاء',
    createdAt: 'تاريخ الإنشاء',
    updatedAt: 'آخر تحديث',
    readAction: 'إجراء القراءة',
    viewDetails: 'عرض التفاصيل',
    viewDetailsHint: 'فتح تفاصيل العمل الآمنة',
    detailsPermissionRequired: 'يتطلب عرض التفاصيل صلاحيات قائمة وتفاصيل الأعمال',
    paginationTotal: 'إجمالي النتائج',
    visibleNow: 'عمل ظاهر الآن',
    paginationLabel: 'التنقل بين صفحات بلاغات الأعمال',
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
    featuredLabel: 'مميز',
    pinnedLabel: 'مثبت',
    people: 'المصمم والمراجع',
    notLinked: 'غير مرتبط',
    relationsUnavailable: 'المصمم والمراجع غير متاحين حسب الصلاحية.',
    media: 'الوسائط',
    mediaPresent: 'توجد وسائط مسجلة',
    mediaAbsent: 'لا توجد وسائط مسجلة',
    mediaUnavailable: 'بيانات الوسائط غير متاحة حسب الصلاحية.',
    lifecycle: 'التسلسل الزمني',
    submittedAt: 'تاريخ الإرسال',
    reviewedAt: 'تاريخ المراجعة',
    approvedAt: 'تاريخ الاعتماد',
    rejectedAt: 'تاريخ الرفض',
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
    kicker: 'Works reports and risk signals',
    title: 'Reports & Violations',
    description: 'A safe administrative list for reading and organizing reported works with clear risk signals and permission-scoped details.',
    totalWorks: 'Reported works',
    filteredScope: 'Works matching current filters',
    authLoadingTitle: 'Checking reports access',
    authLoadingCopy: 'Waiting for the user session to initialize before requesting data.',
    forbiddenTitle: 'Reports access is unavailable',
    forbiddenCopy: 'This account lacks the required section permissions. No data request was made.',
    noticeTitle: 'No mutation actions are available here',
    notice: 'This step is limited to reading and organization. It does not review or dismiss reports, hide or restore works, archive, request changes, publish, or delete.',
    summaryLabel: 'Works reports summary',
    total: 'Total works',
    totalHint: 'All matching works',
    reportedSummary: 'Reported works',
    reportedHint: 'Each has one or more reports',
    highReports: 'High reports',
    highReportsHint: 'Five reports or more',
    publicReported: 'Public reported',
    publicReportedHint: 'Still publicly visible',
    hiddenReported: 'Hidden reported',
    hiddenReportedHint: 'Visibility or status is hidden',
    publishedReported: 'Published reported',
    publishedReportedHint: 'Currently published',
    reviewQueueReported: 'In review queue',
    reviewQueueReportedHint: 'Submitted, in review, or changes requested',
    featuredReported: 'Featured reported',
    featuredReportedHint: 'Marked as featured',
    pinnedReported: 'Pinned reported',
    pinnedReportedHint: 'Marked as pinned',
    totalReports: 'Total reports',
    totalReportsHint: 'Sum of report counters in matching results',
    filtersTitle: 'Reports search and filters',
    filtersCopy: 'Narrow reported works using only safe fields supported by the server.',
    search: 'Search',
    searchPlaceholder: 'Title, slug, or summary',
    searchHint: 'At least 2 and at most 80 characters.',
    status: 'Status',
    visibility: 'Visibility',
    mediaType: 'Media type',
    designerId: 'Designer ID',
    reviewerId: 'Reviewer ID',
    categoryId: 'Category ID',
    minimumReports: 'Minimum reports',
    minimumReportsHint: 'From 1 to 100000.',
    from: 'Updated from',
    to: 'Updated to',
    updatedRangeHint: 'Applied to the last update time.',
    perPage: 'Per page',
    all: 'All',
    yes: 'Yes',
    no: 'No',
    apply: 'Apply filters',
    reset: 'Reset',
    resetHint: 'Clear filters, restore one report, and sort by highest reports first',
    searchTooShort: 'Search must be empty or contain at least two characters.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    invalidIdentifiers: 'Designer, reviewer, and category identifiers must be positive integers.',
    invalidMinimumReports: 'Minimum reports must be an integer between 1 and 100000.',
    validationError: 'The filters could not be applied. Check the search, values, and dates.',
    tableTitle: 'Reported works list',
    tableCopy: 'Sort from supported headers and open details through the only read action.',
    currentPage: 'Current page',
    loadingTitle: 'Loading reported works',
    loadingCopy: 'Fetching the safe list using the current filters...',
    errorTitle: 'Could not load reported works',
    genericError: 'An error occurred while loading the reported works list. Try again.',
    retry: 'Retry',
    emptyTitle: 'No matching reported works',
    emptyCopy: 'No works meet the report threshold and current filters. Change or reset the filters.',
    workTitle: 'Title',
    designer: 'Designer',
    reviewer: 'Reviewer',
    category: 'Category',
    highReportsFlag: 'High reports',
    visibilityRiskFlag: 'Visibility risk',
    needsAttentionFlag: 'Needs attention',
    reportedYes: 'Has reports',
    reportedNo: 'No reports',
    highYes: 'High',
    highNo: 'Below high threshold',
    riskYes: 'Visibility risk',
    riskNo: 'No visibility risk',
    attentionYes: 'Needs attention',
    attentionNo: 'No attention needed',
    reports: 'Report count',
    views: 'Views',
    likes: 'Likes',
    publishedAt: 'Published at',
    hiddenAt: 'Hidden at',
    createdAt: 'Created at',
    updatedAt: 'Updated at',
    readAction: 'Read action',
    viewDetails: 'View details',
    viewDetailsHint: 'Open safe work details',
    detailsPermissionRequired: 'Work list and detail permissions are required',
    paginationTotal: 'Total results',
    visibleNow: 'works visible now',
    paginationLabel: 'Reported works pagination',
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
    featuredLabel: 'Featured',
    pinnedLabel: 'Pinned',
    people: 'Designer and reviewer',
    notLinked: 'Not linked',
    relationsUnavailable: 'Designer and reviewer are unavailable for this permission scope.',
    media: 'Media',
    mediaPresent: 'Media is recorded',
    mediaAbsent: 'No media is recorded',
    mediaUnavailable: 'Media data is unavailable for this permission scope.',
    lifecycle: 'Lifecycle',
    submittedAt: 'Submitted at',
    reviewedAt: 'Reviewed at',
    approvedAt: 'Approved at',
    rejectedAt: 'Rejected at',
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
const hasReportsAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.reports.view')
    && authStore.permissions.includes('admin.works.reports.list')
})
const canViewDetails = computed(() => (
  hasReportsAccess.value
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
  authStore.isInitialized && (!hasReportsAccess.value || serverForbidden.value)
))

const items = ref<ReportedWorkItem[]>([])
const pagination = reactive<ReportsPagination>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
const summary = reactive<ReportsSummary>(emptySummary())

function emptySummary(): ReportsSummary {
  return {
    total: 0,
    reported: 0,
    high_reports: 0,
    public_reported: 0,
    hidden_reported: 0,
    published_reported: 0,
    review_queue_reported: 0,
    featured_reported: 0,
    pinned_reported: 0,
    total_reports: 0
  }
}

function defaultFilters(): ReportsFilters {
  return {
    q: '',
    status: '',
    visibility_status: '',
    media_type: '',
    designer_id: '',
    reviewer_id: '',
    category_id: '',
    min_reports: '1',
    is_featured: '',
    is_pinned: '',
    from: '',
    to: '',
    sort: 'reports_count',
    direction: 'desc',
    per_page: 15
  }
}

const filters = reactive<ReportsFilters>(defaultFilters())
const appliedFilters = reactive<ReportsFilters>(defaultFilters())
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
const drawerTitleId = 'ym-reports-work-detail-title'

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

const summaryCards = computed(() => [
  { key: 'total', label: copy.value.total, value: summary.total, hint: copy.value.totalHint, color: '#8b5cf6' },
  { key: 'reported', label: copy.value.reportedSummary, value: summary.reported, hint: copy.value.reportedHint, color: '#f43f5e' },
  { key: 'high_reports', label: copy.value.highReports, value: summary.high_reports, hint: copy.value.highReportsHint, color: '#ef4444' },
  { key: 'public_reported', label: copy.value.publicReported, value: summary.public_reported, hint: copy.value.publicReportedHint, color: '#f97316' },
  { key: 'hidden_reported', label: copy.value.hiddenReported, value: summary.hidden_reported, hint: copy.value.hiddenReportedHint, color: '#64748b' },
  { key: 'published_reported', label: copy.value.publishedReported, value: summary.published_reported, hint: copy.value.publishedReportedHint, color: '#10b981' },
  { key: 'review_queue_reported', label: copy.value.reviewQueueReported, value: summary.review_queue_reported, hint: copy.value.reviewQueueReportedHint, color: '#38bdf8' },
  { key: 'featured_reported', label: copy.value.featuredReported, value: summary.featured_reported, hint: copy.value.featuredReportedHint, color: '#f59e0b' },
  { key: 'pinned_reported', label: copy.value.pinnedReported, value: summary.pinned_reported, hint: copy.value.pinnedReportedHint, color: '#a855f7' },
  { key: 'total_reports', label: copy.value.totalReports, value: summary.total_reports, hint: copy.value.totalReportsHint, color: '#e11d48' }
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

function flagLabel(key: ReportFlagKey, active: boolean): string {
  const labels = {
    reported: active ? copy.value.reportedYes : copy.value.reportedNo,
    high: active ? copy.value.highYes : copy.value.highNo,
    risk: active ? copy.value.riskYes : copy.value.riskNo,
    attention: active ? copy.value.attentionYes : copy.value.attentionNo
  }

  return labels[key]
}

function flagClass(key: ReportFlagKey, active: boolean): string {
  return active ? 'is-' + key : 'is-neutral'
}

function sortIndicator(key: ReportsSortKey): string {
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

  const identifiers = [filters.designer_id, filters.reviewer_id, filters.category_id]
  const hasInvalidIdentifier = identifiers.some((value) => {
    if (value.trim() === '') return false
    const parsed = Number(value)
    return !Number.isInteger(parsed) || parsed < 1
  })

  if (hasInvalidIdentifier) {
    filterError.value = copy.value.invalidIdentifiers
    return false
  }

  const minimumReports = Number(filters.min_reports)
  if (
    filters.min_reports.trim() === ''
    || !Number.isInteger(minimumReports)
    || minimumReports < 1
    || minimumReports > 100000
  ) {
    filterError.value = copy.value.invalidMinimumReports
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

  // نرسل مفاتيح قائمة السماح فقط، ولا نمرر القيم الفارغة أو أي حقول داخلية.
  const optionalFilters: Array<[string, string]> = [
    ['q', appliedFilters.q.trim()],
    ['status', appliedFilters.status],
    ['visibility_status', appliedFilters.visibility_status],
    ['media_type', appliedFilters.media_type.trim()],
    ['designer_id', appliedFilters.designer_id.trim()],
    ['reviewer_id', appliedFilters.reviewer_id.trim()],
    ['category_id', appliedFilters.category_id.trim()],
    ['min_reports', appliedFilters.min_reports.trim()],
    ['is_featured', appliedFilters.is_featured],
    ['is_pinned', appliedFilters.is_pinned],
    ['from', appliedFilters.from],
    ['to', appliedFilters.to]
  ]

  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }

  return query
}

async function fetchReportedWorks(): Promise<void> {
  if (!authStore.isInitialized || !hasReportsAccess.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++listRequestRevision
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<ReportsResponse>('/admin/works/reports', {
      query: buildListQuery()
    })

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasReportsAccess.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      clearReportsData()
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
      || !hasReportsAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      clearReportsData()
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
  void fetchReportedWorks()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
  void fetchReportedWorks()
}

function changeSort(key: ReportsSortKey): void {
  if (appliedFilters.sort === key) {
    appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'
  } else {
    appliedFilters.sort = key
    appliedFilters.direction = ['title', 'status'].includes(key) ? 'asc' : 'desc'
  }

  filters.sort = appliedFilters.sort
  filters.direction = appliedFilters.direction
  page.value = 1
  void fetchReportedWorks()
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
  void fetchReportedWorks()
}

function openDetails(work: ReportedWorkItem): void {
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

function clearReportsData(): void {
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
  clearReportsData()
  loading.value = false
  error.value = null
  filterError.value = null
  closeDetails()
}

function syncReportsAccessState(): void {
  if (!pageMounted) return

  accessRevision += 1
  serverForbidden.value = false
  closeDetails()

  // لا يصدر طلب قبل اكتمال المصادقة، كما تُرفض الأدوار الخارجية قبل الوصول إلى الخادم.
  if (!authStore.isInitialized) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (!hasReportsAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchReportedWorks()
}

watch(
  authorizationSignature,
  () => syncReportsAccessState(),
  { flush: 'post' }
)

onMounted(() => {
  pageMounted = true
  syncReportsAccessState()
})
</script>

<style scoped>
.ym-works-reports-page {
  color: var(--ym-text);
}

.ym-works-reports-hero,
.ym-works-reports-filter-card,
.ym-works-reports-table-card,
.ym-works-reports-access-state {
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
}

.ym-works-reports-hero {
  position: relative;
  min-height: 270px;
  overflow: hidden;
  background:
    linear-gradient(135deg, rgba(127, 29, 29, 0.94), rgba(15, 23, 42, 0.96) 54%, rgba(124, 45, 18, 0.92)),
    var(--ym-card-bg);
  color: #fff;
  padding: clamp(1.35rem, 4vw, 2.35rem);
}

.ym-works-reports-hero__grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: 32px 32px;
  mask-image: linear-gradient(to bottom, #000, transparent 92%);
  opacity: 0.4;
}

.ym-works-reports-hero__glow {
  position: absolute;
  width: 220px;
  height: 220px;
  border-radius: 999px;
  filter: blur(12px);
  opacity: 0.34;
}

.ym-works-reports-hero__glow.is-one {
  inset-block-start: -90px;
  inset-inline-end: 8%;
  background: #fb7185;
}

.ym-works-reports-hero__glow.is-two {
  inset-block-end: -130px;
  inset-inline-start: 12%;
  background: #f59e0b;
}

.ym-works-reports-hero__content {
  position: relative;
  z-index: 1;
  display: flex;
  min-height: 200px;
  align-items: flex-end;
  justify-content: space-between;
  gap: 2rem;
}

.ym-works-reports-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.ym-works-reports-chip {
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

.ym-works-reports-chip.is-brand {
  border-color: rgba(251, 113, 133, 0.4);
  color: #fda4af;
}

.ym-works-reports-chip.is-readonly {
  border-color: rgba(216, 180, 254, 0.34);
  color: #e9d5ff;
}

.ym-works-reports-kicker {
  color: #fda4af;
  font-size: 12px;
  font-weight: 950;
  letter-spacing: 0.04em;
  margin: 1.1rem 0 0.4rem;
}

.ym-works-reports-hero h1 {
  max-width: 760px;
  color: #fff;
  font-size: clamp(2.25rem, 5vw, 4rem);
  font-weight: 950;
  letter-spacing: -0.04em;
  line-height: 1.1;
  margin: 0;
}

.ym-works-reports-description {
  max-width: 700px;
  color: rgba(255, 255, 255, 0.72);
  font-size: 14px;
  font-weight: 750;
  line-height: 1.85;
  margin: 0.9rem 0 0;
}

.ym-works-reports-hero__summary {
  display: grid;
  flex: 0 0 auto;
  min-width: 190px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  border-radius: 24px;
  background: rgba(15, 23, 42, 0.42);
  backdrop-filter: blur(14px);
  padding: 1rem 1.15rem;
}

.ym-works-reports-hero__summary span,
.ym-works-reports-hero__summary small {
  color: rgba(255, 255, 255, 0.66);
  font-size: 11px;
  font-weight: 850;
}

.ym-works-reports-hero__summary strong {
  color: #fff;
  font-size: 2rem;
  font-weight: 950;
  margin: 0.25rem 0;
}

.ym-works-reports-notice {
  display: flex;
  align-items: flex-start;
  gap: 0.9rem;
  border: 1px solid rgba(245, 158, 11, 0.28);
  border-radius: 22px;
  background: color-mix(in srgb, #f59e0b 8%, var(--ym-control-bg));
  padding: 1rem 1.15rem;
}

.ym-works-reports-notice > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.14);
  color: #fbbf24;
  font-size: 11px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-works-reports-notice strong {
  display: block;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-reports-notice p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.2rem 0 0;
}

.ym-works-reports-summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
}

.ym-works-reports-summary-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--reports-accent) 17%, transparent), transparent 52%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1rem;
}

.ym-works-reports-summary-card::after {
  position: absolute;
  inset-block: 0;
  inset-inline-start: 0;
  width: 3px;
  background: var(--reports-accent);
  content: '';
  opacity: 0.85;
}

.ym-works-reports-summary-card.is-alert {
  border-color: rgba(244, 63, 94, 0.35);
}

.ym-works-reports-summary-card.is-risk {
  border-color: rgba(245, 158, 11, 0.4);
}

.ym-works-reports-summary-card span,
.ym-works-reports-summary-card small {
  display: block;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-reports-summary-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  margin: 0.35rem 0;
}

.ym-works-reports-filter-card,
.ym-works-reports-table-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-works-reports-filter-card > header,
.ym-works-reports-table-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-works-reports-filter-card h2,
.ym-works-reports-table-card h2,
.ym-works-reports-access-state h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-reports-filter-card header p,
.ym-works-reports-table-card__head p,
.ym-works-reports-access-state p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-works-reports-filter-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.9rem;
}

.ym-works-reports-filter-grid label {
  display: grid;
  align-content: start;
  gap: 0.42rem;
}

.ym-works-reports-filter-grid label.is-search {
  grid-column: span 2;
}

.ym-works-reports-filter-grid label > span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-works-reports-filter-grid label > small {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 750;
}

.ym-works-reports-filter-grid input,
.ym-works-reports-filter-grid select {
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

.ym-works-reports-filter-grid input:focus,
.ym-works-reports-filter-grid select:focus {
  border-color: #10b981;
  box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.14);
}

.ym-works-reports-filter-grid select option {
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
}

.ym-works-reports-filter-actions {
  display: flex;
  align-items: flex-end;
}

.ym-works-reports-button {
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

.ym-works-reports-button.is-primary {
  min-width: 130px;
  background: linear-gradient(135deg, #059669, #7c3aed);
  color: #fff;
  box-shadow: 0 12px 28px rgba(5, 150, 105, 0.2);
}

.ym-works-reports-button.is-secondary {
  border-color: var(--ym-control-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-works-reports-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-works-reports-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.ym-works-reports-filter-error {
  border: 1px solid rgba(244, 63, 94, 0.34);
  border-radius: 15px;
  background: rgba(244, 63, 94, 0.1);
  color: #fb7185;
  font-size: 12px;
  font-weight: 850;
  margin: 1rem 0 0;
  padding: 0.75rem 0.85rem;
}

.ym-works-reports-table-card__head {
  align-items: center;
}

.ym-works-reports-table-state {
  display: grid;
  min-width: 130px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: var(--ym-control-bg);
  padding: 0.65rem 0.8rem;
}

.ym-works-reports-table-state span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-reports-table-state strong {
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 950;
}

.ym-works-reports-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 20px;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
}

.ym-works-reports-table {
  width: 100%;
  min-width: 2920px;
  border-collapse: collapse;
  background: color-mix(in srgb, var(--ym-card-bg) 88%, transparent);
}

.ym-works-reports-table th,
.ym-works-reports-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-muted);
  font-size: 12px;
  padding: 0.86rem 0.75rem;
  text-align: start;
  vertical-align: middle;
}

.ym-works-reports-table th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: var(--ym-dropdown-bg);
  color: var(--ym-text);
  font-weight: 950;
  white-space: nowrap;
}

.ym-works-reports-table tbody tr {
  transition: background 150ms ease;
}

.ym-works-reports-table tbody tr.is-high-reports-row {
  background: color-mix(in srgb, #fb7185 4%, transparent);
}

.ym-works-reports-table tbody tr.needs-attention-row {
  box-shadow: inset 3px 0 0 rgba(245, 158, 11, 0.68);
}

.ym-works-reports-table tbody tr:hover {
  background: var(--ym-row-hover);
}

.ym-works-reports-table tbody tr:last-child td {
  border-bottom: 0;
}

.ym-works-reports-table th.is-title,
.ym-works-reports-table td.is-title {
  width: 310px;
  min-width: 310px;
}

.ym-works-reports-table td.is-title strong,
.ym-works-reports-table td.is-title code,
.ym-works-reports-table td.is-title small,
.ym-works-reports-person strong,
.ym-works-reports-person small {
  display: block;
}

.ym-works-reports-table td.is-title strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-reports-table td.is-title code {
  color: #34d399;
  font-size: 10px;
  margin-top: 0.2rem;
  overflow-wrap: anywhere;
}

.ym-works-reports-table td.is-title small {
  max-width: 290px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.55;
  margin-top: 0.35rem;
}

.ym-works-reports-sort {
  display: inline-flex;
  align-items: center;
  gap: 0.42rem;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  padding: 0;
}

.ym-works-reports-sort span {
  display: inline-grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  border-radius: 7px;
  background: rgba(16, 185, 129, 0.13);
  color: #34d399;
}

.ym-works-reports-badge,
.ym-works-reports-flag {
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

.ym-works-reports-badge.is-submitted,
.ym-works-reports-badge.is-in-review {
  border-color: rgba(56, 189, 248, 0.35);
  background: rgba(56, 189, 248, 0.12);
  color: #38bdf8;
}

.ym-works-reports-badge.is-changes-requested {
  border-color: rgba(245, 158, 11, 0.38);
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}

.ym-works-reports-badge.is-approved,
.ym-works-reports-badge.is-published,
.ym-works-reports-badge.is-public {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
}

.ym-works-reports-badge.is-draft,
.ym-works-reports-badge.is-hidden,
.ym-works-reports-badge.is-archived {
  border-color: rgba(148, 163, 184, 0.35);
  background: rgba(100, 116, 139, 0.13);
  color: #cbd5e1;
}

.ym-works-reports-badge.is-rejected {
  border-color: rgba(244, 63, 94, 0.36);
  background: rgba(244, 63, 94, 0.12);
  color: #fb7185;
}

.ym-works-reports-flag.is-featured {
  border-color: rgba(245, 158, 11, 0.38);
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
}

.ym-works-reports-flag.is-pinned {
  border-color: rgba(168, 85, 247, 0.38);
  background: rgba(168, 85, 247, 0.12);
  color: #d8b4fe;
}

.ym-works-reports-flag.is-public {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.1);
  color: #6ee7b7;
}

.ym-works-reports-flag.is-hidden {
  border-color: rgba(148, 163, 184, 0.36);
  background: rgba(100, 116, 139, 0.12);
  color: #cbd5e1;
}

.ym-works-reports-flag.is-reported {
  border-color: rgba(244, 63, 94, 0.4);
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
}

.ym-works-reports-flag.is-high {
  border-color: rgba(244, 63, 94, 0.45);
  background: rgba(244, 63, 94, 0.15);
  color: #fb7185;
}

.ym-works-reports-flag.is-risk {
  border-color: rgba(245, 158, 11, 0.42);
  background: rgba(245, 158, 11, 0.14);
  color: #fbbf24;
}

.ym-works-reports-flag.is-attention {
  border-color: rgba(249, 115, 22, 0.44);
  background: rgba(249, 115, 22, 0.14);
  color: #fb923c;
}

.ym-works-reports-flag.is-neutral {
  color: #94a3b8;
}

.ym-works-reports-person {
  min-width: 130px;
}

.ym-works-reports-person strong {
  color: var(--ym-text);
  font-size: 11px;
  font-weight: 900;
}

.ym-works-reports-person small {
  color: var(--ym-muted);
  font-size: 9px;
  margin-top: 0.18rem;
}

.ym-works-reports-count {
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

.ym-works-reports-count.is-alert {
  background: rgba(244, 63, 94, 0.13);
  color: #fb7185;
}

.ym-works-reports-table time {
  display: inline-block;
  min-width: 125px;
  color: var(--ym-muted);
  font-size: 10px;
  line-height: 1.5;
}

.ym-works-reports-table th.is-action,
.ym-works-reports-table td.is-action {
  position: sticky;
  inset-inline-end: 0;
  z-index: 1;
  min-width: 130px;
  background: var(--ym-dropdown-bg);
}

.ym-works-reports-table th.is-action {
  z-index: 3;
}

.ym-works-reports-details-button {
  width: 100%;
  min-height: 38px;
  border: 1px solid rgba(16, 185, 129, 0.4);
  border-radius: 12px;
  background: rgba(16, 185, 129, 0.12);
  color: #6ee7b7;
  font-size: 11px;
  font-weight: 950;
  padding: 0.55rem 0.7rem;
  transition: background 160ms ease, transform 160ms ease;
}

.ym-works-reports-details-button:hover:not(:disabled) {
  background: rgba(16, 185, 129, 0.2);
  transform: translateY(-1px);
}

.ym-works-reports-details-button:disabled {
  cursor: not-allowed;
  filter: grayscale(0.6);
  opacity: 0.45;
}

.ym-works-reports-state,
.ym-works-reports-access-state,
.ym-reports-detail-state {
  display: grid;
  min-height: 240px;
  place-items: center;
  align-content: center;
  gap: 0.7rem;
  color: var(--ym-muted);
  padding: 2rem;
  text-align: center;
}

.ym-works-reports-state h3,
.ym-reports-detail-state h3 {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
  margin: 0;
}

.ym-works-reports-state p,
.ym-reports-detail-state p {
  max-width: 34rem;
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0;
}

.ym-works-reports-state.is-error,
.ym-reports-detail-state.is-error,
.ym-works-reports-access-state.is-forbidden {
  color: #fb7185;
}

.ym-works-reports-state__icon,
.ym-works-reports-empty-icon {
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

.ym-works-reports-empty-icon {
  background: rgba(148, 163, 184, 0.13);
  color: var(--ym-muted);
}

.ym-works-reports-spinner {
  width: 2.35rem;
  height: 2.35rem;
  border: 3px solid rgba(16, 185, 129, 0.2);
  border-top-color: #34d399;
  border-radius: 999px;
  animation: ym-works-reports-spin 760ms linear infinite;
}

.ym-works-reports-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-top: 1rem;
}

.ym-works-reports-pagination > div {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-works-reports-pagination > div strong {
  color: var(--ym-text);
  font-size: 1.1rem;
  font-weight: 950;
}

.ym-works-reports-pagination nav {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.ym-works-reports-pagination nav span {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 900;
}

.ym-reports-detail-backdrop {
  position: fixed;
  inset: 0;
  z-index: 120;
  display: flex;
  justify-content: flex-end;
  background: rgba(2, 6, 23, 0.68);
  backdrop-filter: blur(6px);
}

.ym-reports-detail-drawer {
  width: min(660px, 100%);
  height: 100dvh;
  overflow-y: auto;
  border-inline-start: 1px solid var(--ym-card-border);
  background: var(--ym-dropdown-bg);
  box-shadow: -24px 0 64px rgba(2, 6, 23, 0.38);
  color: var(--ym-text);
}

.ym-reports-detail-drawer__head {
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

.ym-reports-detail-drawer__head span,
.ym-reports-detail-drawer__head code {
  display: block;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 850;
}

.ym-reports-detail-drawer__head h2 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.35;
  margin: 0.2rem 0;
}

.ym-reports-detail-drawer__close {
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

.ym-reports-detail-content {
  display: grid;
  gap: 1rem;
  padding: 1.25rem;
}

.ym-reports-detail-intro,
.ym-reports-detail-section {
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-card-bg);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.07);
  padding: 1rem;
}

.ym-reports-detail-intro > div {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
}

.ym-reports-detail-intro h3 {
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.45;
  margin: 0.8rem 0 0.25rem;
}

.ym-reports-detail-intro code {
  color: #34d399;
  font-size: 11px;
  overflow-wrap: anywhere;
}

.ym-reports-detail-intro p {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 750;
  line-height: 1.8;
  margin: 0.75rem 0 0;
}

.ym-reports-detail-section > header {
  margin-bottom: 0.8rem;
}

.ym-reports-detail-section > header h3 {
  color: var(--ym-text);
  font-size: 1rem;
  font-weight: 950;
  margin: 0;
}

.ym-reports-detail-section > header p {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 750;
  line-height: 1.65;
  margin: 0.25rem 0 0;
}

.ym-reports-detail-access-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-reports-detail-access-grid > span {
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

.ym-reports-detail-access-grid > span strong {
  font-size: 12px;
  font-weight: 950;
}

.ym-reports-detail-access-grid > span.is-allowed strong {
  color: #34d399;
}

.ym-reports-detail-access-grid > span.is-denied strong {
  color: #94a3b8;
}

.ym-reports-detail-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.65rem;
  margin: 0;
}

.ym-reports-detail-grid > div,
.ym-reports-detail-people article,
.ym-reports-detail-notes > div {
  min-width: 0;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  padding: 0.7rem;
}

.ym-reports-detail-grid dt,
.ym-reports-detail-notes dt,
.ym-reports-detail-people span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-reports-detail-grid dd,
.ym-reports-detail-notes dd {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 900;
  line-height: 1.65;
  margin: 0.3rem 0 0;
  overflow-wrap: anywhere;
}

.ym-reports-detail-grid.is-lifecycle {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ym-reports-detail-people {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.65rem;
}

.ym-reports-detail-people strong,
.ym-reports-detail-people small {
  display: block;
}

.ym-reports-detail-people strong {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
  margin-top: 0.3rem;
}

.ym-reports-detail-people small {
  color: var(--ym-muted);
  font-size: 10px;
  margin-top: 0.18rem;
}

.ym-reports-detail-media {
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

.ym-reports-detail-media strong.is-present {
  color: #34d399;
}

.ym-reports-detail-media strong.is-absent {
  color: #94a3b8;
}

.ym-reports-detail-notes {
  display: grid;
  gap: 0.65rem;
  margin: 0;
}

.ym-reports-detail-section.is-private {
  border-color: color-mix(in srgb, #a78bfa 30%, var(--ym-soft-border));
}

.ym-reports-detail-unavailable {
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

@keyframes ym-works-reports-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1280px) {
  .ym-works-reports-filter-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

@media (max-width: 900px) {
  .ym-works-reports-hero__content,
  .ym-works-reports-filter-card > header,
  .ym-works-reports-table-card__head,
  .ym-works-reports-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-reports-hero__summary {
    min-width: 0;
  }

  .ym-works-reports-summary-grid,
  .ym-works-reports-filter-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-reports-pagination nav {
    justify-content: space-between;
  }
}

@media (max-width: 640px) {
  .ym-works-reports-page {
    font-size: 14px;
  }

  .ym-works-reports-hero,
  .ym-works-reports-filter-card,
  .ym-works-reports-table-card,
  .ym-works-reports-access-state {
    border-radius: 22px;
  }

  .ym-works-reports-hero h1 {
    font-size: 2rem;
  }

  .ym-works-reports-notice {
    flex-direction: column;
  }

  .ym-works-reports-summary-grid,
  .ym-works-reports-filter-grid,
  .ym-reports-detail-access-grid,
  .ym-reports-detail-grid,
  .ym-reports-detail-grid.is-lifecycle,
  .ym-reports-detail-people {
    grid-template-columns: 1fr;
  }

  .ym-works-reports-filter-grid label.is-search {
    grid-column: auto;
  }

  .ym-works-reports-filter-actions,
  .ym-works-reports-filter-actions .ym-works-reports-button {
    width: 100%;
  }

  .ym-works-reports-pagination nav {
    display: grid;
    grid-template-columns: 1fr;
    text-align: center;
  }

  .ym-reports-detail-drawer__head,
  .ym-reports-detail-content {
    padding-inline: 1rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-works-reports-spinner {
    animation-duration: 1.8s;
  }

  .ym-works-reports-button,
  .ym-works-reports-details-button,
  .ym-works-reports-table tbody tr {
    transition: none;
  }
}
</style>
