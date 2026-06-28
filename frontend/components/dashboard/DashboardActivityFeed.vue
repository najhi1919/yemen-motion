<template>
  <section class="ym-activity-card">
    <div class="ym-activity-card__head">
      <h3>{{ title }}</h3>
      <slot name="actions" />
    </div>
    <div class="ym-activity-list" :class="items.length === 0 ? 'is-empty' : ''">
      <div v-if="items.length === 0" class="ym-activity-empty">
        <span class="ym-activity-empty__icon" aria-hidden="true">•</span>
        <span>{{ emptyLabel }}</span>
      </div>
      <article v-for="(item, i) in items" :key="i" class="ym-activity-item" :class="toneClass(item.type)">
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
  position: relative;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, var(--ym-card-border) 88%, rgba(129, 140, 248, 0.14));
  border-radius: 24px;
  background:
    radial-gradient(circle at 12% 0%, rgba(236, 72, 153, 0.1), transparent 16rem),
    radial-gradient(circle at 92% 8%, rgba(56, 189, 248, 0.1), transparent 18rem),
    linear-gradient(180deg, color-mix(in srgb, var(--ym-card-bg) 90%, rgba(255, 255, 255, 0.06)), var(--ym-card-bg)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 16px 40px rgba(2, 6, 23, 0.12),
    inset 0 1px 0 rgba(255, 255, 255, 0.18),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  padding: clamp(1.2rem, 2vw, 1.45rem);
  transition: border-color 200ms ease, box-shadow 200ms ease, transform 200ms ease;
}

.ym-activity-card::before {
  position: absolute;
  inset-inline-start: 1.35rem;
  top: 0;
  height: 3px;
  width: min(15rem, calc(100% - 2.7rem));
  border-end-end-radius: 999px;
  border-end-start-radius: 999px;
  background: linear-gradient(90deg, #6366f1, #ec4899 48%, #38bdf8);
  box-shadow: 0 0 24px rgba(129, 140, 248, 0.22);
  content: "";
  pointer-events: none;
}

.ym-activity-card::after {
  position: absolute;
  inset: 1px;
  inset-block-end: auto;
  height: 50%;
  border-radius: 23px 23px 0 0;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.08), transparent 70%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.08), transparent 36%);
  content: "";
  pointer-events: none;
}

.ym-activity-card > * {
  position: relative;
  z-index: 1;
}

.ym-activity-card:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 70%, rgba(129, 140, 248, 0.34));
  box-shadow:
    0 26px 64px rgba(2, 6, 23, 0.2),
    0 0 34px rgba(129, 140, 248, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.2),
    inset 0 -1px 0 rgba(15, 23, 42, 0.08);
  transform: translateY(-1px);
}

.ym-activity-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  border-bottom: 1px solid color-mix(in srgb, var(--ym-soft-border) 82%, rgba(129, 140, 248, 0.16));
  margin-bottom: 1rem;
  padding-bottom: 1rem;
}

.ym-activity-card__head h3 {
  color: var(--ym-text);
  font-size: clamp(20px, 2vw, 23px);
  font-weight: 950;
  line-height: 1.25;
  margin: 0;
}

.ym-activity-list {
  position: relative;
  display: grid;
  gap: 0.65rem;
}

.ym-activity-list::before {
  position: absolute;
  inset-block: 0.65rem;
  inset-inline-start: 1.5rem;
  width: 1px;
  background: linear-gradient(180deg, transparent, var(--ym-soft-border), transparent);
  content: "";
  pointer-events: none;
}

.ym-activity-list.is-empty::before {
  display: none;
}

.ym-activity-empty {
  display: grid;
  justify-items: center;
  gap: 0.7rem;
  border: 1px dashed color-mix(in srgb, var(--ym-card-border) 82%, rgba(129, 140, 248, 0.18));
  border-radius: 18px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 76%, rgba(255, 255, 255, 0.04)), transparent);
  color: var(--ym-muted);
  font-size: 15px;
  font-weight: 850;
  line-height: 1.6;
  padding: 2rem 1rem;
  text-align: center;
}

.ym-activity-empty__icon {
  display: grid;
  height: 42px;
  width: 42px;
  place-items: center;
  border: 1px solid color-mix(in srgb, #818cf8 36%, transparent);
  border-radius: 14px;
  background: rgba(99, 102, 241, 0.12);
  color: #818cf8;
  font-size: 26px;
  line-height: 1;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.ym-activity-item {
  --activity-color: #818cf8;
  position: relative;
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 62%, transparent);
  border-radius: 18px;
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--ym-control-bg) 72%, transparent), transparent);
  padding: 0.95rem;
  transition: transform 160ms ease, background 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
}

