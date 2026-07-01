<template>
  <div class="ym-roles-page">
    <header class="ym-roles-header">
      <div class="min-w-0">
        <p class="ym-roles-kicker">{{ copy.readonlyBadge }}</p>
        <h1 class="ym-roles-title">{{ copy.title }}</h1>
        <p class="ym-roles-copy">{{ copy.copy }}</p>
      </div>
    </header>

    <section class="ym-roles-card">
      <div v-if="loading" class="ym-roles-state">
        <span class="ym-roles-state__spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </div>

      <div v-else-if="error" class="ym-roles-state is-error">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="!roles.length" class="ym-roles-state">
        <p>{{ copy.empty }}</p>
      </div>

      <div v-else class="ym-roles-table-wrap">
        <table class="ym-roles-table">
          <thead>
            <tr>
              <th>{{ copy.colId }}</th>
              <th>{{ copy.colName }}</th>
              <th>{{ copy.colGuardName }}</th>
              <th>{{ copy.colUsersCount }}</th>
              <th>{{ copy.colPermissionsCount }}</th>
              <th>{{ copy.colCreatedAt }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in roles" :key="role.id">
              <td class="ym-roles-cell-id">{{ role.id }}</td>
              <td class="ym-roles-cell-name">{{ role.name }}</td>
              <td class="ym-roles-cell-guard-name">{{ role.guard_name }}</td>
              <td class="ym-roles-cell-users-count">{{ role.users_count }}</td>
              <td class="ym-roles-cell-permissions-count">{{ role.permissions_count }}</td>
              <td class="ym-roles-cell-created">{{ formatCreatedAt(role.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useApiClient } from '~/composables/useApiClient'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'

type AdminRole = {
  id: number
  name: string
  guard_name: string
  users_count: number
  permissions_count: number
  created_at: string | null
}

type AdminRolesResponse = {
  success: boolean
  data: AdminRole[]
  message?: string
  errors?: Record<string, string[]> | null
}

const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    readonlyBadge: 'قراءة فقط',
    title: 'الأدوار',
    copy: 'قائمة الأدوار للعرض فقط — لا يمكن إنشاء أو تعديل أو حذف من هذه الصفحة.',
    loading: 'يتم تحميل الأدوار...',
    empty: 'لا توجد أدوار لعرضها.',
    colId: '#',
    colName: 'الاسم',
    colGuardName: 'Guard Name',
    colUsersCount: 'عدد المستخدمين',
    colPermissionsCount: 'عدد الصلاحيات',
    colCreatedAt: 'تاريخ الإنشاء',
  },
  en: {
    readonlyBadge: 'Read-only',
    title: 'Roles',
    copy: 'Roles list for viewing only — no create, edit, or delete actions from this page.',
    loading: 'Loading roles...',
    empty: 'No roles to display.',
    colId: '#',
    colName: 'Name',
    colGuardName: 'Guard Name',
    colUsersCount: 'Users Count',
    colPermissionsCount: 'Permissions Count',
    colCreatedAt: 'Created at',
  },
}

const copy = computed(() => copyMap[currentLocale.value])

const roles = ref<AdminRole[]>([])
const loading = ref(false)
const error = ref<string | null>(null)

function formatCreatedAt(value: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return currentLocale.value === 'ar'
    ? date.toLocaleDateString('ar-EG')
    : date.toLocaleDateString('en-GB')
}

async function fetchRoles(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminRolesResponse>('/admin/roles')
    roles.value = response.data
  } catch {
    roles.value = []
    error.value = currentLocale.value === 'ar'
      ? 'تعذر جلب الأدوار. تحقق من تسجيل الدخول وصلاحيات الأدمن.'
      : 'Could not load roles. Check admin authentication and permissions.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void fetchRoles()
})
</script>

<style scoped>
.ym-roles-page {
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
}

.ym-roles-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  border-bottom: 1px solid var(--ym-soft-border);
  padding-bottom: 1.5rem;
}

.ym-roles-kicker {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--ym-muted);
  margin-bottom: 0.25rem;
  text-transform: uppercase;
}

.ym-roles-title {
  font-size: 1.875rem;
  font-weight: 700;
  color: var(--ym-text);
  margin-bottom: 0.5rem;
}

.ym-roles-copy {
  font-size: 0.9375rem;
  color: var(--ym-muted);
  max-width: 600px;
}

.ym-roles-card {
  background-color: var(--ym-card-bg);
  border: 1px solid var(--ym-card-border);
  border-radius: 0.5rem;
  box-shadow: var(--ym-card-shadow);
  padding: 1.5rem;
}

.ym-roles-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 200px;
  color: var(--ym-muted);
  font-size: 1rem;
  gap: 0.75rem;
}

.ym-roles-state.is-error {
  color: var(--ym-danger);
}

.ym-roles-state__spinner {
  border: 4px solid var(--ym-soft-border);
  border-top: 4px solid var(--ym-primary);
  border-radius: 50%;
  width: 30px;
  height: 30px;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.ym-roles-table-wrap {
  overflow-x: auto;
}

.ym-roles-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9375rem;
}

.ym-roles-table th,
.ym-roles-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--ym-soft-border);
  text-align: start;
}

.ym-roles-table th {
  background-color: var(--ym-control-bg);
  color: var(--ym-text);
  font-weight: 600;
  white-space: nowrap;
}

.ym-roles-table td {
  color: var(--ym-muted);
}

.ym-roles-table tbody tr:last-child td {
  border-bottom: none;
}

.ym-roles-cell-id {
  font-weight: 500;
  color: var(--ym-text);
}

.ym-roles-cell-name {
  font-weight: 500;
  color: var(--ym-text);
}

.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created {
  font-family: monospace;
}

@media (max-width: 768px) {
  .ym-roles-page {
    padding: 1rem;
  }

  .ym-roles-header {
    flex-direction: column;
    align-items: flex-start;
    text-align: start;
  }

  .ym-roles-title {
    font-size: 1.5rem;
  }
}
</style>
