<script setup lang="ts">
defineProps<{ total: number }>()
</script>

<template>
  <header class="flex flex-wrap items-center gap-3">
    <h1 class="text-3xl font-extrabold leading-tight text-[#151515] sm:text-4xl">
      أعمالي
    </h1>
    <span class="inline-flex min-h-8 items-center rounded-full bg-[#F2F2F2] px-3 text-sm font-bold text-[#555555]">
      {{ total }} عمل
    </span>
  </header>
</template>
