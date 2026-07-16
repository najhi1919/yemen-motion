<template>
  <div class="ym-works-reports-page space-y-7" :dir="pageDirection">
    <section class="ym-works-reports-hero">
      <div class="ym-works-reports-hero__glow is-one" />
      <div class="ym-works-reports-hero__glow is-two" />
      <div class="ym-works-reports-hero__grid" aria-hidden="true" />
      <div class="ym-works-reports-hero__content">
        <div>
          <div class="ym-works-reports-chips">
            <span class="ym-works-reports-chip is-brand">Yemen Motion</span>
            <span class="ym-works-reports-chip is-readonly">{{ copy.trackingTag }}</span>
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

    <section v-if="authPending" class="ym-works-reports-access-state" role="status" aria-live="polite">
      <span class="ym-works-reports-spinner" aria-hidden="true" />
      <h2>{{ copy.authLoadingTitle }}</h2>
      <p>{{ copy.authLoadingCopy }}</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-reports-access-state is-forbidden" role="status">
      <span class="ym-works-reports-state__icon" aria-hidden="true">!</span>
      <h2>{{ copy.forbiddenTitle }}</h2>
      <p>{{ copy.forbiddenCopy }}</p>
    </section>

    <template v-else>
      <aside class="ym-works-reports-notice" role="note">
        <span>{{ copy.important }}</span>
        <div>
          <strong>{{ copy.sourcesNoticeTitle }}</strong>
          <p>{{ copy.sourcesNotice }}</p>
        </div>
      </aside>

      <section class="ym-works-reports-summary-grid" :aria-label="copy.summaryLabel">
        <article v-for="card in primarySummaryCards" :key="card.key" class="ym-works-reports-summary-card" :style="{ '--reports-accent': card.color }">
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-reports-secondary-summary" :aria-label="copy.secondarySummaryLabel">
        <span v-for="item in secondarySummaryItems" :key="item.key">
          {{ item.label }} <strong>{{ formatNumber(item.value) }}</strong>
        </span>
      </section>

      <section class="ym-reports-secondary-summary is-legacy-summary" :aria-label="copy.reportedSummary">
        <span v-for="item in legacySummaryItems" :key="item.key">{{ item.label }} <strong>{{ formatNumber(item.value) }}</strong></span>
      </section>

      <section v-if="actionStatus" class="ym-reports-action-status" :class="'is-' + actionStatus.kind" role="status" aria-live="polite">
        <div>
          <strong>{{ actionStatus.message }}</strong>
          <span>{{ actionStatus.actionLabel }} · {{ copy.reportWord }} #{{ actionStatus.reportId }} · {{ actionStatus.workTitle }}</span>
        </div>
        <small v-if="actionStatus.changed !== null">
          {{ actionStatus.changed ? copy.actionChanged : copy.actionUnchanged }}
        </small>
        <button type="button" :aria-label="copy.hideActionStatus" :title="copy.hideActionStatus" @click="actionStatus = null">×</button>
      </section>

      <section class="ym-works-reports-filter-card">
        <header>
          <div><h2>{{ copy.filtersTitle }}</h2><p>{{ copy.filtersCopy }}</p></div>
          <button type="button" class="ym-works-reports-button is-secondary" :disabled="loading" @click="resetFilters">{{ copy.reset }}</button>
        </header>
        <form class="ym-works-reports-filter-grid" @submit.prevent="applyFilters">
          <label class="is-search"><span>{{ copy.search }}</span><input v-model.trim="filters.q" type="search" minlength="2" maxlength="80" :placeholder="copy.searchPlaceholder" autocomplete="off" /></label>
          <label><span>{{ copy.workStatus }}</span><select v-model="filters.status"><option value="">{{ copy.all }}</option><option v-for="status in workStatuses" :key="status" :value="status">{{ workStatusLabel(status) }}</option></select></label>
          <label><span>{{ copy.visibility }}</span><select v-model="filters.visibility_status"><option value="">{{ copy.all }}</option><option value="public">{{ copy.public }}</option><option value="hidden">{{ copy.hidden }}</option></select></label>
          <label><span>{{ copy.mediaType }}</span><input v-model.trim="filters.media_type" type="text" maxlength="40" dir="ltr" /></label>
          <label><span>{{ copy.designerId }}</span><input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ copy.reviewerId }}</span><input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ copy.categoryId }}</span><input v-model="filters.category_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>{{ copy.source }}</span><select v-model="filters.report_source"><option value="all">{{ copy.allSources }}</option><option value="legacy">{{ copy.legacy }}</option><option value="tracked">{{ copy.tracked }}</option><option value="both">{{ copy.bothSources }}</option></select></label>
          <label><span>{{ copy.trackedStatus }}</span><select v-model="filters.tracked_status"><option value="">{{ copy.allStatuses }}</option><option v-for="status in reportStatuses" :key="status" :value="status">{{ reportStatusLabel(status) }}</option></select></label>
          <label><span>{{ copy.featured }}</span><select v-model="filters.is_featured"><option value="">{{ copy.all }}</option><option value="1">{{ copy.yes }}</option><option value="0">{{ copy.no }}</option></select></label>
          <label><span>{{ copy.pinned }}</span><select v-model="filters.is_pinned"><option value="">{{ copy.all }}</option><option value="1">{{ copy.yes }}</option><option value="0">{{ copy.no }}</option></select></label>
          <label><span>{{ copy.minimumSignal }}</span><input v-model="filters.min_reports" type="number" min="1" max="100000" inputmode="numeric" /></label>
          <label><span>{{ copy.updatedFrom }}</span><input v-model="filters.from" type="date" /></label>
          <label><span>{{ copy.updatedTo }}</span><input v-model="filters.to" type="date" /></label>
          <label><span>{{ copy.sort }}</span><select v-model="filters.sort"><option v-for="option in workSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label><span>{{ copy.direction }}</span><select v-model="filters.direction"><option value="desc">{{ copy.descending }}</option><option value="asc">{{ copy.ascending }}</option></select></label>
          <label><span>{{ copy.perPage }}</span><select v-model.number="filters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <div class="ym-works-reports-filter-actions"><button type="submit" class="ym-works-reports-button is-primary" :disabled="loading">{{ copy.applyFilters }}</button></div>
        </form>
        <p v-if="filterError" class="ym-works-reports-filter-error" role="alert">{{ filterError }}</p>
      </section>

      <section class="ym-works-reports-table-card">
        <header class="ym-works-reports-table-card__head">
          <div><h2>{{ copy.tableTitle }}</h2><p>{{ copy.tableCopy }}</p></div>
          <div class="ym-works-reports-table-state"><span>{{ copy.currentPage }}</span><strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong></div>
        </header>

        <div v-if="loading" class="ym-works-reports-state" role="status"><span class="ym-works-reports-spinner" /><h3>{{ copy.loadingWorks }}</h3></div>
        <div v-else-if="error" class="ym-works-reports-state is-error" role="alert"><h3>{{ copy.loadWorksError }}</h3><p>{{ error }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="fetchWorks(false)">{{ copy.retry }}</button></div>
        <div v-else-if="items.length === 0" class="ym-works-reports-state"><h3>{{ copy.emptyWorks }}</h3><p>{{ copy.emptyWorksCopy }}</p></div>
        <div v-else class="ym-works-reports-table-wrap">
          <table class="ym-works-reports-table">
            <thead><tr>
              <th><button type="button" class="ym-works-reports-sort" @click="changeWorkSort('title')">{{ copy.work }} <span>{{ sortIndicator('title') }}</span></button></th>
              <th><button type="button" class="ym-works-reports-sort" @click="changeWorkSort('status')">{{ copy.status }} <span>{{ sortIndicator('status') }}</span></button></th>
              <th>{{ copy.administrativeData }}</th>
              <th>{{ copy.sources }}</th>
              <th>{{ copy.trackedStates }}</th>
              <th>{{ copy.indicators }}</th>
              <th><span>{{ copy.datesAndCounts }}</span><div class="ym-table-sort-group"><button type="button" @click="changeWorkSort('views_count')">{{ copy.views }} {{ sortIndicator('views_count') }}</button><button type="button" @click="changeWorkSort('likes_count')">{{ copy.likes }} {{ sortIndicator('likes_count') }}</button><button type="button" @click="changeWorkSort('submitted_at')">{{ copy.submittedAt }} {{ sortIndicator('submitted_at') }}</button><button type="button" @click="changeWorkSort('published_at')">{{ copy.publishedAt }} {{ sortIndicator('published_at') }}</button><button type="button" @click="changeWorkSort('created_at')">{{ copy.createdAt }} {{ sortIndicator('created_at') }}</button><button type="button" @click="changeWorkSort('updated_at')">{{ copy.updatedAt }} {{ sortIndicator('updated_at') }}</button></div></th>
              <th>{{ copy.actions }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="work in items" :key="work.id" :class="{ 'needs-attention-row': work.report_flags.needs_attention }">
                <td class="is-title"><strong :dir="textDirection(work.title)">{{ work.title }}</strong><code dir="ltr">{{ work.slug }} · #{{ work.id }}</code><small v-if="work.summary" :title="work.summary" :dir="textDirection(work.summary)">{{ truncateText(work.summary, 74) }}</small><small>{{ copy.legacyCompatibility }}: {{ formatNumber(work.reports_count) }}</small></td>
                <td><span class="ym-works-reports-badge is-status" :class="'is-' + work.status.replaceAll('_', '-')">{{ workStatusLabel(work.status) }}</span><small>{{ work.visibility_status === 'public' ? copy.public : copy.hidden }}</small></td>
                <td><div class="ym-work-admin-data"><span>{{ copy.mediaType }} <code dir="ltr">{{ displayValue(work.media_type) }}</code></span><span>{{ copy.designer }} <strong>{{ personLabel(work.designer) }}</strong></span><span>{{ copy.reviewer }} <strong>{{ personLabel(work.reviewer) }}</strong></span><span>{{ copy.category }} <code dir="ltr">{{ work.category_id ?? '—' }}</code></span><span>{{ copy.featured }} <strong>{{ yesNo(work.is_featured) }}</strong></span><span>{{ copy.pinned }} <strong>{{ yesNo(work.is_pinned) }}</strong></span></div></td>
                <td><div class="ym-report-counts"><button type="button" class="is-legacy" @click="changeWorkSort('reports_count')">{{ copy.historical }} <strong>{{ formatNumber(work.report_tracking.legacy_count) }}</strong> {{ sortIndicator('reports_count') }}</button><button type="button" class="is-tracked" @click="changeWorkSort('tracked_reports_count')">{{ copy.tracked }} <strong>{{ formatNumber(work.report_tracking.tracked_count) }}</strong> {{ sortIndicator('tracked_reports_count') }}</button><button type="button" class="is-open" @click="changeWorkSort('open_tracked_reports_count')">{{ copy.open }} <strong>{{ formatNumber(work.report_tracking.open_count) }}</strong> {{ sortIndicator('open_tracked_reports_count') }}</button><button type="button" class="is-combined" @click="changeWorkSort('combined_reports_count')">{{ copy.combinedSignal }} <strong>{{ formatNumber(work.report_tracking.combined_signal_count) }}</strong> {{ sortIndicator('combined_reports_count') }}</button></div></td>
                <td><div class="ym-report-status-counts"><span>{{ copy.waiting }} {{ formatNumber(work.report_tracking.pending_count) }}</span><span>{{ copy.review }} {{ formatNumber(work.report_tracking.under_review_count) }}</span><span>{{ copy.closed }} {{ formatNumber(work.report_tracking.dismissed_count) }}</span><span>{{ copy.archived }} {{ formatNumber(work.report_tracking.archived_count) }}</span></div></td>
                <td><div class="ym-report-flags"><span v-if="work.report_flags.has_reports">{{ copy.reportedSummary }}</span><span v-if="work.report_tracking.has_legacy_untracked">{{ copy.legacyCounter }}</span><span v-if="work.report_tracking.has_tracked">{{ copy.trackedRecords }}</span><span v-if="work.report_tracking.has_open_tracked" class="is-alert">{{ copy.needsFollowup }}</span><span v-if="work.report_flags.visibility_risk" class="is-alert">{{ copy.visiblePublicly }}</span><span v-if="work.report_flags.high_reports" class="is-alert">{{ copy.highSignal }}</span><span v-if="work.report_flags.needs_attention" class="is-alert">{{ copy.needsAttention }}</span></div></td>
                <td><div class="ym-work-dates-counts"><span>{{ copy.views }} <strong>{{ formatNumber(work.views_count) }}</strong></span><span>{{ copy.likes }} <strong>{{ formatNumber(work.likes_count) }}</strong></span><time :datetime="work.submitted_at || undefined">{{ copy.submittedAt }}: {{ formatDateTime(work.submitted_at) }}</time><time :datetime="work.published_at || undefined">{{ copy.publishedAt }}: {{ formatDateTime(work.published_at) }}</time><time :datetime="work.hidden_at || undefined">{{ copy.hiddenAt }}: {{ formatDateTime(work.hidden_at) }}</time><time :datetime="work.created_at || undefined">{{ copy.createdAt }}: {{ formatDateTime(work.created_at) }}</time><time :datetime="work.updated_at || undefined">{{ copy.updatedAt }}: {{ formatDateTime(work.updated_at) }}</time></div></td>
                <td class="is-action">
                  <button type="button" class="ym-works-reports-button is-secondary" :disabled="!canViewWorkDetails" :title="canViewWorkDetails ? copy.viewWorkDetails : copy.workDetailsPermission" @click="openWorkDetails(work)">{{ copy.viewWorkDetails }}</button>
                  <button type="button" class="ym-works-reports-details-button" :disabled="work.report_tracking.tracked_count <= 0" :title="trackedButtonTitle(work)" @click="openReports(work)">{{ copy.viewTrackedReports }}</button>
                  <small v-if="work.report_tracking.tracked_count <= 0">{{ copy.noTrackedRecords }}</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="ym-works-reports-pagination">
          <div><span>{{ copy.totalResults }}</span><strong>{{ formatNumber(pagination.total) }}</strong><small>{{ formatNumber(items.length) }} {{ copy.visibleNow }}</small></div>
          <nav :aria-label="copy.workPages"><button type="button" class="ym-works-reports-button is-secondary" :disabled="loading || pagination.current_page <= 1" @click="changeWorkPage(pagination.current_page - 1)">{{ copy.previous }}</button><span>{{ copy.page }} {{ formatNumber(pagination.current_page) }}</span><button type="button" class="ym-works-reports-button is-secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changeWorkPage(pagination.current_page + 1)">{{ copy.next }}</button></nav>
        </footer>
      </section>
    </template>

    <div v-if="workDetailsOpen" class="ym-reports-detail-backdrop is-work-details" role="presentation" @click.self="closeWorkDetails">
      <section class="ym-reports-detail-drawer is-work" role="dialog" aria-modal="true" aria-labelledby="work-details-title">
        <header class="ym-reports-detail-drawer__head">
          <div><span>{{ copy.workDetailsReadonly }}</span><h2 id="work-details-title" :dir="textDirection(selectedWorkDetailsTitle)">{{ selectedWorkDetailsTitle }}</h2><code v-if="selectedWorkDetailsId !== null" dir="ltr">#{{ selectedWorkDetailsId }}</code></div>
          <button type="button" class="ym-reports-detail-drawer__close" :title="copy.closeWorkDetails" :aria-label="copy.closeWorkDetails" :disabled="actionLoading" @click="closeWorkDetails">×</button>
        </header>
        <div v-if="workDetailLoading" class="ym-reports-detail-state" role="status"><span class="ym-works-reports-spinner" /><h3>{{ copy.loadingWorkDetails }}</h3><p>{{ copy.loadingAllowedFields }}</p></div>
        <div v-else-if="workDetailError" class="ym-reports-detail-state is-error" role="alert"><h3>{{ copy.workDetailsError }}</h3><p>{{ workDetailError }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="reloadWorkDetails">{{ copy.retry }}</button></div>
        <div v-else-if="workDetail" class="ym-reports-detail-content">
          <section class="ym-reports-detail-intro"><div><span class="ym-works-reports-badge is-status">{{ workStatusLabel(workDetail.work.status) }}</span><span class="ym-works-reports-badge">{{ workDetail.work.visibility_status === 'public' ? copy.public : copy.hidden }}</span></div><h3 :dir="textDirection(workDetail.work.title)">{{ workDetail.work.title }}</h3><code dir="ltr">{{ workDetail.work.slug }}</code><p v-if="workDetail.work.summary" :dir="textDirection(workDetail.work.summary)">{{ workDetail.work.summary }}</p><p v-else>{{ copy.noSummary }}</p></section>
          <section class="ym-reports-detail-section"><header><h3>{{ copy.accessScope }}</h3><p>{{ copy.accessScopeCopy }}</p></header><div class="ym-reports-detail-access-grid"><span :class="workDetail.field_access.can_view_designer ? 'is-allowed' : 'is-denied'">{{ copy.canViewDesigner }} <strong>{{ accessLabel(workDetail.field_access.can_view_designer) }}</strong></span><span :class="workDetail.field_access.can_view_media ? 'is-allowed' : 'is-denied'">{{ copy.canViewMedia }} <strong>{{ accessLabel(workDetail.field_access.can_view_media) }}</strong></span><span :class="workDetail.field_access.can_view_metadata ? 'is-allowed' : 'is-denied'">{{ copy.canViewMetadata }} <strong>{{ accessLabel(workDetail.field_access.can_view_metadata) }}</strong></span><span :class="workDetail.field_access.can_view_private_notes ? 'is-allowed' : 'is-denied'">{{ copy.canViewPrivateNotes }} <strong>{{ accessLabel(workDetail.field_access.can_view_private_notes) }}</strong></span></div></section>
          <section class="ym-reports-detail-section"><header><h3>{{ copy.basicDetails }}</h3></header><dl class="ym-reports-detail-grid"><div><dt>{{ copy.priceAmount }}</dt><dd dir="ltr">{{ displayValue(workDetail.work.price_amount) }}</dd></div><div><dt>{{ copy.deliveryDays }}</dt><dd>{{ workDetail.work.delivery_days === null ? '—' : formatNumber(workDetail.work.delivery_days) }}</dd></div><div><dt>{{ copy.categoryId }}</dt><dd dir="ltr">{{ workDetail.work.category_id ?? '—' }}</dd></div><div><dt>{{ copy.mediaType }}</dt><dd dir="ltr">{{ displayValue(workDetail.work.media_type) }}</dd></div><div><dt>{{ copy.featured }}</dt><dd>{{ yesNo(workDetail.work.is_featured) }}</dd></div><div><dt>{{ copy.pinned }}</dt><dd>{{ yesNo(workDetail.work.is_pinned) }}</dd></div><div><dt>{{ copy.reportsCount }}</dt><dd>{{ formatNumber(workDetail.work.reports_count) }}</dd></div><div><dt>{{ copy.views }}</dt><dd>{{ formatNumber(workDetail.work.views_count) }}</dd></div><div><dt>{{ copy.likes }}</dt><dd>{{ formatNumber(workDetail.work.likes_count) }}</dd></div></dl></section>
          <section class="ym-reports-detail-section"><header><h3>{{ copy.people }}</h3></header><div v-if="workDetail.field_access.can_view_designer" class="ym-reports-detail-people"><article><span>{{ copy.designer }}</span><strong v-if="workDetail.relations.designer" :dir="textDirection(workDetail.relations.designer.name)">{{ workDetail.relations.designer.name }}</strong><small v-if="workDetail.relations.designer" dir="ltr">#{{ workDetail.relations.designer.id }}</small><strong v-else>{{ copy.notLinked }}</strong></article><article><span>{{ copy.reviewer }}</span><strong v-if="workDetail.relations.reviewer" :dir="textDirection(workDetail.relations.reviewer.name)">{{ workDetail.relations.reviewer.name }}</strong><small v-if="workDetail.relations.reviewer" dir="ltr">#{{ workDetail.relations.reviewer.id }}</small><strong v-else>{{ copy.notLinked }}</strong></article></div><p v-else class="ym-reports-detail-unavailable">{{ copy.relationsUnavailable }}</p></section>
          <section class="ym-reports-detail-section"><header><h3>{{ copy.media }}</h3></header><div v-if="workDetail.media && workDetail.field_access.can_view_media" class="ym-reports-detail-media"><span>{{ copy.mediaType }}: <code dir="ltr">{{ displayValue(workDetail.media.media_type) }}</code></span><strong :class="workDetail.media.has_media ? 'is-present' : 'is-absent'">{{ workDetail.media.has_media ? copy.mediaPresent : copy.mediaAbsent }}</strong></div><p v-else class="ym-reports-detail-unavailable">{{ copy.mediaUnavailable }}</p></section>
          <section class="ym-reports-detail-section"><header><h3>{{ copy.lifecycle }}</h3></header><dl class="ym-reports-detail-grid is-lifecycle"><div v-for="item in workLifecycleItems" :key="item.key"><dt>{{ item.label }}</dt><dd><time :datetime="item.value || undefined">{{ formatDateTime(item.value) }}</time></dd></div></dl></section>
          <section class="ym-reports-detail-section is-private"><header><h3>{{ copy.privateNotes }}</h3><p>{{ copy.privateNotesCopy }}</p></header><dl v-if="workDetail.private_notes && workDetail.field_access.can_view_private_notes" class="ym-reports-detail-notes"><div><dt>{{ copy.internalNotes }}</dt><dd :dir="textDirection(workDetail.private_notes.internal_notes)">{{ displayValue(workDetail.private_notes.internal_notes) }}</dd></div><div><dt>{{ copy.rejectionReason }}</dt><dd :dir="textDirection(workDetail.private_notes.rejection_reason)">{{ displayValue(workDetail.private_notes.rejection_reason) }}</dd></div><div><dt>{{ copy.changeRequestNotes }}</dt><dd :dir="textDirection(workDetail.private_notes.change_request_notes)">{{ displayValue(workDetail.private_notes.change_request_notes) }}</dd></div></dl><p v-else class="ym-reports-detail-unavailable">{{ copy.privateNotesUnavailable }}</p></section>
        </div>
      </section>
    </div>

    <div v-if="reportsDrawerOpen" class="ym-reports-detail-backdrop" role="presentation" @click.self="closeReportsDrawer">
      <section class="ym-reports-detail-drawer is-tracked" role="dialog" aria-modal="true" aria-labelledby="tracked-reports-title">
        <header class="ym-reports-detail-drawer__head">
          <div><span>{{ copy.trackedDrawerTitle }}</span><h2 id="tracked-reports-title" :dir="textDirection(selectedWork?.title)">{{ selectedWork?.title }}</h2><code dir="ltr">{{ selectedWork?.slug }} · #{{ selectedWork?.id }}</code></div>
          <button type="button" class="ym-reports-detail-drawer__close" :title="copy.closePanel" :aria-label="copy.closePanel" :disabled="actionLoading" @click="closeReportsDrawer">×</button>
        </header>

        <div v-if="selectedWork" class="ym-tracked-work-context">
          <span>{{ copy.legacyCounter }} <strong>{{ formatNumber(selectedWork.report_tracking.legacy_count) }}</strong></span>
          <span>{{ copy.trackedRecords }} <strong>{{ formatNumber(selectedWork.report_tracking.tracked_count) }}</strong></span>
          <p v-if="selectedWork.report_tracking.tracked_count <= 0">{{ copy.noTrackedRecords }}</p>
        </div>

        <form class="ym-tracked-filters" @submit.prevent="applyReportFilters">
          <label><span>{{ copy.status }}</span><select v-model="reportFilters.status"><option value="">{{ copy.all }}</option><option v-for="status in reportStatuses" :key="status" :value="status">{{ reportStatusLabel(status) }}</option></select></label>
          <label><span>{{ copy.reasonCode }}</span><input v-model.trim="reportFilters.reason_code" type="text" maxlength="50" dir="ltr" autocomplete="off" /></label>
          <label><span>{{ copy.reporterId }}</span><input v-model="reportFilters.reporter_id" type="number" min="1" /></label>
          <label><span>{{ copy.reviewerId }}</span><input v-model="reportFilters.reviewed_by" type="number" min="1" /></label>
          <label><span>{{ copy.from }}</span><input v-model="reportFilters.from" type="date" /></label>
          <label><span>{{ copy.to }}</span><input v-model="reportFilters.to" type="date" /></label>
          <label><span>{{ copy.sort }}</span><select v-model="reportFilters.sort"><option v-for="option in reportSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label><span>{{ copy.direction }}</span><select v-model="reportFilters.direction"><option value="desc">{{ copy.descending }}</option><option value="asc">{{ copy.ascending }}</option></select></label>
          <label><span>{{ copy.perPage }}</span><select v-model.number="reportFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <div><button type="submit" class="ym-works-reports-button is-primary" :disabled="reportsLoading">{{ copy.applyFilters }}</button><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading" @click="resetReportFilters">{{ copy.clear }}</button></div>
        </form>
        <p v-if="reportFilterError" class="ym-works-reports-filter-error" role="alert">{{ reportFilterError }}</p>

        <div v-if="reportsLoading" class="ym-reports-detail-state" role="status"><span class="ym-works-reports-spinner" /><h3>{{ copy.loadingTracked }}</h3></div>
        <div v-else-if="reportsError" class="ym-reports-detail-state is-error" role="alert"><h3>{{ copy.loadTrackedError }}</h3><p>{{ reportsError }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="fetchTrackedReports(false)">{{ copy.retry }}</button></div>
        <div v-else-if="trackedReports.length === 0" class="ym-reports-detail-state"><h3>{{ copy.emptyTracked }}</h3><p>{{ copy.emptyTrackedCopy }}</p></div>
        <div v-else class="ym-tracked-list">
          <article v-for="report in trackedReports" :key="report.id" class="ym-tracked-report" :class="'is-' + report.status">
            <header><div><code dir="ltr">#{{ report.id }} · {{ report.reason_code }}</code><span class="ym-report-status" :class="'is-' + report.status">{{ reportStatusLabel(report.status) }}</span></div><div class="ym-report-flags"><span v-if="report.report_flags.is_open">{{ copy.open }}</span><span v-if="report.report_flags.needs_attention" class="is-alert">{{ copy.needsAttention }}</span><span v-if="report.report_flags.has_reviewer">{{ copy.hasReviewer }}</span><span v-if="report.report_flags.is_dismissed">{{ copy.closed }}</span><span v-if="report.report_flags.is_archived">{{ copy.archived }}</span></div></header>
            <dl class="ym-report-list-data">
              <div><dt>{{ copy.reporter }}</dt><dd>{{ personLabel(report.reporter) }}</dd></div><div><dt>{{ copy.reviewer }}</dt><dd>{{ personLabel(report.reviewer) }}</dd></div>
              <div><dt>{{ copy.reviewed }}</dt><dd>{{ formatDateTime(report.reviewed_at) }}</dd></div><div><dt>{{ copy.dismissedAt }}</dt><dd>{{ formatDateTime(report.dismissed_at) }}</dd></div><div><dt>{{ copy.archivedAtShort }}</dt><dd>{{ formatDateTime(report.archived_at) }}</dd></div>
              <div><dt>{{ copy.createdAtShort }}</dt><dd>{{ formatDateTime(report.created_at) }}</dd></div><div><dt>{{ copy.updatedAt }}</dt><dd>{{ formatDateTime(report.updated_at) }}</dd></div>
            </dl>
            <footer class="ym-report-actions">
              <button type="button" class="ym-works-reports-button is-secondary" :disabled="!canViewReportDetail || actionLoadingFor(report.id)" :title="canViewReportDetail ? copy.reportDetailHint : copy.reportDetailPermission" @click="openReportDetail(report)">{{ copy.viewReportDetails }}</button>
              <button v-if="canReview" type="button" class="ym-report-action is-review" :disabled="!reviewState(report).enabled || actionLoadingFor(report.id)" :title="reviewState(report).title" @click="openAction('review', report)">{{ copy.startReview }}</button>
              <button v-if="canDismiss" type="button" class="ym-report-action is-dismiss" :disabled="!dismissState(report).enabled || actionLoadingFor(report.id)" :title="dismissState(report).title" @click="openAction('dismiss', report)">{{ report.status === 'dismissed' ? copy.updateDismissal : copy.dismissReport }}</button>
              <button v-if="canArchive" type="button" class="ym-report-action is-archive" :disabled="!archiveState(report).enabled || actionLoadingFor(report.id)" :title="archiveState(report).title" @click="openAction('archive', report)">{{ copy.archiveReport }}</button>
            </footer>
          </article>
        </div>

        <footer class="ym-works-reports-pagination is-drawer"><div><span>{{ copy.totalRecords }}</span><strong>{{ formatNumber(reportPagination.total) }}</strong></div><nav :aria-label="copy.reportPages"><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading || reportPagination.current_page <= 1" @click="changeReportPage(reportPagination.current_page - 1)">{{ copy.previous }}</button><span>{{ formatNumber(reportPagination.current_page) }} / {{ formatNumber(reportPagination.last_page) }}</span><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading || reportPagination.current_page >= reportPagination.last_page" @click="changeReportPage(reportPagination.current_page + 1)">{{ copy.next }}</button></nav></footer>

        <section v-if="reportDetailOpen" class="ym-report-detail-panel" aria-labelledby="report-detail-title">
          <header><div><span>{{ copy.reportDetailsAllowed }}</span><h3 id="report-detail-title">{{ copy.reportWord }} #{{ selectedReportId }}</h3></div><button type="button" :disabled="actionLoading" :title="copy.closeReportDetails" :aria-label="copy.closeReportDetails" @click="closeReportDetail">×</button></header>
          <div v-if="detailLoading" class="ym-reports-detail-state" role="status"><span class="ym-works-reports-spinner" /></div>
          <div v-else-if="detailError" class="ym-reports-detail-state is-error" role="alert"><p>{{ detailError }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="reloadSelectedDetail">{{ copy.retry }}</button></div>
          <template v-else-if="reportDetail">
            <div class="ym-report-detail-context"><strong :dir="textDirection(reportDetail.work.title)">{{ reportDetail.work.title }}</strong><code dir="ltr">{{ reportDetail.work.slug }} · #{{ reportDetail.work.id }}</code><span>{{ workStatusLabel(reportDetail.work.status) }} · {{ reportDetail.work.visibility_status === 'public' ? copy.public : copy.hidden }}</span><span>{{ copy.legacy }} {{ formatNumber(reportDetail.work.legacy_reports_count) }} · {{ copy.tracked }} {{ formatNumber(reportDetail.work.tracked_reports_count) }}</span><span>{{ copy.featured }}: {{ yesNo(reportDetail.work.is_featured) }} · {{ copy.pinned }}: {{ yesNo(reportDetail.work.is_pinned) }}</span></div>
            <dl class="ym-report-detail-grid"><div><dt>{{ copy.reportId }}</dt><dd>#{{ reportDetail.report.id }}</dd></div><div><dt>{{ copy.reasonCode }}</dt><dd dir="ltr">{{ reportDetail.report.reason_code }}</dd></div><div><dt>{{ copy.status }}</dt><dd>{{ reportStatusLabel(reportDetail.report.status) }}</dd></div><div><dt>{{ copy.reporter }}</dt><dd>{{ personLabel(reportDetail.report.reporter) }}</dd></div><div><dt>{{ copy.reviewer }}</dt><dd>{{ personLabel(reportDetail.report.reviewer) }}</dd></div><div><dt>{{ copy.reviewedAt }}</dt><dd>{{ formatDateTime(reportDetail.report.reviewed_at) }}</dd></div><div><dt>{{ copy.dismissedAt }}</dt><dd>{{ formatDateTime(reportDetail.report.dismissed_at) }}</dd></div><div><dt>{{ copy.archivedAt }}</dt><dd>{{ formatDateTime(reportDetail.report.archived_at) }}</dd></div><div><dt>{{ copy.createdAt }}</dt><dd>{{ formatDateTime(reportDetail.report.created_at) }}</dd></div><div><dt>{{ copy.updatedAt }}</dt><dd>{{ formatDateTime(reportDetail.report.updated_at) }}</dd></div></dl>
            <div class="ym-report-flags"><span v-if="reportDetail.report.report_flags.is_open">{{ copy.open }}</span><span v-if="reportDetail.report.report_flags.needs_attention" class="is-alert">{{ copy.needsAttention }}</span><span v-if="reportDetail.report.report_flags.has_reviewer">{{ copy.hasReviewer }}</span><span v-if="reportDetail.report.report_flags.is_dismissed">{{ copy.closed }}</span><span v-if="reportDetail.report.report_flags.is_archived">{{ copy.archived }}</span></div>
            <div class="ym-report-sensitive"><section><h4>{{ copy.reportDetails }}</h4><p v-if="reportDetail.field_access.can_view_report_details" :dir="textDirection(reportDetail.report.details)">{{ reportDetail.report.details || copy.noReportDetails }}</p><p v-else>{{ copy.fieldUnavailable }}</p></section><section><h4>{{ copy.resolutionNotes }}</h4><p v-if="reportDetail.field_access.can_view_resolution_notes" :dir="textDirection(reportDetail.report.resolution_notes)">{{ reportDetail.report.resolution_notes || copy.noResolutionNotes }}</p><p v-else>{{ copy.fieldUnavailable }}</p></section></div>
            <div class="ym-report-field-access"><span>{{ copy.viewReportDetailsAccess }}: {{ yesNo(reportDetail.field_access.can_view_report_details) }}</span><span>{{ copy.viewResolutionAccess }}: {{ yesNo(reportDetail.field_access.can_view_resolution_notes) }}</span></div>
          </template>
        </section>
      </section>
    </div>

    <div v-if="pendingAction" class="ym-action-modal-backdrop" role="presentation" @click.self="cancelAction">
      <section ref="actionModalRef" class="ym-action-modal" role="dialog" aria-modal="true" aria-labelledby="report-action-title" tabindex="-1">
        <header><div><span>{{ copy.confirmReportAction }}</span><h2 id="report-action-title">{{ actionLabel(pendingAction.action) }}</h2></div><button type="button" :disabled="actionLoading" :title="copy.cancel" :aria-label="copy.cancel" @click="cancelAction">×</button></header>
        <dl><div><dt>{{ copy.work }}</dt><dd :dir="textDirection(selectedWork?.title)">{{ selectedWork?.title }}</dd></div><div><dt>{{ copy.reportId }}</dt><dd>#{{ pendingAction.report.id }}</dd></div><div><dt>{{ copy.reasonCode }}</dt><dd dir="ltr">{{ pendingAction.report.reason_code }}</dd></div><div><dt>{{ copy.currentStatus }}</dt><dd>{{ reportStatusLabel(pendingAction.report.status) }}</dd></div></dl>
        <p class="ym-action-transition">{{ actionDescription(pendingAction.action, pendingAction.report) }}</p>
        <p v-if="pendingAction.action === 'archive'" class="ym-action-warning">{{ copy.noRestoreWarning }}</p>
        <label v-if="pendingAction.action === 'dismiss'" class="ym-action-notes"><span>{{ copy.resolutionNotesLabel }}</span><textarea v-model="resolutionNotes" rows="6" minlength="5" maxlength="2000" required autofocus :placeholder="copy.resolutionPlaceholder" /><small>{{ resolutionNotes.length }} / 2000</small><em v-if="resolutionNotesError" role="alert">{{ resolutionNotesError }}</em></label>
        <p v-if="modalActionError" class="ym-works-reports-filter-error" role="alert">{{ modalActionError }}</p>
        <footer><button type="button" class="ym-works-reports-button is-secondary" :disabled="actionLoading" @click="cancelAction">{{ copy.cancel }}</button><button type="button" class="ym-report-action" :class="'is-' + pendingAction.action" :disabled="!actionCanSubmit" @click="executeAction"><span v-if="actionLoading" class="ym-inline-spinner" />{{ actionLoading ? copy.executing : copy.confirmExecution }}</button></footer>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type WorkStatus = 'draft' | 'submitted' | 'in_review' | 'changes_requested' | 'approved' | 'published' | 'rejected' | 'hidden' | 'archived'
type VisibilityStatus = 'hidden' | 'public'
type ReportStatus = 'pending' | 'under_review' | 'dismissed' | 'archived'
type ActionName = 'review' | 'dismiss' | 'archive'
type Direction = 'asc' | 'desc'
type PageSize = 15 | 25 | 50
type WorkSort = 'reports_count' | 'combined_reports_count' | 'tracked_reports_count' | 'open_tracked_reports_count' | 'updated_at' | 'created_at' | 'submitted_at' | 'published_at' | 'title' | 'status' | 'views_count' | 'likes_count'
type ReportSort = 'created_at' | 'updated_at' | 'status' | 'reviewed_at' | 'dismissed_at' | 'archived_at'

interface Person { id: number; name: string }
interface Tracking { legacy_count: number; tracked_count: number; combined_signal_count: number; pending_count: number; under_review_count: number; dismissed_count: number; archived_count: number; open_count: number; has_legacy_untracked: boolean; has_tracked: boolean; has_open_tracked: boolean }
interface WorkFlags { has_reports: boolean; high_reports: boolean; visibility_risk: boolean; needs_attention: boolean }
interface WorkItem { id: number; title: string; slug: string; summary: string | null; status: WorkStatus; visibility_status: VisibilityStatus; media_type: string | null; designer: Person | null; reviewer: Person | null; category_id: number | null; is_featured: boolean; is_pinned: boolean; reports_count: number; views_count: number; likes_count: number; submitted_at: string | null; published_at: string | null; hidden_at: string | null; created_at: string | null; updated_at: string | null; report_tracking: Tracking; report_flags: WorkFlags }
interface ReportFlags { is_open: boolean; is_pending: boolean; is_under_review: boolean; is_dismissed: boolean; is_archived: boolean; has_reviewer: boolean; needs_attention: boolean }
interface ReportItem { id: number; work_id: number; reason_code: string; status: ReportStatus; reporter: Person | null; reviewer: Person | null; reviewed_at: string | null; dismissed_at: string | null; archived_at: string | null; created_at: string | null; updated_at: string | null; report_flags: ReportFlags }
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }
interface Summary { total: number; reported: number; high_reports: number; public_reported: number; hidden_reported: number; published_reported: number; review_queue_reported: number; featured_reported: number; pinned_reported: number; total_reports: number; legacy_reports_total: number; tracked_reports_total: number; combined_report_signal_total: number; open_tracked_reports: number; pending_tracked_reports: number; under_review_tracked_reports: number; dismissed_tracked_reports: number; archived_tracked_reports: number; works_with_legacy_reports: number; works_with_tracked_reports: number; works_with_open_tracked_reports: number; works_with_both_sources: number }
interface WorkContext { id: number; title: string; slug: string; status: WorkStatus; visibility_status: VisibilityStatus; legacy_reports_count: number; tracked_reports_count: number }
interface DetailWork extends WorkContext { is_featured: boolean; is_pinned: boolean }
interface ReportDetail { report: ReportItem & { details: string | null; resolution_notes: string | null }; work: DetailWork; field_access: { can_view_report_details: boolean; can_view_resolution_notes: boolean } }
interface WorkDetailBase { id: number; title: string; slug: string; summary: string | null; status: WorkStatus; visibility_status: VisibilityStatus; media_type: string | null; price_amount: string | null; delivery_days: number | null; category_id: number | null; is_featured: boolean; is_pinned: boolean; reports_count: number; views_count: number; likes_count: number; submitted_at: string | null; reviewed_at: string | null; approved_at: string | null; published_at: string | null; rejected_at: string | null; hidden_at: string | null; archived_at: string | null; updated_at: string | null; created_at: string | null }
interface WorkDetailData { work: WorkDetailBase; relations: { designer: Person | null; reviewer: Person | null }; media: { media_type: string | null; has_media: boolean } | null; private_notes: { internal_notes: string | null; rejection_reason: string | null; change_request_notes: string | null } | null; field_access: { can_view_designer: boolean; can_view_media: boolean; can_view_metadata: boolean; can_view_private_notes: boolean } }
interface ApiResponse<T> { success: boolean; data: T | null; message?: string; errors?: Record<string, string[]> | null }
interface ActionPayload { action: ActionName; changed: boolean; report: unknown; work: unknown }

interface WorkFilters { q: string; status: '' | WorkStatus; visibility_status: '' | VisibilityStatus; media_type: string; designer_id: string; reviewer_id: string; category_id: string; min_reports: string; report_source: 'all' | 'legacy' | 'tracked' | 'both'; tracked_status: '' | ReportStatus; is_featured: '' | '1' | '0'; is_pinned: '' | '1' | '0'; from: string; to: string; sort: WorkSort; direction: Direction; per_page: PageSize }
interface TrackedFilters { status: '' | ReportStatus; reason_code: string; reporter_id: string; reviewed_by: string; from: string; to: string; sort: ReportSort; direction: Direction; per_page: PageSize }

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
const workStatuses: WorkStatus[] = ['draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'published', 'rejected', 'hidden', 'archived']
const reportStatuses: ReportStatus[] = ['pending', 'under_review', 'dismissed', 'archived']
const copyMap = {
  ar: {
    trackingTag: 'بلاغات متتبعة وإجراءات فردية', kicker: 'إدارة بلاغات الأعمال ومؤشرات المخاطر', title: 'البلاغات والمخالفات', description: 'افصل بين العداد التاريخي وسجلات البلاغات القابلة للتتبع، واقرأ تفاصيل العمل والبلاغ ونفّذ الإجراء المسموح على سجل واحد.', totalWorks: 'الأعمال المطابقة', filteredScope: 'وفق الفلاتر الحالية',
    authLoadingTitle: 'جارٍ التحقق من صلاحية البلاغات', authLoadingCopy: 'ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.', forbiddenTitle: 'الوصول إلى بلاغات الأعمال غير متاح', forbiddenCopy: 'لا يملك هذا الحساب صلاحيات القسم المطلوبة. لم يتم إرسال طلب بيانات.',
    important: 'مهم', sourcesNoticeTitle: 'مصدران مختلفان لا يمثلان عدادًا موحدًا', sourcesNotice: 'العداد التاريخي محفوظ في works.reports_count، أما البلاغات المتتبعة فهي سجلات فعلية من work_reports. المصدران غير متزامنين، والإشارة المركبة أداة إدارية فقط وليست عددًا موحدًا موثوقًا.', summaryLabel: 'ملخص مصادر البلاغات', secondarySummaryLabel: 'المؤشرات الثانوية',
    total: 'إجمالي الأعمال', totalHint: 'كل الأعمال المطابقة', reportedSummary: 'الأعمال المبلّغ عنها', reportedHint: 'لها إشارة بلاغ واحدة على الأقل', highReports: 'إشارات مرتفعة', highReportsHint: 'خمس إشارات أو أكثر', publicReported: 'عامة ومبلّغ عنها', publicReportedHint: 'لا تزال ظاهرة للعامة', hiddenReported: 'مخفية ومبلّغ عنها', hiddenReportedHint: 'ظهورها أو حالتها مخفية', publishedReported: 'منشورة ومبلّغ عنها', publishedReportedHint: 'حالتها الحالية منشورة', reviewQueueReported: 'ضمن طابور المراجعة', reviewQueueReportedHint: 'مرسلة أو تحت المراجعة أو تطلب تعديلًا', featuredReported: 'مميزة ومبلّغ عنها', featuredReportedHint: 'تحمل علامة التمييز', pinnedReported: 'مثبتة ومبلّغ عنها', pinnedReportedHint: 'تحمل علامة التثبيت',
    legacyReports: 'البلاغات التاريخية', legacyHint: 'عداد غير متتبع', trackedReports: 'البلاغات المتتبعة', trackedHint: 'سجلات فردية حقيقية', combinedSignal: 'الإشارة المركبة', combinedHint: 'مؤشر إداري فقط', openReports: 'البلاغات المفتوحة', openHint: 'انتظار وتحت المراجعة', pending: 'قيد الانتظار', pendingHint: 'تحتاج بدء المراجعة', underReview: 'تحت المراجعة', underReviewHint: 'قيد المعالجة', dismissedReports: 'بلاغات مغلقة', archivedReports: 'بلاغات مؤرشفة', worksLegacy: 'أعمال لها عداد تاريخي', worksTracked: 'أعمال لها سجلات متتبعة', worksOpen: 'أعمال لها بلاغات مفتوحة', worksBoth: 'أعمال بالمصدرين',
    actionChanged: 'تم تغيير الحالة', actionUnchanged: 'لم تتغير الحالة', hideActionStatus: 'إخفاء حالة الإجراء', reportWord: 'البلاغ',
    filtersTitle: 'بحث وفلاتر الأعمال', filtersCopy: 'تؤثر هذه الفلاتر على قائمة الأعمال فقط.', reset: 'إعادة الضبط', search: 'البحث', searchPlaceholder: 'العنوان أو المعرّف النصي أو الملخص', workStatus: 'حالة العمل', all: 'الكل', visibility: 'الظهور', public: 'عام', hidden: 'مخفي', mediaType: 'نوع الوسائط', designerId: 'معرّف المصمم', reviewerId: 'معرّف المراجع', categoryId: 'معرّف التصنيف', source: 'المصدر', allSources: 'كل المصادر', legacy: 'تاريخي', tracked: 'متتبع', bothSources: 'المصدران معًا', trackedStatus: 'حالة البلاغ المتتبع', allStatuses: 'كل الحالات', featured: 'مميز', pinned: 'مثبت', yes: 'نعم', no: 'لا', minimumSignal: 'الحد الأدنى للإشارة', updatedFrom: 'حُدّث من', updatedTo: 'حُدّث إلى', sort: 'الفرز', direction: 'الاتجاه', descending: 'تنازلي', ascending: 'تصاعدي', perPage: 'لكل صفحة', applyFilters: 'تطبيق الفلاتر', clear: 'مسح',
    tableTitle: 'قائمة الأعمال ذات إشارات البلاغ', tableCopy: 'تعرض المعلومات الإدارية السابقة مع مؤشرات التتبع، وتفصل تفاصيل العمل عن سجلات البلاغات.', currentPage: 'الصفحة الحالية', loadingWorks: 'جارٍ تحميل الأعمال', loadWorksError: 'تعذر تحميل الأعمال', retry: 'إعادة المحاولة', emptyWorks: 'لا توجد نتائج مطابقة', emptyWorksCopy: 'غيّر الفلاتر أو أعد ضبطها.', work: 'العمل', status: 'الحالة', sources: 'المصادر', trackedStates: 'حالات السجلات المتتبعة', indicators: 'المؤشرات', administrativeData: 'البيانات الإدارية', datesAndCounts: 'التواريخ والأعداد', actions: 'الإجراءات', designer: 'المصمم', reviewer: 'المراجع', category: 'التصنيف', views: 'المشاهدات', likes: 'الإعجابات', submittedAt: 'تاريخ الإرسال', publishedAt: 'تاريخ النشر', hiddenAt: 'تاريخ الإخفاء', createdAt: 'تاريخ الإنشاء', updatedAt: 'آخر تحديث', legacyCompatibility: 'reports_count للتوافق', historical: 'تاريخية', open: 'مفتوحة', waiting: 'انتظار', review: 'مراجعة', closed: 'مغلقة', archived: 'مؤرشفة', legacyCounter: 'عداد تاريخي', trackedRecords: 'سجلات متتبعة', needsFollowup: 'يحتاج متابعة', visiblePublicly: 'ظاهر للعامة', highSignal: 'إشارة مرتفعة', viewWorkDetails: 'تفاصيل العمل', viewTrackedReports: 'عرض البلاغات المتتبعة', noTrackedRecords: 'لا توجد سجلات بلاغات فردية متتبعة لهذا العمل.', totalResults: 'إجمالي النتائج', visibleNow: 'ظاهر الآن', previous: 'السابق', next: 'التالي', workPages: 'صفحات الأعمال', page: 'صفحة',
    workDetailsReadonly: 'تفاصيل العمل للقراءة فقط', closeWorkDetails: 'إغلاق تفاصيل العمل', loadingWorkDetails: 'جارٍ تحميل تفاصيل العمل', loadingAllowedFields: 'يتم جلب الحقول المسموحة لهذا الحساب...', workDetailsError: 'تعذر تحميل تفاصيل العمل', noSummary: 'لا يوجد ملخص مسجل لهذا العمل.', accessScope: 'نطاق الحقول المتاح', accessScopeCopy: 'تعكس هذه المؤشرات صلاحيات الحقول التي طبقها الخادم.', canViewDesigner: 'المصمم والمراجع', canViewMedia: 'بيانات الوسائط', canViewMetadata: 'صلاحية metadata', canViewPrivateNotes: 'الملاحظات الخاصة', allowed: 'متاح', unavailable: 'غير متاح', basicDetails: 'البيانات الأساسية', priceAmount: 'القيمة السعرية', deliveryDays: 'مدة التسليم بالأيام', reportsCount: 'عداد البلاغات التاريخي', people: 'المصمم والمراجع', notLinked: 'غير مرتبط', relationsUnavailable: 'المصمم والمراجع غير متاحين حسب الصلاحية.', media: 'الوسائط', mediaPresent: 'توجد وسائط مسجلة', mediaAbsent: 'لا توجد وسائط مسجلة', mediaUnavailable: 'بيانات الوسائط غير متاحة حسب الصلاحية.', lifecycle: 'التسلسل الزمني', reviewedAt: 'تاريخ المراجعة', approvedAt: 'تاريخ الاعتماد', rejectedAt: 'تاريخ الرفض', archivedAt: 'تاريخ الأرشفة', privateNotes: 'الملاحظات الخاصة', privateNotesCopy: 'لا تظهر محتويات هذا القسم إلا عندما يسمح الخادم بذلك.', internalNotes: 'الملاحظات الداخلية', rejectionReason: 'سبب الرفض', changeRequestNotes: 'ملاحظات طلب التعديل', privateNotesUnavailable: 'الملاحظات الخاصة غير متاحة حسب الصلاحية.', workDetailsPermission: 'يتطلب عرض تفاصيل العمل صلاحيات قائمة وتفاصيل الأعمال.',
    trackedDrawerTitle: 'سجلات البلاغات المتتبعة', closePanel: 'إغلاق اللوحة', reasonCode: 'رمز السبب', reporterId: 'معرّف المبلّغ', reporter: 'المبلّغ', from: 'من', to: 'إلى', loadingTracked: 'جارٍ تحميل البلاغات المتتبعة', loadTrackedError: 'تعذر تحميل البلاغات', emptyTracked: 'لا توجد سجلات مطابقة', emptyTrackedCopy: 'لا توجد سجلات بلاغات فردية متتبعة لهذا العمل وفق الفلاتر الحالية.', needsAttention: 'يحتاج انتباه', hasReviewer: 'له مراجع', reviewed: 'تمت المراجعة', dismissedAt: 'أُغلق', archivedAtShort: 'أُرشف', createdAtShort: 'أُنشئ', viewReportDetails: 'عرض التفاصيل', reportDetailHint: 'عرض الحقول التفصيلية المسموحة', reportDetailPermission: 'تتطلب صلاحية admin.works.reports.detail.view', startReview: 'بدء المراجعة', updateDismissal: 'تحديث معالجة البلاغ', dismissReport: 'إغلاق البلاغ', archiveReport: 'أرشفة البلاغ', totalRecords: 'إجمالي السجلات', reportPages: 'صفحات البلاغات',
    reportDetailsAllowed: 'تفاصيل البلاغ المسموحة', closeReportDetails: 'إغلاق التفاصيل', reportContext: 'سياق العمل الآمن', reportId: 'معرّف البلاغ', reportDetails: 'تفاصيل البلاغ', noReportDetails: 'لا توجد تفاصيل.', resolutionNotes: 'ملاحظات المعالجة', noResolutionNotes: 'لا توجد ملاحظات معالجة.', fieldUnavailable: 'هذا الحقل غير متاح.', viewReportDetailsAccess: 'عرض تفاصيل البلاغ', viewResolutionAccess: 'عرض ملاحظات المعالجة',
    confirmReportAction: 'تأكيد إجراء بلاغ فردي', cancel: 'إلغاء', currentStatus: 'الحالة الحالية', noRestoreWarning: 'لا توجد إمكانية استعادة أو إعادة فتح البلاغ في هذه المرحلة.', resolutionNotesLabel: 'ملاحظات معالجة البلاغ', resolutionPlaceholder: 'اكتب سبب المعالجة أو نتيجة الإغلاق...', executing: 'جارٍ التنفيذ...', confirmExecution: 'تأكيد التنفيذ',
    unassigned: 'غير معيّن', reviewAlreadyStarted: 'البلاغ تحت المراجعة بالفعل.', closedCannotReview: 'البلاغ مغلق ولا يمكن بدء مراجعته.', reportArchived: 'البلاغ مؤرشف.', completeReviewAssignment: 'استكمال إسناد المراجعة', archivedCannotChange: 'لا يمكن تعديل بلاغ مؤرشف.', updateResolutionHint: 'تحديث ملاحظات معالجة البلاغ', dismissWithNotes: 'إغلاق البلاغ بملاحظات معالجة', archiveDismissed: 'أرشفة البلاغ المغلق', alreadyArchived: 'البلاغ مؤرشف بالفعل.', closeBeforeArchive: 'يجب إغلاق البلاغ أولًا قبل أرشفته.', reviewTransition: 'سينتقل البلاغ من قيد الانتظار إلى تحت المراجعة ويُسند للمراجع الحالي.', reviewCompletion: 'سيكتمل إسناد المراجع أو وقت المراجعة الناقص.', dismissUpdateTransition: 'سيبقى البلاغ مغلقًا وتُحدّث ملاحظات معالجته.', dismissTransition: 'سينتقل البلاغ إلى حالة مغلق مع حفظ ملاحظات المعالجة.', archiveTransition: 'سينتقل البلاغ المغلق إلى حالة مؤرشف.',
    invalidSearch: 'البحث يجب أن يكون فارغًا أو حرفين على الأقل.', invalidIds: 'المعرّفات يجب أن تكون أعدادًا صحيحة موجبة.', invalidMinimum: 'الحد الأدنى للإشارة يجب أن يكون بين 1 و100000.', invalidDateRange: 'مدى التاريخ غير صحيح.', invalidReasonCode: 'رمز سبب البلاغ يحتوي على محارف غير مسموحة.', invalidReportIds: 'معرّفات المبلّغ والمراجع يجب أن تكون أعدادًا صحيحة موجبة.', invalidReportDateRange: 'مدى التاريخ غير صحيح أو يتجاوز عشر سنوات.', workFiltersError: 'تعذر تطبيق فلاتر الأعمال.', genericWorksError: 'حدث خطأ أثناء تحميل قائمة الأعمال. حاول مرة أخرى.', reportsForbidden: 'غير مصرح بقراءة بلاغات هذا العمل.', workNotFound: 'لم يعد العمل موجودًا.', reportFiltersError: 'تعذر تطبيق فلاتر البلاغات.', genericReportsError: 'حدث خطأ أثناء تحميل البلاغات المتتبعة.', reportDetailForbidden: 'تفاصيل البلاغ تتطلب صلاحية مخصصة.', reportNotFound: 'لم يعد البلاغ موجودًا.', selectedWork: 'العمل المحدد', showDetailsAction: 'عرض التفاصيل', genericDetailError: 'حدث خطأ أثناء تحميل تفاصيل البلاغ.', resolutionValidation: 'ملاحظات المعالجة مطلوبة ويجب أن تكون بين 5 و2000 حرف.', actionSuccess: 'تم تنفيذ إجراء البلاغ بنجاح', actionNoChange: 'لا يوجد تغيير؛ حالة البلاغ مطابقة بالفعل', genericActionError: 'حدث خطأ غير متوقع أثناء تنفيذ الإجراء.', actionValidationError: 'تعذر تنفيذ الإجراء بالقيم الحالية.', actionForbidden: 'غير مصرح بتنفيذ هذا الإجراء.', workDetailsGenericError: 'حدث خطأ أثناء تحميل تفاصيل العمل. حاول مرة أخرى.', workDetailsForbidden: 'تفاصيل هذا العمل غير متاحة حسب صلاحيات الحساب.', workDetailsNotFound: 'لم يعد هذا العمل موجودًا أو لم يعد متاحًا.'
  },
  en: {
    trackingTag: 'Tracked reports and individual actions', kicker: 'Works reports and risk signals', title: 'Reports & Violations', description: 'Separate the legacy counter from traceable report records, read work and report details, and perform the permitted action on one record.', totalWorks: 'Matching works', filteredScope: 'Using current filters',
    authLoadingTitle: 'Checking reports access', authLoadingCopy: 'Waiting for the user session to initialize before requesting data.', forbiddenTitle: 'Works reports access is unavailable', forbiddenCopy: 'This account lacks the required section permissions. No data request was made.',
    important: 'Important', sourcesNoticeTitle: 'Two different sources do not form one unified count', sourcesNotice: 'The legacy counter is stored in works.reports_count, while tracked reports are actual records from work_reports. The sources are not synchronized, and the combined signal is an administrative indicator only.', summaryLabel: 'Report sources summary', secondarySummaryLabel: 'Secondary indicators',
    total: 'Total works', totalHint: 'All matching works', reportedSummary: 'Reported works', reportedHint: 'At least one report signal', highReports: 'High signals', highReportsHint: 'Five signals or more', publicReported: 'Public reported', publicReportedHint: 'Still publicly visible', hiddenReported: 'Hidden reported', hiddenReportedHint: 'Visibility or status is hidden', publishedReported: 'Published reported', publishedReportedHint: 'Currently published', reviewQueueReported: 'In review queue', reviewQueueReportedHint: 'Submitted, in review, or changes requested', featuredReported: 'Featured reported', featuredReportedHint: 'Marked as featured', pinnedReported: 'Pinned reported', pinnedReportedHint: 'Marked as pinned',
    legacyReports: 'Legacy reports', legacyHint: 'Untracked counter', trackedReports: 'Tracked reports', trackedHint: 'Real individual records', combinedSignal: 'Combined signal', combinedHint: 'Administrative indicator only', openReports: 'Open reports', openHint: 'Pending and under review', pending: 'Pending', pendingHint: 'Needs review to start', underReview: 'Under review', underReviewHint: 'Being processed', dismissedReports: 'Dismissed reports', archivedReports: 'Archived reports', worksLegacy: 'Works with legacy reports', worksTracked: 'Works with tracked reports', worksOpen: 'Works with open reports', worksBoth: 'Works with both sources',
    actionChanged: 'State changed', actionUnchanged: 'State did not change', hideActionStatus: 'Hide action status', reportWord: 'Report',
    filtersTitle: 'Works search and filters', filtersCopy: 'These filters affect the works list only.', reset: 'Reset', search: 'Search', searchPlaceholder: 'Title, slug, or summary', workStatus: 'Work status', all: 'All', visibility: 'Visibility', public: 'Public', hidden: 'Hidden', mediaType: 'Media type', designerId: 'Designer ID', reviewerId: 'Reviewer ID', categoryId: 'Category ID', source: 'Source', allSources: 'All sources', legacy: 'Legacy', tracked: 'Tracked', bothSources: 'Both sources', trackedStatus: 'Tracked report status', allStatuses: 'All statuses', featured: 'Featured', pinned: 'Pinned', yes: 'Yes', no: 'No', minimumSignal: 'Minimum signal', updatedFrom: 'Updated from', updatedTo: 'Updated to', sort: 'Sort', direction: 'Direction', descending: 'Descending', ascending: 'Ascending', perPage: 'Per page', applyFilters: 'Apply filters', clear: 'Clear',
    tableTitle: 'Works with report signals', tableCopy: 'Shows the previous administrative information with tracking indicators and separates work details from report records.', currentPage: 'Current page', loadingWorks: 'Loading works', loadWorksError: 'Could not load works', retry: 'Retry', emptyWorks: 'No matching results', emptyWorksCopy: 'Change or reset the filters.', work: 'Work', status: 'Status', sources: 'Sources', trackedStates: 'Tracked record states', indicators: 'Indicators', administrativeData: 'Administrative data', datesAndCounts: 'Dates and counts', actions: 'Actions', designer: 'Designer', reviewer: 'Reviewer', category: 'Category', views: 'Views', likes: 'Likes', submittedAt: 'Submitted at', publishedAt: 'Published at', hiddenAt: 'Hidden at', createdAt: 'Created at', updatedAt: 'Updated at', legacyCompatibility: 'reports_count compatibility', historical: 'Legacy', open: 'Open', waiting: 'Pending', review: 'Review', closed: 'Dismissed', archived: 'Archived', legacyCounter: 'Legacy counter', trackedRecords: 'Tracked records', needsFollowup: 'Needs follow-up', visiblePublicly: 'Publicly visible', highSignal: 'High signal', viewWorkDetails: 'Work details', viewTrackedReports: 'View tracked reports', noTrackedRecords: 'No individually tracked report records exist for this work.', totalResults: 'Total results', visibleNow: 'visible now', previous: 'Previous', next: 'Next', workPages: 'Works pages', page: 'Page',
    workDetailsReadonly: 'Read-only work details', closeWorkDetails: 'Close work details', loadingWorkDetails: 'Loading work details', loadingAllowedFields: 'Fetching fields allowed for this account...', workDetailsError: 'Could not load work details', noSummary: 'No summary has been recorded for this work.', accessScope: 'Available field scope', accessScopeCopy: 'These indicators reflect field permissions enforced by the server.', canViewDesigner: 'Designer and reviewer', canViewMedia: 'Media data', canViewMetadata: 'Metadata permission', canViewPrivateNotes: 'Private notes', allowed: 'Available', unavailable: 'Unavailable', basicDetails: 'Basic details', priceAmount: 'Price amount', deliveryDays: 'Delivery days', reportsCount: 'Legacy report counter', people: 'Designer and reviewer', notLinked: 'Not linked', relationsUnavailable: 'Designer and reviewer are unavailable for this permission scope.', media: 'Media', mediaPresent: 'Media is recorded', mediaAbsent: 'No media is recorded', mediaUnavailable: 'Media data is unavailable for this permission scope.', lifecycle: 'Lifecycle', reviewedAt: 'Reviewed at', approvedAt: 'Approved at', rejectedAt: 'Rejected at', archivedAt: 'Archived at', privateNotes: 'Private notes', privateNotesCopy: 'This section only reveals content when the server allows it.', internalNotes: 'Internal notes', rejectionReason: 'Rejection reason', changeRequestNotes: 'Change request notes', privateNotesUnavailable: 'Private notes are unavailable for this permission scope.', workDetailsPermission: 'Work list and detail permissions are required.',
    trackedDrawerTitle: 'Tracked report records', closePanel: 'Close panel', reasonCode: 'Reason code', reporterId: 'Reporter ID', reporter: 'Reporter', from: 'From', to: 'To', loadingTracked: 'Loading tracked reports', loadTrackedError: 'Could not load reports', emptyTracked: 'No matching records', emptyTrackedCopy: 'No individually tracked report records match the current filters for this work.', needsAttention: 'Needs attention', hasReviewer: 'Has reviewer', reviewed: 'Reviewed', dismissedAt: 'Dismissed', archivedAtShort: 'Archived', createdAtShort: 'Created', viewReportDetails: 'View details', reportDetailHint: 'View allowed detail fields', reportDetailPermission: 'Requires admin.works.reports.detail.view', startReview: 'Start review', updateDismissal: 'Update report resolution', dismissReport: 'Dismiss report', archiveReport: 'Archive report', totalRecords: 'Total records', reportPages: 'Report pages',
    reportDetailsAllowed: 'Allowed report details', closeReportDetails: 'Close report details', reportContext: 'Safe work context', reportId: 'Report ID', reportDetails: 'Report details', noReportDetails: 'No details.', resolutionNotes: 'Resolution notes', noResolutionNotes: 'No resolution notes.', fieldUnavailable: 'This field is unavailable.', viewReportDetailsAccess: 'View report details', viewResolutionAccess: 'View resolution notes',
    confirmReportAction: 'Confirm individual report action', cancel: 'Cancel', currentStatus: 'Current status', noRestoreWarning: 'Restore and reopen are not available at this stage.', resolutionNotesLabel: 'Report resolution notes', resolutionPlaceholder: 'Enter the resolution reason or dismissal result...', executing: 'Executing...', confirmExecution: 'Confirm action',
    unassigned: 'Unassigned', reviewAlreadyStarted: 'The report is already under review.', closedCannotReview: 'A dismissed report cannot start review.', reportArchived: 'The report is archived.', completeReviewAssignment: 'Complete review assignment', archivedCannotChange: 'An archived report cannot be changed.', updateResolutionHint: 'Update report resolution notes', dismissWithNotes: 'Dismiss the report with resolution notes', archiveDismissed: 'Archive the dismissed report', alreadyArchived: 'The report is already archived.', closeBeforeArchive: 'The report must be dismissed before archiving.', reviewTransition: 'The report will move from pending to under review and be assigned to the current reviewer.', reviewCompletion: 'The missing reviewer assignment or review time will be completed.', dismissUpdateTransition: 'The report remains dismissed and its resolution notes will be updated.', dismissTransition: 'The report will move to dismissed and its resolution notes will be saved.', archiveTransition: 'The dismissed report will move to archived.',
    invalidSearch: 'Search must be empty or contain at least two characters.', invalidIds: 'Identifiers must be positive integers.', invalidMinimum: 'The minimum signal must be between 1 and 100000.', invalidDateRange: 'The date range is invalid.', invalidReasonCode: 'The report reason code contains unsupported characters.', invalidReportIds: 'Reporter and reviewer identifiers must be positive integers.', invalidReportDateRange: 'The date range is invalid or exceeds ten years.', workFiltersError: 'Could not apply works filters.', genericWorksError: 'An error occurred while loading the works list. Try again.', reportsForbidden: 'You are not authorized to read reports for this work.', workNotFound: 'The work no longer exists.', reportFiltersError: 'Could not apply report filters.', genericReportsError: 'An error occurred while loading tracked reports.', reportDetailForbidden: 'Report details require a dedicated permission.', reportNotFound: 'The report no longer exists.', selectedWork: 'Selected work', showDetailsAction: 'View details', genericDetailError: 'An error occurred while loading report details.', resolutionValidation: 'Resolution notes are required and must contain 5 to 2000 characters.', actionSuccess: 'The report action was completed successfully', actionNoChange: 'No change; the report already matches this state', genericActionError: 'An unexpected error occurred while performing the action.', actionValidationError: 'The action could not be performed with the current values.', actionForbidden: 'You are not authorized to perform this action.', workDetailsGenericError: 'An error occurred while loading work details. Try again.', workDetailsForbidden: 'Work details are unavailable for this account permissions.', workDetailsNotFound: 'This work no longer exists or is no longer available.'
  }
} as const

const copy = computed(() => copyMap[currentLocale.value])
const pageDirection = computed<'rtl' | 'ltr'>(() => currentLocale.value === 'ar' ? 'rtl' : 'ltr')
const workSortOptions = computed<Array<{ value: WorkSort; label: string }>>(() => [
  { value: 'reports_count', label: copy.value.legacyCounter }, { value: 'combined_reports_count', label: copy.value.combinedSignal }, { value: 'tracked_reports_count', label: copy.value.trackedReports }, { value: 'open_tracked_reports_count', label: copy.value.openReports }, { value: 'updated_at', label: copy.value.updatedAt }, { value: 'created_at', label: copy.value.createdAt }, { value: 'submitted_at', label: copy.value.submittedAt }, { value: 'published_at', label: copy.value.publishedAt }, { value: 'title', label: copy.value.work }, { value: 'status', label: copy.value.status }, { value: 'views_count', label: copy.value.views }, { value: 'likes_count', label: copy.value.likes }
])
const reportSortOptions = computed<Array<{ value: ReportSort; label: string }>>(() => [
  { value: 'created_at', label: copy.value.createdAt }, { value: 'updated_at', label: copy.value.updatedAt }, { value: 'status', label: copy.value.status }, { value: 'reviewed_at', label: copy.value.reviewedAt }, { value: 'dismissed_at', label: copy.value.dismissedAt }, { value: 'archived_at', label: copy.value.archivedAt }
])

const authPending = computed(() => !authStore.isInitialized)
const hasPageAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  return ['admin', 'staff'].includes(authStore.role || '') && hasPermissions('admin.works.access', 'admin.works.reports.view', 'admin.works.reports.list')
})
const canViewReportDetail = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.reports.detail.view')))
const canViewWorkDetails = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.all.view', 'admin.works.detail.view')))
const canReview = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.reports.review')))
const canDismiss = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.reports.dismiss')))
const canArchive = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.reports.archive')))
const serverForbidden = ref(false)
const forbidden = computed(() => authStore.isInitialized && (!hasPageAccess.value || serverForbidden.value))

const items = ref<WorkItem[]>([])
const pagination = reactive<Pagination>(emptyPagination())
const summary = reactive<Summary>(emptySummary())
const filters = reactive<WorkFilters>(defaultWorkFilters())
const appliedFilters = reactive<WorkFilters>(defaultWorkFilters())
const workPage = ref(1)
const loading = ref(false)
const error = ref<string | null>(null)
const filterError = ref<string | null>(null)

const workDetailsOpen = ref(false)
const selectedWorkDetailsId = ref<number | null>(null)
const selectedWorkDetailsTitle = ref('')
const workDetail = ref<WorkDetailData | null>(null)
const workDetailLoading = ref(false)
const workDetailError = ref<string | null>(null)

const reportsDrawerOpen = ref(false)
const selectedWork = ref<WorkItem | null>(null)
const trackedReports = ref<ReportItem[]>([])
const reportPagination = reactive<Pagination>(emptyPagination())
const reportFilters = reactive<TrackedFilters>(defaultReportFilters())
const appliedReportFilters = reactive<TrackedFilters>(defaultReportFilters())
const reportPage = ref(1)
const reportsLoading = ref(false)
const reportsError = ref<string | null>(null)
const reportFilterError = ref<string | null>(null)

const reportDetailOpen = ref(false)
const selectedReportId = ref<number | null>(null)
const reportDetail = ref<ReportDetail | null>(null)
const detailLoading = ref(false)
const detailError = ref<string | null>(null)

const pendingAction = ref<{ action: ActionName; report: ReportItem } | null>(null)
const actionLoading = ref(false)
const actionReportId = ref<number | null>(null)
const resolutionNotes = ref('')
const resolutionNotesError = ref<string | null>(null)
const modalActionError = ref<string | null>(null)
const actionModalRef = ref<HTMLElement | null>(null)
const actionStatus = ref<{ kind: 'success' | 'error'; message: string; actionLabel: string; reportId: number; workTitle: string; changed: boolean | null } | null>(null)

let mounted = false
let accessRevision = 0
let listRevision = 0
let reportsRevision = 0
let detailRevision = 0
let workDetailRevision = 0
let loadedSignature: string | null = null
const authSignature = computed(() => [authStore.isInitialized, authStore.isAuthenticated, authStore.role, [...authStore.permissions].sort().join(',')].join('|'))

const primarySummaryCards = computed(() => [
  { key: 'legacy', label: copy.value.legacyReports, value: summary.legacy_reports_total, hint: copy.value.legacyHint, color: '#f59e0b' },
  { key: 'tracked', label: copy.value.trackedReports, value: summary.tracked_reports_total, hint: copy.value.trackedHint, color: '#38bdf8' },
  { key: 'combined', label: copy.value.combinedSignal, value: summary.combined_report_signal_total, hint: copy.value.combinedHint, color: '#a855f7' },
  { key: 'open', label: copy.value.openReports, value: summary.open_tracked_reports, hint: copy.value.openHint, color: '#f43f5e' },
  { key: 'pending', label: copy.value.pending, value: summary.pending_tracked_reports, hint: copy.value.pendingHint, color: '#fb7185' },
  { key: 'review', label: copy.value.underReview, value: summary.under_review_tracked_reports, hint: copy.value.underReviewHint, color: '#6366f1' }
])
const secondarySummaryItems = computed(() => [
  { key: 'dismissed', label: copy.value.dismissedReports, value: summary.dismissed_tracked_reports }, { key: 'archived', label: copy.value.archivedReports, value: summary.archived_tracked_reports }, { key: 'legacyWorks', label: copy.value.worksLegacy, value: summary.works_with_legacy_reports }, { key: 'trackedWorks', label: copy.value.worksTracked, value: summary.works_with_tracked_reports }, { key: 'openWorks', label: copy.value.worksOpen, value: summary.works_with_open_tracked_reports }, { key: 'bothWorks', label: copy.value.worksBoth, value: summary.works_with_both_sources }
])
const legacySummaryItems = computed(() => [
  { key: 'total', label: copy.value.total, value: summary.total }, { key: 'reported', label: copy.value.reportedSummary, value: summary.reported }, { key: 'high', label: copy.value.highReports, value: summary.high_reports }, { key: 'public', label: copy.value.publicReported, value: summary.public_reported }, { key: 'hidden', label: copy.value.hiddenReported, value: summary.hidden_reported }, { key: 'published', label: copy.value.publishedReported, value: summary.published_reported }, { key: 'queue', label: copy.value.reviewQueueReported, value: summary.review_queue_reported }, { key: 'featured', label: copy.value.featuredReported, value: summary.featured_reported }, { key: 'pinned', label: copy.value.pinnedReported, value: summary.pinned_reported }, { key: 'legacyTotal', label: copy.value.reportsCount, value: summary.total_reports }
])
const workLifecycleItems = computed(() => {
  const work = workDetail.value?.work
  return [
    { key: 'submitted_at', label: copy.value.submittedAt, value: work?.submitted_at ?? null }, { key: 'reviewed_at', label: copy.value.reviewedAt, value: work?.reviewed_at ?? null }, { key: 'approved_at', label: copy.value.approvedAt, value: work?.approved_at ?? null }, { key: 'published_at', label: copy.value.publishedAt, value: work?.published_at ?? null }, { key: 'rejected_at', label: copy.value.rejectedAt, value: work?.rejected_at ?? null }, { key: 'hidden_at', label: copy.value.hiddenAt, value: work?.hidden_at ?? null }, { key: 'archived_at', label: copy.value.archivedAt, value: work?.archived_at ?? null }, { key: 'created_at', label: copy.value.createdAt, value: work?.created_at ?? null }, { key: 'updated_at', label: copy.value.updatedAt, value: work?.updated_at ?? null }
  ]
})
const actionCanSubmit = computed(() => {
  if (!pendingAction.value || actionLoading.value) return false
  if (pendingAction.value.action !== 'dismiss') return true
  const length = resolutionNotes.value.trim().length
  return length >= 5 && length <= 2000
})

function hasPermissions(...permissions: string[]): boolean { return permissions.every(permission => authStore.permissions.includes(permission)) }
function emptyPagination(): Pagination { return { current_page: 1, per_page: 15, total: 0, last_page: 1 } }
function emptySummary(): Summary { return { total: 0, reported: 0, high_reports: 0, public_reported: 0, hidden_reported: 0, published_reported: 0, review_queue_reported: 0, featured_reported: 0, pinned_reported: 0, total_reports: 0, legacy_reports_total: 0, tracked_reports_total: 0, combined_report_signal_total: 0, open_tracked_reports: 0, pending_tracked_reports: 0, under_review_tracked_reports: 0, dismissed_tracked_reports: 0, archived_tracked_reports: 0, works_with_legacy_reports: 0, works_with_tracked_reports: 0, works_with_open_tracked_reports: 0, works_with_both_sources: 0 } }
function defaultWorkFilters(): WorkFilters { return { q: '', status: '', visibility_status: '', media_type: '', designer_id: '', reviewer_id: '', category_id: '', min_reports: '1', report_source: 'all', tracked_status: '', is_featured: '', is_pinned: '', from: '', to: '', sort: 'combined_reports_count', direction: 'desc', per_page: 15 } }
function defaultReportFilters(): TrackedFilters { return { status: '', reason_code: '', reporter_id: '', reviewed_by: '', from: '', to: '', sort: 'created_at', direction: 'desc', per_page: 15 } }
function formatNumber(value: number): string { return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(Number.isFinite(value) ? value : 0) }
function formatDateTime(value: string | null): string { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date) }
function textDirection(value: string | null | undefined): 'rtl' | 'ltr' { return /[\u0600-\u06FF]/.test(String(value ?? '')) ? 'rtl' : 'ltr' }
function yesNo(value: boolean): string { return value ? copy.value.yes : copy.value.no }
function accessLabel(value: boolean): string { return value ? copy.value.allowed : copy.value.unavailable }
function displayValue(value: string | null | undefined): string { return value === null || value === undefined || value.trim() === '' ? '—' : value }
function truncateText(value: string, limit: number): string { const characters = Array.from(value.trim()); return characters.length <= limit ? characters.join('') : characters.slice(0, limit).join('') + '…' }
function personLabel(person: Person | null): string { return person ? `${person.name} · #${person.id}` : copy.value.unassigned }
function workStatusLabel(status: WorkStatus): string { const labels = { draft: { ar: 'مسودة', en: 'Draft' }, submitted: { ar: 'مرسل', en: 'Submitted' }, in_review: { ar: 'تحت المراجعة', en: 'In review' }, changes_requested: { ar: 'تعديلات مطلوبة', en: 'Changes requested' }, approved: { ar: 'معتمد', en: 'Approved' }, published: { ar: 'منشور', en: 'Published' }, rejected: { ar: 'مرفوض', en: 'Rejected' }, hidden: { ar: 'مخفي', en: 'Hidden' }, archived: { ar: 'مؤرشف', en: 'Archived' } }; return labels[status]?.[currentLocale.value] || status }
function reportStatusLabel(status: ReportStatus): string { const labels = { pending: { ar: 'قيد الانتظار', en: 'Pending' }, under_review: { ar: 'تحت المراجعة', en: 'Under review' }, dismissed: { ar: 'مغلق', en: 'Dismissed' }, archived: { ar: 'مؤرشف', en: 'Archived' } }; return labels[status]?.[currentLocale.value] || status }
function actionLabel(action: ActionName): string { return ({ review: copy.value.startReview, dismiss: copy.value.dismissReport, archive: copy.value.archiveReport } as Record<ActionName, string>)[action] }
function trackedButtonTitle(work: WorkItem): string { return work.report_tracking.tracked_count > 0 ? copy.value.viewTrackedReports : copy.value.noTrackedRecords }
function actionLoadingFor(reportId: number): boolean { return actionLoading.value && actionReportId.value === reportId }
function reviewState(report: ReportItem): { enabled: boolean; title: string } { if (report.status === 'pending') return { enabled: true, title: copy.value.startReview }; if (report.status === 'under_review' && (!report.reviewer || !report.reviewed_at)) return { enabled: true, title: copy.value.completeReviewAssignment }; if (report.status === 'under_review') return { enabled: false, title: copy.value.reviewAlreadyStarted }; if (report.status === 'dismissed') return { enabled: false, title: copy.value.closedCannotReview }; return { enabled: false, title: copy.value.reportArchived } }
function dismissState(report: ReportItem): { enabled: boolean; title: string } { return report.status === 'archived' ? { enabled: false, title: copy.value.archivedCannotChange } : { enabled: true, title: report.status === 'dismissed' ? copy.value.updateResolutionHint : copy.value.dismissWithNotes } }
function archiveState(report: ReportItem): { enabled: boolean; title: string } { if (report.status === 'dismissed') return { enabled: true, title: copy.value.archiveDismissed }; if (report.status === 'archived') return { enabled: false, title: copy.value.alreadyArchived }; return { enabled: false, title: copy.value.closeBeforeArchive } }
function actionDescription(action: ActionName, report: ReportItem): string { if (action === 'review') return report.status === 'pending' ? copy.value.reviewTransition : copy.value.reviewCompletion; if (action === 'dismiss') return report.status === 'dismissed' ? copy.value.dismissUpdateTransition : copy.value.dismissTransition; return copy.value.archiveTransition }

function positiveInteger(value: string): boolean { if (!value.trim()) return true; return /^\d+$/.test(value) && Number(value) > 0 }
function dateRangeValid(from: string, to: string): boolean { if (from && to && to < from) return false; if (!from || !to) return true; const [year, month, day] = from.split('-').map(Number); const lastDay = new Date(Date.UTC(year + 10, month, 0)).getUTCDate(); const maximum = `${year + 10}-${String(month).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`; return to <= maximum }
function validateWorkFilters(): boolean { filterError.value = null; if (filters.q.trim().length === 1) { filterError.value = copy.value.invalidSearch; return false }; if (![filters.designer_id, filters.reviewer_id, filters.category_id].every(positiveInteger)) { filterError.value = copy.value.invalidIds; return false }; const minimum = Number(filters.min_reports); if (!Number.isInteger(minimum) || minimum < 1 || minimum > 100000) { filterError.value = copy.value.invalidMinimum; return false }; if (!dateRangeValid(filters.from, filters.to)) { filterError.value = copy.value.invalidDateRange; return false }; return true }
function validateReportFilters(): boolean { reportFilterError.value = null; if (reportFilters.reason_code && !/^[a-z0-9_.-]+$/.test(reportFilters.reason_code)) { reportFilterError.value = copy.value.invalidReasonCode; return false }; if (![reportFilters.reporter_id, reportFilters.reviewed_by].every(positiveInteger)) { reportFilterError.value = copy.value.invalidReportIds; return false }; if (!dateRangeValid(reportFilters.from, reportFilters.to)) { reportFilterError.value = copy.value.invalidReportDateRange; return false }; return true }

function queryFromWorkFilters(): Record<string, string | number> { const query: Record<string, string | number> = { page: workPage.value, per_page: appliedFilters.per_page, sort: appliedFilters.sort, direction: appliedFilters.direction, min_reports: appliedFilters.min_reports, report_source: appliedFilters.report_source }; for (const [key, value] of Object.entries(appliedFilters)) { if (!['per_page', 'sort', 'direction', 'min_reports', 'report_source'].includes(key) && value !== '') query[key] = value as string | number }; return query }
function queryFromReportFilters(): Record<string, string | number> { const query: Record<string, string | number> = { page: reportPage.value, per_page: appliedReportFilters.per_page, sort: appliedReportFilters.sort, direction: appliedReportFilters.direction }; for (const [key, value] of Object.entries(appliedReportFilters)) { if (!['per_page', 'sort', 'direction'].includes(key) && value !== '') query[key] = value as string | number }; return query }

async function fetchWorks(quiet: boolean): Promise<void> {
  if (!authStore.isInitialized || !hasPageAccess.value) return
  const revision = ++listRevision; const currentAccess = accessRevision
  if (!quiet) loading.value = true
  error.value = null
  try {
    const response = await apiFetch<ApiResponse<{ items: WorkItem[]; pagination: Pagination; summary: Summary }>>('/admin/works/reports', { query: queryFromWorkFilters() })
    if (revision !== listRevision || currentAccess !== accessRevision || !hasPageAccess.value) return
    if (!response.success || !response.data) throw new Error('invalid')
    items.value = response.data.items
    Object.assign(pagination, response.data.pagination)
    Object.assign(summary, emptySummary(), response.data.summary)
    workPage.value = response.data.pagination.current_page
    serverForbidden.value = false
    if (selectedWork.value) { const refreshed = items.value.find(work => work.id === selectedWork.value?.id); if (refreshed) selectedWork.value = refreshed }
  } catch (requestError: unknown) {
    if (revision !== listRevision || currentAccess !== accessRevision) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) { serverForbidden.value = true; clearPageData(); return }
    if (status === 422) filterError.value = localizedServerMessage(requestError, copy.value.workFiltersError)
    else if (!quiet) error.value = copy.value.genericWorksError
  } finally { if (revision === listRevision && !quiet) loading.value = false }
}
function applyFilters(): void { if (!validateWorkFilters()) return; Object.assign(appliedFilters, filters); workPage.value = 1; void fetchWorks(false) }
function resetFilters(): void { Object.assign(filters, defaultWorkFilters()); Object.assign(appliedFilters, defaultWorkFilters()); workPage.value = 1; filterError.value = null; void fetchWorks(false) }
function changeWorkPage(next: number): void { if (loading.value || next < 1 || next > pagination.last_page || next === pagination.current_page) return; workPage.value = next; void fetchWorks(false) }
function changeWorkSort(key: WorkSort): void { if (appliedFilters.sort === key) appliedFilters.direction = appliedFilters.direction === 'asc' ? 'desc' : 'asc'; else { appliedFilters.sort = key; appliedFilters.direction = ['title', 'status'].includes(key) ? 'asc' : 'desc' }; filters.sort = appliedFilters.sort; filters.direction = appliedFilters.direction; workPage.value = 1; void fetchWorks(false) }
function sortIndicator(key: WorkSort): string { return appliedFilters.sort !== key ? '↕' : appliedFilters.direction === 'asc' ? '↑' : '↓' }

function openWorkDetails(work: WorkItem): void {
  if (!canViewWorkDetails.value || actionLoading.value) return
  closeReportsDrawer()
  workDetailsOpen.value = true
  selectedWorkDetailsId.value = work.id
  selectedWorkDetailsTitle.value = work.title
  workDetail.value = null
  workDetailError.value = null
  void fetchWorkDetails(work.id)
}
async function fetchWorkDetails(workId: number): Promise<void> {
  if (!canViewWorkDetails.value || !workDetailsOpen.value) return
  const revision = ++workDetailRevision
  workDetailLoading.value = true
  workDetailError.value = null
  try {
    const response = await apiFetch<ApiResponse<WorkDetailData>>(`/admin/works/${workId}`)
    if (revision !== workDetailRevision || selectedWorkDetailsId.value !== workId || !workDetailsOpen.value || !canViewWorkDetails.value) return
    if (!response.success || !response.data) throw new Error('invalid')
    workDetail.value = response.data
    selectedWorkDetailsTitle.value = response.data.work.title
  } catch (requestError: unknown) {
    if (revision !== workDetailRevision) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) workDetailError.value = copy.value.workDetailsForbidden
    else if (status === 404) workDetailError.value = copy.value.workDetailsNotFound
    else workDetailError.value = copy.value.workDetailsGenericError
  } finally { if (revision === workDetailRevision) workDetailLoading.value = false }
}
function closeWorkDetails(): void { if (actionLoading.value) return; workDetailRevision++; workDetailsOpen.value = false; selectedWorkDetailsId.value = null; selectedWorkDetailsTitle.value = ''; workDetail.value = null; workDetailLoading.value = false; workDetailError.value = null }
function reloadWorkDetails(): void { if (selectedWorkDetailsId.value !== null) void fetchWorkDetails(selectedWorkDetailsId.value) }

