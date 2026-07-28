<template>
  <div
    class="ym-staff-page ym-admin-page"
    :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
  >
    <AdminPageHero
      :breadcrumbs="[copy.dashboard, copy.kicker]"
      :breadcrumb-label="copy.breadcrumbLabel"
      :eyebrow="copy.kicker"
      :badge="copy.permissionDriven"
      :title="copy.title"
      :description="copy.description"
    >
      <template #icon>👥</template>
      <template v-if="canCreateStaff" #actions>
        <button
          type="button"
          class="ym-staff-primary-button"
          @click="openCreateStaffModal"
        >
          <span aria-hidden="true">＋</span>
          {{ copy.createStaff }}
        </button>
      </template>
    </AdminPageHero>

    <AdminMetricStrip
      :items="metricItems"
      :locale="currentLocale"
      :aria-label="copy.metricsLabel"
      :loading="loading"
      :updating="refreshing"
    />

    <AdminPolicyBar
      :items="policyItems"
      :aria-label="copy.policyLabel"
      :close-label="copy.close"
    />

    <section class="ym-staff-workspace ym-admin-surface">
      <header class="ym-staff-workspace__head">
        <div>
          <span class="ym-staff-workspace__eyebrow">{{ copy.workspaceEyebrow }}</span>
          <h2>{{ copy.tableTitle }}</h2>
          <p>{{ copy.tableDescription }}</p>
        </div>

        <button
          v-if="canViewStaff"
          type="button"
          class="ym-staff-secondary-button"
          :disabled="loading || refreshing"
          @click="refreshStaff"
        >
          <span aria-hidden="true">↻</span>
          {{ copy.refresh }}
        </button>
      </header>

      <form
        v-if="canViewStaff"
        class="ym-staff-filters"
        role="search"
        @submit.prevent="applyFilters"
      >
        <label class="ym-staff-field is-search">
          <span>{{ copy.searchLabel }}</span>
          <div>
            <span aria-hidden="true">⌕</span>
            <input
              v-model.trim="filters.search"
              type="search"
              :placeholder="copy.searchPlaceholder"
              autocomplete="off"
            >
          </div>
        </label>

        <label class="ym-staff-field">
          <span>{{ copy.roleFilter }}</span>
          <select v-model="filters.role">
            <option value="">{{ copy.allInternalRoles }}</option>
            <option value="staff">staff</option>
            <option v-if="auth.isSuperAdmin" value="admin">admin</option>
          </select>
        </label>

        <label class="ym-staff-field">
          <span>{{ copy.sortLabel }}</span>
          <select v-model="filters.sortBy">
            <option value="id">{{ copy.sortId }}</option>
            <option value="name">{{ copy.sortName }}</option>
            <option value="email">{{ copy.sortEmail }}</option>
            <option value="created_at">{{ copy.sortCreated }}</option>
          </select>
        </label>

        <label class="ym-staff-field">
          <span>{{ copy.directionLabel }}</span>
          <select v-model="filters.sortDirection">
            <option value="asc">{{ copy.ascending }}</option>
            <option value="desc">{{ copy.descending }}</option>
          </select>
        </label>

        <div class="ym-staff-filter-actions">
          <button type="submit" class="ym-staff-primary-button">
            {{ copy.apply }}
          </button>
          <button
            type="button"
            class="ym-staff-secondary-button"
            @click="resetFilters"
          >
            {{ copy.reset }}
          </button>
        </div>
      </form>

      <AdminEmptyState
        v-if="!canViewStaff"
        icon="⛔"
        :title="copy.forbiddenTitle"
        :description="copy.forbiddenDescription"
        tone="forbidden"
      />

      <AdminEmptyState
        v-else-if="error"
        icon="!"
        :title="copy.errorTitle"
        :description="error"
        :action-label="copy.retry"
        tone="error"
        @action="fetchStaff"
      />

      <div v-else-if="loading" class="ym-staff-loading" role="status">
        <span aria-hidden="true" />
        <strong>{{ copy.loading }}</strong>
      </div>

      <AdminEmptyState
        v-else-if="staffUsers.length === 0"
        icon="◇"
        :title="copy.emptyTitle"
        :description="copy.emptyDescription"
        :action-label="hasActiveFilters ? copy.reset : ''"
        @action="resetFilters"
      />

      <div v-else class="ym-staff-table-wrap">
        <table class="ym-staff-table">
          <thead>
            <tr>
              <th>#</th>
              <th>{{ copy.colName }}</th>
              <th>{{ copy.colEmail }}</th>
              <th>{{ copy.colRoles }}</th>
              <th>{{ copy.colCreated }}</th>
              <th v-if="canViewActivity">{{ copy.colActions }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in staffUsers" :key="user.id">
              <td class="is-id">{{ user.id }}</td>
              <td>
                <strong :dir="textDirection(user.name)">{{ user.name }}</strong>
              </td>
              <td dir="ltr">
                <span class="ym-staff-email" :title="user.email">{{ user.email }}</span>
              </td>
              <td>
                <div class="ym-staff-role-list">
                  <span
                    v-for="role in user.roles"
                    :key="role"
                    class="ym-staff-role"
                    :class="`is-${role}`"
                  >
                    {{ role }}
                  </span>
                </div>
              </td>
              <td class="is-date">{{ formatDateTime(user.created_at) }}</td>
              <td v-if="canViewActivity">
                <button
                  type="button"
                  class="ym-staff-row-action"
                  @click="openActivity(user, $event)"
                >
                  <span aria-hidden="true">◷</span>
                  {{ copy.accountActivity }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer
        v-if="canViewStaff && !loading && !error && pagination.total > 0"
        class="ym-staff-pagination"
      >
        <span>
          {{ copy.pageInfo(
            pagination.current_page,
            pagination.last_page,
            pagination.total
          ) }}
        </span>

        <div>
          <button
            type="button"
            class="ym-staff-secondary-button"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >
            {{ copy.previous }}
          </button>
          <strong>{{ pagination.current_page }} / {{ pagination.last_page }}</strong>
          <button
            type="button"
            class="ym-staff-secondary-button"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            {{ copy.next }}
          </button>
        </div>
      </footer>
    </section>

    <p
      v-if="successMessage"
      class="ym-staff-toast is-success"
      role="status"
    >
      {{ successMessage }}
    </p>

    <Teleport to="body">
      <div
        v-if="createModalOpen"
        class="ym-staff-dialog-backdrop"
        :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
        :style="{ '--ym-admin-section-accent': '#06b6d4', '--ym-admin-section-accent-secondary': '#8b5cf6' }"
        role="presentation"
        @mousedown.self="closeCreateStaffModal"
      >
        <section
          ref="createDialog"
          class="ym-staff-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'ym-create-staff-title'"
          tabindex="-1"
        >
          <header>
            <div>
              <span>{{ copy.createEyebrow }}</span>
              <h2 id="ym-create-staff-title">{{ copy.createStaff }}</h2>
              <p>{{ copy.createDescription }}</p>
            </div>
            <button
              type="button"
              class="ym-staff-icon-button"
              :aria-label="copy.close"
              :disabled="savingStaff"
              @click="closeCreateStaffModal"
            >
              ×
            </button>
          </header>

          <form class="ym-staff-create-form" @submit.prevent="submitCreateStaff">
            <p v-if="createError" class="ym-staff-inline-error" role="alert">
              {{ createError }}
            </p>

            <label class="ym-staff-field">
              <span>{{ copy.formName }}</span>
              <input
                ref="firstCreateInput"
                v-model.trim="createForm.name"
                type="text"
                autocomplete="name"
                :aria-invalid="Boolean(fieldError('name'))"
              >
              <small v-if="fieldError('name')">{{ fieldError('name') }}</small>
            </label>

            <label class="ym-staff-field">
              <span>{{ copy.formEmail }}</span>
              <input
                v-model.trim="createForm.email"
                type="email"
                dir="ltr"
                autocomplete="email"
                :aria-invalid="Boolean(fieldError('email'))"
              >
              <small v-if="fieldError('email')">{{ fieldError('email') }}</small>
            </label>

            <div class="ym-staff-form-grid">
              <label class="ym-staff-field">
                <span>{{ copy.formPassword }}</span>
                <input
                  v-model="createForm.password"
                  type="password"
                  autocomplete="new-password"
                  :aria-invalid="Boolean(fieldError('password'))"
                >
                <small v-if="fieldError('password')">{{ fieldError('password') }}</small>
              </label>

              <label class="ym-staff-field">
                <span>{{ copy.formPasswordConfirmation }}</span>
                <input
                  v-model="createForm.password_confirmation"
                  type="password"
                  autocomplete="new-password"
                >
              </label>
            </div>

            <label class="ym-staff-field">
              <span>{{ copy.formRole }}</span>
              <select v-model="createForm.role">
                <option value="staff">staff</option>
                <option value="admin">admin</option>
              </select>
              <small>{{ copy.roleHelp }}</small>
              <small v-if="fieldError('role')">{{ fieldError('role') }}</small>
            </label>

            <footer>
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="savingStaff"
                @click="closeCreateStaffModal"
              >
                {{ copy.cancel }}
              </button>
              <button
                type="submit"
                class="ym-staff-primary-button"
                :disabled="savingStaff"
              >
                <span v-if="savingStaff" class="ym-staff-button-spinner" aria-hidden="true" />
                {{ savingStaff ? copy.saving : copy.save }}
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="activityOpen && selectedStaff"
        class="ym-staff-drawer-backdrop"
        :class="{ 'is-ltr': currentLocale === 'en' }"
        :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
        :style="{ '--ym-admin-section-accent': '#06b6d4', '--ym-admin-section-accent-secondary': '#8b5cf6' }"
        role="presentation"
        @mousedown.self="closeActivity"
      >
        <aside
          ref="activityDrawer"
          class="ym-staff-activity-drawer"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'ym-staff-activity-title'"
          tabindex="-1"
        >
          <header>
            <div>
              <span>{{ copy.activityEyebrow }}</span>
              <h2 id="ym-staff-activity-title">{{ copy.activityTitle }}</h2>
              <p>
                <strong :dir="textDirection(selectedStaff.name)">{{ selectedStaff.name }}</strong>
                <small dir="ltr">{{ selectedStaff.email }}</small>
              </p>
            </div>
            <button
              type="button"
              class="ym-staff-icon-button"
              :aria-label="copy.close"
              @click="closeActivity"
            >
              ×
            </button>
          </header>

          <div class="ym-staff-activity-summary">
            <span>
              <small>{{ copy.accountId }}</small>
              <strong>#{{ selectedStaff.id }}</strong>
            </span>
            <span>
              <small>{{ copy.roles }}</small>
              <strong>{{ selectedStaff.roles.join(', ') }}</strong>
            </span>
            <span>
              <small>{{ copy.eventsCount }}</small>
              <strong>{{ activityPagination.total }}</strong>
            </span>
          </div>

          <AdminEmptyState
            v-if="activityError"
            icon="!"
            :title="copy.activityErrorTitle"
            :description="activityError"
            :action-label="copy.retry"
            tone="error"
            @action="fetchActivity"
          />

          <div v-else-if="activityLoading" class="ym-staff-loading" role="status">
            <span aria-hidden="true" />
            <strong>{{ copy.activityLoading }}</strong>
          </div>

          <AdminEmptyState
            v-else-if="activityEvents.length === 0"
            icon="◷"
            :title="copy.activityEmptyTitle"
            :description="copy.activityEmptyDescription"
          />

          <ol v-else class="ym-staff-timeline">
            <li v-for="event in activityEvents" :key="event.id">
              <span class="ym-staff-timeline__dot" :class="`is-${event.outcome}`" />
              <article>
                <header>
                  <div>
                    <strong>{{ eventLabel(event) }}</strong>
                    <small>{{ formatDateTime(event.occurred_at) }}</small>
                  </div>
                  <span :class="`is-${event.outcome}`">{{ outcomeLabel(event.outcome) }}</span>
                </header>
                <dl>
                  <div>
                    <dt>{{ copy.actor }}</dt>
                    <dd>{{ actorLabel(event) }}</dd>
                  </div>
                  <div>
                    <dt>{{ copy.action }}</dt>
                    <dd>{{ event.action || '—' }}</dd>
                  </div>
                  <div v-if="event.request_id">
                    <dt>Request ID</dt>
                    <dd dir="ltr">{{ event.request_id }}</dd>
                  </div>
                </dl>
                <details v-if="metadataEntries(event).length">
                  <summary>{{ copy.safeMetadata }}</summary>
                  <ul>
                    <li
                      v-for="[key, value] in metadataEntries(event)"
                      :key="key"
                    >
                      <span>{{ key }}</span>
                      <code>{{ formatMetadataValue(value) }}</code>
                    </li>
                  </ul>
                </details>
              </article>
            </li>
          </ol>

          <footer
            v-if="!activityLoading && !activityError && activityPagination.total > 0"
            class="ym-staff-pagination"
          >
            <span>
              {{ copy.pageInfo(
                activityPagination.current_page,
                activityPagination.last_page,
                activityPagination.total
              ) }}
            </span>
            <div>
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="activityPagination.current_page <= 1"
                @click="changeActivityPage(activityPagination.current_page - 1)"
              >
                {{ copy.previous }}
              </button>
              <strong>
                {{ activityPagination.current_page }} / {{ activityPagination.last_page }}
              </strong>
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="activityPagination.current_page >= activityPagination.last_page"
                @click="changeActivityPage(activityPagination.current_page + 1)"
              >
                {{ copy.next }}
              </button>
            </div>
          </footer>
        </aside>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  reactive,
  ref,
  watch
} from 'vue'
import AdminEmptyState from '~/components/admin/visual/AdminEmptyState.vue'
import AdminMetricStrip from '~/components/admin/visual/AdminMetricStrip.vue'
import AdminPageHero from '~/components/admin/visual/AdminPageHero.vue'
import AdminPolicyBar from '~/components/admin/visual/AdminPolicyBar.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'
import { formatYmDateTime } from '~/utils/ymFormatting'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type TeamRole = '' | 'staff' | 'admin'
type StaffSortKey = 'id' | 'name' | 'email' | 'created_at'
type SortDirection = 'asc' | 'desc'
type StaffCreateRole = 'staff' | 'admin'

interface StaffUser {
  id: number
  name: string
  email: string
  roles: string[]
  created_at: string | null
}

interface Pagination<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

interface StaffSummary {
  total: number
  staff_role: number
  admin_role: number
}

interface StaffListResponse {
  success: boolean
  data: Pagination<StaffUser>
  message: string
  errors: Record<string, string[]> | null
  meta: {
    summary: StaffSummary
    available_roles: string[]
  }
}

interface StoreStaffResponse {
  success: boolean
  data: {
    user: StaffUser & { role: StaffCreateRole }
  }
  message: string
  errors: Record<string, string[]> | null
}

interface StaffActivityEvent {
  id: number
  event_type: string
  category: string
  severity: string
  actor_id: number | null
  actor_role: string | null
  target_id: number | null
  action: string | null
  outcome: string
  request_id: string | null
  correlation_id: string | null
  metadata: Record<string, unknown> | null
  occurred_at: string | null
}

interface StaffActivityResponse {
  success: boolean
  data: Pagination<StaffActivityEvent>
  message: string
  errors: Record<string, string[]> | null
  meta: {
    staff: StaffUser
  }
}

interface MetricItem {
  key: string
  label: string
  description: string
  value: number
  tone: 'violet' | 'cyan' | 'indigo' | 'amber' | 'emerald' | 'neutral' | 'rose' | 'magenta'
  icon: string
}

interface PolicyItem {
  key: string
  title: string
  state: string
  description: string
  meta?: string
  icon: string
  tone: 'info' | 'success' | 'warning' | 'neutral'
}

const { apiFetch } = useApiClient()
const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    dashboard: 'لوحة التحكم',
    breadcrumbLabel: 'مسار صفحة الموظفين',
    kicker: 'إدارة الموظفين',
    permissionDriven: 'وصول قائم على الصلاحيات',
    title: 'مركز فريق العمل',
    description: 'مساحة موحدة لإدارة الحسابات الداخلية وعرض سجل عمليات كل حساب، مع إظهار الأدوات وفق الصلاحيات الممنوحة.',
    metricsLabel: 'مؤشرات فريق العمل',
    policyLabel: 'سياسات الوصول إلى إدارة الموظفين',
    close: 'إغلاق',
    workspaceEyebrow: 'سجل الفريق',
    tableTitle: 'الحسابات الداخلية',
    tableDescription: 'يعرض الحسابات المرتبطة بدور staff أو admin، ويستبعد المدير الأعلى والحسابات الخارجية.',
    refresh: 'تحديث',
    searchLabel: 'البحث',
    searchPlaceholder: 'ابحث بالاسم أو البريد الإلكتروني',
    roleFilter: 'الدور الداخلي',
    allInternalRoles: 'جميع الأدوار الداخلية',
    sortLabel: 'الترتيب حسب',
    directionLabel: 'الاتجاه',
    sortId: 'المعرّف',
    sortName: 'الاسم',
    sortEmail: 'البريد',
    sortCreated: 'تاريخ الإنشاء',
    ascending: 'تصاعدي',
    descending: 'تنازلي',
    apply: 'تطبيق',
    reset: 'إعادة الضبط',
    forbiddenTitle: 'لا تملك صلاحية عرض الموظفين',
    forbiddenDescription: 'يتطلب فتح هذه المساحة صلاحية admin.staff.view ضمن دور داخلي.',
    errorTitle: 'تعذر تحميل فريق العمل',
    retry: 'إعادة المحاولة',
    loading: 'يتم تحميل فريق العمل...',
    emptyTitle: 'لا توجد نتائج مطابقة',
    emptyDescription: 'غيّر البحث أو الفلاتر، أو أنشئ أول حساب داخلي عند امتلاك صلاحية الإنشاء.',
    colName: 'الاسم',
    colEmail: 'البريد الإلكتروني',
    colRoles: 'الأدوار',
    colCreated: 'تاريخ الإنشاء',
    colActions: 'الإجراءات',
    accountActivity: 'سجل الحساب',
    previous: 'السابق',
    next: 'التالي',
    createStaff: 'إنشاء موظف',
    createEyebrow: 'حساب داخلي جديد',
    createDescription: 'أنشئ حسابًا بدور staff أو admin. إدارة الأدوار والصلاحيات التفصيلية ستبقى داخل نفس الصفحة في المحطة التالية.',
    formName: 'الاسم',
    formEmail: 'البريد الإلكتروني',
    formPassword: 'كلمة المرور',
    formPasswordConfirmation: 'تأكيد كلمة المرور',
    formRole: 'الدور الأولي',
    roleHelp: 'المفوّض بصلاحية الإنشاء ينشئ staff فقط؛ إنشاء admin محصور بالمدير الأعلى حتى تفعيل إدارة الأدوار.',
    cancel: 'إلغاء',
    save: 'حفظ الموظف',
    saving: 'جارٍ الحفظ...',
    createSuccess: 'تم إنشاء الموظف بنجاح.',
    createError: 'تعذر إنشاء الموظف. راجع الحقول وحاول مرة أخرى.',
    activityEyebrow: 'تتبّع الحساب',
    activityTitle: 'سجل عمليات الحساب',
    accountId: 'معرّف الحساب',
    roles: 'الأدوار الحالية',
    eventsCount: 'إجمالي الأحداث',
    activityErrorTitle: 'تعذر تحميل سجل الحساب',
    activityLoading: 'يتم تحميل سجل الحساب...',
    activityEmptyTitle: 'لا توجد عمليات مسجلة',
    activityEmptyDescription: 'سيظهر هنا إنشاء الحساب وتغييرات الوصول ومحاولات الدخول والعمليات المرتبطة به.',
    actor: 'المنفّذ',
    action: 'الإجراء',
    safeMetadata: 'البيانات الوصفية الآمنة',
    totalAccounts: 'إجمالي الحسابات',
    staffAccounts: 'دور staff',
    adminAccounts: 'دور admin',
    visibleRows: 'صفوف الصفحة',
    superAdminPolicy: 'المدير الأعلى',
    superAdminState: 'كل الصلاحيات تلقائيًا',
    superAdminDescription: 'يتجاوز Super Admin كل الصلاحيات المسجلة بواسطة Gate::before دون الاعتماد على روابط المنح.',
    delegatedPolicy: 'التفويض الدقيق',
    delegatedState: 'عرض وإنشاء وسجل منفصل',
    delegatedDescription: 'يستطيع الدور الداخلي تنفيذ العمليات التي مُنحت له فقط.',
    externalPolicy: 'الحسابات الخارجية',
    externalState: 'ممنوعة من الإدارة',
    externalDescription: 'يبقى client وdesigner ممنوعين حتى عند منح صلاحية إدارية لهما بالخطأ.',
    pageInfo: (page: number, last: number, total: number) =>
      `الصفحة ${page} من ${last} — ${total} سجل`
  },
  en: {
    dashboard: 'Dashboard',
    breadcrumbLabel: 'Staff page breadcrumb',
    kicker: 'Staff management',
    permissionDriven: 'Permission-driven access',
    title: 'Team Command Center',
    description: 'A unified workspace for internal accounts and account activity, with tools revealed by granted permissions.',
    metricsLabel: 'Team metrics',
    policyLabel: 'Staff access policies',
    close: 'Close',
    workspaceEyebrow: 'Team register',
    tableTitle: 'Internal accounts',
    tableDescription: 'Lists staff or admin accounts while excluding the super admin and external accounts.',
    refresh: 'Refresh',
    searchLabel: 'Search',
    searchPlaceholder: 'Search by name or email',
    roleFilter: 'Internal role',
    allInternalRoles: 'All internal roles',
    sortLabel: 'Sort by',
    directionLabel: 'Direction',
    sortId: 'ID',
    sortName: 'Name',
    sortEmail: 'Email',
    sortCreated: 'Created at',
    ascending: 'Ascending',
    descending: 'Descending',
    apply: 'Apply',
    reset: 'Reset',
    forbiddenTitle: 'Staff view permission required',
    forbiddenDescription: 'This workspace requires admin.staff.view on an internal role.',
    errorTitle: 'Could not load the team',
    retry: 'Retry',
    loading: 'Loading team members...',
    emptyTitle: 'No matching accounts',
    emptyDescription: 'Change the filters or create the first internal account when creation is allowed.',
    colName: 'Name',
    colEmail: 'Email',
    colRoles: 'Roles',
    colCreated: 'Created',
    colActions: 'Actions',
    accountActivity: 'Account activity',
    previous: 'Previous',
    next: 'Next',
    createStaff: 'Create staff',
    createEyebrow: 'New internal account',
    createDescription: 'Create a staff or admin account. Detailed role and permission management remains in this page for the next station.',
    formName: 'Name',
    formEmail: 'Email',
    formPassword: 'Password',
    formPasswordConfirmation: 'Confirm password',
    formRole: 'Initial role',
    roleHelp: 'Delegated creators can create staff only; admin creation remains limited to Super Admin until role management is enabled.',
    cancel: 'Cancel',
    save: 'Save staff',
    saving: 'Saving...',
    createSuccess: 'Staff member created successfully.',
    createError: 'Could not create staff. Review the fields and try again.',
    activityEyebrow: 'Account trace',
    activityTitle: 'Account activity',
    accountId: 'Account ID',
    roles: 'Current roles',
    eventsCount: 'Total events',
    activityErrorTitle: 'Could not load account activity',
    activityLoading: 'Loading account activity...',
    activityEmptyTitle: 'No recorded activity',
    activityEmptyDescription: 'Account creation, access changes, authentication events, and related actions will appear here.',
    actor: 'Actor',
    action: 'Action',
    safeMetadata: 'Safe metadata',
    totalAccounts: 'Total accounts',
    staffAccounts: 'staff role',
    adminAccounts: 'admin role',
    visibleRows: 'Visible rows',
    superAdminPolicy: 'Super Admin',
    superAdminState: 'All permissions automatically',
    superAdminDescription: 'Super Admin bypasses all registered abilities through Gate::before.',
    delegatedPolicy: 'Granular delegation',
    delegatedState: 'Separate view, create, and activity',
    delegatedDescription: 'Internal roles can perform only the operations explicitly granted to them.',
    externalPolicy: 'External accounts',
    externalState: 'Blocked from administration',
    externalDescription: 'Client and designer accounts remain blocked even if an admin permission is accidentally granted.',
    pageInfo: (page: number, last: number, total: number) =>
      `Page ${page} of ${last} — ${total} records`
  }
}

