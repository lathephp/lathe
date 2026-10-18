import { Link } from '@inertiajs/react';
import { useId, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import { instantProps } from '../lib/instant';
import { preloadPage } from '../lib/pages';
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
  /** Marks a link active while its visit is on the way; null gives the state back. */
  press: (href: string | null) => void;
}

const tones: Record<string, string> = {
  info: 'bg-info text-info-foreground',
  success: 'bg-success text-success-foreground',
  warning: 'bg-warning text-warning-foreground',
  destructive: 'bg-destructive text-destructive-foreground',
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
      // The current page wears the sidebar accent and a 3 px brand bar at its start.
      'relative flex h-8 items-center gap-2 rounded-md px-2 text-sm outline-none hover:bg-sidebar-hover focus-visible:ring-2 focus-visible:ring-ring aria-[current=page]:bg-sidebar-accent aria-[current=page]:font-medium aria-[current=page]:text-sidebar-accent-foreground aria-[current=page]:before:absolute aria-[current=page]:before:inset-y-1.5 aria-[current=page]:before:start-0 aria-[current=page]:before:w-[3px] aria-[current=page]:before:rounded-full aria-[current=page]:before:bg-brand',
  };
  const content = (
    <>
      <NavIcon name={item.icon} />
      <span className={cn('truncate', context.collapsed && 'md:sr-only')}>{item.label}</span>
      {badge !== undefined && (
        <span
          data-badge-type={badge.type}
          className={cn('ms-auto rounded-full px-1.5 text-xs font-medium', tones[badge.type], context.collapsed && 'md:sr-only')}
        >
          {badge.value}
        </span>
      )}
    </>
  );

  return (
    <li className="relative" onMouseEnter={show} onMouseLeave={hide} onFocus={show} onBlur={hide}>
      {item.rel === undefined ? (
        // A hover of 75 ms fetches the page, kept 30 s or until a write to its resource flushes the tag.
        <Link
          {...link}
          prefetch
          cacheFor="30s"
          cacheTags={item.cacheTag}
          // A link that names its page shows it at once, with the label as its title.
          component={item.component}
          pageProps={item.component === undefined ? null : instantProps({ title: item.label, skeleton: item.skeleton })}
          onPointerEnter={() => {
            if (item.component !== undefined) {
              preloadPage(item.component);
            }
          }}
          onPointerDown={(event) => {
            if (event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey) {
              context.press(item.href);
            }
          }}
          // A touch scroll or a drag that starts on the link cancels the press.
          onPointerCancel={() => {
            context.press(null);
          }}
          onPointerLeave={(event) => {
            // Dragged off with the button held, the press will not click.
            if (event.buttons !== 0) {
              context.press(null);
            }
          }}
        >
          {content}
        </Link>
      ) : (
        <a {...link}>{content}</a>
      )}
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
