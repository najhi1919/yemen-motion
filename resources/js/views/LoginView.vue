<template>
    <div
        class="login-view"
        ref="viewRef"
        @mousemove="handleMouseMove"
        role="main"
        aria-label="صفحة تسجيل الدخول"
    >
        <!-- Three.js Background -->
        <ParticleBackground ref="particleRef" />

        <!-- Gradient overlays -->
        <div class="overlay-gradient" aria-hidden="true"></div>
        <div class="overlay-vignette" aria-hidden="true"></div>
        <div class="aurora aurora-one" aria-hidden="true"></div>
        <div class="aurora aurora-two" aria-hidden="true"></div>
        <div class="orbital-grid" aria-hidden="true"></div>
        <div class="light-beam beam-one" aria-hidden="true"></div>
        <div class="light-beam beam-two" aria-hidden="true"></div>
        <div class="background-fx" aria-hidden="true">
            <span class="cursor-aurora"></span>
            <span class="radial-orbit orbit-one"></span>
            <span class="radial-orbit orbit-two"></span>
            <span class="energy-line line-one"></span>
            <span class="energy-line line-two"></span>
            <span class="spark-field spark-one"></span>
            <span class="spark-field spark-two"></span>
        </div>
        <div class="creative-canvas" aria-hidden="true">
            <div class="floating-card card-brand" tabindex="0">
                <span class="card-dot"></span>
                <strong>Brand Kit</strong>
                <small>12 assets ready</small>
                <div class="mini-palette">
                    <i></i><i></i><i></i><i></i>
                </div>
            </div>

            <div class="floating-card card-render" tabindex="0">
                <span class="render-badge">LIVE</span>
                <div class="render-orbit"></div>
                <strong>3D Render</strong>
                <small>Motion preview</small>
            </div>

            <div class="floating-card card-timeline" tabindex="0">
                <strong>Project Timeline</strong>
                <div class="timeline-rows">
                    <span style="--w: 82%"></span>
                    <span style="--w: 54%"></span>
                    <span style="--w: 68%"></span>
                </div>
            </div>

            <div class="sound-wave" tabindex="0">
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
            </div>
        </div>

        <div class="hero-center" ref="heroRef">
            <div class="stage-kicker">Creative Marketplace OS</div>
            <h1 class="hero-title" ref="heroTitleRef">بوابة المصممين<br>وصناع الحركة</h1>
            <p class="hero-desc">إدارة الطلبات، الملفات، المشاريع، والعملاء داخل تجربة بصرية تليق بمنصة جرافيك احترافية.</p>
            <div class="stage-tags">
                <span>Branding</span>
                <span>Motion</span>
                <span>3D</span>
                <span>Typography</span>
            </div>
            <div class="studio-stats">
                <div>
                    <strong>240+</strong>
                    <span>Creative Orders</span>
                </div>
                <div>
                    <strong>18</strong>
                    <span>Active Designers</span>
                </div>
                <div>
                    <strong>4K</strong>
                    <span>Asset Library</span>
                </div>
            </div>
        </div>

        <!-- Software icons -->
        <div class="software-icons" aria-hidden="true">
            <span class="sw-icon sw-ps" title="Photoshop">Ps</span>
            <span class="sw-icon sw-ai" title="Illustrator">Ai</span>
            <span class="sw-icon sw-ae" title="After Effects">Ae</span>
            <span class="sw-icon sw-figma" title="Figma">Figma</span>
            <span class="sw-icon sw-blender" title="Blender">Bl</span>
            <span class="sw-icon sw-id" title="InDesign">Id</span>
            <span class="sw-icon sw-spline" title="Spline">3D</span>
        </div>

        <!-- Brush strokes -->
        <div class="brush-strokes" aria-hidden="true">
            <span class="brush b1"></span>
            <span class="brush b2"></span>
            <span class="brush b3"></span>
            <span class="brush b4"></span>
        </div>

        <!-- Floating welcome message -->
        <transition name="welcome">
            <div v-if="showWelcome" class="welcome-overlay" ref="welcomeRef">
                <div class="welcome-content">
                    <div class="welcome-icon">✦</div>
                    <h2 class="welcome-title">Welcome Back</h2>
                    <p class="welcome-subtitle">جاري تحويلك إلى لوحة التحكم</p>
                    <div class="welcome-loader">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Login Card -->
        <div class="card-wrapper">
            <transition name="card-transition">
            <GlassLoginCard
                v-if="showCard"
                ref="cardRef"
                :loading="loading"
                :error-msg="errorMsg"
                :logo-state="logoState"
                @login="handleLogin"
                @input-focus="onInputFocus"
                @input-blur="onInputBlur"
                @btn-click="onBtnClick"
            />
            </transition>
        </div>

        <!-- Skip link for accessibility -->
        <a href="#login-email" class="skip-link">تخطى إلى نموذج تسجيل الدخول</a>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import ParticleBackground from '../components/ParticleBackground.vue';
