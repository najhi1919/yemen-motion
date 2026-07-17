<template>
  <Teleport to="body">
    <div v-if="open" class="ym-entity-overlay" role="presentation" @click.self="requestClose">
      <section
        ref="drawerRef"
        class="ym-entity-drawer"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :dir="locale === 'ar' ? 'rtl' : 'ltr'"
        tabindex="-1"
        @keydown.esc="requestClose"
      >
        <header>
          <div>
            <span>{{ mode === 'create' ? text.createEyebrow : text.editEyebrow }}</span>
            <h2 :id="titleId">{{ mode === 'create' ? text.createTitle : text.editTitle }}</h2>
          </div>
          <button type="button" :aria-label="text.close" :disabled="loading" @click="requestClose">×</button>
        </header>

        <form @submit.prevent="submit">
          <label>
            <span>{{ text.nameAr }}</span>
            <input v-model.trim="form.name_ar" type="text" minlength="2" maxlength="120" required />
            <small v-if="fieldErrors.name_ar" role="alert">{{ fieldErrors.name_ar[0] }}</small>
          </label>
          <label>
            <span>{{ text.nameEn }}</span>
            <input v-model.trim="form.name_en" type="text" minlength="2" maxlength="120" dir="ltr" required />
            <small v-if="fieldErrors.name_en" role="alert">{{ fieldErrors.name_en[0] }}</small>
          </label>
          <label v-if="mode === 'create'">
            <span>{{ text.slug }}</span>
            <input v-model.trim="form.slug" type="text" minlength="2" maxlength="160" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" dir="ltr" required autocomplete="off" />
            <em>{{ text.slugHint }}</em>
            <small v-if="fieldErrors.slug" role="alert">{{ fieldErrors.slug[0] }}</small>
          </label>
          <div v-else class="ym-entity-readonly">
            <span>{{ text.slug }}</span>
            <code dir="ltr">{{ entity?.slug }}</code>
          </div>
          <label>
            <span>{{ text.sortOrder }}</span>
            <input v-model.number="form.sort_order" type="number" min="0" max="2147483647" step="1" dir="ltr" required />
            <small v-if="fieldErrors.sort_order" role="alert">{{ fieldErrors.sort_order[0] }}</small>
          </label>

          <dl v-if="mode === 'edit' && entity" class="ym-entity-context">
            <div><dt>{{ text.state }}</dt><dd>{{ entity.is_active ? text.active : text.disabled }}</dd></div>
            <div><dt>{{ text.worksCount }}</dt><dd>{{ formatNumber(entity.works_count) }}</dd></div>
          </dl>

          <p v-if="error" class="ym-entity-error" role="alert">{{ error }}</p>
          <footer>
            <button type="button" class="is-secondary" :disabled="loading" @click="requestClose">{{ text.cancel }}</button>
            <button type="submit" class="is-primary" :disabled="loading">
              <span v-if="loading" class="ym-mini-spinner" aria-hidden="true" />
              {{ loading ? text.saving : text.save }}
            </button>
          </footer>
        </form>
      </section>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue'

interface CatalogEntity {
  id: number
  name_ar: string
  name_en: string
  slug: string
  is_active: boolean
  sort_order: number
  works_count: number
}

const props = defineProps<{
  open: boolean
  mode: 'create' | 'edit'
  entity: CatalogEntity | null
  entityType: 'category' | 'tag'
  locale: 'ar' | 'en'
  loading: boolean
  error: string | null
  fieldErrors: Record<string, string[]>
}>()

const emit = defineEmits<{
  close: []
  submit: [payload: { name_ar: string; name_en: string; slug?: string; sort_order: number }]
}>()

const drawerRef = ref<HTMLElement | null>(null)
const titleId = `ym-taxonomy-${props.entityType}-drawer-title`
const form = reactive({ name_ar: '', name_en: '', slug: '', sort_order: 0 })