function openReports(work: WorkItem): void { if (work.report_tracking.tracked_count <= 0 || actionLoading.value) return; closeWorkDetails(); selectedWork.value = work; reportsDrawerOpen.value = true; trackedReports.value = []; Object.assign(reportFilters, defaultReportFilters()); Object.assign(appliedReportFilters, defaultReportFilters()); reportPage.value = 1; closeReportDetail(); void fetchTrackedReports(false) }
function closeReportsDrawer(): void { if (actionLoading.value) return; reportsRevision++; reportsDrawerOpen.value = false; selectedWork.value = null; trackedReports.value = []; reportsLoading.value = false; reportsError.value = null; reportFilterError.value = null; Object.assign(reportPagination, emptyPagination()); closeReportDetail(); cancelAction() }
async function fetchTrackedReports(quiet: boolean): Promise<void> {
  const workId = selectedWork.value?.id
  if (!workId || !hasPageAccess.value || !reportsDrawerOpen.value) return
  const revision = ++reportsRevision
  if (!quiet) reportsLoading.value = true
  reportsError.value = null
  try {
    const response = await apiFetch<ApiResponse<{ work: WorkContext; items: ReportItem[]; pagination: Pagination }>>(`/admin/works/${workId}/reports`, { query: queryFromReportFilters() })
    if (revision !== reportsRevision || selectedWork.value?.id !== workId || !reportsDrawerOpen.value) return
    if (!response.success || !response.data) throw new Error('invalid')
    trackedReports.value = response.data.items.map(normalizeReport)
    Object.assign(reportPagination, response.data.pagination)
    reportPage.value = response.data.pagination.current_page
  } catch (requestError: unknown) {
    if (revision !== reportsRevision) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) reportsError.value = copy.value.reportsForbidden
    else if (status === 404) { reportsError.value = copy.value.workNotFound; void fetchWorks(true) }
    else if (status === 422) reportFilterError.value = localizedServerMessage(requestError, copy.value.reportFiltersError)
    else if (!quiet) reportsError.value = copy.value.genericReportsError
  } finally { if (revision === reportsRevision && !quiet) reportsLoading.value = false }
}
function applyReportFilters(): void { if (!validateReportFilters()) return; Object.assign(appliedReportFilters, reportFilters); reportPage.value = 1; void fetchTrackedReports(false) }
function resetReportFilters(): void { Object.assign(reportFilters, defaultReportFilters()); Object.assign(appliedReportFilters, defaultReportFilters()); reportPage.value = 1; reportFilterError.value = null; void fetchTrackedReports(false) }
function changeReportPage(next: number): void { if (reportsLoading.value || next < 1 || next > reportPagination.last_page || next === reportPagination.current_page) return; reportPage.value = next; void fetchTrackedReports(false) }

