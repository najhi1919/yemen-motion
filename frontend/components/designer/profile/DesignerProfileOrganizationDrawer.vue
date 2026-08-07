<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type {
  DesignerProfileOrganization,
  DesignerProfileOrganizationInput,
  DesignerProfileOrganizationType,
} from '~/types/designer-profile-organization'

const props = defineProps<{
  open: boolean
  organization: DesignerProfileOrganization | null
  saving: boolean
  error: string | null
  validationErrors: Readonly<Record<string, readonly string[]>>
  logoUrl: string | null
  /** رسالة النجاح الجزئي (PUT نجح + Logo فشل) — تختلف بصرياً عن error */
  notice: string | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  /** يُرسَل Input فقط — بدون expected_updated_at */
  (e: 'save', input: DesignerProfileOrganizationInput, logoAction: { type: 'upload' | 'delete' | 'none', file?: File }): void
  (e: 'request-delete'): void
}>()

const attemptToClose = ref(false)
const localLogoPreview = ref<string | null>(null)
const localLogoFile = ref<File | null>(null)
const localLogoDelete = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)
const localLogoError = ref<string | null>(null)

const form = ref({
  organization_name: '',
  organization_type: 'studio' as DesignerProfileOrganizationType,
  description: '',
  website_url: '',
  show_publicly: false,
})

const isDirty = computed(() => {
  if (!props.organization) {
    // منشأة جديدة: أي حقل يختلف عن Default يُعدّ dirty
    return !!(
      form.value.organization_name
      || form.value.description
      || form.value.website_url
      || form.value.organization_type !== 'studio'
      || form.value.show_publicly !== false
      || localLogoFile.value
    )
  }

  return (
    form.value.organization_name !== props.organization.name
    || form.value.organization_type !== props.organization.type
    || (form.value.description || '') !== (props.organization.description || '')
    || (form.value.website_url || '') !== (props.organization.website_url || '')
    || form.value.show_publicly !== props.organization.show_publicly
    || localLogoFile.value !== null
    || (localLogoDelete.value && props.organization.has_logo)
  )
})

// يُعاد التهيئة فقط عند فتح الـDrawer — لا يوجد watch على props.organization
// حتى لا تضيع تعديلات المستخدم عند resync بعد 409
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    attemptToClose.value = false
    localLogoFile.value = null
    localLogoPreview.value = null
    localLogoDelete.value = false
    localLogoError.value = null

    if (props.organization) {
      form.value = {
        organization_name: props.organization.name,
        organization_type: props.organization.type,
        description: props.organization.description || '',
        website_url: props.organization.website_url || '',
        show_publicly: props.organization.show_publicly,
      }
    } else {
      form.value = {
        organization_name: '',
        organization_type: 'studio',
        description: '',
        website_url: '',
        show_publicly: false,
      }
    }
  }
})

function requestClose() {
  if (props.saving) return
  if (isDirty.value && !attemptToClose.value) {
    attemptToClose.value = true
    return
  }
  emit('close')
}

function handleFileChange(event: Event) {
  localLogoError.value = null
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    localLogoError.value = 'حجم الشعار يجب ألا يتجاوز 2 ميجابايت'
    input.value = ''
    return
  }

  const validTypes = ['image/jpeg', 'image/png', 'image/webp']
  if (!validTypes.includes(file.type)) {
    localLogoError.value = 'صيغة الملف غير مدعومة'
    input.value = ''
    return
  }

  localLogoFile.value = file
  localLogoDelete.value = false

  const reader = new FileReader()
  reader.onload = (e) => {
    localLogoPreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)

  input.value = ''
}

function triggerFileInput() {
  fileInput.value?.click()
}

function removeLogoIntent() {
  localLogoFile.value = null
  localLogoPreview.value = null
  localLogoDelete.value = true
  localLogoError.value = null
}

function handleSubmit() {
  // Input بدون expected_updated_at — Page تضيفه
  const input: DesignerProfileOrganizationInput = {
    organization_name: form.value.organization_name,
    organization_type: form.value.organization_type,
    description: form.value.description || null,
    website_url: form.value.website_url || null,
    show_publicly: form.value.show_publicly,
  }

  let logoAction: { type: 'upload' | 'delete' | 'none', file?: File } = { type: 'none' }
  if (localLogoFile.value) {
    logoAction = { type: 'upload', file: localLogoFile.value }
  } else if (localLogoDelete.value && props.organization?.has_logo) {
    logoAction = { type: 'delete' }
  }

  emit('save', input, logoAction)
  // ملاحظة: localLogoFile وlocalLogoPreview لا يُمسحان هنا
  // عند partial failure (PUT نجح + Logo فشل) يبقى المستخدم قادراً على إعادة المحاولة
}
</script>

