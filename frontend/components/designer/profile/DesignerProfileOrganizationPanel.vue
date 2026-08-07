<script setup lang="ts">
import type { DesignerProfileOrganizationState, DesignerProfileOrganizationType } from '~/types/designer-profile-organization'

const props = defineProps<{
  state: DesignerProfileOrganizationState | null
  logoUrl: string | null
  loading: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'edit'): void
  (e: 'retry'): void
}>()

const typeLabels: Record<DesignerProfileOrganizationType, string> = {
  studio: 'استوديو',
  agency: 'وكالة',
  company: 'شركة',
  brand: 'علامة تجارية',
  other: 'أخرى',
}
</script>

<template>
  <section
    v-if="error"
    class="mb-8 rounded-3xl border border-[var(--ym-d-red-border)] bg-[var(--ym-d-surface)] p-8 text-center shadow-[var(--ym-d-shadow-sm)]"
    role="alert"
  >
    <h2 class="text-xl font-extrabold text-[#151515]">تعذر تحميل المنشأة</h2>
    <p class="mx-auto mt-3 max-w-lg text-neutral-600">{{ error }}</p>
    <button
      type="button"
      class="mt-6 min-h-12 rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
      @click="emit('retry')"
    >
      إعادة المحاولة
    </button>
  </section>

  <section
    v-else-if="loading"
    class="mb-8 rounded-3xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-8 shadow-[var(--ym-d-shadow-sm)]"
    aria-label="جارٍ تحميل قسم المنشأة"
    aria-busy="true"
  >
    <div class="animate-pulse">
      <div class="flex items-center justify-between">
        <div class="h-6 w-40 rounded bg-neutral-200" />
        <div class="h-9 w-20 rounded-lg bg-neutral-200" />
      </div>
      <div class="mt-6 flex items-start gap-4">
        <div class="h-16 w-16 shrink-0 rounded-2xl bg-neutral-100" />
        <div class="flex-1 space-y-3 pt-1">
          <div class="h-5 w-48 rounded bg-neutral-200" />
          <div class="h-4 w-32 rounded bg-neutral-100" />
        </div>
      </div>
    </div>
  </section>

  <section
    v-else
    class="mb-8 rounded-3xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-6 shadow-[var(--ym-d-shadow-sm)] sm:p-8"
  >
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-xl font-extrabold text-[#151515] sm:text-2xl">المنشأة والعلامة التجارية</h2>
        <p v-if="!state?.organization" class="mt-2 text-sm leading-6 text-[var(--ym-d-muted)] max-w-2xl">
          أضف بيانات منشأتك أو علامتك التجارية إن وجدت. هذه الخطوة اختيارية ولا تؤثر على جاهزية نشر ملفك.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex min-h-10 items-center justify-center rounded-xl bg-neutral-100 px-5 text-[15px] font-bold text-neutral-700 transition-colors hover:bg-neutral-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] shrink-0"
        @click="emit('edit')"
      >
        <template v-if="state?.organization">تعديل</template>
        <template v-else>إضافة منشأة أو علامة تجارية</template>
      </button>
    </div>

    <div v-if="state?.organization" class="mt-8 flex items-start gap-5">
      <div v-if="logoUrl" class="shrink-0">
        <img
          :src="logoUrl"
          alt="شعار المنشأة"
          class="h-16 w-16 rounded-2xl object-cover ring-1 ring-black/5"
        >
      </div>
      <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-neutral-50 ring-1 ring-black/5">
        <svg class="h-6 w-6 text-neutral-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
        </svg>
      </div>

      <div class="flex-1 min-w-0 pt-1">
        <h3 class="truncate text-[17px] font-bold text-[#151515]">{{ state.organization.name }}</h3>
        <ul class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-[var(--ym-d-muted)]">
          <li class="flex items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="2" y="7" width="20" height="14" rx="2" stroke-linecap="round" stroke-linejoin="round" />
              <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            {{ typeLabels[state.organization.type] }}
          </li>
          <li class="flex items-center gap-1.5" :class="state.organization.show_publicly ? 'text-emerald-700' : 'text-neutral-500'">
            <svg v-if="state.organization.show_publicly" class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            <svg v-else class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
            </svg>
            {{ state.organization.show_publicly ? 'ظاهر في الملف العام' : 'مخفي عن الملف العام' }}
          </li>
          <li v-if="state.organization.website_url" class="flex items-center gap-1.5">
            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <span dir="ltr">{{ state.organization.website_url }}</span>
          </li>
        </ul>
        <p v-if="state.organization.description" class="mt-4 text-sm leading-6 text-[var(--ym-d-muted)] line-clamp-2">
          {{ state.organization.description }}
        </p>
      </div>
    </div>
  </section>
</template>
