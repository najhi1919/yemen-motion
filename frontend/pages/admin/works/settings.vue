<template>
  <div class="ym-works-settings-page ym-admin-page space-y-5" data-admin-accent="settings" :dir="pageDirection">
    <AdminPageHero
      class="ym-works-settings-approved-hero"
      :breadcrumbs="['إدارة الأعمال', 'إعدادات وصلاحيات الأعمال']"
      breadcrumb-label="مسار إعدادات الأعمال"
      eyebrow="حوكمة إدارة الأعمال"
      badge="إدارة حسب الصلاحية"
      title="إعدادات وصلاحيات الأعمال"
      description="إدارة إعدادات الأعمال ضمن مساحات واضحة للإعدادات التشغيلية وسير العمل والوصول وكتالوج الصلاحيات."
    >
      <template #icon>
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <circle cx="12" cy="12" r="3" />
          <path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.12 2.12-.06-.06a1.7 1.7 0 0 0-1.88-.34 1.7 1.7 0 0 0-1 1.55V20h-3v-.09a1.7 1.7 0 0 0-1-1.55 1.7 1.7 0 0 0-1.88.34l-.06.06-2.12-2.12.06-.06A1.7 1.7 0 0 0 7 14.7a1.7 1.7 0 0 0-1.55-1H5v-3h.09a1.7 1.7 0 0 0 1.55-1A1.7 1.7 0 0 0 6.3 7.8l-.06-.06 2.12-2.12.06.06A1.7 1.7 0 0 0 10.3 6a1.7 1.7 0 0 0 1-1.55V4h3v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.88-.34l.06-.06 2.12 2.12-.06.06A1.7 1.7 0 0 0 19 9.3a1.7 1.7 0 0 0 1.55 1H21v3h-.09a1.7 1.7 0 0 0-1.51 1.7z" />
        </svg>
      </template>
      <template #actions>
        <div class="ym-works-settings-hero__summary">
          <span>الصلاحيات المسجلة</span>
          <strong>{{ formatNumber(data?.permission_registry.total_permissions ?? 0) }}</strong>
          <small>ضمن مجموعة إدارة الأعمال</small>
        </div>
      </template>
    </AdminPageHero>

    <section v-if="authPending" class="ym-works-settings-access-state" role="status" aria-live="polite">
      <span class="ym-works-settings-spinner" aria-hidden="true" />
      <h2>جارٍ التحقق من صلاحية إعدادات الأعمال</h2>
      <p>ننتظر اكتمال تهيئة جلسة المستخدم قبل إرسال أي طلب بيانات.</p>
    </section>

    <section v-else-if="forbidden" class="ym-works-settings-access-state is-forbidden" role="status">
      <span class="ym-works-settings-state__icon" aria-hidden="true">!</span>
      <h2>الوصول إلى إعدادات الأعمال غير متاح</h2>
      <p>لا يملك هذا الحساب الصلاحيات المطلوبة. لم تتم محاولة تحميل بيانات الصفحة.</p>
    </section>

    <template v-else>
      <nav class="ym-works-settings-local-nav" aria-label="أقسام إعدادات الأعمال">
        <a href="#settings-operations"><span aria-hidden="true">⚙</span>الإعدادات التشغيلية</a>
        <a href="#settings-workflow"><span aria-hidden="true">◇</span>سير العمل</a>
        <a href="#settings-access"><span aria-hidden="true">◉</span>الوصول الحالي</a>
        <a href="#settings-permissions"><span aria-hidden="true">▦</span>كتالوج الصلاحيات</a>
      </nav>

      <section class="ym-works-settings-notices" aria-label="سياق إعدادات الأعمال">
        <aside class="ym-works-settings-notice" role="note">
          <span>تخزين دائم</span>
          <p>يمكن حفظ مهلة المراجعة وثقة النشر المباشر وحدود الوسائط حسب صلاحيات الحساب.</p>
        </aside>
        <aside class="ym-works-settings-notice is-restriction" role="note">
          <span>تكامل مرحلي</span>
          <p>الإعدادات والوسائط وواجهة التأليف مطبقة، بينما يظل الإرسال للمراجعة ضمن المرحلة اللاحقة.</p>
        </aside>
      </section>

      <section v-if="loading" class="ym-works-settings-result-card ym-works-settings-state" role="status" aria-live="polite">
        <span class="ym-works-settings-spinner" aria-hidden="true" />
        <h2>جارٍ تحميل إعدادات وصلاحيات الأعمال</h2>
        <p>يتم جلب القيم المحفوظة وعقد الصلاحيات الآمن.</p>
      </section>

      <section v-else-if="error" class="ym-works-settings-result-card ym-works-settings-state is-error" role="alert">
        <span class="ym-works-settings-state__icon" aria-hidden="true">!</span>
        <h2>تعذر تحميل إعدادات الأعمال</h2>
        <p>{{ error }}</p>
        <button type="button" class="ym-works-settings-button is-secondary" @click="fetchSettings">
          إعادة المحاولة
        </button>
      </section>

      <template v-else-if="data">
        <section id="settings-operations" class="ym-works-settings-section-anchor ym-works-settings-operations">
          <header class="ym-works-settings-section-heading">
            <div>
              <span>01</span>
              <div>
                <p>مساحة الإدارة الفعلية</p>
                <h2>الإعدادات التشغيلية</h2>
              </div>
            </div>
            <p>القيم المحفوظة التي تؤثر مباشرةً في المراجعة والنشر ورفع الوسائط.</p>
          </header>

          <aside class="ym-works-settings-readiness" role="note">
            <section class="ym-works-settings-readiness__storage">
              <div>
                <span>جاهزية التخزين</span>
                <h3>
                  {{ data.settings_support.persistent_settings_available
                    ? 'الإعدادات الدائمة متاحة'
                    : 'راجع حالة دعم التخزين الدائم' }}
                </h3>
                <p>{{ data.settings_support.reason }}</p>
              </div>
              <dl>
                <div>
                  <dt>الحالة</dt>
                  <dd><BooleanBadge :value="data.settings_support.persistent_settings_available" /></dd>
                </div>
                <div>
                  <dt>المصدر</dt>
                  <dd><code dir="ltr">{{ data.settings_support.source }}</code></dd>
                </div>
              </dl>
            </section>
            <section class="ym-works-settings-readiness__scope">
              <div>
                <span>نطاق الإدارة</span>
                <p>{{ data.management_support.reason }}</p>
              </div>
              <div class="ym-works-settings-mutation-grid">
                <article v-for="item in mutationItems" :key="item.key">
                  <span>{{ item.label }}</span>
                  <BooleanBadge :value="item.value" />
                </article>
              </div>
            </section>
          </aside>

          <WorksSettingsEditor
            :settings="data.stored_settings"
            :capabilities="data.current_user_capabilities"
            :management-support="data.management_support"
            :saving="saving"
            :save-message="saveMessage"
            :message-tone="messageTone"
            :field-errors="fieldErrors"
            :conflict-version="conflictVersion"
            :locale="currentLocale"
            @save="saveSettings"
            @reload="reloadAfterConflict"
            @reset="resetMutationFeedback"
          />

        </section>

        <section class="ym-works-settings-summary-grid" aria-label="ملخص إعدادات وصلاحيات الأعمال">
          <article
            v-for="card in summaryCards"
            :key="card.key"
            class="ym-works-settings-summary-card"
            :style="{ '--settings-accent': card.color }"
          >
            <span>{{ card.label }}</span>
            <strong>{{ formatNumber(card.value) }}</strong>
            <small>{{ card.hint }}</small>
          </article>
        </section>

        <section class="ym-works-settings-workspace-row">
          <section id="settings-access" class="ym-works-settings-two-column ym-works-settings-section-anchor">
          <article class="ym-works-settings-card">
            <header>
              <div>
                <p>نموذج الوصول</p>
                <h2>الأدوار وحدود التفويض</h2>
              </div>
              <span class="ym-works-settings-section-badge">access_model</span>
            </header>

            <div class="ym-works-settings-role-group">
              <h3>الأدوار الداخلية</h3>
              <div>
                <span v-for="role in data.access_model.internal_roles" :key="role" class="ym-works-settings-role is-internal">
                  <code dir="ltr">{{ role }}</code>
                </span>
              </div>
            </div>

            <div class="ym-works-settings-role-group">
              <h3>الأدوار الممنوعة</h3>
              <div>
                <span v-for="role in data.access_model.forbidden_roles" :key="role" class="ym-works-settings-role is-forbidden">
                  <code dir="ltr">{{ role }}</code>
                </span>
              </div>
            </div>

            <dl class="ym-works-settings-definition-list">
              <div>
                <dt>المسؤول الأعلى يملك جميع الصلاحيات</dt>
                <dd><BooleanBadge :value="data.access_model.super_admin_has_all_permissions" /></dd>
              </div>
              <div>
                <dt>منع العميل والمصمم حتى عند منحهما صلاحيات عرضية</dt>
                <dd><BooleanBadge :value="data.access_model.client_designer_forbidden_even_if_granted" /></dd>
              </div>
            </dl>
          </article>

          <article class="ym-works-settings-card">
            <header>
              <div>
                <p>قدرات الحساب الحالي فقط</p>
                <h2>نطاق الصلاحيات المتاح</h2>
              </div>
              <span class="ym-works-settings-section-badge is-capability">ليست صلاحيات دور</span>
            </header>

            <p class="ym-works-settings-card-copy">
              تحدد هذه القدرات ما يستطيع الحساب الحالي تعديله داخل إعدادات الأعمال، ولا تمثل محررًا للأدوار أو الإسنادات.
            </p>
            <div class="ym-works-settings-capabilities">
              <article v-for="capability in capabilityItems" :key="capability.key">
                <div>
                  <strong>{{ capability.label }}</strong>
                  <code dir="ltr">{{ capability.key }}</code>
                </div>
                <span
                  class="ym-works-settings-capability-badge"
                  :class="capability.value ? 'is-available' : 'is-unavailable'"
                >
                  {{ capability.value ? 'ممنوحة للحساب' : 'غير ممنوحة' }}
                </span>
              </article>
            </div>
          </article>
          </section>

        <section id="settings-workflow" class="ym-works-settings-card ym-works-settings-workflow ym-works-settings-section-anchor">
          <header>
            <div>
              <p>مرجع للقراءة فقط</p>
              <h2>سير عمل الأعمال</h2>
            </div>
            <span class="ym-works-settings-section-badge">غير قابل للتحرير</span>
          </header>

          <p class="ym-works-settings-card-copy">عرض مرجعي للحالات والمراحل المطبقة حاليًا، دون أدوات تعديل.</p>

          <div class="ym-works-settings-workflow-grid">
            <article>
              <h3>حالات العمل</h3>
              <div class="ym-works-settings-code-list">
                <span v-for="status in data.workflow.statuses" :key="status">
                  <code dir="ltr">{{ status }}</code>
                  <small>{{ workflowLabel(status) }}</small>
                </span>
              </div>
            </article>
            <article>
              <h3>حالات الظهور</h3>
              <div class="ym-works-settings-code-list">
                <span v-for="status in data.workflow.visibility_statuses" :key="status">
                  <code dir="ltr">{{ status }}</code>
                  <small>{{ workflowLabel(status) }}</small>
                </span>
              </div>
            </article>
            <article>
              <h3>أحداث دورة الحياة</h3>
              <div class="ym-works-settings-code-list">
                <span v-for="event in data.workflow.lifecycle_events" :key="event">
                  <code dir="ltr">{{ event }}</code>
                  <small>{{ workflowLabel(event) }}</small>
                </span>
              </div>
            </article>
            <article>
              <h3>حالات قائمة المراجعة</h3>
              <div class="ym-works-settings-code-list">
                <span v-for="status in data.workflow.review_queue_statuses" :key="status">
                  <code dir="ltr">{{ status }}</code>
                  <small>{{ workflowLabel(status) }}</small>
                </span>
              </div>
            </article>
          </div>
          <code class="ym-works-settings-workflow-source" dir="ltr">{{ data.workflow.derived_from }}</code>
        </section>
        </section>

        <section id="settings-permissions" class="ym-works-settings-registry-card ym-works-settings-section-anchor">
          <header class="ym-works-settings-registry-head">
            <div>
              <p>مرجع الصلاحيات المسجلة</p>
              <h2>كتالوج صلاحيات الأعمال</h2>
              <div class="ym-works-settings-registry-intro">
                <span class="ym-works-settings-readonly-badge">للقراءة فقط</span>
                <strong class="ym-works-settings-registry-copy">
                  هذا الكتالوج مرجع لصلاحيات منظومة الأعمال، ولا يغيّر الأدوار أو إسنادات المستخدمين.
                </strong>
              </div>
              <span>
                المجموعة:
                <code dir="ltr">{{ data.permission_registry.group }}</code>
              </span>
            </div>
            <div class="ym-works-settings-registry-total">
              <span>النتائج المحلية</span>
              <strong>{{ formatNumber(filteredPermissionCount) }}</strong>
              <small>من {{ formatNumber(data.permission_registry.total_permissions) }}</small>
            </div>
          </header>

          <div class="ym-works-settings-explorer-toolbar">
            <div class="ym-works-settings-local-filters">
            <label class="is-search">
              <span>بحث محلي في الصلاحيات</span>
              <input
                v-model="permissionSearch"
                type="search"
                maxlength="120"
                placeholder="الاسم أو المفتاح أو الوصف أو المجموعة"
                autocomplete="off"
              />
              <small>لا يُرسل هذا البحث إلى الخادم.</small>
            </label>
            <label>
              <span>القسم</span>
              <select v-model="selectedSection">
                <option value="">الكل</option>
                <option v-for="option in sectionFilterOptions" :key="option.key" :value="option.key">
                  {{ option.label }}
                </option>
              </select>
            </label>
              <label>
                <span>اكتمال الوصف</span>
                <select v-model="selectedDescriptionState">
                  <option value="">الكل</option>
                  <option value="with_description">لها وصف</option>
                  <option value="without_description">دون وصف</option>
                </select>
              </label>
            </div>

            <div class="ym-works-settings-explorer-actions" aria-label="إجراءات مستكشف الصلاحيات">
              <div>
                <button type="button" @click="expandAllSections">فتح الكل</button>
                <button type="button" @click="collapseAllSections">طي الكل</button>
              </div>
              <button
                type="button"
                class="is-reset"
                :disabled="!hasExplorerFilters"
                @click="resetPermissionExplorer"
              >
                مسح الفلاتر
              </button>
            </div>
          </div>

          <div v-if="filteredPermissionCount === 0" class="ym-works-settings-empty" role="status">
            <span aria-hidden="true">0</span>
            <div>
              <h3>
                {{ debouncedPermissionSearch
                  ? 'لا توجد صلاحيات مطابقة لعبارة البحث'
                  : 'لا توجد صلاحيات مطابقة للفلاتر الحالية' }}
              </h3>
              <p>
                {{ debouncedPermissionSearch
                  ? 'امسح عبارة البحث لعرض الصلاحيات المتاحة ضمن الفلاتر الحالية.'
                  : 'أعد ضبط الفلاتر لعرض كتالوج الصلاحيات كاملًا.' }}
              </p>
              <button
                v-if="debouncedPermissionSearch"
                type="button"
                class="ym-works-settings-empty-action"
                @click="clearPermissionSearch"
              >
                مسح البحث
              </button>
              <button
                v-else
                type="button"
                class="ym-works-settings-empty-action"
                @click="resetPermissionExplorer"
              >
                إعادة الضبط
              </button>
            </div>
          </div>

          <div v-else class="ym-works-settings-sections">
            <section v-for="section in filteredSections" :key="section.key" class="ym-works-settings-permission-section">
              <h3 class="ym-works-settings-permission-section__heading">
                <button
                  type="button"
                  class="ym-works-settings-accordion-trigger"
                  :aria-expanded="isSectionExpanded(section.key)"
                  :aria-controls="sectionPanelId(section.key)"
                  @click="toggleSection(section.key)"
                  @keydown.enter.prevent="toggleSection(section.key)"
                  @keydown.space.prevent
                  @keyup.space.prevent="toggleSection(section.key)"
                >
                  <span class="ym-works-settings-accordion-copy">
                    <span class="ym-works-settings-accordion-title">{{ section.label }}</span>
                    <code dir="ltr">{{ section.key }}</code>
                  </span>
                  <span class="ym-works-settings-accordion-meta">
                    <strong>{{ formatNumber(section.permissions.length) }}</strong>
                    <span class="ym-works-settings-accordion-chevron" aria-hidden="true">⌄</span>
                  </span>
                </button>
              </h3>

              <div
                v-if="isSectionExpanded(section.key)"
                :id="sectionPanelId(section.key)"
                class="ym-works-settings-accordion-panel"
              >
                <div class="ym-works-settings-permission-grid">
                  <article
                    v-for="permission in section.permissions"
                    :key="permission.name"
                    class="ym-works-settings-permission-card"
                  >
                    <div class="ym-works-settings-permission-card__body">
                      <h4 :dir="textDirection(permission.label)">
                        {{ displayText(permission.label, 'دون تسمية إضافية') }}
                      </h4>
                      <p :dir="textDirection(permission.description)">
                        {{ displayText(permission.description, 'لا يوجد وصف مسجل.') }}
                      </p>
                    </div>
                    <div class="ym-works-settings-permission-card__meta">
                      <span class="ym-works-settings-section-badge">{{ section.label }}</span>
                      <code dir="ltr">{{ permission.name }}</code>
                    </div>
                    <button
                      type="button"
                      class="ym-works-settings-details-button"
                      title="عرض تفاصيل الصلاحية"
                      :aria-label="`عرض تفاصيل الصلاحية ${displayText(permission.label, permission.name)}`"
                      @click="openPermissionDetails(section, permission, $event)"
                    >
                      عرض التفاصيل
                    </button>
                  </article>
                </div>
              </div>
            </section>
          </div>
        </section>
      </template>

      <section v-else-if="hasLoaded" class="ym-works-settings-result-card ym-works-settings-state" role="status">
        <span class="ym-works-settings-empty-icon" aria-hidden="true">0</span>
        <h2>لا توجد بيانات إعدادات متاحة</h2>
        <p>لم تُرجع الواجهة البرمجية عقد إعدادات قابلًا للعرض.</p>
      </section>
    </template>

    <div
      v-if="drawerOpen && selectedPermission"
      class="ym-settings-detail-backdrop"
      @click.self="closePermissionDetails"
    >
      <section
        ref="permissionDrawer"
        class="ym-settings-detail-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ym-settings-detail-title"
        tabindex="-1"
        @keydown="handleDrawerKeydown"
      >
        <header class="ym-settings-detail-drawer__head">
          <div>
            <span>تفاصيل محلية للقراءة فقط</span>
            <h2 id="ym-settings-detail-title">تفاصيل الصلاحية</h2>
            <code dir="ltr">{{ selectedPermission.permission.name }}</code>
          </div>
          <button
            type="button"
            class="ym-settings-detail-drawer__close"
            title="إغلاق التفاصيل"
            aria-label="إغلاق التفاصيل"
            @click="closePermissionDetails"
          >
            ×
          </button>
        </header>

        <div class="ym-settings-detail-content">
          <section class="ym-settings-detail-intro">
            <div>
              <span class="ym-works-settings-section-badge">{{ selectedPermission.section.key }}</span>
            </div>
            <h3 :dir="textDirection(selectedPermission.permission.label)">
              {{ displayText(selectedPermission.permission.label, 'دون تسمية إضافية') }}
            </h3>
            <code dir="ltr">{{ selectedPermission.permission.name }}</code>
          </section>

          <section class="ym-settings-detail-section">
            <h3>بيانات الصلاحية</h3>
            <dl class="ym-settings-detail-grid">
              <div><dt>مفتاح القسم</dt><dd><code dir="ltr">{{ selectedPermission.section.key }}</code></dd></div>
              <div><dt>تسمية القسم</dt><dd>{{ selectedPermission.section.label }}</dd></div>
              <div><dt>اسم الصلاحية</dt><dd><code dir="ltr">{{ selectedPermission.permission.name }}</code></dd></div>
              <div><dt>التسمية</dt><dd :dir="textDirection(selectedPermission.permission.label)">{{ displayText(selectedPermission.permission.label, 'غير مسجلة') }}</dd></div>
              <div class="is-wide"><dt>الوصف</dt><dd :dir="textDirection(selectedPermission.permission.description)">{{ displayText(selectedPermission.permission.description, 'غير مسجل') }}</dd></div>
            </dl>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, nextTick, onBeforeUnmount, onMounted, ref, watch, type PropType } from 'vue'
