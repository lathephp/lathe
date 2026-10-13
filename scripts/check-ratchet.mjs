// Fails when a change lowers a coverage floor. The floors live in
// coverage-floors.json (vitest.config.ts reads them); a floor may only rise.
// Usage: node scripts/check-ratchet.mjs [base-ref]   (default origin/main, then main)
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import process from 'node:process';

function git(args) {
  return execFileSync('git', args, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] });
}

function resolveBase() {
  if (process.argv[2]) {
    return process.argv[2];
  }

  try {
    git(['rev-parse', '--verify', '-q', 'origin/main']);

    return 'origin/main';
  } catch {
    return 'main';
  }
}

function say(line) {
  process.stdout.write(`${line}\n`);
}

const base = resolveBase();
const head = JSON.parse(readFileSync('coverage-floors.json', 'utf8'));
let before;

try {
  before = JSON.parse(git(['show', `${base}:coverage-floors.json`]));
} catch {
  say(`check-ratchet: ${base} has no coverage-floors.json, nothing to compare`);
  process.exit(0);
}

const lowered = [];

for (const [path, metrics] of Object.entries(before)) {
  for (const [metric, floor] of Object.entries(metrics)) {
    const now = head[path]?.[metric];

    if (now === undefined || now < floor) {
      lowered.push(`${path} ${metric}: ${String(floor)} -> ${now === undefined ? 'removed' : String(now)}`);
    }
  }
}

if (lowered.length > 0) {
  say('check-ratchet: a coverage floor went down. Floors may only rise:');
  lowered.forEach((line) => {
    say(`  ${line}`);
  });
  process.exit(1);
}

say('check-ratchet: no floor went down');
