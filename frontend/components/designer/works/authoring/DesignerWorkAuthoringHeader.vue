<script setup lang="ts">
defineProps<{
  title: string
  status?: string
}>()
</script>

<template>
  <header class="ym-designer-title-card px-5 py-5 sm:px-6">
    <span class="absolute inset-y-0 right-0 w-1 bg-[var(--ym-d-red)]" aria-hidden="true" />
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex min-w-0 items-start gap-3">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-white/15 bg-white/10 text-white" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
            <path d="M5 19h14M7 16.5 16.5 7l.5-3 3 3-3 3L7.5 19H5v-2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </span>
        <div class="min-w-0">
          <p class="text-xs font-extrabold text-white/65">استوديو التأليف</p>
          <h1 class="mt-1 break-words text-2xl font-extrabold text-white sm:text-3xl" dir="auto">
            {{ title }}
          </h1>
          <span
            v-if="status"
            class="mt-2 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-bold text-white ring-1 ring-inset ring-white/15"
          >
            {{ status === 'changes_requested' ? 'يحتاج تعديلًا' : status === 'draft' ? 'مسودة' : status }}
          </span>
        </div>
      </div>
      <NuxtLink
        to="/designer/works"
        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[var(--ym-d-red)] bg-[var(--ym-d-red)] px-4 text-sm font-bold text-white transition duration-200 hover:border-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transition-none"
      >
        العودة إلى الأعمال
      </NuxtLink>
    </div>
  </header>
</template>
