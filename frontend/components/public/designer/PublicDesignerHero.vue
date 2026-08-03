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

const emit = defineEmits<{
  'update:activeMobileSection': [section: PublicProfileSection]
}>()

const tabListRef = ref<HTMLElement | null>(null)
const avatarFailed = ref(false)
const coverFailed = ref(false)

const initials = computed(() => {
  const words = props.identity.display_name.trim().split(/\s+/u).filter(Boolean)
  return words.slice(0, 2).map(word => Array.from(word)[0]).join('').toUpperCase() || 'YM'
})

const coverPosition = computed(() =>
  `${props.identity.cover_focal_point.x}% ${props.identity.cover_focal_point.y}%`,
)

const professionalLine = computed(() => [
  props.identity.professional_title,
  props.identity.primary_specialty,
].filter(Boolean).join(' · '))

const hasExpertiseSection = computed(() => {
  const skills = props.professional.sections.skills

  return skills.visible && skills.items.length > 0
})

const hasToolsLanguagesSection = computed(() => {
  const tools = props.professional.sections.tools
  const languages = props.professional.sections.languages

  return (tools.visible && tools.items.length > 0)
    || (languages.visible && languages.items.length > 0)
})

const availableMobileSections = computed<PublicProfileSection[]>(() => {
  const sections: PublicProfileSection[] = ['works', 'intro']

  if (hasExpertiseSection.value) {
    sections.push('expertise')
  }

  if (hasToolsLanguagesSection.value) {
    sections.push('tools-languages')
  }

  return sections
})

function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined'
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

function revealMobileTab(section: PublicProfileSection, focusTab: boolean): void {
  void nextTick(() => {
    const tab = tabListRef.value?.querySelector<HTMLButtonElement>(
      `[data-profile-section="${section}"]`,
    )

    if (!tab) {
      return
    }

    if (focusTab) {
      tab.focus({ preventScroll: true })
    }

    tab.scrollIntoView({
      behavior: prefersReducedMotion() ? 'auto' : 'smooth',
      block: 'nearest',
      inline: 'nearest',
    })
  })
}

function selectMobileSection(
  section: PublicProfileSection,
  focusTab = false,
): void {
  emit('update:activeMobileSection', section)
  revealMobileTab(section, focusTab)
}

function handleMobileTabsKeydown(event: KeyboardEvent): void {
  if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
    return
  }

  const supportedKeys = ['ArrowLeft', 'ArrowRight', 'Home', 'End']

  if (!supportedKeys.includes(event.key)) {
    return
  }

  const target = event.target instanceof HTMLElement
    ? event.target
    : null

  const currentTab = target?.closest<HTMLButtonElement>('[role="tab"]')
  const currentSection = currentTab?.dataset.profileSection as
    | PublicProfileSection
    | undefined

  if (!currentSection) {
    return
  }

  const sections = availableMobileSections.value
  const currentIndex = sections.indexOf(currentSection)

  if (currentIndex < 0 || sections.length === 0) {
    return
  }

  let nextIndex = currentIndex

  if (event.key === 'Home') {
    nextIndex = 0
  } else if (event.key === 'End') {
    nextIndex = sections.length - 1
  } else {
    const isRtl = tabListRef.value
      ? getComputedStyle(tabListRef.value).direction === 'rtl'
      : true

    const direction = event.key === 'ArrowRight'
      ? (isRtl ? -1 : 1)
      : (isRtl ? 1 : -1)

    nextIndex = (
      currentIndex
      + direction
      + sections.length
    ) % sections.length
  }

  const nextSection = sections[nextIndex]

  if (!nextSection) {
    return
  }

  event.preventDefault()
  selectMobileSection(nextSection, true)
}
</script>

