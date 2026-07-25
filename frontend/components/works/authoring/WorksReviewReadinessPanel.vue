<template>
  <section class="ym-readiness" :class="`is-${state}`" :aria-busy="updating || undefined">
    <header>
      <span class="ym-readiness__icon" aria-hidden="true">✓</span>
      <div>
        <p>{{ text.kicker }}</p>
        <h2>{{ text.title }}</h2>
        <small>{{ text.copy }}</small>
      </div>
      <div class="ym-readiness__state">
        <strong>{{ stateLabel }}</strong>
        <span v-if="updating">{{ text.updating }}</span>
      </div>
    </header>

    <aside v-if="status === 'changes_requested' && changeNotes" class="ym-readiness__changes">
      <strong>{{ text.reviewNotes }}</strong>
      <p>{{ changeNotes }}</p>
    </aside>

    <div v-if="error" class="ym-readiness__error" role="alert">
      <span>{{ error }}</span>
      <button type="button" @click="$emit('retry')">{{ text.retry }}</button>
    </div>

    <template v-if="readiness">
      <div v-if="status !== 'submitted'" class="ym-readiness__counts">
        <span><b>{{ formatYmNumber(readiness.blockers_count, locale) }}</b>{{ text.blockers }}</span>
        <span><b>{{ formatYmNumber(readiness.warnings_count, locale) }}</b>{{ text.warnings }}</span>
      </div>

      <div v-if="status !== 'submitted'" class="ym-readiness__sections">
        <article v-for="section in visibleSections" :key="section.key">
          <header>
            <strong>{{ sectionLabel(section.key) }}</strong>
            <span :class="`is-${section.status}`">{{ sectionStatus(section.status) }}</span>
          </header>
          <ul v-if="section.items.length">
            <li v-for="item in section.items" :key="item.code" :class="`is-${item.severity}`">
              <span aria-hidden="true">{{ item.severity === 'blocker' ? '!' : '◇' }}</span>
              <div>
                <strong>{{ itemCopy(item.code).title }}</strong>
                <small>{{ itemCopy(item.code).copy }}</small>
              </div>
              <button v-if="item.target" type="button" @click="$emit('navigate', item.target)">
                {{ text.go }}
              </button>
            </li>
          </ul>
          <p v-else>{{ text.sectionReady }}</p>
        </article>
      </div>

      <footer v-if="state === 'submitted'">
        <p>{{ text.submittedCopy }}</p>
        <div>
          <NuxtLink to="/admin/works/review">{{ text.openQueue }}</NuxtLink>
          <NuxtLink class="is-secondary" to="/admin/works/all">{{ text.backToWorks }}</NuxtLink>
        </div>
      </footer>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'

interface ReadinessItem { code: string; severity: 'blocker' | 'warning'; satisfied: boolean; target: string }
interface ReadinessSection { key: string; status: 'ready' | 'warning' | 'blocked'; items: ReadinessItem[] }
interface Readiness {
  ready: boolean; blockers_count: number; warnings_count: number
  evaluated_at: string; work_updated_at: string | null; sections: ReadinessSection[]
}
const props = defineProps<{
  locale: 'ar' | 'en'
  readiness: Readiness | null
  updating: boolean
  error: string
  status: string
  changeNotes?: string | null
}>()
defineEmits<{ retry: []; navigate: [target: string] }>()

