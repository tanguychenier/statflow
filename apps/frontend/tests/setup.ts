// =============================================================================
// Statflow Dashboard — Vitest global test setup
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { createI18n } from 'vue-i18n'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, vi } from 'vitest'
import '@testing-library/jest-dom'

import en from '@/i18n/locales/en.json'
import fr from '@/i18n/locales/fr.json'
import { numberFormatsEn, numberFormatsFr } from '@/i18n/numberFormats'
import { datetimeFormatsEn, datetimeFormatsFr } from '@/i18n/datetimeFormats'

// Stub localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {}
  return {
    getItem: (key: string) => store[key] ?? null,
    setItem: (key: string, value: string) => {
      store[key] = value
    },
    removeItem: (key: string) => {
      delete store[key]
    },
    clear: () => {
      store = {}
    },
  }
})()

Object.defineProperty(window, 'localStorage', { value: localStorageMock })

// Stub scrollTo — jsdom doesn't implement it; vue-router's scrollBehavior calls it
// after each navigation. Without the stub the "Not implemented" error propagates
// and can abort the navigation, leaving the router in the previous state.
Object.defineProperty(window, 'scrollTo', {
  writable: true,
  configurable: true,
  value: () => {},
})

// Stub matchMedia (jsdom doesn't implement it)
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
})

// Stub ResizeObserver (jsdom doesn't implement it) — used by useChart.
if (!('ResizeObserver' in globalThis)) {
  class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  }
  Object.defineProperty(globalThis, 'ResizeObserver', {
    writable: true,
    value: ResizeObserverStub,
  })
}

// Stub HTMLCanvasElement.getContext — jsdom ships a stub but ECharts (zrender)
// needs a minimal 2D context to initialise without throwing. Provide enough of
// the CanvasRenderingContext2D surface for a silent no-op render.
const canvasContextStub = {
  fillRect: () => {},
  clearRect: () => {},
  getImageData: (_x: number, _y: number, w: number, h: number) =>
    ({ data: new Uint8ClampedArray(w * h * 4), width: w, height: h, colorSpace: 'srgb' }) as ImageData,
  putImageData: () => {},
  createImageData: () => ({ data: new Uint8ClampedArray(0), width: 0, height: 0, colorSpace: 'srgb' }) as ImageData,
  setTransform: () => {},
  drawImage: () => {},
  save: () => {},
  fillText: () => {},
  restore: () => {},
  beginPath: () => {},
  moveTo: () => {},
  lineTo: () => {},
  closePath: () => {},
  stroke: () => {},
  translate: () => {},
  scale: () => {},
  rotate: () => {},
  arc: () => {},
  fill: () => {},
  measureText: () => ({ width: 0 }) as TextMetrics,
  transform: () => {},
  rect: () => {},
  clip: () => {},
  createLinearGradient: () => ({ addColorStop: () => {} }) as unknown as CanvasGradient,
  createPattern: () => null,
} as unknown as CanvasRenderingContext2D
// HTMLCanvasElement.getContext is overloaded — cast via unknown to satisfy all overloads.
;(HTMLCanvasElement.prototype as unknown as { getContext: () => CanvasRenderingContext2D }).getContext =
  () => canvasContextStub

// @testing-library/vue uses its own render — configure default plugins via setup
// The i18n instance is installed into each render call via the global config
export const testI18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en, fr },
  numberFormats: { en: numberFormatsEn, fr: numberFormatsFr },
  datetimeFormats: { en: datetimeFormatsEn, fr: datetimeFormatsFr },
})

// Reset Pinia before each test
beforeEach(() => {
  setActivePinia(createPinia())
  localStorageMock.clear()
})

// Export unused variable reference to avoid lint warnings
void vi
