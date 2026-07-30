<script setup lang="ts">
import type { useDesignerWorkMedia } from '~/composables/useDesignerWorkMedia'
import type { useDesignerWorkPresentation } from '~/composables/useDesignerWorkPresentation'
import type { DesignerWorkCoverDisplayMode } from '~/types/designer-work'
import { getDesignerWorkCoverStyle } from '~/utils/designerWorkCoverPresentation'

const props = defineProps<{
  manager: ReturnType<typeof useDesignerWorkPresentation>
  mediaManager: ReturnType<typeof useDesignerWorkMedia>
}>()

const activePreview = ref<'grid' | 'list' | 'public'>('grid')
const imageFailed = ref(false)
const focalSurface = ref<HTMLElement | null>(null)
const cover = computed(() => props.mediaManager.presentationCover.value)
const coverReady = computed(() => cover.value?.processing_status === 'ready')
const coverUrl = computed(() => cover.value?.url || null)
const focalEnabled = computed(() =>
  props.manager.form.cover_display_mode === 'fill'
  && Boolean(coverUrl.value)
  && coverReady.value
  && props.manager.editable.value,
)
const coverStyle = computed(() => getDesignerWorkCoverStyle(
  props.manager.form.cover_display_mode,
  { x: props.manager.form.focal_x, y: props.manager.form.focal_y },
))
const previewClass = computed(() => ({
  grid: 'mx-auto aspect-video w-full max-w-sm',
  list: 'aspect-[16/5] w-full',
  public: 'mx-auto aspect-[16/10] w-full max-w-md',
}[activePreview.value]))

watch(coverUrl, () => {
  imageFailed.value = false
})

const selectMode = (mode: DesignerWorkCoverDisplayMode) => {
  if (!props.manager.saving.value && props.manager.editable.value) {
    props.manager.setDisplayMode(mode)
  }
}

const updateFromPointer = (event: PointerEvent) => {
  const element = focalSurface.value
  if (!element || !focalEnabled.value) return
  const bounds = element.getBoundingClientRect()
  if (bounds.width <= 0 || bounds.height <= 0) return
  props.manager.setFocalPoint(
    ((event.clientX - bounds.left) / bounds.width) * 100,
    ((event.clientY - bounds.top) / bounds.height) * 100,
  )
}

const onPointerDown = (event: PointerEvent) => {
  if (!focalEnabled.value) return
  focalSurface.value?.setPointerCapture(event.pointerId)
  updateFromPointer(event)
}

const onPointerMove = (event: PointerEvent) => {
  if (focalSurface.value?.hasPointerCapture(event.pointerId)) updateFromPointer(event)
}

const onFocalKeydown = (event: KeyboardEvent) => {
  if (!focalEnabled.value) return
  const step = event.shiftKey ? 5 : 1
  let x = props.manager.form.focal_x
  let y = props.manager.form.focal_y

  if (event.key === 'ArrowLeft') x -= step
  else if (event.key === 'ArrowRight') x += step
  else if (event.key === 'ArrowUp') y -= step
  else if (event.key === 'ArrowDown') y += step
  else return

  event.preventDefault()
  props.manager.setFocalPoint(x, y)
}

const onRange = (axis: 'x' | 'y', event: Event) => {
  const value = Number((event.target as HTMLInputElement).value)
  props.manager.setFocalPoint(
    axis === 'x' ? value : props.manager.form.focal_x,
    axis === 'y' ? value : props.manager.form.focal_y,
  )
}
</script>

