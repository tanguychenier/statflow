// =============================================================================
// Settings — IANA timezone list for the General section selector
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// A curated, locally-bundled subset of canonical IANA zone ids covering the
// regions that matter for a self-hosted analytics deployment. Bundled rather
// than read from Intl.supportedValuesOf so the option list is stable across
// runtimes and the build stays fully offline (no external data fetch).
// =============================================================================

export const TIMEZONES = [
  'UTC',
  'Europe/London',
  'Europe/Paris',
  'Europe/Berlin',
  'Europe/Madrid',
  'Europe/Rome',
  'Europe/Amsterdam',
  'Europe/Brussels',
  'Europe/Zurich',
  'Europe/Lisbon',
  'Europe/Dublin',
  'Europe/Stockholm',
  'Europe/Warsaw',
  'Europe/Athens',
  'Europe/Helsinki',
  'Europe/Moscow',
  'Europe/Istanbul',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'America/Toronto',
  'America/Sao_Paulo',
  'America/Mexico_City',
  'America/Argentina/Buenos_Aires',
  'Africa/Cairo',
  'Africa/Johannesburg',
  'Africa/Lagos',
  'Asia/Dubai',
  'Asia/Kolkata',
  'Asia/Shanghai',
  'Asia/Singapore',
  'Asia/Hong_Kong',
  'Asia/Tokyo',
  'Asia/Seoul',
  'Australia/Sydney',
  'Australia/Perth',
  'Pacific/Auckland',
] as const

export type Timezone = (typeof TIMEZONES)[number]
