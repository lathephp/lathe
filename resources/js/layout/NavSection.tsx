import { Link } from '@inertiajs/react';
import { useId, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import type { NavigationItem } from '../types';

export interface NavBadge {
  value: string;
  type: string;
  tooltip?: string;
}

export interface NavLink extends NavigationItem {
  active?: boolean;
  target?: string;
  rel?: string;
  badge?: NavBadge;
}

/** A section, or a group inside one: the server sends a group with kind "group". */
export interface NavBlock {
  kind?: 'group';
  label: string | null;
  icon: string | null;
  href?: string;
  items: (NavLink | NavBlock)[];
  collapsible?: boolean;
  collapsedByDefault?: boolean;
}

export interface NavContext {
  url: string;
  basePath: string;
  collapsed: boolean;
  /** Lazy badge values by item href, sent apart from the navigation on every visit. */
  badges: Record<string, NavBadge>;
}

// The theme has no info, success or warning tokens yet, so these use the palette. The 700
// shades keep white 12px text above 4.5:1 contrast; the 600 shades fall below it.
const tones: Record<string, string> = {
  info: 'bg-sky-700',
  success: 'bg-emerald-700',
  warning: 'bg-amber-700',
  destructive: 'bg-destructive',
};

/** A server override wins. The panel root is active on its own path only, any other link also below it. */
function isActive(item: NavLink, { url, basePath }: NavContext): boolean {
  const path = url.split(/[?#]/)[0] ?? url;

  return item.active ?? (item.href === basePath ? path === item.href : path === item.href || path.startsWith(`${item.href}/`));
}

function holdsActive(block: NavBlock, context: NavContext): boolean {
  return block.items.some((entry) => ('items' in entry ? holdsActive(entry, context) : isActive(entry, context)));
}

function NavItem({ item, context }: { item: NavLink; context: NavContext }) {
  const [tip, setTip] = useState(false);
  const id = useId();
  const badge = item.badge ?? context.badges[item.href];
  // The rail hides the label, so its tooltip names the link; the badge tooltip follows.
  const text = [context.collapsed && item.label, badge?.tooltip].filter(Boolean).join(' · ');
  const active = isActive(item, context);
  const show = () => {
    setTip(true);
  };
  const hide = () => {
    setTip(false);
  };
  const link = {
    href: item.href,
    target: item.target,
    rel: item.rel,
    'aria-current': active ? ('page' as const) : undefined,
    'aria-describedby': text === '' ? undefined : id,
    'data-active': active,
    className:
      'flex h-8 items-center gap-2 rounded-md px-2 text-sm outline-none hover:bg-background focus-visible:ring-2 focus-visible:ring-ring aria-[current=page]:bg-background aria-[current=page]:font-medium',
  };
  const content = (
    <>
      <NavIcon name={item.icon} />
      <span className={cn('truncate', context.collapsed && 'md:sr-only')}>{item.label}</span>
      {badge !== undefined && (
        <span
          data-badge-type={badge.type}
          className={cn('ms-auto rounded-full px-1.5 text-xs font-medium text-white', tones[badge.type], context.collapsed && 'md:sr-only')}
        >
          {badge.value}
        </span>
      )}
    </>
  );

  return (
    <li className="relative" onMouseEnter={show} onMouseLeave={hide} onFocus={show} onBlur={hide}>
      {item.rel === undefined ? <Link {...link}>{content}</Link> : <a {...link}>{content}</a>}
      {text !== '' && (
        <span
          role="tooltip"
          id={id}
          hidden={!tip}
          className="absolute start-full top-1 z-50 ms-2 rounded-md bg-foreground px-2 py-1 text-xs whitespace-nowrap text-background"
        >
          {text}
        </span>
      )}
    </li>
  );
}

/** A titled list of links. A collapsible one has a chevron, and opens when it holds the current page. */
export function NavSection({ block, context }: { block: NavBlock; context: NavContext }) {
  const [open, setOpen] = useState(() => block.collapsedByDefault !== true || holdsActive(block, context));
  const Heading = block.kind === 'group' ? 'h3' : 'h2';

  return (
    <div className={block.kind === 'group' ? 'mt-1' : 'mb-4'}>
      {block.label !== null && (
        <Heading className={cn('flex items-center px-2 pb-1 text-xs font-medium text-muted-foreground', context.collapsed && 'md:sr-only')}>
          {block.href === undefined ? block.label : <Link href={block.href}>{block.label}</Link>}
          {block.collapsible === true && (
            <button
              type="button"
              aria-label={block.label}
              aria-expanded={open}
              onClick={() => {
                setOpen(!open);
              }}
              className="ms-auto rounded-sm px-1 transition-transform aria-expanded:rotate-90 rtl:not-aria-expanded:rotate-180"
            >
              ›
            </button>
          )}
        </Heading>
      )}
      <ul hidden={!open} className="space-y-0.5">
        {block.items.map((entry) =>
          'items' in entry ? (
            <li key={entry.label}>
              <NavSection block={entry} context={context} />
            </li>
          ) : (
            <NavItem key={entry.href} item={entry} context={context} />
          ),
        )}
      </ul>
    </div>
  );
}
