// =============================================================================
// Statflow Dashboard — Users endpoints
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { http } from '../http'
import type { ChangePasswordRequest, User, UserUpdateRequest } from '../types'

export const usersApi = {
  me: () => http.get<User>('/users/me'),
  updateMe: (body: UserUpdateRequest) => http.patch<User>('/users/me', body),
  changePassword: (body: ChangePasswordRequest) => http.put<void>('/users/me/password', body),
}
