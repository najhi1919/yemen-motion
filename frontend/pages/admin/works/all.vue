<template>
  <div class="ym-works-all-page space-y-7" :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'">
    <section class="ym-works-all-hero">
      <div class="ym-works-all-hero__glow is-one" />
      <div class="ym-works-all-hero__glow is-two" />
      <div class="ym-works-all-hero__grid" aria-hidden="true" />

      <div class="ym-works-all-hero__content">
        <div>
          <div class="ym-works-all-chips">
            <span class="ym-works-all-chip is-brand">Yemen Motion</span>
            <span class="ym-works-all-chip is-readonly">{{ managementBadge }}</span>
          </div>
          <p class="ym-works-all-kicker">{{ copy.kicker }}</p>
          <h1>{{ copy.title }}</h1>
          <p class="ym-works-all-description">
            {{ taxonomyDescription }}
          </p>
        </div>

        <div class="ym-works-all-hero__summary">
          <span>{{ copy.totalWorks }}</span>
          <strong>{{ formatNumber(pagination.total) }}</strong>
          <small>{{ copy.safeRecords }}</small>
        </div>
      </div>
    </section>

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
      <aside class="ym-works-all-notice" role="note">
        <span>{{ managementBadge }}</span>
        <p>{{ taxonomyNotice }}</p>
      </aside>

      <section class="ym-works-all-summary-grid" :aria-label="copy.pageSummary">
        <article
          v-for="card in summaryCards"
          :key="card.key"
          class="ym-works-all-summary-card"
          :style="{ '--works-all-accent': card.color }"
        >
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-works-all-filter-card">
        <header>
          <div>
            <h2>{{ copy.filtersTitle }}</h2>
            <p>{{ copy.filtersCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-works-all-button is-secondary"
            :disabled="loading"
            :title="copy.resetHint"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </header>

        <form class="ym-works-all-filter-grid" @submit.prevent="applyFilters">
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
            <input v-model="filters.category_id" type="number" inputmode="numeric" />
          </label>

          <label>
            <span>{{ copy.featured }}</span>
            <select v-model="filters.is_featured">
              <option v-for="option in booleanOptions" :key="`featured-${option.value}`" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.pinned }}</span>
            <select v-model="filters.is_pinned">
              <option v-for="option in booleanOptions" :key="`pinned-${option.value}`" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </label>

          <label>
            <span>{{ copy.reported }}</span>
            <select v-model="filters.reported">
              <option v-for="option in booleanOptions" :key="`reported-${option.value}`" :value="option.value">
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

          <div class="ym-works-all-filter-actions">
            <button type="submit" class="ym-works-all-button is-primary" :disabled="loading">
              {{ copy.apply }}
            </button>
          </div>
        </form>

        <p v-if="filterError" class="ym-works-all-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-works-all-table-card">
        <header class="ym-works-all-table-card__head">
          <div>
            <h2>{{ copy.tableTitle }}</h2>
            <p>{{ taxonomyTableCopy }}</p>
          </div>
          <div class="ym-works-all-table-state">
            <span>{{ copy.currentPage }}</span>
            <strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong>
          </div>
        </header>

        <div v-if="loading" class="ym-works-all-state" role="status" aria-live="polite">
          <span class="ym-works-all-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-works-all-state is-error" role="alert">
          <span class="ym-works-all-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-all-button is-secondary" @click="fetchWorks">
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="items.length === 0" class="ym-works-all-state" role="status">
          <span class="ym-works-all-empty-icon" aria-hidden="true">0</span>
          <h3>{{ copy.emptyTitle }}</h3>
          <p>{{ copy.emptyCopy }}</p>
        </div>

        <div v-else class="ym-works-all-table-wrap">
          <table class="ym-works-all-table">
            <thead>
              <tr>
                <th v-if="canManageBulkTaxonomy" class="is-selection">
                  <input
                    ref="currentPageCheckbox"
                    type="checkbox"
                    :checked="allCurrentPageSelected"
                    :aria-label="copy.selectCurrentPage"
                    :disabled="items.length === 0"
                    @change="toggleCurrentPage"
                  />
                </th>
                <th class="is-title">
                  <button type="button" class="ym-works-all-sort" @click="changeSort('title')">
                    {{ copy.workTitle }}
                    <span aria-hidden="true">{{ sortIndicator('title') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('status')">
                    {{ copy.status }}
                    <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                  </button>
                </th>
                <th>{{ copy.visibility }}</th>
                <th>{{ copy.mediaType }}</th>
                <th>{{ copy.designer }}</th>
                <th>{{ copy.reviewer }}</th>
                <th class="is-taxonomy">{{ copy.taxonomy }}</th>
                <th>{{ copy.featured }}</th>
                <th>{{ copy.pinned }}</th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('reports_count')">
                    {{ copy.reports }}
                    <span aria-hidden="true">{{ sortIndicator('reports_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('views_count')">
                    {{ copy.views }}
                    <span aria-hidden="true">{{ sortIndicator('views_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('likes_count')">
                    {{ copy.likes }}
                    <span aria-hidden="true">{{ sortIndicator('likes_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('submitted_at')">
                    {{ copy.submittedAt }}
                    <span aria-hidden="true">{{ sortIndicator('submitted_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('published_at')">
                    {{ copy.publishedAt }}
                    <span aria-hidden="true">{{ sortIndicator('published_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('created_at')">
                    {{ copy.createdAt }}
                    <span aria-hidden="true">{{ sortIndicator('created_at') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-all-sort" @click="changeSort('updated_at')">
                    {{ copy.updatedAt }}
                    <span aria-hidden="true">{{ sortIndicator('updated_at') }}</span>
                  </button>
                </th>
                <th class="is-action">{{ copy.actions }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="work in items" :key="work.id">
                <td v-if="canManageBulkTaxonomy" class="is-selection">
                  <input
                    type="checkbox"
                    :checked="isWorkSelected(work.id)"
                    :disabled="!isWorkSelected(work.id) && selectionAtLimit"
                    :aria-label="copy.selectWork(work.title)"
                    @change="toggleWork(work)"
                  />
                </td>
                <td class="is-title">
                  <strong :dir="textDirection(work.title)">{{ work.title }}</strong>
                  <code dir="ltr">{{ work.slug }}</code>
                  <small v-if="work.summary" :title="work.summary" :dir="textDirection(work.summary)">
                    {{ truncateText(work.summary, 74) }}
                  </small>
                </td>
                <td>
                  <span class="ym-works-all-badge is-status" :class="statusClass(work.status)">
                    {{ statusLabel(work.status) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-all-badge" :class="visibilityClass(work.visibility_status)">
                    {{ visibilityLabel(work.visibility_status) }}
                  </span>
                </td>
                <td><code dir="ltr">{{ displayValue(work.media_type) }}</code></td>
                <td>
                  <span v-if="work.designer" class="ym-works-all-person">
                    <strong :dir="textDirection(work.designer.name)">{{ work.designer.name }}</strong>
                    <small dir="ltr">#{{ work.designer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td>
                  <span v-if="work.reviewer" class="ym-works-all-person">
                    <strong :dir="textDirection(work.reviewer.name)">{{ work.reviewer.name }}</strong>
                    <small dir="ltr">#{{ work.reviewer.id }}</small>
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="is-taxonomy">
                  <div class="ym-work-taxonomy-cell">
                    <div class="ym-work-taxonomy-category">
                      <template v-if="work.taxonomy.category">
                        <strong>{{ taxonomyName(work.taxonomy.category) }}</strong>
                        <code dir="ltr">{{ work.taxonomy.category.slug }}</code>
                        <span class="ym-work-taxonomy-state" :class="work.taxonomy.category.is_active ? 'is-active' : 'is-disabled'">
                          {{ work.taxonomy.category.is_active ? copy.activeTaxonomy : copy.disabledTaxonomy }}
                        </span>
                      </template>
                      <template v-else-if="work.taxonomy.category_tracking?.is_legacy_unmapped">
                        <span class="ym-work-taxonomy-state is-legacy">{{ copy.legacyUnmapped }}</span>
                        <code dir="ltr">#{{ work.category_id }}</code>
                      </template>
                      <span v-else-if="work.taxonomy.category_tracking?.is_uncategorized" class="ym-work-taxonomy-state is-empty">
                        {{ copy.uncategorized }}
                      </span>
                      <span v-else class="ym-work-taxonomy-unavailable">{{ copy.taxonomyUnavailable }}</span>
                    </div>
                    <div v-if="work.taxonomy.tags !== null" class="ym-work-taxonomy-tags">
                      <span
                        v-for="tag in work.taxonomy.tags.slice(0, 3)"
                        :key="tag.id"
                        class="ym-work-taxonomy-tag"
                        :class="{ 'is-disabled': !tag.is_active }"
                      >
                        {{ taxonomyName(tag) }}
                      </span>
                      <span v-if="work.taxonomy.tags.length > 3" class="ym-work-taxonomy-more" dir="ltr">
                        +{{ work.taxonomy.tags.length - 3 }}
                      </span>
                      <small v-if="work.taxonomy.tags.length === 0">{{ copy.noTags }}</small>
                    </div>
                    <small v-else class="ym-work-taxonomy-unavailable">{{ copy.tagsUnavailable }}</small>
                  </div>
                </td>
                <td>
                  <span class="ym-works-all-badge is-boolean" :class="work.is_featured ? 'is-yes' : 'is-no'">
                    {{ booleanLabel(work.is_featured) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-all-badge is-boolean" :class="work.is_pinned ? 'is-yes' : 'is-no'">
                    {{ booleanLabel(work.is_pinned) }}
                  </span>
                </td>
                <td>
                  <span class="ym-works-all-count" :class="work.reports_count > 0 ? 'is-alert' : ''">
                    {{ formatNumber(work.reports_count) }}
                  </span>
                </td>
                <td><span class="ym-works-all-count">{{ formatNumber(work.views_count) }}</span></td>
                <td><span class="ym-works-all-count">{{ formatNumber(work.likes_count) }}</span></td>
                <td><time :datetime="work.submitted_at || undefined">{{ formatDateTime(work.submitted_at) }}</time></td>
                <td><time :datetime="work.published_at || undefined">{{ formatDateTime(work.published_at) }}</time></td>
                <td><time :datetime="work.created_at || undefined">{{ formatDateTime(work.created_at) }}</time></td>
                <td><time :datetime="work.updated_at || undefined">{{ formatDateTime(work.updated_at) }}</time></td>
                <td class="is-action">
                  <div class="ym-works-all-row-actions">
                    <button
                      type="button"
                      class="ym-works-all-details-button"
                      :disabled="!canViewDetails"
                      :aria-label="copy.viewDetailsHint"
                      @click="openDetails(work)"
                    >
                      {{ copy.viewDetails }}
                    </button>
                    <button
                      v-if="canManageIndividualTaxonomy"
                      type="button"
                      class="ym-works-all-taxonomy-button"
                      :aria-label="copy.manageTaxonomyFor(work.title)"
                      @click="openTaxonomyAssignment(work)"
                    >
                      {{ copy.manageTaxonomy }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="selectionMessage" class="ym-works-all-selection-message" role="status">
          {{ selectionMessage }}
        </p>
        <p v-if="bulkRefreshWarning" class="ym-works-all-selection-message is-warning" role="alert">
          {{ bulkRefreshWarning }}
          <button type="button" @click="retryBulkRefresh">{{ copy.retry }}</button>
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

        <footer class="ym-works-all-pagination">
          <div>
            <span>{{ copy.paginationTotal }}</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small>
          </div>
          <nav :aria-label="copy.paginationLabel">
            <button
              type="button"
              class="ym-works-all-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              {{ copy.previous }}
            </button>
            <span>{{ copy.pageOf(pagination.current_page, pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-works-all-button is-secondary"
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
      class="ym-work-detail-backdrop"
      role="presentation"
      @click.self="closeDetails"
    >
        <section
          class="ym-work-detail-drawer"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="drawerTitleId"
        >
          <header class="ym-work-detail-drawer__head">
            <div>
              <span>{{ copy.detailReadonly }}</span>
              <h2 :id="drawerTitleId">{{ selectedWorkTitle || copy.detailsTitle }}</h2>
              <code v-if="selectedWorkId !== null" dir="ltr">#{{ selectedWorkId }}</code>
            </div>
            <button
              type="button"
              class="ym-work-detail-drawer__close"
              :aria-label="copy.close"
              :title="copy.close"
              @click="closeDetails"
            >
              ×
            </button>
          </header>

          <div v-if="detailLoading" class="ym-work-detail-state" role="status" aria-live="polite">
            <span class="ym-works-all-spinner" aria-hidden="true" />
            <h3>{{ copy.detailsLoadingTitle }}</h3>
            <p>{{ copy.detailsLoadingCopy }}</p>
          </div>

          <div v-else-if="detailError" class="ym-work-detail-state is-error" role="alert">
            <span class="ym-works-all-state__icon" aria-hidden="true">!</span>
            <h3>{{ copy.detailsErrorTitle }}</h3>
            <p>{{ detailError }}</p>
            <button
              v-if="selectedWorkId !== null"
              type="button"
              class="ym-works-all-button is-secondary"
              @click="retrySelectedDetails"
            >
              {{ copy.retry }}
            </button>
          </div>

          <div v-else-if="detail" class="ym-work-detail-content">
            <section class="ym-work-detail-intro">
              <div>
                <span class="ym-works-all-badge is-status" :class="statusClass(detail.work.status)">
                  {{ statusLabel(detail.work.status) }}
                </span>
                <span class="ym-works-all-badge" :class="visibilityClass(detail.work.visibility_status)">
                  {{ visibilityLabel(detail.work.visibility_status) }}
                </span>
              </div>
              <h3 :dir="textDirection(detail.work.title)">{{ detail.work.title }}</h3>
              <code dir="ltr">{{ detail.work.slug }}</code>
              <p v-if="detail.work.summary" :dir="textDirection(detail.work.summary)">{{ detail.work.summary }}</p>
              <p v-else>{{ copy.noSummary }}</p>
            </section>

            <section class="ym-work-detail-section">
              <header>
                <h3>{{ copy.accessIndicators }}</h3>
                <p>{{ copy.accessIndicatorsCopy }}</p>
              </header>
              <div class="ym-work-detail-access-grid">
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

            <section class="ym-work-detail-section">
              <header>
                <h3>{{ copy.basicDetails }}</h3>
              </header>
              <dl class="ym-work-detail-grid">
                <div>
                  <dt>{{ copy.priceAmount }}</dt>
                  <dd dir="ltr">{{ displayValue(detail.work.price_amount) }}</dd>
                </div>
                <div>
                  <dt>{{ copy.deliveryDays }}</dt>
                  <dd>{{ detail.work.delivery_days === null ? '—' : formatNumber(detail.work.delivery_days) }}</dd>
                </div>
                <div v-if="detail.taxonomy_access.can_view_category">
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

            <section class="ym-work-detail-section is-taxonomy">
              <header class="ym-work-detail-taxonomy-head">
                <div>
                  <h3>{{ copy.taxonomy }}</h3>
                  <p>{{ copy.detailTaxonomyCopy }}</p>
                </div>
                <button
                  v-if="canManageIndividualTaxonomy && assignmentWorkForDetail"
                  type="button"
                  class="ym-works-all-taxonomy-button"
                  @click="openTaxonomyAssignment(assignmentWorkForDetail)"
                >
                  {{ copy.manageTaxonomy }}
                </button>
              </header>

              <div class="ym-work-detail-taxonomy">
                <article>
                  <span>{{ copy.category }}</span>
                  <template v-if="detail.taxonomy.category">
                    <strong>{{ taxonomyName(detail.taxonomy.category) }}</strong>
                    <small>{{ detail.taxonomy.category.name_ar }} · {{ detail.taxonomy.category.name_en }}</small>
                    <code dir="ltr">{{ detail.taxonomy.category.slug }}</code>
                    <b :class="detail.taxonomy.category.is_active ? 'is-active' : 'is-disabled'">
                      {{ detail.taxonomy.category.is_active ? copy.activeTaxonomy : copy.disabledTaxonomy }}
                    </b>
                  </template>
                  <template v-else-if="detail.taxonomy.category_tracking?.is_legacy_unmapped">
                    <b class="is-legacy">{{ copy.legacyUnmapped }}</b>
                    <code dir="ltr">#{{ detail.work.category_id }}</code>
                  </template>
                  <b v-else-if="detail.taxonomy.category_tracking?.is_uncategorized" class="is-empty">{{ copy.uncategorized }}</b>
                  <p v-else>{{ copy.taxonomyUnavailable }}</p>
                </article>

                <article>
                  <span>{{ copy.tags }}</span>
                  <div v-if="detail.taxonomy.tags !== null && detail.taxonomy.tags.length" class="ym-work-detail-tag-list">
                    <span v-for="tag in detail.taxonomy.tags" :key="tag.id" :class="{ 'is-disabled': !tag.is_active }">
                      <strong>{{ taxonomyName(tag) }}</strong>
                      <code dir="ltr">{{ tag.slug }}</code>
                      <small>{{ tag.is_active ? copy.activeTaxonomy : copy.disabledTaxonomy }}</small>
                    </span>
                  </div>
                  <p v-else-if="detail.taxonomy.tags !== null">{{ copy.noTags }}</p>
                  <p v-else>{{ copy.tagsUnavailable }}</p>
                </article>
              </div>
            </section>

            <section class="ym-work-detail-section">
              <header>
                <h3>{{ copy.people }}</h3>
              </header>
              <div v-if="detail.field_access.can_view_designer" class="ym-work-detail-people">
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
              <p v-else class="ym-work-detail-unavailable">{{ copy.relationsUnavailable }}</p>
            </section>

            <section class="ym-work-detail-section">
              <header>
                <h3>{{ copy.media }}</h3>
              </header>
              <div v-if="detail.media" class="ym-work-detail-media">
                <span>{{ copy.mediaType }}: <code dir="ltr">{{ displayValue(detail.media.media_type) }}</code></span>
                <strong :class="detail.media.has_media ? 'is-present' : 'is-absent'">
                  {{ detail.media.has_media ? copy.mediaPresent : copy.mediaAbsent }}
                </strong>
              </div>
              <p v-else class="ym-work-detail-unavailable">{{ copy.mediaUnavailable }}</p>
            </section>

            <section class="ym-work-detail-section">
              <header>
                <h3>{{ copy.lifecycle }}</h3>
              </header>
              <dl class="ym-work-detail-grid is-lifecycle">
                <div v-for="item in lifecycleItems" :key="item.key">
                  <dt>{{ item.label }}</dt>
                  <dd><time :datetime="item.value || undefined">{{ formatDateTime(item.value) }}</time></dd>
                </div>
              </dl>
            </section>

            <section class="ym-work-detail-section is-private">
              <header>
                <h3>{{ copy.privateNotes }}</h3>
                <p>{{ copy.privateNotesCopy }}</p>
              </header>
              <dl v-if="detail.private_notes" class="ym-work-detail-notes">
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
              <p v-else class="ym-work-detail-unavailable">{{ copy.privateNotesUnavailable }}</p>
            </section>
          </div>
        </section>
    </div>

    <WorksTaxonomyAssignmentDrawer
      :open="assignmentOpen"
      :work="assignmentWork"
      :locale="currentLocale"
      :can-update-category="canUpdateAssignedCategory"
      :can-update-tags="canUpdateAssignedTags"
      :permission-revision="assignmentPermissionRevision"
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
type WorkSortKey = 'created_at' | 'updated_at' | 'title' | 'status' | 'submitted_at' | 'published_at' | 'reports_count' | 'views_count' | 'likes_count'

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
  published_at: string | null
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

interface WorksIndexData {
  items: WorkListItem[]
  pagination: WorksPagination
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
  designer_id: string
  reviewer_id: string
  category_id: string
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
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

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
    designerId: 'معرّف المصمم',
    reviewerId: 'معرّف المراجع',
    categoryId: 'معرّف التصنيف',
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
    invalidIdentifiers: 'معرّفات المصمم والمراجع والتصنيف يجب أن تكون أعدادًا صحيحة صالحة.',
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
    designerId: 'Designer ID',
    reviewerId: 'Reviewer ID',
    categoryId: 'Category ID',
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
    invalidIdentifiers: 'Designer, reviewer, and category identifiers must be valid integers.',
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
    designer_id: '',
    reviewer_id: '',
    category_id: '',
    is_featured: '',
    is_pinned: '',
    reported: '',
    from: '',
    to: '',
    sort: 'created_at',
    direction: 'desc',
    per_page: 15
  }
}

const filters = reactive<WorksFilters>(defaultFilters())
const appliedFilters = reactive<WorksFilters>(defaultFilters())
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
const drawerTitleId = 'ym-work-detail-title'
const assignmentOpen = ref(false)
const assignmentWork = ref<WorkListItem | null>(null)
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
const currentPageSelectedCount = computed(() => items.value.filter(work => selectedWorks.value.has(work.id)).length)
const allCurrentPageSelected = computed(() => items.value.length > 0 && currentPageSelectedCount.value === items.value.length)
const selectionAtLimit = computed(() => selectedCount.value >= MAX_BULK_SELECTION)
const currentPageUnselectedCount = computed(() => items.value.length - currentPageSelectedCount.value)
const canSelectCurrentPage = computed(() => selectedCount.value + currentPageUnselectedCount.value <= MAX_BULK_SELECTION)
const sortedSelectedWorks = computed(() => [...selectedWorks.value.values()].sort((a, b) => a.id - b.id))
const assignmentWorkForDetail = computed(() => {
  if (!detail.value) return null
  return items.value.find(work => work.id === detail.value?.work.id) ?? null
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

const summaryCards = computed(() => {
  const reviewStatuses: WorkStatus[] = ['submitted', 'in_review', 'changes_requested']

  return [
    { key: 'total', label: copy.value.total, value: pagination.total, hint: copy.value.totalHint, color: '#8b5cf6' },
    { key: 'visible', label: copy.value.visibleItems, value: items.value.length, hint: copy.value.visibleItemsHint, color: '#38bdf8' },
    { key: 'published', label: copy.value.publishedCurrent, value: items.value.filter(work => work.status === 'published').length, hint: copy.value.publishedCurrentHint, color: '#10b981' },
    { key: 'review', label: copy.value.reviewCurrent, value: items.value.filter(work => reviewStatuses.includes(work.status)).length, hint: copy.value.reviewCurrentHint, color: '#f59e0b' },
    { key: 'reported', label: copy.value.reportedCurrent, value: items.value.filter(work => work.reports_count > 0).length, hint: copy.value.reportedCurrentHint, color: '#f43f5e' }
  ]
})

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
  return characters.length <= limit ? characters.join('') : `${characters.slice(0, limit).join('')}…`
}

function displayValue(value: string | null): string {
  return value === null || value.trim() === '' ? '—' : value
}

function booleanLabel(value: boolean): string {
  return value ? copy.value.yes : copy.value.no
}

function accessLabel(value: boolean): string {
  return value ? copy.value.allowed : copy.value.unavailable
}

function taxonomyName(entity: SafeTaxonomyEntity): string {
  return currentLocale.value === 'ar' ? entity.name_ar : entity.name_en
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
  return `is-${status.replaceAll('_', '-')}`
}

function visibilityLabel(status: VisibilityStatus): string {
  return status === 'public' ? copy.value.publicVisibility : copy.value.hiddenVisibility
}

function visibilityClass(status: VisibilityStatus): string {
  return status === 'public' ? 'is-public' : 'is-hidden'
}

function sortIndicator(key: WorkSortKey): string {
  if (filters.sort !== key) return '↕'
  return filters.direction === 'asc' ? '↑' : '↓'
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
    ['designer_id', appliedFilters.designer_id.trim()],
    ['reviewer_id', appliedFilters.reviewer_id.trim()],
    ['category_id', appliedFilters.category_id.trim()],
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
      items.value = []
      error.value = copy.value.genericError
      return false
    }

    items.value = response.data.items
    Object.assign(pagination, response.data.pagination)
    Object.assign(taxonomyAccess, response.data.taxonomy_access)
    refreshSelectedSnapshots(response.data.items)
    page.value = response.data.pagination.current_page
    if (assignmentOpen.value && assignmentWork.value) {
      const refreshedWork = response.data.items.find(work => work.id === assignmentWork.value?.id)
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
      Object.assign(taxonomyAccess, {
        can_view_category: false,
        can_view_tags: false
      })
      closeTaxonomyAssignment()
      clearBulkSelection()
      return false
    }

    if (status === 422) {
      filterError.value = copy.value.validationError
      return false
    }

    error.value = copy.value.genericError
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

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
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
  closeTaxonomyAssignment()
  drawerOpen.value = true
  selectedWorkId.value = work.id
  selectedWorkTitle.value = work.title
  detail.value = null
  detailError.value = null
  void fetchWorkDetails(work.id)
}

function openTaxonomyAssignment(work: WorkListItem): void {
  if (!canManageIndividualTaxonomy.value) return
  closeBulkAssignment()
  closeDetails()
  assignmentWork.value = work
  assignmentOpen.value = true
}

function closeTaxonomyAssignment(): void {
  assignmentOpen.value = false
  assignmentWork.value = null
}

function openBulkAssignment(): void {
  if (
    !canManageBulkTaxonomy.value
    || selectedCount.value < 1
    || selectedCount.value > MAX_BULK_SELECTION
  ) {
    return
  }
  closeTaxonomyAssignment()
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
}): Promise<void> {
  if (!payload.changed) return
  const refreshes: Promise<void>[] = [fetchWorks()]
  if (drawerOpen.value && selectedWorkId.value === payload.work_id && canViewDetails.value) {
    refreshes.push(fetchWorkDetails(payload.work_id))
  }
  await Promise.all(refreshes)
}

function handleTaxonomyAuthorizationError(): void {
  closeTaxonomyAssignment()
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

function clearPageState(): void {
  listRequestRevision += 1
  items.value = []
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
  closeTaxonomyAssignment()
  clearBulkSelection()
  closeDetails()
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
  void fetchWorks()
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

onMounted(() => {
  pageMounted = true
  syncWorksAccessState()
})
</script>

<style scoped>
.ym-works-all-page {
  color: var(--ym-text);
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
  overflow-x: auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 20px;
  scrollbar-color: rgba(148, 163, 184, 0.55) transparent;
}

.ym-works-all-table {
  width: 100%;
  min-width: 2580px;
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
