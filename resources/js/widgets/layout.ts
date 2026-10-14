import { cn } from '../lib/cn';
import type { WidgetDescriptor } from './types';

/** 12 columns with a 16 px gap. */
export const gridClass = 'grid grid-cols-12 gap-4';

// Literal class names, so Tailwind finds them. Below 768 px every card spans all 12.
const spans: Record<number, string> = {
  3: 'md:col-span-3',
  4: 'md:col-span-4',
  6: 'md:col-span-6',
  8: 'md:col-span-8',
  9: 'md:col-span-9',
  12: 'md:col-span-12',
};

const heights: Record<WidgetDescriptor['height'], string> = { fixed: 'h-40', double: 'h-80', dynamic: '' };

/** The grid cell of a card: its columns and its height (160 px, 320 px or its content's). */
export function cellClass(widget: WidgetDescriptor): string {
  return cn('col-span-12', spans[widget.columns] ?? 'md:col-span-4', heights[widget.height]);
}
