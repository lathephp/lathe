import { waitFor } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { renderPage } from './support';

const dashboard = {
  dashboard: { uriKey: 'main', name: 'Sales', description: null, icon: null, showRefreshButton: false, ranges: [], range: null },
  widgets: [],
};

// Unmounting a page's <Head> schedules Inertia's head update on a timer. Left pending, it
// fires after the file's jsdom is gone and fails the whole run with "document is not defined".
describe('the head update of an unmounted page', () => {
  it('writes the page title while the page is open', async () => {
    renderPage('Dashboard', dashboard);

    await waitFor(() => {
      expect(document.title).toContain('Sales');
    });
  });

  it('has run before the next test starts', () => {
    expect(document.title).not.toContain('Sales');
  });
});
