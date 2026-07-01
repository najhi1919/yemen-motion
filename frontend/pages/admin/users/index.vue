<template>
  <div class="ym-users-page">
    <header class="ym-users-header">
      <div class="min-w-0">
        <p class="ym-users-kicker">{{ copy.readonlyBadge }}</p>
        <h1 class="ym-users-title">{{ copy.title }}</h1>
        <p class="ym-users-copy">{{ copy.copy }}</p>
      </div>
    </header>

    <section class="ym-users-toolbar">
      <div class="ym-users-search">
        <input
          v-model="search"
          type="search"
          :placeholder="copy.searchPlaceholder"
          class="ym-users-input"
        />
      </div>
      <div class="ym-users-role">
        <select v-model="selectedRole" class="ym-users-select">
          <option value="">{{ copy.allRoles }}</option>
          <option v-for="role in availableRoles" :key="role" :value="role">{{ role }}</option>
        </select>
      </div>
    </section>

    <section class="ym-users-card">
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
              <th>{{ copy.colId }}</th>
              <th>{{ copy.colName }}</th>
              <th>{{ copy.colEmail }}</th>
              <th>{{ copy.colRoles }}</th>
              <th>{{ copy.colCreated }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id">
              <td class="ym-users-cell-id">{{ user.id }}</td>
              <td class="ym-users-cell-name">{{ user.name }}</td>
              <td class="ym-users-cell-email">{{ user.email }}</td>
              <td class="ym-users-cell-roles">
                <span v-if="!user.roles.length" class="ym-users-chip is-muted">—</span>
                <span
                  v-for="role in user.roles"
                  :key="role"
                  class="ym-users-chip"
                  :class="`is-${role}`"
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
import { reactive, ref, watch, onMounted } from 'vue'
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

const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonlyBadge: 'قراءة فقط',
    title: 'المستخدمون',
    copy: 'قائمة المستخدمين للعرض فقط — لا يمكن إنشاء أو تعديل أو حذف من هذه الصفحة.',
    searchPlaceholder: 'بحث بالاسم أو البريد...',
    allRoles: 'كل الأدوار',
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
      `الصفحة ${page} من ${last} — ${total} مستخدم`
  },
  en: {
    readonlyBadge: 'Read-only',
    title: 'Users',
    copy: 'Users list for viewing only — no create, edit, or delete actions from this page.',
    searchPlaceholder: 'Search by name or email...',
    allRoles: 'All roles',
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
      `Page ${page} of ${last} — ${total} users`
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const users = ref<AdminUser[]>([])
const availableRoles = ref<string[]>(['admin', 'staff', 'client', 'designer'])
const loading = ref(false)
const error = ref<string | null>(null)
const search = ref('')
const selectedRole = ref('')
const page = ref(1)
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

function formatCreatedAt(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return currentLocale.value === 'ar'
    ? date.toLocaleDateString('ar-EG')
    : date.toLocaleDateString('en-GB')
}

async function fetchUsers(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminUsersResponse>('/admin/users', {
      query: {
        page: page.value,
        per_page: pagination.per_page,
        search: search.value || undefined,
        role: selectedRole.value || undefined
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

watch([search, selectedRole], () => {
  page.value = 1
  void fetchUsers()
})

onMounted(() => {
  void fetchUsers()
})
</script>

<style scoped>
.ym-users-page {
  display: grid;
  gap: clamp(1.25rem, 2.4vw, 1.75rem);
}

.ym-users-header {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.ym-users-kicker {
  margin: 0;
  color: #f59e0b;
  font-size: 13px;
  font-weight: 950;
  letter-spacing: 0.02em;
}

.ym-users-title {
  margin: 0;
  color: var(--ym-text);
  font-size: clamp(1.7rem, 2.6vw, 2.2rem);
  font-weight: 950;
  line-height: 1.15;
}

.ym-users-copy {
  margin: 0;
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 700;
  line-height: 1.7;
  max-width: 60rem;
}

.ym-users-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.ym-users-search {
  flex: 1 1 18rem;
}

.ym-users-role {
  flex: 0 0 12rem;
}

.ym-users-input,
.ym-users-select {
  width: 100%;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 700;
  padding: 0.65rem 0.85rem;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.ym-users-input:focus,
.ym-users-select:focus {
  outline: none;
  border-color: color-mix(in srgb, #38bdf8 52%, transparent);
  box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.16);
}

.ym-users-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: clamp(1rem, 2vw, 1.35rem);
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
  border-radius: 999px;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 30%, transparent);
  border-top-color: #38bdf8;
  animation: ym-users-spin 0.8s linear infinite;
}

@keyframes ym-users-spin {
  to { transform: rotate(360deg); }
}

.ym-users-table-wrap {
  overflow-x: auto;
}

.ym-users-table {
  width: 100%;
  border-collapse: collapse;
  min-width: 760px;
}

.ym-users-table thead th {
  position: sticky;
  top: 0;
  text-align: start;
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 950;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 0.7rem 0.85rem;
  border-bottom: 1px solid var(--ym-soft-border);
  background: color-mix(in srgb, var(--ym-control-bg) 88%, transparent);
}

.ym-users-table tbody td {
  padding: 0.8rem 0.85rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 70%, transparent);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 750;
  vertical-align: middle;
}

.ym-users-table tbody tr:last-child td {
  border-bottom: none;
}

.ym-users-table tbody tr:hover td {
  background: color-mix(in srgb, var(--ym-control-bg) 60%, transparent);
}

.ym-users-cell-id {
  color: var(--ym-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 850;
}

.ym-users-cell-email {
  color: var(--ym-muted);
  direction: ltr;
  unicode-bidi: plaintext;
}

.ym-users-cell-created {
  color: var(--ym-muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.ym-users-cell-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.ym-users-chip {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.2rem 0.6rem;
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.02em;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  border: 1px solid color-mix(in srgb, #38bdf8 32%, transparent);
}

.ym-users-chip.is-muted {
  color: var(--ym-muted);
  background: transparent;
  border-color: transparent;
}

.ym-users-chip.is-admin {
  background: color-mix(in srgb, #ef4444 18%, transparent);
  color: #ef4444;
  border-color: color-mix(in srgb, #ef4444 32%, transparent);
}

.ym-users-chip.is-staff {
  background: color-mix(in srgb, #f59e0b 18%, transparent);
  color: #f59e0b;
  border-color: color-mix(in srgb, #f59e0b 32%, transparent);
}

.ym-users-chip.is-client {
  background: color-mix(in srgb, #10b981 18%, transparent);
  color: #10b981;
  border-color: color-mix(in srgb, #10b981 32%, transparent);
}

.ym-users-chip.is-designer {
  background: color-mix(in srgb, #a78bfa 18%, transparent);
  color: #a78bfa;
  border-color: color-mix(in srgb, #a78bfa 32%, transparent);
}

.ym-users-pagination {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
  justify-content: space-between;
  margin-top: 1rem;
  padding-top: 0.9rem;
  border-top: 1px solid var(--ym-soft-border);
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
  border-radius: 12px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 13.5px;
  font-weight: 900;
  padding: 0.4rem 0.85rem;
  cursor: pointer;
  transition: border-color 160ms ease, background 160ms ease, opacity 160ms ease;
}

.ym-users-page-btn:hover:not(:disabled) {
  border-color: color-mix(in srgb, #38bdf8 52%, transparent);
  background: color-mix(in srgb, #38bdf8 10%, transparent);
}

.ym-users-page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .ym-users-toolbar {
    flex-direction: column;
    align-items: stretch;
  }

  .ym-users-role {
    flex: 1 1 auto;
  }

  .ym-users-pagination {
    justify-content: center;
  }
}
</style>
