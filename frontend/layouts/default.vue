<template>
  <AppTopBar @toggle-sidebar="()=>{}">
    <template #title>الصفحة العامة</template>
  </AppTopBar>
  <slot />
</template>

<script setup lang="ts">
import AppTopBar from '@/components/AppTopBar.vue'
</script>
