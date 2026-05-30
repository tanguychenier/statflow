// =============================================================================
// Auth stories — shared Storybook harness
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Auth components lean on i18n, the Pinia theme store, the in-memory router
// (RouterLink) and vue-query. Stories run outside the real app, so this builds
// the same plugins and exposes a decorator that installs them onto the story's
// Vue app via the current instance's app context (the supported per-story path
// when the global preview setup cannot be edited).
// =============================================================================

import { getCurrentInstance, h } from 'vue'
import { createI18n } from 'vue-i18n'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter, type RouteRecordRaw } from 'vue-router'
import { VueQueryPlugin } from '@tanstack/vue-query'
import type { Decorator } from '@storybook/vue3'
import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'

const Blank = { render: () => h('div') }

const routes: RouteRecordRaw[] = [
  { path: '/', name: 'overview', component: Blank },
  { path: '/login', name: 'login', component: Blank },
  { path: '/register', name: 'register', component: Blank },
  { path: '/forgot-password', name: 'forgot-password', component: Blank },
  { path: '/reset-password', name: 'reset-password', component: Blank },
]

let installed = false

/**
 * Install i18n, Pinia, a memory router and vue-query onto the running story
 * app exactly once. Storybook reuses a single app across stories, so guarding
 * with a flag avoids double-registration warnings.
 */
export const withAuthProviders: Decorator = (story, context) => ({
  setup() {
    const app = getCurrentInstance()?.appContext.app
    if (app && !installed) {
      const i18n = createI18n({
        legacy: false,
        locale: (context.globals.locale as string) ?? 'en',
        fallbackLocale: 'en',
        messages: { en, fr },
      })
      const router = createRouter({ history: createMemoryHistory(), routes })
      void router.push((context.parameters.initialRoute as string) ?? '/')
      app.use(createPinia())
      app.use(i18n)
      app.use(router)
      app.use(VueQueryPlugin)
      installed = true
    }
    return () => h(story())
  },
})
