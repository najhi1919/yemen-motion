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

      <aside
        class="ym-works-review-policy"
        :class="{ 'is-disabled': !reviewPolicy.enabled }"
        role="status"
        aria-live="polite"
      >
        <span>{{ copy.reviewPolicyLabel }}</span>
        <div>
          <strong>{{ reviewPolicyTitle }}</strong>
          <p>{{ reviewPolicyDescription }}</p>
        </div>
      </aside>

      <aside
        class="ym-works-review-policy"
        :class="{ 'is-disabled': !publicationPolicy.direct_publish_trust_enabled }"
        role="status"
        aria-live="polite"
      >
        <span>{{ copy.publicationPolicyLabel }}</span>
        <div>
          <strong>{{ publicationPolicyTitle }}</strong>
          <p>{{ publicationPolicyDescription }}</p>
          <small>{{ copy.settingsVersion(publicationPolicy.settings_version) }}</small>
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

          <label :class="{ 'is-disabled': !reviewPolicy.enabled }">
            <span>{{ copy.overdue }}</span>
            <select
              v-model="filters.overdue"
              :disabled="!reviewPolicy.enabled"
              :aria-disabled="!reviewPolicy.enabled"
              :aria-describedby="!reviewPolicy.enabled ? 'ym-review-overdue-disabled-reason' : undefined"
            >
              <option v-for="option in booleanOptions" :key="'overdue-' + option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <small v-if="!reviewPolicy.enabled" id="ym-review-overdue-disabled-reason">
              {{ copy.overdueFilterDisabled }}
            </small>
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

      <section v-if="lastReviewAction" class="ym-works-review-followup-card">
        <header>
          <div>
            <span>{{ copy.lastActionFollowup }}</span>
            <h2>{{ lastReviewAction.work.title }}</h2>
            <code dir="ltr">{{ lastReviewAction.work.slug }} · #{{ lastReviewAction.work.id }}</code>
          </div>
          <button
            type="button"
            class="ym-works-review-followup-card__close"
            :title="copy.dismissFollowup"
            :aria-label="copy.dismissFollowup"
            :disabled="actionLoading?.workId === lastReviewAction.work.id"
            @click="dismissLastAction"
          >
            ×
          </button>
        </header>

        <div class="ym-works-review-followup-grid">
          <span>
            {{ copy.lastAction }}
            <strong>{{ actionLabels[lastReviewAction.action] }}</strong>
          </span>
          <span>
            {{ copy.status }}
            <strong>{{ statusLabel(lastReviewAction.work.status) }}</strong>
          </span>
          <span>
            {{ copy.visibility }}
            <strong>{{ visibilityLabel(lastReviewAction.work.visibility_status) }}</strong>
          </span>
          <span>
            {{ copy.reviewer }}
            <strong>{{ reviewerDisplay(lastReviewAction.work.reviewer) }}</strong>
          </span>
          <span>
            {{ copy.changeResult }}
            <strong>{{ lastReviewAction.changed ? copy.changedYes : copy.changedNo }}</strong>
          </span>
          <span>
            {{ copy.queueState }}
            <strong>{{ lastReviewAction.work.review_flags.in_queue ? copy.inQueue : copy.outOfQueue }}</strong>
          </span>
        </div>

        <div class="ym-works-review-followup-flags" :aria-label="copy.reviewFlags">
          <span>{{ copy.assigned }}: {{ booleanLabel(lastReviewAction.work.review_flags.assigned) }}</span>
          <span>{{ copy.decisionMade }}: {{ booleanLabel(lastReviewAction.work.review_flags.decision_made) }}</span>
          <span>{{ copy.isPublished }}: {{ booleanLabel(lastReviewAction.work.review_flags.is_published) }}</span>
          <span>{{ copy.reported }}: {{ booleanLabel(lastReviewAction.work.review_flags.has_reports) }}</span>
          <span>{{ copy.needsAttention }}: {{ booleanLabel(lastReviewAction.work.review_flags.needs_attention) }}</span>
        </div>

        <div class="ym-works-review-followup-actions" :aria-label="copy.followupActions">
          <button
            v-for="action in availableReviewActions(lastReviewAction.work)"
            :key="action.key"
            type="button"
            class="ym-works-review-action-button"
            :class="'is-' + action.tone"
            :disabled="!action.enabled || actionLoading?.workId === lastReviewAction.work.id"
            :title="actionLoading?.workId === lastReviewAction.work.id ? copy.actionInProgress : action.reason"
            @click="requestReviewAction(lastReviewAction.work, action.key)"
          >
            {{ action.label }}
          </button>
        </div>
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

        <aside
          v-if="actionStatus"
          class="ym-works-review-action-status"
          :class="actionStatus.kind === 'success' ? 'is-success' : 'is-error'"
          :role="actionStatus.kind === 'error' ? 'alert' : 'status'"
          aria-live="polite"
        >
          <div>
            <strong>{{ actionStatus.message }}</strong>
            <span>{{ actionStatus.actionLabel }} · {{ actionStatus.workLabel }}</span>
          </div>
          <span v-if="actionStatus.changed !== null" class="ym-works-review-action-status__changed">
            {{ actionStatus.changed ? copy.changedYes : copy.changedNo }}
          </span>
        </aside>

        <div v-if="loading" class="ym-works-review-state" role="status" aria-live="polite">
          <span class="ym-works-review-spinner" aria-hidden="true" />
          <h3>{{ copy.loadingTitle }}</h3>
          <p>{{ copy.loadingCopy }}</p>
        </div>

        <div v-else-if="error" class="ym-works-review-state is-error" role="alert">
          <span class="ym-works-review-state__icon" aria-hidden="true">!</span>
          <h3>{{ copy.errorTitle }}</h3>
          <p>{{ error }}</p>
          <button type="button" class="ym-works-review-button is-secondary" @click="fetchReviewQueue()">
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
                <th class="is-review-actions">{{ copy.reviewActions }}</th>
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
                <td class="is-review-actions">
                  <div class="ym-works-review-action-group" :aria-label="copy.actionsFor(work.title)">
                    <button
                      v-for="action in availableReviewActions(work)"
                      :key="action.key"
                      type="button"
                      class="ym-works-review-action-button"
                      :class="'is-' + action.tone"
                      :disabled="!action.enabled || actionLoading?.workId === work.id"
                      :title="actionLoading?.workId === work.id ? copy.actionInProgress : action.reason"
                      @click="requestReviewAction(work, action.key)"
                    >
                      <span
                        v-if="actionLoading?.workId === work.id && actionLoading.key === action.key"
                        class="ym-works-review-action-spinner"
                        aria-hidden="true"
                      />
                      {{ action.label }}
                    </button>
                  </div>
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

    <div
      v-if="pendingReviewAction"
      class="ym-review-action-backdrop"
      role="presentation"
      @click.self="cancelReviewAction"
    >
      <section
        class="ym-review-action-dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="actionDialogTitleId"
        :aria-describedby="actionDialogDescriptionId"
      >
        <span class="ym-review-action-dialog__eyebrow">{{ copy.reviewActionConfirmation }}</span>
        <h2 :id="actionDialogTitleId">{{ copy.confirmAction(pendingReviewAction.label) }}</h2>
        <p :id="actionDialogDescriptionId">{{ actionDescriptions[pendingReviewAction.key] }}</p>

        <div class="ym-review-action-dialog__work">
          <span>{{ copy.affectedWork }}</span>
          <strong :dir="textDirection(pendingReviewAction.target.title)">
            {{ pendingReviewAction.target.title }}
          </strong>
          <code dir="ltr">{{ pendingReviewAction.target.slug }} · #{{ pendingReviewAction.target.id }}</code>
        </div>

        <label v-if="pendingReviewAction.kind === 'reviewer'" class="ym-review-action-dialog__field">
          <span>{{ copy.reviewerId }}</span>
          <input
            v-model="reviewerIdInput"
            type="number"
            min="1"
            step="1"
            inputmode="numeric"
            required
            :disabled="actionLoading !== null"
            :aria-invalid="Boolean(actionFieldErrors.reviewer_id)"
            autofocus
          />
          <small>{{ copy.currentReviewer }}: {{ reviewerDisplay(pendingReviewAction.target.reviewer) }}</small>
          <strong v-if="actionFieldErrors.reviewer_id" role="alert">{{ actionFieldErrors.reviewer_id }}</strong>
        </label>

        <label v-else-if="pendingReviewAction.kind === 'changes'" class="ym-review-action-dialog__field">
          <span>{{ copy.changeRequestNotesInput }}</span>
          <textarea
            v-model="changeRequestNotesInput"
            minlength="5"
            maxlength="2000"
            rows="7"
            required
            :disabled="actionLoading !== null"
            :aria-invalid="Boolean(actionFieldErrors.change_request_notes)"
            autofocus
          />
          <small>{{ textLength(changeRequestNotesInput) }} / 2000</small>
          <strong v-if="actionFieldErrors.change_request_notes" role="alert">
            {{ actionFieldErrors.change_request_notes }}
          </strong>
        </label>

        <label v-else-if="pendingReviewAction.kind === 'reject'" class="ym-review-action-dialog__field">
          <span>{{ copy.rejectionReasonInput }}</span>
          <textarea
            v-model="rejectionReasonInput"
            minlength="5"
            maxlength="2000"
            rows="7"
            required
            :disabled="actionLoading !== null"
            :aria-invalid="Boolean(actionFieldErrors.rejection_reason)"
            autofocus
          />
          <small>{{ textLength(rejectionReasonInput) }} / 2000</small>
          <strong v-if="actionFieldErrors.rejection_reason" role="alert">
            {{ actionFieldErrors.rejection_reason }}
          </strong>
        </label>

        <p v-if="modalActionError" class="ym-review-action-dialog__error" role="alert">
          {{ modalActionError }}
        </p>

        <div class="ym-review-action-dialog__buttons">
          <button
            type="button"
            class="ym-works-review-button is-primary"
            :disabled="!canConfirmReviewAction || actionLoading !== null"
            @click="confirmReviewAction"
          >
            <span v-if="actionLoading !== null" class="ym-works-review-action-spinner" aria-hidden="true" />
            {{ actionLoading !== null ? copy.executingAction : copy.confirmExecution }}
          </button>
          <button
            type="button"
            class="ym-works-review-button is-secondary"
            :disabled="actionLoading !== null"
            @click="cancelReviewAction"
          >
            {{ copy.cancel }}
          </button>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
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
type ReviewActionKey = 'start' | 'assign_reviewer' | 'approve' | 'request_changes' | 'reject' | 'publish' | 'reopen'
type ReviewActionKind = 'simple' | 'reviewer' | 'changes' | 'reject'
type ReviewActionTone = 'primary' | 'info' | 'positive' | 'warning' | 'danger' | 'promotion' | 'neutral'

