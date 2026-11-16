import { __panel, Icon, page, PageHeader } from '@lathephp/core';
import { render, screen, waitFor, within } from '@testing-library/react';
import { beforeAll, describe, expect, it } from 'vitest';
import { NavIcon } from '../components/NavIcon';
import { LentPageHeader } from '../layout/PageHeader';
import { renderPage } from './support';

// app.tsx lends the panel's page header and icon to plugins as these two.
beforeAll(() => {
  __panel.registerComponents({ PageHeader: LentPageHeader, Icon: NavIcon });
});

function Reports() {
  return <PageHeader title="Reports" description="Monthly numbers" actions={<a href="/lathe/reports.csv">Export</a>} />;
}

function renderReports() {
  page('reports', Reports);

  return renderPage('Page', { pageKey: 'reports', pagePath: '/lathe/pages/reports', path: '', title: 'Reports' }, { url: '/lathe/pages/reports' });
}

function ordersTitleClass() {
  const { unmount } = renderPage(
    'Resources/Index',
    {
      resource: {
        uriKey: 'orders',
        label: 'Orders',
        singularLabel: 'Order',
        perPageOptions: [25],
        searchable: true,
        abilities: { create: true },
        definition: { debounce: 0.5 },
      },
      table: { columns: [], sortKeys: [], rows: [], rowKey: 'id' },
      state: { page: 1, perPage: 25, hasMorePages: false, sort: '' },
      count: null,
    },
    { url: '/lathe/resources/orders' },
  );
  const className = screen.getByRole('heading', { level: 1, name: 'Orders' }).className;
  unmount();

  return className;
}

describe('the Icon core lends to plugins', () => {
  // First: once the icon set has loaded, later icons draw at once.
  it('holds an empty box of the size it was given while the icon set loads', () => {
    const { container } = render(<Icon name="Truck" className="size-6" />);
    const box = container.firstElementChild;

    expect(box?.tagName).toBe('SPAN');
    expect(box?.childElementCount).toBe(0);
    expect(box?.getAttribute('class')).toContain('size-6');
  });

  it('draws the shopping cart by its Lucide name and by its Icon case name', () => {
    const kebab = render(<Icon name="shopping-cart" />);
    const cased = render(<Icon name="ShoppingCart" className="size-5" />);

    expect(kebab.container.querySelector('svg.lucide-shopping-cart')).not.toBeNull();
    expect(cased.container.querySelector('svg.lucide-shopping-cart')?.getAttribute('class')).toContain('size-5');
  });

  it('draws the circle for an unknown name, in the size it was given', async () => {
    const { container } = render(<Icon name="no-such-icon" className="size-6" />);

    await waitFor(() => {
      expect(container.querySelector('svg.lucide-circle')?.getAttribute('class')).toContain('size-6');
    });
  });
});

describe('the PageHeader core lends to plugins', () => {
  it("draws the title as the panel's page title, with the description under it", () => {
    const orders = ordersTitleClass();
    renderReports();
    const title = within(screen.getByRole('main')).getByRole('heading', { level: 1, name: 'Reports' });

    expect(title.className).toBe(orders);
    expect(within(screen.getByRole('main')).getByText('Monthly numbers').className).toBe('lathe-page-subtitle');
    expect(title.closest('[data-lathe-header]')?.querySelector('.lathe-page-actions a')?.textContent).toBe('Export');
  });

  it("fills the phone top bar with the title, as a panel page's does", () => {
    renderReports();
    const bar = document.querySelector('.lathe-page-bar-title');

    expect(bar?.textContent).toBe('Reports');
    expect(bar?.closest('header.lathe-topbar')).not.toBeNull();
  });
});
