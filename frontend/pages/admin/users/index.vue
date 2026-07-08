<template>
  <div class="ym-users-page space-y-7">
    <section class="ym-users-hero ym-admin-hero">
      <div class="ym-hero-orb ym-hero-orb-one" />
      <div class="ym-hero-orb ym-hero-orb-two" />
      <div class="ym-hero-orb ym-hero-orb-three" />
      <div class="ym-hero-grid" aria-hidden="true" />
      <div class="ym-hero-content">
        <div class="ym-hero-copy-block">
          <div class="ym-hero-chips">
            <span class="ym-hero-chip ym-hero-chip--brand">
              <i class="ym-hero-chip-dot" aria-hidden="true" />
              {{ copy.brandChip }}
            </span>
            <span class="ym-hero-chip ym-hero-chip--status">
              <i class="ym-hero-chip-dot ym-hero-chip-dot--live" aria-hidden="true" />
              {{ copy.readonlyBadge }}
            </span>
          </div>
          <p class="ym-hero-kicker">{{ copy.kicker }}</p>
          <h1 class="ym-hero-title">{{ copy.title }}</h1>
          <p class="ym-hero-copy">{{ copy.copy }}</p>
        </div>
        <div class="ym-hero-summary">
          <span>{{ copy.activeFilter }}</span>
          <strong>{{ selectedRoleLabel }}</strong>
          <small>{{ copy.pageScope }}: {{ pagination.current_page }} / {{ pagination.last_page }}</small>
        </div>
      </div>
    </section>

    <aside class="ym-readonly-notice" role="note">
      <span class="ym-readonly-notice__badge">{{ copy.readonlyBadge }}</span>
      <p>{{ copy.readonlyNotice }}</p>
    </aside>

    <aside v-if="successMessage" class="ym-users-success" role="status">
      <p>{{ successMessage }}</p>
    </aside>

    <section class="ym-summary-grid">
      <article
        v-for="card in summaryCards"
        :key="card.label"
        class="ym-summary-card"
        :style="{ '--card-accent': card.color }"
      >
        <span>{{ card.label }}</span>
        <strong>{{ card.value }}</strong>
        <small>{{ card.subtitle }}</small>
      </article>
    </section>

    <section class="ym-filter-card">
      <div>
        <h2>{{ copy.filterTitle }}</h2>
        <p>{{ copy.filterCopy }}</p>
      </div>
      <div class="ym-role-filter">
        <span>{{ copy.roleFilter }}</span>
        <div class="ym-role-filter__pills">
          <button
            v-for="role in roleFilterOptions"
            :key="role.value || 'all'"
            type="button"
            class="ym-role-pill"
            :class="selectedRole === role.value ? 'is-active' : ''"
            :style="{ '--role-color': role.color }"
            @click="selectedRole = role.value"
          >
            {{ role.label }}
          </button>
        </div>
      </div>
      <div class="ym-date-filter">
        <label>
          <span>{{ copy.createdFrom }}</span>
          <input v-model="createdFrom" type="date">
        </label>
        <label>
          <span>{{ copy.createdTo }}</span>
          <input v-model="createdTo" type="date">
        </label>
        <p v-if="dateFilterError" class="ym-date-filter__error" role="alert">
          {{ dateFilterError }}
        </p>
      </div>
      <aside v-if="hasAnyActiveFilter" class="ym-active-filters" role="status">
        <div class="ym-active-filters__chips">
          <span
            v-for="filter in activeFilters"
            :key="filter.key"
            class="ym-active-filter-chip"
          >
            <span>{{ filter.label }}: <strong>{{ filter.value }}</strong></span>
            <button type="button" :aria-label="copy.clearFilter(filter.label)" @click="clearActiveFilter(filter.key)">
              ×
            </button>
          </span>
        </div>
        <button type="button" class="ym-active-filters__clear" @click="clearAllFilters">
          {{ copy.clearAll }}
        </button>
      </aside>
    </section>

    <section class="ym-table-card">
      <div class="ym-table-card__head">
        <div>
          <h2>{{ copy.tableTitle }}</h2>
          <p>{{ copy.tableCopy }}</p>
        </div>
        <span>{{ copy.pageInfo(pagination.current_page, pagination.last_page, pagination.total) }}</span>
      </div>

      <div v-if="loading" class="ym-users-state">
        <span class="ym-users-state__spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </div>

      <div v-else-if="error" class="ym-users-state is-error">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="!users.length" class="ym-users-state">
        <p>{{ emptyStateMessage }}</p>
      </div>

      <div v-else class="ym-users-table-wrap">
        <table class="ym-users-table">
          <thead>
            <tr>
              <th class="ym-users-th-id">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('id')">
                    {{ copy.colId }}
                    <span class="ym-sort-indicator" :class="sortBy === 'id' ? 'is-active' : ''">{{ sortIndicator('id') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-users-th-name">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('name')">
                    {{ copy.colName }}
                    <span class="ym-sort-indicator" :class="sortBy === 'name' ? 'is-active' : ''">{{ sortIndicator('name') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-users-th-email">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('email')">
                    {{ copy.colEmail }}
                    <span class="ym-sort-indicator" :class="sortBy === 'email' ? 'is-active' : ''">{{ sortIndicator('email') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-users-th-roles">
                <div class="ym-table-th-content">
                  <span>{{ copy.colRoles }}</span>
                </div>
              </th>
              <th class="ym-users-th-created">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('created_at')">
                    {{ copy.colCreated }}
                    <span class="ym-sort-indicator" :class="sortBy === 'created_at' ? 'is-active' : ''">{{ sortIndicator('created_at') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-users-th-actions">
                <div class="ym-table-th-content">
                  <span>{{ copy.colActions }}</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td class="ym-users-cell-id">{{ user.id }}</td>
              <td class="ym-users-cell-name">
                <span
                  class="ym-name-preview"
                  :title="user.name"
                  :dir="textDirection(user.name)"
                  v-text="truncateText(user.name, 15)"
                />
                <button
                  type="button"
                  class="ym-user-details-trigger"
                  :aria-label="copy.details"
                  @click.stop="openUserDetails(user)"
                >
                  {{ copy.details }}
                </button>
              </td>
              <td class="ym-users-cell-email" dir="ltr">
                <span
                  class="ym-email-preview"
                  :title="user.email"
                  dir="ltr"
                  v-text="truncateText(user.email, 15)"
                />
              </td>
              <td class="ym-users-cell-roles">
                <span v-if="!user.roles.length" class="ym-users-role-empty">—</span>
                <span
                  v-for="role in user.roles"
                  :key="role"
                  class="ym-users-role-icon"
                  :style="roleIconStyle(role)"
                  :title="role"
                  :aria-label="role"
                  :data-tooltip="role"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      v-for="path in roleVisual(role).paths"
                      :key="path"
                      :d="path"
                    />
                  </svg>
                </span>
              </td>
              <td class="ym-users-cell-created">{{ formatCreatedAt(user.created_at) }}</td>
              <td class="ym-users-cell-actions">
                <button
                  type="button"
                  class="ym-users-action-btn"
                  :title="copy.manageRoles"
                  :aria-label="copy.manageRoles"
                  :data-tooltip="copy.manageRoles"
                  @click.stop="openRoleModal(user)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      v-for="path in manageRolesIconPaths"
                      :key="path"
                      :d="path"
                    />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="!loading && !error && users.length" class="ym-users-pagination">
        <span class="ym-users-pagination__info">
          {{ copy.pageInfo(pagination.current_page, pagination.last_page, pagination.total) }}
        </span>
        <div class="ym-users-pagination__actions">
          <button
            type="button"
            class="ym-users-page-btn"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >{{ copy.prev }}</button>
          <span class="ym-users-pagination__current">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            class="ym-users-page-btn"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >{{ copy.next }}</button>
        </div>
      </footer>
    </section>

    <div
      v-if="detailsDrawerOpen && selectedDetailsUser"
      class="ym-user-details-backdrop"
      role="presentation"
      @click.self="closeUserDetails"
    >
      <section
        class="ym-user-details-drawer"
        role="dialog"
        aria-modal="true"
        :aria-label="copy.userDetailsTitle"
      >
        <header class="ym-user-details-drawer__head">
          <div>
            <h2>{{ copy.userDetailsTitle }}</h2>
            <p>{{ copy.userDetailsCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-user-details-drawer__close"
            :aria-label="copy.close"
            @click="closeUserDetails"
          >
            ×
          </button>
        </header>

        <dl class="ym-user-details-list">
          <div class="ym-user-details-list__item">
            <dt>{{ copy.userId }}</dt>
            <dd>{{ selectedDetailsUser.id }}</dd>
          </div>
          <div class="ym-user-details-list__item">
            <dt>{{ copy.detailName }}</dt>
            <dd :dir="textDirection(selectedDetailsUser.name)">{{ selectedDetailsUser.name }}</dd>
          </div>
          <div class="ym-user-details-list__item">
            <dt>{{ copy.detailEmail }}</dt>
            <dd dir="ltr">{{ selectedDetailsUser.email }}</dd>
          </div>
          <div class="ym-user-details-list__item">
            <dt>{{ copy.detailRoles }}</dt>
            <dd>
              <span v-if="!selectedDetailsUser.roles.length" class="ym-user-details-empty">—</span>
              <span v-else class="ym-user-details-roles">
                <span
                  v-for="role in selectedDetailsUser.roles"
                  :key="role"
                  class="ym-user-details-role"
                  :style="roleIconStyle(role)"
                >
                  <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                      v-for="path in roleVisual(role).paths"
                      :key="path"
                      :d="path"
                    />
                  </svg>
                  <span>{{ role }}</span>
                </span>
              </span>
            </dd>
          </div>
          <div class="ym-user-details-list__item">
            <dt>{{ copy.detailCreatedAt }}</dt>
            <dd dir="ltr">{{ formatCreatedAt(selectedDetailsUser.created_at) }}</dd>
          </div>
        </dl>

        <footer class="ym-user-details-drawer__actions">
          <button
            type="button"
            class="ym-user-details-drawer__btn is-secondary"
            @click="closeUserDetails"
          >
            {{ copy.close }}
          </button>
          <button
            type="button"
            class="ym-user-details-drawer__btn is-primary"
            @click="selectedDetailsUser && openRolesFromDetails(selectedDetailsUser)"
          >
            {{ copy.manageRoles }}
          </button>
        </footer>
      </section>
    </div>

    <div
      v-if="roleModalOpen && selectedUser"
      class="ym-role-modal-backdrop"
      role="presentation"
      @click.self="closeRoleModal"
    >
      <section
        class="ym-role-modal"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="'ym-role-modal-title'"
      >
        <header class="ym-role-modal__head">
          <div>
            <p>{{ copy.roleModalKicker }}</p>
            <h2 id="ym-role-modal-title">{{ copy.roleModalTitle }}</h2>
          </div>
          <button
            type="button"
            class="ym-role-modal__close"
            :aria-label="copy.cancel"
            :disabled="savingRoles"
            @click="closeRoleModal"
          >
            ×
          </button>
        </header>

        <div class="ym-role-modal__user">
          <strong :dir="textDirection(selectedUser.name)">{{ selectedUser.name }}</strong>
          <span dir="ltr">{{ selectedUser.email }}</span>
        </div>

        <p v-if="selectedUserIsSuperAdmin" class="ym-role-modal__warning">
          {{ copy.superAdminWarning }}
        </p>

        <p v-if="roleModalError" class="ym-role-modal__error" role="alert">
          {{ roleModalError }}
        </p>

        <div class="ym-role-modal__roles" :aria-label="copy.availableRoles">
          <label
            v-for="role in availableRoles"
            :key="role"
            class="ym-role-option"
            :class="{
              'is-selected': selectedUserRoles.includes(role),
              'is-protected': isProtectedRole(role)
            }"
            :style="{ '--role-color': roleColor(role) }"
          >
            <input
              v-model="selectedUserRoles"
              type="checkbox"
              :value="role"
              :disabled="savingRoles || isProtectedRole(role)"
            >
            <svg class="ym-role-option__icon" viewBox="0 0 24 24" aria-hidden="true">
              <path
                v-for="path in roleVisual(role).paths"
                :key="path"
                :d="path"
              />
            </svg>
            <span class="ym-role-option__name">{{ role }}</span>
            <span class="ym-role-option__check" aria-hidden="true">✓</span>
          </label>
        </div>

        <footer class="ym-role-modal__actions">
          <button
            type="button"
            class="ym-role-modal__btn is-secondary"
            :disabled="savingRoles"
            @click="closeRoleModal"
          >
            {{ copy.cancel }}
          </button>
          <button
            type="button"
            class="ym-role-modal__btn is-primary"
            :disabled="savingRoles"
            @click="saveUserRoles"
          >
            <span v-if="savingRoles" class="ym-role-modal__spinner" aria-hidden="true" />
            {{ savingRoles ? copy.saving : copy.save }}
          </button>
        </footer>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'

type AdminUser = {
  id: number
  name: string
  email: string
  roles: string[]
  created_at: string | null
}

type PaginatedUsers = {
  data: AdminUser[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type AdminUsersResponse = {
  success: boolean
  data: PaginatedUsers
  message?: string
  errors?: Record<string, string[]> | null
  meta?: {
    available_roles?: string[]
  }
}

type AssignRolesResponse = {
  success: boolean
  data: AdminUser
  message?: string
}

type RoleVisual = {
  color: string
  paths: string[]
}

type UsersSortKey = 'id' | 'name' | 'email' | 'created_at'
type SortDirection = 'asc' | 'desc'
const { apiFetch } = useApiClient()
const {
  query: topbarSearchQuery,
  setTopbarSearchConfig,
  resetTopbarSearchConfig,
  clearTopbarSearch
} = useTopbarSearch()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')
let searchDebounceTimer: ReturnType<typeof setTimeout> | null = null
let filtersDebounceTimer: ReturnType<typeof setTimeout> | null = null
let leavingUsersPage = false

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'إدارة أدوار',
    kicker: 'إدارة المستخدمين',
    title: 'مركز المستخدمين',
    copy: 'تعرض المستخدمين وتتيح إدارة الأدوار للمصرح لهم فقط دون إنشاء أو حذف أو تعديل بيانات المستخدم.',
    readonlyNotice: 'تعرض المستخدمين وتتيح إدارة الأدوار للمصرح لهم فقط.',
    activeFilter: 'فلتر الدور',
    pageScope: 'الصفحة',
    allRoles: 'كل الأدوار',
    filterTitle: 'فلترة إدارية مباشرة',
    filterCopy: 'اختر الدور المطلوب لتضييق العرض دون إضافة بحث نصي داخل الصفحة.',
    roleFilter: 'الدور',
    createdFrom: 'من تاريخ',
    createdTo: 'إلى تاريخ',
    invalidDateRange: 'يجب أن يكون تاريخ النهاية مساويًا لتاريخ البداية أو بعده.',
    activeSearch: 'البحث',
    activeRole: 'الدور',
    activeFrom: 'من',
    activeTo: 'إلى',
    clearAll: 'مسح الكل',
    clearFilter: (label: string) => `مسح ${label}`,
    tableTitle: 'سجل المستخدمين',
    tableCopy: 'جدول غني داخل بطاقة مراقبة متصل بنفس endpoint الحالي.',
    totalUsers: 'إجمالي المستخدمين',
    currentPageUsers: 'في الصفحة الحالية',
    selectedRole: 'الدور المحدد',
    currentPage: 'الصفحة الحالية',
    liveData: 'من بيانات API',
    visibleRows: 'صفوف ظاهرة الآن',
    roleScope: 'نطاق العرض',
    paginationScope: 'حالة التصفح',
    loading: 'يتم تحميل المستخدمين...',
    empty: 'لا يوجد مستخدمون مطابقون.',
    emptySearch: 'لا توجد نتائج مطابقة للبحث الحالي. جرّب تغيير كلمة البحث أو مسح البحث.',
    emptyFilters: 'لا توجد نتائج مطابقة للفلاتر الحالية. جرّب تعديل البحث أو الدور أو التاريخ.',
    clearSearch: 'مسح البحث',
    colId: '#',
    colName: 'الاسم',
    colEmail: 'البريد الإلكتروني',
    colRoles: 'الأدوار',
    colCreated: 'تاريخ الإنشاء',
    colActions: 'الإجراءات',
    details: 'التفاصيل',
    userDetailsTitle: 'تفاصيل المستخدم',
    userDetailsCopy: 'معلومات قراءة فقط من سجل المستخدم الحالي.',
    userId: 'رقم المستخدم',
    detailName: 'الاسم',
    detailEmail: 'البريد الإلكتروني',
    detailRoles: 'الأدوار',
    detailCreatedAt: 'تاريخ الإنشاء',
    close: 'إغلاق',
    manageRoles: 'إدارة الأدوار',
    roleModalKicker: 'تعيين محدود',
    roleModalTitle: 'إدارة أدوار المستخدم',
    availableRoles: 'الأدوار المتاحة',
    save: 'حفظ',
    saving: 'جار الحفظ...',
    cancel: 'إلغاء',
    successRoles: 'تم حفظ أدوار المستخدم بنجاح.',
    selectOneRole: 'يجب اختيار دور واحد على الأقل.',
    forbiddenRoles: 'لا تملك صلاحية تعديل أدوار هذا المستخدم.',
    invalidRoles: 'تعذر حفظ الأدوار. تحقق من الاختيارات.',
    genericRolesError: 'تعذر حفظ الأدوار. حاول مرة أخرى.',
    superAdminWarning: 'هذا حساب مدير أعلى محمي. لا يمكن إزالة دور super-admin.',
    prev: 'السابق',
    next: 'التالي',
    pageInfo: (page: number, last: number, total: number) =>
      `الصفحة ${page} من ${last} - ${total} مستخدم`
  },
  en: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'Role assignment',
    kicker: 'User management',
    title: 'Users Command Center',
    copy: 'Displays users and allows authorized role assignment only without user create, delete, or profile edit actions.',
    readonlyNotice: 'Displays users and allows authorized role assignment only.',
    activeFilter: 'Role filter',
    pageScope: 'Page',
    allRoles: 'All roles',
    filterTitle: 'Direct admin filtering',
    filterCopy: 'Select a role to narrow the view through direct administrative filtering.',
    roleFilter: 'Role',
    createdFrom: 'From date',
    createdTo: 'To date',
    invalidDateRange: 'The end date must be the same as or after the start date.',
    activeSearch: 'Search',
    activeRole: 'Role',
    activeFrom: 'From',
    activeTo: 'To',
    clearAll: 'Clear all',
    clearFilter: (label: string) => `Clear ${label}`,
    tableTitle: 'Users register',
    tableCopy: 'A rich table card connected to the current endpoint.',
    totalUsers: 'Total users',
    currentPageUsers: 'Current page users',
    selectedRole: 'Selected role',
    currentPage: 'Current page',
    liveData: 'From API data',
    visibleRows: 'Visible rows now',
    roleScope: 'View scope',
    paginationScope: 'Pagination state',
    loading: 'Loading users...',
    empty: 'No matching users.',
    emptySearch: 'No users match the current search. Try changing the keyword or clearing the search.',
    emptyFilters: 'No users match the current filters. Try changing the search, role, or date range.',
    clearSearch: 'Clear search',
    colId: '#',
    colName: 'Name',
    colEmail: 'Email',
    colRoles: 'Roles',
    colCreated: 'Created at',
    colActions: 'Actions',
    details: 'Details',
    userDetailsTitle: 'User details',
    userDetailsCopy: 'Read-only information from the current user record.',
    userId: 'User ID',
    detailName: 'Name',
    detailEmail: 'Email',
    detailRoles: 'Roles',
    detailCreatedAt: 'Created at',
    close: 'Close',
    manageRoles: 'Manage roles',
    roleModalKicker: 'Limited assignment',
    roleModalTitle: 'Manage user roles',
    availableRoles: 'Available roles',
    save: 'Save',
    saving: 'Saving...',
    cancel: 'Cancel',
    successRoles: 'User roles were saved successfully.',
    selectOneRole: 'Select at least one role.',
    forbiddenRoles: "You do not have permission to modify this user's roles.",
    invalidRoles: 'Could not save roles. Check your selection.',
    genericRolesError: 'Could not save roles. Try again.',
    superAdminWarning: 'This is a protected super-admin account. The super-admin role cannot be removed.',
    prev: 'Prev',
    next: 'Next',
    pageInfo: (page: number, last: number, total: number) =>
      `Page ${page} of ${last} - ${total} users`
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const users = ref<AdminUser[]>([])
const availableRoles = ref<string[]>(['admin', 'staff', 'client', 'designer'])
const loading = ref(false)
const error = ref<string | null>(null)
const selectedRole = ref('')
const createdFrom = ref('')
const createdTo = ref('')
const page = ref(1)
const sortBy = ref<UsersSortKey>('id')
const sortDirection = ref<SortDirection>('asc')
const roleModalOpen = ref(false)
const selectedUser = ref<AdminUser | null>(null)
const selectedUserRoles = ref<string[]>([])
const savingRoles = ref(false)
const roleModalError = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const selectedDetailsUser = ref<AdminUser | null>(null)
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})
const selectedRoleLabel = computed(() => selectedRole.value || copy.value.allRoles)
const activeSearchQuery = computed(() => topbarSearchQuery.value.trim())
const hasActiveSearch = computed(() => activeSearchQuery.value !== '')
const hasActiveRoleFilter = computed(() => selectedRole.value !== '')
const hasActiveDateFilter = computed(() => createdFrom.value !== '' || createdTo.value !== '')
const hasAnyActiveFilter = computed(() => hasActiveSearch.value || hasActiveRoleFilter.value || hasActiveDateFilter.value)
const dateFilterError = computed(() => {
  if (createdFrom.value && createdTo.value && createdFrom.value > createdTo.value) {
    return copy.value.invalidDateRange
  }

  return ''
})
const activeFilters = computed(() => {
  const filters: Array<{ key: string; label: string; value: string }> = []

  if (hasActiveSearch.value) {
    filters.push({ key: 'search', label: copy.value.activeSearch, value: activeSearchQuery.value })
  }

  if (hasActiveRoleFilter.value) {
    filters.push({ key: 'role', label: copy.value.activeRole, value: selectedRole.value })
  }

  if (createdFrom.value) {
    filters.push({ key: 'created_from', label: copy.value.activeFrom, value: createdFrom.value })
  }

  if (createdTo.value) {
    filters.push({ key: 'created_to', label: copy.value.activeTo, value: createdTo.value })
  }

  return filters
})
const emptyStateMessage = computed(() => {
  if (hasAnyActiveFilter.value) return copy.value.emptyFilters
  return copy.value.empty
})
const roleFilterOptions = computed(() => {
  const seen = new Set<string>()
  const roles = ['admin', 'staff', 'client', 'designer', ...availableRoles.value]
    .filter((role) => {
      if (seen.has(role)) return false
      seen.add(role)
      return true
    })

  return [
    { label: copy.value.allRoles, value: '', color: '#6366f1' },
    ...roles.map(role => ({ label: role, value: role, color: roleColor(role) }))
  ]
})
const summaryCards = computed(() => [
  {
    label: copy.value.totalUsers,
    value: pagination.total,
    subtitle: copy.value.liveData,
    color: '#10b981'
  },
  {
    label: copy.value.currentPageUsers,
    value: users.value.length,
    subtitle: copy.value.visibleRows,
    color: '#38bdf8'
  },
  {
    label: copy.value.selectedRole,
    value: selectedRoleLabel.value,
    subtitle: copy.value.roleScope,
    color: '#8b5cf6'
  },
  {
    label: copy.value.currentPage,
    value: `${pagination.current_page} / ${pagination.last_page}`,
    subtitle: copy.value.paginationScope,
    color: '#f59e0b'
  }
])
const selectedUserIsSuperAdmin = computed(() => {
  return selectedUser.value ? isSuperAdminUser(selectedUser.value) : false
})
const detailsDrawerOpen = computed(() => selectedDetailsUser.value !== null)
const fallbackRoleVisual: RoleVisual = {
  color: '#38bdf8',
  paths: [
    'M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z',
    'M7 7h.01'
  ]
}
const roleVisualMap: Record<string, RoleVisual> = {
  'super-admin': {
    color: '#f59e0b',
    paths: [
      'M3 7l4.5 4L12 4l4.5 7L21 7l-2 11H5L3 7Z',
      'M5 21h14'
    ]
  },
  admin: {
    color: '#ef4444',
    paths: [
      'M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z',
      'm9 12 2 2 4-4'
    ]
  },
  staff: {
    color: '#06b6d4',
    paths: [
      'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2',
      'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z',
      'M22 21v-2a4 4 0 0 0-3-3.87',
      'M16 3.13a4 4 0 0 1 0 7.75'
    ]
  },
  client: {
    color: '#10b981',
    paths: [
      'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2',
      'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z'
    ]
  },
  designer: {
    color: '#8b5cf6',
    paths: [
      'M12 2l1.55 5.2L19 9l-5.45 1.8L12 16l-1.55-5.2L5 9l5.45-1.8L12 2Z',
      'M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z',
      'M5 15l.7 1.8L8 17.5l-2.3.7L5 20l-.7-1.8L2 17.5l2.3-.7L5 15Z'
    ]
  }
}
const manageRolesIconPaths = [
  'M12 22s8-3 8-10V5l-8-3-8 3v7c0 7 8 10 8 10Z',
  'M9 12l2 2 4-4',
  'M18.5 18.5l2.5 2.5',
  'M19 16.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z'
]
function summaryCardStyle(color: string): Record<string, string> {
  return {
    '--card-accent': color
  }
}
function toggleSort(key: UsersSortKey): void {
  if (sortBy.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = key
    sortDirection.value = 'asc'
  }

  page.value = 1
  void fetchUsers()
}

function sortIndicator(key: UsersSortKey): string {
  if (sortBy.value !== key) return '↕'
  return sortDirection.value === 'asc' ? '↑' : '↓'
}

function roleColor(role: string): string {
  return roleVisual(role).color
}

function roleVisual(role: string): RoleVisual {
  return roleVisualMap[role] || fallbackRoleVisual
}

function roleIconStyle(role: string): Record<string, string> {
  return {
    '--role-color': roleColor(role)
  }
}

function clearActiveFilter(key: string): void {
  if (key === 'search') {
    clearTopbarSearch()
    return
  }

  if (key === 'role') {
    selectedRole.value = ''
    return
  }

  if (key === 'created_from') {
    createdFrom.value = ''
    return
  }

  if (key === 'created_to') {
    createdTo.value = ''
  }
}

function clearAllFilters(): void {
  clearTopbarSearch()
  selectedRole.value = ''
  createdFrom.value = ''
  createdTo.value = ''
}

function isSuperAdminUser(user: AdminUser): boolean {
  return user.roles.includes('super-admin')
}

function isProtectedRole(role: string): boolean {
  return role === 'super-admin' && selectedUser.value !== null && isSuperAdminUser(selectedUser.value)
}

function openUserDetails(user: AdminUser): void {
  selectedDetailsUser.value = user
}

function closeUserDetails(): void {
  selectedDetailsUser.value = null
}

function openRolesFromDetails(user: AdminUser): void {
  closeUserDetails()
  openRoleModal(user)
}

function openRoleModal(user: AdminUser): void {
  selectedUser.value = user
  selectedUserRoles.value = [...user.roles]
  roleModalError.value = null
  successMessage.value = null
  roleModalOpen.value = true
}

function closeRoleModal(): void {
  if (savingRoles.value) return

  roleModalOpen.value = false
  selectedUser.value = null
  selectedUserRoles.value = []
  roleModalError.value = null
}

function errorStatus(error: unknown): number | null {
  if (!error || typeof error !== 'object') {
    return null
  }

  if ('response' in error && typeof (error as { response?: { status?: unknown } }).response?.status === 'number') {
    return (error as { response: { status: number } }).response.status
  }

  if ('statusCode' in error && typeof (error as { statusCode?: unknown }).statusCode === 'number') {
    return (error as { statusCode: number }).statusCode
  }

  if ('status' in error && typeof (error as { status?: unknown }).status === 'number') {
    return (error as { status: number }).status
  }

  return null
}

function roleSaveErrorMessage(status: number | null): string {
  if (status === 403) {
    return copy.value.forbiddenRoles
  }

  if (status === 422) {
    return copy.value.invalidRoles
  }

  return copy.value.genericRolesError
}

async function saveUserRoles(): Promise<void> {
  if (!selectedUser.value) return

  roleModalError.value = null
  successMessage.value = null

  if (isSuperAdminUser(selectedUser.value) && !selectedUserRoles.value.includes('super-admin')) {
    selectedUserRoles.value = [...selectedUserRoles.value, 'super-admin']
  }

  const roles = Array.from(new Set(selectedUserRoles.value))

  if (!roles.length) {
    roleModalError.value = copy.value.selectOneRole
    return
  }

  savingRoles.value = true

  try {
    const response = await apiFetch<AssignRolesResponse>(`/admin/users/${selectedUser.value.id}/roles`, {
      method: 'PUT',
      body: {
        roles
      }
    })

    const updatedUser = response.data
    const userIndex = users.value.findIndex(user => user.id === updatedUser.id)

    if (userIndex !== -1) {
      users.value.splice(userIndex, 1, updatedUser)
    }

    roleModalOpen.value = false
    selectedUser.value = null
    selectedUserRoles.value = []
    successMessage.value = copy.value.successRoles
  } catch (caughtError) {
    roleModalError.value = roleSaveErrorMessage(errorStatus(caughtError))
  } finally {
    savingRoles.value = false
  }
}


function truncateText(value: string | null | undefined, limit = 15): string {
  const chars = Array.from(String(value ?? '').trim())

  if (chars.length <= limit) {
    return chars.join('')
  }

  return `${chars.slice(0, limit).join('')}…`
}

function textDirection(value: string | null | undefined): 'rtl' | 'ltr' {
  const text = String(value ?? '').trim()

  return /[\u0600-\u06FF]/.test(text) ? 'rtl' : 'ltr'
}

function formatCreatedAt(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  const year = date.getFullYear()
  const month = padDatePart(date.getMonth() + 1)
  const day = padDatePart(date.getDate())
  const hours = padDatePart(date.getHours())
  const minutes = padDatePart(date.getMinutes())

  return `${year}-${month}-${day} ${hours}:${minutes}`
}

function padDatePart(value: number): string {
  return String(value).padStart(2, '0')
}

async function fetchUsers(): Promise<void> {
  if (dateFilterError.value) return

  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminUsersResponse>('/admin/users', {
      query: {
        page: page.value,
        per_page: pagination.per_page,
        role: selectedRole.value || undefined,
        search: activeSearchQuery.value || undefined,
        created_from: createdFrom.value || undefined,
        created_to: createdTo.value || undefined,
        sort_by: sortBy.value,
        sort_direction: sortDirection.value
      }
    })

    users.value = response.data.data
    pagination.current_page = response.data.current_page
    pagination.last_page = response.data.last_page
    pagination.per_page = response.data.per_page
    pagination.total = response.data.total
    availableRoles.value = response.meta?.available_roles || availableRoles.value
  } catch {
    users.value = []
    error.value = currentLocale.value === 'ar'
      ? 'تعذر جلب المستخدمين. تحقق من تسجيل الدخول وصلاحيات الأدمن.'
      : 'Could not load users. Check admin authentication and permissions.'
  } finally {
    loading.value = false
  }
}

function changePage(next: number): void {
  if (next < 1 || next > pagination.last_page) return
  page.value = next
  void fetchUsers()
}

watch(selectedRole, () => {
  page.value = 1
  void fetchUsers()
})

watch(topbarSearchQuery, () => {
  if (leavingUsersPage) return
  if (searchDebounceTimer) clearTimeout(searchDebounceTimer)

  searchDebounceTimer = setTimeout(() => {
    page.value = 1
    void fetchUsers()
  }, 350)
})

watch([createdFrom, createdTo], () => {
  if (leavingUsersPage) return
  if (filtersDebounceTimer) clearTimeout(filtersDebounceTimer)

  filtersDebounceTimer = setTimeout(() => {
    page.value = 1
    void fetchUsers()
  }, 250)
})

onMounted(() => {
  setTopbarSearchConfig({
    scope: 'admin-users',
    placeholder: {
      ar: 'ابحث في المستخدمين بالاسم أو البريد الإلكتروني',
      en: 'Search users by name or email'
    },
    tooltip: {
      ar: 'البحث في المستخدمين',
      en: 'Search users'
    }
  })
  void fetchUsers()
})

onBeforeUnmount(() => {
  leavingUsersPage = true

  if (searchDebounceTimer) {
    clearTimeout(searchDebounceTimer)
    searchDebounceTimer = null
  }

  if (filtersDebounceTimer) {
    clearTimeout(filtersDebounceTimer)
    filtersDebounceTimer = null
  }

  resetTopbarSearchConfig()
  clearTopbarSearch()
})
</script>

<style scoped>
.ym-users-page {
  /* Local section color until admin settings can provide it. */
  --ym-section-accent: #10b981;
  position: relative;
}

.ym-users-hero,
.ym-filter-card,
.ym-table-card,
.ym-summary-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  background: var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 44px rgba(2, 6, 23, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.ym-users-hero {
  border-radius: 28px;
  padding: clamp(1.35rem, 3vw, 2.25rem);
}

.ym-admin-hero {
  border-color: rgba(255, 255, 255, 0.22);
  background:
    radial-gradient(circle at 16% 18%, rgba(255, 255, 255, 0.24), transparent 15rem),
    radial-gradient(circle at 82% 8%, rgba(16, 185, 129, 0.46), transparent 20rem),
    radial-gradient(circle at 96% 90%, rgba(190, 0, 1, 0.3), transparent 22rem),
    linear-gradient(135deg, rgba(6, 78, 59, 0.98), rgba(16, 185, 129, 0.88) 48%, rgba(14, 116, 144, 0.82));
  box-shadow:
    0 34px 80px rgba(16, 185, 129, 0.24),
    0 14px 32px rgba(2, 6, 23, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16);
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-admin-hero:hover {
  transform: translateY(-2px);
  box-shadow:
    0 38px 88px rgba(16, 185, 129, 0.3),
    0 16px 36px rgba(2, 6, 23, 0.22),
    inset 0 1px 0 rgba(255, 255, 255, 0.34),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16);
}

.ym-admin-hero::before {
  position: absolute;
  inset: 1px;
  border-radius: 27px;
  background:
    linear-gradient(115deg, rgba(255, 255, 255, 0.16), transparent 34%),
    linear-gradient(290deg, rgba(255, 255, 255, 0.1), transparent 42%);
  content: "";
  pointer-events: none;
}

.ym-admin-hero::after {
  position: absolute;
  inset-inline: 7%;
  top: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.78), transparent);
  content: "";
  pointer-events: none;
}

.ym-hero-grid {
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.05) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-position: center;
  background-size: 48px 48px;
  mask-image: radial-gradient(circle at 30% 30%, #000 0%, transparent 72%);
  opacity: 0.5;
  pointer-events: none;
}

.ym-hero-orb {
  position: absolute;
  border-radius: 999px;
  filter: blur(44px);
  opacity: 0.3;
}

.ym-hero-orb-one {
  top: -6rem;
  inset-inline-end: -4rem;
  height: 16rem;
  width: 16rem;
  background: rgba(255, 255, 255, 0.32);
}

.ym-hero-orb-two {
  bottom: -6rem;
  inset-inline-start: 20%;
  height: 18rem;
  width: 18rem;
  background: rgba(56, 189, 248, 0.22);
}

.ym-hero-orb-three {
  top: 30%;
  inset-inline-start: -5rem;
  height: 14rem;
  width: 14rem;
  background: rgba(244, 114, 182, 0.26);
}

.ym-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.ym-hero-copy-block {
  min-width: 0;
}

.ym-hero-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.65rem;
}

.ym-hero-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.14);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.3), 0 6px 16px rgba(15, 23, 42, 0.14);
  color: #fff;
  font-size: 12.5px;
  font-weight: 850;
  padding: 0.28rem 0.7rem;
  backdrop-filter: blur(8px);
}

.ym-hero-chip--brand {
  background: rgba(255, 255, 255, 0.2);
}

.ym-hero-chip--status {
  border-color: rgba(134, 239, 172, 0.42);
  background: rgba(34, 197, 94, 0.22);
}

.ym-hero-chip-dot {
  height: 7px;
  width: 7px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.85);
  box-shadow: 0 0 8px rgba(255, 255, 255, 0.7);
}

.ym-hero-chip-dot--live {
  background: #4ade80;
  box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.28), 0 0 12px rgba(74, 222, 128, 0.7);
}