.ym-activity-item.is-success { --activity-color: #10b981; }
.ym-activity-item.is-info { --activity-color: #818cf8; }
.ym-activity-item.is-warning { --activity-color: #f59e0b; }
.ym-activity-item.is-error { --activity-color: #f43f5e; }

.ym-activity-item::before {
  position: absolute;
  inset-block: 0.95rem;
  inset-inline-start: 0;
  width: 3px;
  border-radius: 999px;
  background: linear-gradient(180deg, var(--activity-color), color-mix(in srgb, var(--activity-color) 24%, transparent));
  content: "";
  opacity: 0.72;
}

.ym-activity-item:hover {
  border-color: color-mix(in srgb, var(--activity-color) 34%, var(--ym-card-border));
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--activity-color) 10%, var(--ym-row-hover)), var(--ym-row-hover));
  box-shadow:
    0 14px 30px color-mix(in srgb, var(--activity-color) 10%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.08);
  transform: translateY(-1px);
}

.ym-activity-icon {
  display: grid;
  height: 48px;
  width: 48px;
  flex: 0 0 48px;
  place-items: center;
  border: 1px solid color-mix(in srgb, currentColor 28%, transparent);
  border-radius: 16px;
  box-shadow:
    0 12px 24px color-mix(in srgb, currentColor 12%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  font-size: 22px;
  font-weight: 950;
  transition: transform 160ms ease, box-shadow 160ms ease;
}

.ym-activity-item:hover .ym-activity-icon {
  transform: scale(1.03);
}

.ym-activity-icon.is-success { background: rgba(16, 185, 129, 0.16); color: #10b981; }
.ym-activity-icon.is-info { background: rgba(99, 102, 241, 0.16); color: #818cf8; }
.ym-activity-icon.is-warning { background: rgba(245, 158, 11, 0.16); color: #f59e0b; }
.ym-activity-icon.is-error { background: rgba(244, 63, 94, 0.16); color: #f43f5e; }

.ym-activity-item strong {
  display: block;
  color: var(--ym-text);
  font-size: 15.5px;
  font-weight: 950;
  line-height: 1.45;
}

.ym-activity-item small {
  display: block;
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 820;
  line-height: 1.55;
  margin-top: 0.15rem;
}

.ym-activity-item em {
  border: 1px solid color-mix(in srgb, var(--ym-soft-border) 76%, rgba(129, 140, 248, 0.12));
  border-radius: 999px;
  background: color-mix(in srgb, var(--ym-control-bg) 70%, transparent);
  color: var(--ym-muted);
  flex: 0 0 auto;
  font-size: 12.5px;
  font-style: normal;
  font-weight: 900;
  line-height: 1.2;
  margin-top: 0.15rem;
  padding: 0.35rem 0.6rem;
  white-space: nowrap;
}

:global(.ym-dashboard-light) .ym-activity-card {
  border-color: color-mix(in srgb, var(--ym-card-border) 88%, rgba(109, 40, 217, 0.18));
  background:
    radial-gradient(circle at 12% 0%, rgba(236, 72, 153, 0.08), transparent 16rem),
    radial-gradient(circle at 92% 8%, rgba(14, 165, 233, 0.09), transparent 18rem),
    linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(248, 244, 255, 0.96)),
    var(--ym-card-bg);
  box-shadow:
    var(--ym-card-shadow),
    0 18px 40px rgba(76, 29, 149, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.62),
    inset 0 -1px 0 rgba(109, 40, 217, 0.06);
}

:global(.ym-dashboard-light) .ym-activity-card::after {
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.22), transparent 68%),
    linear-gradient(120deg, rgba(255, 255, 255, 0.14), transparent 36%);
}

:global(.ym-dashboard-light) .ym-activity-card:hover {
  border-color: color-mix(in srgb, var(--ym-card-border) 72%, rgba(109, 40, 217, 0.28));
  box-shadow:
    0 26px 64px rgba(76, 29, 149, 0.14),
    0 0 32px rgba(129, 140, 248, 0.08),
    inset 0 1px 0 rgba(255, 255, 255, 0.68),
    inset 0 -1px 0 rgba(109, 40, 217, 0.07);
}

:global(.ym-dashboard-light) .ym-activity-card__head {
  border-bottom-color: color-mix(in srgb, var(--ym-soft-border) 84%, rgba(109, 40, 217, 0.12));
}

:global(.ym-dashboard-light) .ym-activity-item {
  border-color: color-mix(in srgb, var(--ym-soft-border) 74%, rgba(109, 40, 217, 0.08));
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.64), rgba(248, 244, 255, 0.72));
}

:global(.ym-dashboard-light) .ym-activity-item:hover {
  background:
    linear-gradient(180deg, color-mix(in srgb, var(--activity-color) 8%, rgba(255, 255, 255, 0.82)), rgba(248, 244, 255, 0.88));
  box-shadow:
    0 14px 30px color-mix(in srgb, var(--activity-color) 8%, transparent),
    inset 0 1px 0 rgba(255, 255, 255, 0.44);
}

:global(.ym-dashboard-light) .ym-activity-item em,
:global(.ym-dashboard-light) .ym-activity-empty {
  background: rgba(255, 255, 255, 0.58);
}

@media (max-width: 640px) {
  .ym-activity-item {
    flex-wrap: wrap;
  }

  .ym-activity-item em {
    margin-inline-start: 3.3rem;
  }
}

@media (prefers-reduced-motion: reduce) {
  .ym-activity-card:hover,
  .ym-activity-item:hover,
  .ym-activity-item:hover .ym-activity-icon {
    transform: none;
  }
}
</style>
