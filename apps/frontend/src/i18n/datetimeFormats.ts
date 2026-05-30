// =============================================================================
// Statflow Dashboard — Datetime Format Definitions
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { DateTimeOptions } from 'vue-i18n'

type DatetimeFormats = Record<string, DateTimeOptions>

export const datetimeFormatsEn: DatetimeFormats = {
  short: { year: 'numeric', month: 'short', day: 'numeric' },
  medium: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' },
  long: {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long',
    hour: '2-digit',
    minute: '2-digit',
  },
  dateOnly: { year: 'numeric', month: 'short', day: 'numeric' },
  monthYear: { year: 'numeric', month: 'long' },
  monthShort: { month: 'short', day: 'numeric' },
  timeOnly: { hour: '2-digit', minute: '2-digit' },
  hourOnly: { hour: '2-digit', minute: '2-digit', hour12: true },
}

export const datetimeFormatsFr: DatetimeFormats = {
  short: { year: 'numeric', month: 'short', day: 'numeric' },
  medium: { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' },
  long: {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long',
    hour: '2-digit',
    minute: '2-digit',
  },
  dateOnly: { year: 'numeric', month: 'short', day: 'numeric' },
  monthYear: { year: 'numeric', month: 'long' },
  monthShort: { month: 'short', day: 'numeric' },
  timeOnly: { hour: '2-digit', minute: '2-digit' },
  hourOnly: { hour: '2-digit', minute: '2-digit', hour12: false },
}
