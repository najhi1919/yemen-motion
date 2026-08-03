<template>
  <header class="public-header">
    <a
      href="#public-profile-content"
      class="public-skip-link rounded-xl bg-[#111111] px-4 py-3 text-sm font-bold text-white focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
    >
      الانتقال إلى المحتوى
    </a>

    <nav class="public-header-inner" aria-label="التنقل العام">
      <NuxtLink
        to="/"
        class="public-logo-lockup rounded-lg focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        aria-label="العودة إلى الصفحة الرئيسية"
      >
        <span class="public-brand-part public-brand-part--logo" aria-hidden="true">
          <img src="/logo.svg" alt="" class="public-brand-logo">
        </span>

        <span class="public-brand-part public-brand-part--name" aria-hidden="true">
          <img src="/name.svg" alt="" class="public-brand-name">
        </span>
      </NuxtLink>

      <NuxtLink
        to="/"
        class="public-home-link focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-red-200"
        aria-label="الرئيسية"
        title="الرئيسية"
      >
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <path d="m4 10 8-6.5 8 6.5" />
          <path d="M6.5 9.5V20h11V9.5M9.5 20v-6h5v6" />
        </svg>
      </NuxtLink>
    </nav>
  </header>
</template>

<style scoped>
.public-header {
  position: relative;
  inset: auto;
  z-index: auto;
  height: 60px;
  transform: none;
  border-bottom: 1px solid var(--ym-border);
  background: var(--ym-surface);
  box-shadow: 0 4px 16px rgba(17, 17, 17, 0.035);
}

.public-header::after {
  position: absolute;
  inset-inline: 0;
  inset-block-end: -1px;
  z-index: 1;
  height: 3px;
  pointer-events: none;
  background:
    linear-gradient(
      90deg,
      transparent 0%,
      rgba(55, 42, 34, 0.62) 14%,
      #202126 42%,
      #17181c 58%,
      rgba(226, 29, 29, 0.58) 82%,
      transparent 100%
    ),
    repeating-linear-gradient(
      135deg,
      rgba(164, 108, 72, 0.3) 0 3px,
      rgba(31, 32, 36, 0.12) 3px 7px
    );
  box-shadow:
    0 2px 9px rgba(226, 29, 29, 0.1),
    inset 0 1px 0 rgba(255, 255, 255, 0.12);
  content: "";
}

.public-header-inner {
  position: relative;
  z-index: 2;
  width: 100%;
  max-width: 1180px;
  height: 100%;
  margin-inline: auto;
  padding-inline: 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-sizing: border-box;
}

.public-logo-lockup {
  position: relative;
  width: 162px;
  max-width: 40vw;
  height: 36px;
  display: flex;
  align-items: center;
  gap: 8px;
  text-decoration: none;
}

.public-brand-part {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition:
    transform 190ms cubic-bezier(0.22, 1, 0.36, 1),
    filter 190ms ease;
}

.public-brand-part--logo:hover {
  transform: translateY(-2px) rotate(-3deg) scale(1.07);
  filter:
    brightness(1.04)
    drop-shadow(0 5px 8px rgba(226, 29, 29, 0.18));
}

.public-brand-part--name {
  min-width: 0;
  flex: 1 1 auto;
  transform-origin: center center;
}

.public-brand-part--name::after {
  position: absolute;
  inset-inline: 8%;
  inset-block-end: -3px;
  height: 2px;
  border-radius: 999px;
  background:
    linear-gradient(
      90deg,
      transparent,
      rgba(164, 108, 72, 0.7),
      rgba(226, 29, 29, 0.85),
      transparent
    );
  box-shadow: 0 1px 6px rgba(226, 29, 29, 0.16);
  content: "";
  opacity: 0;
  transform: scaleX(0.25);
  transform-origin: center;
  transition:
    opacity 180ms ease,
    transform 210ms cubic-bezier(0.22, 1, 0.36, 1);
}

.public-brand-part--name:hover {
  transform: translateY(-2px) scale(1.025);
  filter:
    brightness(1.035)
    drop-shadow(0 4px 7px rgba(164, 108, 72, 0.14));
}

.public-brand-part--name:hover::after,
.public-logo-lockup:focus-visible .public-brand-part--name::after {
  opacity: 1;
  transform: scaleX(1);
}

.public-brand-logo {
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  object-fit: contain;
}

.public-brand-name {
  min-width: 0;
  height: 23px;
  flex: 1 1 auto;
  object-fit: contain;
  object-position: right center;
}

.public-home-link {
  width: 40px;
  height: 40px;
  min-height: 40px;
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  border: 1px solid rgba(226, 29, 29, 0.22);
  border-radius: 10px;
  background: rgba(226, 29, 29, 0.035);
  color: var(--ym-red);
  box-shadow: 0 5px 14px rgba(17, 17, 17, 0.055);
  transition: color 180ms ease, border-color 180ms ease, background 180ms ease, box-shadow 180ms ease;
}

.public-home-link svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.public-home-link:hover {
  color: var(--ym-red);
  border-color: rgba(226, 29, 29, 0.44);
  background: rgba(226, 29, 29, 0.08);
  box-shadow: 0 6px 16px rgba(226, 29, 29, 0.1);
}

@media (prefers-reduced-motion: reduce) {
  .public-brand-part,
  .public-brand-part--name::after {
    transition: none;
  }

  .public-brand-part--logo:hover,
  .public-brand-part--name:hover {
    transform: none;
  }
}

.public-skip-link {
  position: fixed;
  inset-block-start: 0;
  inset-inline-start: 0;
  z-index: 60;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.public-skip-link:focus-visible {
  inset-block-start: 12px;
  inset-inline-start: 16px;
  inline-size: auto;
  block-size: auto;
  overflow: visible;
  clip-path: none;
}

@media (max-width: 699px) {
  .public-header {
    height: 56px;
  }

  .public-header-inner {
    gap: 12px;
    padding-inline: 16px;
    flex-wrap: nowrap;
  }

  .public-logo-lockup {
    width: 144px;
    min-width: 0;
    flex-shrink: 1;
  }

  .public-home-link {
    flex-shrink: 0;
  }
}
</style>
