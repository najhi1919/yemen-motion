<template>
    <div
        class="motion-logo"
        ref="logoRef"
        :class="{
            'state-success': state === 'success',
            'state-error': state === 'error',
        }"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
        role="img"
        aria-label="Yemen Motion Logo"
        tabindex="0"
    >
        <img src="/logo.png" alt="Yemen Motion" class="logo-img" :style="imgStyle">
        <div class="logo-ring" ref="ringRef"></div>
        <div class="logo-ring logo-ring-two"></div>
        <div class="glow-default"></div>
        <div class="glow-success"></div>
        <div class="glow-error"></div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    state: { type: String, default: 'idle' },
});

const logoRef = ref(null);
const tiltX = ref(0);
const tiltY = ref(0);
const glowIntensity = ref(0.3);

const imgStyle = computed(() => ({
    transform: `perspective(600px) rotateX(${tiltX.value}deg) rotateY(${tiltY.value}deg)`,
    transition: 'transform 0.15s ease-out',
}));

function handleMouseMove(e) {
    if (!logoRef.value) return;
    const rect = logoRef.value.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    tiltX.value = (y - 0.5) * -10;
    tiltY.value = (x - 0.5) * 10;
    glowIntensity.value = 0.3 + (1 - Math.abs(x - 0.5) * 2) * 0.2;
}

function handleMouseLeave() {
    tiltX.value = 0;
    tiltY.value = 0;
    glowIntensity.value = 0.3;
}
</script>

<style scoped>
.motion-logo {
    position: relative;
    display: inline-block;
    cursor: pointer;
    outline: none;
    width: 112px;
    height: 112px;
    margin: 0 auto;
}

.motion-logo:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 8px;
    border-radius: 50%;
}

.logo-img {
    width: 112px;
    height: 112px;
    position: relative;
    z-index: 2;
    filter:
        drop-shadow(0 0 18px rgba(168, 85, 247, 0.58))
        drop-shadow(0 0 34px rgba(34, 211, 238, 0.18));
    transition: filter 0.4s ease;
    will-change: transform;
}

.logo-ring {
    position: absolute;
    inset: -10px;
    border-radius: 50%;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-top-color: rgba(192, 132, 252, 0.85);
    border-left-color: rgba(34, 211, 238, 0.58);
    animation: ringSpin 8s linear infinite;
    z-index: 1;
}

.logo-ring-two {
    inset: -22px;
    opacity: 0.5;
    animation: ringSpin 12s linear infinite reverse;
}

@keyframes ringSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.glow-default,
.glow-success,
.glow-error {
    position: absolute;
    inset: -30px;
    border-radius: 50%;
    z-index: 0;
    transition: opacity 0.4s ease;
    pointer-events: none;
}

.glow-default {
    background:
        radial-gradient(circle, rgba(168, 85, 247, 0.58) 0%, rgba(34, 211, 238, 0.16) 36%, transparent 72%);
    opacity: v-bind(glowIntensity);
}

.glow-success {
    background: radial-gradient(circle, rgba(16, 185, 129, 0.6) 0%, transparent 70%);
    opacity: 0;
}

.glow-error {
    background: radial-gradient(circle, rgba(239, 68, 68, 0.6) 0%, transparent 70%);
    opacity: 0;
}

.state-success .glow-default { opacity: 0; }
.state-success .glow-success { opacity: 1; }
.state-success .logo-img {
    filter: drop-shadow(0 0 30px rgba(16, 185, 129, 0.6));
}

.state-error .glow-default { opacity: 0; }
.state-error .glow-error { opacity: 1; }
.state-error .logo-img {
    animation: shake 0.4s ease-in-out;
    filter: drop-shadow(0 0 30px rgba(239, 68, 68, 0.6));
}

@keyframes shake {
    0%, 100% { transform: translateX(0) rotateX(0) rotateY(0); }
    20% { transform: translateX(-4px); }
    40% { transform: translateX(4px); }
    60% { transform: translateX(-3px); }
    80% { transform: translateX(3px); }
}
</style>
