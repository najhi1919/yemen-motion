<script setup lang="ts">
import { resolveProfessionalToolPresentation } from '~/data/designer-professional-catalog'
import type { DesignerProfileProfessionalEnvelope, DesignerProfessionalVisibility } from '~/types/designer-profile-professional'

const props = defineProps<{
  state: DesignerProfileProfessionalEnvelope | null
  loading: boolean
  error: string | null
}>()

defineEmits<{ edit: [], retry: [] }>()

const availabilityLabels = { available: 'متاح للعمل', partially_available: 'متاح جزئيًا', unavailable: 'غير متاح حاليًا' }
const levelLabels: Record<string, string> = {
  beginner: 'مبتدئ', intermediate: 'متوسط', advanced: 'متقدم', expert: 'خبير',
  basic: 'أساسي', conversational: 'محادثة', professional: 'مهني', native: 'لغة أم',
}
const specialtyLabels = { service: 'نوع الخدمة', style: 'الأسلوب' }
const specialtyKinds = ['service', 'style'] as const
const visibility = (key: keyof DesignerProfessionalVisibility) => props.state?.professional.visibility[key] ?? false
const toolPresentation = (name: string) => resolveProfessionalToolPresentation(name)
const availabilityClass = computed(() => {
  if (props.state?.professional.availability === 'available') return 'bg-emerald-50 text-emerald-800 ring-emerald-200'
  if (props.state?.professional.availability === 'partially_available') return 'bg-amber-50 text-amber-800 ring-amber-200'
  return 'bg-neutral-100 text-neutral-700 ring-neutral-200'
})
</script>

