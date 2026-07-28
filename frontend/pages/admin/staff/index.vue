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
              <th class="is-id">#</th>
              <th class="is-name">{{ copy.colName }}</th>
              <th class="is-email">{{ copy.colEmail }}</th>
              <th class="is-roles">{{ copy.colRoles }}</th>
              <th class="is-date">{{ copy.colCreated }}</th>
              <th
                v-if="
                  canUpdateStaff
                  || canAssignStaffRoles
                  || canAssignStaffPermissions
                  || canViewActivity
                "
                class="is-actions"
              >
                {{ copy.colActions }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in staffUsers" :key="user.id">
              <td class="is-id">{{ user.id }}</td>
              <td class="is-name">
                <strong :dir="textDirection(user.name)">{{ user.name }}</strong>
              </td>
              <td class="is-email" dir="ltr">
                <span class="ym-staff-email" :title="user.email">{{ user.email }}</span>
              </td>
              <td class="is-roles">
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
              <td
                v-if="
                  canUpdateStaff
                  || canAssignStaffRoles
                  || canAssignStaffPermissions
                  || canViewActivity
                "
                class="is-actions"
              >
                <div class="ym-staff-row-actions">
                  <button
                    v-if="canUpdateStaff"
                    type="button"
                    class="ym-staff-row-action is-edit"
                    @click="openEditStaffModal(user, $event)"
                  >
                    <span aria-hidden="true">✎</span>
                    {{ copy.editStaff }}
                  </button>
                  <button
                    v-if="canAssignStaffRoles"
                    type="button"
                    class="ym-staff-row-action is-roles"
                    @click="openRoleModal(user, $event)"
                  >
                    <span aria-hidden="true">⌘</span>
                    {{ copy.manageRoles }}
                  </button>
                  <button
                    v-if="canAssignStaffPermissions"
                    type="button"
                    class="ym-staff-row-action is-permissions"
                    @click="openPermissionsModal(user, $event)"
                  >
                    <span aria-hidden="true">✓</span>
                    {{ copy.managePermissions }}
                  </button>
                  <button
                    v-if="canViewActivity"
                    type="button"
                    class="ym-staff-row-action"
                    @click="openActivity(user, $event)"
                  >
                    <span aria-hidden="true">◷</span>
                    {{ copy.accountActivity }}
                  </button>
                </div>
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
        class="ym-staff-dialog-backdrop ym-admin-page"
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
        v-if="editModalOpen && editingStaff"
        class="ym-staff-dialog-backdrop ym-admin-page"
        :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
        :style="{ '--ym-admin-section-accent': '#06b6d4', '--ym-admin-section-accent-secondary': '#8b5cf6' }"
        role="presentation"
        @mousedown.self="closeEditStaffModal"
      >
        <section
          ref="editDialog"
          class="ym-staff-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'ym-edit-staff-title'"
          tabindex="-1"
        >
          <header>
            <div>
              <span>{{ copy.editEyebrow }}</span>
              <h2 id="ym-edit-staff-title">{{ copy.editStaff }}</h2>
              <p>{{ copy.editDescription }}</p>
            </div>
            <button
              type="button"
              class="ym-staff-icon-button"
              :aria-label="copy.close"
              :disabled="updatingStaff"
              @click="closeEditStaffModal"
            >
              ×
            </button>
          </header>

          <form class="ym-staff-create-form" @submit.prevent="submitEditStaff">
            <p v-if="editError" class="ym-staff-inline-error" role="alert">
              {{ editError }}
            </p>

            <label class="ym-staff-field">
              <span>{{ copy.formName }}</span>
              <input
                ref="firstEditInput"
                v-model.trim="editForm.name"
                type="text"
                autocomplete="name"
                :aria-invalid="Boolean(editFieldError('name'))"
              >
              <small v-if="editFieldError('name')">{{ editFieldError('name') }}</small>
            </label>

            <label class="ym-staff-field">
              <span>{{ copy.formEmail }}</span>
              <input
                v-model.trim="editForm.email"
                type="email"
                dir="ltr"
                autocomplete="email"
                :aria-invalid="Boolean(editFieldError('email'))"
              >
              <small v-if="editFieldError('email')">{{ editFieldError('email') }}</small>
            </label>

            <p class="ym-staff-edit-scope">
              {{ copy.editScope }}
            </p>

            <footer>
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="updatingStaff"
                @click="closeEditStaffModal"
              >
                {{ copy.cancel }}
              </button>
              <button
                type="submit"
                class="ym-staff-primary-button"
                :disabled="updatingStaff"
              >
                <span v-if="updatingStaff" class="ym-staff-button-spinner" aria-hidden="true" />
                {{ updatingStaff ? copy.updating : copy.saveChanges }}
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="roleModalOpen && roleStaff"
        class="ym-staff-dialog-backdrop ym-admin-page"
        :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
        :style="{ '--ym-admin-section-accent': '#06b6d4', '--ym-admin-section-accent-secondary': '#8b5cf6' }"
        role="presentation"
        @mousedown.self="closeRoleModal"
      >
        <section
          ref="roleDialog"
          class="ym-staff-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'ym-staff-roles-title'"
          tabindex="-1"
        >
          <header>
            <div>
              <span>{{ copy.rolesEyebrow }}</span>
              <h2 id="ym-staff-roles-title">{{ copy.rolesTitle }}</h2>
              <p>{{ copy.rolesDescription }}</p>
            </div>
            <button
              type="button"
              class="ym-staff-icon-button"
              :aria-label="copy.close"
              :disabled="savingStaffRoles"
              @click="closeRoleModal"
            >
              ×
            </button>
          </header>

          <form class="ym-staff-create-form" @submit.prevent="submitStaffRoles">
            <p v-if="roleError" class="ym-staff-inline-error" role="alert">
              {{ roleError }}
            </p>

            <div class="ym-staff-role-modal-user">
              <strong :dir="textDirection(roleStaff.name)">{{ roleStaff.name }}</strong>
              <span dir="ltr">{{ roleStaff.email }}</span>
              <small>#{{ roleStaff.id }}</small>
            </div>

            <fieldset class="ym-staff-role-fieldset">
              <legend>{{ copy.availableInternalRoles }}</legend>
              <div class="ym-staff-role-options">
                <label
                  v-for="role in ['staff', 'admin']"
                  :key="role"
                  class="ym-staff-role-option"
                  :class="[`is-${role}`, { 'is-selected': selectedStaffRoles.includes(role) }]"
                >
                  <span class="ym-staff-role-option__check">
                    <input
                      v-model="selectedStaffRoles"
                      type="checkbox"
                      :value="role"
                      :disabled="savingStaffRoles"
                    >
                    <span aria-hidden="true">
                      {{ selectedStaffRoles.includes(role) ? '✓' : '' }}
                    </span>
                  </span>
                  <span class="ym-staff-role-option__content">
                    <strong>{{ role }}</strong>
                    <small>
                      {{
                        role === 'staff'
                          ? copy.staffRoleDescription
                          : copy.adminRoleDescription
                      }}
                    </small>
                  </span>
                </label>
              </div>
            </fieldset>

            <p class="ym-staff-edit-scope">
              {{ copy.rolesScope }}
            </p>
            <small v-if="roleFieldError('roles')" class="ym-staff-role-field-error">
              {{ roleFieldError('roles') }}
            </small>

            <footer>
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="savingStaffRoles"
                @click="closeRoleModal"
              >
                {{ copy.cancel }}
              </button>
              <button
                type="submit"
                class="ym-staff-primary-button"
                :disabled="savingStaffRoles"
              >
                <span
                  v-if="savingStaffRoles"
                  class="ym-staff-button-spinner"
                  aria-hidden="true"
                />
                {{ savingStaffRoles ? copy.savingRoles : copy.saveRoles }}
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="permissionsModalOpen && permissionsStaff"
        class="ym-staff-dialog-backdrop ym-admin-page"
        :dir="currentLocale === 'en' ? 'ltr' : 'rtl'"
        :style="{ '--ym-admin-section-accent': '#06b6d4', '--ym-admin-section-accent-secondary': '#8b5cf6' }"
        role="presentation"
        @mousedown.self="closePermissionsModal"
      >
        <section
          ref="permissionsDialog"
          class="ym-staff-dialog ym-staff-permissions-dialog"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="'ym-staff-permissions-title'"
          tabindex="-1"
        >
          <header>
            <div>
              <span>{{ copy.permissionsEyebrow }}</span>
              <h2 id="ym-staff-permissions-title">{{ copy.permissionsTitle }}</h2>
              <p>{{ copy.permissionsDescription }}</p>
            </div>
            <button
              type="button"
              class="ym-staff-icon-button"
              :aria-label="copy.close"
              :disabled="savingStaffPermissions"
              @click="closePermissionsModal"
            >
              ×
            </button>
          </header>

          <form class="ym-staff-permissions-form" @submit.prevent="submitStaffPermissions">
            <div class="ym-staff-permissions-body">
              <div
                v-if="permissionsLoading"
                class="ym-staff-loading"
                role="status"
              >
                <span aria-hidden="true" />
                <strong>{{ copy.permissionsLoading }}</strong>
              </div>

              <div
                v-else-if="permissionsError && !permissionsLoaded"
                class="ym-staff-permissions-error"
              >
                <p class="ym-staff-inline-error" role="alert">
                  {{ permissionsError }}
                </p>
                <button
                  type="button"
                  class="ym-staff-secondary-button"
                  @click="loadStaffPermissions"
                >
                  {{ copy.retry }}
                </button>
              </div>

              <template v-else>
                <div class="ym-staff-permissions-user">
                  <div>
                    <strong :dir="textDirection(permissionsStaff.name)">
                      {{ permissionsStaff.name }}
                    </strong>
                    <span dir="ltr">{{ permissionsStaff.email }}</span>
                  </div>
                  <div class="ym-staff-permissions-user__meta">
                    <small>#{{ permissionsStaff.id }}</small>
                    <span>
                      <small
                        v-for="role in permissionsStaff.roles"
                        :key="role"
                        class="ym-staff-permissions-user__role"
                      >
                        {{ role }}
                      </small>
                    </span>
                  </div>
                </div>

                <p v-if="permissionsError" class="ym-staff-inline-error" role="alert">
                  {{ permissionsError }}
                </p>

                <div class="ym-staff-permissions-summary">
                  <span>
                    <small>{{ copy.availablePermissionCount }}</small>
                    <strong>{{ availableStaffPermissions.length }}</strong>
                  </span>
                  <span>
                    <small>{{ copy.directPermissionCount }}</small>
                    <strong>{{ directStaffPermissions.length }}</strong>
                  </span>
                  <span>
                    <small>{{ copy.inheritedPermissionCount }}</small>
                    <strong>{{ inheritedStaffPermissions.length }}</strong>
                  </span>
                  <span v-if="protectedDirectPermissions.length" class="is-protected">
                    <small>{{ copy.protectedPermissionCount }}</small>
                    <strong>{{ protectedDirectPermissions.length }}</strong>
                  </span>
                </div>

                <div class="ym-staff-permissions-toolbar">
                  <label class="ym-staff-field">
                    <span>{{ copy.permissionsSearch }}</span>
                    <input
                      v-model.trim="permissionsSearch"
                      type="search"
                      :placeholder="copy.permissionsSearchPlaceholder"
                    >
                  </label>
                  <label class="ym-staff-field">
                    <span>{{ copy.permissionsFilter }}</span>
                    <select v-model="permissionsFilter">
                      <option value="all">{{ copy.permissionsFilterAll }}</option>
                      <option value="direct">{{ copy.permissionsFilterDirect }}</option>
                      <option value="inherited">{{ copy.permissionsFilterInherited }}</option>
                      <option value="effective">{{ copy.permissionsFilterEffective }}</option>
                    </select>
                  </label>
                  <div class="ym-staff-permissions-legend" aria-label="Permission legend">
                    <span class="is-direct">{{ copy.permissionsLegendDirect }}</span>
                    <span class="is-inherited">{{ copy.permissionsLegendInherited }}</span>
                    <span class="is-protected">{{ copy.permissionsLegendProtected }}</span>
                  </div>
                </div>

                <p
                  v-if="permissionFieldError('permissions')"
                  class="ym-staff-inline-error"
                  role="alert"
                >
                  {{ permissionFieldError('permissions') }}
                </p>

                <section
                  v-if="protectedDirectPermissions.length"
                  class="ym-staff-protected-permissions"
                >
                  <header>
                    <strong>{{ copy.protectedPermissionsTitle }}</strong>
                    <p>{{ copy.protectedPermissionsDescription }}</p>
                  </header>
                  <ul>
                    <li v-for="permission in protectedDirectPermissions" :key="permission">
                      <code>{{ permission }}</code>
                    </li>
                  </ul>
                </section>

                <section class="ym-staff-permissions-catalog">
                  <header>
                    <strong>{{ copy.manageablePermissions }}</strong>
                    <small>{{ filteredPermissionGroups.length }}</small>
                  </header>

                  <div
                    v-if="filteredPermissionGroups.length"
                    class="ym-staff-permission-groups"
                  >
                    <details
                      v-for="group in filteredPermissionGroups"
                      :key="group.name"
                      class="ym-staff-permission-group"
                      open
                    >
                      <summary>
                        <strong>{{ staffPermissionGroupLabel(group.name) }}</strong>
                        <small>{{ group.permissions.length }}</small>
                      </summary>
                      <div class="ym-staff-permission-list">
                        <label
                          v-for="permission in group.permissions"
                          :key="permission.name"
                          class="ym-staff-permission-row"
                          :class="{
                            'is-selected': selectedDirectPermissions.includes(permission.name)
                          }"
                          :title="permission.name"
                        >
                          <input
                            v-model="selectedDirectPermissions"
                            type="checkbox"
                            :value="permission.name"
                            :disabled="savingStaffPermissions"
                          >
                          <span class="ym-staff-permission-row__meta">
                            <strong>{{ staffPermissionLabel(permission) }}</strong>
                          </span>
                          <span class="ym-staff-permission-row__badges">
                            <small
                              v-if="selectedDirectPermissions.includes(permission.name)"
                              class="is-direct"
                            >
                              {{ copy.directPermission }}
                            </small>
                            <small
                              v-if="inheritedStaffPermissions.includes(permission.name)"
                              class="is-inherited"
                            >
                              {{ copy.inheritedPermission }}
                            </small>
                            <small
                              v-if="effectiveStaffPermissions.includes(permission.name)"
                              class="is-effective"
                            >
                              {{ copy.effectivePermission }}
                            </small>
                          </span>
                        </label>
                      </div>
                    </details>
                  </div>

                  <p v-else class="ym-staff-permissions-empty">
                    {{ copy.noPermissions }}
                  </p>
                </section>
              </template>
            </div>

            <footer class="ym-staff-permissions-footer">
              <button
                type="button"
                class="ym-staff-secondary-button"
                :disabled="savingStaffPermissions"
                @click="closePermissionsModal"
              >
                {{ copy.cancel }}
              </button>
              <button
                type="submit"
                class="ym-staff-primary-button"
                :disabled="permissionsLoading || savingStaffPermissions || !permissionsLoaded"
              >
                <span
                  v-if="savingStaffPermissions"
                  class="ym-staff-button-spinner"
                  aria-hidden="true"
                />
                {{
                  savingStaffPermissions
                    ? copy.savingPermissions
                    : copy.savePermissions
                }}
              </button>
            </footer>
          </form>
        </section>
      </div>
    </Teleport>

    <Teleport to="body">
      <div
        v-if="activityOpen && selectedStaff"
        class="ym-staff-drawer-backdrop ym-admin-page"
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
type PermissionsFilter = 'all' | 'direct' | 'inherited' | 'effective'

