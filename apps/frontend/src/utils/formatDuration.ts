// =============================================================================
// Statflow Dashboard — Duration Formatter
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

/**
 * Formats a duration in seconds to a human-readable string.
 * Output is "2m 04s" format — universally understood across EN and FR.
 *
 * @param seconds - Duration in seconds (non-negative integer)
 * @returns Formatted duration string
 */
export function formatDuration(seconds: number): string {
  if (seconds < 0) return '0s'
  const m = Math.floor(seconds / 60)
  const s = Math.floor(seconds % 60)
  if (m === 0) return `${s}s`
  return `${m}m ${String(s).padStart(2, '0')}s`
}

/**
 * Formats a large number with compact notation.
 * Falls back to Intl.NumberFormat for locale-aware compact formatting.
 *
 * @param value - The number to format
 * @param locale - The active locale ('en' | 'fr')
 * @returns Compact formatted string (e.g. "12.0K" or "12 k")
 */
export function formatCompact(value: number, locale: string): string {
  return new Intl.NumberFormat(locale, {
    notation: 'compact',
    compactDisplay: 'short',
    maximumFractionDigits: 1,
  }).format(value)
}
