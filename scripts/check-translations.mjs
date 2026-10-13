// Checks the translation catalogue against the code: every key a source file passes to
// __() is in lang/en.json, every en.json key is used, and every other shipped locale
// holds every en.json key. Keys built at run time cannot be collected, so none are used.
// Run from the repo root; `--root <dir>` checks another tree (the tests use a fixture).
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import process from 'node:process';

const rootFlag = process.argv.indexOf('--root');
const root = rootFlag === -1 ? process.cwd() : process.argv[rootFlag + 1];

// Keys the code reaches through a variable, so no literal call site shows them.
const dynamic = new Set([]);

const skip = new Set(['node_modules', 'dist', 'vendor', '__tests__', '.claude']);

function* walk(dir) {
  let entries;

  try {
    entries = readdirSync(dir);
  } catch {
    return;
  }

  for (const name of entries) {
    if (skip.has(name)) {
      continue;
    }

    const path = join(dir, name);

    if (statSync(path).isDirectory()) {
      yield* walk(path);
    } else {
      yield path;
    }
  }
}

const unescape = (text) => text.replace(/\\(['"`\\])/g, '$1');

// A string-literal first argument: PHP `Lathe::__(` and TS `__(`, not `.__(` or `x__(`.
const calls = {
  php: /\bLathe::__\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")/g,
  ts: /(?<![\w.$])__\(\s*(?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)"|`((?:[^`\\$]|\\.)*)`)/g,
};

const used = new Map();

for (const dir of ['src', 'resources/js', 'packages']) {
  for (const file of walk(join(root, dir))) {
    const kind = file.endsWith('.php') ? 'php' : /\.(ts|tsx)$/.test(file) && !/\.test\./.test(file) && !file.endsWith('.d.ts') ? 'ts' : null;

    if (kind === null) {
      continue;
    }

    const source = readFileSync(file, 'utf8');

    for (const match of source.matchAll(calls[kind])) {
      const key = unescape(match[1] ?? match[2] ?? match[3]);
      used.set(key, used.get(key) ?? relative(root, file));
    }
  }
}

const langDir = join(root, 'lang');
const locales = readdirSync(langDir)
  .filter((name) => name.endsWith('.json'))
  .map((name) => [name, JSON.parse(readFileSync(join(langDir, name), 'utf8'))]);
const en = locales.find(([name]) => name === 'en.json')?.[1] ?? {};

const problems = [];

for (const [key, file] of used) {
  if (!(key in en)) {
    problems.push(`missing from en.json: "${key}" (used in ${file})`);
  }
}

for (const key of Object.keys(en)) {
  if (!used.has(key) && !dynamic.has(key)) {
    problems.push(`unused in en.json: "${key}"`);
  }
}

for (const [name, catalogue] of locales) {
  if (name === 'en.json') {
    continue;
  }

  for (const key of Object.keys(en)) {
    if (!(key in catalogue)) {
      problems.push(`missing from ${name}: "${key}"`);
    }
  }
}

if (problems.length > 0) {
  process.stderr.write(`${problems.join('\n')}\n\n${problems.length} translation problem${problems.length === 1 ? '' : 's'}\n`);
  process.exit(1);
}

process.stdout.write(`translations ok: ${used.size} keys, ${locales.length} locale file${locales.length === 1 ? '' : 's'}\n`);
