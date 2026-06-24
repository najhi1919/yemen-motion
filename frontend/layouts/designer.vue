<template>
  <div class="flex min-h-screen">
    <AppSidebar />
    <div class="flex-1 flex flex-col">
      <AppTopBar @toggle-sidebar="toggleSidebar">
        <template #title><slot name="title">لوحة المصمم</slot></template>
      </AppTopBar>

      <BackgroundWatermark />
      <IdentityCard class="mx-auto my-4" />

      <main class="flex-1 p-4">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppTopBar from '@/components/AppTopBar.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import IdentityCard from '@/components/IdentityCard.vue'
import BackgroundWatermark from '@/components/BackgroundWatermark.vue'

function toggleSidebar() {}
</script>
