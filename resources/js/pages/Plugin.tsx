import { Head, usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { createElement, lazy, Suspense } from 'react';
import type { SharedProps } from '../types';

// Only the settings path draws the form, so other plugin pages never load the field code.
const PluginSettingsForm = lazy(() => import('../plugins/PluginSettingsForm'));

interface PluginProps {
  plugin: string;
  title: string;
  path: string;
}

/**
 * Hosts the component a plugin registered with plugin(uriKey, page), or the settings form on
 * the `settings` path. A lazy page is a React.lazy component, so Suspense covers its load.
 */
export default function Plugin({ plugin, title, path }: PluginProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const page = __panel.pluginPage(plugin);

  if (path === 'settings') {
    return (
      <>
        <Head title={title} />
        <Suspense fallback={<div aria-hidden="true" className="h-64" />}>
          <PluginSettingsForm pluginKey={plugin} title={title} />
        </Suspense>
      </>
    );
  }

  return (
    <>
      <Head title={title} />
      {page === undefined ? (
        <p className="text-sm text-muted-foreground">{__('This page is not available.')}</p>
      ) : (
        <Suspense fallback={null}>{createElement(page, { pluginKey: plugin, basePath, path })}</Suspense>
      )}
    </>
  );
}
