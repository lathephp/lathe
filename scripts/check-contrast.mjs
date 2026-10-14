// Fails when a theme token pair is hard to read, in light and dark.
// Usage: node scripts/check-contrast.mjs [app.css]
// The 8 styles (resources/css/styles) only shape components and declare no colour, so
// every style shares the tokens in app.css and one pass covers all of them.
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { modes, ratio, report } from './lib/colour.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const css = readFileSync(process.argv[2] ?? join(root, 'resources/css/app.css'), 'utf8');

const failures = [];
let checked = 0;

function expectRatio(mode, label, fg, bg, min) {
  checked++;
  const r = ratio(fg, bg);
  if (r < min) failures.push(`${mode}: ${label} is ${r.toFixed(2)}:1, needs ${min}:1 (${fg} on ${bg})`);
}

for (const [mode, t] of Object.entries(modes(css))) {
  for (const name of Object.keys(t)) {
    const base = name.replace(/-foreground$/, '');
    if (base !== name && t[base]) expectRatio(mode, `${name} on ${base}`, t[name], t[base], 4.5);
  }
  for (const ground of ['background', 'card']) {
    for (const name of ['ring', 'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5']) {
      expectRatio(mode, `${name} on ${ground}`, t[name], t[ground], 3);
    }
  }
}

process.exit(report(failures, checked));