const copy = computed(() => copyMap[currentLocale.value])
const canViewStaff = computed(() => auth.can('admin.staff.view'))
const canCreateStaff = computed(() => auth.can('admin.staff.create'))
const canViewActivity = computed(() => auth.can('admin.staff.activity.view'))

const staffUsers = ref<StaffUser[]>([])
const loading = ref(false)
const refreshing = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const hasLoaded = ref(false)
const page = ref(1)

const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

const summary = reactive<StaffSummary>({
  total: 0,
  staff_role: 0,
  admin_role: 0
})

const filters = reactive({
  search: '',
  role: '' as TeamRole,
  sortBy: 'id' as StaffSortKey,
  sortDirection: 'asc' as SortDirection
})

const createModalOpen = ref(false)
const savingStaff = ref(false)
const createError = ref<string | null>(null)
const createFieldErrors = ref<Record<string, string[]>>({})
const createDialog = ref<HTMLElement | null>(null)
const firstCreateInput = ref<HTMLInputElement | null>(null)
const createTrigger = ref<HTMLElement | null>(null)

const createForm = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'staff' as StaffCreateRole
})

const activityOpen = ref(false)
const selectedStaff = ref<StaffUser | null>(null)
const activityEvents = ref<StaffActivityEvent[]>([])
const activityLoading = ref(false)
const activityError = ref<string | null>(null)
const activityDrawer = ref<HTMLElement | null>(null)
const activityTrigger = ref<HTMLElement | null>(null)

const activityPagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 10,
  total: 0
})

const metricItems = computed<MetricItem[]>(() => [
  {
    key: 'total',
    label: copy.value.totalAccounts,
    description: copy.value.tableDescription,
    value: summary.total,
    tone: 'cyan',
    icon: '◎'
  },
  {
    key: 'staff',
    label: copy.value.staffAccounts,
    description: 'staff',
    value: summary.staff_role,
    tone: 'emerald',
    icon: 'S'
  },
  {
    key: 'admin',
    label: copy.value.adminAccounts,
    description: 'admin',
    value: summary.admin_role,
    tone: 'violet',
    icon: 'A'
  },
  {
    key: 'visible',
    label: copy.value.visibleRows,
    description: copy.value.pageInfo(
      pagination.current_page,
      pagination.last_page,
      pagination.total
    ),
    value: staffUsers.value.length,
    tone: 'amber',
    icon: '≡'
  }
])

const policyItems = computed<PolicyItem[]>(() => [
  {
    key: 'super-admin',
    title: copy.value.superAdminPolicy,
    state: copy.value.superAdminState,
    description: copy.value.superAdminDescription,
    meta: 'Gate::before',
    icon: '◆',
    tone: 'success'
  },
  {
    key: 'delegated',
    title: copy.value.delegatedPolicy,
    state: copy.value.delegatedState,
    description: copy.value.delegatedDescription,
    meta: 'admin.staff.*',
    icon: '⌘',
    tone: 'info'
  },
  {
    key: 'external',
    title: copy.value.externalPolicy,
    state: copy.value.externalState,
    description: copy.value.externalDescription,
    meta: 'client / designer',
    icon: '⊘',
    tone: 'warning'
  }
])

