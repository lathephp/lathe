'use no memo';

import type { ComponentProps, KeyboardEvent } from 'react';

// The panel's first-paint copy of the kit ToggleGroup, as a segmented row (outline, small,
// no gap): the kit's slots, data attributes and classes, so the style's cn-toggle-group rules
// draw it without loading Base UI. The arrows move the focus along the row, as the kit's do
// (the other way under right to left); Enter or Space presses the focused choice.
// The kit item's own classes for an outline row with no gap, written out: each choice but the first
// drops its start border, so the row reads as one segmented control.
const item =
  'cn-toggle-group-item cn-toggle cn-toggle-variant-outline cn-toggle-size-sm inline-flex shrink-0 items-center justify-center whitespace-nowrap outline-none hover:bg-muted focus-visible:z-10 border-s-0 first:border-s';

function moveFocus(event: KeyboardEvent<HTMLButtonElement>) {
  const items = Array.from(event.currentTarget.parentElement?.children ?? []) as HTMLElement[];
  const from = items.indexOf(event.currentTarget);
  const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key];

  if (step !== undefined) {
    event.preventDefault();
    items[(from + (document.dir === 'rtl' ? -step : step) + items.length) % items.length]?.focus();
  }
}

export function ToggleGroup({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      role="group"
      data-slot="toggle-group"
      data-variant="outline"
      data-size="sm"
      data-spacing="0"
      data-orientation="horizontal"
      className={`cn-toggle-group group/toggle-group w-fit items-center ${className ?? ''}`}
      {...props}
    />
  );
}

export function ToggleGroupItem({ pressed, className, ...props }: ComponentProps<'button'> & { pressed: boolean }) {
  return (
    <button
      type="button"
      aria-pressed={pressed}
      data-pressed={pressed ? '' : undefined}
      tabIndex={pressed ? 0 : -1}
      data-slot="toggle-group-item"
      data-variant="outline"
      data-size="sm"
      data-spacing="0"
      onKeyDown={moveFocus}
      className={`${item} ${className ?? ''}`}
      {...props}
    />
  );
}
