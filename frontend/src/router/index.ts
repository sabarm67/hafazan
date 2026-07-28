import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
    { path: '/mushaf', name: 'mushaf', component: () => import('../views/MushafView.vue') },
    { path: '/session', name: 'session', component: () => import('../views/SessionView.vue') },
    { path: '/murajaah', name: 'murajaah', component: () => import('../views/MurajaahView.vue') },
    { path: '/teacher', name: 'teacher-portal', component: () => import('../views/TeacherPortalView.vue') },
    { path: '/parent', name: 'parent-portal', component: () => import('../views/ParentPortalView.vue') },
    { path: '/settings', name: 'settings', component: () => import('../views/SettingsView.vue') },
    { path: '/login', name: 'login', component: () => import('../views/LoginView.vue') },
    { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue') },
  ],
})

export default router