import AdminPageHero from '~/components/admin/visual/AdminPageHero.vue'
import { useApiClient } from '~/composables/useApiClient'
import { useAuthStore } from '~/stores/authStore'

definePageMeta({ layout: 'admin' })

type SectionKey = 'navigation' | 'read_detail' | 'content_management' | 'review' | 'visibility' | 'reports' | 'taxonomy' | 'bulk' | 'activity_audit' | 'settings' | 'search'

interface SettingsSupport {
  persistent_settings_available: boolean
  source: string
  reason: string
}

interface AccessModel {
  internal_roles: string[]
  forbidden_roles: string[]
  super_admin_has_all_permissions: boolean
  client_designer_forbidden_even_if_granted: boolean
}

interface Workflow {
  statuses: string[]
  visibility_statuses: string[]
  lifecycle_events: string[]
  review_queue_statuses: string[]
  derived_from: string
}

interface PermissionItem {
  name: string
  label?: string
  description?: string
}

interface PermissionSection {
  key: string
  label: string
  permissions: PermissionItem[]
}

interface PermissionRegistry {
  group: string
  total_permissions: number
  sections: PermissionSection[]
}

interface CurrentUserCapabilities {
  can_view_settings: boolean
  can_manage_settings: boolean
  can_manage_workflow: boolean
  can_manage_review_sla: boolean
  can_manage_direct_publish_trust: boolean
  can_manage_media_limits: boolean
}

interface ManagementSupport {
  settings_mutation_available: boolean
  workflow_mutation_available: boolean
  review_sla_mutation_available: boolean
  direct_publish_trust_mutation_available: boolean
  media_limits_mutation_available: boolean
  reason: string
}

type AllowedMediaType = 'image' | 'video' | 'gallery'

interface StoredMediaLimits {
  max_items: number | null
  max_file_size_kb: number | null
  allowed_types: AllowedMediaType[] | null
}

interface StoredSettingsValues {
  review_sla_hours: number | null
  direct_publish_trust_enabled: boolean
  media_limits: StoredMediaLimits
}

interface StoredSettings {
  scope: string
  version: number
  values: StoredSettingsValues
  storage_record_found: boolean
  updated_at: string | null
}

interface SettingsData {
  settings_support: SettingsSupport
  stored_settings: StoredSettings
  access_model: AccessModel
  workflow: Workflow
  permission_registry: PermissionRegistry
  current_user_capabilities: CurrentUserCapabilities
  management_support: ManagementSupport
}