const hasActiveFilters = computed(() => (
  filters.search !== ''
  || filters.role !== ''
  || filters.sortBy !== 'id'
  || filters.sortDirection !== 'asc'
))

function textDirection(value: string): 'rtl' | 'ltr' {
  return /[\u0600-\u06FF]/.test(value) ? 'rtl' : 'ltr'
}

function formatDateTime(value: string | null): string {
  return value ? formatYmDateTime(value, currentLocale.value) : '—'
}

function resetCreateForm(): void {
  createForm.name = ''
  createForm.email = ''
  createForm.password = ''
  createForm.password_confirmation = ''
  createForm.role = 'staff'
  createError.value = null
  createFieldErrors.value = {}
}

async function openCreateStaffModal(event?: MouseEvent): Promise<void> {
  if (!canCreateStaff.value) return

  createTrigger.value = event?.currentTarget as HTMLElement | null
  successMessage.value = null
  resetCreateForm()
  createModalOpen.value = true

  await nextTick()
  createDialog.value?.focus()
  firstCreateInput.value?.focus()
}

function closeCreateStaffModal(): void {
  if (savingStaff.value) return

  createModalOpen.value = false
  createError.value = null
  createFieldErrors.value = {}
  nextTick(() => createTrigger.value?.focus())
}

function fieldError(field: string): string {
  return createFieldErrors.value[field]?.[0] ?? ''
}

