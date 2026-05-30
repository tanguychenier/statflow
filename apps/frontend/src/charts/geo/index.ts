// =============================================================================
// Statflow Dashboard — Bundled GeoJSON registration
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// 100%-local geography: the world topology ships in the bundle (no CDN, per the
// project's offline-first rule). `registerWorldMap()` lazy-imports the JSON the
// first time a <GeoMap> mounts, so the ~world payload is code-split out of the
// main chunk and only loaded on demand. ECharts caches the registration, so
// subsequent maps reuse it.
//
// The committed world.geo.json is a compact placeholder topology with a handful
// of real countries; the full Natural-Earth world set is dropped in at this
// path by the asset pipeline without any code change here.
// =============================================================================

import { echarts } from '../echarts'

export const WORLD_MAP_NAME = 'world'

let registered = false

export async function registerWorldMap(): Promise<void> {
  if (registered) return
  const geoJson = (await import('./world.geo.json')).default
  // The ECharts typings predate GeoJSON `registerMap`; the JSON is the documented input.
  echarts.registerMap(WORLD_MAP_NAME, geoJson as unknown as Parameters<typeof echarts.registerMap>[1])
  registered = true
}

/** Test-only reset so the lazy-registration guard can be exercised repeatedly. */
export function _resetWorldMapForTests(): void {
  registered = false
}