import GlassLoginCard from '../components/GlassLoginCard.vue';
import { useLoginAnimations } from '../composables/useLoginAnimations';

const router = useRouter();
const viewRef = ref(null);
const particleRef = ref(null);
const cardRef = ref(null);
const welcomeRef = ref(null);
const heroRef = ref(null);
const heroTitleRef = ref(null);
const showCard = ref(false);
const showWelcome = ref(false);
const loading = ref(false);
const errorMsg = ref(null);
const logoState = ref('idle');

const { isAnimating, cardEntrance, errorShake, successAnimation, pageTransition, buttonRipple } = useLoginAnimations();

async function handleLogin({ email, password }) {
    if (isAnimating.value) return;
    loading.value = true;
    errorMsg.value = null;

    try {
        const response = await fetch('/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email, password }),
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'بيانات الدخول غير صحيحة');
        }

        // Success flow
        localStorage.setItem('token', data.token);
        logoState.value = 'success';

        // Small delay for success animation to show
        await new Promise(resolve => setTimeout(resolve, 400));

        // Show welcome overlay
        showWelcome.value = true;

        // Wait for welcome animation, then transition
        await new Promise(resolve => setTimeout(resolve, 1200));

        // Page transition
        if (cardRef.value?.$el) {
            pageTransition(cardRef.value.$el, () => {
                router.push('/admin');
            });
        } else {
            router.push('/admin');
        }

    } catch (e) {
        errorMsg.value = e.message;
        logoState.value = 'error';

        // Shake the card
        await nextTick();
        if (cardRef.value?.$el) {
            errorShake(cardRef.value.$el);
        }

        // Reset logo state after error
        setTimeout(() => {
            logoState.value = 'idle';
        }, 1500);
    } finally {
        loading.value = false;
    }
}

function onInputFocus(e) {
    const { inputFocus } = useLoginAnimations();
    inputFocus(e.target);
    // Reset error on input focus
    if (errorMsg.value) {
        errorMsg.value = null;
        logoState.value = 'idle';
    }
}

function onInputBlur(e) {
    const { inputBlur } = useLoginAnimations();
    inputBlur(e.target);
}

function onBtnClick(e) {
    buttonRipple(e.currentTarget, e.clientX, e.clientY);
}

let heroMouseX = 0;
let heroMouseY = 0;
let heroRaf = null;

function handleMouseMove(e) {
    if (particleRef.value?.handleMouseMove) {
        particleRef.value.handleMouseMove(e.clientX, e.clientY);
    }
    if (viewRef.value) {
        viewRef.value.style.setProperty('--mouse-x', `${e.clientX}px`);
        viewRef.value.style.setProperty('--mouse-y', `${e.clientY}px`);
    }
    heroMouseX = e.clientX;
    heroMouseY = e.clientY;
    if (!heroRaf) {
        heroRaf = requestAnimationFrame(updateHeroTilt);
    }
}

function updateHeroTilt() {
    heroRaf = null;
    const el = heroTitleRef.value;
    if (!el) return;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const cx = vw / 2;
    const cy = vh / 2;
    const dx = (heroMouseX - cx) / cx;
    const dy = (heroMouseY - cy) / cy;
    el.style.setProperty('--tx', `${dx * 18}px`);
    el.style.setProperty('--ty', `${dy * 10}px`);
}

