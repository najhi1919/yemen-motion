<script setup lang="ts">
import type { ProfessionalSuggestion } from '~/data/designer-professional-catalog'
import type { DesignerProfessionalListItem } from '~/types/designer-profile-professional'

const props = withDefaults(defineProps<{
  editorType: 'specialty' | 'skill' | 'tool' | 'language'
  title: string
  items: DesignerProfessionalListItem[]
  levels?: readonly string[]
  levelLabels?: Record<string, string>
  max: number
  errorPrefix: string
  validationErrors: Record<string, string[]>
  suggestions?: ProfessionalSuggestion[]
  suggestionLabel?: string
  showSuggestionBadges?: boolean
  sectionNumber?: string
}>(), {
  levels: () => [],
  levelLabels: () => ({}),
  suggestions: () => [],
  suggestionLabel: 'اقتراحات جاهزة',
  showSuggestionBadges: false,
})

const emit = defineEmits<{
  'update:items': [items: DesignerProfessionalListItem[]]
  'draft-change': [pending: boolean]
}>()
const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
const newName = ref('')
const newLevel = ref('')
const localError = ref<string | null>(null)
const suggestionsOpen = ref(false)
const activeSuggestionIndex = ref(-1)
const leveled = computed(() => props.editorType !== 'specialty')
const hasPendingDraft = computed(() => newName.value.trim() !== '')
const listboxId = computed(() => `professional-suggestions-${props.errorPrefix.replace(/[^a-z0-9]+/gi, '-')}`)

watch(() => props.levels, levels => {
  if (!newLevel.value && levels.length) newLevel.value = levels[0] || ''
}, { immediate: true })

watch(hasPendingDraft, pending => emit('draft-change', pending))

const normalized = (value: string) => value.trim().replace(/\s+/g, ' ').toLocaleLowerCase('und')
const isOther = (suggestion: ProfessionalSuggestion) => normalized(suggestion.name) === normalized('أخرى')
const isAdded = (suggestion: ProfessionalSuggestion) => !isOther(suggestion) && props.items.some(item => normalized(item.name) === normalized(suggestion.name))
const filteredSuggestions = computed(() => {
  const query = normalized(newName.value)
  if (!query) return props.suggestions
  return props.suggestions.filter(suggestion => isOther(suggestion)
    || normalized(suggestion.name).includes(query)
    || suggestion.keywords?.some(keyword => normalized(keyword).includes(query)))
})
const activeSuggestionId = computed(() => activeSuggestionIndex.value >= 0
  ? `${listboxId.value}-option-${activeSuggestionIndex.value}`
  : undefined)
const itemError = (index: number, field: 'name' | 'level') => props.validationErrors[`${props.errorPrefix}.${index}.${field}`]?.[0]
  || props.validationErrors[`${props.errorPrefix}.${index}`]?.[0]
  || null

function defaultSuggestionLevel(): string {
  if (props.editorType === 'language') return 'basic'
  if (props.editorType === 'skill' || props.editorType === 'tool') return 'intermediate'
  return ''
}

function addValue(value: string, level = newLevel.value): boolean {
  const name = value.trim().replace(/\s+/g, ' ')
  if (name.length < 2) {
    localError.value = 'أدخل اسمًا من حرفين على الأقل.'
    return false
  }
  if (props.items.some(item => normalized(item.name) === normalized(name))) {
    localError.value = 'هذا الاسم مضاف مسبقًا.'
    return false
  }
  if (props.items.length >= props.max) {
    localError.value = 'وصلت إلى الحد الأقصى للعناصر.'
    return false
  }
  if (leveled.value && !level) {
    localError.value = 'اختر مستوى العنصر.'
    return false
  }
  const item: DesignerProfessionalListItem = leveled.value
    ? { name, level } as DesignerProfessionalListItem
    : { name }
  emit('update:items', [...props.items, item])
  newName.value = ''
  localError.value = null
  suggestionsOpen.value = false
  activeSuggestionIndex.value = -1
  return true
}

function add(): boolean {
  return addValue(newName.value)
}

function commitPending(): boolean {
  if (!hasPendingDraft.value) return true
  return add()
}

defineExpose({ commitPending })

function selectSuggestion(suggestion: ProfessionalSuggestion): void {
  if (isOther(suggestion)) {
    suggestionsOpen.value = false
    activeSuggestionIndex.value = -1
    nextTick(() => input.value?.focus())
    return
  }
  if (isAdded(suggestion)) return
  addValue(suggestion.name, defaultSuggestionLevel())
}

function openSuggestions(): void {
  if (!props.suggestions.length) return
  suggestionsOpen.value = true
  activeSuggestionIndex.value = filteredSuggestions.value.length ? 0 : -1
}