interface SettingsResponse {
  success: boolean
  data: SettingsData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface SettingsMutationValues extends Partial<Omit<StoredSettingsValues, 'media_limits'>> {
  media_limits?: Partial<StoredMediaLimits>
}

interface SettingsMutationPayload {
  version: number
  values: SettingsMutationValues
}

interface SettingsMutationSuccessData {
  changed: boolean
  changed_keys: string[]
  previous_version: number
  current_version: number
  stored_settings: StoredSettings
}

interface SettingsMutationConflictData {
  current_version: number
}

interface SettingsMutationResponse {
  success: boolean
  data: SettingsMutationSuccessData | SettingsMutationConflictData | null
  message?: string
  errors?: Record<string, string[]> | null
}

interface SelectedPermission {
  section: Pick<PermissionSection, 'key' | 'label'>
  permission: PermissionItem
}

const BooleanBadge = defineComponent({
  props: { value: { type: Boolean as PropType<boolean>, required: true } },
  setup(props) {
    return () => h('span', {
      class: ['ym-works-settings-boolean', props.value ? 'is-yes' : 'is-no']
    }, props.value ? 'نعم' : 'لا')
  }
})

const authStore = useAuthStore()
const { apiFetch } = useApiClient()
const currentLocale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')
const pageDirection = computed<'rtl' | 'ltr'>(() => currentLocale.value === 'ar' ? 'rtl' : 'ltr')

const authPending = computed(() => !authStore.isInitialized)
const hasSettingsAccess = computed(() => {
  if (!authStore.isInitialized || !authStore.isAuthenticated) return false
  if (authStore.role === 'super-admin') return true
  if (!['admin', 'staff'].includes(authStore.role || '')) return false

  return authStore.permissions.includes('admin.works.access')
    && authStore.permissions.includes('admin.works.settings.view')
})
const serverForbidden = ref(false)
const forbidden = computed(() => authStore.isInitialized && (!hasSettingsAccess.value || serverForbidden.value))

const data = ref<SettingsData | null>(null)
const loading = ref(false)
const hasLoaded = ref(false)
const error = ref<string | null>(null)
const saving = ref(false)
const saveMessage = ref<string | null>(null)
const messageTone = ref<'success' | 'error' | 'info' | null>(null)
const fieldErrors = ref<Record<string, string[]>>({})
const conflictVersion = ref<number | null>(null)
const permissionSearch = ref('')
const debouncedPermissionSearch = ref('')
const selectedSection = ref<'' | SectionKey>('')
const selectedDescriptionState = ref<'' | 'with_description' | 'without_description'>('')
const expandedSections = ref<Set<string>>(new Set())
const drawerOpen = ref(false)
const selectedPermission = ref<SelectedPermission | null>(null)
const permissionDrawer = ref<HTMLElement | null>(null)

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let requestRevision = 0
let mutationRevision = 0
let handlingUnauthorized = false
let permissionSearchTimer: ReturnType<typeof setTimeout> | null = null
let expandedBeforeSearch: Set<string> | null = null
let permissionExplorerInitialized = false
let permissionDrawerTrigger: HTMLElement | null = null
let previousBodyOverflow = ''

const authorizationSignature = computed(() => [
  authStore.isInitialized ? 'ready' : 'pending',
  authStore.isAuthenticated ? 'authenticated' : 'guest',
  authStore.role || '',
  [...authStore.permissions].sort().join(',')
].join('|'))

const sectionLabels: Record<SectionKey, string> = {
  navigation: 'الوصول والتنقل',
  read_detail: 'القائمة وتفاصيل القراءة',
  content_management: 'إنشاء وتحديث المحتوى',
  review: 'المراجعة',
  visibility: 'الظهور والتمييز',
  reports: 'البلاغات',
  taxonomy: 'التصنيفات والوسوم',
  bulk: 'الإجراءات الجماعية',
  activity_audit: 'النشاط والتدقيق',
  settings: 'الإعدادات',
  search: 'البحث'
}

const sectionFilterOptions = computed(() => {
  if (!data.value) return []
  return data.value.permission_registry.sections.map(section => ({
    key: section.key as SectionKey,
    label: section.label
  }))
})

const capabilityLabels: Record<keyof CurrentUserCapabilities, string> = {
  can_view_settings: 'عرض إعدادات الأعمال',
  can_manage_settings: 'إدارة إعدادات الأعمال',
  can_manage_workflow: 'إدارة سير العمل',
  can_manage_review_sla: 'إدارة مهلة المراجعة',
  can_manage_direct_publish_trust: 'إدارة ثقة النشر المباشر',
  can_manage_media_limits: 'إدارة حدود الوسائط'
}

const mutationLabels: Record<keyof Omit<ManagementSupport, 'reason'>, string> = {
  settings_mutation_available: 'تعديل الإعدادات',
  workflow_mutation_available: 'تعديل سير العمل',
  review_sla_mutation_available: 'تعديل مهلة المراجعة',
  direct_publish_trust_mutation_available: 'تعديل ثقة النشر المباشر',
  media_limits_mutation_available: 'تعديل حدود الوسائط'
}

const capabilityItems = computed(() => {
  if (!data.value) return []
  return (Object.entries(data.value.current_user_capabilities) as Array<[keyof CurrentUserCapabilities, boolean]>)
    .map(([key, value]) => ({ key, label: capabilityLabels[key], value }))
})

const mutationItems = computed(() => {
  if (!data.value) return []
  const support = data.value.management_support
  return (Object.keys(mutationLabels) as Array<keyof typeof mutationLabels>)
    .map(key => ({ key, label: mutationLabels[key], value: support[key] }))
})

const summaryCards = computed(() => {
  if (!data.value) return []
  const settings = data.value
  const availableCapabilities = capabilityItems.value.filter(item => item.value).length
  const availableMutations = mutationItems.value.filter(item => item.value).length

  return [
    { key: 'permissions', label: 'إجمالي الصلاحيات', value: settings.permission_registry.total_permissions, hint: 'صلاحيات الأعمال المسجلة', color: '#8b5cf6' },
    { key: 'sections', label: 'الأقسام', value: settings.permission_registry.sections.length, hint: 'مجموعات وظيفية واضحة', color: '#38bdf8' },
    { key: 'internal_roles', label: 'الأدوار الداخلية', value: settings.access_model.internal_roles.length, hint: 'مسموحة وفق التفويض', color: '#10b981' },
    { key: 'forbidden_roles', label: 'الأدوار الممنوعة', value: settings.access_model.forbidden_roles.length, hint: 'ممنوعة دائمًا', color: '#f43f5e' },
    { key: 'statuses', label: 'حالات الأعمال', value: settings.workflow.statuses.length, hint: 'حالات سير العمل', color: '#f59e0b' },
    { key: 'events', label: 'أحداث دورة الحياة', value: settings.workflow.lifecycle_events.length, hint: 'العقد الحالي للنشاط', color: '#06b6d4' },
    { key: 'queue', label: 'حالات قائمة المراجعة', value: settings.workflow.review_queue_statuses.length, hint: 'ضمن مسار المراجعة', color: '#c084fc' },
    { key: 'capabilities', label: 'القدرات المتاحة', value: availableCapabilities, hint: 'للحساب الحالي', color: '#22c55e' },
    { key: 'mutations', label: 'واجهات التعديل المتاحة', value: availableMutations, hint: 'وفق عقد دعم الإدارة', color: '#fb7185' }
  ]
})

const filteredSections = computed<PermissionSection[]>(() => {
  if (!data.value) return []
  const term = debouncedPermissionSearch.value.trim().toLocaleLowerCase()

  return data.value.permission_registry.sections
    .filter(section => selectedSection.value === '' || section.key === selectedSection.value)
    .map((section) => {
      const sectionMatches = term === '' || section.label.toLocaleLowerCase().includes(term) || section.key.toLocaleLowerCase().includes(term)
      const permissions = section.permissions.filter((permission) => {
        const hasDescription = typeof permission.description === 'string' && permission.description.trim() !== ''
        if (selectedDescriptionState.value === 'with_description' && !hasDescription) return false
        if (selectedDescriptionState.value === 'without_description' && hasDescription) return false
        if (sectionMatches) return true
        return [permission.name, permission.label, permission.description, section.label, section.key]
          .some(value => String(value ?? '').toLocaleLowerCase().includes(term))
      })
      return { ...section, permissions }
    })
    .filter(section => section.permissions.length > 0)
})

const filteredPermissionCount = computed(() => filteredSections.value
  .reduce((total, section) => total + section.permissions.length, 0))

const hasExplorerFilters = computed(() => (
  permissionSearch.value.trim() !== ''
  || selectedSection.value !== ''
  || selectedDescriptionState.value !== ''
))

function formatNumber(value: number): string {
  return new Intl.NumberFormat(currentLocale.value === 'ar' ? 'ar-YE' : 'en-US').format(value)
}

function textDirection(value: string | null | undefined): 'rtl' | 'ltr' {
  return /[\u0600-\u06FF]/.test(String(value ?? '')) ? 'rtl' : 'ltr'
}

function displayText(value: string | null | undefined, fallback: string): string {
  return typeof value === 'string' && value.trim() !== '' ? value : fallback
}

function workflowLabel(value: string): string {
  const labels: Record<string, string> = {
    draft: 'مسودة', submitted: 'مرسل', in_review: 'قيد المراجعة', changes_requested: 'تعديلات مطلوبة',
    approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف',
    public: 'عام', created: 'إنشاء', updated: 'تحديث', reviewed: 'مراجعة'
  }
  return labels[value] ?? value
}

function initializePermissionExplorer(): void {
  if (permissionExplorerInitialized || !data.value) return
  const firstSection = data.value.permission_registry.sections[0]
  const isMobile = typeof window !== 'undefined' && window.matchMedia('(max-width: 640px)').matches
  expandedSections.value = !isMobile && firstSection ? new Set([firstSection.key]) : new Set()
  permissionExplorerInitialized = true
}

function sectionPanelId(sectionKey: string): string {
  return `ym-settings-permissions-${sectionKey.replace(/[^a-zA-Z0-9_-]/g, '-')}`
}

function isSectionExpanded(sectionKey: string): boolean {
  return debouncedPermissionSearch.value.trim() !== '' || expandedSections.value.has(sectionKey)
}

function toggleSection(sectionKey: string): void {
  const next = new Set(expandedSections.value)
  if (next.has(sectionKey)) next.delete(sectionKey)
  else next.add(sectionKey)
  expandedSections.value = next
}

function expandAllSections(): void {
  if (!data.value) return
  expandedSections.value = new Set(data.value.permission_registry.sections.map(section => section.key))
}

function collapseAllSections(): void {
  expandedSections.value = new Set()
}

function clearPermissionSearch(): void {
  if (permissionSearchTimer) clearTimeout(permissionSearchTimer)
  permissionSearch.value = ''
  debouncedPermissionSearch.value = ''
}

function resetPermissionExplorer(): void {
  clearPermissionSearch()
  selectedSection.value = ''
  selectedDescriptionState.value = ''
}

function drawerFocusableElements(): HTMLElement[] {
  if (!permissionDrawer.value) return []
  return Array.from(permissionDrawer.value.querySelectorAll<HTMLElement>(
    'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
  )).filter(element => element.offsetParent !== null)
}

function handleDrawerKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    closePermissionDetails()
    return
  }
  if (event.key !== 'Tab') return

  const focusable = drawerFocusableElements()
  if (focusable.length === 0) {
    event.preventDefault()
    permissionDrawer.value?.focus()
    return
  }
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function errorStatus(requestError: unknown): number | null {
  if (!requestError || typeof requestError !== 'object') return null
  if ('response' in requestError && typeof (requestError as { response?: { status?: unknown } }).response?.status === 'number') {
    return (requestError as { response: { status: number } }).response.status
  }
  if ('statusCode' in requestError && typeof (requestError as { statusCode?: unknown }).statusCode === 'number') {
    return (requestError as { statusCode: number }).statusCode
  }
  if ('status' in requestError && typeof (requestError as { status?: unknown }).status === 'number') {
    return (requestError as { status: number }).status
  }
  return null
}

function errorData(requestError: unknown): Record<string, unknown> | null {
  if (!requestError || typeof requestError !== 'object') return null
  const candidate = requestError as {
    data?: unknown
    response?: { _data?: unknown }
  }
  const payload = candidate.data ?? candidate.response?._data
  return payload && typeof payload === 'object'
    ? payload as Record<string, unknown>
    : null
}

function serverErrors(requestError: unknown): Record<string, string[]> {
  const errors = errorData(requestError)?.errors
  if (!errors || typeof errors !== 'object') return {}

  return Object.fromEntries(
    Object.entries(errors)
      .filter((entry): entry is [string, string[]] => (
        Array.isArray(entry[1]) && entry[1].every(message => typeof message === 'string')
      ))
  )
}

function serverMessage(requestError: unknown): string | null {
  const message = errorData(requestError)?.message
  return typeof message === 'string' && message.trim() !== '' ? message : null
}

function conflictCurrentVersion(requestError: unknown): number | null {
  const responseData = errorData(requestError)?.data
  if (!responseData || typeof responseData !== 'object' || !('current_version' in responseData)) return null
  const version = (responseData as { current_version?: unknown }).current_version
  return typeof version === 'number' && Number.isInteger(version) ? version : null
}

function changedKeyLabel(key: string): string {
  const labels: Record<string, string> = {
    review_sla_hours: 'مهلة المراجعة',
    direct_publish_trust_enabled: 'ثقة النشر المباشر',
    'media_limits.max_items': 'الحد الأقصى لعناصر الوسائط',
    'media_limits.max_file_size_kb': 'الحد الأقصى لحجم الملف',
    'media_limits.allowed_types': 'أنواع الوسائط المسموحة'
  }
  return labels[key] ?? key
}

function mutationResultMessage(response: SettingsMutationResponse, result: SettingsMutationSuccessData): string {
  const parts = [
    response.message || (result.changed ? 'تم تحديث إعدادات الأعمال بنجاح.' : 'القيم مطابقة للإعدادات المحفوظة.'),
    `الإصدار السابق: ${result.previous_version}، الإصدار الحالي: ${result.current_version}.`
  ]
  if (result.changed_keys.length > 0) {
    parts.push(`الحقول المتغيرة: ${result.changed_keys.map(changedKeyLabel).join('، ')}.`)
  }
  return parts.join(' ')
}

