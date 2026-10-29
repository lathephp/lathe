import { cn } from '../lib/cn';
import type { WidgetDescriptor } from './types';

/** 12 columns with a 20 px gap, 12 px on a phone; a row's cards stretch to its tallest. */
export const gridClass = 'grid grid-cols-12 gap-3 md:gap-5';

// Literal class names, so Tailwind finds them. Below 768 px value and progress cards of a third or less sit two across and the rest span all 12.
const spans: Record<number, string> = {
  3: 'md:col-span-3',
  4: 'md:col-span-4',
  6: 'md:col-span-6',
  8: 'md:col-span-8',
  9: 'md:col-span-9',
  12: 'md:col-span-12',
};

const heights: Record<WidgetDescriptor['height'], string> = { fixed: 'min-h-40', double: 'min-h-80', dynamic: '' };
// Two across on a phone, a value or progress card is only as high as its number.
const desktopHeights: Record<WidgetDescriptor['height'], string> = { fixed: 'md:min-h-40', double: 'md:min-h-80', dynamic: '' };

/** The grid cell of a card: its columns and its least height (160 px, 320 px or its content's). */
export function cellClass(widget: WidgetDescriptor): string {
  // A half card or wider is wide on purpose, so it keeps the whole row on a phone.
  const twoAcross = (widget.component === 'value-widget' || widget.component === 'progress-widget') && widget.columns <= 4;

  return cn(twoAcross ? 'col-span-6' : 'col-span-12', spans[widget.columns] ?? 'md:col-span-4', (twoAcross ? desktopHeights : heights)[widget.height]);
}
