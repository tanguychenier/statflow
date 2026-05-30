/**
 * Statflow Tracker — Build-time environment flag.
 *
 * `tsup` replaces `__BROWSER__` with the literal `true` in the shipped bundles
 * via `define`, so esbuild dead-code-eliminates the SSR guards (`BROWSER ? … : …`)
 * down to the browser branch — every guarded global access costs nothing in the
 * built tracker. When the TypeScript sources are imported directly (the vitest
 * suite, run under jsdom) `__BROWSER__` is undefined, so the flag falls back to
 * a real runtime check and the guards still protect non-browser contexts.
 * Copyright (c) 2026 Tanguy Chénier. AGPL-3.0-only.
 */

declare const __BROWSER__: boolean;

export const BROWSER: boolean =
  typeof __BROWSER__ !== 'undefined' ? __BROWSER__ : typeof window !== 'undefined';