async function submitCreateStaff(): Promise<void> {
  if (!canCreateStaff.value) return

  savingStaff.value = true
  createError.value = null
  createFieldErrors.value = {}
  successMessage.value = null

  try {
    const response = await apiFetch<StoreStaffResponse>('/admin/staff', {
      method: 'POST',
      body: {
        name: createForm.name,
        email: createForm.email,
        password: createForm.password,
        password_confirmation: createForm.password_confirmation,
        role: createForm.role
      }
    })

    createModalOpen.value = false
    resetCreateForm()
    successMessage.value = response.message || copy.value.createSuccess
    page.value = 1
    await fetchStaff()
  } catch (caughtError: unknown) {
    const err = caughtError as any
    createFieldErrors.value = err?.data?.errors ?? err?.response?._data?.errors ?? {}
    createError.value = err?.data?.message
      || err?.response?._data?.message
      || copy.value.createError
  } finally {
    savingStaff.value = false
  }
}

async function fetchStaff(): Promise<void> {
  if (!canViewStaff.value) return

  loading.value = !hasLoaded.value
  refreshing.value = hasLoaded.value
  error.value = null

  try {
    const response = await apiFetch<StaffListResponse>('/admin/staff', {
      query: {
        page: page.value,
        per_page: pagination.per_page,
        search: filters.search || undefined,
        role: filters.role || undefined,
        sort_by: filters.sortBy,
        sort_direction: filters.sortDirection
      }
    })

    staffUsers.value = response.data.data
    pagination.current_page = response.data.current_page
    pagination.last_page = response.data.last_page
    pagination.per_page = response.data.per_page
    pagination.total = response.data.total
    summary.total = response.meta.summary.total
    summary.staff_role = response.meta.summary.staff_role
    summary.admin_role = response.meta.summary.admin_role
    hasLoaded.value = true
  } catch (caughtError: unknown) {
    const err = caughtError as any
    staffUsers.value = []
    error.value = err?.data?.message
      || err?.response?._data?.message
      || (currentLocale.value === 'ar'
        ? 'تعذر جلب فريق العمل. تحقق من الاتصال وصلاحية العرض.'
        : 'Could not load the team. Check connectivity and view permission.')
  } finally {
    loading.value = false
    refreshing.value = false
  }
}

