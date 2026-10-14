import { request, useLathe } from '@lathephp/core';
import { lazy, Suspense, useEffect, useState } from 'react';
import { cn } from '../lib/cn';
import { cellClass, gridClass } from './layout';
import type { WidgetDescriptor } from './types';

// The grid and its renderers load only when the list names a card, so a page without widgets never fetches them.
const WidgetGrid = lazy(() => import('./WidgetGrid').then((module) => ({ default: module.WidgetGrid })));

interface ResourceWidgetsProps {
  /** resources/{r} or resources/{r}/lenses/{l}, under the API path. */
  path: string;
  /** Given on a detail page. */
  resourceId?: string;
}

/** The cards of a Resource index, detail page or lens, listed by the API after the page paints. */
export function ResourceWidgets({ path, resourceId }: ResourceWidgetsProps) {
  const { apiPath } = useLathe();
  const [widgets, setWidgets] = useState<WidgetDescriptor[]>([]);
  const query = resourceId === undefined ? '' : `?${new URLSearchParams({ resourceId }).toString()}`;

  useEffect(() => {
    let live = true;

    // A list that fails to load leaves the page as it is, without cards.
    request<{ widgets: WidgetDescriptor[] }>(`${apiPath}/${path}/widgets${query}`).then(
      (data) => {
        if (live) {
          setWidgets(data?.widgets ?? []);
        }
      },
      () => undefined,
    );

    return () => {
      live = false;
    };
  }, [apiPath, path, query]);

  if (widgets.length === 0) {
    return null;
  }

  return (
    <Suspense
      fallback={
        // Holds each card's place while the grid loads.
        <div aria-hidden="true" className={gridClass}>
          {widgets.map((widget) => (
            <div key={widget.uriKey} className={cn('min-h-40 rounded-lg border border-border', cellClass(widget))} />
          ))}
        </div>
      }
    >
      <WidgetGrid widgets={widgets} valueUrl={`${apiPath}/${path}/widgets`} params={resourceId === undefined ? undefined : { resourceId }} />
    </Suspense>
  );
}
