<template>
  <section class="ym-settings-editor" aria-labelledby="ym-settings-editor-title">
    <header class="ym-settings-editor__head">
      <div>
        <p>إدارة القيم المحفوظة</p>
        <h2 id="ym-settings-editor-title">محرر إعدادات الأعمال</h2>
        <span>مهلة المراجعة وثقة النشر المباشر وحدود الوسائط مطبقة، كما أن ترتيب الوسائط والغلاف وواجهة التأليف الإدارية الرسومية متاحة. الإرسال للمراجعة ما يزال لاحقًا.</span>
      </div>
      <div class="ym-settings-editor__dirty" :class="`is-${editorStatus.tone}`" aria-live="polite">
        <strong>{{ dirtyCount }}</strong>
        <span>{{ editorStatus.label }}</span>
        <small>{{ editorStatus.detail }}</small>
      </div>
    </header>

    <dl class="ym-settings-editor__metadata">
      <div>
        <dt>النطاق العام</dt>
        <dd><code dir="ltr">{{ settings.scope }}</code></dd>
      </div>
      <div>
        <dt>الإصدار الحالي</dt>
        <dd>{{ settings.version }}</dd>
      </div>
      <div>
        <dt>آخر تحديث</dt>
        <dd>{{ formattedUpdatedAt }}</dd>
      </div>
      <div>
        <dt>سجل التخزين موجود</dt>
        <dd>{{ settings.storage_record_found ? 'نعم' : 'لا' }}</dd>
      </div>
    </dl>

    <div
      v-if="saveMessage"
      class="ym-settings-editor__message"
      :class="`is-${messageTone || 'info'}`"
      :role="messageTone === 'error' ? 'alert' : 'status'"
      aria-live="polite"
    >
      {{ saveMessage }}
    </div>

    <aside v-if="conflictVersion !== null" class="ym-settings-editor__conflict" role="alert" aria-live="assertive">
      <div>
        <strong>تعارض في إصدار الإعدادات</strong>
        <p>
          توجد نسخة أحدث على الخادم بالإصدار
          <bdi>{{ conflictVersion }}</bdi>.
          بقيت تعديلاتك المحلية كما هي ولم تتم إعادة المحاولة.
        </p>
      </div>
      <button type="button" @click="confirmReload">إعادة تحميل الإعدادات</button>
    </aside>

    <div class="ym-settings-editor__cards">
      <section class="ym-settings-editor-group is-review-publish" aria-labelledby="ym-settings-review-publish-title">
        <header class="ym-settings-editor-group__head">
          <span aria-hidden="true">✓</span>
          <div>
            <h3 id="ym-settings-review-publish-title">المراجعة والنشر</h3>
            <p>سياسات توقيت المراجعة وقرار النشر الناتج عن الاعتماد.</p>
          </div>
        </header>
        <div class="ym-settings-editor-group__grid">
      <article class="ym-settings-editor-card" :aria-disabled="!canEditReview">
        <header>
          <div>
            <p>سياسة زمنية محفوظة</p>
            <h3>مهلة المراجعة</h3>
          </div>
          <span v-if="!canEditReview" class="ym-settings-editor__locked">لا توجد صلاحية تعديل</span>
        </header>

        <label class="ym-settings-editor__switch">
          <input
            v-model="draft.reviewEnabled"
            type="checkbox"
            role="switch"
            :disabled="!canEditReview || saving"
            :aria-disabled="!canEditReview || saving"
          />
          <span aria-hidden="true" />
          <b>{{ draft.reviewEnabled ? 'مهلة المراجعة مفعلة' : 'مهلة المراجعة غير مفعلة' }}</b>
        </label>

        <label class="ym-settings-editor__field">
          <span>عدد الساعات</span>
          <input
            id="ym-review-sla-hours"
            v-model="draft.reviewHours"
            type="number"
            inputmode="numeric"
            dir="ltr"
            min="1"
            max="720"
            step="1"
            placeholder="1 – 720"
            :disabled="!canEditReview || saving || !draft.reviewEnabled"
            :aria-disabled="!canEditReview || saving || !draft.reviewEnabled"
            :aria-invalid="Boolean(fieldError('values.review_sla_hours'))"
            :aria-describedby="fieldError('values.review_sla_hours') ? 'ym-review-sla-help ym-review-sla-error' : 'ym-review-sla-help'"
          />
          <small id="ym-review-sla-help">
            لا تُضاف قيمة تلقائية عند التفعيل. أدخل عددًا صحيحًا من 1 إلى 720.
          </small>
          <em v-if="fieldError('values.review_sla_hours')" id="ym-review-sla-error" role="alert">
            {{ fieldError('values.review_sla_hours') }}
          </em>
        </label>

        <p class="ym-settings-editor-card__note">
          تُطبق هذه القيمة على قائمة طلبات المراجعة وحساب الطلبات المتأخرة.
        </p>
      </article>

      <article class="ym-settings-editor-card" :aria-disabled="!canEditDirectPublish">
        <header>
          <div>
            <p>قرار ثقة محفوظ</p>
            <h3>ثقة النشر المباشر</h3>
          </div>
          <span v-if="!canEditDirectPublish" class="ym-settings-editor__locked">لا توجد صلاحية تعديل</span>
        </header>

        <label class="ym-settings-editor__switch is-prominent">
          <input
            v-model="draft.directPublishEnabled"
            type="checkbox"
            role="switch"
            :disabled="!canEditDirectPublish || saving"
            :aria-disabled="!canEditDirectPublish || saving"
            :aria-invalid="Boolean(fieldError('values.direct_publish_trust_enabled'))"
            :aria-describedby="fieldError('values.direct_publish_trust_enabled') ? 'ym-direct-publish-error' : undefined"
          />
          <span aria-hidden="true" />
          <b>{{ draft.directPublishEnabled ? 'مفعلة' : 'غير مفعلة' }}</b>
        </label>

        <em
          v-if="fieldError('values.direct_publish_trust_enabled')"
          id="ym-direct-publish-error"
          class="ym-settings-editor__standalone-error"
          role="alert"
        >
          {{ fieldError('values.direct_publish_trust_enabled') }}
        </em>

        <p class="ym-settings-editor-card__note is-warning">
          عند التفعيل، اعتماد عمل تحت المراجعة سينقله مباشرةً إلى منشور وعام.
        </p>
      </article>
        </div>
      </section>

      <section class="ym-settings-editor-group is-media" aria-labelledby="ym-settings-media-title">
        <header class="ym-settings-editor-group__head">
          <span aria-hidden="true">▧</span>
          <div>
            <h3 id="ym-settings-media-title">حدود الوسائط</h3>
            <p>الحدود التشغيلية لعدد الملفات وحجم كل ملف وأنواع الوسائط المتاحة.</p>
          </div>
        </header>
      <article class="ym-settings-editor-card is-media" :aria-disabled="!canEditMedia">
        <header>
          <div>
            <p>قيود محفوظة</p>
            <h3>حدود الوسائط</h3>
          </div>
          <span v-if="!canEditMedia" class="ym-settings-editor__locked">لا توجد صلاحية تعديل</span>
        </header>

        <div class="ym-settings-editor__media-grid">
          <section>
            <label class="ym-settings-editor__switch">
              <input
                v-model="draft.maxItemsUnlimited"
                type="checkbox"
                role="switch"
                :disabled="!canEditMedia || saving"
                :aria-disabled="!canEditMedia || saving"
              />
              <span aria-hidden="true" />
              <b>عدد العناصر دون حد</b>
            </label>
            <label class="ym-settings-editor__field">
              <span>الحد الأقصى للعناصر</span>
              <input
                id="ym-media-max-items"
                v-model="draft.maxItems"
                type="number"
                inputmode="numeric"
                dir="ltr"
                min="1"
                max="100"
                step="1"
                placeholder="1 – 100"
                :disabled="!canEditMedia || saving || draft.maxItemsUnlimited"
                :aria-disabled="!canEditMedia || saving || draft.maxItemsUnlimited"
                :aria-invalid="Boolean(fieldError('values.media_limits.max_items'))"
                :aria-describedby="fieldError('values.media_limits.max_items') ? 'ym-media-max-items-help ym-media-max-items-error' : 'ym-media-max-items-help'"
              />
              <small id="ym-media-max-items-help">عدد صحيح من 1 إلى 100.</small>
              <em v-if="fieldError('values.media_limits.max_items')" id="ym-media-max-items-error" role="alert">
                {{ fieldError('values.media_limits.max_items') }}
              </em>
            </label>
          </section>

          <section>
            <label class="ym-settings-editor__switch">
              <input
                v-model="draft.maxFileSizeUnlimited"
                type="checkbox"
                role="switch"
                :disabled="!canEditMedia || saving"
                :aria-disabled="!canEditMedia || saving"
              />
              <span aria-hidden="true" />
              <b>حجم الملف دون حد</b>
            </label>
            <label class="ym-settings-editor__field">
              <span>الحد الأقصى لحجم الملف بالكيلوبايت</span>
              <input
                id="ym-media-max-file-size"
                v-model="draft.maxFileSize"
                type="number"
                inputmode="numeric"
                dir="ltr"
                min="1"
                max="2097152"
                step="1"
                placeholder="1 – 2097152"
                :disabled="!canEditMedia || saving || draft.maxFileSizeUnlimited"
                :aria-disabled="!canEditMedia || saving || draft.maxFileSizeUnlimited"
                :aria-invalid="Boolean(fieldError('values.media_limits.max_file_size_kb'))"
                :aria-describedby="fieldError('values.media_limits.max_file_size_kb') ? 'ym-media-max-file-help ym-media-max-file-error' : 'ym-media-max-file-help'"
              />
              <small id="ym-media-max-file-help">
                عدد صحيح من 1 إلى 2097152.
                <template v-if="fileSizeInMb !== null"> يعادل {{ fileSizeInMb }} <bdi dir="ltr">MB</bdi> تقريبًا.</template>
              </small>
              <em v-if="fieldError('values.media_limits.max_file_size_kb')" id="ym-media-max-file-error" role="alert">
                {{ fieldError('values.media_limits.max_file_size_kb') }}
              </em>
            </label>
          </section>

          <section class="is-wide">
            <label class="ym-settings-editor__switch">
              <input
                v-model="draft.allMediaTypes"
                type="checkbox"
                role="switch"
                :disabled="!canEditMedia || saving"
                :aria-disabled="!canEditMedia || saving"
              />
              <span aria-hidden="true" />
              <b>السماح بجميع الأنواع</b>
            </label>

            <fieldset
              class="ym-settings-editor__types"
              :disabled="!canEditMedia || saving || draft.allMediaTypes"
              :aria-invalid="Boolean(fieldError('values.media_limits.allowed_types') || fieldError('values.media_limits'))"
              :aria-describedby="fieldError('values.media_limits.allowed_types') || fieldError('values.media_limits') ? 'ym-media-types-help ym-media-types-error' : 'ym-media-types-help'"
            >
              <legend>أنواع الوسائط المسموحة</legend>
              <label v-for="option in mediaTypeOptions" :key="option.value">
                <input v-model="draft.allowedTypes" type="checkbox" :value="option.value" />
                <span>{{ option.label }}</span>
                <code dir="ltr">{{ option.value }}</code>
              </label>
            </fieldset>
            <small id="ym-media-types-help" class="ym-settings-editor__types-help">
              عند تقييد الأنواع يجب اختيار نوع واحد على الأقل.
            </small>
            <em
              v-if="fieldError('values.media_limits.allowed_types') || fieldError('values.media_limits')"
              id="ym-media-types-error"
              class="ym-settings-editor__standalone-error"
              role="alert"
            >
              {{ fieldError('values.media_limits.allowed_types') || fieldError('values.media_limits') }}
            </em>
          </section>
        </div>
        <p class="ym-settings-editor-card__note is-warning">
          حدود الوسائط مطبقة على الرفع الإداري، وترتيب الوسائط والغلاف وواجهة التأليف الإدارية الرسومية متاحة، بينما الإرسال للمراجعة ما يزال لاحقًا.
        </p>
      </article>
      </section>
    </div>

    <footer class="ym-settings-editor__actions">
      <div aria-live="polite">
        <strong v-if="saving">جارٍ الحفظ…</strong>
        <span v-else-if="hasLocalErrors">صحّح أخطاء الحقول قبل الحفظ.</span>
        <span v-else-if="dirtyCount > 0">سيتم إرسال الحقول المتغيرة والمصرح بها فقط.</span>
        <span v-else>لا توجد تغييرات غير محفوظة.</span>
      </div>
      <div>
        <button
          type="button"
          class="is-secondary"
          :disabled="saving || dirtyCount === 0"
          :aria-disabled="saving || dirtyCount === 0"
          @click="resetDraft"
        >
          إلغاء التعديلات
        </button>
        <button
          ref="saveButton"
          type="button"
          class="is-primary"
          :disabled="saving || dirtyCount === 0 || hasLocalErrors || conflictVersion !== null"
          :aria-disabled="saving || dirtyCount === 0 || hasLocalErrors || conflictVersion !== null"
          @click="save"
        >
          {{ saving ? 'جارٍ الحفظ' : 'حفظ التغييرات' }}
        </button>
      </div>
    </footer>

    <Teleport to="body">
      <div
        v-if="confirmationKind"
        class="ym-settings-confirm"
        :dir="locale === 'en' ? 'ltr' : 'rtl'"
        role="dialog"
        aria-modal="true"
        aria-labelledby="ym-settings-confirm-title"
        aria-describedby="ym-settings-confirm-description"
        @mousedown.self="resolveConfirmation(false)"
      >
        <section ref="confirmationPanel" class="ym-settings-confirm__panel" tabindex="-1">
          <span class="ym-settings-confirm__icon" aria-hidden="true">!</span>
          <h2 id="ym-settings-confirm-title">{{ confirmationCopy.title }}</h2>
          <p id="ym-settings-confirm-description">{{ confirmationCopy.description }}</p>
          <div class="ym-settings-confirm__actions">
            <button ref="confirmationCancelButton" type="button" @click="resolveConfirmation(false)">
              {{ confirmationCopy.cancel }}
            </button>
            <button type="button" class="is-primary" @click="resolveConfirmation(true)">
              {{ confirmationCopy.confirm }}
            </button>
          </div>
        </section>
      </div>
    </Teleport>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

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