const text = computed(() => props.locale === 'ar' ? {
  kicker:'بوابة الجودة', title:'جاهزية المراجعة', copy:'يعتمد هذا الفحص على أحدث بيانات محفوظة في الخادم.',
  updating:'جارٍ تحديث الجاهزية…', retry:'إعادة المحاولة', blockers:'نواقص مانعة', warnings:'توصيات',
  ready:'جاهز للإرسال', blocked:'غير جاهز', submitted:'مرسل للمراجعة', changes:'يحتاج تعديلات',
  sectionReady:'متطلبات هذا القسم مكتملة.', go:'الانتقال إلى القسم', reviewNotes:'ملاحظات المراجع الحالية',
  submittedCopy:'تم إرسال العمل للمراجعة وأصبحت مساحة التأليف للقراءة فقط.',
  openQueue:'فتح طلبات المراجعة', backToWorks:'العودة إلى كل الأعمال'
} : {
  kicker:'Quality gate', title:'Review readiness', copy:'This check uses the latest saved server data.',
  updating:'Updating readiness…', retry:'Retry', blockers:'Blocking items', warnings:'Recommendations',
  ready:'Ready to submit', blocked:'Not ready', submitted:'Submitted for review', changes:'Changes requested',
  sectionReady:'This section is complete.', go:'Go to section', reviewNotes:'Current reviewer notes',
  submittedCopy:'The work was submitted and the authoring workspace is now read only.',
  openQueue:'Open review queue', backToWorks:'Back to all works'
})
const state = computed(() => props.status === 'submitted'
  ? 'submitted'
  : props.readiness?.ready ? 'ready' : 'blocked')
