<template>
  <section class="ym-catalog-panel" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
    <header class="ym-catalog-heading">
      <div><span>{{ text.eyebrow }}</span><h2>{{ text.title }}</h2><p>{{ canAnyAction ? text.description : text.readOnly }}</p></div>
      <div class="ym-catalog-heading__actions">
        <button v-if="entityType==='tag'&&canMerge" type="button" class="is-secondary" @click="mergeOpen=true">{{ text.merge }}</button>
        <button v-if="canCreate" type="button" class="is-primary" @click="openCreate">{{ text.create }}</button>
      </div>
    </header>

    <p v-if="liveMessage" class="ym-catalog-live" aria-live="polite">{{ safeText(liveMessage) }}</p>

    <div v-if="summary" class="ym-catalog-summary" :aria-label="text.summary">
      <article v-for="card in summaryCards" :key="card.key"><span>{{ card.label }}</span><strong>{{ number(card.value) }}</strong></article>
    </div>

    <form class="ym-catalog-filters" @submit.prevent="applyFilters">
      <label class="is-search"><span>{{ text.search }}</span><input v-model.trim="draft.q" type="search" maxlength="80" :placeholder="text.searchPlaceholder" autocomplete="off" /></label>
      <label><span>{{ text.state }}</span><select v-model="draft.state"><option value="all">{{ text.all }}</option><option value="active">{{ text.active }}</option><option value="disabled">{{ text.disabled }}</option></select></label>
      <div class="ym-catalog-filter-toolbar">
        <button type="button" class="is-secondary" :class="{ 'is-open': advancedOpen }" @click="advancedOpen=!advancedOpen">{{ text.advanced }}</button>
        <button type="submit" class="is-primary" :disabled="loading">{{ text.apply }}</button>
        <button type="button" class="is-secondary" :disabled="loading" @click="resetFilters">{{ text.reset }}</button>
      </div>
      <div v-show="advancedOpen" class="ym-catalog-advanced">
        <label><span>{{ text.sort }}</span><select v-model="draft.sort"><option v-for="option in sortOptions" :key="option" :value="option">{{ sortLabel(option) }}</option></select></label>
        <label><span>{{ text.direction }}</span><select v-model="draft.direction"><option value="asc">{{ text.asc }}</option><option value="desc">{{ text.desc }}</option></select></label>
        <label><span>{{ text.perPage }}</span><select v-model.number="draft.per_page"><option :value="15">15</option><option :value="25">25</option><option :value="50">50</option></select></label>
      </div>
    </form>

    <div class="ym-catalog-table-card">
      <div v-if="loading && !hasLoaded" class="ym-catalog-state" role="status"><span class="ym-catalog-spinner" aria-hidden="true" /><h3>{{ text.initialLoading }}</h3><p>{{ text.loadingCopy }}</p></div>
      <div v-else-if="error" class="ym-catalog-state is-error" role="alert"><span aria-hidden="true">!</span><h3>{{ text.errorTitle }}</h3><p>{{ safeText(error) }}</p><button type="button" class="is-secondary" @click="fetchCatalog">{{ text.retry }}</button></div>
      <div v-else-if="hasLoaded&&items.length===0" class="ym-catalog-state" role="status"><span aria-hidden="true">0</span><h3>{{ hasFilters ? text.filteredEmpty : text.empty }}</h3><p>{{ hasFilters ? text.filteredEmptyCopy : text.emptyCopy }}</p></div>
      <WorksTaxonomyCatalogSmartTable
        v-else
        :items="items"
        :pagination="pagination"
        :loading="loading"
        :locale="locale"
        :entity-type="entityType"
        :can-update="canUpdate"
        :can-disable="canDisable"
        @edit="openEdit"
        @disable="openDisable"
        @page="changePage"
      />
    </div>

    <WorksTaxonomyEntityDrawer :open="drawerOpen" :mode="drawerMode" :entity="selected" :entity-type="entityType" :locale="locale" :loading="mutationLoading" :error="mutationError" :field-errors="fieldErrors" @close="closeDrawer" @submit="submitEntity" />
    <WorksTaxonomyConfirmDialog :open="confirmOpen" :entity="selected" :entity-type="entityType" :locale="locale" :loading="mutationLoading" :error="mutationError" @close="closeConfirm" @confirm="disableEntity" />
    <WorksTaxonomyTagMergeDialog v-if="entityType==='tag'" :open="mergeOpen" :locale="locale" @close="mergeOpen=false" @merged="afterMerge" @authorization-error="handleMergeAuthorizationError" />
  </section>
