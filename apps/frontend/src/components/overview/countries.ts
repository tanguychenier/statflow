// =============================================================================
// Overview — ISO 3166-1 alpha-2 country helpers
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Country breakdowns arrive as alpha-2 codes (clickhouse-schema.sql:
// `country_code LowCardinality(FixedString(2))`). The choropleth keys on the
// English country *name* used by the bundled world topology, and the top-list
// shows a flag glyph. Both transforms live here so the view stays declarative
// and the mapping is unit-tested in isolation.
// =============================================================================

/**
 * Alpha-2 → English display name, aligned with the Natural-Earth `name`
 * property the bundled world.geo.json registers under WORLD_MAP_NAME. Only the
 * codes the analytics pipeline can realistically emit are listed; an unknown
 * code falls back to its upper-cased two letters so nothing renders blank.
 */
const COUNTRY_NAMES: Record<string, string> = {
  AE: 'United Arab Emirates',
  AR: 'Argentina',
  AT: 'Austria',
  AU: 'Australia',
  BE: 'Belgium',
  BR: 'Brazil',
  CA: 'Canada',
  CH: 'Switzerland',
  CL: 'Chile',
  CN: 'China',
  CZ: 'Czechia',
  DE: 'Germany',
  DK: 'Denmark',
  EG: 'Egypt',
  ES: 'Spain',
  FI: 'Finland',
  FR: 'France',
  GB: 'United Kingdom',
  GR: 'Greece',
  HK: 'Hong Kong',
  HU: 'Hungary',
  ID: 'Indonesia',
  IE: 'Ireland',
  IL: 'Israel',
  IN: 'India',
  IT: 'Italy',
  JP: 'Japan',
  KR: 'South Korea',
  MX: 'Mexico',
  MY: 'Malaysia',
  NG: 'Nigeria',
  NL: 'Netherlands',
  NO: 'Norway',
  NZ: 'New Zealand',
  PH: 'Philippines',
  PL: 'Poland',
  PT: 'Portugal',
  RO: 'Romania',
  RU: 'Russia',
  SA: 'Saudi Arabia',
  SE: 'Sweden',
  SG: 'Singapore',
  TH: 'Thailand',
  TR: 'Turkey',
  TW: 'Taiwan',
  UA: 'Ukraine',
  US: 'United States of America',
  VN: 'Vietnam',
  ZA: 'South Africa',
}

const ALPHA2 = /^[A-Za-z]{2}$/

/** Human-readable country name for an alpha-2 code (falls back to the code). */
export function countryName(code: string): string {
  const upper = code.toUpperCase()
  return COUNTRY_NAMES[upper] ?? upper
}

/**
 * Regional-indicator flag emoji for an alpha-2 code. Invalid or empty codes
 * yield an empty string so the caller can render the row without a flag.
 */
export function countryFlag(code: string): string {
  if (!ALPHA2.test(code)) return ''
  const upper = code.toUpperCase()
  const base = 0x1f1e6 // regional indicator 'A'
  const first = base + (upper.charCodeAt(0) - 65)
  const second = base + (upper.charCodeAt(1) - 65)
  return String.fromCodePoint(first, second)
}