const stateLabel = computed(() => {
  if (props.status === 'submitted') return text.value.submitted
  if (props.status === 'changes_requested') return text.value.changes
  return props.readiness?.ready ? text.value.ready : text.value.blocked
})
const visibleSections = computed(() => props.readiness?.sections || [])
const sectionNames = {
  ar:{ status:'الحالة', content:'المحتوى', organization:'التنظيم', media:'الوسائط' },
  en:{ status:'Status', content:'Content', organization:'Organization', media:'Media' }
}
const copies: Record<string, { ar:[string,string]; en:[string,string] }> = {
  invalid_status:{ar:['حالة العمل غير قابلة للإرسال','لا يمكن إرسال العمل من حالته الحالية.'],en:['Work status cannot be submitted','This work cannot be submitted from its current state.']},
  title_missing:{ar:['العنوان غير مكتمل','أضف عنوانًا واضحًا للعمل.'],en:['Title is missing','Add a clear work title.']},
  summary_missing:{ar:['الملخص غير مكتمل','أضف ملخصًا قصيرًا يساعد المراجع على فهم العمل.'],en:['Summary is missing','Add a short summary for the reviewer.']},
  description_missing:{ar:['الوصف غير مكتمل','أضف وصف العمل قبل الإرسال.'],en:['Description is missing','Add the work description before submitting.']},
  media_type_missing:{ar:['نوع الوسائط غير محدد','حدد نوع الوسائط واحفظ بيانات العمل.'],en:['Media type is missing','Select and save the media type.']},
  media_type_not_allowed:{ar:['نوع الوسائط غير مسموح','اختر نوعًا تسمح به إعدادات الأعمال الحالية.'],en:['Media type is not allowed','Choose a type allowed by current settings.']},
  category_missing:{ar:['التصنيف غير محدد','اختر تصنيفًا فعالًا للعمل.'],en:['Category is missing','Choose an active category.']},
  category_inactive:{ar:['التصنيف معطل','استبدل التصنيف الحالي بتصنيف فعال.'],en:['Category is inactive','Replace it with an active category.']},
  category_invalid:{ar:['التصنيف غير صالح','اختر تصنيفًا صالحًا للعمل.'],en:['Category is invalid','Choose a valid category.']},
  media_missing:{ar:['لا توجد وسائط','أضف وسيطًا واحدًا على الأقل.'],en:['Media is missing','Add at least one media item.']},
  media_limit_exceeded:{ar:['عدد الوسائط يتجاوز الحد','أزل العناصر الزائدة قبل الإرسال.'],en:['Media limit exceeded','Remove extra media before submitting.']},
  media_type_mismatch:{ar:['وسيط غير مطابق','تحقق من توافق النوع والصيغة مع نمط العمل.'],en:['Media type mismatch','Check media kind and format.']},
  media_processing_pending:{ar:['معالجة الوسائط لم تكتمل','انتظر حتى تصبح جميع الوسائط جاهزة.'],en:['Media processing is pending','Wait until every item is ready.']},
  media_processing_failed:{ar:['فشلت معالجة وسيط','أزل الوسيط المتعذر أوأعد رفعه.'],en:['Media processing failed','Remove or replace the failed item.']},
  media_invalid:{ar:['حالة وسيط غير صالحة','راجع وسائط العمل قبل الإرسال.'],en:['Invalid media state','Review work media before submitting.']},
  cover_missing:{ar:['الغلاف غير محدد','عيّن صورة جاهزة كغلاف.'],en:['Cover is missing','Select a ready image as the cover.']},
  cover_invalid:{ar:['الغلاف غير صالح','عيّن وسيط صورة فعالًا كغلاف.'],en:['Cover is invalid','Choose an active image media item.']},
  cover_not_ready:{ar:['الغلاف غير جاهز','انتظر اكتمال معالجة صورة الغلاف.'],en:['Cover is not ready','Wait for cover processing to finish.']},
  designer_missing:{ar:['لم يحدد مصمم','إسناد مصمم يساعد فريق المراجعة.'],en:['Designer not assigned','Assigning a designer helps reviewers.']},
  tags_missing:{ar:['لا توجد وسوم','الوسوم تحسن تنظيم العمل واكتشافه.'],en:['No tags selected','Tags improve organization and discovery.']},
  price_missing:{ar:['السعر غير محدد','إضافة السعر توصية غير مانعة.'],en:['Price is missing','Adding a price is recommended.']},
  delivery_missing:{ar:['مدة التسليم غير محددة','إضافة مدة التسليم توصية غير مانعة.'],en:['Delivery time is missing','Adding delivery time is recommended.']},
  summary_short:{ar:['الملخص قصير','يمكن توضيح فكرة العمل بمزيد من التفاصيل.'],en:['Summary is short','Consider adding more context.']},
  description_short:{ar:['الوصف قصير','يمكن إثراء الوصف قبل المراجعة.'],en:['Description is short','Consider enriching the description.']},
  internal_notes_missing:{ar:['لا توجد ملاحظات إدارية','الملاحظات الداخلية اختيارية.'],en:['No internal notes','Internal notes are optional.']}
}
function sectionLabel(key:string) { return (sectionNames[props.locale] as Record<string,string>)[key] || key }
function sectionStatus(status:string) {
  return props.locale === 'ar'
    ? ({ ready:'مكتمل', warning:'توصيات', blocked:'ناقص' } as Record<string,string>)[status]
    : ({ ready:'Complete', warning:'Recommendations', blocked:'Blocked' } as Record<string,string>)[status]
}
function itemCopy(code:string) {
  const pair = copies[code]?.[props.locale] || [code, '']
  return { title:pair[0], copy:pair[1] }
}
</script>

