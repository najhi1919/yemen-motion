<template>
  <section class="ym-index-filters" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
    <form class="ym-filter-command" @submit.prevent="submit">
      <label class="ym-filter-command__search">
        <span class="sr-only">{{ text.search }}</span>
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6" /><path d="m16 16 4 4" /></svg>
        <input
          v-model.trim="draft.q"
          type="search"
          minlength="2"
          maxlength="80"
          autocomplete="off"
          :placeholder="text.searchPlaceholder"
          :aria-label="text.search"
          :title="text.searchTooltip"
        />
        <kbd aria-hidden="true">⌕</kbd>
      </label>

      <WorksIndexFilterPopover
        :label="text.status"
        :summary="statusSummary"
        :aria-label="text.chooseStatus"
        :tooltip="text.chooseStatus"
        :close-label="text.close"
        :active="Boolean(draft.status)"
        :disabled="loading"
      >
        <template #default="{ close }">
          <div class="ym-filter-options">
            <button v-for="option in statusOptions" :key="option.value" type="button" :class="{ 'is-selected': draft.status === option.value }" :title="option.label" :aria-label="option.label" @click="draft.status = option.value; close()">{{ option.label }}</button>
          </div>
        </template>
      </WorksIndexFilterPopover>

      <WorksIndexFilterPopover
        :label="text.visibility"
        :summary="visibilitySummary"
        :aria-label="text.chooseVisibility"
        :tooltip="text.chooseVisibility"
        :close-label="text.close"
        :active="Boolean(draft.visibility_status)"
        :disabled="loading"
      >
        <template #default="{ close }"><div class="ym-filter-options"><button v-for="option in visibilityOptions" :key="option.value" type="button" :class="{ 'is-selected': draft.visibility_status === option.value }" :title="option.label" :aria-label="option.label" @click="draft.visibility_status = option.value; close()">{{ option.label }}</button></div></template>
      </WorksIndexFilterPopover>

      <WorksIndexFilterPopover
        :label="text.media"
        :summary="mediaSummary"
        :aria-label="text.chooseMedia"
        :tooltip="text.chooseMedia"
        :close-label="text.close"
        :active="Boolean(draft.media_type)"
        :disabled="loading"
      >
        <template #default="{ close }"><div class="ym-filter-options"><button v-for="option in mediaOptions" :key="option.value" type="button" :class="{ 'is-selected': draft.media_type === option.value }" :title="option.label" :aria-label="option.label" @click="draft.media_type = option.value; close()">{{ option.label }}</button></div></template>
      </WorksIndexFilterPopover>

      <WorksIndexFilterPopover
        :label="text.date"
        :summary="dateSummary"
        :aria-label="text.chooseDate"
        :tooltip="text.chooseDate"
        :close-label="text.close"
        :active="Boolean(draft.from || draft.to)"
        :disabled="loading"
      >
        <template #default="{ close }">
          <div class="ym-date-filter">
            <div class="ym-filter-options is-grid">
              <button v-for="preset in datePresets" :key="preset.key" type="button" :title="preset.label" :aria-label="preset.label" @click="selectDatePreset(preset.key)">{{ preset.label }}</button>
            </div>
            <div v-if="customDateVisible" class="ym-date-filter__custom">
              <label><span>{{ text.from }}</span><input v-model="dateDraft.from" type="date" lang="en" dir="ltr" /></label>
              <label><span>{{ text.to }}</span><input v-model="dateDraft.to" type="date" lang="en" dir="ltr" /></label>
            </div>
            <footer>
              <button type="button" class="is-primary" :title="text.applyDate" @click="applyDate(close)">{{ text.applyDate }}</button>
              <button type="button" :title="text.clearDate" @click="clearDate">{{ text.clearDate }}</button>
              <button type="button" :title="text.cancel" @click="cancelDate(close)">{{ text.cancel }}</button>
            </footer>
          </div>
        </template>
      </WorksIndexFilterPopover>

      <WorksIndexFilterPopover
        :label="text.properties"
        :summary="propertiesSummary"
        :aria-label="text.chooseProperties"
        :tooltip="text.chooseProperties"
        :close-label="text.close"
        :active="propertiesCount > 0"
        :disabled="loading"
      >
        <div class="ym-property-options">
          <label v-for="property in propertyOptions" :key="property.key">
            <span>{{ property.label }}</span>
            <select v-model="draft[property.key]">
              <option value="">{{ text.all }}</option>
              <option value="1">{{ text.yes }}</option>
              <option value="0">{{ text.no }}</option>
            </select>
          </label>
        </div>
      </WorksIndexFilterPopover>

      <WorksIndexFilterPopover
        :label="text.display"
        :summary="formatYmNumber(draft.per_page, locale)"
        :aria-label="text.chooseDisplay"
        :tooltip="text.chooseDisplay"
        :close-label="text.close"
        :active="draft.per_page !== 15"
        :disabled="loading"
      >
        <template #default="{ close }"><div class="ym-filter-options"><button v-for="size in [15, 25, 50]" :key="size" type="button" :class="{ 'is-selected': draft.per_page === size }" :title="formatYmNumber(size, locale)" :aria-label="formatYmNumber(size, locale)" @click="draft.per_page = size; close()">{{ formatYmNumber(size, locale) }}</button></div></template>
      </WorksIndexFilterPopover>

      <div class="ym-filter-command__actions">
        <button type="submit" class="is-primary" :disabled="loading" :title="text.applyTooltip" :aria-label="text.applyTooltip">{{ text.apply }}</button>
        <button type="button" class="is-secondary" :disabled="loading" :title="text.resetTooltip" :aria-label="text.resetTooltip" @click="$emit('reset')">{{ text.reset }}</button>
      </div>
    </form>

    <p v-if="error" class="ym-index-filters__error" role="alert">{{ error }}</p>

    <div v-if="chips.length" class="ym-index-filters__chips" :aria-label="text.activeFilters">
      <span>{{ text.activeFilters }}</span>
      <button v-for="chip in chips" :key="chip.key" type="button" :title="text.removeFilter(chip.label)" :aria-label="text.removeFilter(chip.label)" @click="$emit('remove', chip.key)">
        {{ chip.label }}<b aria-hidden="true">×</b>
      </button>
      <button type="button" class="is-clear" :title="text.clearAll" @click="$emit('reset')">{{ text.clearAll }}</button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import WorksIndexFilterPopover from './WorksIndexFilterPopover.vue'
