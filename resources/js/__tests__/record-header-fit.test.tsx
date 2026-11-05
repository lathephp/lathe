/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

// Read from disk: Vitest turns a stylesheet import into an empty string.
const css = readFileSync('resources/css/app.css', 'utf8');

/** The body of the block that opens with `opener`, braces balanced. */
function block(opener: string): string {
  const start = css.indexOf(opener);

  if (start === -1) {
    return '';
  }

  let depth = 0;

  for (let index = css.indexOf('{', start); index < css.length; index++) {
    depth += css[index] === '{' ? 1 : css[index] === '}' ? -1 : 0;

    if (depth === 0) {
      return css.slice(start, index + 1);
    }
  }

  return '';
}

function order() {
  renderPage('Resources/Detail', {
    resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } },
    breadcrumbs: [],
    record: {
      id: 12,
      title: 'ORD-00012345',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [],
      abilities: { view: true, update: true, delete: true, replicate: true, restore: false, forceDelete: false },
      trashed: false,
    },
  });
}

describe('a record header too narrow for its title and buttons on one row', () => {
  it('keeps the title in a heading before the buttons, so the buttons are what wraps below', () => {
    order();
    const title = screen.getByRole('heading', { level: 1, name: 'ORD-00012345' });
    const heading = title.closest('.lathe-page-heading');
    const actions = heading?.nextElementSibling;

    expect(title.className).toBe('lathe-page-title');
    expect(heading?.parentElement?.className).toBe('lathe-page-header');
    expect(heading?.parentElement?.firstElementChild).toBe(heading);
    expect(actions?.className).toBe('lathe-page-actions');
    expect(actions?.contains(screen.getByRole('button', { name: 'Delete' }))).toBe(true);
    expect(actions?.contains(screen.getByRole('link', { name: 'Replicate' }))).toBe(true);
    expect(actions?.contains(screen.getByRole('link', { name: 'Edit' }))).toBe(true);
  });

  // jsdom does no layout, so this pins the rule: at 768 px with the sidebar open the trunk drew the h1
  // "ORD-00012345" 54 px wide with a scrollWidth of 196, cut to an ellipsis by the buttons beside it.
  it('wraps the buttons to a row below from the md breakpoint up and wraps the title instead of cutting it', () => {
    expect(block('.lathe-page-header {')).toMatch(/@apply[^;]*\bmd:flex-wrap\b/);
    expect(block('.lathe-page-heading {')).toMatch(/@apply[^;]*\bmin-w-0\b/);

    const title = block('.lathe-page-title {');
    expect(title).not.toMatch(/\btruncate\b/);
    expect(title).toMatch(/@apply[^;]*\bwrap-anywhere\b/);
    expect(title).toMatch(/@apply[^;]*\bmin-w-0\b/);

    // A phone keeps its title in the top bar and the h1 for screen readers.
    expect(block('[data-page-bar] .lathe-page-title {')).toMatch(/@apply\s+max-md:sr-only;/);
  });
});
