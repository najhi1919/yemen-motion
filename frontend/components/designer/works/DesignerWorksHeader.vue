<script setup lang="ts">
defineProps<{ total: number }>()
</script>

<template>
  <header class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex min-w-0 items-center gap-3">
      <h1 class="text-3xl font-extrabold leading-tight text-[#151515] sm:text-4xl">
        أعمالي
      </h1>
      <span class="inline-flex min-h-8 items-center rounded-full bg-[#F2F2F2] px-3 text-sm font-bold text-[#555555]">
        {{ total }} عمل
      </span>
    </div>
    <NuxtLink
      to="/designer/works/create"
      class="inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-[#E21D1D] px-5 text-sm font-bold text-white transition hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none sm:w-auto"
    >
      إضافة عمل
    </NuxtLink>
  </header>
</template>
