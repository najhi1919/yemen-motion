<template>
  <div class="ym-roles-page space-y-7">
    <section class="ym-roles-hero ym-admin-hero">
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
          <span>{{ copy.primaryGuard }}</span>
          <strong>{{ primaryGuard }}</strong>
          <small>{{ copy.rolesCount }}: {{ roles.length }}</small>
        </div>
      </div>
    </section>

    <aside class="ym-readonly-notice" role="note">
      <span class="ym-readonly-notice__badge">{{ copy.readonlyBadge }}</span>
      <p>{{ copy.readonlyNotice }}</p>
    </aside>

    <section class="ym-create-role-card" aria-labelledby="ym-create-role-title">
      <div class="ym-create-role-card__copy">
        <h2 id="ym-create-role-title">{{ copy.createTitle }}</h2>
        <p>{{ copy.createCopy }}</p>
      </div>

      <form class="ym-create-role-form" @submit.prevent="createRole">
        <label class="ym-create-role-field">
          <span>{{ copy.createNameLabel }}</span>
          <input
            v-model="createRoleName"
            type="text"
            dir="ltr"
            autocomplete="off"
            :placeholder="copy.createNamePlaceholder"
            :disabled="creatingRole"
            @input="clearCreateFeedback"
          />
        </label>

        <button type="submit" class="ym-create-role-button" :disabled="!canCreateRole">
          {{ creatingRole ? copy.creating : copy.createAction }}
        </button>
      </form>

      <p
        v-if="createRoleFeedback"
        class="ym-create-role-feedback"
        :class="createRoleFeedbackType === 'success' ? 'is-success' : 'is-error'"
      >
        {{ createRoleFeedback }}
      </p>

      <small class="ym-create-role-hint">{{ copy.createHint }}</small>
    </section>

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

    <section class="ym-table-card">
      <div class="ym-table-card__head">
        <div>
          <h2>{{ copy.tableTitle }}</h2>
          <p>{{ copy.tableCopy }}</p>
        </div>
        <span>{{ copy.readonlyBadge }}</span>
      </div>

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
              <th class="ym-roles-th-id">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('id')">
                    {{ copy.colId }}
                    <span class="ym-sort-indicator" :class="sortKey === 'id' ? 'is-active' : ''">{{ sortIndicator('id') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-roles-th-name">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('name')">
                    {{ copy.colName }}
                    <span class="ym-sort-indicator" :class="sortKey === 'name' ? 'is-active' : ''">{{ sortIndicator('name') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-roles-th-guard-name">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('guard_name')">
                    {{ copy.colGuardName }}
                    <span class="ym-sort-indicator" :class="sortKey === 'guard_name' ? 'is-active' : ''">{{ sortIndicator('guard_name') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-roles-th-users-count">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('users_count')">
                    {{ copy.colUsersCount }}
                    <span class="ym-sort-indicator" :class="sortKey === 'users_count' ? 'is-active' : ''">{{ sortIndicator('users_count') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-roles-th-permissions-count">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('permissions_count')">
                    {{ copy.colPermissionsCount }}
                    <span class="ym-sort-indicator" :class="sortKey === 'permissions_count' ? 'is-active' : ''">{{ sortIndicator('permissions_count') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-roles-th-created">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('created_at')">
                    {{ copy.colCreatedAt }}
                    <span class="ym-sort-indicator" :class="sortKey === 'created_at' ? 'is-active' : ''">{{ sortIndicator('created_at') }}</span>
                  </button>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in sortedRoles" :key="role.id">
              <td class="ym-roles-cell-id">{{ role.id }}</td>
              <td class="ym-roles-cell-name">
                <span
                  class="ym-role-name"
                  :title="role.name"
                  v-text="truncateText(role.name, 15)"
                />
              </td>
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
import { computed, onMounted, ref } from 'vue'
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

type StoreRoleResponse = {
  success: boolean
  data: AdminRole
  message?: string
  errors?: Record<string, string[]> | null
}

type RolesSortKey = 'id' | 'name' | 'guard_name' | 'users_count' | 'permissions_count' | 'created_at'
type SortDirection = 'asc' | 'desc'

const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'إدارة محدودة',
    kicker: 'الأدوار والصلاحيات',
    title: 'مركز الأدوار',
    copy: 'عرض رقابي للأدوار وعدد المستخدمين والصلاحيات المرتبطة بها مع إمكانية إنشاء أدوار مخصصة.',
    readonlyNotice: 'هذه المرحلة تسمح بإنشاء role مخصص فقط. التعديل والحذف وربط الصلاحيات مؤجل لخطوات لاحقة.',
    primaryGuard: 'Guard الأساسي',
    rolesCount: 'عدد الأدوار',
    tableTitle: 'سجل الأدوار',
    tableCopy: 'جدول غني يعرض الأدوار فقط من endpoint الحالي دون pagination أو أدوات تعديل.',
    totalRoles: 'عدد الأدوار',
    totalUsers: 'إجمالي المستخدمين عبر الأدوار',
    totalPermissions: 'إجمالي الصلاحيات المرتبطة',
    guardLabel: 'Guard الأساسي',
    liveData: 'من بيانات API',
    usersScope: 'مجموع users_count',
    permissionsScope: 'مجموع permissions_count',
    guardScope: 'أول guard متاح',
    loading: 'يتم تحميل الأدوار...',
    empty: 'لا توجد أدوار لعرضها.',
    colId: '#',
    colName: 'الاسم',
    colGuardName: 'Guard Name',
    colUsersCount: 'عدد المستخدمين',
    colPermissionsCount: 'عدد الصلاحيات',
    colCreatedAt: 'تاريخ الإنشاء',
    createTitle: 'إنشاء دور مخصص',
    createCopy: 'أضف role جديدًا لاستخدامه لاحقًا في ربط الصلاحيات أو تعيين المستخدمين.',
    createNameLabel: 'اسم الدور',
    createNamePlaceholder: 'مثال: support-agent',
    createAction: 'إنشاء الدور',
    creating: 'جارٍ الإنشاء...',
    createSuccess: 'تم إنشاء الدور بنجاح.',
    createNameRequired: 'اكتب اسم الدور أولًا.',
    createHint: 'الأدوار المحمية مثل super-admin و admin لا تُنشأ من هذه الواجهة.'
  },
  en: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'Limited management',
    kicker: 'Roles and permissions',
    title: 'Roles Command Center',
    copy: 'A governance view of roles, users, and linked permissions with custom role creation.',
    readonlyNotice: 'This phase only allows creating custom roles. Edit, delete, and permission binding are deferred.',
    primaryGuard: 'Primary guard',
    rolesCount: 'Roles count',
    tableTitle: 'Roles register',
    tableCopy: 'A rich table listing roles from the current endpoint without pagination or editing tools.',
    totalRoles: 'Roles count',
    totalUsers: 'Total users across roles',
    totalPermissions: 'Linked permissions total',
    guardLabel: 'Primary guard',
    liveData: 'From API data',
    usersScope: 'Sum of users_count',
    permissionsScope: 'Sum of permissions_count',
    guardScope: 'First available guard',
    loading: 'Loading roles...',
    empty: 'No roles to display.',
    colId: '#',
    colName: 'Name',
    colGuardName: 'Guard Name',
    colUsersCount: 'Users Count',
    colPermissionsCount: 'Permissions Count',
    colCreatedAt: 'Created at',
    createTitle: 'Create custom role',
    createCopy: 'Add a new role to use later for permission binding or user assignment.',
    createNameLabel: 'Role name',
    createNamePlaceholder: 'Example: support-agent',
    createAction: 'Create role',
    creating: 'Creating...',
    createSuccess: 'Role created successfully.',
    createNameRequired: 'Enter a role name first.',
    createHint: 'Protected roles like super-admin and admin are not created from this UI.'
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const roles = ref<AdminRole[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const createRoleName = ref('')
const creatingRole = ref(false)
const createRoleFeedback = ref<string | null>(null)
const createRoleFeedbackType = ref<'success' | 'error' | null>(null)
const sortKey = ref<RolesSortKey>('id')
const sortDirection = ref<SortDirection>('asc')
const totalRoleUsers = computed(() => roles.value.reduce((total, role) => total + Number(role.users_count || 0), 0))
const totalRolePermissions = computed(() => roles.value.reduce((total, role) => total + Number(role.permissions_count || 0), 0))
const primaryGuard = computed(() => roles.value.find(role => role.guard_name)?.guard_name || 'web')
const normalizedCreateRoleName = computed(() => createRoleName.value.trim())
const canCreateRole = computed(() => normalizedCreateRoleName.value.length > 0 && !creatingRole.value)
const summaryCards = computed(() => [
  { label: copy.value.totalRoles, value: roles.value.length, subtitle: copy.value.liveData, color: '#8b5cf6' },
  { label: copy.value.totalUsers, value: totalRoleUsers.value, subtitle: copy.value.usersScope, color: '#10b981' },
  { label: copy.value.totalPermissions, value: totalRolePermissions.value, subtitle: copy.value.permissionsScope, color: '#38bdf8' },
  { label: copy.value.guardLabel, value: primaryGuard.value, subtitle: copy.value.guardScope, color: '#f59e0b' }
])
const sortedRoles = computed(() => {
  const direction = sortDirection.value === 'asc' ? 1 : -1

  return [...roles.value].sort((first, second) => compareRoles(first, second, sortKey.value) * direction)
})

function compareRoles(first: AdminRole, second: AdminRole, key: RolesSortKey): number {
  if (key === 'id' || key === 'users_count' || key === 'permissions_count') {
    return Number(first[key] || 0) - Number(second[key] || 0)
  }

  if (key === 'created_at') return dateValue(first.created_at) - dateValue(second.created_at)

  return String(first[key] || '').localeCompare(String(second[key] || ''), 'en', {
    numeric: true,
    sensitivity: 'base'
  })
}

function dateValue(value: string | null): number {
  if (!value) return 0
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? 0 : date.getTime()
}

function toggleSort(key: RolesSortKey): void {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    return
  }

  sortKey.value = key
  sortDirection.value = 'asc'
}

function sortIndicator(key: RolesSortKey): string {
  if (sortKey.value !== key) return '↕'
  return sortDirection.value === 'asc' ? '↑' : '↓'
}



function truncateText(value: string | null | undefined, limit = 15): string {
  const chars = Array.from(String(value ?? '').trim())

  if (chars.length <= limit) {
    return chars.join('')
  }

  return `${chars.slice(0, limit).join('')}…`
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

function clearCreateFeedback(): void {
  createRoleFeedback.value = null
  createRoleFeedbackType.value = null
}

function firstValidationError(errors?: Record<string, string[]> | null): string | null {
  if (!errors) return null

  for (const messages of Object.values(errors)) {
    const firstMessage = messages.find(Boolean)
    if (firstMessage) return firstMessage
  }

  return null
}

async function createRole(): Promise<void> {
  if (!normalizedCreateRoleName.value) {
    createRoleFeedback.value = copy.value.createNameRequired
    createRoleFeedbackType.value = 'error'
    return
  }

  creatingRole.value = true
  createRoleFeedback.value = null
  createRoleFeedbackType.value = null

  try {
    await apiFetch<StoreRoleResponse>('/admin/roles', {
      method: 'POST',
      body: {
        name: normalizedCreateRoleName.value
      }
    })

    createRoleName.value = ''
    createRoleFeedback.value = copy.value.createSuccess
    createRoleFeedbackType.value = 'success'

    await fetchRoles()
  } catch (requestError: unknown) {
    const err = requestError as any
    createRoleFeedback.value = firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || (currentLocale.value === 'ar'
        ? 'فشل إنشاء الدور.'
        : 'Could not create role.')
    createRoleFeedbackType.value = 'error'
  } finally {
    creatingRole.value = false
  }
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
  /* Local section color until admin settings can provide it. */
  --ym-section-accent: #8b5cf6;
  position: relative;
}

.ym-roles-hero,
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

.ym-roles-hero {
  border-radius: 28px;
  padding: clamp(1.35rem, 3vw, 2.25rem);
}

.ym-admin-hero {
  border-color: rgba(255, 255, 255, 0.22);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 15rem),
    radial-gradient(circle at 85% 8%, rgba(139, 92, 246, 0.42), transparent 19rem),
    radial-gradient(circle at 95% 92%, rgba(190, 0, 1, 0.32), transparent 22rem),
    linear-gradient(135deg, rgba(76, 29, 149, 0.98), rgba(139, 92, 246, 0.92) 48%, rgba(190, 0, 1, 0.78));
  box-shadow:
    0 34px 80px rgba(139, 92, 246, 0.26),
    0 14px 32px rgba(2, 6, 23, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16);
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-admin-hero:hover {
  transform: translateY(-2px);
  box-shadow:
    0 38px 88px rgba(139, 92, 246, 0.32),
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

.ym-create-role-card {
  position: relative;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, #10b981 38%, var(--ym-card-border));
  border-radius: 24px;
  background:
    radial-gradient(circle at 8% 0%, color-mix(in srgb, #10b981 16%, transparent), transparent 16rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 92%, rgba(16, 185, 129, 0.08)), var(--ym-card-bg));
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.12);
  padding: clamp(1rem, 2vw, 1.25rem);
}

.ym-create-role-card__copy h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 18px;
  font-weight: 950;
}

.ym-create-role-card__copy p,
.ym-create-role-hint {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
  line-height: 1.7;
}

.ym-create-role-card__copy p {
  margin: 0.3rem 0 0;
}

.ym-create-role-form {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 0.8rem;
  align-items: end;
  margin-top: 1rem;
}

.ym-create-role-field {
  display: grid;
  gap: 0.45rem;
}

.ym-create-role-field span {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
}

.ym-create-role-field input {
  width: 100%;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 850;
  outline: none;
  padding: 0.75rem 0.9rem;
}

.ym-create-role-field input:focus {
  border-color: color-mix(in srgb, #10b981 62%, var(--ym-soft-border));
  box-shadow: 0 0 0 4px color-mix(in srgb, #10b981 14%, transparent);
}

.ym-create-role-button {
  border: 1px solid color-mix(in srgb, #10b981 54%, transparent);
  border-radius: 16px;
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  font-size: 14px;
  font-weight: 950;
  min-height: 44px;
  padding: 0.72rem 1rem;
  transition: transform 160ms ease, opacity 160ms ease;
}

.ym-create-role-button:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-create-role-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.ym-create-role-feedback {
  border-radius: 14px;
  font-size: 13px;
  font-weight: 850;
  margin: 0.8rem 0 0;
  padding: 0.65rem 0.75rem;
}

.ym-create-role-feedback.is-success {
  background: color-mix(in srgb, #10b981 16%, transparent);
  color: #10b981;
}

.ym-create-role-feedback.is-error {
  background: color-mix(in srgb, #ef4444 14%, transparent);
  color: #ef4444;
}

.ym-create-role-hint {
  display: block;
  margin-top: 0.75rem;
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

.ym-table-card {
  border-radius: 28px;
  background:
    radial-gradient(circle at 10% 0%, rgba(236, 72, 153, 0.1), transparent 18rem),
    radial-gradient(circle at 92% 12%, rgba(56, 189, 248, 0.1), transparent 20rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg));
  padding: clamp(1rem, 2vw, 1.35rem);
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

.ym-table-card__head h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 18px;
  font-weight: 950;
}

.ym-table-card__head p {
  margin: 0.25rem 0 0;
}

.ym-table-card__head span {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-control-bg) 80%, transparent);
  padding: 0.4rem 0.75rem;
  color: var(--ym-text);
}

.ym-roles-state {
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

.ym-roles-state.is-error {
  color: #ef4444;
}

.ym-roles-state__spinner {
  height: 26px;
  width: 26px;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 30%, transparent);
  border-top-color: #38bdf8;
  border-radius: 999px;
  animation: ym-roles-spin 0.8s linear infinite;
}

@keyframes ym-roles-spin {
  to { transform: rotate(360deg); }
}

.ym-roles-table-wrap {
  position: relative;
  z-index: 1;
  overflow-x: auto;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 74%, transparent);
  border-radius: 22px;
  background: color-mix(in srgb, var(--ym-card-bg) 82%, transparent);
}

.ym-roles-table {
  width: max-content;
  min-width: max(100%, 1040px);
  border-collapse: collapse;
  table-layout: fixed;
}

.ym-roles-table thead th {
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

.ym-roles-table th,
.ym-roles-table td {
  box-sizing: border-box;
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


.ym-roles-table tbody td {
  padding: 0.85rem 0.95rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 62%, transparent);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 750;
  vertical-align: middle;
}

.ym-roles-table tbody tr:last-child td {
  border-bottom: none;
}

.ym-roles-table tbody tr:hover td {
  background: color-mix(in srgb, #38bdf8 9%, transparent);
}

.ym-roles-cell-id,
.ym-roles-cell-created,
.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count {
  color: var(--ym-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 850;
  white-space: nowrap;
}

.ym-role-name {
  display: inline-flex;
  align-items: center;
  border: 1px solid color-mix(in srgb, #38bdf8 32%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 16%, transparent);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  padding: 0.28rem 0.7rem;
}

@media (min-width: 768px) {
  .ym-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
  .ym-readonly-notice,
  .ym-create-role-form {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-create-role-form {
    display: flex;
  }

  .ym-create-role-field,
  .ym-create-role-button {
    width: 100%;
  }
}
/* YM-ADMIN-UI final fix: clean semantic roles table */
.ym-roles-table-wrap {
  overflow-x: auto;
}

.ym-roles-table {
  width: 100% !important;
  min-width: 1040px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  direction: rtl;
}

/* من اليمين لليسار:
   # | الاسم | Guard Name | عدد المستخدمين | عدد الصلاحيات | تاريخ الإنشاء
*/
.ym-roles-table th:nth-child(1),
.ym-roles-table td:nth-child(1) {
  width: 6% !important;
}

.ym-roles-table th:nth-child(2),
.ym-roles-table td:nth-child(2) {
  width: 20% !important;
}

.ym-roles-table th:nth-child(3),
.ym-roles-table td:nth-child(3) {
  width: 17% !important;
}

.ym-roles-table th:nth-child(4),
.ym-roles-table td:nth-child(4) {
  width: 15% !important;
}

.ym-roles-table th:nth-child(5),
.ym-roles-table td:nth-child(5) {
  width: 20% !important;
}

.ym-roles-table th:nth-child(6),
.ym-roles-table td:nth-child(6) {
  width: 22% !important;
}

.ym-roles-table th,
.ym-roles-table td,
.ym-roles-cell-id,
.ym-roles-cell-name,
.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created {
  display: table-cell !important;
  box-sizing: border-box;
  overflow: hidden;
  vertical-align: middle;
}

.ym-roles-table .ym-table-th-content,
.ym-roles-table .ym-sort-button {
  display: flex !important;
  width: 100% !important;
  min-width: 0;
  align-items: center !important;
  justify-content: center !important;
  gap: 0.35rem;
  text-align: center !important;
}

/* كل أعمدة roles قصيرة/رقمية، لذلك التوسيط هو الأنسب هنا */
.ym-roles-th-id,
.ym-roles-th-name,
.ym-roles-th-guard-name,
.ym-roles-th-users-count,
.ym-roles-th-permissions-count,
.ym-roles-th-created,
.ym-roles-cell-id,
.ym-roles-cell-name,
.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created {
  text-align: center !important;
  white-space: nowrap !important;
}

.ym-roles-cell-id,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created {
  direction: ltr !important;
  font-variant-numeric: tabular-nums;
}

.ym-roles-cell-guard-name {
  direction: ltr !important;
  unicode-bidi: isolate !important;
}

.ym-roles-cell-name {
  direction: rtl !important;
}

.ym-role-name {
  display: inline-flex !important;
  max-width: 16ch;
  min-width: 72px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

</style>