function openReportDetail(report: ReportItem): void { if (!canViewReportDetail.value || actionLoadingFor(report.id)) return; selectedReportId.value = report.id; reportDetailOpen.value = true; reportDetail.value = null; detailError.value = null; void fetchReportDetail(report.id) }
async function fetchReportDetail(reportId: number): Promise<void> {
  if (!canViewReportDetail.value || !reportDetailOpen.value) return
  const revision = ++detailRevision; detailLoading.value = true; detailError.value = null
  try {
    const response = await apiFetch<ApiResponse<ReportDetail>>(`/admin/works/reports/${reportId}`)
    if (revision !== detailRevision || selectedReportId.value !== reportId || !reportDetailOpen.value || !canViewReportDetail.value) return
    if (!response.success || !response.data) throw new Error('invalid')
    reportDetail.value = normalizeDetail(response.data)
  } catch (requestError: unknown) {
    if (revision !== detailRevision) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) detailError.value = copy.value.reportDetailForbidden
    else if (status === 404) {
      removeReportLocally(reportId)
      actionStatus.value = { kind: 'error', message: copy.value.reportNotFound, actionLabel: copy.value.showDetailsAction, reportId, workTitle: selectedWork.value?.title || copy.value.selectedWork, changed: null }
      closeReportDetail()
      void fetchTrackedReports(true)
    }
    else detailError.value = copy.value.genericDetailError
  } finally { if (revision === detailRevision) detailLoading.value = false }
}
function closeReportDetail(): void { detailRevision++; reportDetailOpen.value = false; selectedReportId.value = null; reportDetail.value = null; detailLoading.value = false; detailError.value = null }
function reloadSelectedDetail(): void { if (selectedReportId.value !== null) void fetchReportDetail(selectedReportId.value) }

