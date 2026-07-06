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
              {{ copy.managementBadge }}
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

    <aside class="ym-roles-notice" role="note">
      <span class="ym-roles-notice__badge">{{ copy.managementBadge }}</span>
      <p>{{ copy.managementNotice }}</p>
    </aside>

    <Transition name="ym-role-modal-fade">
      <div
        v-if="roleModalMode"
        class="ym-role-modal-backdrop"
        role="presentation"
        @click.self="closeRoleModal"
      >
        <section
          class="ym-role-modal"
          :class="roleModalMode === 'permissions' ? 'is-permissions' : ''"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="roleModalTitleId"
        >
          <button
            type="button"
            class="ym-role-modal__close"
            :aria-label="copy.closeModal"
            @click="closeRoleModal"
          >
            ×
          </button>

          <div class="ym-role-modal__head">
            <span>{{ roleModalEyebrow }}</span>
            <h2 :id="roleModalTitleId">{{ roleModalTitle }}</h2>
            <p>{{ roleModalCopy }}</p>
          </div>

          <p
            v-if="roleModalFeedback"
            class="ym-role-modal-feedback"
            :class="roleModalFeedbackType === 'success' ? 'is-success' : 'is-error'"
          >
            {{ roleModalFeedback }}
          </p>

          <form
            v-if="roleModalMode === 'create'"
            class="ym-role-modal-form"
            @submit.prevent="createRole"
          >
            <label class="ym-create-role-field">
              <span>{{ copy.createNameLabel }}</span>
              <input
                v-model="createRoleName"
                type="text"
                dir="ltr"
                autocomplete="off"
                :placeholder="copy.createNamePlaceholder"
                :disabled="creatingRole"
                @input="clearRoleModalFeedback"
              />
            </label>

            <div class="ym-role-modal-actions">
              <button type="button" class="ym-role-modal-button" @click="closeRoleModal">
                {{ copy.cancelAction }}
              </button>
              <button type="submit" class="ym-role-modal-button is-primary" :disabled="!canCreateRole">
                {{ creatingRole ? copy.creating : copy.createAction }}
              </button>
            </div>
          </form>

          <form
            v-else-if="roleModalMode === 'edit' && selectedRole"
            class="ym-role-modal-form"
            @submit.prevent="updateSelectedRole"
          >
            <div class="ym-role-modal-current">
              <span>{{ copy.currentRoleLabel }}</span>
              <strong>{{ selectedRole.name }}</strong>
            </div>

            <label class="ym-create-role-field">
              <span>{{ copy.createNameLabel }}</span>
              <input
                v-model="editingRoleName"
                type="text"
                dir="ltr"
                autocomplete="off"
                :placeholder="copy.createNamePlaceholder"
                :disabled="savingRoleId === selectedRole.id"
                @input="clearRoleModalFeedback"
              />
            </label>

            <div class="ym-role-modal-actions">
              <button type="button" class="ym-role-modal-button" @click="closeRoleModal">
                {{ copy.cancelAction }}
              </button>
              <button
                type="submit"
                class="ym-role-modal-button is-primary"
                :disabled="savingRoleId === selectedRole.id"
              >
                {{ savingRoleId === selectedRole.id ? copy.savingAction : copy.saveAction }}
              </button>
            </div>
          </form>

          <div v-else-if="roleModalMode === 'delete' && selectedRole" class="ym-role-modal-form">
            <div class="ym-role-delete-summary">
              <span>{{ copy.currentRoleLabel }}</span>
              <strong>{{ selectedRole.name }}</strong>
              <small>{{ copy.usersCount }}: {{ selectedRole.users_count }}</small>
            </div>

            <p
              v-if="!canDeleteRole(selectedRole)"
              class="ym-role-delete-warning"
            >
              {{ deleteRoleBlockingMessage(selectedRole) }}
            </p>

            <p v-else class="ym-role-delete-danger">
              {{ copy.deleteRoleConfirm }}
            </p>

            <div class="ym-role-modal-actions">
              <button type="button" class="ym-role-modal-button" @click="closeRoleModal">
                {{ copy.cancelAction }}
              </button>
              <button
                type="button"
                class="ym-role-modal-button is-danger"
                :disabled="!canDeleteRole(selectedRole) || deletingRoleId === selectedRole.id"
                @click="deleteSelectedRole"
              >
                {{ deletingRoleId === selectedRole.id ? copy.deletingAction : copy.confirmDeleteAction }}
              </button>
            </div>
          </div>

          <div v-else-if="roleModalMode === 'permissions' && selectedRole" class="ym-role-modal-form">
            <div class="ym-role-permissions-summary">
              <div>
                <span>{{ copy.currentRoleLabel }}</span>
                <strong>{{ permissionsModalRoleName }}</strong>
              </div>
              <div>
                <span>{{ copy.currentPermissionsLabel }}</span>
                <strong>{{ selectedPermissionNames.length }}</strong>
              </div>
            </div>

            <div v-if="loadingPermissionsRoleId === selectedRole.id" class="ym-role-permissions-state">
              <span class="ym-roles-state__spinner" aria-hidden="true" />
              <p>{{ copy.loadingPermissions }}</p>
            </div>

            <div v-else-if="!groupedAvailablePermissions.length" class="ym-role-permissions-state">
              <p>{{ copy.emptyPermissions }}</p>
            </div>

            <div v-else class="ym-permissions-groups" :dir="currentLocale === 'ar' ? 'rtl' : 'ltr'">
              <section
                v-for="group in groupedAvailablePermissions"
                :key="group.name"
                class="ym-permissions-group"
              >
                <div class="ym-permissions-group__head">
                  <strong dir="ltr">{{ group.name }}</strong>
                  <span>{{ group.selectedCount }} / {{ group.permissions.length }}</span>
                </div>

                <label
                  v-for="permission in group.permissions"
                  :key="permission.name"
                  class="ym-permission-checkbox"
                >
                  <input
                    type="checkbox"
                    :checked="isPermissionSelected(permission.name)"
                    :disabled="savingPermissionsRoleId === selectedRole.id"
                    @change="togglePermission(permission.name)"
                  />
                  <span>
                    <strong dir="ltr">{{ permission.name }}</strong>
                    <small>{{ permission.label_ar || permission.name }}</small>
                  </span>
                </label>
              </section>
            </div>

            <div class="ym-role-modal-actions">
              <button type="button" class="ym-role-modal-button" @click="closeRoleModal">
                {{ copy.cancelAction }}
              </button>
              <button
                type="button"
                class="ym-role-modal-button is-primary"
                :disabled="!canSaveRolePermissions"
                @click="saveSelectedRolePermissions"
              >
                {{ savingPermissionsRoleId === selectedRole.id ? copy.savingAction : copy.saveAction }}
              </button>
            </div>
          </div>
        </section>
      </div>
    </Transition>

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
          <button type="button" class="ym-create-role-button" @click="openCreateRoleModal">
            {{ copy.openCreateAction }}
          </button>
        </div>
      </div>

      <p
        v-if="createRoleFeedback"
        class="ym-create-role-feedback"
        :class="createRoleFeedbackType === 'success' ? 'is-success' : 'is-error'"
      >
        {{ createRoleFeedback }}
      </p>

      <p
        v-if="roleActionFeedback"
        class="ym-role-action-feedback"
        :class="roleActionFeedbackType === 'success' ? 'is-success' : 'is-error'"
      >
        {{ roleActionFeedback }}
      </p>

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
              <th class="ym-roles-th-actions">
                <div class="ym-table-th-content">
                  <span>{{ copy.colActions }}</span>
                </div>
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="role in sortedRoles" :key="role.id">
              <td class="ym-roles-cell-id">{{ role.id }}</td>
              <td class="ym-roles-cell-name">
                <span class="ym-role-name-wrap">
                  <span
                    v-if="isRoleProtected(role)"
                    class="ym-role-protected-dot"
                    :aria-label="copy.protectedRoleTooltip"
                    :data-tooltip="copy.protectedRoleTooltip"
                    tabindex="0"
                  />
                  <span
                    class="ym-role-name"
                    :class="isRoleProtected(role) ? 'is-protected' : ''"
                    v-text="truncateText(role.name, 15)"
                  />
                </span>
              </td>
              <td class="ym-roles-cell-guard-name">{{ role.guard_name }}</td>
              <td class="ym-roles-cell-users-count">{{ role.users_count }}</td>
              <td class="ym-roles-cell-permissions-count">{{ role.permissions_count }}</td>
              <td class="ym-roles-cell-created">{{ formatCreatedAt(role.created_at) }}</td>
              <td class="ym-roles-cell-actions">
                <div v-if="!isRoleProtected(role)" class="ym-role-actions">
                  <button
                    type="button"
                    class="ym-role-action-button is-permissions"
                    :aria-label="copy.permissionsActionTooltip"
                    :data-tooltip="copy.permissionsActionTooltip"
                    @click="openRolePermissionsModal(role)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M12 3.6 19 6.7v5.1c0 4.1-2.8 7.9-7 8.9-4.2-1-7-4.8-7-8.9V6.7l7-3.1Z" />
                      <path d="M9.4 12.1 11.2 14l3.8-4" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    class="ym-role-action-button is-edit"
                    :aria-label="copy.editAction"
                    :data-tooltip="copy.editAction"
                    @click="openEditRoleModal(role)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M4.8 16.8 4 20l3.2-.8L17.7 8.7l-2.4-2.4L4.8 16.8Z" />
                      <path d="m14.5 7.1 2.4 2.4" />
                      <path d="M13.8 20H20" />
                    </svg>
                  </button>

                  <button
                    type="button"
                    class="ym-role-action-button is-danger"
                    :class="canDeleteRole(role) ? '' : 'is-soft-locked'"
                    :aria-label="copy.deleteAction"
                    :data-tooltip="copy.deleteAction"
                    @click="openDeleteRoleModal(role)"
                  >
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                      <path d="M5 7h14" />
                      <path d="M9 7V5.6C9 4.7 9.7 4 10.6 4h2.8c.9 0 1.6.7 1.6 1.6V7" />
                      <path d="M8 10v8" />
                      <path d="M12 10v8" />
                      <path d="M16 10v8" />
                      <path d="M7 7l.7 13h8.6L17 7" />
                    </svg>
                  </button>
                </div>
              </td>
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
  permissions?: string[]
  is_protected?: boolean
  created_at: string | null
}

