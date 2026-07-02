<template>
  <div class="ym-permissions-page space-y-7">
    <section class="ym-permissions-hero ym-admin-hero">
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
          <p class="ym-hero-copy">{{ copy.description }}</p>
        </div>

        <div class="ym-hero-summary">
          <span>{{ copy.totalPermissions }}</span>
          <strong>{{ permissions.length }}</strong>
          <small>{{ copy.groupsCount }}: {{ groupedPermissions.length }}</small>
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

      <div class="ym-permission-filter">
        <span>{{ copy.typeFilter }}</span>
        <div class="ym-permission-filter__pills">
          <button
            v-for="option in typeFilterOptions"
            :key="option.value"
            type="button"
            class="ym-permission-pill"
            :class="permissionType === option.value ? 'is-active' : ''"
            @click="permissionType = option.value"
          >
            {{ option.label }}
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
        <span>{{ copy.visibleCount }}: {{ filteredPermissions.length }}</span>
      </div>

      <div v-if="loading" class="ym-permissions-state">
        <span class="ym-permissions-state__spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </div>

      <div v-else-if="error" class="ym-permissions-state is-error">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="!filteredPermissions.length" class="ym-permissions-state">
        <p>{{ copy.empty }}</p>
      </div>

      <div v-else class="ym-permissions-table-wrap">
        <table class="ym-permissions-table">
          <thead>
            <tr>
              <th class="ym-permissions-th-name">
                <button type="button" class="ym-sort-button" @click="toggleSort('name')">
                  {{ copy.colName }}
                  <span class="ym-sort-indicator" :class="sortKey === 'name' ? 'is-active' : ''">{{ sortIndicator('name') }}</span>
                </button>
              </th>
              <th class="ym-permissions-th-group">
                <button type="button" class="ym-sort-button" @click="toggleSort('group')">
                  {{ copy.colGroup }}
                  <span class="ym-sort-indicator" :class="sortKey === 'group' ? 'is-active' : ''">{{ sortIndicator('group') }}</span>
                </button>
              </th>
              <th class="ym-permissions-th-label">
                <span>{{ copy.colLabel }}</span>
              </th>
              <th class="ym-permissions-th-type">
                <button type="button" class="ym-sort-button" @click="toggleSort('is_system')">
                  {{ copy.colType }}
                  <span class="ym-sort-indicator" :class="sortKey === 'is_system' ? 'is-active' : ''">{{ sortIndicator('is_system') }}</span>
                </button>
              </th>
              <th class="ym-permissions-th-guard">
                <span>{{ copy.colGuard }}</span>
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="permission in sortedPermissions" :key="permission.id">
              <td class="ym-permissions-cell-name" dir="ltr">
                <span class="ym-permission-code" :title="permission.name">{{ permission.name }}</span>
              </td>
              <td class="ym-permissions-cell-group" dir="ltr">{{ permission.group || '—' }}</td>
              <td class="ym-permissions-cell-label">{{ permission.label_ar || permission.name }}</td>
              <td class="ym-permissions-cell-type">
                <span class="ym-permission-type" :class="permission.is_system ? 'is-system' : 'is-custom'">
                  {{ permission.is_system ? copy.systemPermission : copy.customPermission }}
                </span>
              </td>
              <td class="ym-permissions-cell-guard">{{ permission.guard_name }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="ym-groups-card">
      <div class="ym-groups-card__head">
        <h2>{{ copy.groupsTitle }}</h2>
        <p>{{ copy.groupsCopy }}</p>
      </div>

      <div v-if="!groupedPermissions.length" class="ym-permissions-state">
        <p>{{ copy.emptyGroups }}</p>
      </div>

      <div v-else class="ym-groups-grid">
        <article v-for="group in groupedPermissions" :key="group.name" class="ym-group-card">
          <span dir="ltr">{{ group.name }}</span>
          <strong>{{ group.count }}</strong>
          <small>{{ copy.permissionUnit }}</small>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApiClient } from '~/composables/useApiClient'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'
type PermissionType = 'all' | 'system' | 'custom'
type PermissionSortKey = 'name' | 'group' | 'is_system'
type SortDirection = 'asc' | 'desc'

type AdminPermission = {
  id: number
  name: string
  guard_name: string
  group: string
  label_ar: string
  is_system: boolean
  created_at: string | null
}

type AdminPermissionsResponse = {
  success: boolean
  data: AdminPermission[]
  message?: string
  errors?: Record<string, string[]> | null
}

