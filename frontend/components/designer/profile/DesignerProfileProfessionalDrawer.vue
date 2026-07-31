<script setup lang="ts">
import DesignerProfileProfessionalListEditor from '~/components/designer/profile/DesignerProfileProfessionalListEditor.vue'
import { resolveDesignerProfessionalCatalog } from '~/data/designer-professional-catalog'
import type {
  DesignerProfileProfessionalData,
  DesignerProfileProfessionalPayload,
  DesignerProfessionalLanguage,
  DesignerProfessionalListItem,
  DesignerProfessionalSkill,
  DesignerProfessionalSpecialty,
  DesignerProfessionalTool,
} from '~/types/designer-profile-professional'

const props = defineProps<{
  open: boolean
  professional: DesignerProfileProfessionalData | null
  saving: boolean
  error: string | null
  validationErrors: Record<string, string[]>
  primarySpecialty?: string | null
}>()
const emit = defineEmits<{ close: [], save: [payload: DesignerProfileProfessionalPayload] }>()
const drawer = ref<HTMLElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
type PendingEditor = { commitPending: () => boolean }
const serviceEditor = ref<PendingEditor | null>(null)
const styleEditor = ref<PendingEditor | null>(null)
const skillsEditor = ref<PendingEditor | null>(null)
const toolsEditor = ref<PendingEditor | null>(null)
const languagesEditor = ref<PendingEditor | null>(null)
const pendingDrafts = reactive({
  service: false,
  style: false,
  skills: false,
  tools: false,
  languages: false,
})
const showUnsavedWarning = ref(false)
const initialSnapshot = ref('')
const isHydrating = ref(false)
let returnFocus: HTMLElement | null = null
let previousOverflow = ''

type ProfessionalFormState = Omit<DesignerProfileProfessionalData, 'updated_at'>

const defaults = (): ProfessionalFormState => ({
  availability: 'unavailable', years_of_experience: null, professional_note: null,
  visibility: { availability: true, specialties: true, skills: true, tools: true, languages: true, experience: true },
  specialties: { service: [], occasion: [], style: [] }, skills: [], tools: [], languages: [],
})
const form = reactive<ProfessionalFormState>(defaults())
const catalog = computed(() => resolveDesignerProfessionalCatalog(props.primarySpecialty))
const professionalLevels = ['beginner', 'intermediate', 'advanced', 'expert'] as const
const languageLevels = ['basic', 'conversational', 'professional', 'native'] as const
const levelLabels: Record<string, string> = { beginner: 'مبتدئ', intermediate: 'متوسط', advanced: 'متقدم', expert: 'خبير', basic: 'أساسي', conversational: 'محادثة', professional: 'مهني', native: 'لغة أم' }
const visibilityLabels = { availability: 'حالة التوفر', specialties: 'التخصصات', skills: 'المهارات', tools: 'البرامج والأدوات', languages: 'اللغات', experience: 'الخبرة' }
const visibilityKeys = ['availability', 'specialties', 'skills', 'tools', 'languages', 'experience'] as const
const availabilityOptions = [
  { value: 'available', label: 'متاح للعمل' },
  { value: 'partially_available', label: 'متاح جزئيًا' },
  { value: 'unavailable', label: 'غير متاح' },
] as const
const noteLength = computed(() => form.professional_note?.length || 0)
const canonicalSnapshot = () => JSON.stringify({
  availability: form.availability,
  years_of_experience: form.years_of_experience,
  professional_note: form.professional_note,
  visibility: {
    availability: form.visibility.availability,
    specialties: form.visibility.specialties,
    skills: form.visibility.skills,
    tools: form.visibility.tools,
    languages: form.visibility.languages,
    experience: form.visibility.experience,
  },
  specialties: {
    service: form.specialties.service.map(item => ({ name: item.name })),
    occasion: form.specialties.occasion.map(item => ({ name: item.name })),
    style: form.specialties.style.map(item => ({ name: item.name })),
  },
  skills: form.skills.map(item => ({ name: item.name, level: item.level })),
  tools: form.tools.map(item => ({ name: item.name, level: item.level })),
  languages: form.languages.map(item => ({ name: item.name, level: item.level })),
})
const isDirty = computed(() => !isHydrating.value && canonicalSnapshot() !== initialSnapshot.value)
const hasPendingDrafts = computed(() => Object.values(pendingDrafts).some(Boolean))
const totalSpecialties = computed(() => form.specialties.service.length + form.specialties.style.length)
const listNamesValid = computed(() => [
  ...form.specialties.service, ...form.specialties.style,
  ...form.skills, ...form.tools, ...form.languages,
].every(item => cleanName(item.name).length >= 2 && cleanName(item.name).length <= 80))
const canSave = computed(() => (isDirty.value || hasPendingDrafts.value) && !props.saving && noteLength.value <= 1200 && totalSpecialties.value <= 12 && listNamesValid.value)
const rootError = (field: string) => props.validationErrors[field]?.[0] || null
const specialtyMax = (kind: 'service' | 'style') => Math.min(6, 12 - totalSpecialties.value + form.specialties[kind].length)