interface SettingsMutationValues extends Partial<Omit<StoredSettingsValues, 'media_limits'>> {
  media_limits?: Partial<StoredMediaLimits>
}

interface SettingsMutationPayload {
  version: number
  values: SettingsMutationValues
}

interface DraftState {
  reviewEnabled: boolean
  reviewHours: string
  directPublishEnabled: boolean
  maxItemsUnlimited: boolean
  maxItems: string
  maxFileSizeUnlimited: boolean
  maxFileSize: string
  allMediaTypes: boolean
  allowedTypes: AllowedMediaType[]
}

const props = defineProps<{
  settings: StoredSettings
  capabilities: CurrentUserCapabilities
  managementSupport: ManagementSupport
  saving: boolean
  saveMessage: string | null
  messageTone: 'success' | 'error' | 'info' | null
  fieldErrors: Record<string, string[]>
  conflictVersion: number | null
  locale?: 'ar' | 'en'
}>()

const emit = defineEmits<{
  save: [payload: SettingsMutationPayload]
  reload: []
  reset: []
}>()

const serverSnapshot = ref<StoredSettingsValues>(cloneValues(props.settings.values))
const draft = reactive<DraftState>(draftFromValues(props.settings.values))
const saveButton = ref<HTMLButtonElement | null>(null)
const confirmationPanel = ref<HTMLElement | null>(null)
const confirmationCancelButton = ref<HTMLButtonElement | null>(null)
const confirmationKind = ref<'direct-publish' | 'navigation' | 'reload' | null>(null)
let confirmationResolver: ((confirmed: boolean) => void) | null = null
let confirmationReturnFocus: HTMLElement | null = null
let previousBodyOverflow = ''
let beforeUnloadAttached = false

