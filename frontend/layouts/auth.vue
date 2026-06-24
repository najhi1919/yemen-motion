<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-50 dark:bg-gray-900">
    <BackgroundWatermark />
    <div class="z-10 w-full max-w-md p-6 bg-white dark:bg-gray-800 rounded shadow">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import BackgroundWatermark from '@/components/BackgroundWatermark.vue'
</script>
