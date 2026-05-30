<script setup lang="ts">
// =============================================================================
// Toaster — renders the global toast queue (components.md §11)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Mounted once near the app root. The region is aria-live=polite so new toasts
// are announced without stealing focus (accessibility.md §4.3). Hovering a toast
// pauses its auto-dismiss timer.
// =============================================================================
import { CheckCircle2, Info, Loader2, TriangleAlert, X, XCircle } from 'lucide-vue-next'
import { useToast, type ToastVariant } from '@/composables/useToast'

const { toasts, dismiss, pause, resume } = useToast()

const ICON = {
  default: Info,
  success: CheckCircle2,
  error: XCircle,
  warning: TriangleAlert,
  loading: Loader2,
}

const ACCENT: Record<ToastVariant, string> = {
  default: 'border-l-border',
  success: 'border-l-positive',
  error: 'border-l-negative',
  warning: 'border-l-warning',
  loading: 'border-l-border',
}

const ICON_COLOR: Record<ToastVariant, string> = {
  default: 'text-fg-secondary',
  success: 'text-positive-text',
  error: 'text-negative-text',
  warning: 'text-warning-text',
  loading: 'text-fg-secondary',
}
</script>

<template>
  <div
    class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-[360px] max-w-[calc(100vw-2rem)] flex-col gap-2"
    role="region"
    aria-label="Notifications"
    aria-live="polite"
    aria-atomic="false"
  >
    <TransitionGroup name="sf-toast">
      <div
        v-for="item in toasts"
        :key="item.id"
        class="pointer-events-auto flex items-start gap-3 rounded-lg border border-l-2 border-border bg-bg-overlay p-3 shadow-lg"
        :class="ACCENT[item.variant]"
        @mouseenter="pause(item.id)"
        @mouseleave="resume(item.id)"
      >
        <component
          :is="ICON[item.variant]"
          class="mt-0.5 size-4 shrink-0"
          :class="[ICON_COLOR[item.variant], item.variant === 'loading' && 'animate-spin']"
          aria-hidden="true"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-fg-primary">{{ item.title }}</p>
          <p v-if="item.description" class="mt-0.5 text-xs text-fg-secondary">
            {{ item.description }}
          </p>
          <button
            v-if="item.action"
            type="button"
            class="mt-2 text-xs font-semibold text-accent-text hover:underline"
            @click="item.action.onClick()"
          >
            {{ item.action.label }}
          </button>
        </div>
        <button
          type="button"
          class="flex size-5 shrink-0 items-center justify-center rounded-sm text-fg-muted hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus"
          aria-label="Dismiss notification"
          @click="dismiss(item.id)"
        >
          <X class="size-3.5" aria-hidden="true" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.sf-toast-enter-active,
.sf-toast-leave-active {
  transition:
    opacity var(--sf-duration-slow) var(--sf-ease-out),
    transform var(--sf-duration-slow) var(--sf-ease-out);
}

.sf-toast-enter-from,
.sf-toast-leave-to {
  opacity: 0;
  transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
  .sf-toast-enter-active,
  .sf-toast-leave-active {
    transition: none;
  }
}
</style>
