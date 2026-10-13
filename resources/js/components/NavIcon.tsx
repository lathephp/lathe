import { Activity, Circle, LayoutDashboard, ShoppingCart, type LucideIcon } from 'lucide-react';
import { createElement } from 'react';

// Only the icons named here ship; the full Lucide set would weigh several hundred KB.
const icons: Record<string, LucideIcon | undefined> = { Activity, LayoutDashboard, ShoppingCart };

/** A navigation icon by its Lucide name, or a plain circle for a name the panel does not ship. */
export function NavIcon({ name }: { name: string | null }) {
  return createElement((name === null ? undefined : icons[name]) ?? Circle, {
    'aria-hidden': true,
    className: 'size-4 shrink-0',
  });
}
