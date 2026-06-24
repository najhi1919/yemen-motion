<template>
  <div class="flex items-center gap-2 p-2 border rounded bg-gray-50 dark:bg-gray-700">
    <input v-model="query" type="text" placeholder="بحث..." class="flex-1 bg-transparent outline-none" />
    <select v-model="filter" class="bg-transparent">
      <option value="">الكل</option>
      <option value="works">أعمال</option>
      <option value="contests">مسابقات</option>
    </select>
    <select v-model="sort" class="bg-transparent">
      <option value="newest">الأحدث</option>
      <option value="oldest">الأقدم</option>
    </select>
    <button @click="reset" class="p-1 hover:bg-gray-200 rounded">↺</button>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'

const query = ref('')
const filter = ref('')
const sort = ref('newest')
const router = useRouter()

watch([query, filter, sort], () => {
  router.replace({ query: { q: query.value, f: filter.value, s: sort.value } })
})

function reset() {
  query.value = ''
  filter.value = ''
  sort.value = 'newest'
}
</script>
