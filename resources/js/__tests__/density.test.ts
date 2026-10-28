/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const nova = readFileSync('resources/css/styles/style-nova.css', 'utf8');

describe('nova density', () => {
  it('holds no 34 px control height', () => {
    expect(nova).not.toContain('34px');
  });

  it('makes the default button 32 px tall', () => {
    expect(nova).toMatch(/\.cn-button-size-default\s*\{\s*@apply h-8 /);
  });
});

// The panel's own buttons and inputs, which first-load pages use in place of the ui package's.
const panel = readFileSync('resources/css/app.css', 'utf8');

describe('panel control density', () => {
  it('holds no 34 px control height', () => {
    expect(panel).not.toContain('34px');
  });

  it('makes the default panel button 32 px tall', () => {
    expect(panel).toMatch(/\.lathe-button-size-default\s*\{\s*@apply h-8 /);
  });
});
