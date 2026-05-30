// =============================================================================
// Statflow Dashboard — Vue Router Configuration
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Every route the SPA will ever use is declared here. Screen agents fill the
// component bodies but never touch this file — the route table is frozen so
// navigation, the command palette, and the sidebar all agree on names/paths.
// =============================================================================

import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    /** i18n key for the document.title prefix (e.g. "nav.overview"). */
    titleKey?: string
    /** Whether the route requires an authenticated session. */
    requiresAuth?: boolean
    /** Which shell the route renders inside. */
    layout?: 'shell' | 'auth' | 'public' | 'blank'
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) return savedPosition
    return { top: 0, behavior: 'smooth' }
  },
  routes: [
    // ── Auth layout (Screen 7) ───────────────────────────────────────────────
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/auth/LoginView.vue'),
      meta: { titleKey: 'auth.login.title', layout: 'auth' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/auth/RegisterView.vue'),
      meta: { titleKey: 'auth.register.title', layout: 'auth' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/auth/ForgotPasswordView.vue'),
      meta: { titleKey: 'auth.forgotPassword.title', layout: 'auth' },
    },
    {
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/auth/ResetPasswordView.vue'),
      meta: { titleKey: 'auth.resetPassword.title', layout: 'auth' },
    },

    // ── Public shared dashboard (Screen 8) — no auth, blank layout ───────────
    {
      path: '/share/:token',
      name: 'public-dashboard',
      component: () => import('@/views/PublicDashboardView.vue'),
      meta: { layout: 'public' },
    },

    // ── Authenticated shell layout ───────────────────────────────────────────
    {
      path: '/',
      component: () => import('@/components/layout/AppShell.vue'),
      meta: { requiresAuth: true, layout: 'shell' },
      children: [
        {
          path: '',
          name: 'overview',
          component: () => import('@/views/OverviewView.vue'),
          meta: { titleKey: 'nav.overview' },
        },
        {
          path: 'realtime',
          name: 'realtime',
          component: () => import('@/views/RealtimeView.vue'),
          meta: { titleKey: 'nav.realtime' },
        },
        // Behaviour > Heatmaps (Screen 2)
        {
          path: 'behaviour/heatmaps',
          name: 'heatmaps',
          component: () => import('@/views/HeatmapsView.vue'),
          meta: { titleKey: 'nav.heatmaps' },
        },
        // Pages & Sources (Screen 5)
        {
          path: 'pages-sources',
          name: 'pages-sources',
          component: () => import('@/views/PagesSourcesView.vue'),
          meta: { titleKey: 'nav.pagesAndSources' },
        },
        {
          path: 'funnels',
          name: 'funnels',
          component: () => import('@/views/FunnelsView.vue'),
          meta: { titleKey: 'nav.funnels' },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/SettingsView.vue'),
          meta: { titleKey: 'nav.settings' },
        },
      ],
    },

    // ── 404 fallback ─────────────────────────────────────────────────────────
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { titleKey: 'errors.notFound', layout: 'blank' },
    },
  ],
})

// Redirect unauthenticated users away from protected routes, preserving the
// intended destination so login can return them to it. On the very first
// navigation we await the boot-time silent refresh: the access token lives only
// in memory, so without this a hard refresh of a protected route would always
// bounce to login even when the refresh cookie is still valid.
router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.bootstrap()
  if (!to.meta.requiresAuth) return true
  if (auth.isAuthenticated) return true
  return { name: 'login', query: { redirect: to.fullPath } }
})

export default router
