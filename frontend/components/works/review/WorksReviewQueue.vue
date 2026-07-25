<template>
  <AdminDataSurface
    :eyebrow="text.eyebrow"
    :title="text.title"
    :description="text.description"
    :busy="loading || updating"
  >
    <template #controls>
      <div class="ym-review-queue__controls">
        <label>
          <span>{{ text.timeField }}</span>
          <select :value="timeField" @change="$emit('time-field', ($event.target as HTMLSelectElement).value)">
            <option value="submitted_at">{{ text.submittedAt }}</option>
            <option value="reviewed_at">{{ text.reviewedAt }}</option>
            <option value="updated_at">{{ text.updatedAt }}</option>
            <option value="deadline">{{ text.deadline }}</option>
          </select>
        </label>
        <button
          type="button"
          class="ym-admin-button"
          :aria-label="text.sortDirection"
          :title="text.sortDirection"
          @click="$emit('sort-time')"
        >
          <span aria-hidden="true">{{ direction === 'asc' ? '↑' : '↓' }}</span>
          {{ direction === 'asc' ? text.ascending : text.descending }}
        </button>
      </div>
    </template>

    <aside
      v-if="actionStatus"
      class="ym-review-queue__status"
      :class="actionStatus.kind === 'success' ? 'is-success' : 'is-error'"
      :role="actionStatus.kind === 'error' ? 'alert' : 'status'"
      aria-live="polite"
    >
      <div><strong>{{ actionStatus.message }}</strong><span>{{ actionStatus.actionLabel }} · {{ actionStatus.workLabel }}</span></div>
      <b v-if="actionStatus.changed !== null">{{ actionStatus.changed ? text.changed : text.unchanged }}</b>
    </aside>

    <AdminEmptyState
      v-if="loading"
      icon="◌"
      :title="text.loadingTitle"
      :description="text.loadingDescription"
    />
    <AdminEmptyState
      v-else-if="error"
      icon="!"
      tone="error"
      :title="text.errorTitle"
      :description="error"
      :action-label="text.retry"
      @action="$emit('retry')"
    />
    <div v-else-if="items.length === 0" class="ym-review-queue__empty">
      <AdminEmptyState
        icon="◇"
        :value="formatNumber(0)"
        :title="text.emptyTitle"
        :description="filtered ? text.filteredEmptyDescription : text.emptyDescription"
        :action-label="filtered ? text.reset : ''"
        @action="$emit('reset')"
      />
      <footer class="ym-review-pagination">
        <span>{{ text.visible }} <b>{{ formatNumber(0) }}</b> · {{ text.total }} <b>{{ formatNumber(pagination.total) }}</b></span>
        <nav :aria-label="text.pagination">
          <button type="button" class="ym-admin-button" disabled>{{ text.previous }}</button>
          <strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong>
          <button type="button" class="ym-admin-button" disabled>{{ text.next }}</button>
        </nav>
      </footer>
    </div>

    <template v-else>
      <div class="ym-review-queue__table">
        <table>
          <colgroup>
            <col class="is-order"><col class="is-work"><col class="is-state"><col class="is-team">
            <col class="is-time"><col class="is-signals"><col class="is-actions">
          </colgroup>
          <thead>
            <tr>
              <th :aria-sort="ariaSort('id')">
                <AdminFloatingOverlay
                  :label="text.order"
                  :description="sortDescription('id', text.order)"
                  trigger-class="ym-review-head-sort"
                  @activate="$emit('sort-column', 'id')"
                >
                  <template #trigger><span>#</span><small>{{ sortState('id') }}</small></template>
                </AdminFloatingOverlay>
              </th>
              <th :aria-sort="ariaSort('title')">
                <AdminFloatingOverlay
                  :label="text.work"
                  :description="sortDescription('title', text.work)"
                  trigger-class="ym-review-head-sort"
                  @activate="$emit('sort-column', 'title')"
                >
                  <template #trigger><span>{{ text.work }}</span><small>{{ sortState('title') }}</small></template>
                </AdminFloatingOverlay>
              </th>
              <th>{{ text.reviewState }}</th>
              <th>{{ text.assignment }}</th>
              <th :aria-sort="ariaSort(timeSortKey)">
                <AdminFloatingOverlay
                  :label="selectedTimeLabel"
                  :description="sortDescription(timeSortKey, selectedTimeLabel)"
                  trigger-class="ym-review-head-sort"
                  @activate="$emit('sort-column', timeSortKey)"
                >
                  <template #trigger><span>{{ selectedTimeLabel }}</span><small>{{ sortState(timeSortKey) }}</small></template>
                </AdminFloatingOverlay>
              </th>
              <th>{{ text.signals }}</th>
              <th>{{ text.actions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in items" :key="item.id" :class="{ 'needs-attention': item.review_flags.needs_attention }">
              <td><strong class="ym-review-row-number">{{ rowNumber(index) }}</strong></td>
              <td>
                <AdminFloatingOverlay
                  :label="item.title"
                  :aria-label="`${text.moreAbout}: ${item.title}`"
                  :close-label="text.close"
                  interactive
                >
                  <template #trigger>
                    <div class="ym-review-work">
                      <span class="ym-review-work__icon" aria-hidden="true">{{ mediaIcon(item.media_type) }}</span>
                      <div><strong dir="auto">{{ item.title }}</strong><small>{{ mediaLabel(item.media_type) }}</small></div>
                    </div>
                  </template>
                  <dl class="ym-review-overlay-list">
                    <div><dt>{{ text.slug }}</dt><dd dir="ltr">{{ item.slug }}</dd></div>
                    <div><dt>{{ text.summary }}</dt><dd dir="auto">{{ item.summary || text.noSummary }}</dd></div>
                  </dl>
                </AdminFloatingOverlay>
              </td>
              <td>
                <div class="ym-review-state-cell">
                  <span class="ym-review-badge" :class="`is-${item.status.replaceAll('_','-')}`">{{ statusLabel(item.status) }}</span>
                  <small>{{ visibilityLabel(item.visibility_status) }}</small>
                  <b>{{ queueState(item) }}</b>
                </div>
              </td>
              <td>
                <div class="ym-review-team">
                  <span><i aria-hidden="true">◈</i><b>{{ item.designer?.name || text.unassigned }}</b></span>
                  <span><i aria-hidden="true">✓</i><b>{{ item.reviewer?.name || text.unassigned }}</b></span>
                </div>
              </td>
              <td>
                <AdminFloatingOverlay
                  :label="text.timeDetails"
                  :aria-label="`${selectedTimeLabel}: ${formatTime(primaryTime(item))}`"
                  :close-label="text.close"
                  interactive
                >
                  <template #trigger>
                    <time class="ym-review-time" :datetime="primaryTime(item) || undefined">{{ formatTime(primaryTime(item)) }}</time>
                  </template>
                  <dl class="ym-review-overlay-list is-latin">
                    <div><dt>{{ text.submittedAt }}</dt><dd>{{ formatTime(item.submitted_at) }}</dd></div>
                    <div><dt>{{ text.reviewedAt }}</dt><dd>{{ formatTime(item.reviewed_at) }}</dd></div>
                    <div><dt>{{ text.updatedAt }}</dt><dd>{{ formatTime(item.updated_at) }}</dd></div>
                    <div><dt>{{ text.createdAt }}</dt><dd>{{ formatTime(item.created_at) }}</dd></div>
                    <div><dt>{{ text.deadline }}</dt><dd>{{ formatTime(deadline(item)) }}</dd></div>
                  </dl>
                </AdminFloatingOverlay>
              </td>
              <td>
                <AdminFloatingOverlay
                  :label="text.signals"
                  :aria-label="signalAria(item)"
                  :close-label="text.close"
                  interactive
                >
                  <template #trigger>
                    <div class="ym-review-signals">
                      <span :class="{ 'is-alert': item.review_flags.overdue }" :title="text.overdue">◷</span>
                      <span :class="{ 'is-alert': item.reports_count > 0 }" :title="text.reports">!</span>
                      <span :class="{ 'is-warning': item.review_flags.needs_attention }" :title="text.needsAttention">◇</span>
                      <b>{{ formatNumber(item.reports_count) }}</b>
                    </div>
                  </template>
                  <dl class="ym-review-overlay-list">
                    <div><dt>{{ text.overdue }}</dt><dd>{{ yesNo(item.review_flags.overdue) }}</dd></div>
                    <div><dt>{{ text.needsAttention }}</dt><dd>{{ yesNo(item.review_flags.needs_attention) }}</dd></div>
                    <div><dt>{{ text.assignment }}</dt><dd>{{ yesNo(item.review_flags.assigned) }}</dd></div>
                    <div><dt>{{ text.reports }}</dt><dd>{{ formatNumber(item.reports_count) }}</dd></div>
                    <div><dt>{{ text.views }}</dt><dd>{{ formatNumber(item.views_count) }}</dd></div>
                    <div><dt>{{ text.likes }}</dt><dd>{{ formatNumber(item.likes_count) }}</dd></div>
                  </dl>
                </AdminFloatingOverlay>
              </td>
              <td>
                <div class="ym-review-actions" :aria-label="`${text.actions}: ${item.title}`">
                  <AdminFloatingOverlay
                    v-for="action in actionResolver(item)"
                    :key="action.key"
                    :label="action.label"
                    :description="busyWorkId === item.id ? text.actionInProgress : action.reason"
                    :aria-label="action.enabled ? action.label : `${action.label}: ${action.reason}`"
                    :disabled="!action.enabled || busyWorkId === item.id"
                    :trigger-class="`ym-review-action is-${action.tone}`"
                    @activate="$emit('action', item, action.key)"
                  >
                    <template #trigger>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="actionPath(action.key)" /></svg>
                    </template>
                  </AdminFloatingOverlay>
                  <AdminFloatingOverlay
                    :label="text.details"
                    :description="canViewDetails ? text.detailsHint : text.detailsUnavailable"
                    :disabled="!canViewDetails"
                    trigger-class="ym-review-action is-details"
                    @activate="$emit('details', item)"
                  >
                    <template #trigger>
                      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </template>
                  </AdminFloatingOverlay>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="ym-review-queue__cards">
        <article v-for="(item, index) in items" :key="`card-${item.id}`" :class="{ 'needs-attention': item.review_flags.needs_attention }">
          <header>
            <div>
              <span class="ym-review-card-order">#{{ rowNumber(index) }}</span>
              <h3 dir="auto">{{ item.title }}</h3>
              <span>{{ mediaLabel(item.media_type) }}</span>
            </div>
            <span class="ym-review-badge" :class="`is-${item.status.replaceAll('_','-')}`">{{ statusLabel(item.status) }}</span>
          </header>
          <dl>
            <div><dt>{{ text.designer }}</dt><dd>{{ item.designer?.name || text.unassigned }}</dd></div>
            <div><dt>{{ text.reviewer }}</dt><dd>{{ item.reviewer?.name || text.unassigned }}</dd></div>
            <div><dt>{{ selectedTimeLabel }}</dt><dd class="ym-admin-latin">{{ formatTime(primaryTime(item)) }}</dd></div>
            <div><dt>{{ text.topSignal }}</dt><dd>{{ topSignal(item) }}</dd></div>
          </dl>
          <footer>
            <button type="button" class="ym-admin-button" :disabled="!canViewDetails" @click="$emit('details', item)">{{ text.details }}</button>
            <div class="ym-review-actions">
              <AdminFloatingOverlay
                v-for="action in actionResolver(item)"
                :key="action.key"
                :label="action.label"
                :description="busyWorkId === item.id ? text.actionInProgress : action.reason"
                :aria-label="action.enabled ? action.label : `${action.label}: ${action.reason}`"
                :disabled="!action.enabled || busyWorkId === item.id"
                :trigger-class="`ym-review-action is-${action.tone}`"
                @activate="$emit('action', item, action.key)"
              >
                <template #trigger>
                  <svg viewBox="0 0 24 24" aria-hidden="true"><path :d="actionPath(action.key)" /></svg>
                </template>
              </AdminFloatingOverlay>
            </div>
          </footer>
        </article>
      </div>

      <footer class="ym-review-pagination">
        <span>{{ text.visible }} <b>{{ formatNumber(items.length) }}</b> · {{ text.total }} <b>{{ formatNumber(pagination.total) }}</b></span>
        <nav :aria-label="text.pagination">
          <button type="button" class="ym-admin-button" :disabled="loading || pagination.current_page <= 1" @click="$emit('page', pagination.current_page - 1)">{{ text.previous }}</button>
          <strong>{{ formatNumber(pagination.current_page) }} / {{ formatNumber(pagination.last_page) }}</strong>
          <button type="button" class="ym-admin-button" :disabled="loading || pagination.current_page >= pagination.last_page" @click="$emit('page', pagination.current_page + 1)">{{ text.next }}</button>
        </nav>
      </footer>
    </template>
  </AdminDataSurface>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AdminDataSurface from '~/components/admin/visual/AdminDataSurface.vue'
import AdminEmptyState from '~/components/admin/visual/AdminEmptyState.vue'
import AdminFloatingOverlay from '~/components/admin/visual/AdminFloatingOverlay.vue'
import { formatYmDateTime, formatYmNumber, type YmLocale } from '~/utils/ymFormatting'

interface Person { id: number; name: string }
type ReviewStatus = 'submitted' | 'in_review' | 'changes_requested'
type VisibilityStatus = 'public' | 'hidden'
type ReviewActionKey = 'start' | 'assign_reviewer' | 'approve' | 'request_changes' | 'reject' | 'publish' | 'reopen'
type ReviewActionTone = 'primary' | 'info' | 'positive' | 'warning' | 'danger' | 'promotion' | 'neutral'
interface ReviewItem {
  id: number
  title: string
  slug: string
  summary: string | null
  status: ReviewStatus
  visibility_status: VisibilityStatus
  media_type: string | null
  designer: Person | null
  reviewer: Person | null
  reports_count: number
  views_count: number
  likes_count: number
  submitted_at: string | null
  reviewed_at: string | null
  updated_at: string | null
  created_at: string | null
  review_flags: { assigned: boolean; overdue: boolean; needs_attention: boolean }
}
interface ActionView { key: ReviewActionKey; label: string; enabled: boolean; reason: string; tone: ReviewActionTone }
interface Pagination { current_page: number; per_page: number; total: number; last_page: number }
interface ActionStatus { kind: 'success' | 'error'; message: string; changed: boolean | null; actionLabel: string; workLabel: string }

const props = defineProps<{
  items: ReviewItem[]
  pagination: Pagination
  actionStatus: ActionStatus | null
  locale: YmLocale
  loading: boolean
  updating: boolean
  error: string | null
  filtered: boolean
  canViewDetails: boolean
  busyWorkId: number | null
  direction: 'asc' | 'desc'
  sortKey: string
  timeField: string
  reviewSlaHours: number | null
  actionResolver: (item: ReviewItem) => ActionView[]
  text: Record<string, string>
}>()

defineEmits<{
  retry: []
  reset: []
  page: [page: number]
  details: [item: ReviewItem]
  action: [item: ReviewItem, key: ReviewActionKey]
  'time-field': [field: string]
  'sort-time': []
  'sort-column': [key: 'id' | 'title' | 'submitted_at' | 'updated_at']
}>()

const selectedTimeLabel = computed(() => ({
  submitted_at: props.text.submittedAt,
  reviewed_at: props.text.reviewedAt,
  updated_at: props.text.updatedAt,
  deadline: props.text.deadline
}[props.timeField] || props.text.submittedAt))
const timeSortKey = computed<'submitted_at' | 'updated_at'>(() => (
  props.timeField === 'submitted_at' || props.timeField === 'deadline'
    ? 'submitted_at'
    : 'updated_at'
))

const formatNumber = (value: number): string => formatYmNumber(value, props.locale)
const formatTime = (value: string | null): string => formatYmDateTime(value, props.locale)
const statusLabel = (status: string): string => ({
  submitted: props.locale === 'ar' ? 'قيد المراجعة' : 'Submitted',
  in_review: props.locale === 'ar' ? 'تحت المراجعة' : 'In review',
  changes_requested: props.locale === 'ar' ? 'تعديلات مطلوبة' : 'Changes requested'
}[status] || status)
const visibilityLabel = (status: string): string => status === 'public'
  ? (props.locale === 'ar' ? 'عام' : 'Public')
  : (props.locale === 'ar' ? 'مخفي' : 'Hidden')
const mediaLabel = (type: string | null): string => ({
  image: props.locale === 'ar' ? 'صورة' : 'Image',
  video: props.locale === 'ar' ? 'فيديو' : 'Video',
  gallery: props.locale === 'ar' ? 'معرض صور' : 'Gallery'
}[type || ''] || (props.locale === 'ar' ? 'غير محدد' : 'Not specified'))
const mediaIcon = (type: string | null): string => type === 'video' ? '▶' : type === 'gallery' ? '▦' : '▧'
const yesNo = (value: boolean): string => value
  ? (props.locale === 'ar' ? 'نعم' : 'Yes')
  : (props.locale === 'ar' ? 'لا' : 'No')
const rowNumber = (index: number): string => formatNumber(
  ((props.pagination.current_page - 1) * props.pagination.per_page) + index + 1
)
const sortState = (key: string): string => {
  if (props.sortKey !== key) return props.text.notSorted
  return props.direction === 'asc' ? `${props.text.ascending} ↑` : `${props.text.descending} ↓`
}
const sortDescription = (key: string, label: string): string => {
  const nextDirection = props.sortKey === key && props.direction === 'asc'
    ? props.text.descending
    : props.text.ascending
  return props.locale === 'ar'
    ? `ترتيب ${nextDirection} حسب ${label}`
    : `Sort by ${label}, ${nextDirection.toLowerCase()}`
}
const ariaSort = (key: string): 'ascending' | 'descending' | 'none' => (
  props.sortKey === key ? (props.direction === 'asc' ? 'ascending' : 'descending') : 'none'
)

function deadline(item: ReviewItem): string | null {
  if (!item.submitted_at || props.reviewSlaHours === null) return null
  const date = new Date(item.submitted_at)
  if (Number.isNaN(date.getTime())) return null
  date.setTime(date.getTime() + props.reviewSlaHours * 60 * 60 * 1000)
  return date.toISOString()
}
function primaryTime(item: ReviewItem): string | null {
  if (props.timeField === 'reviewed_at') return item.reviewed_at
  if (props.timeField === 'updated_at') return item.updated_at
  if (props.timeField === 'deadline') return deadline(item)
  return item.submitted_at
}
function queueState(item: ReviewItem): string {
  if (item.status === 'changes_requested') return props.text.decisionMade
  return item.review_flags.assigned ? props.text.assigned : props.text.awaiting
}
function signalAria(item: ReviewItem): string {
  return `${props.text.signals}: ${props.text.reports} ${formatNumber(item.reports_count)}`
}
function topSignal(item: ReviewItem): string {
  if (item.review_flags.overdue) return props.text.overdue
  if (item.reports_count > 0) return `${props.text.reports}: ${formatNumber(item.reports_count)}`
  if (item.review_flags.needs_attention) return props.text.needsAttention
  return props.text.stable
}
function actionPath(key: ReviewActionKey): string {
  return ({
    start: 'M8 5v14l11-7L8 5Z',
    assign_reviewer: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M22 11h-6',
    approve: 'm5 12 4 4L19 6',
    request_changes: 'M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5',
    reject: 'M6 6l12 12M18 6 6 18',
    publish: 'M12 16V3m0 0L7 8m5-5 5 5M5 21h14',
    reopen: 'M20 11a8 8 0 1 0-2.3 5.7M20 4v7h-7'
  })[key]
}
</script>

<style scoped>
.ym-review-queue__controls{display:flex;flex-wrap:wrap;align-items:end;gap:8px}.ym-review-queue__controls label{display:grid;gap:4px}.ym-review-queue__controls label span{color:var(--ym-admin-muted);font-size:11.5px;font-weight:800}.ym-review-queue__controls select{min-height:42px;border:1px solid var(--ym-control-border);border-radius:11px;padding:0 10px;color:var(--ym-admin-text);background:var(--ym-admin-surface-soft);font:inherit;font-size:12.5px}.ym-review-queue__status{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid color-mix(in srgb,var(--ym-admin-success) 30%,transparent);border-radius:12px;padding:10px 12px;color:var(--ym-admin-success);background:color-mix(in srgb,var(--ym-admin-success) 7%,transparent)}.ym-review-queue__status.is-error{border-color:color-mix(in srgb,var(--ym-admin-danger) 30%,transparent);color:var(--ym-admin-danger);background:color-mix(in srgb,var(--ym-admin-danger) 7%,transparent)}.ym-review-queue__status div{display:grid}.ym-review-queue__status span{font-size:11.5px}.ym-review-queue__table{min-width:0}.ym-review-queue__table table{width:100%;table-layout:fixed;border-collapse:separate;border-spacing:0 7px}.ym-review-queue__table col.is-work{width:22%}.ym-review-queue__table col.is-state{width:14%}.ym-review-queue__table col.is-team{width:17%}.ym-review-queue__table col.is-time{width:14%}.ym-review-queue__table col.is-signals{width:11%}.ym-review-queue__table col.is-actions{width:22%}.ym-review-queue__table th{height:58px;border-block:1px solid color-mix(in srgb,var(--ym-admin-accent-electric) 25%,var(--ym-admin-border));padding:8px 10px;color:var(--ym-admin-text);background:linear-gradient(100deg,color-mix(in srgb,var(--ym-admin-accent) 8%,var(--ym-admin-surface-raised)),color-mix(in srgb,var(--ym-admin-info) 4%,var(--ym-admin-surface-raised)));font-size:13.5px;font-weight:850;text-align:start}.ym-review-queue__table th:first-child{border-radius:0 13px 13px 0}.ym-dashboard-ltr .ym-review-queue__table th:first-child{border-radius:13px 0 0 13px}.ym-review-queue__table th:last-child{border-radius:13px 0 0 13px}.ym-dashboard-ltr .ym-review-queue__table th:last-child{border-radius:0 13px 13px 0}.ym-review-queue__table td{height:80px;border-block:1px solid var(--ym-admin-border);padding:9px 10px;color:var(--ym-admin-muted);background:linear-gradient(105deg,color-mix(in srgb,var(--ym-admin-accent) 3%,var(--ym-admin-surface-soft)),var(--ym-admin-surface-soft));font-size:13px;vertical-align:middle}.ym-review-queue__table td:first-child{border-inline-start:1px solid var(--ym-admin-border);border-radius:0 13px 13px 0}.ym-review-queue__table td:last-child{border-inline-end:1px solid var(--ym-admin-border);border-radius:13px 0 0 13px}.ym-dashboard-ltr .ym-review-queue__table td:first-child{border-radius:13px 0 0 13px}.ym-dashboard-ltr .ym-review-queue__table td:last-child{border-radius:0 13px 13px 0}.ym-review-queue__table tr.needs-attention td{border-color:color-mix(in srgb,var(--ym-admin-warning) 24%,var(--ym-admin-border))}.ym-review-work{display:grid;grid-template-columns:38px minmax(0,1fr);align-items:center;gap:9px}.ym-review-work__icon{display:grid;width:36px;height:36px;place-items:center;border-radius:11px;color:var(--ym-admin-info);background:color-mix(in srgb,var(--ym-admin-info) 9%,transparent)}.ym-review-work div{display:grid;min-width:0}.ym-review-work strong{overflow:hidden;color:var(--ym-admin-text);font-size:14px;font-weight:900;text-overflow:ellipsis;white-space:nowrap}.ym-review-work small{color:var(--ym-admin-muted);font-size:11.5px}.ym-review-state-cell,.ym-review-team{display:grid;gap:5px}.ym-review-state-cell small,.ym-review-state-cell b{font-size:11px}.ym-review-state-cell b{color:var(--ym-admin-info)}.ym-review-badge{display:inline-flex;width:max-content;max-width:100%;border:1px solid color-mix(in srgb,var(--ym-admin-info) 30%,transparent);border-radius:999px;padding:4px 8px;color:var(--ym-admin-info);background:color-mix(in srgb,var(--ym-admin-info) 9%,transparent);font-size:11px;font-weight:900}.ym-review-badge.is-changes-requested{border-color:color-mix(in srgb,var(--ym-admin-warning) 35%,transparent);color:var(--ym-admin-warning);background:color-mix(in srgb,var(--ym-admin-warning) 9%,transparent)}.ym-review-team span{display:grid;grid-template-columns:18px minmax(0,1fr);gap:5px}.ym-review-team i{color:var(--ym-admin-accent-electric);font-style:normal}.ym-review-team b{overflow:hidden;color:var(--ym-admin-text);font-size:12.5px;text-overflow:ellipsis;white-space:nowrap}.ym-review-time{display:inline-block;direction:ltr;unicode-bidi:isolate;color:var(--ym-admin-text);font-size:12.5px;font-weight:750;font-variant-numeric:tabular-nums}.ym-review-signals{display:flex;flex-wrap:wrap;align-items:center;gap:5px}.ym-review-signals span{display:grid;width:26px;height:26px;place-items:center;border-radius:8px;color:var(--ym-admin-muted);background:var(--ym-admin-surface-raised)}.ym-review-signals span.is-alert{color:var(--ym-admin-danger);background:color-mix(in srgb,var(--ym-admin-danger) 10%,transparent)}.ym-review-signals span.is-warning{color:var(--ym-admin-warning)}.ym-review-signals b{font-variant-numeric:tabular-nums}.ym-review-actions{display:flex;flex-wrap:wrap;gap:6px}.ym-review-action{display:grid!important;width:36px!important;height:36px;place-items:center;border:1px solid var(--ym-admin-border)!important;border-radius:10px!important;color:var(--ym-admin-text)!important;background:var(--ym-admin-surface-raised)!important}.ym-review-action svg{width:18px;fill:none;stroke:currentColor;stroke-width:1.8}.ym-review-action.is-positive{color:var(--ym-admin-success)!important}.ym-review-action.is-warning{color:var(--ym-admin-warning)!important}.ym-review-action.is-danger{color:var(--ym-admin-danger)!important}.ym-review-action.is-info,.ym-review-action.is-details{color:var(--ym-admin-info)!important}.ym-review-overlay-list{display:grid;gap:8px;margin:0}.ym-review-overlay-list>div{display:grid;gap:2px}.ym-review-overlay-list dt{color:rgba(226,232,240,.7);font-size:11px}.ym-review-overlay-list dd{margin:0;color:#fff;font-size:12.5px;overflow-wrap:anywhere}.ym-review-overlay-list.is-latin dd{direction:ltr;unicode-bidi:isolate;font-variant-numeric:tabular-nums}.ym-review-queue__cards{display:none}.ym-review-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid var(--ym-admin-border);padding-top:12px;color:var(--ym-admin-muted);font-size:12.5px}.ym-review-pagination b,.ym-review-pagination strong{color:var(--ym-admin-text);font-variant-numeric:tabular-nums}.ym-review-pagination nav{display:flex;align-items:center;gap:8px}@media(max-width:1100px){.ym-review-queue__table{display:none}.ym-review-queue__cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.ym-review-queue__cards>article{display:grid;gap:12px;border:1px solid var(--ym-admin-border);border-radius:15px;padding:14px;background:var(--ym-admin-surface-soft);box-shadow:inset 0 1px rgba(255,255,255,.05)}.ym-review-queue__cards>article.needs-attention{border-color:color-mix(in srgb,var(--ym-admin-warning) 34%,var(--ym-admin-border))}.ym-review-queue__cards header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.ym-review-queue__cards h3{margin:0;color:var(--ym-admin-text);font-size:15px;line-height:1.45}.ym-review-queue__cards header div>span{color:var(--ym-admin-muted);font-size:12px}.ym-review-queue__cards dl{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin:0}.ym-review-queue__cards dl div{display:grid;gap:3px}.ym-review-queue__cards dt{color:var(--ym-admin-muted);font-size:11.5px}.ym-review-queue__cards dd{margin:0;color:var(--ym-admin-text);font-size:13.5px;font-weight:750}.ym-review-queue__cards footer{display:flex;align-items:center;justify-content:space-between;gap:8px;border-top:1px solid var(--ym-admin-border);padding-top:10px}.ym-review-queue__cards .ym-review-action{width:40px!important;height:40px}}@media(max-width:700px){.ym-review-queue__cards{grid-template-columns:1fr}.ym-review-pagination{align-items:stretch;flex-direction:column}.ym-review-pagination nav{justify-content:space-between}.ym-review-queue__controls{display:grid;grid-template-columns:1fr 1fr}.ym-review-queue__controls select,.ym-review-queue__controls .ym-admin-button{width:100%}}@media(max-width:420px){.ym-review-queue__cards dl{grid-template-columns:1fr}.ym-review-queue__cards footer{align-items:stretch;flex-direction:column}.ym-review-pagination nav{display:grid;grid-template-columns:1fr}.ym-review-pagination nav strong{text-align:center}}@media(prefers-reduced-motion:reduce){.ym-review-queue__cards>article{animation:none}}
:global(.ym-dashboard-ltr) .ym-review-queue__table th:first-child,
:global(.ym-dashboard-ltr) .ym-review-queue__table td:first-child {
  border-radius: 13px 0 0 13px;
}

:global(.ym-dashboard-ltr) .ym-review-queue__table th:last-child,
:global(.ym-dashboard-ltr) .ym-review-queue__table td:last-child {
  border-radius: 0 13px 13px 0;
}

.ym-review-queue__table th:last-child {
  text-align: center;
}

.ym-review-queue__controls {
  gap: 10px;
}

.ym-review-queue__controls label span {
  font-size: 12px;
}

.ym-review-queue__controls select {
  min-height: 44px;
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 24%, var(--ym-control-border));
  border-radius: var(--ym-admin-radius-md);
  font-size: 13.5px;
}

.ym-review-queue__table table {
  border-spacing: 0 8px;
}

.ym-review-queue__table col.is-order {
  width: 5%;
}

.ym-review-queue__table col.is-work {
  width: 20%;
}

.ym-review-queue__table col.is-state {
  width: 13%;
}

.ym-review-queue__table col.is-team {
  width: 16%;
}

.ym-review-queue__table col.is-time {
  width: 13%;
}

.ym-review-queue__table col.is-signals {
  width: 10%;
}

.ym-review-queue__table col.is-actions {
  width: 23%;
}

.ym-review-queue__table th {
  height: 62px;
  border-block-color: color-mix(in srgb, var(--ym-admin-section-accent) 42%, var(--ym-admin-border));
  padding: 11px 12px;
  background:
    linear-gradient(
      108deg,
      color-mix(in srgb, var(--ym-admin-section-accent-strong) 12%, var(--ym-admin-surface-raised)),
      color-mix(in srgb, var(--ym-admin-section-accent) 8%, var(--ym-admin-surface-raised))
    );
  box-shadow:
    inset 0 1px rgba(255, 255, 255, .09),
    inset 0 -2px color-mix(in srgb, var(--ym-admin-section-accent) 16%, transparent);
  font-size: 14px;
  font-weight: 900;
  line-height: 1.4;
  text-align: center;
}

.ym-review-queue__table th :deep(.ym-admin-overlay) {
  display: flex;
  justify-content: center;
}

.ym-review-queue__table th :deep(.ym-review-head-sort) {
  display: inline-flex;
  width: auto;
  min-height: 40px;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 1px solid transparent;
  border-radius: 10px;
  padding: 4px 7px;
  color: var(--ym-admin-text);
  background: transparent;
  font: inherit;
  cursor: pointer;
}

.ym-review-queue__table th :deep(.ym-review-head-sort small) {
  color: var(--ym-admin-section-accent);
  font-size: 10.5px;
  font-weight: 850;
  white-space: nowrap;
}

.ym-review-queue__table th :deep(.ym-review-head-sort:hover),
.ym-review-queue__table th :deep(.ym-review-head-sort:focus-visible) {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 30%, transparent);
  background: color-mix(in srgb, var(--ym-admin-section-accent) 8%, transparent);
}

.ym-review-queue__table td {
  height: 88px;
  border-block-color: color-mix(in srgb, var(--ym-admin-border) 82%, var(--ym-admin-section-accent) 18%);
  padding: 11px 12px;
  color: color-mix(in srgb, var(--ym-admin-muted) 88%, var(--ym-admin-text) 12%);
  background:
    linear-gradient(105deg, color-mix(in srgb, var(--ym-admin-section-accent) 4%, var(--ym-admin-surface-soft)), var(--ym-admin-surface-soft));
  font-size: 14px;
  line-height: 1.5;
  transition:
    border-color var(--ym-admin-motion-fast) ease,
    background var(--ym-admin-motion-fast) ease,
    box-shadow var(--ym-admin-motion-fast) ease;
}

.ym-review-queue__table tbody tr:hover td {
  border-block-color: color-mix(in srgb, var(--ym-admin-section-accent) 44%, var(--ym-admin-border));
  background:
    linear-gradient(105deg, color-mix(in srgb, var(--ym-admin-section-accent) 10%, var(--ym-admin-surface-soft)), var(--ym-admin-surface-soft));
  box-shadow: 0 8px 20px color-mix(in srgb, var(--ym-admin-section-accent-strong) 8%, transparent);
}

.ym-review-work {
  grid-template-columns: 42px minmax(0, 1fr);
  gap: 10px;
}

.ym-review-work__icon {
  width: 40px;
  height: 40px;
  color: var(--ym-admin-section-accent);
  background: var(--ym-admin-section-accent-soft);
  font-size: 17px;
}

.ym-review-work strong {
  font-size: 15px;
  line-height: 1.45;
}

.ym-review-work small,
.ym-review-state-cell small {
  color: color-mix(in srgb, var(--ym-admin-muted) 86%, var(--ym-admin-text) 14%);
  font-size: 12.5px;
}

.ym-review-state-cell {
  justify-items: center;
}

.ym-review-state-cell b {
  color: var(--ym-admin-section-accent);
  font-size: 12px;
}

.ym-review-badge {
  padding: 5px 10px;
  font-size: 12px;
  line-height: 1.25;
}

.ym-review-team {
  gap: 7px;
}

.ym-review-team span {
  align-items: center;
}

.ym-review-team i {
  color: var(--ym-admin-section-accent);
}

.ym-review-team b,
.ym-review-time {
  font-size: 13.5px;
  line-height: 1.45;
}

.ym-review-queue__table td:first-child,
.ym-review-queue__table td:nth-child(5) {
  text-align: center;
}

.ym-review-row-number {
  display: inline-grid;
  min-width: 34px;
  min-height: 34px;
  direction: ltr;
  unicode-bidi: isolate;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 18%, var(--ym-admin-border));
  border-radius: 10px;
  color: var(--ym-admin-section-accent);
  background: color-mix(in srgb, var(--ym-admin-section-accent) 7%, var(--ym-admin-surface-raised));
  font-size: 13px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.ym-review-signals {
  justify-content: center;
  gap: 6px;
}

.ym-review-signals span {
  width: 30px;
  height: 30px;
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 14%, var(--ym-admin-border));
  font-size: 13px;
}