<template>
  <section class="mt-6 overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-white shadow-[var(--ym-d-shadow-sm)]" aria-labelledby="professional-overview-title">
    <div v-if="loading" class="animate-pulse space-y-5 p-6 motion-reduce:animate-none" aria-busy="true" aria-label="جارٍ تحميل البيانات المهنية">
      <div class="h-7 w-48 rounded bg-neutral-200" />
      <div class="h-4 w-72 max-w-full rounded bg-neutral-100" />
      <div class="grid gap-4 sm:grid-cols-2"><div v-for="n in 6" :key="n" class="h-32 rounded-2xl bg-neutral-100" /></div>
    </div>
    <div v-else-if="error && !state" class="p-7 text-center" role="alert">
      <h2 class="text-xl font-black">تعذر تحميل البيانات المهنية</h2>
      <p class="mt-2 text-[var(--ym-d-muted)]">{{ error }}</p>
      <button type="button" class="mt-4 min-h-11 rounded-xl bg-[var(--ym-d-red)] px-5 font-bold text-white" @click="$emit('retry')">إعادة المحاولة</button>
    </div>
    <template v-else-if="state">
      <header class="flex flex-col gap-4 border-b border-[var(--ym-d-border)] p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2.5">
            <h2 id="professional-overview-title" class="text-xl font-black text-[var(--ym-d-text)]">البيانات المهنية</h2>
            <span class="w-fit rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset" :class="availabilityClass">{{ availabilityLabels[state.professional.availability] }}</span>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ym-d-muted)]">نظّم تخصصاتك وخبراتك وحالة توفرك من مكان واحد.</p>
        </div>
        <button type="button" class="min-h-11 w-full shrink-0 rounded-xl bg-[var(--ym-d-charcoal)] px-5 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] sm:w-fit" @click="$emit('edit')">تعديل البيانات المهنية</button>
      </header>

      <div class="p-5 sm:p-6">
        <div v-if="state.completion.percentage < 100" class="rounded-2xl bg-[var(--ym-d-surface-warm)] p-4">
          <div class="flex items-center justify-between gap-3 text-sm font-bold"><span>الاكتمال المهني</span><bdi dir="ltr" class="text-[var(--ym-d-red-strong)]">{{ state.completion.percentage }}%</bdi></div>
          <div class="mt-3 h-2 overflow-hidden rounded-full bg-neutral-200" role="progressbar" aria-label="الاكتمال المهني" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="state.completion.percentage">
            <div class="h-full rounded-full bg-[var(--ym-d-red)] transition-[width] duration-300 motion-reduce:transition-none" :style="{ width: `${state.completion.percentage}%` }" />
          </div>
          <p class="mt-2 text-sm text-[var(--ym-d-muted)]"><bdi dir="ltr">{{ state.completion.completed }}</bdi> من <bdi dir="ltr">{{ state.completion.total }}</bdi> أقسام مكتملة</p>
        </div>
        <div v-else class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center" role="status">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 4 4L19 6" /></svg>
          </span>
          <div class="min-w-0 flex-1"><p class="font-extrabold text-emerald-950">البيانات المهنية مكتملة</p><p class="mt-0.5 text-sm leading-6 text-emerald-800">جميع بيانات هذا القسم محفوظة ومكتملة.</p></div>
          <bdi dir="ltr" class="w-fit rounded-full border border-emerald-300 bg-white px-3 py-1 text-xs font-extrabold text-emerald-800">100%</bdi>
        </div>

        <div class="mt-6 grid min-w-0 gap-4 md:grid-cols-2">
          <article class="min-w-0 rounded-2xl border border-[var(--ym-d-border)] p-4 md:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">التخصصات</h3><bdi v-if="state.professional.specialties.service.length + state.professional.specialties.style.length" dir="ltr" class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-600">{{ state.professional.specialties.service.length + state.professional.specialties.style.length }}</bdi></div>
              <span class="text-xs font-bold" :class="visibility('specialties') ? 'text-emerald-700' : 'text-neutral-500'">{{ visibility('specialties') ? 'ظاهر للعامة' : 'خاص' }}</span>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
              <div v-for="kind in specialtyKinds" :key="kind" class="min-w-0">
                <p class="text-xs font-bold text-[var(--ym-d-muted)]">{{ specialtyLabels[kind] }}</p>
                <div v-if="state.professional.specialties[kind].length" class="mt-2 flex flex-wrap gap-2"><span v-for="item in state.professional.specialties[kind]" :key="item.name" dir="auto" class="max-w-full break-words rounded-full bg-neutral-100 px-3 py-1.5 text-sm">{{ item.name }}</span></div>
                <p v-else class="mt-2 text-sm text-[var(--ym-d-muted)]">لا توجد عناصر.</p>
              </div>
            </div>
          </article>

          <article class="min-w-0 rounded-2xl border border-[var(--ym-d-border)] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">المهارات</h3><bdi v-if="state.professional.skills.length" dir="ltr" class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-600">{{ state.professional.skills.length }}</bdi></div><span class="text-xs font-bold" :class="visibility('skills') ? 'text-emerald-700' : 'text-neutral-500'">{{ visibility('skills') ? 'ظاهر للعامة' : 'خاص' }}</span></div>
            <div v-if="state.professional.skills.length" class="mt-3 flex flex-wrap gap-2"><span v-for="item in state.professional.skills" :key="item.name" class="max-w-full rounded-xl border border-neutral-200 bg-white px-3 py-2"><strong dir="auto" class="block break-words text-sm">{{ item.name }}</strong><span class="mt-0.5 block text-xs text-neutral-500">{{ levelLabels[item.level] }}</span></span></div>
            <p v-else class="mt-3 text-sm text-[var(--ym-d-muted)]">لم تضف مهارات بعد.</p>
          </article>

          <article class="min-w-0 rounded-2xl border border-[var(--ym-d-border)] p-4">
            <div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">اللغات</h3><bdi v-if="state.professional.languages.length" dir="ltr" class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-600">{{ state.professional.languages.length }}</bdi></div><span class="text-xs font-bold" :class="visibility('languages') ? 'text-emerald-700' : 'text-neutral-500'">{{ visibility('languages') ? 'ظاهر للعامة' : 'خاص' }}</span></div>
            <div v-if="state.professional.languages.length" class="mt-3 flex flex-wrap gap-2"><span v-for="item in state.professional.languages" :key="item.name" class="max-w-full rounded-xl border border-neutral-200 bg-white px-3 py-2"><strong dir="auto" class="block break-words text-sm">{{ item.name }}</strong><span class="mt-0.5 block text-xs text-neutral-500">{{ levelLabels[item.level] }}</span></span></div>
            <p v-else class="mt-3 text-sm text-[var(--ym-d-muted)]">لم تضف لغات بعد.</p>
          </article>

          <article class="min-w-0 rounded-2xl border border-[var(--ym-d-border)] p-4 md:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2"><div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">البرامج والأدوات</h3><bdi v-if="state.professional.tools.length" dir="ltr" class="rounded-full bg-neutral-100 px-2 py-0.5 text-xs font-bold text-neutral-600">{{ state.professional.tools.length }}</bdi></div><span class="text-xs font-bold" :class="visibility('tools') ? 'text-emerald-700' : 'text-neutral-500'">{{ visibility('tools') ? 'ظاهر للعامة' : 'خاص' }}</span></div>
            <div v-if="state.professional.tools.length" class="mt-4 grid min-w-0 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              <div v-for="item in state.professional.tools" :key="item.name" class="flex min-w-0 items-center gap-3 rounded-xl border border-neutral-200 bg-white p-3">
                <span dir="ltr" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-xs font-bold" :style="{ backgroundColor: toolPresentation(item.name).badgeBackground, color: toolPresentation(item.name).badgeForeground }" aria-hidden="true">{{ toolPresentation(item.name).badge }}</span>
                <div class="min-w-0"><strong dir="auto" class="block break-words text-sm text-neutral-900">{{ item.name }}</strong><span class="mt-0.5 block text-xs text-neutral-500">{{ levelLabels[item.level] }}</span></div>
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-[var(--ym-d-muted)]">لم تضف برامج أو أدوات بعد.</p>
          </article>

          <article class="col-span-full flex min-w-0 flex-col items-start gap-3 rounded-2xl border border-[var(--ym-d-border)] p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">الخبرة</h3><span class="text-xs font-bold" :class="visibility('experience') ? 'text-emerald-700' : 'text-neutral-500'">{{ visibility('experience') ? 'ظاهر للعامة' : 'خاص' }}</span></div>
            <p v-if="state.professional.years_of_experience !== null" class="text-sm"><bdi dir="ltr" class="font-bold">{{ state.professional.years_of_experience }}</bdi> سنوات خبرة</p><p v-else class="text-sm text-[var(--ym-d-muted)]">لم تحدد سنوات الخبرة.</p>
          </article>

          <article v-if="state.professional.professional_note" class="min-w-0 rounded-2xl border border-[var(--ym-d-border)] p-4 md:col-span-2">
            <div class="flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" /><h3 class="font-black">معلومات مهنية إضافية</h3></div>
            <p class="mt-3 whitespace-pre-line break-words text-sm leading-7">{{ state.professional.professional_note }}</p>
          </article>
        </div>
      </div>
    </template>
  </section>
</template>