import { formatYmDate, formatYmNumber } from '~/utils/ymFormatting'

type FilterKey = 'q' | 'status' | 'visibility_status' | 'media_type' | 'is_featured' | 'is_pinned' | 'reported' | 'from' | 'to'
type BooleanKey = 'is_featured' | 'is_pinned' | 'reported'
interface FilterModel {
  q: string
  status: string
  visibility_status: string
  media_type: string
  is_featured: string
  is_pinned: string
  reported: string
  from: string
  to: string
  per_page: number
}

const props = defineProps<{ locale: 'ar' | 'en'; modelValue: FilterModel; applied: FilterModel; loading: boolean; error: string | null }>()
const emit = defineEmits<{ apply: [filters: FilterModel]; reset: []; remove: [key: FilterKey] }>()
const draft = reactive<FilterModel>({ ...props.modelValue })
const dateDraft = reactive({ from: draft.from, to: draft.to })
const customDateVisible = ref(Boolean(draft.from || draft.to))

const copies = {
  ar: {
    search: 'البحث', searchPlaceholder: 'ابحث في العنوان أو الملخص…', searchTooltip: 'البحث في الأعمال',
    status: 'الحالة', visibility: 'الظهور', media: 'الوسائط', date: 'التاريخ', properties: 'الخصائص', display: 'العرض',
    all: 'الكل', public: 'عام', hidden: 'مخفي', image: 'صورة', video: 'فيديو', gallery: 'معرض صور',
    featured: 'مميز', pinned: 'مثبت', reported: 'عليه بلاغات', yes: 'نعم', no: 'لا',
    apply: 'تطبيق', reset: 'إعادة الضبط', close: 'إغلاق', cancel: 'إلغاء',
    chooseStatus: 'اختيار حالة الأعمال', chooseVisibility: 'اختيار حالة الظهور', chooseMedia: 'اختيار نوع الوسائط',
    chooseDate: 'اختيار نطاق تاريخ الإنشاء', chooseProperties: 'اختيار خصائص الأعمال', chooseDisplay: 'اختيار عدد العناصر في الصفحة',
    applyTooltip: 'تطبيق الفلاتر المحددة', resetTooltip: 'مسح الفلاتر وإعادة القيم الافتراضية',
    today: 'اليوم', last7: 'آخر 7 أيام', last30: 'آخر 30 يومًا', thisMonth: 'هذا الشهر', custom: 'نطاق مخصص',
    from: 'من', to: 'إلى', applyDate: 'تطبيق التاريخ', clearDate: 'مسح التاريخ',
    activeFilters: 'الفلاتر النشطة', clearAll: 'مسح الكل', propertiesActive: (count: string) => `${count} نشطة`,
    removeFilter: (label: string) => `إزالة فلتر ${label}`
  },
  en: {
    search: 'Search', searchPlaceholder: 'Search title or summary…', searchTooltip: 'Search works',
    status: 'Status', visibility: 'Visibility', media: 'Media', date: 'Date', properties: 'Properties', display: 'Show',
    all: 'All', public: 'Public', hidden: 'Hidden', image: 'Image', video: 'Video', gallery: 'Gallery',
    featured: 'Featured', pinned: 'Pinned', reported: 'Reported', yes: 'Yes', no: 'No',
    apply: 'Apply', reset: 'Reset', close: 'Close', cancel: 'Cancel',
    chooseStatus: 'Choose work status', chooseVisibility: 'Choose visibility', chooseMedia: 'Choose media type',
    chooseDate: 'Choose created date range', chooseProperties: 'Choose work properties', chooseDisplay: 'Choose items per page',
    applyTooltip: 'Apply selected filters', resetTooltip: 'Clear filters and restore defaults',
    today: 'Today', last7: 'Last 7 days', last30: 'Last 30 days', thisMonth: 'This month', custom: 'Custom range',
    from: 'From', to: 'To', applyDate: 'Apply date', clearDate: 'Clear date',
    activeFilters: 'Active filters', clearAll: 'Clear all', propertiesActive: (count: string) => `${count} active`,
    removeFilter: (label: string) => `Remove ${label} filter`
  }
} as const

