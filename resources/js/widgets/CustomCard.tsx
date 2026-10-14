import { __panel, useTranslation } from '@lathephp/core';
import { createElement } from 'react';
import type { WidgetRendererProps } from './types';

/** The client component a CustomWidget names with withComponent(), given the descriptor and the value. */
export function CustomCard({ widget, value }: WidgetRendererProps) {
  const { __ } = useTranslation();
  const component = typeof widget.clientComponent === 'string' ? __panel.widgetComponent(widget.clientComponent) : undefined;

  if (component === undefined) {
    return <p className="text-sm text-muted-foreground">{__('The widget could not be loaded.')}</p>;
  }

  return createElement(component, { widget, value });
}
