import { router } from '@inertiajs/react';
import { __panel, toast } from '@lathephp/core';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { StaleAssetScreen } from '../components/StaleAssetScreen';
import { Toaster } from '../components/Toaster';
import { assetHashFromUrl, markBuildStale, resetBuildStale, setEntryHash } from '../lib/assets';
import { showFlash } from '../lib/visits';
import { bracketed, renderPage, visibleStrings } from './support';

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false }, widgets: [] };

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
  setEntryHash(null);
});

function sidebar(): HTMLElement {
  return screen.getByRole('complementary');
}

describe('dashboard', () => {
  it('shows its title and the empty state', () => {
    renderPage('Dashboard', dashboard);

    expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Dashboard');
    expect(screen.getByText('No widgets yet')).toBeTruthy();
  });

  it('points an empty dashboard at the widget generator and its docs', () => {
    renderPage('Dashboard', dashboard);

    expect(screen.getByText('php artisan lathe:widget TotalUsers --type=value').tagName).toBe('CODE');
    expect(screen.getByRole('link', { name: 'Read the widget docs' }).getAttribute('href')).toBe('https://lathephp.com/docs/widgets');
  });
});

describe('sidebar', () => {
  it('lists the navigation sections with their icons', () => {
    renderPage('Dashboard', dashboard);
    const nav = within(sidebar());

    expect(nav.getByRole('link', { name: 'Dashboard' }).getAttribute('href')).toBe('/lathe');
    expect(nav.getByText('Resources')).toBeTruthy();
    expect(nav.getByRole('link', { name: 'Orders' }).getAttribute('href')).toBe('/lathe/resources/orders');
    expect(nav.getByText('Heartbeat', { selector: 'h2' })).toBeTruthy();

    const heartbeat = nav.getByRole('link', { name: 'Heartbeat' });

    expect(heartbeat.getAttribute('href')).toBe('/lathe/plugins/heartbeat');
    expect(heartbeat.querySelector('svg.lucide-activity')).not.toBeNull();
  });

  it('marks the link of the current page', () => {
    renderPage('Dashboard', dashboard, { url: '/lathe/resources/orders?page=2' });
    const nav = within(sidebar());

    expect(nav.getByRole('link', { name: 'Orders' }).getAttribute('aria-current')).toBe('page');
    expect(nav.getByRole('link', { name: 'Dashboard' }).getAttribute('aria-current')).toBeNull();
  });

  it('shows the logo image beside the app name when a logo is set', () => {
    renderPage('Dashboard', dashboard, { shared: { brand: { logo: '/logo.svg', logoDark: null } } });
    const nav = within(sidebar());

    expect(nav.getByRole('img', { name: 'Acme' }).getAttribute('src')).toBe('/logo.svg');
    expect(nav.getByText('Acme')).toBeTruthy();
    expect(nav.queryByText('A')).toBeNull();
  });

  it('swaps in the dark logo with the dark class when one is set', () => {
    renderPage('Dashboard', dashboard, { shared: { brand: { logo: '/logo.svg', logoDark: '/logo-dark.svg' } } });
    const images = within(sidebar()).getAllByRole('img', { hidden: true });

    expect(images.map((img) => img.getAttribute('src'))).toEqual(['/logo.svg', '/logo-dark.svg']);
    expect(images[0]?.classList.contains('dark:hidden')).toBe(true);
    expect(images[1]?.classList.contains('hidden')).toBe(true);
    expect(images[1]?.classList.contains('dark:block')).toBe(true);
  });

  it('shows no image without a logo', () => {
    renderPage('Dashboard', dashboard);

    expect(within(sidebar()).queryByRole('img', { hidden: true })).toBeNull();
  });

  it('shows the app name beside its first letter and the version label', () => {
    renderPage('Dashboard', dashboard);
    const nav = within(sidebar());

    expect(nav.getByText('Acme')).toBeTruthy();
    expect(nav.getByText('A')).toBeTruthy();
    expect(nav.getByText('v1.0.0')).toBeTruthy();
  });

  it('hides a null version label', () => {
    renderPage('Dashboard', dashboard, { shared: { versionLabel: null } });

    expect(within(sidebar()).queryByText('v1.0.0')).toBeNull();
  });

  it('collapses to the rail and remembers it in a cookie', () => {
    const cookie = vi.spyOn(document, 'cookie', 'set');
    renderPage('Dashboard', dashboard);

    expect(sidebar().dataset.state).toBe('expanded');

    fireEvent.click(screen.getByRole('button', { name: 'Toggle sidebar' }));

    expect(sidebar().dataset.state).toBe('collapsed');
    expect(sidebar().className).toContain('md:w-(--sidebar-width-icon)');
    expect(cookie).toHaveBeenCalledWith('sidebar_state=false; path=/; max-age=31536000; SameSite=Lax');
  });

  it('paints the rail collapsed when the cookie said so', () => {
    renderPage('Dashboard', dashboard, { shared: { sidebarOpen: false } });

    expect(sidebar().dataset.state).toBe('collapsed');
    expect(sidebar().className).toContain('md:w-(--sidebar-width-icon)');
  });

  it('has no toggle when the sidebar is not collapsible', () => {
    renderPage('Dashboard', dashboard, {
      shared: {
        sidebarOpen: false,
        layout: {
          sidebar: { collapsible: 'none', width: '16rem', widthIcon: '3rem' },
          header: { sticky: true, search: true },
          content: { width: 'full' },
        },
      },
    });

    expect(screen.queryByRole('button', { name: 'Toggle sidebar' })).toBeNull();
    expect(sidebar().dataset.state).toBe('expanded');
  });

  it('opens as a sheet from the header on a narrow screen', () => {
    renderPage('Dashboard', dashboard);

    expect(sidebar().dataset.mobile).toBe('closed');

    fireEvent.click(screen.getByRole('button', { name: 'Open menu' }));

    expect(sidebar().dataset.mobile).toBe('open');

    fireEvent.click(screen.getByRole('button', { name: 'Close menu' }));

    expect(sidebar().dataset.mobile).toBe('closed');
  });
});

