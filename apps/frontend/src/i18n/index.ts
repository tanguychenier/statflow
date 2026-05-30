// =============================================================================
// Statflow Dashboard — i18n Instance
// Copyright (c) Tanguy Chénier — AGPL-3.0
// =============================================================================

import { createI18n } from 'vue-i18n'
import { detectLocale } from './detectLocale'
import { datetimeFormatsEn, datetimeFormatsFr } from './datetimeFormats'
import { numberFormatsEn, numberFormatsFr } from './numberFormats'
import en from './locales/en.json'
import fr from './locales/fr.json'

export const i18n = createI18n({
  legacy: false,
  locale: detectLocale(),
  fallbackLocale: 'en',
  messages: { en, fr },
  datetimeFormats: {
    en: datetimeFormatsEn,
    fr: datetimeFormatsFr,
  },
  numberFormats: {
    en: numberFormatsEn,
    fr: numberFormatsFr,
  },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: import.meta.env.DEV,
})
