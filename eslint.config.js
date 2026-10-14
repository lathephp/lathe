import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';
import noUntranslatedLiteral from './eslint-rules/no-untranslated-literal.js';

export default defineConfig([
  // A worktree under .claude carries its own copy of this config; vendor is the PHP side.
  // Stubs are templates: the scaffold quality test lints what they render to.
  globalIgnores(['.claude/**', 'dist/**', 'packages/*/dist/**', 'vendor/**', 'plugins/*/vendor/**', 'workbench/**', 'stubs/**']),
  js.configs.recommended,
  tseslint.configs.strictTypeChecked,
  {
    languageOptions: {
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    linterOptions: {
      reportUnusedDisableDirectives: 'error',
    },
  },
  reactHooks.configs.flat.recommended,
  jsxA11y.flatConfigs.recommended,
  {
    rules: {
      // A wide table's scroll container is a focusable region, so keyboard users can scroll it.
      'jsx-a11y/no-noninteractive-tabindex': ['error', { roles: ['tabpanel', 'region'], tags: [] }],
    },
  },
  {
    // Client source only: tests assert on literal text, and the tooling has no UI.
    files: ['resources/js/**/*.tsx', 'packages/*/src/**/*.tsx'],
    ignores: ['**/__tests__/**'],
    plugins: { lathe: { rules: { 'no-untranslated-literal': noUntranslatedLiteral } } },
    rules: { 'lathe/no-untranslated-literal': 'error' },
  },
  {
    files: ['**/*.js', '**/*.mjs'],
    extends: [tseslint.configs.disableTypeChecked],
  },
  {
    // Base UI stays behind the wrappers in packages/ui/src, so a Base UI upgrade changes one folder.
    files: ['**/*.{js,jsx,mjs,ts,tsx}'],
    ignores: ['packages/ui/src/**'],
    rules: {
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            {
              group: ['@base-ui/react', '@base-ui/react/*'],
              message: 'Import Base UI only inside packages/ui/src and use the wrapper from @lathephp/ui.',
            },
          ],
        },
      ],
    },
  },
]);