describe('layout', () => {
  it('starts the tab order with a skip link', () => {
    renderPage('Dashboard', dashboard);
    const first = document.body.querySelector('a[href], button, input, select, textarea');

    expect(first?.textContent).toBe('Skip to content');
    expect(first?.getAttribute('href')).toBe('#content');
    expect(document.getElementById('content')?.tagName).toBe('MAIN');
  });

  it('centres contained content at 96rem', () => {
    renderPage('Dashboard', dashboard, {
      shared: {
        layout: {
          sidebar: { collapsible: 'icon', width: '16rem', widthIcon: '3rem' },
          header: { sticky: true, search: true },
          content: { width: 'contained' },
        },
      },
    });

    expect(screen.getByRole('main').firstElementChild?.className).toContain('mx-auto max-w-384');
  });

  it('lets full width content fill the inset', () => {
    renderPage('Dashboard', dashboard);

    expect(screen.getByRole('main').firstElementChild?.className).not.toContain('max-w-384');
  });

  it('sticks the header unless the layout turns it off', () => {
    const { unmount } = renderPage('Dashboard', dashboard);

    expect(screen.getByRole('banner').className).toContain('sticky top-0');
    unmount();

    renderPage('Dashboard', dashboard, {
      shared: {
        layout: {
          sidebar: { collapsible: 'icon', width: '16rem', widthIcon: '3rem' },
          header: { sticky: false, search: true },
          content: { width: 'full' },
        },
      },
    });

    expect(screen.getByRole('banner').className).not.toContain('sticky');
  });

  it('hides the header search button when the layout turns it off', () => {
    const globalSearch = { enabled: true, debounce: 0.5 };
    const { unmount } = renderPage('Dashboard', dashboard, { shared: { globalSearch } });

    expect(screen.getByRole('button', { name: 'Search' })).toBeTruthy();
    unmount();

    renderPage('Dashboard', dashboard, {
      shared: {
        layout: {
          sidebar: { collapsible: 'icon', width: '16rem', widthIcon: '3rem' },
          header: { sticky: true, search: false },
          content: { width: 'full' },
        },
        globalSearch,
      },
    });

    expect(screen.queryByRole('button', { name: 'Search' })).toBeNull();
  });

  it('links the footer to lathephp.com', () => {
    renderPage('Dashboard', dashboard);

    expect(screen.getByRole('link', { name: 'Powered by Lathe' }).getAttribute('href')).toBe('https://lathephp.com');
  });

  it('hides a null footer', () => {
    renderPage('Dashboard', dashboard, { shared: { footer: null } });

    expect(screen.queryByText('Powered by Lathe')).toBeNull();
    expect(screen.queryByRole('contentinfo')).toBeNull();
  });
});

