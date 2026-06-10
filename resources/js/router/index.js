import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/admin/login',
        name: 'AdminLogin',
        component: () => import('../views/LoginView.vue'),
    },
    {
        path: '/admin',
        name: 'AdminDashboard',
        component: () => import('../Layouts/AdminLayout.vue'),
        meta: { requiresAuth: true },
    },
    {
        path: '/',
        component: () => import('../Layouts/AdminLayout.vue'),
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('token');
    if (to.meta.requiresAuth && !token) {
        next('/admin/login');
    } else {
        next();
    }
});

export default router;
