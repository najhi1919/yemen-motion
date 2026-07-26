<template>
  <div class="ym-works-visibility-page ym-admin-page" data-admin-accent="visibility">
    <AdminPageHero
      :breadcrumbs="visibilityBreadcrumbs"
      :breadcrumb-label="copy.filtersTitle"
      :eyebrow="copy.kicker"
      :badge="copy.readonly"
      :title="copy.title"
      :description="copy.description"
    >
      <template #icon>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
          <circle cx="12" cy="12" r="3" />
        </svg>
      </template>
    </AdminPageHero>

    <section
      v-if="authPending"
      class="ym-works-visibility-access-state"
      role="status"
      aria-live="polite"
    >
      <span class="ym-works-visibility-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section
      v-else-if="forbidden"
      class="ym-works-visibility-access-state is-forbidden"
      role="status"
    >
      <span class="ym-works-visibility-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <AdminMetricStrip
        :items="metricItems"
        :locale="currentLocale"
        :aria-label="copy.summaryLabel"
        :loading="loading && items.length === 0"
        :updating="loading && items.length > 0"
      />

      <section class="ym-works-visibility-filter-card ym-admin-surface">
        <form class="ym-works-visibility-filter-form" @submit.prevent="applyFilters">
          <div class="ym-works-visibility-filter-grid">
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
          </div>

          <div class="ym-works-visibility-filter-toolbar">
            <button
              type="button"
              class="ym-works-visibility-advanced-toggle"
              :class="{ 'is-open': showAdvancedFilters }"
              @click="showAdvancedFilters = !showAdvancedFilters"
            >
              <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="4" y1="21" x2="4" y2="14" /><line x1="4" y1="10" x2="4" y2="3" />
                <line x1="12" y1="21" x2="12" y2="12" /><line x1="12" y1="8" x2="12" y2="3" />
                <line x1="20" y1="21" x2="20" y2="16" /><line x1="20" y1="12" x2="20" y2="3" />
                <line x1="1" y1="14" x2="7" y2="14" /><line x1="9" y1="8" x2="15" y2="8" />
                <line x1="17" y1="16" x2="23" y2="16" />
              </svg>
              <span>{{ copy.advancedFilters }}</span>
              <b v-if="activeAdvancedFiltersCount > 0" class="ym-works-visibility-advanced-badge">
                {{ activeAdvancedFiltersCount }}
              </b>
            </button>

            <div class="ym-works-visibility-filter-actions">
              <button type="submit" class="ym-works-visibility-button is-primary" :disabled="loading">
                {{ copy.apply }}
              </button>
              <button
                type="button"
                class="ym-works-visibility-button is-secondary"
                :disabled="loading"
                @click="resetFilters"
              >
                {{ copy.reset }}
              </button>
            </div>
          </div>

          <div v-show="showAdvancedFilters" class="ym-works-visibility-filter-grid is-advanced">
            <label>
              <span>{{ copy.designerId }}</span>
              <input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" class="ym-works-visibility-number-input" />
            </label>

            <label>
              <span>{{ copy.reviewerId }}</span>
              <input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" class="ym-works-visibility-number-input" />
            </label>

            <label>
              <span>{{ copy.categoryId }}</span>
              <input v-model="filters.category_id" type="number" min="1" inputmode="numeric" class="ym-works-visibility-number-input" />
            </label>

            <label>
              <span>{{ copy.reported }}</span>
              <select v-model="filters.reported">
                <option v-for="option in booleanOptions" :key="'reported-' + option.value" :value="option.value">
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
          </div>

        </form>

        <p v-if="filterError" class="ym-works-visibility-filter-error" role="alert">
          {{ filterError }}
        </p>
      </section>

      <section class="ym-works-visibility-table-card ym-admin-surface">
        <aside
          v-if="actionStatus"
          class="ym-works-visibility-action-status"
          :class="actionStatus.kind === 'success' ? 'is-success' : 'is-error'"
          :role="actionStatus.kind === 'error' ? 'alert' : 'status'"
          aria-live="polite"
        >
          <div>
            <strong>{{ actionStatus.message }}</strong>
            <span>{{ actionStatus.actionLabel }} · {{ actionStatus.workLabel }}</span>
          </div>
          <span v-if="actionStatus.changed !== null" class="ym-works-visibility-action-status__changed">
            {{ actionStatus.changed ? copy.changedYes : copy.changedNo }}
          </span>
        </aside>

        <div v-if="loading" class="ym-works-visibility-state" role="status" aria-live="polite">
          <span class="ym-works-visibility-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-works-visibility-state is-error" role="alert">
          <span class="ym-works-visibility-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-visibility-button is-secondary" @click="fetchVisibilityWorks()">
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="items.length === 0" class="ym-works-visibility-state" role="status">
          <span class="ym-works-visibility-empty-icon" aria-hidden="true">0</span>
          <h3>{{ copy.emptyTitle }}</h3>
          <p>{{ copy.emptyCopy }}</p>
        </div>

        <div v-else class="ym-works-visibility-table-wrap">
          <table class="ym-works-visibility-table">
            <thead>
              <tr>
                <th class="is-sequence">#</th>
                <th class="is-title">
                  <button type="button" class="ym-works-visibility-sort" @click="changeSort('title')">
                    {{ copy.workTitle }}
                    <span aria-hidden="true">{{ sortIndicator('title') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-visibility-sort" @click="changeSort('status')">
                    {{ copy.statusAndVisibility }}
                    <span aria-hidden="true">{{ sortIndicator('status') }}</span>
                  </button>
                </th>
                <th>{{ copy.promotion }}</th>
                <th>
                  <button type="button" class="ym-works-visibility-sort" @click="changeSort('reports_count')">
                    {{ copy.metrics }}
                    <span aria-hidden="true">{{ sortIndicator('reports_count') }}</span>
                  </button>
                </th>
                <th>
                  <button type="button" class="ym-works-visibility-sort" @click="changeSort('published_at')">
                    {{ copy.dates }}
                    <span aria-hidden="true">{{ sortIndicator('published_at') }}</span>
                  </button>
                </th>
                <th class="is-visibility-actions">{{ copy.visibilityActions }}</th>
                <th class="is-action">{{ copy.readAction }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(work, index) in items"
                :key="work.id"
                :class="{
                  'is-promoted-row': work.visibility_flags.is_promoted,
                  'has-reports-row': work.visibility_flags.has_reports
                }"
              >
                <td class="is-sequence" data-label="#">
                  {{ formatNumber((pagination.current_page - 1) * pagination.per_page + index + 1) }}
                </td>
                <td class="is-title" :data-label="copy.workTitle">
                  <strong :dir="textDirection(work.title)">{{ work.title }}</strong>
                  <small v-if="work.summary" :title="work.summary" :dir="textDirection(work.summary)">
                    {{ truncateText(work.summary, 48) }}
                  </small>
                </td>
                <td :data-label="copy.statusAndVisibility">
                  <div class="ym-works-visibility-cell-stack">
                    <span class="ym-works-visibility-badge is-status" :class="statusClass(work.status)">
                      {{ statusLabel(work.status) }}
                    </span>
                    <span class="ym-works-visibility-badge" :class="visibilityClass(work.visibility_status)">
                      {{ visibilityLabel(work.visibility_status) }}
                    </span>
                  </div>
                </td>
                <td :data-label="copy.promotion">
                  <div class="ym-works-visibility-cell-stack is-promotion">
                    <span class="ym-works-visibility-flag" :class="work.is_featured ? 'is-featured' : 'is-neutral'">
                      <b aria-hidden="true">★</b>
                      {{ copy.featuredLabel }}: {{ booleanLabel(work.is_featured) }}
                    </span>
                    <span class="ym-works-visibility-flag" :class="work.is_pinned ? 'is-pinned' : 'is-neutral'">
                      <b aria-hidden="true">📌</b>
                      {{ copy.pinnedLabel }}: {{ booleanLabel(work.is_pinned) }}
                    </span>
                  </div>
                </td>
                <td :data-label="copy.metrics">
                  <div class="ym-works-visibility-cell-stack is-metrics">
                    <span>
                      <b aria-hidden="true">👁</b>
                      <small>{{ copy.views }}</small>
                      <strong>{{ formatNumber(work.views_count) }}</strong>
                    </span>
                    <span>
                      <b class="is-like" aria-hidden="true">♥</b>
                      <small>{{ copy.likes }}</small>
                      <strong>{{ formatNumber(work.likes_count) }}</strong>
                    </span>
                    <span :class="work.reports_count > 0 ? 'is-alert' : ''">
                      <b class="is-report" aria-hidden="true">⚠</b>
                      <small>{{ copy.reports }}</small>
                      <strong>{{ formatNumber(work.reports_count) }}</strong>
                    </span>
                  </div>
                </td>
                <td :data-label="copy.dates">
                  <div class="ym-works-visibility-cell-stack is-dates">
                    <time :datetime="work.published_at || undefined">
                      <b>{{ copy.publishedShort }}:</b>
                      {{ work.published_at ? formatDateTime(work.published_at) : copy.unpublished }}
                    </time>
                    <time :datetime="work.updated_at || undefined">
                      <b>{{ copy.updatedShort }}:</b>
                      {{ formatDateTime(work.updated_at) }}
                    </time>
                  </div>
                </td>
                <td class="is-visibility-actions" :data-label="copy.visibilityActions">
                  <div class="ym-works-visibility-action-icons" :aria-label="copy.actionsFor(work.title)">
                    <button
                      v-for="action in availableActions(work)"
                      :key="action.key"
                      type="button"
                      class="ym-works-visibility-icon-button"
                      :class="'is-' + action.tone"
                      :disabled="!action.enabled || actionWorkId === work.id"
                      :title="actionWorkId === work.id ? copy.actionInProgress : action.reason"
                      :aria-label="actionWorkId === work.id ? copy.actionInProgress : action.reason"
                      @click="requestAction(work, action.key)"
                    >
                      <span
                        v-if="actionWorkId === work.id && pendingAction?.key === action.key"
                        class="ym-works-visibility-action-spinner"
                        aria-hidden="true"
                      />
                      <svg
                        v-else
                        viewBox="0 0 24 24"
                        width="18"
                        height="18"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                        v-html="actionSvgPath(action.key)"
                      />
                    </button>
                  </div>
                </td>
                <td class="is-action" :data-label="copy.readAction">
                  <button
                    type="button"
                    class="ym-works-visibility-details-button"
                    :disabled="!canViewDetails"
                    :title="canViewDetails ? copy.viewDetailsHint : copy.detailsPermissionRequired"
                    @click="openDetails(work, $event)"
                  >
                    {{ copy.viewDetails }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <footer class="ym-works-visibility-pagination">
          <div>
            <span>{{ copy.paginationTotal }}</span>
            <strong>{{ formatNumber(pagination.total) }}</strong>
            <small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small>
          </div>
          <nav :aria-label="copy.paginationLabel">
            <button
              type="button"
              class="ym-works-visibility-button is-secondary"
              :disabled="loading || pagination.current_page <= 1"
              @click="changePage(pagination.current_page - 1)"
            >
              {{ copy.previous }}
            </button>
            <span>{{ copy.pageOf(pagination.current_page, pagination.last_page) }}</span>
            <button
              type="button"
              class="ym-works-visibility-button is-secondary"
              :disabled="loading || pagination.current_page >= pagination.last_page"
              @click="changePage(pagination.current_page + 1)"
            >
              {{ copy.next }}
            </button>
          </nav>
        </footer>
      </section>
    </template>

    <WorksReviewDetailWorkspace
      v-if="drawerOpen && selectedWorkId !== null"
      :work-id="selectedWorkId"
      :title="selectedWorkTitle"
      :detail="detail"
      :loading="detailLoading"
      :error="detailError"
      :locale="currentLocale"
      :context-note="detailPublishNote"
      section="visibility"
      @close="closeDetails"
      @retry="retrySelectedDetails"
    />

    <div
      v-if="drawerOpen && selectedWorkId === null"
      class="ym-visibility-detail-backdrop"
      role="presentation"
      @click.self="closeDetails"
    >
      <section
        class="ym-visibility-detail-drawer"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="drawerTitleId"
      >
        <header class="ym-visibility-detail-drawer__head">
          <div>
            <span>{{ copy.detailReadonly }}</span>
            <h2 :id="drawerTitleId">{{ selectedWorkTitle || copy.detailsTitle }}</h2>
            <code v-if="selectedWorkId !== null" dir="ltr">#{{ selectedWorkId }}</code>
          </div>
          <button
            type="button"
            class="ym-visibility-detail-drawer__close"
            :aria-label="copy.close"
            :title="copy.close"
            @click="closeDetails"
          >
            ×
          </button>
        </header>

        <div v-if="detailLoading" class="ym-visibility-detail-state" role="status" aria-live="polite">
          <span class="ym-works-visibility-spinner" aria-hidden="true" />
          <h3>{{ copy.detailsLoadingTitle }}</h3>
          <p>{{ copy.detailsLoadingCopy }}</p>
        </div>

        <div v-else-if="detailError" class="ym-visibility-detail-state is-error" role="alert">
          <span class="ym-works-visibility-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.detailsErrorTitle }}</h3>
          <p>{{ detailError }}</p>
          <button
            v-if="selectedWorkId !== null"
            type="button"
            class="ym-works-visibility-button is-secondary"
            @click="retrySelectedDetails"
          >
            {{ copy.retry }}
          </button>
        </div>

        <div v-else-if="detail" class="ym-visibility-detail-content">
          <section class="ym-visibility-detail-intro">
            <div>
              <span class="ym-works-visibility-badge is-status" :class="statusClass(detail.work.status)">
                {{ statusLabel(detail.work.status) }}
              </span>
              <span class="ym-works-visibility-badge" :class="visibilityClass(detail.work.visibility_status)">
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

          <section class="ym-visibility-detail-section">
            <header>
              <h3>{{ copy.accessIndicators }}</h3>
              <p>{{ copy.accessIndicatorsCopy }}</p>
            </header>
            <div class="ym-visibility-detail-access-grid">
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

          <section class="ym-visibility-detail-section">
            <header><h3>{{ copy.basicDetails }}</h3></header>
            <dl class="ym-visibility-detail-grid">
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
                <dd>{{ displayMediaType(detail.work.media_type) }}</dd>
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

          <section class="ym-visibility-detail-section">
            <header><h3>{{ copy.people }}</h3></header>
            <div v-if="detail.field_access.can_view_designer" class="ym-visibility-detail-people">
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
            <p v-else class="ym-visibility-detail-unavailable">{{ copy.relationsUnavailable }}</p>
          </section>

          <section class="ym-visibility-detail-section">
            <header><h3>{{ copy.media }}</h3></header>
            <div v-if="detail.media" class="ym-visibility-detail-media">
              <span>
                {{ copy.mediaType }}:
                <strong>{{ displayMediaType(detail.media.media_type) }}</strong>
              </span>
              <strong :class="detail.media.has_media ? 'is-present' : 'is-absent'">
                {{ detail.media.has_media ? copy.mediaPresent : copy.mediaAbsent }}
              </strong>
            </div>
            <p v-else class="ym-visibility-detail-unavailable">{{ copy.mediaUnavailable }}</p>
          </section>

          <section class="ym-visibility-detail-section">
            <header><h3>{{ copy.lifecycle }}</h3></header>
            <dl class="ym-visibility-detail-grid is-lifecycle">
              <div v-for="item in lifecycleItems" :key="item.key">
                <dt>{{ item.label }}</dt>
                <dd><time :datetime="item.value || undefined">{{ formatDateTime(item.value) }}</time></dd>
              </div>
            </dl>
          </section>

          <section class="ym-visibility-detail-section is-private">
            <header>
              <h3>{{ copy.privateNotes }}</h3>
              <p>{{ copy.privateNotesCopy }}</p>
            </header>
            <dl v-if="detail.private_notes" class="ym-visibility-detail-notes">
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
            <p v-else class="ym-visibility-detail-unavailable">{{ copy.privateNotesUnavailable }}</p>
          </section>
        </div>
      </section>
    </div>

    <div
      v-if="pendingAction"
      class="ym-visibility-action-backdrop"
      role="presentation"
      @click.self="cancelAction"
    >
      <section
        class="ym-visibility-action-dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="actionDialogTitleId"
        :aria-describedby="actionDialogDescriptionId"
      >
        <span class="ym-visibility-action-dialog__eyebrow">{{ copy.visibilityActionConfirmation }}</span>
        <h2 :id="actionDialogTitleId">{{ copy.confirmAction(pendingAction.label) }}</h2>
        <p :id="actionDialogDescriptionId">{{ copy.confirmActionCopy }}</p>
        <div class="ym-visibility-action-dialog__work">
          <span>{{ copy.affectedWork }}</span>
          <strong :dir="textDirection(pendingAction.workLabel)">{{ pendingAction.workLabel }}</strong>
          <code dir="ltr">#{{ pendingAction.workId }}</code>
        </div>
        <div class="ym-visibility-action-dialog__buttons">
          <button
            type="button"
            class="ym-works-visibility-button is-primary"
            :disabled="actionWorkId !== null"
            @click="confirmAction"
          >
            <span v-if="actionWorkId !== null" class="ym-works-visibility-action-spinner" aria-hidden="true" />
            {{ actionWorkId !== null ? copy.executingAction : copy.confirmExecution }}
          </button>
          <button
            type="button"
            class="ym-works-visibility-button is-secondary"
            :disabled="actionWorkId !== null"
            @click="cancelAction"
          >
            {{ copy.cancel }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import AdminPageHero from '~/components/admin/visual/AdminPageHero.vue'
import AdminMetricStrip from '~/components/admin/visual/AdminMetricStrip.vue'
import { formatYmDateTime, formatYmNumber } from '~/utils/ymFormatting'

definePageMeta({ layout: 'admin' })

const showAdvancedFilters = ref(false)

type Locale = 'ar' | 'en'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = 'hidden' | 'public'
type BooleanFilter = '' | '1' | '0'
type PageSize = 15 | 25 | 50
type SortDirection = 'asc' | 'desc'
type VisibilitySortKey = 'updated_at' | 'published_at' | 'created_at' | 'title' | 'status' | 'reports_count' | 'views_count' | 'likes_count'
type VisibilityFlagKey = 'public' | 'hidden' | 'promoted' | 'reported'
type VisibilityActionKey = 'publish' | 'unpublish' | 'hide' | 'restore' | 'feature' | 'unfeature' | 'pin' | 'unpin'
type VisibilityActionTone = 'positive' | 'warning' | 'promotion' | 'neutral'

interface UserReference {
  id: number
  name: string
}

interface VisibilityFlags {
  is_public: boolean
  is_hidden: boolean
  is_promoted: boolean
  has_reports: boolean
}

interface VisibilityWorkItem {
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
  approved_at: string | null
  published_at: string | null
  hidden_at: string | null
  updated_at: string | null
  created_at: string | null
  visibility_flags: VisibilityFlags
}

interface VisibilityPagination {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

interface VisibilitySummary {
  total: number
  public: number
  hidden: number
  featured: number
  pinned: number
  published: number
  hidden_status: number
  reported: number
  promoted: number
}

interface VisibilityData {
  items: VisibilityWorkItem[]
  pagination: VisibilityPagination
  summary: VisibilitySummary
  filters: Record<string, unknown>
}

interface VisibilityResponse {
  success: boolean
  data: VisibilityData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface VisibilityActionWorkPayload {
  id: number
  title: string
  slug: string
  summary: string | null
  status: WorkStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  category_id: number | null
  is_featured: boolean
  is_pinned: boolean
  reports_count: number
  views_count: number
  likes_count: number
  published_at: string | null
  hidden_at: string | null
  updated_at: string | null
  created_at: string | null
  visibility_flags: VisibilityFlags
}

interface VisibilityActionResponse {
  success: boolean
  data: {
    action: VisibilityActionKey
    changed: boolean
    work: VisibilityActionWorkPayload
  } | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface VisibilityActionDefinition {
  key: VisibilityActionKey
  permission: string
  endpoint: string
  tone: VisibilityActionTone
}

interface VisibilityActionView extends VisibilityActionDefinition {
  label: string
  enabled: boolean
  reason: string
}

interface PendingVisibilityAction {
  key: VisibilityActionKey
  label: string
  workId: number
  workLabel: string
  expectedUpdatedAt: string
}

interface VisibilityActionStatus {
  kind: 'success' | 'error'
  message: string
  changed: boolean | null
  actionLabel: string
  workLabel: string
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

interface VisibilityFilters {
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
  sort: VisibilitySortKey
  direction: SortDirection
  per_page: PageSize
}

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const visibilityActionDefinitions: VisibilityActionDefinition[] = [
  { key: 'publish', permission: 'admin.works.publish', endpoint: 'publish', tone: 'positive' },
  { key: 'unpublish', permission: 'admin.works.unpublish', endpoint: 'unpublish', tone: 'warning' },
  { key: 'hide', permission: 'admin.works.hide', endpoint: 'hide', tone: 'warning' },
  { key: 'restore', permission: 'admin.works.restore_visibility', endpoint: 'restore', tone: 'positive' },
  { key: 'feature', permission: 'admin.works.feature', endpoint: 'feature', tone: 'promotion' },
  { key: 'unfeature', permission: 'admin.works.unfeature', endpoint: 'unfeature', tone: 'neutral' },
  { key: 'pin', permission: 'admin.works.pin', endpoint: 'pin', tone: 'promotion' },
  { key: 'unpin', permission: 'admin.works.unpin', endpoint: 'unpin', tone: 'neutral' }
]

const copyMap = {
  ar: {
    readonly: 'إجراءات ظهور مضبوطة',
    kicker: 'إدارة ظهور الأعمال وترويجها',
    title: 'الظهور والتمييز',
    description: 'قائمة إدارية آمنة لمراجعة حالة ظهور الأعمال وتمييزها وتثبيتها، مع تفاصيل مقيدة بصلاحيات الحقول.',
    totalWorks: 'إجمالي الأعمال',
    filteredScope: 'نتائج مطابقة للفلاتر الحالية',
    authLoadingTitle: 'جارٍ التحقق من صلاحية الظهور والتمييز',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى الظهور والتمييز غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب صلاحيات القسم المطلوبة. لم تتم محاولة تحميل البيانات.',
    noticeTitle: 'إجراءات الظهور محكومة بالصلاحية والحالة',
    notice: 'لا يظهر للمستخدم إلا ما تسمح به صلاحياته، وتظل الإجراءات غير المناسبة لحالة العمل معطلة بسبب واضح قبل التأكيد والتنفيذ.',
    summaryLabel: 'ملخص ظهور الأعمال وتمييزها',
    total: 'الإجمالي',
    totalHint: 'كل الأعمال المطابقة',
    public: 'عامة',
    publicHint: 'ظهورها العام متاح',
    hidden: 'مخفية الظهور',
    hiddenHint: 'ظهورها العام مخفي',
    featuredSummary: 'مميزة',
    featuredHint: 'تحمل علامة التمييز',
    pinnedSummary: 'مثبتة',
    pinnedHint: 'تحمل علامة التثبيت',
    published: 'منشورة',
    publishedHint: 'حالتها منشورة',
    hiddenStatus: 'بحالة مخفي',
    hiddenStatusHint: 'حالة العمل نفسها مخفية',
    reported: 'عليها بلاغات',
    reportedHint: 'بلاغ واحد أو أكثر',
    promoted: 'مروّجة',
    promotedHint: 'مميزة أو مثبتة',
    filtersTitle: 'بحث وفلاتر الظهور',
    filtersCopy: 'ضيّق القائمة عبر الحقول الآمنة التي يدعمها الخادم فقط.',
    search: 'البحث',
    searchPlaceholder: 'العنوان أو المعرّف النصي أو الملخص',
    searchHint: 'حرفان على الأقل، وبحد أقصى 80 حرفًا.',
    status: 'الحالة',
    visibility: 'الظهور',
    mediaType: 'نوع الوسائط',
    designerId: 'معرّف المصمم',
    reviewerId: 'معرّف المراجع',
    categoryId: 'معرّف التصنيف',
    from: 'حُدّث من',
    to: 'حُدّث إلى',
    updatedRangeHint: 'يطبق على تاريخ آخر تحديث.',
    perPage: 'لكل صفحة',
    all: 'الكل',
    yes: 'نعم',
    no: 'لا',
    apply: 'تطبيق الفلاتر',
    reset: 'إعادة الضبط',
    resetHint: 'مسح الفلاتر وإعادة ترتيب الأحدث تحديثًا أولًا',
    searchTooShort: 'نص البحث يجب أن يكون فارغًا أو يحتوي حرفين على الأقل.',
    invalidDateRange: 'تاريخ النهاية يجب أن يساوي تاريخ البداية أو يأتي بعده.',
    invalidIdentifiers: 'معرّفات المصمم والمراجع والتصنيف يجب أن تكون أعدادًا صحيحة موجبة.',
    validationError: 'تعذر تطبيق الفلاتر. تحقق من البحث والقيم والتواريخ.',
    tableTitle: 'قائمة الظهور والتمييز',
    tableCopy: 'رتّب النتائج من رؤوس الأعمدة، ونفّذ إجراءات الظهور المصرح بها بعد التأكيد.',
    currentPage: 'الصفحة الحالية',
    loadingTitle: 'جارٍ تحميل قائمة الظهور والتمييز',
    loadingCopy: 'يتم جلب القائمة الآمنة وفق الفلاتر الحالية...',
    errorTitle: 'تعذر تحميل قائمة الظهور والتمييز',
    genericError: 'حدث خطأ أثناء تحميل قائمة الظهور والتمييز. حاول مرة أخرى.',
    retry: 'إعادة المحاولة',
    emptyTitle: 'لا توجد أعمال مطابقة',
    emptyCopy: 'لا توجد أعمال ضمن نطاق الفلاتر الحالي. جرّب تعديل الفلاتر أو إعادة ضبطها.',
    workTitle: 'العنوان',
    designer: 'المصمم',
    reviewer: 'المراجع',
    category: 'التصنيف',
    publicFlag: 'عام',
    hiddenFlag: 'مخفي',
    promotedFlag: 'مروّج',
    reportedFlag: 'عليه بلاغات',
    publicYes: 'عام',
    publicNo: 'غير عام',
    hiddenYes: 'مخفي',
    hiddenNo: 'غير مخفي',
    promotedYes: 'مروّج',
    promotedNo: 'غير مروّج',
    reportedYes: 'عليه بلاغات',
    reportedNo: 'دون بلاغات',
    reports: 'البلاغات',
    views: 'المشاهدات',
    likes: 'الإعجابات',
    publishedAt: 'تاريخ النشر',
    hiddenAt: 'تاريخ الإخفاء',
    createdAt: 'تاريخ الإنشاء',
    updatedAt: 'آخر تحديث',
    visibilityActions: 'إجراءات الظهور',
    actionsFor: (title: string) => 'إجراءات الظهور للعمل: ' + title,
    actionInProgress: 'جارٍ تنفيذ إجراء لهذا العمل.',
    changedYes: 'تم تغيير الحالة',
    changedNo: 'لم تتغير الحالة',
    visibilityActionConfirmation: 'تأكيد إجراء ظهور فعلي',
    confirmAction: (action: string) => 'تأكيد إجراء: ' + action,
    confirmActionCopy: 'سيتم تنفيذ إجراء ظهور فعلي على العمل المحدد. راجع العمل والإجراء قبل المتابعة.',
    affectedWork: 'العمل المتأثر',
    confirmExecution: 'تأكيد التنفيذ',
    executingAction: 'جارٍ التنفيذ...',
    cancel: 'إلغاء',
    actionSucceeded: 'تم تنفيذ الإجراء بنجاح',
    actionUnchanged: 'لا يوجد تغيير؛ الحالة مطابقة بالفعل',
    actionValidationFailed: 'تعذر تنفيذ الإجراء من حالة العمل الحالية.',
    actionDenied: 'غير مصرح بتنفيذ هذا الإجراء.',
    actionNotFound: 'لم يعد العمل موجودًا.',
    actionFailed: 'تعذر تنفيذ إجراء الظهور. حاول مرة أخرى.',
    actionConflict: 'تغير العمل في الخادم. حُمّلت النسخة الأحدث؛ راجعها قبل إعادة المحاولة.',
    actionResponseInvalid: 'تعذر اعتماد نتيجة الإجراء. أُبقيت بيانات الصفحة دون تغيير.',
    readyAction: 'الإجراء متاح لهذه الحالة.',
    statusDoesNotAllow: 'حالة العمل الحالية لا تسمح بهذا الإجراء.',
    archivedUnavailable: 'لا يمكن تنفيذ هذا الإجراء على عمل مؤرشف.',
    alreadyPublishedPublic: 'منشور وعام بالفعل.',
    alreadyUnpublished: 'غير منشور بالفعل.',
    alreadyHidden: 'مخفي بالفعل.',
    alreadyVisible: 'ظاهر بالفعل.',
    restoreUnavailable: 'حالة العمل الحالية لا تسمح باستعادة الظهور.',
    alreadyFeatured: 'مميز بالفعل.',
    notFeatured: 'العمل غير مميز بالفعل.',
    alreadyPinned: 'مثبت بالفعل.',
    notPinned: 'العمل غير مثبت بالفعل.',
    publishedPublicRequired: 'يتطلب الإجراء عملًا منشورًا وعامًا.',
    approvalRequired: 'لا يمكن النشر أو استعادة الظهور قبل اعتماد العمل.',
    readAction: 'إجراء القراءة',
    viewDetails: 'عرض التفاصيل',
    viewDetailsHint: 'فتح تفاصيل العمل الآمنة',
    detailsPermissionRequired: 'يتطلب عرض التفاصيل صلاحيات قائمة وتفاصيل الأعمال',
    paginationTotal: 'إجمالي النتائج',
    visibleNow: 'عمل ظاهر الآن',
    paginationLabel: 'التنقل بين صفحات الظهور والتمييز',
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
    hiddenVisibility: 'مخفي',
    advancedFilters: 'فلاتر متقدمة',
    statusAndVisibility: 'الحالة والظهور',
    promotion: 'الترويج',
    dates: 'التواريخ',
    metrics: 'الإحصائيات',
    publishedShort: 'نشر',
    updatedShort: 'تحديث',
    unpublished: 'غير منشور',
    imageMedia: 'صورة',
    videoMedia: 'فيديو'
  },
  en: {
    readonly: 'Controlled visibility actions',
    kicker: 'Works visibility and promotion management',
    title: 'Visibility & Promotion',
    description: 'A safe administrative list for reviewing work visibility, featuring, and pinning with permission-scoped details.',
    totalWorks: 'Total works',
    filteredScope: 'Results matching current filters',
    authLoadingTitle: 'Checking visibility access',
    authLoadingCopy: 'Waiting for the user session to initialize before requesting data.',
    forbiddenTitle: 'Visibility access is unavailable',
    forbiddenCopy: 'This account lacks the required section permissions. No data request was made.',
    noticeTitle: 'Visibility actions are permission and state controlled',
    notice: 'Users only see permitted actions. Actions that do not fit the work state stay disabled with a clear reason before confirmation.',
    summaryLabel: 'Works visibility and promotion summary',
    total: 'Total',
    totalHint: 'All matching works',
    public: 'Public',
    publicHint: 'Publicly visible works',
    hidden: 'Hidden visibility',
    hiddenHint: 'Public visibility is hidden',
    featuredSummary: 'Featured',
    featuredHint: 'Marked as featured',
    pinnedSummary: 'Pinned',
    pinnedHint: 'Marked as pinned',
    published: 'Published',
    publishedHint: 'Published status',
    hiddenStatus: 'Hidden status',
    hiddenStatusHint: 'The work status is hidden',
    reported: 'Reported',
    reportedHint: 'One or more reports',
    promoted: 'Promoted',
    promotedHint: 'Featured or pinned',
    filtersTitle: 'Visibility search and filters',
    filtersCopy: 'Narrow the list using only safe fields supported by the server.',
    search: 'Search',
    searchPlaceholder: 'Title, slug, or summary',
    searchHint: 'At least 2 and at most 80 characters.',
    status: 'Status',
    visibility: 'Visibility',
    mediaType: 'Media type',
    designerId: 'Designer ID',
    reviewerId: 'Reviewer ID',
    categoryId: 'Category ID',
    from: 'Updated from',
    to: 'Updated to',
    updatedRangeHint: 'Applied to the last update time.',
    perPage: 'Per page',
    all: 'All',
    yes: 'Yes',
    no: 'No',
    apply: 'Apply filters',
    reset: 'Reset',
    resetHint: 'Clear filters and restore latest updated first',
    searchTooShort: 'Search must be empty or contain at least two characters.',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    invalidIdentifiers: 'Designer, reviewer, and category identifiers must be positive integers.',
    validationError: 'The filters could not be applied. Check the search, values, and dates.',
    tableTitle: 'Visibility and promotion list',
    tableCopy: 'Sort from supported headers and run permitted visibility actions after confirmation.',
    currentPage: 'Current page',
    loadingTitle: 'Loading visibility works',
    loadingCopy: 'Fetching the safe list using the current filters...',
    errorTitle: 'Could not load visibility works',
    genericError: 'An error occurred while loading the visibility list. Try again.',
    retry: 'Retry',
    emptyTitle: 'No matching works',
    emptyCopy: 'There are no works in the current filter scope. Change or reset the filters.',
    workTitle: 'Title',
    designer: 'Designer',
    reviewer: 'Reviewer',
    category: 'Category',
    publicFlag: 'Public',
    hiddenFlag: 'Hidden',
    promotedFlag: 'Promoted',
    reportedFlag: 'Has reports',
    publicYes: 'Public',
    publicNo: 'Not public',
    hiddenYes: 'Hidden',
    hiddenNo: 'Not hidden',
    promotedYes: 'Promoted',
    promotedNo: 'Not promoted',
    reportedYes: 'Has reports',
    reportedNo: 'No reports',
    reports: 'Reports',
    views: 'Views',
    likes: 'Likes',
    publishedAt: 'Published at',
    hiddenAt: 'Hidden at',
    createdAt: 'Created at',
    updatedAt: 'Updated at',
    visibilityActions: 'Visibility actions',
    actionsFor: (title: string) => 'Visibility actions for: ' + title,
    actionInProgress: 'An action is being executed for this work.',
    changedYes: 'State changed',
    changedNo: 'State unchanged',
    visibilityActionConfirmation: 'Confirm a real visibility action',
    confirmAction: (action: string) => 'Confirm action: ' + action,
    confirmActionCopy: 'A real visibility action will be run on the selected work. Review the work and action before continuing.',
    affectedWork: 'Affected work',
    confirmExecution: 'Confirm execution',
    executingAction: 'Executing...',
    cancel: 'Cancel',
    actionSucceeded: 'The action was completed successfully',
    actionUnchanged: 'No change; the state already matches',
    actionValidationFailed: 'The action cannot be run from the current work state.',
    actionDenied: 'You are not authorized to run this action.',
    actionNotFound: 'The work no longer exists.',
    actionFailed: 'The visibility action could not be completed. Try again.',
    actionConflict: 'The work changed on the server. The latest version was loaded; review it before retrying.',
    actionResponseInvalid: 'The action result could not be accepted. Page data was left unchanged.',
    readyAction: 'The action is available for this state.',
    statusDoesNotAllow: 'The current work state does not allow this action.',
    archivedUnavailable: 'This action cannot be run on an archived work.',
    alreadyPublishedPublic: 'Already published and public.',
    alreadyUnpublished: 'Already unpublished.',
    alreadyHidden: 'Already hidden.',
    alreadyVisible: 'Already visible.',
    restoreUnavailable: 'The current state does not allow restoring visibility.',
    alreadyFeatured: 'Already featured.',
    notFeatured: 'The work is already not featured.',
    alreadyPinned: 'Already pinned.',
    notPinned: 'The work is already not pinned.',
    publishedPublicRequired: 'This action requires a published and public work.',
    approvalRequired: 'The work must be approved before it can be published or restored.',
    readAction: 'Read action',
    viewDetails: 'View details',
    viewDetailsHint: 'Open safe work details',
    detailsPermissionRequired: 'Work list and detail permissions are required',
    paginationTotal: 'Total results',
    visibleNow: 'works visible now',
    paginationLabel: 'Visibility list pagination',
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
    hiddenVisibility: 'Hidden',
    advancedFilters: 'Advanced filters',
    statusAndVisibility: 'Status & visibility',
    promotion: 'Promotion',
    dates: 'Dates',
    metrics: 'Metrics',
    publishedShort: 'Published',
    updatedShort: 'Updated',
    unpublished: 'Unpublished',
    imageMedia: 'Image',
    videoMedia: 'Video'
  }
} as const

const copy = computed(() => copyMap[currentLocale.value])
const actionLabels = computed<Record<VisibilityActionKey, string>>(() => currentLocale.value === 'ar'
  ? {
      publish: 'نشر',
      unpublish: 'إلغاء النشر',
      hide: 'إخفاء',
      restore: 'استعادة الظهور',
      feature: 'تمييز',
      unfeature: 'إلغاء التمييز',
      pin: 'تثبيت',
      unpin: 'إلغاء التثبيت'
    }
  : {
      publish: 'Publish',
      unpublish: 'Unpublish',
      hide: 'Hide',
      restore: 'Restore visibility',
      feature: 'Feature',
      unfeature: 'Unfeature',
      pin: 'Pin',
      unpin: 'Unpin'
    })
const authPending = computed(() => !authStore.isInitialized)
const hasVisibilityAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (!['super-admin', 'admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.can('admin.works.access')
    && authStore.can('admin.works.visibility.view')
})
const canViewDetails = computed(() => (
  hasVisibilityAccess.value
  && authStore.can('admin.works.all.view')
  && authStore.can('admin.works.detail.view')
))
const serverForbidden = ref(false)
const forbidden = computed(() => (
  authStore.isInitialized && (!hasVisibilityAccess.value || serverForbidden.value)
))

const items = ref<VisibilityWorkItem[]>([])
const pagination = reactive<VisibilityPagination>({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
const summary = reactive<VisibilitySummary>(emptySummary())

function emptySummary(): VisibilitySummary {
  return {
    total: 0,
    public: 0,
    hidden: 0,
    featured: 0,
    pinned: 0,
    published: 0,
    hidden_status: 0,
    reported: 0,
    promoted: 0
  }
}

function defaultFilters(): VisibilityFilters {
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
    sort: 'updated_at',
    direction: 'desc',
    per_page: 15
  }
}

const filters = reactive<VisibilityFilters>(defaultFilters())
const appliedFilters = reactive<VisibilityFilters>(defaultFilters())
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
const drawerTitleId = 'ym-visibility-work-detail-title'
const detailsTrigger = ref<HTMLElement | null>(null)
const pendingAction = ref<PendingVisibilityAction | null>(null)
const actionWorkId = ref<number | null>(null)
const actionStatus = ref<VisibilityActionStatus | null>(null)
const actionDialogTitleId = 'ym-visibility-action-dialog-title'
const actionDialogDescriptionId = 'ym-visibility-action-dialog-description'

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let listRequestRevision = 0
let detailRequestRevision = 0
let searchTimer: ReturnType<typeof setTimeout> | null = null

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
  { key: 'public', label: copy.value.public, value: summary.public, hint: copy.value.publicHint, color: '#10b981' },
  { key: 'hidden', label: copy.value.hidden, value: summary.hidden, hint: copy.value.hiddenHint, color: '#64748b' },
  { key: 'featured', label: copy.value.featuredSummary, value: summary.featured, hint: copy.value.featuredHint, color: '#f59e0b' },
  { key: 'pinned', label: copy.value.pinnedSummary, value: summary.pinned, hint: copy.value.pinnedHint, color: '#a855f7' },
  { key: 'published', label: copy.value.published, value: summary.published, hint: copy.value.publishedHint, color: '#22c55e' },
  { key: 'hidden_status', label: copy.value.hiddenStatus, value: summary.hidden_status, hint: copy.value.hiddenStatusHint, color: '#94a3b8' },
  { key: 'reported', label: copy.value.reported, value: summary.reported, hint: copy.value.reportedHint, color: '#f43f5e' }
])

const visibilityBreadcrumbs = computed(() => ['Admin', 'Works', copy.value.title])

const metricItems = computed(() => summaryCards.value.map(card => ({
  key: card.key,
  label: card.label,
  description: card.hint,
  value: card.value,
  tone: metricTone(card.key),
  icon: metricIcon(card.key)
})))

function metricTone(key: string): 'violet' | 'cyan' | 'indigo' | 'amber' | 'emerald' | 'neutral' | 'rose' | 'magenta' {
  const map: Record<string, 'violet' | 'cyan' | 'indigo' | 'amber' | 'emerald' | 'neutral' | 'rose' | 'magenta'> = {
    total: 'neutral',
    public: 'cyan',
    hidden: 'neutral',
    featured: 'amber',
    pinned: 'neutral',
    published: 'cyan',
    hidden_status: 'neutral',
    reported: 'rose',
    promoted: 'magenta'
  }
  return map[key] ?? 'neutral'
}

function metricIcon(key: string): string {
  const map: Record<string, string> = {
    total: '⊞',
    public: '◉',
    hidden: '◌',
    featured: '★',
    pinned: '◈',
    published: '↗',
    hidden_status: '•',
    reported: '⚠',
    promoted: '♦'
  }
  return map[key] ?? '·'
}

const activeAdvancedFiltersCount = computed(() => {
  let count = 0
  if (filters.designer_id) count++
  if (filters.reviewer_id) count++
  if (filters.category_id) count++
  if (filters.reported) count++
  if (filters.from) count++
  if (filters.to) count++
  if (filters.per_page !== 15) count++
  return count
})

function actionSvgPath(key: VisibilityActionKey): string {
  const paths: Record<VisibilityActionKey, string> = {
    publish: '<circle cx="12" cy="12" r="10"/><polyline points="16 8 12 12 8 8"/>',
    unpublish: '<circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/>',
    hide: '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
    restore: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
    feature: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
    unfeature: '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" fill="none"/>',
    pin: '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76z"/>',
    unpin: '<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76z"/><line x1="2" y1="2" x2="22" y2="22"/>'
  }
  return paths[key]
}

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

const detailPublishNote = computed(() => {
  const work = detail.value?.work
  if (!work) return null

  if (
    work.status === 'approved'
    || work.status === 'published'
    || (work.status === 'hidden' && work.approved_at !== null)
  ) {
    return null
  }

  return copy.value.approvalRequired
})

function formatNumber(value: number): string {
  return formatYmNumber(value, currentLocale.value)
}

function formatDateTime(value: string | null): string {
  return formatYmDateTime(value, currentLocale.value)
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

function displayMediaType(value: string | null | undefined): string {
  const normalizedValue = value?.trim().toLowerCase()
  if (normalizedValue === 'image') return copy.value.imageMedia
  if (normalizedValue === 'video') return copy.value.videoMedia
  return displayValue(value)
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

function flagLabel(key: VisibilityFlagKey, active: boolean): string {
  const labels = {
    public: active ? copy.value.publicYes : copy.value.publicNo,
    hidden: active ? copy.value.hiddenYes : copy.value.hiddenNo,
    promoted: active ? copy.value.promotedYes : copy.value.promotedNo,
    reported: active ? copy.value.reportedYes : copy.value.reportedNo
  }

  return labels[key]
}

function flagClass(key: VisibilityFlagKey, active: boolean): string {
  return active ? 'is-' + key : 'is-neutral'
}

function hasActionPermission(permission: string): boolean {
  return hasVisibilityAccess.value && authStore.can(permission)
}

function actionAvailability(
  work: VisibilityWorkItem,
  key: VisibilityActionKey
): { enabled: boolean; reason: string } {
  const isArchived = work.status === 'archived'
  const isPublishedPublic = work.status === 'published' && work.visibility_status === 'public'

  if (key === 'publish') {
    if (isArchived) return { enabled: false, reason: copy.value.archivedUnavailable }
    if (isPublishedPublic) return { enabled: false, reason: copy.value.alreadyPublishedPublic }
    if (work.status === 'hidden' && !work.approved_at) {
      return { enabled: false, reason: copy.value.approvalRequired }
    }
    const enabled = ['approved', 'hidden', 'published'].includes(work.status)
    return { enabled, reason: enabled ? copy.value.readyAction : copy.value.statusDoesNotAllow }
  }

  if (key === 'unpublish') {
    if (work.status === 'approved' && work.visibility_status === 'hidden') {
      return { enabled: false, reason: copy.value.alreadyUnpublished }
    }
    const enabled = work.status === 'published' || work.status === 'approved'
    return { enabled, reason: enabled ? copy.value.readyAction : copy.value.statusDoesNotAllow }
  }

  if (key === 'hide') {
    if (isArchived) return { enabled: false, reason: copy.value.archivedUnavailable }
    if (work.status === 'hidden' && work.visibility_status === 'hidden') {
      return { enabled: false, reason: copy.value.alreadyHidden }
    }
    return { enabled: true, reason: copy.value.readyAction }
  }

  if (key === 'restore') {
    if (isArchived) return { enabled: false, reason: copy.value.archivedUnavailable }
    if (isPublishedPublic) return { enabled: false, reason: copy.value.alreadyVisible }
    if (work.status === 'hidden' && !work.approved_at) {
      return { enabled: false, reason: copy.value.approvalRequired }
    }
    const enabled = work.status === 'hidden'
      || (work.visibility_status === 'hidden' && ['approved', 'published'].includes(work.status))
    return { enabled, reason: enabled ? copy.value.readyAction : copy.value.restoreUnavailable }
  }

  if (key === 'feature') {
    if (work.is_featured) return { enabled: false, reason: copy.value.alreadyFeatured }
    return {
      enabled: isPublishedPublic,
      reason: isPublishedPublic ? copy.value.readyAction : copy.value.publishedPublicRequired
    }
  }

  if (key === 'unfeature') {
    return {
      enabled: work.is_featured,
      reason: work.is_featured ? copy.value.readyAction : copy.value.notFeatured
    }
  }

  if (key === 'pin') {
    if (work.is_pinned) return { enabled: false, reason: copy.value.alreadyPinned }
    return {
      enabled: isPublishedPublic,
      reason: isPublishedPublic ? copy.value.readyAction : copy.value.publishedPublicRequired
    }
  }

  return {
    enabled: work.is_pinned,
    reason: work.is_pinned ? copy.value.readyAction : copy.value.notPinned
  }
}

function availableActions(work: VisibilityWorkItem): VisibilityActionView[] {
  return visibilityActionDefinitions
    .filter((action) => hasActionPermission(action.permission))
    .map((action) => {
      const availability = actionAvailability(work, action.key)
      const label = actionLabels.value[action.key]

      return {
        ...action,
        label,
        enabled: availability.enabled,
        reason: availability.enabled ? label : availability.reason
      }
    })
}

function requestAction(work: VisibilityWorkItem, key: VisibilityActionKey): void {
  if (actionWorkId.value !== null || pendingAction.value) return

  const definition = visibilityActionDefinitions.find((action) => action.key === key)
  if (!definition || !hasActionPermission(definition.permission)) return

  const availability = actionAvailability(work, key)
  if (!availability.enabled) return

  pendingAction.value = {
    key,
    label: actionLabels.value[key],
    workId: work.id,
    workLabel: work.title || work.slug,
    expectedUpdatedAt: work.updated_at || ''
  }
}

function cancelAction(): void {
  if (actionWorkId.value !== null) return
  pendingAction.value = null
}

function serverErrorMessage(requestError: unknown): string | null {
  if (!requestError || typeof requestError !== 'object') return null

  const errorData = 'data' in requestError
    ? (requestError as { data?: unknown }).data
    : null

  if (
    errorData
    && typeof errorData === 'object'
    && 'message' in errorData
    && typeof (errorData as { message?: unknown }).message === 'string'
  ) {
    const message = (errorData as { message: string }).message.trim()
    return message || null
  }

  return null
}

function mergeActionWork(workPayload: VisibilityActionWorkPayload): void {
  const index = items.value.findIndex((work) => work.id === workPayload.id)
  if (index !== -1) {
    items.value[index] = { ...items.value[index], ...workPayload }
  }

  if (detail.value?.work.id === workPayload.id) {
    detail.value.work = { ...detail.value.work, ...workPayload }
    selectedWorkTitle.value = workPayload.title
  }
}

async function confirmAction(): Promise<void> {
  const action = pendingAction.value
  if (!action || actionWorkId.value !== null) return

  const definition = visibilityActionDefinitions.find((item) => item.key === action.key)
  const currentWork = items.value.find((work) => work.id === action.workId)
  if (!definition || !currentWork || !hasActionPermission(definition.permission)) {
    pendingAction.value = null
    actionStatus.value = {
      kind: 'error',
      message: currentWork ? copy.value.actionDenied : copy.value.actionNotFound,
      changed: null,
      actionLabel: action.label,
      workLabel: action.workLabel
    }
    return
  }

  const availability = actionAvailability(currentWork, action.key)
  if (!availability.enabled) {
    pendingAction.value = null
    actionStatus.value = {
      kind: 'error',
      message: availability.reason,
      changed: null,
      actionLabel: action.label,
      workLabel: action.workLabel
    }
    return
  }

  actionWorkId.value = action.workId
  actionStatus.value = null

  try {
    const response = await apiFetch<VisibilityActionResponse>(
      '/admin/works/' + action.workId + '/visibility/' + definition.endpoint,
      {
        method: 'PATCH',
        body: {
          expected_updated_at: action.expectedUpdatedAt
        }
      }
    )

    if (!response.success || !response.data?.work) {
      actionStatus.value = {
        kind: 'error',
        message: copy.value.actionResponseInvalid,
        changed: null,
        actionLabel: action.label,
        workLabel: action.workLabel
      }
      return
    }

    mergeActionWork(response.data.work)
    actionStatus.value = {
      kind: 'success',
      message: response.data.changed ? copy.value.actionSucceeded : copy.value.actionUnchanged,
      changed: response.data.changed,
      actionLabel: action.label,
      workLabel: response.data.work.title || response.data.work.slug
    }

    if (drawerOpen.value && selectedWorkId.value === action.workId && canViewDetails.value) {
      void fetchWorkDetails(action.workId)
    }
    void fetchVisibilityWorks(true)
  } catch (requestError: unknown) {
    const status = errorStatus(requestError)
    let message = copy.value.actionFailed

    if (status === 422) message = serverErrorMessage(requestError) || copy.value.actionValidationFailed
    if (status === 401 || status === 403) message = copy.value.actionDenied
    if (status === 409) {
      message = copy.value.actionConflict
      void fetchVisibilityWorks(true)
      if (drawerOpen.value && selectedWorkId.value === action.workId && canViewDetails.value) {
        void fetchWorkDetails(action.workId)
      }
    }
    if (status === 404) {
      message = copy.value.actionNotFound
      items.value = items.value.filter((work) => work.id !== action.workId)
      void fetchVisibilityWorks(true)
    }

    actionStatus.value = {
      kind: 'error',
      message,
      changed: null,
      actionLabel: action.label,
      workLabel: action.workLabel
    }
  } finally {
    actionWorkId.value = null
    pendingAction.value = null
  }
}

function sortIndicator(key: VisibilitySortKey): string {
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

async function fetchVisibilityWorks(silent = false): Promise<void> {
  if (!authStore.isInitialized || !hasVisibilityAccess.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++listRequestRevision
  if (!silent) {
    loading.value = true
    error.value = null
  }

  try {
    const response = await apiFetch<VisibilityResponse>('/admin/works/visibility', {
      query: buildListQuery()
    })

    if (
      requestAccessRevision !== accessRevision
      || currentRequestRevision !== listRequestRevision
      || !hasVisibilityAccess.value
    ) {
      return
    }

    if (!response.success || !response.data) {
      if (!silent) {
        clearVisibilityData()
        error.value = copy.value.genericError
      }
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
      || !hasVisibilityAccess.value
    ) {
      return
    }

    const status = errorStatus(requestError)

    if (status === 401 || status === 403) {
      serverForbidden.value = true
      clearVisibilityData()
      return
    }

    if (status === 422) {
      if (!silent) filterError.value = copy.value.validationError
      return
    }

    if (!silent) error.value = copy.value.genericError
  } finally {
    if (!silent && requestAccessRevision === accessRevision && currentRequestRevision === listRequestRevision) {
      loading.value = false
    }
  }
}

function applyFilters(): void {
  if (!validateFilters()) return

  Object.assign(appliedFilters, filters)
  page.value = 1
  void fetchVisibilityWorks()
}

function resetFilters(): void {
  const defaults = defaultFilters()
  Object.assign(filters, defaults)
  Object.assign(appliedFilters, defaults)
  page.value = 1
  filterError.value = null
  void fetchVisibilityWorks()
}

function changeSort(key: VisibilitySortKey): void {
  if (appliedFilters.sort === key) {
    appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'
  } else {
    appliedFilters.sort = key
    appliedFilters.direction = ['title', 'status'].includes(key) ? 'asc' : 'desc'
  }

  filters.sort = appliedFilters.sort
  filters.direction = appliedFilters.direction
  page.value = 1
  void fetchVisibilityWorks()
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
  void fetchVisibilityWorks()
}

function openDetails(work: VisibilityWorkItem, event?: Event): void {
  if (!canViewDetails.value) return

  detailsTrigger.value = event?.currentTarget instanceof HTMLElement
    ? event.currentTarget
    : null
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
  const trigger = detailsTrigger.value
  detailRequestRevision += 1
  drawerOpen.value = false
  selectedWorkId.value = null
  selectedWorkTitle.value = ''
  detail.value = null
  detailError.value = null
  detailLoading.value = false
  detailsTrigger.value = null
  if (trigger) void nextTick(() => trigger.focus())
}

function retrySelectedDetails(): void {
  if (selectedWorkId.value === null) return

  void fetchWorkDetails(selectedWorkId.value)
}

function clearVisibilityData(): void {
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
  clearVisibilityData()
  loading.value = false
  error.value = null
  filterError.value = null
  if (actionWorkId.value === null) pendingAction.value = null
  closeDetails()
}

function syncVisibilityAccessState(): void {
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

  if (!hasVisibilityAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return

  loadedAuthorizationSignature = authorizationSignature.value
  void fetchVisibilityWorks()
}

watch(
  authorizationSignature,
  () => syncVisibilityAccessState(),
  { flush: 'post' }
)

watch(
  () => filters.q,
  (value) => {
    if (searchTimer) clearTimeout(searchTimer)

    const query = value.trim()
    if (query.length === 1) {
      filterError.value = copy.value.searchTooShort
      return
    }

    filterError.value = null
    if (query === appliedFilters.q.trim()) return

    searchTimer = setTimeout(() => {
      appliedFilters.q = query
      page.value = 1
      void fetchVisibilityWorks()
    }, 320)
  }
)

onMounted(() => {
  pageMounted = true
  syncVisibilityAccessState()
})

onBeforeUnmount(() => {
  pageMounted = false
  listRequestRevision += 1
  detailRequestRevision += 1
  if (searchTimer) clearTimeout(searchTimer)
})
</script>

<style scoped>
/* ── page base and local section identity ── */
.ym-works-visibility-page{
  --ym-visibility-accent:#0f8f6f;
  --ym-visibility-accent-bright:#34d399;
  --ym-visibility-accent-soft:rgba(16,185,129,.14);
  --ym-visibility-gold:#c49a43;
  --ym-visibility-violet:#312e55;
  --ym-visibility-topbar-clearance:6.5rem;
  display:grid;gap:10px;color:var(--ym-text)
}
.ym-works-visibility-page :deep(.ym-admin-hero){
  --ym-admin-section-accent:var(--ym-visibility-accent);
  --ym-admin-section-accent-soft:var(--ym-visibility-accent-soft);
  --ym-admin-section-accent-secondary:var(--ym-visibility-gold);
  --ym-admin-section-highlight:#84a654;
  border-color:color-mix(in srgb,var(--ym-visibility-accent) 35%,var(--ym-card-border));
  background:
    radial-gradient(circle at 82% 18%,rgba(196,154,67,.1),transparent 26%),
    linear-gradient(135deg,color-mix(in srgb,var(--ym-visibility-accent) 16%,var(--ym-card-bg)),color-mix(in srgb,#66733b 9%,var(--ym-card-bg)) 58%,color-mix(in srgb,var(--ym-visibility-violet) 7%,var(--ym-card-bg)));
  padding-block:clamp(1rem,2vw,1.35rem)
}
.ym-works-visibility-page :deep(.ym-admin-hero h1){
  background:linear-gradient(100deg,var(--ym-text) 8%,var(--ym-visibility-accent-bright) 65%,#9a8a3f);
  background-clip:text;-webkit-background-clip:text;-webkit-text-fill-color:transparent
}
.ym-works-visibility-page :deep(.ym-admin-hero__icon),
.ym-works-visibility-page :deep(.ym-admin-hero__eyebrow b){
  border-color:color-mix(in srgb,var(--ym-visibility-accent) 45%,var(--ym-soft-border));
  background:var(--ym-visibility-accent-soft);
  color:var(--ym-visibility-accent-bright)
}
.ym-works-visibility-page :deep(.ym-admin-hero__eyebrow){color:color-mix(in srgb,var(--ym-visibility-accent-bright) 82%,var(--ym-text))}
.ym-works-visibility-page :deep(.ym-admin-metrics){
  grid-template-columns:repeat(4,minmax(0,1fr))
}
.ym-works-visibility-page :deep(.ym-admin-metrics > *){
  min-height:68px;border-color:color-mix(in srgb,var(--ym-card-border) 91%,#0891b2);
  background:color-mix(in srgb,var(--ym-card-bg) 97%,#0891b2 3%)
}

/* ── access / loading / forbidden ── */
.ym-works-visibility-access-state,
.ym-works-visibility-state,
.ym-visibility-detail-state{
  display:grid;min-height:240px;place-items:center;align-content:center;
  gap:.7rem;color:var(--ym-muted);padding:2rem;text-align:center
}
.ym-works-visibility-access-state{
  border:1px solid var(--ym-card-border);border-radius:30px;
  background:var(--ym-card-bg);box-shadow:var(--ym-card-shadow)
}
.ym-works-visibility-state h3,.ym-visibility-detail-state h3{color:var(--ym-text);font-size:1.1rem;font-weight:950;margin:0}
.ym-works-visibility-state p,.ym-visibility-detail-state p{max-width:34rem;color:var(--ym-muted);font-size:13px;font-weight:800;line-height:1.7;margin:0}
.ym-works-visibility-state.is-error,.ym-visibility-detail-state.is-error,.ym-works-visibility-access-state.is-forbidden{color:#fb7185}
.ym-works-visibility-state__icon,.ym-works-visibility-empty-icon{
  display:grid;width:3rem;height:3rem;place-items:center;border-radius:999px;
  background:rgba(244,63,94,.13);color:#fb7185;font-size:1.1rem;font-weight:950
}
.ym-works-visibility-empty-icon{background:rgba(148,163,184,.13);color:var(--ym-muted)}
.ym-works-visibility-spinner{
  width:2.35rem;height:2.35rem;border:3px solid rgba(16,185,129,.2);
  border-top-color:#34d399;border-radius:999px;
  animation:ym-vis-spin 760ms linear infinite
}

/* ── notice ── */
.ym-works-visibility-notice{
  display:flex;align-items:flex-start;gap:.9rem;
  border:1px solid rgba(245,158,11,.28);border-radius:22px;
  background:color-mix(in srgb,#f59e0b 8%,var(--ym-control-bg));
  padding:1rem 1.15rem
}
.ym-works-visibility-notice>span{
  flex:0 0 auto;border-radius:999px;background:rgba(245,158,11,.14);
  color:#fbbf24;font-size:11px;font-weight:950;padding:.38rem .7rem
}
.ym-works-visibility-notice strong{display:block;color:var(--ym-text);font-size:13px;font-weight:950}
.ym-works-visibility-notice p{color:var(--ym-muted);font-size:13px;font-weight:800;line-height:1.7;margin:.2rem 0 0}

/* ── filter card ── */
.ym-works-visibility-filter-card{padding:.7rem .8rem}
.ym-works-visibility-filter-card>header{
  display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem
}
.ym-works-visibility-filter-card h2{color:var(--ym-text);font-size:1.25rem;font-weight:950;margin:0}
.ym-works-visibility-filter-card header p{color:var(--ym-muted);font-size:13px;font-weight:800;line-height:1.7;margin:.3rem 0 0}
.ym-works-visibility-filter-form{display:grid;gap:.6rem}

/* ── filter grid ── */
.ym-works-visibility-filter-grid{display:grid;grid-template-columns:2fr repeat(5,minmax(132px,1fr));gap:.55rem}
.ym-works-visibility-filter-grid.is-advanced{border-top:1px solid var(--ym-soft-border);padding-top:.65rem}
.ym-works-visibility-filter-grid label{display:grid;align-content:start;gap:.28rem}
.ym-works-visibility-filter-grid label>span{color:var(--ym-muted);font-size:12px;font-weight:900}
.ym-works-visibility-filter-grid label>small{color:var(--ym-muted);font-size:10px;font-weight:750}
.ym-works-visibility-filter-grid input,.ym-works-visibility-filter-grid select{
  width:100%;min-height:39px;border:1px solid var(--ym-control-border);border-radius:11px;
  outline:none;background:var(--ym-control-bg);color:var(--ym-text);
  font-size:12px;font-weight:800;padding:.52rem .65rem;
  transition:border-color 160ms ease,box-shadow 160ms ease
}
.ym-works-visibility-filter-grid input:focus,.ym-works-visibility-filter-grid select:focus{
  border-color:#3b8d99;box-shadow:0 0 0 3px rgba(59,141,153,.13)
}
.ym-works-visibility-filter-grid select option{background:var(--ym-dropdown-bg);color:var(--ym-text)}
.ym-works-visibility-number-input{appearance:textfield;-moz-appearance:textfield}
.ym-works-visibility-number-input::-webkit-outer-spin-button,.ym-works-visibility-number-input::-webkit-inner-spin-button{
  -webkit-appearance:none;margin:0
}

/* ── advanced toggle ── */
.ym-works-visibility-advanced-toggle{
  display:inline-flex;align-items:center;gap:.5rem;
  min-height:36px;border:1px solid var(--ym-soft-border);border-radius:10px;
  background:var(--ym-control-bg);color:var(--ym-muted);
  font-size:11px;font-weight:900;padding:.4rem .65rem;
  cursor:pointer;transition:background 160ms ease,border-color 160ms ease,color 160ms ease
}
.ym-works-visibility-advanced-toggle:hover{background:color-mix(in srgb,var(--ym-control-bg) 85%,#fff);color:var(--ym-text)}
.ym-works-visibility-advanced-toggle.is-open{border-color:rgba(59,141,153,.38);color:#67b5c1}
.ym-works-visibility-advanced-badge{
  display:inline-grid;min-width:20px;height:20px;place-items:center;
  border-radius:999px;background:rgba(59,141,153,.13);color:#67b5c1;
  font-size:10px;font-weight:950
}

/* ── filter actions ── */
.ym-works-visibility-filter-actions{
  display:flex;align-items:center;gap:.45rem
}
.ym-works-visibility-filter-toolbar{display:flex;align-items:center;justify-content:space-between;gap:.65rem}

/* ── buttons ── */
.ym-works-visibility-button{
  display:inline-flex;min-height:38px;align-items:center;justify-content:center;
  border:1px solid transparent;border-radius:11px;font-size:12px;font-weight:950;
  padding:.5rem .8rem;cursor:pointer;
  transition:transform 160ms ease,border-color 160ms ease,opacity 160ms ease
}
.ym-works-visibility-button.is-primary{
  min-width:110px;
  background:linear-gradient(135deg,#337f83,#3b82a0);color:#fff;
  box-shadow:0 8px 20px rgba(14,116,144,.14)
}
.ym-works-visibility-button.is-secondary{
  border-color:var(--ym-control-border);background:var(--ym-control-bg);color:var(--ym-text)
}
.ym-works-visibility-button:hover:not(:disabled){transform:translateY(-1px)}
.ym-works-visibility-button:disabled{cursor:not-allowed;opacity:.5}

/* ── filter error ── */
.ym-works-visibility-filter-error{
  border:1px solid rgba(244,63,94,.34);border-radius:15px;
  background:rgba(244,63,94,.1);color:#fb7185;
  font-size:12px;font-weight:850;margin:1rem 0 0;padding:.75rem .85rem
}

/* ── table card ── */
.ym-works-visibility-table-card{padding:.55rem}
.ym-works-visibility-table-card__head{
  display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem
}
.ym-works-visibility-table-card h2{color:var(--ym-text);font-size:1.25rem;font-weight:950;margin:0}
.ym-works-visibility-table-card__head p{color:var(--ym-muted);font-size:13px;font-weight:800;line-height:1.7;margin:.3rem 0 0}
/* ── action status ── */
.ym-works-visibility-action-status{
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  border:1px solid var(--ym-soft-border);border-radius:16px;
  margin:0 0 .55rem;padding:.65rem .8rem
}
.ym-works-visibility-action-status.is-success{border-color:rgba(16,185,129,.35);background:rgba(16,185,129,.09)}
.ym-works-visibility-action-status.is-error{border-color:rgba(244,63,94,.36);background:rgba(244,63,94,.09)}
.ym-works-visibility-action-status div{display:grid;gap:.18rem}
.ym-works-visibility-action-status strong{color:var(--ym-text);font-size:12px;font-weight:950}
.ym-works-visibility-action-status span{color:var(--ym-muted);font-size:10px;font-weight:850}
.ym-works-visibility-action-status__changed{flex:0 0 auto;border-radius:999px;background:var(--ym-control-bg);padding:.38rem .62rem}

/* ── table ── */
.ym-works-visibility-table-wrap{
  overflow:hidden;border:1px solid var(--ym-soft-border);border-radius:20px;
  background:color-mix(in srgb,var(--ym-card-bg) 97%,var(--ym-visibility-violet) 3%);
  scrollbar-color:rgba(148,163,184,.55) transparent
}
.ym-works-visibility-table{
  width:100%;min-width:0;border-collapse:collapse;
  background:color-mix(in srgb,var(--ym-card-bg) 97%,var(--ym-visibility-violet) 3%);
  table-layout:fixed
}
.ym-works-visibility-table th,.ym-works-visibility-table td{
  border-bottom:1px solid var(--ym-soft-border);color:var(--ym-muted);
  font-size:13px;padding:.62rem .5rem;text-align:center;vertical-align:middle;min-width:0
}
.ym-works-visibility-table th{
  position:static;top:auto;z-index:auto;
  border-bottom:2px solid color-mix(in srgb,var(--ym-control-border) 74%,#64748b);
  background:color-mix(in srgb,var(--ym-dropdown-bg) 92%,#dcecf0);
  box-shadow:none;color:var(--ym-text);font-weight:950;white-space:nowrap
}
.ym-works-visibility-table tbody tr{height:82px;transition:background 150ms ease}
.ym-works-visibility-table tbody tr.is-promoted-row{background:color-mix(in srgb,var(--ym-visibility-gold) 4%,transparent)}
.ym-works-visibility-table tbody tr.has-reports-row{box-shadow:inset 3px 0 0 rgba(244,63,94,.6)}
.ym-works-visibility-table tbody tr:hover{background:var(--ym-row-hover)}
.ym-works-visibility-table tbody tr:last-child td{border-bottom:0}

/* ── column widths (8-col layout) ── */
.ym-works-visibility-table .is-sequence{
  width:5%;color:var(--ym-text);font-size:15px;font-weight:950;font-variant-numeric:tabular-nums
}
.ym-works-visibility-table th.is-title,.ym-works-visibility-table td.is-title{width:19%;text-align:start}
.ym-works-visibility-table th:nth-child(3),.ym-works-visibility-table td:nth-child(3){width:12%}
.ym-works-visibility-table th:nth-child(4),.ym-works-visibility-table td:nth-child(4){width:12%}
.ym-works-visibility-table th:nth-child(5),.ym-works-visibility-table td:nth-child(5){width:12%}
.ym-works-visibility-table th:nth-child(6),.ym-works-visibility-table td:nth-child(6){width:14%}
.ym-works-visibility-table td.is-title strong,.ym-works-visibility-table td.is-title small{display:block}
.ym-works-visibility-table td.is-title strong{color:var(--ym-text);font-size:14px;font-weight:950}
.ym-works-visibility-table td.is-title code{color:#34d399;font-size:11px;margin-top:.2rem;overflow-wrap:anywhere}
.ym-works-visibility-table td.is-title small{
  display:-webkit-box;max-width:290px;overflow:hidden;-webkit-box-orient:vertical;
  -webkit-line-clamp:1;color:var(--ym-muted);font-size:11px;line-height:1.35;margin-top:.2rem
}
.ym-works-visibility-table th.is-visibility-actions,.ym-works-visibility-table td.is-visibility-actions{width:19%}
.ym-works-visibility-table th.is-action,.ym-works-visibility-table td.is-action{width:7%}

/* ── sort button ── */
.ym-works-visibility-sort{
  display:inline-flex;align-items:center;gap:.42rem;border:0;
  background:transparent;color:inherit;font:inherit;padding:0;cursor:pointer
}
.ym-works-visibility-sort span{
  display:inline-grid;width:1.35rem;height:1.35rem;place-items:center;
  border-radius:7px;background:rgba(59,141,153,.12);color:#67b5c1
}

/* ── badges ── */
.ym-works-visibility-badge,.ym-works-visibility-flag{
  display:inline-flex;min-height:28px;align-items:center;justify-content:center;
  border:1px solid #64748b;border-radius:999px;
  background:#334155;color:#f8fafc;
  font-size:12.5px;font-weight:950;line-height:1.25;padding:.32rem .64rem;white-space:nowrap
}
.ym-works-visibility-badge.is-submitted,.ym-works-visibility-badge.is-in-review{border-color:#38bdf8;background:#155e75;color:#ecfeff}
.ym-works-visibility-badge.is-changes-requested{border-color:#f59e0b;background:#78350f;color:#fffbeb}
.ym-works-visibility-badge.is-approved,.ym-works-visibility-badge.is-published,.ym-works-visibility-badge.is-public{border-color:#34d399;background:#065f46;color:#ecfdf5}
.ym-works-visibility-badge.is-draft,.ym-works-visibility-badge.is-hidden,.ym-works-visibility-badge.is-archived{border-color:#94a3b8;background:#334155;color:#f8fafc}
.ym-works-visibility-badge.is-rejected{border-color:#fb7185;background:#881337;color:#fff1f2}

/* ── flags ── */
.ym-works-visibility-flag.is-featured,.ym-works-visibility-flag.is-promoted{border-color:#f59e0b;background:#78350f;color:#fffbeb}
.ym-works-visibility-flag.is-pinned{border-color:#2dd4bf;background:#115e59;color:#f0fdfa}
.ym-works-visibility-flag.is-public{border-color:#34d399;background:#065f46;color:#ecfdf5}
.ym-works-visibility-flag.is-hidden{border-color:#94a3b8;background:#334155;color:#f8fafc}
.ym-works-visibility-flag.is-reported{border-color:#fb7185;background:#881337;color:#fff1f2}
.ym-works-visibility-flag.is-neutral{border-color:#94a3b8;background:#334155;color:#f8fafc}

/* ── cell stacks ── */
.ym-works-visibility-cell-stack{display:grid;gap:.28rem}
.ym-works-visibility-cell-stack.is-promotion b{color:#fff;font-size:13px;line-height:1}
.ym-works-visibility-cell-stack.is-metrics span{
  display:grid;grid-template-columns:14px minmax(0,1fr) auto;min-width:0;min-height:1.65rem;
  align-items:center;gap:.26rem;border:1px solid color-mix(in srgb,var(--ym-soft-border) 72%,#64748b);
  border-radius:10px;background:color-mix(in srgb,var(--ym-control-bg) 92%,#64748b 8%);
  color:var(--ym-text);font-size:11px;font-weight:900;padding:.12rem .36rem
}
.ym-works-visibility-cell-stack.is-metrics b{font-size:11px;line-height:1}
.ym-works-visibility-cell-stack.is-metrics b.is-like{color:#fb7185;font-size:13px}
.ym-works-visibility-cell-stack.is-metrics b.is-report{color:#f59e0b}
.ym-works-visibility-cell-stack.is-metrics small{overflow:hidden;color:color-mix(in srgb,var(--ym-muted) 78%,#cbd5e1);font-size:11px;font-weight:850;text-overflow:ellipsis;white-space:nowrap}
.ym-works-visibility-cell-stack.is-metrics strong{color:var(--ym-text);font-size:13px;font-weight:950;font-variant-numeric:tabular-nums}
.ym-works-visibility-cell-stack.is-metrics .is-alert{background:rgba(244,63,94,.13);color:#fb7185}
.ym-works-visibility-cell-stack.is-dates{gap:.34rem}
.ym-works-visibility-cell-stack.is-dates time{display:block;color:var(--ym-text);font-size:13px;font-weight:900;line-height:1.45}
.ym-works-visibility-cell-stack.is-dates time b{color:color-mix(in srgb,var(--ym-muted) 78%,#cbd5e1);font-size:12px;font-weight:950}
.ym-works-visibility-state-text{font-size:12px;font-weight:900}
.ym-works-visibility-state-text.is-public{color:#34d399}

/* ── icon buttons ── */
.ym-works-visibility-action-icons{display:flex;flex-wrap:wrap;gap:.4rem;justify-content:center}
.ym-works-visibility-icon-button{
  display:inline-grid;width:40px;height:40px;place-items:center;
  border:1px solid var(--ym-soft-border);border-radius:10px;
  background:#334155;color:#f8fafc;cursor:pointer;
  box-shadow:0 4px 12px rgba(15,23,42,.1);
  transition:background 150ms ease,border-color 150ms ease,box-shadow 150ms ease,transform 150ms ease
}
.ym-works-visibility-icon-button svg{pointer-events:none;stroke-width:2.25}
.ym-works-visibility-icon-button.is-positive:not(:disabled){border-color:#34d399;background:#065f46;color:#fff;box-shadow:0 4px 13px rgba(16,185,129,.18)}
.ym-works-visibility-icon-button.is-warning:not(:disabled){border-color:#f59e0b;background:#78350f;color:#fff;box-shadow:0 4px 13px rgba(245,158,11,.16)}
.ym-works-visibility-icon-button.is-promotion:not(:disabled){border-color:#d6b55a;background:#6b4f16;color:#fff;box-shadow:0 4px 13px rgba(196,154,67,.16)}
.ym-works-visibility-icon-button.is-neutral:not(:disabled){border-color:#94a3b8;background:#334155;color:#fff;box-shadow:0 4px 12px rgba(100,116,139,.15)}
.ym-works-visibility-icon-button:hover:not(:disabled){
  filter:brightness(1.12);transform:translateY(-2px);box-shadow:0 7px 18px color-mix(in srgb,currentColor 22%,transparent)
}
.ym-works-visibility-icon-button:disabled{border-color:#64748b;background:#334155;color:#f1f5f9;cursor:not-allowed;box-shadow:none;filter:grayscale(.2);opacity:.82}
.ym-works-visibility-action-spinner{
  display:inline-block;width:.85rem;height:.85rem;
  border:2px solid currentColor;border-inline-end-color:transparent;
  border-radius:999px;animation:ym-vis-spin 760ms linear infinite
}

/* ── details button ── */
.ym-works-visibility-details-button{
  width:auto;min-width:78px;min-height:38px;border:1px solid #34d399;border-radius:11px;
  background:#065f46;color:#fff;font-size:12.5px;font-weight:950;line-height:1.25;
  padding:.45rem .65rem;cursor:pointer;
  transition:background 160ms ease,border-color 160ms ease,box-shadow 160ms ease,transform 160ms ease
}
.ym-works-visibility-details-button:hover:not(:disabled){border-color:#6ee7b7;background:#047857;box-shadow:0 5px 14px rgba(16,185,129,.22);transform:translateY(-1px)}
.ym-works-visibility-details-button:focus-visible{outline:3px solid rgba(45,212,191,.26);outline-offset:2px}
.ym-works-visibility-details-button:disabled{border-color:#64748b;background:#334155;color:#f1f5f9;cursor:not-allowed;filter:grayscale(.2);opacity:.82}

/* ── pagination ── */
.ym-works-visibility-pagination{
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  border-top:1px solid var(--ym-soft-border);margin-top:.75rem;padding-top:.85rem
}
.ym-works-visibility-pagination>div{display:flex;align-items:baseline;gap:.45rem;color:var(--ym-muted);font-size:12px;font-weight:850}
.ym-works-visibility-pagination>div strong{color:var(--ym-text);font-size:1.1rem;font-weight:950}
.ym-works-visibility-pagination nav{display:flex;align-items:center;gap:.75rem}
.ym-works-visibility-pagination nav span{color:var(--ym-muted);font-size:12px;font-weight:900}

/* ── detail drawer ── */
.ym-visibility-detail-backdrop{
  position:fixed;inset:0;z-index:120;display:flex;justify-content:flex-end;
  background:rgba(2,6,23,.68);backdrop-filter:blur(6px)
}
.ym-visibility-detail-drawer{
  width:min(660px,100%);height:100dvh;overflow-y:auto;
  border-inline-start:1px solid var(--ym-card-border);
  background:var(--ym-dropdown-bg);box-shadow:-24px 0 64px rgba(2,6,23,.38);color:var(--ym-text)
}
.ym-visibility-detail-drawer__head{
  position:sticky;top:0;z-index:4;display:flex;align-items:flex-start;
  justify-content:space-between;gap:1rem;
  border-bottom:1px solid var(--ym-soft-border);
  background:color-mix(in srgb,var(--ym-dropdown-bg) 92%,transparent);
  backdrop-filter:blur(18px);padding:1.2rem 1.35rem
}
.ym-visibility-detail-drawer__head span,.ym-visibility-detail-drawer__head code{display:block;color:var(--ym-muted);font-size:11px;font-weight:850}
.ym-visibility-detail-drawer__head h2{color:var(--ym-text);font-size:1.35rem;font-weight:950;line-height:1.35;margin:.2rem 0}
.ym-visibility-detail-drawer__close{
  display:grid;flex:0 0 auto;width:42px;height:42px;place-items:center;
  border:1px solid var(--ym-control-border);border-radius:14px;
  background:var(--ym-control-bg);color:var(--ym-text);
  font-size:1.45rem;line-height:1;cursor:pointer
}
.ym-visibility-detail-content{display:grid;gap:1rem;padding:1.25rem}
.ym-visibility-detail-intro,.ym-visibility-detail-section{
  border:1px solid var(--ym-soft-border);border-radius:22px;
  background:var(--ym-card-bg);box-shadow:inset 0 1px 0 rgba(255,255,255,.07);padding:1rem
}
.ym-visibility-detail-intro>div{display:flex;flex-wrap:wrap;gap:.45rem}
.ym-visibility-detail-intro h3{color:var(--ym-text);font-size:1.35rem;font-weight:950;line-height:1.45;margin:.8rem 0 .25rem}
.ym-visibility-detail-intro code{color:#34d399;font-size:11px;overflow-wrap:anywhere}
.ym-visibility-detail-intro p{color:var(--ym-muted);font-size:13px;font-weight:750;line-height:1.8;margin:.75rem 0 0}
.ym-visibility-detail-section>header{margin-bottom:.8rem}
.ym-visibility-detail-section>header h3{color:var(--ym-text);font-size:1rem;font-weight:950;margin:0}
.ym-visibility-detail-section>header p{color:var(--ym-muted);font-size:11px;font-weight:750;line-height:1.65;margin:.25rem 0 0}

/* ── access grid ── */
.ym-visibility-detail-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}
.ym-visibility-detail-access-grid>span{display:grid;gap:.22rem;border:1px solid var(--ym-soft-border);border-radius:15px;background:var(--ym-control-bg);color:var(--ym-muted);font-size:11px;font-weight:850;padding:.7rem}
.ym-visibility-detail-access-grid>span strong{font-size:12px;font-weight:950}
.ym-visibility-detail-access-grid>span.is-allowed strong{color:#34d399}
.ym-visibility-detail-access-grid>span.is-denied strong{color:#94a3b8}

/* ── detail grid ── */
.ym-visibility-detail-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem;margin:0}
.ym-visibility-detail-grid.is-lifecycle{grid-template-columns:repeat(2,minmax(0,1fr))}
.ym-visibility-detail-grid>div,.ym-visibility-detail-people article,.ym-visibility-detail-notes>div{
  min-width:0;border:1px solid var(--ym-soft-border);border-radius:15px;
  background:var(--ym-control-bg);padding:.7rem
}
.ym-visibility-detail-grid dt,.ym-visibility-detail-notes dt,.ym-visibility-detail-people span{color:var(--ym-muted);font-size:10px;font-weight:850}
.ym-visibility-detail-grid dd,.ym-visibility-detail-notes dd{color:var(--ym-text);font-size:12px;font-weight:900;line-height:1.65;margin:.3rem 0 0;overflow-wrap:anywhere}

/* ── people ── */
.ym-visibility-detail-people{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem}
.ym-visibility-detail-people strong,.ym-visibility-detail-people small{display:block}
.ym-visibility-detail-people strong{color:var(--ym-text);font-size:12px;font-weight:950;margin-top:.3rem}
.ym-visibility-detail-people small{color:var(--ym-muted);font-size:10px;margin-top:.18rem}

/* ── media ── */
.ym-visibility-detail-media{
  display:flex;align-items:center;justify-content:space-between;gap:1rem;
  border:1px solid var(--ym-soft-border);border-radius:15px;
  background:var(--ym-control-bg);color:var(--ym-muted);font-size:12px;font-weight:850;padding:.8rem
}
.ym-visibility-detail-media strong.is-present{color:#34d399}
.ym-visibility-detail-media strong.is-absent{color:#94a3b8}

/* ── notes ── */
.ym-visibility-detail-notes{display:grid;gap:.65rem;margin:0}
.ym-visibility-detail-section.is-private{border-color:color-mix(in srgb,#a78bfa 30%,var(--ym-soft-border))}
.ym-visibility-detail-unavailable{
  border:1px dashed var(--ym-control-border);border-radius:15px;
  background:var(--ym-control-bg);color:var(--ym-muted);
  font-size:12px;font-weight:850;line-height:1.7;margin:0;padding:.8rem
}

/* ── action dialog ── */
.ym-visibility-action-backdrop{
  position:fixed;inset:0;z-index:140;display:grid;place-items:center;
  background:rgba(2,6,23,.72);backdrop-filter:blur(7px);padding:1rem
}
.ym-visibility-action-dialog{
  width:min(520px,100%);border:1px solid var(--ym-card-border);border-radius:24px;
  background:var(--ym-dropdown-bg);box-shadow:0 28px 80px rgba(2,6,23,.48);color:var(--ym-text);padding:1.35rem
}
.ym-visibility-action-dialog__eyebrow{color:#a78bfa;font-size:11px;font-weight:950}
.ym-visibility-action-dialog h2{font-size:1.3rem;font-weight:950;margin:.35rem 0 0}
.ym-visibility-action-dialog>p{color:var(--ym-muted);font-size:13px;font-weight:800;line-height:1.75;margin:.65rem 0 0}
.ym-visibility-action-dialog__work{
  display:grid;gap:.22rem;border:1px solid var(--ym-soft-border);border-radius:16px;
  background:var(--ym-control-bg);margin-top:1rem;padding:.85rem
}
.ym-visibility-action-dialog__work span,.ym-visibility-action-dialog__work code{color:var(--ym-muted);font-size:10px;font-weight:850}
.ym-visibility-action-dialog__work strong{color:var(--ym-text);font-size:13px;font-weight:950}
.ym-visibility-action-dialog__buttons{display:flex;justify-content:flex-end;gap:.65rem;margin-top:1rem}

/* ── animation ── */
@keyframes ym-vis-spin{to{transform:rotate(360deg)}}

/* ── light mode contrast and local watermark restraint ── */
:global(.ym-dashboard-light) .ym-works-visibility-filter-card,
:global(.ym-dashboard-light) .ym-works-visibility-table-card{
  border-color:color-mix(in srgb,var(--ym-control-border) 82%,#64748b);
  background:color-mix(in srgb,var(--ym-card-bg) 88%,rgba(255,255,255,.86));
  backdrop-filter:blur(20px) saturate(118%)
}
:global(.ym-dashboard-light) .ym-works-visibility-table-wrap,
:global(.ym-dashboard-light) .ym-works-visibility-table{
  border-color:color-mix(in srgb,var(--ym-control-border) 78%,#64748b);
  background:color-mix(in srgb,var(--ym-card-bg) 91%,rgba(236,253,245,.82))
}
:global(.ym-dashboard-light) .ym-works-visibility-table th{
  border-bottom-color:color-mix(in srgb,var(--ym-control-border) 72%,#475569);
  background:color-mix(in srgb,var(--ym-dropdown-bg) 89%,#e8f2ec);
  box-shadow:0 1px 0 color-mix(in srgb,var(--ym-control-border) 70%,#64748b),0 8px 18px rgba(15,23,42,.07);
  color:color-mix(in srgb,var(--ym-text) 94%,#0f172a)
}
:global(.ym-dashboard-light) .ym-works-visibility-table td{
  border-bottom-color:color-mix(in srgb,var(--ym-soft-border) 65%,#94a3b8);
  color:color-mix(in srgb,var(--ym-muted) 78%,#334155)
}
:global(.ym-dashboard-light) .ym-works-visibility-table td.is-title small,
:global(.ym-dashboard-light) .ym-works-visibility-cell-stack.is-metrics small,
:global(.ym-dashboard-light) .ym-works-visibility-cell-stack.is-dates time,
:global(.ym-dashboard-light) .ym-works-visibility-state-text{
  color:color-mix(in srgb,var(--ym-muted) 70%,#334155)
}
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-approved,
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-published,
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-public{
  border-color:rgba(5,150,105,.48);background:rgba(5,150,105,.13);color:#047857
}
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-draft,
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-hidden,
:global(.ym-dashboard-light) .ym-works-visibility-badge.is-archived,
:global(.ym-dashboard-light) .ym-works-visibility-flag.is-neutral{
  border-color:rgba(100,116,139,.4);background:rgba(100,116,139,.11);color:#475569
}
:global(.ym-dashboard-light) .ym-works-visibility-flag.is-pinned{
  border-color:rgba(15,118,110,.44);background:rgba(15,118,110,.12);color:#0f766e
}
:global(.ym-dashboard-light) .ym-works-visibility-cell-stack.is-metrics small,
:global(.ym-dashboard-light) .ym-works-visibility-cell-stack.is-dates time b{color:#475569}
:global(.ym-dashboard-light) .ym-works-visibility-details-button{
  border-color:rgba(5,150,105,.5);background:rgba(5,150,105,.13);color:#047857
}
:global(.ym-dashboard-light:has(.ym-works-visibility-page) .ym-background-watermark .ym-watermark-logo){opacity:.04}
:global(.ym-dashboard-light:has(.ym-works-visibility-page) .ym-background-watermark .ym-watermark-name){opacity:.03}

/* ── responsive ── */
@media(min-width:1760px){
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:repeat(8,minmax(0,1fr))}
}
@media(max-width:1280px){
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:repeat(4,minmax(0,1fr))}
  .ym-works-visibility-filter-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:1050px){
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:repeat(3,minmax(0,1fr))}
}
@media(max-width:900px){
  .ym-works-visibility-filter-card>header,.ym-works-visibility-table-card__head,.ym-works-visibility-pagination{flex-direction:column;align-items:stretch}
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:repeat(2,minmax(0,1fr))}
  .ym-works-visibility-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .ym-works-visibility-pagination nav{justify-content:space-between}
  .ym-works-visibility-table th{position:static;box-shadow:0 1px 0 var(--ym-control-border)}
}
@media(max-width:760px){
  .ym-works-visibility-table thead{display:none}
  .ym-works-visibility-table,.ym-works-visibility-table tbody{display:grid;width:100%}
  .ym-works-visibility-table tbody{gap:.8rem}
  .ym-works-visibility-table tbody tr{
    display:grid;width:100%;overflow:hidden;border:1px solid var(--ym-soft-border);
    border-radius:18px;background:color-mix(in srgb,var(--ym-card-bg) 97%,#059669 3%)
  }
  .ym-works-visibility-table tbody td{
    display:grid;width:100%;grid-template-columns:minmax(7.5rem,38%) minmax(0,1fr);
    align-items:center;gap:.75rem;border-block-end:1px solid var(--ym-soft-border);text-align:start
  }
  .ym-works-visibility-table tbody td::before{content:attr(data-label);color:var(--ym-muted);font-size:.75rem;font-weight:850}
  .ym-works-visibility-table tbody td.is-title,.ym-works-visibility-table tbody td.is-visibility-actions,.ym-works-visibility-table tbody td.is-action,.ym-works-visibility-table tbody td.is-sequence{width:100%}
  .ym-works-visibility-table tbody td.is-title{display:block}
  .ym-works-visibility-table tbody td.is-title::before{display:block;margin-block-end:.4rem}
  .ym-works-visibility-filter-grid{grid-template-columns:1fr}
  .ym-works-visibility-filter-toolbar{align-items:stretch;flex-direction:column}
  .ym-works-visibility-filter-actions{flex-direction:column}
  .ym-works-visibility-filter-actions .ym-works-visibility-button{width:100%}
  .ym-works-visibility-pagination nav{display:grid;grid-template-columns:1fr;text-align:center}
  .ym-visibility-detail-drawer__head,.ym-visibility-detail-content{padding-inline:1rem}
  .ym-visibility-detail-access-grid,.ym-visibility-detail-grid,.ym-visibility-detail-grid.is-lifecycle,.ym-visibility-detail-people{grid-template-columns:1fr}
}
@media(max-width:640px){
  .ym-works-visibility-page{font-size:14px}
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:repeat(2,minmax(0,1fr))}
  .ym-works-visibility-access-state{border-radius:22px}
  .ym-works-visibility-notice{flex-direction:column}
  .ym-works-visibility-action-status,.ym-visibility-action-dialog__buttons{flex-direction:column;align-items:stretch}
  .ym-visibility-action-dialog__buttons .ym-works-visibility-button{width:100%}
}
@media(max-width:430px){
  .ym-works-visibility-page :deep(.ym-admin-metrics){grid-template-columns:1fr}
}
@media(prefers-reduced-motion:reduce){
  .ym-works-visibility-spinner{animation-duration:1.8s}
  .ym-works-visibility-button,.ym-works-visibility-details-button,.ym-works-visibility-icon-button,.ym-works-visibility-table tbody tr{transition:none}
}
</style>