describe('user menu', () => {
  it('shows the user and logs out with a POST', () => {
    const post = vi.spyOn(router, 'post').mockImplementation(() => undefined);
    renderPage('Dashboard', dashboard);

    fireEvent.click(within(sidebar()).getByRole('button', { name: /Ada Lovelace/ }));

    expect(screen.getByText('ada@example.com')).toBeTruthy();

    fireEvent.click(screen.getByRole('menuitem', { name: 'Log out' }));

    expect(post).toHaveBeenCalledWith('/lathe/logout');
  });
});

describe('toasts', () => {
  it('renders the toasts of the panel store and dismisses one', async () => {
    render(<Toaster />);

    act(() => {
      toast.success('Order saved');
      toast.error('The order could not be saved.');
    });

    expect((await screen.findByRole('status')).textContent).toContain('Order saved');
    expect(screen.getByRole('alert').textContent).toContain('The order could not be saved.');

    fireEvent.click(within(screen.getByRole('status')).getByRole('button', { name: 'Dismiss' }));

    await waitFor(() => {
      expect(screen.queryByText('Order saved')).toBeNull();
    });
    expect(__panel.toasts.get()).toHaveLength(1);
  });

  it('turns the flash messages into toasts', async () => {
    render(<Toaster />);

    act(() => {
      showFlash({ success: 'Order saved', error: null });
      showFlash({ success: null, error: 'Order failed' });
    });

    expect((await screen.findByRole('status')).textContent).toContain('Order saved');
    expect(screen.getByRole('alert').textContent).toContain('Order failed');
    expect(__panel.toasts.get()).toHaveLength(2);
  });
});

describe('stale assets', () => {
  it('reads the asset hash from the entry URL', () => {
    expect(assetHashFromUrl('https://shop.test/lathe/assets/k3j9x2/app-8f2kq0za.js')).toBe('k3j9x2');
    expect(assetHashFromUrl('https://shop.test/admin/assets/mryjopee/app-mryjopee.js')).toBe('mryjopee');
    expect(assetHashFromUrl('http://localhost:5173/resources/js/app.tsx')).toBeNull();
  });

  it('blocks the screen when the server serves another build', () => {
    const reload = vi.fn();
    vi.stubGlobal('location', { reload });
    setEntryHash('abc123');
    render(<StaleAssetScreen />);

    expect(screen.queryByText('Lathe was updated')).toBeNull();

    act(() => {
      __panel.sharedProps.set({ assetHash: 'def456' });
    });

    expect(screen.getByRole('alertdialog').textContent).toContain('Lathe was updated');
    expect(screen.getByText('If this keeps happening, run php artisan lathe:assets --force')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Reload' }));

    expect(reload).toHaveBeenCalledOnce();
  });

  it('shows when a page of the old build fails to load', () => {
    setEntryHash('abc123');
    __panel.sharedProps.set({ assetHash: 'abc123' });
    render(<StaleAssetScreen />);

    expect(screen.queryByText('Lathe was updated')).toBeNull();

    act(() => {
      markBuildStale();
    });

    expect(screen.getByRole('alertdialog').textContent).toContain('Lathe was updated');
    resetBuildStale();
  });

  it('stays hidden while the hashes match', () => {
    setEntryHash('abc123');
    __panel.sharedProps.set({ assetHash: 'abc123' });
    render(<StaleAssetScreen />);

    expect(screen.queryByText('Lathe was updated')).toBeNull();
  });
});

describe('translation', () => {
  it('sends every string of the shell through the translator', () => {
    __panel.setTranslator(bracketed);
    renderPage('Dashboard', dashboard);
    fireEvent.click(within(sidebar()).getByRole('button', { name: /Ada Lovelace/ }));

    const data = new Set([
      'Acme',
      'A',
      'v1.0.0',
      'Dashboard',
      'Resources',
      'Orders',
      'Heartbeat',
      'AL',
      'Ada Lovelace',
      'ada@example.com',
      'Powered by Lathe',
      'php artisan lathe:widget TotalUsers --type=value',
    ]);
    const strings = visibleStrings();

    expect(strings).toContain('[Skip to content]');
    expect(strings).toContain('[No widgets yet]');
    expect(strings).toContain('[Log out]');
    expect(strings.filter((text) => !data.has(text) && !/^\[.+\]$/.test(text))).toEqual([]);
  });

  it('sends the stale asset screen through the translator', () => {
    __panel.setTranslator(bracketed);
    setEntryHash('abc123');
    __panel.sharedProps.set({ assetHash: 'def456' });
    render(<StaleAssetScreen />);

    expect(visibleStrings().filter((text) => !/^\[.+\]$/.test(text))).toEqual([]);
  });
});
