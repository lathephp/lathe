// Writes docs/getting-started/upgrade.md from the CHANGELOG: the newest release's entries marked
// [api], then its other entries under "### Changed", each with the indented lines under it.
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

// The release sections in order, newest first, each with its [api] entries and its other
// entries under "### Changed". An entry keeps the indented lines under it, such as an example.
const sections = [];
let subsection = null;
let entry = null;
for (const line of changelog.split('\n')) {
  const heading = line.match(/^## \[([^\]]+)\]/);
  if (heading) {
    sections.push({ version: heading[1], api: [], behaviour: [] });
    subsection = null;
    entry = null;
  } else if (line.startsWith('### ')) {
    subsection = line.slice(4);
    entry = null;
  } else if (sections.length === 0) {
    continue;
  } else if (/^- \[api\] /.test(line)) {
    entry = [line.replace(/^- \[api\] /, '- ')];
    sections.at(-1).api.push(entry);
  } else if (line.startsWith('- ') && subsection === 'Changed') {
    entry = [line];
    sections.at(-1).behaviour.push(entry);
  } else if (entry && (line === '' || line.startsWith('  '))) {
    entry.push(line);
  } else {
    entry = null;
  }
}
const list = (entries) => entries.map((lines) => lines.join('\n').trimEnd()).join('\n');
const releases = sections.filter((section) => section.version !== 'Unreleased');
const [current, previous] = releases;

let body;
if (!current) {
  body = 'There is no release yet.';
} else if (!previous) {
  body = `${current.version} is the first release of Lathe, so there are no API changes to upgrade across.`;
} else if (current.api.length === 0 && current.behaviour.length === 0) {
  body = `${current.version} changes no public API since ${previous.version}. Run \`composer update lathe/lathe\` and you are done.`;
} else {
  body = [
    `These changes happen between ${previous.version} and ${current.version}. Check each API change against your code before you update, and tell your users about the behaviour changes.`,
    '',
    `## From ${previous.version} to ${current.version}`,
    ...(current.api.length > 0 ? ['', '### API changes', '', list(current.api)] : []),
    ...(current.behaviour.length > 0 ? ['', '### Behaviour changes', '', list(current.behaviour)] : []),
  ].join('\n');
}

writeFileSync(
  output,
  `---
title: Upgrade guide
description: The API and behaviour changes between two Lathe releases, taken from the changelog.
order: 8
---

# Upgrade guide

This page is generated from the [changelog](changelog.md). Its API changes are the entries marked \`[api]\`, which change the public API: the classes and methods in the API snapshot, the artisan commands, the config keys, the route names and the JSON contract. Its behaviour changes are the other entries under Changed, which change what the panel does for your users.

${body}

## What you see

After \`composer update lathe/lathe\` and the changes above, \`php artisan lathe:doctor\` ends with \`0 FAIL\`.
`,
);
process.stdout.write(`wrote ${output}\n`);
