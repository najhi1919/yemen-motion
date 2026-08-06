<script setup lang="ts">
import type { DeepReadonly } from 'vue'
import type {
  DesignerProfileFeaturedWork,
  DesignerProfileFeaturedWorksEnvelope,
} from '~/types/designer-profile-featured-works'

type FeaturedWorkView =
  DeepReadonly<DesignerProfileFeaturedWork>

type FeaturedWorksView =
  DeepReadonly<DesignerProfileFeaturedWorksEnvelope>

const props = defineProps<{
  state: FeaturedWorksView | null
  loading: boolean
  error: string | null
  coverUrls: Readonly<Record<number, string>>
}>()

defineEmits<{
  edit: []
  retry: []
}>()

const coverSource = (
  work: FeaturedWorkView,
): string | null => {
  const mediaId = work.cover_media?.id

  return mediaId ? props.coverUrls[mediaId] || null : null
}
</script>

<template>
  <section
    class="mt-6 overflow-hidden rounded-[20px] border border-[var(--ym-d-border)] bg-white shadow-[var(--ym-d-shadow-sm)]"
    aria-labelledby="featured-works-overview-title"
  >
    <div
      v-if="loading"
      class="animate-pulse space-y-5 p-6 motion-reduce:animate-none"
      aria-busy="true"
      aria-label="جارٍ تحميل الأعمال المميزة"
    >
      <div class="h-7 w-48 rounded bg-neutral-200" />
      <div class="h-4 w-80 max-w-full rounded bg-neutral-100" />
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="item in 3"
          :key="item"
          class="h-40 rounded-2xl bg-neutral-100"
        />
      </div>
    </div>

    <div
      v-else-if="error && !state"
      class="p-7 text-center"
      role="alert"
    >
      <h2 class="text-xl font-black text-[var(--ym-d-text)]">
        تعذر تحميل الأعمال المميزة
      </h2>
      <p class="mt-2 text-[var(--ym-d-muted)]">
        {{ error }}
      </p>
      <button
        type="button"
        class="mt-4 min-h-11 rounded-xl bg-[var(--ym-d-red)] px-5 font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
        @click="$emit('retry')"
      >
        إعادة المحاولة
      </button>
    </div>

    <template v-else-if="state">
      <header class="flex flex-col gap-4 border-b border-[var(--ym-d-border)] p-5 sm:p-6 md:flex-row md:items-center md:justify-between">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2.5">
            <h2
              id="featured-works-overview-title"
              class="text-xl font-black text-[var(--ym-d-text)]"
            >
              الأعمال المميزة
            </h2>
            <span class="rounded-full bg-[var(--ym-d-red-soft)] px-3 py-1 text-xs font-extrabold text-[var(--ym-d-red-strong)] ring-1 ring-inset ring-[var(--ym-d-red-border)]">
              <bdi dir="ltr">{{ state.selected.length }}/{{ state.limit }}</bdi>
            </span>
          </div>
          <p class="mt-2 text-sm leading-6 text-[var(--ym-d-muted)]">
            اختر أهم أعمالك ورتّبها يدويًا لتظهر أولًا في ملفك العام.
          </p>
        </div>

        <button
          type="button"
          class="min-h-11 w-full shrink-0 rounded-xl bg-[var(--ym-d-charcoal)] px-5 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] sm:w-fit"
          @click="$emit('edit')"
        >
          إدارة الأعمال المميزة
        </button>
      </header>

      <div class="p-5 sm:p-6">
        <p
          v-if="error"
          class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-bold text-[#8F1111]"
          role="alert"
        >
          {{ error }}
        </p>

        <ol
          v-if="state.selected.length"
          class="grid min-w-0 gap-4 sm:grid-cols-2 lg:grid-cols-3"
          aria-label="ترتيب الأعمال المميزة الحالي"
        >
          <li
            v-for="(work, index) in state.selected"
            :key="work.id"
            class="min-w-0 overflow-hidden rounded-2xl border border-[var(--ym-d-border)] bg-white"
          >
            <div class="relative aspect-[16/9] overflow-hidden bg-[var(--ym-d-surface-muted)]">
              <img
                v-if="coverSource(work)"
                :src="coverSource(work) || undefined"
                :alt="`غلاف ${work.title}`"
                class="h-full w-full object-cover"
                :style="{
                  objectPosition:
                    `${work.cover_presentation.focal_point.x}% ${work.cover_presentation.focal_point.y}%`,
                }"
              >
              <div
                v-else
                class="flex h-full items-center justify-center text-sm font-black text-neutral-400"
                aria-hidden="true"
              >
                YM
              </div>

              <bdi
                dir="ltr"
                class="absolute right-3 top-3 rounded-full bg-black/75 px-2.5 py-1 text-xs font-black text-white"
              >
                {{ index + 1 }}
              </bdi>
            </div>

            <div class="min-w-0 p-4">
              <h3
                dir="auto"
                class="break-words font-extrabold text-[var(--ym-d-text)]"
              >
                {{ work.title }}
              </h3>
              <p class="mt-1 text-xs font-bold text-[var(--ym-d-muted)]">
                {{ work.category?.name_ar || 'دون تصنيف' }}
              </p>
            </div>
          </li>
        </ol>

        <div
          v-else
          class="rounded-2xl border border-dashed border-[var(--ym-d-border-strong)] bg-[var(--ym-d-surface-muted)] px-5 py-10 text-center"
        >
          <h3 class="font-black text-[var(--ym-d-text)]">
            لم تختر أعمالًا مميزة بعد
          </h3>
          <p class="mx-auto mt-2 max-w-xl text-sm leading-7 text-[var(--ym-d-muted)]">
            لديك
            <bdi dir="ltr" class="font-bold">{{ state.eligible.length }}</bdi>
            عملًا عامًا مؤهلًا. يمكنك اختيار ما يصل إلى
            <bdi dir="ltr" class="font-bold">{{ state.limit }}</bdi>
            أعمال.
          </p>
        </div>
      </div>
    </template>
  </section>
</template>
