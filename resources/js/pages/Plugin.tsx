import { Head, usePage } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { createElement, Suspense } from 'react';
import type { SharedProps } from '../types';

interface PluginProps {
  plugin: string;
  title: string;
  path: string;
}

/**
 * Hosts the component a plugin registered with plugin(uriKey, page). A lazy page is a
 * React.lazy component, so Suspense covers its load.
 */
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
        <Suspense fallback={null}>{createElement(page, { pluginKey: plugin, basePath, path })}</Suspense>
      )}
    </>
  );
}
