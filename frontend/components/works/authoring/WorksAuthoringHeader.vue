<template>
  <header class="ym-authoring-head">
    <div class="ym-authoring-head__glow" aria-hidden="true" />
    <nav :aria-label="text.breadcrumb">
      <NuxtLink to="/admin/works/all">{{ text.works }}</NuxtLink>
      <span aria-hidden="true">/</span>
      <NuxtLink to="/admin/works/all">{{ text.allWorks }}</NuxtLink>
      <span aria-hidden="true">/</span>
      <b>{{ mode === 'create' ? text.create : text.edit }}</b>
    </nav>

    <div class="ym-authoring-head__body">
      <div class="ym-authoring-head__title">
        <span class="ym-authoring-head__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M4 19.5V15l10.7-10.7a2.1 2.1 0 0 1 3 3L7 18H4Zm9-13 4 4M12 20h8" />
          </svg>
        </span>
        <div>
          <p>{{ mode === 'create' ? text.createEyebrow : text.editEyebrow }}</p>
          <h1 :dir="titleDirection">{{ displayTitle }}</h1>
          <span>{{ mode === 'create' ? text.createCopy : text.editCopy }}</span>
        </div>
      </div>

      <NuxtLink class="ym-authoring-head__back" to="/admin/works/all">
        <span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span>
        {{ text.back }}
      </NuxtLink>
    </div>

    <dl v-if="mode === 'edit' && work" class="ym-authoring-head__meta">
      <div><dt>{{ text.status }}</dt><dd>{{ statusLabel(work.status) }}</dd></div>
      <div><dt>{{ text.visibility }}</dt><dd>{{ visibilityLabel(work.visibility_status) }}</dd></div>
      <div><dt>{{ text.slug }}</dt><dd><code dir="ltr">{{ work.slug }}</code></dd></div>
      <div><dt>{{ text.updated }}</dt><dd><time dir="ltr">{{ formatYmDateTime(work.updated_at, locale) }}</time></dd></div>
      <div><dt>{{ text.media }}</dt><dd>{{ mediaLabel(work.media_type) }}</dd></div>
    </dl>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatYmDateTime } from '~/utils/ymFormatting'

const props = defineProps<{
  mode: 'create' | 'edit'
  locale: 'ar' | 'en'
  work: {
    title: string
    status: string
    visibility_status: string
    slug: string
    updated_at: string | null
    media_type: string | null
  } | null
}>()

const copies = {
  ar: {
    breadcrumb: 'مسار التنقل', works: 'الأعمال', allWorks: 'كل الأعمال', create: 'إنشاء عمل',
    edit: 'تحرير العمل', createEyebrow: 'إنشاء مسودة', editEyebrow: 'تحرير العمل',
    createCopy: 'ابدأ ببيانات العمل الأساسية، ثم أضف التصنيف والوسائط بعد إنشاء المسودة.',
    editCopy: 'حدّث البيانات المصرح بها مع الحفاظ على أحدث نسخة محفوظة.', back: 'العودة إلى كل الأعمال',
    status: 'الحالة', visibility: 'الظهور', slug: 'Slug', updated: 'آخر تحديث', media: 'نوع الوسائط'
  },
  en: {
    breadcrumb: 'Breadcrumb', works: 'Works', allWorks: 'All works', create: 'Create work',
    edit: 'Edit work', createEyebrow: 'Create draft', editEyebrow: 'Edit work',
    createCopy: 'Start with the core work data, then add taxonomy and media after creating the draft.',
    editCopy: 'Update the authorized fields while preserving the latest saved version.', back: 'Back to all works',
    status: 'Status', visibility: 'Visibility', slug: 'Slug', updated: 'Last update', media: 'Media type'
  }
} as const
const text = computed(() => copies[props.locale])
const displayTitle = computed(() => props.mode === 'create'
  ? (props.locale === 'ar' ? 'إنشاء عمل جديد' : 'Create a new work')
  : props.work?.title || (props.locale === 'ar' ? 'تحرير العمل' : 'Edit work'))
const titleDirection = computed(() => /[\u0600-\u06ff]/.test(displayTitle.value) ? 'rtl' : 'ltr')

function statusLabel(value: string): string {
  const ar: Record<string, string> = { draft:'مسودة', changes_requested:'تعديلات مطلوبة', submitted:'مرسل', in_review:'قيد المراجعة', approved:'معتمد', published:'منشور', rejected:'مرفوض', hidden:'مخفي', archived:'مؤرشف' }
  return props.locale === 'ar' ? ar[value] || value : value.replaceAll('_', ' ')
}
function visibilityLabel(value: string): string {
  if (props.locale === 'ar') return value === 'public' ? 'عام' : value === 'hidden' ? 'مخفي' : value
  return value.replaceAll('_', ' ')
}
function mediaLabel(value: string | null): string {
  const labels = props.locale === 'ar'
    ? { image: 'صورة', gallery: 'معرض صور', video: 'فيديو' }
    : { image: 'Image', gallery: 'Gallery', video: 'Video' }
  return value ? labels[value as keyof typeof labels] || value : '—'
}
</script>

