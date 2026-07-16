<template>
  <div class="ym-works-reports-page space-y-7" dir="rtl">
    <section class="ym-works-reports-hero">
      <div class="ym-works-reports-hero__glow is-one" />
      <div class="ym-works-reports-hero__glow is-two" />
      <div class="ym-works-reports-hero__grid" aria-hidden="true" />
      <div class="ym-works-reports-hero__content">
        <div>
          <div class="ym-works-reports-chips">
            <span class="ym-works-reports-chip is-brand">Yemen Motion</span>
            <span class="ym-works-reports-chip is-readonly">بلاغات متتبعة وإجراءات فردية</span>
          </div>
          <p class="ym-works-reports-kicker">إدارة بلاغات الأعمال ومؤشرات المخاطر</p>
          <h1>البلاغات والمخالفات</h1>
          <p class="ym-works-reports-description">
            افصل بين العداد التاريخي وسجلات البلاغات القابلة للتتبع، واقرأ تفاصيل البلاغ ونفّذ الإجراء المسموح على سجل واحد.
          </p>
        </div>
        <div class="ym-works-reports-hero__summary">
          <span>الأعمال المطابقة</span>
          <strong>{{ formatNumber(summary.total) }}</strong>
          <small>وفق الفلاتر الحالية</small>
        </div>
      </div>
    </section>

    <section v-if="authPending" class="ym-works-reports-access-state" role="status" aria-live="polite">
      <span class="ym-works-reports-spinner" aria-hidden="true" />
      <h2>جارٍ التحقق من صلاحية البلاغات</h2>
      <p>ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-reports-access-state is-forbidden" role="status">
      <span class="ym-works-reports-state__icon" aria-hidden="true">!</span>
      <h2>الوصول إلى بلاغات الأعمال غير متاح</h2>
      <p>لا يملك هذا الحساب صلاحيات القسم المطلوبة. لم يتم إرسال طلب بيانات.</p>
    </section>

    <template v-else>
      <aside class="ym-works-reports-notice" role="note">
        <span>مهم</span>
        <div>
          <strong>مصدران مختلفان لا يمثلان عدادًا موحدًا</strong>
          <p>
            العداد التاريخي محفوظ في works.reports_count، أما البلاغات المتتبعة فهي سجلات فعلية من work_reports.
            المصدران غير متزامنين، والإشارة المركبة أداة إدارية فقط وليست عددًا موحدًا موثوقًا.
          </p>
        </div>
      </aside>

      <section class="ym-works-reports-summary-grid" aria-label="ملخص مصادر البلاغات">
        <article v-for="card in primarySummaryCards" :key="card.key" class="ym-works-reports-summary-card" :style="{ '--reports-accent': card.color }">
          <span>{{ card.label }}</span>
          <strong>{{ formatNumber(card.value) }}</strong>
          <small>{{ card.hint }}</small>
        </article>
      </section>

      <section class="ym-reports-secondary-summary" aria-label="المؤشرات الثانوية">
        <span v-for="item in secondarySummaryItems" :key="item.key">
          {{ item.label }} <strong>{{ formatNumber(item.value) }}</strong>
        </span>
      </section>

      <section v-if="actionStatus" class="ym-reports-action-status" :class="'is-' + actionStatus.kind" role="status" aria-live="polite">
        <div>
          <strong>{{ actionStatus.message }}</strong>
          <span>{{ actionStatus.actionLabel }} · البلاغ #{{ actionStatus.reportId }} · {{ actionStatus.workTitle }}</span>
        </div>
        <small v-if="actionStatus.changed !== null">
          {{ actionStatus.changed ? 'تم تغيير الحالة' : 'لم تتغير الحالة' }}
        </small>
        <button type="button" aria-label="إخفاء حالة الإجراء" title="إخفاء حالة الإجراء" @click="actionStatus = null">×</button>
      </section>

      <section class="ym-works-reports-filter-card">
        <header>
          <div><h2>بحث وفلاتر الأعمال</h2><p>تؤثر هذه الفلاتر على قائمة الأعمال فقط.</p></div>
          <button type="button" class="ym-works-reports-button is-secondary" :disabled="loading" @click="resetFilters">إعادة الضبط</button>
        </header>
        <form class="ym-works-reports-filter-grid" @submit.prevent="applyFilters">
          <label class="is-search"><span>البحث</span><input v-model.trim="filters.q" type="search" minlength="2" maxlength="80" placeholder="العنوان أو slug أو الملخص" autocomplete="off" /></label>
          <label><span>حالة العمل</span><select v-model="filters.status"><option value="">الكل</option><option v-for="status in workStatuses" :key="status" :value="status">{{ workStatusLabel(status) }}</option></select></label>
          <label><span>الظهور</span><select v-model="filters.visibility_status"><option value="">الكل</option><option value="public">عام</option><option value="hidden">مخفي</option></select></label>
          <label><span>نوع الوسائط</span><input v-model.trim="filters.media_type" type="text" maxlength="40" dir="ltr" /></label>
          <label><span>معرّف المصمم</span><input v-model="filters.designer_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>معرّف المراجع</span><input v-model="filters.reviewer_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>معرّف التصنيف</span><input v-model="filters.category_id" type="number" min="1" inputmode="numeric" /></label>
          <label><span>المصدر</span><select v-model="filters.report_source"><option value="all">كل المصادر</option><option value="legacy">تاريخي</option><option value="tracked">متتبع</option><option value="both">المصدران معًا</option></select></label>
          <label><span>حالة البلاغ المتتبع</span><select v-model="filters.tracked_status"><option value="">كل الحالات</option><option v-for="status in reportStatuses" :key="status" :value="status">{{ reportStatusLabel(status) }}</option></select></label>
          <label><span>مميز</span><select v-model="filters.is_featured"><option value="">الكل</option><option value="1">نعم</option><option value="0">لا</option></select></label>
          <label><span>مثبت</span><select v-model="filters.is_pinned"><option value="">الكل</option><option value="1">نعم</option><option value="0">لا</option></select></label>
          <label><span>الحد الأدنى للإشارة</span><input v-model="filters.min_reports" type="number" min="1" max="100000" inputmode="numeric" /></label>
          <label><span>حُدّث من</span><input v-model="filters.from" type="date" /></label>
          <label><span>حُدّث إلى</span><input v-model="filters.to" type="date" /></label>
          <label><span>الفرز</span><select v-model="filters.sort"><option v-for="option in workSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label><span>الاتجاه</span><select v-model="filters.direction"><option value="desc">تنازلي</option><option value="asc">تصاعدي</option></select></label>
          <label><span>لكل صفحة</span><select v-model.number="filters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <div class="ym-works-reports-filter-actions"><button type="submit" class="ym-works-reports-button is-primary" :disabled="loading">تطبيق الفلاتر</button></div>
        </form>
        <p v-if="filterError" class="ym-works-reports-filter-error" role="alert">{{ filterError }}</p>
      </section>

      <section class="ym-works-reports-table-card">
        <header class="ym-works-reports-table-card__head">
          <div><h2>قائمة الأعمال ذات إشارات البلاغ</h2><p>العداد التاريخي معروض للتوافق فقط، والإجراءات متاحة داخل البلاغات الفردية.</p></div>
          <div class="ym-works-reports-table-state"><span>الصفحة</span><strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong></div>
        </header>

        <div v-if="loading" class="ym-works-reports-state" role="status"><span class="ym-works-reports-spinner" /><h3>جارٍ تحميل الأعمال</h3></div>
        <div v-else-if="error" class="ym-works-reports-state is-error" role="alert"><h3>تعذر تحميل الأعمال</h3><p>{{ error }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="fetchWorks(false)">إعادة المحاولة</button></div>
        <div v-else-if="items.length === 0" class="ym-works-reports-state"><h3>لا توجد نتائج مطابقة</h3><p>غيّر الفلاتر أو أعد ضبطها.</p></div>
        <div v-else class="ym-works-reports-table-wrap">
          <table class="ym-works-reports-table">
            <thead><tr><th>العمل</th><th>الحالة</th><th>المصادر</th><th>حالات السجلات المتتبعة</th><th>المؤشرات</th><th>الإجراء</th></tr></thead>
            <tbody>
              <tr v-for="work in items" :key="work.id" :class="{ 'needs-attention-row': work.report_flags.needs_attention }">
                <td class="is-title"><strong :dir="textDirection(work.title)">{{ work.title }}</strong><code dir="ltr">{{ work.slug }} · #{{ work.id }}</code><small>reports_count للتوافق: {{ formatNumber(work.reports_count) }}</small></td>
                <td><span class="ym-works-reports-badge is-status" :class="'is-' + work.status.replaceAll('_', '-')">{{ workStatusLabel(work.status) }}</span><small>{{ work.visibility_status === 'public' ? 'عام' : 'مخفي' }}</small></td>
                <td><div class="ym-report-counts"><span class="is-legacy">تاريخية <strong>{{ formatNumber(work.report_tracking.legacy_count) }}</strong></span><span class="is-tracked">متتبعة <strong>{{ formatNumber(work.report_tracking.tracked_count) }}</strong></span><span class="is-open">مفتوحة <strong>{{ formatNumber(work.report_tracking.open_count) }}</strong></span><span class="is-combined">إشارة مركبة <strong>{{ formatNumber(work.report_tracking.combined_signal_count) }}</strong></span></div></td>
                <td><div class="ym-report-status-counts"><span>انتظار {{ formatNumber(work.report_tracking.pending_count) }}</span><span>مراجعة {{ formatNumber(work.report_tracking.under_review_count) }}</span><span>مغلقة {{ formatNumber(work.report_tracking.dismissed_count) }}</span><span>مؤرشفة {{ formatNumber(work.report_tracking.archived_count) }}</span></div></td>
                <td><div class="ym-report-flags"><span v-if="work.report_tracking.has_legacy_untracked">عداد تاريخي</span><span v-if="work.report_tracking.has_tracked">سجلات متتبعة</span><span v-if="work.report_tracking.has_open_tracked" class="is-alert">يحتاج متابعة</span><span v-if="work.report_flags.visibility_risk" class="is-alert">ظاهر للعامة</span></div></td>
                <td class="is-action">
                  <button type="button" class="ym-works-reports-details-button" :disabled="work.report_tracking.tracked_count <= 0" :title="trackedButtonTitle(work)" @click="openReports(work)">عرض البلاغات المتتبعة</button>
                  <small v-if="work.report_tracking.tracked_count <= 0">لا توجد سجلات بلاغات فردية متتبعة لهذا العمل.</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <footer class="ym-works-reports-pagination">
          <div><span>إجمالي النتائج</span><strong>{{ formatNumber(pagination.total) }}</strong><small>{{ formatNumber(items.length) }} ظاهر الآن</small></div>
          <nav aria-label="صفحات الأعمال"><button type="button" class="ym-works-reports-button is-secondary" :disabled="loading || pagination.current_page <= 1" @click="changeWorkPage(pagination.current_page - 1)">السابق</button><span>صفحة {{ formatNumber(pagination.current_page) }}</span><button type="button" class="ym-works-reports-button is-secondary" :disabled="loading || pagination.current_page >= pagination.last_page" @click="changeWorkPage(pagination.current_page + 1)">التالي</button></nav>
        </footer>
      </section>
    </template>

    <div v-if="reportsDrawerOpen" class="ym-reports-detail-backdrop" role="presentation" @click.self="closeReportsDrawer">
      <section class="ym-reports-detail-drawer is-tracked" role="dialog" aria-modal="true" aria-labelledby="tracked-reports-title">
        <header class="ym-reports-detail-drawer__head">
          <div><span>سجلات البلاغات المتتبعة</span><h2 id="tracked-reports-title" :dir="textDirection(selectedWork?.title)">{{ selectedWork?.title }}</h2><code dir="ltr">{{ selectedWork?.slug }} · #{{ selectedWork?.id }}</code></div>
          <button type="button" class="ym-reports-detail-drawer__close" title="إغلاق اللوحة" aria-label="إغلاق اللوحة" :disabled="actionLoading" @click="closeReportsDrawer">×</button>
        </header>

        <div v-if="selectedWork" class="ym-tracked-work-context">
          <span>العداد التاريخي <strong>{{ formatNumber(selectedWork.report_tracking.legacy_count) }}</strong></span>
          <span>السجلات المتتبعة <strong>{{ formatNumber(selectedWork.report_tracking.tracked_count) }}</strong></span>
          <p v-if="selectedWork.report_tracking.tracked_count <= 0">لا توجد سجلات بلاغات فردية متتبعة لهذا العمل.</p>
        </div>

        <form class="ym-tracked-filters" @submit.prevent="applyReportFilters">
          <label><span>الحالة</span><select v-model="reportFilters.status"><option value="">الكل</option><option v-for="status in reportStatuses" :key="status" :value="status">{{ reportStatusLabel(status) }}</option></select></label>
          <label><span>رمز السبب</span><input v-model.trim="reportFilters.reason_code" type="text" maxlength="50" dir="ltr" autocomplete="off" /></label>
          <label><span>معرّف المبلّغ</span><input v-model="reportFilters.reporter_id" type="number" min="1" /></label>
          <label><span>معرّف المراجع</span><input v-model="reportFilters.reviewed_by" type="number" min="1" /></label>
          <label><span>من</span><input v-model="reportFilters.from" type="date" /></label>
          <label><span>إلى</span><input v-model="reportFilters.to" type="date" /></label>
          <label><span>الفرز</span><select v-model="reportFilters.sort"><option v-for="option in reportSortOptions" :key="option.value" :value="option.value">{{ option.label }}</option></select></label>
          <label><span>الاتجاه</span><select v-model="reportFilters.direction"><option value="desc">تنازلي</option><option value="asc">تصاعدي</option></select></label>
          <label><span>لكل صفحة</span><select v-model.number="reportFilters.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
          <div><button type="submit" class="ym-works-reports-button is-primary" :disabled="reportsLoading">تطبيق</button><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading" @click="resetReportFilters">مسح</button></div>
        </form>
        <p v-if="reportFilterError" class="ym-works-reports-filter-error" role="alert">{{ reportFilterError }}</p>

        <div v-if="reportsLoading" class="ym-reports-detail-state" role="status"><span class="ym-works-reports-spinner" /><h3>جارٍ تحميل البلاغات المتتبعة</h3></div>
        <div v-else-if="reportsError" class="ym-reports-detail-state is-error" role="alert"><h3>تعذر تحميل البلاغات</h3><p>{{ reportsError }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="fetchTrackedReports(false)">إعادة المحاولة</button></div>
        <div v-else-if="trackedReports.length === 0" class="ym-reports-detail-state"><h3>لا توجد سجلات مطابقة</h3><p>لا توجد سجلات بلاغات فردية متتبعة لهذا العمل وفق الفلاتر الحالية.</p></div>
        <div v-else class="ym-tracked-list">
          <article v-for="report in trackedReports" :key="report.id" class="ym-tracked-report" :class="'is-' + report.status">
            <header><div><code dir="ltr">#{{ report.id }} · {{ report.reason_code }}</code><span class="ym-report-status" :class="'is-' + report.status">{{ reportStatusLabel(report.status) }}</span></div><div class="ym-report-flags"><span v-if="report.report_flags.is_open">مفتوح</span><span v-if="report.report_flags.needs_attention" class="is-alert">يحتاج انتباه</span><span v-if="report.report_flags.has_reviewer">له مراجع</span><span v-if="report.report_flags.is_dismissed">مغلق</span><span v-if="report.report_flags.is_archived">مؤرشف</span></div></header>
            <dl class="ym-report-list-data">
              <div><dt>المبلّغ</dt><dd>{{ personLabel(report.reporter) }}</dd></div><div><dt>المراجع</dt><dd>{{ personLabel(report.reviewer) }}</dd></div>
              <div><dt>تمت المراجعة</dt><dd>{{ formatDateTime(report.reviewed_at) }}</dd></div><div><dt>أُغلق</dt><dd>{{ formatDateTime(report.dismissed_at) }}</dd></div><div><dt>أُرشف</dt><dd>{{ formatDateTime(report.archived_at) }}</dd></div>
              <div><dt>أُنشئ</dt><dd>{{ formatDateTime(report.created_at) }}</dd></div><div><dt>آخر تحديث</dt><dd>{{ formatDateTime(report.updated_at) }}</dd></div>
            </dl>
            <footer class="ym-report-actions">
              <button type="button" class="ym-works-reports-button is-secondary" :disabled="!canViewReportDetail || actionLoadingFor(report.id)" :title="canViewReportDetail ? 'عرض الحقول التفصيلية المسموحة' : 'تتطلب صلاحية admin.works.reports.detail.view'" @click="openReportDetail(report)">عرض التفاصيل</button>
              <button v-if="canReview" type="button" class="ym-report-action is-review" :disabled="!reviewState(report).enabled || actionLoadingFor(report.id)" :title="reviewState(report).title" @click="openAction('review', report)">بدء المراجعة</button>
              <button v-if="canDismiss" type="button" class="ym-report-action is-dismiss" :disabled="!dismissState(report).enabled || actionLoadingFor(report.id)" :title="dismissState(report).title" @click="openAction('dismiss', report)">{{ report.status === 'dismissed' ? 'تحديث معالجة البلاغ' : 'إغلاق البلاغ' }}</button>
              <button v-if="canArchive" type="button" class="ym-report-action is-archive" :disabled="!archiveState(report).enabled || actionLoadingFor(report.id)" :title="archiveState(report).title" @click="openAction('archive', report)">أرشفة البلاغ</button>
            </footer>
          </article>
        </div>

        <footer class="ym-works-reports-pagination is-drawer"><div><span>إجمالي السجلات</span><strong>{{ formatNumber(reportPagination.total) }}</strong></div><nav aria-label="صفحات البلاغات"><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading || reportPagination.current_page <= 1" @click="changeReportPage(reportPagination.current_page - 1)">السابق</button><span>{{ formatNumber(reportPagination.current_page) }} / {{ formatNumber(reportPagination.last_page) }}</span><button type="button" class="ym-works-reports-button is-secondary" :disabled="reportsLoading || reportPagination.current_page >= reportPagination.last_page" @click="changeReportPage(reportPagination.current_page + 1)">التالي</button></nav></footer>

        <section v-if="reportDetailOpen" class="ym-report-detail-panel" aria-labelledby="report-detail-title">
          <header><div><span>تفاصيل البلاغ المسموحة</span><h3 id="report-detail-title">البلاغ #{{ selectedReportId }}</h3></div><button type="button" :disabled="detailLoading || actionLoading" title="إغلاق التفاصيل" aria-label="إغلاق التفاصيل" @click="closeReportDetail">×</button></header>
          <div v-if="detailLoading" class="ym-reports-detail-state" role="status"><span class="ym-works-reports-spinner" /></div>
          <div v-else-if="detailError" class="ym-reports-detail-state is-error" role="alert"><p>{{ detailError }}</p><button type="button" class="ym-works-reports-button is-secondary" @click="reloadSelectedDetail">إعادة المحاولة</button></div>
          <template v-else-if="reportDetail">
            <div class="ym-report-detail-context"><strong :dir="textDirection(reportDetail.work.title)">{{ reportDetail.work.title }}</strong><code dir="ltr">{{ reportDetail.work.slug }} · #{{ reportDetail.work.id }}</code><span>{{ workStatusLabel(reportDetail.work.status) }} · {{ reportDetail.work.visibility_status === 'public' ? 'عام' : 'مخفي' }}</span><span>تاريخي {{ formatNumber(reportDetail.work.legacy_reports_count) }} · متتبع {{ formatNumber(reportDetail.work.tracked_reports_count) }}</span><span>مميز: {{ yesNo(reportDetail.work.is_featured) }} · مثبت: {{ yesNo(reportDetail.work.is_pinned) }}</span></div>
            <dl class="ym-report-detail-grid"><div><dt>المعرّف</dt><dd>#{{ reportDetail.report.id }}</dd></div><div><dt>رمز السبب</dt><dd dir="ltr">{{ reportDetail.report.reason_code }}</dd></div><div><dt>الحالة</dt><dd>{{ reportStatusLabel(reportDetail.report.status) }}</dd></div><div><dt>المبلّغ</dt><dd>{{ personLabel(reportDetail.report.reporter) }}</dd></div><div><dt>المراجع</dt><dd>{{ personLabel(reportDetail.report.reviewer) }}</dd></div><div><dt>المراجعة</dt><dd>{{ formatDateTime(reportDetail.report.reviewed_at) }}</dd></div><div><dt>الإغلاق</dt><dd>{{ formatDateTime(reportDetail.report.dismissed_at) }}</dd></div><div><dt>الأرشفة</dt><dd>{{ formatDateTime(reportDetail.report.archived_at) }}</dd></div><div><dt>الإنشاء</dt><dd>{{ formatDateTime(reportDetail.report.created_at) }}</dd></div><div><dt>التحديث</dt><dd>{{ formatDateTime(reportDetail.report.updated_at) }}</dd></div></dl>
            <div class="ym-report-flags"><span v-if="reportDetail.report.report_flags.is_open">مفتوح</span><span v-if="reportDetail.report.report_flags.needs_attention" class="is-alert">يحتاج انتباه</span><span v-if="reportDetail.report.report_flags.has_reviewer">له مراجع</span><span v-if="reportDetail.report.report_flags.is_dismissed">مغلق</span><span v-if="reportDetail.report.report_flags.is_archived">مؤرشف</span></div>
            <div class="ym-report-sensitive"><section><h4>تفاصيل البلاغ</h4><p v-if="reportDetail.field_access.can_view_report_details" :dir="textDirection(reportDetail.report.details)">{{ reportDetail.report.details || 'لا توجد تفاصيل.' }}</p><p v-else>هذا الحقل غير متاح.</p></section><section><h4>ملاحظات المعالجة</h4><p v-if="reportDetail.field_access.can_view_resolution_notes" :dir="textDirection(reportDetail.report.resolution_notes)">{{ reportDetail.report.resolution_notes || 'لا توجد ملاحظات معالجة.' }}</p><p v-else>هذا الحقل غير متاح.</p></section></div>
            <div class="ym-report-field-access"><span>عرض تفاصيل البلاغ: {{ yesNo(reportDetail.field_access.can_view_report_details) }}</span><span>عرض ملاحظات المعالجة: {{ yesNo(reportDetail.field_access.can_view_resolution_notes) }}</span></div>
          </template>
        </section>
      </section>
    </div>

    <div v-if="pendingAction" class="ym-action-modal-backdrop" role="presentation" @click.self="cancelAction">
      <section ref="actionModalRef" class="ym-action-modal" role="dialog" aria-modal="true" aria-labelledby="report-action-title" tabindex="-1">
        <header><div><span>تأكيد إجراء بلاغ فردي</span><h2 id="report-action-title">{{ actionLabel(pendingAction.action) }}</h2></div><button type="button" :disabled="actionLoading" title="إلغاء" aria-label="إلغاء" @click="cancelAction">×</button></header>
        <dl><div><dt>العمل</dt><dd :dir="textDirection(selectedWork?.title)">{{ selectedWork?.title }}</dd></div><div><dt>معرّف البلاغ</dt><dd>#{{ pendingAction.report.id }}</dd></div><div><dt>رمز السبب</dt><dd dir="ltr">{{ pendingAction.report.reason_code }}</dd></div><div><dt>الحالة الحالية</dt><dd>{{ reportStatusLabel(pendingAction.report.status) }}</dd></div></dl>
        <p class="ym-action-transition">{{ actionDescription(pendingAction.action, pendingAction.report) }}</p>
        <p v-if="pendingAction.action === 'archive'" class="ym-action-warning">لا توجد إمكانية استعادة أو إعادة فتح البلاغ في هذه المرحلة.</p>
        <label v-if="pendingAction.action === 'dismiss'" class="ym-action-notes"><span>ملاحظات معالجة البلاغ</span><textarea v-model="resolutionNotes" rows="6" minlength="5" maxlength="2000" required autofocus placeholder="اكتب سبب المعالجة أو نتيجة الإغلاق..." /><small>{{ resolutionNotes.length }} / 2000</small><em v-if="resolutionNotesError" role="alert">{{ resolutionNotesError }}</em></label>
        <p v-if="modalActionError" class="ym-works-reports-filter-error" role="alert">{{ modalActionError }}</p>
        <footer><button type="button" class="ym-works-reports-button is-secondary" :disabled="actionLoading" @click="cancelAction">إلغاء</button><button type="button" class="ym-report-action" :class="'is-' + pendingAction.action" :disabled="!actionCanSubmit" @click="executeAction"><span v-if="actionLoading" class="ym-inline-spinner" />{{ actionLoading ? 'جارٍ التنفيذ...' : 'تأكيد التنفيذ' }}</button></footer>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

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
interface WorkItem { id: number; title: string; slug: string; status: WorkStatus; visibility_status: VisibilityStatus; reports_count: number; report_tracking: Tracking; report_flags: WorkFlags }
interface ReportFlags { is_open: boolean; is_pending: boolean; is_under_review: boolean; is_dismissed: boolean; is_archived: boolean; has_reviewer: boolean; needs_attention: boolean }
interface ReportItem { id: number; work_id: number; reason_code: string; status: ReportStatus; reporter: Person | null; reviewer: Person | null; reviewed_at: string | null; dismissed_at: string | null; archived_at: string | null; created_at: string | null; updated_at: string | null; report_flags: ReportFlags }
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }
interface Summary { total: number; legacy_reports_total: number; tracked_reports_total: number; combined_report_signal_total: number; open_tracked_reports: number; pending_tracked_reports: number; under_review_tracked_reports: number; dismissed_tracked_reports: number; archived_tracked_reports: number; works_with_legacy_reports: number; works_with_tracked_reports: number; works_with_open_tracked_reports: number; works_with_both_sources: number }
interface WorkContext { id: number; title: string; slug: string; status: WorkStatus; visibility_status: VisibilityStatus; legacy_reports_count: number; tracked_reports_count: number }
interface DetailWork extends WorkContext { is_featured: boolean; is_pinned: boolean }
interface ReportDetail { report: ReportItem & { details: string | null; resolution_notes: string | null }; work: DetailWork; field_access: { can_view_report_details: boolean; can_view_resolution_notes: boolean } }
interface ApiResponse<T> { success: boolean; data: T | null; message?: string; errors?: Record<string, string[]> | null }
interface ActionPayload { action: ActionName; changed: boolean; report: unknown; work: unknown }

interface WorkFilters { q: string; status: '' | WorkStatus; visibility_status: '' | VisibilityStatus; media_type: string; designer_id: string; reviewer_id: string; category_id: string; min_reports: string; report_source: 'all' | 'legacy' | 'tracked' | 'both'; tracked_status: '' | ReportStatus; is_featured: '' | '1' | '0'; is_pinned: '' | '1' | '0'; from: string; to: string; sort: WorkSort; direction: Direction; per_page: PageSize }
interface TrackedFilters { status: '' | ReportStatus; reason_code: string; reporter_id: string; reviewed_by: string; from: string; to: string; sort: ReportSort; direction: Direction; per_page: PageSize }

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const locale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')
const workStatuses: WorkStatus[] = ['draft', 'submitted', 'in_review', 'changes_requested', 'approved', 'published', 'rejected', 'hidden', 'archived']
const reportStatuses: ReportStatus[] = ['pending', 'under_review', 'dismissed', 'archived']
const workSortOptions: Array<{ value: WorkSort; label: string }> = [
  { value: 'reports_count', label: 'العداد التاريخي' }, { value: 'combined_reports_count', label: 'الإشارة المركبة' }, { value: 'tracked_reports_count', label: 'البلاغات المتتبعة' }, { value: 'open_tracked_reports_count', label: 'البلاغات المفتوحة' }, { value: 'updated_at', label: 'آخر تحديث' }, { value: 'created_at', label: 'تاريخ الإنشاء' }, { value: 'submitted_at', label: 'تاريخ الإرسال' }, { value: 'published_at', label: 'تاريخ النشر' }, { value: 'title', label: 'العنوان' }, { value: 'status', label: 'الحالة' }, { value: 'views_count', label: 'المشاهدات' }, { value: 'likes_count', label: 'الإعجابات' }
]
const reportSortOptions: Array<{ value: ReportSort; label: string }> = [
  { value: 'created_at', label: 'تاريخ الإنشاء' }, { value: 'updated_at', label: 'آخر تحديث' }, { value: 'status', label: 'الحالة' }, { value: 'reviewed_at', label: 'تاريخ المراجعة' }, { value: 'dismissed_at', label: 'تاريخ الإغلاق' }, { value: 'archived_at', label: 'تاريخ الأرشفة' }
]

const authPending = computed(() => !authStore.isInitialized)
const hasPageAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  return ['admin', 'staff'].includes(authStore.role || '') && hasPermissions('admin.works.access', 'admin.works.reports.view', 'admin.works.reports.list')
})
const canViewReportDetail = computed(() => hasPageAccess.value && (authStore.role === 'super-admin' || hasPermissions('admin.works.reports.detail.view')))
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
let loadedSignature: string | null = null
const authSignature = computed(() => [authStore.isInitialized, authStore.isAuthenticated, authStore.role, [...authStore.permissions].sort().join(',')].join('|'))

