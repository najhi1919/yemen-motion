<template>
  <div class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-800 shadow-lg rounded-lg z-50">
    <div class="flex justify-between p-2 border-b">
      <div class="flex space-x-2">
        <button @click="filter='all'" :class="filterClass('all')">الكل</button>
        <button @click="filter='unread'" :class="filterClass('unread')">غير مقروء</button>
        <button @click="filter='read'" :class="filterClass('read')">مقروء</button>
      </div>
      <button @click="$emit('close')">✕</button>
    </div>
    <ul class="max-h-64 overflow-y-auto">
      <li v-for="note in filtered" :key="note.id" class="p-2 border-b flex items-center">
        <svg class="w-5 h-5 mr-2 text-blue-500"><use href="#icon-notif"/></svg>
        <div class="flex-1">
          <p class="font-medium">{{ note.title }}</p>
          <p class="text-xs text-gray-500">{{ note.time }}</p>
        </div>
      </li>
    </ul>
    <div class="p-2 text-center">
      <button class="text-sm text-blue-600" @click="markAllRead">تحديد الكل كمقروء</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useNotificationStore } from '@/stores/notificationStore'

const filter = ref('all')
const store = useNotificationStore()

const filtered = computed(() => {
  if (filter.value === 'unread') return store.unread
  if (filter.value === 'read') return store.read
  return store.all
})

function filterClass(type: string) {
  return filter.value === type ? 'font-bold' : ''
}

function markAllRead() {
  store.markAllRead()
}
</script>

<style scoped>
/* يمكن تحسين المظهر لاحقاً باستخدام Tailwind أو GSAP للأنيميشن */
</style>
