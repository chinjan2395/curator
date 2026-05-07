import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'

export default [
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['src/**/*.{js,vue}'],
    rules: {
      // -----------------------------------------------------------------------
      // Architecture: forbid raw HTML elements in views/pages
      // -----------------------------------------------------------------------
      'vue/no-restricted-html-elements': [
        'error',
        {
          element: 'button',
          message: 'Use <AppButton> from @/components/ui instead of raw <button>.',
        },
        {
          element: 'input',
          message: 'Use <AppInput> or <AppCheckbox> from @/components/ui instead of raw <input>.',
        },
        {
          element: 'select',
          message: 'Use <AppSelect> from @/components/ui instead of raw <select>.',
        },
        {
          element: 'textarea',
          message: 'Use <AppInput type="textarea"> from @/components/ui instead of raw <textarea>.',
        },
        {
          element: 'table',
          message: 'Use <AppTable> from @/components/ui instead of raw <table>.',
        },
      ],

      // -----------------------------------------------------------------------
      // No inline styles
      // -----------------------------------------------------------------------
      'vue/no-restricted-syntax': [
        'error',
        {
          selector: "VAttribute[key.name='style']",
          message: 'Inline styles are forbidden. Use design tokens and Tailwind utility classes via the design system.',
        },
      ],

      // -----------------------------------------------------------------------
      // No direct axios/fetch in components or views
      // -----------------------------------------------------------------------
      'no-restricted-imports': [
        'error',
        {
          paths: [
            {
              name: 'axios',
              message: 'Do not import axios directly in components or views. Use a store, service, or composable.',
            },
          ],
        },
      ],

      // -----------------------------------------------------------------------
      // General quality
      // -----------------------------------------------------------------------
      'vue/component-api-style': ['error', ['script-setup']],
      'vue/define-props-declaration': ['error', 'runtime'],
      'vue/define-emits-declaration': ['error', 'runtime'],
      'vue/no-unused-vars': 'error',
      'vue/no-unused-refs': 'error',
      'vue/padding-line-between-blocks': ['error', 'always'],
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
  {
    files: ['src/views/**/*.vue'],
    rules: {
      'vue/no-restricted-syntax': [
        'error',
        {
          selector: "VAttribute[key.name='class'][value.value=/\\b(action-link|action-link--premium|btn-primary|btn-secondary)\\b/]",
          message: 'Legacy action/button classes are forbidden. Use AppButton variants and composition instead.',
        },
      ],
    },
  },
  {
    // Allow raw HTML elements inside design system components themselves
    files: ['src/components/ui/**/*.vue', 'src/components/layout/**/*.vue'],
    rules: {
      'vue/no-restricted-html-elements': 'off',
      'no-restricted-imports': 'off',
    },
  },
  {
    // Allow axios in stores and composables (they ARE the abstraction layer)
    files: ['src/stores/**/*.js', 'src/composables/**/*.js', 'src/services/**/*.js'],
    rules: {
      'no-restricted-imports': 'off',
    },
  },
  {
    ignores: ['node_modules/', 'dist/', 'public/'],
  },
]
