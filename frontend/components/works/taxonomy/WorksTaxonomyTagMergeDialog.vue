<template>
  <Teleport to="body">
    <div v-if="open" class="ym-merge-overlay" role="presentation" @click.self="requestClose">
      <section class="ym-merge-dialog" role="dialog" aria-modal="true" aria-labelledby="ym-tag-merge-title" :dir="locale === 'ar' ? 'rtl' : 'ltr'" tabindex="-1" @keydown.esc="requestClose">
        <header>
          <div><span>{{ text.eyebrow }}</span><h2 id="ym-tag-merge-title">{{ text.title }}</h2><p>{{ text.intro }}</p></div>
          <button type="button" :aria-label="text.close" :disabled="busy" @click="requestClose">×</button>
        </header>

        <template v-if="step === 'select'">
          <div class="ym-merge-columns">
            <section>
              <div class="ym-merge-section-head"><div><strong>{{ text.target }}</strong><small>{{ text.targetHint }}</small></div><span>{{ target ? '1 / 1' : '0 / 1' }}</span></div>
              <label class="ym-merge-search"><span>{{ text.searchTarget }}</span><input v-model.trim="targetQuery" type="search" maxlength="80" autocomplete="off" /></label>
              <p v-if="targetQuery.length === 1" class="ym-merge-hint">{{ text.twoChars }}</p>
              <div v-if="targetLoading" class="ym-merge-state" role="status">{{ text.searching }}</div>
              <div v-else-if="targetError" class="ym-merge-state is-error" role="alert">{{ targetError }} <button type="button" @click="searchTargets">{{ text.retry }}</button></div>
              <div v-else class="ym-merge-results">
                <label v-for="tag in targetResults" :key="'target-'+tag.id" class="ym-merge-option" :class="{'is-selected':target?.id===tag.id}">
                  <input v-model="targetId" type="radio" name="merge-target" :value="tag.id" @change="chooseTarget(tag)" />
                  <span><strong>{{ displayName(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code><small>{{ text.works }}: {{ number(tag.works_count) }}</small></span>
                </label>
                <p v-if="targetResults.length===0" class="ym-merge-empty">{{ text.noResults }}</p>
              </div>
              <p v-if="targetFieldError" class="ym-merge-form-error" role="alert">{{ targetFieldError }}</p>
            </section>

            <section>
              <div class="ym-merge-section-head"><div><strong>{{ text.sources }}</strong><small>{{ text.sourcesHint }}</small></div><span>{{ sources.length }} / 25</span></div>
              <label class="ym-merge-search"><span>{{ text.searchSources }}</span><input v-model.trim="sourceQuery" type="search" maxlength="80" autocomplete="off" /></label>
              <p v-if="sourceQuery.length === 1" class="ym-merge-hint">{{ text.twoChars }}</p>
              <div v-if="sourceLoading" class="ym-merge-state" role="status">{{ text.searching }}</div>
              <div v-else-if="sourceError" class="ym-merge-state is-error" role="alert">{{ sourceError }} <button type="button" @click="searchSources">{{ text.retry }}</button></div>
              <div v-else class="ym-merge-results">
                <label v-for="tag in visibleSourceResults" :key="'source-'+tag.id" class="ym-merge-option" :class="{'is-selected':isSourceSelected(tag.id)}">
                  <input type="checkbox" :checked="isSourceSelected(tag.id)" :disabled="!isSourceSelected(tag.id)&&sources.length>=25" @change="toggleSource(tag)" />
                  <span><strong>{{ displayName(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code><small><b :class="tag.is_active?'is-active':'is-disabled'">{{ tag.is_active?text.active:text.disabled }}</b> · {{ text.works }}: {{ number(tag.works_count) }}</small></span>
                </label>
                <p v-if="visibleSourceResults.length===0" class="ym-merge-empty">{{ text.noResults }}</p>
              </div>
              <p v-if="sourceFieldError" class="ym-merge-form-error" role="alert">{{ sourceFieldError }}</p>
            </section>
          </div>
          <section v-if="sources.length" class="ym-merge-selected"><strong>{{ text.selectedSources }}</strong><div><button v-for="tag in sources" :key="tag.id" type="button" :aria-label="text.removeSource(displayName(tag))" @click="toggleSource(tag)">{{ displayName(tag) }} <span aria-hidden="true">×</span></button></div></section>
          <p v-if="formError" class="ym-merge-form-error" role="alert">{{ formError }}</p>
          <footer><button type="button" class="is-secondary" @click="requestClose">{{ text.cancel }}</button><button type="button" class="is-primary" :disabled="!target||sources.length===0" @click="goConfirm">{{ text.review }}</button></footer>
        </template>

        <template v-else-if="step === 'confirm'">
          <section class="ym-merge-review">
            <div><span>{{ text.target }}</span><strong>{{ target ? displayName(target) : '—' }}</strong><code v-if="target" dir="ltr">{{ target.slug }}</code></div>
            <div><span>{{ text.sourceCount }}</span><strong>{{ number(sources.length) }}</strong></div>
          </section>
          <div class="ym-merge-review-list"><article v-for="tag in sources" :key="tag.id"><strong>{{ displayName(tag) }}</strong><code dir="ltr">{{ tag.slug }}</code><span>{{ tag.is_active?text.active:text.disabled }}</span></article></div>
          <aside class="ym-merge-warning"><strong>{{ text.confirmTitle }}</strong><p>{{ text.disableWarning }}</p><p>{{ text.transferWarning }}</p></aside>
          <p v-if="formError" class="ym-merge-form-error" role="alert">{{ formError }}</p>
          <footer><button type="button" class="is-secondary" :disabled="loading" @click="step='select'">{{ text.back }}</button><button type="button" class="is-primary" :disabled="loading" @click="submitMerge">{{ loading?text.merging:text.confirm }}</button></footer>
        </template>

        <template v-else>
          <section class="ym-merge-result" aria-live="polite"><span aria-hidden="true">✓</span><h3>{{ resultMessage }}</h3><p>{{ text.resultCopy }}</p></section>
          <dl v-if="result" class="ym-merge-summary"><div v-for="item in summaryItems" :key="item.key"><dt>{{ item.label }}</dt><dd>{{ number(item.value) }}</dd></div></dl>
          <footer><button type="button" class="is-primary" @click="emit('close')">{{ text.done }}</button></footer>
        </template>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useApiClient } from '~/composables/useApiClient'

interface TagItem { id:number;name_ar:string;name_en:string;slug:string;disabled_at:string|null;is_active:boolean;sort_order:number;works_count:number }
interface CatalogResponse { success:boolean;data:{items:TagItem[]}|null;message?:string }
interface MergeSummary { source_tags_requested:number;source_tags_disabled:number;affected_works:number;source_assignments_removed:number;target_assignments_added:number;duplicate_assignments_collapsed:number }
interface MergeResponse { success:boolean;data:{summary:MergeSummary;changed:boolean}|null;message?:string;errors?:Record<string,string[]>|null }
const props=defineProps<{open:boolean;locale:'ar'|'en'}>()
const emit=defineEmits<{close:[];merged:[message:string];authorizationError:[]}>()
const {apiFetch}=useApiClient()
const step=ref<'select'|'confirm'|'result'>('select'),target=ref<TagItem|null>(null),targetId=ref<number|null>(null),sources=ref<TagItem[]>([]),targetQuery=ref(''),sourceQuery=ref(''),targetResults=ref<TagItem[]>([]),sourceResults=ref<TagItem[]>([]),targetLoading=ref(false),sourceLoading=ref(false),loading=ref(false),targetError=ref<string|null>(null),sourceError=ref<string|null>(null),targetFieldError=ref<string|null>(null),sourceFieldError=ref<string|null>(null),formError=ref<string|null>(null),result=ref<MergeSummary|null>(null),resultMessage=ref('')
let revision=0,targetSearchRevision=0,sourceSearchRevision=0,targetTimer:ReturnType<typeof setTimeout>|null=null,sourceTimer:ReturnType<typeof setTimeout>|null=null,resetting=false
const copies={ar:{eyebrow:'دمج آمن',title:'دمج وسوم الأعمال',intro:'انقل إسنادات عدة وسوم إلى هدف فعال واحد دون عرض الأعمال.',close:'إغلاق حوار الدمج',target:'الوسم الهدف',targetHint:'وسم فعال واحد فقط',sources:'الوسوم المصدر',sourcesHint:'من 1 إلى 25 وسمًا، فعالًا أو معطلًا',searchTarget:'بحث الهدف',searchSources:'بحث المصادر',twoChars:'اكتب حرفين على الأقل للبحث.',searching:'جارٍ البحث…',retry:'إعادة المحاولة',works:'الأعمال',noResults:'لا توجد نتائج مطابقة.',active:'فعال',disabled:'معطل',selectedSources:'المصادر المحددة',removeSource:(n:string)=>`إزالة ${n}`,cancel:'إلغاء',review:'مراجعة الدمج',sourceCount:'عدد المصادر',confirmTitle:'هذا الإجراء لا يحذف الوسوم',disableWarning:'ستُعطّل المصادر الفعالة، وستبقى المصادر المعطلة على حالها.',transferWarning:'ستُنقل الإسنادات إلى الهدف وتزال إسنادات المصادر مع طي التكرارات.',back:'رجوع',confirm:'تنفيذ الدمج',merging:'جارٍ الدمج…',resultCopy:'اكتملت العملية ويمكن مراجعة الحسابات الآمنة أدناه.',done:'تم',generic:'تعذر إكمال العملية. حاول مرة أخرى.',required:'اختر هدفًا ومصدرًا واحدًا على الأقل.',requested:'المصادر المطلوبة',disabledCount:'المصادر المعطلة',affected:'الأعمال المتأثرة',removed:'إسنادات المصادر المزالة',added:'إسنادات الهدف المضافة',collapsed:'التكرارات المطوية'},en:{eyebrow:'Safe merge',title:'Merge work tags',intro:'Move assignments from multiple tags into one active target without exposing works.',close:'Close merge dialog',target:'Target tag',targetHint:'One active tag only',sources:'Source tags',sourcesHint:'Choose 1–25 active or disabled tags',searchTarget:'Search target',searchSources:'Search sources',twoChars:'Enter at least two characters to search.',searching:'Searching…',retry:'Retry',works:'Works',noResults:'No matching results.',active:'Active',disabled:'Disabled',selectedSources:'Selected sources',removeSource:(n:string)=>`Remove ${n}`,cancel:'Cancel',review:'Review merge',sourceCount:'Source count',confirmTitle:'This operation does not delete tags',disableWarning:'Active sources are disabled; already-disabled sources remain unchanged.',transferWarning:'Assignments move to the target and source assignments are removed with duplicates collapsed.',back:'Back',confirm:'Merge tags',merging:'Merging…',resultCopy:'The operation completed. Review its safe aggregate counts below.',done:'Done',generic:'Could not complete the operation. Try again.',required:'Choose a target and at least one source.',requested:'Sources requested',disabledCount:'Sources disabled',affected:'Affected works',removed:'Source assignments removed',added:'Target assignments added',collapsed:'Duplicates collapsed'}}
const text=computed(()=>copies[props.locale])
const busy=computed(()=>loading.value||targetLoading.value||sourceLoading.value)
const visibleSourceResults=computed(()=>sourceResults.value.filter(tag=>tag.id!==target.value?.id))
const summaryItems=computed(()=>result.value?[{key:'requested',label:text.value.requested,value:result.value.source_tags_requested},{key:'disabled',label:text.value.disabledCount,value:result.value.source_tags_disabled},{key:'affected',label:text.value.affected,value:result.value.affected_works},{key:'removed',label:text.value.removed,value:result.value.source_assignments_removed},{key:'added',label:text.value.added,value:result.value.target_assignments_added},{key:'collapsed',label:text.value.collapsed,value:result.value.duplicate_assignments_collapsed}]:[])
watch(()=>props.open,(open)=>{revision++;if(open){reset();void searchTargets();void searchSources()}})
watch(targetQuery,q=>scheduleSearch('target',q))
watch(sourceQuery,q=>scheduleSearch('source',q))
onUnmounted(()=>{revision++;targetSearchRevision++;sourceSearchRevision++;if(targetTimer)clearTimeout(targetTimer);if(sourceTimer)clearTimeout(sourceTimer)})
function reset(){resetting=true;targetSearchRevision++;sourceSearchRevision++;step.value='select';target.value=null;targetId.value=null;sources.value=[];targetQuery.value='';sourceQuery.value='';targetResults.value=[];sourceResults.value=[];targetError.value=null;sourceError.value=null;targetFieldError.value=null;sourceFieldError.value=null;formError.value=null;result.value=null;resultMessage.value='';loading.value=false;setTimeout(()=>{resetting=false},0)}
function scheduleSearch(kind:'target'|'source',q:string){if(resetting)return;const timer=kind==='target'?targetTimer:sourceTimer;if(timer)clearTimeout(timer);if(q.length===1){if(kind==='target'){targetSearchRevision++;targetLoading.value=false;targetResults.value=[]}else{sourceSearchRevision++;sourceLoading.value=false;sourceResults.value=[]}return}const next=setTimeout(()=>{kind==='target'?void searchTargets():void searchSources()},280);if(kind==='target')targetTimer=next;else sourceTimer=next}
async function searchTargets(){await search('target')}
async function searchSources(){await search('source')}
async function search(kind:'target'|'source'){const q=(kind==='target'?targetQuery.value:sourceQuery.value).trim();if(q.length===1)return;const currentDialog=revision;const currentSearch=kind==='target'?++targetSearchRevision:++sourceSearchRevision;if(kind==='target'){targetLoading.value=true;targetError.value=null}else{sourceLoading.value=true;sourceError.value=null}try{const query:Record<string,string|number>={state:kind==='target'?'active':'all',sort:'works_count',direction:'desc',page:1,per_page:50};if(q.length>=2)query.q=q;const response=await apiFetch<CatalogResponse>('/admin/works/taxonomy/tags',{query});if(!isCurrentSearch(kind,currentDialog,currentSearch))return;if(!response.success||!response.data)throw new Error('invalid');if(kind==='target')targetResults.value=response.data.items;else sourceResults.value=response.data.items}catch(error:unknown){if(!isCurrentSearch(kind,currentDialog,currentSearch))return;if([401,403].includes(errorStatus(error)??0)){emit('authorizationError');requestClose();return}if(kind==='target')targetError.value=text.value.generic;else sourceError.value=text.value.generic}finally{if(isCurrentSearch(kind,currentDialog,currentSearch)){if(kind==='target')targetLoading.value=false;else sourceLoading.value=false}}}
function isCurrentSearch(kind:'target'|'source',dialogRevision:number,searchRevision:number){return props.open&&dialogRevision===revision&&searchRevision===(kind==='target'?targetSearchRevision:sourceSearchRevision)}
function chooseTarget(tag:TagItem){target.value=tag;targetId.value=tag.id;sources.value=sources.value.filter(source=>source.id!==tag.id);targetFieldError.value=null;formError.value=null}
function isSourceSelected(id:number){return sources.value.some(tag=>tag.id===id)}
function toggleSource(tag:TagItem){if(tag.id===target.value?.id)return;const index=sources.value.findIndex(item=>item.id===tag.id);if(index>=0)sources.value.splice(index,1);else if(sources.value.length<25)sources.value.push(tag);sourceFieldError.value=null;formError.value=null}
function goConfirm(){if(!target.value||sources.value.length===0){formError.value=text.value.required;return}step.value='confirm'}
async function submitMerge(){if(loading.value||!target.value||sources.value.length===0)return;loading.value=true;formError.value=null;targetFieldError.value=null;sourceFieldError.value=null;const current=revision;try{const response=await apiFetch<MergeResponse>('/admin/works/taxonomy/tags/merge',{method:'PATCH',body:{target_tag_id:target.value.id,source_tag_ids:sources.value.map(tag=>tag.id)}});if(current!==revision||!props.open)return;if(!response.success||!response.data)throw new Error('invalid');result.value=response.data.summary;resultMessage.value=response.message||'';step.value='result';emit('merged',response.message||'')}catch(error:unknown){if(current!==revision)return;if([401,403].includes(errorStatus(error)??0)){emit('authorizationError');requestClose();return}const data=errorData(error);const errors=data?.errors as Record<string,string[]>|undefined;targetFieldError.value=errors?.target_tag_id?.[0]||null;sourceFieldError.value=errors?.source_tag_ids?.[0]||errors?.['source_tag_ids.0']?.[0]||null;formError.value=targetFieldError.value||sourceFieldError.value?null:(typeof data?.message==='string'?data.message:text.value.generic);step.value='select'}finally{if(current===revision)loading.value=false}}
function requestClose(){if(!busy.value){revision++;emit('close')}}
function displayName(tag:TagItem){return props.locale==='ar'?tag.name_ar:tag.name_en}function number(v:number){return new Intl.NumberFormat(props.locale==='ar'?'ar-YE':'en-US').format(v)}
function errorData(error:unknown):Record<string,unknown>|null{if(!error||typeof error!=='object')return null;const item=error as {data?:unknown;response?:{_data?:unknown}};const data=item.data??item.response?._data;return data&&typeof data==='object'?data as Record<string,unknown>:null}
function errorStatus(error:unknown):number|null{if(!error||typeof error!=='object')return null;const item=error as {status?:number;statusCode?:number;response?:{status?:number}};return item.response?.status??item.statusCode??item.status??null}
</script>

<style scoped>
.ym-merge-overlay{position:fixed;inset:0;z-index:100;display:grid;place-items:center;background:rgba(2,6,23,.66);padding:1rem}.ym-merge-dialog{width:min(100%,980px);max-height:92vh;overflow:auto;border:1px solid var(--ym-card-border);border-radius:26px;outline:none;background:var(--ym-card-bg);color:var(--ym-text);box-shadow:0 30px 80px rgba(2,6,23,.4);padding:1.25rem}.ym-merge-dialog>header{display:flex;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--ym-soft-border);padding-bottom:1rem}.ym-merge-dialog>header span{color:#8b5cf6;font-size:11px;font-weight:950}.ym-merge-dialog h2{margin:.2rem 0}.ym-merge-dialog header p{color:var(--ym-muted);margin:.3rem 0 0}.ym-merge-dialog>header button{width:40px;height:40px;border:1px solid var(--ym-control-border);border-radius:12px;background:var(--ym-control-bg);color:var(--ym-text);font-size:1.4rem}.ym-merge-columns{display:grid;grid-template-columns:1fr 1fr;gap:1rem;padding:1rem 0}.ym-merge-columns>section{min-width:0;border:1px solid var(--ym-soft-border);border-radius:18px;padding:1rem}.ym-merge-section-head{display:flex;justify-content:space-between;gap:.7rem}.ym-merge-section-head div{display:grid;gap:.25rem}.ym-merge-section-head small,.ym-merge-hint{color:var(--ym-muted);font-size:11px}.ym-merge-section-head>span{color:#8b5cf6;font-weight:900}.ym-merge-search{display:grid;gap:.35rem;margin-top:.8rem}.ym-merge-search span{color:var(--ym-muted);font-size:11px;font-weight:900}.ym-merge-search input{min-height:43px;border:1px solid var(--ym-control-border);border-radius:13px;outline:none;background:var(--ym-control-bg);color:var(--ym-text);padding:.65rem}.ym-merge-results{display:grid;gap:.5rem;max-height:300px;overflow:auto;margin-top:.7rem}.ym-merge-option{display:flex;align-items:flex-start;gap:.65rem;border:1px solid var(--ym-soft-border);border-radius:14px;padding:.7rem;cursor:pointer}.ym-merge-option.is-selected{border-color:#8b5cf6;background:rgba(139,92,246,.08)}.ym-merge-option>span{display:grid;min-width:0;gap:.15rem}.ym-merge-option code{overflow:hidden;text-overflow:ellipsis;color:#8b5cf6}.ym-merge-option small{color:var(--ym-muted)}.ym-merge-option b{font-weight:900}.ym-merge-option b.is-active{color:#10b981}.ym-merge-option b.is-disabled{color:#f59e0b}.ym-merge-state,.ym-merge-empty{color:var(--ym-muted);padding:1rem;text-align:center}.ym-merge-state.is-error,.ym-merge-form-error{color:#fb7185}.ym-merge-state button{border:0;background:transparent;color:#8b5cf6;font-weight:900}.ym-merge-selected{border:1px solid var(--ym-soft-border);border-radius:16px;padding:.8rem}.ym-merge-selected>div{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:.55rem}.ym-merge-selected button{border:1px solid rgba(139,92,246,.3);border-radius:999px;background:rgba(139,92,246,.1);color:var(--ym-text);padding:.4rem .65rem}.ym-merge-review{display:grid;grid-template-columns:2fr 1fr;gap:.8rem;margin-top:1rem}.ym-merge-review>div,.ym-merge-review-list article{display:grid;gap:.3rem;border:1px solid var(--ym-soft-border);border-radius:15px;padding:.85rem}.ym-merge-review span,.ym-merge-review-list span{color:var(--ym-muted);font-size:11px}.ym-merge-review-list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-top:.8rem}.ym-merge-warning{border:1px solid rgba(245,158,11,.3);border-radius:16px;background:color-mix(in srgb,#f59e0b 8%,var(--ym-control-bg));padding:1rem;margin-top:1rem}.ym-merge-warning p{color:var(--ym-muted);margin:.4rem 0 0}.ym-merge-result{text-align:center;padding:1.5rem}.ym-merge-result>span{display:grid;width:48px;height:48px;place-items:center;border-radius:50%;background:rgba(16,185,129,.15);color:#10b981;font-size:1.4rem;margin:auto}.ym-merge-result p{color:var(--ym-muted)}.ym-merge-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:.7rem}.ym-merge-summary div{border:1px solid var(--ym-soft-border);border-radius:15px;padding:.8rem}.ym-merge-summary dt{color:var(--ym-muted);font-size:11px}.ym-merge-summary dd{font-size:1.25rem;font-weight:950;margin:.3rem 0 0}footer{display:flex;justify-content:flex-end;gap:.7rem;border-top:1px solid var(--ym-soft-border);margin-top:1rem;padding-top:1rem}footer button{min-height:43px;border-radius:13px;padding:.65rem 1rem;font-weight:900}.is-secondary{border:1px solid var(--ym-control-border);background:var(--ym-control-bg);color:var(--ym-text)}.is-primary{border:1px solid #7c3aed;background:#7c3aed;color:#fff}button:focus-visible,input:focus{box-shadow:0 0 0 3px rgba(139,92,246,.18)}@media(max-width:760px){.ym-merge-columns,.ym-merge-review,.ym-merge-summary{grid-template-columns:1fr}.ym-merge-review-list{grid-template-columns:1fr}.ym-merge-dialog{padding:1rem}}
</style>
