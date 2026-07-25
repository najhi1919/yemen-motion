<template>
  <section class="ym-review-filters ym-admin-surface">
    <form class="ym-review-filters__bar" @submit.prevent="submitFilters">
      <label class="ym-review-filters__search ym-admin-field">
        <span>{{ text.search }}</span>
        <input
          :value="filters.q"
          type="search"
          minlength="2"
          maxlength="80"
          :placeholder="text.searchPlaceholder"
          autocomplete="off"
          @input="patchSearch(($event.target as HTMLInputElement).value)"
        />
      </label>

      <label class="ym-review-filters__select ym-admin-field">
        <span>{{ text.status }}</span>
        <select :value="filters.status" @change="patch('status', ($event.target as HTMLSelectElement).value)">
          <option value="">{{ text.all }}</option>
          <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>

      <label class="ym-review-filters__select ym-admin-field">
        <span>{{ text.mediaType }}</span>
        <select :value="filters.media_type" @change="patch('media_type', ($event.target as HTMLSelectElement).value)">
          <option value="">{{ text.all }}</option>
          <option v-for="option in mediaOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </label>

      <AdminFilterPopover
        class="ym-review-filters__popover-trigger"
        :label="text.date"
        :summary="dateSummary"
        icon="◷"
        :aria-label="text.dateAria"
        :close-label="text.close"
        :active="Boolean(filters.from || filters.to)"
      >
        <div class="ym-review-filters__popover">
          <label class="ym-admin-field">
            <span>{{ text.from }}</span>
            <input :value="filters.from" type="date" lang="en" @input="patch('from', ($event.target as HTMLInputElement).value)" />
          </label>
          <label class="ym-admin-field">
            <span>{{ text.to }}</span>
            <input :value="filters.to" type="date" lang="en" @input="patch('to', ($event.target as HTMLInputElement).value)" />
          </label>
          <button type="button" class="ym-admin-button" @click="clearDate">{{ text.clearDate }}</button>
        </div>
      </AdminFilterPopover>

      <AdminFilterPopover
        class="ym-review-filters__popover-trigger"
        :label="text.properties"
        :summary="propertiesSummary"
        icon="◇"
        :aria-label="text.propertiesAria"
        :close-label="text.close"
        :active="Boolean(filters.assigned || filters.overdue)"
      >
        <div class="ym-review-filters__popover">
          <label class="ym-admin-field">
            <span>{{ text.assigned }}</span>
            <select :value="filters.assigned" @change="patch('assigned', ($event.target as HTMLSelectElement).value)">
              <option v-for="option in booleanOptions" :key="`assigned-${option.value}`" :value="option.value">{{ option.label }}</option>
            </select>
          </label>
          <label class="ym-admin-field" :class="{ 'is-disabled': !overdueEnabled }">
            <span>{{ text.overdue }}</span>
            <select
              :value="filters.overdue"
              :disabled="!overdueEnabled"
              :aria-describedby="!overdueEnabled ? overdueReasonId : undefined"
              @change="patch('overdue', ($event.target as HTMLSelectElement).value)"
            >
              <option v-for="option in booleanOptions" :key="`overdue-${option.value}`" :value="option.value">{{ option.label }}</option>
            </select>
            <small v-if="!overdueEnabled" :id="overdueReasonId">{{ text.overdueDisabled }}</small>
          </label>
        </div>
      </AdminFilterPopover>

      <label class="ym-review-filters__view ym-admin-field">
        <span>{{ text.perPage }}</span>
        <select :value="filters.per_page" @change="patch('per_page', Number(($event.target as HTMLSelectElement).value))">
          <option :value="15">15</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
      </label>

      <button type="submit" class="ym-admin-button is-primary" :disabled="loading">{{ text.apply }}</button>
      <button type="button" class="ym-admin-button" :disabled="loading" @click="resetFilters">{{ text.reset }}</button>
    </form>

    <p v-if="error" class="ym-review-filters__error" role="alert">{{ error }}</p>

    <div v-if="chips.length" class="ym-review-filters__chips" :aria-label="text.activeFilters">
      <button
        v-for="chip in chips"
        :key="chip.key"
        type="button"
        class="ym-admin-focusable"
        :aria-label="`${text.removeFilter}: ${chip.label}`"
        @click="$emit('remove', chip.key)"
      >
        <span>{{ chip.label }}</span><b aria-hidden="true">×</b>
      </button>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, getCurrentInstance, onBeforeUnmount } from 'vue'
import AdminFilterPopover from '~/components/admin/visual/AdminFilterPopover.vue'
import { formatYmNumber, type YmLocale } from '~/utils/ymFormatting'

interface Filters {
  q: string
  status: string
  media_type: string
  assigned: string
  overdue: string
  from: string
  to: string
  per_page: number
}
interface Option { value: string; label: string }
interface Chip { key: string; label: string }

const props = defineProps<{
  filters: Filters
  statusOptions: Option[]
  mediaOptions: Option[]
  booleanOptions: Option[]
  chips: Chip[]
  overdueEnabled: boolean
  locale: YmLocale
  loading: boolean
  error: string | null
  text: {
    search: string
    searchPlaceholder: string
    status: string
    mediaType: string
    all: string
    date: string
    dateAria: string
    from: string
    to: string
    clearDate: string
    properties: string
    propertiesAria: string
    assigned: string
    overdue: string
    overdueDisabled: string
    perPage: string
    apply: string
    reset: string
    close: string
    activeFilters: string
    removeFilter: string
    none: string
    selected: string
  }
}>()