const primarySummaryCards = computed(() => [
  { key: 'legacy', label: 'البلاغات التاريخية', value: summary.legacy_reports_total, hint: 'عداد غير متتبع', color: '#f59e0b' },
  { key: 'tracked', label: 'البلاغات المتتبعة', value: summary.tracked_reports_total, hint: 'سجلات فردية حقيقية', color: '#38bdf8' },
  { key: 'combined', label: 'الإشارة المركبة', value: summary.combined_report_signal_total, hint: 'مؤشر إداري فقط', color: '#a855f7' },
  { key: 'open', label: 'البلاغات المفتوحة', value: summary.open_tracked_reports, hint: 'انتظار وتحت المراجعة', color: '#f43f5e' },
  { key: 'pending', label: 'قيد الانتظار', value: summary.pending_tracked_reports, hint: 'تحتاج بدء المراجعة', color: '#fb7185' },
  { key: 'review', label: 'تحت المراجعة', value: summary.under_review_tracked_reports, hint: 'قيد المعالجة', color: '#6366f1' }
])
const secondarySummaryItems = computed(() => [
  { key: 'dismissed', label: 'بلاغات مغلقة', value: summary.dismissed_tracked_reports }, { key: 'archived', label: 'بلاغات مؤرشفة', value: summary.archived_tracked_reports }, { key: 'legacyWorks', label: 'أعمال لها عداد تاريخي', value: summary.works_with_legacy_reports }, { key: 'trackedWorks', label: 'أعمال لها سجلات متتبعة', value: summary.works_with_tracked_reports }, { key: 'openWorks', label: 'أعمال لها بلاغات مفتوحة', value: summary.works_with_open_tracked_reports }, { key: 'bothWorks', label: 'أعمال بالمصدرين', value: summary.works_with_both_sources }
])
const actionCanSubmit = computed(() => {
  if (!pendingAction.value || actionLoading.value) return false
  if (pendingAction.value.action !== 'dismiss') return true
  const length = resolutionNotes.value.trim().length
  return length >= 5 && length <= 2000
})

