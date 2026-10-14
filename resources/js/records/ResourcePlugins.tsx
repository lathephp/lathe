import { __panel } from '@lathephp/core';
import { createElement, Suspense } from 'react';
import type { ResourcePluginEntry } from '../fields/types';

interface ResourcePluginsProps {
  resourceName: string;
  resourceId: string | number;
  plugins: ResourcePluginEntry[];
}

/**
 * The blocks under a record's fields, one for each resource plugin the server listed. A
 * component is drawn from the registry by its key; a key with no component draws nothing.
 * The Detail page loads this file only for a record that has plugins.
 */
export function ResourcePlugins({ resourceName, resourceId, plugins }: ResourcePluginsProps) {
  return (
    <>
      {plugins.map((plugin) => {
        const component = __panel.resourcePluginComponent(plugin.component);

        return component === undefined ? null : (
          <Suspense key={plugin.component} fallback={<div aria-hidden="true" className="h-32 animate-pulse rounded-md bg-muted" />}>
            {createElement(component, { resourceName, resourceId: String(resourceId) })}
          </Suspense>
        );
      })}
    </>
  );
}
