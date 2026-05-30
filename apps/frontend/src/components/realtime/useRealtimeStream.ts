// =============================================================================
// useRealtimeStream — Server-Sent Events lifecycle for the Realtime screen
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Owns a single EventSource against
// `GET /analytics/{site_id}/realtime/stream` (openapi.yaml). Two named channels
// are consumed: `stats` (a RealtimeResponse every ~2s) and `event` (one
// RealtimeEvent per ingest). The token rides as a query parameter because
// EventSource cannot set headers; on token expiry the server closes the stream
// and we reconnect with a freshly refreshed token (auth store), backing off so a
// genuinely dead backend is not hammered. The transport is pluggable so the
// connection logic is unit-testable without a real network.
// =============================================================================

import { ref, shallowRef, watch, onScopeDispose, type Ref } from 'vue'
import { storeToRefs } from 'pinia'
import { analyticsApi } from '@/api/endpoints/analytics'
import { useAuthStore } from '@/stores/auth'
import type { RealtimeEvent, RealtimeResponse } from '@/api/types'

export type RealtimeStreamStatus = 'idle' | 'connecting' | 'open' | 'reconnecting' | 'error'

/** EventSource.CLOSED — the source has given up and will not reconnect itself. */
const EVENT_SOURCE_CLOSED = 2

/** Minimal slice of the EventSource API we depend on — keeps tests honest. */
export interface RealtimeEventSource {
  addEventListener(type: string, listener: (event: MessageEvent) => void): void
  close(): void
  readonly url?: string
  /** EventSource.readyState: 0 CONNECTING · 1 OPEN · 2 CLOSED. */
  readonly readyState?: number
}

export type EventSourceFactory = (url: string) => RealtimeEventSource

export interface UseRealtimeStreamOptions {
  /** Override the EventSource constructor (tests inject a fake). */
  createSource?: EventSourceFactory
  /** Reconnect backoff in ms (first retry); doubled up to `maxBackoffMs`. */
  baseBackoffMs?: number
  maxBackoffMs?: number
}

export interface UseRealtimeStream {
  stats: Ref<RealtimeResponse | null>
  status: Ref<RealtimeStreamStatus>
  /** Monotonically bumped each time a new `event` arrives — drives appends. */
  onEvent: (handler: (event: RealtimeEvent) => void) => void
  /** Last `stats` arrival time (ms epoch) for the "updated Ns ago" label. */
  lastStatsAt: Ref<number | null>
  connect: () => void
  disconnect: () => void
}

const DEFAULT_BASE_BACKOFF = 1_000
const DEFAULT_MAX_BACKOFF = 15_000

function defaultFactory(url: string): RealtimeEventSource {
  return new EventSource(url)
}

/**
 * Establish and maintain the realtime SSE connection for `siteId`. Returns
 * reactive stream state plus an `onEvent` registration for the live panel. The
 * connection is torn down automatically when the owning scope is disposed.
 */
export function useRealtimeStream(
  siteId: Ref<string | null>,
  options: UseRealtimeStreamOptions = {},
): UseRealtimeStream {
  const auth = useAuthStore()
  const { accessToken } = storeToRefs(auth)

  const createSource = options.createSource ?? defaultFactory
  const baseBackoff = options.baseBackoffMs ?? DEFAULT_BASE_BACKOFF
  const maxBackoff = options.maxBackoffMs ?? DEFAULT_MAX_BACKOFF

  const stats = ref<RealtimeResponse | null>(null)
  const status = ref<RealtimeStreamStatus>('idle')
  const lastStatsAt = ref<number | null>(null)

  const source = shallowRef<RealtimeEventSource | null>(null)
  const eventHandlers = new Set<(event: RealtimeEvent) => void>()
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null
  let backoff = baseBackoff
  let disposed = false

  function onEvent(handler: (event: RealtimeEvent) => void): void {
    eventHandlers.add(handler)
  }

  function clearTimer(): void {
    if (reconnectTimer !== null) {
      clearTimeout(reconnectTimer)
      reconnectTimer = null
    }
  }

  function teardown(): void {
    clearTimer()
    if (source.value) {
      source.value.close()
      source.value = null
    }
  }

  function parse<T>(raw: string): T | null {
    try {
      return JSON.parse(raw) as T
    } catch {
      return null
    }
  }

  function open(): void {
    const id = siteId.value
    if (!id || disposed) return

    teardown()
    status.value = status.value === 'error' || backoff > baseBackoff ? 'reconnecting' : 'connecting'

    const url = analyticsApi.realtimeStreamUrl(id, accessToken.value)
    const es = createSource(url)
    source.value = es

    es.addEventListener('open', () => {
      status.value = 'open'
      backoff = baseBackoff
    })

    es.addEventListener('stats', (message) => {
      const payload = parse<RealtimeResponse>(message.data)
      if (payload) {
        stats.value = payload
        lastStatsAt.value = Date.now()
        status.value = 'open'
      }
    })

    es.addEventListener('event', (message) => {
      const payload = parse<RealtimeEvent>(message.data)
      if (payload) eventHandlers.forEach((handler) => handler(payload))
    })

    // EventSource surfaces token-expiry / network drops as a generic `error`.
    // For transient errors the browser keeps the source in CONNECTING and
    // auto-reconnects itself — owning a reconnect there would race the native
    // retry and double the connections. Only when the source has moved to
    // CLOSED (e.g. token expiry, where the server hung up) do we take over:
    // mint a fresh token and reopen explicitly.
    es.addEventListener('error', () => {
      if (disposed) return
      status.value = 'error'
      if (es.readyState === EVENT_SOURCE_CLOSED) scheduleReconnect()
    })
  }

  function scheduleReconnect(): void {
    clearTimer()
    const delay = backoff
    backoff = Math.min(backoff * 2, maxBackoff)
    reconnectTimer = setTimeout(() => {
      void auth.refresh().finally(() => {
        if (!disposed && siteId.value) open()
      })
    }, delay)
  }

  function connect(): void {
    disposed = false
    backoff = baseBackoff
    open()
  }

  function disconnect(): void {
    status.value = 'idle'
    teardown()
  }

  // Re-open whenever the scoped site changes (and tear down when it clears).
  watch(siteId, (next) => {
    if (next) {
      backoff = baseBackoff
      open()
    } else {
      disconnect()
    }
  })

  onScopeDispose(() => {
    disposed = true
    teardown()
  })

  return { stats, status, onEvent, lastStatsAt, connect, disconnect }
}
