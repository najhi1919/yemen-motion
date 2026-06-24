<template>
  <div v-if="user" class="flex items-center p-3 bg-white/10 backdrop-blur-sm rounded-xl border border-white/10 mt-4 mx-2">
    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-sm mr-3 overflow-hidden">
      <img
        :src="userAvatar"
        :alt="user.name"
        :class="user.avatar ? 'w-full h-full object-cover' : 'w-full h-full object-contain p-1.5'"
      />
    </div>
    <div class="flex-1 min-w-0">
      <p class="text-white font-semibold text-sm truncate">{{ user.name }}</p>
      <span :class="['inline-block px-2 py-0.5 rounded text-[10px] font-bold mt-0.5', roleBadgeClass]">
        {{ roleLabel }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '~/stores/authStore'

const auth = useAuthStore()

const user = computed(() => auth.user)

const userAvatar = computed(() => user.value?.avatar || '/logo.svg')

const roleLabel = computed(() => {
  const role = auth.role
  const labels: Record<string, string> = {
    admin: 'مدير النظام',
    staff: 'موظف',
    designer: 'مصمم',
    client: 'عميل'
  }
  return labels[role || ''] || 'مستخدم'
})

const roleBadgeClass = computed(() => {
  const role = auth.role
  const colors: Record<string, string> = {
    admin: 'bg-red-500/80 text-white',
    staff: 'bg-amber-500/80 text-white',
    designer: 'bg-blue-500/80 text-white',
    client: 'bg-emerald-500/80 text-white'
  }
  return colors[role || ''] || 'bg-gray-500/80 text-white'
})

</script>