<template>
  <section class="profile-header" aria-labelledby="public-designer-name">
    <div class="profile-cover">
      <img
        v-if="identity.cover_url && !coverFailed"
        :src="identity.cover_url"
        :alt="`صورة غلاف ${identity.display_name}`"
        class="profile-cover-image"
        :style="{ objectPosition: coverPosition }"
        @error="coverFailed = true"
      >
      <div v-else class="profile-cover-fallback" aria-hidden="true">
        <span class="profile-cover-mark" />
      </div>
    </div>

    <div class="profile-identity-area">
      <div class="profile-identity-row">
        <div class="profile-avatar">
          <img
            v-if="identity.avatar_url && !avatarFailed"
            :src="identity.avatar_url"
            :alt="`الصورة الشخصية لـ ${identity.display_name}`"
            class="profile-avatar-image"
            @error="avatarFailed = true"
          >
          <span v-else aria-hidden="true">{{ initials }}</span>
        </div>

        <div class="profile-identity-copy">
          <h1 id="public-designer-name" dir="auto" class="profile-name">
            {{ identity.display_name }}
          </h1>
          <bdi dir="ltr" class="profile-username">@{{ identity.username }}</bdi>
          <p v-if="professionalLine" class="profile-professional-line">
            <bdi dir="auto">{{ professionalLine }}</bdi>
          </p>
        </div>
      </div>

      <nav class="profile-tabs profile-tabs-desktop" aria-label="أقسام ملف المصمم">
        <a href="#works" class="profile-tab profile-tab-active">الأعمال</a>
        <a href="#intro" class="profile-tab">نبذة عني</a>
        <a v-if="hasExpertiseSection" href="#expertise" class="profile-tab">الخبرات والمهارات</a>
        <a v-if="hasToolsLanguagesSection" href="#tools-languages" class="profile-tab">الأدوات واللغات</a>
      </nav>

      <div
        ref="tabListRef"
        class="profile-tabs profile-tabs-mobile"
        role="tablist"
        aria-label="أقسام ملف المصمم"
        aria-orientation="horizontal"
        @keydown="handleMobileTabsKeydown"
      >
        <button
          type="button"
          id="profile-tab-works"
          role="tab"
          class="profile-tab"
          data-profile-section="works"
          :class="{ 'profile-tab-active': activeMobileSection === 'works' }"
          :aria-selected="activeMobileSection === 'works'"
          :tabindex="activeMobileSection === 'works' ? 0 : -1"
          aria-controls="profile-panel-works"
          @click="selectMobileSection('works')"
        >
          الأعمال
        </button>
        <button
          type="button"
          id="profile-tab-intro"
          role="tab"
          class="profile-tab"
          data-profile-section="intro"
          :class="{ 'profile-tab-active': activeMobileSection === 'intro' }"
          :aria-selected="activeMobileSection === 'intro'"
          :tabindex="activeMobileSection === 'intro' ? 0 : -1"
          aria-controls="profile-panel-intro"
          @click="selectMobileSection('intro')"
        >
          نبذة عني
        </button>
        <button
          v-if="hasExpertiseSection"
          type="button"
          id="profile-tab-expertise"
          role="tab"
          class="profile-tab"
          data-profile-section="expertise"
          :class="{ 'profile-tab-active': activeMobileSection === 'expertise' }"
          :aria-selected="activeMobileSection === 'expertise'"
          :tabindex="activeMobileSection === 'expertise' ? 0 : -1"
          aria-controls="profile-panel-expertise"
          @click="selectMobileSection('expertise')"
        >
          الخبرات والمهارات
        </button>
        <button
          v-if="hasToolsLanguagesSection"
          type="button"
          id="profile-tab-tools-languages"
          role="tab"
          class="profile-tab"
          data-profile-section="tools-languages"
          :class="{ 'profile-tab-active': activeMobileSection === 'tools-languages' }"
          :aria-selected="activeMobileSection === 'tools-languages'"
          :tabindex="activeMobileSection === 'tools-languages' ? 0 : -1"
          aria-controls="profile-panel-tools-languages"
          @click="selectMobileSection('tools-languages')"
        >
          الأدوات واللغات
        </button>
      </div>
    </div>
  </section>