function hasPermissions(...permissions: string[]): boolean { return permissions.every(permission => authStore.permissions.includes(permission)) }
function emptyPagination(): Pagination { return { current_page: 1, per_page: 15, total: 0, last_page: 1 } }
function emptySummary(): Summary { return { total: 0, legacy_reports_total: 0, tracked_reports_total: 0, combined_report_signal_total: 0, open_tracked_reports: 0, pending_tracked_reports: 0, under_review_tracked_reports: 0, dismissed_tracked_reports: 0, archived_tracked_reports: 0, works_with_legacy_reports: 0, works_with_tracked_reports: 0, works_with_open_tracked_reports: 0, works_with_both_sources: 0 } }
function defaultWorkFilters(): WorkFilters { return { q: '', status: '', visibility_status: '', media_type: '', designer_id: '', reviewer_id: '', category_id: '', min_reports: '1', report_source: 'all', tracked_status: '', is_featured: '', is_pinned: '', from: '', to: '', sort: 'combined_reports_count', direction: 'desc', per_page: 15 } }
function defaultReportFilters(): TrackedFilters { return { status: '', reason_code: '', reporter_id: '', reviewed_by: '', from: '', to: '', sort: 'created_at', direction: 'desc', per_page: 15 } }
function formatNumber(value: number): string { return new Intl.NumberFormat(locale.value === 'ar' ? 'ar-YE' : 'en-US').format(Number.isFinite(value) ? value : 0) }
function formatDateTime(value: string | null): string { if (!value) return '—'; const date = new Date(value); return Number.isNaN(date.getTime()) ? '—' : new Intl.DateTimeFormat(locale.value === 'ar' ? 'ar-YE' : 'en-US', { dateStyle: 'medium', timeStyle: 'short' }).format(date) }
function textDirection(value: string | null | undefined): 'rtl' | 'ltr' { return /[\u0600-\u06FF]/.test(String(value ?? '')) ? 'rtl' : 'ltr' }
function yesNo(value: boolean): string { return value ? 'نعم' : 'لا' }
function personLabel(person: Person | null): string { return person ? `${person.name} · #${person.id}` : 'غير معيّن' }
function workStatusLabel(status: WorkStatus): string { return ({ draft: 'مسودة', submitted: 'مرسل', in_review: 'تحت المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' } as Record<WorkStatus, string>)[status] || status }
function reportStatusLabel(status: ReportStatus): string { return ({ pending: 'قيد الانتظار', under_review: 'تحت المراجعة', dismissed: 'مغلق', archived: 'مؤرشف' } as Record<ReportStatus, string>)[status] || status }
function actionLabel(action: ActionName): string { return ({ review: 'بدء المراجعة', dismiss: 'إغلاق البلاغ', archive: 'أرشفة البلاغ' } as Record<ActionName, string>)[action] }
function trackedButtonTitle(work: WorkItem): string { return work.report_tracking.tracked_count > 0 ? 'فتح سجلات البلاغات المتتبعة لهذا العمل' : 'لا توجد سجلات بلاغات متتبعة لهذا العمل؛ الموجود عداد تاريخي فقط.' }
function actionLoadingFor(reportId: number): boolean { return actionLoading.value && actionReportId.value === reportId }
function reviewState(report: ReportItem): { enabled: boolean; title: string } { if (report.status === 'pending') return { enabled: true, title: 'بدء مراجعة البلاغ' }; if (report.status === 'under_review' && (!report.reviewer || !report.reviewed_at)) return { enabled: true, title: 'استكمال إسناد المراجعة' }; if (report.status === 'under_review') return { enabled: false, title: 'البلاغ تحت المراجعة بالفعل.' }; if (report.status === 'dismissed') return { enabled: false, title: 'البلاغ مغلق ولا يمكن بدء مراجعته.' }; return { enabled: false, title: 'البلاغ مؤرشف.' } }
function dismissState(report: ReportItem): { enabled: boolean; title: string } { return report.status === 'archived' ? { enabled: false, title: 'لا يمكن تعديل بلاغ مؤرشف.' } : { enabled: true, title: report.status === 'dismissed' ? 'تحديث ملاحظات معالجة البلاغ' : 'إغلاق البلاغ بملاحظات معالجة' } }
function archiveState(report: ReportItem): { enabled: boolean; title: string } { if (report.status === 'dismissed') return { enabled: true, title: 'أرشفة البلاغ المغلق' }; if (report.status === 'archived') return { enabled: false, title: 'البلاغ مؤرشف بالفعل.' }; return { enabled: false, title: 'يجب إغلاق البلاغ أولًا قبل أرشفته.' } }
function actionDescription(action: ActionName, report: ReportItem): string { if (action === 'review') return report.status === 'pending' ? 'سينتقل البلاغ من قيد الانتظار إلى تحت المراجعة ويُسند للمراجع الحالي.' : 'سيكتمل إسناد المراجع أو وقت المراجعة الناقص.'; if (action === 'dismiss') return report.status === 'dismissed' ? 'سيبقى البلاغ مغلقًا وتُحدّث ملاحظات معالجته.' : 'سينتقل البلاغ إلى حالة مغلق مع حفظ ملاحظات المعالجة.'; return 'سينتقل البلاغ المغلق إلى حالة مؤرشف.' }

