'use no memo';

import type { ComponentProps, KeyboardEvent } from 'react';

// The kit's Tabs, TabsList (line) and TabsTrigger classes as plain strings. The strips sit on the
// first paint, so they cannot load the kit's Base UI components; the cn-tabs rules in the style
// sheets draw them alike. Both strips are horizontal rows; a vertical field group turns into a
// column at md with its own md: classes.
export const tabsRoot = 'cn-tabs group/tabs';

export const tabsList =
  'cn-tabs-list cn-tabs-list-variant-line group/tabs-list flex items-center gap-1 bg-transparent text-muted-foreground';

export const tabsTrigger =
  'cn-tabs-trigger relative inline-flex h-[calc(100%-1px)] shrink-0 items-center justify-center whitespace-nowrap text-foreground/60 hover:text-foreground data-active:text-foreground after:absolute after:bg-foreground after:opacity-0 after:transition-opacity after:inset-x-0 after:bottom-[-5px] after:h-0.5 data-active:after:opacity-100';

/**
 * A tab's key handler, as Base UI Tabs moves: the arrows along the list (swapped under rtl)
 * wrap, Home and End jump. The key selects through the caller's select, as a click does.
 */
export function tabKeys(select: (index: number) => void, vertical: boolean) {
  const rtl = typeof document !== 'undefined' && document.dir === 'rtl';
  const forward = rtl ? 'ArrowLeft' : 'ArrowRight';
  const backward = rtl ? 'ArrowRight' : 'ArrowLeft';

  return (event: KeyboardEvent<HTMLElement>) => {
    const tabs = Array.from(event.currentTarget.closest('[role="tablist"]')?.querySelectorAll<HTMLElement>('[role="tab"]') ?? []);
    const from = tabs.indexOf(event.currentTarget);
    const next = (from + 1) % tabs.length;
    const previous = (from + tabs.length - 1) % tabs.length;
    // A vertical group is a column at md and a row below it, so it takes both pairs of arrows.
    const to = { Home: 0, End: tabs.length - 1, [forward]: next, [backward]: previous, ...(vertical ? { ArrowDown: next, ArrowUp: previous } : {}) }[event.key];

    if (from < 0 || to === undefined) {
      return;
    }

    event.preventDefault();
    select(to);
    tabs[to]?.focus();
  };
}

type TabsTriggerProps = Omit<ComponentProps<'button'>, 'type' | 'role'> & { active: boolean };

/** A tab of a strip: the kit TabsTrigger's role, slot and state on a plain button, in the Tab order only while open. */
export function TabsTrigger({ active, className, ...props }: TabsTriggerProps) {
  return (
    <button
      type="button"
      role="tab"
      tabIndex={active ? 0 : -1}
      aria-selected={active}
      data-slot="tabs-trigger"
      data-active={active || undefined}
      className={`${tabsTrigger} ${className ?? ''}`}
      {...props}
    />
  );
}
