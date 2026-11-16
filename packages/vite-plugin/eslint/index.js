import rule from './no-raw-ui.js';

// No parser is set here: the app's own config parses its JSX and TSX.
export default [
  {
    files: ['resources/js/lathe/**/*.tsx'],
    plugins: { lathe: { rules: { 'no-raw-ui': rule } } },
    rules: { 'lathe/no-raw-ui': 'error' },
  },
];
