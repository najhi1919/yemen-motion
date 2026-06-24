<template>
  <button @click="toggle" class="p-2">
    {{ theme === 'dark' ? '☀' : '🌙' }}
  </button>
</template>

<script setup lang="ts">
import { useThemeStore } from '@/stores/themeStore'
const themeStore = useThemeStore()
const theme = themeStore.theme
function toggle() {
  themeStore.toggle()
}
</script>
