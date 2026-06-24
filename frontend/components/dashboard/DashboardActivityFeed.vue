<template>
  <section class="ym-activity-card">
    <div class="ym-activity-card__head">
      <h3>{{ title }}</h3>
      <slot name="actions" />
    </div>
    <div class="space-y-2">
      <div v-if="items.length === 0" class="ym-activity-empty">
        {{ emptyLabel }}
      </div>
      <article v-for="(item, i) in items" :key="i" class="ym-activity-item">
        <span class="ym-activity-icon" :class="toneClass(item.type)">{{ item.icon || '•' }}</span>
        <span class="min-w-0 flex-1">
          <strong>{{ item.title }}</strong>
          <small>{{ item.description }}</small>
        </span>
        <em>{{ item.time }}</em>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
export interface ActivityItem {
  icon?: string
  title: string
  description: string
  time: string
  type: 'success' | 'info' | 'warning' | 'error'
}

defineProps<{
  title: string
  items: ActivityItem[]
  emptyLabel?: string
}>()

function toneClass(type: string): string {
  const map: Record<string, string> = {
    success: 'is-success',
    info: 'is-info',
    warning: 'is-warning',
    error: 'is-error'
  }
  return map[type] || 'is-info'
}
</script>

<style scoped>
.ym-activity-card {
  border: 1px solid var(--ym-card-border);
  border-radius: 24px;
  background: var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.13);
  padding: 1.35rem;
}

.ym-activity-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}

.ym-activity-card__head h3 {
  color: var(--ym-text);
  font-size: 22px;
  font-weight: 950;
  margin: 0;
}

.ym-activity-empty {
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 800;
  padding: 2rem;
  text-align: center;
}

.ym-activity-item {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  border: 1px solid transparent;
  border-radius: 18px;
  padding: 0.95rem;
  transition: transform 160ms ease, background 160ms ease, border-color 160ms ease;
}

.ym-activity-item:hover {
  border-color: var(--ym-card-border);
  background: var(--ym-row-hover);
  transform: translateY(-1px);
}

.ym-activity-icon {
  display: grid;
  height: 48px;
  width: 48px;
  flex: 0 0 48px;
  place-items: center;
  border-radius: 16px;
  font-size: 22px;
  font-weight: 950;
}

.ym-activity-icon.is-success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.ym-activity-icon.is-info { background: rgba(99, 102, 241, 0.15); color: #818cf8; }
.ym-activity-icon.is-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.ym-activity-icon.is-error { background: rgba(244, 63, 94, 0.15); color: #f43f5e; }

.ym-activity-item strong {
  display: block;
  color: var(--ym-text);
  font-size: 15.5px;
  font-weight: 930;
  line-height: 1.45;
}

.ym-activity-item small {
  display: block;
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 800;
  line-height: 1.5;
  margin-top: 0.15rem;
}

.ym-activity-item em {
  color: var(--ym-muted);
  flex: 0 0 auto;
  font-size: 14px;
  font-style: normal;
  font-weight: 800;
  margin-top: 0.15rem;
}
</style>
