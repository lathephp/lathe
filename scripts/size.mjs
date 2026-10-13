// B4: the first-load JavaScript of each panel route, gzip -9 bytes, against the 200 KB budget.
// A route loads the app entry, the page component it renders and the import-map entries
// (react, react-dom, @lathephp/core, @lathephp/ui), each with every chunk it imports
// statically. CSS, fonts and plugin modules are left out.
// Usage: node scripts/size.mjs [dist-folder] [--json]; exits 1 when a route is over budget.
import { readFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import process from 'node:process';
import { gzipSync } from 'node:zlib';

const budget = 200_000;
const args = process.argv.slice(2);
const dist = resolve(args.find((arg) => !arg.startsWith('--')) ?? 'dist');
const manifest = JSON.parse(readFileSync(join(dist, 'manifest.json'), 'utf8'));

// The route a person opens and the page component it renders. m3 adds the create form.
const routes = {
  '/lathe': 'resources/js/pages/Dashboard.tsx',
  '/lathe/resources/orders': 'resources/js/pages/Resources/Index.tsx',
};

function closure(keys, seen = new Set()) {
  for (const key of keys) {
    const chunk = manifest[key];

    if (chunk === undefined || seen.has(key)) {
      continue;
    }

    seen.add(key);
    closure(chunk.imports ?? [], seen);
  }

  return seen;
}

const vendors = Object.keys(manifest).filter((key) => key.startsWith('lathe-vendor:'));
const results = Object.entries(routes).map(([route, page]) => {
  const files = [...new Set([...closure(['resources/js/app.tsx', page, ...vendors])].map((key) => manifest[key].file))]
    .filter((file) => file.endsWith('.js'))
    .sort();
  const bytes = files.reduce((sum, file) => sum + gzipSync(readFileSync(join(dist, file)), { level: 9 }).length, 0);

  return { route, bytes, budget, files };
});

if (args.includes('--json')) {
  process.stdout.write(`${JSON.stringify(results, null, 2)}\n`);
} else {
  for (const { route, bytes } of results) {
    process.stdout.write(`${route} ${bytes} bytes gzip (budget ${budget})\n`);
  }
}

process.exit(results.every(({ bytes }) => bytes <= budget) ? 0 : 1);
