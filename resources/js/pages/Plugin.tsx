import { Head, usePage } from '@inertiajs/react';
import { __panel, useTranslation, type ComponentOrLazy, type PluginPageProps } from '@lathephp/core';
import { createElement, lazy, Suspense, type ComponentType } from 'react';
import type { SharedProps } from '../types';

interface PluginProps {
  plugin: string;
  title: string;
  path: string;
}

type Loader = () => Promise<{ default: ComponentType<PluginPageProps> }>;

// One lazy wrapper per loader, so a re-render does not remount the plugin page.
const lazyPages = new WeakMap<Loader, ComponentType<PluginPageProps>>();

/**
 * A registration is a component or a `() => import()` loader. Both are functions, so the
 * arity tells them apart: a loader takes no arguments, a component takes its props.
 */
function pageComponent(page: ComponentOrLazy<PluginPageProps>): ComponentType<PluginPageProps> {
  if (page.length !== 0) {
    return page as ComponentType<PluginPageProps>;
  }

  const loader = page as Loader;
  let component = lazyPages.get(loader);

  if (component === undefined) {
    component = lazy(loader);
    lazyPages.set(loader, component);
  }

  return component;
}

/** Hosts the component a plugin registered with plugin(uriKey, page). */
export default function Plugin({ plugin, title, path }: PluginProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const page = __panel.pluginPage(plugin);

  return (
    <>
      <Head title={title} />
      {page === undefined ? (
        <p className="text-sm text-muted-foreground">{__('This page is not available.')}</p>
      ) : (
        <Suspense fallback={null}>
          {createElement(pageComponent(page), { pluginKey: plugin, basePath, path })}
        </Suspense>
      )}
    </>
  );
}