function positiveInteger(value: string): boolean { if (!value.trim()) return true; return /^\d+$/.test(value) && Number(value) > 0 }
function dateRangeValid(from: string, to: string): boolean { if (from && to && to < from) return false; if (!from || !to) return true; const [year, month, day] = from.split('-').map(Number); const lastDay = new Date(Date.UTC(year + 10, month, 0)).getUTCDate(); const maximum = `${year + 10}-${String(month).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`; return to <= maximum }
function validateWorkFilters(): boolean { filterError.value = null; if (filters.q.trim().length === 1) { filterError.value = 'البحث يجب أن يكون فارغًا أو حرفين على الأقل.'; return false }; if (![filters.designer_id, filters.reviewer_id, filters.category_id].every(positiveInteger)) { filterError.value = 'المعرّفات يجب أن تكون أعدادًا صحيحة موجبة.'; return false }; const minimum = Number(filters.min_reports); if (!Number.isInteger(minimum) || minimum < 1 || minimum > 100000) { filterError.value = 'الحد الأدنى للإشارة يجب أن يكون بين 1 و100000.'; return false }; if (!dateRangeValid(filters.from, filters.to)) { filterError.value = 'مدى التاريخ غير صحيح.'; return false }; return true }
function validateReportFilters(): boolean { reportFilterError.value = null; if (reportFilters.reason_code && !/^[a-z0-9_.-]+$/.test(reportFilters.reason_code)) { reportFilterError.value = 'رمز سبب البلاغ يحتوي على محارف غير مسموحة.'; return false }; if (![reportFilters.reporter_id, reportFilters.reviewed_by].every(positiveInteger)) { reportFilterError.value = 'معرّفات المبلّغ والمراجع يجب أن تكون أعدادًا صحيحة موجبة.'; return false }; if (!dateRangeValid(reportFilters.from, reportFilters.to)) { reportFilterError.value = 'مدى التاريخ غير صحيح أو يتجاوز عشر سنوات.'; return false }; return true }

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
    if (status === 422) filterError.value = serverMessage(requestError) || 'تعذر تطبيق فلاتر الأعمال.'
    else if (!quiet) error.value = 'حدث خطأ أثناء تحميل قائمة الأعمال. حاول مرة أخرى.'
  } finally { if (revision === listRevision && !quiet) loading.value = false }
}
function applyFilters(): void { if (!validateWorkFilters()) return; Object.assign(appliedFilters, filters); workPage.value = 1; void fetchWorks(false) }
function resetFilters(): void { Object.assign(filters, defaultWorkFilters()); Object.assign(appliedFilters, defaultWorkFilters()); workPage.value = 1; filterError.value = null; void fetchWorks(false) }
function changeWorkPage(next: number): void { if (loading.value || next < 1 || next > pagination.last_page || next === pagination.current_page) return; workPage.value = next; void fetchWorks(false) }

