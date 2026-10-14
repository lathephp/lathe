import { lazy, type ComponentType } from 'react';
import { CustomCard } from './CustomCard';
import { HelpCard } from './HelpCard';
import { ProgressCard } from './ProgressCard';
import type { WidgetRendererProps } from './types';
import { ValueCard } from './ValueCard';

type Charts = typeof import('./charts');

// Every chart card comes from one chunk, fetched when the first of them mounts.
const chart = (name: keyof Charts) => lazy(() => import('./charts').then((charts) => ({ default: charts[name] })));

/** The renderer of each card, keyed by the component the server names. A card with no entry shows its error state. */
export const widgetRenderers: Record<string, ComponentType<WidgetRendererProps> | undefined> = {
  'value-widget': ValueCard,
  'progress-widget': ProgressCard,
  // The table card carries the row icons, so it loads on its own too.
  'table-widget': lazy(() => import('./TableCard').then((module) => ({ default: module.TableCard }))),
  'help-widget': HelpCard,
  'custom-widget': CustomCard,
  'trend-widget': chart('TrendCard'),
  'bar-widget': chart('BarCard'),
  'pie-widget': chart('PieCard'),
  'partition-widget': chart('PartitionCard'),
  'radar-widget': chart('RadarCard'),
  'radial-widget': chart('RadialCard'),
};