const text = computed(() => copies[props.locale])
const statusLabels = computed<Record<string, string>>(() => props.locale === 'ar'
  ? { draft: 'مسودة', submitted: 'قيد المراجعة', in_review: 'تحت المراجعة', changes_requested: 'تعديلات مطلوبة', approved: 'معتمد', published: 'منشور', rejected: 'مرفوض', hidden: 'مخفي', archived: 'مؤرشف' }
  : { draft: 'Draft', submitted: 'Submitted', in_review: 'In review', changes_requested: 'Changes requested', approved: 'Approved', published: 'Published', rejected: 'Rejected', hidden: 'Hidden', archived: 'Archived' })
const statusOptions = computed(() => [{ value: '', label: text.value.all }, ...Object.entries(statusLabels.value).map(([value, label]) => ({ value, label }))])
const visibilityOptions = computed(() => [{ value: '', label: text.value.all }, { value: 'public', label: text.value.public }, { value: 'hidden', label: text.value.hidden }])
const mediaLabels = computed<Record<string, string>>(() => ({ image: text.value.image, video: text.value.video, gallery: text.value.gallery }))
const mediaOptions = computed(() => [{ value: '', label: text.value.all }, ...Object.entries(mediaLabels.value).map(([value, label]) => ({ value, label }))])
const propertyOptions = computed<Array<{ key: BooleanKey; label: string }>>(() => [
  { key: 'is_featured', label: text.value.featured },
  { key: 'is_pinned', label: text.value.pinned },
  { key: 'reported', label: text.value.reported }
])
const datePresets = computed(() => [
  { key: 'today', label: text.value.today },
  { key: 'last7', label: text.value.last7 },
  { key: 'last30', label: text.value.last30 },
  { key: 'month', label: text.value.thisMonth },
  { key: 'custom', label: text.value.custom }
])
const propertiesCount = computed(() => propertyOptions.value.filter(option => Boolean(draft[option.key])).length)
const statusSummary = computed(() => draft.status ? statusLabels.value[draft.status] || text.value.all : text.value.all)
const visibilitySummary = computed(() => draft.visibility_status === 'public' ? text.value.public : draft.visibility_status === 'hidden' ? text.value.hidden : text.value.all)
const mediaSummary = computed(() => draft.media_type ? mediaLabels.value[draft.media_type] || text.value.all : text.value.all)
const propertiesSummary = computed(() => propertiesCount.value ? text.value.propertiesActive(formatYmNumber(propertiesCount.value, props.locale)) : text.value.all)
const dateSummary = computed(() => {
  if (!draft.from && !draft.to) return text.value.all
  return [formatDateInput(draft.from), formatDateInput(draft.to)].filter(Boolean).join(' – ')
})