</template>

<script setup lang="ts">
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import WorksTaxonomyCatalogSmartTable from './WorksTaxonomyCatalogSmartTable.vue'
import WorksTaxonomyConfirmDialog from './WorksTaxonomyConfirmDialog.vue'
import WorksTaxonomyEntityDrawer from './WorksTaxonomyEntityDrawer.vue'
import WorksTaxonomyTagMergeDialog from './WorksTaxonomyTagMergeDialog.vue'
import { useApiClient } from '~/composables/useApiClient'
import { formatYmNumber, toLatinDigits } from '~/utils/ymFormatting'

type EntityType='category'|'tag';type State='all'|'active'|'disabled';type Sort='sort_order'|'name_ar'|'name_en'|'slug'|'works_count'|'created_at'|'updated_at';type Direction='asc'|'desc';type PageSize=15|25|50
interface Item{id:number;name_ar:string;name_en:string;slug:string;disabled_at:string|null;is_active:boolean;sort_order:number;works_count:number;created_at:string;updated_at:string}
interface Pagination{current_page:number;per_page:number;total:number;last_page:number}
interface CatalogResponse{success:boolean;data:{items:Item[];pagination:Pagination;summary:Record<string,number>}|null;message?:string;errors?:Record<string,string[]>|null}
interface MutationResponse{success:boolean;data:{changed:boolean}|null;message?:string;errors?:Record<string,string[]>|null}
interface Filters{q:string;state:State;sort:Sort;direction:Direction;per_page:PageSize}
const props=defineProps<{entityType:EntityType;locale:'ar'|'en';active:boolean;canCreate:boolean;canUpdate:boolean;canDisable:boolean;canMerge?:boolean;permissionRevision:string}>()
const emit=defineEmits<{changed:[];authorizationError:[entity:EntityType]}>()
const {apiFetch}=useApiClient();const items=ref<Item[]>([]),summary=ref<Record<string,number>|null>(null),pagination=reactive<Pagination>({current_page:1,per_page:15,total:0,last_page:1}),loading=ref(false),hasLoaded=ref(false),error=ref<string|null>(null),liveMessage=ref(''),page=ref(1),drawerOpen=ref(false),drawerMode=ref<'create'|'edit'>('create'),confirmOpen=ref(false),mergeOpen=ref(false),advancedOpen=ref(false),selected=ref<Item|null>(null),mutationLoading=ref(false),mutationError=ref<string|null>(null),fieldErrors=ref<Record<string,string[]>>({})
let requestRevision=0,mutationRevision=0,searchTimer:ReturnType<typeof setTimeout>|null=null
const defaults=():Filters=>({q:'',state:'all',sort:'sort_order',direction:'asc',per_page:15});const draft=reactive<Filters>(defaults()),applied=reactive<Filters>(defaults())
const canMerge=computed(()=>props.canMerge===true),canAnyAction=computed(()=>props.canCreate||props.canUpdate||props.canDisable||canMerge.value),hasFilters=computed(()=>applied.q!==''||applied.state!=='all')
const sortOptions:Sort[]=['sort_order','name_ar','name_en','slug','works_count','created_at','updated_at']
const copies={ar:{eyebrow:props.entityType==='category'?'كتالوج التصنيفات':'كتالوج الوسوم',title:props.entityType==='category'?'إدارة التصنيفات':'إدارة الوسوم',description:'اقرأ الكتالوج ونفّذ الإجراءات التي يصرّح بها حسابك.',readOnly:'يعرض هذا الحساب الكتالوج فقط؛ الإجراءات غير ظاهرة لعدم توفر صلاحياتها.',merge:'دمج الوسوم',create:props.entityType==='category'?'إنشاء تصنيف':'إنشاء وسم',summary:'ملخص الكتالوج',search:'البحث',searchPlaceholder:'الاسم أو المعرّف النصي',state:'الحالة',all:'الكل',active:'فعال',disabled:'معطل',sort:'الترتيب',direction:'الاتجاه',asc:'تصاعدي',desc:'تنازلي',perPage:'لكل صفحة',advanced:'فلاتر متقدمة',apply:'تطبيق',reset:'إعادة ضبط',initialLoading:'جارٍ تحميل الكتالوج',loadingCopy:'يتم جلب البيانات الآمنة حسب الفلاتر الحالية.',errorTitle:'تعذر تحميل الكتالوج',retry:'إعادة المحاولة',empty:'الكتالوج فارغ',emptyCopy:'لا توجد سجلات في هذا الكتالوج بعد.',filteredEmpty:'لا توجد نتائج مطابقة',filteredEmptyCopy:'غيّر البحث أو الحالة ثم حاول مجددًا.',refreshing:'جارٍ تحديث النتائج…',identity:props.entityType==='category'?'التصنيف وهويته':'الوسم وهويته',nameAr:'الاسم العربي',nameEn:'الاسم الإنجليزي',sortOrder:'الترتيب',works:'الأعمال',worksAndUsage:'الأعمال والاستخدام',usage:'الاستخدام',used:'مستخدم',unused:'غير مستخدم',created:'الإنشاء',updated:'آخر تحديث',actions:'الإجراءات',edit:'تعديل',disable:'تعطيل',editAria:(n:string)=>`تعديل ${n}`,disableAria:(n:string)=>`تعطيل ${n}`,total:'الإجمالي',pagination:'ترقيم صفحات الكتالوج',previous:'السابق',next:'التالي',page:(p:number,l:number)=>`الصفحة ${p} من ${l}`,generic:'حدث خطأ أثناء تحميل الكتالوج.',mutationGeneric:'تعذر حفظ التغيير. حاول مرة أخرى.',totalLabel:'الإجمالي',activeLabel:'الفعالة',disabledLabel:'المعطلة',usedLabel:'المستخدمة',unusedLabel:'غير المستخدمة',legacyIds:'معرفات قديمة غير مربوطة',legacyWorks:'أعمال بقيم قديمة',assignments:'إجمالي الإسنادات'},en:{eyebrow:props.entityType==='category'?'Category catalog':'Tag catalog',title:props.entityType==='category'?'Manage categories':'Manage tags',description:'Read the catalog and use only the actions authorized for your account.',readOnly:'This account has catalog read access only; unauthorized actions are not shown.',merge:'Merge tags',create:props.entityType==='category'?'Create category':'Create tag',summary:'Catalog summary',search:'Search',searchPlaceholder:'Name or text identifier',state:'State',all:'All',active:'Active',disabled:'Disabled',sort:'Sort',direction:'Direction',asc:'Ascending',desc:'Descending',perPage:'Per page',advanced:'Advanced filters',apply:'Apply',reset:'Reset',initialLoading:'Loading catalog',loadingCopy:'Fetching safe catalog data with current filters.',errorTitle:'Could not load catalog',retry:'Retry',empty:'Catalog is empty',emptyCopy:'There are no records in this catalog yet.',filteredEmpty:'No matching results',filteredEmptyCopy:'Change search or state and try again.',refreshing:'Refreshing results…',identity:props.entityType==='category'?'Category identity':'Tag identity',nameAr:'Arabic name',nameEn:'English name',sortOrder:'Order',works:'works',worksAndUsage:'Works and usage',usage:'Usage',used:'Used',unused:'Unused',created:'Created',updated:'Last updated',actions:'Actions',edit:'Edit',disable:'Disable',editAria:(n:string)=>`Edit ${n}`,disableAria:(n:string)=>`Disable ${n}`,total:'Total',pagination:'Catalog pagination',previous:'Previous',next:'Next',page:(p:number,l:number)=>`Page ${p} of ${l}`,generic:'An error occurred while loading the catalog.',mutationGeneric:'Could not save the change. Try again.',totalLabel:'Total',activeLabel:'Active',disabledLabel:'Disabled',usedLabel:'Used',unusedLabel:'Unused',legacyIds:'Unmapped legacy IDs',legacyWorks:'Works with legacy values',assignments:'Total assignments'}}
const text=computed(()=>copies[props.locale])
const summaryCards=computed(()=>{const s=summary.value;if(!s)return[];return props.entityType==='category'?[{key:'total',label:text.value.totalLabel,value:s.total??0},{key:'active',label:text.value.activeLabel,value:s.active??0},{key:'disabled',label:text.value.disabledLabel,value:s.disabled??0},{key:'used',label:text.value.usedLabel,value:s.used??0},{key:'legacy_unmapped_category_ids',label:text.value.legacyIds,value:s.legacy_unmapped_category_ids??0}]:[{key:'total',label:text.value.totalLabel,value:s.total??0},{key:'active',label:text.value.activeLabel,value:s.active??0},{key:'disabled',label:text.value.disabledLabel,value:s.disabled??0},{key:'used',label:text.value.usedLabel,value:s.used??0},{key:'assignments_total',label:text.value.assignments,value:s.assignments_total??0}]})
watch(()=>props.active,active=>{if(active&&!hasLoaded.value)void fetchCatalog()},{immediate:true})
watch(()=>props.permissionRevision,()=>{requestRevision++;mutationRevision++;forceCloseActions();items.value=[];summary.value=null;hasLoaded.value=false;loading.value=false;if(props.active)void fetchCatalog()})
watch(()=>draft.q,q=>{if(searchTimer)clearTimeout(searchTimer);const normalized=q.trim();if(normalized===applied.q||normalized.length===1)return;searchTimer=setTimeout(()=>{applied.q=normalized;page.value=1;void fetchCatalog()},325)})
watch(()=>props.canCreate,value=>{if(!value&&drawerMode.value==='create'){mutationRevision++;forceCloseActions()}});watch(()=>props.canUpdate,value=>{if(!value&&drawerMode.value==='edit'){mutationRevision++;forceCloseActions()}});watch(()=>props.canDisable,value=>{if(!value&&confirmOpen.value){mutationRevision++;forceCloseActions()}});watch(canMerge,value=>{if(!value&&mergeOpen.value){mutationRevision++;forceCloseActions()}})
onUnmounted(()=>{requestRevision++;mutationRevision++;if(searchTimer)clearTimeout(searchTimer)})
function endpoint(){return `/admin/works/taxonomy/${props.entityType==='category'?'categories':'tags'}`}
async function fetchCatalog(){if(!props.active)return;const current=++requestRevision;loading.value=true;error.value=null;try{const query:Record<string,string|number>={state:applied.state,sort:applied.sort,direction:applied.direction,page:page.value,per_page:applied.per_page};if(applied.q.trim())query.q=applied.q.trim();const response=await apiFetch<CatalogResponse>(endpoint(),{query});if(current!==requestRevision)return;if(!response.success||!response.data)throw new Error('invalid');items.value=response.data.items;summary.value=response.data.summary;Object.assign(pagination,response.data.pagination);page.value=response.data.pagination.current_page;hasLoaded.value=true}catch(requestError:unknown){if(current!==requestRevision)return;const status=errorStatus(requestError);if(status===401||status===403){items.value=[];summary.value=null;hasLoaded.value=false;mutationRevision++;forceCloseActions();emit('authorizationError',props.entityType);error.value=status===403?(props.locale==='ar'?'لم تعد صلاحية عرض هذا الكتالوج متاحة.':'Catalog permission is no longer available.'):text.value.generic}else if(status===422)error.value=serverMessage(requestError)||text.value.generic;else error.value=serverMessage(requestError)||text.value.generic}finally{if(current===requestRevision)loading.value=false}}
function applyFilters(){if(searchTimer)clearTimeout(searchTimer);Object.assign(applied,draft);page.value=1;void fetchCatalog()}function resetFilters(){if(searchTimer)clearTimeout(searchTimer);Object.assign(draft,defaults());Object.assign(applied,defaults());page.value=1;void fetchCatalog()}function changePage(next:number){if(loading.value||next<1||next>pagination.last_page||next===pagination.current_page)return;page.value=next;void fetchCatalog()}
function openCreate(){if(!props.canCreate)return;selected.value=null;drawerMode.value='create';mutationError.value=null;fieldErrors.value={};drawerOpen.value=true}function openEdit(item:Item){if(!props.canUpdate)return;selected.value=item;drawerMode.value='edit';mutationError.value=null;fieldErrors.value={};drawerOpen.value=true}function closeDrawer(){if(mutationLoading.value)return;drawerOpen.value=false;selected.value=null;mutationError.value=null;fieldErrors.value={}}function openDisable(item:Item){if(!props.canDisable||!item.is_active)return;selected.value=item;mutationError.value=null;confirmOpen.value=true}function closeConfirm(){if(mutationLoading.value)return;confirmOpen.value=false;selected.value=null;mutationError.value=null}
function forceCloseActions(){drawerOpen.value=false;confirmOpen.value=false;mergeOpen.value=false;selected.value=null;mutationLoading.value=false;mutationError.value=null;fieldErrors.value={}}
async function submitEntity(payload:{name_ar:string;name_en:string;slug?:string;sort_order:number}){if(mutationLoading.value)return;const creating=drawerMode.value==='create';if((creating&&!props.canCreate)||(!creating&&!props.canUpdate))return;const current=++mutationRevision;mutationLoading.value=true;mutationError.value=null;fieldErrors.value={};try{const body=creating?payload:{name_ar:payload.name_ar,name_en:payload.name_en,sort_order:payload.sort_order};const response=await apiFetch<MutationResponse>(creating?endpoint():`${endpoint()}/${selected.value?.id}`,{method:creating?'POST':'PATCH',body});if(current!==mutationRevision)return;liveMessage.value=response.message||'';drawerOpen.value=false;selected.value=null;await refreshAfterMutation()}catch(requestError:unknown){if(current!==mutationRevision)return;const status=errorStatus(requestError);if(status===422){fieldErrors.value=serverErrors(requestError);mutationError.value=serverMessage(requestError)}else if(status===401||status===403){forceCloseActions();emit('authorizationError',props.entityType)}else mutationError.value=serverMessage(requestError)||text.value.mutationGeneric}finally{if(current===mutationRevision)mutationLoading.value=false}}
async function disableEntity(){if(mutationLoading.value||!props.canDisable||!selected.value)return;const current=++mutationRevision;mutationLoading.value=true;mutationError.value=null;try{const response=await apiFetch<MutationResponse>(`${endpoint()}/${selected.value.id}/disable`,{method:'PATCH'});if(current!==mutationRevision)return;liveMessage.value=response.message||'';confirmOpen.value=false;selected.value=null;await refreshAfterMutation()}catch(requestError:unknown){if(current!==mutationRevision)return;const status=errorStatus(requestError);if(status===401||status===403){forceCloseActions();emit('authorizationError',props.entityType)}else mutationError.value=serverMessage(requestError)||text.value.mutationGeneric}finally{if(current===mutationRevision)mutationLoading.value=false}}
async function refreshAfterMutation(){await fetchCatalog();emit('changed')}
function afterMerge(message:string){liveMessage.value=message;void refreshAfterMutation()}
function handleMergeAuthorizationError(){mergeOpen.value=false;items.value=[];summary.value=null;hasLoaded.value=false;emit('authorizationError',props.entityType)}
function number(value:number){return formatYmNumber(Number.isFinite(value)?value:0,props.locale)}
function safeText(value:string|number){return toLatinDigits(value)}
function sortLabel(value:Sort){const labels:Record<Sort,{ar:string;en:string}>={sort_order:{ar:'ترتيب العرض',en:'Sort order'},name_ar:{ar:'الاسم العربي',en:'Arabic name'},name_en:{ar:'الاسم الإنجليزي',en:'English name'},slug:{ar:'المعرّف النصي',en:'Text identifier'},works_count:{ar:'عدد الأعمال',en:'Works count'},created_at:{ar:'تاريخ الإنشاء',en:'Created at'},updated_at:{ar:'تاريخ التحديث',en:'Updated at'}};return labels[value][props.locale]}
function errorStatus(error:unknown):number|null{if(!error||typeof error!=='object')return null;const e=error as {status?:number;statusCode?:number;response?:{status?:number}};return e.response?.status??e.statusCode??e.status??null}function errorData(error:unknown):Record<string,unknown>|null{if(!error||typeof error!=='object')return null;const e=error as {data?:unknown;response?:{_data?:unknown}};const data=e.data??e.response?._data;return data&&typeof data==='object'?data as Record<string,unknown>:null}function serverMessage(error:unknown):string|null{const message=errorData(error)?.message;return typeof message==='string'?message:null}function serverErrors(error:unknown):Record<string,string[]>{const errors=errorData(error)?.errors;return errors&&typeof errors==='object'?errors as Record<string,string[]>:{}}
</script>

