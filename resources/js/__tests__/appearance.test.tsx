import { fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] };
const root = document.documentElement;

/** A matchMedia that answers the dark query from `state.dark` and tells its listeners when it flips. */
function stubOsScheme(dark: boolean) {
  const listeners = new Set<() => void>();
  const state = { dark };

  vi.stubGlobal('matchMedia', () => ({
    get matches() {
      return state.dark;
    },
    addEventListener: (_type: string, listener: () => void) => listeners.add(listener),
    removeEventListener: (_type: string, listener: () => void) => listeners.delete(listener),
  }));

  return {
    flip(next: boolean) {
      state.dark = next;
      listeners.forEach((listener) => {
        listener();
      });
    },
  };
}

async function openAppearance() {
  fireEvent.click(within(screen.getByRole('complementary')).getByRole('button', { name: /Ada Lovelace/ }));
  const menu = screen.getByRole('menu', { name: 'User menu' });

  return within(await within(menu).findByRole('group', { name: 'Appearance' }));
}

beforeEach(() => {
  localStorage.clear();
  delete root.dataset.latheAppearance;
  root.classList.remove('dark', 'light');
});

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

describe('appearance switcher', () => {
  it('offers Light, Dark and System with the stored choice checked', async () => {
    stubOsScheme(false);
    root.dataset.latheAppearance = 'dark';
    renderPage('Dashboard', dashboard);

    const group = await openAppearance();

    expect(group.getAllByRole('menuitemradio').map((item) => [item.textContent, item.getAttribute('aria-checked')])).toEqual([
      ['Light', 'false'],
      ['Dark', 'true'],
      ['System', 'false'],
    ]);
  });

  it('adds the dark class to html and stores the choice when Dark is picked', async () => {
    stubOsScheme(false);
    renderPage('Dashboard', dashboard);

    fireEvent.click((await openAppearance()).getByRole('menuitemradio', { name: 'Dark' }));

    expect(root.classList.contains('dark')).toBe(true);
    expect(localStorage.getItem('lathe-appearance')).toBe('dark');
  });

  it('removes the dark class when Light is picked while the OS is dark', async () => {
    stubOsScheme(true);
    root.dataset.latheAppearance = 'dark';
    root.classList.add('dark');
    renderPage('Dashboard', dashboard);

    fireEvent.click((await openAppearance()).getByRole('menuitemradio', { name: 'Light' }));

    expect(root.classList.contains('dark')).toBe(false);
    expect(localStorage.getItem('lathe-appearance')).toBe('light');
  });

  it('follows the OS in System mode', async () => {
    stubOsScheme(true);
    renderPage('Dashboard', dashboard);

    fireEvent.click((await openAppearance()).getByRole('menuitemradio', { name: 'System' }));

    expect(root.classList.contains('dark')).toBe(true);
    expect(localStorage.getItem('lathe-appearance')).toBe('system');
  });

  it('keeps the choice for the session when storage throws', async () => {
    stubOsScheme(false);
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('blocked', 'SecurityError');
    });
    renderPage('Dashboard', dashboard);

    fireEvent.click((await openAppearance()).getByRole('menuitemradio', { name: 'Dark' }));

    expect(root.classList.contains('dark')).toBe(true);
    fireEvent.keyDown(screen.getByRole('menu'), { key: 'Escape' });
    await waitFor(() => {
      expect(screen.queryByRole('menu')).toBeNull();
    });
    expect((await openAppearance()).getByRole('menuitemradio', { name: 'Dark' }).getAttribute('aria-checked')).toBe('true');
  });
});
