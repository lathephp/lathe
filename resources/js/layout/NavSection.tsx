import { Link, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useId, useState } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import { instantProps } from '../lib/instant';
import { preloadPage } from '../lib/pages';
import type { Breadcrumb } from '../fields/types';
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

type Place = Pick<NavContext, 'url' | 'basePath'>;

const pathOf = (url: string) => url.split(/[?#]/)[0] ?? url;

/** A server override wins. The panel root is active on its own path only, any other link also below it. */
function isActive(item: NavLink, { url, basePath }: Place): boolean {
  const path = pathOf(url);

  return item.active ?? (item.href === basePath ? path === item.href : path === item.href || path.startsWith(`${item.href}/`));
}

/** The menu groups around the current page's link, then the link: the top bar's trail when a page sends none. */
export function navTrail(blocks: NavBlock[], place: Place): Breadcrumb[] {
  for (const block of blocks) {
    for (const entry of block.items) {
      const tail =
        'items' in entry
          ? navTrail([entry], place)
          : isActive(entry, place)
            ? [{ label: entry.label, href: pathOf(place.url) === entry.href ? null : entry.href }]
            : [];

      if (tail.length > 0) {
        return block.label === null ? tail : [{ label: block.label, href: block.href ?? null }, ...tail];
      }
    }
  }

  return [];
}

function holdsActive(block: NavBlock, context: NavContext): boolean {
  return block.items.some((entry) => ('items' in entry ? holdsActive(entry, context) : isActive(entry, context)));
}

function NavItem({ item, context }: { item: NavLink; context: NavContext }) {
  const { onceProps } = usePage();
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
    // The current page wears the sidebar accent and a 3 px brand bar at the nav's edge (app.css).
    className: 'lathe-nav-item',
  };
  const content = (
    <>
      <NavIcon name={item.icon} />
      <span className={cn('truncate', context.collapsed && 'md:sr-only')}>{item.label}</span>
      {badge !== undefined && (
        <span data-badge-type={badge.type} className={cn('lathe-nav-badge', context.collapsed && 'md:sr-only')}>
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
          pageProps={item.component === undefined ? null : instantProps({ title: item.label, skeleton: item.skeleton }, onceProps)}
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
    <div className="lathe-nav-section">
      {block.label !== null && (
        <Heading className={cn('lathe-nav-heading', context.collapsed && 'md:sr-only')}>
          {block.href !== undefined && <Link href={block.href}>{block.label}</Link>}
          {block.collapsible === true ? (
            <button
              type="button"
              aria-label={block.href === undefined ? undefined : block.label}
              aria-expanded={open}
              onClick={() => {
                setOpen(!open);
              }}
            >
              {block.href === undefined && block.label}
              {/* A closed group says how many entries it holds. */}
              {!open && <span aria-hidden="true">{block.items.length}</span>}
              <ChevronDown aria-hidden="true" className="size-3.5" />
            </button>
          ) : (
            block.href === undefined && block.label
          )}
        </Heading>
      )}
      <ul hidden={!open}>
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
