// =============================================================================
// Statflow Dashboard — Auth endpoints
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { http } from '../http'
import type {
  ForgotPasswordRequest,
  LoginRequest,
  RegisterRequest,
  ResetPasswordRequest,
  TokenResponse,
  User,
} from '../types'

export const authApi = {
  login: (body: LoginRequest) =>
    http.post<TokenResponse>('/auth/login', body, { anonymous: true }),

  register: (body: RegisterRequest) =>
    http.post<User>('/auth/register', body, { anonymous: true }),

  forgotPassword: (body: ForgotPasswordRequest) =>
    http.post<void>('/auth/forgot-password', body, { anonymous: true }),

  resetPassword: (body: ResetPasswordRequest) =>
    http.post<void>('/auth/reset-password', body, { anonymous: true }),

  logout: () => http.post<void>('/auth/logout'),

  /** Reads the `sf_rt` cookie server-side; no body, no Authorization header. */
  refresh: () => http.post<TokenResponse>('/auth/refresh', undefined, { anonymous: true }),
}