const mediaTypeOptions: Array<{ value: AllowedMediaType; label: string }> = [
  { value: 'image', label: 'صور' },
  { value: 'video', label: 'فيديو' },
  { value: 'gallery', label: 'معرض' }
]

const canEditReview = computed(() => (
  props.managementSupport.settings_mutation_available
  && props.managementSupport.review_sla_mutation_available
  && (props.capabilities.can_manage_settings || props.capabilities.can_manage_review_sla)
))
const canEditDirectPublish = computed(() => (
  props.managementSupport.settings_mutation_available
  && props.managementSupport.direct_publish_trust_mutation_available
  && (props.capabilities.can_manage_settings || props.capabilities.can_manage_direct_publish_trust)
))
const canEditMedia = computed(() => (
  props.managementSupport.settings_mutation_available
  && props.managementSupport.media_limits_mutation_available
  && (props.capabilities.can_manage_settings || props.capabilities.can_manage_media_limits)
))

const localErrors = computed<Record<string, string>>(() => {
  const errors: Record<string, string> = {}

  if (canEditReview.value && draft.reviewEnabled && parseInteger(draft.reviewHours, 1, 720) === null) {
    errors['values.review_sla_hours'] = 'أدخل عددًا صحيحًا من 1 إلى 720.'
  }
  if (canEditMedia.value && !draft.maxItemsUnlimited && parseInteger(draft.maxItems, 1, 100) === null) {
    errors['values.media_limits.max_items'] = 'أدخل عددًا صحيحًا من 1 إلى 100.'
  }
  if (canEditMedia.value && !draft.maxFileSizeUnlimited && parseInteger(draft.maxFileSize, 1, 2097152) === null) {
    errors['values.media_limits.max_file_size_kb'] = 'أدخل عددًا صحيحًا من 1 إلى 2097152.'
  }
  if (canEditMedia.value && !draft.allMediaTypes && draft.allowedTypes.length === 0) {
    errors['values.media_limits.allowed_types'] = 'اختر نوع وسائط واحدًا على الأقل أو اسمح بجميع الأنواع.'
  }

  return errors
})

const hasLocalErrors = computed(() => Object.keys(localErrors.value).length > 0)

const dirtyPaths = computed<string[]>(() => {
  const paths: string[] = []
  const snapshot = serverSnapshot.value

  if (canEditReview.value && reviewIsDirty(snapshot.review_sla_hours)) {
    paths.push('values.review_sla_hours')
  }
  if (
    canEditDirectPublish.value
    && draft.directPublishEnabled !== snapshot.direct_publish_trust_enabled
  ) {
    paths.push('values.direct_publish_trust_enabled')
  }
  if (canEditMedia.value && maxItemsIsDirty(snapshot.media_limits.max_items)) {
    paths.push('values.media_limits.max_items')
  }
  if (canEditMedia.value && maxFileSizeIsDirty(snapshot.media_limits.max_file_size_kb)) {
    paths.push('values.media_limits.max_file_size_kb')
  }
  if (canEditMedia.value && allowedTypesAreDirty(snapshot.media_limits.allowed_types)) {
    paths.push('values.media_limits.allowed_types')
  }

  return paths
})

