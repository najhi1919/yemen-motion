<template>
  <div class="ym-works-settings-page space-y-7" dir="rtl">
    <section class="ym-works-settings-hero">
      <div class="ym-works-settings-hero__glow is-one" />
      <div class="ym-works-settings-hero__glow is-two" />
      <div class="ym-works-settings-hero__grid" aria-hidden="true" />

      <div class="ym-works-settings-hero__content">
        <div>
          <div class="ym-works-settings-chips">
            <span class="ym-works-settings-chip is-brand">Yemen Motion</span>
            <span class="ym-works-settings-chip is-readonly">إدارة حسب الصلاحية</span>
          </div>
          <p class="ym-works-settings-kicker">حوكمة إدارة الأعمال</p>
          <h1>إعدادات وصلاحيات الأعمال</h1>
          <p class="ym-works-settings-description">
            إدارة القيم المحفوظة لإعدادات الأعمال حسب صلاحيات الحساب، مع إبقاء نموذج الوصول وسير العمل وسجل الصلاحيات ظاهرًا.
          </p>
        </div>

        <div class="ym-works-settings-hero__summary">
          <span>الصلاحيات المسجلة</span>
          <strong>{{ formatNumber(data?.permission_registry.total_permissions ?? 0) }}</strong>
          <small>ضمن مجموعة إدارة الأعمال</small>
        </div>
      </div>
    </section>

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
      <section class="ym-works-settings-notices" aria-label="ملاحظات إعدادات الأعمال">
        <aside class="ym-works-settings-notice" role="note">
          <span>تخزين دائم</span>
          <p>يمكن حفظ مهلة المراجعة وثقة النشر المباشر وحدود الوسائط حسب صلاحيات الحساب.</p>
        </aside>
        <aside class="ym-works-settings-notice is-restriction" role="note">
          <span>حالة التكامل</span>
          <p>مهلة المراجعة مطبقة على قائمة المراجعة، وثقة النشر المباشر مطبقة على نتيجة اعتماد العمل، وحدود الوسائط فقط ما تزال محفوظة بانتظار التكامل التشغيلي.</p>
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
        <aside class="ym-works-settings-info-card is-support" role="note">
          <span class="ym-works-settings-info-card__icon" aria-hidden="true">i</span>
          <div>
            <h2>
              {{ data.settings_support.persistent_settings_available
                ? 'الإعدادات الدائمة متاحة'
                : 'راجع حالة دعم التخزين الدائم' }}
            </h2>
            <p>{{ data.settings_support.reason }}</p>
            <dl>
              <div>
                <dt>دعم التخزين الدائم</dt>
                <dd><BooleanBadge :value="data.settings_support.persistent_settings_available" /></dd>
              </div>
              <div>
                <dt>المصدر</dt>
                <dd><code dir="ltr">{{ data.settings_support.source }}</code></dd>
              </div>
            </dl>
          </div>
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

        <section class="ym-works-settings-two-column">
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
                <p>قدرات الحساب الحالي</p>
                <h2>نطاق الصلاحيات المتاح</h2>
              </div>
              <span class="ym-works-settings-section-badge is-capability">حسب التفويض</span>
            </header>

            <p class="ym-works-settings-card-copy">
              تحدد هذه القدرات الأقسام التي يمكن للحساب تعديلها داخل إعدادات الأعمال.
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
                  {{ capability.value ? 'متاح كصلاحية' : 'غير متاح' }}
                </span>
              </article>
            </div>
          </article>
        </section>

        <section class="ym-works-settings-card ym-works-settings-workflow">
          <header>
            <div>
              <p>النموذج النظري الحالي</p>
              <h2>سير عمل الأعمال</h2>
            </div>
            <code dir="ltr">{{ data.workflow.derived_from }}</code>
          </header>

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
        </section>

        <aside class="ym-works-settings-info-card is-management" role="note">
          <span class="ym-works-settings-info-card__icon" aria-hidden="true">i</span>
          <div>
            <h2>حالة دعم الإدارة</h2>
            <p>{{ data.management_support.reason }}</p>
            <div class="ym-works-settings-mutation-grid">
              <article v-for="item in mutationItems" :key="item.key">
                <span>{{ item.label }}</span>
                <BooleanBadge :value="item.value" />
              </article>
            </div>
          </div>
        </aside>

        <section class="ym-works-settings-registry-card">
          <header class="ym-works-settings-registry-head">
            <div>
              <p>سجل الصلاحيات</p>
              <h2>صلاحيات إدارة الأعمال المسجلة</h2>
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

          <div class="ym-works-settings-local-filters">
            <label class="is-search">
              <span>بحث محلي في الصلاحيات</span>
              <input
                v-model.trim="permissionSearch"
                type="search"
                maxlength="120"
                placeholder="اسم الصلاحية أو التسمية أو الوصف أو القسم"
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
          </div>

          <div v-if="filteredPermissionCount === 0" class="ym-works-settings-empty" role="status">
            <span aria-hidden="true">0</span>
            <div>
              <h3>لا توجد صلاحيات مطابقة</h3>
              <p>غيّر نص البحث المحلي أو اختر قسمًا آخر.</p>
            </div>
          </div>

          <div v-else class="ym-works-settings-sections">
            <section v-for="section in filteredSections" :key="section.key" class="ym-works-settings-permission-section">
              <header>
                <div>
                  <span class="ym-works-settings-section-badge">{{ section.key }}</span>
                  <h3>{{ section.label }}</h3>
                </div>
                <strong>{{ formatNumber(section.permissions.length) }}</strong>
              </header>

              <div class="ym-works-settings-permission-grid">
                <article v-for="permission in section.permissions" :key="permission.name" class="ym-works-settings-permission-card">
                  <div class="ym-works-settings-permission-card__top">
                    <span class="ym-works-settings-section-badge">{{ section.key }}</span>
                    <span class="ym-works-settings-kind" :class="inferredType(permission.name).tone">
                      {{ inferredType(permission.name).label }}
                    </span>
                  </div>
                  <code dir="ltr">{{ permission.name }}</code>
                  <h4 :dir="textDirection(permission.label)">{{ displayText(permission.label, 'دون تسمية إضافية') }}</h4>
                  <p :dir="textDirection(permission.description)">{{ displayText(permission.description, 'لا يوجد وصف مسجل.') }}</p>
                  <dl>
                    <div><dt>القسم</dt><dd>{{ section.label }}</dd></div>
                    <div><dt>نوع القسم</dt><dd><code dir="ltr">{{ section.key }}</code></dd></div>
                  </dl>
                  <button
                    type="button"
                    class="ym-works-settings-details-button"
                    @click="openPermissionDetails(section, permission)"
                  >
                    عرض تفاصيل الصلاحية
                  </button>
                </article>
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
      <section class="ym-settings-detail-drawer" role="dialog" aria-modal="true" aria-labelledby="ym-settings-detail-title">
        <header class="ym-settings-detail-drawer__head">
          <div>
            <span>تفاصيل محلية للقراءة فقط</span>
            <h2 id="ym-settings-detail-title">تفاصيل الصلاحية</h2>
            <code dir="ltr">{{ selectedPermission.permission.name }}</code>
          </div>
          <button type="button" class="ym-settings-detail-drawer__close" aria-label="إغلاق التفاصيل" @click="closePermissionDetails">×</button>
        </header>

        <div class="ym-settings-detail-content">
          <section class="ym-settings-detail-intro">
            <div>
              <span class="ym-works-settings-section-badge">{{ selectedPermission.section.key }}</span>
              <span class="ym-works-settings-kind" :class="inferredType(selectedPermission.permission.name).tone">
                {{ inferredType(selectedPermission.permission.name).label }}
              </span>
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
              <div><dt>النوع التقديري</dt><dd>{{ inferredType(selectedPermission.permission.name).label }}</dd></div>
            </dl>
          </section>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onMounted, ref, watch, type PropType } from 'vue'
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
const selectedSection = ref<'' | SectionKey>('')
const drawerOpen = ref(false)
const selectedPermission = ref<SelectedPermission | null>(null)

