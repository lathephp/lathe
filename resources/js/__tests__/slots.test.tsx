import { registry } from '@lathephp/core';
import { screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

afterEach(() => {
  vi.restoreAllMocks();
  vi.unstubAllEnvs();
});

const dashboard = { dashboard: { uriKey: 'main', name: 'Dashboard', description: null, icon: null, showRefreshButton: false, ranges: [], range: null }, widgets: [] };

interface Seen {
  slotVersion?: number;
  footer?: { text: string };
  username?: string;
}

const seen: Record<string, Seen> = {};

describe('registry.replace', () => {
  it('swaps the shell footer and passes slotVersion and the footer', () => {
    registry.replace('shell.footer', (props: Seen) => {
      seen.footer = props;

      return <footer>Custom footer</footer>;
    });

    renderPage('Dashboard', dashboard);

    expect(screen.getByRole('contentinfo').textContent).toBe('Custom footer');
    expect(screen.queryByText('Powered by Lathe')).toBeNull();
    expect(seen.footer).toMatchObject({ slotVersion: 1, footer: { text: 'Powered by Lathe' } });
  });

  it('swaps the shell header', () => {
    registry.replace('shell.header', () => <header>Custom header</header>);

    renderPage('Dashboard', dashboard);

    expect(screen.getByRole('banner').textContent).toBe('Custom header');
    expect(screen.queryByRole('button', { name: 'Open menu' })).toBeNull();
  });

  it('swaps the sign-in page and passes its props', () => {
    registry.replace('auth.login', (props: Seen) => {
      seen.login = props;

      return <p>Sign in with your badge</p>;
    });

    renderPage('Auth/Login', { username: 'email', errors: {} }, { shared: { auth: { user: null } }, url: '/lathe/login' });

    expect(screen.getByText('Sign in with your badge')).toBeTruthy();
    expect(screen.queryByRole('button', { name: 'Sign in' })).toBeNull();
    expect(seen.login).toMatchObject({ slotVersion: 1, username: 'email' });
  });

  it('takes the sidebar and a field or filter component as keys', () => {
    const Part = () => null;

    expect(() => {
      registry.replace('shell.sidebar', Part);
      registry.replace('field.index:badge-field', Part);
      registry.replace('filter:select-filter', Part);
    }).not.toThrow();
  });

  it('throws on an unknown key in development', () => {
    expect(() => {
      registry.replace('shell.navbar', () => null);
    }).toThrow('[lathe] registry.replace() has no part named "shell.navbar".');
  });

  it('warns on an unknown key in production', () => {
    vi.stubEnv('DEV', false);
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => undefined);

    registry.replace('badge-field', () => null);

    expect(warn).toHaveBeenCalledWith('[lathe] registry.replace() has no part named "badge-field".');
  });
});