.ym-hero-kicker,
.ym-hero-copy,
.ym-hero-summary small,
.ym-hero-summary span {
  color: rgba(255, 255, 255, 0.92);
  font-weight: 850;
}

.ym-hero-kicker {
  margin: 0 0 0.3rem;
  font-size: 14.5px;
}

.ym-hero-title {
  margin: 0;
  color: #fff;
  font-size: clamp(2.1rem, 3.4vw, 2.75rem);
  font-weight: 950;
  line-height: 1.06;
  text-shadow: 0 2px 16px rgba(49, 46, 129, 0.35);
}

.ym-hero-copy {
  max-width: 56rem;
  margin: 0.5rem 0 0;
  font-size: 15.5px;
  line-height: 1.75;
}

.ym-hero-summary {
  min-width: min(100%, 260px);
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 22px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.12)),
    rgba(255, 255, 255, 0.14);
  box-shadow:
    0 20px 48px rgba(30, 41, 59, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.08);
  padding: 1.05rem 1.1rem;
  backdrop-filter: blur(14px);
}

.ym-hero-summary strong {
  display: block;
  margin: 0.2rem 0;
  color: #fff;
  font-size: 18px;
  font-weight: 950;
  line-height: 1.5;
}

.ym-readonly-notice {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px solid rgba(245, 158, 11, 0.32);
  border-radius: 20px;
  background: rgba(245, 158, 11, 0.09);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 0.9rem 1rem;
  color: var(--ym-text);
}

