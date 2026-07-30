<script setup lang="ts">
import type { DesignerWork } from '~/types/designer-work'
import DesignerWorkCard from './DesignerWorkCard.vue'

defineProps<{
  works: readonly DesignerWork[]
  coverUrls: Readonly<Record<number, string>>
}>()
</script>

<template>
  <div class="ym-works-compact-list grid min-w-0 grid-cols-1 gap-3">
    <DesignerWorkCard
      v-for="work in works"
      :key="work.id"
      :work="work"
      :cover-url="work.cover_media ? coverUrls[work.cover_media.id] : undefined"
      variant="list"
      class="min-w-0"
    />
  </div>
</template>