<style scoped>
.ym-readiness{display:grid;gap:18px;border:1px solid var(--aw-border);border-radius:19px;padding:clamp(20px,2.3vw,24px);color:var(--aw-text);background:var(--aw-surface);box-shadow:var(--aw-shadow),inset 0 1px 0 var(--aw-highlight)}
.ym-readiness>header{display:flex;align-items:flex-start;gap:13px}.ym-readiness__icon{display:grid;flex:0 0 auto;width:42px;height:42px;place-items:center;border-radius:13px;color:var(--aw-electric);background:color-mix(in srgb,var(--aw-electric) 12%,transparent);font-weight:900}.ym-readiness header>div:nth-child(2){display:grid;flex:1;gap:3px}.ym-readiness header p,.ym-readiness header h2{margin:0}.ym-readiness header p{color:var(--aw-kicker);font-size:12.5px;font-weight:850}.ym-readiness header h2{font-size:20px}.ym-readiness header small{color:var(--aw-muted);font-size:13.5px;line-height:1.6}.ym-readiness__state{display:grid;justify-items:end;gap:3px;border-radius:12px;padding:8px 11px;color:var(--aw-text);background:var(--aw-control);font-size:13px}.ym-readiness__state span{color:var(--aw-muted);font-size:12px}.is-ready .ym-readiness__state{color:var(--aw-emerald)}.is-blocked .ym-readiness__state{color:var(--aw-rose)}.is-submitted .ym-readiness__state{color:var(--aw-cyan)}
.ym-readiness__changes,.ym-readiness__error{border:1px solid color-mix(in srgb,var(--aw-amber) 32%,var(--aw-border));border-radius:14px;padding:12px 14px;background:color-mix(in srgb,var(--aw-amber) 8%,var(--aw-control))}.ym-readiness__changes p{margin:4px 0 0;color:var(--aw-muted);line-height:1.6}.ym-readiness__error{display:flex;justify-content:space-between;gap:10px;color:var(--aw-rose)}.ym-readiness button,.ym-readiness a{min-height:40px;border:1px solid var(--aw-border);border-radius:10px;padding:8px 12px;color:var(--aw-text);background:var(--aw-control);font:inherit;font-weight:800;text-decoration:none}
.ym-readiness__counts{display:flex;flex-wrap:wrap;gap:8px}.ym-readiness__counts span{display:flex;align-items:center;gap:8px;border-radius:12px;padding:8px 12px;background:var(--aw-control);color:var(--aw-muted);font-size:13px}.ym-readiness__counts b{direction:ltr;color:var(--aw-text);font-size:18px;font-variant-numeric:tabular-nums}.ym-readiness__sections{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.ym-readiness__sections article{display:grid;align-content:start;gap:10px;border:1px solid var(--aw-soft-border);border-radius:15px;padding:13px;background:var(--aw-control)}.ym-readiness__sections article>header{display:flex;justify-content:space-between;gap:8px}.ym-readiness__sections article>header span{color:var(--aw-muted);font-size:12px}.ym-readiness__sections ul{display:grid;gap:8px;margin:0;padding:0;list-style:none}.ym-readiness__sections li{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:8px;border-block-start:1px solid var(--aw-soft-border);padding-block-start:9px}.ym-readiness__sections li>span{color:var(--aw-rose);font-weight:900}.ym-readiness__sections li.is-warning>span{color:var(--aw-amber)}.ym-readiness__sections li div{display:grid;gap:2px}.ym-readiness__sections li strong{font-size:13.5px}.ym-readiness__sections li small,.ym-readiness__sections article>p{margin:0;color:var(--aw-muted);font-size:12.5px;line-height:1.55}.ym-readiness__sections li button{min-height:36px;padding:6px 9px;font-size:12px}.ym-readiness>footer{display:flex;align-items:center;justify-content:space-between;gap:12px;border-block-start:1px solid var(--aw-soft-border);padding-block-start:14px}.ym-readiness>footer p{margin:0;color:var(--aw-muted)}.ym-readiness>footer div{display:flex;gap:8px}.ym-readiness>footer a:first-child{border:0;color:#fff;background:linear-gradient(135deg,var(--aw-violet),var(--aw-magenta))}
button:focus-visible,a:focus-visible{outline:3px solid color-mix(in srgb,var(--aw-electric) 40%,transparent);outline-offset:2px}
@media(max-width:760px){.ym-readiness__sections{grid-template-columns:1fr}.ym-readiness>header,.ym-readiness>footer{align-items:stretch;flex-direction:column}.ym-readiness__state{justify-items:start}.ym-readiness>footer div{display:grid}.ym-readiness__sections li{grid-template-columns:auto minmax(0,1fr)}.ym-readiness__sections li button{grid-column:2}}
@media(prefers-reduced-motion:reduce){*{transition:none!important}}
</style>