.ym-readonly-notice__badge {
  flex: 0 0 auto;
  border: 1px solid rgba(245, 158, 11, 0.38);
  border-radius: 999px;
  background: rgba(245, 158, 11, 0.16);
  padding: 0.35rem 0.7rem;
  color: #f59e0b;
  font-size: 13px;
  font-weight: 950;
}

.ym-readonly-notice p {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
}

.ym-users-success {
  border: 1px solid rgba(16, 185, 129, 0.34);
  border-radius: 20px;
  background: rgba(16, 185, 129, 0.1);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 0.9rem 1rem;
  color: #10b981;
}

.ym-users-success p {
  margin: 0;
  font-size: 14px;
  font-weight: 900;
  line-height: 1.7;
}

.ym-summary-grid {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1rem;
}

.ym-summary-card {
  --card-accent: var(--ym-section-accent);
  border-color: color-mix(in srgb, var(--card-accent, #6366f1) 60%, var(--ym-card-border));
  border-radius: 24px;
  background:
    radial-gradient(circle at 92% 6%, color-mix(in srgb, var(--card-accent, #6366f1) 45%, transparent), transparent 8.5rem),
    linear-gradient(180deg, color-mix(in srgb, var(--card-accent, #6366f1) 18%, var(--ym-card-bg)), var(--ym-card-bg));
  padding: 1rem;
  transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease;
}

.ym-summary-card::before {
  position: absolute;
  inset-inline: 1rem;
  top: 0;
  height: 5px;
  border-radius: 0 0 999px 999px;
  background: linear-gradient(90deg, var(--card-accent, #6366f1), color-mix(in srgb, var(--card-accent, #6366f1) 25%, transparent));
  box-shadow: 0 0 24px color-mix(in srgb, var(--card-accent, #6366f1) 60%, transparent);
  content: "";
}

.ym-summary-card::after {
  position: absolute;
  top: 1rem;
  inset-inline-end: 1rem;
  height: 0.8rem;
  width: 0.8rem;
  border-radius: 999px;
  background: var(--card-accent, #6366f1);
  box-shadow: 0 0 24px color-mix(in srgb, var(--card-accent, #6366f1) 80%, transparent);
  content: "";
}

.ym-filter-card::before,
.ym-table-card::before {
  position: absolute;
  inset-inline: 1.25rem;
  top: 0;
  height: 3px;
  border-end-end-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(90deg, var(--ym-section-accent), color-mix(in srgb, var(--ym-section-accent) 36%, #38bdf8));
  content: "";
}

.ym-summary-card:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 58%, var(--card-accent));
  box-shadow: 0 28px 64px rgba(2, 6, 23, 0.18), 0 0 34px color-mix(in srgb, var(--card-accent) 13%, transparent), inset 0 1px 0 rgba(255, 255, 255, 0.18);
  transform: translateY(-2px);
}

.ym-summary-card span,
.ym-summary-card small,
.ym-filter-card p,
.ym-table-card__head p,
.ym-table-card__head span {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.6;
}

.ym-summary-card strong {
  display: block;
  margin: 0.25rem 0;
  color: var(--ym-text);
  font-size: clamp(1.45rem, 2vw, 1.95rem);
  font-weight: 950;
  line-height: 1.15;
}

.ym-filter-card,
.ym-table-card {
  border-radius: 28px;
  background:
    radial-gradient(circle at 10% 0%, rgba(236, 72, 153, 0.1), transparent 18rem),
    radial-gradient(circle at 92% 12%, rgba(56, 189, 248, 0.1), transparent 20rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg));
  padding: clamp(1rem, 2vw, 1.35rem);
}

.ym-filter-card {
  display: grid;
  gap: 1rem;
}

.ym-filter-card h2,
.ym-table-card__head h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 18px;
  font-weight: 950;
}

.ym-filter-card p,
.ym-table-card__head p {
  margin: 0.25rem 0 0;
}

.ym-role-filter {
  display: grid;
  gap: 0.45rem;
}

.ym-role-filter span {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 900;
}

.ym-role-filter__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 26%, var(--ym-card-border));
  border-radius: 22px;
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--ym-section-accent) 12%, transparent), transparent 12rem),
    var(--ym-control-bg);
  padding: 0.45rem;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1), 0 12px 28px rgba(2, 6, 23, 0.08);
}

.ym-role-pill {
  --role-color: var(--ym-section-accent);
  min-height: 46px;
  border: 1px solid color-mix(in srgb, var(--role-color) 30%, var(--ym-soft-border));
  border-radius: 16px;
  background: color-mix(in srgb, var(--role-color) 7%, transparent);
  color: var(--ym-muted);
  font-size: 14.5px;
  font-weight: 950;
  padding: 0 1.05rem;
  transition: transform 160ms ease, background 160ms ease, color 160ms ease, box-shadow 160ms ease, border-color 160ms ease;
}

.ym-role-pill:hover,
.ym-role-pill.is-active {
  border-color: color-mix(in srgb, var(--role-color) 58%, transparent);
  background: color-mix(in srgb, var(--role-color) 18%, transparent);
  color: var(--ym-text);
  box-shadow: 0 14px 28px color-mix(in srgb, var(--role-color) 18%, transparent), inset 0 1px 0 rgba(255, 255, 255, 0.08);
  transform: translateY(-1px);
}

.ym-role-pill:focus-visible {
  outline: none;
  border-color: color-mix(in srgb, var(--role-color) 68%, transparent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--role-color) 22%, transparent), 0 14px 28px color-mix(in srgb, var(--role-color) 18%, transparent);
}

.ym-date-filter {
  display: grid;
  grid-template-columns: repeat(2, minmax(8.75rem, 10.25rem));
  align-items: end;
  gap: 0.55rem;
  justify-content: start;
}

.ym-date-filter label {
  display: grid;
  gap: 0.35rem;
  min-width: 0;
}

.ym-date-filter span {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 900;
}

.ym-date-filter input {
  min-height: 38px;
  width: 10.25rem;
  max-width: 100%;
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 26%, var(--ym-card-border));
  border-radius: 12px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 13.5px;
  font-weight: 900;
  padding: 0 0.65rem;
  color-scheme: dark;
  outline: none;
  transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease;
}

.ym-dashboard-light .ym-date-filter input {
  color-scheme: light;
}

.ym-date-filter input:focus {
  border-color: color-mix(in srgb, var(--ym-section-accent) 58%, transparent);
  background: color-mix(in srgb, var(--ym-section-accent) 8%, var(--ym-control-bg));
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ym-section-accent) 18%, transparent);
}

.ym-date-filter__error {
  grid-column: 1 / -1;
  margin: 0;
  border: 1px solid rgba(239, 68, 68, 0.36);
  border-radius: 14px;
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
  font-size: 13px;
  font-weight: 900;
  padding: 0.65rem 0.75rem;
}

.ym-active-filters {
  display: flex;
  flex-wrap: wrap;
  grid-column: 1 / -1;
  align-items: center;
  justify-content: space-between;
  gap: 0.7rem;
  border: 1px solid color-mix(in srgb, #38bdf8 34%, var(--ym-soft-border));
  border-radius: 18px;
  background: color-mix(in srgb, #38bdf8 10%, var(--ym-control-bg));
  padding: 0.75rem 0.85rem;
  color: var(--ym-text);
}

.ym-active-filters__chips {
  display: flex;
  min-width: 0;
  flex: 1 1 auto;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.ym-active-filter-chip {
  display: inline-flex;
  min-width: 0;
  max-width: min(100%, 24rem);
  align-items: center;
  gap: 0.45rem;
  border: 1px solid color-mix(in srgb, #38bdf8 34%, var(--ym-soft-border));
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 9%, var(--ym-control-bg));
  padding: 0.38rem 0.45rem 0.38rem 0.7rem;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 900;
  line-height: 1.35;
}

.ym-active-filter-chip span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-active-filter-chip strong {
  display: inline-block;
  max-width: 13rem;
  overflow: hidden;
  color: var(--ym-text);
  text-overflow: ellipsis;
  vertical-align: bottom;
  white-space: nowrap;
}

.ym-active-filter-chip button,
.ym-active-filters__clear {
  flex: 0 0 auto;
  border: 1px solid color-mix(in srgb, #38bdf8 42%, var(--ym-soft-border));
  background: color-mix(in srgb, #38bdf8 12%, var(--ym-control-bg));
  color: var(--ym-text);
  cursor: pointer;
  transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
}

.ym-active-filter-chip button {
  display: inline-grid;
  width: 1.35rem;
  height: 1.35rem;
  place-items: center;
  border-radius: 999px;
  font-size: 15px;
  font-weight: 950;
  line-height: 1;
}

.ym-active-filters__clear {
  border-radius: 999px;
  font-size: 13px;
  font-weight: 950;
  padding: 0.42rem 0.75rem;
}

.ym-active-filter-chip button:hover,
.ym-active-filter-chip button:focus-visible,
.ym-active-filters__clear:hover,
.ym-active-filters__clear:focus-visible {
  border-color: color-mix(in srgb, #38bdf8 64%, transparent);
  background: color-mix(in srgb, #38bdf8 18%, var(--ym-control-bg));
  outline: none;
  transform: translateY(-1px);
}

.ym-table-card__head {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-table-card__head span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-control-bg) 80%, transparent);
  padding: 0.4rem 0.75rem;
  color: var(--ym-text);
}

.ym-users-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
  padding: 2.5rem 1rem;
  color: var(--ym-muted);
  text-align: center;
  font-size: 15px;
  font-weight: 800;
}

.ym-users-state.is-error {
  color: #ef4444;
}

.ym-users-state__spinner {
  height: 26px;
  width: 26px;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 30%, transparent);
  border-top-color: #38bdf8;
  border-radius: 999px;
  animation: ym-users-spin 0.8s linear infinite;
}

@keyframes ym-users-spin {
  to { transform: rotate(360deg); }
}

.ym-users-table-wrap {
  position: relative;
  z-index: 1;
  overflow-x: auto;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 74%, transparent);
  border-radius: 22px;
  background: color-mix(in srgb, var(--ym-card-bg) 82%, transparent);
}

.ym-users-table {
  width: max-content;
  min-width: max(100%, 962px);
  border-collapse: collapse;
  table-layout: fixed;
}

.ym-users-table thead th {
  position: relative;
  padding: 0.85rem 0.95rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 75%, transparent);
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 90%, rgba(99, 102, 241, 0.16)), var(--ym-control-bg));
  color: var(--ym-text);
  font-size: 12.5px;
  font-weight: 950;
  text-align: start;
  text-transform: uppercase;
}

.ym-users-table th,
.ym-users-table td {
  box-sizing: border-box;
}

.ym-users-table thead th:nth-child(3) {
  direction: ltr;
  text-align: left;
}

.ym-users-table thead th:nth-child(3) .ym-sort-button {
  justify-content: flex-start;
  direction: ltr;
  text-align: left;
}

.ym-table-th-content {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 0.45rem;
}

.ym-sort-button {
  display: inline-flex;
  min-width: 0;
  max-width: calc(100% - 1rem);
  align-items: center;
  gap: 0.35rem;
  color: inherit;
  cursor: pointer;
  font: inherit;
  text-align: start;
}

.ym-sort-indicator {
  display: inline-flex;
  width: 1rem;
  justify-content: center;
  color: var(--ym-muted);
  font-size: 11px;
  line-height: 1;
  opacity: 0.58;
}

.ym-sort-indicator.is-active {
  color: #38bdf8;
  opacity: 1;
}


.ym-users-table tbody td {
  padding: 0.85rem 0.95rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 62%, transparent);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 750;
  vertical-align: middle;
}

.ym-users-table tbody tr:last-child td {
  border-bottom: none;
}

.ym-users-table tbody tr {
  transition: background 160ms ease, transform 160ms ease;
}

.ym-users-table tbody tr:hover td {
  background: color-mix(in srgb, #38bdf8 9%, transparent);
}

.ym-users-cell-id,
.ym-users-cell-created {
  color: var(--ym-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 850;
  white-space: nowrap;
}

.ym-users-cell-name {
  font-weight: 900;
  min-width: 0;
  overflow: hidden;
}

.ym-users-cell-email {
  color: var(--ym-muted);
  direction: ltr;
  min-width: 0;
  text-align: left;
  unicode-bidi: isolate;
  overflow: hidden;
}

.ym-truncated-text,
.ym-email-text {
  display: block;
  width: 100%;
  max-width: 100%;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-email-text {
  direction: ltr;
  text-align: left;
  unicode-bidi: isolate;
}

.ym-users-cell-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.ym-users-chip {
  display: inline-flex;
  min-width: 5.6rem;
  max-width: 7.5rem;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, #38bdf8 32%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 900;
  overflow: hidden;
  padding: 0.2rem 0.6rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-users-chip.is-muted {
  border-color: transparent;
  background: transparent;
  color: var(--ym-muted);
}

.ym-users-chip.is-admin {
  border-color: color-mix(in srgb, #ef4444 32%, transparent);
  background: color-mix(in srgb, #ef4444 18%, transparent);
  color: #ef4444;
}

.ym-users-chip.is-staff {
  border-color: color-mix(in srgb, #f59e0b 32%, transparent);
  background: color-mix(in srgb, #f59e0b 18%, transparent);
  color: #f59e0b;
}

.ym-users-chip.is-client {
  border-color: color-mix(in srgb, #10b981 32%, transparent);
  background: color-mix(in srgb, #10b981 18%, transparent);
  color: #10b981;
}

.ym-users-chip.is-designer {
  border-color: color-mix(in srgb, #a78bfa 32%, transparent);
  background: color-mix(in srgb, #a78bfa 18%, transparent);
  color: #a78bfa;
}

.ym-users-pagination {
  position: relative;
  z-index: 1;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px solid color-mix(in srgb, var(--ym-soft-border) 70%, transparent);
}

.ym-users-pagination__info {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
}

.ym-users-pagination__actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.ym-users-pagination__current {
  color: var(--ym-text);
  font-size: 13.5px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.ym-users-page-btn {
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 900;
  padding: 0.5rem 0.9rem;
  transition: border-color 160ms ease, background 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.ym-users-page-btn:hover:not(:disabled) {
  border-color: color-mix(in srgb, #38bdf8 52%, transparent);
  background: color-mix(in srgb, #38bdf8 10%, transparent);
  transform: translateY(-1px);
}

.ym-users-page-btn:disabled {
  cursor: not-allowed;
  opacity: 0.4;
}

.ym-users-action-btn {
  display: inline-flex;
  min-height: 38px;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 42%, var(--ym-soft-border));
  border-radius: 14px;
  background: color-mix(in srgb, var(--ym-section-accent) 12%, var(--ym-control-bg));
  color: var(--ym-text);
  cursor: pointer;
  font-size: 12.5px;
  font-weight: 950;
  line-height: 1.2;
  padding: 0.45rem 0.7rem;
  text-align: center;
  transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.ym-users-action-btn:hover {
  border-color: color-mix(in srgb, var(--ym-section-accent) 62%, transparent);
  background: color-mix(in srgb, var(--ym-section-accent) 18%, var(--ym-control-bg));
  box-shadow: 0 12px 26px color-mix(in srgb, var(--ym-section-accent) 18%, transparent);
  transform: translateY(-1px);
}

.ym-users-action-btn:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ym-section-accent) 24%, transparent);
}

.ym-role-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  background: rgba(2, 6, 23, 0.62);
  padding: 1rem;
  backdrop-filter: blur(10px);
}

.ym-role-modal {
  width: min(100%, 560px);
  max-height: min(92vh, 720px);
  overflow-y: auto;
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 34%, var(--ym-card-border));
  border-radius: 24px;
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--ym-section-accent) 16%, transparent), transparent 13rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 94%, rgba(255, 255, 255, 0.08)), var(--ym-card-bg));
  box-shadow:
    0 34px 90px rgba(2, 6, 23, 0.36),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  color: var(--ym-text);
  padding: clamp(1rem, 2vw, 1.35rem);
}

.ym-role-modal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-role-modal__head p {
  margin: 0 0 0.25rem;
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 900;
}

.ym-role-modal__head h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 20px;
  font-weight: 950;
  line-height: 1.25;
}

.ym-role-modal__close {
  display: inline-flex;
  width: 38px;
  height: 38px;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  cursor: pointer;
  font-size: 22px;
  font-weight: 800;
  line-height: 1;
}

.ym-role-modal__close:disabled {
  cursor: not-allowed;
  opacity: 0.45;
}

.ym-role-modal__user {
  display: grid;
  gap: 0.25rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 78%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, var(--ym-control-bg) 78%, transparent);
  padding: 0.85rem 0.95rem;
}

.ym-role-modal__user strong,
.ym-role-modal__user span {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-role-modal__user strong {
  color: var(--ym-text);
  font-size: 15px;
  font-weight: 950;
  unicode-bidi: isolate;
}

.ym-role-modal__user span {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  text-align: left;
  unicode-bidi: isolate;
}

.ym-role-modal__warning,
.ym-role-modal__error {
  margin: 0.85rem 0 0;
  border-radius: 16px;
  padding: 0.75rem 0.85rem;
  font-size: 13.5px;
  font-weight: 900;
  line-height: 1.65;
}

.ym-role-modal__warning {
  border: 1px solid rgba(245, 158, 11, 0.34);
  background: rgba(245, 158, 11, 0.1);
  color: #f59e0b;
}

.ym-role-modal__error {
  border: 1px solid rgba(239, 68, 68, 0.34);
  background: rgba(239, 68, 68, 0.1);
  color: #ef4444;
}

.ym-role-modal__roles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-top: 1rem;
}

.ym-role-option {
  --role-color: var(--ym-section-accent);
  position: relative;
  display: inline-flex;
  min-height: 42px;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--role-color) 32%, var(--ym-soft-border));
  border-radius: 999px;
  background: color-mix(in srgb, var(--role-color) 8%, transparent);
  color: var(--ym-muted);
  cursor: pointer;
  font-size: 13px;
  font-weight: 950;
  line-height: 1.2;
  padding: 0 0.95rem;
  transition: border-color 160ms ease, background 160ms ease, color 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.ym-role-option input {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}

.ym-role-option.is-selected {
  border-color: color-mix(in srgb, var(--role-color) 64%, transparent);
  background: color-mix(in srgb, var(--role-color) 18%, transparent);
  color: var(--ym-text);
  box-shadow: 0 10px 22px color-mix(in srgb, var(--role-color) 14%, transparent);
}

.ym-role-option.is-protected {
  cursor: not-allowed;
  opacity: 0.86;
}

.ym-role-modal__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.7rem;
  margin-top: 1.25rem;
  padding-top: 1rem;
  border-top: 1px solid color-mix(in srgb, var(--ym-soft-border) 72%, transparent);
}

.ym-role-modal__btn {
  display: inline-flex;
  min-height: 44px;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  border-radius: 14px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 950;
  padding: 0 1rem;
  transition: border-color 160ms ease, background 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.ym-role-modal__btn.is-secondary {
  border: 1px solid var(--ym-soft-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-role-modal__btn.is-primary {
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 62%, transparent);
  background: color-mix(in srgb, var(--ym-section-accent) 28%, var(--ym-control-bg));
  color: var(--ym-text);
}

.ym-role-modal__btn:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-role-modal__btn:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.ym-role-modal__spinner {
  width: 16px;
  height: 16px;
  border: 2px solid color-mix(in srgb, #fff 36%, transparent);
  border-top-color: #fff;
  border-radius: 999px;
  animation: ym-users-spin 0.8s linear infinite;
}

@media (min-width: 768px) {
  .ym-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-filter-card {
    grid-template-columns: minmax(0, 1fr) minmax(16rem, 22rem);
    align-items: center;
  }
}

@media (min-width: 1024px) {
  .ym-hero-content {
    align-items: center;
    flex-direction: row;
    justify-content: space-between;
  }

  .ym-summary-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .ym-readonly-notice {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-users-pagination {
    justify-content: center;
  }
}

/* YM-ADMIN-UI-001E manual fix: visibly colored summary cards */
.ym-summary-card {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  border-color: color-mix(in srgb, var(--card-accent, #6366f1) 56%, var(--ym-card-border));
  background:
    radial-gradient(
      circle at 92% 8%,
      color-mix(in srgb, var(--card-accent, #6366f1) 40%, transparent),
      transparent 8.5rem
    ),
    linear-gradient(
      180deg,
      color-mix(in srgb, var(--card-accent, #6366f1) 16%, var(--ym-card-bg)),
      var(--ym-card-bg)
    );
  box-shadow:
    var(--ym-card-shadow),
    0 18px 44px rgba(2, 6, 23, 0.14),
    0 0 28px color-mix(in srgb, var(--card-accent, #6366f1) 14%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
}

.ym-summary-card::before {
  position: absolute;
  inset-inline: 1rem;
  top: 0;
  z-index: 0;
  height: 4px;
  border-radius: 0 0 999px 999px;
  background: linear-gradient(
    90deg,
    var(--card-accent, #6366f1),
    color-mix(in srgb, var(--card-accent, #6366f1) 20%, transparent)
  );
  box-shadow: 0 0 24px color-mix(in srgb, var(--card-accent, #6366f1) 62%, transparent);
  content: "";
}

.ym-summary-card::after {
  position: absolute;
  top: 1rem;
  inset-inline-end: 1rem;
  z-index: 0;
  width: 0.72rem;
  height: 0.72rem;
  border-radius: 999px;
  background: var(--card-accent, #6366f1);
  box-shadow: 0 0 22px color-mix(in srgb, var(--card-accent, #6366f1) 75%, transparent);
  content: "";
}

.ym-summary-card > span,
.ym-summary-card > strong,
.ym-summary-card > small {
  position: relative;
  z-index: 1;
}

.ym-summary-card > span {
  color: color-mix(in srgb, var(--card-accent, #6366f1) 72%, var(--ym-muted));
}

.ym-summary-card > strong {
  color: var(--ym-text);
}

.ym-summary-card > small {
  color: var(--ym-muted);
}




/* YM-ADMIN-UI final fix: clean semantic users table */
.ym-users-table-wrap {
  overflow-x: auto;
}

.ym-users-table {
  width: 100% !important;
  min-width: 920px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  direction: rtl;
}

/* DOM والترتيب البصري من اليمين لليسار:
   # | الاسم | البريد الإلكتروني | الأدوار | تاريخ الإنشاء
*/
.ym-users-table th:nth-child(1),
.ym-users-table td:nth-child(1) {
  width: 6%;
}

.ym-users-table th:nth-child(2),
.ym-users-table td:nth-child(2) {
  width: 30%;
}

.ym-users-table th:nth-child(3),
.ym-users-table td:nth-child(3) {
  width: 28%;
}

.ym-users-table th:nth-child(4),
.ym-users-table td:nth-child(4) {
  width: 18%;
}

.ym-users-table th:nth-child(5),
.ym-users-table td:nth-child(5) {
  width: 18%;
}

.ym-users-table th,
.ym-users-table td {
  box-sizing: border-box;
  overflow: hidden;
  vertical-align: middle;
}

.ym-users-table .ym-table-th-content,
.ym-users-table .ym-sort-button {
  width: 100%;
}

/* # */
.ym-users-th-id,
.ym-users-cell-id {
  direction: ltr;
  text-align: center;
  white-space: nowrap;
}

.ym-users-th-id .ym-table-th-content,
.ym-users-th-id .ym-sort-button {
  justify-content: center;
}

/* الاسم */
.ym-users-th-name,
.ym-users-cell-name {
  direction: rtl;
  text-align: right;
  white-space: nowrap;
}

.ym-users-th-name .ym-table-th-content,
.ym-users-th-name .ym-sort-button {
  justify-content: flex-end;
  direction: rtl;
  text-align: right;
}

.ym-name-preview {
  display: inline-block;
  max-width: 16ch;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

/* البريد */
.ym-users-th-email,
.ym-users-cell-email {
  direction: ltr;
  text-align: left;
  white-space: nowrap;
  unicode-bidi: isolate;
}

.ym-users-th-email .ym-table-th-content,
.ym-users-th-email .ym-sort-button {
  justify-content: flex-start;
  direction: ltr;
  text-align: left;
}

.ym-email-preview {
  display: inline-block;
  max-width: 16ch;
  direction: ltr;
  text-align: left;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

/* الأدوار */
.ym-users-th-roles,
.ym-users-cell-roles {
  direction: rtl;
  text-align: center;
  white-space: nowrap;
}

.ym-users-th-roles .ym-table-th-content {
  justify-content: center;
}

.ym-users-cell-roles .ym-users-chip {
  display: inline-flex;
  min-width: 76px;
  max-width: 92px;
  justify-content: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* التاريخ */
.ym-users-th-created,
.ym-users-cell-created {
  direction: ltr;
  text-align: center;
  white-space: nowrap;
}

.ym-users-th-created .ym-table-th-content,
.ym-users-th-created .ym-sort-button {
  justify-content: center;
  direction: ltr;
  text-align: center;
}

/* YM-ADMIN-UI final fix: force users table cells back to semantic table-cell */
.ym-users-table {
  width: 100% !important;
  min-width: 920px !important;
  table-layout: fixed !important;
  border-collapse: separate;
  border-spacing: 0;
  direction: rtl;
}

/* هذا هو الإصلاح الحاسم:
   أي CSS قديم حوّل td/th إلى flex/grid يتم إلغاؤه هنا.
*/
.ym-users-table thead,
.ym-users-table tbody {
  display: table-header-group;
}

.ym-users-table tbody {
  display: table-row-group;
}

.ym-users-table tr {
  display: table-row !important;
}

.ym-users-table th,
.ym-users-table td,
.ym-users-cell-id,
.ym-users-cell-name,
.ym-users-cell-email,
.ym-users-cell-roles,
.ym-users-cell-created {
  display: table-cell !important;
  box-sizing: border-box;
  overflow: hidden;
  vertical-align: middle;
}

/* توزيع الأعمدة النهائي */
.ym-users-table th:nth-child(1),
.ym-users-table td:nth-child(1) {
  width: 6% !important;
}

.ym-users-table th:nth-child(2),
.ym-users-table td:nth-child(2) {
  width: 30% !important;
}

.ym-users-table th:nth-child(3),
.ym-users-table td:nth-child(3) {
  width: 28% !important;
}

.ym-users-table th:nth-child(4),
.ym-users-table td:nth-child(4) {
  width: 18% !important;
}

.ym-users-table th:nth-child(5),
.ym-users-table td:nth-child(5) {
  width: 18% !important;
}

/* العناوين */
.ym-users-table .ym-table-th-content,
.ym-users-table .ym-sort-button {
  width: 100%;
}

/* # */
.ym-users-th-id,
.ym-users-cell-id {
  direction: ltr;
  text-align: center !important;
  white-space: nowrap;
}

.ym-users-th-id .ym-table-th-content,
.ym-users-th-id .ym-sort-button {
  justify-content: center;
}

/* الاسم */
.ym-users-th-name,
.ym-users-cell-name {
  direction: rtl;
  text-align: right !important;
  white-space: nowrap;
}

.ym-users-th-name .ym-table-th-content,
.ym-users-th-name .ym-sort-button {
  justify-content: flex-end;
  direction: rtl;
  text-align: right;
}

.ym-name-preview {
  display: inline-block !important;
  max-width: 16ch;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

/* البريد */
.ym-users-th-email,
.ym-users-cell-email {
  direction: ltr;
  text-align: left !important;
  white-space: nowrap;
  unicode-bidi: isolate;
}

.ym-users-th-email .ym-table-th-content,
.ym-users-th-email .ym-sort-button {
  justify-content: flex-start;
  direction: ltr;
  text-align: left;
}

.ym-email-preview {
  display: inline-block !important;
  max-width: 16ch;
  direction: ltr;
  text-align: left;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

/* الأدوار */
.ym-users-th-roles,
.ym-users-cell-roles {
  direction: rtl;
  text-align: center !important;
  white-space: nowrap;
}

.ym-users-th-roles .ym-table-th-content {
  justify-content: center;
}

.ym-users-cell-roles .ym-users-chip {
  display: inline-flex !important;
  min-width: 76px;
  max-width: 92px;
  justify-content: center;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  vertical-align: middle;
}

/* التاريخ */
.ym-users-th-created,
.ym-users-cell-created {
  direction: ltr;
  text-align: center !important;
  white-space: nowrap;
}

.ym-users-th-created .ym-table-th-content,
.ym-users-th-created .ym-sort-button {
  justify-content: center;
  direction: ltr;
  text-align: center;
}

/* إبقاء resize مخفيًا في users */
/* YM-ADMIN-UI final fix: users name bidi alignment */
.ym-users-th-name,
.ym-users-cell-name {
  direction: rtl !important;
  text-align: right !important;
  white-space: nowrap !important;
}

.ym-users-th-name .ym-table-th-content,
.ym-users-th-name .ym-sort-button {
  justify-content: flex-end !important;
  direction: rtl !important;
  text-align: right !important;
}

/* المهم: لا نجعل الاسم يرث RTL دائمًا.
   الاسم الإنجليزي يأخذ dir="ltr" من Vue، والاسم العربي يأخذ dir="rtl".
*/
.ym-users-cell-name .ym-name-preview {
  display: inline-block !important;
  max-width: 16ch !important;
  overflow: hidden !important;
  text-overflow: clip !important;
  white-space: nowrap !important;
  vertical-align: middle !important;
  unicode-bidi: isolate !important;
}

.ym-users-cell-name .ym-name-preview[dir="ltr"] {
  direction: ltr !important;
  text-align: left !important;
}

.ym-users-cell-name .ym-name-preview[dir="rtl"] {
  direction: rtl !important;
  text-align: right !important;
}

/* YM-ADMIN-UI final fix: correct RTL name header flex alignment */
.ym-users-th-name .ym-table-th-content,
.ym-users-th-name .ym-sort-button {
  justify-content: flex-start !important;
  direction: rtl !important;
  text-align: right !important;
}

/* YM-ADMIN-UI final fix: compact name-email column gap */

/* توزيع أكثر واقعية:
   # 6% | الاسم 22% | البريد 26% | الأدوار 20% | التاريخ 26%
*/
.ym-users-table th:nth-child(1),
.ym-users-table td:nth-child(1) {
  width: 6% !important;
}

.ym-users-table th:nth-child(2),
.ym-users-table td:nth-child(2) {
  width: 22% !important;
}

.ym-users-table th:nth-child(3),
.ym-users-table td:nth-child(3) {
  width: 26% !important;
}

.ym-users-table th:nth-child(4),
.ym-users-table td:nth-child(4) {
  width: 20% !important;
}

.ym-users-table th:nth-child(5),
.ym-users-table td:nth-child(5) {
  width: 26% !important;
}

/* الاسم يبقى تحت عنوان الاسم */
.ym-users-th-name,
.ym-users-cell-name {
  direction: rtl !important;
  text-align: right !important;
}

.ym-users-th-name .ym-table-th-content,
.ym-users-th-name .ym-sort-button {
  justify-content: flex-start !important;
  direction: rtl !important;
  text-align: right !important;
}

/* البريد يقترب من عمود الاسم، لكن النص داخله يبقى LTR */
.ym-users-th-email,
.ym-users-cell-email {
  direction: rtl !important;
  text-align: right !important;
}

.ym-users-th-email .ym-table-th-content,
.ym-users-th-email .ym-sort-button {
  justify-content: flex-start !important;
  direction: rtl !important;
  text-align: right !important;
}

.ym-users-cell-email .ym-email-preview {
  direction: ltr !important;
  text-align: left !important;
  unicode-bidi: isolate !important;
}

/* YM-USERS-UI-001B: six-column role assignment table */
.ym-users-table {
  min-width: 1080px !important;
}

.ym-users-table th,
.ym-users-table td,
.ym-users-cell-id,
.ym-users-cell-name,
.ym-users-cell-email,
.ym-users-cell-roles,
.ym-users-cell-created,
.ym-users-cell-actions {
  display: table-cell !important;
}

.ym-users-table th:nth-child(1),
.ym-users-table td:nth-child(1) {
  width: 6% !important;
}

.ym-users-table th:nth-child(2),
.ym-users-table td:nth-child(2) {
  width: 20% !important;
}

.ym-users-table th:nth-child(3),
.ym-users-table td:nth-child(3) {
  width: 24% !important;
}

.ym-users-table th:nth-child(4),
.ym-users-table td:nth-child(4) {
  width: 18% !important;
}

.ym-users-table th:nth-child(5),
.ym-users-table td:nth-child(5) {
  width: 20% !important;
}

.ym-users-table th:nth-child(6),
.ym-users-table td:nth-child(6) {
  width: 12% !important;
}

.ym-users-th-actions,
.ym-users-cell-actions {
  direction: rtl !important;
  text-align: center !important;
  white-space: nowrap;
}

.ym-users-th-actions .ym-table-th-content {
  justify-content: center;
}

/* YM-USERS-UI-001C: role icons and clearer role assignment modal */
.ym-users-cell-roles,
.ym-users-cell-actions {
  overflow: visible !important;
}

.ym-users-cell-roles {
  line-height: 1 !important;
  white-space: normal !important;
}

.ym-users-role-empty {
  display: inline-flex;
  width: 2.25rem;
  height: 2.25rem;
  align-items: center;
  justify-content: center;
  color: var(--ym-muted);
  font-size: 18px;
  font-weight: 950;
}

.ym-users-role-icon {
  --role-color: #38bdf8;
  position: relative;
  display: inline-flex;
  width: 2.35rem;
  height: 2.35rem;
  align-items: center;
  justify-content: center;
  margin: 0.14rem;
  border: 1px solid color-mix(in srgb, var(--role-color) 52%, var(--ym-soft-border));
  border-radius: 999px;
  background:
    radial-gradient(circle at 30% 18%, rgba(255, 255, 255, 0.38), transparent 1.2rem),
    color-mix(in srgb, var(--role-color) 18%, var(--ym-control-bg));
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--role-color) 10%, transparent),
    0 10px 22px color-mix(in srgb, var(--role-color) 20%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.18);
  color: var(--role-color);
  vertical-align: middle;
  transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.ym-users-role-icon:hover {
  border-color: color-mix(in srgb, var(--role-color) 78%, transparent);
  background:
    radial-gradient(circle at 30% 18%, rgba(255, 255, 255, 0.48), transparent 1.2rem),
    color-mix(in srgb, var(--role-color) 26%, var(--ym-control-bg));
  box-shadow:
    0 0 0 1px color-mix(in srgb, var(--role-color) 18%, transparent),
    0 14px 28px color-mix(in srgb, var(--role-color) 28%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.22);
  transform: translateY(-1px);
}

.ym-users-role-icon svg,
.ym-users-action-btn svg,
.ym-role-option__icon {
  display: block;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.ym-users-role-icon svg {
  width: 1.12rem;
  height: 1.12rem;
}

.ym-users-role-icon::after,
.ym-users-action-btn::after {
  position: absolute;
  inset-inline-start: 50%;
  bottom: calc(100% + 0.5rem);
  z-index: 40;
  width: max-content;
  max-width: 10rem;
  padding: 0.34rem 0.55rem;
  border: 1px solid color-mix(in srgb, var(--ym-text) 16%, transparent);
  border-radius: 10px;
  background: color-mix(in srgb, var(--ym-card-bg) 92%, #020617);
  box-shadow: 0 14px 32px rgba(2, 6, 23, 0.24);
  color: var(--ym-text);
  content: attr(data-tooltip);
  font-size: 12px;
  font-weight: 900;
  line-height: 1.2;
  opacity: 0;
  pointer-events: none;
  text-align: center;
  transform: translateX(-50%) translateY(0.25rem);
  transition: opacity 140ms ease, transform 140ms ease;
  white-space: nowrap;
}

.ym-users-role-icon:hover::after,
.ym-users-action-btn:hover::after,
.ym-users-role-icon:focus-visible::after,
.ym-users-action-btn:focus-visible::after {
  opacity: 1;
  transform: translateX(-50%) translateY(0);
}

.ym-users-action-btn {
  position: relative;
  width: 3rem;
  height: 3rem;
  min-height: 3rem;
  padding: 0;
  border-color: color-mix(in srgb, var(--ym-section-accent) 60%, var(--ym-soft-border));
  border-radius: 16px;
  background:
    radial-gradient(circle at 28% 18%, rgba(255, 255, 255, 0.34), transparent 1.5rem),
    color-mix(in srgb, var(--ym-section-accent) 18%, var(--ym-control-bg));
  box-shadow:
    0 12px 28px color-mix(in srgb, var(--ym-section-accent) 18%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  color: color-mix(in srgb, var(--ym-section-accent) 86%, var(--ym-text));
}

.ym-users-action-btn svg {
  width: 1.35rem;
  height: 1.35rem;
}

.ym-user-details-trigger {
  display: none;
  margin-inline-start: 0.55rem;
  min-height: 2rem;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, #38bdf8 42%, var(--ym-soft-border));
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 10%, var(--ym-control-bg));
  color: var(--ym-text);
  cursor: pointer;
  font-size: 12px;
  font-weight: 950;
  padding: 0 0.65rem;
  transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
  vertical-align: middle;
  white-space: nowrap;
}

.ym-user-details-trigger:hover,
.ym-user-details-trigger:focus-visible {
  border-color: color-mix(in srgb, #38bdf8 68%, transparent);
  background: color-mix(in srgb, #38bdf8 18%, var(--ym-control-bg));
  outline: none;
  transform: translateY(-1px);
}

.ym-users-action-btn:hover {
  border-color: color-mix(in srgb, var(--ym-section-accent) 78%, transparent);
  background:
    radial-gradient(circle at 28% 18%, rgba(255, 255, 255, 0.44), transparent 1.5rem),
    color-mix(in srgb, var(--ym-section-accent) 26%, var(--ym-control-bg));
  box-shadow:
    0 16px 34px color-mix(in srgb, var(--ym-section-accent) 26%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.ym-role-modal {
  width: min(100%, 640px);
  border-color: color-mix(in srgb, var(--ym-section-accent) 48%, var(--ym-card-border));
  border-radius: 26px;
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--ym-section-accent) 20%, transparent), transparent 14rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 96%, rgba(255, 255, 255, 0.14)), var(--ym-card-bg));
  box-shadow:
    0 38px 100px rgba(2, 6, 23, 0.42),
    0 0 0 1px rgba(255, 255, 255, 0.06),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  padding: clamp(1.25rem, 3vw, 1.75rem);
}

.ym-role-modal__head {
  gap: 1.25rem;
  margin-bottom: 1.25rem;
}

.ym-role-modal__head p {
  margin-bottom: 0.35rem;
  color: color-mix(in srgb, var(--ym-section-accent) 70%, var(--ym-muted));
  font-size: 14px;
  letter-spacing: 0;
}

.ym-role-modal__head h2 {
  font-size: clamp(1.55rem, 2.6vw, 2rem);
  line-height: 1.18;
}

.ym-role-modal__close {
  width: 2.8rem;
  height: 2.8rem;
  border-color: color-mix(in srgb, var(--ym-soft-border) 82%, var(--ym-text));
  border-radius: 16px;
  font-size: 26px;
}

.ym-role-modal__user {
  gap: 0.38rem;
  border-color: color-mix(in srgb, var(--ym-section-accent) 26%, var(--ym-soft-border));
  border-radius: 20px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 88%, rgba(255, 255, 255, 0.1)), var(--ym-control-bg));
  padding: 1rem 1.1rem;
}

.ym-role-modal__user strong {
  font-size: 17px;
}

.ym-role-modal__user span {
  font-size: 14.5px;
}

.ym-role-modal__warning,
.ym-role-modal__error {
  margin-top: 1rem;
  border-radius: 18px;
  padding: 0.9rem 1rem;
  font-size: 15px;
}

.ym-role-modal__roles {
  gap: 0.8rem;
  margin-top: 1.15rem;
}

.ym-role-option {
  min-height: 3.35rem;
  gap: 0.55rem;
  border-width: 1.5px;
  border-color: color-mix(in srgb, var(--role-color) 40%, var(--ym-soft-border));
  background: color-mix(in srgb, var(--role-color) 10%, var(--ym-control-bg));
  color: var(--ym-text);
  font-size: 15px;
  padding: 0 0.95rem;
}

.ym-role-option:hover {
  border-color: color-mix(in srgb, var(--role-color) 62%, transparent);
  background: color-mix(in srgb, var(--role-color) 16%, var(--ym-control-bg));
  transform: translateY(-1px);
}

.ym-role-option__icon {
  width: 1.15rem;
  height: 1.15rem;
  color: var(--role-color);
}

.ym-role-option__name {
  max-width: 9.5rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-role-option__check {
  display: inline-flex;
  width: 1.35rem;
  height: 1.35rem;
  align-items: center;
  justify-content: center;
  border: 1px solid color-mix(in srgb, var(--role-color) 44%, var(--ym-soft-border));
  border-radius: 999px;
  color: transparent;
  font-size: 12px;
  font-weight: 950;
}

.ym-role-option.is-selected {
  border-color: color-mix(in srgb, var(--role-color) 76%, transparent);
  background:
    radial-gradient(circle at 92% 12%, color-mix(in srgb, var(--role-color) 30%, transparent), transparent 4.5rem),
    color-mix(in srgb, var(--role-color) 20%, var(--ym-control-bg));
  box-shadow:
    0 14px 30px color-mix(in srgb, var(--role-color) 18%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.14);
}

.ym-role-option.is-selected .ym-role-option__check {
  background: var(--role-color);
  color: #fff;
}

.ym-role-option.is-protected {
  border-style: dashed;
  opacity: 0.92;
}

.ym-role-modal__actions {
  gap: 0.85rem;
  margin-top: 1.45rem;
  padding-top: 1.2rem;
}

.ym-role-modal__btn {
  min-height: 3.1rem;
  min-width: 7.25rem;
  border-radius: 16px;
  font-size: 15.5px;
  padding: 0 1.2rem;
}

.ym-role-modal__btn.is-secondary {
  border-color: color-mix(in srgb, var(--ym-soft-border) 84%, var(--ym-text));
}

.ym-role-modal__btn.is-primary {
  border-color: color-mix(in srgb, var(--ym-section-accent) 76%, transparent);
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-section-accent) 42%, var(--ym-control-bg)), color-mix(in srgb, var(--ym-section-accent) 30%, var(--ym-control-bg)));
  box-shadow: 0 14px 28px color-mix(in srgb, var(--ym-section-accent) 24%, transparent);
}

.ym-user-details-backdrop {
  position: fixed;
  inset: 0;
  z-index: 78;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  background: rgba(2, 6, 23, 0.58);
  padding: 0.75rem;
  backdrop-filter: blur(10px);
}

.ym-user-details-drawer {
  width: min(100%, 34rem);
  max-height: min(88vh, 42rem);
  overflow-y: auto;
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 42%, var(--ym-card-border));
  border-radius: 24px 24px 18px 18px;
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--ym-section-accent) 20%, transparent), transparent 13rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 96%, rgba(255, 255, 255, 0.12)), var(--ym-card-bg));
  box-shadow:
    0 34px 90px rgba(2, 6, 23, 0.36),
    inset 0 1px 0 rgba(255, 255, 255, 0.14);
  color: var(--ym-text);
  padding: 1rem;
}

.ym-user-details-drawer__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-user-details-drawer__head h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 1.35rem;
  font-weight: 950;
  line-height: 1.2;
}

.ym-user-details-drawer__head p {
  margin: 0.35rem 0 0;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 850;
  line-height: 1.65;
}

.ym-user-details-drawer__close {
  display: inline-flex;
  width: 2.7rem;
  height: 2.7rem;
  flex: 0 0 auto;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  cursor: pointer;
  font-size: 26px;
  font-weight: 850;
  line-height: 1;
  transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
}

.ym-user-details-drawer__close:hover,
.ym-user-details-drawer__close:focus-visible {
  border-color: color-mix(in srgb, var(--ym-section-accent) 58%, transparent);
  background: color-mix(in srgb, var(--ym-section-accent) 12%, var(--ym-control-bg));
  outline: none;
  transform: translateY(-1px);
}

.ym-user-details-list {
  display: grid;
  gap: 0.7rem;
  margin: 0;
}

.ym-user-details-list__item {
  display: grid;
  gap: 0.35rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 78%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, var(--ym-control-bg) 72%, transparent);
  padding: 0.8rem 0.9rem;
}

.ym-user-details-list__item dt {
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 900;
}

.ym-user-details-list__item dd {
  min-width: 0;
  margin: 0;
  overflow-wrap: anywhere;
  color: var(--ym-text);
  font-size: 15px;
  font-weight: 950;
  line-height: 1.55;
  unicode-bidi: isolate;
}

.ym-user-details-list__item dd[dir="ltr"] {
  text-align: left;
}

.ym-user-details-empty {
  color: var(--ym-muted);
}

.ym-user-details-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.ym-user-details-role {
  --role-color: #38bdf8;
  display: inline-flex;
  min-width: 0;
  max-width: 100%;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid color-mix(in srgb, var(--role-color) 42%, var(--ym-soft-border));
  border-radius: 999px;
  background: color-mix(in srgb, var(--role-color) 12%, var(--ym-control-bg));
  color: var(--ym-text);
  padding: 0.38rem 0.65rem;
}

.ym-user-details-role svg {
  display: block;
  width: 1rem;
  height: 1rem;
  flex: 0 0 auto;
  color: var(--role-color);
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 2;
}

.ym-user-details-role span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-user-details-drawer__actions {
  display: flex;
  gap: 0.65rem;
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px solid color-mix(in srgb, var(--ym-soft-border) 72%, transparent);
}

.ym-user-details-drawer__btn {
  display: inline-flex;
  min-height: 2.85rem;
  flex: 1 1 0;
  align-items: center;
  justify-content: center;
  border-radius: 15px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 950;
  padding: 0 0.9rem;
  transition: border-color 160ms ease, background 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.ym-user-details-drawer__btn.is-secondary {
  border: 1px solid var(--ym-soft-border);
  background: var(--ym-control-bg);
  color: var(--ym-text);
}

.ym-user-details-drawer__btn.is-primary {
  border: 1px solid color-mix(in srgb, var(--ym-section-accent) 68%, transparent);
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-section-accent) 38%, var(--ym-control-bg)), color-mix(in srgb, var(--ym-section-accent) 26%, var(--ym-control-bg)));
  color: var(--ym-text);
  box-shadow: 0 14px 28px color-mix(in srgb, var(--ym-section-accent) 20%, transparent);
}

.ym-user-details-drawer__btn:hover,
.ym-user-details-drawer__btn:focus-visible {
  outline: none;
  transform: translateY(-1px);
}

@media (min-width: 761px) {
  .ym-user-details-backdrop {
    display: none;
  }
}

/* YM-USERS-UI-003: table and filters responsive polish */
.ym-filter-card {
  grid-template-columns: minmax(16rem, 1fr) minmax(22rem, 1.15fr) minmax(18rem, auto) !important;
  align-items: end;
  gap: 1rem 1.1rem;
}

.ym-filter-card > div:first-child {
  align-self: center;
  min-width: 0;
}

.ym-role-filter,
.ym-date-filter {
  min-width: 0;
}

.ym-role-filter__pills {
  align-items: center;
  min-width: 0;
  max-width: 100%;
  padding: 0.4rem;
}

.ym-role-pill {
  min-height: 40px;
  max-width: 100%;
  padding-inline: 0.85rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-date-filter {
  justify-content: end;
}

.ym-date-filter input {
  min-height: 38px;
  width: 9.75rem;
}

.ym-active-filters {
  align-items: flex-start;
  overflow: hidden;
}

.ym-active-filters__chips {
  flex: 999 1 18rem;
  overflow: hidden;
}

.ym-active-filter-chip {
  max-width: min(100%, 22rem);
}

.ym-active-filter-chip span {
  max-width: 100%;
}

.ym-active-filter-chip strong {
  max-width: min(12rem, 42vw);
}

.ym-active-filters__clear {
  margin-inline-start: auto;
  min-height: 2.15rem;
  white-space: nowrap;
}

.ym-table-card {
  overflow: hidden;
}

.ym-users-table-wrap {
  max-width: 100%;
  overflow-x: auto;
  overflow-y: visible;
  overscroll-behavior-inline: contain;
  scrollbar-gutter: stable;
}

.ym-users-table {
  min-width: 1120px !important;
}

.ym-users-table tbody td {
  padding-block: 0.72rem;
  padding-inline: 0.82rem;
}

.ym-users-table th:nth-child(1),
.ym-users-table td:nth-child(1) {
  width: 5% !important;
}

.ym-users-table th:nth-child(2),
.ym-users-table td:nth-child(2) {
  width: 19% !important;
}

.ym-users-table th:nth-child(3),
.ym-users-table td:nth-child(3) {
  width: 27% !important;
}

.ym-users-table th:nth-child(4),
.ym-users-table td:nth-child(4) {
  width: 18% !important;
}

.ym-users-table th:nth-child(5),
.ym-users-table td:nth-child(5) {
  width: 19% !important;
}

.ym-users-table th:nth-child(6),
.ym-users-table td:nth-child(6) {
  width: 12% !important;
}

.ym-users-cell-name .ym-name-preview,
.ym-users-cell-email .ym-email-preview {
  max-width: 100% !important;
  text-overflow: ellipsis !important;
}

.ym-users-cell-roles {
  line-height: 1.15 !important;
}

.ym-users-role-icon {
  width: 2.12rem;
  height: 2.12rem;
  margin: 0.1rem;
}

.ym-users-role-icon svg {
  width: 1rem;
  height: 1rem;
}

.ym-users-action-btn {
  width: 2.75rem;
  height: 2.75rem;
  min-height: 2.75rem;
  border-radius: 15px;
}

.ym-users-action-btn svg {
  width: 1.24rem;
  height: 1.24rem;
}

.ym-users-state {
  min-height: 12rem;
  justify-content: center;
  border: 1px dashed color-mix(in srgb, var(--ym-soft-border) 72%, transparent);
  border-radius: 20px;
  background: color-mix(in srgb, var(--ym-control-bg) 44%, transparent);
}

@media (max-width: 1180px) {
  .ym-filter-card {
    grid-template-columns: minmax(0, 1fr) minmax(18rem, auto) !important;
    align-items: start;
  }

  .ym-filter-card > div:first-child {
    grid-column: 1 / -1;
  }

  .ym-date-filter {
    justify-content: start;
  }
}

@media (max-width: 760px) {
  .ym-filter-card {
    grid-template-columns: minmax(0, 1fr) !important;
    gap: 0.9rem;
    padding: 0.95rem;
  }

  .ym-role-filter__pills {
    border-radius: 18px;
  }

  .ym-role-pill {
    flex: 1 1 8.5rem;
    justify-content: center;
    min-width: 0;
  }

  .ym-date-filter {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    width: 100%;
  }

  .ym-date-filter input {
    width: 100%;
  }

  .ym-active-filters {
    flex-direction: column;
    align-items: stretch;
  }

  .ym-active-filters__chips {
    flex: 1 1 auto;
    width: 100%;
  }

  .ym-active-filter-chip {
    max-width: 100%;
  }

  .ym-active-filter-chip strong {
    max-width: min(12rem, 48vw);
  }

  .ym-active-filters__clear {
    width: 100%;
    margin-inline-start: 0;
  }

  .ym-table-card {
    padding: 0.95rem;
  }

  .ym-table-card__head {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-table-card__head span {
    width: fit-content;
    max-width: 100%;
  }

  .ym-users-table {
    min-width: 980px !important;
  }

  .ym-users-table thead th,
  .ym-users-table tbody td {
    padding-inline: 0.7rem;
  }

  .ym-user-details-trigger {
    display: inline-flex;
  }

  .ym-users-pagination {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-users-pagination__actions {
    justify-content: space-between;
    width: 100%;
  }

  .ym-users-page-btn {
    min-height: 44px;
    flex: 1 1 0;
  }
}

@media (max-width: 420px) {
  .ym-date-filter {
    grid-template-columns: minmax(0, 1fr);
  }

  .ym-active-filter-chip {
    width: 100%;
    justify-content: space-between;
  }

  .ym-active-filter-chip strong {
    max-width: 52vw;
  }

  .ym-users-pagination__actions {
    flex-wrap: wrap;
  }

  .ym-users-pagination__current {
    order: -1;
    width: 100%;
    text-align: center;
  }
}

</style>
