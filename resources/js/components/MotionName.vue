<template>
    <div
        class="motion-name"
        ref="nameRef"
        :style="nameStyle"
        @mousemove="handleMouseMove"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
        role="img"
        aria-label="يمن موشن"
    >
        <img src="/name.png" alt="يمن موشن" class="name-image" ref="imgRef">
        <div class="name-shimmer"></div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const nameRef = ref(null);
const imgRef = ref(null);

const props = defineProps({
    text: { type: String, default: 'يمن موشن' },
    errorMode: { type: Boolean, default: false },
});

const mouseX = ref(0);
const mouseY = ref(0);
const hovered = ref(false);

const nameStyle = computed(() => ({
    transform: hovered.value
        ? `perspective(400px) rotateX(${mouseY.value * -5}deg) rotateY(${mouseX.value * 5}deg)`
        : 'none',
    transition: 'transform 0.2s ease-out',
}));

function handleMouseMove(e) {
    if (!nameRef.value) return;
    const rect = nameRef.value.getBoundingClientRect();
    mouseX.value = (e.clientX - rect.left) / rect.width - 0.5;
    mouseY.value = (e.clientY - rect.top) / rect.height - 0.5;
}

function handleMouseEnter() { hovered.value = true; }
function handleMouseLeave() { hovered.value = false; }
</script>

<style scoped>
.motion-name {
    display: inline-block;
    position: relative;
    cursor: default;
    margin: 18px auto 14px;
    padding: 6px 10px;
    border-radius: 16px;
}

.motion-name::before {
    content: '';
    position: absolute;
    inset: -18px -34px;
    background: radial-gradient(ellipse, rgba(168, 85, 247, 0.16), transparent 68%);
    pointer-events: none;
}

.name-image {
    height: 50px;
    display: block;
    position: relative;
    z-index: 1;
    filter:
        drop-shadow(0 0 10px rgba(255, 255, 255, 0.10))
        drop-shadow(0 0 22px rgba(168, 85, 247, 0.18));
    transition: filter 0.3s ease;
}

.name-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.18) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 4s ease-in-out infinite;
    pointer-events: none;
    border-radius: 4px;
    mix-blend-mode: screen;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

.motion-name:hover .name-image {
    filter:
        drop-shadow(0 0 16px rgba(255, 255, 255, 0.14))
        drop-shadow(0 0 32px rgba(34, 211, 238, 0.22));
}

@media (max-width: 768px) {
    .name-image {
        height: 42px;
    }
}
</style>