.ym-review-actions {
  display: grid;
  grid-template-columns: repeat(auto-fit, 40px);
  justify-content: center;
  gap: 7px;
  min-width: 0;
}

.ym-review-actions :deep(.ym-admin-overlay) {
  display: flex;
  width: 40px;
}

.ym-review-actions :deep(.ym-review-action) {
  display: grid !important;
  width: 40px !important;
  height: 40px !important;
  place-items: center;
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 18%, var(--ym-admin-border)) !important;
  border-radius: 11px !important;
  color: var(--ym-admin-text) !important;
  background: color-mix(in srgb, var(--ym-admin-section-accent) 5%, var(--ym-admin-surface-raised)) !important;
  box-shadow: inset 0 1px rgba(255, 255, 255, .07);
  transition:
    transform var(--ym-admin-motion-fast) ease,
    border-color var(--ym-admin-motion-fast) ease,
    color var(--ym-admin-motion-fast) ease,
    box-shadow var(--ym-admin-motion-fast) ease;
}

.ym-review-actions :deep(.ym-review-action svg) {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ym-review-actions :deep(.ym-review-action.is-primary),
.ym-review-actions :deep(.ym-review-action.is-info),
.ym-review-actions :deep(.ym-review-action.is-details) {
  color: var(--ym-admin-section-accent) !important;
}

.ym-review-actions :deep(.ym-review-action.is-positive) {
  color: var(--ym-admin-success) !important;
}

.ym-review-actions :deep(.ym-review-action.is-warning) {
  color: var(--ym-admin-warning) !important;
}

.ym-review-actions :deep(.ym-review-action.is-danger) {
  color: var(--ym-admin-danger) !important;
}

.ym-review-actions :deep(.ym-review-action.is-promotion) {
  color: var(--ym-admin-section-accent-secondary) !important;
}

.ym-review-actions :deep(.ym-review-action:not([aria-disabled="true"])) {
  border-color: color-mix(in srgb, currentColor 34%, var(--ym-admin-border)) !important;
  box-shadow:
    inset 0 1px rgba(255, 255, 255, .09),
    0 0 12px color-mix(in srgb, currentColor 9%, transparent);
}

.ym-review-actions :deep(.ym-review-action:hover),
.ym-review-actions :deep(.ym-review-action:focus-visible) {
  z-index: 1;
  transform: translateY(-1px);
  border-color: currentColor !important;
  box-shadow: 0 0 18px color-mix(in srgb, currentColor 18%, transparent);
}

.ym-review-actions :deep(.ym-admin-overlay__trigger[aria-disabled="true"]) {
  opacity: .58;
}

.ym-review-actions :deep(.ym-admin-overlay__trigger[aria-disabled="true"] svg) {
  opacity: .78;
}

.ym-review-queue__empty {
  display: grid;
  gap: 12px;
}

.ym-review-queue__cards > article {
  animation: ym-review-card-in var(--ym-admin-motion-normal) ease both;
  transition:
    transform var(--ym-admin-motion-fast) ease,
    border-color var(--ym-admin-motion-fast) ease,
    box-shadow var(--ym-admin-motion-fast) ease;
}

.ym-review-queue__cards > article:hover {
  transform: translateY(-1px);
  border-color: color-mix(in srgb, var(--ym-admin-accent-electric) 38%, var(--ym-admin-border));
  box-shadow: var(--ym-admin-shadow-glow);
}

.ym-review-queue__cards h3 {
  font-size: 16px;
}

.ym-review-card-order {
  display: inline-flex;
  width: max-content;
  direction: ltr;
  unicode-bidi: isolate;
  border-radius: 8px;
  margin-block-end: 4px;
  padding: 2px 7px;
  color: var(--ym-admin-section-accent) !important;
  background: color-mix(in srgb, var(--ym-admin-section-accent) 8%, transparent);
  font-size: 12px !important;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.ym-review-queue__cards header div > span,
.ym-review-queue__cards dt {
  color: color-mix(in srgb, var(--ym-admin-muted) 86%, var(--ym-admin-text) 14%);
  font-size: 12.5px;
}

.ym-review-queue__cards dd {
  font-size: 14px;
  line-height: 1.5;
}

@keyframes ym-review-card-in {
  from {
    opacity: 0;
    transform: translateY(5px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-review-queue__table td,
  .ym-review-actions :deep(.ym-review-action) {
    transition: none;
  }

  .ym-review-actions :deep(.ym-review-action:hover) {
    transform: none;
  }

  .ym-review-queue__cards > article {
    animation: none;
    transition: none;
  }

  .ym-review-queue__cards > article:hover {
    transform: none;
  }
}
</style>