let pageMounted = false
let loadedAuthorizationSignature: string | null = null
let accessRevision = 0
let requestRevision = 0
let mutationRevision = 0

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

const sectionFilterOptions = (Object.entries(sectionLabels) as Array<[SectionKey, string]>)
  .map(([key, label]) => ({ key, label }))

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
  const term = permissionSearch.value.trim().toLocaleLowerCase()

  return data.value.permission_registry.sections
    .filter(section => selectedSection.value === '' || section.key === selectedSection.value)
    .map((section) => {
      const sectionMatches = term === '' || section.label.toLocaleLowerCase().includes(term) || section.key.toLocaleLowerCase().includes(term)
      const permissions = section.permissions.filter((permission) => {
        if (sectionMatches) return true
        return [permission.name, permission.label, permission.description]
          .some(value => String(value ?? '').toLocaleLowerCase().includes(term))
      })
      return { ...section, permissions }
    })
    .filter(section => section.permissions.length > 0)
})

const filteredPermissionCount = computed(() => filteredSections.value
  .reduce((total, section) => total + section.permissions.length, 0))

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

function inferredType(name: string): { label: string; tone: string } {
  const segments = name.toLocaleLowerCase().split('.')
  const readTerms = ['view', 'list', 'detail', 'read', 'search', 'access']
  if (segments.some(segment => readTerms.includes(segment))) return { label: 'قراءة', tone: 'is-read' }
  return { label: 'إدارة أو إجراء', tone: 'is-manage' }
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
    if (currentMutationRevision !== mutationRevision) return
    const status = errorStatus(requestError)

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

    if (status === 401) {
      fieldErrors.value = {}
      conflictVersion.value = null
      saveMessage.value = null
      messageTone.value = null
      authStore.clearAuth()
      clearPageState()
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
  } catch (requestError: unknown) {
    if (requestAccessRevision !== accessRevision || currentRequestRevision !== requestRevision || !hasSettingsAccess.value) return
    const status = errorStatus(requestError)
    if (status === 401 || status === 403) {
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

function openPermissionDetails(section: PermissionSection, permission: PermissionItem): void {
  selectedPermission.value = {
    section: { key: section.key, label: section.label },
    permission
  }
  drawerOpen.value = true
}

function closePermissionDetails(): void {
  drawerOpen.value = false
  selectedPermission.value = null
}

function clearSettingsData(): void {
  data.value = null
  hasLoaded.value = false
  permissionSearch.value = ''
  selectedSection.value = ''
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
onMounted(() => {
  pageMounted = true
  syncSettingsAccessState()
})
</script>

<style scoped>
.ym-works-settings-page { color: var(--ym-text); }
.ym-works-settings-hero,.ym-works-settings-result-card,.ym-works-settings-access-state,.ym-works-settings-card,.ym-works-settings-registry-card { position: relative; overflow: hidden; border: 1px solid var(--ym-card-border); border-radius: 30px; background: var(--ym-card-bg); box-shadow: var(--ym-card-shadow),inset 0 1px 0 rgba(255,255,255,.1); }
.ym-works-settings-hero { padding: clamp(1.25rem,3vw,2rem); }
.ym-works-settings-hero::before { position: absolute; inset: 0; background: linear-gradient(135deg,rgba(14,165,233,.17),transparent 46%); content: ''; pointer-events: none; }
.ym-works-settings-hero__grid { position: absolute; inset: 0; background: linear-gradient(rgba(148,163,184,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(148,163,184,.045) 1px,transparent 1px); background-size: 44px 44px; mask-image: linear-gradient(to bottom,black,transparent 86%); pointer-events: none; }
.ym-works-settings-hero__glow { position: absolute; width: 19rem; height: 19rem; border-radius: 999px; filter: blur(18px); opacity: .24; pointer-events: none; }
.ym-works-settings-hero__glow.is-one { inset-block-start: -10rem; inset-inline-start: -5rem; background: #0ea5e9; }
.ym-works-settings-hero__glow.is-two { inset-block-end: -11rem; inset-inline-end: -4rem; background: #8b5cf6; }
.ym-works-settings-hero__content { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; }
.ym-works-settings-chips { display: flex; flex-wrap: wrap; gap: .6rem; margin-bottom: 1rem; }
.ym-works-settings-chip { border: 1px solid var(--ym-soft-border); border-radius: 999px; background: var(--ym-control-bg); color: var(--ym-muted); font-size: 12px; font-weight: 950; padding: .42rem .72rem; }
.ym-works-settings-chip.is-brand { color: #fbbf24; }
.ym-works-settings-chip.is-readonly { color: #38bdf8; }
.ym-works-settings-kicker { color: var(--ym-muted); font-size: 14px; font-weight: 900; margin: 0 0 .3rem; }
.ym-works-settings-hero h1 { color: var(--ym-text); font-size: clamp(2rem,4.5vw,3.35rem); font-weight: 950; line-height: 1.1; margin: 0; }
.ym-works-settings-description { max-width: 58rem; color: var(--ym-muted); font-size: 15px; font-weight: 800; line-height: 1.8; margin: .8rem 0 0; }
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
.ym-works-settings-summary-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 1rem; }
.ym-works-settings-summary-card { border: 1px solid var(--ym-soft-border); border-radius: 22px; background: linear-gradient(135deg,color-mix(in srgb,var(--settings-accent) 16%,transparent),transparent 54%),var(--ym-card-bg); box-shadow: var(--ym-card-shadow); padding: 1rem; }
.ym-works-settings-summary-card span,.ym-works-settings-summary-card small { display: block; color: var(--ym-muted); font-size: 11px; font-weight: 850; }
.ym-works-settings-summary-card strong { display: block; color: var(--ym-text); font-size: 1.8rem; font-weight: 950; margin: .3rem 0; }
.ym-works-settings-two-column { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }
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
.ym-works-settings-workflow > header > code { max-width: 48%; color: var(--ym-muted); font-size: 10px; overflow-wrap: anywhere; text-align: end; }
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
.ym-works-settings-registry-head code { color: #38bdf8; }
.ym-works-settings-registry-total { display: grid; min-width: 135px; border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-control-bg); padding: .65rem .8rem; }
.ym-works-settings-registry-total span,.ym-works-settings-registry-total small { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-works-settings-registry-total strong { color: var(--ym-text); font-size: 1.25rem; font-weight: 950; }
.ym-works-settings-local-filters { display: grid; grid-template-columns: minmax(0,2fr) minmax(220px,1fr); gap: .9rem; border: 1px solid var(--ym-soft-border); border-radius: 20px; background: var(--ym-control-bg); padding: 1rem; }
.ym-works-settings-local-filters label { display: grid; gap: .4rem; }
.ym-works-settings-local-filters label > span { color: var(--ym-muted); font-size: 11px; font-weight: 900; }
.ym-works-settings-local-filters label > small { color: var(--ym-muted); font-size: 9px; font-weight: 750; }
.ym-works-settings-local-filters input,.ym-works-settings-local-filters select { width: 100%; min-height: 45px; border: 1px solid var(--ym-control-border); border-radius: 14px; outline: none; background: var(--ym-card-bg); color: var(--ym-text); font-size: 12px; font-weight: 800; padding: .7rem .8rem; }
.ym-works-settings-local-filters input:focus,.ym-works-settings-local-filters select:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,.13); }
.ym-works-settings-local-filters option { background: var(--ym-dropdown-bg); }
.ym-works-settings-sections { display: grid; gap: 1rem; margin-top: 1rem; }
.ym-works-settings-permission-section { border: 1px solid var(--ym-soft-border); border-radius: 22px; background: color-mix(in srgb,var(--ym-control-bg) 72%,transparent); padding: 1rem; }
.ym-works-settings-permission-section > header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; }
.ym-works-settings-permission-section > header > div { display: flex; align-items: center; gap: .65rem; }
.ym-works-settings-permission-section h3 { color: var(--ym-text); font-size: 1rem; font-weight: 950; margin: 0; }
.ym-works-settings-permission-section > header > strong { display: grid; min-width: 2rem; min-height: 2rem; place-items: center; border-radius: 10px; background: rgba(56,189,248,.1); color: #38bdf8; font-size: 11px; }
.ym-works-settings-permission-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: .75rem; }
.ym-works-settings-permission-card { display: flex; min-width: 0; flex-direction: column; border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-card-bg); padding: .85rem; }
.ym-works-settings-permission-card__top { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: .7rem; }
.ym-works-settings-kind { border: 1px solid var(--ym-soft-border); border-radius: 999px; font-size: 9px; font-weight: 950; padding: .3rem .5rem; }
.ym-works-settings-kind.is-read { border-color: rgba(56,189,248,.32); background: rgba(56,189,248,.1); color: #38bdf8; }
.ym-works-settings-kind.is-manage { border-color: rgba(245,158,11,.34); background: rgba(245,158,11,.11); color: #fbbf24; }
.ym-works-settings-permission-card > code { color: #c4b5fd; font-size: 10px; font-weight: 850; overflow-wrap: anywhere; }
.ym-works-settings-permission-card h4 { color: var(--ym-text); font-size: 13px; font-weight: 950; line-height: 1.55; margin: .65rem 0 .25rem; }
.ym-works-settings-permission-card p { min-height: 3.2em; color: var(--ym-muted); font-size: 10px; font-weight: 750; line-height: 1.6; margin: 0 0 .7rem; }
.ym-works-settings-permission-card dl { display: grid; gap: .4rem; margin: auto 0 .75rem; }
.ym-works-settings-permission-card dl div { display: flex; align-items: baseline; justify-content: space-between; gap: .5rem; }
.ym-works-settings-permission-card dt { color: var(--ym-muted); font-size: 9px; font-weight: 850; }
.ym-works-settings-permission-card dd { color: var(--ym-text); font-size: 9px; font-weight: 850; margin: 0; }
.ym-works-settings-details-button { width: 100%; min-height: 38px; border: 1px solid rgba(14,165,233,.36); border-radius: 12px; background: rgba(14,165,233,.1); color: #38bdf8; font-size: 11px; font-weight: 950; padding: .55rem .7rem; }
.ym-works-settings-empty { display: flex; min-height: 190px; align-items: center; justify-content: center; gap: 1rem; margin-top: 1rem; text-align: start; }
.ym-works-settings-empty > span { display: grid; width: 3rem; height: 3rem; place-items: center; border-radius: 999px; background: rgba(148,163,184,.13); color: var(--ym-muted); font-weight: 950; }
.ym-works-settings-empty h3 { color: var(--ym-text); font-size: 1rem; font-weight: 950; margin: 0; }
.ym-works-settings-empty p { color: var(--ym-muted); font-size: 12px; font-weight: 800; margin: .3rem 0 0; }
.ym-settings-detail-backdrop { position: fixed; inset: 0; z-index: 120; display: flex; justify-content: flex-end; background: rgba(2,6,23,.68); backdrop-filter: blur(6px); }
.ym-settings-detail-drawer { width: min(650px,100%); height: 100dvh; overflow-y: auto; border-inline-start: 1px solid var(--ym-card-border); background: var(--ym-dropdown-bg); box-shadow: -24px 0 64px rgba(2,6,23,.38); color: var(--ym-text); }
.ym-settings-detail-drawer__head { position: sticky; top: 0; z-index: 4; display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--ym-soft-border); background: color-mix(in srgb,var(--ym-dropdown-bg) 92%,transparent); backdrop-filter: blur(18px); padding: 1.2rem 1.35rem; }
.ym-settings-detail-drawer__head span,.ym-settings-detail-drawer__head code { display: block; color: var(--ym-muted); font-size: 10px; font-weight: 850; overflow-wrap: anywhere; }
.ym-settings-detail-drawer__head h2 { color: var(--ym-text); font-size: 1.35rem; font-weight: 950; margin: .2rem 0; }
.ym-settings-detail-drawer__close { display: grid; flex: 0 0 auto; width: 42px; height: 42px; place-items: center; border: 1px solid var(--ym-control-border); border-radius: 14px; background: var(--ym-control-bg); color: var(--ym-text); font-size: 1.45rem; }
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
@media (max-width: 1180px) { .ym-works-settings-permission-grid { grid-template-columns: repeat(2,minmax(0,1fr)); } .ym-works-settings-mutation-grid { grid-template-columns: repeat(3,minmax(0,1fr)); } }
@media (max-width: 900px) { .ym-works-settings-hero__content,.ym-works-settings-card > header,.ym-works-settings-registry-head { align-items: stretch; flex-direction: column; } .ym-works-settings-hero__summary { min-width: 0; } .ym-works-settings-two-column,.ym-works-settings-workflow-grid { grid-template-columns: 1fr; } .ym-works-settings-workflow > header > code { max-width: none; text-align: start; } }
@media (max-width: 640px) { .ym-works-settings-hero,.ym-works-settings-result-card,.ym-works-settings-access-state,.ym-works-settings-card,.ym-works-settings-registry-card { border-radius: 22px; } .ym-works-settings-hero h1 { font-size: 2rem; } .ym-works-settings-notices,.ym-works-settings-summary-grid,.ym-works-settings-local-filters,.ym-works-settings-permission-grid,.ym-works-settings-mutation-grid,.ym-settings-detail-grid { grid-template-columns: 1fr; } .ym-works-settings-notice,.ym-works-settings-info-card { align-items: flex-start; } .ym-works-settings-permission-section > header > div { align-items: flex-start; flex-direction: column; } .ym-settings-detail-grid > div.is-wide { grid-column: auto; } .ym-settings-detail-drawer__head,.ym-settings-detail-content { padding-inline: 1rem; } }
@media (prefers-reduced-motion: reduce) { .ym-works-settings-spinner { animation-duration: 1.8s; } }
</style>
