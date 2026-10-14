import { useLathe } from '@lathephp/core';
import type { WidgetDescriptor } from './types';
import { WidgetGrid } from './WidgetGrid';

interface ResourceWidgetsProps {
  /** The page's widgets prop: the cards this user may see here. */
  widgets: WidgetDescriptor[];
  /** resources/{r} or resources/{r}/lenses/{l}, under the API path. */
  path: string;
  /** Given on a detail page. */
  resourceId?: string;
}

/**
 * The cards of a Resource index, detail page or lens. The page lists them and loads this
 * module only when the list is not empty; each card fetches its own value.
 */
export function ResourceWidgets({ widgets, path, resourceId }: ResourceWidgetsProps) {
  const { apiPath } = useLathe();

  if (widgets.length === 0) {
    return null;
  }

  return <WidgetGrid widgets={widgets} valueUrl={`${apiPath}/${path}/widgets`} params={resourceId === undefined ? undefined : { resourceId }} />;
}
