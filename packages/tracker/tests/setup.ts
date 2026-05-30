/**
 * Vitest global test setup for the Statflow tracker package.
 * Runs before each test file.
 */

import { afterEach, vi } from 'vitest';

// jsdom does not implement sendBeacon — add a stub so spy-based tests work.
if (typeof navigator !== 'undefined' && !('sendBeacon' in navigator)) {
  Object.defineProperty(navigator, 'sendBeacon', {
    value: (_url: string, _data?: unknown): boolean => true,
    writable: true,
    configurable: true,
  });
}

// Restore all mocks after each test to prevent state leak
afterEach(() => {
  vi.restoreAllMocks();
});