</template>

<style scoped>
.profile-cover {
  position: relative;
  width: 100%;
  min-height: 260px;
  max-height: 420px;
  aspect-ratio: 2.65 / 1;
  overflow: hidden;
  border-radius: 20px 20px 0 0;
  background: #eceef1;
}

.profile-cover::after {
  position: absolute;
  inset: 0;
  pointer-events: none;
  background:
    linear-gradient(to top, rgba(16, 17, 20, 0.38), rgba(16, 17, 20, 0.07) 40%, transparent 64%),
    radial-gradient(circle at 86% 100%, rgba(226, 29, 29, 0.13), transparent 31%);
  content: "";
}

.profile-cover-image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.profile-cover-fallback {
  display: grid;
  width: 100%;
  height: 100%;
  place-items: center;
  background:
    radial-gradient(circle at 72% 28%, rgba(226, 29, 29, 0.14), transparent 24%),
    linear-gradient(135deg, #1d1e22, var(--ym-feature-dark) 72%);
}

.profile-cover-mark {
  width: 54px;
  height: 54px;
  border: 11px solid #fff;
  border-inline-start-color: var(--ym-red);
  border-radius: 14px;
  box-shadow: 0 8px 22px rgba(17, 17, 17, 0.1);
  transform: rotate(45deg);
}

.profile-identity-area {
  position: relative;
  display: flex;
  min-height: 170px;
  flex-direction: column;
  padding-inline: 32px;
  padding-bottom: 0;
  overflow: visible;
  border: 1px solid var(--ym-border);
  border-top: 0;
  border-radius: 0 0 20px 20px;
  background: var(--ym-surface);
  box-shadow: var(--ym-shadow);
}

.profile-identity-row {
  position: relative;
  z-index: 2;
  display: flex;
  width: 100%;
  min-height: 128px;
  flex: 1;
  align-items: flex-end;
  gap: 24px;
}

.profile-avatar {
  display: flex;
  width: 168px;
  height: 168px;
  flex-shrink: 0;
  align-items: center;
  justify-content: center;
  margin-top: -84px;
  margin-bottom: 14px;
  overflow: hidden;
  border: 5px solid var(--ym-surface);
  outline: 2px solid var(--ym-red);
  outline-offset: 2px;
  border-radius: 50%;
  background: var(--ym-charcoal);
  box-shadow: 0 16px 34px rgba(17, 17, 17, 0.18), 0 0 18px rgba(226, 29, 29, 0.12);
  color: #fff;
  font-size: 32px;
  font-weight: 700;
}

.profile-avatar-image {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}

.profile-identity-copy {
  min-width: 0;
  padding-bottom: 24px;
}

.profile-name {
  margin: 0;
  color: var(--ym-charcoal);
  font-size: clamp(36px, 4vw, 48px);
  font-weight: 800;
  line-height: 1.15;
  overflow-wrap: anywhere;
}

.profile-username {
  display: block;
  width: fit-content;
  max-width: 100%;
  margin-top: 6px;
  color: var(--ym-red);
  font-size: 15px;
  font-weight: 700;
  overflow-wrap: anywhere;
}

.profile-professional-line {
  margin: 8px 0 0;
  color: #555b65;
  font-size: 17px;
  line-height: 1.6;
  overflow-wrap: anywhere;
}

.profile-tabs {
  position: relative;
  display: flex;
  min-height: 52px;
  align-items: stretch;
  gap: 8px;
  margin: 0 10px 10px;
  overflow-x: auto;
  overscroll-behavior-inline: contain;
  scroll-padding-inline: 16px;
  -webkit-overflow-scrolling: touch;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 14px;
  background:
    repeating-linear-gradient(135deg, rgba(184, 126, 72, 0.068) 0 1px, transparent 1px 10px),
    radial-gradient(circle at 92% -36%, rgba(194, 139, 83, 0.14), transparent 31%),
    radial-gradient(circle at 8% 120%, rgba(226, 29, 29, 0.14), transparent 31%),
    linear-gradient(180deg, #222126 0%, #101114 100%);
  box-shadow:
    inset 0 1px 0 rgba(214, 166, 110, 0.11),
    inset 0 -1px 0 rgba(226, 29, 29, 0.1),
    0 10px 22px rgba(17, 17, 17, 0.13);
  scrollbar-width: none;
}

.profile-tabs::-webkit-scrollbar {
  display: none;
}

.profile-tab {
  position: relative;
  display: inline-flex;
  min-width: max-content;
  flex: 0 0 auto;
  align-items: center;
  padding-inline: 18px;
  border: 0;
  background: transparent;
  color: #c4c5cb;
  cursor: pointer;
  font-family: inherit;
  font-size: 15px;
  font-weight: 600;
  white-space: nowrap;
  text-decoration: none;
  transition: color 180ms ease, background 180ms ease;
}

.profile-tab::after {
  position: absolute;
  inset-inline: 18px;
  inset-block-end: 0;
  height: 3px;
  border-radius: 999px 999px 0 0;
  background: var(--ym-red);
  content: "";
  opacity: 0;
  transition: opacity 180ms ease;
}

.profile-tab:hover {
  color: #fff;
  background: rgba(226, 29, 29, 0.07);
}

.profile-tab-active {
  background: rgba(226, 29, 29, 0.1);
  box-shadow: inset 0 1px 0 rgba(218, 171, 119, 0.07), 0 0 14px rgba(226, 29, 29, 0.135);
  color: #fff;
}

.profile-tab-active::after {
  opacity: 1;
}

.profile-tab:focus-visible {
  border-radius: 6px;
  outline: 3px solid rgba(226, 29, 29, 0.28);
  outline-offset: -3px;
}

.profile-tabs-mobile {
  display: none;
}

@media (max-width: 899px) {
  .profile-tabs-desktop {
    display: none;
  }

  .profile-tabs-mobile {
    display: flex;
  }
}

@media (max-width: 699px) {
  .profile-header {
    width: calc(100% + 32px);
    margin-inline: -16px;
  }

  .profile-cover {
    min-height: 180px;
    aspect-ratio: 1.9 / 1;
    border-radius: 0;
  }

  .profile-identity-area {
    min-height: 0;
    padding-inline: 16px;
    border-inline: 0;
    border-radius: 0;
  }

  .profile-identity-row {
    min-width: 0;
    flex-direction: column;
    align-items: center;
    gap: 12px;
  }

  .profile-avatar {
    width: 116px;
    height: 116px;
    margin-top: -58px;
    margin-bottom: 2px;
    margin-inline: auto;
    border-width: 4px;
    font-size: 25px;
  }

  .profile-identity-copy {
    display: flex;
    width: 100%;
    max-width: 100%;
    flex-direction: column;
    align-items: center;
    padding-bottom: 20px;
    box-sizing: border-box;
    text-align: center;
  }

  .profile-name {
    font-size: clamp(32px, 10vw, 38px);
  }

  .profile-username {
    margin-inline: auto;
  }

  .profile-tabs {
    max-width: calc(100% + 32px);
    flex-wrap: nowrap;
    margin: 0 -16px;
    padding-inline: 16px;
    border-radius: 0;
    white-space: nowrap;
  }

  .profile-identity-area::after {
    position: absolute;
    inset-block-end: 0;
    inset-inline-end: 0;
    z-index: 2;
    width: 18px;
    height: 52px;
    pointer-events: none;
    background: linear-gradient(to right, #101114, rgba(16, 17, 20, 0));
    content: "";
  }

  .profile-tab {
    padding-inline: 14px;
  }

  .profile-professional-line {
    max-width: 100%;
    white-space: normal;
  }
}

@media (prefers-reduced-motion: reduce) {
  .profile-cover-image,
  .profile-avatar-image,
  .profile-tab,
  .profile-tab::after {
    transition: none;
  }
}
</style>
