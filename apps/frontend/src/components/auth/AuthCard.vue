<script setup lang="ts">
// =============================================================================
// AuthCard — centred card shell for the auth screens (screens.md §7.1)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// One shell for login / register / forgot / reset: dark full-bleed background,
// a surface card with the brand mark, title and optional subtitle, plus the
// theme toggle in the corner. The card drops its shadow below 768px per spec.
// The default slot holds the form; the `footer` slot holds the cross-links.
// =============================================================================
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Moon, Sun } from 'lucide-vue-next'
import { useTheme } from '@/composables/useTheme'

defineProps<{ title: string; subtitle?: string }>()

const { t } = useI18n()
const { isDark, toggleTheme } = useTheme()

const themeToggleLabel = computed(() =>
  isDark.value ? t('auth.themeToggle.toLight') : t('auth.themeToggle.toDark'),
)
</script>

<template>
  <main
    class="relative flex min-h-dvh flex-col items-center justify-center bg-bg-base p-4"
    :aria-label="title"
  >
    <button
      type="button"
      class="absolute right-4 top-4 inline-flex size-9 items-center justify-center rounded-md text-fg-secondary outline-none transition-colors hover:bg-bg-subtle hover:text-fg-primary focus-visible:ring-2 focus-visible:ring-border-focus"
      :aria-label="themeToggleLabel"
      data-testid="auth-theme-toggle"
      @click="toggleTheme"
    >
      <Sun v-if="isDark" class="size-4" aria-hidden="true" />
      <Moon v-else class="size-4" aria-hidden="true" />
    </button>

    <section
      class="flex w-[400px] max-w-[calc(100vw-2rem)] flex-col gap-6 rounded-xl border border-border bg-bg-surface p-8 shadow-none sm:shadow-xl"
    >
      <header class="flex flex-col gap-2">
        <div
          class="inline-flex items-center gap-2 text-xl font-semibold text-accent-text"
          data-testid="auth-brand"
        >
          <span aria-hidden="true">◆</span>
          <span>{{ t('auth.brand') }}</span>
        </div>
        <h1 class="text-xl font-semibold text-fg-primary">{{ title }}</h1>
        <p v-if="subtitle" class="text-sm text-fg-secondary">{{ subtitle }}</p>
      </header>

      <slot />

      <footer v-if="$slots.footer" class="flex flex-col gap-2 text-sm text-fg-secondary">
        <slot name="footer" />
      </footer>
    </section>
  </main>
</template>
