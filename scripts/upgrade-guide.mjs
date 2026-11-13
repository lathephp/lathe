// Writes docs/getting-started/upgrade.md from the CHANGELOG: a section for every release, newest
// first, with its entries marked [api], then its other entries under "### Changed", each with the
// indented lines under it.
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

// Each release with an entry to tell, against the release just below it; the oldest is the first
// release, so nothing came before it.
const changes = releases
  .map((release, index) => ({ ...release, previous: releases[index + 1]?.version }))
  .filter((release) => release.previous && (release.api.length > 0 || release.behaviour.length > 0))
  .map((release) =>
    [
      `## From ${release.previous} to ${release.version}`,
      ...(release.api.length > 0 ? ['', '### API changes', '', list(release.api)] : []),
      ...(release.behaviour.length > 0 ? ['', '### Behaviour changes', '', list(release.behaviour)] : []),
    ].join('\n'),
  );

let body;
if (releases.length === 0) {
  body = 'There is no release yet.';
} else if (releases.length === 1) {
  body = `${releases[0].version} is the first release of Lathe, so there are no API changes to upgrade across.`;
} else if (changes.length === 0) {
  body = `No release since ${releases.at(-1).version} changes the public API. Run \`composer update lathe/lathe\` and you are done.`;
} else {
  body = [
    'It covers every release, newest first: each section holds the changes between a release and the one before it. Going up several releases, read each section from the release you run to the newest. Check each API change against your code before you update, and tell your users about the behaviour changes.',
    '',
    changes.join('\n\n'),
  ].join('\n');
}

writeFileSync(
  output,
  `---
title: Upgrade guide
description: The API and behaviour changes of every Lathe release since the one before it, taken from the changelog.
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
