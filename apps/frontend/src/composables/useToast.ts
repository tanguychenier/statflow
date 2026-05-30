// =============================================================================
// Statflow Dashboard — useToast composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A headless toast queue (components.md §11). The <Toaster> component renders
// `toasts`; any code calls `toast.success(...)` etc. Auto-dismiss timings follow
// the spec: success/default 4s, warning 6s, error & loading do not auto-dismiss.
// A module-level singleton means one shared queue across the whole app.
// =============================================================================

import { ref } from 'vue'

export type ToastVariant = 'default' | 'success' | 'error' | 'warning' | 'loading'

export interface ToastAction {
  label: string
  onClick: () => void
}

export interface ToastItem {
  id: string
  variant: ToastVariant
  title: string
  description?: string
  action?: ToastAction
  /** Milliseconds before auto-dismiss; 0 means it stays until dismissed. */
  duration: number
}

export interface ToastOptions {
  description?: string
  action?: ToastAction
  duration?: number
}

const DEFAULT_DURATIONS: Record<ToastVariant, number> = {
  default: 4000,
  success: 4000,
  warning: 6000,
  error: 0,
  loading: 0,
}

const toasts = ref<ToastItem[]>([])
const timers = new Map<string, ReturnType<typeof setTimeout>>()

function createId(): string {
  return `toast-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
}

function dismiss(id: string) {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
  toasts.value = toasts.value.filter((toast) => toast.id !== id)
}

function push(variant: ToastVariant, title: string, options: ToastOptions = {}): string {
  const id = createId()
  const duration = options.duration ?? DEFAULT_DURATIONS[variant]
  toasts.value = [
    ...toasts.value,
    { id, variant, title, description: options.description, action: options.action, duration },
  ]
  if (duration > 0) {
    timers.set(
      id,
      setTimeout(() => dismiss(id), duration),
    )
  }
  return id
}

/** Pause auto-dismiss while the cursor hovers a toast (components.md §11.2). */
function pause(id: string) {
  const timer = timers.get(id)
  if (timer) {
    clearTimeout(timer)
    timers.delete(id)
  }
}

function resume(id: string) {
  const item = toasts.value.find((toast) => toast.id === id)
  if (item && item.duration > 0 && !timers.has(id)) {
    timers.set(
      id,
      setTimeout(() => dismiss(id), item.duration),
    )
  }
}

export const toast = {
  show: push,
  default: (title: string, options?: ToastOptions) => push('default', title, options),
  success: (title: string, options?: ToastOptions) => push('success', title, options),
  error: (title: string, options?: ToastOptions) => push('error', title, options),
  warning: (title: string, options?: ToastOptions) => push('warning', title, options),
  loading: (title: string, options?: ToastOptions) => push('loading', title, options),
  dismiss,
}

export function useToast() {
  return { toasts, toast, dismiss, pause, resume }
}
