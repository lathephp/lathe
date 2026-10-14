import { __panel, resourcePlugin, type ResourcePluginProps } from '@lathephp/core';
import { screen } from '@testing-library/react';
import { lazy } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { renderPage } from './support';

const resource = { uriKey: 'orders', label: 'Orders', singularLabel: 'Order', abilities: { create: true } };

function detail(resourcePlugins: Array<Record<string, unknown>>) {
  return renderPage('Resources/Detail', {
    resource,
    breadcrumbs: [],
    record: {
      id: 12,
      title: 'ORD-00012',
      subtitle: '',
      avatar: null,
      fields: [],
      layout: [],
      resourcePlugins,
      abilities: { view: true, update: false, delete: false, replicate: false, restore: false, forceDelete: false },
      trashed: false,
    },
  });
}

function FakeTimeline({ resourceName, resourceId }: ResourcePluginProps) {
  return <p>timeline of {resourceName} {resourceId}</p>;
}

describe('resource plugin block', () => {
  it('renders the registered component with the resource name and record id', async () => {
    resourcePlugin('fake-timeline', FakeTimeline);
    detail([{ component: 'fake-timeline', name: 'Fake Timeline', heading: 'Activity timeline' }]);

    expect(await screen.findByText('timeline of orders 12')).toBeTruthy();
  });

  it('loads a lazy registration', async () => {
    resourcePlugin('lazy-timeline', lazy(() => Promise.resolve({ default: FakeTimeline })));
    detail([{ component: 'lazy-timeline', name: 'Lazy Timeline' }]);

    expect(await screen.findByText('timeline of orders 12')).toBeTruthy();
  });

  it('imports the scripts the server lists before drawing the block, once per URL', async () => {
    const url = '/lathe/modules/scripted-block-6ade060b.js?resource=orders';
    const loadModules = vi.spyOn(__panel, 'loadModules').mockImplementation(() => {
      resourcePlugin('scripted-block', () => <p>drawn by its script</p>);

      return Promise.resolve([]);
    });
    const { unmount } = detail([{ component: 'scripted-block', name: 'Scripted', scripts: [url] }]);

    expect(await screen.findByText('drawn by its script')).toBeTruthy();
    unmount();
    detail([{ component: 'scripted-block', name: 'Scripted', scripts: [url] }]);

    expect(await screen.findByText('drawn by its script')).toBeTruthy();
    expect(loadModules.mock.calls).toEqual([[[url]]]);
    loadModules.mockRestore();
  });

  it('draws one block for each plugin in the order the server listed them', async () => {
    resourcePlugin('first-block', () => <p>first</p>);
    resourcePlugin('second-block', () => <p>second</p>);
    detail([{ component: 'first-block', name: 'First' }, { component: 'unregistered-block', name: 'Gone' }, { component: 'second-block', name: 'Second' }]);

    await screen.findByText('second');

    expect(screen.getAllByText(/^(first|second)$/).map((node) => node.textContent)).toEqual(['first', 'second']);
  });
});