function refreshStaff(): void {
  void fetchStaff()
}

function applyFilters(): void {
  page.value = 1
  void fetchStaff()
}

function resetFilters(): void {
  filters.search = ''
  filters.role = ''
  filters.sortBy = 'id'
  filters.sortDirection = 'asc'
  page.value = 1
  void fetchStaff()
}

function changePage(nextPage: number): void {
  if (nextPage < 1 || nextPage > pagination.last_page) return

  page.value = nextPage
  void fetchStaff()
}

async function openActivity(user: StaffUser, event: MouseEvent): Promise<void> {
  if (!canViewActivity.value) return

  activityTrigger.value = event.currentTarget as HTMLElement
  selectedStaff.value = user
  activityEvents.value = []
  activityError.value = null
  activityPagination.current_page = 1
  activityOpen.value = true

  await nextTick()
  activityDrawer.value?.focus()
  await fetchActivity()
}

function closeActivity(): void {
  activityOpen.value = false
  selectedStaff.value = null
  activityEvents.value = []
  activityError.value = null
  nextTick(() => activityTrigger.value?.focus())
}

async function fetchActivity(): Promise<void> {
  if (!selectedStaff.value || !canViewActivity.value) return

  activityLoading.value = true
  activityError.value = null

  try {
    const response = await apiFetch<StaffActivityResponse>(
      `/admin/staff/${selectedStaff.value.id}/activity`,
      {
        query: {
          page: activityPagination.current_page,
          per_page: activityPagination.per_page
        }
      }
    )

    activityEvents.value = response.data.data
    activityPagination.current_page = response.data.current_page
    activityPagination.last_page = response.data.last_page
    activityPagination.per_page = response.data.per_page
    activityPagination.total = response.data.total
  } catch (caughtError: unknown) {
    const err = caughtError as any
    activityEvents.value = []
    activityError.value = err?.data?.message
      || err?.response?._data?.message
      || (currentLocale.value === 'ar'
        ? 'تعذر جلب سجل عمليات الحساب.'
        : 'Could not load account activity.')
  } finally {
    activityLoading.value = false
  }
}

function changeActivityPage(nextPage: number): void {
  if (nextPage < 1 || nextPage > activityPagination.last_page) return

  activityPagination.current_page = nextPage
  void fetchActivity()
}

function eventLabel(event: StaffActivityEvent): string {
  const labels: Record<string, { ar: string, en: string }> = {
    'staff.created': {
      ar: 'تم إنشاء الحساب الداخلي',
      en: 'Internal account created'
    },
    'user.roles.synced': {
      ar: 'تم تحديث أدوار الحساب',
      en: 'Account roles updated'
    },
    'user.login': {
      ar: 'تسجيل دخول إلى الحساب',
      en: 'Account login'
    },
    'user.login.failed': {
      ar: 'محاولة تسجيل دخول فاشلة',
      en: 'Failed login attempt'
    },
    'user.logout': {
      ar: 'تسجيل خروج من الحساب',
      en: 'Account logout'
    }
  }

  return labels[event.event_type]?.[currentLocale.value] || event.event_type
}

function outcomeLabel(outcome: string): string {
  if (currentLocale.value === 'en') {
    return outcome === 'success' ? 'Success' : outcome === 'failed' ? 'Failed' : outcome
  }

  return outcome === 'success' ? 'ناجح' : outcome === 'failed' ? 'فشل' : outcome
}

function actorLabel(event: StaffActivityEvent): string {
  if (!event.actor_id) return '—'
  return `${event.actor_role || 'user'} #${event.actor_id}`
}

function metadataEntries(event: StaffActivityEvent): [string, unknown][] {
  return Object.entries(event.metadata || {}).slice(0, 12)
}

function formatMetadataValue(value: unknown): string {
  if (value === null || value === undefined) return '—'
  if (typeof value === 'string') return value
  return JSON.stringify(value)
}

function handleEscape(event: KeyboardEvent): void {
  if (event.key !== 'Escape') return

  if (createModalOpen.value) {
    closeCreateStaffModal()
    return
  }

  if (activityOpen.value) {
    closeActivity()
  }
}

watch(
  canViewStaff,
  (allowed) => {
    if (allowed && !hasLoaded.value) {
      void fetchStaff()
    }
  }
)

onMounted(() => {
  window.addEventListener('keydown', handleEscape)

  if (canViewStaff.value) {
    void fetchStaff()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleEscape)
})
</script>

<style scoped>
.ym-staff-page {
  --ym-admin-section-accent: #06b6d4;
  --ym-admin-section-accent-secondary: #8b5cf6;
  display: grid;
  gap: 14px;
  min-width: 0;
}

.ym-staff-workspace {
  display: grid;
  gap: 14px;
  overflow: hidden;
  padding: clamp(14px, 2vw, 20px);
}

.ym-staff-workspace__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.ym-staff-workspace__head h2,
.ym-staff-dialog h2,
.ym-staff-activity-drawer h2 {
  margin: 3px 0 0;
  color: var(--ym-admin-text);
  font-size: 21px;
  font-weight: 950;
}

.ym-staff-workspace__head p,
.ym-staff-dialog header p,
.ym-staff-activity-drawer header p {
  max-width: 760px;
  margin: 5px 0 0;
  color: var(--ym-admin-muted);
  font-size: 13.5px;
  font-weight: 750;
  line-height: 1.7;
}

.ym-staff-workspace__eyebrow,
.ym-staff-dialog header > div > span,
.ym-staff-activity-drawer header > div > span {
  color: var(--ym-admin-section-accent);
  font-size: 12px;
  font-weight: 950;
  letter-spacing: .04em;
}

