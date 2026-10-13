import { __panel, plugin, type PluginPageProps } from '@lathephp/core';
import { screen, within } from '@testing-library/react';
import { lazy } from 'react';
import { describe, expect, it } from 'vitest';
import { bracketed, renderPage } from './support';

function Status({ pluginKey, basePath, path }: PluginPageProps) {
  return (
    <p>
      {pluginKey} at {basePath} on {path ?? 'no path'}
    </p>
  );
}

function renderPlugin(key: string, path = '') {
  return renderPage('Plugin', { plugin: key, title: 'Heartbeat', path }, { url: `/lathe/plugins/${key}` });
}

describe('plugin page', () => {
  it('renders the registered component inside the shell with its props', () => {
    plugin('heartbeat', Status);
    renderPlugin('heartbeat', 'checks/12');

    const main = within(screen.getByRole('main'));

    expect(main.getByText('heartbeat at /lathe on checks/12')).toBeTruthy();
    expect(screen.getByRole('complementary')).toBeTruthy();
  });

  it('renders a component that takes no props', () => {
    function Bare() {
      return <p>bare page</p>;
    }
    plugin('bare', Bare);
    renderPlugin('bare');

    expect(within(screen.getByRole('main')).getByText('bare page')).toBeTruthy();
  });

  it('loads a lazy registration', async () => {
    plugin('beacon', lazy(() => Promise.resolve({ default: Status })));
    renderPlugin('beacon');

    expect(await screen.findByText('beacon at /lathe on')).toBeTruthy();
  });

  it('says so when no component is registered for the key', () => {
    renderPlugin('missing');

    expect(within(screen.getByRole('main')).getByText('This page is not available.')).toBeTruthy();
  });

  it('sends its message through the translator', () => {
    __panel.setTranslator(bracketed);
    renderPlugin('missing');

    expect(within(screen.getByRole('main')).getByText('[This page is not available.]')).toBeTruthy();
  });
});
