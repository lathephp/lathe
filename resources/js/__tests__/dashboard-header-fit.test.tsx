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

function dashboard() {
  renderPage(
    'Dashboard',
    {
      dashboard: {
        uriKey: 'main',
        name: 'Main',
        description: null,
        customizable: true,
        customized: false,
        ranges: [
          { value: 7, label: '7 days' },
          { value: 30, label: '30 days' },
        ],
        range: 7,
      },
      widgets: [
        {
          name: 'Orders',
          uriKey: 'orders',
          component: 'value-widget',
          width: '1/2',
          columns: 6,
          height: 'fixed',
          help: null,
          helpWidth: 250,
          refreshEvery: 0,
          refreshWhenActionsRun: false,
          refreshWhenFiltersChange: false,
          onlyOnDetail: false,
          requestsValue: true,
          ranges: [],
        },
      ],
    },
    {},
  );
}

describe('a dashboard header too narrow for one row', () => {
  it('sits in a container of its own, around the title, the controls and the range strip', () => {
    dashboard();
    const header = screen.getByRole('heading', { level: 1, name: 'Main' }).closest('.lathe-dashboard-header');

    expect(header).not.toBeNull();
    expect(header?.contains(screen.getByRole('radiogroup', { name: 'Range' }))).toBe(true);
    expect(header?.contains(screen.getByRole('button', { name: 'Refresh' }))).toBe(true);
    expect(header?.querySelector('.lathe-dashboard-actions')?.contains(screen.getByRole('button', { name: 'Customize' }))).toBe(true);
  });

  // jsdom does no layout, so this pins the rule: a browser check at 768 px measured the row 807 px wide.
  it('puts the range strip on a full-width row of its own under 768 px of header, from the md breakpoint up', () => {
    expect(block('.lathe-dashboard-header {')).toMatch(/container:\s*dashboard-header\s*\/\s*inline-size/);

    const narrow = block('@media (width >= 48rem)');
    expect(narrow).toContain('@container dashboard-header (width < 48rem)');
    expect(narrow).toMatch(/\.lathe-dashboard-header \.lathe-page-header \{[^}]*flex-wrap/);
    expect(narrow).toMatch(/\.lathe-dashboard-header \.lathe-page-actions,\s*\.lathe-dashboard-header \.lathe-dashboard-actions \{[^}]*contents/);
    expect(narrow).toMatch(/\.lathe-dashboard-header \[role='radiogroup'\] \{[^}]*order-last[^}]*basis-full[^}]*overflow-x-auto/);
  });
});
