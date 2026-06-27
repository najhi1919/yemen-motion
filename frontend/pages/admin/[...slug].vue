<template>
  <section class="ym-placeholder-page">
    <div class="ym-placeholder-card">
      <span class="ym-placeholder-badge">{{ copy.badge }}</span>
      <h2>{{ pageTitle }}</h2>
      <p>{{ copy.message }}</p>
      <code>{{ route.path }}</code>
    </div>
  </section>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin' })

type Locale = 'ar' | 'en'

const route = useRoute()
const currentLocale = useState<Locale>('ym-dashboard-locale', () => 'ar')

const dictionary = {
  ar: {
    badge: 'قيد التجهيز',
    fallbackTitle: 'صفحة إدارية غير مكتملة',
    message: 'هذه الصفحة موجودة ضمن خطة لوحة الإدارة، لكنها لم تُبن بعد. سيتم تجهيزها في مرحلة لاحقة.'
  },
  en: {
    badge: 'In progress',
    fallbackTitle: 'Admin page under construction',
    message: 'This admin page is planned, but it has not been built yet. It will be completed in a later phase.'
  }
}

const copy = computed(() => dictionary[currentLocale.value])
const pageTitle = computed(() => {
  const slug = route.params.slug
  const parts = Array.isArray(slug) ? slug : slug ? [slug] : []
  if (!parts.length) return copy.value.fallbackTitle
  return parts.map(part => String(part).replace(/[-_]+/g, ' ')).join(' / ')
})
</script>

<style scoped>
.ym-placeholder-page {
  display: grid;
  min-height: min(58vh, 560px);
  place-items: center;
  padding: clamp(1rem, 3vw, 2rem);
}

.ym-placeholder-card {
  width: min(100%, 720px);
  border: 1px solid var(--ym-card-border);
  border-radius: 30px;
  background:
    radial-gradient(circle at 20% 0%, color-mix(in srgb, #818cf8 18%, transparent), transparent 18rem),
    var(--ym-card-bg);
  box-shadow: var(--ym-card-shadow), inset 0 1px 0 rgba(255, 255, 255, 0.14);
  color: var(--ym-text);
  padding: clamp(1.4rem, 4vw, 2.4rem);
  text-align: center;
}

.ym-placeholder-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--ym-soft-border);
  border-radius: 999px;
  background: var(--ym-control-bg);
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 950;
  margin-bottom: 1rem;
  padding: 0.42rem 0.78rem;
}

.ym-placeholder-card h2 {
  font-size: clamp(1.85rem, 4vw, 2.8rem);
  font-weight: 950;
  line-height: 1.15;
  margin: 0;
}

.ym-placeholder-card p {
  color: var(--ym-muted);
  font-size: 16px;
  font-weight: 820;
  line-height: 1.8;
  margin: 0.85rem auto 0;
  max-width: 42rem;
}

.ym-placeholder-card code {
  display: inline-flex;
  max-width: 100%;
  overflow-wrap: anywhere;
  border: 1px solid var(--ym-soft-border);
  border-radius: 14px;
  background: var(--ym-control-bg);
  color: var(--ym-text);
  font-size: 14px;
  font-weight: 900;
  margin-top: 1.15rem;
  padding: 0.55rem 0.75rem;
}
</style>