<template>
  <section
    class="rounded-[20px] border border-[var(--ym-d-border)] bg-[var(--ym-d-surface)] p-4 text-[var(--ym-d-text)] shadow-[var(--ym-d-shadow-sm)] sm:p-6"
    aria-labelledby="designer-work-presentation-title"
    :aria-busy="manager.loading.value || manager.saving.value"
  >
    <header class="flex items-start gap-3 border-b border-[var(--ym-d-border)] pb-5">
      <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[var(--ym-d-charcoal)] text-white" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" class="h-5 w-5">
          <rect x="3.5" y="5" width="17" height="14" rx="2.5" stroke="currentColor" stroke-width="1.7" />
          <path d="M8 9.5h8M8 14.5h8M12 7v10" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" opacity=".7" />
        </svg>
      </span>
      <div>
        <p class="text-xs font-extrabold text-[var(--ym-d-red-strong)]">طريقة تقديم العمل</p>
        <h2 id="designer-work-presentation-title" class="mt-1 text-xl font-extrabold">عرض الغلاف</h2>
        <p class="mt-1 text-sm text-[var(--ym-d-muted)]">
          اضبط طريقة ظهور الغلاف في الشبكة والقائمة والملف العام.
        </p>
      </div>
    </header>

    <div v-if="manager.loading.value" class="mt-5 space-y-4" role="status" aria-label="جارٍ تحميل إعدادات عرض الغلاف">
      <div class="h-24 animate-pulse rounded-2xl bg-neutral-100 motion-reduce:animate-none" />
      <div class="aspect-video animate-pulse rounded-2xl bg-neutral-100 motion-reduce:animate-none" />
    </div>

    <div v-else class="mt-5 grid min-w-0 gap-6 md:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
      <div class="min-w-0 space-y-5">
        <fieldset :disabled="manager.saving.value || !manager.editable.value">
          <legend class="text-sm font-extrabold">طريقة العرض</legend>
          <div class="mt-3 grid gap-3 sm:grid-cols-2 md:grid-cols-1">
            <button
              v-for="mode in [
                { value: 'fill' as const, title: 'ملء الإطار', label: 'Fill', description: 'يملأ المساحة بالكامل وقد يقص بعض الأطراف.' },
                { value: 'fit' as const, title: 'احتواء كامل', label: 'Fit', description: 'يعرض الغلاف كاملًا وقد يترك مساحة حوله.' },
              ]"
              :key="mode.value"
              type="button"
              class="min-h-20 rounded-2xl border p-4 text-right transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:cursor-not-allowed disabled:opacity-55 motion-reduce:transition-none"
              :class="manager.form.cover_display_mode === mode.value
                ? 'border-[var(--ym-d-red)] bg-[var(--ym-d-red-soft)]'
                : 'border-[var(--ym-d-border)] bg-[var(--ym-d-surface-warm)] hover:border-[var(--ym-d-border-strong)]'"
              :aria-pressed="manager.form.cover_display_mode === mode.value"
              @click="selectMode(mode.value)"
            >
              <span class="flex items-center justify-between gap-3">
                <strong>{{ mode.title }}</strong>
                <bdi dir="ltr" class="text-xs font-bold text-[var(--ym-d-red-strong)]">{{ mode.label }}</bdi>
              </span>
              <span class="mt-1 block text-xs leading-5 text-[var(--ym-d-muted)]">{{ mode.description }}</span>
            </button>
          </div>
        </fieldset>

        <div class="rounded-2xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-warm)] p-4">
          <h3 class="font-extrabold">نقطة التركيز</h3>
          <p v-if="manager.form.cover_display_mode === 'fit'" class="mt-2 text-sm text-[var(--ym-d-muted)]">
            نقطة التركيز تستخدم عند اختيار «ملء الإطار».
          </p>
          <p v-else-if="!coverUrl" class="mt-2 text-sm text-[var(--ym-d-muted)]">
            اختر غلافًا جاهزًا لضبط نقطة التركيز.
          </p>

          <div class="mt-4 space-y-4">
            <label class="block">
              <span class="mb-2 flex items-center justify-between gap-3 text-sm font-bold">
                الموضع الأفقي
                <bdi dir="ltr">{{ manager.form.focal_x }}%</bdi>
              </span>
              <input
                type="range"
                min="0"
                max="100"
                :value="manager.form.focal_x"
                class="min-h-11 w-full accent-[var(--ym-d-red)]"
                :disabled="!focalEnabled"
                @input="onRange('x', $event)"
              >
            </label>
            <label class="block">
              <span class="mb-2 flex items-center justify-between gap-3 text-sm font-bold">
                الموضع الرأسي
                <bdi dir="ltr">{{ manager.form.focal_y }}%</bdi>
              </span>
              <input
                type="range"
                min="0"
                max="100"
                :value="manager.form.focal_y"
                class="min-h-11 w-full accent-[var(--ym-d-red)]"
                :disabled="!focalEnabled"
                @input="onRange('y', $event)"
              >
            </label>
            <button
              type="button"
              class="min-h-11 rounded-xl border border-[var(--ym-d-red-border)] bg-white px-4 text-sm font-bold text-[var(--ym-d-red-strong)] hover:bg-[var(--ym-d-red-soft)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:opacity-45"
              :disabled="!focalEnabled"
              @click="manager.resetFocalPoint()"
            >
              توسيط نقطة التركيز
            </button>
          </div>
        </div>
      </div>

      <div class="min-w-0">
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="نوع معاينة الغلاف">
          <button
            v-for="tab in [
              { value: 'grid' as const, label: 'الشبكة' },
              { value: 'list' as const, label: 'القائمة' },
              { value: 'public' as const, label: 'الملف العام' },
            ]"
            :key="tab.value"
            type="button"
            role="tab"
            class="min-h-11 rounded-xl border px-4 text-sm font-bold focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
            :class="activePreview === tab.value
              ? 'border-[var(--ym-d-red-border)] bg-[var(--ym-d-red-soft)] text-[var(--ym-d-red-strong)]'
              : 'border-[var(--ym-d-border)] bg-white text-[var(--ym-d-text)]'"
            :aria-selected="activePreview === tab.value"
            @click="activePreview = tab.value"
          >
            {{ tab.label }}
          </button>
        </div>

        <div class="mt-4 rounded-2xl border border-[var(--ym-d-border)] bg-[var(--ym-d-surface-muted)] p-3 sm:p-5">
          <div
            v-if="!cover"
            class="grid min-h-48 place-items-center rounded-xl border border-dashed border-[var(--ym-d-border-strong)] bg-white p-6 text-center text-sm font-semibold text-[var(--ym-d-muted)]"
          >
            اختر غلافًا من قسم وسائط العمل لتظهر المعاينة هنا.
          </div>
          <div
            v-else-if="!coverReady"
            class="grid min-h-48 place-items-center rounded-xl bg-white p-6 text-center text-sm font-semibold text-[var(--ym-d-muted)]"
          >
            الغلاف قيد المعالجة.
          </div>
          <div v-else class="min-w-0">
            <div
              ref="focalSurface"
              class="relative overflow-hidden rounded-xl border border-[var(--ym-d-border)] bg-neutral-200 outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
              :class="previewClass"
              :tabindex="focalEnabled ? 0 : -1"
              :aria-label="focalEnabled ? 'اسحب أو استخدم الأسهم لضبط نقطة تركيز الغلاف' : undefined"
              @pointerdown="onPointerDown"
              @pointermove="onPointerMove"
              @keydown="onFocalKeydown"
            >
              <img
                v-if="coverUrl && !imageFailed"
                :src="coverUrl"
                alt=""
                class="h-full w-full select-none"
                :style="coverStyle"
                draggable="false"
                @error="imageFailed = true"
              >
              <div v-else class="grid h-full w-full place-items-center bg-neutral-100">
                <img src="/logo.svg" alt="" class="h-14 w-14 opacity-20">
              </div>
              <span
                v-if="focalEnabled && !imageFailed"
                class="pointer-events-none absolute h-6 w-6 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[var(--ym-d-red)]/80 shadow-[0_0_0_2px_rgba(17,17,17,.55)]"
                :style="{ left: `${manager.form.focal_x}%`, top: `${manager.form.focal_y}%` }"
                aria-hidden="true"
              />
            </div>
            <div class="mt-3 min-w-0">
              <bdi dir="ltr" class="block truncate text-xs font-bold text-[var(--ym-d-muted)]">
                #{{ manager.current.value?.public_code }}
              </bdi>
              <p class="mt-1 line-clamp-2 font-extrabold" dir="auto">
                {{ manager.current.value?.title }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-6 border-t border-[var(--ym-d-border)] pt-5">
      <p v-if="manager.success.value" class="text-sm font-bold text-emerald-700" role="status" aria-live="polite">
        {{ manager.success.value }}
      </p>
      <p v-if="manager.error.value" class="text-sm font-bold text-[#B81414]" role="alert">
        {{ manager.error.value }}
      </p>
      <div class="mt-3 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
        <button
          type="button"
          class="min-h-11 rounded-xl border border-[var(--ym-d-border-strong)] bg-white px-5 font-bold text-[var(--ym-d-text)] hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:opacity-45"
          :disabled="manager.saving.value || !manager.dirty.value"
          @click="manager.reset()"
        >
          تراجع عن التغييرات
        </button>
        <button
          type="button"
          class="min-h-11 rounded-xl bg-[var(--ym-d-red)] px-5 font-bold text-white hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:cursor-not-allowed disabled:opacity-45"
          :disabled="manager.saving.value || !manager.dirty.value || !manager.editable.value"
          @click="manager.save()"
        >
          {{ manager.saving.value ? 'جارٍ الحفظ…' : 'حفظ طريقة العرض' }}
        </button>
      </div>
    </div>
  </section>
</template>
