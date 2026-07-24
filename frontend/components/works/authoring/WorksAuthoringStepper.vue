<template>
  <section class="ym-authoring-stepper" :aria-label="text.label">
    <ol>
      <li
        v-for="(step, index) in steps"
        :key="step.title"
        :class="{ 'is-active': index === 0, 'is-locked': index > 0 }"
        :aria-current="index === 0 ? 'step' : undefined"
        :aria-disabled="index > 0 || undefined"
      >
        <span class="ym-step-number">{{ formatYmNumber(index + 1, locale) }}</span>
        <div><strong>{{ step.title }}</strong><small>{{ index === 0 ? text.current : step.note }}</small></div>
        <i v-if="index > 0" :title="text.locked" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5z" /></svg>
        </i>
        <span v-if="index > 0" class="sr-only">{{ text.locked }}</span>
      </li>
    </ol>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'
const props = defineProps<{ locale: 'ar' | 'en' }>()
const text = computed(() => props.locale === 'ar' ? {
  label:'مراحل إنشاء العمل',current:'أكمل البيانات الأساسية لإنشاء المسودة.',locked:'تتاح هذه المرحلة بعد إنشاء المسودة.',
  steps:[['بيانات المسودة',''],['التصنيف والوسوم','بعد إنشاء المسودة'],['الوسائط والغلاف','بعد إنشاء المسودة'],['المراجعة','مرحلة لاحقة']]
} : {
  label:'Work creation steps',current:'Complete the basic data to create the draft.',locked:'This step becomes available after creating the draft.',
  steps:[['Draft data',''],['Category and tags','After creating the draft'],['Media and cover','After creating the draft'],['Review','Later stage']]
})
const steps = computed(() => text.value.steps.map(([title, note]) => ({ title, note })))
</script>

<style scoped>
.ym-authoring-stepper{border:1px solid var(--aw-border);border-radius:18px;padding:10px;background:var(--aw-surface);box-shadow:var(--aw-soft-shadow),inset 0 1px 0 var(--aw-highlight)}
ol{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:0;padding:0;list-style:none}
li{position:relative;display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:10px;min-height:66px;border:1px solid var(--aw-soft-border);border-radius:13px;padding:10px 11px;color:var(--aw-muted);background:var(--aw-control)}
.ym-step-number{display:grid;width:36px;height:36px;place-items:center;border-radius:11px;color:var(--aw-text);background:color-mix(in srgb,var(--aw-muted) 12%,transparent);font-size:17px;font-weight:900;font-variant-numeric:tabular-nums}
li>div{display:grid;min-width:0;gap:3px}strong{color:var(--aw-text);font-size:14px;font-weight:850;line-height:1.35}small{color:var(--aw-muted);font-size:12.5px;line-height:1.4}
i{display:grid;width:26px;height:26px;place-items:center;border-radius:8px;color:var(--aw-muted);background:color-mix(in srgb,var(--aw-muted) 8%,transparent);font-style:normal}i svg{width:14px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.is-active{border-color:color-mix(in srgb,var(--aw-electric) 54%,var(--aw-border));background:linear-gradient(135deg,color-mix(in srgb,var(--aw-violet) 14%,var(--aw-control)),color-mix(in srgb,var(--aw-magenta) 7%,var(--aw-control)));box-shadow:0 0 20px color-mix(in srgb,var(--aw-electric) 12%,transparent),inset 0 1px 0 var(--aw-highlight)}
.is-active .ym-step-number{color:#fff;background:linear-gradient(135deg,var(--aw-violet),var(--aw-magenta));box-shadow:0 6px 16px color-mix(in srgb,var(--aw-violet) 28%,transparent)}
.is-active strong{font-weight:900}.is-locked{opacity:.86}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
@media(max-width:900px){ol{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:560px){ol{grid-template-columns:1fr}li{min-height:62px}}
</style>