onMounted(async () => {
    // Entrance animation sequence
    await nextTick();

    // Show card with GSAP animation
    showCard.value = true;

    await nextTick();
    if (cardRef.value?.$el) {
        cardEntrance(cardRef.value.$el);
    }

    // Focus trap for accessibility
    const firstInput = document.getElementById('login-email');
    if (firstInput) firstInput.focus();
});

onUnmounted(() => {
    if (heroRaf) cancelAnimationFrame(heroRaf);
});
</script>

<style scoped>
.login-view {
    min-height: 100vh;
    display: grid;
    grid-template-columns: minmax(340px, 440px) minmax(0, 1fr);
    gap: clamp(28px, 5vw, 80px);
    padding: clamp(24px, 6vw, 72px);
    background:
        radial-gradient(circle at 18% 20%, rgba(168, 85, 247, 0.22), transparent 34%),
        radial-gradient(circle at 88% 72%, rgba(34, 211, 238, 0.14), transparent 38%),
        linear-gradient(135deg, #05020A 0%, #0B0614 48%, #120719 100%);
    font-family: var(--font-primary);
    position: relative;
    overflow-x: hidden;
    overflow-y: auto;
    direction: ltr;
    isolation: isolate;
    --mouse-x: 50vw;
    --mouse-y: 50vh;
}

.overlay-gradient {
    position: absolute;
    inset: 0;
    z-index: 2;
    background:
        radial-gradient(ellipse at 20% 50%, rgba(168, 85, 247, 0.18) 0%, transparent 52%),
        radial-gradient(ellipse at 80% 50%, rgba(34, 211, 238, 0.12) 0%, transparent 48%),
        radial-gradient(ellipse at 50% 100%, rgba(251, 113, 133, 0.10) 0%, transparent 50%);
    pointer-events: none;
}

.overlay-vignette {
    position: absolute;
    inset: 0;
    z-index: 2;
    background: radial-gradient(ellipse at center, transparent 50%, rgba(10, 10, 15, 0.6) 100%);
    pointer-events: none;
}

.aurora {
    position: absolute;
    z-index: 2;
    width: 520px;
    height: 520px;
    border-radius: 999px;
    filter: blur(58px);
    mix-blend-mode: screen;
    pointer-events: none;
    animation: auroraFloat 10s ease-in-out infinite alternate;
}

.aurora-one {
    top: -160px;
    left: 12%;
    background: rgba(168, 85, 247, 0.22);
}

.aurora-two {
    bottom: -180px;
    right: 16%;
    background: rgba(34, 211, 238, 0.16);
    animation-delay: -4s;
}

@keyframes auroraFloat {
    from { transform: translate3d(-20px, 10px, 0) scale(0.92); }
    to { transform: translate3d(28px, -24px, 0) scale(1.08); }
}

.orbital-grid {
    position: absolute;
    inset: 0;
    z-index: 2;
    opacity: 0.16;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 64px 64px;
    mask-image: radial-gradient(circle at 48% 50%, black 0%, transparent 72%);
    pointer-events: none;
}

.light-beam {
    position: absolute;
    z-index: 2;
    width: 520px;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.32), transparent);
    filter: blur(0.5px);
    pointer-events: none;
}

.background-fx {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
    overflow: hidden;
}

.cursor-aurora {
    position: absolute;
    left: var(--mouse-x);
    top: var(--mouse-y);
    width: 420px;
    height: 420px;
    border-radius: 999px;
    background:
        radial-gradient(circle, rgba(34, 211, 238, 0.18) 0%, rgba(168, 85, 247, 0.10) 34%, transparent 68%);
    filter: blur(34px);
    transform: translate(-50%, -50%);
    mix-blend-mode: screen;
    opacity: 0.72;
    transition: left 0.18s ease-out, top 0.18s ease-out;
}

