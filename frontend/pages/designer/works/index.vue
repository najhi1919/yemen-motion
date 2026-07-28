<script setup lang="ts">
import type { DesignerWorkGroup } from '~/types/designer-work'

definePageMeta({
  layout: 'designer',
})

useHead({ title: 'أعمالي' })

const {
  works,
  summary,
  meta,
  filters,
  loading,
  updating,
  error,
  coverUrls,
  fetchWorks,
  resetFilters,
} = useDesignerWorks()

const applySearch = async (value: string) => {
  if (filters.q === value) return
  filters.q = value
  filters.page = 1
  await fetchWorks()
}

const applyGroup = async (value: DesignerWorkGroup) => {
  if (filters.group === value) return
  filters.group = value
  filters.page = 1
  await fetchWorks()
}

const applyPage = async (value: number) => {
  filters.page = value
  await fetchWorks()
}

const resetAndFetch = async () => {
  resetFilters()
  await fetchWorks()
}

await useAsyncData('designer-owned-works', () => fetchWorks())
</script>

<template>
  <div class="mx-auto max-w-7xl space-y-6 overflow-x-hidden px-4 py-7 sm:px-6 sm:py-9 lg:px-8">
    <DesignerWorksHeader :total="summary.total" />
    <DesignerWorksFilters
      :query="filters.q"
      :group="filters.group"
      :summary="summary"
      @search="applySearch"
      @group="applyGroup"
    />
    <DesignerWorksGrid
      :works="works"
      :meta="meta"
      :cover-urls="coverUrls"
      :loading="loading"
      :updating="updating"
      :error="error"
      :filtered="Boolean(filters.q) || filters.group !== 'all'"
      @retry="fetchWorks"
      @reset="resetAndFetch"
      @page="applyPage"
    />
  </div>
</template>
