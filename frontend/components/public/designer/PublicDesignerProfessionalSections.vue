<script setup lang="ts">
import type {
  PublicDesignerIdentity,
  PublicDesignerProfessional,
} from '~/types/public-designer-profile'

type PublicProfileSection = 'works' | 'intro' | 'expertise' | 'tools-languages'

const props = defineProps<{
  identity: PublicDesignerIdentity
  professional: PublicDesignerProfessional
  activeMobileSection: PublicProfileSection
}>()

const availabilityLabels: Record<string, string> = {
  available: 'متاح للعمل',
  limited: 'متاح بشكل محدود',
  partially_available: 'متاح بشكل محدود',
  unavailable: 'غير متاح حاليًا',
}

const levelLabels: Record<string, string> = {
  beginner: 'مبتدئ',
  intermediate: 'متوسط',
  advanced: 'متقدم',
  expert: 'خبير',
  basic: 'أساسي',
  conversational: 'محادثة',
  professional: 'مهني',
  native: 'لغة أم',
}

const isLtrName = (value: string) => /[A-Za-z]/.test(value)

const normalizeToolName = (value: string) => value.trim().toLowerCase().replace(/\s+/gu, ' ')

const toolBrands: Record<string, { label: string, variant: string }> = {
  'adobe photoshop': { label: 'Ps', variant: 'photoshop' },
  'adobe illustrator': { label: 'Ai', variant: 'illustrator' },
  'adobe after effects': { label: 'Ae', variant: 'after-effects' },
  'adobe indesign': { label: 'Id', variant: 'indesign' },
  'adobe premiere pro': { label: 'Pr', variant: 'premiere' },
  figma: { label: 'F', variant: 'figma' },
  blender: { label: 'B', variant: 'blender' },
  canva: { label: 'C', variant: 'canva' },
}

const toolBrand = (name: string) => {
  const normalizedName = normalizeToolName(name)
  const knownBrand = toolBrands[normalizedName]
  if (knownBrand) {
    return { ...knownBrand, className: `tool-badge-${knownBrand.variant}` }
  }

  const label = Array.from(normalizedName.replace(/[^\p{L}\p{N}]/gu, ''))
    .slice(0, 2)
    .join('')
    .toUpperCase()

  return { label: label || 'YM', variant: 'default', className: 'tool-badge-default' }
}
const sections = computed(() => props.professional.sections)
const services = computed(() => sections.value.specialties.visible
  ? sections.value.specialties.service
  : [])
const styles = computed(() => sections.value.specialties.visible
  ? sections.value.specialties.style
  : [])
const skills = computed(() => sections.value.skills.visible ? sections.value.skills.items : [])
const tools = computed(() => sections.value.tools.visible ? sections.value.tools.items : [])
const languages = computed(() => sections.value.languages.visible ? sections.value.languages.items : [])
const note = computed(() => props.professional.additional_information?.professional_note?.trim() || '')

const prominentField = computed(() => services.value[0]?.name || styles.value[0]?.name || null)
const introDetails = computed(() => {
  const items: Array<{ label: string, value: string }> = []

  if (sections.value.availability.visible) {
    items.push({
      label: 'حالة التوفر',
      value: availabilityLabels[sections.value.availability.value] || sections.value.availability.value,
    })
  }

  if (sections.value.experience.visible && sections.value.experience.years_of_experience !== null) {
    items.push({
      label: 'سنوات الخبرة',
      value: `${sections.value.experience.years_of_experience} سنوات`,
    })
  }

  if (props.identity.primary_specialty) {
    items.push({ label: 'التخصص الرئيسي', value: props.identity.primary_specialty })
  }

  if (prominentField.value) {
    items.push({ label: 'المجال البارز', value: prominentField.value })
  }

  return items
})

const hasServicesStyles = computed(() => services.value.length > 0 || styles.value.length > 0)
const hasExpertise = computed(() => skills.value.length > 0)
const hasToolsLanguages = computed(() => tools.value.length > 0 || languages.value.length > 0)
</script>

