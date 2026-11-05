/// <reference types="node" />
import { readFileSync } from 'node:fs';
import { screen, within } from '@testing-library/react';
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

function renderOrder() {
  renderPage(
    'Resources/Detail',
    {
      resource: { uriKey: 'orders', label: 'Orders', singularLabel: 'Order' },
      record: { id: 12, title: 'ORD-1', subtitle: '', avatar: null, fields: [], layout: [], abilities: {}, trashed: false },
      actions: { actions: [] },
      widgets: [],
      breadcrumbs: [
        { label: 'Dashboard', href: '/lathe' },
        { label: 'Orders', href: '/lathe/resources/orders' },
        { label: 'ORD-1', href: null },
      ],
    },
    { url: '/lathe/resources/orders/12', shared: { globalSearch: { enabled: true, debounce: 0.5 } } },
  );
}

describe('a top bar too narrow for the search box beside the trail', () => {
  it('holds the trail and the search button in the top bar the rules query', () => {
    renderOrder();
    const bar = screen.getByRole('banner');

    expect(bar.classList.contains('lathe-topbar')).toBe(true);
    expect(within(bar).getByRole('button', { name: 'Search' }).classList.contains('lathe-topbar-search')).toBe(true);
    expect(within(bar).getByRole('navigation', { name: 'Breadcrumb' }).querySelector('li:last-child > [aria-current="page"]')?.textContent).toBe(
      'ORD-1',
    );
  });

  // jsdom does no layout, so this pins the rule: a browser check at 768x1024 with the sidebar open
  // measured the box's left edge at 346 px and the last crumb's right edge at 445 px.
  it('drops the search box to its icon under 45rem of top bar, from the md breakpoint up', () => {
    expect(block('.lathe-topbar {')).toMatch(/container:\s*topbar\s*\/\s*inline-size/);

    const narrow = block('@container topbar (width < 45rem)');
    expect(narrow).toMatch(/\.lathe-topbar-search \{[^}]*md:w-8[^}]*md:justify-center[^}]*md:border-0[^}]*md:bg-transparent[^}]*md:px-0/);
    expect(narrow).toMatch(/\.lathe-topbar-search > span,\s*\.lathe-topbar-search > kbd \{[^}]*hidden/);
  });

  it('lets the last crumb, and only it, give way with an ellipsis when even the icon does not fit', () => {
    expect(block('.lathe-crumbs li:not(:last-child)')).toMatch(/^[^{]*\.lathe-crumbs svg \{[^}]*shrink-0/);
    expect(block('.lathe-crumbs li:last-child {')).toMatch(/min-w-0/);
    expect(block('.lathe-crumbs [aria-current="page"] {')).toMatch(/truncate/);
  });
});
