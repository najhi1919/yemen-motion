<script setup lang="ts">
defineProps<{ total: number }>()
</script>

<template>
  <header class="ym-designer-works-hero ym-designer-title-card p-5 sm:p-7">
    <span class="absolute inset-x-0 top-0 h-1 bg-[var(--ym-d-red)]" aria-hidden="true" />

    <div class="relative z-10 grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(240px,270px)] md:items-stretch">
      <div class="flex min-w-0 flex-col items-start justify-center py-1 md:py-3">
        <p class="mb-2 text-sm font-extrabold text-white/65">
          استوديو الأعمال
        </p>
        <h1 class="text-3xl font-black leading-tight text-white sm:text-4xl lg:text-[2.75rem]">
          أعمالي
        </h1>
        <p class="mt-3 max-w-2xl text-sm leading-7 text-white/70 sm:text-base">
          أنشئ أعمالك، طوّرها، وأدر وسائطها من مساحة واحدة.
        </p>

        <NuxtLink
          to="/designer/works/create"
          class="group mt-6 inline-flex min-h-12 w-full items-center justify-center gap-3 rounded-xl bg-[var(--ym-d-red)] px-5 text-sm font-extrabold text-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] motion-reduce:transform-none motion-reduce:transition-none sm:w-auto"
        >
          إضافة عمل جديد
          <svg viewBox="0 0 20 20" fill="none" class="h-5 w-5" aria-hidden="true">
            <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </NuxtLink>
      </div>

      <div class="ym-designer-works-metric relative flex min-h-40 flex-col justify-between overflow-hidden rounded-[20px] border border-white/15 bg-white/[0.07] p-5 text-white sm:p-6">
        <span class="relative z-10 h-1 w-10 rounded-full bg-[var(--ym-d-red)]" aria-hidden="true" />
        <div class="relative z-10">
          <span class="text-sm font-bold text-white/65">إجمالي الأعمال</span>
          <bdi class="mt-2 block text-5xl font-black leading-none text-white">{{ total.toLocaleString('ar-YE-u-nu-latn') }}</bdi>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.ym-designer-works-metric::before,
.ym-designer-works-metric::after {
  position: absolute;
  pointer-events: none;
  content: "";
}

.ym-designer-works-metric::before {
  inset: -35% 38% 20% -30%;
  border: 1px solid rgba(255, 255, 255, 0.08);
  transform: rotate(34deg);
}

.ym-designer-works-metric::after {
  right: -3rem;
  bottom: -4rem;
  width: 11rem;
  height: 11rem;
  border-radius: 999px;
  background: radial-gradient(circle, rgba(226, 29, 29, 0.25), transparent 68%);
}
</style>
