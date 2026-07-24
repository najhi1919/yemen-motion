<template>
  <div class="ym-compact-top" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
    <header class="ym-compact-header" :class="{ 'is-entered': entered }">
      <div class="ym-compact-header__copy">
        <span class="ym-compact-header__mark" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M4 17.5 12 4l8 13.5-8 2.5-8-2.5Z" /><path d="m8.5 15 3.5-6 3.5 6-3.5 1-3.5-1Z" /></svg>
        </span>
        <nav :aria-label="text.breadcrumb"><NuxtLink to="/admin/works">{{ text.works }}</NuxtLink><span>/</span><span>{{ text.allWorks }}</span></nav>
        <h1>{{ text.allWorks }}</h1>
        <p>{{ text.description }}</p>
      </div>
      <NuxtLink v-if="canCreate" to="/admin/works/create" class="ym-compact-header__create" :title="text.createHint" :aria-label="text.createHint">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
        {{ text.create }}
      </NuxtLink>
    </header>

    <section
      class="ym-summary-strip"
      :class="{ 'is-loading': loading, 'is-updating': updating }"
      :aria-label="text.summary"
      :aria-busy="loading || updating"
      aria-live="polite"
    >
      <div v-for="item in metrics" :key="item.key" :class="`is-${item.key}`">
        <WorksIndexFloatingOverlay
          :label="item.label"
          :description="item.hint"
          :trigger-aria-label="`${item.label}: ${item.value === null ? text.summaryUnavailable : formatYmNumber(item.value, locale)}`"
        >
          <template #trigger>
            <span class="ym-summary-strip__icon" aria-hidden="true">
              <svg viewBox="0 0 24 24"><path :d="item.icon" /></svg>
            </span>
            <span>{{ item.label }}</span>
            <strong>{{ item.value === null ? '—' : formatYmNumber(item.value, locale) }}</strong>
          </template>
        </WorksIndexFloatingOverlay>
      </div>
    </section>
    <p v-if="summary === null || warning" class="ym-summary-status" :class="{ 'is-warning': Boolean(warning) }" role="status">
      {{ warning || (loading || updating ? text.summaryUpdating : text.summaryUnavailable) }}
    </p>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import WorksIndexFloatingOverlay from './WorksIndexFloatingOverlay.vue'
import { formatYmNumber } from '~/utils/ymFormatting'

const props = defineProps<{
  locale: 'ar' | 'en'
  canCreate: boolean
  summary: {
    total_filtered: number
    visible_on_page: number
    published_filtered: number
    review_cycle_filtered: number
    reported_filtered: number
  } | null
  loading: boolean
  updating: boolean
  warning: string | null
}>()
const entered = ref(false)
onMounted(() => {
  requestAnimationFrame(() => {
    entered.value = true
  })
})

const copies = {
  ar: { breadcrumb: 'مسار التنقل', works: 'الأعمال', allWorks: 'كل الأعمال', description: 'عرض الأعمال وإدارتها والبحث والتصفية حسب صلاحيات الحساب.', create: 'إنشاء عمل جديد', createHint: 'فتح مساحة إنشاء عمل جديد', summary: 'ملخص نتائج الأعمال', total: 'الإجمالي', visible: 'الظاهر', published: 'المنشور', review: 'المراجعة', reported: 'البلاغات', totalHint: 'جميع الأعمال المطابقة للفلاتر الحالية قبل تقسيم الصفحات.', pageHint: 'عدد الأعمال الفعلية في الصفحة الحالية.', publishedHint: 'الأعمال المنشورة ضمن جميع النتائج المطابقة للفلاتر.', reviewHint: 'الأعمال المرسلة أو قيد المراجعة أو المطلوب تعديلها ضمن جميع النتائج المفلترة.', reportedHint: 'الأعمال التي لديها بلاغ واحد أو أكثر ضمن جميع النتائج المفلترة.', loading: 'جارٍ التحميل', summaryUpdating: 'جارٍ تحديث الملخص…', summaryUnavailable: 'الملخص غير متاح.', close: 'إغلاق' },
  en: { breadcrumb: 'Breadcrumb', works: 'Works', allWorks: 'All Works', description: 'Browse, manage, search, and filter works according to account permissions.', create: 'Create new work', createHint: 'Open the new work authoring workspace', summary: 'Works results summary', total: 'Total', visible: 'Visible', published: 'Published', review: 'In review', reported: 'Reported', totalHint: 'All works matching the current filters before pagination.', pageHint: 'Actual works on the current page.', publishedHint: 'Published works across all filtered results.', reviewHint: 'Submitted, in-review, or changes-requested works across all filtered results.', reportedHint: 'Works with one or more reports across all filtered results.', loading: 'Loading', summaryUpdating: 'Updating summary…', summaryUnavailable: 'Summary unavailable.', close: 'Close' }
} as const
const text = computed(() => copies[props.locale])
const metrics = computed(() => [
  { key: 'total', label: text.value.total, value: props.summary?.total_filtered ?? null, hint: text.value.totalHint, icon: 'M4 5h16v14H4zM8 9h8M8 13h5' },
  { key: 'visible', label: text.value.visible, value: props.summary?.visible_on_page ?? null, hint: text.value.pageHint, icon: 'M2.5 12s3.5-5 9.5-5 9.5 5 9.5 5-3.5 5-9.5 5-9.5-5-9.5-5Zm9.5 2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z' },
  { key: 'published', label: text.value.published, value: props.summary?.published_filtered ?? null, hint: text.value.publishedHint, icon: 'm5 12 4 4L19 6' },
  { key: 'review', label: text.value.review, value: props.summary?.review_cycle_filtered ?? null, hint: text.value.reviewHint, icon: 'M12 3a9 9 0 1 0 9 9M12 7v5l3 2' },
  { key: 'reported', label: text.value.reported, value: props.summary?.reported_filtered ?? null, hint: text.value.reportedHint, icon: 'M12 3 3.5 19h17L12 3Zm0 6v4m0 3h.01' }
])
</script>