.radial-orbit {
    position: absolute;
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow:
        inset 0 0 60px rgba(168, 85, 247, 0.08),
        0 0 80px rgba(34, 211, 238, 0.06);
    opacity: 0.48;
    animation: orbitPulse 9s ease-in-out infinite alternate;
}

.orbit-one {
    width: 520px;
    height: 520px;
    right: 6%;
    top: 10%;
}

.orbit-two {
    width: 360px;
    height: 360px;
    left: 22%;
    bottom: 8%;
    animation-delay: -4s;
}

.energy-line {
    position: absolute;
    width: 42vw;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.35), rgba(34, 211, 238, 0.22), transparent);
    filter: blur(0.4px);
    opacity: 0.28;
    animation: lineDrift 7s ease-in-out infinite alternate;
}

.line-one {
    top: 24%;
    right: -8%;
    transform: rotate(-18deg);
}

.line-two {
    bottom: 18%;
    left: 16%;
    transform: rotate(-18deg);
    animation-delay: -3s;
}

.spark-field {
    position: absolute;
    width: 240px;
    height: 240px;
    opacity: 0.34;
    background-image:
        radial-gradient(circle, rgba(255, 255, 255, 0.8) 0 1px, transparent 1.8px),
        radial-gradient(circle, rgba(34, 211, 238, 0.55) 0 1px, transparent 1.7px);
    background-size: 42px 42px, 58px 58px;
    background-position: 0 0, 20px 12px;
    animation: sparkFloat 10s linear infinite;
}

.spark-one {
    right: 10%;
    bottom: 12%;
}

.spark-two {
    left: 10%;
    top: 10%;
    opacity: 0.24;
    animation-duration: 13s;
    animation-direction: reverse;
}

@keyframes orbitPulse {
    from { transform: scale(0.96) rotate(0deg); opacity: 0.28; }
    to { transform: scale(1.04) rotate(10deg); opacity: 0.58; }
}

@keyframes lineDrift {
    from { translate: -18px 0; opacity: 0.14; }
    to { translate: 22px -10px; opacity: 0.34; }
}

@keyframes sparkFloat {
    from { transform: translate3d(0, 0, 0); background-position: 0 0, 20px 12px; }
    to { transform: translate3d(10px, -16px, 0); background-position: 42px -42px, 78px -46px; }
}

.creative-canvas {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}

.creative-canvas > * {
    pointer-events: auto;
}

.floating-card {
    position: absolute;
    display: flex;
    flex-direction: column;
    gap: 8px;
    min-width: 154px;
    padding: 18px;
    border-radius: 24px;
    color: rgba(255, 255, 255, 0.86);
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0.045)),
        rgba(12, 8, 24, 0.44);
    border: 1px solid rgba(255, 255, 255, 0.16);
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.36), inset 0 1px 0 rgba(255, 255, 255, 0.18);
    backdrop-filter: blur(22px);
    transform-style: preserve-3d;
    cursor: default;
    transition:
        transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 0.35s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.35s ease;
}

.floating-card:hover {
    transform: scale(1.08) translateY(-6px);
    border-color: rgba(255, 255, 255, 0.32);
    box-shadow: 0 32px 100px rgba(0, 0, 0, 0.46), 0 0 40px rgba(168, 85, 247, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.24);
    animation-play-state: paused;
}

.floating-card strong {
    font-size: 13px;
    font-weight: 950;
    letter-spacing: 0.2px;
}

.floating-card small {
    color: rgba(255, 255, 255, 0.48);
    font-size: 11px;
    font-weight: 700;
}

.card-brand {
    top: 2%;
    right: 2%;
    animation: driftOne 8s ease-in-out infinite alternate;
}

.card-render {
    bottom: 2%;
    right: 2%;
    align-items: center;
    min-width: 170px;
    animation: driftTwo 9s ease-in-out infinite alternate;
}

.card-timeline {
    bottom: 2%;
    left: 2%;
    min-width: 210px;
    animation: driftThree 10s ease-in-out infinite alternate;
}

@keyframes driftOne {
    from { transform: translate3d(0, 0, 0) rotate(-3deg); }
    to { transform: translate3d(10px, 16px, 0) rotate(4deg); }
}

