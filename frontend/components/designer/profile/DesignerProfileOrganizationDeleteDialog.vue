<script setup lang="ts">
const props = defineProps<{
  open: boolean
  saving: boolean
  error: string | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'confirm'): void
}>()
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
      <div
        class="absolute inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity"
        aria-hidden="true"
        @click="!saving && emit('close')"
      />

      <div
        class="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8"
        role="dialog"
        aria-modal="true"
        aria-labelledby="delete-dialog-title"
        dir="rtl"
      >
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
          <svg class="h-8 w-8 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
        </div>

        <h2 id="delete-dialog-title" class="mt-5 text-center text-xl font-extrabold text-[#151515]">
          حذف المنشأة
        </h2>
        <p class="mt-3 text-center text-[15px] leading-relaxed text-neutral-600">
          هل أنت متأكد من رغبتك في حذف المنشأة؟ سيؤدي هذا الإجراء إلى مسح كافة بيانات المنشأة والشعار الخاص بها نهائياً ولا يمكن التراجع عنه.
        </p>

        <!-- خطأ API (409 أو غيره) -->
        <div v-if="error" class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3" role="alert">
          <p class="text-sm font-bold text-red-700">{{ error }}</p>
        </div>

        <div class="mt-8 flex gap-3">
          <button
            type="button"
            class="flex min-h-12 flex-1 items-center justify-center rounded-xl bg-neutral-100 px-4 font-bold text-neutral-700 transition-colors hover:bg-neutral-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-neutral-200"
            :disabled="saving"
            @click="emit('close')"
          >
            إلغاء
          </button>
          <button
            type="button"
            class="flex min-h-12 flex-1 items-center justify-center rounded-xl bg-red-600 px-4 font-bold text-white transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/30 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="saving"
            @click="emit('confirm')"
          >
            <svg v-if="saving" class="mr-2 h-5 w-5 animate-spin rtl:ml-2 rtl:mr-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" stroke-opacity="0.25" />
              <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round" />
            </svg>
            {{ saving ? 'جاري الحذف...' : 'حذف المنشأة' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