function openAction(action: ActionName, report: ReportItem): void { const allowed = action === 'review' ? canReview.value && reviewState(report).enabled : action === 'dismiss' ? canDismiss.value && dismissState(report).enabled : canArchive.value && archiveState(report).enabled; if (!allowed || actionLoadingFor(report.id)) return; pendingAction.value = { action, report }; resolutionNotes.value = ''; resolutionNotesError.value = null; modalActionError.value = null; void nextTick(() => actionModalRef.value?.focus()) }
function cancelAction(): void { if (actionLoading.value) return; pendingAction.value = null; resolutionNotes.value = ''; resolutionNotesError.value = null; modalActionError.value = null }
async function executeAction(): Promise<void> {
  const pending = pendingAction.value; const work = selectedWork.value
  if (!pending || !work || !actionCanSubmit.value) return
  if (pending.action === 'dismiss') { const length = resolutionNotes.value.trim().length; if (length < 5 || length > 2000) { resolutionNotesError.value = copy.value.resolutionValidation; return } }
  actionLoading.value = true; actionReportId.value = pending.report.id; modalActionError.value = null; resolutionNotesError.value = null
  try {
    const options = pending.action === 'dismiss' ? { method: 'PATCH' as const, body: { resolution_notes: resolutionNotes.value.trim() } } : { method: 'PATCH' as const }
    const response = await apiFetch<ApiResponse<ActionPayload>>(`/admin/works/reports/${pending.report.id}/${pending.action}`, options)
    if (!response.success || !response.data) throw new Error('invalid')
    const safeReport = normalizeReport(response.data.report)
    updateReportLocally(safeReport)
    actionStatus.value = { kind: 'success', message: response.data.changed ? copy.value.actionSuccess : copy.value.actionNoChange, actionLabel: actionLabel(pending.action), reportId: pending.report.id, workTitle: work.title, changed: Boolean(response.data.changed) }
    const shouldReloadDetail = reportDetailOpen.value && selectedReportId.value === pending.report.id && canViewReportDetail.value
    pendingAction.value = null; resolutionNotes.value = ''; resolutionNotesError.value = null
    await Promise.all([fetchTrackedReports(true), fetchWorks(true)])
    if (shouldReloadDetail && reportDetailOpen.value) await fetchReportDetail(pending.report.id)
  } catch (requestError: unknown) {
    const status = errorStatus(requestError); let message = copy.value.genericActionError
    if (status === 422) { message = localizedServerMessage(requestError, copy.value.actionValidationError); resolutionNotesError.value = currentLocale.value === 'ar' ? fieldError(requestError, 'resolution_notes') : copy.value.resolutionValidation; modalActionError.value = message }
    else if (status === 401 || status === 403) { message = copy.value.actionForbidden; modalActionError.value = message }
    else if (status === 404) { message = copy.value.reportNotFound; removeReportLocally(pending.report.id); if (selectedReportId.value === pending.report.id) closeReportDetail(); pendingAction.value = null; resolutionNotes.value = ''; void Promise.all([fetchTrackedReports(true), fetchWorks(true)]) }
    else modalActionError.value = message
    actionStatus.value = { kind: 'error', message, actionLabel: actionLabel(pending.action), reportId: pending.report.id, workTitle: work.title, changed: null }
  } finally { actionLoading.value = false; actionReportId.value = null }
}