interface UserReference {
  id: number
  name: string
}

interface ReviewFlags {
  assigned: boolean
  overdue: boolean
  needs_attention: boolean
}

interface ActionReviewFlags {
  assigned: boolean
  in_queue: boolean
  decision_made: boolean
  is_published: boolean
  has_reports: boolean
  needs_attention: boolean
}

interface ReviewActionWork {
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
  reviewed_at: string | null
  approved_at: string | null
  published_at: string | null
  rejected_at: string | null
  updated_at: string | null
  created_at: string | null
  review_flags: ActionReviewFlags
}

interface ReviewActionResponse {
  success: boolean
  data: {
    action: ReviewActionKey
    changed: boolean
    auto_published: boolean
    publication_policy: PublicationPolicy
    work: ReviewActionWork
  } | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface ReviewActionTarget {
  id: number
  title: string
  slug: string
  status: WorkStatus
  visibility_status: VisibilityStatus
  reviewer: UserReference | null
}

interface ReviewActionDefinition {
  key: ReviewActionKey
  endpoint: string
  permission: string
  kind: ReviewActionKind
  tone: ReviewActionTone
}

interface ReviewActionView extends ReviewActionDefinition {
  label: string
  enabled: boolean
  reason: string
}

interface PendingReviewAction extends ReviewActionDefinition {
  label: string
  target: ReviewActionTarget
}

interface ReviewActionStatus {
  kind: 'success' | 'error'
  message: string
  changed: boolean | null
  actionLabel: string
  workLabel: string
}

interface LastReviewAction {
  action: ReviewActionKey
  changed: boolean
  work: {
    id: number
    title: string
    slug: string
    status: WorkStatus
    visibility_status: VisibilityStatus
    reviewer: UserReference | null
    review_flags: ActionReviewFlags
  }
}

interface ReviewActionFieldErrors {
  reviewer_id?: string
  change_request_notes?: string
  rejection_reason?: string
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

interface ReviewPolicy {
  source: 'work_settings'
  enabled: boolean
  review_sla_hours: number | null
  overdue_cutoff: string | null
  settings_version: number
}

interface PublicationPolicy {
  source: 'work_settings'
  direct_publish_trust_enabled: boolean
  approval_behavior: 'approve_only' | 'approve_and_publish'
  settings_version: number
}

interface ReviewQueueData {
  items: ReviewQueueItem[]
  pagination: ReviewPagination
  summary: ReviewSummary
  review_policy: ReviewPolicy
  publication_policy: PublicationPolicy
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

const reviewActionDefinitions: ReviewActionDefinition[] = [
  {
    key: 'start',
    endpoint: 'start',
    permission: 'admin.works.review.start',
    kind: 'simple',
    tone: 'primary'
  },
  {
    key: 'assign_reviewer',
    endpoint: 'assign-reviewer',
    permission: 'admin.works.review.assign_reviewer',
    kind: 'reviewer',
    tone: 'info'
  },
  {
    key: 'approve',
    endpoint: 'approve',
    permission: 'admin.works.review.approve',
    kind: 'simple',
    tone: 'positive'
  },
  {
    key: 'request_changes',
    endpoint: 'request-changes',
    permission: 'admin.works.review.request_changes',
    kind: 'changes',
    tone: 'warning'
  },
  {
    key: 'reject',
    endpoint: 'reject',
    permission: 'admin.works.review.reject',
    kind: 'reject',
    tone: 'danger'
  },
  {
    key: 'publish',
    endpoint: 'publish',
    permission: 'admin.works.review.publish_after_approval',
    kind: 'simple',
    tone: 'promotion'
  },
  {
    key: 'reopen',
    endpoint: 'reopen',
    permission: 'admin.works.review.reopen',
    kind: 'simple',
    tone: 'neutral'
  }
]

const copyMap = {
  ar: {
    readonly: 'إجراءات مراجعة مضبوطة',
    kicker: 'إدارة دورة مراجعة الأعمال',
    title: 'طلبات المراجعة',
    description: 'قائمة إدارية آمنة لتنظيم الأعمال المرسلة وتحت المراجعة وطلبات التعديل، مع قراءة التفاصيل المسموحة فقط.',
    totalRequests: 'إجمالي الطلبات',
    safeQueue: 'طلبات مطابقة للفلاتر الحالية',
    authLoadingTitle: 'جارٍ التحقق من صلاحية المراجعة',
    authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.',
    forbiddenTitle: 'الوصول إلى طلبات المراجعة غير متاح',
    forbiddenCopy: 'لا يملك هذا الحساب صلاحيات قائمة المراجعة المطلوبة. لم تتم محاولة تحميل البيانات.',
    noticeTitle: 'قرارات المراجعة محكومة بالصلاحية والحالة',
    notice: 'لا يظهر للمستخدم إلا الإجراء الذي تسمح به صلاحيته، وتبقى الحالات غير المناسبة معطلة بسبب واضح قبل التأكيد.',
    reviewPolicyLabel: 'سياسة التأخر الزمنية',
    reviewSlaEnabled: (hours: number) => `مهلة المراجعة الحالية: ${hours} ساعة`,
    reviewSlaEnabledDescription: 'يُعد الطلب متأخرًا بعد تجاوز هذه المهلة.',
    reviewSlaDisabled: 'مهلة المراجعة غير مفعلة',
    reviewSlaDisabledDescription: 'لن تُصنف الطلبات كمتأخرة اعتمادًا على الزمن.',
    overdueFilterDisabled: 'فلتر التأخر معطل لأن مهلة المراجعة غير مفعلة.',
    publicationPolicyLabel: 'سياسة نتيجة الاعتماد',
    directPublishEnabled: 'النشر المباشر بعد الاعتماد مفعّل',
    directPublishEnabledDescription: 'اعتماد العمل سينشره للعامة مباشرةً وفق سياسة الثقة الحالية.',
    directPublishDisabled: 'النشر المباشر بعد الاعتماد غير مفعّل',
    directPublishDisabledDescription: 'سيبقى العمل مخفيًا بعد الاعتماد إلى أن يُنفذ إجراء النشر المنفصل.',
    settingsVersion: (version: number) => `إصدار الإعدادات: ${version}`,
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
    overdueHint: (hours: number) => `بعد ${hours} ساعة`,
    overdueDisabledHint: 'مهلة التأخر غير مفعلة',
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
    tableCopy: 'رتّب الطلبات من رؤوس الأعمدة، ونفّذ إجراءات المراجعة المصرح بها بعد التأكيد.',
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
    visibility: 'الظهور',
    reviewActions: 'إجراءات المراجعة',
    actionsFor: (title: string) => 'إجراءات المراجعة للعمل: ' + title,
    actionInProgress: 'جارٍ تنفيذ إجراء مراجعة لهذا العمل.',
    actionAvailable: 'الإجراء متاح لهذه الحالة.',
    startAlreadyOpen: 'المراجعة بدأت بالفعل.',
    startUnavailable: 'لا يمكن بدء المراجعة من الحالة الحالية.',
    assignUnavailable: 'لا يمكن تعيين المراجع في الحالة الحالية.',
    alreadyApproved: 'العمل معتمد بالفعل.',
    approveUnavailable: 'لا يمكن اعتماد العمل من الحالة الحالية.',
    changesUnavailable: 'لا يمكن طلب تعديلات من الحالة الحالية.',
    rejectUnavailable: 'لا يمكن رفض العمل من الحالة الحالية.',
    alreadyPublished: 'العمل منشور بالفعل.',
    publishedHiddenUnavailable: 'العمل منشور لكنه غير عام؛ لا يمكن تنفيذ النشر بعد الاعتماد.',
    publishUnavailable: 'لا يمكن النشر قبل اعتماد العمل.',
    reopenAlreadyOpen: 'المراجعة مفتوحة بالفعل.',
    reopenUnavailable: 'لا يمكن إعادة فتح المراجعة من الحالة الحالية.',
    lastActionFollowup: 'متابعة آخر إجراء مراجعة',
    dismissFollowup: 'إغلاق بطاقة المتابعة',
    lastAction: 'آخر إجراء',
    changeResult: 'نتيجة التغيير',
    changedYes: 'تم تغيير الحالة',
    changedNo: 'لم تتغير الحالة',
    queueState: 'حالة الطابور',
    inQueue: 'ضمن طابور المراجعة',
    outOfQueue: 'خارج طابور المراجعة',
    reviewFlags: 'مؤشرات المراجعة الحالية',
    decisionMade: 'صدر قرار',
    isPublished: 'منشور',
    followupActions: 'الإجراءات التالية المتاحة',
    reviewActionConfirmation: 'تأكيد إجراء مراجعة فعلي',
    confirmAction: (action: string) => 'تأكيد إجراء: ' + action,
    affectedWork: 'العمل المتأثر',
    currentReviewer: 'المراجع الحالي',
    changeRequestNotesInput: 'ملاحظات طلب التعديلات',
    rejectionReasonInput: 'سبب رفض العمل',
    confirmExecution: 'تأكيد التنفيذ',
    executingAction: 'جارٍ التنفيذ...',
    cancel: 'إلغاء',
    actionSucceeded: 'تم تنفيذ إجراء المراجعة بنجاح',
    approveAndPublishSucceeded: 'تم اعتماد العمل ونشره للعامة مباشرةً.',
    actionUnchanged: 'لا يوجد تغيير؛ الحالة مطابقة بالفعل',
    actionDenied: 'غير مصرح بتنفيذ هذا الإجراء.',
    actionNotFound: 'لم يعد العمل موجودًا.',
    actionFailed: 'تعذر تنفيذ إجراء المراجعة. حاول مرة أخرى.',
    actionResponseInvalid: 'تعذر اعتماد نتيجة الإجراء. أُبقيت بيانات الصفحة دون تغيير.',
    reviewerRequired: 'أدخل معرّف مراجع صحيحًا وموجبًا.',
    notesLengthInvalid: 'يجب أن يكون النص بين 5 و2000 حرف.',
    startDescription: 'سيُنقل العمل إلى حالة تحت المراجعة ويُعيّن المنفذ كمراجع عند عدم وجود مراجع.',
    assignDescription: 'سيُعيّن معرّف المستخدم الداخلي المحدد كمراجع دون تغيير حالة العمل.',
    approveDescription: 'سيصبح العمل معتمدًا ومخفيًا، ويمكن نشره لاحقًا بإجراء مستقل.',
    approveAndPublishDescription: 'سيصبح العمل منشورًا للعامة فور تأكيد الاعتماد.',
    changesDescription: 'سيُطلب تعديل العمل، أو تُحدّث الملاحظات إذا كان الطلب قائمًا.',
    rejectDescription: 'سيُرفض العمل، أو يُحدّث سبب الرفض إذا كان مرفوضًا بالفعل.',
    publishDescription: 'سيُنشر العمل المعتمد ويصبح ظاهرًا للعامة.',
    reopenDescription: 'سيُعاد العمل إلى حالة تحت المراجعة مع الحفاظ على أثر القرارات السابقة.',
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
    readonly: 'Controlled review actions',
    kicker: 'Works review workflow',
    title: 'Review Requests',
    description: 'A safe administrative queue for organizing submitted, in-review, and changes-requested works with permission-scoped details.',
    totalRequests: 'Total requests',
    safeQueue: 'Requests matching current filters',
    authLoadingTitle: 'Checking review queue access',
    authLoadingCopy: 'Waiting for the user session to initialize before requesting data.',
    forbiddenTitle: 'Review requests access is unavailable',
    forbiddenCopy: 'This account lacks the required review queue permissions. No data request was made.',
    noticeTitle: 'Review decisions are permission and state controlled',
    notice: 'Users only see actions allowed by their exact permissions. Invalid state transitions remain disabled with a clear reason.',
    reviewPolicyLabel: 'Time-based overdue policy',
    reviewSlaEnabled: (hours: number) => `Current review SLA: ${hours} hours`,
    reviewSlaEnabledDescription: 'A request becomes overdue only after it exceeds this threshold.',
    reviewSlaDisabled: 'Review SLA is disabled',
    reviewSlaDisabledDescription: 'Requests will not be classified as overdue based on time.',
    overdueFilterDisabled: 'The overdue filter is disabled because the review SLA is disabled.',
    publicationPolicyLabel: 'Approval result policy',
    directPublishEnabled: 'Direct publishing after approval is enabled',
    directPublishEnabledDescription: 'Approving the work will publish it publicly under the current trust policy.',
    directPublishDisabled: 'Direct publishing after approval is disabled',
    directPublishDisabledDescription: 'The work will remain hidden after approval until the separate publish action is run.',
    settingsVersion: (version: number) => `Settings version: ${version}`,
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
    overdueHint: (hours: number) => `After ${hours} hours`,
    overdueDisabledHint: 'The overdue threshold is disabled',
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
    tableCopy: 'Sort from supported headers and run permitted review actions after confirmation.',
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
    visibility: 'Visibility',
    reviewActions: 'Review actions',
    actionsFor: (title: string) => 'Review actions for: ' + title,
    actionInProgress: 'A review action is being executed for this work.',
    actionAvailable: 'The action is available for this state.',
    startAlreadyOpen: 'The review has already started.',
    startUnavailable: 'The review cannot be started from the current state.',
    assignUnavailable: 'A reviewer cannot be assigned in the current state.',
    alreadyApproved: 'The work is already approved.',
    approveUnavailable: 'The work cannot be approved from the current state.',
    changesUnavailable: 'Changes cannot be requested from the current state.',
    rejectUnavailable: 'The work cannot be rejected from the current state.',
    alreadyPublished: 'The work is already published.',
    publishedHiddenUnavailable: 'The work is published but not public; publish-after-approval is unavailable.',
    publishUnavailable: 'The work must be approved before publishing.',
    reopenAlreadyOpen: 'The review is already open.',
    reopenUnavailable: 'The review cannot be reopened from the current state.',
    lastActionFollowup: 'Latest review action follow-up',
    dismissFollowup: 'Dismiss follow-up card',
    lastAction: 'Last action',
    changeResult: 'Change result',
    changedYes: 'State changed',
    changedNo: 'State unchanged',
    queueState: 'Queue state',
    inQueue: 'In the review queue',
    outOfQueue: 'Outside the review queue',
    reviewFlags: 'Current review flags',
    decisionMade: 'Decision made',
    isPublished: 'Published',
    followupActions: 'Available next actions',
    reviewActionConfirmation: 'Confirm a real review action',
    confirmAction: (action: string) => 'Confirm action: ' + action,
    affectedWork: 'Affected work',
    currentReviewer: 'Current reviewer',
    changeRequestNotesInput: 'Change request notes',
    rejectionReasonInput: 'Rejection reason',
    confirmExecution: 'Confirm execution',
    executingAction: 'Executing...',
    cancel: 'Cancel',
    actionSucceeded: 'The review action was completed successfully',
    approveAndPublishSucceeded: 'The work was approved and published publicly.',
    actionUnchanged: 'No change; the state already matches',
    actionDenied: 'You are not authorized to run this action.',
    actionNotFound: 'The work no longer exists.',
    actionFailed: 'The review action could not be completed. Try again.',
    actionResponseInvalid: 'The action result could not be accepted. Page data was left unchanged.',
    reviewerRequired: 'Enter a valid positive reviewer ID.',
    notesLengthInvalid: 'The text must contain between 5 and 2000 characters.',
    startDescription: 'The work will move into review and the actor will be assigned when no reviewer exists.',
    assignDescription: 'The selected internal user ID will be assigned without changing the work state.',
    approveDescription: 'The work will become approved and hidden, and can be published later with a separate action.',
    approveAndPublishDescription: 'The work will become publicly published as soon as approval is confirmed.',
    changesDescription: 'Changes will be requested, or the notes will be updated for an existing request.',
    rejectDescription: 'The work will be rejected, or its rejection reason will be updated.',
    publishDescription: 'The approved work will be published and made public.',
    reopenDescription: 'The work will return to review while preserving prior decision history.',
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
const actionLabels = computed<Record<ReviewActionKey, string>>(() => currentLocale.value === 'ar'
  ? {
      start: 'بدء المراجعة',
      assign_reviewer: 'تعيين المراجع',
      approve: publicationPolicy.value.approval_behavior === 'approve_and_publish'
        ? 'اعتماد ونشر مباشرة'
        : 'اعتماد العمل',
      request_changes: 'طلب تعديلات',
      reject: 'رفض العمل',
      publish: 'النشر بعد الاعتماد',
      reopen: 'إعادة فتح المراجعة'
    }
  : {
      start: 'Start review',
      assign_reviewer: 'Assign reviewer',
      approve: publicationPolicy.value.approval_behavior === 'approve_and_publish'
        ? 'Approve and publish'
        : 'Approve work',
      request_changes: 'Request changes',
      reject: 'Reject work',
      publish: 'Publish after approval',
      reopen: 'Reopen review'
    })
const actionDescriptions = computed<Record<ReviewActionKey, string>>(() => ({
  start: copy.value.startDescription,
  assign_reviewer: copy.value.assignDescription,
  approve: publicationPolicy.value.approval_behavior === 'approve_and_publish'
    ? copy.value.approveAndPublishDescription
    : copy.value.approveDescription,
  request_changes: copy.value.changesDescription,
  reject: copy.value.rejectDescription,
  publish: copy.value.publishDescription,
  reopen: copy.value.reopenDescription
}))
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
const reviewPolicy = ref<ReviewPolicy>(disabledReviewPolicy())
const publicationPolicy = ref<PublicationPolicy>(disabledPublicationPolicy())

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

function disabledReviewPolicy(): ReviewPolicy {
  return {
    source: 'work_settings',
    enabled: false,
    review_sla_hours: null,
    overdue_cutoff: null,
    settings_version: 1
  }
}

function disabledPublicationPolicy(): PublicationPolicy {
  return {
    source: 'work_settings',
    direct_publish_trust_enabled: false,
    approval_behavior: 'approve_only',
    settings_version: 1
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
const pendingReviewAction = ref<PendingReviewAction | null>(null)
const actionLoading = ref<{ workId: number; key: ReviewActionKey } | null>(null)
const actionStatus = ref<ReviewActionStatus | null>(null)
const lastReviewAction = ref<LastReviewAction | null>(null)
const reviewerIdInput = ref('')
const changeRequestNotesInput = ref('')
const rejectionReasonInput = ref('')
const actionFieldErrors = reactive<ReviewActionFieldErrors>({})
const modalActionError = ref<string | null>(null)
const actionDialogTitleId = 'ym-review-action-dialog-title'
const actionDialogDescriptionId = 'ym-review-action-dialog-description'

const canConfirmReviewAction = computed(() => {
  const pending = pendingReviewAction.value
  if (!pending) return false

  if (pending.kind === 'reviewer') {
    const reviewerId = Number(reviewerIdInput.value)
    return Number.isInteger(reviewerId) && reviewerId > 0
  }

  if (pending.kind === 'changes') {
    const length = textLength(changeRequestNotesInput.value.trim())
    return length >= 5 && length <= 2000
  }

  if (pending.kind === 'reject') {
    const length = textLength(rejectionReasonInput.value.trim())
    return length >= 5 && length <= 2000
  }

  return true
})

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

const reviewPolicyTitle = computed(() => (
  reviewPolicy.value.enabled && reviewPolicy.value.review_sla_hours !== null
    ? copy.value.reviewSlaEnabled(reviewPolicy.value.review_sla_hours)
    : copy.value.reviewSlaDisabled
))

const reviewPolicyDescription = computed(() => (
  reviewPolicy.value.enabled
    ? copy.value.reviewSlaEnabledDescription
    : copy.value.reviewSlaDisabledDescription
))

const publicationPolicyTitle = computed(() => (
  publicationPolicy.value.direct_publish_trust_enabled
    ? copy.value.directPublishEnabled
    : copy.value.directPublishDisabled
))

const publicationPolicyDescription = computed(() => (
  publicationPolicy.value.direct_publish_trust_enabled
    ? copy.value.directPublishEnabledDescription
    : copy.value.directPublishDisabledDescription
))

const overdueSummaryHint = computed(() => (
  reviewPolicy.value.enabled && reviewPolicy.value.review_sla_hours !== null
    ? copy.value.overdueHint(reviewPolicy.value.review_sla_hours)
    : copy.value.overdueDisabledHint
))

const summaryCards = computed(() => [
  { key: 'total', label: copy.value.total, value: summary.total, hint: copy.value.totalHint, color: '#8b5cf6' },
  { key: 'submitted', label: copy.value.submitted, value: summary.submitted, hint: copy.value.submittedHint, color: '#38bdf8' },
  { key: 'in_review', label: copy.value.inReview, value: summary.in_review, hint: copy.value.inReviewHint, color: '#6366f1' },
  { key: 'changes_requested', label: copy.value.changesRequested, value: summary.changes_requested, hint: copy.value.changesRequestedHint, color: '#f59e0b' },
  { key: 'assigned', label: copy.value.assigned, value: summary.assigned, hint: copy.value.assignedHint, color: '#14b8a6' },
  { key: 'unassigned', label: copy.value.unassigned, value: summary.unassigned, hint: copy.value.unassignedHint, color: '#94a3b8' },
  { key: 'overdue', label: copy.value.overdue, value: summary.overdue, hint: overdueSummaryHint.value, color: '#f43f5e' },
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

function textLength(value: string): number {
  return Array.from(value).length
}

function reviewerDisplay(reviewer: UserReference | null): string {
  return reviewer ? reviewer.name + ' (#' + reviewer.id + ')' : '—'
}

function hasActionPermission(permission: string): boolean {
  if (!hasReviewAccess.value) return false
  return authStore.role === 'super-admin' || authStore.permissions.includes(permission)
}

function reviewActionAvailability(
  target: ReviewActionTarget,
  key: ReviewActionKey
): { enabled: boolean; reason: string } {
  const hasReviewer = target.reviewer !== null

  if (key === 'start') {
    if (target.status === 'submitted') return { enabled: true, reason: copy.value.actionAvailable }
    if (target.status === 'in_review' && !hasReviewer) {
      return { enabled: true, reason: copy.value.actionAvailable }
    }
    return {
      enabled: false,
      reason: target.status === 'in_review' ? copy.value.startAlreadyOpen : copy.value.startUnavailable
    }
  }

  if (key === 'assign_reviewer') {
    const enabled = ['submitted', 'in_review', 'changes_requested'].includes(target.status)
    return { enabled, reason: enabled ? copy.value.actionAvailable : copy.value.assignUnavailable }
  }

  if (key === 'approve') {
    if (target.status === 'in_review') return { enabled: true, reason: copy.value.actionAvailable }
    return {
      enabled: false,
      reason: target.status === 'approved' ? copy.value.alreadyApproved : copy.value.approveUnavailable
    }
  }

  if (key === 'request_changes') {
    const enabled = target.status === 'in_review' || target.status === 'changes_requested'
    return { enabled, reason: enabled ? copy.value.actionAvailable : copy.value.changesUnavailable }
  }

  if (key === 'reject') {
    const enabled = target.status === 'in_review' || target.status === 'rejected'
    return { enabled, reason: enabled ? copy.value.actionAvailable : copy.value.rejectUnavailable }
  }

  if (key === 'publish') {
    if (target.status === 'approved') return { enabled: true, reason: copy.value.actionAvailable }
    if (target.status === 'published' && target.visibility_status === 'public') {
      return { enabled: false, reason: copy.value.alreadyPublished }
    }
    return {
      enabled: false,
      reason: target.status === 'published'
        ? copy.value.publishedHiddenUnavailable
        : copy.value.publishUnavailable
    }
  }

  if (target.status === 'in_review') {
    return hasReviewer
      ? { enabled: false, reason: copy.value.reopenAlreadyOpen }
      : { enabled: true, reason: copy.value.actionAvailable }
  }

  const enabled = ['changes_requested', 'rejected', 'approved'].includes(target.status)
  return { enabled, reason: enabled ? copy.value.actionAvailable : copy.value.reopenUnavailable }
}

function availableReviewActions(target: ReviewActionTarget): ReviewActionView[] {
  return reviewActionDefinitions
    .filter((action) => hasActionPermission(action.permission))
    .map((action) => ({
      ...action,
      label: actionLabels.value[action.key],
      ...reviewActionAvailability(target, action.key)
    }))
}

function toActionTarget(source: ReviewActionTarget): ReviewActionTarget {
  return {
    id: source.id,
    title: source.title,
    slug: source.slug,
    status: source.status,
    visibility_status: source.visibility_status,
    reviewer: source.reviewer
      ? { id: source.reviewer.id, name: source.reviewer.name }
      : null
  }
}

function clearActionFieldErrors(): void {
  delete actionFieldErrors.reviewer_id
  delete actionFieldErrors.change_request_notes
  delete actionFieldErrors.rejection_reason
  modalActionError.value = null
}

function clearActionInputs(): void {
  reviewerIdInput.value = ''
  changeRequestNotesInput.value = ''
  rejectionReasonInput.value = ''
  clearActionFieldErrors()
}

function requestReviewAction(target: ReviewActionTarget, key: ReviewActionKey): void {
  if (actionLoading.value || pendingReviewAction.value) return

  const definition = reviewActionDefinitions.find((action) => action.key === key)
  if (!definition || !hasActionPermission(definition.permission)) return

  const availability = reviewActionAvailability(target, key)
  if (!availability.enabled) return

  clearActionInputs()
  if (definition.kind === 'reviewer' && target.reviewer) {
    reviewerIdInput.value = String(target.reviewer.id)
  }

  pendingReviewAction.value = {
    ...definition,
    label: actionLabels.value[key],
    target: toActionTarget(target)
  }
}

function cancelReviewAction(): void {
  if (actionLoading.value) return
  pendingReviewAction.value = null
  clearActionInputs()
}

function dismissLastAction(): void {
  if (actionLoading.value?.workId === lastReviewAction.value?.work.id) return
  lastReviewAction.value = null
}

function requestErrorData(requestError: unknown): Record<string, unknown> | null {
  if (!requestError || typeof requestError !== 'object' || !('data' in requestError)) return null
  const data = (requestError as { data?: unknown }).data
  return data && typeof data === 'object' ? data as Record<string, unknown> : null
}

function serverActionMessage(requestError: unknown): string | null {
  const data = requestErrorData(requestError)
  if (!data || typeof data.message !== 'string') return null
  const message = data.message.trim()
  return message || null
}

function applyServerFieldErrors(requestError: unknown): void {
  const data = requestErrorData(requestError)
  const errors = data?.errors
  if (!errors || typeof errors !== 'object') return

  for (const key of ['reviewer_id', 'change_request_notes', 'rejection_reason'] as const) {
    const messages = (errors as Record<string, unknown>)[key]
    if (Array.isArray(messages) && typeof messages[0] === 'string') {
      actionFieldErrors[key] = messages[0]
    }
  }
}

function safeUserReference(value: unknown): UserReference | null {
  if (!value || typeof value !== 'object') return null
  const user = value as Record<string, unknown>
  if (!Number.isInteger(user.id) || typeof user.name !== 'string') return null
  return { id: user.id as number, name: user.name }
}

function isWorkStatus(value: unknown): value is WorkStatus {
  return typeof value === 'string' && [
    'draft',
    'submitted',
    'in_review',
    'changes_requested',
    'approved',
    'published',
    'rejected',
    'hidden',
    'archived'
  ].includes(value)
}

function isReviewStatus(value: WorkStatus): value is ReviewStatus {
  return ['submitted', 'in_review', 'changes_requested'].includes(value)
}

function safeStringOrNull(value: unknown): string | null {
  return typeof value === 'string' ? value : null
}

function safeCount(value: unknown): number {
  return typeof value === 'number' && Number.isFinite(value) ? value : 0
}

function toSafePublicationPolicy(value: unknown): PublicationPolicy | null {
  if (!value || typeof value !== 'object') return null
  const policy = value as Record<string, unknown>

  if (
    policy.source !== 'work_settings'
    || typeof policy.direct_publish_trust_enabled !== 'boolean'
    || !['approve_only', 'approve_and_publish'].includes(String(policy.approval_behavior))
    || !Number.isInteger(policy.settings_version)
    || (policy.direct_publish_trust_enabled
      ? policy.approval_behavior !== 'approve_and_publish'
      : policy.approval_behavior !== 'approve_only')
  ) {
    return null
  }

  return {
    source: 'work_settings',
    direct_publish_trust_enabled: policy.direct_publish_trust_enabled,
    approval_behavior: policy.approval_behavior as PublicationPolicy['approval_behavior'],
    settings_version: policy.settings_version as number
  }
}

function toSafeActionWork(value: unknown): ReviewActionWork | null {
  if (!value || typeof value !== 'object') return null
  const work = value as Record<string, unknown>
  const flags = work.review_flags

  if (
    !Number.isInteger(work.id)
    || typeof work.title !== 'string'
    || typeof work.slug !== 'string'
    || !isWorkStatus(work.status)
    || (work.visibility_status !== 'hidden' && work.visibility_status !== 'public')
    || !flags
    || typeof flags !== 'object'
  ) {
    return null
  }

  const reviewFlags = flags as Record<string, unknown>
  if (![
    'assigned',
    'in_queue',
    'decision_made',
    'is_published',
    'has_reports',
    'needs_attention'
  ].every((key) => typeof reviewFlags[key] === 'boolean')) {
    return null
  }

  const designer = safeUserReference(work.designer)
  const reviewer = safeUserReference(work.reviewer)
  if ((work.designer !== null && !designer) || (work.reviewer !== null && !reviewer)) {
    return null
  }

  return {
    id: work.id as number,
    title: work.title,
    slug: work.slug,
    summary: safeStringOrNull(work.summary),
    status: work.status,
    visibility_status: work.visibility_status,
    media_type: safeStringOrNull(work.media_type),
    designer,
    reviewer,
    category_id: typeof work.category_id === 'number' ? work.category_id : null,
    is_featured: work.is_featured === true,
    is_pinned: work.is_pinned === true,
    reports_count: safeCount(work.reports_count),
    views_count: safeCount(work.views_count),
    likes_count: safeCount(work.likes_count),
    submitted_at: safeStringOrNull(work.submitted_at),
    reviewed_at: safeStringOrNull(work.reviewed_at),
    approved_at: safeStringOrNull(work.approved_at),
    published_at: safeStringOrNull(work.published_at),
    rejected_at: safeStringOrNull(work.rejected_at),
    updated_at: safeStringOrNull(work.updated_at),
    created_at: safeStringOrNull(work.created_at),
    review_flags: {
      assigned: reviewFlags.assigned === true,
      in_queue: reviewFlags.in_queue === true,
      decision_made: reviewFlags.decision_made === true,
      is_published: reviewFlags.is_published === true,
      has_reports: reviewFlags.has_reports === true,
      needs_attention: reviewFlags.needs_attention === true
    }
  }
}

function updateQueueItemFromAction(work: ReviewActionWork): void {
  const index = items.value.findIndex((item) => item.id === work.id)

  if (!isReviewStatus(work.status)) {
    if (index !== -1) items.value.splice(index, 1)
    return
  }

  if (index === -1) return
  const current = items.value[index]
  if (!current) return

  items.value[index] = {
    id: work.id,
    title: work.title,
    slug: work.slug,
    summary: work.summary,
    status: work.status,
    visibility_status: work.visibility_status,
    media_type: work.media_type,
    designer: work.designer,
    reviewer: work.reviewer,
    category_id: work.category_id,
    reports_count: work.reports_count,
    views_count: work.views_count,
    likes_count: work.likes_count,
    submitted_at: work.submitted_at,
    reviewed_at: work.reviewed_at,
    updated_at: work.updated_at,
    created_at: work.created_at,
    review_flags: {
      assigned: work.review_flags.assigned,
      overdue: work.status === 'changes_requested' ? false : current.review_flags.overdue,
      needs_attention: work.review_flags.needs_attention
    }
  }
}

function updateOpenDetailFromAction(work: ReviewActionWork): void {
  if (!detail.value || detail.value.work.id !== work.id) return

  const current = detail.value.work
  detail.value.work = {
    id: work.id,
    title: work.title,
    slug: work.slug,
    summary: work.summary,
    status: work.status,
    visibility_status: work.visibility_status,
    media_type: work.media_type,
    price_amount: current.price_amount,
    delivery_days: current.delivery_days,
    category_id: work.category_id,
    is_featured: work.is_featured,
    is_pinned: work.is_pinned,
    reports_count: work.reports_count,
    views_count: work.views_count,
    likes_count: work.likes_count,
    submitted_at: work.submitted_at,
    reviewed_at: work.reviewed_at,
    approved_at: work.approved_at,
    published_at: work.published_at,
    rejected_at: work.rejected_at,
    hidden_at: current.hidden_at,
    archived_at: current.archived_at,
    updated_at: work.updated_at,
    created_at: work.created_at
  }

  if (detail.value.field_access.can_view_designer) {
    detail.value.relations = {
      designer: work.designer,
      reviewer: work.reviewer
    }
  }
  selectedWorkTitle.value = work.title
}

function validateActionInput(pending: PendingReviewAction): boolean {
  clearActionFieldErrors()

  if (pending.kind === 'reviewer') {
    const reviewerId = Number(reviewerIdInput.value)
    if (!Number.isInteger(reviewerId) || reviewerId < 1) {
      actionFieldErrors.reviewer_id = copy.value.reviewerRequired
      return false
    }
  }

  if (pending.kind === 'changes') {
    const length = textLength(changeRequestNotesInput.value.trim())
    if (length < 5 || length > 2000) {
      actionFieldErrors.change_request_notes = copy.value.notesLengthInvalid
      return false
    }
  }

  if (pending.kind === 'reject') {
    const length = textLength(rejectionReasonInput.value.trim())
    if (length < 5 || length > 2000) {
      actionFieldErrors.rejection_reason = copy.value.notesLengthInvalid
      return false
    }
  }

  return true
}

async function confirmReviewAction(): Promise<void> {
  const pending = pendingReviewAction.value
  if (!pending || actionLoading.value || !validateActionInput(pending)) return
  if (!hasActionPermission(pending.permission)) return

  const availability = reviewActionAvailability(pending.target, pending.key)
  if (!availability.enabled) {
    modalActionError.value = availability.reason
    return
  }

  actionLoading.value = { workId: pending.target.id, key: pending.key }
  actionStatus.value = null
  modalActionError.value = null

  const options: { method: 'PATCH'; body?: Record<string, string | number> } = { method: 'PATCH' }
  if (pending.kind === 'reviewer') {
    options.body = { reviewer_id: Number(reviewerIdInput.value) }
  } else if (pending.kind === 'changes') {
    options.body = { change_request_notes: changeRequestNotesInput.value.trim() }
  } else if (pending.kind === 'reject') {
    options.body = { rejection_reason: rejectionReasonInput.value.trim() }
  }

  try {
    const response = await apiFetch<ReviewActionResponse>(
      '/admin/works/' + pending.target.id + '/review/' + pending.endpoint,
      options
    )
    const safePublicationPolicy = toSafePublicationPolicy(response.data?.publication_policy)
    const safeWork = toSafeActionWork(response.data?.work)

    if (
      !response.success
      || !response.data
      || response.data.action !== pending.key
      || typeof response.data.changed !== 'boolean'
      || typeof response.data.auto_published !== 'boolean'
      || (response.data.auto_published && pending.key !== 'approve')
      || !safePublicationPolicy
      || !safeWork
      || safeWork.id !== pending.target.id
    ) {
      modalActionError.value = copy.value.actionResponseInvalid
      actionStatus.value = {
        kind: 'error',
        message: copy.value.actionResponseInvalid,
        changed: null,
        actionLabel: pending.label,
        workLabel: pending.target.title
      }
      return
    }

    publicationPolicy.value = safePublicationPolicy
    updateQueueItemFromAction(safeWork)
    updateOpenDetailFromAction(safeWork)
    lastReviewAction.value = {
      action: pending.key,
      changed: response.data.changed === true,
      work: {
        id: safeWork.id,
        title: safeWork.title,
        slug: safeWork.slug,
        status: safeWork.status,
        visibility_status: safeWork.visibility_status,
        reviewer: safeWork.reviewer
          ? { id: safeWork.reviewer.id, name: safeWork.reviewer.name }
          : null,
        review_flags: {
          assigned: safeWork.review_flags.assigned,
          in_queue: safeWork.review_flags.in_queue,
          decision_made: safeWork.review_flags.decision_made,
          is_published: safeWork.review_flags.is_published,
          has_reports: safeWork.review_flags.has_reports,
          needs_attention: safeWork.review_flags.needs_attention
        }
      }
    }
    actionStatus.value = {
      kind: 'success',
      message: response.data.auto_published
        ? copy.value.approveAndPublishSucceeded
        : (response.data.changed ? copy.value.actionSucceeded : copy.value.actionUnchanged),
      changed: response.data.changed === true,
      actionLabel: pending.label,
      workLabel: safeWork.title
    }

    pendingReviewAction.value = null
    clearActionInputs()

    if (drawerOpen.value && selectedWorkId.value === safeWork.id && canViewDetails.value) {
      void fetchWorkDetails(safeWork.id)
    }
    void fetchReviewQueue(true)
  } catch (requestError: unknown) {
    const status = errorStatus(requestError)
    let message = copy.value.actionFailed

    if (status === 422) {
      applyServerFieldErrors(requestError)
      message = actionFieldErrors.reviewer_id
        || actionFieldErrors.change_request_notes
        || actionFieldErrors.rejection_reason
        || serverActionMessage(requestError)
        || copy.value.actionFailed
      modalActionError.value = message
    } else if (status === 401 || status === 403) {
      message = copy.value.actionDenied
      pendingReviewAction.value = null
      clearActionInputs()
    } else if (status === 404) {
      message = copy.value.actionNotFound
      items.value = items.value.filter((item) => item.id !== pending.target.id)
      if (lastReviewAction.value?.work.id === pending.target.id) lastReviewAction.value = null
      if (drawerOpen.value && selectedWorkId.value === pending.target.id) closeDetails()
      pendingReviewAction.value = null
      clearActionInputs()
      void fetchReviewQueue(true)
    } else {
      modalActionError.value = message
    }

    actionStatus.value = {
      kind: 'error',
      message,
      changed: null,
      actionLabel: pending.label,
      workLabel: pending.target.title
    }
  } finally {
    actionLoading.value = null
  }
}

function handleActionEscape(event: KeyboardEvent): void {
  if (event.key === 'Escape' && pendingReviewAction.value && !actionLoading.value) {
    cancelReviewAction()
  }
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
    ['from', appliedFilters.from],
    ['to', appliedFilters.to]
  ]

  if (reviewPolicy.value.enabled && appliedFilters.overdue !== '') {
    query.overdue = appliedFilters.overdue
  }

  for (const [key, value] of optionalFilters) {
    if (value !== '') query[key] = value
  }

  return query
}

async function fetchReviewQueue(silent = false): Promise<void> {
  if (!authStore.isInitialized || !hasReviewAccess.value) return

  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++listRequestRevision
  if (!silent) {
    loading.value = true
    error.value = null
  }

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
      if (!silent) {
        items.value = []
        Object.assign(summary, emptySummary())
        error.value = copy.value.genericError
      }
      return
    }

    const overdueWasApplied = appliedFilters.overdue !== ''
    reviewPolicy.value = response.data.review_policy
    const safePublicationPolicy = toSafePublicationPolicy(response.data.publication_policy)
    if (!safePublicationPolicy) {
      if (!silent) {
        items.value = []
        Object.assign(summary, emptySummary())
        error.value = copy.value.genericError
      }
      return
    }
    publicationPolicy.value = safePublicationPolicy
    if (!response.data.review_policy.enabled) {
      filters.overdue = ''
      appliedFilters.overdue = ''
      if (overdueWasApplied) {
        page.value = 1
        void fetchReviewQueue(silent)
        return
      }
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
  reviewPolicy.value = disabledReviewPolicy()
  publicationPolicy.value = disabledPublicationPolicy()
  filters.overdue = ''
  appliedFilters.overdue = ''
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
  pendingReviewAction.value = null
  lastReviewAction.value = null
  clearActionInputs()
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
  window.addEventListener('keydown', handleActionEscape)
  syncReviewAccessState()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleActionEscape)
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

.ym-works-review-policy {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px solid rgba(56, 189, 248, 0.34);
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), transparent), var(--ym-card-bg);
  padding: 1rem 1.1rem;
}

.ym-works-review-policy > span {
  flex: 0 0 auto;
  border-radius: 999px;
  background: rgba(56, 189, 248, 0.14);
  color: #38bdf8;
  font-size: 10px;
  font-weight: 950;
  padding: 0.38rem 0.65rem;
}

.ym-works-review-policy strong {
  display: block;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-works-review-policy p {
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 800;
  line-height: 1.65;
  margin: 0.2rem 0 0;
}

.ym-works-review-policy.is-disabled {
  border-color: rgba(148, 163, 184, 0.3);
  background: var(--ym-card-bg);
}

.ym-works-review-policy.is-disabled > span {
  background: rgba(148, 163, 184, 0.12);
  color: #94a3b8;
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

.ym-works-review-filter-grid label.is-disabled select {
  cursor: not-allowed;
  opacity: 0.55;
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

.ym-works-review-followup-card {
  border: 1px solid color-mix(in srgb, #8b5cf6 38%, var(--ym-card-border));
  border-radius: 24px;
  background:
    linear-gradient(135deg, rgba(139, 92, 246, 0.12), transparent 54%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1rem;
}

.ym-works-review-followup-card > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.ym-works-review-followup-card > header span,
.ym-works-review-followup-card > header code {
  display: block;
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-review-followup-card > header h2 {
  color: var(--ym-text);
  font-size: 1.15rem;
  font-weight: 950;
  margin: 0.25rem 0;
}

.ym-works-review-followup-card__close {
  display: grid;
  flex: 0 0 auto;
  width: 38px;
  height: 38px;
  place-items: center;
  border: 1px solid var(--ym-control-border);
  border-radius: 12px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 1.25rem;
}

.ym-works-review-followup-card__close:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.ym-works-review-followup-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.55rem;
  margin-top: 0.85rem;
}

.ym-works-review-followup-grid > span {
  display: grid;
  gap: 0.2rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
  padding: 0.65rem;
}

.ym-works-review-followup-grid strong {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
}

.ym-works-review-followup-flags,
.ym-works-review-followup-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.45rem;
  margin-top: 0.7rem;
}

.ym-works-review-followup-flags span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
  padding: 0.35rem 0.58rem;
}

.ym-works-review-action-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  margin: 0 1.2rem 1rem;
  padding: 0.75rem 0.9rem;
}

.ym-works-review-action-status.is-success {
  border-color: rgba(16, 185, 129, 0.35);
  background: rgba(16, 185, 129, 0.09);
}

.ym-works-review-action-status.is-error {
  border-color: rgba(244, 63, 94, 0.36);
  background: rgba(244, 63, 94, 0.09);
}

.ym-works-review-action-status div {
  display: grid;
  gap: 0.18rem;
}

.ym-works-review-action-status strong {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
}

.ym-works-review-action-status span {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-review-action-status__changed {
  flex: 0 0 auto;
  border-radius: 999px;
  background: var(--ym-control-bg);
  padding: 0.38rem 0.62rem;
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
  min-width: 2460px;
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

.ym-works-review-table th.is-review-actions,
.ym-works-review-table td.is-review-actions {
  position: sticky;
  inset-inline-end: 130px;
  z-index: 1;
  width: 360px;
  min-width: 360px;
  background: var(--ym-dropdown-bg);
}

.ym-works-review-table th.is-review-actions {
  z-index: 3;
}

.ym-works-review-action-group {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.4rem;
}

.ym-works-review-action-button {
  display: inline-flex;
  min-height: 34px;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 10px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 10px;
  font-weight: 950;
  padding: 0.42rem 0.52rem;
  transition: background 150ms ease, border-color 150ms ease, transform 150ms ease;
  white-space: nowrap;
}

.ym-works-review-action-button.is-primary {
  border-color: rgba(99, 102, 241, 0.42);
  background: rgba(99, 102, 241, 0.12);
  color: #a5b4fc;
}

.ym-works-review-action-button.is-info {
  border-color: rgba(56, 189, 248, 0.4);
  background: rgba(56, 189, 248, 0.11);
  color: #7dd3fc;
}

.ym-works-review-action-button.is-positive {
  border-color: rgba(16, 185, 129, 0.4);
  background: rgba(16, 185, 129, 0.11);
  color: #34d399;
}

.ym-works-review-action-button.is-warning {
  border-color: rgba(245, 158, 11, 0.4);
  background: rgba(245, 158, 11, 0.11);
  color: #fbbf24;
}

.ym-works-review-action-button.is-danger {
  border-color: rgba(244, 63, 94, 0.42);
  background: rgba(244, 63, 94, 0.11);
  color: #fb7185;
}

.ym-works-review-action-button.is-promotion {
  border-color: rgba(16, 185, 129, 0.48);
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(139, 92, 246, 0.12));
  color: #6ee7b7;
}

.ym-works-review-action-button.is-neutral {
  border-color: rgba(139, 92, 246, 0.4);
  background: rgba(139, 92, 246, 0.11);
  color: #c4b5fd;
}

.ym-works-review-action-button:hover:not(:disabled) {
  filter: brightness(1.15);
  transform: translateY(-1px);
}

.ym-works-review-action-button:disabled {
  cursor: not-allowed;
  filter: grayscale(0.65);
  opacity: 0.42;
}

.ym-works-review-action-spinner {
  display: inline-block;
  flex: 0 0 auto;
  width: 0.85rem;
  height: 0.85rem;
  border: 2px solid currentColor;
  border-inline-end-color: transparent;
  border-radius: 999px;
  animation: ym-works-review-spin 760ms linear infinite;
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

.ym-review-action-backdrop {
  position: fixed;
  inset: 0;
  z-index: 140;
  display: grid;
  place-items: center;
  background: rgba(2, 6, 23, 0.72);
  backdrop-filter: blur(7px);
  padding: 1rem;
}

.ym-review-action-dialog {
  width: min(560px, 100%);
  max-height: calc(100dvh - 2rem);
  overflow-y: auto;
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background: var(--ym-dropdown-bg);
  box-shadow: 0 28px 80px rgba(2, 6, 23, 0.48);
  color: var(--ym-text);
  padding: 1.35rem;
}

.ym-review-action-dialog__eyebrow {
  color: #a78bfa;
  font-size: 11px;
  font-weight: 950;
}

.ym-review-action-dialog h2 {
  font-size: 1.3rem;
  font-weight: 950;
  margin: 0.35rem 0 0;
}

.ym-review-action-dialog > p:not(.ym-review-action-dialog__error) {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.75;
  margin: 0.65rem 0 0;
}

.ym-review-action-dialog__work {
  display: grid;
  gap: 0.22rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: var(--ym-control-bg);
  margin-top: 1rem;
  padding: 0.85rem;
}

.ym-review-action-dialog__work span,
.ym-review-action-dialog__work code {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-review-action-dialog__work strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-review-action-dialog__field {
  display: grid;
  gap: 0.38rem;
  margin-top: 1rem;
}

.ym-review-action-dialog__field > span {
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
}

.ym-review-action-dialog__field input,
.ym-review-action-dialog__field textarea {
  width: 100%;
  border: 1px solid var(--ym-control-border);
  border-radius: 13px;
  outline: none;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 13px;
  padding: 0.72rem 0.78rem;
}

.ym-review-action-dialog__field textarea {
  min-height: 150px;
  line-height: 1.7;
  resize: vertical;
}

.ym-review-action-dialog__field input:focus,
.ym-review-action-dialog__field textarea:focus,
.ym-review-action-dialog button:focus-visible {
  border-color: #818cf8;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.18);
}

.ym-review-action-dialog__field small {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 800;
}

.ym-review-action-dialog__field > strong,
.ym-review-action-dialog__error {
  color: #fb7185;
  font-size: 11px;
  font-weight: 900;
}

.ym-review-action-dialog__error {
  border: 1px solid rgba(244, 63, 94, 0.34);
  border-radius: 13px;
  background: rgba(244, 63, 94, 0.09);
  margin: 0.8rem 0 0;
  padding: 0.65rem 0.75rem;
}

.ym-review-action-dialog__buttons {
  display: flex;
  justify-content: flex-end;
  gap: 0.65rem;
  margin-top: 1rem;
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
  .ym-works-review-filter-grid,
  .ym-works-review-followup-grid {
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

  .ym-works-review-notice,
  .ym-works-review-policy {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-works-review-action-status,
  .ym-review-action-dialog__buttons {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-review-action-dialog__buttons .ym-works-review-button {
    width: 100%;
  }

  .ym-works-review-summary-grid,
  .ym-works-review-filter-grid,
  .ym-works-review-followup-grid,
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
  .ym-works-review-action-button,
  .ym-works-review-table tbody tr {
    transition: none;
  }
}
</style>