interface StaffUser {
  id: number
  name: string
  email: string
  roles: string[]
  created_at: string | null
}

interface StaffPermissionOption {
  name: string
  group: string
  label_ar: string
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

interface UpdateStaffResponse {
  success: boolean
  data: {
    user: StaffUser
  }
  message: string
  errors: Record<string, string[]> | null
}

interface SyncStaffRolesResponse {
  success: boolean
  data: {
    user: StaffUser
  }
  message: string
  errors: Record<string, string[]> | null
}

interface StaffPermissionsResponse {
  success: boolean
  data: {
    user: StaffUser
    permissions: {
      available: StaffPermissionOption[]
      direct: string[]
      inherited: string[]
      effective: string[]
    }
  }
  message: string
  errors: Record<string, string[]> | null
}

interface SyncStaffPermissionsResponse {
  success: boolean
  data: {
    user: StaffUser
    permissions: {
      direct: string[]
      inherited: string[]
      effective: string[]
    }
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
    editStaff: 'تعديل البيانات',
    manageRoles: 'إدارة الأدوار',
    managePermissions: 'إدارة الصلاحيات',
    permissionsEyebrow: 'التفويض المباشر',
    permissionsTitle: 'إدارة الصلاحيات المباشرة',
    permissionsDescription: 'حدّد ما يمكن منحه مباشرة لهذا الحساب، بينما تبقى الصلاحيات الموروثة للقراءة فقط.',
    permissionsLoading: 'يتم تحميل صلاحيات الموظف...',
    permissionsLoadError: 'تعذر تحميل صلاحيات الموظف.',
    permissionsSearch: 'البحث في الصلاحيات',
    permissionsSearchPlaceholder: 'ابحث عن صلاحية',
    permissionsFilter: 'نوع الصلاحية',
    permissionsFilterAll: 'الكل',
    permissionsFilterDirect: 'مباشرة',
    permissionsFilterInherited: 'موروثة',
    permissionsFilterEffective: 'فعالة',
    directPermission: 'مباشرة',
    inheritedPermission: 'موروثة',
    effectivePermission: 'فعالة',
    savePermissions: 'حفظ الصلاحيات',
    savingPermissions: 'جارٍ حفظ الصلاحيات...',
    permissionsSuccess: 'تم تحديث الصلاحيات المباشرة للموظف بنجاح.',
    permissionsError: 'تعذر تحديث صلاحيات الموظف. تحقق من الاختيارات وحاول مرة أخرى.',
    noPermissions: 'لا توجد صلاحيات مطابقة.',
    protectedPermissionsTitle: 'صلاحيات مباشرة محفوظة خارج نطاق إدارتك',
    protectedPermissionsDescription: 'سيحافظ النظام على هذه الصلاحيات، ولا يمكنك تعديلها من هذا الحساب.',
    permissionsLegendDirect: 'مباشرة',
    permissionsLegendInherited: 'موروثة',
    permissionsLegendProtected: 'محفوظة',
    manageablePermissions: 'الصلاحيات المتاحة للإدارة',
    protectedPermissionCount: 'المحفوظة',
    permissionCount: 'عدد الصلاحيات',
    availablePermissionCount: 'المتاح للإدارة',
    directPermissionCount: 'المباشرة',
    inheritedPermissionCount: 'الموروثة',
    rolesEyebrow: 'الوصول الداخلي',
    rolesTitle: 'إدارة أدوار الموظف',
    rolesDescription: 'اختر الأدوار الداخلية المرتبطة بالحساب. لا يمكن إسناد أدوار خارجية أو دور المدير الأعلى.',
    availableInternalRoles: 'الأدوار الداخلية المتاحة',
    rolesScope: 'يجب أن يبقى الحساب مرتبطًا بدور داخلي واحد على الأقل.',
    saveRoles: 'حفظ الأدوار',
    savingRoles: 'جارٍ حفظ الأدوار...',
    rolesSuccess: 'تم تحديث أدوار الموظف بنجاح.',
    rolesError: 'تعذر تحديث أدوار الموظف. تحقق من الاختيارات وحاول مرة أخرى.',
    rolesRequired: 'اختر دورًا داخليًا واحدًا على الأقل.',
    staffRoleDescription: 'وصول تشغيلي داخلي وفق الصلاحيات الممنوحة.',
    adminRoleDescription: 'وصول إداري داخلي وفق الصلاحيات الممنوحة.',
    editEyebrow: 'الملف الأساسي للحساب',
    editDescription: 'عدّل الاسم والبريد الإلكتروني فقط. تبقى الأدوار والصلاحيات وكلمة المرور خارج نطاق هذه العملية.',
    editScope: 'لن يؤدي الحفظ إلى تغيير الدور أو الصلاحيات أو كلمة المرور.',
    saveChanges: 'حفظ التغييرات',
    updating: 'جارٍ التحديث...',
    updateSuccess: 'تم تحديث بيانات الموظف بنجاح.',
    updateError: 'تعذر تحديث بيانات الموظف. راجع الحقول وحاول مرة أخرى.',
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
    delegatedState: 'عرض وإنشاء وتعديل وسجل منفصل',
    delegatedDescription: 'يستطيع الدور الداخلي تنفيذ العمليات التي مُنحت له فقط، مع فصل تعديل البيانات عن إدارة الوصول.',
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
    editStaff: 'Edit profile',
    manageRoles: 'Manage roles',
    managePermissions: 'Manage permissions',
    permissionsEyebrow: 'Direct delegation',
    permissionsTitle: 'Manage direct permissions',
    permissionsDescription: 'Choose what can be granted directly to this account. Inherited permissions remain read-only.',
    permissionsLoading: 'Loading staff permissions...',
    permissionsLoadError: 'Could not load staff permissions.',
    permissionsSearch: 'Search permissions',
    permissionsSearchPlaceholder: 'Search for a permission',
    permissionsFilter: 'Permission type',
    permissionsFilterAll: 'All',
    permissionsFilterDirect: 'Direct',
    permissionsFilterInherited: 'Inherited',
    permissionsFilterEffective: 'Effective',
    directPermission: 'Direct',
    inheritedPermission: 'Inherited',
    effectivePermission: 'Effective',
    savePermissions: 'Save permissions',
    savingPermissions: 'Saving permissions...',
    permissionsSuccess: 'Staff direct permissions updated successfully.',
    permissionsError: 'Could not update staff permissions. Review the selection and try again.',
    noPermissions: 'No matching permissions.',
    protectedPermissionsTitle: 'Protected direct permissions outside your management scope',
    protectedPermissionsDescription: 'These permissions will be preserved and cannot be modified by this account.',
    permissionsLegendDirect: 'Direct',
    permissionsLegendInherited: 'Inherited',
    permissionsLegendProtected: 'Protected',
    manageablePermissions: 'Manageable permissions',
    protectedPermissionCount: 'Protected',
    permissionCount: 'Permission count',
    availablePermissionCount: 'Manageable',
    directPermissionCount: 'Direct',
    inheritedPermissionCount: 'Inherited',
    rolesEyebrow: 'Internal access',
    rolesTitle: 'Manage staff roles',
    rolesDescription: 'Select the internal roles assigned to this account. External roles and the super-admin role cannot be assigned.',
    availableInternalRoles: 'Available internal roles',
    rolesScope: 'The account must retain at least one internal role.',
    saveRoles: 'Save roles',
    savingRoles: 'Saving roles...',
    rolesSuccess: 'Staff roles updated successfully.',
    rolesError: 'Could not update staff roles. Review the selection and try again.',
    rolesRequired: 'Select at least one internal role.',
    staffRoleDescription: 'Internal operational access through granted permissions.',
    adminRoleDescription: 'Internal administrative access through granted permissions.',
    editEyebrow: 'Core account profile',
    editDescription: 'Update name and email only. Roles, permissions, and password stay outside this action.',
    editScope: 'Saving will not change roles, permissions, or password.',
    saveChanges: 'Save changes',
    updating: 'Updating...',
    updateSuccess: 'Staff profile updated successfully.',
    updateError: 'Could not update the staff profile. Review the fields and try again.',
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
    delegatedState: 'Separate view, create, update, and activity',
    delegatedDescription: 'Internal roles can perform only explicitly granted actions, with profile editing separated from access management.',
    externalPolicy: 'External accounts',
    externalState: 'Blocked from administration',
    externalDescription: 'Client and designer accounts remain blocked even if an admin permission is accidentally granted.',
    pageInfo: (page: number, last: number, total: number) =>
      `Page ${page} of ${last} — ${total} records`
  }
}

const copy = computed(() => copyMap[currentLocale.value])

const staffPermissionGroupLabels: Record<Locale, Record<string, string>> = {
  ar: {
    'admin.access': 'الوصول الإداري',
    'admin.permissions': 'الصلاحيات',
    'admin.roles': 'الأدوار',
    'admin.staff': 'الموظفون',
    'admin.users': 'المستخدمون',
    'admin.works': 'الأعمال',
    'dashboard.overview': 'لوحة التحكم',
    'dashboard.stats': 'إحصاءات اللوحة',
    'dashboard.chart': 'الرسوم البيانية',
    'dashboard.activity': 'نشاط المنصة',
    orders: 'الطلبات',
    works: 'الأعمال',
    default: 'صلاحيات أخرى'
  },
  en: {
    'admin.access': 'Administrative access',
    'admin.permissions': 'Permissions',
    'admin.roles': 'Roles',
    'admin.staff': 'Staff',
    'admin.users': 'Users',
    'admin.works': 'Works',
    'dashboard.overview': 'Dashboard',
    'dashboard.stats': 'Dashboard statistics',
    'dashboard.chart': 'Charts',
    'dashboard.activity': 'Platform activity',
    orders: 'Orders',
    works: 'Works',
    default: 'Other permissions'
  }
}

function staffPermissionGroupLabel(group: string): string {
  const labels = staffPermissionGroupLabels[currentLocale.value]

  return labels[group]
    || (group.startsWith('admin.works') ? labels['admin.works'] : '')
    || (group.startsWith('works') ? labels.works : '')
    || labels.default
}

function staffPermissionLabel(permission: StaffPermissionOption): string {
  if (currentLocale.value === 'ar') return permission.label_ar

  return permission.name
    .split('.')
    .slice(-2)
    .join(' ')
    .replaceAll('_', ' ')
    .replace(/\b\w/g, letter => letter.toUpperCase())
}

const canViewStaff = computed(() => auth.can('admin.staff.view'))
const canCreateStaff = computed(() => auth.can('admin.staff.create'))
const canUpdateStaff = computed(() => auth.can('admin.staff.update'))
const canAssignStaffRoles = computed(() => auth.can('admin.staff.assign_roles'))
const canAssignStaffPermissions = computed(
  () => auth.can('admin.staff.assign_permissions')
)
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

const editModalOpen = ref(false)
const updatingStaff = ref(false)
const editingStaff = ref<StaffUser | null>(null)
const editError = ref<string | null>(null)
const editFieldErrors = ref<Record<string, string[]>>({})
const editDialog = ref<HTMLElement | null>(null)
const firstEditInput = ref<HTMLInputElement | null>(null)
const editTrigger = ref<HTMLElement | null>(null)

const editForm = reactive({
  name: '',
  email: ''
})

const roleModalOpen = ref(false)
const roleStaff = ref<StaffUser | null>(null)
const selectedStaffRoles = ref<string[]>([])
const savingStaffRoles = ref(false)
const roleError = ref<string | null>(null)
const roleFieldErrors = ref<Record<string, string[]>>({})
const roleDialog = ref<HTMLElement | null>(null)
const roleTrigger = ref<HTMLElement | null>(null)

const permissionsModalOpen = ref(false)
const permissionsStaff = ref<StaffUser | null>(null)
const permissionsLoading = ref(false)
const permissionsLoaded = ref(false)
const savingStaffPermissions = ref(false)
const permissionsError = ref<string | null>(null)
const permissionFieldErrors = ref<Record<string, string[]>>({})
const permissionsDialog = ref<HTMLElement | null>(null)
const permissionsTrigger = ref<HTMLElement | null>(null)
const availableStaffPermissions = ref<StaffPermissionOption[]>([])
const directStaffPermissions = ref<string[]>([])
const inheritedStaffPermissions = ref<string[]>([])
const effectiveStaffPermissions = ref<string[]>([])
const selectedDirectPermissions = ref<string[]>([])
const permissionsSearch = ref('')
const permissionsFilter = ref<PermissionsFilter>('all')

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

const protectedDirectPermissions = computed(() => {
  const availableNames = new Set(
    availableStaffPermissions.value.map(permission => permission.name)
  )

  return directStaffPermissions.value
    .filter(permission => !availableNames.has(permission))
    .sort()
})

const filteredPermissionGroups = computed(() => {
  const search = permissionsSearch.value.trim().toLocaleLowerCase()
  const groups = new Map<string, StaffPermissionOption[]>()

  availableStaffPermissions.value
    .filter((permission) => {
      const matchesSearch = search === ''
        || permission.name.toLocaleLowerCase().includes(search)
        || permission.group.toLocaleLowerCase().includes(search)
        || permission.label_ar.toLocaleLowerCase().includes(search)

      if (!matchesSearch) return false
      if (permissionsFilter.value === 'direct') {
        return selectedDirectPermissions.value.includes(permission.name)
      }
      if (permissionsFilter.value === 'inherited') {
        return inheritedStaffPermissions.value.includes(permission.name)
      }
      if (permissionsFilter.value === 'effective') {
        return effectiveStaffPermissions.value.includes(permission.name)
      }

      return true
    })
    .sort((left, right) => (
      left.group.localeCompare(right.group)
      || left.name.localeCompare(right.name)
    ))
    .forEach((permission) => {
      const group = groups.get(permission.group) ?? []
      group.push(permission)
      groups.set(permission.group, group)
    })

  return [...groups.entries()].map(([name, permissions]) => ({
    name,
    permissions
  }))
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

async function openEditStaffModal(user: StaffUser, event: MouseEvent): Promise<void> {
  if (!canUpdateStaff.value) return

  editTrigger.value = event.currentTarget as HTMLElement
  editingStaff.value = user
  editForm.name = user.name
  editForm.email = user.email
  editError.value = null
  editFieldErrors.value = {}
  successMessage.value = null
  editModalOpen.value = true

  await nextTick()
  editDialog.value?.focus()
  firstEditInput.value?.focus()
}

function closeEditStaffModal(): void {
  if (updatingStaff.value) return

  editModalOpen.value = false
  editingStaff.value = null
  editError.value = null
  editFieldErrors.value = {}
  nextTick(() => editTrigger.value?.focus())
}

function editFieldError(field: string): string {
  return editFieldErrors.value[field]?.[0] ?? ''
}

async function submitEditStaff(): Promise<void> {
  if (!canUpdateStaff.value || !editingStaff.value) return

  updatingStaff.value = true
  editError.value = null
  editFieldErrors.value = {}
  successMessage.value = null

  try {
    const response = await apiFetch<UpdateStaffResponse>(
      `/admin/staff/${editingStaff.value.id}`,
      {
        method: 'PATCH',
        body: {
          name: editForm.name,
          email: editForm.email
        }
      }
    )

    editModalOpen.value = false
    editingStaff.value = null
    editError.value = null
    editFieldErrors.value = {}
    successMessage.value = response.message || copy.value.updateSuccess
    await fetchStaff()
    nextTick(() => editTrigger.value?.focus())
  } catch (caughtError: unknown) {
    const err = caughtError as any
    editFieldErrors.value = err?.data?.errors ?? err?.response?._data?.errors ?? {}
    editError.value = err?.data?.message
      || err?.response?._data?.message
      || copy.value.updateError
  } finally {
    updatingStaff.value = false
  }
}

async function openRoleModal(user: StaffUser, event: MouseEvent): Promise<void> {
  if (!canAssignStaffRoles.value) return

  roleTrigger.value = event.currentTarget as HTMLElement
  roleStaff.value = user
  selectedStaffRoles.value = [...new Set(
    user.roles.filter(role => role === 'staff' || role === 'admin')
  )]
  roleError.value = null
  roleFieldErrors.value = {}
  successMessage.value = null
  roleModalOpen.value = true

  await nextTick()
  roleDialog.value?.focus()
}

function closeRoleModal(): void {
  if (savingStaffRoles.value) return

  roleModalOpen.value = false
  roleStaff.value = null
  selectedStaffRoles.value = []
  roleError.value = null
  roleFieldErrors.value = {}
  nextTick(() => roleTrigger.value?.focus())
}

function roleFieldError(field: string): string {
  return roleFieldErrors.value[field]?.[0] ?? ''
}

async function submitStaffRoles(): Promise<void> {
  if (!canAssignStaffRoles.value || !roleStaff.value) return

  const roles = [...new Set(
    selectedStaffRoles.value.filter(role => role === 'staff' || role === 'admin')
  )].sort()

  roleError.value = null
  roleFieldErrors.value = {}
  successMessage.value = null

  if (roles.length === 0) {
    roleError.value = copy.value.rolesRequired
    roleFieldErrors.value = { roles: [copy.value.rolesRequired] }
    return
  }

  savingStaffRoles.value = true

  try {
    const response = await apiFetch<SyncStaffRolesResponse>(
      `/admin/staff/${roleStaff.value.id}/roles`,
      {
        method: 'PUT',
        body: { roles }
      }
    )

    savingStaffRoles.value = false
    closeRoleModal()
    successMessage.value = response.message || copy.value.rolesSuccess
    await fetchStaff()
  } catch (caughtError: unknown) {
    const err = caughtError as any
    roleFieldErrors.value = err?.data?.errors ?? err?.response?._data?.errors ?? {}
    roleError.value = err?.data?.message
      || err?.response?._data?.message
      || copy.value.rolesError
  } finally {
    savingStaffRoles.value = false
  }
}

function resetStaffPermissionsState(): void {
  availableStaffPermissions.value = []
  directStaffPermissions.value = []
  inheritedStaffPermissions.value = []
  effectiveStaffPermissions.value = []
  selectedDirectPermissions.value = []
  permissionsSearch.value = ''
  permissionsFilter.value = 'all'
  permissionsLoaded.value = false
  permissionsError.value = null
  permissionFieldErrors.value = {}
}

async function openPermissionsModal(user: StaffUser, event: MouseEvent): Promise<void> {
  if (!canAssignStaffPermissions.value) return

  permissionsTrigger.value = event.currentTarget as HTMLElement
  permissionsStaff.value = user
  resetStaffPermissionsState()
  successMessage.value = null
  permissionsModalOpen.value = true

  await nextTick()
  permissionsDialog.value?.focus()
  await loadStaffPermissions()
}

function closePermissionsModal(): void {
  if (savingStaffPermissions.value) return

  permissionsModalOpen.value = false
  permissionsStaff.value = null
  permissionsLoading.value = false
  resetStaffPermissionsState()
  nextTick(() => permissionsTrigger.value?.focus())
}

function permissionFieldError(field: string): string {
  return permissionFieldErrors.value[field]?.[0] ?? ''
}

async function loadStaffPermissions(): Promise<void> {
  if (!canAssignStaffPermissions.value || !permissionsStaff.value) return

  permissionsLoading.value = true
  permissionsError.value = null
  permissionFieldErrors.value = {}
  availableStaffPermissions.value = []
  directStaffPermissions.value = []
  inheritedStaffPermissions.value = []
  effectiveStaffPermissions.value = []
  selectedDirectPermissions.value = []

  try {
    const response = await apiFetch<StaffPermissionsResponse>(
      `/admin/staff/${permissionsStaff.value.id}/permissions`
    )
    const permissions = response.data.permissions
    const availableNames = new Set(
      permissions.available.map(permission => permission.name)
    )

    availableStaffPermissions.value = [...permissions.available].sort((left, right) => (
      left.group.localeCompare(right.group)
      || left.name.localeCompare(right.name)
    ))
    directStaffPermissions.value = [...new Set(permissions.direct)].sort()
    inheritedStaffPermissions.value = [...new Set(permissions.inherited)].sort()
    effectiveStaffPermissions.value = [...new Set(permissions.effective)].sort()
    selectedDirectPermissions.value = directStaffPermissions.value
      .filter(permission => availableNames.has(permission))
      .sort()
    permissionsLoaded.value = true
  } catch (caughtError: unknown) {
    const err = caughtError as any
    permissionsError.value = err?.data?.message
      || err?.response?._data?.message
      || copy.value.permissionsLoadError
  } finally {
    permissionsLoading.value = false
  }
}

async function submitStaffPermissions(): Promise<void> {
  if (!canAssignStaffPermissions.value || !permissionsStaff.value) return

  const availableNames = new Set(
    availableStaffPermissions.value.map(permission => permission.name)
  )
  const permissions = [...new Set(
    selectedDirectPermissions.value.filter(permission => availableNames.has(permission))
  )].sort()

  savingStaffPermissions.value = true
  permissionsError.value = null
  permissionFieldErrors.value = {}
  successMessage.value = null

  try {
    const response = await apiFetch<SyncStaffPermissionsResponse>(
      `/admin/staff/${permissionsStaff.value.id}/permissions`,
      {
        method: 'PUT',
        body: { permissions }
      }
    )

    savingStaffPermissions.value = false
    closePermissionsModal()
    successMessage.value = response.message || copy.value.permissionsSuccess
    await fetchStaff()
    await nextTick()
    permissionsTrigger.value?.focus()
  } catch (caughtError: unknown) {
    const err = caughtError as any
    permissionFieldErrors.value = err?.data?.errors
      ?? err?.response?._data?.errors
      ?? {}
    permissionsError.value = err?.data?.message
      || err?.response?._data?.message
      || copy.value.permissionsError
  } finally {
    savingStaffPermissions.value = false
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
    'staff.updated': {
      ar: 'تم تحديث بيانات الحساب الأساسية',
      en: 'Core account profile updated'
    },
    'staff.roles.synced': {
      ar: 'تم تحديث أدوار الحساب الداخلي',
      en: 'Internal account roles updated'
    },
    'staff.permissions.synced': {
      ar: 'تم تحديث الصلاحيات المباشرة للحساب',
      en: 'Direct account permissions updated'
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

  if (editModalOpen.value) {
    closeEditStaffModal()
    return
  }

  if (roleModalOpen.value) {
    closeRoleModal()
    return
  }

  if (permissionsModalOpen.value) {
    closePermissionsModal()
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

.ym-staff-row-actions {
  display: grid;
  justify-items: center;
  gap: 6px;
}

.ym-staff-row-action {
  min-height: 34px;
  border-color: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 28%, var(--ym-admin-border));
  color: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 76%, var(--ym-admin-text));
  white-space: nowrap;
}

.ym-staff-row-action.is-edit {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 34%, var(--ym-admin-border));
  color: color-mix(in srgb, var(--ym-admin-section-accent) 80%, var(--ym-admin-text));
}

.ym-staff-row-action.is-roles {
  border-color: color-mix(in srgb, #8b5cf6 38%, var(--ym-admin-border));
  color: color-mix(in srgb, #8b5cf6 82%, var(--ym-admin-text));
}

.ym-staff-row-action.is-permissions {
  border-color: color-mix(in srgb, #06b6d4 42%, var(--ym-admin-border));
  color: color-mix(in srgb, #0891b2 84%, var(--ym-admin-text));
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

.ym-staff-edit-scope {
  margin: 0;
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 22%, var(--ym-admin-border));
  border-radius: 12px;
  padding: 10px 12px;
  background: color-mix(in srgb, var(--ym-admin-section-accent) 6%, var(--ym-admin-surface-soft));
  color: var(--ym-admin-muted);
  font-size: 12px;
  font-weight: 800;
  line-height: 1.65;
}

.ym-staff-role-modal-user {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 4px 14px;
  border: 1px solid color-mix(in srgb, var(--ym-admin-section-accent) 24%, var(--ym-admin-border));
  border-radius: 14px;
  padding: 12px 14px;
  background: color-mix(in srgb, var(--ym-admin-section-accent) 6%, var(--ym-admin-surface-soft));
}

.ym-staff-role-modal-user strong,
.ym-staff-role-modal-user span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-role-modal-user span,
.ym-staff-role-modal-user small {
  color: var(--ym-admin-muted);
  font-size: 12px;
}

.ym-staff-role-modal-user small {
  grid-column: 2;
  grid-row: 1;
  font-weight: 900;
}

.ym-staff-role-fieldset {
  min-width: 0;
  margin: 0;
  border: 0;
  padding: 0;
}

.ym-staff-role-fieldset legend {
  margin-bottom: 9px;
  color: var(--ym-admin-text);
  font-size: 12px;
  font-weight: 900;
}

.ym-staff-role-options {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.ym-staff-role-option {
  display: flex;
  min-width: 0;
  align-items: flex-start;
  gap: 11px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 14px;
  padding: 13px;
  background: var(--ym-admin-surface-soft);
  cursor: pointer;
  transition: border-color .16s ease, background .16s ease, transform .16s ease;
}

.ym-staff-role-option:hover {
  transform: translateY(-1px);
}

.ym-staff-role-option.is-staff.is-selected {
  border-color: color-mix(in srgb, #06b6d4 55%, var(--ym-admin-border));
  background: color-mix(in srgb, #06b6d4 9%, var(--ym-admin-surface-soft));
}

.ym-staff-role-option.is-admin.is-selected {
  border-color: color-mix(in srgb, #8b5cf6 55%, var(--ym-admin-border));
  background: color-mix(in srgb, #8b5cf6 9%, var(--ym-admin-surface-soft));
}

.ym-staff-role-option__check {
  position: relative;
  display: grid;
  width: 23px;
  height: 23px;
  flex: 0 0 23px;
  place-items: center;
  border: 1px solid var(--ym-admin-border);
  border-radius: 7px;
  background: var(--ym-admin-control-bg);
  color: #fff;
  font-size: 13px;
  font-weight: 950;
}

.ym-staff-role-option__check input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}

.ym-staff-role-option.is-selected .ym-staff-role-option__check {
  border-color: transparent;
  background: #8b5cf6;
}

.ym-staff-role-option.is-staff.is-selected .ym-staff-role-option__check {
  background: #0891b2;
}

.ym-staff-role-option__content {
  display: grid;
  min-width: 0;
  gap: 4px;
}

.ym-staff-role-option__content strong {
  color: var(--ym-admin-text);
  font-size: 13px;
}

.ym-staff-role-option__content small {
  color: var(--ym-admin-muted);
  font-size: 11.5px;
  line-height: 1.55;
}

.ym-staff-role-field-error {
  margin-top: -6px;
  color: var(--ym-admin-danger, #ef4444);
  font-size: 12px;
  font-weight: 800;
}

.ym-staff-permissions-dialog {
  display: flex;
  width: min(100%, 1020px);
  height: min(88dvh, 860px);
  max-height: 88dvh;
  flex-direction: column;
  overflow: hidden;
  padding: 0;
}

.ym-staff-permissions-dialog > header {
  position: relative;
  z-index: 2;
  flex: 0 0 auto;
  border-bottom: 1px solid var(--ym-admin-border);
  padding: 16px 18px 14px;
  background: color-mix(in srgb, var(--ym-admin-surface) 96%, transparent);
}

.ym-staff-permissions-dialog > header p {
  max-width: 680px;
  margin-top: 4px;
  line-height: 1.45;
}

.ym-staff-permissions-form {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
  overflow: hidden;
}

.ym-staff-permissions-body {
  display: flex;
  min-height: 0;
  flex: 1 1 auto;
  flex-direction: column;
  gap: 11px;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding: 14px 18px 18px;
  scrollbar-color: color-mix(in srgb, var(--ym-admin-section-accent) 62%, #94a3b8) var(--ym-admin-surface-soft);
  scrollbar-width: thin;
}

.ym-staff-permissions-body::-webkit-scrollbar {
  width: 10px;
}

.ym-staff-permissions-body::-webkit-scrollbar-track {
  border-radius: 999px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-permissions-body::-webkit-scrollbar-thumb {
  border: 2px solid var(--ym-admin-surface-soft);
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-admin-section-accent) 62%, #94a3b8);
}

.ym-staff-permissions-form > footer {
  position: relative;
  z-index: 2;
  display: flex;
  flex: 0 0 auto;
  justify-content: flex-end;
  gap: 8px;
  border-top: 1px solid var(--ym-admin-border);
  padding: 12px 18px;
  background: var(--ym-admin-surface);
}

.ym-staff-permissions-error {
  display: grid;
  align-content: center;
  justify-items: center;
  gap: 10px;
}

.ym-staff-permissions-user {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 11px;
  padding: 9px 11px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-permissions-user > div {
  display: grid;
  min-width: 0;
  gap: 3px;
}

.ym-staff-permissions-user strong,
.ym-staff-permissions-user span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-permissions-user span,
.ym-staff-permissions-user small {
  color: var(--ym-admin-muted);
  font-size: 11px;
}

.ym-staff-permissions-user__meta {
  align-items: end;
  justify-items: end;
}

.ym-staff-permissions-user__meta > span {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 4px;
}

.ym-staff-permissions-user__role {
  border: 1px solid var(--ym-admin-border);
  border-radius: 999px;
  padding: 2px 6px;
  background: var(--ym-admin-surface);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-weight: 800;
}

.ym-staff-permissions-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.ym-staff-permissions-summary > span {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 999px;
  padding: 5px 9px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-permissions-summary > span.is-protected {
  border-color: color-mix(in srgb, #f59e0b 34%, var(--ym-admin-border));
  background: color-mix(in srgb, #f59e0b 7%, var(--ym-admin-surface-soft));
}

.ym-staff-permissions-summary small {
  color: var(--ym-admin-muted);
  font-size: 10.5px;
  font-weight: 800;
}

.ym-staff-permissions-summary strong {
  color: var(--ym-admin-text);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
}

.ym-staff-permissions-toolbar {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(170px, 1fr);
  gap: 8px;
  border: 1px solid var(--ym-admin-border);
  border-radius: 12px;
  padding: 9px;
  background: var(--ym-admin-surface-soft);
}

.ym-staff-permissions-toolbar .ym-staff-field {
  gap: 4px;
}

.ym-staff-permissions-toolbar .ym-staff-field > span {
  font-size: 10.5px;
}

.ym-staff-permissions-toolbar input,
.ym-staff-permissions-toolbar select {
  min-height: 37px;
}

.ym-staff-permissions-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.ym-staff-permissions-legend > span {
  border-radius: 999px;
  padding: 3px 7px;
  background: var(--ym-admin-surface-soft);
  color: var(--ym-admin-muted);
  font-size: 9.5px;
  font-weight: 850;
}

.ym-staff-permissions-legend .is-direct {
  color: #0891b2;
}

.ym-staff-permissions-legend .is-inherited {
  color: #7c3aed;
}

.ym-staff-permissions-legend .is-protected {
  color: #d97706;
}

.ym-staff-permissions-catalog {
  display: grid;
  gap: 8px;
}

.ym-staff-permissions-catalog > header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.ym-staff-permissions-catalog > header strong {
  color: var(--ym-admin-text);
  font-size: 12.5px;
}

.ym-staff-permissions-catalog > header small {
  min-width: 24px;
  border-radius: 999px;
  padding: 3px 7px;
  background: var(--ym-admin-surface-soft);
  color: var(--ym-admin-muted);
  text-align: center;
  font-size: 10px;
  font-weight: 900;
}

.ym-staff-permission-groups {
  display: grid;
  gap: 7px;
}

.ym-staff-permission-group {
  overflow: hidden;
  border: 1px solid var(--ym-admin-border);
  border-radius: 10px;
  background: var(--ym-admin-surface);
}

.ym-staff-permission-group > summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 8px 10px;
  background: var(--ym-admin-surface-soft);
  cursor: pointer;
  list-style: none;
}

.ym-staff-permission-group > summary::-webkit-details-marker {
  display: none;
}

.ym-staff-permission-group > summary::before {
  content: '▾';
  color: var(--ym-admin-muted);
  font-size: 10px;
  transition: transform .16s ease;
}

.ym-staff-permission-group:not([open]) > summary::before {
  transform: rotate(-90deg);
}

.ym-staff-permission-group > summary strong {
  flex: 1;
  color: var(--ym-admin-text);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}

.ym-staff-permission-group > summary small {
  min-width: 22px;
  border-radius: 999px;
  padding: 2px 6px;
  background: var(--ym-admin-surface);
  color: var(--ym-admin-muted);
  text-align: center;
  font-size: 9.5px;
  font-weight: 900;
}

.ym-staff-permission-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  border-top: 1px solid var(--ym-admin-border);
}

.ym-staff-permission-row {
  display: grid;
  min-width: 0;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 5px 9px;
  border-bottom: 1px solid var(--ym-admin-border);
  padding: 7px 9px;
  background: var(--ym-admin-surface);
  cursor: pointer;
}

.ym-staff-permission-row:nth-child(odd) {
  border-inline-end: 1px solid var(--ym-admin-border);
}

.ym-staff-permission-row.is-selected {
  background: color-mix(in srgb, var(--ym-admin-section-accent) 5%, var(--ym-admin-surface));
}

.ym-staff-permission-row > input {
  width: 15px;
  height: 15px;
  accent-color: var(--ym-admin-section-accent);
}

.ym-staff-permission-row__meta {
  display: grid;
  min-width: 0;
  gap: 1px;
}

.ym-staff-permission-row__meta strong {
  overflow: hidden;
  color: var(--ym-admin-text);
  font-size: 11.5px;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-permission-row__meta code,
.ym-staff-protected-permissions code {
  overflow-wrap: anywhere;
  color: color-mix(in srgb, var(--ym-admin-section-accent-secondary) 82%, var(--ym-admin-text));
  font-size: 9.5px;
}

.ym-staff-permission-row__meta small {
  color: var(--ym-admin-muted);
  font-size: 9px;
}

.ym-staff-permission-row__badges {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 5px;
}

.ym-staff-permission-row__badges small {
  border: 1px solid var(--ym-admin-border);
  border-radius: 999px;
  padding: 1px 5px;
  color: var(--ym-admin-muted);
  font-size: 8.5px;
  font-weight: 900;
}

.ym-staff-permission-row__badges .is-direct {
  border-color: color-mix(in srgb, #06b6d4 38%, var(--ym-admin-border));
  color: #0891b2;
}

.ym-staff-permission-row__badges .is-inherited {
  border-color: color-mix(in srgb, #8b5cf6 38%, var(--ym-admin-border));
  color: #7c3aed;
}

.ym-staff-permission-row__badges .is-effective {
  border-color: color-mix(in srgb, #10b981 38%, var(--ym-admin-border));
  color: #059669;
}

.ym-staff-permissions-empty {
  margin: 0;
  border: 1px dashed var(--ym-admin-border);
  border-radius: 13px;
  padding: 22px;
  color: var(--ym-admin-muted);
  text-align: center;
  font-size: 12px;
  font-weight: 800;
}

.ym-staff-protected-permissions {
  border: 1px solid color-mix(in srgb, #f59e0b 34%, var(--ym-admin-border));
  border-radius: 10px;
  padding: 9px 10px;
  background: color-mix(in srgb, #f59e0b 7%, var(--ym-admin-surface-soft));
}

.ym-staff-protected-permissions header strong {
  color: var(--ym-admin-text);
  font-size: 11.5px;
}

.ym-staff-protected-permissions header p {
  margin: 2px 0 0;
  color: var(--ym-admin-muted);
  font-size: 10.5px;
  line-height: 1.45;
}

.ym-staff-protected-permissions ul {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin: 7px 0 0;
  padding: 0;
  list-style: none;
}

.ym-staff-protected-permissions li {
  max-width: 100%;
  border: 1px solid var(--ym-admin-border);
  border-radius: 7px;
  padding: 3px 6px;
  background: var(--ym-admin-surface);
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
  .ym-staff-role-options,
  .ym-staff-permissions-toolbar,
  .ym-staff-activity-summary {
    grid-template-columns: 1fr;
  }

  .ym-staff-permission-list {
    grid-template-columns: 1fr;
  }

  .ym-staff-permission-row:nth-child(odd) {
    border-inline-end: 0;
  }

  .ym-staff-permission-row {
    grid-template-columns: auto minmax(0, 1fr);
  }

  .ym-staff-permission-row__badges {
    grid-column: 2;
    justify-content: flex-start;
  }

  .ym-staff-filter-actions,
  .ym-staff-pagination > div {
    justify-content: space-between;
  }

  .ym-staff-dialog {
    padding: 14px;
  }

  .ym-staff-permissions-dialog {
    height: 88dvh;
    padding: 0;
  }

  .ym-staff-permissions-dialog > header,
  .ym-staff-permissions-form > footer {
    padding-inline: 13px;
  }

  .ym-staff-permissions-body {
    padding-inline: 13px;
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

/* YM-STAFF-001B visual verification patch */
.ym-staff-table {
  min-width: 920px;
  table-layout: fixed;
}

.ym-staff-table th,
.ym-staff-table td {
  vertical-align: middle;
}

.ym-staff-table .is-id {
  width: 6%;
}

.ym-staff-table .is-name {
  width: 18%;
}

.ym-staff-table .is-email {
  width: 28%;
}

.ym-staff-table .is-roles {
  width: 14%;
}

.ym-staff-table .is-date {
  width: 18%;
}

.ym-staff-table .is-actions {
  width: 16%;
}

.ym-staff-table th.is-id,
.ym-staff-table td.is-id,
.ym-staff-table th.is-email,
.ym-staff-table td.is-email,
.ym-staff-table th.is-roles,
.ym-staff-table td.is-roles,
.ym-staff-table th.is-date,
.ym-staff-table td.is-date,
.ym-staff-table th.is-actions,
.ym-staff-table td.is-actions {
  text-align: center;
}

.ym-staff-table th.is-name,
.ym-staff-table td.is-name {
  text-align: start;
}

.ym-staff-table td.is-name strong {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ym-staff-table td.is-email {
  direction: ltr;
}

.ym-staff-table .is-roles .ym-staff-role-list,
.ym-staff-table .is-actions .ym-staff-row-action {
  justify-content: center;
}

.ym-staff-dialog-backdrop,
.ym-staff-drawer-backdrop {
  --ym-admin-text: #0f172a;
  --ym-admin-muted: #64748b;
  --ym-admin-border: rgba(100, 116, 139, .24);
  --ym-admin-surface: #ffffff;
  --ym-admin-surface-soft: #f8fafc;
  --ym-admin-control-bg: #ffffff;
  --ym-admin-danger: #ef4444;
  isolation: isolate;
  background: rgba(2, 6, 23, .58);
  backdrop-filter: blur(8px) saturate(115%);
}

.ym-staff-dialog,
.ym-staff-activity-drawer {
  background:
    radial-gradient(circle at 90% 0%, rgba(6, 182, 212, .13), transparent 260px),
    radial-gradient(circle at 8% 100%, rgba(139, 92, 246, .08), transparent 280px),
    #ffffff;
  box-shadow:
    0 34px 100px rgba(2, 6, 23, .5),
    inset 0 1px 0 rgba(255, 255, 255, .9);
}

.ym-staff-drawer-backdrop {
  align-items: center;
  justify-items: end;
  padding: 12px;
}

.ym-staff-drawer-backdrop.is-ltr {
  justify-items: start;
}

.ym-staff-activity-drawer {
  display: grid;
  width: min(calc(100% - 24px), 680px);
  height: calc(100dvh - 24px);
  min-height: 0;
  grid-template-rows: auto auto minmax(0, 1fr) auto;
  overflow: hidden;
  border-radius: 24px;
  padding: 0;
}

.ym-staff-activity-drawer > header {
  position: relative;
  z-index: 2;
  border-bottom: 1px solid var(--ym-admin-border);
  padding: 20px;
  background:
    linear-gradient(
      135deg,
      rgba(6, 182, 212, .09),
      rgba(139, 92, 246, .06)
    ),
    #ffffff;
  box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
}

.ym-staff-activity-drawer > header .ym-staff-icon-button {
  flex: 0 0 auto;
  border-radius: 999px;
  background: #f8fafc;
}

.ym-staff-activity-summary {
  margin: 14px 18px 0;
}

.ym-staff-timeline {
  min-height: 0;
  margin: 14px 18px 0;
  overflow-y: auto;
  padding: 0 4px 12px;
  scrollbar-gutter: stable;
}

.ym-staff-activity-drawer > .ym-staff-loading,
.ym-staff-activity-drawer :deep(.ym-admin-empty) {
  min-height: 0;
  margin: 14px 18px 18px;
  overflow-y: auto;
  border: 1px solid var(--ym-admin-border);
  border-radius: 18px;
  background:
    radial-gradient(circle at 50% 20%, rgba(6, 182, 212, .07), transparent 190px),
    #f8fafc;
}

.ym-staff-activity-drawer :deep(.ym-admin-empty) {
  align-content: center;
  padding: 28px 20px;
}

.ym-staff-activity-drawer > .ym-staff-pagination {
  margin: 0;
  border-top: 1px solid var(--ym-admin-border);
  padding: 12px 18px 16px;
  background: #ffffff;
}

/* Final compact permissions presentation */
.ym-staff-dialog.ym-staff-permissions-dialog {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 24%, var(--ym-admin-border));
  background:
    radial-gradient(circle at 88% -8%, rgba(6, 182, 212, .12), transparent 300px),
    radial-gradient(circle at 4% 105%, rgba(139, 92, 246, .07), transparent 320px),
    rgba(255, 255, 255, .98);
  box-shadow:
    0 34px 100px rgba(2, 6, 23, .42),
    0 0 0 1px rgba(255, 255, 255, .7) inset;
}

.ym-staff-permissions-dialog > header {
  position: relative;
  background: rgba(255, 255, 255, .86);
  backdrop-filter: blur(16px);
}

.ym-staff-permissions-dialog > header::after {
  position: absolute;
  inset-inline: 18px;
  bottom: -1px;
  height: 2px;
  border-radius: 999px;
  background: linear-gradient(90deg, #06b6d4, #8b5cf6, transparent 82%);
  content: '';
  opacity: .7;
}

.ym-staff-permissions-dialog > header h2 {
  font-size: clamp(19px, 2.2vw, 24px);
  letter-spacing: -.025em;
  text-shadow: 0 8px 26px rgba(6, 182, 212, .12);
}

.ym-staff-permissions-dialog > header p {
  font-size: 12px;
}

.ym-staff-permissions-dialog .ym-staff-icon-button {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 18%, var(--ym-admin-border));
  background: rgba(248, 250, 252, .86);
}

.ym-staff-permissions-dialog .ym-staff-icon-button:hover:not(:disabled) {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 46%, var(--ym-admin-border));
  background: #ffffff;
  transform: rotate(3deg);
}

.ym-staff-permissions-user {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 18%, var(--ym-admin-border));
  background:
    linear-gradient(135deg, rgba(6, 182, 212, .055), rgba(139, 92, 246, .035)),
    #ffffff;
  box-shadow: 0 8px 22px rgba(15, 23, 42, .035);
}

.ym-staff-permissions-user strong {
  color: var(--ym-admin-text);
  font-size: 13.5px;
  font-weight: 950;
}

.ym-staff-permissions-summary > span {
  box-shadow: 0 3px 10px rgba(15, 23, 42, .025);
}

.ym-staff-permissions-summary > span:first-child {
  border-color: color-mix(in srgb, #06b6d4 25%, var(--ym-admin-border));
}

.ym-staff-permissions-summary strong {
  min-width: 18px;
  border-radius: 999px;
  padding: 1px 5px;
  background: #ffffff;
  text-align: center;
  font-size: 12.5px;
}

.ym-staff-permissions-toolbar {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 16%, var(--ym-admin-border));
  background: rgba(248, 250, 252, .9);
  box-shadow: 0 8px 24px rgba(15, 23, 42, .03);
}

.ym-staff-permissions-toolbar .ym-staff-permissions-legend {
  grid-column: 1 / -1;
  border-top: 1px solid var(--ym-admin-border);
  padding-top: 7px;
}

.ym-staff-permissions-legend > span {
  position: relative;
  padding-inline-start: 17px;
  background: transparent;
  font-size: 10px;
}

.ym-staff-permissions-legend > span::before {
  position: absolute;
  inset-inline-start: 5px;
  top: 50%;
  width: 6px;
  height: 6px;
  border-radius: 999px;
  background: currentColor;
  content: '';
  transform: translateY(-50%);
}

.ym-staff-permissions-catalog > header {
  padding-inline: 2px;
}

.ym-staff-permissions-catalog > header strong {
  font-size: 14px;
  font-weight: 950;
}

.ym-staff-permission-group {
  border-color: color-mix(in srgb, #64748b 18%, var(--ym-admin-border));
  border-radius: 13px;
  background: rgba(255, 255, 255, .92);
  box-shadow: 0 7px 20px rgba(15, 23, 42, .035);
}

.ym-staff-permission-group > summary {
  min-height: 39px;
  padding: 8px 11px;
  background:
    linear-gradient(90deg, rgba(6, 182, 212, .055), rgba(139, 92, 246, .025)),
    #f8fafc;
}

.ym-staff-permission-group > summary strong {
  font-family: inherit;
  font-size: 13px;
  font-weight: 950;
}

.ym-staff-permission-list {
  gap: 7px;
  border-top-color: color-mix(in srgb, var(--ym-admin-section-accent) 13%, var(--ym-admin-border));
  padding: 8px;
  background: rgba(248, 250, 252, .62);
}

.ym-staff-permission-row {
  min-height: 56px;
  border: 1px solid rgba(148, 163, 184, .2);
  border-radius: 10px;
  padding: 8px 10px;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 3px 10px rgba(15, 23, 42, .025);
  transition:
    transform .16s ease,
    border-color .16s ease,
    background .16s ease,
    box-shadow .16s ease;
}

.ym-staff-permission-row:nth-child(odd) {
  border-inline-end: 1px solid rgba(148, 163, 184, .2);
}

.ym-staff-permission-row:hover {
  z-index: 1;
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 36%, var(--ym-admin-border));
  box-shadow: 0 8px 20px rgba(6, 182, 212, .07);
  transform: translateY(-1px);
}

.ym-staff-permission-row.is-selected {
  border-color: color-mix(in srgb, var(--ym-admin-section-accent) 48%, var(--ym-admin-border));
  background:
    linear-gradient(135deg, rgba(6, 182, 212, .085), rgba(139, 92, 246, .035)),
    #ffffff;
  box-shadow: 0 8px 20px rgba(6, 182, 212, .075);
}

.ym-staff-permission-row > input {
  width: 18px;
  height: 18px;
}

.ym-staff-permission-row__meta strong {
  color: #172033;
  font-size: 13px;
  font-weight: 900;
  line-height: 1.45;
  white-space: normal;
}

.ym-staff-permission-row__badges small {
  padding: 2px 6px;
  background: #ffffff;
  font-size: 9px;
}

.ym-staff-protected-permissions {
  box-shadow: 0 6px 18px rgba(245, 158, 11, .045);
}

.ym-staff-permissions-form > footer {
  background: rgba(255, 255, 255, .9);
  backdrop-filter: blur(16px);
  box-shadow: 0 -10px 28px rgba(15, 23, 42, .035);
}

.ym-staff-permissions-footer .ym-staff-primary-button {
  min-width: 142px;
  box-shadow: 0 10px 24px rgba(6, 182, 212, .2);
}

@media (max-width: 700px) {
  .ym-staff-drawer-backdrop {
    padding: 0;
  }

  .ym-staff-activity-drawer {
    width: 100%;
    height: 100dvh;
    border-radius: 0;
  }

  .ym-staff-activity-drawer > header {
    padding: 16px;
  }

  .ym-staff-activity-summary,
  .ym-staff-timeline,
  .ym-staff-activity-drawer > .ym-staff-loading,
  .ym-staff-activity-drawer :deep(.ym-admin-empty) {
    margin-inline: 14px;
  }
}

</style>
