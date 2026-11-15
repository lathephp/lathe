import { act, fireEvent, screen, within } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { showKeys } from '../lib/keys';
import { renderPage } from './support';

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] };
const groups = ['Move', 'Edit', 'Select', 'Undo', 'Open'];

describe('keyboard shortcuts sheet', () => {
  it('opens from the user menu and lists the key groups', async () => {
    renderPage('Dashboard', dashboard);
    fireEvent.click(within(screen.getByRole('complementary')).getByRole('button', { name: /Ada Lovelace/ }));

    fireEvent.click(within(screen.getByRole('menu', { name: 'User menu' })).getByRole('menuitem', { name: 'Keyboard shortcuts' }));

    const dialog = await screen.findByRole('dialog', { name: 'Keyboard shortcuts' });
    expect(screen.queryByRole('menu', { name: 'User menu' })).toBeNull();
    expect(within(dialog).getAllByRole('heading').map((heading) => heading.textContent)).toEqual(['Keyboard shortcuts', ...groups]);
  });

  it('names Space, which opens the preview panel', async () => {
    renderPage('Dashboard', dashboard);

    act(() => {
      showKeys();
    });

    expect((await screen.findByRole('dialog', { name: 'Keyboard shortcuts' })).textContent).toContain('Preview the row beside the list');
  });

  it("draws each key with the style's kbd look and each group heading as a dialog title", async () => {
    renderPage('Dashboard', dashboard);

    act(() => {
      showKeys();
    });

    const dialog = await screen.findByRole('dialog', { name: 'Keyboard shortcuts' });
    const keys = [...dialog.querySelectorAll('kbd')];
    expect(keys.length).toBeGreaterThan(10);
    expect(keys.filter((key) => !key.classList.contains('cn-kbd'))).toEqual([]);
    expect(within(dialog).getByText('Space').classList.contains('cn-kbd')).toBe(true);
    const headings = within(dialog).getAllByRole('heading', { level: 3 });
    expect(headings.map((heading) => heading.textContent)).toEqual(groups);
    expect(headings.filter((heading) => !heading.classList.contains('cn-dialog-title'))).toEqual([]);
  });

  it('opens on showKeys and closes on Escape', async () => {
    renderPage('Dashboard', dashboard);

    act(() => {
      showKeys();
    });

    await screen.findByRole('dialog', { name: 'Keyboard shortcuts' });
    fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Keyboard shortcuts' })).toBeNull();
  });
});
