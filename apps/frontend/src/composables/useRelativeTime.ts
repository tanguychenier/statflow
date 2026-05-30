// =============================================================================
// Statflow Dashboard — useRelativeTime composable
// Copyright (c) Tanguy Chénier — AGPL-3.0
//
// Locale-aware relative time ("2 minutes ago", "il y a 2 minutes") via the
// native Intl.RelativeTimeFormat. Used by the realtime screen and team list.
// =============================================================================

import { useI18n } from 'vue-i18n'

const MINUTE = 60
const HOUR = 3600
const DAY = 86_400
const MONTH = 2_592_000
const YEAR = 31_536_000

export function useRelativeTime() {
  const { locale } = useI18n()

  function format(date: Date | string | number): string {
    const target = date instanceof Date ? date : new Date(date)
    const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
    const diffSec = Math.round((target.getTime() - Date.now()) / 1000)
    const abs = Math.abs(diffSec)

    if (abs < MINUTE) return rtf.format(diffSec, 'second')
    if (abs < HOUR) return rtf.format(Math.round(diffSec / MINUTE), 'minute')
    if (abs < DAY) return rtf.format(Math.round(diffSec / HOUR), 'hour')
    if (abs < MONTH) return rtf.format(Math.round(diffSec / DAY), 'day')
    if (abs < YEAR) return rtf.format(Math.round(diffSec / MONTH), 'month')
    return rtf.format(Math.round(diffSec / YEAR), 'year')
  }

  return { format }
}
