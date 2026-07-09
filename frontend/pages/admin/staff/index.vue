<template>
  <div class="ym-staff-page space-y-7">
    <section class="ym-staff-hero ym-admin-hero">
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
          <span>{{ copy.fixedRole }}</span>
          <strong>staff</strong>
          <small>{{ copy.pageScope }}: {{ pagination.current_page }} / {{ pagination.last_page }}</small>
        </div>
      </div>
    </section>

    <aside class="ym-readonly-notice" role="note">
      <span class="ym-readonly-notice__badge">{{ copy.readonlyBadge }}</span>
      <p>{{ copy.readonlyNotice }}</p>
    </aside>

    <aside v-if="successMessage" class="ym-staff-feedback is-success" role="status">
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

    <section class="ym-table-card">
      <div class="ym-table-card__head">
        <div>
          <h2>{{ copy.tableTitle }}</h2>
          <p>{{ copy.tableCopy }}</p>
        </div>
        <div class="ym-table-card__actions">
          <span>{{ copy.pageInfo(pagination.current_page, pagination.last_page, pagination.total) }}</span>
          <button
            v-if="canCreateStaff"
            type="button"
            class="ym-create-staff-button"
            @click="openCreateStaffModal"
          >
            {{ copy.createStaff }}
          </button>
        </div>
      </div>

      <div v-if="loading" class="ym-staff-state">
        <span class="ym-staff-state__spinner" aria-hidden="true" />
        <p>{{ copy.loading }}</p>
      </div>

      <div v-else-if="error" class="ym-staff-state is-error">
        <p>{{ error }}</p>
      </div>

      <div v-else-if="!staffUsers.length" class="ym-staff-state">
        <p>{{ copy.empty }}</p>
      </div>

      <div v-else class="ym-staff-table-wrap">
        <table class="ym-staff-table">
          <thead>
            <tr>
              <th class="ym-staff-th-id">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('id')">
                    {{ copy.colId }}
                    <span class="ym-sort-indicator" :class="sortBy === 'id' ? 'is-active' : ''">{{ sortIndicator('id') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-staff-th-name">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('name')">
                    {{ copy.colName }}
                    <span class="ym-sort-indicator" :class="sortBy === 'name' ? 'is-active' : ''">{{ sortIndicator('name') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-staff-th-email">
                <div class="ym-table-th-content">
                  <button type="button" class="ym-sort-button" @click="toggleSort('email')">
                    {{ copy.colEmail }}
                    <span class="ym-sort-indicator" :class="sortBy === 'email' ? 'is-active' : ''">{{ sortIndicator('email') }}</span>
                  </button>
                </div>
              </th>
              <th class="ym-staff-th-roles">
                <div class="ym-table-th-content">
                  <span>{{ copy.colRoles }}</span>
                </div>
              </th>
              <th class="ym-staff-th-created">
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
            <tr v-for="user in staffUsers" :key="user.id">
              <td class="ym-staff-cell-id">{{ user.id }}</td>
              <td class="ym-staff-cell-name">
                <span
                  class="ym-name-preview"
                  :title="user.name"
                  :dir="textDirection(user.name)"
                  v-text="truncateText(user.name, 15)"
                />
              </td>
              <td class="ym-staff-cell-email" dir="ltr">
                <span
                  class="ym-email-preview"
                  :title="user.email"
                  dir="ltr"
                  v-text="truncateText(user.email, 15)"
                />
              </td>
              <td class="ym-staff-cell-roles">
                <span v-if="!user.roles.length" class="ym-staff-chip is-muted">—</span>
                <span
                  v-for="role in user.roles"
                  :key="role"
                  class="ym-staff-chip"
                  :class="`is-${role}`"
                  :title="role"
                >{{ role }}</span>
              </td>
              <td class="ym-staff-cell-created">{{ formatCreatedAt(user.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="!loading && !error && staffUsers.length" class="ym-staff-pagination">
        <span class="ym-staff-pagination__info">
          {{ copy.pageInfo(pagination.current_page, pagination.last_page, pagination.total) }}
        </span>
        <div class="ym-staff-pagination__actions">
          <button
            type="button"
            class="ym-staff-page-btn"
            :disabled="pagination.current_page <= 1"
            @click="changePage(pagination.current_page - 1)"
          >{{ copy.prev }}</button>
          <span class="ym-staff-pagination__current">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
          <button
            type="button"
            class="ym-staff-page-btn"
            :disabled="pagination.current_page >= pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >{{ copy.next }}</button>
        </div>
      </footer>
    </section>

    <div
      v-if="createModalOpen"
      class="ym-staff-modal-backdrop"
      role="presentation"
      @click.self="closeCreateStaffModal"
    >
      <section
        class="ym-staff-modal"
        role="dialog"
        aria-modal="true"
        :aria-label="copy.createStaff"
      >
        <header class="ym-staff-modal__head">
          <div>
            <h2>{{ copy.createStaff }}</h2>
            <p>{{ copy.createStaffCopy }}</p>
          </div>
          <button
            type="button"
            class="ym-staff-modal__close"
            :aria-label="copy.cancel"
            :disabled="savingStaff"
            @click="closeCreateStaffModal"
          >
            ×
          </button>
        </header>

        <form class="ym-staff-form" @submit.prevent="submitCreateStaff">
          <div v-if="createError" class="ym-staff-feedback is-error" role="alert">
            <p>{{ createError }}</p>
          </div>

          <label class="ym-staff-field">
            <span>{{ copy.formName }}</span>
            <input v-model.trim="createForm.name" type="text" autocomplete="name" />
            <small v-if="fieldError('name')">{{ fieldError('name') }}</small>
          </label>

          <label class="ym-staff-field">
            <span>{{ copy.formEmail }}</span>
            <input v-model.trim="createForm.email" type="email" dir="ltr" autocomplete="email" />
            <small v-if="fieldError('email')">{{ fieldError('email') }}</small>
          </label>

          <label class="ym-staff-field">
            <span>{{ copy.formPassword }}</span>
            <input v-model="createForm.password" type="password" autocomplete="new-password" />
            <small v-if="fieldError('password')">{{ fieldError('password') }}</small>
          </label>

          <label class="ym-staff-field">
            <span>{{ copy.formPasswordConfirmation }}</span>
            <input v-model="createForm.password_confirmation" type="password" autocomplete="new-password" />
            <small v-if="fieldError('password_confirmation')">{{ fieldError('password_confirmation') }}</small>
          </label>

          <label class="ym-staff-field">
            <span>{{ copy.formRole }}</span>
            <select v-model="createForm.role">
              <option value="staff">staff</option>
              <option value="admin">admin</option>
            </select>
            <small v-if="fieldError('role')">{{ fieldError('role') }}</small>
          </label>

          <footer class="ym-staff-form__actions">
            <button
              type="button"
              class="ym-staff-form__secondary"
              :disabled="savingStaff"
              @click="closeCreateStaffModal"
            >
              {{ copy.cancel }}
            </button>
            <button type="submit" class="ym-staff-form__primary" :disabled="savingStaff">
              {{ savingStaff ? copy.saving : copy.saveStaff }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'

type AdminStaffUser = {
  id: number
  name: string
  email: string
  roles: string[]
  created_at: string | null
}

type PaginatedStaffUsers = {
  data: AdminStaffUser[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

type AdminStaffResponse = {
  success: boolean
  data: PaginatedStaffUsers
  message?: string
  errors?: Record<string, string[]> | null
}

type StoreStaffResponse = {
  success: boolean
  message?: string
  data: {
    user: AdminStaffUser & {
      role: 'staff' | 'admin'
    }
  }
  errors?: Record<string, string[]> | null
}

type StaffSortKey = 'id' | 'name' | 'email' | 'created_at'
type SortDirection = 'asc' | 'desc'
type StaffCreateRole = 'staff' | 'admin'

const { apiFetch } = useApiClient()
const auth = useAuthStore()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'إدارة محدودة',
    kicker: 'إدارة الموظفين',
    title: 'مركز فريق العمل',
    copy: 'عرض تشغيلي لأعضاء الفريق مرتبط بفلتر الدور الثابت staff مع إنشاء محدود للحسابات الداخلية.',
    readonlyNotice: 'إنشاء الموظفين متاح مؤقتًا للمدير الأعلى فقط، مع إبقاء التعديل والحذف مؤجلين لمرحلة إدارة الموظفين الكاملة.',
    fixedRole: 'الدور الثابت',
    pageScope: 'الصفحة',
    tableTitle: 'سجل الموظفين',
    tableCopy: 'جدول متابعة غني يعرض بيانات الفريق من endpoint المستخدمين الحالي مع role ثابت.',
    createStaff: 'إنشاء موظف جديد',
    createStaffCopy: 'أدخل بيانات الحساب واختر الدور الداخلي المسموح لهذه المرحلة.',
    formName: 'الاسم',
    formEmail: 'البريد الإلكتروني',
    formPassword: 'كلمة المرور',
    formPasswordConfirmation: 'تأكيد كلمة المرور',
    formRole: 'الدور',
    saveStaff: 'حفظ الموظف',
    saving: 'جار الحفظ...',
    cancel: 'إلغاء',
    createSuccess: 'تم إنشاء الموظف بنجاح.',
    createError: 'تعذر إنشاء الموظف. راجع الحقول وحاول مرة أخرى.',
    totalStaff: 'إجمالي الموظفين',
    currentPageStaff: 'في الصفحة الحالية',
    roleLabel: 'الدور',
    currentPage: 'الصفحة الحالية',
    liveData: 'من بيانات API',
    visibleRows: 'صفوف ظاهرة الآن',
    fixedRoleScope: 'نطاق ثابت',
    paginationScope: 'حالة التصفح',
    loading: 'يتم تحميل الموظفين...',
    empty: 'لا يوجد موظفون مطابقون.',
    colId: '#',
    colName: 'الاسم',
    colEmail: 'البريد الإلكتروني',
    colRoles: 'الأدوار',
    colCreated: 'تاريخ الإنشاء',
    prev: 'السابق',
    next: 'التالي',
    pageInfo: (page: number, last: number, total: number) =>
      `الصفحة ${page} من ${last} - ${total} موظف`
  },
  en: {
    brandChip: 'Yemen Motion',
    readonlyBadge: 'Limited management',
    kicker: 'Staff management',
    title: 'Staff Command Center',
    copy: 'An operational view of team members using the fixed staff role filter with limited internal account creation.',
    readonlyNotice: 'Staff creation is temporarily limited to the super admin, while edit and delete actions remain deferred.',
    fixedRole: 'Fixed role',
    pageScope: 'Page',
    tableTitle: 'Staff register',
    tableCopy: 'A rich monitoring table served by the current users endpoint with a fixed role.',
    createStaff: 'Create new staff',
    createStaffCopy: 'Enter account details and choose the internal role allowed in this step.',
    formName: 'Name',
    formEmail: 'Email',
    formPassword: 'Password',
    formPasswordConfirmation: 'Confirm password',
    formRole: 'Role',
    saveStaff: 'Save staff',
    saving: 'Saving...',
    cancel: 'Cancel',
    createSuccess: 'Staff member created successfully.',
    createError: 'Could not create staff. Check the fields and try again.',
    totalStaff: 'Total staff',
    currentPageStaff: 'Current page staff',
    roleLabel: 'Role',
    currentPage: 'Current page',
    liveData: 'From API data',
    visibleRows: 'Visible rows now',
    fixedRoleScope: 'Fixed scope',
    paginationScope: 'Pagination state',
    loading: 'Loading staff...',
    empty: 'No matching staff.',
    colId: '#',
    colName: 'Name',
    colEmail: 'Email',
    colRoles: 'Roles',
    colCreated: 'Created at',
    prev: 'Prev',
    next: 'Next',
    pageInfo: (page: number, last: number, total: number) =>
      `Page ${page} of ${last} - ${total} staff`
  }
}

const copy = computed(() => copyMap[currentLocale.value])
const canCreateStaff = computed(() => auth.role === 'super-admin')

const staffUsers = ref<AdminStaffUser[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const successMessage = ref<string | null>(null)
const createModalOpen = ref(false)
const savingStaff = ref(false)
const createError = ref<string | null>(null)
const createFieldErrors = ref<Record<string, string[]>>({})
const createForm = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: 'staff' as StaffCreateRole
})
const page = ref(1)
const sortBy = ref<StaffSortKey>('id')
const sortDirection = ref<SortDirection>('asc')
const pagination = reactive({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})
const summaryCards = computed(() => [
  { label: copy.value.totalStaff, value: pagination.total, subtitle: copy.value.liveData, color: '#06b6d4' },
  { label: copy.value.currentPageStaff, value: staffUsers.value.length, subtitle: copy.value.visibleRows, color: '#10b981' },
  { label: copy.value.roleLabel, value: 'staff', subtitle: copy.value.fixedRoleScope, color: '#8b5cf6' },
  { label: copy.value.currentPage, value: `${pagination.current_page} / ${pagination.last_page}`, subtitle: copy.value.paginationScope, color: '#f59e0b' }
])

function toggleSort(key: StaffSortKey): void {
  if (sortBy.value === key) {
    sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortBy.value = key
    sortDirection.value = 'asc'
  }

  page.value = 1
  void fetchStaff()
}

function sortIndicator(key: StaffSortKey): string {
  if (sortBy.value !== key) return '↕'
  return sortDirection.value === 'asc' ? '↑' : '↓'
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

function resetCreateForm(): void {
  createForm.name = ''
  createForm.email = ''
  createForm.password = ''
  createForm.password_confirmation = ''
  createForm.role = 'staff'
  createError.value = null
  createFieldErrors.value = {}
}

function openCreateStaffModal(): void {
  if (!canCreateStaff.value) return

  successMessage.value = null
  resetCreateForm()
  createModalOpen.value = true
}

function closeCreateStaffModal(): void {
  if (savingStaff.value) return
  createModalOpen.value = false
  createError.value = null
  createFieldErrors.value = {}
}

function fieldError(field: string): string {
  return createFieldErrors.value[field]?.[0] ?? ''
}

async function submitCreateStaff(): Promise<void> {
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
    await fetchStaff()
  } catch (caughtError: unknown) {
    const err = caughtError as any
    createFieldErrors.value = err?.data?.errors ?? err?.response?._data?.errors ?? {}
    createError.value = err?.data?.message || err?.response?._data?.message || copy.value.createError
  } finally {
    savingStaff.value = false
  }
}

async function fetchStaff(): Promise<void> {
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<AdminStaffResponse>('/admin/users', {
      query: {
        page: page.value,
        per_page: pagination.per_page,
        role: 'staff',
        sort_by: sortBy.value,
        sort_direction: sortDirection.value
      }
    })

    staffUsers.value = response.data.data
    pagination.current_page = response.data.current_page
    pagination.last_page = response.data.last_page
    pagination.per_page = response.data.per_page
    pagination.total = response.data.total
  } catch {
    staffUsers.value = []
    error.value = currentLocale.value === 'ar'
      ? 'تعذر جلب الموظفين. تحقق من تسجيل الدخول وصلاحيات الأدمن.'
      : 'Could not load staff. Check admin authentication and permissions.'
  } finally {
    loading.value = false
  }
}

function changePage(next: number): void {
  if (next < 1 || next > pagination.last_page) return
  page.value = next
  void fetchStaff()
}

onMounted(() => {
  void fetchStaff()
})
</script>

<style scoped>
.ym-staff-page {
  /* Local section color until admin settings can provide it. */
  --ym-section-accent: #06b6d4;
  position: relative;
}

.ym-staff-hero,
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

.ym-staff-hero {
  border-radius: 28px;
  padding: clamp(1.35rem, 3vw, 2.25rem);
}

.ym-admin-hero {
  border-color: rgba(255, 255, 255, 0.22);
  background:
    radial-gradient(circle at 18% 18%, rgba(255, 255, 255, 0.22), transparent 15rem),
    radial-gradient(circle at 85% 8%, rgba(6, 182, 212, 0.4), transparent 19rem),
    radial-gradient(circle at 95% 92%, rgba(190, 0, 1, 0.32), transparent 22rem),
    linear-gradient(135deg, rgba(14, 116, 144, 0.98), rgba(6, 182, 212, 0.9) 48%, rgba(190, 0, 1, 0.78));
  box-shadow:
    0 34px 80px rgba(6, 182, 212, 0.24),
    0 14px 32px rgba(2, 6, 23, 0.2),
    inset 0 1px 0 rgba(255, 255, 255, 0.32),
    inset 0 -1px 0 rgba(30, 41, 59, 0.16);
  transition: transform 200ms ease, box-shadow 200ms ease;
}

.ym-admin-hero:hover {
  transform: translateY(-2px);
  box-shadow:
    0 38px 88px rgba(6, 182, 212, 0.3),
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
  background: rgba(56, 189, 248, 0.24);
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

.ym-table-card__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.65rem;
}

.ym-create-staff-button,
.ym-staff-form__primary,
.ym-staff-form__secondary,
.ym-staff-modal__close {
  transition: border-color 160ms ease, background 160ms ease, color 160ms ease, opacity 160ms ease, transform 160ms ease;
}

.ym-create-staff-button,
.ym-staff-form__primary {
  border: 1px solid color-mix(in srgb, #06b6d4 48%, transparent);
  border-radius: 999px;
  background: linear-gradient(135deg, #0891b2, #06b6d4);
  color: #fff;
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 950;
  padding: 0.55rem 0.95rem;
  box-shadow: 0 16px 34px rgba(6, 182, 212, 0.2);
}

.ym-create-staff-button:hover,
.ym-staff-form__primary:hover:not(:disabled) {
  transform: translateY(-1px);
}

.ym-staff-feedback {
  border: 1px solid color-mix(in srgb, #06b6d4 36%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, #06b6d4 12%, transparent);
  color: var(--ym-text);
  padding: 0.75rem 0.9rem;
}

.ym-staff-feedback p {
  margin: 0;
  font-size: 14px;
  font-weight: 850;
  line-height: 1.7;
}

.ym-staff-feedback.is-success {
  border-color: color-mix(in srgb, #10b981 36%, transparent);
  background: color-mix(in srgb, #10b981 12%, transparent);
}

.ym-staff-feedback.is-error {
  border-color: color-mix(in srgb, #ef4444 42%, transparent);
  background: color-mix(in srgb, #ef4444 10%, transparent);
  color: #ef4444;
}

.ym-staff-modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: grid;
  place-items: center;
  background: rgba(2, 6, 23, 0.62);
  padding: 1rem;
  backdrop-filter: blur(10px);
}

.ym-staff-modal {
  width: min(100%, 560px);
  max-height: calc(100dvh - 2rem);
  overflow-y: auto;
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background:
    radial-gradient(circle at 90% 0%, rgba(6, 182, 212, 0.18), transparent 14rem),
    var(--ym-card-bg);
  box-shadow:
    0 28px 80px rgba(2, 6, 23, 0.42),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  padding: clamp(1rem, 2vw, 1.25rem);
}

.ym-staff-modal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-staff-modal__head h2 {
  margin: 0;
  color: var(--ym-text);
  font-size: 20px;
  font-weight: 950;
}

.ym-staff-modal__head p {
  margin: 0.35rem 0 0;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 800;
  line-height: 1.7;
}

.ym-staff-modal__close {
  display: grid;
  flex: 0 0 auto;
  height: 2.3rem;
  width: 2.3rem;
  place-items: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  cursor: pointer;
  font-size: 1.4rem;
  font-weight: 900;
  line-height: 1;
}

.ym-staff-form {
  display: grid;
  gap: 0.85rem;
}

.ym-staff-field {
  display: grid;
  gap: 0.4rem;
}

.ym-staff-field span {
  color: var(--ym-text);
  font-size: 13.5px;
  font-weight: 900;
}

.ym-staff-field input,
.ym-staff-field select {
  width: 100%;
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 800;
  outline: none;
  padding: 0.72rem 0.85rem;
}

.ym-staff-field input:focus,
.ym-staff-field select:focus {
  border-color: color-mix(in srgb, #06b6d4 56%, transparent);
  box-shadow: 0 0 0 3px color-mix(in srgb, #06b6d4 14%, transparent);
}

.ym-staff-field small {
  color: #ef4444;
  font-size: 12.5px;
  font-weight: 850;
  line-height: 1.5;
}

.ym-staff-form__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.65rem;
  margin-top: 0.25rem;
}

.ym-staff-form__secondary {
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  cursor: pointer;
  font-size: 13.5px;
  font-weight: 950;
  padding: 0.55rem 0.95rem;
}

.ym-staff-form__primary:disabled,
.ym-staff-form__secondary:disabled,
.ym-staff-modal__close:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.ym-staff-state {
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

.ym-staff-state.is-error {
  color: #ef4444;
}

.ym-staff-state__spinner {
  height: 26px;
  width: 26px;
  border: 3px solid color-mix(in srgb, var(--ym-muted) 30%, transparent);
  border-top-color: #38bdf8;
  border-radius: 999px;
  animation: ym-staff-spin 0.8s linear infinite;
}

@keyframes ym-staff-spin {
  to { transform: rotate(360deg); }
}

.ym-staff-table-wrap {
  position: relative;
  z-index: 1;
  overflow-x: auto;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 74%, transparent);
  border-radius: 22px;
  background: color-mix(in srgb, var(--ym-card-bg) 82%, transparent);
}

.ym-staff-table {
  width: max-content;
  min-width: max(100%, 962px);
  border-collapse: collapse;
  table-layout: fixed;
}

.ym-staff-table thead th {
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

.ym-staff-table th,
.ym-staff-table td {
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


.ym-staff-table tbody td {
  padding: 0.85rem 0.95rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 62%, transparent);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 750;
  vertical-align: middle;
}

.ym-staff-table tbody tr:last-child td {
  border-bottom: none;
}

.ym-staff-table tbody tr:hover td {
  background: color-mix(in srgb, #38bdf8 9%, transparent);
}

.ym-staff-cell-id,
.ym-staff-cell-created {
  color: var(--ym-muted);
  font-variant-numeric: tabular-nums;
  font-weight: 850;
  white-space: nowrap;
}

.ym-staff-cell-name {
  font-weight: 900;
  min-width: 0;
  overflow: hidden;
}

.ym-staff-cell-email {
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

.ym-staff-cell-roles {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.ym-staff-chip {
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

.ym-staff-chip.is-muted {
  border-color: transparent;
  background: transparent;
  color: var(--ym-muted);
}

.ym-staff-chip.is-admin {
  border-color: color-mix(in srgb, #ef4444 32%, transparent);
  background: color-mix(in srgb, #ef4444 18%, transparent);
  color: #ef4444;
}

.ym-staff-chip.is-staff {
  border-color: color-mix(in srgb, #f59e0b 32%, transparent);
  background: color-mix(in srgb, #f59e0b 18%, transparent);
  color: #f59e0b;
}

.ym-staff-chip.is-client {
  border-color: color-mix(in srgb, #10b981 32%, transparent);
  background: color-mix(in srgb, #10b981 18%, transparent);
  color: #10b981;
}

.ym-staff-chip.is-designer {
  border-color: color-mix(in srgb, #a78bfa 32%, transparent);
  background: color-mix(in srgb, #a78bfa 18%, transparent);
  color: #a78bfa;
}

.ym-staff-pagination {
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

.ym-staff-pagination__info {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 800;
}

.ym-staff-pagination__actions {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

.ym-staff-pagination__current {
  color: var(--ym-text);
  font-size: 13.5px;
  font-weight: 900;
  font-variant-numeric: tabular-nums;
}

.ym-staff-page-btn {
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

.ym-staff-page-btn:hover:not(:disabled) {
  border-color: color-mix(in srgb, #38bdf8 52%, transparent);
  background: color-mix(in srgb, #38bdf8 10%, transparent);
  transform: translateY(-1px);
}

.ym-staff-page-btn:disabled {
  cursor: not-allowed;
  opacity: 0.4;
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
  .ym-readonly-notice {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-staff-pagination {
    justify-content: center;
  }
}
/* YM-ADMIN-UI final fix: clean semantic staff table */
.ym-staff-table-wrap {
  overflow-x: auto;
}

.ym-staff-table {
  width: 100% !important;
  min-width: 920px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  direction: rtl;
}

/* من اليمين لليسار:
   # | الاسم | البريد الإلكتروني | الأدوار | تاريخ الإنشاء
*/
.ym-staff-table th:nth-child(1),
.ym-staff-table td:nth-child(1) {
  width: 6% !important;
}

.ym-staff-table th:nth-child(2),
.ym-staff-table td:nth-child(2) {
  width: 22% !important;
}

.ym-staff-table th:nth-child(3),
.ym-staff-table td:nth-child(3) {
  width: 26% !important;
}

.ym-staff-table th:nth-child(4),
.ym-staff-table td:nth-child(4) {
  width: 20% !important;
}

.ym-staff-table th:nth-child(5),
.ym-staff-table td:nth-child(5) {
  width: 26% !important;
}

.ym-staff-table th,
.ym-staff-table td,
.ym-staff-cell-id,
.ym-staff-cell-name,
.ym-staff-cell-email,
.ym-staff-cell-roles,
.ym-staff-cell-created {
  display: table-cell !important;
  box-sizing: border-box;
  overflow: hidden;
  vertical-align: middle;
}

.ym-staff-table .ym-table-th-content,
.ym-staff-table .ym-sort-button {
  width: 100%;
}

/* # */
.ym-staff-th-id,
.ym-staff-cell-id {
  direction: ltr;
  text-align: center !important;
  white-space: nowrap;
}

.ym-staff-th-id .ym-table-th-content,
.ym-staff-th-id .ym-sort-button {
  justify-content: center;
}

/* الاسم */
.ym-staff-th-name,
.ym-staff-cell-name {
  direction: rtl;
  text-align: right !important;
  white-space: nowrap;
}

.ym-staff-th-name .ym-table-th-content,
.ym-staff-th-name .ym-sort-button {
  justify-content: flex-start;
  direction: rtl;
  text-align: right;
}

.ym-staff-cell-name .ym-name-preview {
  display: inline-block !important;
  max-width: 16ch;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

.ym-staff-cell-name .ym-name-preview[dir="ltr"] {
  direction: ltr !important;
  text-align: left !important;
}

.ym-staff-cell-name .ym-name-preview[dir="rtl"] {
  direction: rtl !important;
  text-align: right !important;
}

/* البريد */
.ym-staff-th-email,
.ym-staff-cell-email {
  direction: rtl !important;
  text-align: right !important;
  white-space: nowrap;
  unicode-bidi: isolate;
}

.ym-staff-th-email .ym-table-th-content,
.ym-staff-th-email .ym-sort-button {
  justify-content: flex-start;
  direction: rtl;
  text-align: right;
}

.ym-staff-cell-email .ym-email-preview {
  display: inline-block !important;
  max-width: 16ch;
  direction: ltr !important;
  text-align: left !important;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate !important;
}

/* الأدوار */
.ym-staff-th-roles,
.ym-staff-cell-roles {
  direction: rtl;
  text-align: center !important;
  white-space: nowrap;
}

.ym-staff-th-roles .ym-table-th-content {
  justify-content: center;
}

.ym-staff-cell-roles .ym-staff-chip {
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
.ym-staff-th-created,
.ym-staff-cell-created {
  direction: ltr;
  text-align: center !important;
  white-space: nowrap;
}

.ym-staff-th-created .ym-table-th-content,
.ym-staff-th-created .ym-sort-button {
  justify-content: center;
  direction: ltr;
  text-align: center;
}

/* YM-ADMIN-UI final fix: staff email column users parity */
.ym-staff-table .ym-staff-th-email,
.ym-staff-table .ym-staff-cell-email {
  direction: rtl !important;
  text-align: right !important;
  white-space: nowrap !important;
  unicode-bidi: isolate !important;
}

.ym-staff-table .ym-staff-th-email .ym-table-th-content,
.ym-staff-table .ym-staff-th-email .ym-sort-button {
  display: flex !important;
  width: 100% !important;
  justify-content: flex-start !important;
  align-items: center !important;
  direction: rtl !important;
  text-align: right !important;
}

.ym-staff-table .ym-staff-cell-email .ym-email-preview {
  display: inline-block !important;
  max-width: 16ch !important;
  direction: ltr !important;
  text-align: left !important;
  overflow: hidden !important;
  text-overflow: clip !important;
  white-space: nowrap !important;
  vertical-align: middle !important;
  unicode-bidi: isolate !important;
}

</style>
