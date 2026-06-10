<template>
    <div
        class="glass-card"
        ref="cardRef"
        :style="cardTransform"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
        role="region"
        aria-label="نموذج تسجيل الدخول"
    >
        <div class="card-glow-border"></div>
        <div class="card-noise"></div>
        <div class="corner corner-top"></div>
        <div class="corner corner-bottom"></div>
        <div class="studio-window-bar" aria-hidden="true">
            <span></span><span></span><span></span>
            <em>YM Studio Console</em>
        </div>

        <!-- Logo & Name -->
        <div class="card-header">
            <div class="access-pill">Secure Studio Access</div>
            <MotionLogo
                ref="motionLogoRef"
                :state="logoState"
                @hover="onLogoHover"
            />
            <div class="name-wrapper" ref="nameWrapperRef">
                <MotionName
                    ref="motionNameRef"
                    :text="nameText"
                    :auto-animate="true"
                />
            </div>
            <div class="subtitle-wrapper">
                <span class="subtitle-line"></span>
                <span class="subtitle">لوحة تحكم يمن موشن</span>
                <span class="subtitle-line"></span>
            </div>
        </div>

        <!-- Form -->
        <form @submit.prevent="$emit('login', { email, password })" class="card-form">
            <transition name="error-fade">
                <div v-if="errorMsg" class="error-message" role="alert">
                    <span class="error-icon">!</span>
                    <span>{{ errorMsg }}</span>
                </div>
            </transition>

            <div class="input-group">
                <label for="login-email" class="input-label">
                    <span class="label-icon"></span>
                    البريد الإلكتروني
                </label>
                <div class="input-wrapper">
                    <input
                        id="login-email"
                        v-model="email"
                        type="email"
                        required
                        autocomplete="email"
                        placeholder="admin@yemenmotion.com"
                        dir="ltr"
                        class="input-field"
                        :class="{ 'input-error': errorMsg }"
                        @focus="$emit('input-focus', $event)"
                        @blur="$emit('input-blur', $event)"
                        aria-required="true"
                    />
                    <div class="input-glow"></div>
                </div>
            </div>

            <div class="input-group">
                <label for="login-password" class="input-label">
                    <span class="label-icon"></span>
                    كلمة المرور
                </label>
                <div class="input-wrapper">
                    <input
                        id="login-password"
                        v-model="password"
                        :type="showPassword ? 'text' : 'password'"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                        dir="ltr"
                        class="input-field"
                        :class="{ 'input-error': errorMsg }"
                        @focus="$emit('input-focus', $event)"
                        @blur="$emit('input-blur', $event)"
                        aria-required="true"
                    />
                    <button
                        type="button"
                        @click="showPassword = !showPassword"
                        class="toggle-password"
                        :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                        tabindex="-1"
                    >
                        {{ showPassword ? 'إخفاء' : 'عرض' }}
                    </button>
                    <div class="input-glow"></div>
                </div>
            </div>

            <button
                type="submit"
                :disabled="loading || disabled"
                class="submit-btn"
                ref="btnRef"
                @click="$emit('btn-click', $event)"
                aria-busy="loading"
            >
                <div class="btn-shimmer"></div>
                <span v-if="loading" class="spinner" aria-hidden="true"></span>
                <span v-else class="btn-text" tabindex="-1">دخول الاستوديو</span>
            </button>
        </form>

        <div class="card-footer">
            <div class="footer-metrics">
                <span>Admin</span>
                <span>Projects</span>
                <span>Assets</span>
            </div>
            <p>© 2026 Yemen Motion Creative Platform</p>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import MotionLogo from './MotionLogo.vue';
import MotionName from './MotionName.vue';

const props = defineProps({
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    errorMsg: { type: [String, null], default: null },
    logoState: { type: String, default: 'idle' },
    nameText: { type: String, default: 'يمن موشن' },
});

defineEmits(['login', 'input-focus', 'input-blur', 'btn-click']);

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const cardRef = ref(null);
const btnRef = ref(null);

const cardTransform = ref({});

function handleMouseMove(e) {
    const card = cardRef.value;
    if (!card) return;
    const rect = card.getBoundingClientRect();
    const x = (e.clientX - rect.left) / rect.width;
    const y = (e.clientY - rect.top) / rect.height;
    const tiltX = (y - 0.5) * -6;
    const tiltY = (x - 0.5) * 6;

    cardTransform.value = {
        transform: `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg)`,
        transition: 'transform 0.15s ease-out',
    };
}

