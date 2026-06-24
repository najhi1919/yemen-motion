<template>
  <button @click="toggle" class="p-2">
    {{ locale === 'ar' ? 'EN' : 'AR' }}
  </button>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const locale = ref('ar')
function toggle() {
  locale.value = locale.value === 'ar' ? 'en' : 'ar'
}
</script>
