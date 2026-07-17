<template>
  <Teleport to="body">
    <div v-if="open && entity" class="ym-confirm-overlay" role="presentation" @click.self="requestClose">
      <section ref="dialogRef" class="ym-confirm-dialog" role="dialog" aria-modal="true" :aria-labelledby="titleId" :dir="locale === 'ar' ? 'rtl' : 'ltr'" tabindex="-1" @keydown.esc="requestClose">
        <header><span>{{ text.eyebrow }}</span><h2 :id="titleId">{{ text.title }}</h2></header>
        <div class="ym-confirm-identity"><strong>{{ locale === 'ar' ? entity.name_ar : entity.name_en }}</strong><code dir="ltr">{{ entity.slug }}</code><small>{{ text.works }}: {{ formatNumber(entity.works_count) }}</small></div>
        <p>{{ text.warning }}</p>
        <p v-if="error" class="is-error" role="alert">{{ error }}</p>
        <footer><button type="button" class="is-secondary" :disabled="loading" @click="requestClose">{{ text.cancel }}</button><button type="button" class="is-danger" :disabled="loading" @click="emit('confirm')">{{ loading ? text.working : text.confirm }}</button></footer>
      </section>
    </div>
  </Teleport>
</template>
<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
interface Entity { id:number; name_ar:string; name_en:string; slug:string; works_count:number }
const props=defineProps<{open:boolean;entity:Entity|null;entityType:'category'|'tag';locale:'ar'|'en';loading:boolean;error:string|null}>()
const emit=defineEmits<{close:[];confirm:[]}>()
const dialogRef=ref<HTMLElement|null>(null)
const titleId=`ym-disable-${props.entityType}-title`
const text=computed(()=>props.locale==='ar'?{eyebrow:'تأكيد التعطيل',title:props.entityType==='category'?'تعطيل التصنيف':'تعطيل الوسم',works:'الأعمال المرتبطة',warning:props.entityType==='category'?'ستبقى الأعمال مرتبطة بهذا التصنيف. لن يُحذف التصنيف أو أي عمل.':'ستبقى إسنادات الأعمال الحالية مرتبطة بهذا الوسم. لن يُحذف الوسم أو تُفصل الأعمال.',cancel:'إلغاء',confirm:'تعطيل',working:'جارٍ التعطيل'}:{eyebrow:'Disable confirmation',title:props.entityType==='category'?'Disable category':'Disable tag',works:'Linked works',warning:props.entityType==='category'?'Works remain linked to this category. Neither the category nor works are deleted.':'Existing work assignments remain linked to this tag. Neither the tag nor assignments are deleted.',cancel:'Cancel',confirm:'Disable',working:'Disabling'})
watch(()=>props.open,async open=>{if(open){await nextTick();dialogRef.value?.focus()}})
function requestClose(){if(!props.loading)emit('close')}function formatNumber(v:number){return new Intl.NumberFormat(props.locale==='ar'?'ar-YE':'en-US').format(v)}
</script>
<style scoped>
.ym-confirm-overlay{position:fixed;inset:0;z-index:95;display:grid;place-items:center;background:rgba(2,6,23,.62);padding:1rem}.ym-confirm-dialog{width:min(100%,500px);border:1px solid var(--ym-card-border);border-radius:24px;outline:none;background:var(--ym-card-bg);color:var(--ym-text);box-shadow:0 25px 70px rgba(2,6,23,.35);padding:1.25rem}.ym-confirm-dialog header span{color:#f59e0b;font-size:11px;font-weight:900}.ym-confirm-dialog h2{margin:.25rem 0 1rem}.ym-confirm-identity{display:grid;gap:.35rem;border:1px solid var(--ym-soft-border);border-radius:16px;background:var(--ym-control-bg);padding:1rem}.ym-confirm-identity code{direction:ltr;text-align:left;color:#8b5cf6}.ym-confirm-identity small,.ym-confirm-dialog p{color:var(--ym-muted);line-height:1.7}.is-error{color:#fb7185!important}footer{display:flex;justify-content:flex-end;gap:.7rem;margin-top:1.1rem}button{min-height:43px;border-radius:13px;padding:.65rem 1rem;font-weight:900}.is-secondary{border:1px solid var(--ym-control-border);background:var(--ym-control-bg);color:var(--ym-text)}.is-danger{border:1px solid #e11d48;background:#e11d48;color:#fff}button:focus-visible{box-shadow:0 0 0 3px rgba(225,29,72,.2)}
</style>