const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'قراءة فقط',
    kicker: 'إدارة الوصول',
    title: 'مركز الصلاحيات',
    description: 'عرض منظم لكل صلاحيات النظام والصلاحيات المخصصة تمهيدًا لربطها بالأدوار من الواجهة الرسومية.',
    readonlyNotice: 'هذه المرحلة تعرض الصلاحيات فقط. لا توجد عمليات إنشاء أو تعديل أو حذف من الواجهة في هذه الخطوة.',
    totalPermissions: 'إجمالي الصلاحيات',
    groupsCount: 'المجموعات',
    totalSystem: 'صلاحيات النظام',
    totalCustom: 'صلاحيات مخصصة',
    totalGroups: 'مجموعات الصلاحيات',
    guardLabel: 'Guard الأساسي',
    liveData: 'من بيانات API',
    systemScope: 'مرتبطة بال registry',
    customScope: 'منشأة من الإدارة',
    groupScope: 'تصنيف منطقي',
    guardScope: 'أول guard متاح',
    filterTitle: 'فلترة الصلاحيات',
    filterCopy: 'اعرض كل الصلاحيات أو افصل بين system و custom permissions.',
    typeFilter: 'النوع',
    allPermissions: 'الكل',
    systemPermissions: 'System',
    customPermissions: 'Custom',
    tableTitle: 'سجل الصلاحيات',
    tableCopy: 'جدول قراءة فقط يعرض اسم الصلاحية، المجموعة، التسمية العربية، النوع، و guard.',
    visibleCount: 'المعروض',
    loading: 'يتم تحميل الصلاحيات...',
    empty: 'لا توجد صلاحيات مطابقة.',
    colName: 'الصلاحية',
    colGroup: 'المجموعة',
    colLabel: 'التسمية',
    colType: 'النوع',
    colGuard: 'Guard',
    systemPermission: 'System',
    customPermission: 'Custom',
    groupsTitle: 'مجموعات الصلاحيات',
    groupsCopy: 'تجميع سريع حسب group لتسهيل بناء شاشة matrix لاحقًا.',
    emptyGroups: 'لا توجد مجموعات لعرضها.',
    permissionUnit: 'صلاحية'
  },
  en: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'Read-only',
    kicker: 'Access management',
    title: 'Permissions Center',
    description: 'A structured view of system and custom permissions before wiring graphical role-permission assignment.',
    readonlyNotice: 'This phase only displays permissions. Create, edit, and delete actions are intentionally deferred.',
    totalPermissions: 'Total permissions',
    groupsCount: 'Groups',
    totalSystem: 'System permissions',
    totalCustom: 'Custom permissions',
    totalGroups: 'Permission groups',
    guardLabel: 'Primary guard',
    liveData: 'From API data',
    systemScope: 'Registry-backed',
    customScope: 'Managed permissions',
    groupScope: 'Logical grouping',
    guardScope: 'First available guard',
    filterTitle: 'Permission filters',
    filterCopy: 'View all permissions or separate system and custom permissions.',
    typeFilter: 'Type',
    allPermissions: 'All',
    systemPermissions: 'System',
    customPermissions: 'Custom',
    tableTitle: 'Permissions register',
    tableCopy: 'A read-only table listing permission name, group, Arabic label, type, and guard.',
    visibleCount: 'Visible',
    loading: 'Loading permissions...',
    empty: 'No matching permissions.',
    colName: 'Permission',
    colGroup: 'Group',
    colLabel: 'Label',
    colType: 'Type',
    colGuard: 'Guard',
    systemPermission: 'System',
    customPermission: 'Custom',
    groupsTitle: 'Permission groups',
    groupsCopy: 'Quick grouping by group to prepare for the future matrix screen.',
    emptyGroups: 'No groups to display.',
    permissionUnit: 'permissions'
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const permissions = ref<AdminPermission[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const permissionType = ref<PermissionType>('all')
const sortKey = ref<PermissionSortKey>('group')
const sortDirection = ref<SortDirection>('asc')

const typeFilterOptions = computed(() => [
  { value: 'all' as const, label: copy.value.allPermissions },
  { value: 'system' as const, label: copy.value.systemPermissions },
  { value: 'custom' as const, label: copy.value.customPermissions }
])

const systemPermissionsCount = computed(() => permissions.value.filter(permission => permission.is_system).length)
const customPermissionsCount = computed(() => permissions.value.filter(permission => !permission.is_system).length)
const primaryGuard = computed(() => permissions.value.find(permission => permission.guard_name)?.guard_name || 'web')

const groupedPermissions = computed(() => {
  const groups = new Map<string, number>()

  for (const permission of permissions.value) {
    const group = permission.group || 'ungrouped'
    groups.set(group, (groups.get(group) || 0) + 1)
  }

  return [...groups.entries()]
    .map(([name, count]) => ({ name, count }))
    .sort((first, second) => first.name.localeCompare(second.name, 'en', { numeric: true, sensitivity: 'base' }))
})

const summaryCards = computed(() => [
  { label: copy.value.totalPermissions, value: permissions.value.length, subtitle: copy.value.liveData, color: '#8b5cf6' },
  { label: copy.value.totalSystem, value: systemPermissionsCount.value, subtitle: copy.value.systemScope, color: '#38bdf8' },
  { label: copy.value.totalCustom, value: customPermissionsCount.value, subtitle: copy.value.customScope, color: '#10b981' },
  { label: copy.value.guardLabel, value: primaryGuard.value, subtitle: copy.value.guardScope, color: '#f59e0b' }
])

const filteredPermissions = computed(() => {
  if (permissionType.value === 'system') {
    return permissions.value.filter(permission => permission.is_system)
  }

  if (permissionType.value === 'custom') {
    return permissions.value.filter(permission => !permission.is_system)
  }

  return permissions.value
})

const sortedPermissions = computed(() => {
  const direction = sortDirection.value === 'asc' ? 1 : -1

  return [...filteredPermissions.value].sort((first, second) => {
    if (sortKey.value === 'is_system') {
      return (Number(first.is_system) - Number(second.is_system)) * direction
    }

    return String(first[sortKey.value] || '').localeCompare(String(second[sortKey.value] || ''), 'en', {
      numeric: true,
      sensitivity: 'base'
    }) * direction
  })
})

function toggleSort(key: PermissionSortKey): void {
  if (sortKey.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    return
  }

  sortKey.value = key
  sortDirection.value = 'asc'
}

function sortIndicator(key: PermissionSortKey): string {
  if (sortKey.value !== key) return '↕'
  return sortDirection.value === 'asc' ? '↑' : '↓'
}

async function fetchPermissions(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminPermissionsResponse>('/admin/permissions')
    permissions.value = response.data || []
  } catch (requestError: unknown) {
    const err = requestError as any
    error.value = err?.data?.message || err?.message || 'فشل تحميل الصلاحيات.'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void fetchPermissions()
})
</script>

