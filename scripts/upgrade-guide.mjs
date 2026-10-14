// Writes docs/getting-started/upgrade.md from the CHANGELOG: every entry marked [api] in the
// newest release section and the sections after the previous release, newest first.
// Usage: node scripts/upgrade-guide.mjs [--changelog CHANGELOG.md] [--output docs/getting-started/upgrade.md]
import { readFileSync, writeFileSync } from 'node:fs';
import process from 'node:process';

const args = process.argv.slice(2);
const option = (name, fallback) => {
  const index = args.indexOf(name);
  return index === -1 ? fallback : args[index + 1];
};
const changelog = readFileSync(option('--changelog', 'CHANGELOG.md'), 'utf8');
const output = option('--output', 'docs/getting-started/upgrade.md');

// The release sections in order, newest first, each with its [api] lines.
const sections = [];
for (const line of changelog.split('\n')) {
  const heading = line.match(/^## \[([^\]]+)\]/);
  if (heading) {
    sections.push({ version: heading[1], api: [] });
  } else if (sections.length > 0 && /^- \[api\] /.test(line)) {
    sections.at(-1).api.push(line.replace(/^- \[api\] /, '- '));
  }
}
const releases = sections.filter((section) => section.version !== 'Unreleased');
const [current, previous] = releases;

let body;
if (!current) {
  body = 'There is no release yet.';
} else if (!previous) {
  body = `${current.version} is the first release of Lathe, so there are no API changes to upgrade across.`;
} else if (current.api.length === 0) {
  body = `${current.version} changes no public API since ${previous.version}. Run \`composer update lathe/lathe\` and you are done.`;
} else {
  body = [`These public API changes happen between ${previous.version} and ${current.version}. Check each one against your code before you update.`, '', `## ${previous.version} to ${current.version}`, '', ...current.api].join('\n');
}

writeFileSync(
  output,
  `---
title: Upgrade guide
description: The public API changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the entries marked \`[api]\` in the [changelog](changelog.md). It lists only changes to the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract.

${body}

## What you see

After \`composer update lathe/lathe\` and the changes above, \`php artisan lathe:doctor\` ends with \`0 FAIL\`.
`,
);
process.stdout.write(`wrote ${output}\n`);