function handleMouseLeave() {
    cardTransform.value = {
        transform: 'perspective(1000px) rotateX(0) rotateY(0)',
        transition: 'transform 0.5s ease-out',
    };
}

function onLogoHover(isHovering) {
    // Forward to parent if needed
}
</script>

<style scoped>
.glass-card {
    position: relative;
    z-index: 3;
    justify-self: start;
    background:
        linear-gradient(145deg, rgba(255, 255, 255, 0.125), rgba(255, 255, 255, 0.035) 42%, rgba(168, 85, 247, 0.08)),
        var(--bg-card);
    backdrop-filter: blur(30px) saturate(145%);
    -webkit-backdrop-filter: blur(30px) saturate(145%);
    border: 1px solid var(--border-glass);
    border-radius: var(--radius-xl);
    padding: 34px 34px 30px;
    width: 100%;
    max-width: 440px;
    box-shadow: var(--shadow-card);
    transform-style: preserve-3d;
    overflow: hidden;
    isolation: isolate;
}

.card-glow-border {
    position: absolute;
    inset: -1px;
    border-radius: inherit;
    background: conic-gradient(from 140deg, transparent, rgba(168, 85, 247, 0.75), rgba(34, 211, 238, 0.68), rgba(251, 113, 133, 0.65), transparent 72%);
    background-size: 100% 100%;
    z-index: -1;
    animation: glowBorder 7s linear infinite;
    opacity: 0.72;
    filter: blur(10px);
}

@keyframes glowBorder {
    from { transform: rotate(0deg) scale(1.1); }
    to { transform: rotate(360deg) scale(1.1); }
}

.card-noise {
    position: absolute;
    inset: 0;
    z-index: -1;
    opacity: 0.42;
    background:
        radial-gradient(circle at 18% 10%, rgba(255, 255, 255, 0.18), transparent 20%),
        radial-gradient(circle at 88% 88%, rgba(34, 211, 238, 0.13), transparent 26%),
        repeating-linear-gradient(135deg, rgba(255, 255, 255, 0.025) 0 1px, transparent 1px 7px);
}

.corner {
    position: absolute;
    width: 120px;
    height: 120px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    pointer-events: none;
}

.corner-top {
    top: 18px;
    right: 18px;
    border-left: none;
    border-bottom: none;
    border-radius: 0 20px 0 0;
}

.corner-bottom {
    left: 18px;
    bottom: 18px;
    border-right: none;
    border-top: none;
    border-radius: 0 0 0 20px;
}

.studio-window-bar {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: -12px -10px 24px;
    padding: 10px 12px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.045);
    border: 1px solid rgba(255, 255, 255, 0.08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.09);
}

.studio-window-bar span {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: var(--accent);
    box-shadow: 0 0 12px rgba(251, 113, 133, 0.45);
}

.studio-window-bar span:nth-child(2) {
    background: var(--gold);
    box-shadow: 0 0 12px rgba(251, 191, 36, 0.45);
}

.studio-window-bar span:nth-child(3) {
    background: var(--secondary);
    box-shadow: 0 0 12px rgba(34, 211, 238, 0.45);
}

.studio-window-bar em {
    margin-right: auto;
    color: rgba(255, 255, 255, 0.34);
    font-size: 10px;
    font-style: normal;
    font-weight: 900;
    letter-spacing: 1px;
    text-transform: uppercase;
}

/* Header */
.card-header {
    text-align: center;
    margin-bottom: 30px;
    position: relative;
}

.access-pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    padding: 8px 14px;
    border-radius: 999px;
    color: rgba(255, 255, 255, 0.66);
    border: 1px solid rgba(255, 255, 255, 0.13);
    background: rgba(255, 255, 255, 0.055);
    font-size: 11px;
    font-weight: 900;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.name-wrapper {
    margin: 16px auto 12px;
    font-size: 28px;
    font-weight: 800;
    color: var(--text-primary);
    direction: rtl;
}

.subtitle-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.subtitle-line {
    width: 52px;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.34), transparent);
}

.subtitle {
    color: var(--text-secondary);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* Error */
.error-fade-enter-active, .error-fade-leave-active {
    transition: all 0.3s ease;
}
.error-fade-enter-from, .error-fade-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

.error-message {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.32);
    color: #FECACA;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    font-size: 13px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.error-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(239, 68, 68, 0.24);
    font-weight: 900;
    flex-shrink: 0;
}

