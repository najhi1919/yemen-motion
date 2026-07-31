<script setup lang="ts">
import type {
  DesignerProfilePublicationState,
  DesignerPublicationBlockerAction,
} from '~/types/designer-profile-publication'

const props = defineProps<{
  state: DesignerProfilePublicationState | null
  loading: boolean
  error: string | null
  successMessage: string | null
}>()

const emit = defineEmits<{
  retry: []
  preview: []
  publish: []
  hide: []
  editBasic: []
  editAvatar: []
  editProfessional: []
}>()

const actionLabels: Partial<Record<DesignerPublicationBlockerAction, string>> = {
  edit_basic: 'تعديل البيانات الأساسية',
  edit_avatar: 'إضافة صورة شخصية',
  edit_professional: 'تعديل البيانات المهنية',
}

const statusLabel = computed(() => {
  if (!props.state) return ''
  if (props.state.publication.status === 'published') return 'منشور'
  if (props.state.publication.status === 'hidden') return 'مخفي'
  return props.state.readiness.ready ? 'جاهز للنشر' : 'مسودة'
})

const statusClass = computed(() => {
  if (!props.state) return ''
  if (props.state.publication.status === 'published') {
    return 'border-emerald-200 bg-emerald-50 text-emerald-800'
  }
  if (props.state.publication.status === 'hidden') {
    return 'border-amber-200 bg-amber-50 text-amber-800'
  }
  if (props.state.readiness.ready) {
    return 'border-red-200 bg-red-50 text-[#B42318]'
  }
  return 'border-neutral-200 bg-neutral-100 text-neutral-700'
})

const description = computed(() => {
  if (!props.state) return ''
  if (props.state.publication.status === 'published') {
    return 'حالة نشر ملفك مفعّلة. يمكنك معاينته أوإخفاؤه مؤقتًا.'
  }
  if (props.state.publication.status === 'hidden') {
    return 'ملفك غير ظاهر للعامة، لكن بياناته محفوظة ويمكنك إعادة نشره.'
  }
  if (props.state.readiness.ready) {
    return 'اكتملت المتطلبات الأساسية. راجع المعاينة ثم انشر ملفك عندما تكون مستعدًا.'
  }
  return 'ملفك غير ظاهر للعامة حاليًا. أكمل المتطلبات التالية حتى يصبح جاهزًا للنشر.'
})