type AdminPermission = {
  id: number
  name: string
  guard_name: string
  group: string
  label_ar: string
  is_system: boolean
  created_at: string | null
}

type AdminRolesResponse = {
  success: boolean
  data: AdminRole[]
  message?: string
  errors?: Record<string, string[]> | null
}

type AdminRoleResponse = {
  success: boolean
  data: AdminRole
  message?: string
  errors?: Record<string, string[]> | null
}

type AdminPermissionsResponse = {
  success: boolean
  data: AdminPermission[]
  message?: string
  errors?: Record<string, string[]> | null
}

type StoreRoleResponse = {
  success: boolean
  data: AdminRole
  message?: string
  errors?: Record<string, string[]> | null
}

type UpdateRoleResponse = {
  success: boolean
  data: AdminRole
  message?: string
  errors?: Record<string, string[]> | null
}

type DeleteRoleResponse = {
  success: boolean
  message?: string
  errors?: Record<string, string[]> | null
}

type SyncRolePermissionsResponse = {
  success: boolean
  data: AdminRole
  message?: string
  errors?: Record<string, string[]> | null
}

type RoleModalMode = 'create' | 'edit' | 'delete' | 'permissions' | null

type RolesSortKey = 'id' | 'name' | 'guard_name' | 'users_count' | 'permissions_count' | 'created_at'
type SortDirection = 'asc' | 'desc'