<template>
  <aside class="profile-intro-sidebar" aria-label="نبذة المصمم ومعلوماته المهنية">
    <div
      id="profile-panel-intro"
      role="tabpanel"
      aria-labelledby="profile-tab-intro"
      :class="[
        'profile-panel-group',
        { 'profile-mobile-panel-active': activeMobileSection === 'intro' },
      ]"
    >
    <section id="intro" class="profile-sidebar-card profile-intro-card" aria-labelledby="intro-title">
      <div class="profile-sidebar-heading">
        <span class="profile-sidebar-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <circle cx="12" cy="8" r="3.25" />
            <path d="M5.5 19c.7-3.2 3-5 6.5-5s5.8 1.8 6.5 5" />
          </svg>
        </span>
        <h2 id="intro-title" class="profile-sidebar-title">نبذة عني</h2>
      </div>
      <p v-if="identity.bio" dir="auto" class="profile-bio">{{ identity.bio }}</p>

      <dl v-if="introDetails.length" class="profile-intro-details">
        <div v-for="item in introDetails" :key="item.label" class="profile-detail-row">
          <span class="profile-detail-marker" aria-hidden="true" />
          <div class="min-w-0">
            <dt class="profile-detail-label">{{ item.label }}</dt>
            <dd dir="auto" class="profile-detail-value">{{ item.value }}</dd>
          </div>
        </div>
      </dl>
    </section>

    <section v-if="hasServicesStyles" class="profile-sidebar-card profile-services-card" aria-labelledby="services-styles-title">
      <div class="profile-sidebar-heading">
        <span class="profile-sidebar-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="m12 3 1.15 3.85L17 8l-3.85 1.15L12 13l-1.15-3.85L7 8l3.85-1.15L12 3Z" />
            <path d="m18.5 14 .65 2.35 2.35.65-2.35.65L18.5 20l-.65-2.35-2.35-.65 2.35-.65.65-2.35Z" />
          </svg>
        </span>
        <h2 id="services-styles-title" class="profile-sidebar-title">الخدمات والأساليب</h2>
      </div>

      <div v-if="services.length" class="profile-sidebar-group">
        <h3 class="profile-sidebar-subtitle">الخدمات</h3>
        <div class="profile-chips">
          <template v-for="item in services" :key="`service-${item.name}`">
            <bdi v-if="isLtrName(item.name)" dir="ltr" class="service-chip">{{ item.name }}</bdi>
            <span v-else dir="auto" class="service-chip">{{ item.name }}</span>
          </template>
        </div>
      </div>

      <div v-if="styles.length" class="profile-sidebar-group">
        <h3 class="profile-sidebar-subtitle">الأساليب</h3>
        <div class="profile-chips">
          <template v-for="item in styles" :key="`style-${item.name}`">
            <bdi v-if="isLtrName(item.name)" dir="ltr" class="style-chip">{{ item.name }}</bdi>
            <span v-else dir="auto" class="style-chip">{{ item.name }}</span>
          </template>
        </div>
      </div>
    </section>

    <section v-if="note" class="profile-sidebar-card profile-note-card" aria-labelledby="professional-note-title">
      <div class="profile-sidebar-heading">
        <span class="profile-sidebar-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <rect x="5" y="4" width="14" height="16" rx="2.5" />
            <path d="M9 9h6M9 13h6M9 17h3" />
          </svg>
        </span>
        <h2 id="professional-note-title" class="profile-sidebar-title">معلومات إضافية</h2>
      </div>
      <p dir="auto" class="profile-note">{{ note }}</p>
    </section>
    </div>

    <div
      id="profile-panel-expertise"
      role="tabpanel"
      aria-labelledby="profile-tab-expertise"
      :class="[
        'profile-panel-group',
        { 'profile-mobile-panel-active': activeMobileSection === 'expertise' },
      ]"
    >
    <section v-if="hasExpertise" id="expertise" class="profile-sidebar-card profile-expertise-card" aria-labelledby="expertise-title">
      <div class="profile-sidebar-heading">
        <span class="profile-sidebar-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <path d="M8 4h8l2 5-6 10L6 9l2-5Z" />
            <path d="m9.5 9 2.5 2 2.5-2M12 11v8" />
          </svg>
        </span>
        <h2 id="expertise-title" class="profile-sidebar-title">الخبرات والمهارات</h2>
      </div>
      <ul class="profile-compact-list">
        <li v-for="item in skills" :key="`skill-${item.name}`" class="profile-compact-row">
          <bdi v-if="isLtrName(item.name)" dir="ltr" class="profile-item-name">{{ item.name }}</bdi>
          <span v-else dir="auto" class="profile-item-name">{{ item.name }}</span>
          <span class="level-badge">{{ levelLabels[item.level] || item.level }}</span>
        </li>
      </ul>
    </section>
    </div>

    <div
      id="profile-panel-tools-languages"
      role="tabpanel"
      aria-labelledby="profile-tab-tools-languages"
      :class="[
        'profile-panel-group',
        { 'profile-mobile-panel-active': activeMobileSection === 'tools-languages' },
      ]"
    >
    <section v-if="hasToolsLanguages" id="tools-languages" class="profile-sidebar-card profile-tools-card" aria-labelledby="tools-languages-title">
      <div class="profile-sidebar-heading">
        <span class="profile-sidebar-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" focusable="false">
            <rect x="4" y="4" width="6" height="6" rx="1.5" />
            <rect x="14" y="4" width="6" height="6" rx="1.5" />
            <rect x="4" y="14" width="6" height="6" rx="1.5" />
            <rect x="14" y="14" width="6" height="6" rx="1.5" />
          </svg>
        </span>
        <h2 id="tools-languages-title" class="profile-sidebar-title">الأدوات واللغات</h2>
      </div>

      <div v-if="tools.length" class="profile-sidebar-group">
        <h3 class="profile-sidebar-subtitle">البرامج والأدوات</h3>
        <ul class="profile-compact-list">
          <li v-for="item in tools" :key="`tool-${item.name}`" class="profile-compact-row profile-tool-row">
            <span class="profile-tool-identity">
              <span class="profile-tool-badge" :class="toolBrand(item.name).className" aria-hidden="true">
                {{ toolBrand(item.name).label }}
              </span>
              <bdi v-if="isLtrName(item.name)" dir="ltr" class="profile-item-name">{{ item.name }}</bdi>
              <span v-else dir="auto" class="profile-item-name">{{ item.name }}</span>
            </span>
            <span class="level-badge">{{ levelLabels[item.level] || item.level }}</span>
          </li>
        </ul>
      </div>

      <div v-if="languages.length" class="profile-sidebar-group">
        <h3 class="profile-sidebar-subtitle">اللغات</h3>
        <ul class="profile-compact-list">
          <li v-for="item in languages" :key="`language-${item.name}`" class="profile-compact-row">
            <bdi v-if="isLtrName(item.name)" dir="ltr" class="profile-item-name">{{ item.name }}</bdi>
            <span v-else dir="auto" class="profile-item-name">{{ item.name }}</span>
            <span class="level-badge">{{ levelLabels[item.level] || item.level }}</span>
          </li>
        </ul>
      </div>
    </section>
    </div>
  </aside>
