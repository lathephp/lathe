import { page } from '@lathephp/core';
import { screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

function Gallery({ pageKey, basePath, path }: { pageKey: string; basePath: string; path?: string }) {
  return <p>{`${pageKey} at ${basePath} on ${path ?? ''}`}</p>;
}

function renderStandalone(key: string, path = '') {
  return renderPage('Page', { pageKey: key, basePath: `/lathe/pages/${key}`, path, title: 'UI Gallery' }, { url: `/lathe/pages/${key}` });
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('standalone page', () => {
  it('renders the registered component in the shell with its key, base path and sub path', () => {
    page('ui-gallery', Gallery);
    renderStandalone('ui-gallery', 'a/b');

    expect(within(screen.getByRole('main')).getByText('ui-gallery at /lathe/pages/ui-gallery on a/b')).toBeTruthy();
  });

  it('shows an in-shell notice and logs the key when no module registered the page', () => {
    const error = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    renderStandalone('missing-page');

    expect(within(screen.getByRole('main')).getByText('This page is not available.')).toBeTruthy();
    expect(error).toHaveBeenCalledWith('[lathe] no component is registered for the page "missing-page"');
  });
});
