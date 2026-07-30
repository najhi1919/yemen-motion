<script setup lang="ts">
import DesignerWorkAuthoringForm from '~/components/designer/works/authoring/DesignerWorkAuthoringForm.vue'
import DesignerWorkAuthoringHeader from '~/components/designer/works/authoring/DesignerWorkAuthoringHeader.vue'
import DesignerWorkMediaSection from '~/components/designer/works/media/DesignerWorkMediaSection.vue'

definePageMeta({ layout: 'designer' })
useHead({ title: 'تعديل العمل' })

const route = useRoute()
const workId = Number(route.params.work)
const authoring = useDesignerWorkAuthoring()
const {
  form,
  work,
  allowedMediaTypes,
  editable,
  loading,
  saving,
  error,
  notFound,
  validationErrors,
  success,
  dirty,
  fetchWork,
  updateWork,
  reset,
} = authoring
const mediaManager = useDesignerWorkMedia(workId)

await fetchWork(workId)

if (work.value) {
  await mediaManager.fetchMedia()
}

const saveBasicData = async () => {
  const previousMediaType = work.value?.media_type
  const saved = await updateWork()
  if (saved && work.value?.media_type !== previousMediaType) {
    await mediaManager.fetchMedia()
  }
}
</script>

<template>
  <div class="ym-designer-authoring-page mx-auto w-full max-w-4xl space-y-6 px-4 py-7 sm:px-6 sm:py-10">
    <div v-if="loading" class="space-y-4" role="status" aria-label="جارٍ تحميل بيانات العمل">
      <div class="h-28 animate-pulse rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-muted)] motion-reduce:animate-none" />
      <div class="h-[520px] animate-pulse rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] motion-reduce:animate-none" />
    </div>

    <section v-else-if="notFound" class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-8 text-center shadow-[var(--ym-d-shadow-sm)]">
      <h1 class="text-2xl font-extrabold text-[var(--ym-d-text)]">
        العمل غير موجود
      </h1>
      <NuxtLink to="/designer/works" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[var(--ym-d-red)] px-5 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]">
        العودة إلى الأعمال
      </NuxtLink>
    </section>

    <template v-else-if="work">
      <DesignerWorkAuthoringHeader :title="work.title" :status="work.status" />
      <div
        v-if="!editable"
        class="rounded-[16px] border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900"
        role="alert"
      >
        لا يمكن تعديل العمل في حالته الحالية. يمكنك مراجعة البيانات دون حفظها.
      </div>
      <DesignerWorkAuthoringForm
        :draft="form"
        :allowed-media-types="allowedMediaTypes"
        :errors="validationErrors"
        :editable="editable"
        :saving="saving"
        :dirty="dirty"
        :create-mode="false"
        :success="success"
        :general-error="error"
        @save="saveBasicData"
        @reset="reset"
      />
      <DesignerWorkMediaSection :manager="mediaManager" />
    </template>

    <section v-else role="alert" class="rounded-[20px] border border-[var(--ym-d-red-border)] bg-[var(--ym-d-surface)] p-5 text-[#8F1111] shadow-[var(--ym-d-shadow-sm)]">
      <p>تعذر تحميل بيانات العمل.</p>
      <button type="button" class="mt-3 min-h-11 rounded-xl bg-[var(--ym-d-red)] px-5 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]" @click="fetchWork(workId)">
        إعادة المحاولة
      </button>
    </section>
  </div>
</template>
