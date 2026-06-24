<template>
  <section class="ym-placeholder-page">
    <div class="ym-placeholder-card">
      <span class="ym-placeholder-badge">{{ copy.badge }}</span>
      <h1>{{ copy.title }}</h1>
      <p>{{ copy.description }}</p>
    </div>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'client' })

type Locale = 'ar' | 'en'

const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const copy = computed(() => ({
  ar: {
    badge: 'صفحة مؤقتة',
    title: 'لوحة العميل ضمن خطة البناء ولم تجهز بعد.',
    description: 'تم إنشاء هذه الصفحة مؤقتًا لمنع 404 أثناء مرحلة بناء App Shell.'
  },
  en: {
    badge: 'Temporary page',
    title: 'The client dashboard is planned but is not ready yet.',
    description: 'This temporary page prevents a 404 during the App Shell build phase.'
  }
})[currentLocale.value])
</script>

<style scoped>
.ym-placeholder-page {
  display: grid;
  min-height: 52vh;
  place-items: center;
  padding: 1rem;
}

.ym-placeholder-card {
  width: min(100%, 720px);
  border: 1px solid var(--ym-card-border);
  border-radius: 28px;
  background: var(--ym-card-bg);
  padding: clamp(1.5rem, 4vw, 2.5rem);
  text-align: center;
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.ym-placeholder-badge {
  display: inline-flex;
  border: 1px solid rgba(16, 185, 129, 0.34);
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.12);
  padding: 0.4rem 0.8rem;
  color: #10b981;
  font-size: 13px;
  font-weight: 950;
}

.ym-placeholder-card h1 {
  margin: 1rem 0 0.6rem;
  color: var(--ym-text);
  font-size: clamp(1.6rem, 4vw, 2.25rem);
  font-weight: 950;
  line-height: 1.35;
}

.ym-placeholder-card p {
  margin: 0;
  color: var(--ym-muted);
  font-size: 16px;
  font-weight: 750;
  line-height: 1.8;
}
</style>
