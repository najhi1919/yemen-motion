<script setup lang="ts">
defineProps<{
  title: string
  status?: string
}>()
</script>

<template>
  <header class="flex flex-wrap items-center justify-between gap-4 rounded-[18px] border border-[rgba(17,17,17,0.1)] bg-white px-5 py-4 shadow-[0_5px_18px_rgba(17,17,17,0.035)] sm:px-6">
    <div class="min-w-0">
      <h1 class="break-words text-2xl font-extrabold text-[#151515] sm:text-3xl" dir="auto">
        {{ title }}
      </h1>
      <span
        v-if="status"
        class="mt-2 inline-flex rounded-full bg-[#F2F2F2] px-3 py-1 text-xs font-bold text-[#555555]"
      >
        {{ status === 'changes_requested' ? 'يحتاج تعديلًا' : status === 'draft' ? 'مسودة' : status }}
      </span>
    </div>
    <NuxtLink
      to="/designer/works"
      class="inline-flex min-h-11 items-center justify-center rounded-xl border border-[rgba(17,17,17,0.14)] px-4 text-sm font-bold text-[#333333] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
    >
      العودة إلى الأعمال
    </NuxtLink>
  </header>
</template>