const formattedDate = (value: string | null) => {
  if (!value) return null
  return new Intl.DateTimeFormat('ar-YE-u-nu-latn', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(new Date(value))
}

const runBlockerAction = (action: DesignerPublicationBlockerAction) => {
  if (action === 'edit_basic') emit('editBasic')
  if (action === 'edit_avatar') emit('editAvatar')
  if (action === 'edit_professional') emit('editProfessional')
}
</script>

<template>
  <section
    class="mt-6 overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-white shadow-[var(--ym-d-shadow-sm)]"
    aria-labelledby="designer-publication-title"
  >
    <header class="flex flex-col gap-3 border-b border-neutral-200 bg-[#111111] px-6 py-5 text-white sm:flex-row sm:items-center sm:justify-between sm:px-7">
      <div>
        <div class="flex items-center gap-3">
          <span class="h-1 w-9 rounded-full bg-[#E21D1D]" aria-hidden="true" />
          <h2 id="designer-publication-title" class="text-xl font-extrabold text-white">نشر الملف</h2>
        </div>
        <p class="mt-2 text-sm leading-6 text-white/70">راجع جاهزية ملفك وتحكم في ظهوره دون حذف بياناتك.</p>
      </div>
      <span
        v-if="state"
        class="inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-xs font-extrabold"
        :class="statusClass"
      >
        {{ statusLabel }}
      </span>
    </header>

    <div v-if="loading && !state" class="space-y-4 p-6 sm:p-7" aria-busy="true" aria-label="جارٍ تحميل حالة نشر الملف">
      <div class="h-5 w-32 animate-pulse rounded bg-neutral-200 motion-reduce:animate-none" />
      <div class="h-4 w-full max-w-xl animate-pulse rounded bg-neutral-100 motion-reduce:animate-none" />
      <div class="h-12 w-44 animate-pulse rounded-xl bg-neutral-100 motion-reduce:animate-none" />
    </div>

    <div v-else-if="error && !state" class="p-6 sm:p-7">
      <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-[#B42318]">
        تعذر جلب حالة نشر الملف.
      </p>
      <button
        type="button"
        class="mt-4 inline-flex min-h-11 items-center justify-center rounded-xl border border-neutral-300 bg-white px-5 text-sm font-bold text-neutral-900 transition-colors hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
        @click="emit('retry')"
      >
        إعادة المحاولة
      </button>
    </div>

    <div v-else-if="state" class="p-6 sm:p-7">
      <div aria-live="polite">
        <p
          v-if="successMessage"
          role="status"
          class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800"
        >
          {{ successMessage }}
        </p>
      </div>

      <div v-if="error" class="mb-5 flex flex-col gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <p role="alert" class="text-sm font-semibold text-[#B42318]">{{ error }}</p>
        <button
          type="button"
          class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-red-300 bg-white px-4 text-sm font-bold text-[#B42318] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
          @click="emit('retry')"
        >
          إعادة المحاولة
        </button>
      </div>

      <p class="max-w-3xl text-[15px] leading-7 text-neutral-700">{{ description }}</p>

      <p
        v-if="state.publication.status === 'published' && state.publication.published_at"
        class="mt-3 text-sm text-neutral-600"
      >
        تاريخ النشر: <bdi dir="ltr" class="font-semibold text-neutral-800">{{ formattedDate(state.publication.published_at) }}</bdi>
      </p>
      <p
        v-if="state.publication.status === 'hidden' && state.publication.hidden_at"
        class="mt-3 text-sm text-neutral-600"
      >
        تاريخ الإخفاء: <bdi dir="ltr" class="font-semibold text-neutral-800">{{ formattedDate(state.publication.hidden_at) }}</bdi>
      </p>

      <div
        v-if="!state.readiness.ready && state.readiness.blockers.length"
        class="mt-6"
      >
        <h3 class="text-sm font-extrabold text-neutral-950">ما يحتاج إلى إكمال</h3>
        <ul class="mt-3 grid gap-3 lg:grid-cols-2">
          <li
            v-for="blocker in state.readiness.blockers"
            :key="blocker.code"
            class="flex min-w-0 flex-col gap-3 rounded-xl border border-neutral-200 bg-[#FCFCFC] p-4 sm:flex-row sm:items-center sm:justify-between"
          >
            <p class="min-w-0 text-sm leading-6 text-neutral-700">{{ blocker.message }}</p>
            <button
              v-if="actionLabels[blocker.action]"
              type="button"
              class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-neutral-300 bg-white px-4 text-sm font-bold text-neutral-900 transition-colors hover:border-red-300 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
              @click="runBlockerAction(blocker.action)"
            >
              {{ actionLabels[blocker.action] }}
            </button>
          </li>
        </ul>
      </div>

      <div class="mt-6 flex flex-col gap-3 border-t border-neutral-200 pt-5 sm:flex-row sm:flex-wrap">
        <button
          v-if="state.actions.can_preview"
          type="button"
          class="inline-flex min-h-12 items-center justify-center rounded-xl border border-neutral-800 bg-white px-5 text-sm font-bold text-neutral-950 transition-colors hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-neutral-300 motion-reduce:transition-none"
          @click="emit('preview')"
        >
          {{ state.publication.status === 'draft' ? 'معاينة الملف كزائر' : 'معاينة الملف' }}
        </button>
        <button
          v-if="state.actions.can_publish"
          type="button"
          class="inline-flex min-h-12 items-center justify-center rounded-xl bg-[#E21D1D] px-6 text-sm font-bold text-white transition-colors hover:bg-[#C91414] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200 motion-reduce:transition-none"
          @click="emit('publish')"
        >
          {{ state.publication.status === 'hidden' ? 'إعادة نشر الملف' : 'نشر الملف' }}
        </button>
        <button
          v-if="state.actions.can_hide"
          type="button"
          class="inline-flex min-h-12 items-center justify-center rounded-xl border border-amber-300 bg-amber-50 px-5 text-sm font-bold text-amber-900 transition-colors hover:bg-amber-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-amber-200 motion-reduce:transition-none"
          @click="emit('hide')"
        >
          إخفاء الملف
        </button>
      </div>

      <p v-if="state.publication.status === 'published'" class="mt-4 text-xs leading-6 text-neutral-500">
        ستستخدم الصفحة العامة هذه الحالة عند تنفيذها في المرحلة التالية.
      </p>
    </div>
  </section>
</template>