const emit = defineEmits<{
  patch: [key: keyof Filters, value: string | number]
  search: [value: string]
  apply: []
  reset: []
  remove: [key: string]
}>()

const overdueReasonId = `ym-review-overdue-${getCurrentInstance()?.uid ?? 'filter'}`
let searchTimer: ReturnType<typeof setTimeout> | null = null
const dateSummary = computed(() => {
  if (!props.filters.from && !props.filters.to) return props.text.none
  return [props.filters.from || '—', props.filters.to || '—'].join(' – ')
})
const propertiesSummary = computed(() => {
  const count = [props.filters.assigned, props.filters.overdue].filter(Boolean).length
  return count ? `${formatYmNumber(count, props.locale)} ${props.text.selected}` : props.text.none
})

function patch(key: keyof Filters, value: string | number): void {
  emit('patch', key, value)
}
function clearSearchTimer(): void {
  if (searchTimer !== null) clearTimeout(searchTimer)
  searchTimer = null
}
function patchSearch(value: string): void {
  emit('patch', 'q', value)
  clearSearchTimer()
  searchTimer = setTimeout(() => {
    searchTimer = null
    emit('search', value)
  }, 320)
}
function submitFilters(): void {
  clearSearchTimer()
  emit('apply')
}
function resetFilters(): void {
  clearSearchTimer()
  emit('reset')
}
function clearDate(): void {
  emit('patch', 'from', '')
  emit('patch', 'to', '')
}

onBeforeUnmount(clearSearchTimer)
</script>

<style scoped>
.ym-review-filters{display:grid;gap:12px;border-color:color-mix(in srgb,var(--ym-admin-section-accent) 15%,var(--ym-admin-border-strong));padding:15px}.ym-review-filters__bar{display:grid;grid-template-columns:minmax(240px,2fr) repeat(2,minmax(132px,1fr)) repeat(2,minmax(126px,.9fr)) 88px auto auto;align-items:end;gap:10px;min-width:0}.ym-review-filters__search,.ym-review-filters__select,.ym-review-filters__view,.ym-review-filters__popover-trigger{min-width:0}.ym-review-filters__search{border-radius:14px;padding:7px;background:color-mix(in srgb,var(--ym-admin-section-accent) 6%,var(--ym-admin-surface-soft));box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--ym-admin-section-accent) 14%,transparent)}.ym-review-filters__search input{background:color-mix(in srgb,var(--ym-admin-surface-raised) 72%,transparent)}.ym-review-filters__view select{min-width:0}.ym-review-filters__popover-trigger{align-self:end}.ym-review-filters__popover-trigger :deep(.ym-admin-overlay__trigger){min-height:44px;border-radius:var(--ym-admin-radius-md)}.ym-review-filters__popover{display:grid;gap:12px}.ym-review-filters__popover .is-disabled{opacity:.62}.ym-review-filters__popover small{color:var(--ym-admin-warning);font-size:12px;line-height:1.5}.ym-review-filters__error{margin:0;border:1px solid color-mix(in srgb,var(--ym-admin-danger) 35%,transparent);border-radius:11px;padding:10px 12px;color:var(--ym-admin-danger);background:color-mix(in srgb,var(--ym-admin-danger) 8%,transparent);font-size:13px}.ym-review-filters__chips{display:flex;flex-wrap:wrap;gap:8px;border-top:1px solid var(--ym-admin-border);padding-top:11px}.ym-review-filters__chips button{display:inline-flex;min-height:36px;align-items:center;gap:8px;border:1px solid color-mix(in srgb,var(--ym-admin-section-accent) 27%,var(--ym-admin-border));border-radius:999px;padding:0 11px;color:var(--ym-admin-text);background:color-mix(in srgb,var(--ym-admin-section-accent) 7%,var(--ym-admin-surface-soft));font:inherit;font-size:12px;font-weight:850;transition:border-color var(--ym-admin-motion-fast) ease,transform var(--ym-admin-motion-fast) ease}.ym-review-filters__chips button:hover{transform:translateY(-1px);border-color:var(--ym-admin-section-accent)}.ym-review-filters__chips b{color:var(--ym-admin-danger);font-size:16px}.ym-review-filters__bar>.ym-admin-button.is-primary{min-width:126px;background:linear-gradient(135deg,var(--ym-admin-section-accent-strong),var(--ym-admin-section-accent-secondary));box-shadow:0 10px 24px color-mix(in srgb,var(--ym-admin-section-accent-strong) 16%,transparent)}.ym-review-filters__bar>.ym-admin-button:not(.is-primary){border-color:color-mix(in srgb,var(--ym-admin-section-accent) 14%,var(--ym-admin-border-strong))}@media(max-width:1400px){.ym-review-filters__bar{grid-template-columns:repeat(4,minmax(0,1fr))}.ym-review-filters__search{grid-column:span 2}.ym-review-filters__bar>.ym-admin-button{width:100%}}@media(max-width:800px){.ym-review-filters__bar{grid-template-columns:repeat(2,minmax(0,1fr))}.ym-review-filters__search{grid-column:1/-1}}@media(max-width:480px){.ym-review-filters{padding:12px}.ym-review-filters__bar{grid-template-columns:1fr}.ym-review-filters__search{grid-column:auto}.ym-review-filters__bar>.ym-admin-button{min-width:0}}
</style>