/* Form */
.card-form {
    width: 100%;
}

.input-group {
    margin-bottom: 20px;
}

.input-label {
    display: flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 247, 237, 0.72);
    font-size: 12px;
    font-weight: 800;
    margin-bottom: 8px;
}

.label-icon {
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: linear-gradient(135deg, var(--primary-light), var(--secondary));
    box-shadow: 0 0 14px rgba(34, 211, 238, 0.5);
}

.input-wrapper {
    position: relative;
}

.input-field {
    width: 100%;
    padding: 16px 18px;
    background:
        linear-gradient(135deg, rgba(255, 255, 255, 0.075), rgba(255, 255, 255, 0.025)),
        var(--bg-input);
    border: 1px solid var(--border-input);
    border-radius: 18px;
    color: var(--text-primary);
    font-size: 15px;
    font-family: var(--font-primary);
    transition: all var(--transition-normal);
    outline: none;
    position: relative;
    z-index: 2;
}

.input-field:focus {
    border-color: rgba(34, 211, 238, 0.52);
    background: rgba(255, 255, 255, 0.075);
    box-shadow: 0 0 30px rgba(34, 211, 238, 0.14), inset 0 1px 0 rgba(255, 255, 255, 0.12);
}

.input-field.input-error {
    border-color: rgba(239, 68, 68, 0.4);
}

.input-field::placeholder {
    color: var(--text-muted);
}

.input-glow {
    position: absolute;
    inset: -1px;
    border-radius: 18px;
    background: linear-gradient(135deg, var(--primary-light), var(--secondary), var(--accent));
    opacity: 0;
    z-index: 1;
    transition: opacity var(--transition-normal);
    filter: blur(8px);
}

.input-wrapper:focus-within .input-glow {
    opacity: 0.12;
}

.toggle-password {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    cursor: pointer;
    font-size: 11px;
    font-weight: 900;
    color: rgba(255, 255, 255, 0.58);
    padding: 6px 8px;
    border-radius: 999px;
    opacity: 0.72;
    transition: opacity var(--transition-fast);
    z-index: 3;
}

.toggle-password:hover {
    opacity: 0.8;
}

/* Button */
.submit-btn {
    width: 100%;
    padding: 17px;
    background:
        radial-gradient(circle at 20% 10%, rgba(255, 255, 255, 0.26), transparent 22%),
        linear-gradient(135deg, var(--primary-light), var(--primary-dark) 46%, var(--secondary) 100%);
    background-size: 220% 220%;
    border: none;
    border-radius: 20px;
    color: white;
    font-size: 15px;
    font-weight: 900;
    font-family: var(--font-primary);
    cursor: pointer;
    transition: all var(--transition-normal);
    margin-top: 10px;
    position: relative;
    overflow: hidden;
    animation: gradientShift 4s ease-in-out infinite;
    box-shadow: 0 18px 48px rgba(109, 40, 217, 0.34), 0 0 34px rgba(34, 211, 238, 0.18);
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.btn-shimmer {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.08) 50%,
        transparent 100%
    );
    background-size: 200% 100%;
    animation: shimmer 3s ease-in-out infinite;
    z-index: 1;
    pointer-events: none;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.btn-text {
    position: relative;
    z-index: 2;
}

.submit-btn:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow:
        0 22px 58px -5px var(--shadow-primary),
        0 0 42px var(--shadow-secondary);
}

.submit-btn:active:not(:disabled) {
    transform: translateY(-1px);
}

.submit-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.submit-btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 3px;
}

/* Spinner */
.spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    display: inline-block;
    position: relative;
    z-index: 2;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Footer */
.card-footer {
    text-align: center;
    margin-top: 24px;
}

.footer-metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}

.footer-metrics span {
    padding: 9px 8px;
    border-radius: 14px;
    color: rgba(255, 255, 255, 0.52);
    background: rgba(255, 255, 255, 0.045);
    border: 1px solid rgba(255, 255, 255, 0.07);
    font-size: 10px;
    font-weight: 900;
    letter-spacing: 0.8px;
    text-transform: uppercase;
}

.card-footer p {
    color: rgba(255, 255, 255, 0.15);
    font-size: 11px;
    letter-spacing: 1px;
}

@media (max-width: 768px) {
    .glass-card {
        padding: 28px 22px;
        border-radius: 28px;
    }
    .name-wrapper {
        font-size: 22px;
    }
}
</style>
