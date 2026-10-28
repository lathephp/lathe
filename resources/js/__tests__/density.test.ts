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
