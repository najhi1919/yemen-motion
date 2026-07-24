<template>
  <nav class="ym-authoring-nav" :aria-label="text.label">
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      :class="{ 'is-active': activeId === item.id }"
      :aria-current="activeId === item.id ? 'location' : undefined"
      @click="$emit('navigate', item.id)"
    >
      <span aria-hidden="true">{{ item.icon }}</span>{{ item.label }}
    </button>
  </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'
defineProps<{ items: Array<{ id: string; label: string; icon: string }>; activeId: string }>()
defineEmits<{ navigate: [id: string] }>()
const locale = useState<'ar' | 'en'>('ym-dashboard-locale', () => 'ar')
const text = computed(() => locale.value === 'ar' ? { label:'التنقل بين أقسام التأليف' } : { label:'Authoring section navigation' })
</script>

<style scoped>
.ym-authoring-nav{position:sticky;z-index:8;top:12px;box-sizing:border-box;display:flex;inline-size:100%;max-inline-size:100%;min-width:0;flex-wrap:wrap;gap:7px;border:1px solid color-mix(in srgb,var(--aw-electric) 28%,var(--aw-border));border-radius:16px;padding:8px;background:color-mix(in srgb,var(--aw-surface-strong) 94%,transparent);box-shadow:var(--aw-soft-shadow),inset 0 1px 0 var(--aw-highlight);backdrop-filter:blur(10px)}
button{display:inline-flex;min-height:42px;align-items:center;gap:7px;border:1px solid transparent;border-radius:11px;padding:0 13px;color:var(--aw-muted);background:transparent;font-size:13.5px;font-weight:800;cursor:pointer;transition:color .16s ease,background .16s ease,border-color .16s ease}
button span{color:var(--aw-electric)}button:hover{border-color:var(--aw-soft-border);color:var(--aw-text);background:var(--aw-control)}button.is-active{border-color:color-mix(in srgb,var(--aw-electric) 48%,transparent);color:var(--aw-text);background:linear-gradient(135deg,color-mix(in srgb,var(--aw-violet) 12%,var(--aw-control)),color-mix(in srgb,var(--aw-cyan) 6%,var(--aw-control)));box-shadow:inset 0 1px 0 var(--aw-highlight)}button:focus-visible{outline:3px solid color-mix(in srgb,var(--aw-electric) 42%,transparent);outline-offset:2px}
@media(max-width:640px){.ym-authoring-nav{position:relative;top:auto}button{flex:1 1 calc(50% - 7px);justify-content:center;min-height:44px}}
@media(prefers-reduced-motion:reduce){button{transition:none}}
</style>