<template>
  <Teleport to="body">
    <!-- Backdrop -->
    <div
      v-if="open"
      class="fixed inset-0 z-50 bg-neutral-900/40 backdrop-blur-sm"
      aria-hidden="true"
      @click="requestClose"
    />

    <!-- Drawer -->
    <aside
      v-if="open"
      class="fixed inset-y-0 left-0 z-[60] isolate flex h-dvh min-h-dvh max-h-dvh w-full max-w-2xl flex-col overflow-hidden bg-white shadow-2xl transition-transform"
      role="dialog"
      aria-modal="true"
      aria-labelledby="organization-drawer-title"
      dir="rtl"
      style="
        --ym-d-red: #E21D1D;
        --ym-d-red-strong: #C91414;
        --ym-d-focus: rgba(226, 29, 29, 0.24);
        --ym-d-red-border: rgba(226, 29, 29, 0.22);
        --ym-d-surface: #FFFFFF;
        --ym-d-surface-muted: #F7F6F5;
        --ym-d-text: #151515;
        --ym-d-muted: #666666;
        --ym-d-border: rgba(17, 17, 17, 0.10);
      "
    >
      <div class="flex h-16 shrink-0 items-center justify-between border-b border-neutral-100 px-6">
        <h2 id="organization-drawer-title" class="text-lg font-extrabold text-[#151515]">
          تعديل بيانات المنشأة
        </h2>
        <button
          type="button"
          class="flex h-10 w-10 items-center justify-center rounded-full text-neutral-400 hover:bg-neutral-100 hover:text-neutral-600 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
          :disabled="saving"
          @click="requestClose"
        >
          <span class="sr-only">إغلاق</span>
          <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-8">
        <!-- خطأ API -->
        <div v-if="error" class="mb-6 rounded-2xl border border-[var(--ym-d-red-border)] bg-[var(--ym-d-surface)] p-5" role="alert">
          <p class="font-bold text-[var(--ym-d-red)]">{{ error }}</p>
        </div>

        <!-- نجاح جزئي (PUT نجح + Logo فشل) — بصرياً مختلف عن error -->
        <div
          v-if="notice"
          class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5"
          role="status"
        >
          <p class="font-bold text-amber-800">{{ notice }}</p>
        </div>

        <form id="organization-form" class="space-y-8" @submit.prevent="handleSubmit">
          <!-- Logo Section -->
          <fieldset class="rounded-2xl border border-neutral-200 p-6">
            <legend class="px-2 text-sm font-bold text-neutral-700">شعار المنشأة</legend>
            <div class="mt-4 flex items-center gap-6">
              <div class="relative shrink-0">
                <template v-if="localLogoPreview">
                  <img :src="localLogoPreview" alt="معاينة الشعار" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-black/10">
                </template>
                <template v-else-if="props.logoUrl && !localLogoDelete">
                  <img :src="props.logoUrl" alt="شعار المنشأة الحالي" class="h-20 w-20 rounded-2xl object-cover ring-1 ring-black/10">
                </template>
                <template v-else>
                  <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-neutral-50 ring-1 ring-black/5">
                    <svg class="h-8 w-8 text-neutral-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                  </div>
                </template>
              </div>

              <div>
                <div class="flex items-center gap-3">
                  <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl bg-neutral-100 px-5 text-sm font-bold text-neutral-700 hover:bg-neutral-200 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)]"
                    @click="triggerFileInput"
                  >
                    تغيير الشعار
                  </button>
                  <button
                    v-if="(props.organization?.has_logo && !localLogoDelete) || localLogoFile"
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl px-5 text-sm font-bold text-red-600 hover:bg-red-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/30"
                    @click="removeLogoIntent"
                  >
                    إزالة
                  </button>
                </div>
                <input
                  ref="fileInput"
                  type="file"
                  class="hidden"
                  accept="image/jpeg,image/png,image/webp"
                  @change="handleFileChange"
                >
                <p class="mt-2 text-xs text-neutral-500">JPG, PNG أو WEBP. الحد الأقصى 2 ميجابايت.</p>
                <p v-if="localLogoError" class="mt-1 text-xs font-bold text-[var(--ym-d-red)]">{{ localLogoError }}</p>
              </div>
            </div>
          </fieldset>

          <div>
            <label for="org_name" class="block text-sm font-bold text-neutral-700">اسم المنشأة</label>
            <input
              id="org_name"
              v-model="form.organization_name"
              type="text"
              class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-[#151515] focus:border-[var(--ym-d-focus)] focus:ring-1 focus:ring-[var(--ym-d-focus)] sm:text-sm"
              :class="{ 'border-red-500': validationErrors.organization_name }"
            >
            <p v-if="validationErrors.organization_name" class="mt-1.5 text-sm text-[var(--ym-d-red)]">
              {{ validationErrors.organization_name[0] }}
            </p>
          </div>

          <div>
            <label for="org_type" class="block text-sm font-bold text-neutral-700">النوع</label>
            <select
              id="org_type"
              v-model="form.organization_type"
              class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-[#151515] focus:border-[var(--ym-d-focus)] focus:ring-1 focus:ring-[var(--ym-d-focus)] sm:text-sm"
              :class="{ 'border-red-500': validationErrors.organization_type }"
            >
              <option value="studio">استوديو</option>
              <option value="agency">وكالة</option>
              <option value="company">شركة</option>
              <option value="brand">علامة تجارية</option>
              <option value="other">أخرى</option>
            </select>
            <p v-if="validationErrors.organization_type" class="mt-1.5 text-sm text-[var(--ym-d-red)]">
              {{ validationErrors.organization_type[0] }}
            </p>
          </div>

          <div>
            <label for="org_description" class="block text-sm font-bold text-neutral-700">الوصف</label>
            <textarea
              id="org_description"
              v-model="form.description"
              rows="4"
              maxlength="1000"
              class="mt-2 block w-full resize-y rounded-xl border border-neutral-300 bg-white px-4 py-3 text-[#151515] focus:border-[var(--ym-d-focus)] focus:ring-1 focus:ring-[var(--ym-d-focus)] sm:text-sm"
              :class="{ 'border-red-500': validationErrors.description }"
            />
            <p class="mt-2 text-xs text-neutral-500">{{ form.description?.length || 0 }} / 1000</p>
            <p v-if="validationErrors.description" class="mt-1.5 text-sm text-[var(--ym-d-red)]">
              {{ validationErrors.description[0] }}
            </p>
          </div>

          <div>
            <label for="org_website" class="block text-sm font-bold text-neutral-700">الموقع الإلكتروني</label>
            <input
              id="org_website"
              v-model="form.website_url"
              type="url"
              placeholder="https://"
              class="mt-2 block w-full rounded-xl border border-neutral-300 bg-white px-4 py-3 text-[#151515] text-left focus:border-[var(--ym-d-focus)] focus:ring-1 focus:ring-[var(--ym-d-focus)] sm:text-sm"
              :class="{ 'border-red-500': validationErrors.website_url }"
              dir="ltr"
            >
            <p v-if="validationErrors.website_url" class="mt-1.5 text-sm text-[var(--ym-d-red)] text-right">
              {{ validationErrors.website_url[0] }}
            </p>
          </div>

          <div class="flex items-center gap-3 rounded-2xl bg-neutral-50 p-5">
            <input
              id="org_show"
              v-model="form.show_publicly"
              type="checkbox"
              class="h-5 w-5 rounded border-neutral-300 text-[var(--ym-d-focus)] focus:ring-[var(--ym-d-focus)]"
            >
            <label for="org_show" class="font-bold text-neutral-700">
              إظهار في ملفي العام
            </label>
          </div>
        </form>

        <div v-if="props.organization" class="mt-12 border-t border-red-100 pt-8">
          <h3 class="text-sm font-bold text-red-700">إدارة المنشأة</h3>
          <p class="mt-1 text-sm text-neutral-500">حذف هذه المنشأة سيؤدي إلى مسح كافة بياناتها وشعارها نهائياً.</p>
          <button
            type="button"
            class="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-red-50 px-5 text-sm font-bold text-red-700 transition-colors hover:bg-red-100 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-500/30"
            :disabled="saving"
            @click="emit('request-delete')"
          >
            حذف المنشأة
          </button>
        </div>
      </div>

      <div class="relative z-10 shrink-0 border-t border-neutral-100 bg-white p-6">
        <div v-if="attemptToClose" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-800">
          لديك تعديلات غير محفوظة. هل أنت متأكد من الإغلاق؟
          <div class="mt-3 flex gap-3">
            <button
              type="button"
              class="rounded-lg bg-amber-100 px-4 py-2 hover:bg-amber-200"
              @click="emit('close')"
            >
              تجاهل التعديلات وإغلاق
            </button>
            <button
              type="button"
              class="rounded-lg bg-white px-4 py-2 hover:bg-neutral-50"
              @click="attemptToClose = false"
            >
              تراجع
            </button>
          </div>
        </div>

        <button
          type="submit"
          form="organization-form"
          class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[var(--ym-d-red)] px-6 font-bold text-white transition-colors hover:bg-[var(--ym-d-red-strong)] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[var(--ym-d-focus)] disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="saving"
        >
          <svg v-if="saving" class="mr-2 h-5 w-5 animate-spin rtl:ml-2 rtl:mr-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25" />
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round" />
          </svg>
          {{ saving ? 'جاري الحفظ...' : 'حفظ' }}
        </button>
      </div>
    </aside>
  </Teleport>
</template>
