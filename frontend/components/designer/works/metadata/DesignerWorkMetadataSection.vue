<script setup lang="ts">
import type { useDesignerWorkMetadata } from '~/composables/useDesignerWorkMetadata'
import type { DesignerWorkTaxonomyOption } from '~/types/designer-work-metadata'

const props = defineProps<{
  manager: ReturnType<typeof useDesignerWorkMetadata>
}>()

const tagSearch = ref('')
const copied = ref(false)
const selectedTags = computed(() =>
  props.manager.form.tag_ids
    .map(id => props.manager.tags.value.find(tag => tag.id === id))
    .filter((tag): tag is DesignerWorkTaxonomyOption => tag !== undefined),
)
const availableTags = computed(() => {
  const query = tagSearch.value.trim().toLocaleLowerCase('ar')

  return props.manager.tags.value.filter(tag => {
    if (props.manager.form.tag_ids.includes(tag.id) || !tag.is_active) return false
    if (!query) return true
    return [tag.name_ar, tag.name_en, tag.slug]
      .filter(Boolean)
      .some(value => String(value).toLocaleLowerCase('ar').includes(query))
  })
})
const maximumReached = computed(() =>
  props.manager.form.tag_ids.length >= (props.manager.state.value?.max_tags ?? 10),
)

async function copyCode(): Promise<void> {
  const code = props.manager.current.value?.public_code
  if (!code || !import.meta.client) return

  try {
    await navigator.clipboard.writeText(code)
    copied.value = true
  } catch {
    copied.value = false
  }
}
</script>