const dirtyCount = computed(() => dirtyPaths.value.length)
const editorStatus = computed(() => {
  if (props.saving) {
    return { tone: 'saving', label: 'جارٍ الحفظ', detail: 'يتم تحديث القيم المحفوظة.' }
  }
  if (props.conflictVersion !== null) {
    return { tone: 'conflict', label: 'تعارض في النسخة', detail: 'المسودة محفوظة محليًا.' }
  }
  if (hasLocalErrors.value || Object.keys(props.fieldErrors).length > 0) {
    return { tone: 'error', label: 'خطأ تحقق', detail: 'تحتاج بعض الحقول إلى مراجعة.' }
  }
  if (props.messageTone === 'success' && dirtyCount.value === 0) {
    return { tone: 'saved', label: 'تم الحفظ', detail: 'القيم متزامنة مع الخادم.' }
  }
  if (dirtyCount.value > 0) {
    return { tone: 'dirty', label: 'تغييرات غير محفوظة', detail: `${dirtyCount.value} حقول متغيرة.` }
  }
  return { tone: 'clean', label: 'لا تغييرات', detail: 'القيم الحالية محفوظة.' }
})
const confirmationCopy = computed(() => {
  if (confirmationKind.value === 'direct-publish') {
    return {
      title: 'تأكيد تفعيل ثقة النشر المباشر',
      description: 'قد تنتقل الأعمال المؤهلة بعد اعتمادها مباشرةً إلى حالة منشورة وعامة وفق العقد الحالي، دون خطوة نشر يدوية مستقلة.',
      cancel: 'العودة إلى التعديلات',
      confirm: 'تأكيد التفعيل والحفظ'
    }
  }
  if (confirmationKind.value === 'reload') {
    return {
      title: 'إعادة تحميل الإعدادات؟',
      description: 'ستُفقد التعديلات المحلية الحالية وتُستبدل بالنسخة الأحدث من الخادم.',
      cancel: 'الاحتفاظ بالمسودة',
      confirm: 'إعادة التحميل'
    }
  }
  return {
    title: 'مغادرة الصفحة مع تغييرات غير محفوظة؟',
    description: 'ستُفقد التعديلات المحلية إذا تابعت الانتقال قبل حفظها.',
    cancel: 'البقاء في الصفحة',
    confirm: 'مغادرة الصفحة'
  }
})

const fileSizeInMb = computed<string | null>(() => {
  if (draft.maxFileSizeUnlimited) return null
  const value = parseInteger(draft.maxFileSize, 1, 2097152)
  if (value === null) return null
  return new Intl.NumberFormat(props.locale === 'en' ? 'en-US' : 'ar-YE', {
    maximumFractionDigits: 2
  }).format(value / 1024)
})

const formattedUpdatedAt = computed(() => {
  if (!props.settings.updated_at) return 'لم يُحفظ تعديل بعد.'
  const date = new Date(props.settings.updated_at)
  if (Number.isNaN(date.getTime())) return props.settings.updated_at
  return new Intl.DateTimeFormat(props.locale === 'en' ? 'en-US' : 'ar-YE', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
})

watch(
  () => props.settings,
  settings => replaceSnapshot(settings.values),
  { deep: true }
)

watch(dirtyCount, count => syncBeforeUnload(count > 0), { immediate: true })

watch(confirmationKind, async kind => {
  if (!import.meta.client) return
  if (kind) {
    previousBodyOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onConfirmationKeydown)
    await nextTick()
    confirmationCancelButton.value?.focus()
  } else {
    restoreConfirmationEnvironment()
  }
})

function cloneValues(values: StoredSettingsValues): StoredSettingsValues {
  return {
    review_sla_hours: values.review_sla_hours,
    direct_publish_trust_enabled: values.direct_publish_trust_enabled,
    media_limits: {
      max_items: values.media_limits.max_items,
      max_file_size_kb: values.media_limits.max_file_size_kb,
      allowed_types: values.media_limits.allowed_types
        ? [...values.media_limits.allowed_types]
        : null
    }
  }
}

function draftFromValues(values: StoredSettingsValues): DraftState {
  return {
    reviewEnabled: values.review_sla_hours !== null,
    reviewHours: values.review_sla_hours === null ? '' : String(values.review_sla_hours),
    directPublishEnabled: values.direct_publish_trust_enabled,
    maxItemsUnlimited: values.media_limits.max_items === null,
    maxItems: values.media_limits.max_items === null ? '' : String(values.media_limits.max_items),
    maxFileSizeUnlimited: values.media_limits.max_file_size_kb === null,
    maxFileSize: values.media_limits.max_file_size_kb === null ? '' : String(values.media_limits.max_file_size_kb),
    allMediaTypes: values.media_limits.allowed_types === null,
    allowedTypes: values.media_limits.allowed_types ? [...values.media_limits.allowed_types] : []
  }
}

function replaceSnapshot(values: StoredSettingsValues): void {
  serverSnapshot.value = cloneValues(values)
  Object.assign(draft, draftFromValues(values))
}

function parseInteger(value: string, minimum: number, maximum: number): number | null {
  if (!/^\d+$/.test(value)) return null
  const parsed = Number(value)
  return Number.isSafeInteger(parsed) && parsed >= minimum && parsed <= maximum ? parsed : null
}

function normalizedTypes(types: AllowedMediaType[]): AllowedMediaType[] {
  const selected = new Set(types)
  return mediaTypeOptions.map(option => option.value).filter(type => selected.has(type))
}

function sameTypes(left: AllowedMediaType[] | null, right: AllowedMediaType[] | null): boolean {
  if (left === null || right === null) return left === right
  const normalizedLeft = normalizedTypes(left)
  const normalizedRight = normalizedTypes(right)
  return normalizedLeft.length === normalizedRight.length
    && normalizedLeft.every((type, index) => type === normalizedRight[index])
}

function reviewIsDirty(snapshot: number | null): boolean {
  if (!draft.reviewEnabled) return snapshot !== null
  const value = parseInteger(draft.reviewHours, 1, 720)
  return value === null || value !== snapshot
}

function maxItemsIsDirty(snapshot: number | null): boolean {
  if (draft.maxItemsUnlimited) return snapshot !== null
  const value = parseInteger(draft.maxItems, 1, 100)
  return value === null || value !== snapshot
}

function maxFileSizeIsDirty(snapshot: number | null): boolean {
  if (draft.maxFileSizeUnlimited) return snapshot !== null
  const value = parseInteger(draft.maxFileSize, 1, 2097152)
  return value === null || value !== snapshot
}

