/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { expect, it } from 'vitest';

function read(path: string): unknown {
  return JSON.parse(readFileSync(new URL(path, import.meta.url), 'utf8'));
}

function version(folder: string): unknown {
  return (read(`../../../${folder}/package.json`) as { version?: unknown }).version;
}

it('pins one version for the three npm packages and the composer branch', () => {
  const composer = read('../../../../composer.json') as { extra?: { 'branch-alias'?: { 'dev-main'?: string } } };
  // The branch alias 1.0.x-dev is the npm pre-release 1.0.0-dev.
  const branch = composer.extra?.['branch-alias']?.['dev-main']?.replace('.x-dev', '.0-dev');

  const versions = [version('core'), version('ui'), version('vite-plugin')];

  expect(branch).toBe('1.0.0-dev');
  expect(versions).toEqual([versions[0], versions[0], versions[0]]);
  // main carries the branch pre-release; a release commit carries the tag's version of the same line.
  expect(versions[0]).toMatch(/^1\.0\.0(-dev|-(alpha|beta|rc)\.\d+)?$/);
});

it('names the GitHub repository in each npm package, which npm checks against the provenance', () => {
  for (const folder of ['core', 'ui', 'vite-plugin']) {
    expect((read(`../../../${folder}/package.json`) as { repository?: unknown }).repository).toEqual({
      type: 'git',
      url: 'git+https://github.com/lathephp/lathe.git',
      directory: `packages/${folder}`,
    });
  }
});

it('gives the three npm packages one React peer range, as @lathephp/ui declares it', () => {
  const peers = Object.fromEntries(
    ['core', 'ui', 'vite-plugin'].map((folder) => {
      const json = read(`../../../${folder}/package.json`) as { peerDependencies?: Record<string, string> };
      const peer = json.peerDependencies ?? {};

      return [folder, { react: peer.react, 'react-dom': peer['react-dom'] }];
    }),
  );
  const declared = Object.values(peers).flatMap((peer) => Object.values(peer).filter((range) => range !== undefined));

  expect(peers.ui.react).toBe('^19.3.0');
  expect(declared).toEqual(declared.map(() => peers.ui.react));
});
