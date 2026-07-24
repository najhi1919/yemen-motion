<template>
  <section
    class="ym-media-upload"
    :class="{ 'is-dragging': dragActive, 'is-disabled': disabled, 'has-file': selectedFile }"
    :aria-busy="uploading"
  >
    <label
      class="ym-media-upload__drop"
      :for="inputId"
      @dragenter.prevent="setDrag(true)"
      @dragover.prevent="setDrag(true)"
      @dragleave.prevent="setDrag(false)"
      @drop.prevent="onDrop"
    >
      <input
        :id="inputId"
        ref="fileInput"
        type="file"
        name="file"
        :accept="accept"
        :disabled="disabled || uploading"
        :aria-invalid="Boolean(error)"
        :aria-describedby="`${inputId}-help ${inputId}-status`"
        @change="onInput"
      />
      <span class="ym-media-upload__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24">
          <path d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M5 14v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4" />
        </svg>
      </span>
      <span class="ym-media-upload__content">
          <strong :dir="selectedFile ? 'auto' : undefined" :title="selectedFile?.name">
          {{ selectedFile ? selectedFile.name : copy.drop }}
        </strong>
        <span>{{ selectedFile ? selectedMeta : copy.choose }}</span>
      </span>
      <span class="ym-media-upload__browse">{{ copy.browse }}</span>
    </label>

    <div :id="`${inputId}-help`" class="ym-media-upload__rules">
      <span>{{ copy.single }}</span>
      <span>{{ acceptedLabel }}</span>
      <span v-if="maxSizeLabel">{{ copy.max }} {{ maxSizeLabel }}</span>
      <span>{{ remainingLabel }}</span>
    </div>

    <p
      v-if="disabledReason || error"
      :id="`${inputId}-status`"
      class="ym-media-upload__status"
      :class="{ 'is-error': error }"
      :role="error ? 'alert' : 'note'"
    >
      {{ error || disabledReason }}
    </p>
    <p v-else :id="`${inputId}-status`" class="ym-media-upload__status" role="status">
      {{ actionReason || (uploading ? copy.uploading : selectedFile ? copy.selected : copy.ready) }}
    </p>

    <button
      class="ym-media-upload__submit"
      type="button"
      :disabled="!canUpload"
      @click="$emit('upload')"
    >
      <span v-if="uploading" class="ym-media-upload__spinner" aria-hidden="true" />
      {{ uploading ? copy.uploading : copy.upload }}
    </button>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { formatYmNumber } from '~/utils/ymFormatting'

const props = defineProps<{
  locale: 'ar' | 'en'
  accept: string
  acceptedLabel: string
  maxSizeLabel: string
  remaining: number | null
  selectedFile: File | null
  uploading: boolean
  disabled: boolean
  canUpload: boolean
  disabledReason: string
  actionReason: string
  error: string
}>()

const emit = defineEmits<{
  select: [file: File | null]
  upload: []
}>()

const inputId = 'ym-media-upload-input'
const fileInput = ref<HTMLInputElement | null>(null)
const dragActive = ref(false)

const copy = computed(() => props.locale === 'ar' ? {
  drop: 'اسحب الملف هنا',
  choose: 'أواختر ملفًا واحدًا من جهازك',
  browse: 'اختيار ملف',
  single: 'ملف واحد لكل طلب',
  max: 'الحجم الأقصى:',
  ready: 'منطقة الرفع جاهزة.',
  selected: 'الملف جاهز للرفع.',
  uploading: 'جارٍ رفع الوسيط…',
  upload: 'رفع الوسيط'
} : {
  drop: 'Drop the file here',
  choose: 'or choose one file from your device',
  browse: 'Choose file',
  single: 'One file per request',
  max: 'Maximum size:',
  ready: 'Upload area is ready.',
  selected: 'The file is ready to upload.',
  uploading: 'Uploading media…',
  upload: 'Upload media'
})

const selectedMeta = computed(() => {
  if (!props.selectedFile) return ''
  return `${props.selectedFile.type || '—'} · ${formatSize(props.selectedFile.size)}`
})

const remainingLabel = computed(() => props.remaining === null
  ? (props.locale === 'ar' ? 'السعة المتبقية: دون حد' : 'Remaining capacity: unlimited')
  : props.locale === 'ar'
    ? `الأماكن المتبقية: ${formatYmNumber(props.remaining, props.locale)}`
    : `Remaining slots: ${formatYmNumber(props.remaining, props.locale)}`
)

function onInput(event: Event) {
  const input = event.target as HTMLInputElement
  emit('select', input.files?.[0] ?? null)
}

function setDrag(active: boolean) {
  if (!props.disabled && !props.uploading) dragActive.value = active
}

