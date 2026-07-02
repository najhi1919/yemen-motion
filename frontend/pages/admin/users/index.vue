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
        <p>{{ copy.empty }}</p>
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
                <span v-if="!user.roles.length" class="ym-users-chip is-muted">—</span>
                <span
                  v-for="role in user.roles"
                  :key="role"
                  class="ym-users-chip"
                  :class="`is-${role}`"
                  :title="role"
                >{{ role }}</span>
              </td>
              <td class="ym-users-cell-created">{{ formatCreatedAt(user.created_at) }}</td>
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
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

type UsersSortKey = 'id' | 'name' | 'email' | 'created_at'
type SortDirection = 'asc' | 'desc'
const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'قراءة فقط',
    kicker: 'إدارة المستخدمين',
    title: 'مركز المستخدمين',
    copy: 'امتداد تشغيلي للوحة التحكم يعرض حسابات المنصة وأدوارها دون أي إجراءات إنشاء أو تعديل أو حذف.',
    readonlyNotice: 'هذه الصفحة مخصصة للقراءة فقط وتعرض بيانات المستخدمين دون أي إجراءات تغيير.',
    activeFilter: 'فلتر الدور',
    pageScope: 'الصفحة',
    allRoles: 'كل الأدوار',
    filterTitle: 'فلترة إدارية مباشرة',
    filterCopy: 'اختر الدور المطلوب لتضييق العرض دون إضافة بحث نصي داخل الصفحة.',
    roleFilter: 'الدور',
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
    colId: '#',
    colName: 'الاسم',
    colEmail: 'البريد الإلكتروني',
    colRoles: 'الأدوار',
    colCreated: 'تاريخ الإنشاء',
    prev: 'السابق',
    next: 'التالي',
    pageInfo: (page: number, last: number, total: number) =>
      `الصفحة ${page} من ${last} - ${total} مستخدم`
  },
  en: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'Read-only',
    kicker: 'User management',
    title: 'Users Command Center',
    copy: 'An operational dashboard extension for viewing platform accounts and roles without create, edit, or delete actions.',
    readonlyNotice: 'This page is read-only and displays user data without any change actions.',
    activeFilter: 'Role filter',
    pageScope: 'Page',
    allRoles: 'All roles',
    filterTitle: 'Direct admin filtering',
    filterCopy: 'Select a role to narrow the view through direct administrative filtering.',
    roleFilter: 'Role',
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
    colId: '#',
    colName: 'Name',
    colEmail: 'Email',
    colRoles: 'Roles',
    colCreated: 'Created at',
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
const page = ref(1)
const sortBy = ref<UsersSortKey>('id')
const sortDirection = ref<SortDirection>('asc')
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})
const selectedRoleLabel = computed(() => selectedRole.value || copy.value.allRoles)
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
  const colors: Record<string, string> = {
    admin: '#ef4444',
    staff: '#06b6d4',
    client: '#10b981',
    designer: '#8b5cf6'
  }

  return colors[role] || '#38bdf8'
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
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminUsersResponse>('/admin/users', {
      query: {
        page: page.value,
        per_page: pagination.per_page,
        role: selectedRole.value || undefined,
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

onMounted(() => {
  void fetchUsers()
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

</style>
