// =============================================================================
// Statflow Dashboard — Application entry point
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

// Fonts — subsetted to Latin + Latin-Extended
import '@fontsource-variable/inter'
import '@fontsource-variable/jetbrains-mono'

// Design tokens + Tailwind CSS 4 (single import, processed by @tailwindcss/vite)
import './assets/tokens.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'

import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import { configureAuth } from './api/configureAuth'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 1000 * 60 * 5, // 5 minutes
      retry: 2,
      refetchOnWindowFocus: false,
    },
  },
})

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(VueQueryPlugin, { queryClient })

// Bridge the HTTP client to the auth store now that Pinia + router exist.
configureAuth(router)

app.mount('#app')