function boolLabel(value: string): string {
  return value === '1' ? text.value.yes : text.value.no
}

const chips = computed(() => {
  const value = props.applied
  const result: Array<{ key: FilterKey; label: string }> = []
  if (value.q) result.push({ key: 'q', label: `${text.value.search}: ${value.q}` })
  if (value.status) result.push({ key: 'status', label: `${text.value.status}: ${statusLabels.value[value.status]}` })
  if (value.visibility_status) result.push({ key: 'visibility_status', label: `${text.value.visibility}: ${value.visibility_status === 'public' ? text.value.public : text.value.hidden}` })
  if (value.media_type) result.push({ key: 'media_type', label: `${text.value.media}: ${mediaLabels.value[value.media_type]}` })
  for (const property of propertyOptions.value) if (value[property.key]) result.push({ key: property.key, label: `${property.label}: ${boolLabel(value[property.key])}` })
  if (value.from || value.to) result.push({ key: 'from', label: `${text.value.date}: ${[formatDateInput(value.from), formatDateInput(value.to)].filter(Boolean).join(' – ')}` })
  return result
})

function inputDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function formatDateInput(value: string): string {
  return value ? formatYmDate(`${value}T12:00:00`, props.locale) : ''
}

function selectDatePreset(key: string): void {
  if (key === 'custom') {
    customDateVisible.value = true
    return
  }
  const today = new Date()
  const from = new Date(today)
  if (key === 'last7') from.setDate(today.getDate() - 6)
  if (key === 'last30') from.setDate(today.getDate() - 29)
  if (key === 'month') from.setDate(1)
  dateDraft.from = inputDate(from)
  dateDraft.to = inputDate(today)
  customDateVisible.value = false
}

function applyDate(close: () => void): void {
  draft.from = dateDraft.from
  draft.to = dateDraft.to
  close()
}

function clearDate(): void {
  dateDraft.from = ''
  dateDraft.to = ''
  draft.from = ''
  draft.to = ''
  customDateVisible.value = false
}

function cancelDate(close: () => void): void {
  dateDraft.from = draft.from
  dateDraft.to = draft.to
  customDateVisible.value = Boolean(draft.from || draft.to)
  close()
}

watch(() => props.modelValue, value => {
  Object.assign(draft, value)
  dateDraft.from = value.from
  dateDraft.to = value.to
}, { deep: true })

function submit(): void {
  emit('apply', { ...draft })
}
</script>