async function saveSettings(payload: SettingsMutationPayload): Promise<void> {
  if (saving.value || !data.value) return
  const currentMutationRevision = ++mutationRevision
  saving.value = true
  saveMessage.value = null
  messageTone.value = null
  fieldErrors.value = {}
  conflictVersion.value = null

  try {
    const response = await apiFetch<SettingsMutationResponse>('/admin/works/settings', {
      method: 'PATCH',
      body: payload
    })
    if (currentMutationRevision !== mutationRevision || !data.value) return
    if (!response.success || !response.data || !('stored_settings' in response.data)) {
      saveMessage.value = response.message || 'تعذر اعتماد استجابة حفظ إعدادات الأعمال.'
      messageTone.value = 'error'
      return
    }

    requestRevision += 1
    loading.value = false
    data.value = {
      ...data.value,
      stored_settings: response.data.stored_settings
    }
    fieldErrors.value = {}
    conflictVersion.value = null
    saveMessage.value = mutationResultMessage(response, response.data)
    messageTone.value = response.data.changed ? 'success' : 'info'
  } catch (requestError: unknown) {
    const status = errorStatus(requestError)

    if (status === 401) {
      handleUnauthorized()
      return
    }

    if (currentMutationRevision !== mutationRevision) return

    if (status === 409) {
      conflictVersion.value = conflictCurrentVersion(requestError)
      fieldErrors.value = {}
      saveMessage.value = serverMessage(requestError) || 'توجد نسخة أحدث من إعدادات الأعمال على الخادم.'
      messageTone.value = 'error'
      return
    }

    if (status === 422) {
      const errors = serverErrors(requestError)
      const bindableFields = new Set([
        'values.review_sla_hours',
        'values.direct_publish_trust_enabled',
        'values.media_limits',
        'values.media_limits.max_items',
        'values.media_limits.max_file_size_kb',
        'values.media_limits.allowed_types'
      ])
      fieldErrors.value = errors
      const unboundError = Object.entries(errors).find(([key]) => !bindableFields.has(key))
      saveMessage.value = unboundError?.[1]?.[0]
        || (Object.keys(errors).length === 0
          ? serverMessage(requestError) || 'تعذر التحقق من القيم المرسلة.'
          : null)
      messageTone.value = saveMessage.value ? 'error' : null
      return
    }

    if (status === 403) {
      fieldErrors.value = {}
      conflictVersion.value = null
      await fetchSettings()
      if (currentMutationRevision !== mutationRevision) return
      saveMessage.value = 'تغيّرت صلاحيات الحساب. تم تحديث القدرات وأصبحت الحقول غير المصرح بها مقفلة.'
      messageTone.value = 'error'
      return
    }

    saveMessage.value = serverMessage(requestError) || 'تعذر حفظ إعدادات الأعمال. حاول مرة أخرى.'
    messageTone.value = 'error'
  } finally {
    if (currentMutationRevision === mutationRevision) saving.value = false
  }
}

function resetMutationFeedback(): void {
  if (saving.value) return
  mutationRevision += 1
  fieldErrors.value = {}
  conflictVersion.value = null
  saveMessage.value = null
  messageTone.value = null
}

async function reloadAfterConflict(): Promise<void> {
  if (saving.value) return
  resetMutationFeedback()
  await fetchSettings()
}

async function fetchSettings(): Promise<void> {
  if (!authStore.isInitialized || !hasSettingsAccess.value) return
  const requestAccessRevision = accessRevision
  const currentRequestRevision = ++requestRevision
  loading.value = true
  error.value = null

  try {
    const response = await apiFetch<SettingsResponse>('/admin/works/settings')
    if (requestAccessRevision !== accessRevision || currentRequestRevision !== requestRevision || !hasSettingsAccess.value) return

    if (!response.success || !response.data) {
      clearSettingsData()
      error.value = 'حدث خطأ أثناء تحميل إعدادات وصلاحيات الأعمال. حاول مرة أخرى.'
      return
    }

    data.value = response.data
    hasLoaded.value = true
    serverForbidden.value = false
    initializePermissionExplorer()
  } catch (requestError: unknown) {
    const status = errorStatus(requestError)

    if (status === 401) {
      handleUnauthorized()
      return
    }

    if (requestAccessRevision !== accessRevision || currentRequestRevision !== requestRevision || !hasSettingsAccess.value) return

    if (status === 403) {
      serverForbidden.value = true
      clearSettingsData()
      closePermissionDetails()
      return
    }
    error.value = 'حدث خطأ أثناء تحميل إعدادات وصلاحيات الأعمال. حاول مرة أخرى.'
  } finally {
    if (requestAccessRevision === accessRevision && currentRequestRevision === requestRevision) loading.value = false
  }
}

async function openPermissionDetails(section: PermissionSection, permission: PermissionItem, event: Event): Promise<void> {
  permissionDrawerTrigger = event.currentTarget instanceof HTMLElement ? event.currentTarget : null
  selectedPermission.value = {
    section: { key: section.key, label: section.label },
    permission
  }
  drawerOpen.value = true
  previousBodyOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  await nextTick()
  permissionDrawer.value?.focus()
}

async function closePermissionDetails(): Promise<void> {
  const trigger = permissionDrawerTrigger
  drawerOpen.value = false
  selectedPermission.value = null
  document.body.style.overflow = previousBodyOverflow
  permissionDrawerTrigger = null
  await nextTick()
  if (trigger?.isConnected) trigger.focus()
}

function clearSettingsData(): void {
  data.value = null
  hasLoaded.value = false
  clearPermissionSearch()
  selectedSection.value = ''
  selectedDescriptionState.value = ''
  expandedSections.value = new Set()
  expandedBeforeSearch = null
  permissionExplorerInitialized = false
}

function clearPageState(): void {
  requestRevision += 1
  mutationRevision += 1
  clearSettingsData()
  loading.value = false
  saving.value = false
  error.value = null
  saveMessage.value = null
  messageTone.value = null
  fieldErrors.value = {}
  conflictVersion.value = null
  closePermissionDetails()
}

function handleUnauthorized(): void {
  if (handlingUnauthorized) return
  handlingUnauthorized = true
  serverForbidden.value = false
  clearPageState()

  if (
    authStore.isAuthenticated
    || authStore.user !== null
    || authStore.role !== null
    || authStore.permissions.length > 0
  ) {
    authStore.clearAuth()
  }

  queueMicrotask(() => {
    handlingUnauthorized = false
  })
}

function syncSettingsAccessState(): void {
  if (!pageMounted) return
  accessRevision += 1
  serverForbidden.value = false
  closePermissionDetails()

  if (!authStore.isInitialized || !hasSettingsAccess.value) {
    loadedAuthorizationSignature = null
    clearPageState()
    return
  }

  if (loadedAuthorizationSignature === authorizationSignature.value) return
  loadedAuthorizationSignature = authorizationSignature.value
  void fetchSettings()
}

watch(authorizationSignature, () => syncSettingsAccessState(), { flush: 'post' })
watch(permissionSearch, (value) => {
  if (permissionSearchTimer) clearTimeout(permissionSearchTimer)
  permissionSearchTimer = setTimeout(() => {
    debouncedPermissionSearch.value = value.trim()
    permissionSearchTimer = null
  }, 250)
})
watch(debouncedPermissionSearch, (value, previousValue) => {
  if (value !== '' && previousValue === '') {
    expandedBeforeSearch = new Set(expandedSections.value)
  } else if (value === '' && previousValue !== '' && expandedBeforeSearch) {
    expandedSections.value = new Set(expandedBeforeSearch)
    expandedBeforeSearch = null
  }
})
onMounted(() => {
  pageMounted = true
  syncSettingsAccessState()
})
onBeforeUnmount(() => {
  if (permissionSearchTimer) clearTimeout(permissionSearchTimer)
  if (drawerOpen.value) document.body.style.overflow = previousBodyOverflow
})
</script>

