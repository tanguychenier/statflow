// =============================================================================
// Statflow Dashboard — Wire the HTTP client to the auth store
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Bridges the (Vue-agnostic) HTTP client to the Pinia auth store: the client
// pulls the access token, asks the store to refresh on 401, and routes the user
// to /login when the session is unrecoverable. Called once after Pinia + router
// are installed.
// =============================================================================

import type { Router } from 'vue-router'
import { configureHttpClient } from './http'
import { useAuthStore } from '@/stores/auth'

export function configureAuth(router: Router): void {
  const auth = useAuthStore()
  configureHttpClient({
    getAccessToken: () => auth.getAccessToken(),
    refreshAccessToken: () => auth.refresh(),
    onAuthFailure: () => {
      auth.clearSession()
      if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
      }
    },
  })
}