@keyframes driftTwo {
    from { transform: translate3d(0, 0, 0) rotate(4deg); }
    to { transform: translate3d(-12px, -10px, 0) rotate(-3deg); }
}

@keyframes driftThree {
    from { transform: translate3d(0, 0, 0) rotate(2deg); }
    to { transform: translate3d(12px, -14px, 0) rotate(-3deg); }
}

.card-dot {
    width: 11px;
    height: 11px;
    border-radius: 999px;
    background: var(--success);
    box-shadow: 0 0 18px rgba(16, 185, 129, 0.8);
}

.mini-palette {
    display: flex;
    gap: 6px;
    margin-top: 4px;
}

.mini-palette i {
    width: 26px;
    height: 26px;
    border-radius: 9px;
    background: var(--primary);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.28);
}

.mini-palette i:nth-child(2) { background: var(--secondary); }
.mini-palette i:nth-child(3) { background: var(--accent); }
.mini-palette i:nth-child(4) { background: var(--gold); }

.render-badge {
    align-self: flex-end;
    padding: 5px 8px;
    border-radius: 999px;
    background: rgba(16, 185, 129, 0.16);
    color: #86EFAC;
    border: 1px solid rgba(16, 185, 129, 0.28);
    font-size: 10px;
    font-weight: 950;
}

.render-orbit {
    width: 74px;
    height: 74px;
    border-radius: 50%;
    margin: 0 auto 2px;
    background:
        radial-gradient(circle at 35% 32%, rgba(255, 255, 255, 0.78), transparent 10%),
        radial-gradient(circle, rgba(168, 85, 247, 0.55), rgba(34, 211, 238, 0.18) 52%, transparent 70%);
    border: 1px solid rgba(255, 255, 255, 0.14);
    box-shadow: 0 0 34px rgba(168, 85, 247, 0.34);
    animation: renderPulse 3s ease-in-out infinite;
}

@keyframes renderPulse {
    0%, 100% { transform: scale(0.94); filter: hue-rotate(0deg); }
    50% { transform: scale(1.05); filter: hue-rotate(32deg); }
}

.timeline-rows {
    display: grid;
    gap: 8px;
    margin-top: 4px;
}

.timeline-rows span {
    width: var(--w);
    height: 8px;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--primary-light), var(--secondary));
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.18);
    animation: timelineGlow 2.6s ease-in-out infinite alternate;
}

.timeline-rows span:nth-child(2) { animation-delay: -0.9s; }
.timeline-rows span:nth-child(3) { animation-delay: -1.5s; }

@keyframes timelineGlow {
    from { opacity: 0.42; transform: scaleX(0.9); transform-origin: right; }
    to { opacity: 1; transform: scaleX(1); transform-origin: right; }
}

.sound-wave {
    position: absolute;
    left: 34%;
    bottom: 2%;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 18px 20px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.055);
    border: 1px solid rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(18px);
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.32);
    cursor: default;
    transition:
        transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
        border-color 0.35s ease,
        box-shadow 0.35s ease;
}

.sound-wave:hover {
    transform: scale(1.08) translateY(-4px);
    border-color: rgba(255, 255, 255, 0.32);
    box-shadow: 0 32px 100px rgba(0, 0, 0, 0.46), 0 0 40px rgba(34, 211, 238, 0.18);
}

.sound-wave span {
    display: block;
    width: 5px;
    height: 18px;
    border-radius: 999px;
    background: linear-gradient(180deg, var(--secondary), var(--primary-light));
    animation: wave 1s ease-in-out infinite;
}

.sound-wave:hover span {
    animation-duration: 0.5s;
}

.sound-wave span:nth-child(2) { animation-delay: -0.15s; }
.sound-wave span:nth-child(3) { animation-delay: -0.3s; }
.sound-wave span:nth-child(4) { animation-delay: -0.45s; }
.sound-wave span:nth-child(5) { animation-delay: -0.6s; }
.sound-wave span:nth-child(6) { animation-delay: -0.75s; }
.sound-wave span:nth-child(7) { animation-delay: -0.9s; }