function openReports(work: WorkItem): void { if (work.report_tracking.tracked_count <= 0) return; selectedWork.value = work; reportsDrawerOpen.value = true; trackedReports.value = []; Object.assign(reportFilters, defaultReportFilters()); Object.assign(appliedReportFilters, defaultReportFilters()); reportPage.value = 1; closeReportDetail(); void fetchTrackedReports(false) }
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
    if (status === 401 || status === 403) reportsError.value = 'غير مصرح بقراءة بلاغات هذا العمل.'
    else if (status === 404) { reportsError.value = 'لم يعد العمل موجودًا.'; void fetchWorks(true) }
    else if (status === 422) reportFilterError.value = serverMessage(requestError) || 'تعذر تطبيق فلاتر البلاغات.'
    else if (!quiet) reportsError.value = 'حدث خطأ أثناء تحميل البلاغات المتتبعة.'
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
    if (status === 401 || status === 403) detailError.value = 'تفاصيل البلاغ تتطلب صلاحية مخصصة.'
    else if (status === 404) {
      removeReportLocally(reportId)
      actionStatus.value = { kind: 'error', message: 'لم يعد البلاغ موجودًا.', actionLabel: 'عرض التفاصيل', reportId, workTitle: selectedWork.value?.title || 'العمل المحدد', changed: null }
      closeReportDetail()
      void fetchTrackedReports(true)
    }
    else detailError.value = 'حدث خطأ أثناء تحميل تفاصيل البلاغ.'
  } finally { if (revision === detailRevision) detailLoading.value = false }
}
function closeReportDetail(): void { detailRevision++; reportDetailOpen.value = false; selectedReportId.value = null; reportDetail.value = null; detailLoading.value = false; detailError.value = null }
function reloadSelectedDetail(): void { if (selectedReportId.value !== null) void fetchReportDetail(selectedReportId.value) }