function moveActive(direction: -1 | 1): void {
  if (!suggestionsOpen.value) openSuggestions()
  const count = filteredSuggestions.value.length
  if (!count) return
  activeSuggestionIndex.value = (activeSuggestionIndex.value + direction + count) % count
}

function onNewNameInput(): void {
  localError.value = null
  openSuggestions()
}

function onInputKeydown(event: KeyboardEvent): void {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    moveActive(1)
    return
  }
  if (event.key === 'ArrowUp') {
    event.preventDefault()
    moveActive(-1)
    return
  }
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    suggestionsOpen.value = false
    activeSuggestionIndex.value = -1
    return
  }
  if (event.key !== 'Enter') return
  event.preventDefault()
  const suggestion = suggestionsOpen.value ? filteredSuggestions.value[activeSuggestionIndex.value] : undefined
  if (suggestion) selectSuggestion(suggestion)
  else add()
}

function closeSuggestions(): void {
  suggestionsOpen.value = false
  activeSuggestionIndex.value = -1
}

function onDocumentPointerDown(event: PointerEvent): void {
  const target = event.target
  if (!(target instanceof Node) || root.value?.contains(target)) return
  closeSuggestions()
}

async function onFocusOut(): Promise<void> {
  await nextTick()
  const activeElement = document.activeElement
  if (activeElement instanceof Node && root.value?.contains(activeElement)) return
  closeSuggestions()
}

onMounted(() => document.addEventListener('pointerdown', onDocumentPointerDown, true))
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocumentPointerDown, true))

function updateName(index: number, value: string): void {
  if (value.trim() && props.items.some((item, itemIndex) => itemIndex !== index && normalized(item.name) === normalized(value))) {
    localError.value = 'هذا الاسم مضاف مسبقًا.'
    return
  }
  localError.value = null
  emit('update:items', props.items.map((item, itemIndex) => itemIndex === index ? { ...item, name: value } : item))
}

function onNameInput(index: number, event: Event): void {
  updateName(index, (event.target as HTMLInputElement).value)
}

function updateLevel(index: number, value: string): void {
  emit('update:items', props.items.map((item, itemIndex) => itemIndex === index ? { ...item, level: value } : item))
}

function onLevelChange(index: number, event: Event): void {
  updateLevel(index, (event.target as HTMLSelectElement).value)
}

function remove(index: number): void {
  emit('update:items', props.items.filter((_, itemIndex) => itemIndex !== index))
}

function move(index: number, direction: -1 | 1): void {
  const target = index + direction
  if (target < 0 || target >= props.items.length) return
  const next = [...props.items]
  ;[next[index], next[target]] = [next[target]!, next[index]!]
  emit('update:items', next)
}

function suggestionFor(name: string): ProfessionalSuggestion | undefined {
  return props.suggestions.find(suggestion => !isOther(suggestion) && normalized(suggestion.name) === normalized(name))
}

function manualBadge(name: string): string {
  return Array.from(name.trim()).slice(0, 2).join('').toLocaleUpperCase('und')
}
</script>