<style scoped>
.ym-catalog-panel{display:grid;gap:10px;color:var(--ym-text)}
.ym-catalog-heading,.ym-catalog-filters,.ym-catalog-table-card{border:1px solid var(--ym-card-border);border-radius:18px;padding:11px 12px;background:var(--ym-card-bg);box-shadow:var(--ym-card-shadow)}
.ym-catalog-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem}.ym-catalog-heading span{color:#8b5cf6;font-size:11px;font-weight:950}.ym-catalog-heading h2{margin:.25rem 0;font-size:1.25rem}.ym-catalog-heading p{margin:0;color:var(--ym-muted);font-size:12.5px}.ym-catalog-heading__actions{display:flex;align-items:center;gap:.6rem}.ym-catalog-heading__actions button{min-height:40px}
.ym-catalog-live{min-height:0;margin:0;color:#10b981;font-size:13px;font-weight:900}
.ym-catalog-summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.ym-catalog-summary article{border:1px solid var(--ym-soft-border);border-radius:14px;padding:9px 11px;background:var(--ym-card-bg)}.ym-catalog-summary span{display:block;color:var(--ym-muted);font-size:11px;font-weight:850}.ym-catalog-summary strong{display:block;margin-top:.3rem;font-size:1.3rem}
.ym-catalog-filters{display:grid;grid-template-columns:minmax(260px,2fr) minmax(150px,1fr) auto;align-items:end;gap:8px}.ym-catalog-filters label{display:grid;gap:.35rem}.ym-catalog-filters label>span{color:var(--ym-muted);font-size:11px;font-weight:900}.ym-catalog-filters input,.ym-catalog-filters select{width:100%;min-height:43px;border:1px solid var(--ym-control-border);border-radius:13px;outline:none;padding:.65rem;color:var(--ym-text);background:var(--ym-control-bg)}.ym-catalog-filter-toolbar{display:flex;align-items:end;gap:6px}.ym-catalog-filter-toolbar .is-open{border-color:#0891b2;box-shadow:inset 0 -2px rgba(8,145,178,.35)}.ym-catalog-advanced{grid-column:1/-1;display:grid;grid-template-columns:repeat(3,minmax(140px,1fr));gap:8px;border-top:1px solid var(--ym-soft-border);padding-top:9px}
.ym-catalog-table-card{min-width:0;padding:0;overflow:hidden}.ym-catalog-state{display:grid;min-height:220px;place-items:center;align-content:center;text-align:center}.ym-catalog-state p{color:var(--ym-muted)}.ym-catalog-state.is-error{color:#fb7185}.ym-catalog-spinner{width:30px;height:30px;border:3px solid var(--ym-soft-border);border-top-color:#8b5cf6;border-radius:50%;animation:spin .75s linear infinite}
button{min-height:40px;border-radius:12px;padding:.55rem .8rem;font-weight:900}.is-primary{border:1px solid #7c3aed;color:#fff;background:#7c3aed}.is-secondary{border:1px solid var(--ym-control-border);color:var(--ym-text);background:var(--ym-control-bg)}button:focus-visible,input:focus,select:focus{box-shadow:0 0 0 3px rgba(139,92,246,.18)}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:1050px){.ym-catalog-summary{grid-template-columns:repeat(3,1fr)}.ym-catalog-filters{grid-template-columns:2fr 1fr}.ym-catalog-filter-toolbar,.ym-catalog-advanced{grid-column:1/-1}}
@media(max-width:650px){.ym-catalog-heading{display:grid}.ym-catalog-heading__actions{flex-wrap:wrap}.ym-catalog-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.ym-catalog-summary article:last-child:nth-child(odd){grid-column:1/-1}.ym-catalog-filters,.ym-catalog-advanced{grid-template-columns:1fr}.ym-catalog-filter-toolbar{align-items:stretch;flex-wrap:wrap}.ym-catalog-filter-toolbar button{flex:1}}
</style>
