<script setup lang="ts">
// =============================================================================
// PublicDashboardView — public, read-only shared dashboard (screens.md §8)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Route: /share/:token — no auth, no sidebar/topbar, no filters. Fetches the
// owner-configured aggregate metrics via usePublicOverview and renders a minimal
// header + KPI cards. Optional password protection (screens.md §8.3): a 401
// surfaces a centred password gate; a validated password is replayed and kept in
// sessionStorage so a reload stays in. Theme follows the visitor's OS preference
// (screens.md §8.2), independent of the owner's saved theme.
//
// All shaping/branching is delegated to pure helpers under
// ./components/publicshared so this file is a thin, e2e-tested orchestrator.
// =============================================================================
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { RotateCcw } from 'lucide-vue-next'
import { MetricCard } from '@/components/MetricCard'
import { Button } from '@/components/ui'
import EmptyState from '@/components/ui/empty-state/EmptyState.vue'
import PublicDashboardHeader from '@/components/publicshared/PublicDashboardHeader.vue'
import PublicPasswordGate from '@/components/publicshared/PublicPasswordGate.vue'
import { usePublicOverview } from '@/api/composables/usePublic'
import { formatDuration } from '@/utils/formatDuration'
import {
  buildPublicMetrics,
  type PublicMetricFormatters,
} from '@/components/publicshared/publicMetrics'
import { parsePublicPeriod } from '@/components/publicshared/publicPeriod'
import {
  classifyShareError,
  clearStoredSharePassword,
  readStoredSharePassword,
  storeSharePassword,
} from '@/components/publicshared/publicShareState'
import type { PublicOverviewParams } from '@/api/types'

const route = useRoute()
const { t, n } = useI18n()

const token = computed(() => (route.params.token as string | undefined) ?? '')

// A password we are currently presenting to the API. Seeded from sessionStorage
// so a refresh of an already-unlocked link skips the gate.
const sharePassword = ref<string | null>(readStoredSharePassword(token.value))

const params = computed<PublicOverviewParams>(() =>
  sharePassword.value ? { sharePassword: sharePassword.value } : {},
)

const query = usePublicOverview(token, params)

// ── OS-driven theme (screens.md §8.2) ───────────────────────────────────────
// The public view ignores the app's persisted theme and tracks the visitor's
// prefers-color-scheme. We snapshot any existing data-theme so we restore it on
// unmount, keeping the rest of the SPA untouched if the visitor navigates away.
const SCHEME_QUERY = '(prefers-color-scheme: dark)'
let previousTheme: string | null = null
let media: MediaQueryList | null = null

function applyOsTheme(prefersDark: boolean) {
  document.documentElement.setAttribute('data-theme', prefersDark ? 'dark' : 'light')
}

function onSchemeChange(event: MediaQueryListEvent) {
  applyOsTheme(event.matches)
}

onMounted(() => {
  previousTheme = document.documentElement.getAttribute('data-theme')
  media = window.matchMedia(SCHEME_QUERY)
  applyOsTheme(media.matches)
  media.addEventListener('change', onSchemeChange)
})

onBeforeUnmount(() => {
  media?.removeEventListener('change', onSchemeChange)
  if (previousTheme) document.documentElement.setAttribute('data-theme', previousTheme)
})

// ── Error classification ─────────────────────────────────────────────────────
const errorKind = computed(() =>
  query.isError.value ? classifyShareError(query.error.value) : null,
)

// A failed attempt *with* a password means the password was wrong; a 401 without
// one means the link is simply protected and we must prompt for the first time.
const passwordRejected = computed(
  () => errorKind.value === 'password-required' && sharePassword.value !== null,
)

const showPasswordGate = computed(() => errorKind.value === 'password-required')
const showNotFound = computed(() => errorKind.value === 'not-found')
const showGenericError = computed(() => errorKind.value === 'generic')

// A rejected password must not stay in storage / in flight.
watch(passwordRejected, (rejected: boolean) => {
  if (rejected) clearStoredSharePassword(token.value)
})

// Persist a password only once it has actually unlocked the dashboard.
watch(
  () => query.isSuccess.value,
  (ok: boolean) => {
    if (ok && sharePassword.value) storeSharePassword(token.value, sharePassword.value)
  },
)

// The public-overview query key is scoped to the token + date range only — the
// share password rides in a header, so changing it never invalidates the cache.
// We therefore refetch explicitly on submit to replay the request with the new
// password (the queryFn reads the latest params at call time).
function onPasswordSubmit(password: string) {
  sharePassword.value = password
  query.refetch()
}

function onRetry() {
  query.refetch()
}

// ── KPI cards ────────────────────────────────────────────────────────────────
const formatters = computed<PublicMetricFormatters>(() => ({
  count: (value: number) => n(value, 'integer'),
  percent: (value: number) => n(value / 100, 'percent'),
  duration: (value: number) => formatDuration(value),
}))

const metricCards = computed(() => buildPublicMetrics(query.data.value, formatters.value))

const period = computed(() => parsePublicPeriod(query.data.value?.period))

const isInitialLoad = computed(() => query.isPending.value && !query.isError.value)
</script>

<template>
  <PublicPasswordGate
    v-if="showPasswordGate"
    :loading="query.isFetching.value"
    :error="passwordRejected"
    @submit="onPasswordSubmit"
  />

  <main
    v-else
    class="public-dashboard"
    :aria-label="t('publicDashboard.header.title')"
    :aria-busy="isInitialLoad"
  >
    <div class="public-dashboard__inner">
      <PublicDashboardHeader :period="period" :loading="isInitialLoad" />

      <!-- Link invalid / sharing disabled -->
      <EmptyState
        v-if="showNotFound"
        variant="no-access"
        :title="t('publicDashboard.notFound.title')"
        :description="t('publicDashboard.notFound.description')"
      />

      <!-- Network / server error — retryable -->
      <EmptyState
        v-else-if="showGenericError"
        variant="error"
        :title="t('publicDashboard.error.title')"
        :description="t('publicDashboard.error.description')"
      >
        <template #action>
          <Button variant="outline" size="sm" @click="onRetry">
            <template #leading><RotateCcw class="size-4" aria-hidden="true" /></template>
            {{ t('common.retry') }}
          </Button>
        </template>
      </EmptyState>

      <!-- KPI cards -->
      <section
        v-else
        class="public-dashboard__metrics"
        :aria-label="t('publicDashboard.metrics.aria')"
      >
        <MetricCard
          v-for="card in metricCards"
          :key="card.id"
          :title="t(card.titleKey)"
          :value="card.value"
          :trend="card.trend"
          :loading="isInitialLoad"
        />
      </section>
    </div>
  </main>
</template>

<style scoped>
.public-dashboard {
  min-height: 100dvh;
  background-color: var(--sf-bg-base);
  padding: var(--sf-space-6) var(--sf-space-4);
}

.public-dashboard__inner {
  margin-inline: auto;
  max-width: var(--sf-content-max-width);
  display: flex;
  flex-direction: column;
  gap: var(--sf-space-6);
}

.public-dashboard__metrics {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--sf-space-4);
}

@media (min-width: 640px) {
  .public-dashboard__metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .public-dashboard__metrics {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }
}
</style>
