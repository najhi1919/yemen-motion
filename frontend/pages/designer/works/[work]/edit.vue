<script setup lang="ts">
import DesignerWorkAuthoringForm from '~/components/designer/works/authoring/DesignerWorkAuthoringForm.vue'
import DesignerWorkAuthoringHeader from '~/components/designer/works/authoring/DesignerWorkAuthoringHeader.vue'

definePageMeta({ layout: 'designer' })
useHead({ title: 'تعديل العمل' })

const route = useRoute()
const workId = Number(route.params.work)
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
} = useDesignerWorkAuthoring()

await useAsyncData(`designer-work-authoring-${workId}`, () => fetchWork(workId))
</script>

<template>
  <div class="mx-auto w-full max-w-4xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
    <div v-if="loading" class="space-y-4" role="status" aria-label="جارٍ تحميل بيانات العمل">
      <div class="h-24 animate-pulse rounded-[18px] bg-neutral-200 motion-reduce:animate-none" />
      <div class="h-[520px] animate-pulse rounded-[18px] bg-neutral-200 motion-reduce:animate-none" />
    </div>

    <section v-else-if="notFound" class="rounded-[18px] border border-[rgba(17,17,17,0.1)] bg-white p-8 text-center">
      <h1 class="text-2xl font-extrabold text-[#151515]">
        العمل غير موجود
      </h1>
      <NuxtLink to="/designer/works" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-[#E21D1D] px-5 font-bold text-white">
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
        @save="updateWork"
        @reset="reset"
      />
    </template>

    <section v-else role="alert" class="rounded-[18px] border border-red-200 bg-red-50 p-5 text-[#8F1111]">
      <p>تعذر تحميل بيانات العمل.</p>
      <button type="button" class="mt-3 min-h-11 rounded-xl bg-[#E21D1D] px-5 font-bold text-white" @click="fetchWork(workId)">
        إعادة المحاولة
      </button>
    </section>
  </div>
</template>
