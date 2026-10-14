import { createElement } from 'react';
import { tableIcon } from './tableIcons';

/** The icon a card names. It loads on its own because it carries every icon the panel ships. */
export default function WidgetIcon({ name }: { name: string }) {
  return createElement(tableIcon(name), { 'aria-hidden': true, className: 'size-5 shrink-0 text-muted-foreground' });
}