@keyframes wave {
    0%, 100% { height: 14px; opacity: 0.45; }
    50% { height: 42px; opacity: 1; }
}

.floating-card:focus-visible,
.sound-wave:focus-visible {
    outline: 2px solid var(--primary-light);
    outline-offset: 4px;
}

.beam-one {
    top: 20%;
    right: -120px;
    transform: rotate(-28deg);
}

.beam-two {
    bottom: 22%;
    left: -120px;
    transform: rotate(-28deg);
    opacity: 0.45;
}

/* Hero center — grid item, order 2 = right column */
.hero-center {
    grid-column: 2;
    grid-row: 1;
    justify-self: center;
    align-self: center;
    z-index: 4;
    max-width: 760px;
    width: 100%;
    color: var(--text-primary);
    text-align: center;
    padding: 40px 20px;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    direction: rtl;
}

.stage-kicker {
    display: inline-flex;
    padding: 8px 14px;
    margin-bottom: 16px;
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.06);
    color: rgba(255, 247, 237, 0.72);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
    pointer-events: auto;
}

.hero-title {
    margin: 0;
    font-size: clamp(48px, 7vw, 96px);
    line-height: 1.08;
    letter-spacing: -1px;
    background: none;
    -webkit-background-clip: initial;
    background-clip: border-box;
    color: #fff;
    text-shadow:
        0 14px 42px rgba(168, 85, 247, 0.22),
        0 0 34px rgba(34, 211, 238, 0.18);
    filter: none;
    /* Subtle 2D parallax only — no 3D perspective that clips */
    transform: translate(var(--tx, 0px), var(--ty, 0px));
    transition: transform 0.1s ease-out, filter 0.4s ease;
    will-change: transform;
    pointer-events: auto;
}

.hero-desc {
    max-width: 520px;
    margin: 18px auto 0;
    color: var(--text-secondary);
    font-size: clamp(15px, 1.5vw, 18px);
    line-height: 1.9;
    pointer-events: auto;
    transition: transform 0.1s ease-out;
    transform: translateY(calc(var(--ty, 0px) * 0.5));
}

.stage-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
    justify-content: center;
    pointer-events: auto;
}

.stage-tags span {
    padding: 8px 14px;
    border-radius: 999px;
    color: rgba(255, 255, 255, 0.78);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.10), rgba(255, 255, 255, 0.035));
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.14);
    font-size: 12px;
    font-weight: 800;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
    cursor: default;
}

.stage-tags span:hover {
    transform: translateY(-3px) scale(1.04);
    border-color: rgba(168, 85, 247, 0.4);
    box-shadow: 0 8px 24px rgba(168, 85, 247, 0.18);
}

.studio-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    max-width: 440px;
    margin: 18px auto 0;
    pointer-events: auto;
}

.studio-stats div {
    padding: 14px 14px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.055);
    border: 1px solid rgba(255, 255, 255, 0.105);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(16px);
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    cursor: default;
}

.studio-stats div:hover {
    transform: translateY(-4px) scale(1.02);
    border-color: rgba(34, 211, 238, 0.3);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2), 0 0 30px rgba(34, 211, 238, 0.08);
}

.studio-stats strong {
    display: block;
    color: #fff;
    font-size: 24px;
    font-weight: 950;
    line-height: 1;
}

.studio-stats span {
    display: block;
    margin-top: 8px;
    color: rgba(255, 247, 237, 0.48);
    font-size: 11px;
    font-weight: 800;
    line-height: 1.4;
}

/* Software Icons */
.software-icons {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
}

.sw-icon {
    position: absolute;
    padding: 14px 20px;
    border-radius: 18px;
    font-size: 17px;
    font-weight: 950;
    letter-spacing: 0.5px;
    color: rgba(255, 255, 255, 0.92);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(8px);
    transform-style: preserve-3d;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.35s ease;
    animation: swFloat 8s ease-in-out infinite alternate;
    pointer-events: auto;
    cursor: default;
}

.sw-icon:hover {
    transform: scale(1.18) translateY(-8px);
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.2);
    animation-play-state: paused;
}