function allowedTypesAreDirty(snapshot: AllowedMediaType[] | null): boolean {
  const value = draft.allMediaTypes ? null : normalizedTypes(draft.allowedTypes)
  if (!draft.allMediaTypes && value.length === 0) return true
  return !sameTypes(value, snapshot)
}

function fieldError(path: string): string | null {
  return localErrors.value[path] || props.fieldErrors[path]?.[0] || null
}

async function save(): Promise<void> {
  if (props.saving || dirtyCount.value === 0 || hasLocalErrors.value || props.conflictVersion !== null) return

  const requiresDirectPublishConfirmation = (
    serverSnapshot.value.direct_publish_trust_enabled === false
    && draft.directPublishEnabled === true
    && dirtyPaths.value.includes('values.direct_publish_trust_enabled')
  )

  if (
    requiresDirectPublishConfirmation
    && !await askForConfirmation('direct-publish', saveButton.value)
  ) {
    return
  }

  emitSave()
}

function emitSave(): void {
  const values: SettingsMutationValues = {}
  const paths = new Set(dirtyPaths.value)

  if (paths.has('values.review_sla_hours')) {
    values.review_sla_hours = draft.reviewEnabled
      ? parseInteger(draft.reviewHours, 1, 720)
      : null
  }
  if (paths.has('values.direct_publish_trust_enabled')) {
    values.direct_publish_trust_enabled = draft.directPublishEnabled
  }

  const mediaLimits: Partial<StoredMediaLimits> = {}
  if (paths.has('values.media_limits.max_items')) {
    mediaLimits.max_items = draft.maxItemsUnlimited
      ? null
      : parseInteger(draft.maxItems, 1, 100)
  }
  if (paths.has('values.media_limits.max_file_size_kb')) {
    mediaLimits.max_file_size_kb = draft.maxFileSizeUnlimited
      ? null
      : parseInteger(draft.maxFileSize, 1, 2097152)
  }
  if (paths.has('values.media_limits.allowed_types')) {
    mediaLimits.allowed_types = draft.allMediaTypes
      ? null
      : normalizedTypes(draft.allowedTypes)
  }
  if (Object.keys(mediaLimits).length > 0) values.media_limits = mediaLimits

  emit('save', { version: props.settings.version, values })
}

function resetDraft(): void {
  replaceSnapshot(serverSnapshot.value)
  emit('reset')
}

async function confirmReload(): Promise<void> {
  if (
    dirtyCount.value > 0
    && !await askForConfirmation('reload', document.activeElement as HTMLElement | null)
  ) {
    return
  }
  emit('reload')
}

function askForConfirmation(
  kind: 'direct-publish' | 'navigation' | 'reload',
  returnFocusTo: HTMLElement | null
): Promise<boolean> {
  if (confirmationKind.value || confirmationResolver) return Promise.resolve(false)
  confirmationReturnFocus = returnFocusTo
  confirmationKind.value = kind
  return new Promise(resolve => {
    confirmationResolver = resolve
  })
}

function resolveConfirmation(confirmed: boolean): void {
  const resolve = confirmationResolver
  const returnFocusTo = confirmationReturnFocus
  confirmationResolver = null
  confirmationReturnFocus = null
  confirmationKind.value = null
  resolve?.(confirmed)
  void nextTick(() => returnFocusTo?.focus())
}