function normalizePerson(value: unknown): Person | null { if (!value || typeof value !== 'object') return null; const item = value as Record<string, unknown>; const id = Number(item.id); return Number.isInteger(id) && id > 0 && typeof item.name === 'string' ? { id, name: item.name } : null }
function nullableString(value: unknown): string | null { return typeof value === 'string' ? value : null }
function normalizeReport(value: unknown): ReportItem { const item = value && typeof value === 'object' ? value as Record<string, unknown> : {}; const flags = item.report_flags && typeof item.report_flags === 'object' ? item.report_flags as Record<string, unknown> : {}; const status = reportStatuses.includes(item.status as ReportStatus) ? item.status as ReportStatus : 'pending'; return { id: Number(item.id) || 0, work_id: Number(item.work_id) || selectedWork.value?.id || 0, reason_code: typeof item.reason_code === 'string' ? item.reason_code : '—', status, reporter: normalizePerson(item.reporter), reviewer: normalizePerson(item.reviewer), reviewed_at: nullableString(item.reviewed_at), dismissed_at: nullableString(item.dismissed_at), archived_at: nullableString(item.archived_at), created_at: nullableString(item.created_at), updated_at: nullableString(item.updated_at), report_flags: { is_open: Boolean(flags.is_open), is_pending: Boolean(flags.is_pending), is_under_review: Boolean(flags.is_under_review), is_dismissed: Boolean(flags.is_dismissed), is_archived: Boolean(flags.is_archived), has_reviewer: Boolean(flags.has_reviewer), needs_attention: Boolean(flags.needs_attention) } } }
function normalizeDetail(data: ReportDetail): ReportDetail { const report = normalizeReport(data.report); return { report: { ...report, details: nullableString(data.report.details), resolution_notes: nullableString(data.report.resolution_notes) }, work: { id: Number(data.work.id), title: String(data.work.title || ''), slug: String(data.work.slug || ''), status: data.work.status, visibility_status: data.work.visibility_status, is_featured: Boolean(data.work.is_featured), is_pinned: Boolean(data.work.is_pinned), legacy_reports_count: Number(data.work.legacy_reports_count) || 0, tracked_reports_count: Number(data.work.tracked_reports_count) || 0 }, field_access: { can_view_report_details: Boolean(data.field_access.can_view_report_details), can_view_resolution_notes: Boolean(data.field_access.can_view_resolution_notes) } } }
function updateReportLocally(report: ReportItem): void { const index = trackedReports.value.findIndex(item => item.id === report.id); if (index >= 0) trackedReports.value.splice(index, 1, report) }
function removeReportLocally(reportId: number): void { trackedReports.value = trackedReports.value.filter(report => report.id !== reportId); reportPagination.total = Math.max(0, reportPagination.total - 1) }