</template>

<style scoped>
.profile-intro-sidebar {
  min-width: 0;
}

.profile-sidebar-card {
  position: relative;
  min-width: 0;
  margin-bottom: 16px;
  padding: 22px;
  overflow: hidden;
  border: 1px solid rgba(17, 17, 17, 0.075);
  border-radius: 16px;
  background:
    radial-gradient(circle at 100% 0, rgba(226, 29, 29, 0.05), transparent 88px),
    linear-gradient(180deg, #fff 0%, #fefefe 100%);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.95),
    0 12px 30px rgba(17, 17, 17, 0.07);
  scroll-margin-top: 76px;
  transition: border-color 180ms ease, box-shadow 180ms ease;
}

.profile-sidebar-card:hover {
  border-color: rgba(226, 29, 29, 0.34);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.95),
    0 16px 36px rgba(17, 17, 17, 0.09);
}

.profile-sidebar-card:last-child {
  margin-bottom: 0;
}

.profile-sidebar-heading {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 10px;
}

.profile-sidebar-icon {
  display: inline-grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border: 1px solid rgba(226, 29, 29, 0.2);
  border-radius: 10px;
  background: rgba(226, 29, 29, 0.045);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
  color: var(--ym-red);
}

.profile-sidebar-icon svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.profile-sidebar-title {
  margin: 0;
  color: var(--ym-charcoal);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.4;
}

.profile-bio,
.profile-note {
  margin: 14px 0 0;
  color: #333;
  font-size: 16px;
  line-height: 1.8;
  white-space: pre-line;
  overflow-wrap: anywhere;
}

.profile-intro-details {
  margin-top: 16px;
  padding-top: 10px;
  border-top: 1px solid var(--ym-border);
}

.profile-detail-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding-block: 7px;
  font-size: 15px;
}

.profile-detail-marker {
  width: 7px;
  height: 7px;
  flex-shrink: 0;
  margin-top: 9px;
  border-radius: 2px;
  background: var(--ym-red);
  transform: rotate(45deg);
}

.profile-detail-label {
  color: var(--ym-muted);
  font-size: 13px;
  font-weight: 600;
}

.profile-detail-value {
  margin-top: 1px;
  color: var(--ym-charcoal);
  font-weight: 700;
  line-height: 1.65;
  overflow-wrap: anywhere;
}

