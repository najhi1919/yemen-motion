<template>
    <div class="admin-layout" dir="rtl">
        <div v-if="loading" class="loading-screen">
            <div class="loading-content">
                <img src="/logo.png" alt="YM" class="loading-logo">
                <div class="loading-bar">
                    <div class="loading-bar-fill"></div>
                </div>
            </div>
        </div>
        <div v-else class="admin-content">
            <header class="admin-header">
                <div class="header-left">
                    <img src="/logo.png" alt="YM" class="header-logo">
                    <span class="header-divider"></span>
                    <span class="header-title">لوحة التحكم</span>
                </div>
                <div class="header-right">
                    <div class="header-user">
                        <span class="header-greeting">مرحباً،</span>
                        <span class="header-name">{{ user?.name || 'موظف' }}</span>
                    </div>
                    <button @click="handleLogout" class="btn-logout">
                        تسجيل خروج
                    </button>
                </div>
            </header>
            <main class="admin-main">
                <div class="welcome-section">
                    <h1 class="welcome-title">مرحباً بك في لوحة التحكم</h1>
                    <p class="welcome-subtitle">تم تسجيل الدخول بنجاح</p>
                </div>
            </main>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import gsap from 'gsap';

const router = useRouter();
const user = ref(null);
const loading = ref(true);

onMounted(async () => {
    const token = localStorage.getItem('token');
    if (!token) {
        router.push('/admin/login');
        return;
    }
    try {
        const res = await fetch('/api/user', {
            headers: { 'Authorization': `Bearer ${token}` }
        });
        if (res.ok) {
            user.value = await res.json();
        } else {
            localStorage.removeItem('token');
            router.push('/admin/login');
        }
    } catch {
        localStorage.removeItem('token');
        router.push('/admin/login');
    } finally {
        loading.value = false;
    }
});

function handleLogout() {
    localStorage.removeItem('token');

    // Animated logout
    const content = document.querySelector('.admin-content');
    if (content) {
        gsap.to(content, {
            opacity: 0,
            scale: 0.95,
            filter: 'blur(10px)',
            duration: 0.4,
            ease: 'power3.in',
            onComplete: () => {
                router.push('/admin/login');
            },
        });
    } else {
        router.push('/admin/login');
    }
}
</script>

<style scoped>
.admin-layout {
    min-height: 100vh;
    background: var(--bg-deep);
    font-family: var(--font-primary);
    color: var(--text-primary);
}

.loading-screen {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-deep);
}

.loading-content {
    text-align: center;
}

.loading-logo {
    width: 64px;
    height: 64px;
    margin: 0 auto 24px;
    animation: loadingPulse 2s ease-in-out infinite;
}

@keyframes loadingPulse {
    0%, 100% { filter: drop-shadow(0 0 10px var(--shadow-primary)); }
    50% { filter: drop-shadow(0 0 25px var(--shadow-primary)); }
}

.loading-bar {
    width: 200px;
    height: 3px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    overflow: hidden;
}

.loading-bar-fill {
    height: 100%;
    width: 30%;
    background: linear-gradient(90deg, var(--primary), var(--secondary));
    border-radius: 2px;
    animation: loadingBar 1.5s ease-in-out infinite;
}

@keyframes loadingBar {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(400%); }
}

.admin-content {
    animation: contentFadeIn 0.5s ease-out;
}

@keyframes contentFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.admin-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 32px;
    background: rgba(18, 18, 28, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
}

.header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.header-logo {
    width: 36px;
    height: 36px;
}

.header-divider {
    width: 1px;
    height: 24px;
    background: rgba(255, 255, 255, 0.1);
}

.header-title {
    color: var(--text-secondary);
    font-size: 14px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 20px;
}

.header-user {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.header-greeting {
    font-size: 11px;
    color: var(--text-muted);
}

.header-name {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
}

.btn-logout {
    padding: 8px 20px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
    border-radius: 8px;
    color: #FCA5A5;
    font-size: 13px;
    font-family: var(--font-primary);
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn-logout:hover {
    background: rgba(239, 68, 68, 0.2);
    transform: translateY(-1px);
}

.admin-main {
    padding: 48px 32px;
}

.welcome-section {
    text-align: center;
    padding: 60px 20px;
}

.welcome-title {
    font-size: 28px;
    font-weight: 800;
    margin-bottom: 8px;
    color: var(--text-primary);
}

.welcome-subtitle {
    font-size: 16px;
    color: var(--text-secondary);
}

@media (max-width: 768px) {
    .admin-header {
        padding: 12px 16px;
    }
    .header-title {
        display: none;
    }
    .admin-main {
        padding: 24px 16px;
    }
    .welcome-title {
        font-size: 22px;
    }
}
</style>