<style scoped>
.ym-authoring-head{position:relative;display:grid;gap:16px;overflow:hidden;border:1px solid var(--aw-border);border-radius:22px;padding:20px 22px;background:linear-gradient(135deg,color-mix(in srgb,var(--aw-surface-strong) 91%,var(--aw-violet) 9%),color-mix(in srgb,var(--aw-surface-strong) 94%,var(--aw-magenta) 6%));box-shadow:var(--aw-shadow),inset 0 1px 0 color-mix(in srgb,#fff 12%,transparent)}
.ym-authoring-head:before{position:absolute;inset-block-start:0;inset-inline:22px;height:1px;background:linear-gradient(90deg,transparent,var(--aw-violet),var(--aw-magenta),transparent);content:""}
.ym-authoring-head__glow{position:absolute;width:260px;height:200px;border-radius:50%;inset-block-start:-130px;inset-inline-start:10%;background:color-mix(in srgb,var(--aw-violet) 17%,transparent);filter:blur(28px);pointer-events:none}
nav,.ym-authoring-head__body,.ym-authoring-head__meta{position:relative}
nav{display:flex;flex-wrap:wrap;align-items:center;gap:7px;color:var(--aw-muted);font-size:12.5px}nav a{color:inherit;text-decoration:none}nav b{color:color-mix(in srgb,var(--aw-electric) 72%,var(--aw-text))}
.ym-authoring-head__body{display:flex;align-items:center;justify-content:space-between;gap:20px}.ym-authoring-head__title{display:flex;min-width:0;align-items:flex-start;gap:14px}.ym-authoring-head__title>div{min-width:0}.ym-authoring-head__icon{display:grid;flex:0 0 auto;width:44px;height:44px;place-items:center;border:1px solid color-mix(in srgb,var(--aw-electric) 38%,transparent);border-radius:14px;color:var(--aw-electric);background:color-mix(in srgb,var(--aw-electric) 10%,transparent)}.ym-authoring-head__icon svg{width:23px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.ym-authoring-head__title p{margin:0;color:color-mix(in srgb,var(--aw-electric) 72%,var(--aw-text));font-size:12.5px;font-weight:850}.ym-authoring-head h1{overflow:visible;margin:3px 0 5px;padding-block:.08em;color:var(--aw-text);font-size:clamp(38px,4vw,46px);font-weight:900;line-height:1.2;overflow-wrap:anywhere}.ym-authoring-head__title>div>span{color:var(--aw-muted);font-size:14px;line-height:1.65}
.ym-authoring-head__back{display:inline-flex;flex:0 0 auto;min-height:44px;align-items:center;gap:7px;border:1px solid var(--aw-border);border-radius:12px;padding:0 14px;color:var(--aw-text);background:var(--aw-control);font-size:13.5px;font-weight:800;text-decoration:none;transition:transform .16s ease,border-color .16s ease}.ym-authoring-head__back:hover{transform:translateY(-1px);border-color:var(--aw-electric)}.ym-authoring-head__back:focus-visible{outline:3px solid color-mix(in srgb,var(--aw-electric) 38%,transparent);outline-offset:2px}
.ym-authoring-head__meta{display:flex;flex-wrap:wrap;gap:7px;border-block-start:1px solid var(--aw-soft-border);margin:0;padding-block-start:13px}.ym-authoring-head__meta>div{display:flex;min-height:34px;align-items:center;gap:7px;border-radius:999px;padding:5px 10px;background:var(--aw-control)}.ym-authoring-head__meta dt{color:var(--aw-muted);font-size:12px}.ym-authoring-head__meta dd{margin:0;color:var(--aw-text);font-size:12.5px;font-weight:750}.ym-authoring-head__meta code,.ym-authoring-head__meta time{color:var(--aw-electric);font-variant-numeric:tabular-nums}
@media(max-width:900px){.ym-authoring-head h1{font-size:clamp(32px,5vw,38px)}}
@media(max-width:640px){.ym-authoring-head{padding:17px 16px}.ym-authoring-head__body{align-items:stretch;flex-direction:column}.ym-authoring-head h1{font-size:clamp(26px,8vw,30px)}.ym-authoring-head__back{justify-content:center}.ym-authoring-head__meta>div{max-width:100%}.ym-authoring-head__meta code{overflow-wrap:anywhere}}
@media(prefers-reduced-motion:reduce){.ym-authoring-head__back{transition:none}.ym-authoring-head__back:hover{transform:none}}
</style>