.profile-sidebar-group {
  margin-top: 18px;
}

.profile-sidebar-subtitle {
  margin: 0 0 10px;
  color: var(--ym-muted);
  font-size: 14px;
  font-weight: 700;
}

.profile-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.service-chip,
.style-chip {
  max-width: 100%;
  padding: 7px 12px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.4;
  overflow-wrap: anywhere;
}

.service-chip {
  border: 1px solid var(--ym-charcoal);
  background: var(--ym-charcoal);
  color: #fff;
}

.style-chip {
  border: 1px solid var(--ym-border-strong);
  background: #f5f5f6;
  color: var(--ym-charcoal);
}

.profile-compact-list {
  display: grid;
  gap: 0;
  margin: 14px 0 0;
  padding: 0;
  list-style: none;
}

.profile-compact-row {
  display: flex;
  min-width: 0;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding-block: 9px;
  border-bottom: 1px solid var(--ym-border);
  border-radius: 8px;
  transition: background-color 180ms ease;
}

.profile-compact-row:last-child {
  border-bottom: 0;
}

.profile-item-name {
  min-width: 0;
  color: var(--ym-charcoal);
  font-size: 15px;
  font-weight: 600;
  overflow-wrap: anywhere;
}

.level-badge {
  flex-shrink: 0;
  padding: 3px 8px;
  border: 1px solid var(--ym-border-strong);
  border-radius: 999px;
  background: #f3f4f6;
  color: #333;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.4;
}

.profile-tool-identity {
  display: flex;
  min-width: 0;
  align-items: center;
  gap: 10px;
}

.profile-tool-row:hover {
  background: #fafafa;
}

.profile-tool-badge {
  display: inline-grid;
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  place-items: center;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 9px;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04), 0 6px 14px rgba(0, 0, 0, 0.18);
  font-family: "IBM Plex Sans", sans-serif;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: -0.03em;
}

.tool-badge-photoshop { background: #071e34; color: #31a8ff; border-color: rgba(49, 168, 255, 0.45); }
.tool-badge-illustrator { background: #2b1606; color: #ff9a00; border-color: rgba(255, 154, 0, 0.45); }
.tool-badge-after-effects { background: #17102d; color: #9999ff; border-color: rgba(153, 153, 255, 0.45); }
.tool-badge-indesign { background: #2c0d1a; color: #f06b9d; border-color: rgba(240, 107, 157, 0.42); }
.tool-badge-premiere { background: #15142d; color: #aaa7ff; border-color: rgba(170, 167, 255, 0.42); }
.tool-badge-figma {
  border-color: rgba(255, 255, 255, 0.2);
  background:
    radial-gradient(circle at 25% 28%, #f24e1e 0 2px, transparent 3px),
    radial-gradient(circle at 75% 28%, #a259ff 0 2px, transparent 3px),
    radial-gradient(circle at 25% 72%, #1abcfe 0 2px, transparent 3px),
    #202024;
  color: #fff;
}
.tool-badge-blender { background: #26170b; color: #f5792a; border-color: rgba(245, 121, 42, 0.45); }
.tool-badge-canva { background: #06272b; color: #37d5dc; border-color: rgba(55, 213, 220, 0.45); }
.tool-badge-default { background: #f3f4f6; color: var(--ym-charcoal); border-color: var(--ym-border-strong); }

@media (min-width: 900px) {
  .profile-intro-sidebar {
    display: grid;
    gap: 16px;
  }

  .profile-panel-group {
    display: contents;
  }

  .profile-sidebar-card {
    margin-bottom: 0;
  }

  .profile-intro-card { order: 1; }
  .profile-services-card { order: 2; }
  .profile-expertise-card { order: 3; }
  .profile-tools-card { order: 4; }
  .profile-note-card { order: 5; }
}

@media (max-width: 899px) {
  .profile-panel-group {
    display: none;
  }

  .profile-panel-group.profile-mobile-panel-active {
    display: block;
  }

  .profile-intro-sidebar,
  .profile-sidebar-card {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
  }

  .profile-sidebar-card {
    margin-bottom: 12px;
    padding: 16px;
    border-radius: 10px;
  }

  .profile-compact-row {
    justify-content: flex-start;
    flex-wrap: wrap;
  }

  .profile-item-name {
    flex: 0 1 auto;
  }
}

@media (prefers-reduced-motion: reduce) {
  .profile-sidebar-card {
    transition: border-color 100ms linear;
  }

  .profile-compact-row {
    transition: none;
  }
}
</style>