const copies = {
  ar: {
    createEyebrow: 'سجل جديد', editEyebrow: 'تعديل آمن', createTitle: props.entityType === 'category' ? 'إنشاء تصنيف' : 'إنشاء وسم', editTitle: props.entityType === 'category' ? 'تعديل التصنيف' : 'تعديل الوسم',
    close: 'إغلاق', nameAr: 'الاسم العربي', nameEn: 'الاسم الإنجليزي', slug: 'slug', slugHint: 'ثابت بعد الإنشاء ولا يُولّد تلقائيًا.', sortOrder: 'ترتيب العرض', state: 'الحالة الحالية', worksCount: 'عدد الأعمال', active: 'فعال', disabled: 'معطل', cancel: 'إلغاء', save: 'حفظ', saving: 'جارٍ الحفظ'
  },
  en: {
    createEyebrow: 'New record', editEyebrow: 'Safe update', createTitle: props.entityType === 'category' ? 'Create category' : 'Create tag', editTitle: props.entityType === 'category' ? 'Edit category' : 'Edit tag',
    close: 'Close', nameAr: 'Arabic name', nameEn: 'English name', slug: 'Slug', slugHint: 'Immutable after creation and never generated automatically.', sortOrder: 'Sort order', state: 'Current state', worksCount: 'Works count', active: 'Active', disabled: 'Disabled', cancel: 'Cancel', save: 'Save', saving: 'Saving'
  }
}
const text = computed(() => copies[props.locale])

watch(() => props.open, async (open) => {
  if (!open) return
  form.name_ar = props.entity?.name_ar ?? ''
  form.name_en = props.entity?.name_en ?? ''
  form.slug = ''
  form.sort_order = props.entity?.sort_order ?? 0
  await nextTick()
  drawerRef.value?.focus()
})

function requestClose() { if (!props.loading) emit('close') }
function submit() {
  if (props.loading) return
  const payload: { name_ar: string; name_en: string; slug?: string; sort_order: number } = {
    name_ar: form.name_ar,
    name_en: form.name_en,
    sort_order: Number(form.sort_order) || 0
  }
  if (props.mode === 'create') payload.slug = form.slug
  emit('submit', payload)
}
function formatNumber(value: number) { return new Intl.NumberFormat(props.locale === 'ar' ? 'ar-YE' : 'en-US').format(value) }
</script>

<style scoped>
.ym-entity-overlay{position:fixed;inset:0;z-index:90;display:flex;justify-content:flex-end;background:rgba(2,6,23,.62)}
.ym-entity-drawer{width:min(100%,520px);height:100%;overflow:auto;outline:none;background:var(--ym-card-bg);color:var(--ym-text);box-shadow:-18px 0 50px rgba(2,6,23,.25);padding:1.25rem}
.ym-entity-drawer>header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--ym-soft-border);padding-bottom:1rem}.ym-entity-drawer header span{color:#8b5cf6;font-size:11px;font-weight:900}.ym-entity-drawer h2{font-size:1.5rem;margin:.25rem 0 0}.ym-entity-drawer header button{width:40px;height:40px;border:1px solid var(--ym-control-border);border-radius:12px;background:var(--ym-control-bg);color:var(--ym-text);font-size:1.4rem}
form{display:grid;gap:1rem;padding-top:1.2rem}label{display:grid;gap:.4rem}label>span,.ym-entity-readonly>span{color:var(--ym-muted);font-size:12px;font-weight:900}input{min-height:46px;border:1px solid var(--ym-control-border);border-radius:14px;outline:none;background:var(--ym-control-bg);color:var(--ym-text);padding:.75rem}input:focus,button:focus-visible{border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,.18)}label em{color:var(--ym-muted);font-size:11px;font-style:normal}label small,.ym-entity-error{color:#fb7185;font-size:12px;font-weight:800}.ym-entity-readonly{display:grid;gap:.4rem}.ym-entity-readonly code{border:1px solid var(--ym-soft-border);border-radius:12px;background:var(--ym-control-bg);padding:.8rem}.ym-entity-context{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin:0}.ym-entity-context div{border:1px solid var(--ym-soft-border);border-radius:14px;padding:.8rem}.ym-entity-context dt{color:var(--ym-muted);font-size:11px}.ym-entity-context dd{font-weight:900;margin:.25rem 0 0}footer{display:flex;justify-content:flex-end;gap:.7rem;border-top:1px solid var(--ym-soft-border);padding-top:1rem}footer button{min-height:43px;border-radius:13px;padding:.65rem 1rem;font-weight:900}.is-secondary{border:1px solid var(--ym-control-border);background:var(--ym-control-bg);color:var(--ym-text)}.is-primary{border:1px solid #7c3aed;background:#7c3aed;color:#fff}.ym-mini-spinner{display:inline-block;width:13px;height:13px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
</style>
