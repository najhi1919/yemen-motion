<script setup lang="ts">
import type {
  PublicDesignerIdentity,
  PublicDesignerWork,
} from '~/types/public-designer-profile'

defineProps<{
  identity: PublicDesignerIdentity
  works: PublicDesignerWork[]
  total: number
}>()
</script>

<template>
  <section id="works" class="public-works-feed" aria-labelledby="public-designer-works-title">
    <header class="public-works-header">
      <div class="public-works-heading-group">
        <span class="public-works-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <rect x="3.5" y="5" width="17" height="14" rx="2.5" />
            <circle cx="9" cy="10" r="1.5" />
            <path d="m6.5 16 3.5-3 2.5 2 2.5-2 2.5 3" />
          </svg>
        </span>
        <div class="min-w-0">
          <h2 id="public-designer-works-title" class="public-works-heading">الأعمال المنشورة</h2>
          <p class="public-works-description">
            أعمال متاحة للعامة تعكس خبرة المصمم ومجالاته الإبداعية.
          </p>
        </div>
      </div>
      <div class="public-works-count" aria-label="عدد الأعمال المنشورة">
        <bdi dir="ltr">{{ total }}</bdi>
        <span>{{ total === 1 ? 'عمل منشور' : 'أعمال منشورة' }}</span>
      </div>
    </header>

    <div v-if="works.length" class="public-works-list">
      <PublicDesignerWorkCard
        v-for="work in works"
        :key="work.public_code"
        :work="work"
        :identity="identity"
      />
    </div>

    <div v-else class="public-works-empty">
      <div class="public-empty-artboard" aria-hidden="true" />
      <h3 class="public-empty-title">لا توجد أعمال منشورة بعد</h3>
      <p class="public-empty-description">ستظهر هنا الأعمال العامة عندما يضيفها المصمم إلى ملفه.</p>
    </div>
  </section>
</template>

<style scoped>
.public-works-feed {
  position: relative;
  isolation: isolate;
  min-width: 0;
  padding: 8px;
  overflow: hidden;
  border-radius: 20px;
  background: radial-gradient(
    circle at 20% 12%,
    rgba(226, 29, 29, 0.04),
    transparent 32%
  );
  box-sizing: border-box;
  scroll-margin-top: 76px;
}

.public-works-feed::before {
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(26, 26, 29, 0.42) 1px, transparent 1px),
    linear-gradient(90deg, rgba(26, 26, 29, 0.42) 1px, transparent 1px);
  background-size: 28px 28px;
  content: "";
  opacity: 0.035;
}

.public-works-header {
  isolation: isolate;
  position: relative;
  display: flex;
  min-width: 0;
  align-items: flex-start;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 16px;
  padding: 22px 24px;
  overflow: hidden;
  border: 1px solid rgba(196, 141, 84, 0.16);
  border-radius: 16px;
  background:
    repeating-linear-gradient(135deg, rgba(184, 126, 72, 0.064) 0 1px, transparent 1px 11px),
    radial-gradient(circle at 8% -35%, rgba(194, 139, 83, 0.16), transparent 31%),
    radial-gradient(circle at 92% 130%, rgba(226, 29, 29, 0.18), transparent 34%),
    linear-gradient(135deg, #242329 0%, #101114 72%);
  box-shadow:
    inset 0 1px 0 rgba(214, 166, 110, 0.105),
    inset 0 -1px 0 rgba(226, 29, 29, 0.065),
    0 16px 34px rgba(17, 17, 17, 0.16),
    0 0 22px rgba(226, 29, 29, 0.065);
}

.public-works-header::after {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-end: -28px;
  width: 180px;
  height: 1px;
  pointer-events: none;
  background: linear-gradient(90deg, transparent, rgba(207, 151, 92, 0.3), rgba(226, 29, 29, 0.1), transparent);
  content: "";
  opacity: 0.7;
  transform: rotate(-18deg);
}

.public-works-heading-group {
  position: relative;
  z-index: 1;
  display: flex;
  min-width: 0;
  align-items: flex-start;
  gap: 11px;
}

.public-works-icon {
  display: inline-grid;
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  place-items: center;
  border: 1px solid rgba(226, 29, 29, 0.3);
  border-radius: 10px;
  background: rgba(226, 29, 29, 0.11);
  color: var(--ym-red);
}

.public-works-icon svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.public-works-heading {
  margin: 0;
  color: #fff;
  font-size: 24px;
  font-weight: 700;
  line-height: 1.4;
}

.public-works-description {
  margin: 6px 0 0;
  color: #b8bac1;
  font-size: 15px;
  line-height: 1.7;
}

.public-works-count {
  position: relative;
  z-index: 1;
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 7px;
  padding: 7px 11px;
  border: 1px solid rgba(226, 29, 29, 0.25);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.075);
  color: #d2d3d8;
  box-shadow: inset 0 1px 0 rgba(224, 185, 143, 0.09), 0 6px 14px rgba(0, 0, 0, 0.12);
  font-size: 13px;
  font-weight: 600;
}

.public-works-count bdi {
  color: var(--ym-red);
  font-size: 18px;
  font-weight: 700;
}

.public-works-list {
  min-width: 0;
}

.public-works-empty {
  position: relative;
  padding: 66px 24px;
  overflow: hidden;
  border: 1px solid var(--ym-border);
  border-radius: 16px;
  background:
    linear-gradient(135deg, rgba(226, 29, 29, 0.035), transparent 40%),
    var(--ym-surface);
  box-shadow: var(--ym-shadow);
  text-align: center;
}

.public-empty-artboard {
  position: relative;
  width: 66px;
  height: 52px;
  margin-inline: auto;
  border: 2px solid var(--ym-charcoal);
  border-radius: 12px;
  background:
    linear-gradient(135deg, rgba(226, 29, 29, 0.03), transparent 62%),
    var(--ym-surface);
  box-shadow: 0 10px 24px rgba(17, 17, 17, 0.09);
  transform: rotate(-2deg);
}

.public-empty-artboard::before {
  position: absolute;
  inset-inline-start: 15px;
  inset-block-start: 29px;
  width: 34px;
  height: 3px;
  background: var(--ym-red);
  content: "";
  transform: rotate(-32deg);
}

.public-empty-artboard::after {
  position: absolute;
  inset-inline-end: 11px;
  inset-block-start: 10px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ym-red);
  content: "";
}

.public-empty-title {
  margin: 16px 0 0;
  color: var(--ym-charcoal);
  font-size: 21px;
  font-weight: 700;
}

.public-empty-description {
  max-width: 480px;
  margin: 7px auto 0;
  color: var(--ym-muted);
  font-size: 15px;
  line-height: 1.7;
}

@media (max-width: 899px) {
  .public-works-feed,
  .public-works-header,
  .public-works-list,
  .public-works-empty {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }

  .public-works-feed {
    padding: 4px;
    border-radius: 14px;
  }

  .public-works-header {
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
    padding: 16px;
    border-radius: 10px;
  }

  .public-works-empty {
    padding: 48px 16px;
    border-radius: 10px;
  }
}

</style>