const { apiFetch } = useApiClient()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copyMap = {
  ar: {
    brandChip: 'Yemen Motion',
    managementBadge: 'إدارة نشطة',
    kicker: 'الأدوار والصلاحيات',
    title: 'مركز الأدوار',
    copy: 'إدارة الأدوار المخصصة، التعديل والحذف عند السماح، وربط الصلاحيات من نفس الصفحة.',
    managementNotice: 'يمكنك إنشاء دور، تعديل دور مخصص، حذف دور قابل للحذف، وإدارة صلاحيات الأدوار غير المحمية.',
    primaryGuard: 'Guard الأساسي',
    rolesCount: 'عدد الأدوار',
    tableTitle: 'سجل الأدوار',
    tableCopy: 'جدول إدارة يعرض الأدوار، المستخدمين، الصلاحيات، والإجراءات المتاحة لكل دور.',
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
    createNameLabel: 'اسم الدور',
    createNamePlaceholder: 'مثال: support-agent',
    createAction: 'إنشاء الدور',
    creating: 'جارٍ الإنشاء...',
    createSuccess: 'تم إنشاء الدور بنجاح.',
    createNameRequired: 'اكتب اسم الدور أولًا.',
    openCreateAction: 'إنشاء',
    closeModal: 'إغلاق النافذة',
    colActions: 'الإجراءات',
    editAction: 'تعديل',
    deleteAction: 'حذف',
    saveAction: 'حفظ',
    savingAction: 'جارٍ الحفظ...',
    deletingAction: 'جارٍ الحذف...',
    cancelAction: 'إلغاء',
    confirmDeleteAction: 'تأكيد الحذف',
    protectedRoleTooltip: 'هذا الدور محمي',
    assignedRoleDeleteTitle: 'لا يمكن حذف دور مرتبط بمستخدمين.',
    deleteRoleTitle: 'حذف الدور',
    updateRoleSuccess: 'تم تعديل الدور بنجاح.',
    deleteRoleSuccess: 'تم حذف الدور بنجاح.',
    permissionsRoleSuccess: 'تم تحديث صلاحيات الدور بنجاح.',
    editRoleNameRequired: 'اكتب اسم الدور قبل الحفظ.',
    updateRoleFailed: 'فشل تعديل الدور.',
    deleteRoleFailed: 'فشل حذف الدور.',
    permissionsRoleFailed: 'فشل تحديث صلاحيات الدور.',
    protectedRoleBlocked: 'لا يمكن تعديل أو حذف دور محمي.',
    protectedRolePermissionsBlocked: 'لا يمكن إدارة صلاحيات دور محمي.',
    permissionsActionTooltip: 'الصلاحيات والارتباطات',
    deleteRoleConfirm: 'هل تريد حذف هذا الدور؟ لا يمكن التراجع عن هذه العملية.',
    currentRoleLabel: 'الدور الحالي',
    currentPermissionsLabel: 'الصلاحيات الحالية',
    usersCount: 'عدد المستخدمين',
    createModalEyebrow: 'عملية إنشاء',
    editModalEyebrow: 'عملية تعديل',
    deleteModalEyebrow: 'عملية حذف',
    permissionsModalEyebrow: 'إسناد الصلاحيات',
    createModalTitle: 'إنشاء دور مخصص',
    editModalTitle: 'تعديل اسم الدور',
    deleteModalTitle: 'حذف الدور',
    permissionsModalTitle: 'إدارة صلاحيات الدور',
    createModalCopy: 'اكتب اسم role جديد وسيتم إضافته إلى جدول الأدوار مباشرة بعد نجاح العملية.',
    editModalCopy: 'عدّل اسم الدور المخصص من هذه النافذة دون تغيير مكانك داخل الجدول.',
    deleteModalCopy: 'راجع حالة الدور قبل الحذف. الأدوار المرتبطة بمستخدمين لا يمكن حذفها.',
    permissionsModalCopy: 'حدد الصلاحيات المناسبة لهذا الدور، ثم احفظ التغييرات دون مغادرة الجدول.',
    roleAssignedDeleteBlocked: 'هذا الدور مرتبط بمستخدمين حاليًا، لذلك لا يمكن حذفه لحماية العلاقات داخل النظام.',
    loadingPermissions: 'يتم تحميل تفاصيل الدور والصلاحيات...',
    emptyPermissions: 'لا توجد صلاحيات متاحة للإسناد.'
  },
  en: {
    brandChip: 'Yemen Motion',
    managementBadge: 'Active management',
    kicker: 'Roles and permissions',
    title: 'Roles Command Center',
    copy: 'Manage custom roles, allowed edits and deletes, and permission assignment from the same page.',
    managementNotice: 'Create roles, edit custom roles, delete deletable roles, and manage permissions for unprotected roles.',
    primaryGuard: 'Primary guard',
    rolesCount: 'Roles count',
    tableTitle: 'Roles register',
    tableCopy: 'A management table for roles, users, permissions, and available row actions.',
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
    createNameLabel: 'Role name',
    createNamePlaceholder: 'Example: support-agent',
    createAction: 'Create role',
    creating: 'Creating...',
    createSuccess: 'Role created successfully.',
    createNameRequired: 'Enter a role name first.',
    openCreateAction: 'Create',
    closeModal: 'Close modal',
    colActions: 'Actions',
    editAction: 'Edit',
    deleteAction: 'Delete',
    saveAction: 'Save',
    savingAction: 'Saving...',
    deletingAction: 'Deleting...',
    cancelAction: 'Cancel',
    confirmDeleteAction: 'Confirm delete',
    protectedRoleTooltip: 'Protected role',
    assignedRoleDeleteTitle: 'Cannot delete a role assigned to users.',
    deleteRoleTitle: 'Delete role',
    updateRoleSuccess: 'Role updated successfully.',
    deleteRoleSuccess: 'Role deleted successfully.',
    permissionsRoleSuccess: 'Role permissions updated successfully.',
    editRoleNameRequired: 'Enter a role name before saving.',
    updateRoleFailed: 'Could not update role.',
    deleteRoleFailed: 'Could not delete role.',
    permissionsRoleFailed: 'Could not update role permissions.',
    protectedRoleBlocked: 'Protected roles cannot be edited or deleted.',
    protectedRolePermissionsBlocked: 'Protected role permissions cannot be managed.',
    permissionsActionTooltip: 'Permissions & bindings',
    deleteRoleConfirm: 'Delete this role? This action cannot be undone.',
    currentRoleLabel: 'Current role',
    currentPermissionsLabel: 'Current permissions',
    usersCount: 'Users count',
    createModalEyebrow: 'Create action',
    editModalEyebrow: 'Edit action',
    deleteModalEyebrow: 'Delete action',
    permissionsModalEyebrow: 'Permission assignment',
    createModalTitle: 'Create custom role',
    editModalTitle: 'Edit role name',
    deleteModalTitle: 'Delete role',
    permissionsModalTitle: 'Manage role permissions',
    createModalCopy: 'Enter a new role name and it will be added to the roles table after success.',
    editModalCopy: 'Edit the custom role name in this modal without moving inside the table.',
    deleteModalCopy: 'Review the role state before deletion. Roles assigned to users cannot be deleted.',
    permissionsModalCopy: 'Select the permissions for this role, then save without leaving the table.',
    roleAssignedDeleteBlocked: 'This role is currently assigned to users, so it cannot be deleted.',
    loadingPermissions: 'Loading role details and permissions...',
    emptyPermissions: 'No permissions are available for assignment.'
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const roles = ref<AdminRole[]>([])
const availablePermissions = ref<AdminPermission[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const createRoleName = ref('')
const creatingRole = ref(false)
const createRoleFeedback = ref<string | null>(null)
const createRoleFeedbackType = ref<'success' | 'error' | null>(null)
const roleModalTitleId = 'ym-role-modal-title'
const roleModalMode = ref<RoleModalMode>(null)
const selectedRole = ref<AdminRole | null>(null)
const editingRoleName = ref('')
const savingRoleId = ref<number | null>(null)
const deletingRoleId = ref<number | null>(null)
const loadingPermissionsRoleId = ref<number | null>(null)
const savingPermissionsRoleId = ref<number | null>(null)
const selectedPermissionNames = ref<string[]>([])
const roleActionFeedback = ref<string | null>(null)
const roleActionFeedbackType = ref<'success' | 'error' | null>(null)
const roleModalFeedback = ref<string | null>(null)
const roleModalFeedbackType = ref<'success' | 'error' | null>(null)
const sortKey = ref<RolesSortKey>('id')
const sortDirection = ref<SortDirection>('asc')
const totalRoleUsers = computed(() => roles.value.reduce((total, role) => total + Number(role.users_count || 0), 0))
const totalRolePermissions = computed(() => roles.value.reduce((total, role) => total + Number(role.permissions_count || 0), 0))
const primaryGuard = computed(() => roles.value.find(role => role.guard_name)?.guard_name || 'web')
const normalizedCreateRoleName = computed(() => createRoleName.value.trim())
const canCreateRole = computed(() => normalizedCreateRoleName.value.length > 0 && !creatingRole.value)
const permissionsModalRoleName = computed(() => selectedRole.value?.name || '—')
const selectedPermissionSet = computed(() => new Set(selectedPermissionNames.value))
const groupedAvailablePermissions = computed(() => {
  const groups = new Map<string, AdminPermission[]>()

  for (const permission of availablePermissions.value) {
    const groupName = permission.group || 'ungrouped'
    const groupPermissions = groups.get(groupName) || []
    groupPermissions.push(permission)
    groups.set(groupName, groupPermissions)
  }

  return [...groups.entries()]
    .map(([name, permissions]) => {
      const sortedPermissions = [...permissions].sort((first, second) => first.name.localeCompare(second.name, 'en', {
        numeric: true,
        sensitivity: 'base'
      }))

      return {
        name,
        permissions: sortedPermissions,
        selectedCount: sortedPermissions.filter(permission => selectedPermissionSet.value.has(permission.name)).length
      }
    })
    .sort((first, second) => first.name.localeCompare(second.name, 'en', {
      numeric: true,
      sensitivity: 'base'
    }))
})
const canSaveRolePermissions = computed(() => {
  if (!selectedRole.value || !canManageRolePermissions(selectedRole.value)) return false
  if (loadingPermissionsRoleId.value === selectedRole.value.id) return false
  if (savingPermissionsRoleId.value === selectedRole.value.id) return false
  return true
})
const roleModalEyebrow = computed(() => {
  if (roleModalMode.value === 'permissions') return copy.value.permissionsModalEyebrow
  if (roleModalMode.value === 'edit') return copy.value.editModalEyebrow
  if (roleModalMode.value === 'delete') return copy.value.deleteModalEyebrow
  return copy.value.createModalEyebrow
})
const roleModalTitle = computed(() => {
  if (roleModalMode.value === 'permissions') return copy.value.permissionsModalTitle
  if (roleModalMode.value === 'edit') return copy.value.editModalTitle
  if (roleModalMode.value === 'delete') return copy.value.deleteModalTitle
  return copy.value.createModalTitle
})
const roleModalCopy = computed(() => {
  if (roleModalMode.value === 'permissions') return copy.value.permissionsModalCopy
  if (roleModalMode.value === 'edit') return copy.value.editModalCopy
  if (roleModalMode.value === 'delete') return copy.value.deleteModalCopy
  return copy.value.createModalCopy
})
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

function firstValidationError(errors?: Record<string, string[]> | null): string | null {
  if (!errors) return null

  for (const messages of Object.values(errors)) {
    const firstMessage = messages.find(Boolean)
    if (firstMessage) return firstMessage
  }

  return null
}

function clearRoleModalFeedback(): void {
  roleModalFeedback.value = null
  roleModalFeedbackType.value = null
}

function clearRoleActionFeedback(): void {
  roleActionFeedback.value = null
  roleActionFeedbackType.value = null
}

function setRoleModalError(message: string): void {
  roleModalFeedback.value = message
  roleModalFeedbackType.value = 'error'
}

function isRoleProtected(role: AdminRole): boolean {
  return Boolean(role.is_protected) || ['super-admin', 'admin'].includes(role.name)
}

function canManageRolePermissions(role: AdminRole): boolean {
  return !isRoleProtected(role) && role.name !== 'super-admin'
}

function canDeleteRole(role: AdminRole): boolean {
  return !isRoleProtected(role) && Number(role.users_count || 0) === 0
}

function deleteRoleTitle(role: AdminRole): string {
  if (isRoleProtected(role)) return copy.value.protectedRoleBlocked
  if (!canDeleteRole(role)) return copy.value.assignedRoleDeleteTitle
  return copy.value.deleteRoleTitle
}

function deleteRoleBlockingMessage(role: AdminRole): string {
  if (isRoleProtected(role)) return copy.value.protectedRoleBlocked
  return copy.value.roleAssignedDeleteBlocked
}

function openCreateRoleModal(): void {
  createRoleName.value = ''
  createRoleFeedback.value = null
  createRoleFeedbackType.value = null
  selectedRole.value = null
  roleModalMode.value = 'create'
  clearRoleModalFeedback()
  clearRoleActionFeedback()
}

function openEditRoleModal(role: AdminRole): void {
  if (isRoleProtected(role)) {
    roleActionFeedback.value = copy.value.protectedRoleBlocked
    roleActionFeedbackType.value = 'error'
    return
  }

  selectedRole.value = role
  editingRoleName.value = role.name
  roleModalMode.value = 'edit'
  clearRoleModalFeedback()
  clearRoleActionFeedback()
}

function openDeleteRoleModal(role: AdminRole): void {
  selectedRole.value = role
  roleModalMode.value = 'delete'
  clearRoleModalFeedback()
  clearRoleActionFeedback()
}

function openRolePermissionsModal(role: AdminRole): void {
  if (!canManageRolePermissions(role)) {
    roleActionFeedback.value = copy.value.protectedRolePermissionsBlocked
    roleActionFeedbackType.value = 'error'
    return
  }

  selectedRole.value = role
  selectedPermissionNames.value = role.permissions ? [...role.permissions] : []
  roleModalMode.value = 'permissions'
  clearRoleModalFeedback()
  clearRoleActionFeedback()
  void fetchRolePermissionsPayload(role)
}

function closeRoleModal(): void {
  if (creatingRole.value || savingRoleId.value || deletingRoleId.value || savingPermissionsRoleId.value) return

  roleModalMode.value = null
  selectedRole.value = null
  editingRoleName.value = ''
  selectedPermissionNames.value = []
  clearRoleModalFeedback()
}

async function updateSelectedRole(): Promise<void> {
  if (!selectedRole.value) return
  await updateRole(selectedRole.value)
}

async function deleteSelectedRole(): Promise<void> {
  if (!selectedRole.value) return
  await deleteRole(selectedRole.value)
}

async function saveSelectedRolePermissions(): Promise<void> {
  if (!selectedRole.value) return
  await syncRolePermissions(selectedRole.value)
}

function isPermissionSelected(permissionName: string): boolean {
  return selectedPermissionSet.value.has(permissionName)
}

function togglePermission(permissionName: string): void {
  const nextPermissionNames = new Set(selectedPermissionNames.value)

  if (nextPermissionNames.has(permissionName)) {
    nextPermissionNames.delete(permissionName)
  } else {
    nextPermissionNames.add(permissionName)
  }

  selectedPermissionNames.value = [...nextPermissionNames].sort((first, second) => first.localeCompare(second, 'en', {
    numeric: true,
    sensitivity: 'base'
  }))
  clearRoleModalFeedback()
}

async function fetchRolePermissionsPayload(role: AdminRole): Promise<void> {
  loadingPermissionsRoleId.value = role.id

  try {
    const [permissionsResponse, roleResponse] = await Promise.all([
      apiFetch<AdminPermissionsResponse>('/admin/permissions'),
      apiFetch<AdminRoleResponse>(`/admin/roles/${role.id}`)
    ])

    availablePermissions.value = permissionsResponse.data || []

    if (selectedRole.value?.id === role.id) {
      selectedRole.value = roleResponse.data
      selectedPermissionNames.value = [...(roleResponse.data.permissions || [])]
    }
  } catch (requestError: unknown) {
    const err = requestError as any
    setRoleModalError(
      firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || copy.value.permissionsRoleFailed
    )
  } finally {
    if (loadingPermissionsRoleId.value === role.id) {
      loadingPermissionsRoleId.value = null
    }
  }
}

async function updateRole(role: AdminRole): Promise<void> {
  clearRoleModalFeedback()
  clearRoleActionFeedback()

  if (isRoleProtected(role)) {
    setRoleModalError(copy.value.protectedRoleBlocked)
    return
  }

  const nextName = editingRoleName.value.trim()
  if (!nextName) {
    setRoleModalError(copy.value.editRoleNameRequired)
    return
  }

  savingRoleId.value = role.id

  try {
    await apiFetch<UpdateRoleResponse>(`/admin/roles/${role.id}`, {
      method: 'PATCH',
      body: {
        name: nextName
      }
    })

    roleActionFeedback.value = copy.value.updateRoleSuccess
    roleActionFeedbackType.value = 'success'
    savingRoleId.value = null
    closeRoleModal()
    await fetchRoles()
  } catch (requestError: unknown) {
    const err = requestError as any
    setRoleModalError(
      firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || copy.value.updateRoleFailed
    )
  } finally {
    savingRoleId.value = null
  }
}

async function deleteRole(role: AdminRole): Promise<void> {
  clearRoleModalFeedback()
  clearRoleActionFeedback()

  if (!canDeleteRole(role)) {
    setRoleModalError(deleteRoleBlockingMessage(role))
    return
  }

  deletingRoleId.value = role.id

  try {
    await apiFetch<DeleteRoleResponse>(`/admin/roles/${role.id}`, {
      method: 'DELETE'
    })

    roleActionFeedback.value = copy.value.deleteRoleSuccess
    roleActionFeedbackType.value = 'success'
    deletingRoleId.value = null
    closeRoleModal()
    await fetchRoles()
  } catch (requestError: unknown) {
    const err = requestError as any
    setRoleModalError(
      firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || copy.value.deleteRoleFailed
    )
  } finally {
    deletingRoleId.value = null
  }
}

async function syncRolePermissions(role: AdminRole): Promise<void> {
  clearRoleModalFeedback()
  clearRoleActionFeedback()

  if (!canManageRolePermissions(role)) {
    setRoleModalError(copy.value.protectedRolePermissionsBlocked)
    return
  }

  savingPermissionsRoleId.value = role.id

  try {
    await apiFetch<SyncRolePermissionsResponse>(`/admin/roles/${role.id}/permissions`, {
      method: 'PUT',
      body: {
        permissions: selectedPermissionNames.value
      }
    })

    roleActionFeedback.value = copy.value.permissionsRoleSuccess
    roleActionFeedbackType.value = 'success'
    savingPermissionsRoleId.value = null
    closeRoleModal()
    await fetchRoles()
  } catch (requestError: unknown) {
    const err = requestError as any
    setRoleModalError(
      firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || copy.value.permissionsRoleFailed
    )
  } finally {
    savingPermissionsRoleId.value = null
  }
}

async function createRole(): Promise<void> {
  if (!normalizedCreateRoleName.value) {
    setRoleModalError(copy.value.createNameRequired)
    return
  }

  creatingRole.value = true
  createRoleFeedback.value = null
  createRoleFeedbackType.value = null
  clearRoleModalFeedback()
  clearRoleActionFeedback()

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

    creatingRole.value = false
    closeRoleModal()
    await fetchRoles()
  } catch (requestError: unknown) {
    const err = requestError as any
    setRoleModalError(
      firstValidationError(err?.data?.errors)
      || err?.data?.message
      || err?.message
      || (currentLocale.value === 'ar'
        ? 'فشل إنشاء الدور.'
        : 'Could not create role.')
    )
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

.ym-roles-notice {
  display: flex;
  align-items: center;
  gap: 0.9rem;
  border: 1px solid color-mix(in srgb, #10b981 34%, var(--ym-soft-border));
  border-radius: 20px;
  background:
    linear-gradient(180deg, color-mix(in srgb, #10b981 10%, transparent), transparent),
    color-mix(in srgb, var(--ym-card-bg) 86%, transparent);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
  padding: 0.9rem 1rem;
  color: var(--ym-text);
}

.ym-roles-notice__badge {
  flex: 0 0 auto;
  border: 1px solid color-mix(in srgb, #10b981 46%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, #10b981 16%, transparent);
  padding: 0.35rem 0.7rem;
  color: #10b981;
  font-size: 13px;
  font-weight: 950;
}

.ym-roles-notice p {
  margin: 0;
  font-size: 14px;
  font-weight: 800;
  line-height: 1.7;
}

.ym-create-role-form,
.ym-role-modal-form {
  display: grid;
  gap: 0.9rem;
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

.ym-role-modal-fade-enter-active,
.ym-role-modal-fade-leave-active {
  transition: opacity 180ms ease;
}

.ym-role-modal-fade-enter-from,
.ym-role-modal-fade-leave-to {
  opacity: 0;
}

.ym-role-modal-backdrop {
  position: fixed;
  z-index: 80;
  inset: 0;
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at 50% 12%, rgba(56, 189, 248, 0.18), transparent 18rem),
    rgba(2, 6, 23, 0.64);
  padding: 1rem;
  backdrop-filter: blur(14px);
}

.ym-role-modal {
  position: relative;
  width: min(100%, 520px);
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 72%, rgba(255, 255, 255, 0.2));
  border-radius: 28px;
  background:
    radial-gradient(circle at 8% 0%, color-mix(in srgb, #8b5cf6 22%, transparent), transparent 14rem),
    radial-gradient(circle at 94% 14%, color-mix(in srgb, #38bdf8 18%, transparent), transparent 14rem),
    var(--ym-card-bg);
  box-shadow:
    0 34px 90px rgba(2, 6, 23, 0.42),
    inset 0 1px 0 rgba(255, 255, 255, 0.16);
  padding: clamp(1.1rem, 2.4vw, 1.5rem);
}

.ym-role-modal.is-permissions {
  width: min(100%, 760px);
}

.ym-role-modal__close {
  position: absolute;
  top: 0.85rem;
  inset-inline-end: 0.85rem;
  display: grid;
  height: 2rem;
  width: 2rem;
  place-items: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-control-bg) 86%, transparent);
  color: var(--ym-text);
  font-size: 20px;
  font-weight: 900;
  line-height: 1;
}

.ym-role-modal__head {
  padding-inline-end: 2.5rem;
}

.ym-role-modal__head span {
  display: inline-flex;
  border: 1px solid color-mix(in srgb, #38bdf8 34%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 12%, transparent);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 950;
  padding: 0.28rem 0.62rem;
}

.ym-role-modal__head h2 {
  margin: 0.75rem 0 0;
  color: var(--ym-text);
  font-size: clamp(1.35rem, 2.3vw, 1.75rem);
  font-weight: 950;
}

.ym-role-modal__head p {
  margin: 0.45rem 0 0;
  color: var(--ym-muted);
  font-size: 13.5px;
  font-weight: 800;
  line-height: 1.75;
}

.ym-role-modal-feedback,
.ym-role-action-feedback {
  border-radius: 14px;
  font-size: 13px;
  font-weight: 850;
  margin: 1rem 0 0;
  padding: 0.7rem 0.8rem;
}

.ym-role-action-feedback {
  margin: 0 0 1rem;
}

.ym-role-modal-feedback.is-success,
.ym-role-action-feedback.is-success {
  background: color-mix(in srgb, #10b981 16%, transparent);
  color: #10b981;
}

.ym-role-modal-feedback.is-error,
.ym-role-action-feedback.is-error {
  background: color-mix(in srgb, #ef4444 14%, transparent);
  color: #ef4444;
}

.ym-role-modal-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.65rem;
}

.ym-role-modal-button {
  border: 1px solid var(--ym-soft-border);
  border-radius: 16px;
  background: color-mix(in srgb, var(--ym-control-bg) 82%, transparent);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  min-height: 42px;
  padding: 0.68rem 1rem;
}

.ym-role-modal-button.is-primary {
  border-color: color-mix(in srgb, #10b981 54%, transparent);
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
}

.ym-role-modal-button.is-danger {
  border-color: color-mix(in srgb, #ef4444 54%, transparent);
  background: linear-gradient(135deg, #ef4444, #b91c1c);
  color: #fff;
}

.ym-role-modal-button:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.ym-role-modal-current,
.ym-role-delete-summary {
  display: grid;
  gap: 0.25rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: color-mix(in srgb, var(--ym-control-bg) 72%, transparent);
  padding: 0.85rem;
}

.ym-role-modal-current span,
.ym-role-delete-summary span,
.ym-role-delete-summary small {
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 850;
}

.ym-role-modal-current strong,
.ym-role-delete-summary strong {
  color: var(--ym-text);
  font-size: 15px;
  font-weight: 950;
}

.ym-role-delete-warning,
.ym-role-delete-danger {
  border-radius: 16px;
  font-size: 13.5px;
  font-weight: 850;
  line-height: 1.7;
  margin: 0;
  padding: 0.85rem;
}

.ym-role-delete-warning {
  background: color-mix(in srgb, #f59e0b 14%, transparent);
  color: #f59e0b;
}

.ym-role-delete-danger {
  background: color-mix(in srgb, #ef4444 14%, transparent);
  color: #ef4444;
}

.ym-role-permissions-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.75rem;
}

.ym-role-permissions-summary > div {
  display: grid;
  gap: 0.25rem;
  border: 1px solid var(--ym-soft-border);
  border-radius: 18px;
  background: color-mix(in srgb, var(--ym-control-bg) 72%, transparent);
  padding: 0.85rem;
}

.ym-role-permissions-summary span,
.ym-role-permissions-state,
.ym-permissions-group__head span,
.ym-permission-checkbox small {
  color: var(--ym-muted);
  font-size: 12.5px;
  font-weight: 850;
}

.ym-role-permissions-summary strong {
  color: var(--ym-text);
  font-size: 16px;
  font-variant-numeric: tabular-nums;
  font-weight: 950;
}

.ym-role-permissions-state {
  display: grid;
  justify-items: center;
  gap: 0.65rem;
  border: 1px dashed color-mix(in srgb, var(--ym-soft-border) 80%, transparent);
  border-radius: 18px;
  padding: 1.3rem 1rem;
  text-align: center;
}

.ym-permissions-groups {
  display: grid;
  max-height: min(52vh, 520px);
  gap: 0.85rem;
  overflow: auto;
  padding-inline-end: 0.25rem;
}

.ym-permissions-group {
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 78%, transparent);
  border-radius: 18px;
  background: color-mix(in srgb, var(--ym-control-bg) 58%, transparent);
  padding: 0.8rem;
}

.ym-permissions-group__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.65rem;
}

.ym-permissions-group__head strong {
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
}

.ym-permissions-group__head span {
  border: 1px solid color-mix(in srgb, #38bdf8 34%, transparent);
  border-radius: 999px;
  background: color-mix(in srgb, #38bdf8 12%, transparent);
  color: #38bdf8;
  font-variant-numeric: tabular-nums;
  padding: 0.25rem 0.55rem;
}

.ym-permission-checkbox {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  gap: 0.65rem;
  border-radius: 14px;
  cursor: pointer;
  padding: 0.55rem 0.45rem;
  transition: background 150ms ease;
}

.ym-permission-checkbox:hover {
  background: color-mix(in srgb, #38bdf8 8%, transparent);
}

.ym-permission-checkbox input {
  height: 1.05rem;
  width: 1.05rem;
  accent-color: #10b981;
}

.ym-permission-checkbox span {
  display: grid;
  min-width: 0;
  gap: 0.15rem;
}

.ym-permission-checkbox strong {
  overflow: hidden;
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 900;
  text-overflow: ellipsis;
  white-space: nowrap;
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
  align-items: center;
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

.ym-table-card__actions {
  display: flex;
  flex: 0 0 auto;
  align-items: center;
  justify-content: flex-end;
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
  min-width: max(100%, 1240px);
  border-collapse: collapse;
  table-layout: fixed;
}

.ym-roles-table thead th {
  position: relative;
  padding: 1rem 1.05rem;
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
  height: 68px;
  padding: 1rem 1.05rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 62%, transparent);
  color: var(--ym-text);
  font-size: 14.5px;
  font-weight: 780;
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
  font-weight: 920;
  white-space: nowrap;
}

.ym-roles-cell-id,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count {
  color: var(--ym-text);
  font-size: 15px;
}

.ym-role-name {
  display: inline-flex;
  align-items: center;
  border: 1px solid color-mix(in srgb, #38bdf8 38%, transparent);
  border-radius: 14px;
  background:
    linear-gradient(180deg, color-mix(in srgb, #38bdf8 18%, transparent), color-mix(in srgb, #38bdf8 10%, transparent));
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 950;
  min-height: 34px;
  padding: 0.42rem 0.75rem;
}

.ym-role-name-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  max-width: 100%;
  vertical-align: middle;
}

.ym-role-protected-dot {
  position: relative;
  z-index: 1;
  display: inline-flex;
  height: 0.58rem;
  width: 0.58rem;
  flex: 0 0 auto;
  border: 2px solid color-mix(in srgb, #fff 82%, transparent);
  border-radius: 999px;
  background: #ef4444;
  box-shadow:
    0 0 0 4px color-mix(in srgb, #ef4444 14%, transparent),
    0 0 16px color-mix(in srgb, #ef4444 58%, transparent);
  overflow: visible;
}

.ym-role-name.is-protected {
  border-color: color-mix(in srgb, #f59e0b 36%, transparent);
  background: color-mix(in srgb, #f59e0b 16%, transparent);
}

.ym-role-actions {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  overflow: visible;
}

.ym-role-action-button {
  position: relative;
  display: inline-grid;
  height: 2.4rem;
  width: 2.4rem;
  place-items: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: color-mix(in srgb, var(--ym-control-bg) 82%, transparent);
  color: var(--ym-text);
  font-size: 12px;
  font-weight: 950;
  line-height: 1;
  cursor: pointer;
  padding: 0;
  overflow: visible;
  transition: transform 150ms ease, opacity 150ms ease, border-color 150ms ease, background 150ms ease;
}

.ym-role-action-button svg {
  height: 1.05rem;
  width: 1.05rem;
  fill: none;
  stroke: currentColor;
  stroke-linecap: round;
  stroke-linejoin: round;
  stroke-width: 1.9;
}

.ym-role-action-button:hover:not(:disabled) {
  border-color: color-mix(in srgb, #38bdf8 48%, var(--ym-soft-border));
  transform: translateY(-1px);
}

.ym-role-action-button.is-permissions {
  border-color: color-mix(in srgb, #38bdf8 42%, transparent);
  background: color-mix(in srgb, #38bdf8 11%, transparent);
  color: #38bdf8;
}

.ym-role-action-button.is-edit {
  border-color: color-mix(in srgb, #10b981 42%, transparent);
  background: color-mix(in srgb, #10b981 11%, transparent);
  color: #10b981;
}

.ym-role-action-button.is-danger {
  border-color: color-mix(in srgb, #ef4444 42%, transparent);
  background: color-mix(in srgb, #ef4444 12%, transparent);
  color: #ef4444;
}

.ym-role-action-button.is-soft-locked {
  border-color: color-mix(in srgb, #f59e0b 42%, transparent);
  background: color-mix(in srgb, #f59e0b 11%, transparent);
  color: #f59e0b;
}

.ym-role-action-button:disabled {
  cursor: not-allowed;
  filter: saturate(0.65);
  opacity: 0.48;
}

.ym-role-action-button::after,
.ym-role-protected-dot::after {
  position: absolute;
  z-index: 40;
  content: attr(data-tooltip);
  bottom: calc(100% + 0.6rem);
  left: 50%;
  min-width: max-content;
  max-width: 13rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 82%, transparent);
  border-radius: 10px;
  background: color-mix(in srgb, #020617 92%, transparent);
  box-shadow: 0 14px 34px rgba(2, 6, 23, 0.24);
  color: #fff;
  font-size: 11.5px;
  font-weight: 850;
  line-height: 1.35;
  opacity: 0;
  padding: 0.42rem 0.55rem;
  pointer-events: none;
  text-align: center;
  transform: translateX(-50%) translateY(4px);
  transition: opacity 150ms ease, transform 150ms ease;
  white-space: nowrap;
}

.ym-role-action-button::before,
.ym-role-protected-dot::before {
  position: absolute;
  z-index: 39;
  bottom: calc(100% + 0.34rem);
  left: 50%;
  height: 0.45rem;
  width: 0.45rem;
  background: color-mix(in srgb, #020617 92%, transparent);
  content: "";
  opacity: 0;
  pointer-events: none;
  transform: translateX(-50%) rotate(45deg);
  transition: opacity 150ms ease;
}

.ym-role-action-button:hover::after,
.ym-role-action-button:focus-visible::after,
.ym-role-protected-dot:hover::after,
.ym-role-protected-dot:focus-visible::after,
.ym-role-action-button:hover::before,
.ym-role-action-button:focus-visible::before,
.ym-role-protected-dot:hover::before,
.ym-role-protected-dot:focus-visible::before {
  opacity: 1;
}

.ym-role-action-button:hover::after,
.ym-role-action-button:focus-visible::after,
.ym-role-protected-dot:hover::after,
.ym-role-protected-dot:focus-visible::after {
  transform: translateX(-50%) translateY(0);
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
  .ym-roles-notice,
  .ym-create-role-form {
    align-items: flex-start;
    flex-direction: column;
  }

  .ym-create-role-form,
  .ym-role-modal-form {
    display: flex;
  }

  .ym-create-role-field,
  .ym-create-role-button {
    width: 100%;
  }

  .ym-role-permissions-summary {
    grid-template-columns: 1fr;
  }

  .ym-role-modal-actions {
    justify-content: stretch;
  }

  .ym-role-modal-button {
    flex: 1 1 10rem;
  }
}
/* YM-ADMIN-UI final fix: clean semantic roles table */
.ym-roles-table-wrap {
  overflow-x: auto;
}

.ym-roles-table {
  width: 100% !important;
  min-width: 1240px;
  table-layout: fixed;
  border-collapse: separate;
  border-spacing: 0;
  direction: rtl;
}

/* من اليمين لليسار:
   # | الاسم | Guard Name | عدد المستخدمين | عدد الصلاحيات | تاريخ الإنشاء | الإجراءات
*/
.ym-roles-table th:nth-child(1),
.ym-roles-table td:nth-child(1) {
  width: 6% !important;
}

.ym-roles-table th:nth-child(2),
.ym-roles-table td:nth-child(2) {
  width: 18% !important;
}

.ym-roles-table th:nth-child(3),
.ym-roles-table td:nth-child(3) {
  width: 15% !important;
}

.ym-roles-table th:nth-child(4),
.ym-roles-table td:nth-child(4) {
  width: 12% !important;
}

.ym-roles-table th:nth-child(5),
.ym-roles-table td:nth-child(5) {
  width: 16% !important;
}

.ym-roles-table th:nth-child(6),
.ym-roles-table td:nth-child(6) {
  width: 16% !important;
}

.ym-roles-table th:nth-child(7),
.ym-roles-table td:nth-child(7) {
  width: 17% !important;
}

.ym-roles-table th,
.ym-roles-table td,
.ym-roles-cell-id,
.ym-roles-cell-name,
.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created,
.ym-roles-cell-actions {
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
.ym-roles-th-actions,
.ym-roles-cell-id,
.ym-roles-cell-name,
.ym-roles-cell-guard-name,
.ym-roles-cell-users-count,
.ym-roles-cell-permissions-count,
.ym-roles-cell-created,
.ym-roles-cell-actions {
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

.ym-roles-cell-name,
.ym-roles-cell-actions {
  overflow: visible !important;
}

.ym-role-name {
  display: inline-flex !important;
  max-width: 18ch;
  min-width: 84px;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  text-overflow: clip;
  white-space: nowrap;
  vertical-align: middle;
  unicode-bidi: isolate;
}

</style>