.ym-staff-filters {
  display: grid;
  grid-template-columns: minmax(230px, 1.7fr) repeat(3, minmax(145px, .7fr)) auto;
  align-items: end;
  gap: 10px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 16px;
  padding: 11px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-field {
  display: grid;
  min-width: 0;
  gap: 5px;
}

.ym-staff-field > span {
  color: var(--ym-admin-muted);
  font-size: 12px;
  font-weight: 850;
}

.ym-staff-field input,
.ym-staff-field select {
  width: 100%;
  min-height: 42px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 12px;
  outline: none;
  padding: 0 12px;
  background: var(--ym-admin-control-bg, var(--ym-admin-surface));
  color: var(--ym-admin-text);
  font-size: 13.5px;
  font-weight: 750;
  transition: border-color .16s ease, box-shadow .16s ease;
}

.ym-staff-field input:focus,
.ym-staff-field select:focus {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 70%, var(--ym-admin-border));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ym-admin-section-accent) 14%, transparent);
}

.ym-staff-field small {
  color: var(--ym-admin-muted);
  font-size: 11.5px;
  line-height: 1.5;
}

.ym-staff-field small:last-child:not(:only-child) {
  color: var(--ym-admin-danger, #ef4444);
}

.ym-staff-field.is-search > div {
  position: relative;
}

.ym-staff-field.is-search > div > span {
  position: absolute;
  inset-inline-start: 13px;
  top: 50%;
  color: var(--ym-admin-muted);
  transform: translateY(-50%);
}

.ym-staff-field.is-search input {
  padding-inline-start: 36px;
}

.ym-staff-filter-actions,
.ym-staff-pagination > div,
.ym-staff-create-form footer {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ym-staff-primary-button,
.ym-staff-secondary-button,
.ym-staff-icon-button,
.ym-staff-row-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  min-height: 39px;
  border-radius: 12px;
  padding: 0 13px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 900;
  transition: transform .16s ease, border-color .16s ease, opacity .16s ease;
}

.ym-staff-primary-button {
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 62%, transparent);
  background: linear-gradient(135deg, #0891b2, #06b6d4);
  box-shadow: 0 10px 24px rgba(6, 182, 212, .2);
  color: #fff;
}

.ym-staff-secondary-button,
.ym-staff-icon-button,
.ym-staff-row-action {
  border: 1px solid var(--ym-admin-border);
  background: var(--ym-admin-surface-soft);
  color: var(--ym-admin-text);
}

.ym-staff-row-action {
  min-height: 34px;
  border-color: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 28%, var(--ym-admin-border));
  color: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 76%, var(--ym-admin-text));
  white-space: nowrap;
}

.ym-staff-primary-button:hover:not(:disabled),
.ym-staff-secondary-button:hover:not(:disabled),
.ym-staff-row-action:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-staff-primary-button:disabled,
.ym-staff-secondary-button:disabled,
.ym-staff-icon-button:disabled {
  cursor: not-allowed;
  opacity: .5;
}

.ym-staff-loading {
  display: flex;
  min-height: 190px;
  align-items: center;
  justify-content: center;
  gap: 10px;
  color: var(--ym-admin-muted);
}

.ym-staff-loading > span,
.ym-staff-button-spinner {
  width: 19px;
  height: 19px;
  border: 2px solid color-mix(in srgb, var(--ym-admin-section-accent) 24%, transparent);
  border-top-color: var(--ym-admin-section-accent);
  border-radius: 999px;
  animation: ym-staff-spin .8s linear infinite;
}

.ym-staff-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--ym-admin-border);
  border-radius: 15px;
}

.ym-staff-table {
  width: 100%;
  min-width: 850px;
  border-collapse: collapse;
}

.ym-staff-table th,
.ym-staff-table td {
  border-bottom: 1px solid var(--ym-admin-border);
  padding: 12px 13px;
  text-align: start;
}

.ym-staff-table th {
  background: color-mix(in srgb, var(--ym-admin-surface-soft) 94%, transparent);
  color: var(--ym-admin-muted);
  font-size: 11.5px;
  font-weight: 950;
}

.ym-staff-table td {
  color: var(--ym-admin-text);
  font-size: 13px;
  font-weight: 750;
}

.ym-staff-table tbody tr {
  transition: background .16s ease;
}

.ym-staff-table tbody tr:hover {
  background: color-mix(in srgb, var(--ym-admin-section-accent) 5%, transparent);
}

.ym-staff-table tbody tr:last-child td {
  border-bottom: 0;
}

