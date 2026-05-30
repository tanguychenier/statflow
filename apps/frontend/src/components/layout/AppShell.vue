<script setup lang="ts">
// =============================================================================
// AppShell — Authenticated dashboard layout (sidebar + topbar + content area)
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'
import { useSidebarState } from '@/composables/useSidebarState'

const { collapsed: sidebarCollapsed, toggle: toggleSidebar } = useSidebarState()
</script>

<template>
  <div class="shell" :class="{ 'shell--collapsed': sidebarCollapsed }">
    <!-- Skip to main content — WCAG 2.4.1 -->
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <AppSidebar :collapsed="sidebarCollapsed" />

    <div class="shell__body">
      <AppTopbar @toggle-sidebar="toggleSidebar" />

      <main id="main-content" class="shell__content">
        <RouterView v-slot="{ Component, route }">
          <Transition name="page" mode="out-in" appear>
            <component :is="Component" :key="route.path" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: var(--sf-sidebar-width) 1fr;
  min-height: 100dvh;
  background-color: var(--sf-bg-base);
  transition: grid-template-columns var(--sf-duration-slow) var(--sf-ease-in-out);
}

.shell--collapsed {
  grid-template-columns: var(--sf-sidebar-collapsed-width) 1fr;
}

.shell__body {
  display: flex;
  flex-direction: column;
  min-width: 0;
  overflow: hidden;
}

.shell__content {
  flex: 1;
  overflow-y: auto;
  padding: var(--sf-space-6);
  max-width: var(--sf-content-max-width);
  width: 100%;
}

/* Self-contained visually-hidden state — does not lean on the global .sr-only
  utility, so a change to that utility (or scoped-style attribute specificity)
  can never strand the link half-revealed. Every property the focus state
  overrides is declared here, so the two rules are exact inverses. */
.skip-link {
  position: absolute;
  width: 1px;
  height: 1px;
  margin: -1px;
  padding: 0;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  clip-path: inset(50%);
  white-space: nowrap;
  border: 0;
}

.skip-link:focus,
.skip-link:focus-visible {
  position: fixed;
  inset-block-start: var(--sf-space-4);
  inset-inline-start: var(--sf-space-4);
  z-index: 9999;
  width: auto;
  height: auto;
  margin: 0;
  padding: var(--sf-space-2) var(--sf-space-4);
  overflow: visible;
  clip: auto;
  clip-path: none;
  background-color: var(--sf-accent);
  color: var(--sf-fg-inverse);
  border-radius: var(--sf-radius-md);
  font-size: var(--sf-text-sm);
  font-weight: var(--sf-weight-medium);
}

/* Page transition — 200ms fade only, no slide (avoids motion sickness) */
.page-enter-active,
.page-leave-active {
  transition: opacity var(--sf-duration-slow) var(--sf-ease-out);
}

.page-enter-from,
.page-leave-to {
  opacity: 0;
}

/* Responsive — tablet */
@media (width < 768px) {
  .shell {
    grid-template-columns: 0 1fr;
  }

  .shell--collapsed {
    grid-template-columns: 0 1fr;
  }
}
</style>
