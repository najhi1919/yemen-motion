<script setup lang="ts">
import DesignerWorkAuthoringForm from '~/components/designer/works/authoring/DesignerWorkAuthoringForm.vue'
import DesignerWorkAuthoringHeader from '~/components/designer/works/authoring/DesignerWorkAuthoringHeader.vue'

definePageMeta({ layout: 'designer' })
useHead({ title: 'إضافة عمل جديد' })

const router = useRouter()
const {
  form,
  allowedMediaTypes,
  saving,
  error,
  validationErrors,
  success,
  dirty,
  createWork,
} = useDesignerWorkAuthoring()

const save = async () => {
  const created = await createWork()
  if (created) await router.push(`/designer/works/${created.id}/edit`)
}
</script>

<template>
  <div class="ym-designer-authoring-page mx-auto w-full max-w-4xl space-y-6 px-4 py-7 sm:px-6 sm:py-10">
    <DesignerWorkAuthoringHeader title="إضافة عمل جديد" />
    <DesignerWorkAuthoringForm
      :draft="form"
      :allowed-media-types="allowedMediaTypes"
      :errors="validationErrors"
      :editable="true"
      :saving="saving"
      :dirty="dirty"
      :create-mode="true"
      :success="success"
      :general-error="error"
      @save="save"
      @cancel="router.push('/designer/works')"
    />
  </div>
</template>