.ym-staff-table .is-id,
.ym-staff-table .is-date {
  color: var(--ym-admin-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ym-staff-email {
  display: block;
  max-width: 260px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-role-list {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.ym-staff-role {
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent-secondary) 28%, var(--ym-admin-border));
  border-radius: 999px;
  padding: 3px 8px;
  background: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 8%, transparent);
  color: var(--ym-admin-text);
  font-size: 11px;
  font-weight: 900;
}

.ym-staff-role.is-admin {
  border-color: color-mix(in srgb, #f59e0b 35%, var(--ym-admin-border));
  background: color-mix(in srgb, #f59e0b 9%, transparent);
  color: #d97706;
}

.ym-staff-role.is-staff {
  border-color: color-mix(in srgb, #10b981 35%, var(--ym-admin-border));
  background: color-mix(in srgb, #10b981 9%, transparent);
  color: #059669;
}

.ym-staff-pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--ym-admin-muted);
  font-size: 12.5px;
  font-weight: 800;
}

.ym-staff-pagination strong {
  color: var(--ym-admin-text);
  font-variant-numeric: tabular-nums;
}

.ym-staff-dialog-backdrop,
.ym-staff-drawer-backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  background: rgba(2, 6, 23, .66);
  backdrop-filter: blur(10px);
}

.ym-staff-dialog-backdrop {
  place-items: center;
  padding: 16px;
}

.ym-staff-dialog,
.ym-staff-activity-drawer {
  border: 1px solid var(--ym-admin-border);
  background:
    radial-gradient(circle at 90% 0%, rgba(6, 182, 212, .13), transparent 240px),
    var(--ym-admin-surface);
  box-shadow: 0 30px 90px rgba(2, 6, 23, .48);
  color: var(--ym-admin-text);
}

.ym-staff-dialog {
  width: min(100%, 620px);
  max-height: calc(100dvh - 32px);
  overflow-y: auto;
  border-radius: 22px;
  padding: 18px;
}

.ym-staff-dialog > header,
.ym-staff-activity-drawer > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
}

.ym-staff-icon-button {
  width: 38px;
  min-height: 38px;
  padding: 0;
  font-size: 21px;
}

.ym-staff-create-form {
  display: grid;
  gap: 12px;
  margin-top: 18px;
}

.ym-staff-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.ym-staff-create-form footer {
  justify-content: flex-end;
  margin-top: 5px;
}

.ym-staff-inline-error {
  margin: 0;
  border: 1px solid color-mix(in srgb, #ef4444 35%, transparent);
  border-radius: 12px;
  padding: 10px 12px;
  background: color-mix(in srgb, #ef4444 8%, transparent);
  color: #ef4444;
  font-size: 13px;
  font-weight: 850;
}

.ym-staff-drawer-backdrop {
  justify-items: end;
}

.ym-staff-activity-drawer {
  width: min(100%, 720px);
  height: 100dvh;
  overflow-y: auto;
  padding: 18px;
}

.ym-staff-drawer-backdrop.is-ltr {
  justify-items: start;
}

.ym-staff-activity-drawer header p {
  display: grid;
  gap: 2px;
}

.ym-staff-activity-drawer header p strong {
  color: var(--ym-admin-text);
}

.ym-staff-activity-drawer header p small {
  color: var(--ym-admin-muted);
}

.ym-staff-activity-summary {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
  margin: 16px 0;
}

.ym-staff-activity-summary > span {
  display: grid;
  gap: 4px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 13px;
  padding: 10px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-activity-summary small {
  color: var(--ym-admin-muted);
  font-size: 11px;
  font-weight: 800;
}

.ym-staff-activity-summary strong {
  overflow: hidden;
  color: var(--ym-admin-text);
  font-size: 13px;
  font-weight: 950;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-timeline {
  display: grid;
  gap: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ym-staff-timeline > li {
  position: relative;
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr);
  gap: 10px;
  padding-bottom: 13px;
}

.ym-staff-timeline > li:not(:last-child)::before {
  position: absolute;
  inset-inline-start: 10px;
  top: 20px;
  bottom: -2px;
  width: 1px;
  background: var(--ym-admin-border);
  content: "";
}

.ym-staff-timeline__dot {
  position: relative;
  z-index: 1;
  width: 11px;
  height: 11px;
  margin: 6px 0 0 5px;
  border: 2px solid var(--ym-admin-surface);
  border-radius: 999px;
  background: #94a3b8;
  box-shadow: 0 0 0 3px color-mix(in srgb, #94a3b8 18%, transparent);
}

.ym-staff-timeline__dot.is-success {
  background: #10b981;
  box-shadow: 0 0 0 3px color-mix(in srgb, #10b981 18%, transparent);
}

.ym-staff-timeline__dot.is-failed {
  background: #ef4444;
  box-shadow: 0 0 0 3px color-mix(in srgb, #ef4444 18%, transparent);
}

.ym-staff-timeline article {
  border: 1px solid var(--ym-admin-border);
  border-radius: 14px;
  padding: 11px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-timeline article > header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 10px;
}

.ym-staff-timeline article > header > div {
  display: grid;
  gap: 3px;
}

.ym-staff-timeline article > header strong {
  color: var(--ym-admin-text);
  font-size: 13.5px;
  font-weight: 950;
}

.ym-staff-timeline article > header small {
  color: var(--ym-admin-muted);
  font-size: 11.5px;
}

.ym-staff-timeline article > header > span {
  border-radius: 999px;
  padding: 3px 8px;
  background: color-mix(in srgb, #94a3b8 10%, transparent);
  color: #64748b;
  font-size: 10.5px;
  font-weight: 950;
}

.ym-staff-timeline article > header > span.is-success {
  background: color-mix(in srgb, #10b981 10%, transparent);
  color: #059669;
}

.ym-staff-timeline article > header > span.is-failed {
  background: color-mix(in srgb, #ef4444 10%, transparent);
  color: #dc2626;
}

.ym-staff-timeline dl {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 14px;
  margin: 10px 0 0;
}

.ym-staff-timeline dl > div {
  display: flex;
  gap: 5px;
}

.ym-staff-timeline dt {
  color: var(--ym-admin-muted);
  font-size: 11px;
  font-weight: 800;
}

.ym-staff-timeline dd {
  margin: 0;
  color: var(--ym-admin-text);
  font-size: 11px;
  font-weight: 900;
}

.ym-staff-timeline details {
  margin-top: 10px;
  border-top: 1px solid var(--ym-admin-border);
  padding-top: 9px;
}

.ym-staff-timeline summary {
  cursor: pointer;
  color: var(--ym-admin-section-accent-secondary);
  font-size: 11.5px;
  font-weight: 900;
}

.ym-staff-timeline ul {
  display: grid;
  gap: 6px;
  margin: 9px 0 0;
  padding: 0;
  list-style: none;
}

.ym-staff-timeline ul li {
  display: grid;
  grid-template-columns: minmax(110px, .7fr) minmax(0, 1.3fr);
  gap: 8px;
}

.ym-staff-timeline ul span {
  color: var(--ym-admin-muted);
  font-size: 11px;
}

.ym-staff-timeline code {
  overflow-wrap: anywhere;
  color: var(--ym-admin-text);
  font-size: 11px;
}

.ym-staff-toast {
  position: fixed;
  z-index: 120;
  inset-inline-end: 24px;
  bottom: 24px;
  max-width: min(420px, calc(100vw - 48px));
  margin: 0;
  border: 1px solid color-mix(in srgb, #10b981 38%, transparent);
  border-radius: 13px;
  padding: 11px 14px;
  background: color-mix(in srgb, #10b981 90%, #052e2b);
  box-shadow: 0 18px 50px rgba(2, 6, 23, .35);
  color: #fff;
  font-size: 13px;
  font-weight: 900;
}

@keyframes ym-staff-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 1120px) {
  .ym-staff-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-staff-filter-actions {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
}

@media (max-width: 700px) {
  .ym-staff-workspace__head,
  .ym-staff-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-staff-filters,
  .ym-staff-form-grid,
  .ym-staff-activity-summary {
    grid-template-columns: 1fr;
  }

  .ym-staff-filter-actions,
  .ym-staff-pagination > div {
    justify-content: space-between;
  }

  .ym-staff-dialog {
    padding: 14px;
  }

  .ym-staff-activity-drawer {
    width: 100%;
    padding: 14px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-staff-primary-button,
  .ym-staff-secondary-button,
  .ym-staff-row-action {
    transition: none;
  }
}
</style>