<style scoped>
.ym-index-filters { border: 1px solid color-mix(in srgb, var(--ym-card-border) 78%, var(--ym-violet) 22%); border-radius: 18px; padding: 13px; color: var(--ym-text); background: var(--ym-card-bg); box-shadow: var(--ym-card-shadow), inset 0 1px 0 color-mix(in srgb, #fff 9%, transparent); }
.ym-filter-command { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
.ym-filter-command__search { position: relative; display: flex; width: clamp(300px, 30vw, 420px); min-width: 260px; align-items: center; }
.ym-filter-command__search svg { position: absolute; inset-inline-start: 12px; width: 18px; fill: none; stroke: var(--ym-muted); stroke-width: 1.8; pointer-events: none; }
.ym-filter-command__search input { width: 100%; height: 44px; border: 1px solid var(--ym-control-border); border-radius: 12px; outline: none; padding-inline: 39px 38px; color: var(--ym-text); background: var(--ym-input-bg); font-size: 14px; }
.ym-filter-command__search input:focus { border-color: var(--ym-violet-electric); box-shadow: 0 0 0 3px color-mix(in srgb, var(--ym-violet-electric) 30%, transparent); }
.ym-filter-command__search kbd { position: absolute; inset-inline-end: 11px; display: grid; width: 24px; height: 24px; place-items: center; border: 1px solid var(--ym-control-border); border-radius: 7px; color: var(--ym-muted); background: var(--ym-control-bg); font-family: inherit; }
.ym-filter-command__actions { display: flex; gap: 7px; margin-inline-start: auto; }
.ym-filter-command__actions button, .ym-date-filter footer button { min-height: 44px; border: 1px solid var(--ym-control-border); border-radius: 12px; padding: 0 14px; color: var(--ym-text); background: var(--ym-input-bg); font-size: 13px; font-weight: 850; cursor: pointer; transition: transform .16s ease, box-shadow .16s ease; }
.ym-filter-command__actions .is-primary, .ym-date-filter footer .is-primary { border-color: transparent; color: #fff; background: linear-gradient(135deg, var(--ym-violet), var(--ym-magenta)); box-shadow: 0 8px 20px color-mix(in srgb, var(--ym-violet) 22%, transparent); }
.ym-filter-command__actions button:hover:not(:disabled) { transform: translateY(-1px); }
.ym-filter-command__actions button:focus-visible { outline: 3px solid color-mix(in srgb, var(--ym-violet-electric) 38%, transparent); outline-offset: 2px; }
.ym-filter-options { display: grid; gap: 6px; }
.ym-filter-options.is-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.ym-filter-options button { min-height: 40px; border: 1px solid var(--ym-control-border); border-radius: 10px; padding: 7px 10px; color: var(--ym-text); background: var(--ym-input-bg); text-align: start; cursor: pointer; }
.ym-filter-options button.is-selected { border-color: var(--ym-violet-electric); color: var(--ym-violet-electric); background: color-mix(in srgb, var(--ym-violet) 12%, transparent); }
.ym-property-options { display: grid; gap: 10px; }
.ym-property-options label, .ym-date-filter__custom label { display: grid; gap: 5px; color: var(--ym-muted); font-size: 13px; font-weight: 750; }
.ym-property-options select, .ym-date-filter__custom input { min-height: 42px; border: 1px solid var(--ym-control-border); border-radius: 10px; padding: 0 10px; color: var(--ym-text); background: var(--ym-input-bg); font-size: 14px; }
.ym-date-filter { display: grid; gap: 12px; }
.ym-date-filter__custom { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; border-top: 1px solid var(--ym-card-border); padding-top: 11px; }
.ym-date-filter footer { display: flex; flex-wrap: wrap; gap: 7px; border-top: 1px solid var(--ym-card-border); padding-top: 11px; }
.ym-date-filter footer button { min-height: 40px; padding-inline: 10px; }
.ym-index-filters__error { margin: 10px 0 0; border-radius: 10px; padding: 9px 11px; color: var(--ym-rose); background: color-mix(in srgb, var(--ym-rose) 10%, transparent); font-size: 13px; }
.ym-index-filters__chips { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; border-top: 1px solid var(--ym-card-border); margin-top: 11px; padding-top: 10px; }
.ym-index-filters__chips > span { color: var(--ym-muted); font-size: 12px; font-weight: 800; }
.ym-index-filters__chips button { min-height: 34px; border: 1px solid color-mix(in srgb, var(--ym-violet) 30%, var(--ym-card-border)); border-radius: 999px; padding: 0 10px; color: var(--ym-text); background: color-mix(in srgb, var(--ym-violet) 9%, transparent); font-size: 12px; cursor: pointer; }
.ym-index-filters__chips b { margin-inline-start: 5px; }
.ym-index-filters__chips .is-clear { border-style: dashed; color: var(--ym-violet-electric); background: transparent; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; }
@media (max-width: 1080px) { .ym-filter-command__search { width: 100%; }.ym-filter-command__actions { margin-inline-start: 0; } }
@media (max-width: 640px) { .ym-index-filters { padding: 10px; }.ym-filter-command { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }.ym-filter-command__search, .ym-filter-command__actions { grid-column: 1 / -1; width: 100%; min-width: 0; }.ym-filter-command__actions button { flex: 1; }.ym-date-filter__custom { grid-template-columns: 1fr; } }
@media (prefers-reduced-motion: reduce) { .ym-filter-command__actions button { transition: none; }.ym-filter-command__actions button:hover { transform: none; } }
</style>
