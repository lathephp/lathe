import { Head } from '@inertiajs/react';
import { __panel, useTranslation } from '@lathephp/core';
import { createElement, Suspense, useEffect } from 'react';

interface PageRouteProps {
  pageKey: string;
  basePath: string;
  path: string;
  title: string;
}

/**
 * Hosts the component a module registered with page(uriKey, component) at /lathe/pages/{uriKey}.
 * A key no module registered shows an in-shell notice and logs the key.
 */
export default function Page({ pageKey, basePath, path, title }: PageRouteProps) {
  const { __ } = useTranslation();
  const page = __panel.pageComponent(pageKey);

  useEffect(() => {
    if (page === undefined) {
      console.error(`[lathe] no component is registered for the page "${pageKey}"`);
    }
  }, [page, pageKey]);

  return (
    <>
      <Head title={title} />
      {page === undefined ? (
        <p className="text-sm text-muted-foreground">{__('This page is not available.')}</p>
      ) : (
        <Suspense fallback={null}>{createElement(page, { pageKey, basePath, path })}</Suspense>
      )}
    </>
  );
}