<style scoped>
.ym-works-settings-page {
  --ym-admin-section-accent: #22d3ee;
  --ym-admin-section-accent-secondary: #8b5cf6;
  --ym-admin-section-accent-strong: #0891b2;
  --ym-admin-section-accent-soft: rgba(34,211,238,.1);
  --ym-admin-section-highlight: #a78bfa;
  color: var(--ym-text);
}
.ym-works-settings-local-nav { display: grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: .65rem; border: 1px solid var(--ym-card-border); border-radius: 18px; background: color-mix(in srgb,var(--ym-card-bg) 88%,transparent); box-shadow: var(--ym-card-shadow),inset 0 1px rgba(255,255,255,.08); backdrop-filter: blur(14px); padding: .65rem; }
.ym-works-settings-local-nav a { display: flex; min-height: 42px; align-items: center; justify-content: center; gap: .5rem; border: 1px solid transparent; border-radius: 12px; color: color-mix(in srgb,var(--ym-muted) 86%,var(--ym-text) 14%); font-size: 12px; font-weight: 900; text-decoration: none; transition: border-color .18s ease,background .18s ease,color .18s ease,transform .18s ease; }
.ym-works-settings-local-nav a span { color: #22d3ee; font-size: 15px; }
.ym-works-settings-local-nav a:hover,.ym-works-settings-local-nav a:focus-visible { border-color: rgba(34,211,238,.36); outline: none; background: rgba(34,211,238,.09); color: var(--ym-text); transform: translateY(-1px); }
.ym-works-settings-section-anchor { scroll-margin-top: 180px; }
.ym-works-settings-section-anchor:target { outline: 2px solid rgba(34,211,238,.24); outline-offset: 4px; }
.ym-works-settings-operations { display: grid; gap: 1rem; }
.ym-works-settings-section-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; padding-inline: .25rem; }
.ym-works-settings-section-heading > div { display: flex; align-items: center; gap: .75rem; }
.ym-works-settings-section-heading > div > span { display: grid; width: 2.35rem; height: 2.35rem; place-items: center; border: 1px solid rgba(34,211,238,.34); border-radius: 12px; background: rgba(34,211,238,.1); color: #22d3ee; font-size: 11px; font-weight: 950; }
.ym-works-settings-section-heading p { max-width: 38rem; color: var(--ym-muted); font-size: 11px; font-weight: 800; line-height: 1.65; margin: 0; }
.ym-works-settings-section-heading > div p { color: #22d3ee; font-size: 10px; font-weight: 950; }
.ym-works-settings-section-heading h2 { color: var(--ym-text); font-size: 1.35rem; font-weight: 950; margin: .15rem 0 0; }
.ym-works-settings-result-card,.ym-works-settings-access-state,.ym-works-settings-card,.ym-works-settings-registry-card { position: relative; overflow: hidden; border: 1px solid var(--ym-card-border); border-radius: 30px; background: var(--ym-card-bg); box-shadow: var(--ym-card-shadow),inset 0 1px 0 rgba(255,255,255,.1); }
.ym-works-settings-hero__summary { display: grid; min-width: min(100%,220px); border: 1px solid var(--ym-soft-border); border-radius: 24px; background: var(--ym-control-bg); padding: 1rem; }
.ym-works-settings-hero__summary span,.ym-works-settings-hero__summary small { color: var(--ym-muted); font-size: 12px; font-weight: 850; }
.ym-works-settings-hero__summary strong { color: var(--ym-text); font-size: 2rem; font-weight: 950; }
.ym-works-settings-notices { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
.ym-works-settings-notice { display: flex; align-items: center; gap: .85rem; border: 1px solid var(--ym-soft-border); border-radius: 22px; background: var(--ym-control-bg); padding: 1rem 1.15rem; }
.ym-works-settings-notice > span { flex: 0 0 auto; border-radius: 999px; background: rgba(56,189,248,.14); color: #38bdf8; font-size: 12px; font-weight: 950; padding: .38rem .7rem; }
.ym-works-settings-notice.is-restriction > span { background: rgba(245,158,11,.13); color: #fbbf24; }
.ym-works-settings-notice p { color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.7; margin: 0; }
.ym-works-settings-state { display: grid; min-height: 250px; place-items: center; align-content: center; gap: .7rem; color: var(--ym-muted); padding: 2rem; text-align: center; }
.ym-works-settings-state h2,.ym-works-settings-access-state h2 { color: var(--ym-text); font-size: 1.2rem; font-weight: 950; margin: 0; }
.ym-works-settings-state p,.ym-works-settings-access-state p { max-width: 36rem; color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.7; margin: 0; }
.ym-works-settings-access-state { display: grid; min-height: 240px; place-items: center; align-content: center; gap: .7rem; padding: 2rem; text-align: center; }
.ym-works-settings-state.is-error,.ym-works-settings-access-state.is-forbidden { color: #fb7185; }
.ym-works-settings-state__icon,.ym-works-settings-empty-icon { display: grid; width: 3rem; height: 3rem; place-items: center; border-radius: 999px; background: rgba(244,63,94,.13); color: #fb7185; font-weight: 950; }
.ym-works-settings-empty-icon { background: rgba(148,163,184,.13); color: var(--ym-muted); }
.ym-works-settings-spinner { width: 2.35rem; height: 2.35rem; border: 3px solid rgba(14,165,233,.2); border-top-color: #0ea5e9; border-radius: 999px; animation: ym-works-settings-spin 760ms linear infinite; }
.ym-works-settings-button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border: 1px solid var(--ym-control-border); border-radius: 14px; background: var(--ym-control-bg); color: var(--ym-text); font-size: 13px; font-weight: 950; padding: .7rem 1rem; }
.ym-works-settings-info-card { display: flex; align-items: flex-start; gap: 1rem; border: 1px solid rgba(56,189,248,.34); border-radius: 24px; background: linear-gradient(135deg,rgba(56,189,248,.1),transparent),var(--ym-card-bg); padding: 1.15rem; }
.ym-works-settings-info-card.is-management { border-color: rgba(167,139,250,.34); background: linear-gradient(135deg,rgba(167,139,250,.1),transparent),var(--ym-card-bg); }
.ym-works-settings-info-card__icon { display: grid; flex: 0 0 auto; width: 2.4rem; height: 2.4rem; place-items: center; border-radius: 999px; background: rgba(56,189,248,.16); color: #38bdf8; font-weight: 950; }
.ym-works-settings-info-card > div { flex: 1; min-width: 0; }
.ym-works-settings-info-card h2 { color: var(--ym-text); font-size: 1.1rem; font-weight: 950; margin: 0; }
.ym-works-settings-info-card p { color: var(--ym-muted); font-size: 13px; font-weight: 800; line-height: 1.75; margin: .4rem 0 .8rem; }
.ym-works-settings-info-card dl { display: grid; gap: .5rem; margin: 0; }
.ym-works-settings-info-card dl div { display: flex; align-items: center; gap: .6rem; }
.ym-works-settings-info-card dt { color: var(--ym-muted); font-size: 11px; font-weight: 900; }
.ym-works-settings-info-card dd { margin: 0; }
.ym-works-settings-summary-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .85rem; }
.ym-works-settings-summary-card { display: grid; min-height: 116px; align-content: center; border: 1px solid color-mix(in srgb,var(--settings-accent) 24%,var(--ym-soft-border)); border-radius: 19px; background: linear-gradient(135deg,color-mix(in srgb,var(--settings-accent) 12%,transparent),transparent 54%),color-mix(in srgb,var(--ym-card-bg) 94%,transparent); box-shadow: var(--ym-card-shadow),inset 0 1px rgba(255,255,255,.08); padding: 1rem 1.1rem; }
.ym-works-settings-summary-card span,.ym-works-settings-summary-card small { display: block; color: color-mix(in srgb,var(--ym-muted) 86%,var(--ym-text) 14%); font-size: 12px; font-weight: 850; line-height: 1.45; }
.ym-works-settings-summary-card strong { display: block; color: var(--ym-text); font-size: 2.05rem; font-weight: 950; line-height: 1; margin: .45rem 0; font-variant-numeric: tabular-nums; }
.ym-works-settings-workspace-row { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1.08fr); align-items: start; gap: 1rem; }
.ym-works-settings-two-column { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
.ym-works-settings-workspace-row .ym-works-settings-two-column { grid-template-columns: 1fr; }
.ym-works-settings-card,.ym-works-settings-registry-card { padding: clamp(1rem,2.4vw,1.45rem); }
.ym-works-settings-card > header,.ym-works-settings-registry-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
.ym-works-settings-card header p,.ym-works-settings-registry-head p { color: #38bdf8; font-size: 11px; font-weight: 950; margin: 0 0 .25rem; }
.ym-works-settings-card h2,.ym-works-settings-registry-card h2 { color: var(--ym-text); font-size: 1.25rem; font-weight: 950; margin: 0; }
.ym-works-settings-section-badge { display: inline-flex; align-items: center; border: 1px solid rgba(56,189,248,.3); border-radius: 999px; background: rgba(56,189,248,.1); color: #38bdf8; font-family: ui-monospace,SFMono-Regular,Menlo,monospace; font-size: 10px; font-weight: 900; padding: .35rem .6rem; white-space: nowrap; }
.ym-works-settings-section-badge.is-capability { border-color: rgba(167,139,250,.32); background: rgba(167,139,250,.11); color: #c4b5fd; font-family: inherit; }
.ym-works-settings-role-group + .ym-works-settings-role-group { margin-top: 1rem; }
.ym-works-settings-role-group h3,.ym-works-settings-workflow-grid h3 { color: var(--ym-muted); font-size: 11px; font-weight: 950; margin: 0 0 .55rem; }
.ym-works-settings-role-group > div { display: flex; flex-wrap: wrap; gap: .5rem; }
.ym-works-settings-role { border: 1px solid var(--ym-soft-border); border-radius: 999px; padding: .38rem .65rem; }
.ym-works-settings-role code { color: inherit; font-size: 10px; font-weight: 900; }
.ym-works-settings-role.is-internal { border-color: rgba(16,185,129,.32); background: rgba(16,185,129,.11); color: #34d399; }
.ym-works-settings-role.is-forbidden { border-color: rgba(244,63,94,.34); background: rgba(244,63,94,.11); color: #fb7185; }
.ym-works-settings-definition-list { display: grid; gap: .6rem; margin: 1rem 0 0; }
.ym-works-settings-definition-list div { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid var(--ym-soft-border); border-radius: 15px; background: var(--ym-control-bg); padding: .7rem; }
.ym-works-settings-definition-list dt { color: var(--ym-muted); font-size: 11px; font-weight: 850; }
.ym-works-settings-definition-list dd { margin: 0; }
.ym-works-settings-boolean { display: inline-flex; min-width: 3rem; justify-content: center; border: 1px solid var(--ym-soft-border); border-radius: 999px; font-size: 10px; font-weight: 950; padding: .3rem .55rem; }
.ym-works-settings-boolean.is-yes { border-color: rgba(16,185,129,.34); background: rgba(16,185,129,.11); color: #34d399; }
.ym-works-settings-boolean.is-no { border-color: rgba(148,163,184,.28); background: rgba(148,163,184,.09); color: #94a3b8; }
.ym-works-settings-card-copy { color: var(--ym-muted); font-size: 12px; font-weight: 800; line-height: 1.7; margin: -.25rem 0 .8rem; }
.ym-works-settings-capabilities { display: grid; gap: .6rem; }
.ym-works-settings-capabilities article { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid var(--ym-soft-border); border-radius: 15px; background: var(--ym-control-bg); padding: .7rem; }
.ym-works-settings-capabilities strong,.ym-works-settings-capabilities code { display: block; }
.ym-works-settings-capabilities strong { color: var(--ym-text); font-size: 11px; font-weight: 900; }
.ym-works-settings-capabilities code { color: var(--ym-muted); font-size: 9px; margin-top: .2rem; }
.ym-works-settings-capability-badge { flex: 0 0 auto; border: 1px solid var(--ym-soft-border); border-radius: 999px; font-size: 10px; font-weight: 950; padding: .35rem .6rem; }
.ym-works-settings-capability-badge.is-available { border-color: rgba(16,185,129,.34); background: rgba(16,185,129,.11); color: #34d399; }
.ym-works-settings-capability-badge.is-unavailable { color: #94a3b8; }
.ym-works-settings-workflow-source { display: block; margin-top: 1rem; color: var(--ym-muted); font-size: 10px; overflow-wrap: anywhere; text-align: end; }
.ym-works-settings-workflow-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
.ym-works-settings-workflow-grid > article { border: 1px solid var(--ym-soft-border); border-radius: 18px; background: var(--ym-control-bg); padding: .85rem; }
.ym-works-settings-code-list { display: flex; flex-wrap: wrap; gap: .5rem; }
.ym-works-settings-code-list > span { display: grid; gap: .18rem; border: 1px solid var(--ym-soft-border); border-radius: 12px; background: color-mix(in srgb,var(--ym-card-bg) 75%,transparent); padding: .5rem .6rem; }
.ym-works-settings-code-list code { color: #c4b5fd; font-size: 10px; }
.ym-works-settings-code-list small { color: var(--ym-muted); font-size: 9px; font-weight: 800; }
.ym-works-settings-mutation-grid { display: grid; grid-template-columns: repeat(5,minmax(0,1fr)); gap: .6rem; }
.ym-works-settings-mutation-grid article { display: flex; align-items: center; justify-content: space-between; gap: .5rem; border: 1px solid var(--ym-soft-border); border-radius: 14px; background: var(--ym-control-bg); padding: .65rem; }
.ym-works-settings-mutation-grid article > span { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-works-settings-registry-head { align-items: center; }
.ym-works-settings-registry-head > div > span { display: block; color: var(--ym-muted); font-size: 11px; font-weight: 850; margin-top: .45rem; }
.ym-works-settings-registry-intro { display: flex; align-items: center; flex-wrap: wrap; gap: .55rem; margin-top: .55rem; }
.ym-works-settings-registry-copy { color: color-mix(in srgb,#22d3ee 72%,var(--ym-text) 28%); font-size: 12px; font-weight: 850; line-height: 1.6; }
.ym-works-settings-readonly-badge { display: inline-flex; align-items: center; border: 1px solid rgba(34,211,238,.34); border-radius: 999px; background: rgba(34,211,238,.1); color: #22d3ee; font-size: 10px; font-weight: 950; padding: .32rem .58rem; }
.ym-works-settings-registry-head code { color: #38bdf8; }
.ym-works-settings-registry-total { display: grid; min-width: 135px; border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-control-bg); padding: .65rem .8rem; }
.ym-works-settings-registry-total span,.ym-works-settings-registry-total small { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-works-settings-registry-total strong { color: var(--ym-text); font-size: 1.25rem; font-weight: 950; }
.ym-works-settings-explorer-toolbar { display: grid; gap: .75rem; border: 1px solid var(--ym-soft-border); border-radius: 20px; background: var(--ym-control-bg); padding: 1rem; }
.ym-works-settings-local-filters { display: grid; grid-template-columns: minmax(0,2fr) repeat(2,minmax(180px,1fr)); gap: .75rem; }
.ym-works-settings-local-filters label { display: grid; gap: .4rem; }
.ym-works-settings-local-filters label > span { color: var(--ym-muted); font-size: 11px; font-weight: 900; }
.ym-works-settings-local-filters label > small { color: var(--ym-muted); font-size: 9px; font-weight: 750; }
.ym-works-settings-local-filters input,.ym-works-settings-local-filters select { width: 100%; min-height: 45px; border: 1px solid var(--ym-control-border); border-radius: 14px; outline: none; background: var(--ym-card-bg); color: var(--ym-text); font-size: 12px; font-weight: 800; padding: .7rem .8rem; }
.ym-works-settings-local-filters input:focus,.ym-works-settings-local-filters select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,.13); }
.ym-works-settings-local-filters option { background: var(--ym-dropdown-bg); }
.ym-works-settings-explorer-actions,.ym-works-settings-explorer-actions > div { display: flex; align-items: center; gap: .55rem; }
.ym-works-settings-explorer-actions { justify-content: space-between; }
.ym-works-settings-explorer-actions button,.ym-works-settings-empty-action { min-height: 36px; border: 1px solid var(--ym-control-border); border-radius: 11px; background: color-mix(in srgb,var(--ym-card-bg) 86%,transparent); color: var(--ym-text); font-size: 10px; font-weight: 900; padding: .48rem .72rem; }
.ym-works-settings-explorer-actions button:hover,.ym-works-settings-explorer-actions button:focus-visible,.ym-works-settings-empty-action:hover,.ym-works-settings-empty-action:focus-visible { border-color: rgba(34,211,238,.48); outline: none; background: rgba(34,211,238,.1); color: #22d3ee; }
.ym-works-settings-explorer-actions button.is-reset { border-color: rgba(167,139,250,.3); color: #c4b5fd; }
.ym-works-settings-explorer-actions button:disabled { cursor: default; opacity: .58; }
.ym-works-settings-sections { display: grid; gap: .7rem; margin-top: 1rem; }
.ym-works-settings-permission-section { border: 1px solid var(--ym-soft-border); border-radius: 18px; background: color-mix(in srgb,var(--ym-control-bg) 72%,transparent); overflow: hidden; }
.ym-works-settings-permission-section__heading { margin: 0; }
.ym-works-settings-accordion-trigger { display: flex; width: 100%; min-height: 62px; align-items: center; justify-content: space-between; gap: 1rem; border: 0; background: transparent; color: var(--ym-text); padding: .8rem 1rem; text-align: start; }
.ym-works-settings-accordion-trigger:hover { background: rgba(56,189,248,.065); }
.ym-works-settings-accordion-trigger:focus-visible { outline: 3px solid rgba(34,211,238,.28); outline-offset: -3px; }
.ym-works-settings-accordion-copy { display: grid; min-width: 0; gap: .2rem; }
.ym-works-settings-accordion-title { color: var(--ym-text); font-size: 14px; font-weight: 950; }
.ym-works-settings-accordion-copy code { color: var(--ym-muted); font-size: 9px; overflow-wrap: anywhere; }
.ym-works-settings-accordion-meta { display: flex; flex: 0 0 auto; align-items: center; gap: .65rem; }
.ym-works-settings-accordion-meta strong { display: grid; min-width: 2rem; min-height: 2rem; place-items: center; border-radius: 10px; background: rgba(56,189,248,.1); color: #38bdf8; font-size: 11px; }
.ym-works-settings-accordion-chevron { color: var(--ym-muted); font-size: 1.25rem; transition: transform .2s ease; }
.ym-works-settings-accordion-trigger[aria-expanded="true"] .ym-works-settings-accordion-chevron { transform: rotate(180deg); }
.ym-works-settings-accordion-panel { border-top: 1px solid var(--ym-soft-border); padding: .8rem; }
.ym-works-settings-permission-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .75rem; }
.ym-works-settings-permission-card { display: grid; min-width: 0; grid-template-rows: 1fr auto auto; gap: .6rem; border: 1px solid var(--ym-soft-border); border-radius: 15px; background: var(--ym-card-bg); padding: .75rem; }
.ym-works-settings-permission-card__body { min-width: 0; }
.ym-works-settings-permission-card h4 { color: var(--ym-text); font-size: 13px; font-weight: 950; line-height: 1.5; margin: 0 0 .25rem; }
.ym-works-settings-permission-card p { color: var(--ym-muted); font-size: 10px; font-weight: 750; line-height: 1.55; margin: 0; }
.ym-works-settings-permission-card__meta { display: flex; min-width: 0; align-items: center; justify-content: space-between; gap: .5rem; }
.ym-works-settings-permission-card__meta .ym-works-settings-section-badge { max-width: 42%; overflow: hidden; text-overflow: ellipsis; }
.ym-works-settings-permission-card__meta code { min-width: 0; color: #c4b5fd; font-size: 9px; font-weight: 850; overflow-wrap: anywhere; text-align: end; }
.ym-works-settings-details-button { width: 100%; min-height: 36px; border: 1px solid rgba(14,165,233,.36); border-radius: 11px; background: rgba(14,165,233,.09); color: #38bdf8; font-size: 10px; font-weight: 950; padding: .48rem .65rem; }
.ym-works-settings-details-button:hover,.ym-works-settings-details-button:focus-visible { border-color: rgba(34,211,238,.62); outline: none; background: rgba(34,211,238,.15); box-shadow: 0 0 0 3px rgba(34,211,238,.1); color: #67e8f9; }
.ym-works-settings-empty { display: flex; min-height: 190px; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; text-align: start; }
.ym-works-settings-empty > span { display: grid; width: 3rem; height: 3rem; place-items: center; border-radius: 999px; background: rgba(148,163,184,.13); color: var(--ym-muted); font-weight: 950; }
.ym-works-settings-empty h3 { color: var(--ym-text); font-size: 1rem; font-weight: 950; margin: 0; }
.ym-works-settings-empty p { color: var(--ym-muted); font-size: 12px; font-weight: 800; margin: .3rem 0 0; }
.ym-works-settings-empty-action { margin-top: .75rem; }
.ym-settings-detail-backdrop { position: fixed; inset: 0; z-index: 120; display: flex; justify-content: flex-end; background: rgba(2,6,23,.68); backdrop-filter: blur(6px); }
.ym-settings-detail-drawer { width: min(650px,100%); height: 100dvh; overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-dropdown-bg); box-shadow: -24px 0 64px rgba(2,6,23,.38); color: var(--ym-text); }
.ym-settings-detail-drawer__head { position: sticky; top: 0; z-index: 4; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--ym-soft-border); background: color-mix(in srgb,var(--ym-dropdown-bg) 92%,transparent); backdrop-filter: blur(18px); padding: 1.2rem 1.35rem; }
.ym-settings-detail-drawer__head span,.ym-settings-detail-drawer__head code { display: block; color: var(--ym-muted); font-size: 10px; font-weight: 850; overflow-wrap: anywhere; }
.ym-settings-detail-drawer__head h2 { color: var(--ym-text); font-size: 1.35rem; font-weight: 950; margin: .2rem 0; }
.ym-settings-detail-drawer__close { display: grid; flex: 0 0 auto; width: 42px; height: 42px; place-items: center; border: 1px solid var(--ym-control-border); border-radius: 14px; background: var(--ym-control-bg); color: var(--ym-text); font-size: 1.45rem; }
.ym-settings-detail-drawer__close:hover,.ym-settings-detail-drawer__close:focus-visible { border-color: rgba(34,211,238,.5); outline: none; box-shadow: 0 0 0 3px rgba(34,211,238,.12); color: #22d3ee; }
.ym-settings-detail-content { display: grid; gap: 1rem; padding: 1.25rem; }
.ym-settings-detail-intro,.ym-settings-detail-section { border: 1px solid var(--ym-soft-border); border-radius: 22px; background: var(--ym-card-bg); padding: 1rem; }
.ym-settings-detail-intro > div { display: flex; flex-wrap: wrap; gap: .45rem; }
.ym-settings-detail-intro h3 { color: var(--ym-text); font-size: 1.3rem; font-weight: 950; margin: .8rem 0 .3rem; }
.ym-settings-detail-intro > code { color: #c4b5fd; font-size: 10px; overflow-wrap: anywhere; }
.ym-settings-detail-section > h3 { color: var(--ym-text); font-size: 1rem; font-weight: 950; margin: 0 0 .8rem; }
.ym-settings-detail-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .65rem; margin: 0; }
.ym-settings-detail-grid > div { min-width: 0; border: 1px solid var(--ym-soft-border); border-radius: 15px; background: var(--ym-control-bg); padding: .7rem; }
.ym-settings-detail-grid > div.is-wide { grid-column: 1 / -1; }
.ym-settings-detail-grid dt { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-settings-detail-grid dd { color: var(--ym-text); font-size: 12px; font-weight: 900; line-height: 1.65; margin: .3rem 0 0; overflow-wrap: anywhere; }
@keyframes ym-works-settings-spin { to { transform: rotate(360deg); } }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-registry-card) { border-color: color-mix(in srgb,var(--ym-card-border) 82%,#0891b2 18%); }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card) { background: linear-gradient(135deg,color-mix(in srgb,var(--settings-accent) 9%,transparent),transparent 58%),color-mix(in srgb,var(--ym-card-bg) 96%,#e8f7fb 4%); }
:global(body:has(.ym-works-settings-page) .ym-watermark-glow) { opacity: .035; }
:global(html:has(.ym-works-settings-page)),
:global(body:has(.ym-works-settings-page)) { overflow-x: clip; }
:global(.ym-dashboard-light .ym-works-settings-page) {
  --ym-text: #172033;
  --ym-muted: #4b5568;
  --ym-card-bg: rgba(248,250,253,.94);
  --ym-dropdown-bg: #f8fafc;
  --ym-control-bg: rgba(255,255,255,.88);
  --ym-card-border: rgba(99,102,241,.3);
  --ym-soft-border: rgba(71,85,105,.23);
  --ym-control-border: rgba(71,85,105,.34);
  --ym-card-shadow: 0 14px 34px rgba(51,65,85,.11);
  --ym-admin-section-accent: #0891b2;
  --ym-admin-accent-electric: #06b6d4;
  --ym-admin-section-accent-soft: rgba(8,145,178,.13);
  background: linear-gradient(145deg,rgba(238,242,255,.88),rgba(240,249,255,.58) 48%,rgba(245,243,255,.82));
  border-radius: 28px;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-admin-page-hero) {
  border-color: rgba(8,145,178,.34);
  background: linear-gradient(135deg,rgba(236,254,255,.94),rgba(248,250,252,.94) 52%,rgba(245,243,255,.92));
  box-shadow: 0 18px 42px rgba(51,65,85,.12),inset 0 1px rgba(255,255,255,.92);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-admin-page-hero h1),
:global(.ym-dashboard-light .ym-works-settings-page .ym-admin-page-hero__icon) { color: #0786a0; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav) {
  border-color: rgba(99,102,241,.28);
  background: rgba(248,250,253,.94);
  box-shadow: 0 10px 26px rgba(51,65,85,.09),inset 0 1px rgba(255,255,255,.9);
  backdrop-filter: blur(5px);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav a) {
  border-color: rgba(71,85,105,.12);
  background: rgba(255,255,255,.64);
  color: #475569;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav a:hover),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav a:focus-visible),
:global(.ym-dashboard-light .ym-works-settings-page:has(#settings-operations:target) .ym-works-settings-local-nav a[href="#settings-operations"]),
:global(.ym-dashboard-light .ym-works-settings-page:has(#settings-workflow:target) .ym-works-settings-local-nav a[href="#settings-workflow"]),
:global(.ym-dashboard-light .ym-works-settings-page:has(#settings-access:target) .ym-works-settings-local-nav a[href="#settings-access"]),
:global(.ym-dashboard-light .ym-works-settings-page:has(#settings-permissions:target) .ym-works-settings-local-nav a[href="#settings-permissions"]) {
  border-color: rgba(8,145,178,.5);
  background: rgba(207,250,254,.74);
  color: #0e7490;
  box-shadow: 0 5px 14px rgba(8,145,178,.1);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-notice),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-info-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-registry-card) {
  border-color: rgba(99,102,241,.28);
  background-color: rgba(248,250,253,.94);
  box-shadow: 0 14px 34px rgba(51,65,85,.1),inset 0 1px rgba(255,255,255,.9);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-info-card) {
  border-color: rgba(8,145,178,.35);
  background: linear-gradient(135deg,rgba(207,250,254,.56),rgba(248,250,253,.94) 40%);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-info-card.is-management) {
  border-color: rgba(99,102,241,.32);
  background: linear-gradient(135deg,rgba(224,231,255,.62),rgba(248,250,253,.94) 42%);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card) {
  border-color: color-mix(in srgb,var(--settings-accent) 34%,rgba(71,85,105,.24));
  background: linear-gradient(135deg,color-mix(in srgb,var(--settings-accent) 10%,rgba(255,255,255,.94)),rgba(248,250,253,.94) 58%);
  box-shadow: 0 10px 24px rgba(51,65,85,.09),inset 0 1px rgba(255,255,255,.95);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card strong) { color: #111827; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card span),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card small),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-section-heading p),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-card-copy),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-notice p),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-info-card p) { color: #4b5568; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-definition-list div),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-capabilities article),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-workflow-grid > article),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-mutation-grid article),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-registry-total),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-explorer-toolbar) {
  border-color: rgba(71,85,105,.24);
  background: rgba(255,255,255,.88);
  box-shadow: 0 5px 14px rgba(51,65,85,.055);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-boolean.is-yes),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-capability-badge.is-available) {
  border-color: rgba(5,150,105,.45);
  background: rgba(209,250,229,.82);
  color: #047857;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-boolean.is-no),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-capability-badge.is-unavailable) {
  border-color: rgba(190,24,93,.28);
  background: rgba(255,228,230,.66);
  color: #9f1239;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters label > span),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters label > small) { color: #475569; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters input),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters select) {
  border-color: rgba(71,85,105,.34);
  background: rgba(255,255,255,.97);
  box-shadow: inset 0 1px rgba(255,255,255,.95),0 3px 10px rgba(51,65,85,.055);
  color: #172033;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-section) {
  border-color: rgba(71,85,105,.27);
  background: rgba(241,245,249,.9);
  box-shadow: 0 5px 15px rgba(51,65,85,.055);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-trigger) {
  background: rgba(248,250,252,.9);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-trigger:hover),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-trigger[aria-expanded="true"]) {
  background: rgba(224,242,254,.76);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-title) { color: #172033; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-copy code),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-chevron) { color: #526078; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-meta strong) {
  border: 1px solid rgba(8,145,178,.28);
  background: rgba(207,250,254,.8);
  color: #0e7490;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-panel) { border-color: rgba(71,85,105,.24); }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-card) {
  border-color: rgba(71,85,105,.25);
  background: rgba(255,255,255,.94);
  box-shadow: 0 7px 18px rgba(51,65,85,.075);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-card p) { color: #4b5568; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-card__meta code) { color: #6652a3; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-details-button) {
  border-color: rgba(8,145,178,.48);
  background: rgba(207,250,254,.7);
  color: #0e7490;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-explorer-actions button:disabled) { color: #64748b; opacity: .78; }
:global(.ym-dashboard-light .ym-works-settings-page .ym-settings-detail-drawer) {
  border-color: rgba(99,102,241,.3);
  background: #f8fafc;
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-settings-detail-drawer__head) {
  border-color: rgba(71,85,105,.24);
  background: rgba(248,250,252,.96);
  backdrop-filter: blur(6px);
}
:global(.ym-dashboard-light .ym-works-settings-page .ym-settings-detail-intro),
:global(.ym-dashboard-light .ym-works-settings-page .ym-settings-detail-section),
:global(.ym-dashboard-light .ym-works-settings-page .ym-settings-detail-grid > div) {
  border-color: rgba(71,85,105,.24);
  background: rgba(255,255,255,.92);
  box-shadow: 0 5px 14px rgba(51,65,85,.055);
}
:global(body.ym-dashboard-light:has(.ym-works-settings-page) .ym-background-watermark),
:global(.ym-dashboard-light:has(.ym-works-settings-page) .ym-background-watermark) { opacity: .24; }
:global(body.ym-dashboard-light:has(.ym-works-settings-page) .ym-watermark-glow),
:global(.ym-dashboard-light:has(.ym-works-settings-page) .ym-watermark-glow) { opacity: .012; }
@media (max-width: 1180px) { .ym-works-settings-permission-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-mutation-grid { grid-template-columns: repeat(3,minmax(0,1fr)); } }
@media (max-width: 1020px) { .ym-works-settings-summary-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-summary-card:last-child:nth-child(odd) { grid-column: 1 / -1; } .ym-works-settings-workspace-row { grid-template-columns: 1fr; } }
@media (max-width: 900px) { .ym-works-settings-local-nav { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-card > header,.ym-works-settings-registry-head,.ym-works-settings-section-heading { align-items: stretch; flex-direction: column; } .ym-works-settings-hero__summary { min-width: 0; } .ym-works-settings-two-column,.ym-works-settings-workflow-grid { grid-template-columns: 1fr; } .ym-works-settings-workflow-source { text-align: start; } .ym-works-settings-local-filters { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-local-filters .is-search { grid-column: 1 / -1; } }
@media (max-width: 640px) { .ym-works-settings-result-card,.ym-works-settings-access-state,.ym-works-settings-card,.ym-works-settings-registry-card { border-radius: 22px; } .ym-works-settings-notices,.ym-works-settings-summary-grid,.ym-works-settings-local-filters,.ym-works-settings-permission-grid,.ym-works-settings-mutation-grid,.ym-settings-detail-grid { grid-template-columns: 1fr; } .ym-works-settings-summary-card:last-child:nth-child(odd) { grid-column: auto; } .ym-works-settings-local-nav { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-local-nav a { min-width: 0; padding-inline: .35rem; } .ym-works-settings-notice,.ym-works-settings-info-card { align-items: flex-start; } .ym-works-settings-local-filters .is-search { grid-column: auto; } .ym-works-settings-explorer-actions { align-items: stretch; flex-direction: column; } .ym-works-settings-explorer-actions > div { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-explorer-actions button { width: 100%; } .ym-works-settings-accordion-trigger { min-height: 56px; padding: .7rem .75rem; } .ym-works-settings-accordion-panel { padding: .6rem; } .ym-works-settings-permission-card { grid-template-columns: minmax(0,1fr) auto; grid-template-rows: auto auto; align-items: center; padding: .7rem; } .ym-works-settings-permission-card__body { grid-column: 1; grid-row: 1; } .ym-works-settings-permission-card__meta { grid-column: 1; grid-row: 2; align-items: flex-start; flex-direction: column; } .ym-works-settings-permission-card__meta .ym-works-settings-section-badge { max-width: 100%; } .ym-works-settings-permission-card__meta code { max-width: 100%; text-align: start; } .ym-works-settings-details-button { grid-column: 2; grid-row: 1 / 3; width: auto; min-width: 86px; } .ym-settings-detail-grid > div.is-wide { grid-column: auto; } .ym-settings-detail-drawer { width: min(100%,520px); } .ym-settings-detail-drawer__head,.ym-settings-detail-content { padding-inline: 1rem; } }
@media (prefers-reduced-motion: reduce) { .ym-works-settings-spinner { animation-duration: 1.8s; } }

/* ============================================================
   YM_SETTINGS_UNIFIED_VISUAL_V4
   Unified local visual layer for the settings workspaces.
   ============================================================ */

/* ---------- Shared workspace language ---------- */

.ym-works-settings-page {
  --ws-accent: #0891b2;
  --ws-accent-bright: #22d3ee;
  --ws-violet: #7c3aed;
  --ws-border: color-mix(in srgb, var(--ym-text) 17%, var(--ym-card-border));
  --ws-border-soft: color-mix(in srgb, var(--ym-text) 11%, var(--ym-soft-border));
  --ws-surface: color-mix(in srgb, var(--ym-card-bg) 97%, var(--ym-control-bg));
  --ws-surface-soft: color-mix(in srgb, var(--ym-control-bg) 86%, var(--ym-card-bg));
  min-width: 0;
  overflow-x: clip;
}

.ym-works-settings-page :where(button, a, input, select):focus-visible {
  outline: 3px solid color-mix(in srgb, var(--ws-accent-bright) 34%, transparent);
  outline-offset: 2px;
}

.ym-works-settings-approved-hero {
  border-color: color-mix(in srgb, var(--ws-accent) 30%, var(--ym-admin-border-strong));
}

.ym-works-settings-hero__summary {
  min-width: 174px;
  border-color: color-mix(in srgb, var(--ws-accent) 28%, var(--ym-soft-border));
  border-radius: 16px;
  background: color-mix(in srgb, var(--ym-control-bg) 94%, transparent);
  padding: .68rem .85rem;
}

.ym-works-settings-hero__summary strong {
  color: color-mix(in srgb, var(--ws-accent-bright) 74%, var(--ym-text));
  font-size: 1.65rem;
  line-height: 1.05;
  margin-block: .12rem;
}

.ym-works-settings-hero__summary span,
.ym-works-settings-hero__summary small {
  font-size: 11px;
  line-height: 1.4;
}

/* ---------- Compact workspace tabs ---------- */

.ym-works-settings-local-nav {
  position: relative;
  z-index: 2;
  gap: 0;
  overflow: hidden;
  border-color: var(--ws-border);
  border-radius: 15px;
  background: var(--ws-surface);
  padding: 4px;
  backdrop-filter: blur(10px);
}

.ym-works-settings-local-nav a {
  position: relative;
  min-height: 42px;
  border-radius: 10px;
  padding: .45rem .65rem;
  color: color-mix(in srgb, var(--ym-muted) 76%, var(--ym-text));
  font-size: 12px;
}

.ym-works-settings-local-nav a::after {
  position: absolute;
  inset-inline: 22%;
  inset-block-end: 2px;
  height: 2px;
  border-radius: 999px;
  background: transparent;
  content: "";
}

.ym-works-settings-local-nav a:hover,
.ym-works-settings-local-nav a:focus-visible {
  border-color: color-mix(in srgb, var(--ws-accent) 28%, transparent);
  background: color-mix(in srgb, var(--ws-accent) 8%, transparent);
  color: var(--ym-text);
  transform: none;
}

.ym-works-settings-page:not(:has(.ym-works-settings-section-anchor:target))
  .ym-works-settings-local-nav a[href="#settings-operations"],
.ym-works-settings-page:has(#settings-operations:target)
  .ym-works-settings-local-nav a[href="#settings-operations"],
.ym-works-settings-page:has(#settings-workflow:target)
  .ym-works-settings-local-nav a[href="#settings-workflow"],
.ym-works-settings-page:has(#settings-access:target)
  .ym-works-settings-local-nav a[href="#settings-access"],
.ym-works-settings-page:has(#settings-permissions:target)
  .ym-works-settings-local-nav a[href="#settings-permissions"] {
  border-color: color-mix(in srgb, var(--ws-accent) 36%, transparent);
  background: color-mix(in srgb, var(--ws-accent) 12%, var(--ym-control-bg));
  color: color-mix(in srgb, var(--ws-accent-bright) 70%, var(--ym-text));
}

.ym-works-settings-page:not(:has(.ym-works-settings-section-anchor:target))
  .ym-works-settings-local-nav a[href="#settings-operations"]::after,
.ym-works-settings-page:has(#settings-operations:target)
  .ym-works-settings-local-nav a[href="#settings-operations"]::after,
.ym-works-settings-page:has(#settings-workflow:target)
  .ym-works-settings-local-nav a[href="#settings-workflow"]::after,
.ym-works-settings-page:has(#settings-access:target)
  .ym-works-settings-local-nav a[href="#settings-access"]::after,
.ym-works-settings-page:has(#settings-permissions:target)
  .ym-works-settings-local-nav a[href="#settings-permissions"]::after {
  background: var(--ws-accent-bright);
}

/* ---------- One visible workspace at a time ---------- */

.ym-works-settings-summary-grid,
.ym-works-settings-workspace-row,
.ym-works-settings-registry-card {
  display: none;
}

.ym-works-settings-page:has(#settings-workflow:target) .ym-works-settings-notices,
.ym-works-settings-page:has(#settings-access:target) .ym-works-settings-notices,
.ym-works-settings-page:has(#settings-permissions:target) .ym-works-settings-notices,
.ym-works-settings-page:has(#settings-workflow:target) .ym-works-settings-operations,
.ym-works-settings-page:has(#settings-access:target) .ym-works-settings-operations,
.ym-works-settings-page:has(#settings-permissions:target) .ym-works-settings-operations {
  display: none;
}

.ym-works-settings-page:has(#settings-access:target) .ym-works-settings-summary-grid {
  display: grid;
}

.ym-works-settings-page:has(#settings-access:target) .ym-works-settings-workspace-row,
.ym-works-settings-page:has(#settings-workflow:target) .ym-works-settings-workspace-row {
  display: block;
}

.ym-works-settings-page:has(#settings-access:target) #settings-workflow,
.ym-works-settings-page:has(#settings-workflow:target) #settings-access {
  display: none;
}

.ym-works-settings-page:has(#settings-permissions:target) .ym-works-settings-registry-card {
  display: block;
}

/* ---------- Context and operations ---------- */

.ym-works-settings-notices {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0;
  overflow: hidden;
  border: 1px solid var(--ws-border);
  border-radius: 16px;
  background: var(--ws-surface);
  box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
}

.ym-works-settings-notice {
  min-width: 0;
  border: 0;
  border-radius: 0;
  background: transparent;
  padding: .72rem .9rem;
}

.ym-works-settings-notice + .ym-works-settings-notice {
  border-inline-start: 1px solid var(--ws-border-soft);
}

.ym-works-settings-notice > span {
  background: color-mix(in srgb, var(--ws-accent) 14%, transparent);
  color: color-mix(in srgb, var(--ws-accent-bright) 76%, var(--ym-text));
  font-size: 10.5px;
  padding: .3rem .6rem;
}

.ym-works-settings-notice.is-restriction > span {
  border: 1px solid color-mix(in srgb, #f59e0b 28%, transparent);
  background: color-mix(in srgb, #f59e0b 12%, transparent);
  color: #f59e0b;
}

.ym-works-settings-notice p {
  font-size: 11.5px;
  line-height: 1.6;
}

.ym-works-settings-operations {
  gap: .8rem;
}

.ym-works-settings-section-heading {
  align-items: center;
  border-bottom: 1px solid var(--ws-border-soft);
  padding: .35rem .2rem .65rem;
}

.ym-works-settings-section-heading h2 {
  font-size: 1.18rem;
}

.ym-works-settings-section-heading > div > span {
  width: 2.15rem;
  height: 2.15rem;
  border-radius: 10px;
}

.ym-works-settings-section-heading > p {
  font-size: 11.5px;
}

.ym-works-settings-readiness {
  display: grid;
  grid-template-columns: minmax(250px, .72fr) minmax(0, 1.28fr);
  align-items: stretch;
  overflow: hidden;
  border: 1px solid var(--ws-border);
  border-radius: 18px;
  background: var(--ws-surface);
  box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
}

.ym-works-settings-readiness > section {
  min-width: 0;
  padding: .8rem .9rem;
}

.ym-works-settings-readiness__storage {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: .75rem;
  border-inline-end: 1px solid var(--ws-border-soft);
}

.ym-works-settings-readiness__storage > div > span,
.ym-works-settings-readiness__scope > div:first-child > span {
  color: color-mix(in srgb, var(--ws-accent-bright) 72%, var(--ym-text));
  font-size: 10px;
  font-weight: 950;
}

.ym-works-settings-readiness h3 {
  margin: .12rem 0;
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 950;
}

.ym-works-settings-readiness p {
  margin: 0;
  color: var(--ym-muted);
  font-size: 11px;
  font-weight: 780;
  line-height: 1.55;
}

.ym-works-settings-readiness dl {
  display: grid;
  align-content: center;
  gap: .35rem;
  margin: 0;
}

.ym-works-settings-readiness dl > div {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .5rem;
}

.ym-works-settings-readiness dt {
  color: var(--ym-muted);
  font-size: 10px;
  font-weight: 850;
}

.ym-works-settings-readiness dd {
  margin: 0;
}

.ym-works-settings-readiness code {
  color: color-mix(in srgb, var(--ws-violet) 66%, var(--ym-text));
  font-size: 10px;
}

.ym-works-settings-readiness__scope {
  display: grid;
  gap: .55rem;
}

.ym-works-settings-mutation-grid {
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: .4rem;
}

.ym-works-settings-mutation-grid article {
  min-height: 38px;
  border-color: var(--ws-border-soft);
  border-radius: 10px;
  background: var(--ws-surface-soft);
  padding: .42rem .5rem;
}

.ym-works-settings-mutation-grid article > span {
  font-size: 9.5px;
  line-height: 1.35;
}

/* ---------- Existing editor, visually unified ---------- */

.ym-works-settings-operations :deep(.ym-settings-editor) {
  border-color: var(--ws-border);
  border-radius: 20px;
  background: var(--ws-surface);
  box-shadow: 0 10px 26px rgba(15, 23, 42, .07);
}

.ym-works-settings-operations :deep(.ym-settings-editor__head) {
  padding: .85rem 1rem .7rem;
}

.ym-works-settings-operations :deep(.ym-settings-editor__head h2) {
  font-size: 1.2rem;
}

.ym-works-settings-operations :deep(.ym-settings-editor__cards) {
  gap: .75rem;
}

.ym-works-settings-operations :deep(.ym-settings-editor-group) {
  border-color: var(--ws-border-soft);
  border-radius: 16px;
  background: var(--ws-surface-soft);
  box-shadow: none;
}

.ym-works-settings-operations :deep(.ym-settings-editor-group__head h3) {
  font-size: 15px;
}

.ym-works-settings-operations :deep(.ym-settings-editor-card) {
  border-color: var(--ws-border-soft);
  border-radius: 13px;
  background: color-mix(in srgb, var(--ym-card-bg) 96%, var(--ym-control-bg));
  box-shadow: none;
}

.ym-works-settings-operations :deep(.ym-settings-editor-card h3),
.ym-works-settings-operations :deep(.ym-settings-editor-field > span) {
  font-size: 13px;
}

.ym-works-settings-operations :deep(input),
.ym-works-settings-operations :deep(select) {
  min-height: 41px;
}

.ym-works-settings-operations :deep(input:focus),
.ym-works-settings-operations :deep(select:focus),
.ym-works-settings-operations :deep(button:focus-visible) {
  border-color: var(--ws-accent);
  outline: none;
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--ws-accent-bright) 20%, transparent);
}

.ym-works-settings-operations :deep(.ym-settings-editor__actions) {
  position: static;
  border-color: var(--ws-border-soft);
  background: var(--ws-surface-soft);
  box-shadow: none;
}

/* ---------- Access workspace ---------- */

.ym-works-settings-summary-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .7rem;
}

.ym-works-settings-summary-card {
  min-height: 92px;
  border-color: color-mix(in srgb, var(--settings-accent) 24%, var(--ws-border));
  border-radius: 16px;
  background:
    linear-gradient(135deg, color-mix(in srgb, var(--settings-accent) 9%, transparent), transparent 52%),
    var(--ws-surface);
  box-shadow: 0 7px 20px rgba(15, 23, 42, .055);
  padding: .78rem .9rem;
}

.ym-works-settings-summary-card strong {
  font-size: 1.72rem;
  margin: .3rem 0;
}

.ym-works-settings-summary-card span,
.ym-works-settings-summary-card small {
  font-size: 11px;
}

.ym-works-settings-two-column {
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
}

.ym-works-settings-workspace-row .ym-works-settings-two-column {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.ym-works-settings-two-column > .ym-works-settings-card {
  height: auto;
  align-self: start;
}

.ym-works-settings-card,
.ym-works-settings-registry-card {
  border-color: var(--ws-border);
  border-radius: 20px;
  background: var(--ws-surface);
  box-shadow: 0 9px 26px rgba(15, 23, 42, .065);
}

.ym-works-settings-card {
  padding: 1rem;
}

.ym-works-settings-card > header {
  margin-bottom: .75rem;
}

.ym-works-settings-definition-list div,
.ym-works-settings-capabilities article {
  min-height: 44px;
  border-color: var(--ws-border-soft);
  border-radius: 12px;
  background: var(--ws-surface-soft);
  padding: .55rem .65rem;
}

.ym-works-settings-capabilities {
  gap: .45rem;
}

.ym-works-settings-capabilities code {
  font-size: 9.5px;
}

/* ---------- Workflow workspace ---------- */

.ym-works-settings-workflow-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
  align-items: start;
  gap: .7rem;
}

.ym-works-settings-workflow-grid > article {
  height: auto;
  align-self: start;
  border-color: var(--ws-border-soft);
  border-radius: 14px;
  background: var(--ws-surface-soft);
  padding: .72rem;
}

.ym-works-settings-workflow-grid h3 {
  color: var(--ym-text);
  font-size: 12px;
}

.ym-works-settings-code-list {
  gap: .4rem;
}

.ym-works-settings-code-list > span {
  width: 100%;
  border-color: var(--ws-border-soft);
  border-radius: 10px;
  background: color-mix(in srgb, var(--ym-card-bg) 88%, transparent);
  padding: .42rem .5rem;
}

.ym-works-settings-code-list code {
  color: color-mix(in srgb, var(--ws-violet) 62%, var(--ym-text));
  font-size: 9.5px;
}

.ym-works-settings-code-list small {
  color: color-mix(in srgb, var(--ym-muted) 80%, var(--ym-text));
  font-size: 10.5px;
}

/* ---------- Permission explorer ---------- */

.ym-works-settings-registry-card {
  padding: 1rem;
}

.ym-works-settings-registry-head {
  margin-bottom: .75rem;
}

.ym-works-settings-explorer-toolbar {
  grid-template-columns: minmax(0, 1fr) auto;
  align-items: end;
  gap: .65rem;
  border-color: var(--ws-border-soft);
  border-radius: 15px;
  background: var(--ws-surface-soft);
  padding: .72rem;
}

.ym-works-settings-local-filters {
  grid-template-columns: minmax(210px, 1.5fr) repeat(2, minmax(150px, .75fr));
  gap: .6rem;
}

.ym-works-settings-local-filters input,
.ym-works-settings-local-filters select {
  min-height: 41px;
  border-radius: 11px;
  padding: .55rem .7rem;
}

.ym-works-settings-local-filters label > span {
  font-size: 10.5px;
}

.ym-works-settings-local-filters label > small {
  font-size: 9.5px;
}

.ym-works-settings-explorer-actions {
  align-items: flex-end;
  justify-content: flex-end;
}

.ym-works-settings-sections {
  gap: .55rem;
  margin-top: .75rem;
}

.ym-works-settings-permission-section {
  border-color: var(--ws-border-soft);
  border-radius: 14px;
  background: var(--ws-surface-soft);
}

.ym-works-settings-accordion-trigger {
  min-height: 46px;
  padding: .55rem .8rem;
}

.ym-works-settings-accordion-trigger[aria-expanded="true"] {
  background: color-mix(in srgb, var(--ws-accent) 10%, var(--ym-control-bg));
}

.ym-works-settings-accordion-trigger[aria-expanded="true"]
  .ym-works-settings-accordion-chevron {
  color: var(--ws-accent-bright);
}

.ym-works-settings-accordion-panel {
  border-color: var(--ws-border-soft);
  padding: .7rem;
}

.ym-works-settings-permission-grid {
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: .65rem;
}

.ym-works-settings-permission-card {
  min-height: 142px;
  border-color: var(--ws-border-soft);
  border-radius: 13px;
  background: color-mix(in srgb, var(--ym-card-bg) 96%, var(--ym-control-bg));
  box-shadow: none;
  padding: .72rem;
}

.ym-works-settings-permission-card h4 {
  font-size: 13px;
}

.ym-works-settings-permission-card p {
  font-size: 11px;
  line-height: 1.55;
}

.ym-works-settings-details-button {
  min-height: 36px;
  border-color: color-mix(in srgb, var(--ws-accent) 34%, var(--ym-control-border));
  border-radius: 10px;
  background: color-mix(in srgb, var(--ws-accent) 9%, var(--ym-control-bg));
  color: color-mix(in srgb, var(--ws-accent-bright) 68%, var(--ym-text));
}

/* ---------- Light mode, scoped safely ---------- */

:global(.ym-dashboard-light .ym-works-settings-page) {
  background:
    linear-gradient(145deg, rgba(238, 242, 255, .82), rgba(240, 249, 255, .54) 48%, rgba(245, 243, 255, .76));
  border-radius: 26px;
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-approved-hero) {
  border-color: rgba(8, 145, 178, .34);
  background: linear-gradient(135deg, rgba(236, 254, 255, .96), rgba(248, 250, 252, .96) 54%, rgba(245, 243, 255, .94));
  box-shadow: 0 16px 38px rgba(51, 65, 85, .11), inset 0 1px rgba(255, 255, 255, .94);
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-nav),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-notices),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-readiness),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-registry-card) {
  border-color: rgba(71, 85, 105, .25);
  background: rgba(248, 250, 253, .96);
  box-shadow: 0 10px 28px rgba(51, 65, 85, .09), inset 0 1px rgba(255, 255, 255, .94);
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-definition-list div),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-capabilities article),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-workflow-grid > article),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-section),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-explorer-toolbar) {
  border-color: rgba(71, 85, 105, .22);
  background-color: rgba(255, 255, 255, .9);
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-notice p),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-readiness p),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-card-copy),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card span),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-summary-card small),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-card p) {
  color: #475569;
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters input),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-local-filters select),
:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-permission-card) {
  border-color: rgba(71, 85, 105, .28);
  background: rgba(255, 255, 255, .97);
  color: #172033;
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-works-settings-accordion-trigger[aria-expanded="true"]) {
  background: rgba(207, 250, 254, .78);
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-background-watermark) {
  opacity: .2;
}

:global(.ym-dashboard-light .ym-works-settings-page .ym-watermark-glow) {
  opacity: .01;
}

/* ---------- Responsive layout ---------- */

@media (max-width: 1180px) {
  .ym-works-settings-mutation-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .ym-works-settings-workflow-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-permission-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-explorer-toolbar {
    grid-template-columns: 1fr;
    align-items: stretch;
  }

  .ym-works-settings-explorer-actions {
    justify-content: space-between;
  }
}

@media (max-width: 900px) {
  .ym-works-settings-local-nav {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-readiness {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-readiness__storage {
    border-inline-end: 0;
    border-bottom: 1px solid var(--ws-border-soft);
  }

  .ym-works-settings-summary-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .ym-works-settings-workspace-row .ym-works-settings-two-column {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-local-filters {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-local-filters .is-search {
    grid-column: 1 / -1;
  }
}

@media (max-width: 640px) {
  .ym-works-settings-local-nav {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-local-nav a {
    min-height: 40px;
    justify-content: flex-start;
  }

  .ym-works-settings-notices {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-notice + .ym-works-settings-notice {
    border-inline-start: 0;
    border-top: 1px solid var(--ws-border-soft);
  }

  .ym-works-settings-section-heading {
    align-items: flex-start;
  }

  .ym-works-settings-readiness__storage {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-mutation-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-summary-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-summary-card:last-child:nth-child(odd) {
    grid-column: 1 / -1;
  }

  .ym-works-settings-workflow-grid,
  .ym-works-settings-local-filters,
  .ym-works-settings-permission-grid {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-local-filters .is-search {
    grid-column: auto;
  }

  .ym-works-settings-registry-head,
  .ym-works-settings-section-heading {
    flex-direction: column;
  }

  .ym-works-settings-explorer-actions {
    align-items: stretch;
    flex-direction: column;
  }

  .ym-works-settings-explorer-actions > div {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .ym-works-settings-explorer-actions button {
    width: 100%;
  }

  .ym-works-settings-permission-card {
    min-height: 0;
  }
}

@media (max-width: 420px) {
  .ym-works-settings-summary-grid {
    grid-template-columns: 1fr;
  }

  .ym-works-settings-summary-card:last-child:nth-child(odd) {
    grid-column: auto;
  }
}

</style>