<style scoped>
.ym-permissions-page {
  color: var(--ym-text);
}

.ym-permissions-hero,
.ym-table-card,
.ym-filter-card,
.ym-groups-card {
  position: relative;
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background:
    radial-gradient(circle at 10% 10%, color-mix(in srgb, #8b5cf6 13%, transparent), transparent 20rem),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.ym-permissions-hero {
  padding: clamp(1.25rem, 3vw, 2rem);
}

.ym-hero-content {
  position: relative;
  z-index: 1;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.5rem;
}

.ym-hero-copy-block {
  min-width: 0;
}

.ym-hero-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin-bottom: 1rem;
}

.ym-hero-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 950;
  padding: 0.42rem 0.7rem;
}

.ym-hero-chip-dot {
  width: 0.48rem;
  height: 0.48rem;
  border-radius: 999px;
  background: #8b5cf6;
  box-shadow: 0 0 0 4px color-mix(in srgb, #8b5cf6 14%, transparent);
}

.ym-hero-chip-dot--live {
  background: #10b981;
  box-shadow: 0 0 0 4px color-mix(in srgb, #10b981 14%, transparent);
}

.ym-hero-kicker {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 950;
  margin: 0 0 0.3rem;
}

.ym-hero-title {
  color: var(--ym-text);
  font-size: clamp(2rem, 4.5vw, 3.6rem);
  font-weight: 950;
  line-height: 1.08;
  margin: 0;
}

.ym-hero-copy {
  color: var(--ym-muted);
  font-size: 16px;
  font-weight: 820;
  line-height: 1.8;
  margin: 0.8rem 0 0;
  max-width: 56rem;
}

.ym-hero-summary {
  display: grid;
  min-width: min(100%, 220px);
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-hero-summary span,
.ym-hero-summary small {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 850;
}

.ym-hero-summary strong {
  color: var(--ym-text);
  font-size: 2rem;
  font-weight: 950;
  line-height: 1.15;
}

.ym-readonly-notice {
  display: flex;
  align-items: center;
  gap: 0.85rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  padding: 1rem 1.15rem;
}

.ym-readonly-notice__badge {
  flex: 0 0 auto;
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 18%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 950;
  padding: 0.38rem 0.7rem;
}

.ym-readonly-notice p {
  font-size: 14px;
  font-weight: 820;
  line-height: 1.7;
  margin: 0;
}

.ym-summary-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.ym-summary-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 24px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--card-accent) 15%, transparent), transparent 46%),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow);
  padding: 1.1rem;
}

.ym-summary-card span {
  color: var(--ym-muted);
  display: block;
  font-size: 13px;
  font-weight: 900;
}

.ym-summary-card strong {
  color: var(--ym-text);
  display: block;
  font-size: clamp(1.55rem, 3vw, 2.1rem);
  font-weight: 950;
  line-height: 1.1;
  margin-top: 0.45rem;
}