<style scoped>
.ym-compact-top { display: grid; gap: 14px; }
.ym-compact-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; border: 1px solid var(--ym-card-border); border-radius: 20px; padding: 18px 20px; opacity: 0; transform: translateY(5px); color: var(--ym-text); background: var(--ym-card-bg); box-shadow: 0 12px 30px rgba(2, 6, 23, .08); transition: opacity .2s ease, transform .2s ease, border-color .18s ease, box-shadow .18s ease; }
.ym-compact-header.is-entered { opacity: 1; transform: translateY(0); }
.ym-compact-header__copy { position: relative; min-width: 0; padding-inline-start: 42px; overflow: visible; }
.ym-compact-header__mark { position: absolute; inset-block-start: 2px; inset-inline-start: 0; display: grid; width: 32px; height: 32px; place-items: center; border: 1px solid color-mix(in srgb, var(--ym-violet) 34%, transparent); border-radius: 10px; color: var(--ym-violet-electric); background: color-mix(in srgb, var(--ym-violet) 10%, transparent); box-shadow: 0 0 18px color-mix(in srgb, var(--ym-violet) 16%, transparent); }
.ym-compact-header__mark svg { width: 19px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linejoin: round; }
.ym-compact-header nav { display: flex; gap: 7px; color: var(--ym-muted); font-size: 13px; font-weight: 750; }
.ym-compact-header nav a { color: #8b5cf6; text-decoration: none; }
.ym-compact-header h1 { display: block; width: fit-content; height: auto; margin: 4px 0 0; padding-block: .1em .12em; overflow: visible; color: var(--ym-text); background: linear-gradient(110deg, var(--ym-violet-electric), var(--ym-magenta)); background-clip: text; -webkit-background-clip: text; font-size: clamp(2.5rem, 3vw, 3rem); font-weight: 900; line-height: 1.22; -webkit-text-fill-color: transparent; }
.ym-compact-header p { margin: 0; color: var(--ym-muted); font-size: 14px; line-height: 1.55; }
.ym-compact-header__create { display: inline-flex; flex: 0 0 auto; min-height: 44px; align-items: center; gap: 8px; border-radius: 12px; padding: 0 15px; color: #fff; background: var(--ym-violet); font-size: 14px; font-weight: 850; text-decoration: none; }
.ym-compact-header__create svg { width: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
.ym-compact-header__create:focus-visible { outline: 3px solid color-mix(in srgb, #8b5cf6 48%, transparent); outline-offset: 3px; }
.ym-summary-strip { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; border: 1px solid var(--ym-card-border); border-radius: 16px; padding: 8px; color: var(--ym-text); background: var(--ym-card-bg); }
.ym-summary-strip > div { --metric: var(--ym-violet); min-width: 0; min-height: 52px; border: 1px solid color-mix(in srgb, var(--metric) 22%, var(--ym-card-border)); border-radius: 12px; padding: 8px 11px; background: linear-gradient(135deg, color-mix(in srgb, var(--metric) 10%, transparent), transparent 58%); }
.ym-summary-strip > div.is-visible { --metric: var(--ym-cyan); }
.ym-summary-strip > div.is-published { --metric: var(--ym-emerald); }
.ym-summary-strip > div.is-review { --metric: var(--ym-amber); }
.ym-summary-strip > div.is-reported { --metric: var(--ym-rose); }
.ym-summary-strip > div + div { border-inline-start: 1px solid var(--ym-card-border); }
.ym-summary-strip :deep(.ym-floating-overlay), .ym-summary-strip :deep(.ym-floating-overlay__trigger) { display: grid; width: 100%; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: 8px; }
.ym-summary-strip__icon { display: grid; width: 26px; height: 26px; place-items: center; border-radius: 8px; color: var(--metric); background: color-mix(in srgb, var(--metric) 13%, transparent); }
.ym-summary-strip__icon svg { width: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.ym-summary-strip span { color: var(--ym-muted); font-size: 13px; font-weight: 750; }
.ym-summary-strip strong { direction: ltr; unicode-bidi: isolate; color: var(--ym-text); font-size: 20px; font-variant-numeric: tabular-nums; transition: opacity .18s ease; }
.ym-summary-strip.is-updating { opacity: .72; }
.ym-summary-status { margin: -5px 4px 0; color: var(--ym-muted); font-size: 12px; font-weight: 750; }.ym-summary-status.is-warning { color: var(--ym-amber); }
@media (max-width: 900px) { .ym-compact-header h1 { font-size: clamp(2.125rem, 5vw, 2.375rem); } }
@media (max-width: 680px) { .ym-compact-header { align-items: stretch; flex-direction: column; padding: 16px; } .ym-compact-header h1 { font-size: clamp(1.6875rem, 8vw, 1.875rem); }.ym-compact-header__create { justify-content: center; padding-inline: 20px; } .ym-compact-header p { display: -webkit-box; overflow: hidden; -webkit-line-clamp: 2; -webkit-box-orient: vertical; } .ym-summary-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ym-summary-strip > div { min-height: 46px; } .ym-summary-strip > div:last-child { grid-column: 1 / -1; } .ym-summary-strip > div + div { border-inline-start: 1px solid color-mix(in srgb, var(--metric) 22%, var(--ym-card-border)); } }
.ym-compact-header { position: relative; isolation: isolate; overflow: hidden; border-color: color-mix(in srgb, var(--ym-card-border) 82%, #8b5cf6 18%); background: linear-gradient(135deg, color-mix(in srgb, var(--ym-card-bg) 96%, #8b5cf6 4%), var(--ym-card-bg)); box-shadow: 0 14px 34px rgba(2, 6, 23, .1), inset 0 1px 0 color-mix(in srgb, #fff 10%, transparent); }
.ym-compact-header::before { position: absolute; inset-block-start: 0; inset-inline: 18px; height: 1px; background: linear-gradient(90deg, transparent, var(--ym-violet-electric), var(--ym-magenta), transparent); content: ''; opacity: .8; }
.ym-compact-header:hover { border-color: color-mix(in srgb, var(--ym-violet-electric) 38%, var(--ym-card-border)); box-shadow: 0 16px 38px rgba(2,6,23,.12), inset 0 1px 0 color-mix(in srgb, #fff 12%, transparent); }
.ym-compact-header::after { content: ''; position: absolute; z-index: -1; width: 210px; height: 210px; inset-block-start: -145px; inset-inline-end: -70px; border-radius: 50%; background: #8b5cf6; filter: blur(55px); opacity: .13; pointer-events: none; }
.ym-compact-header__create { border: 1px solid color-mix(in srgb, #fff 20%, transparent); background: linear-gradient(135deg, var(--ym-violet), var(--ym-magenta)); box-shadow: 0 9px 22px color-mix(in srgb, var(--ym-violet) 27%, transparent), inset 0 1px 0 rgba(255,255,255,.18); transition: transform .16s ease, box-shadow .16s ease, filter .16s ease; }
.ym-compact-header__create:hover { transform: translateY(-1px); filter: brightness(1.05); box-shadow: 0 12px 26px color-mix(in srgb, var(--ym-violet) 32%, transparent), inset 0 1px 0 rgba(255,255,255,.2); }
.ym-compact-header__create:active { transform: translateY(0); }
.ym-summary-strip { overflow: hidden; border-color: color-mix(in srgb, var(--ym-card-border) 86%, #8b5cf6 14%); background: linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 97%, #8b5cf6 3%), var(--ym-card-bg)); box-shadow: 0 9px 24px rgba(2, 6, 23, .07), inset 0 1px 0 color-mix(in srgb, #fff 8%, transparent); }
.ym-summary-strip > div { transition: background .18s ease, box-shadow .18s ease; }
.ym-summary-strip > div:hover { background: color-mix(in srgb, #8b5cf6 7%, transparent); box-shadow: inset 0 -2px 0 color-mix(in srgb, #8b5cf6 46%, transparent); }
@supports not ((background-clip: text) or (-webkit-background-clip: text)) {
  .ym-compact-header h1 { background: none; -webkit-text-fill-color: currentColor; }
}
@media (prefers-reduced-motion: reduce) { .ym-compact-header { opacity: 1; transform: none; }.ym-compact-header, .ym-compact-header__create, .ym-summary-strip strong { transition: none; } }
</style>