function onDrop(event: DragEvent) {
  dragActive.value = false
  if (props.disabled || props.uploading) return
  emit('select', event.dataTransfer?.files?.[0] ?? null)
}

function formatSize(bytes: number): string {
  if (bytes < 1024 * 1024) {
    return `${formatYmNumber(Math.max(1, Math.round(bytes / 1024)), props.locale)} KB`
  }
  if (bytes < 1024 * 1024 * 1024) {
    return `${formatYmNumber(bytes / (1024 * 1024), props.locale, { maximumFractionDigits: 1 })} MB`
  }
  return `${formatYmNumber(bytes / (1024 * 1024 * 1024), props.locale, { maximumFractionDigits: 2 })} GB`
}

function focusInput() {
  fileInput.value?.focus()
}

function openPicker() {
  fileInput.value?.click()
}

function reset() {
  if (fileInput.value) fileInput.value.value = ''
  dragActive.value = false
}

defineExpose({ focusInput, openPicker, reset })
</script>

<style scoped>
.ym-media-upload{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:16px;border:1px solid rgba(139,92,246,.28);border-radius:17px;background:linear-gradient(135deg,rgba(124,58,237,.08),rgba(34,211,238,.035));box-shadow:inset 0 1px rgba(255,255,255,.07);transition:border-color .18s ease,background .18s ease}.ym-media-upload.is-dragging{border-color:rgba(34,211,238,.75);background:rgba(34,211,238,.1)}.ym-media-upload__drop{position:relative;grid-column:1/-1;display:flex;align-items:center;gap:14px;min-height:112px;padding:18px;border:1px dashed rgba(139,92,246,.52);border-radius:14px;cursor:pointer;transition:transform .16s ease,border-color .16s ease,background .16s ease}.ym-media-upload__drop:hover{transform:translateY(-1px);border-color:#8b5cf6;background:rgba(124,58,237,.055)}.ym-media-upload__drop input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}.ym-media-upload__drop:focus-within{outline:3px solid rgba(34,211,238,.32);outline-offset:3px}.ym-media-upload__icon{display:grid;place-items:center;width:48px;height:48px;flex:0 0 auto;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#ec4899);color:white;box-shadow:0 10px 28px rgba(124,58,237,.25)}.ym-media-upload__icon svg{width:25px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}.ym-media-upload__content{display:grid;gap:4px;min-width:0;flex:1}.ym-media-upload__content strong{font-size:15px;overflow-wrap:anywhere}.ym-media-upload__content span,.ym-media-upload__rules,.ym-media-upload__status{font-size:12.75px;color:var(--ym-media-muted,#a7b2c7)}.ym-media-upload__browse,.ym-media-upload__submit{min-height:44px;border:1px solid rgba(139,92,246,.36);border-radius:12px;font-weight:800}.ym-media-upload__browse{display:grid;place-items:center;padding:0 16px;background:rgba(139,92,246,.12)}.ym-media-upload__rules{display:flex;align-items:center;flex-wrap:wrap;gap:6px 14px}.ym-media-upload__rules span{position:relative}.ym-media-upload__rules span+span::before{content:"";position:absolute;inset-inline-start:-8px;top:50%;width:3px;height:3px;border-radius:50%;background:#8b5cf6}.ym-media-upload__status{margin:0;align-self:center}.ym-media-upload__status.is-error{color:#fda4af}.ym-media-upload__submit{min-width:142px;padding:0 18px;background:linear-gradient(135deg,#7c3aed,#ec4899);color:white;box-shadow:0 9px 24px rgba(124,58,237,.22)}.ym-media-upload__submit:disabled{opacity:.48;cursor:not-allowed}.ym-media-upload__spinner{display:inline-block;width:15px;height:15px;margin-inline-end:7px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:ym-media-spin .8s linear infinite}.ym-media-upload.is-disabled .ym-media-upload__drop{cursor:not-allowed;opacity:.62}:global(.ym-media-manager.is-light) .ym-media-upload{background:linear-gradient(135deg,rgba(255,255,255,.78),rgba(237,233,254,.68));box-shadow:inset 0 1px #fff,0 12px 30px rgba(76,29,149,.08)}@keyframes ym-media-spin{to{transform:rotate(360deg)}}@media(max-width:640px){.ym-media-upload{grid-template-columns:1fr}.ym-media-upload__drop{align-items:flex-start;flex-wrap:wrap;min-height:142px}.ym-media-upload__browse{width:100%;min-height:44px}.ym-media-upload__submit{width:100%}}@media(prefers-reduced-motion:reduce){.ym-media-upload__drop{transition:none}.ym-media-upload__drop:hover{transform:none}.ym-media-upload__spinner{animation:none}}
.ym-media-upload__content strong[dir="auto"]{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;unicode-bidi:plaintext;text-align:start}
</style>
