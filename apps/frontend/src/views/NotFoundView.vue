<script setup lang="ts">
// =============================================================================
// NotFoundView — 404 fallback page
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'

const { t } = useI18n()
const router = useRouter()
</script>

<template>
  <main class="not-found" aria-label="Page not found">
    <h1 class="not-found__code" aria-hidden="true">404</h1>
    <p class="not-found__message">{{ t('errors.notFound') }}</p>
    <button
      class="not-found__back"
      type="button"
      @click="router.push('/')"
    >
      {{ t('common.back') }}
    </button>
  </main>
</template>

<style scoped>
.not-found {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--sf-space-4);
  background-color: var(--sf-bg-base);
  text-align: center;
}

.not-found__code {
  margin: 0;
  font-size: var(--sf-text-5xl);
  font-weight: var(--sf-weight-bold);
  color: var(--sf-fg-disabled);
  letter-spacing: var(--sf-tracking-tight);
}

.not-found__message {
  margin: 0;
  font-size: var(--sf-text-lg);
  color: var(--sf-fg-secondary);
}

.not-found__back {
  padding: var(--sf-space-2) var(--sf-space-5);
  background-color: var(--sf-accent);
  color: var(--sf-fg-inverse);
  border: none;
  border-radius: var(--sf-radius-md);
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-medium);
  cursor: pointer;
  transition: background-color var(--sf-duration-base) var(--sf-ease-default);
}

.not-found__back:hover {
  background-color: var(--sf-accent-hover);
}
</style>
