import js from '@eslint/js'
import globals from 'globals'
import reactHooks from 'eslint-plugin-react-hooks'
import reactRefresh from 'eslint-plugin-react-refresh'
import tseslint from 'typescript-eslint'
import { defineConfig, globalIgnores } from 'eslint/config'

export default defineConfig([
  globalIgnores(['dist']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.recommended,
      reactHooks.configs['recommended-latest'],
      reactRefresh.configs.vite,
    ],
    languageOptions: {
      ecmaVersion: 2020,
      globals: globals.browser,
    },
    rules: {
      // Warn on console usage (except warn/error) - helps catch debug logs
      'no-console': ['warn', { allow: ['warn', 'error'] }],

      // Prefer explicit types over any
      '@typescript-eslint/no-explicit-any': 'warn',

      // Ensure unused vars are caught (ignore underscore-prefixed)
      '@typescript-eslint/no-unused-vars': ['error', {
        argsIgnorePattern: '^_',
        varsIgnorePattern: '^_',
      }],

      // Consistent return types
      '@typescript-eslint/explicit-function-return-type': 'off',

      // Prefer const assertions
      'prefer-const': 'error',

      // No duplicate imports
      'no-duplicate-imports': 'error',
    },
  },
])