function fill(): void {
  const source = props.professional || defaults()
  isHydrating.value = true
  form.availability = source.availability
  form.years_of_experience = source.years_of_experience
  form.professional_note = source.professional_note
  form.visibility = { ...source.visibility }
  form.specialties = {
    service: source.specialties.service.map(item => ({ name: item.name })),
    occasion: [],
    style: source.specialties.style.map(item => ({ name: item.name })),
  }
  form.skills = source.skills.map(item => ({ name: item.name, level: item.level }))
  form.tools = source.tools.map(item => ({ name: item.name, level: item.level }))
  form.languages = source.languages.map(item => ({ name: item.name, level: item.level }))
  Object.keys(pendingDrafts).forEach(key => { pendingDrafts[key as keyof typeof pendingDrafts] = false })
  showUnsavedWarning.value = false
  initialSnapshot.value = canonicalSnapshot()
  isHydrating.value = false
}

function requestClose(): void {
  if (props.saving) return
  if (isDirty.value || hasPendingDrafts.value) {
    showUnsavedWarning.value = true
    return
  }
  emit('close')
}

function discard(): void {
  fill()
  emit('close')
}

const cleanName = (name: string) => name.trim().replace(/\s+/g, ' ')
async function submit(): Promise<void> {
  const committed = [
    serviceEditor.value,
    styleEditor.value,
    skillsEditor.value,
    toolsEditor.value,
    languagesEditor.value,
  ].map(editor => editor?.commitPending() ?? true)

  if (committed.includes(false)) return

  await nextTick()
  if (!canSave.value) return

  emit('save', {
    availability: form.availability,
    years_of_experience: form.years_of_experience === null ? null : Number(form.years_of_experience),
    professional_note: form.professional_note?.trim() || null,
    visibility: { ...form.visibility },
    specialties: {
      service: form.specialties.service.map(item => cleanName(item.name)),
      occasion: [],
      style: form.specialties.style.map(item => cleanName(item.name)),
    },
    skills: form.skills.map(item => ({ ...item, name: cleanName(item.name) })),
    tools: form.tools.map(item => ({ ...item, name: cleanName(item.name) })),
    languages: form.languages.map(item => ({ ...item, name: cleanName(item.name) })),
  })
}

function setSpecialties(kind: 'service' | 'style', items: DesignerProfessionalListItem[]): void {
  form.specialties[kind] = items.map(item => ({ name: item.name })) as DesignerProfessionalSpecialty[]
}
function setSkills(items: DesignerProfessionalListItem[]): void { form.skills = items as DesignerProfessionalSkill[] }
function setTools(items: DesignerProfessionalListItem[]): void { form.tools = items as DesignerProfessionalTool[] }
function setLanguages(items: DesignerProfessionalListItem[]): void { form.languages = items as DesignerProfessionalLanguage[] }
function setYears(event: Event): void {
  const value = (event.target as HTMLInputElement).value
  form.years_of_experience = value === '' ? null : Number(value)
}

