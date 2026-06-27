<template>
  <div class="ym-background-watermark pointer-events-none select-none" aria-hidden="true">
    <div class="ym-watermark-identity">
      <img src="/logo.svg" alt="" class="ym-watermark-logo" />
      <img src="/name.svg" alt="" class="ym-watermark-name" />
    </div>
    <div class="ym-watermark-glow ym-watermark-glow-one" />
    <div class="ym-watermark-glow ym-watermark-glow-two" />
  </div>
</template>

<style scoped>
.ym-background-watermark {
  position: fixed;
  inset: 0;
  width: 100vw;
  height: 100dvh;
  z-index: 1;
  overflow: hidden;
}

.ym-watermark-identity {
  position: absolute;
  top: 52%;
  left: 50%;
  display: grid;
  width: min(56vw, 720px);
  justify-items: center;
  gap: clamp(0.8rem, 2vw, 1.6rem);
  transform: translate(-50%, -50%) rotate(-4deg);
}

.ym-watermark-logo {
  width: min(33vw, 390px);
  aspect-ratio: 1;
  object-fit: contain;
  opacity: var(--ym-watermark-opacity-logo, 0.075);
  filter: drop-shadow(0 0 42px rgba(190, 0, 1, 0.28));
}

.ym-watermark-name {
  width: min(46vw, 610px);
  height: auto;
  object-fit: contain;
  opacity: var(--ym-watermark-opacity-name, 0.06);
  filter: drop-shadow(0 0 34px rgba(99, 102, 241, 0.24));
}

.ym-watermark-glow {
  position: absolute;
  border-radius: 999px;
  filter: blur(70px);
  opacity: 0.16;
}

.ym-watermark-glow-one {
  top: 16%;
  left: 24%;
  width: 22rem;
  height: 22rem;
  background: rgba(190, 0, 1, 0.28);
}

.ym-watermark-glow-two {
  right: 12%;
  bottom: 12%;
  width: 26rem;
  height: 26rem;
  background: rgba(99, 102, 241, 0.24);
}

@media (max-width: 900px) {
  .ym-watermark-identity {
    width: min(76vw, 560px);
  }

  .ym-watermark-logo {
    width: min(52vw, 330px);
  }

  .ym-watermark-name {
    width: min(68vw, 470px);
  }
}
</style>
