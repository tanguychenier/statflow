// =============================================================================
// useRealtimeStream — composable tests (SSE lifecycle)
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// The transport is injected as a fake EventSource so connection, parsing,
// reconnect-with-refresh and teardown are exercised without a network. Lifecycle
// hooks (watch / onScopeDispose) run inside an effectScope.
// =============================================================================

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { effectScope, nextTick, ref, type EffectScope, type Ref } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import {
  useRealtimeStream,
  type RealtimeEventSource,
} from '@/components/realtime/useRealtimeStream'

type Listener = (event: MessageEvent) => void

const ES_CONNECTING = 0
const ES_CLOSED = 2

class FakeEventSource implements RealtimeEventSource {
  static instances: FakeEventSource[] = []
  listeners = new Map<string, Set<Listener>>()
  closed = false
  // Mirrors the native EventSource: CONNECTING while the browser auto-retries,
  // CLOSED once it gives up. Tests flip this before emitting `error`.
  readyState = ES_CONNECTING

  constructor(public url: string) {
    FakeEventSource.instances.push(this)
  }

  addEventListener(type: string, listener: Listener): void {
    if (!this.listeners.has(type)) this.listeners.set(type, new Set())
    this.listeners.get(type)!.add(listener)
  }

  close(): void {
    this.closed = true
    this.readyState = ES_CLOSED
  }

  emit(type: string, data?: unknown): void {
    const payload = { data: data === undefined ? '' : JSON.stringify(data) } as MessageEvent
    this.listeners.get(type)?.forEach((listener) => listener(payload))
  }

  emitRaw(type: string, raw: string): void {
    this.listeners.get(type)?.forEach((listener) => listener({ data: raw } as MessageEvent))
  }

  static last(): FakeEventSource {
    return FakeEventSource.instances[FakeEventSource.instances.length - 1]
  }
}

function runInScope<T>(fn: () => T): { value: T; scope: EffectScope } {
  const scope = effectScope()
  const value = scope.run(fn) as T
  return { value, scope }
}

function setup(siteId: Ref<string | null>, options = {}) {
  return runInScope(() =>
    useRealtimeStream(siteId, {
      createSource: (url) => new FakeEventSource(url),
      baseBackoffMs: 10,
      maxBackoffMs: 40,
      ...options,
    }),
  )
}

describe('useRealtimeStream', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    FakeEventSource.instances = []
    vi.useFakeTimers()
    const auth = useAuthStore()
    auth.setSession('tok-123', 3600)
  })

  it('connects, marks the stream open and passes the token in the URL', () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    stream.connect()
    const es = FakeEventSource.last()
    expect(es.url).toContain('site-1')
    expect(es.url).toContain('access_token=tok-123')
    es.emit('open')
    expect(stream.status.value).toBe('open')
  })

  it('applies stats payloads and records the arrival time', () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    stream.connect()
    const es = FakeEventSource.last()
    es.emit('stats', { current_visitors: 42, top_pages: [], top_sources: [], updated_at: 'x' })
    expect(stream.stats.value?.current_visitors).toBe(42)
    expect(stream.lastStatsAt.value).not.toBeNull()
    expect(stream.status.value).toBe('open')
  })

  it('dispatches event payloads to registered handlers', () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    const received: unknown[] = []
    stream.onEvent((e) => received.push(e))
    stream.connect()
    FakeEventSource.last().emit('event', { event_name: 'click', pathname: '/x' })
    expect(received).toHaveLength(1)
    expect((received[0] as { event_name: string }).event_name).toBe('click')
  })

  it('ignores malformed JSON on both channels without throwing', () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    const received: unknown[] = []
    stream.onEvent((e) => received.push(e))
    stream.connect()
    const es = FakeEventSource.last()
    es.emitRaw('stats', '{not json')
    es.emitRaw('event', 'also bad')
    expect(stream.stats.value).toBeNull()
    expect(received).toHaveLength(0)
  })

  it('schedules a reconnect with a refreshed token after a CLOSED error', async () => {
    const siteId = ref<string | null>('site-1')
    const auth = useAuthStore()
    const refreshSpy = vi.spyOn(auth, 'refresh').mockResolvedValue('tok-new')
    const { value: stream } = setup(siteId)
    stream.connect()
    const first = FakeEventSource.last()
    first.readyState = ES_CLOSED
    first.emit('error')
    expect(stream.status.value).toBe('error')
    await vi.advanceTimersByTimeAsync(15)
    expect(refreshSpy).toHaveBeenCalled()
    // A second source is created on reconnect.
    expect(FakeEventSource.instances.length).toBeGreaterThan(1)
  })

  it('does not reconnect on a transient error while the source is still connecting', async () => {
    const siteId = ref<string | null>('site-1')
    const auth = useAuthStore()
    const refreshSpy = vi.spyOn(auth, 'refresh').mockResolvedValue('tok-new')
    const { value: stream } = setup(siteId)
    stream.connect()
    const first = FakeEventSource.last()
    // Native EventSource stays in CONNECTING and retries by itself.
    first.readyState = ES_CONNECTING
    first.emit('error')
    expect(stream.status.value).toBe('error')
    await vi.advanceTimersByTimeAsync(50)
    expect(refreshSpy).not.toHaveBeenCalled()
    // No second source: we deferred to the browser's own reconnect.
    expect(FakeEventSource.instances).toHaveLength(1)
  })

  it('reopens when the site id changes and tears down when it clears', async () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    stream.connect()
    const first = FakeEventSource.last()
    siteId.value = 'site-2'
    await nextTick()
    expect(first.closed).toBe(true)
    expect(FakeEventSource.last().url).toContain('site-2')

    siteId.value = null
    await nextTick()
    expect(stream.status.value).toBe('idle')
  })

  it('does nothing on connect when no site is selected', () => {
    const siteId = ref<string | null>(null)
    const { value: stream } = setup(siteId)
    stream.connect()
    expect(FakeEventSource.instances).toHaveLength(0)
  })

  it('closes the source and stops reconnecting when the scope is disposed', async () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream, scope } = setup(siteId)
    stream.connect()
    const es = FakeEventSource.last()
    scope.stop()
    expect(es.closed).toBe(true)
    // An error after disposal must not schedule further work.
    es.emit('error')
    await vi.advanceTimersByTimeAsync(50)
    expect(FakeEventSource.instances).toHaveLength(1)
  })

  it('disconnect closes the stream and returns to idle', () => {
    const siteId = ref<string | null>('site-1')
    const { value: stream } = setup(siteId)
    stream.connect()
    const es = FakeEventSource.last()
    stream.disconnect()
    expect(es.closed).toBe(true)
    expect(stream.status.value).toBe('idle')
  })
})
