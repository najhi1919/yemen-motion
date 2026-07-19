<template>
  <WorksAuthoringWorkspace v-if="workId !== null" mode="edit" :work-id="workId" />
  <section v-else class="ym-invalid-work" role="alert" dir="rtl">
    <strong>404</strong>
    <h1>معرّف العمل غير صالح</h1>
    <p>يجب أن يحتوي المسار على معرّف رقمي موجب.</p>
    <NuxtLink to="/admin/works/all">العودة إلى كل الأعمال</NuxtLink>
  </section>
</template>

<script setup lang="ts">
import WorksAuthoringWorkspace from '~/components/works/authoring/WorksAuthoringWorkspace.vue'

definePageMeta({ layout: 'admin' })

const route = useRoute()
const workId = computed(() => {
  const raw = Array.isArray(route.params.work) ? route.params.work[0] : route.params.work
  return typeof raw === 'string' && /^[1-9][0-9]*$/.test(raw) ? Number(raw) : null
})
</script>

<style scoped>
.ym-invalid-work{display:grid;place-items:center;min-height:420px;padding:2rem;text-align:center;border:1px solid rgba(239,68,68,.25);border-radius:26px;background:rgba(127,29,29,.08)}.ym-invalid-work strong{font-size:3rem;color:#fca5a5}.ym-invalid-work p{color:#94a3b8}.ym-invalid-work a{padding:.7rem 1rem;border-radius:12px;background:#f59e0b;color:#111827;font-weight:900;text-decoration:none}
</style>
