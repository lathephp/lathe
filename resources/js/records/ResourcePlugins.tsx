import { __panel } from '@lathephp/core';
import { createElement, Suspense, useEffect, useState } from 'react';
import type { ResourcePluginEntry } from '../fields/types';

interface ResourcePluginsProps {
  resourceName: string;
  resourceId: string | number;
  plugins: ResourcePluginEntry[];
}

// Each script URL is imported once per page, whichever record asks for it first.
const loaded = new Map<string, Promise<unknown>>();
const settled = new Set<string>();

function scriptsOf(plugins: ResourcePluginEntry[]): string[] {
  return plugins.flatMap((plugin) => (Array.isArray(plugin.scripts) ? plugin.scripts.filter((url): url is string => typeof url === 'string') : []));
}

function load(urls: string[]): Promise<unknown> {
  return Promise.all(
    urls.map((url) => {
      let promise = loaded.get(url);

      if (promise === undefined) {
        promise = __panel.loadModules([url]).finally(() => settled.add(url));
        loaded.set(url, promise);
      }

      return promise;
    }),
  );
}

/**
 * The blocks under a record's fields, one for each resource plugin the server listed. A
 * component is drawn from the registry by its key; a key with no component draws nothing.
 * The scripts the server lists are imported before the blocks are drawn, since the page
 * load never had them. The Detail page loads this file only for a record that has plugins.
 */
export function ResourcePlugins({ resourceName, resourceId, plugins }: ResourcePluginsProps) {
  const scripts = scriptsOf(plugins);
  const key = scripts.join(' ');
  const [loadedKey, setLoadedKey] = useState<string | null>(null);
  const ready = loadedKey === key || scripts.every((url) => settled.has(url));

  useEffect(() => {
    let current = true;

    void load(key === '' ? [] : key.split(' ')).then(() => {
      if (current) {
        setLoadedKey(key);
      }
    });

    return () => {
      current = false;
    };
  }, [key]);

  if (!ready) {
    return <div aria-hidden="true" className="h-32 animate-pulse rounded-md bg-muted" />;
  }

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