.ym-summary-card small {
  color: var(--ym-muted);
  display: block;
  font-size: 12px;
  font-weight: 800;
  margin-top: 0.35rem;
}

.ym-filter-card,
.ym-table-card,
.ym-groups-card {
  padding: clamp(1rem, 2.4vw, 1.45rem);
}

.ym-filter-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1.2rem;
}

.ym-filter-card h2,
.ym-table-card__head h2,
.ym-groups-card__head h2 {
  color: var(--ym-text);
  font-size: 1.25rem;
  font-weight: 950;
  margin: 0;
}

.ym-filter-card p,
.ym-table-card__head p,
.ym-groups-card__head p {
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
  margin: 0.3rem 0 0;
}

.ym-permission-filter {
  display: grid;
  gap: 0.55rem;
}

.ym-permission-filter > span {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 900;
}

.ym-permission-filter__pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.55rem;
}

.ym-permission-pill {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 950;
  padding: 0.48rem 0.82rem;
  transition: transform 160ms ease, border-color 160ms ease, background 160ms ease, color 160ms ease;
}

.ym-permission-pill:hover,
.ym-permission-pill.is-active {
  border-color: color-mix(in srgb, #8b5cf6 45%, var(--ym-soft-border));
  background: color-mix(in srgb, #8b5cf6 16%, var(--ym-control-bg));
  color: var(--ym-text);
  transform: translateY(-1px);
}

.ym-table-card__head,
.ym-groups-card__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-table-card__head > span {
  flex: 0 0 auto;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 950;
  padding: 0.45rem 0.75rem;
}

.ym-permissions-state {
  display: grid;
  min-height: 10rem;
  place-items: center;
  color: var(--ym-muted);
  font-weight: 850;
  text-align: center;
}

.ym-permissions-state.is-error {
  color: #ef4444;
}

.ym-permissions-state__spinner {
  width: 2rem;
  height: 2rem;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 24%, transparent);
  border-top-color: #8b5cf6;
  border-radius: 999px;
  animation: ym-spin 800ms linear infinite;
}

.ym-permissions-table-wrap {
  overflow-x: auto;
}

.ym-permissions-table {
  width: 100%;
  min-width: 920px;
  border-collapse: separate;
  border-spacing: 0;
}

.ym-permissions-table th,
.ym-permissions-table td {
  border-bottom: 1px solid var(--ym-soft-border);
  color: var(--ym-text);
  font-size: 14px;
  padding: 0.9rem 0.85rem;
  text-align: start;
  vertical-align: middle;
}

.ym-permissions-table th {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 950;
  white-space: nowrap;
}

.ym-sort-button {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  color: inherit;
  font: inherit;
  border: 0;
  background: transparent;
  cursor: pointer;
  padding: 0;
}

.ym-sort-indicator {
  color: color-mix(in srgb, var(--ym-muted) 65%, transparent);
  font-size: 12px;
}

.ym-sort-indicator.is-active {
  color: #8b5cf6;
}

.ym-permission-code {
  display: inline-block;
  max-width: 22rem;
  overflow: hidden;
  color: #38bdf8;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 13px;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-permissions-cell-group {
  color: var(--ym-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 13px;
  font-weight: 820;
}

.ym-permissions-cell-label {
  color: var(--ym-text);
  font-weight: 850;
}

.ym-permission-type {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 5.5rem;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 950;
  padding: 0.4rem 0.65rem;
}

.ym-permission-type.is-system {
  background: color-mix(in srgb, #38bdf8 16%, transparent);
  color: #38bdf8;
}

.ym-permission-type.is-custom {
  background: color-mix(in srgb, #10b981 16%, transparent);
  color: #10b981;
}

.ym-groups-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.ym-group-card {
  border: 1px solid var(--ym-soft-border);
  border-radius: 22px;
  background: var(--ym-control-bg);
  padding: 1rem;
}

.ym-group-card span {
  display: block;
  overflow: hidden;
  color: #38bdf8;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 13px;
  font-weight: 850;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-group-card strong {
  display: block;
  color: var(--ym-text);
  font-size: 1.75rem;
  font-weight: 950;
  margin-top: 0.55rem;
}

.ym-group-card small {
  color: var(--ym-muted);
  font-size: 12px;
  font-weight: 800;
}

@keyframes ym-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 1100px) {
  .ym-summary-grid,
  .ym-groups-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-hero-content,
  .ym-filter-card {
    align-items: stretch;
    flex-direction: column;
  }
}

@media (max-width: 640px) {
  .ym-summary-grid,
  .ym-groups-grid {
    grid-template-columns: 1fr;
  }

  .ym-readonly-notice,
  .ym-table-card__head,
  .ym-groups-card__head {
    align-items: stretch;
    flex-direction: column;
  }
}
</style>
