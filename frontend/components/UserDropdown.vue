<template>
  <div v-if="user" class="relative">
    <button
      class="flex items-center gap-2 p-1.5 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
      :aria-label="accountTooltip"
      :title="accountTooltip"
      @click="open = !open"
    >
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0 overflow-hidden">
        <img
          :src="userAvatar"
          :alt="user?.name || 'Yemen Motion'"
          :class="user?.avatar ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-1'"
        />
      </div>
    </button>
    <div v-if="open" class="absolute left-0 mt-2 w-56 bg-white dark:bg-gray-800 shadow-xl rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden z-50">
      <div class="p-3 border-b border-gray-100 dark:border-gray-700">
        <p class="text-gray-900 dark:text-white text-sm font-bold">{{ user?.name }}</p>
        <p class="text-gray-400 dark:text-gray-500 text-xs">{{ user?.email }}</p>
      </div>
      <ul class="py-1">
        <li><NuxtLink to="/admin" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">لوحة التحكم</NuxtLink></li>
        <li><NuxtLink to="/settings" class="block px-4 py-2.5 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">الإعدادات</NuxtLink></li>
      </ul>
      <div class="border-t border-gray-100 dark:border-gray-700 py-1">
        <button @click="logout" class="w-full text-right px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10 transition-colors">تسجيل الخروج</button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/authStore'

const auth = useAuthStore()
const user = computed(() => auth.user)
const open = ref(false)
const currentLocale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')
const accountTooltip = computed(() => currentLocale.value === 'ar' ? 'إعدادات الحساب' : 'Account settings')

const userAvatar = computed(() => user.value?.avatar || '/logo.svg')

function logout() {
  open.value = false
  auth.logout()
}
</script>
