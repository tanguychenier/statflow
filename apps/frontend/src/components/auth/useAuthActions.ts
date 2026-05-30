// =============================================================================
// Statflow Dashboard — Auth screen actions (Screen 7)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A thin orchestration layer the auth views call. It bridges the typed auth
// endpoints (src/api/endpoints/auth) and the session store (stores/auth):
//   - login goes through the store so the access token + expiry are set, which
//     is what flips `isAuthenticated` and lets the router guard pass.
//   - register / forgot / reset are stateless from the SPA's point of view, so
//     they call the endpoint client directly.
// Errors propagate unchanged (as ApiError) — the views map them to messages.
// =============================================================================

import { authApi } from '@/api/endpoints/auth'
import { useAuthStore } from '@/stores/auth'
import type {
  ForgotPasswordRequest,
  LoginRequest,
  RegisterRequest,
  ResetPasswordRequest,
} from '@/api/types'

export function useAuthActions() {
  const auth = useAuthStore()

  return {
    login: (credentials: LoginRequest) => auth.login(credentials),
    register: (body: RegisterRequest) => authApi.register(body),
    forgotPassword: (body: ForgotPasswordRequest) => authApi.forgotPassword(body),
    resetPassword: (body: ResetPasswordRequest) => authApi.resetPassword(body),
  }
}
