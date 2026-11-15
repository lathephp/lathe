import type { KeyboardEvent } from 'react';

// The kit's Tabs, TabsList (line) and TabsTrigger classes as plain strings. The strips sit on the
// first paint, so they cannot load the kit's Base UI components; the cn-tabs rules in the style
// sheets draw them alike.
export const tabsRoot = 'cn-tabs group/tabs';

export const tabsList =
  'cn-tabs-list cn-tabs-list-variant-line group/tabs-list flex items-center gap-1 bg-transparent text-muted-foreground group-data-vertical/tabs:h-fit group-data-vertical/tabs:flex-col';

export const tabsTrigger =
  'cn-tabs-trigger relative inline-flex h-[calc(100%-1px)] shrink-0 items-center justify-center whitespace-nowrap text-foreground/60 group-data-vertical/tabs:w-full group-data-vertical/tabs:justify-start hover:text-foreground data-active:text-foreground after:absolute after:bg-foreground after:opacity-0 after:transition-opacity group-data-horizontal/tabs:after:inset-x-0 group-data-horizontal/tabs:after:bottom-[-5px] group-data-horizontal/tabs:after:h-0.5 group-data-vertical/tabs:after:inset-y-0 group-data-vertical/tabs:after:-end-1 group-data-vertical/tabs:after:w-0.5 data-active:after:opacity-100';

/**
 * A tab's key handler, as Base UI Tabs moves: the arrows along the list (swapped under rtl)
 * wrap, Home and End jump. The key selects through the caller's select, as a click does.
 */
export function tabKeys(select: (index: number) => void, vertical: boolean) {
  const rtl = typeof document !== 'undefined' && document.dir === 'rtl';
  const next = vertical ? 'ArrowDown' : rtl ? 'ArrowLeft' : 'ArrowRight';
  const previous = vertical ? 'ArrowUp' : rtl ? 'ArrowRight' : 'ArrowLeft';

  return (event: KeyboardEvent<HTMLElement>) => {
    const tabs = Array.from(event.currentTarget.closest('[role="tablist"]')?.querySelectorAll<HTMLElement>('[role="tab"]') ?? []);
    const from = tabs.indexOf(event.currentTarget);
    const to = { Home: 0, End: tabs.length - 1, [next]: (from + 1) % tabs.length, [previous]: (from + tabs.length - 1) % tabs.length }[event.key];

    if (from < 0 || to === undefined) {
      return;
    }

    event.preventDefault();
    select(to);
    tabs[to]?.focus();
  };
}