@keyframes swFloat {
    from { transform: translate3d(0, 0, 0) rotate(-6deg); }
    to { transform: translate3d(12px, -18px, 0) rotate(6deg); }
}

.sw-ps     { background: linear-gradient(135deg, #001e36, #003366); }
.sw-ai     { background: linear-gradient(135deg, #330000, #660000); }
.sw-ae     { background: linear-gradient(135deg, #1a0033, #3b0066); }
.sw-figma  { background: linear-gradient(135deg, #33001a, #660033); }
.sw-blender{ background: linear-gradient(135deg, #332600, #664d00); }
.sw-id     { background: linear-gradient(135deg, #1a0033, #330066); }
.sw-spline { background: linear-gradient(135deg, #003333, #006666); }

/* Icon positions — placed in safe margins only */
.sw-ps     { top: 3%; left: 54%; }
.sw-ai     { top: 5%; left: 78%; }
.sw-ae     { top: 3%; left: 10%; }
.sw-figma  { top: 92%; left: 78%; }
.sw-blender{ top: 90%; left: 48%; }
.sw-id     { top: 92%; left: 10%; }
.sw-spline { top: 5%; left: 42%; }

.sw-icon:nth-child(2) { animation-delay: -1.4s; animation-duration: 9s; }
.sw-icon:nth-child(3) { animation-delay: -2.8s; animation-duration: 7s; }
.sw-icon:nth-child(4) { animation-delay: -0.7s; animation-duration: 10s; }
.sw-icon:nth-child(5) { animation-delay: -3.5s; animation-duration: 8.5s; }
.sw-icon:nth-child(6) { animation-delay: -1.9s; animation-duration: 9.5s; }
.sw-icon:nth-child(7) { animation-delay: -4.2s; animation-duration: 7.5s; }

/* Brush Strokes */
.brush-strokes {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}

.brush {
    position: absolute;
    border-radius: 999px;
    opacity: 0.5;
    animation: brushSway 12s ease-in-out infinite alternate;
}

.b1 {
    width: 600px;
    height: 24px;
    top: 12%;
    left: -140px;
    background: linear-gradient(90deg, transparent 0%, rgba(168, 85, 247, 0.6) 30%, rgba(168, 85, 247, 0.7) 50%, rgba(168, 85, 247, 0.6) 70%, transparent 100%);
    filter: blur(4px);
    mix-blend-mode: screen;
    animation-name: brushSway1;
}

.b2 {
    width: 480px;
    height: 20px;
    bottom: 14%;
    right: -100px;
    background: linear-gradient(90deg, transparent 0%, rgba(34, 211, 238, 0.5) 25%, rgba(34, 211, 238, 0.6) 50%, rgba(34, 211, 238, 0.5) 75%, transparent 100%);
    filter: blur(4px);
    mix-blend-mode: screen;
    animation-name: brushSway2;
    animation-delay: -4s;
}

.b3 {
    width: 360px;
    height: 16px;
    top: 55%;
    left: 4%;
    background: linear-gradient(90deg, transparent 0%, rgba(251, 113, 133, 0.45) 25%, rgba(251, 113, 133, 0.55) 50%, rgba(251, 113, 133, 0.45) 75%, transparent 100%);
    filter: blur(3px);
    mix-blend-mode: screen;
    animation-name: brushSway3;
    animation-delay: -7s;
}

.b4 {
    width: 300px;
    height: 14px;
    top: 4%;
    right: 26%;
    background: linear-gradient(90deg, transparent 0%, rgba(250, 204, 21, 0.4) 25%, rgba(250, 204, 21, 0.5) 50%, rgba(250, 204, 21, 0.4) 75%, transparent 100%);
    filter: blur(3px);
    mix-blend-mode: screen;
    animation-name: brushSway4;
    animation-delay: -9s;
}

@keyframes brushSway1 {
    from { transform: rotate(32deg) translateX(-20px); opacity: 0.35; }
    to   { transform: rotate(32deg) translateX(24px);  opacity: 0.7; }
}
@keyframes brushSway2 {
    from { transform: rotate(-26deg) translateX(-20px); opacity: 0.35; }
    to   { transform: rotate(-26deg) translateX(24px);  opacity: 0.7; }
}
@keyframes brushSway3 {
    from { transform: rotate(62deg) translateX(-16px); opacity: 0.3; }
    to   { transform: rotate(62deg) translateX(20px);  opacity: 0.6; }
}
@keyframes brushSway4 {
    from { transform: rotate(-42deg) translateX(-14px); opacity: 0.25; }
    to   { transform: rotate(-42deg) translateX(18px);  opacity: 0.55; }
}

/* Card wrapper — grid item, order 1 = left column */
.card-wrapper {
    grid-column: 1;
    grid-row: 1;
    justify-self: start;
    align-self: center;
    z-index: 5;
    direction: rtl;
    width: 100%;
}

/* Card transition */
.card-transition-enter-active {
    transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
}
.card-transition-enter-from {
    opacity: 0;
    transform: translateY(40px) scale(0.95);
}
.card-transition-leave-active {
    transition: all 0.5s cubic-bezier(0.5, 0, 0.5, 1);
}
.card-transition-leave-to {
    opacity: 0;
    transform: scale(0.9);
    filter: blur(10px);
}

/* Welcome overlay */
.welcome-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(10, 10, 15, 0.9);
    backdrop-filter: blur(20px);
}

.welcome-enter-active {
    transition: all 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
.welcome-enter-from {
    opacity: 0;
}
.welcome-leave-active {
    transition: all 0.4s ease;
}
.welcome-leave-to {
    opacity: 0;
}

.welcome-content {
    text-align: center;
    animation: welcomeFloat 2s ease-in-out infinite;
}

@keyframes welcomeFloat {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}

.welcome-icon {
    font-size: 48px;
    margin-bottom: 20px;
    animation: welcomeSpin 3s linear infinite;
}

@keyframes welcomeSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.welcome-title {
    color: white;
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 12px;
    font-family: var(--font-primary);
}

.welcome-subtitle {
    color: rgba(255, 255, 255, 0.5);
    font-size: 16px;
}

.welcome-loader {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-top: 24px;
}

.welcome-loader span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: loaderDot 1s ease-in-out infinite;
}

.welcome-loader span:nth-child(2) {
    animation-delay: 0.2s;
    background: var(--secondary);
}

.welcome-loader span:nth-child(3) {
    animation-delay: 0.4s;
    background: var(--accent);
}

@keyframes loaderDot {
    0%, 100% { transform: scale(0.5); opacity: 0.3; }
    50% { transform: scale(1); opacity: 1; }
}

/* Skip link */
.skip-link {
    position: absolute;
    top: -100px;
    left: 16px;
    z-index: 200;
    background: var(--primary);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
}

.skip-link:focus {
    top: 16px;
}

@media (max-width: 1180px) {
    .login-view {
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
    }

    .card-wrapper {
        max-width: 380px;
    }
    
    .hero-center {
        max-width: 600px;
    }

    .software-icons,
    .creative-canvas {
        display: none;
    }
}

@media (max-width: 768px) {
    .login-view {
        grid-template-columns: 1fr;
        padding: 40px 22px;
    }

    .hero-center {
        grid-column: 1;
        grid-row: 1;
        margin-bottom: 24px;
    }

    .card-wrapper {
        grid-column: 1;
        grid-row: 2;
        justify-self: center;
        width: 100%;
        max-width: 420px;
        margin: 0 auto;
    }

    .hero-title {
        font-size: clamp(36px, 12vw, 52px);
    }

    .hero-desc {
        font-size: 14px;
    }

    .studio-stats {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        max-width: 100%;
    }

    .software-icons,
    .background-fx,
    .brush-strokes,
    .creative-canvas {
        display: none;
    }

    .welcome-title {
        font-size: 28px;
    }
    .welcome-icon {
        font-size: 36px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cursor-aurora,
    .radial-orbit,
    .energy-line,
    .spark-field {
        animation: none;
        transition: none;
    }
}
</style>