<template>
  <section
    class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-warm)] shadow-[var(--ym-d-shadow-sm)]"
    aria-labelledby="designer-work-metadata-title"
    :aria-busy="manager.loading.value || manager.saving.value"
  >
    <header class="border-b border-[var(--ym-d-border)] p-4 sm:p-6">
      <p class="text-xs font-extrabold text-[var(--ym-d-red-strong)]">تنظيم العمل</p>
      <h2 id="designer-work-metadata-title" class="mt-1 text-xl font-extrabold text-[var(--ym-d-charcoal)]">
        التصنيف والهوية
      </h2>
      <p class="mt-1 text-sm leading-6 text-[var(--ym-d-muted)]">
        نظّم العمل ليسهل العثور عليه وعرضه بصورة صحيحة.
      </p>
    </header>

    <div v-if="manager.loading.value" class="space-y-4 p-4 sm:p-6" role="status">
      <div class="h-20 animate-pulse rounded-2xl bg-neutral-200 motion-reduce:animate-none" />
      <div class="h-32 animate-pulse rounded-2xl bg-neutral-100 motion-reduce:animate-none" />
    </div>

    <div v-else-if="manager.current.value" class="space-y-6 p-4 sm:p-6">
      <div class="rounded-2xl border border-[var(--ym-d-border)] bg-white p-4">
        <p class="text-sm font-bold text-[var(--ym-d-muted)]">رمز العمل</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
          <bdi dir="ltr" class="font-mono text-base font-black tabular-nums text-[var(--ym-d-charcoal)]">
            #{{ manager.current.value.public_code }}
          </bdi>
          <button
            type="button"
            class="min-h-11 rounded-xl border border-[var(--ym-d-red-border)] bg-white px-4 text-sm font-bold text-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-soft)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
            @click="copyCode"
          >
            نسخ
          </button>
        </div>
        <p class="mt-2 text-sm font-semibold text-emerald-700" aria-live="polite">
          {{ copied ? 'تم نسخ الرمز' : '' }}
        </p>
      </div>

      <div>
        <label for="designer-work-category" class="mb-2 block text-sm font-bold text-[var(--ym-d-charcoal)]">
          التصنيف الرئيسي
        </label>
        <select
          id="designer-work-category"
          v-model.number="manager.form.category_id"
          class="min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-4 text-base text-[var(--ym-d-text)] outline-none focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)] disabled:bg-neutral-100"
          :disabled="!manager.editable.value || manager.saving.value"
          :aria-invalid="Boolean(manager.validationErrors.value.category_id)"
        >
          <option :value="null">غير مصنف</option>
          <option
            v-if="manager.state.value?.category_tracking.is_legacy_unmapped"
            :value="manager.current.value.category_id"
          >
            التصنيف الحالي القديم
          </option>
          <option v-for="category in manager.categories.value" :key="category.id" :value="category.id">
            {{ category.name_ar }}{{ category.name_en ? ` — ${category.name_en}` : '' }}{{ category.is_active ? '' : ' (معطل)' }}
          </option>
        </select>
        <p
          v-if="manager.state.value?.category_tracking.is_legacy_unmapped"
          class="mt-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900"
        >
          التصنيف الحالي قديم وغير موجود في الكتالوج. اختر تصنيفًا جديدًا أوأزل التصنيف.
        </p>
        <p v-if="manager.validationErrors.value.category_id" class="mt-2 text-sm font-semibold text-[#B81414]" role="alert">
          {{ manager.validationErrors.value.category_id[0] }}
        </p>
      </div>

      <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h3 class="text-sm font-bold text-[var(--ym-d-charcoal)]">الوسوم</h3>
          <span class="text-sm font-semibold text-[var(--ym-d-muted)]">
            {{ manager.form.tag_ids.length }} من {{ manager.state.value?.max_tags ?? 10 }} وسوم
          </span>
        </div>

        <div v-if="selectedTags.length" class="mt-3 flex flex-wrap gap-2">
          <span
            v-for="tag in selectedTags"
            :key="tag.id"
            class="inline-flex min-h-11 max-w-full items-center gap-2 rounded-full border border-[var(--ym-d-red-border)] bg-[var(--ym-d-red-soft)] px-3 text-sm font-bold text-[var(--ym-d-red-strong)]"
          >
            <span class="max-w-40 truncate" dir="auto">{{ tag.name_ar }}</span>
            <span v-if="!tag.is_active" class="text-xs">(معطل)</span>
            <button
              type="button"
              class="grid h-8 w-8 place-items-center rounded-full hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--ym-d-red)]"
              :aria-label="`إزالة وسم ${tag.name_ar}`"
              :disabled="!manager.editable.value || manager.saving.value"
              @click="manager.removeTag(tag.id)"
            >
              ×
            </button>
          </span>
        </div>

        <label for="designer-work-tag-search" class="sr-only">ابحث في الوسوم</label>
        <input
          id="designer-work-tag-search"
          v-model="tagSearch"
          type="search"
          placeholder="ابحث في الوسوم…"
          class="mt-4 min-h-12 w-full rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-4 text-base outline-none focus:border-[var(--ym-d-red)] focus:ring-4 focus:ring-[var(--ym-d-focus)]"
          :disabled="!manager.editable.value || manager.saving.value"
        >
        <p v-if="maximumReached" class="mt-2 text-sm font-semibold text-[var(--ym-d-red-strong)]">
          وصلت إلى الحد الأقصى للوسوم.
        </p>

        <div class="mt-3 grid gap-2 sm:grid-cols-2">
          <label
            v-for="tag in availableTags"
            :key="tag.id"
            class="flex min-h-11 min-w-0 cursor-pointer items-center gap-3 rounded-xl border border-[var(--ym-d-border)] bg-white px-3 hover:border-[var(--ym-d-red-border)]"
          >
            <input
              type="checkbox"
              :checked="false"
              :disabled="maximumReached || !manager.editable.value || manager.saving.value"
              class="h-5 w-5 accent-[var(--ym-d-red)]"
              @change="manager.toggleTag(tag.id)"
            >
            <span class="min-w-0 truncate text-sm font-semibold" dir="auto">{{ tag.name_ar }}</span>
          </label>
        </div>
        <p v-if="manager.validationErrors.value.tag_ids" class="mt-2 text-sm font-semibold text-[#B81414]" role="alert">
          {{ manager.validationErrors.value.tag_ids[0] }}
        </p>
      </div>
    </div>

    <footer class="border-t border-[var(--ym-d-border)] bg-white p-4 sm:px-6">
      <p v-if="manager.dirty.value" class="mb-3 text-sm font-semibold text-amber-700">توجد تغييرات غير محفوظة</p>
      <p v-if="manager.success.value" class="mb-3 text-sm font-semibold text-emerald-700" aria-live="polite">{{ manager.success.value }}</p>
      <p v-if="manager.error.value" class="mb-3 text-sm font-semibold text-[#B81414]" role="alert">{{ manager.error.value }}</p>
      <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-5 text-sm font-bold text-[var(--ym-d-charcoal)] disabled:opacity-45"
          :disabled="manager.saving.value || !manager.dirty.value"
          @click="manager.reset"
        >
          تراجع عن التغييرات
        </button>
        <button
          type="button"
          class="min-h-11 rounded-xl bg-[var(--ym-d-red)] px-6 text-sm font-bold text-white hover:bg-[var(--ym-d-red-strong)] disabled:opacity-45 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="manager.saving.value || !manager.editable.value || !manager.dirty.value"
          @click="manager.save"
        >
          {{ manager.saving.value ? 'جارٍ الحفظ…' : 'حفظ التصنيف والوسوم' }}
        </button>
      </div>
    </footer>
  </section>
</template>
