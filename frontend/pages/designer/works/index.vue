<script setup lang="ts">
import DesignerWorkArchiveDialog from '~/components/designer/works/archive/DesignerWorkArchiveDialog.vue'

const worksViewRoute = useRoute()
const worksViewRouter = useRouter()
const routeView = worksViewRoute.query.view
const worksView = ref<'grid' | 'list'>(
  routeView === 'list' || routeView === 'grid' ? routeView : 'grid',
)

watch(
  () => worksViewRoute.query.view,
  value => {
    if (value === 'grid' || value === 'list') {
      worksView.value = value

      if (import.meta.client) {
        localStorage.setItem('ym-designer-works-view', value)
      }
    }
  },
)

onMounted(async () => {
  if (
    worksViewRoute.query.view === 'grid'
    || worksViewRoute.query.view === 'list'
  ) {
    localStorage.setItem(
      'ym-designer-works-view',
      worksViewRoute.query.view,
    )
    return
  }

  const storedView = localStorage.getItem('ym-designer-works-view')

  if (storedView === 'grid' || storedView === 'list') {
    worksView.value = storedView
    await worksViewRouter.replace({
      query: {
        ...worksViewRoute.query,
        view: storedView,
      },
    })
  }
})

async function changeWorksView(value: 'grid' | 'list'): Promise<void> {
  if (worksView.value === value) {
    return
  }

  worksView.value = value

  if (import.meta.client) {
    localStorage.setItem('ym-designer-works-view', value)
  }

  await worksViewRouter.replace({
    query: {
      ...worksViewRoute.query,
      view: value,
    },
  })
}

import type { DesignerWork, DesignerWorkGroup, DesignerWorkLifecycleAction } from '~/types/designer-work'

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
  lifecycleActionBusyId,
  lifecycleActionError,
  fetchWorks,
  resetFilters,
  archiveWork,
  restoreWork,
  clearLifecycleActionError,
} = useDesignerWorks()

const pendingLifecycleWork = ref<DesignerWork | null>(null)
const pendingLifecycleAction = ref<DesignerWorkLifecycleAction>('archive')

const openLifecycleDialog = (work: DesignerWork, action: DesignerWorkLifecycleAction) => {
  if (lifecycleActionBusyId.value !== null) return
  clearLifecycleActionError()
  pendingLifecycleWork.value = work
  pendingLifecycleAction.value = action
}

const closeLifecycleDialog = () => {
  if (lifecycleActionBusyId.value !== null) return
  clearLifecycleActionError()
  pendingLifecycleWork.value = null
}

const confirmLifecycleAction = async () => {
  const work = pendingLifecycleWork.value
  if (!work || lifecycleActionBusyId.value !== null) return

  const succeeded = pendingLifecycleAction.value === 'archive'
    ? await archiveWork(work)
    : await restoreWork(work)

  if (succeeded) pendingLifecycleWork.value = null
}

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
  <div class="ym-designer-works-page mx-auto max-w-[1280px] space-y-6 overflow-x-hidden px-4 py-6 sm:space-y-7 sm:px-6 sm:py-9 lg:px-8 lg:py-10">
    <DesignerWorksHeader :total="summary.total" />
    <DesignerWorksFilters
      :view="worksView"
      @view="changeWorksView"
      :query="filters.q"
      :group="filters.group"
      :summary="summary"
      @search="applySearch"
      @group="applyGroup"
    />
    <DesignerWorksGrid
      :view="worksView"
      :works="works"
      :meta="meta"
      :cover-urls="coverUrls"
      :loading="loading"
      :updating="updating"
      :error="error"
      :filtered="Boolean(filters.q) || filters.group !== 'all'"
      :lifecycle-action-busy-id="lifecycleActionBusyId"
      @retry="fetchWorks"
      @reset="resetAndFetch"
      @page="applyPage"
      @archive="openLifecycleDialog($event, 'archive')"
      @restore="openLifecycleDialog($event, 'restore')"
    />
    <DesignerWorkArchiveDialog
      :open="pendingLifecycleWork !== null"
      :work="pendingLifecycleWork"
      :action="pendingLifecycleAction"
      :busy="lifecycleActionBusyId !== null"
      :error="lifecycleActionError"
      @confirm="confirmLifecycleAction"
      @close="closeLifecycleDialog"
    />
  </div>
</template>
