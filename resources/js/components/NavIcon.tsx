import { Activity, Circle, LayoutDashboard, ShoppingCart, type LucideIcon } from 'lucide-react';
import { createElement, Suspense } from 'react';
import { lazyNamed } from '../lib/lazy';

// The icons of the default navigation ship with the first load; the rest of the Icon enum
// loads with the icon set after first paint.
const icons: Record<string, LucideIcon | undefined> = { Activity, LayoutDashboard, ShoppingCart };

// After a deploy the old build's icon set is gone; a missing set draws circles rather than failing the page.
const SetIcon = lazyNamed(() =>
  import('./icon-set').then(
    (module) => module.SetIcon,
    () => CircleIcon,
  ),
);

function CircleIcon() {
  return createElement(Circle, { 'aria-hidden': true, className: 'size-4 shrink-0' });
}

/** A navigation icon by its Lucide name; until the icon set loads, an empty box of the same size holds its place. */
export function NavIcon({ name }: { name: string | null }) {
  // One element per render: the compiler's cache would only add first-load bytes.
  'use no memo';

  const eager = name === null ? Circle : icons[name];

  if (eager !== undefined) {
    return createElement(eager, { 'aria-hidden': true, className: 'size-4 shrink-0' });
  }

  return (
    <Suspense fallback={<span aria-hidden="true" className="inline-block size-4 shrink-0" />}>
      <SetIcon name={name ?? ''} />
    </Suspense>
  );
}