function onKeydown(event: KeyboardEvent): void {
  if (!props.open) return
  if (event.key === 'Escape' && !props.saving) {
    event.preventDefault()
    requestClose()
    return
  }
  if (event.key !== 'Tab' || !drawer.value) return
  const focusable = Array.from(drawer.value.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
  const first = focusable[0]
  const last = focusable[focusable.length - 1]
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
  if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
}

watch(() => props.open, async open => {
  if (open) {
    fill()
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    closeButton.value?.focus({ preventScroll: true })
  } else {
    document.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = previousOverflow
    await nextTick()
    returnFocus?.focus({ preventScroll: true })
    returnFocus = null
  }
})
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  if (import.meta.client) document.body.style.overflow = previousOverflow
})
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 bg-black/45" aria-hidden="true" @click="requestClose" />
    <section v-if="open" ref="drawer" role="dialog" aria-modal="true" aria-labelledby="professional-drawer-title" class="fixed inset-0 z-[60] isolate flex h-dvh min-h-dvh max-h-dvh flex-col overflow-hidden shadow-2xl sm:left-auto sm:w-[min(760px,92vw)]" style="background-color: var(--ym-d-page, #FCFCFC);" dir="rtl">
      <header class="relative z-10 flex shrink-0 items-start justify-between gap-4 border-b-2 border-[#E21D1D] px-5 py-5 text-white sm:px-7" style="background-color: var(--ym-d-charcoal, #171717);">
        <div><p class="text-xs font-bold text-white/65">الملف المهني</p><h2 id="professional-drawer-title" class="mt-1 text-xl font-black">تعديل البيانات المهنية</h2><p v-if="isDirty || hasPendingDrafts" class="mt-1 text-sm font-bold text-amber-200">لديك تغييرات غير محفوظة</p></div>
        <button ref="closeButton" type="button" class="min-h-11 min-w-11 rounded-xl border border-white/30 text-xl" aria-label="إغلاق البيانات المهنية" @click="requestClose">×</button>
      </header>
      <div class="relative z-0 min-h-0 flex-1 overflow-y-auto p-4 sm:p-7" style="background-color: var(--ym-d-page, #FCFCFC);">
        <div v-if="showUnsavedWarning" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4" role="alert"><p class="font-bold text-amber-900">توجد تغييرات غير محفوظة.</p><div class="mt-3 flex gap-2"><button type="button" class="min-h-11 rounded-lg bg-amber-900 px-4 font-bold text-white" @click="discard">تجاهل وإغلاق</button><button type="button" class="min-h-11 rounded-lg border bg-white px-4 font-bold" @click="showUnsavedWarning = false">متابعة التعديل</button></div></div>
        <p v-if="error" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-800" role="alert">{{ error }}</p>
        <form id="professional-profile-form" class="space-y-5" @submit.prevent="submit">
          <section class="rounded-2xl border border-t-2 border-t-[#E21D1D] bg-white p-4">
            <h3 class="flex items-center gap-2 font-black"><span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs text-white" aria-hidden="true">1</span>حالة التوفر</h3>
            <div class="mt-3 grid gap-2 sm:grid-cols-3"><label v-for="option in availabilityOptions" :key="option.value" class="flex min-h-11 items-center gap-2 rounded-xl border p-3" :class="form.availability === option.value ? 'border-red-300 bg-red-50/50' : ''"><input v-model="form.availability" type="radio" :value="option.value" class="focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200" style="accent-color: #E21D1D;">{{ option.label }}</label></div>
            <p v-if="rootError('availability')" class="mt-2 text-sm font-bold text-red-700">{{ rootError('availability') }}</p>
          </section>
          <section class="rounded-2xl border border-t-2 border-t-[#E21D1D] bg-white p-4">
            <h3 class="flex items-center gap-2 font-black"><span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs text-white" aria-hidden="true">2</span>الخبرة</h3>
            <label for="professional-years" class="mt-3 block text-sm font-bold">سنوات الخبرة</label><input id="professional-years" :value="form.years_of_experience ?? ''" type="number" min="0" max="70" dir="ltr" class="mt-2 min-h-11 w-full rounded-xl border px-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200 sm:w-48" @input="setYears"><p v-if="rootError('years_of_experience')" class="mt-2 text-sm font-bold text-red-700">{{ rootError('years_of_experience') }}</p>
          </section>
          <section class="space-y-4 rounded-2xl border border-t-2 border-t-[#E21D1D] bg-white p-4">
            <h3 class="flex items-center gap-2 font-black"><span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs text-white" aria-hidden="true">3</span>التخصصات <bdi dir="ltr" class="text-sm text-[var(--ym-d-muted,#666)]">{{ totalSpecialties }}/12</bdi></h3>
            <DesignerProfileProfessionalListEditor ref="serviceEditor" editor-type="specialty" title="نوع الخدمة" :items="form.specialties.service" :max="specialtyMax('service')" error-prefix="specialties.service" :validation-errors="validationErrors" :suggestions="catalog.services" suggestion-label="خدمات مقترحة حسب تخصصك" @draft-change="pendingDrafts.service = $event" @update:items="setSpecialties('service', $event)" />
            <DesignerProfileProfessionalListEditor ref="styleEditor" editor-type="specialty" title="الأسلوب" :items="form.specialties.style" :max="specialtyMax('style')" error-prefix="specialties.style" :validation-errors="validationErrors" :suggestions="catalog.styles" suggestion-label="أساليب مقترحة" @draft-change="pendingDrafts.style = $event" @update:items="setSpecialties('style', $event)" />
            <p v-if="rootError('specialties')" class="text-sm font-bold text-red-700">{{ rootError('specialties') }}</p>
          </section>
          <DesignerProfileProfessionalListEditor ref="skillsEditor" section-number="4" editor-type="skill" title="المهارات" :items="form.skills" :levels="professionalLevels" :level-labels="levelLabels" :max="20" error-prefix="skills" :validation-errors="validationErrors" :suggestions="catalog.skills" suggestion-label="مهارات مقترحة حسب تخصصك" @draft-change="pendingDrafts.skills = $event" @update:items="setSkills" />
          <DesignerProfileProfessionalListEditor ref="toolsEditor" section-number="5" editor-type="tool" title="البرامج والأدوات" :items="form.tools" :levels="professionalLevels" :level-labels="levelLabels" :max="20" error-prefix="tools" :validation-errors="validationErrors" :suggestions="catalog.tools" suggestion-label="أدوات مقترحة حسب تخصصك" show-suggestion-badges @draft-change="pendingDrafts.tools = $event" @update:items="setTools" />
          <DesignerProfileProfessionalListEditor ref="languagesEditor" section-number="6" editor-type="language" title="اللغات" :items="form.languages" :levels="languageLevels" :level-labels="levelLabels" :max="8" error-prefix="languages" :validation-errors="validationErrors" :suggestions="catalog.languages" suggestion-label="لغات مقترحة" show-suggestion-badges @draft-change="pendingDrafts.languages = $event" @update:items="setLanguages" />
          <section class="rounded-2xl border border-t-2 border-t-[#E21D1D] bg-white p-4"><div class="flex justify-between gap-3"><h3 class="flex items-center gap-2 font-black"><span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs text-white" aria-hidden="true">7</span>المعلومات الإضافية</h3><bdi dir="ltr" class="text-sm text-[var(--ym-d-muted,#666)]">{{ noteLength }}/1200</bdi></div><textarea v-model="form.professional_note" rows="5" maxlength="1200" class="mt-3 w-full rounded-xl border p-3 focus:border-[#E21D1D] focus:outline-none focus:ring-4 focus:ring-red-200" /><p v-if="rootError('professional_note')" class="mt-2 text-sm font-bold text-red-700">{{ rootError('professional_note') }}</p></section>
          <section class="rounded-2xl border border-t-2 border-t-[#E21D1D] bg-white p-4"><h3 class="flex items-center gap-2 font-black"><span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-[#E21D1D] px-2 text-xs text-white" aria-hidden="true">8</span>الخصوصية</h3><p class="mt-1 text-sm text-[var(--ym-d-muted,#666)]">يُستخدم هذا الإعداد عند نشر الملف العام لاحقًا.</p><div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2"><label v-for="key in visibilityKeys" :key="key" class="flex min-h-12 cursor-pointer items-center gap-2.5 rounded-xl border bg-white px-4 py-3 text-right transition-colors hover:bg-neutral-50 focus-within:ring-4 focus-within:ring-red-200 motion-reduce:transition-none" :class="form.visibility[key] ? 'border-red-300 bg-red-50/50' : 'border-neutral-200'"><input v-model="form.visibility[key]" type="checkbox" class="h-5 w-5 shrink-0 cursor-pointer focus-visible:outline-none" style="accent-color: #E21D1D;"><span>{{ visibilityLabels[key] }}</span></label></div></section>
        </form>
      </div>
      <footer class="relative z-10 shrink-0 border-t bg-white p-4 sm:px-7" style="background-color: #FFFFFF;"><button type="submit" form="professional-profile-form" :disabled="!canSave" class="min-h-12 w-full rounded-xl bg-[#E21D1D] px-6 font-black text-white transition-colors hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 disabled:cursor-not-allowed disabled:bg-neutral-300 disabled:text-neutral-600 motion-reduce:transition-none sm:w-auto">{{ saving ? 'جارٍ الحفظ…' : 'حفظ البيانات المهنية' }}</button></footer>
    </section>
  </Teleport>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: 0.01ms !important; } }
</style>