function errorStatus(error: unknown): number | null { if (!error || typeof error !== 'object') return null; const item = error as { response?: { status?: number }; statusCode?: number; status?: number }; return item.response?.status ?? item.statusCode ?? item.status ?? null }
function errorData(error: unknown): Record<string, unknown> | null { if (!error || typeof error !== 'object') return null; const item = error as { data?: unknown; response?: { _data?: unknown } }; const data = item.data ?? item.response?._data; return data && typeof data === 'object' ? data as Record<string, unknown> : null }
function serverMessage(error: unknown): string | null { const message = errorData(error)?.message; return typeof message === 'string' && message.trim() ? message : null }
function localizedServerMessage(error: unknown, fallback: string): string { return currentLocale.value === 'ar' ? serverMessage(error) || fallback : fallback }
function fieldError(error: unknown, field: string): string | null { const errors = errorData(error)?.errors; if (!errors || typeof errors !== 'object') return null; const value = (errors as Record<string, unknown>)[field]; return Array.isArray(value) && typeof value[0] === 'string' ? value[0] : null }

function clearPageData(): void { listRevision++; items.value = []; Object.assign(summary, emptySummary()); Object.assign(pagination, emptyPagination()); loading.value = false; error.value = null; filterError.value = null; closeReportsDrawer(); closeWorkDetails() }
function syncAccess(): void { if (!mounted) return; accessRevision++; serverForbidden.value = false; if (!authStore.isInitialized || !hasPageAccess.value) { loadedSignature = null; clearPageData(); return }; if (loadedSignature === authSignature.value) return; loadedSignature = authSignature.value; void fetchWorks(false) }
function handleEscape(event: KeyboardEvent): void { if (event.key !== 'Escape') return; if (pendingAction.value) { if (!actionLoading.value) cancelAction(); return }; if (reportDetailOpen.value) { if (!actionLoading.value) closeReportDetail(); return }; if (reportsDrawerOpen.value) { if (!actionLoading.value) closeReportsDrawer(); return }; if (workDetailsOpen.value && !actionLoading.value) closeWorkDetails() }

