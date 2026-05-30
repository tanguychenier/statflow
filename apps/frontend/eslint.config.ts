import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'

export default tseslint.config(
  {
    ignores: ['dist/**', 'coverage/**', 'storybook-static/**', '.storybook/**', 'node_modules/**'],
  },
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
        extraFileExtensions: ['.vue'],
        sourceType: 'module',
      },
    },
  },
  {
    rules: {
      // TypeScript
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
      '@typescript-eslint/explicit-function-return-type': 'off',
      '@typescript-eslint/consistent-type-imports': ['error', { prefer: 'type-imports' }],

      // Vue
      'vue/multi-word-component-names': 'off',
      'vue/component-api-style': ['error', ['script-setup']],
      'vue/define-macros-order': [
        'error',
        { order: ['defineOptions', 'defineProps', 'defineEmits', 'defineSlots'] },
      ],
      'vue/no-unused-refs': 'error',
      'vue/block-lang': ['error', { script: { lang: 'ts' } }],
      'vue/block-order': ['error', { order: ['script', 'template', 'style'] }],
      // TypeScript's optional (`?`) already expresses the absence of a default;
      // this rule would require redundant `undefined` defaults for every optional prop.
      'vue/require-default-prop': 'off',
      // Formatting rules handled by Prettier
      'vue/max-attributes-per-line': 'off',
      'vue/singleline-html-element-content-newline': 'off',
      'vue/html-self-closing': 'off',
      'vue/html-indent': 'off',
      // Formatting handled by Prettier
      'vue/html-closing-bracket-newline': 'off',

      // General
      'no-console': ['warn', { allow: ['warn', 'error'] }],
    },
  },
  // Test files use inline defineComponent stubs — relax rules that only apply
  // to production .vue single-file components.
  {
    files: ['tests/**/*.ts', 'tests/**/*.spec.ts'],
    rules: {
      'vue/one-component-per-file': 'off',
      'vue/component-api-style': 'off',
      'vue/require-default-prop': 'off',
    },
  },
)