<template>
  <fieldset ref="root" class="rounded-2xl border border-t-2 border-[var(--ym-d-border,#E5E5E5)] border-t-[#E21D1D] bg-white p-4" @focusout="onFocusOut">
    <legend class="px-2 text-sm font-extrabold text-[var(--ym-d-text,#171717)]"><span v-if="sectionNumber" class="ms-2 inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs font-black text-white" aria-hidden="true">{{ sectionNumber }}</span>{{ title }}</legend>
    <div class="space-y-3">
      <div v-for="(item, index) in items" :key="`${index}-${item.name}`" class="rounded-xl bg-[var(--ym-d-surface-muted,#F5F5F5)] p-3">
        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
          <div class="flex min-w-0 items-center gap-2">
            <span v-if="showSuggestionBadges" class="inline-flex h-9 min-w-9 shrink-0 items-center justify-center rounded-lg px-1 text-xs font-black" :style="{ backgroundColor: suggestionFor(item.name)?.badgeBackground || '#E5E5E5', color: suggestionFor(item.name)?.badgeForeground || '#171717' }" aria-hidden="true">{{ suggestionFor(item.name)?.badge || manualBadge(item.name) }}</span>
            <input :value="item.name" type="text" maxlength="80" class="min-h-11 min-w-0 flex-1 rounded-lg border border-[var(--ym-d-border-strong,#A3A3A3)] bg-white px-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200" :aria-label="`اسم ${title}: ${item.name}`" @input="onNameInput(index, $event)">
          </div>
          <select v-if="leveled" :value="'level' in item ? item.level : ''" class="min-h-11 rounded-lg border border-[var(--ym-d-border-strong,#A3A3A3)] bg-white px-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200" :aria-label="`مستوى ${item.name}`" @change="onLevelChange(index, $event)">
            <option v-for="level in levels" :key="level" :value="level">{{ levelLabels[level] || level }}</option>
          </select>
          <div class="flex gap-1">
            <button type="button" class="min-h-11 min-w-11 rounded-lg border bg-white" :disabled="index === 0" :aria-label="`تحريك ${item.name} لأعلى`" @click="move(index, -1)">↑</button>
            <button type="button" class="min-h-11 min-w-11 rounded-lg border bg-white" :disabled="index === items.length - 1" :aria-label="`تحريك ${item.name} لأسفل`" @click="move(index, 1)">↓</button>
            <button type="button" class="min-h-11 min-w-11 rounded-lg border border-red-200 bg-red-50 text-red-800" :aria-label="`حذف ${item.name}`" @click="remove(index)">×</button>
          </div>
        </div>
        <p v-if="itemError(index, 'name') || itemError(index, 'level')" class="mt-2 text-sm font-bold text-red-700">{{ itemError(index, 'name') || itemError(index, 'level') }}</p>
      </div>
    </div>

    <div class="relative mt-3">
      <p v-if="suggestions.length" class="mb-2 text-xs font-bold text-neutral-600">{{ suggestionLabel }}</p>
      <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_150px_auto]">
        <input
          ref="input"
          v-model="newName"
          type="text"
          maxlength="80"
          :placeholder="`إضافة إلى ${title}`"
          class="min-h-11 rounded-lg border border-[var(--ym-d-border-strong,#A3A3A3)] px-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200"
          role="combobox"
          aria-autocomplete="list"
          :aria-expanded="suggestionsOpen"
          :aria-controls="listboxId"
          :aria-activedescendant="activeSuggestionId"
          @focus="openSuggestions"
          @input="onNewNameInput"
          @keydown="onInputKeydown"
        >
        <select v-if="leveled" v-model="newLevel" class="min-h-11 rounded-lg border border-[var(--ym-d-border-strong,#A3A3A3)] px-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200">
          <option v-for="level in levels" :key="level" :value="level">{{ levelLabels[level] || level }}</option>
        </select>
        <button type="button" class="min-h-11 rounded-lg bg-[#E21D1D] px-4 font-bold text-white transition-colors hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:bg-neutral-300 motion-reduce:transition-none" :disabled="items.length >= max" @click="add">إضافة</button>
      </div>

      <ul v-if="suggestionsOpen && filteredSuggestions.length" :id="listboxId" role="listbox" :aria-label="suggestionLabel" class="absolute inset-x-0 top-full z-20 mt-2 max-h-72 overflow-y-auto rounded-xl border border-neutral-200 bg-white p-2 shadow-xl sm:left-[calc(150px+0.5rem)]">
        <li v-for="(suggestion, index) in filteredSuggestions" :id="`${listboxId}-option-${index}`" :key="suggestion.name" role="option" :aria-selected="isAdded(suggestion)" :aria-disabled="isAdded(suggestion)" class="mt-1 first:mt-0">
          <button type="button" class="flex min-h-11 w-full items-center gap-2 rounded-lg border px-3 py-2 text-right transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none" :class="[
            index === activeSuggestionIndex ? 'border-[#E21D1D] bg-red-50 text-[#B42318]' : 'border-transparent hover:bg-neutral-50',
            isAdded(suggestion) ? 'cursor-not-allowed border-red-200 bg-red-50/70 text-[#B42318]' : '',
          ]" :disabled="isAdded(suggestion)" @mousedown.prevent="selectSuggestion(suggestion)" @mouseenter="activeSuggestionIndex = index">
            <span v-if="showSuggestionBadges && suggestion.badge" class="inline-flex h-8 min-w-8 shrink-0 items-center justify-center rounded-lg px-1 text-xs font-black" :style="{ backgroundColor: suggestion.badgeBackground || '#F5F5F5', color: suggestion.badgeForeground || '#171717' }" aria-hidden="true">{{ suggestion.badge }}</span>
            <span class="min-w-0 flex-1">{{ suggestion.name }}</span>
            <span v-if="isAdded(suggestion)" class="font-black text-[#E21D1D]" aria-hidden="true">✓</span>
          </button>
        </li>
      </ul>
    </div>

    <p v-if="hasPendingDraft" class="mt-2 text-xs leading-5 text-[var(--ym-d-muted,#666)]">اضغط إضافة أوEnter لتثبيت العنصر، أواضغط حفظ ليُضاف تلقائيًا.</p>
    <div class="mt-2 flex justify-between gap-3 text-xs text-[var(--ym-d-muted,#666)]"><span>{{ localError }}</span><bdi dir="ltr">{{ items.length }}/{{ max }}</bdi></div>
  </fieldset>
</template>
