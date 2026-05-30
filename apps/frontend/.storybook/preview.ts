import type { Preview } from '@storybook/vue3'
import { createI18n } from 'vue-i18n'
import { createPinia } from 'pinia'
import '../src/assets/tokens.css'
import en from '../src/i18n/locales/en.json'
import fr from '../src/i18n/locales/fr.json'

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: { en, fr },
})

const pinia = createPinia()

const preview: Preview = {
  parameters: {
    backgrounds: {
      default: 'dark',
      values: [
        { name: 'dark', value: '#09090b' },
        { name: 'light', value: '#fafafa' },
      ],
    },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    a11y: {
      config: {
        rules: [
          // Colour-contrast is verified via design tokens; disable automated check
          // to avoid false positives in stories with custom backgrounds.
          { id: 'color-contrast', enabled: true },
        ],
      },
    },
  },
  decorators: [
    (story) => ({
      components: { story },
      setup() {
        return {}
      },
      template: '<div data-theme="dark" style="background: var(--sf-bg-base); min-height: 100vh; padding: 24px;"><story /></div>',
    }),
  ],
  globals: {
    locale: 'en',
    locales: {
      en: 'English',
      fr: 'Français',
    },
  },
}

export default preview
