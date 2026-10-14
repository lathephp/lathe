// Fails when a chart colour slot is hard to see on the card, in light and dark.
// Usage: node scripts/check-chart-slots.mjs [app.css] [PartitionColors.php]
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { modes, ratio, report } from './lib/colour.mjs';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const css = readFileSync(process.argv[2] ?? join(root, 'resources/css/app.css'), 'utf8');
const php = readFileSync(process.argv[3] ?? join(root, 'src/Widgets/PartitionColors.php'), 'utf8');

const palette = [...php.matchAll(/^\s*'(var\(--chart-\d\)|#[0-9a-fA-F]{3,8})',$/gm)].map((m) => m[1]);
const failures = [];
let checked = 0;

for (const [mode, t] of Object.entries(modes(css))) {
  palette.forEach((slot, i) => {
    const colour = slot.startsWith('var(') ? t[slot.slice(6, -1)] : slot;
    const r = ratio(colour, t.card);
    checked++;
    if (r < 3) failures.push(`${mode}: chart slot ${i + 1} ${slot} is ${r.toFixed(2)}:1 on the card, needs 3:1`);
  });
}

process.exit(report(failures, checked));
