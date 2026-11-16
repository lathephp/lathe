import js from '@eslint/js';
import { defineConfig, globalIgnores } from 'eslint/config';
import jsxA11y from 'eslint-plugin-jsx-a11y';
import reactHooks from 'eslint-plugin-react-hooks';
import tseslint from 'typescript-eslint';
import noMixedHistoryTests from './eslint-rules/no-mixed-history-tests.js';
import noRawUi from './eslint-rules/no-raw-ui.js';
import noRefInPassiveEffect from './eslint-rules/no-ref-in-passive-effect.js';
import noUntranslatedLiteral from './eslint-rules/no-untranslated-literal.js';

const lathe = {
  rules: {
    'no-mixed-history-tests': noMixedHistoryTests,
    'no-raw-ui': noRawUi,
    'no-ref-in-passive-effect': noRefInPassiveEffect,
    'no-untranslated-literal': noUntranslatedLiteral,
  },
};

// The panel files whose raw controls m47 has not moved to the kit yet; each sweep task empties its part.
const notYetSwept = [
  'resources/js/layout/NavSection.tsx',
  'resources/js/widgets/HelpTooltip.tsx',
  'resources/js/components/ConfirmPasswordDialog.tsx',
  'resources/js/fields/choice/SelectForms.tsx',
  'resources/js/fields/date/DatePicker.tsx',
  'resources/js/fields/date/DateText.tsx',
  'resources/js/fields/key-value/KeyValueFields.tsx',
  'resources/js/fields/number/TokenInput.tsx',
  'resources/js/fields/relation/BelongsToForm.tsx',
  'resources/js/fields/relation/CreateRelationSheet.tsx',
  'resources/js/fields/relation/TagForm.tsx',
  'resources/js/fields/repeater/RepeaterFields.tsx',
  'resources/js/fields/rich/CodeEditor.tsx',
  'resources/js/fields/rich/MarkdownEditor.tsx',
  'resources/js/fields/rich/TiptapEditor.tsx',
  'resources/js/fields/slug/SlugInput.tsx',
  'resources/js/fields/upload/FileControl.tsx',
  'resources/js/index/ColumnPicker.tsx',
  'resources/js/index/InlineEditor.tsx',
  'resources/js/index/RenameTabDialog.tsx',
  'resources/js/index/RowMenuPanel.tsx',
  'resources/js/index/SaveTabDialog.tsx',
  'resources/js/index/board/Board.tsx',
  'resources/js/index/board/MoveMenu.tsx',
  'resources/js/index/filters/RangeFilter.tsx',
  'resources/js/layout/AppearanceSwitcher.tsx',
  'resources/js/layout/ImpersonationBanner.tsx',
  'resources/js/notifications/NotificationItem.tsx',
  'resources/js/notifications/NotificationPanel.tsx',
  'resources/js/pages/Auth/ConfirmPassword.tsx',
  'resources/js/pages/Auth/ForgotPassword.tsx',
  'resources/js/pages/Auth/Login.tsx',
  'resources/js/pages/Auth/ResetPassword.tsx',
  'resources/js/pages/Auth/TwoFactorChallenge.tsx',
  'resources/js/pages/Security.tsx',
  'resources/js/palette/SearchPalette.tsx',
  'resources/js/plugins/PluginSettingsForm.tsx',
  'resources/js/records/HistoryCard.tsx',
  'resources/js/relations/RelationPanel.tsx',
  'resources/js/widgets/charts/RadialCard.tsx',
  'resources/js/widgets/customize/CustomizeMode.tsx',
];

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
    plugins: { lathe },
    rules: { 'lathe/no-untranslated-literal': 'error' },
  },
  {
    // Panel code draws its controls with the kit or the panel's copies in components/ui, and its
    // colours with theme tokens; the kit and those copies are where the raw elements live.
    files: ['resources/js/**/*.tsx'],
    ignores: ['**/__tests__/**', 'resources/js/components/ui/**', ...notYetSwept],
    plugins: { lathe },
    rules: { 'lathe/no-raw-ui': 'error' },
  },
  {
    // A ref synced in a passive effect is stale for a key pressed before that effect runs.
    files: ['resources/js/**/*.{ts,tsx}', 'packages/*/src/**/*.{ts,tsx}'],
    ignores: ['**/__tests__/**'],
    plugins: { lathe },
    rules: { 'lathe/no-ref-in-passive-effect': 'error' },
  },
  {
    // A test that asserts the URL or the history leaks router state into its neighbours.
    files: ['resources/js/**/__tests__/**/*.{ts,tsx}'],
    plugins: { lathe },
    rules: { 'lathe/no-mixed-history-tests': 'error' },
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