function onConfirmationKeydown(event: KeyboardEvent): void {
  if (!confirmationKind.value) return
  if (event.key === 'Escape') {
    event.preventDefault()
    resolveConfirmation(false)
    return
  }
  if (event.key !== 'Tab' || !confirmationPanel.value) return

  const focusable = [...confirmationPanel.value.querySelectorAll<HTMLElement>(
    'button,[href],[tabindex]:not([tabindex="-1"])'
  )].filter(element => !element.hasAttribute('disabled'))

  if (focusable.length === 0) {
    event.preventDefault()
    confirmationPanel.value.focus()
    return
  }

  const first = focusable[0]!
  const last = focusable[focusable.length - 1]!
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

function restoreConfirmationEnvironment(): void {
  if (!import.meta.client) return
  document.removeEventListener('keydown', onConfirmationKeydown)
  document.body.style.overflow = previousBodyOverflow
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
  if (dirtyCount.value === 0) return
  event.preventDefault()
  event.returnValue = ''
}

function syncBeforeUnload(shouldAttach: boolean): void {
  if (!import.meta.client || shouldAttach === beforeUnloadAttached) return
  if (shouldAttach) {
    window.addEventListener('beforeunload', onBeforeUnload)
  } else {
    window.removeEventListener('beforeunload', onBeforeUnload)
  }
  beforeUnloadAttached = shouldAttach
}

onBeforeRouteLeave(async () => {
  if (dirtyCount.value === 0) return true
  return await askForConfirmation('navigation', document.activeElement as HTMLElement | null)
})

onBeforeUnmount(() => {
  syncBeforeUnload(false)
  if (import.meta.client) restoreConfirmationEnvironment()
  confirmationResolver?.(false)
  confirmationResolver = null
})
</script>

<style scoped>
.ym-settings-editor {
  overflow: hidden;
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255,255,255,.1);
}
.ym-settings-editor__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  padding: clamp(1rem,2.5vw,1.5rem);
}
.ym-settings-editor__head p { color: #38bdf8; font-size: 11px; font-weight: 950; margin: 0 0 .25rem; }
.ym-settings-editor__head h2 { color: var(--ym-text); font-size: 1.4rem; font-weight: 950; margin: 0; }
.ym-settings-editor__head > div > span { display: block; color: var(--ym-muted); font-size: 12px; font-weight: 800; line-height: 1.7; margin-top: .45rem; }
.ym-settings-editor__dirty {
  display: grid;
  min-width: 145px;
  border: 1px solid var(--ym-soft-border);
  border-radius: 17px;
  background: var(--ym-control-bg);
  padding: .7rem .85rem;
}
.ym-settings-editor__dirty strong { color: var(--ym-text); font-size: 1.3rem; font-weight: 950; }
.ym-settings-editor__dirty span { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-settings-editor__dirty small { color: var(--ym-muted); font-size: 9px; font-weight: 750; margin-top: .18rem; }
.ym-settings-editor__dirty.is-dirty { border-color: rgba(245,158,11,.38); background: rgba(245,158,11,.1); }
.ym-settings-editor__dirty.is-dirty strong { color: #fbbf24; }
.ym-settings-editor__dirty.is-saving { border-color: rgba(56,189,248,.38); background: rgba(56,189,248,.1); }
.ym-settings-editor__dirty.is-saving strong { color: #38bdf8; }
.ym-settings-editor__dirty.is-saved { border-color: rgba(16,185,129,.38); background: rgba(16,185,129,.1); }
.ym-settings-editor__dirty.is-saved strong { color: #34d399; }
.ym-settings-editor__dirty.is-conflict,.ym-settings-editor__dirty.is-error { border-color: rgba(244,63,94,.38); background: rgba(244,63,94,.1); }
.ym-settings-editor__dirty.is-conflict strong,.ym-settings-editor__dirty.is-error strong { color: #fb7185; }
.ym-settings-editor__metadata {
  display: grid;
  grid-template-columns: repeat(4,minmax(0,1fr));
  gap: .65rem;
  margin: 0;
  padding: 0 clamp(1rem,2.5vw,1.5rem) 1rem;
}
.ym-settings-editor__metadata div {
  border: 1px solid var(--ym-soft-border);
  border-radius: 15px;
  background: var(--ym-control-bg);
  padding: .7rem;
}
.ym-settings-editor__metadata dt { color: var(--ym-muted); font-size: 10px; font-weight: 850; }
.ym-settings-editor__metadata dd { color: var(--ym-text); font-size: 12px; font-weight: 950; margin: .3rem 0 0; overflow-wrap: anywhere; }
.ym-settings-editor__metadata code { color: #c4b5fd; font-size: 10px; }
.ym-settings-editor__message,.ym-settings-editor__conflict {
  margin: 0 clamp(1rem,2.5vw,1.5rem) 1rem;
  border: 1px solid rgba(56,189,248,.35);
  border-radius: 17px;
  background: rgba(56,189,248,.1);
  color: #38bdf8;
  font-size: 12px;
  font-weight: 850;
  line-height: 1.7;
  padding: .8rem;
}
.ym-settings-editor__message.is-success { border-color: rgba(16,185,129,.35); background: rgba(16,185,129,.1); color: #34d399; }
.ym-settings-editor__message.is-error { border-color: rgba(244,63,94,.35); background: rgba(244,63,94,.1); color: #fb7185; }
.ym-settings-editor__conflict { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-color: rgba(245,158,11,.4); background: rgba(245,158,11,.1); color: var(--ym-text); }
.ym-settings-editor__conflict strong { color: #fbbf24; }
.ym-settings-editor__conflict p { color: var(--ym-muted); margin: .25rem 0 0; }
.ym-settings-editor__conflict button { flex: 0 0 auto; border: 1px solid rgba(245,158,11,.4); border-radius: 13px; background: rgba(245,158,11,.12); color: #fbbf24; font-size: 11px; font-weight: 950; padding: .65rem .8rem; }
.ym-settings-editor__cards { display: grid; grid-template-columns: 1fr; gap: 1rem; padding: 0 clamp(1rem,2.5vw,1.5rem) 1.25rem; }
.ym-settings-editor-group { min-width: 0; border: 1px solid color-mix(in srgb,#22d3ee 20%,var(--ym-soft-border)); border-radius: 24px; background: linear-gradient(145deg,rgba(34,211,238,.055),transparent 46%),color-mix(in srgb,var(--ym-control-bg) 82%,transparent); padding: 1rem; }
.ym-settings-editor-group.is-media { border-color: color-mix(in srgb,#8b5cf6 22%,var(--ym-soft-border)); background: linear-gradient(145deg,rgba(139,92,246,.06),transparent 46%),color-mix(in srgb,var(--ym-control-bg) 82%,transparent); }
.ym-settings-editor-group__head { display: flex; align-items: center; gap: .75rem; margin-bottom: .9rem; }
.ym-settings-editor-group__head > span { display: grid; width: 2.5rem; height: 2.5rem; flex: 0 0 auto; place-items: center; border: 1px solid rgba(34,211,238,.34); border-radius: 13px; background: rgba(34,211,238,.1); color: #22d3ee; font-size: 17px; font-weight: 950; }
.ym-settings-editor-group.is-media .ym-settings-editor-group__head > span { border-color: rgba(139,92,246,.34); background: rgba(139,92,246,.11); color: #a78bfa; }
.ym-settings-editor-group__head h3 { color: var(--ym-text); font-size: 1.05rem; font-weight: 950; margin: 0; }
.ym-settings-editor-group__head p { color: var(--ym-muted); font-size: 11px; font-weight: 800; line-height: 1.6; margin: .2rem 0 0; }
.ym-settings-editor-group__grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .9rem; }
.ym-settings-editor-card { min-width: 0; border: 1px solid var(--ym-soft-border); border-radius: 22px; background: var(--ym-control-bg); padding: 1rem; }
.ym-settings-editor-card[aria-disabled="true"] { border-style: dashed; background: color-mix(in srgb,var(--ym-control-bg) 92%,transparent); }
.ym-settings-editor-card.is-media { grid-column: 1 / -1; }
.ym-settings-editor-card > header { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; margin-bottom: 1rem; }
.ym-settings-editor-card header p { color: var(--ym-muted); font-size: 10px; font-weight: 850; margin: 0 0 .2rem; }
.ym-settings-editor-card h3 { color: var(--ym-text); font-size: 1.1rem; font-weight: 950; margin: 0; }
.ym-settings-editor__locked { border: 1px solid rgba(148,163,184,.3); border-radius: 999px; color: #94a3b8; font-size: 9px; font-weight: 950; padding: .35rem .55rem; white-space: nowrap; }
.ym-settings-editor__switch { display: flex; align-items: center; gap: .65rem; cursor: pointer; }
.ym-settings-editor__switch input { position: absolute; width: 1px; height: 1px; opacity: 0; }
.ym-settings-editor__switch > span { position: relative; width: 44px; height: 24px; flex: 0 0 auto; border: 1px solid var(--ym-control-border); border-radius: 999px; background: var(--ym-card-bg); transition: .2s ease; }
.ym-settings-editor__switch > span::after { position: absolute; top: 3px; inset-inline-start: 3px; width: 16px; height: 16px; border-radius: 999px; background: #94a3b8; content: ''; transition: .2s ease; }
.ym-settings-editor__switch input:checked + span { border-color: rgba(16,185,129,.55); background: rgba(16,185,129,.18); }
.ym-settings-editor__switch input:checked + span::after { inset-inline-start: 22px; background: #34d399; }
.ym-settings-editor__switch input:focus-visible + span { outline: 3px solid rgba(14,165,233,.3); outline-offset: 2px; }
.ym-settings-editor__switch input:disabled ~ * { cursor: not-allowed; opacity: .62; }
.ym-settings-editor__switch b { color: var(--ym-text); font-size: 11px; font-weight: 900; }
.ym-settings-editor__switch.is-prominent { min-height: 72px; border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-card-bg); padding: .8rem; }
.ym-settings-editor__field { display: grid; gap: .4rem; margin-top: .85rem; }
.ym-settings-editor__field > span,.ym-settings-editor__types legend { color: var(--ym-text); font-size: 11px; font-weight: 900; }
.ym-settings-editor__field input {
  width: 100%;
  min-height: 44px;
  border: 1px solid var(--ym-control-border);
  border-radius: 13px;
  outline: none;
  background: var(--ym-card-bg);
  color: var(--ym-text);
  font-size: 13px;
  font-weight: 850;
  padding: .65rem .75rem;
}
.ym-settings-editor__field input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14,165,233,.13); }
.ym-settings-editor__field input:disabled { cursor: not-allowed; opacity: .58; }
.ym-settings-editor__field small,.ym-settings-editor__types-help { color: var(--ym-muted); font-size: 9px; font-weight: 750; line-height: 1.6; }
.ym-settings-editor__field em,.ym-settings-editor__standalone-error { color: #fb7185; font-size: 10px; font-style: normal; font-weight: 850; line-height: 1.6; }
.ym-settings-editor-card__note { border-inline-start: 3px solid #38bdf8; color: var(--ym-muted); font-size: 10px; font-weight: 800; line-height: 1.7; margin: 1rem 0 0; padding-inline-start: .7rem; }
.ym-settings-editor-card__note.is-warning { border-color: #f59e0b; }
.ym-settings-editor__media-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: .9rem; }
.ym-settings-editor__media-grid > section { border: 1px solid var(--ym-soft-border); border-radius: 17px; background: var(--ym-card-bg); padding: .85rem; }
.ym-settings-editor__media-grid > section.is-wide { grid-column: 1 / -1; }
.ym-settings-editor__types { display: flex; flex-wrap: wrap; gap: .6rem; border: 0; margin: .9rem 0 0; padding: 0; }
.ym-settings-editor__types legend { width: 100%; margin-bottom: .45rem; }
.ym-settings-editor__types label { display: grid; grid-template-columns: auto 1fr; column-gap: .45rem; align-items: center; min-width: 130px; border: 1px solid var(--ym-soft-border); border-radius: 13px; color: var(--ym-text); cursor: pointer; font-size: 11px; font-weight: 850; padding: .6rem; }
.ym-settings-editor__types code { grid-column: 2; color: var(--ym-muted); font-size: 9px; }
.ym-settings-editor__types:disabled { opacity: .58; }
.ym-settings-editor__types-help,.ym-settings-editor__standalone-error { display: block; margin-top: .55rem; }
.ym-settings-editor__actions { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-top: 1px solid var(--ym-soft-border); background: linear-gradient(90deg,rgba(34,211,238,.055),rgba(139,92,246,.045)),color-mix(in srgb,var(--ym-card-bg) 96%,transparent); padding: 1rem clamp(1rem,2.5vw,1.5rem); }
.ym-settings-editor__actions > div:first-child { color: var(--ym-muted); font-size: 11px; font-weight: 850; }
.ym-settings-editor__actions > div:first-child strong { color: #38bdf8; }
.ym-settings-editor__actions > div:last-child { display: flex; gap: .6rem; }
.ym-settings-editor__actions button { min-height: 42px; border: 1px solid var(--ym-control-border); border-radius: 13px; font-size: 11px; font-weight: 950; padding: .65rem .85rem; }
.ym-settings-editor__actions button.is-secondary { background: var(--ym-control-bg); color: var(--ym-text); }
.ym-settings-editor__actions button.is-primary { border-color: rgba(14,165,233,.45); background: #0284c7; color: #fff; }
.ym-settings-editor__actions button:disabled { cursor: not-allowed; opacity: .5; }
.ym-settings-confirm { position: fixed; inset: 0; z-index: 1750; display: grid; place-items: center; background: rgba(2,6,23,.68); backdrop-filter: blur(5px); padding: 18px; }
.ym-settings-confirm__panel { display: grid; justify-items: center; width: min(100%,470px); border: 1px solid rgba(245,158,11,.4); border-radius: 20px; outline: none; background: var(--ym-dropdown-bg,#0f172a); box-shadow: 0 28px 80px rgba(0,0,0,.46); color: var(--ym-text,#f8fafc); padding: 1.5rem; text-align: center; }
.ym-settings-confirm__icon { display: grid; width: 48px; height: 48px; place-items: center; border-radius: 15px; background: rgba(245,158,11,.14); color: #fbbf24; font-size: 24px; font-weight: 950; }
.ym-settings-confirm h2 { color: var(--ym-text,#f8fafc); font-size: 1.2rem; font-weight: 950; margin: .85rem 0 .4rem; }
.ym-settings-confirm p { color: var(--ym-muted,#cbd5e1); font-size: 13px; font-weight: 750; line-height: 1.75; margin: 0; }
.ym-settings-confirm__actions { display: grid; grid-template-columns: 1fr 1fr; gap: .65rem; width: 100%; margin-top: 1.2rem; }
.ym-settings-confirm__actions button { min-height: 44px; border: 1px solid var(--ym-control-border,rgba(148,163,184,.28)); border-radius: 12px; background: var(--ym-control-bg,rgba(148,163,184,.08)); color: var(--ym-text,#f8fafc); font-size: 12px; font-weight: 950; padding: .65rem .8rem; }
.ym-settings-confirm__actions button.is-primary { border-color: rgba(245,158,11,.5); background: #d97706; color: #fff; }
.ym-settings-confirm__actions button:focus-visible { outline: 3px solid rgba(56,189,248,.38); outline-offset: 3px; }
:global(.ym-dashboard-light .ym-settings-editor-group) { border-color: color-mix(in srgb,#0891b2 22%,var(--ym-soft-border)); background: linear-gradient(145deg,rgba(34,211,238,.05),transparent 48%),color-mix(in srgb,var(--ym-control-bg) 96%,#e8f7fb 4%); }
:global(.ym-dashboard-light .ym-settings-editor-card) { border-color: color-mix(in srgb,var(--ym-soft-border) 82%,#64748b 18%); }
:global(.ym-dashboard-light .ym-settings-editor__field input:disabled),
:global(.ym-dashboard-light .ym-settings-editor__types:disabled) { opacity: .72; }
:global(.ym-dashboard-light .ym-settings-editor) {
  border-color: rgba(99,102,241,.3);
  background: rgba(248,250,253,.94);
  box-shadow: 0 16px 38px rgba(51,65,85,.11),inset 0 1px rgba(255,255,255,.92);
}
:global(.ym-dashboard-light .ym-settings-editor__head > div > span),
:global(.ym-dashboard-light .ym-settings-editor__dirty span),
:global(.ym-dashboard-light .ym-settings-editor__dirty small),
:global(.ym-dashboard-light .ym-settings-editor__metadata dt),
:global(.ym-dashboard-light .ym-settings-editor-group__head p),
:global(.ym-dashboard-light .ym-settings-editor-card header p),
:global(.ym-dashboard-light .ym-settings-editor__field small),
:global(.ym-dashboard-light .ym-settings-editor__types-help),
:global(.ym-dashboard-light .ym-settings-editor-card__note),
:global(.ym-dashboard-light .ym-settings-editor__actions > div:first-child) { color: #4b5568; }
:global(.ym-dashboard-light .ym-settings-editor__dirty),
:global(.ym-dashboard-light .ym-settings-editor__metadata article) {
  border-color: rgba(71,85,105,.24);
  background: rgba(255,255,255,.88);
  box-shadow: 0 5px 14px rgba(51,65,85,.055);
}
:global(.ym-dashboard-light .ym-settings-editor-group) {
  border-color: rgba(8,145,178,.34);
  background: linear-gradient(145deg,rgba(207,250,254,.45),rgba(241,245,249,.92) 48%);
  box-shadow: 0 8px 22px rgba(51,65,85,.065);
}
:global(.ym-dashboard-light .ym-settings-editor-group.is-media) {
  border-color: rgba(99,102,241,.32);
  background: linear-gradient(145deg,rgba(224,231,255,.58),rgba(241,245,249,.92) 48%);
}
:global(.ym-dashboard-light .ym-settings-editor-card) {
  border-color: rgba(71,85,105,.27);
  background: rgba(255,255,255,.92);
  box-shadow: 0 7px 18px rgba(51,65,85,.07);
}
:global(.ym-dashboard-light .ym-settings-editor-card[aria-disabled="true"]) {
  border-color: rgba(71,85,105,.28);
  background: rgba(241,245,249,.88);
}
:global(.ym-dashboard-light .ym-settings-editor__switch.is-prominent),
:global(.ym-dashboard-light .ym-settings-editor__media-grid > section),
:global(.ym-dashboard-light .ym-settings-editor__types label) {
  border-color: rgba(71,85,105,.25);
  background: rgba(248,250,252,.96);
  box-shadow: 0 4px 12px rgba(51,65,85,.05);
}
:global(.ym-dashboard-light .ym-settings-editor__switch > span) {
  border-color: rgba(71,85,105,.42);
  background: #e2e8f0;
  box-shadow: inset 0 1px 2px rgba(51,65,85,.12);
}
:global(.ym-dashboard-light .ym-settings-editor__switch input:checked + span) {
  border-color: #0891b2;
  background: #0891b2;
  box-shadow: 0 0 0 3px rgba(8,145,178,.12),inset 0 1px rgba(255,255,255,.25);
}
:global(.ym-dashboard-light .ym-settings-editor__switch input:focus-visible + span) {
  outline: 3px solid rgba(6,182,212,.28);
  outline-offset: 2px;
}
:global(.ym-dashboard-light .ym-settings-editor__switch input:disabled ~ *) { color: #64748b; opacity: .82; }
:global(.ym-dashboard-light .ym-settings-editor__field input) {
  border-color: rgba(71,85,105,.36);
  background: rgba(255,255,255,.98);
  box-shadow: inset 0 1px rgba(255,255,255,.95),0 3px 10px rgba(51,65,85,.055);
  color: #172033;
}
:global(.ym-dashboard-light .ym-settings-editor__field input:disabled) {
  border-color: rgba(71,85,105,.28);
  background: #eef2f7;
  color: #64748b;
  opacity: .86;
}
:global(.ym-dashboard-light .ym-settings-editor__types:disabled) { color: #64748b; opacity: .84; }
:global(.ym-dashboard-light .ym-settings-editor-card__note) {
  border-color: #0891b2;
  background: rgba(236,254,255,.62);
  border-radius: 0 10px 10px 0;
  padding-block: .45rem;
}
:global(.ym-dashboard-light .ym-settings-editor__actions) {
  border-color: rgba(71,85,105,.24);
  background: linear-gradient(90deg,rgba(207,250,254,.48),rgba(238,242,255,.62)),rgba(248,250,253,.96);
}
:global(.ym-dashboard-light .ym-settings-editor__actions button.is-secondary) {
  border-color: rgba(71,85,105,.3);
  background: rgba(255,255,255,.9);
}
:global(.ym-dashboard-light .ym-settings-editor__actions button:disabled) { color: #64748b; opacity: .72; }
@media (max-width: 840px) {
  .ym-settings-editor__metadata,.ym-settings-editor__media-grid { grid-template-columns: 1fr 1fr; }
  .ym-settings-editor-group__grid { grid-template-columns: 1fr; }
  .ym-settings-editor-card { grid-column: 1 / -1; }
}
@media (max-width: 640px) {
  .ym-settings-editor { border-radius: 22px; }
  .ym-settings-editor__head,.ym-settings-editor__conflict,.ym-settings-editor__actions { align-items: stretch; flex-direction: column; }
  .ym-settings-editor__dirty { min-width: 0; }
  .ym-settings-editor__metadata,.ym-settings-editor__cards,.ym-settings-editor__media-grid { grid-template-columns: 1fr; }
  .ym-settings-editor__metadata div,.ym-settings-editor__media-grid > section,.ym-settings-editor-card { grid-column: auto; }
  .ym-settings-editor__actions > div:last-child { display: grid; grid-template-columns: 1fr 1fr; }
  .ym-settings-confirm { align-items: end; padding: 0; }
  .ym-settings-confirm__panel { width: 100%; border-radius: 20px 20px 0 0; padding: 1.35rem 1rem calc(1.35rem + env(safe-area-inset-bottom)); }
}
@media (prefers-reduced-motion: reduce) {
  .ym-settings-editor__switch > span,.ym-settings-editor__switch > span::after { transition: none; }
}
</style>
