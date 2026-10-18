import { cn } from '../lib/cn';
import type { WidgetDescriptor } from './types';

/** 12 columns with a 20 px gap; a row's cards stretch to its tallest. */
export const gridClass = 'grid grid-cols-12 gap-5';

// Literal class names, so Tailwind finds them. Below 768 px value cards sit two across and the rest span all 12.
const spans: Record<number, string> = {
  3: 'md:col-span-3',
  4: 'md:col-span-4',
  6: 'md:col-span-6',
  8: 'md:col-span-8',
  9: 'md:col-span-9',
  12: 'md:col-span-12',
};

const heights: Record<WidgetDescriptor['height'], string> = { fixed: 'min-h-40', double: 'min-h-80', dynamic: '' };

/** The grid cell of a card: its columns and its least height (160 px, 320 px or its content's). */
export function cellClass(widget: WidgetDescriptor): string {
  return cn(widget.component === 'value-widget' ? 'col-span-6' : 'col-span-12', spans[widget.columns] ?? 'md:col-span-4', heights[widget.height]);
}
