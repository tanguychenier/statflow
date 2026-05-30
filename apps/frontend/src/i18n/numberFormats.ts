// =============================================================================
// Statflow Dashboard — Number Format Definitions
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import type { NumberOptions } from 'vue-i18n'

type NumberFormats = Record<string, NumberOptions>

export const numberFormatsEn: NumberFormats = {
  integer: { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 },
  decimal: { style: 'decimal', minimumFractionDigits: 1, maximumFractionDigits: 2 },
  percent: { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 },
  percentWhole: { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: 0 },
  compact: { notation: 'compact', compactDisplay: 'short', maximumFractionDigits: 1 },
  currency: { style: 'currency', currency: 'USD', minimumFractionDigits: 0, maximumFractionDigits: 0 },
}

export const numberFormatsFr: NumberFormats = {
  integer: { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 },
  decimal: { style: 'decimal', minimumFractionDigits: 1, maximumFractionDigits: 2 },
  percent: { style: 'percent', minimumFractionDigits: 1, maximumFractionDigits: 1 },
  percentWhole: { style: 'percent', minimumFractionDigits: 0, maximumFractionDigits: 0 },
  compact: { notation: 'compact', compactDisplay: 'short', maximumFractionDigits: 1 },
  currency: { style: 'currency', currency: 'EUR', minimumFractionDigits: 0, maximumFractionDigits: 0 },
}
