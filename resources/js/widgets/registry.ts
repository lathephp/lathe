import type { ComponentType } from 'react';
import { ProgressCard } from './ProgressCard';
import type { WidgetRendererProps } from './types';
import { ValueCard } from './ValueCard';

/** The renderer of each card, keyed by the component the server names. A card with no entry shows its error state. */
export const widgetRenderers: Record<string, ComponentType<WidgetRendererProps> | undefined> = {
  'value-widget': ValueCard,
  'progress-widget': ProgressCard,
};