function openAction(action: ActionName, report: ReportItem): void { const allowed = action === 'review' ? canReview.value && reviewState(report).enabled : action === 'dismiss' ? canDismiss.value && dismissState(report).enabled : canArchive.value && archiveState(report).enabled; if (!allowed || actionLoadingFor(report.id)) return; pendingAction.value = { action, report }; resolutionNotes.value = ''; resolutionNotesError.value = null; modalActionError.value = null; void nextTick(() => actionModalRef.value?.focus()) }
function cancelAction(): void { if (actionLoading.value) return; pendingAction.value = null; resolutionNotes.value = ''; resolutionNotesError.value = null; modalActionError.value = null }
async function executeAction(): Promise<void> {
  const pending = pendingAction.value; const work = selectedWork.value
  if (!pending || !work || !actionCanSubmit.value) return
  if (pending.action === 'dismiss') { const length = resolutionNotes.value.trim().length; if (length < 5 || length > 2000) { resolutionNotesError.value = 'ملاحظات المعالجة مطلوبة ويجب أن تكون بين 5 و2000 حرف.'; return } }
  actionLoading.value = true; actionReportId.value = pending.report.id; modalActionError.value = null; resolutionNotesError.value = null
  try {
    const options = pending.action === 'dismiss' ? { method: 'PATCH' as const, body: { resolution_notes: resolutionNotes.value.trim() } } : { method: 'PATCH' as const }
    const response = await apiFetch<ApiResponse<ActionPayload>>(`/admin/works/reports/${pending.report.id}/${pending.action}`, options)
    if (!response.success || !response.data) throw new Error('invalid')
    const safeReport = normalizeReport(response.data.report)
    updateReportLocally(safeReport)
    actionStatus.value = { kind: 'success', message: response.data.changed ? 'تم تنفيذ إجراء البلاغ بنجاح' : 'لا يوجد تغيير؛ حالة البلاغ مطابقة بالفعل', actionLabel: actionLabel(pending.action), reportId: pending.report.id, workTitle: work.title, changed: Boolean(response.data.changed) }
    const shouldReloadDetail = reportDetailOpen.value && selectedReportId.value === pending.report.id && canViewReportDetail.value
    pendingAction.value = null; resolutionNotes.value = ''; resolutionNotesError.value = null
    await Promise.all([fetchTrackedReports(true), fetchWorks(true)])
    if (shouldReloadDetail && reportDetailOpen.value) await fetchReportDetail(pending.report.id)
  } catch (requestError: unknown) {
    const status = errorStatus(requestError); let message = 'حدث خطأ غير متوقع أثناء تنفيذ الإجراء.'
    if (status === 422) { message = serverMessage(requestError) || 'تعذر تنفيذ الإجراء بالقيم الحالية.'; resolutionNotesError.value = fieldError(requestError, 'resolution_notes'); modalActionError.value = message }
    else if (status === 401 || status === 403) { message = 'غير مصرح بتنفيذ هذا الإجراء.'; modalActionError.value = message }
    else if (status === 404) { message = 'لم يعد البلاغ موجودًا.'; removeReportLocally(pending.report.id); if (selectedReportId.value === pending.report.id) closeReportDetail(); pendingAction.value = null; resolutionNotes.value = ''; void Promise.all([fetchTrackedReports(true), fetchWorks(true)]) }
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
function fieldError(error: unknown, field: string): string | null { const errors = errorData(error)?.errors; if (!errors || typeof errors !== 'object') return null; const value = (errors as Record<string, unknown>)[field]; return Array.isArray(value) && typeof value[0] === 'string' ? value[0] : null }

function clearPageData(): void { listRevision++; items.value = []; Object.assign(summary, emptySummary()); Object.assign(pagination, emptyPagination()); loading.value = false; error.value = null; filterError.value = null; closeReportsDrawer() }
function syncAccess(): void { if (!mounted) return; accessRevision++; serverForbidden.value = false; if (!authStore.isInitialized || !hasPageAccess.value) { loadedSignature = null; clearPageData(); return }; if (loadedSignature === authSignature.value) return; loadedSignature = authSignature.value; void fetchWorks(false) }
function handleEscape(event: KeyboardEvent): void { if (event.key !== 'Escape') return; if (pendingAction.value) { if (!actionLoading.value) cancelAction(); return }; if (reportDetailOpen.value && !detailLoading.value) { closeReportDetail(); return }; if (reportsDrawerOpen.value && !actionLoading.value) closeReportsDrawer() }

watch(authSignature, syncAccess, { flush: 'post' })
watch(resolutionNotes, () => { if (resolutionNotesError.value && resolutionNotes.value.trim().length >= 5 && resolutionNotes.value.trim().length <= 2000) resolutionNotesError.value = null })
onMounted(() => { mounted = true; window.addEventListener('keydown', handleEscape); syncAccess() })
onBeforeUnmount(() => { window.removeEventListener('keydown', handleEscape); accessRevision++; listRevision++; reportsRevision++; detailRevision++ })
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
.ym-works-reports-table-wrap { overflow-x: auto; }.ym-works-reports-table { width: 100%; min-width: 1120px; border-collapse: collapse; }.ym-works-reports-table th { background: rgba(148,163,184,.08); color: var(--ym-text-muted); font-size: 11px; text-align: right; white-space: nowrap; }.ym-works-reports-table th,.ym-works-reports-table td { border-bottom: 1px solid var(--ym-card-border); padding: .85rem; vertical-align: top; }.ym-works-reports-table td.is-title { min-width: 220px; }.ym-works-reports-table td.is-title strong,.ym-works-reports-table td.is-title code,.ym-works-reports-table td.is-title small,.ym-works-reports-table td.is-action small { display: block; margin-block: .25rem; }.ym-works-reports-table td small { color: var(--ym-text-muted); }.needs-attention-row { background: rgba(244,63,94,.035); }.ym-works-reports-badge,.ym-report-status { display: inline-flex; border-radius: 999px; background: rgba(99,102,241,.12); color: #6366f1; padding: .3rem .55rem; font-size: 11px; font-weight: 900; }.ym-report-counts,.ym-report-status-counts,.ym-report-flags { display: flex; flex-wrap: wrap; gap: .35rem; }.ym-report-counts span,.ym-report-status-counts span,.ym-report-flags span { border: 1px solid var(--ym-card-border); border-radius: 999px; padding: .3rem .5rem; font-size: 10px; white-space: nowrap; }.ym-report-counts .is-legacy { color: #d97706; }.ym-report-counts .is-tracked { color: #0284c7; }.ym-report-counts .is-open,.ym-report-flags .is-alert { color: #e11d48; }.ym-report-counts .is-combined { color: #9333ea; }
.ym-works-reports-table-state { display: grid; text-align: center; }.ym-works-reports-table-state span { color: var(--ym-text-muted); font-size: 11px; }.ym-works-reports-pagination { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem 1.25rem; }.ym-works-reports-pagination>div { display: grid; }.ym-works-reports-pagination>div span,.ym-works-reports-pagination>div small { color: var(--ym-text-muted); font-size: 11px; }.ym-works-reports-pagination nav { display: flex; align-items: center; gap: .7rem; }
.ym-reports-detail-backdrop,.ym-action-modal-backdrop { position: fixed; z-index: 80; inset: 0; background: rgba(2,6,23,.62); backdrop-filter: blur(4px); }.ym-reports-detail-drawer { position: absolute; inset-block: 0; inset-inline-end: 0; width: min(1080px,96vw); overflow-y: auto; background: var(--ym-page-bg,#fff); box-shadow: -20px 0 60px rgba(0,0,0,.25); padding-bottom: 2rem; }.ym-reports-detail-drawer__head { position: sticky; z-index: 4; inset-block-start: 0; display: flex; justify-content: space-between; gap: 1rem; align-items: center; border-bottom: 1px solid var(--ym-card-border); background: var(--ym-card-bg); padding: 1.1rem 1.35rem; }.ym-reports-detail-drawer__head span { color: #6366f1; font-size: 11px; font-weight: 900; }.ym-reports-detail-drawer__head h2 { margin: .2rem 0; }.ym-reports-detail-drawer__close,.ym-report-detail-panel>header button,.ym-action-modal>header button { width: 38px; height: 38px; border: 1px solid var(--ym-card-border); border-radius: 50%; background: var(--ym-card-bg); color: var(--ym-text); font-size: 1.35rem; cursor: pointer; }.ym-tracked-work-context { display: flex; flex-wrap: wrap; gap: .65rem; padding: 1rem 1.3rem 0; }.ym-tracked-work-context span { border-radius: 999px; background: rgba(99,102,241,.1); padding: .55rem .8rem; }.ym-tracked-work-context p { width: 100%; color: #d97706; }.ym-tracked-list { display: grid; gap: 1rem; padding: 1.25rem; }.ym-tracked-report { border: 1px solid var(--ym-card-border); border-inline-start: 4px solid #6366f1; border-radius: 18px; background: var(--ym-card-bg); padding: 1rem; }.ym-tracked-report.is-dismissed { border-inline-start-color: #d97706; }.ym-tracked-report.is-archived { border-inline-start-color: #475569; }.ym-tracked-report>header { display: flex; justify-content: space-between; gap: 1rem; }.ym-tracked-report>header>div { display: flex; flex-wrap: wrap; gap: .45rem; align-items: center; }.ym-report-list-data,.ym-report-detail-grid { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .75rem; margin: 1rem 0; }.ym-report-list-data>div,.ym-report-detail-grid>div { border-radius: 12px; background: rgba(148,163,184,.07); padding: .65rem; }.ym-report-list-data dt,.ym-report-detail-grid dt { color: var(--ym-text-muted); font-size: 10px; }.ym-report-list-data dd,.ym-report-detail-grid dd { margin: .25rem 0 0; font-size: 12px; }.ym-report-actions { display: flex; flex-wrap: wrap; gap: .55rem; border-top: 1px solid var(--ym-card-border); padding-top: .8rem; }
.ym-report-detail-panel { position: fixed; z-index: 6; inset-block: 0; inset-inline-end: 0; width: min(620px,94vw); overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-page-bg,#fff); box-shadow: -15px 0 50px rgba(0,0,0,.24); padding: 1.2rem; }.ym-report-detail-panel>header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; }.ym-report-detail-panel h3 { margin: .25rem 0; }.ym-report-detail-context { display: flex; flex-wrap: wrap; gap: .5rem; border-radius: 16px; background: rgba(99,102,241,.08); padding: .8rem; }.ym-report-detail-context>* { border-inline-end: 1px solid var(--ym-card-border); padding-inline-end: .5rem; }.ym-report-sensitive { display: grid; gap: .8rem; }.ym-report-sensitive section { border: 1px solid var(--ym-card-border); border-radius: 15px; padding: .9rem; }.ym-report-sensitive h4 { margin: 0 0 .5rem; }.ym-report-sensitive p { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.8; }.ym-report-field-access { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1rem; color: var(--ym-text-muted); font-size: 11px; }
.ym-action-modal-backdrop { z-index: 100; display: grid; place-items: center; padding: 1rem; }.ym-action-modal { width: min(560px,96vw); max-height: 92vh; overflow-y: auto; border: 1px solid var(--ym-card-border); border-radius: 24px; background: var(--ym-card-bg); box-shadow: 0 25px 80px rgba(0,0,0,.35); padding: 1.2rem; outline: none; }.ym-action-modal:focus { box-shadow: 0 0 0 4px rgba(99,102,241,.3),0 25px 80px rgba(0,0,0,.35); }.ym-action-modal>header,.ym-action-modal>footer { display: flex; justify-content: space-between; gap: .7rem; align-items: center; }.ym-action-modal>header h2 { margin: .2rem 0; }.ym-action-modal>header span { color: var(--ym-text-muted); font-size: 11px; }.ym-action-modal dl { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; }.ym-action-modal dl div { border-radius: 12px; background: rgba(148,163,184,.08); padding: .65rem; }.ym-action-modal dt { color: var(--ym-text-muted); font-size: 10px; }.ym-action-modal dd { margin: .25rem 0 0; }.ym-action-transition,.ym-action-warning { border-radius: 12px; background: rgba(99,102,241,.08); padding: .75rem; line-height: 1.7; }.ym-action-warning { background: rgba(245,158,11,.1); color: #d97706; }.ym-action-notes { margin-block: 1rem; }.ym-action-notes small { text-align: left; }.ym-action-notes em { color: #ef4444; font-style: normal; }.ym-action-modal>footer { justify-content: flex-end; border-top: 1px solid var(--ym-card-border); margin-top: 1rem; padding-top: 1rem; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 1200px) { .ym-works-reports-summary-grid { grid-template-columns: repeat(3,1fr); }.ym-works-reports-filter-grid,.ym-tracked-filters { grid-template-columns: repeat(3,1fr); } }
@media (max-width: 760px) { .ym-works-reports-hero__content,.ym-works-reports-filter-card>header,.ym-works-reports-table-card__head,.ym-works-reports-pagination,.ym-tracked-report>header { align-items: stretch; flex-direction: column; }.ym-works-reports-summary-grid { grid-template-columns: repeat(2,1fr); }.ym-works-reports-filter-grid,.ym-tracked-filters { grid-template-columns: 1fr; }.ym-report-list-data,.ym-report-detail-grid { grid-template-columns: repeat(2,1fr); }.ym-works-reports-table { min-width: 0; }.ym-works-reports-table thead { display: none; }.ym-works-reports-table tbody,.ym-works-reports-table tr,.ym-works-reports-table td { display: block; width: 100%; }.ym-works-reports-table tr { border-bottom: 10px solid rgba(148,163,184,.08); }.ym-works-reports-table td { border-bottom: 1px dashed var(--ym-card-border); }.ym-action-modal dl { grid-template-columns: 1fr; } }
</style>