watch(authSignature, syncAccess, { flush: 'post' })
watch(resolutionNotes, () => { if (resolutionNotesError.value && resolutionNotes.value.trim().length >= 5 && resolutionNotes.value.trim().length <= 2000) resolutionNotesError.value = null })
watch(currentLocale, () => {
  error.value = null
  filterError.value = null
  reportsError.value = null
  reportFilterError.value = null
  detailError.value = null
  workDetailError.value = null
  modalActionError.value = null
  resolutionNotesError.value = null
  actionStatus.value = null
  if (workDetailsOpen.value && selectedWorkDetailsId.value !== null) void fetchWorkDetails(selectedWorkDetailsId.value)
  if (reportsDrawerOpen.value) void fetchTrackedReports(true)
  if (reportDetailOpen.value && selectedReportId.value !== null) void fetchReportDetail(selectedReportId.value)
})
onMounted(() => { mounted = true; window.addEventListener('keydown', handleEscape); syncAccess() })
onBeforeUnmount(() => { window.removeEventListener('keydown', handleEscape); accessRevision++; listRevision++; reportsRevision++; detailRevision++; workDetailRevision++ })
</script>

<style scoped>
.ym-works-reports-page { color: var(--ym-text); }
.ym-works-reports-hero, .ym-works-reports-filter-card, .ym-works-reports-table-card, .ym-works-reports-access-state { border: 1px solid var(--ym-card-border); border-radius: 30px; background: var(--ym-card-bg); box-shadow: var(--ym-card-shadow); }
.ym-works-reports-hero { position: relative; min-height: 270px; overflow: hidden; padding: clamp(1.35rem, 4vw, 2.35rem); color: #fff; background: linear-gradient(135deg, rgba(127,29,29,.94), rgba(15,23,42,.96) 54%, rgba(124,45,18,.92)); }
.ym-works-reports-hero__grid { position: absolute; inset: 0; opacity: .35; background-image: linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px); background-size: 32px 32px; }
.ym-works-reports-hero__glow { position: absolute; width: 220px; height: 220px; border-radius: 50%; filter: blur(12px); opacity: .34; }
.ym-works-reports-hero__glow.is-one { inset-block-start: -90px; inset-inline-end: 8%; background: #fb7185; }.ym-works-reports-hero__glow.is-two { inset-block-end: -130px; inset-inline-start: 12%; background: #f59e0b; }
.ym-works-reports-hero__content { position: relative; z-index: 1; display: flex; min-height: 200px; align-items: flex-end; justify-content: space-between; gap: 2rem; }.ym-works-reports-chips { display: flex; flex-wrap: wrap; gap: .55rem; }.ym-works-reports-chip { border: 1px solid rgba(255,255,255,.16); border-radius: 999px; background: rgba(255,255,255,.08); color: rgba(255,255,255,.82); font-size: 11px; font-weight: 900; padding: .4rem .72rem; }.ym-works-reports-chip.is-brand { color: #fda4af; }.ym-works-reports-chip.is-readonly { color: #e9d5ff; }.ym-works-reports-kicker { color: #fda4af; font-size: 12px; font-weight: 900; margin: 1rem 0 .4rem; }.ym-works-reports-hero h1 { color: #fff; font-size: clamp(2.25rem,5vw,4rem); font-weight: 950; line-height: 1.1; margin: 0; }.ym-works-reports-description { max-width: 720px; color: rgba(255,255,255,.74); font-size: 14px; line-height: 1.9; }.ym-works-reports-hero__summary { display: grid; min-width: 190px; border: 1px solid rgba(255,255,255,.16); border-radius: 24px; background: rgba(15,23,42,.42); padding: 1rem 1.15rem; }.ym-works-reports-hero__summary strong { font-size: 2rem; }.ym-works-reports-hero__summary span,.ym-works-reports-hero__summary small { color: rgba(255,255,255,.68); font-size: 11px; }
.ym-works-reports-access-state,.ym-works-reports-state,.ym-reports-detail-state { display: grid; place-items: center; gap: .7rem; min-height: 190px; padding: 2rem; text-align: center; }.ym-works-reports-access-state.is-forbidden,.is-error { color: #ef4444; }.ym-works-reports-spinner,.ym-inline-spinner { width: 24px; height: 24px; border: 3px solid rgba(99,102,241,.22); border-top-color: #6366f1; border-radius: 50%; animation: spin .75s linear infinite; }.ym-inline-spinner { width: 15px; height: 15px; display: inline-block; margin-inline-end: .4rem; vertical-align: middle; }
.ym-works-reports-notice { display: flex; gap: 1rem; align-items: flex-start; border: 1px solid rgba(245,158,11,.35); border-radius: 22px; background: rgba(245,158,11,.08); padding: 1rem 1.15rem; }.ym-works-reports-notice>span { border-radius: 999px; background: #f59e0b; color: #fff; font-weight: 900; padding: .35rem .65rem; }.ym-works-reports-notice strong { display: block; }.ym-works-reports-notice p { margin: .3rem 0 0; color: var(--ym-text-muted); line-height: 1.8; }
.ym-works-reports-summary-grid { display: grid; grid-template-columns: repeat(6,minmax(0,1fr)); gap: 1rem; }.ym-works-reports-summary-card { position: relative; overflow: hidden; display: grid; gap: .45rem; min-height: 130px; border: 1px solid var(--ym-card-border); border-radius: 22px; background: var(--ym-card-bg); padding: 1rem; }.ym-works-reports-summary-card::before { content: ''; position: absolute; inset-block: 0; inset-inline-start: 0; width: 4px; background: var(--reports-accent); }.ym-works-reports-summary-card span,.ym-works-reports-summary-card small { color: var(--ym-text-muted); font-size: 11px; }.ym-works-reports-summary-card strong { font-size: 1.7rem; }.ym-reports-secondary-summary { display: flex; flex-wrap: wrap; gap: .7rem; }.ym-reports-secondary-summary span { border: 1px solid var(--ym-card-border); border-radius: 999px; background: var(--ym-card-bg); padding: .55rem .8rem; color: var(--ym-text-muted); font-size: 12px; }.ym-reports-secondary-summary strong { color: var(--ym-text); margin-inline-start: .35rem; }
.ym-reports-action-status { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid; border-radius: 18px; padding: .85rem 1rem; }.ym-reports-action-status.is-success { border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.08); }.ym-reports-action-status.is-error { border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.08); }.ym-reports-action-status div { display: grid; gap: .25rem; }.ym-reports-action-status span,.ym-reports-action-status small { font-size: 12px; color: var(--ym-text-muted); }.ym-reports-action-status button { border: 0; background: transparent; color: inherit; font-size: 1.3rem; cursor: pointer; }
.ym-works-reports-filter-card,.ym-works-reports-table-card { overflow: hidden; }.ym-works-reports-filter-card>header,.ym-works-reports-table-card__head { display: flex; justify-content: space-between; gap: 1rem; align-items: center; border-bottom: 1px solid var(--ym-card-border); padding: 1.2rem 1.35rem; }.ym-works-reports-filter-card h2,.ym-works-reports-table-card h2 { margin: 0; font-size: 1.15rem; }.ym-works-reports-filter-card header p,.ym-works-reports-table-card header p { color: var(--ym-text-muted); margin: .3rem 0 0; font-size: 12px; }.ym-works-reports-filter-grid,.ym-tracked-filters { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: 1rem; padding: 1.25rem; }.ym-works-reports-filter-grid label,.ym-tracked-filters label,.ym-action-notes { display: grid; gap: .4rem; color: var(--ym-text-muted); font-size: 12px; font-weight: 800; }.ym-works-reports-filter-grid input,.ym-works-reports-filter-grid select,.ym-tracked-filters input,.ym-tracked-filters select,.ym-action-notes textarea { width: 100%; border: 1px solid var(--ym-input-border,var(--ym-card-border)); border-radius: 12px; background: var(--ym-input-bg,var(--ym-card-bg)); color: var(--ym-text); padding: .7rem .75rem; outline: none; }.ym-works-reports-filter-grid input:focus,.ym-works-reports-filter-grid select:focus,.ym-tracked-filters input:focus,.ym-tracked-filters select:focus,.ym-action-notes textarea:focus { border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.16); }.ym-works-reports-filter-actions,.ym-tracked-filters>div { display: flex; gap: .6rem; align-items: end; }.ym-works-reports-filter-error { margin: 0 1.25rem 1.25rem; color: #ef4444; font-size: 12px; }
.ym-works-reports-button,.ym-works-reports-details-button,.ym-report-action { border: 1px solid transparent; border-radius: 12px; padding: .65rem .85rem; font-weight: 850; cursor: pointer; transition: .18s ease; }.ym-works-reports-button:disabled,.ym-works-reports-details-button:disabled,.ym-report-action:disabled { opacity: .48; cursor: not-allowed; }.ym-works-reports-button.is-primary,.ym-works-reports-details-button { background: #6366f1; color: #fff; }.ym-works-reports-button.is-secondary { border-color: var(--ym-card-border); background: var(--ym-card-bg); color: var(--ym-text); }.ym-report-action.is-review { background: #2563eb; color: #fff; }.ym-report-action.is-dismiss { background: #d97706; color: #fff; }.ym-report-action.is-archive { background: #334155; color: #fff; }
.ym-works-reports-table-wrap { overflow-x: auto; }.ym-works-reports-table { width: 100%; min-width: 1480px; border-collapse: collapse; }.ym-works-reports-table th { background: rgba(148,163,184,.08); color: var(--ym-text-muted); font-size: 11px; text-align: start; white-space: nowrap; }.ym-works-reports-table th,.ym-works-reports-table td { border-bottom: 1px solid var(--ym-card-border); padding: .85rem; vertical-align: top; }.ym-works-reports-table td.is-title { min-width: 220px; }.ym-works-reports-table td.is-title strong,.ym-works-reports-table td.is-title code,.ym-works-reports-table td.is-title small,.ym-works-reports-table td.is-action small { display: block; margin-block: .25rem; }.ym-works-reports-table td small { color: var(--ym-text-muted); }.needs-attention-row { background: rgba(244,63,94,.035); }.ym-works-reports-badge,.ym-report-status { display: inline-flex; border-radius: 999px; background: rgba(99,102,241,.12); color: #6366f1; padding: .3rem .55rem; font-size: 11px; font-weight: 900; }.ym-report-counts,.ym-report-status-counts,.ym-report-flags { display: flex; flex-wrap: wrap; gap: .35rem; }.ym-report-counts span,.ym-report-counts button,.ym-report-status-counts span,.ym-report-flags span { border: 1px solid var(--ym-card-border); border-radius: 999px; background: transparent; padding: .3rem .5rem; font-size: 10px; white-space: nowrap; }.ym-report-counts button { cursor: pointer; }.ym-report-counts .is-legacy { color: #d97706; }.ym-report-counts .is-tracked { color: #0284c7; }.ym-report-counts .is-open,.ym-report-flags .is-alert { color: #e11d48; }.ym-report-counts .is-combined { color: #9333ea; }.ym-works-reports-sort { display: inline-flex; gap: .35rem; align-items: center; border: 0; background: transparent; color: inherit; font: inherit; font-weight: 900; cursor: pointer; }.ym-table-sort-group { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .4rem; }.ym-table-sort-group button { border: 1px solid var(--ym-card-border); border-radius: 999px; background: var(--ym-card-bg); color: var(--ym-text-muted); font-size: 9px; padding: .2rem .4rem; cursor: pointer; }.ym-work-admin-data,.ym-work-dates-counts { display: grid; gap: .35rem; min-width: 180px; color: var(--ym-text-muted); font-size: 10px; }.ym-work-admin-data strong,.ym-work-dates-counts strong { color: var(--ym-text); }.ym-work-dates-counts time { white-space: nowrap; }.ym-works-reports-table td.is-action { min-width: 180px; }.ym-works-reports-table td.is-action button { display: block; width: 100%; margin-block-end: .45rem; }
.ym-works-reports-table-state { display: grid; text-align: center; }.ym-works-reports-table-state span { color: var(--ym-text-muted); font-size: 11px; }.ym-works-reports-pagination { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; }.ym-works-reports-pagination>div { display: grid; }.ym-works-reports-pagination>div span,.ym-works-reports-pagination>div small { color: var(--ym-text-muted); font-size: 11px; }.ym-works-reports-pagination nav { display: flex; align-items: center; gap: .7rem; }
.ym-reports-detail-backdrop,.ym-action-modal-backdrop { position: fixed; z-index: 80; inset: 0; background: rgba(2,6,23,.62); backdrop-filter: blur(4px); }.ym-reports-detail-drawer { position: absolute; inset-block: 0; inset-inline-end: 0; width: min(1080px,96vw); overflow-y: auto; background: var(--ym-page-bg,var(--ym-card-bg)); color: var(--ym-text); box-shadow: -20px 0 60px rgba(0,0,0,.25); padding-bottom: 2rem; }.ym-reports-detail-drawer__head { position: sticky; z-index: 4; inset-block-start: 0; display: flex; justify-content: space-between; gap: 1rem; align-items: center; border-bottom: 1px solid var(--ym-card-border); background: var(--ym-card-bg); padding: 1.1rem 1.35rem; }.ym-reports-detail-drawer__head span { color: #6366f1; font-size: 11px; font-weight: 900; }.ym-reports-detail-drawer__head h2 { margin: .2rem 0; }.ym-reports-detail-drawer__close,.ym-report-detail-panel>header button,.ym-action-modal>header button { width: 38px; height: 38px; border: 1px solid var(--ym-card-border); border-radius: 50%; background: var(--ym-card-bg); color: var(--ym-text); font-size: 1.35rem; cursor: pointer; }.ym-tracked-work-context { display: flex; flex-wrap: wrap; gap: .65rem; padding: 1rem 1.3rem 0; }.ym-tracked-work-context span { border-radius: 999px; background: rgba(99,102,241,.1); padding: .55rem .8rem; }.ym-tracked-work-context p { width: 100%; color: #d97706; }.ym-tracked-list { display: grid; gap: 1rem; padding: 1.25rem; }.ym-tracked-report { border: 1px solid var(--ym-card-border); border-inline-start: 4px solid #6366f1; border-radius: 18px; background: var(--ym-card-bg); padding: 1rem; }.ym-tracked-report.is-dismissed { border-inline-start-color: #d97706; }.ym-tracked-report.is-archived { border-inline-start-color: #475569; }.ym-tracked-report>header { display: flex; justify-content: space-between; gap: 1rem; }.ym-tracked-report>header>div { display: flex; flex-wrap: wrap; gap: .45rem; align-items: center; }.ym-report-list-data,.ym-report-detail-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }.ym-report-list-data>div,.ym-report-detail-grid>div { border-radius: 12px; background: rgba(148,163,184,.07); padding: .65rem; }.ym-report-list-data dt,.ym-report-detail-grid dt { color: var(--ym-text-muted); font-size: 10px; }.ym-report-list-data dd,.ym-report-detail-grid dd { margin: .25rem 0 0; font-size: 12px; }.ym-report-actions { display: flex; flex-wrap: wrap; gap: .55rem; border-top: 1px solid var(--ym-card-border); padding-top: .8rem; }
.ym-report-detail-panel { position: fixed; z-index: 6; inset-block: 0; inset-inline-end: 0; width: min(620px,94vw); overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-page-bg,var(--ym-card-bg)); color: var(--ym-text); box-shadow: -15px 0 50px rgba(0,0,0,.24); padding: 1.2rem; }.ym-report-detail-panel>header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }.ym-report-detail-panel h3 { margin: .25rem 0; }.ym-report-detail-context { display: flex; flex-wrap: wrap; gap: .5rem; border-radius: 16px; background: rgba(99,102,241,.08); padding: .8rem; }.ym-report-detail-context>* { border-inline-end: 1px solid var(--ym-card-border); padding-inline-end: .5rem; }.ym-report-sensitive { display: grid; gap: .8rem; }.ym-report-sensitive section { border: 1px solid var(--ym-card-border); border-radius: 15px; padding: .9rem; }.ym-report-sensitive h4 { margin: 0 0 .5rem; }.ym-report-sensitive p { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; }.ym-report-field-access { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; color: var(--ym-text-muted); font-size: 11px; }
.ym-reports-detail-content { display: grid; gap: 1rem; padding: 1.25rem; }.ym-reports-detail-intro,.ym-reports-detail-section { border: 1px solid var(--ym-card-border); border-radius: 18px; background: var(--ym-card-bg); padding: 1rem; }.ym-reports-detail-intro>div { display: flex; flex-wrap: wrap; gap: .45rem; }.ym-reports-detail-intro h3 { margin: .75rem 0 .25rem; }.ym-reports-detail-intro p { color: var(--ym-text-muted); line-height: 1.8; }.ym-reports-detail-section>header h3 { margin: 0; }.ym-reports-detail-section>header p { color: var(--ym-text-muted); font-size: 12px; }.ym-reports-detail-access-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .65rem; }.ym-reports-detail-access-grid span { display: grid; gap: .3rem; border-radius: 12px; background: rgba(148,163,184,.08); padding: .7rem; font-size: 11px; }.ym-reports-detail-access-grid .is-allowed strong { color: #10b981; }.ym-reports-detail-access-grid .is-denied strong { color: #ef4444; }.ym-reports-detail-people { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }.ym-reports-detail-people article { display: grid; gap: .3rem; border-radius: 12px; background: rgba(148,163,184,.08); padding: .75rem; }.ym-reports-detail-people span,.ym-reports-detail-people small { color: var(--ym-text-muted); font-size: 11px; }.ym-reports-detail-media { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }.ym-reports-detail-media .is-present { color: #10b981; }.ym-reports-detail-media .is-absent { color: #64748b; }.ym-reports-detail-notes { display: grid; gap: .75rem; }.ym-reports-detail-notes>div { border-radius: 12px; background: rgba(245,158,11,.08); padding: .8rem; }.ym-reports-detail-notes dt { color: var(--ym-text-muted); font-size: 11px; }.ym-reports-detail-notes dd { margin: .35rem 0 0; white-space: pre-wrap; overflow-wrap: anywhere; }.ym-reports-detail-unavailable { color: var(--ym-text-muted); font-size: 12px; }.ym-reports-detail-section.is-private { border-color: rgba(245,158,11,.28); }
.ym-action-modal-backdrop { z-index: 100; display: grid; place-items: center; padding: 1rem; }.ym-action-modal { width: min(560px,96vw); max-height: 92vh; overflow-y: auto; border: 1px solid var(--ym-card-border); border-radius: 24px; background: var(--ym-card-bg); box-shadow: 0 25px 80px rgba(0,0,0,.35); padding: 1.2rem; outline: none; }.ym-action-modal:focus { box-shadow: 0 0 0 4px rgba(99,102,241,.3),0 25px 80px rgba(0,0,0,.35); }.ym-action-modal>header,.ym-action-modal>footer { display: flex; justify-content: space-between; gap: .7rem; align-items: center; }.ym-action-modal>header h2 { margin: .2rem 0; }.ym-action-modal>header span { color: var(--ym-text-muted); font-size: 11px; }.ym-action-modal dl { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }.ym-action-modal dl div { border-radius: 12px; background: rgba(148,163,184,.08); padding: .65rem; }.ym-action-modal dt { color: var(--ym-text-muted); font-size: 10px; }.ym-action-modal dd { margin: .25rem 0 0; }.ym-action-transition,.ym-action-warning { border-radius: 12px; background: rgba(99,102,241,.08); padding: .75rem; line-height: 1.7; }.ym-action-warning { background: rgba(245,158,11,.1); color: #d97706; }.ym-action-notes { margin-block: 1rem; }.ym-action-notes small { text-align: left; }.ym-action-notes em { color: #ef4444; font-style: normal; }.ym-action-modal>footer { justify-content: flex-end; border-top: 1px solid var(--ym-card-border); margin-top: 1rem; padding-top: 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 1200px) { .ym-works-reports-summary-grid { grid-template-columns: repeat(3,1fr); }.ym-works-reports-filter-grid,.ym-tracked-filters { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 760px) { .ym-works-reports-hero__content,.ym-works-reports-filter-card>header,.ym-works-reports-table-card__head,.ym-works-reports-pagination,.ym-tracked-report>header { align-items: stretch; flex-direction: column; }.ym-works-reports-summary-grid { grid-template-columns: repeat(2,1fr); }.ym-works-reports-filter-grid,.ym-tracked-filters { grid-template-columns: 1fr; }.ym-report-list-data,.ym-report-detail-grid,.ym-reports-detail-access-grid,.ym-reports-detail-people { grid-template-columns: repeat(2,1fr); }.ym-works-reports-table { min-width: 0; }.ym-works-reports-table thead { display: none; }.ym-works-reports-table tbody,.ym-works-reports-table tr,.ym-works-reports-table td { display: block; width: 100%; }.ym-works-reports-table tr { border-bottom: 10px solid rgba(148,163,184,.08); }.ym-works-reports-table td { border-bottom: 1px dashed var(--ym-card-border); }.ym-action-modal dl { grid-template-columns: 1fr; } }
@media (max-width: 480px) { .ym-works-reports-summary-grid,.ym-report-list-data,.ym-report-detail-grid,.ym-reports-detail-access-grid,.ym-reports-detail-people { grid-template-columns: 1fr; } }
</style>
