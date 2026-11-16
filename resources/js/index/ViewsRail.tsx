import { Link, router, usePage } from '@inertiajs/react';
import { formatNumber, useTranslation } from '@lathephp/core';
import { Bookmark, ChevronDown, Ellipsis, GripVertical } from 'lucide-react';
import { Suspense, useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react';
import { Button, buttonClass } from '../components/ui/Button';
import { DropdownMenuItem, DropdownMenuTrigger } from '../components/ui/DropdownMenu';
import { NavIcon } from '../components/NavIcon';
import { Badge, toneVariants } from '../components/ui/Badge';
import { cn } from '../lib/cn';
import { menuClasses, useMenuKeys } from '../lib/menuKeys';
import { DeleteTabDialog, RenameTabDialog, SaveTabDialog } from './lazy';
import type { BadgeType, IndexProps, SavedTab, TabCount } from './types';
import type { SharedProps } from '../types';
import type { IndexUrlState } from './useIndexState';
import { useSavedTabs } from './useSavedTabs';

interface ViewsProps extends Pick<IndexProps, 'resource'> {
  state: IndexUrlState;
  visit: (changes: IndexUrlState) => void;
}

/** The rail beside the card, the toolbar's Views menu, and the dialogs and announcements both share. */
export interface Views {
  rail: ReactNode;
  button: ReactNode;
  overlays: ReactNode;
}

type Dialog = { kind: 'save' } | { kind: 'rename'; tab: SavedTab } | { kind: 'delete'; tab: SavedTab };

/** A capped count reads "10,000+". */
function badgeText(counts: TabCount | undefined, fallback: string | number | null = null): string | null {
  if (counts?.count !== undefined) {
    return `${formatNumber(counts.count.value)}${counts.count.capped ? '+' : ''}`;
  }

  const badge = counts?.badge ?? fallback;

  return badge === null ? null : String(badge);
}

/**
 * A badge whose count is deferred holds a placeholder of the badge's height (text-xs, 1rem) until the counts
 * arrive, then the value fades in over 160 ms.
 */
function TabBadge({ text, type, deferred = false, pending = false }: { text: string | null; type: BadgeType | null; deferred?: boolean; pending?: boolean }) {
  if (text === null) {
    return deferred && pending ? (
      <span aria-hidden="true" data-badge-placeholder="" data-slot="skeleton" className="cn-skeleton h-5 w-12 rounded-full" />
    ) : null;
  }

  return (
    <Badge
      variant={toneVariants[type ?? ''] ?? 'secondary'}
      className={cn(
        'lathe-tab-count',
        // A count keeps at least its placeholder's width, so the views beside it do not move.
        deferred && 'min-w-12 animate-in fade-in duration-[160ms] motion-reduce:animate-none',
      )}
    >
      {text}
    </Badge>
  );
}

/** A saved view's ⋯ menu, with Rename and Delete. It keeps its own open state, since a hook can't run in the list's map. */
function ViewOptionsMenu({ tab, onChoose }: { tab: SavedTab; onChoose: (kind: 'rename' | 'delete') => void }) {
  const { __ } = useTranslation();
  const id = useId();
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  // Escape stops at the menu, so the Views menu around it stays open.
  useMenuKeys(root, open, () => {
    setOpen(false);
  });

  return (
    <div ref={root} className="contents">
      <DropdownMenuTrigger
        open={open}
        aria-label={__('Options for :name', { name: tab.name })}
        aria-controls={open ? id : undefined}
        className={`${buttonClass('ghost', 'icon-xs')} text-muted-foreground`}
        onClick={() => {
          setOpen(!open);
        }}
      >
        <Ellipsis aria-hidden="true" className="size-4" />
      </DropdownMenuTrigger>
      {open && (
        <div
          id={id}
          role="menu"
          aria-label={__('Options for :name', { name: tab.name })}
          tabIndex={-1}
          data-open=""
          data-side="bottom"
          className={`${menuClasses.content} absolute end-0 top-full z-10 mt-1 w-36`}
        >
          {(['rename', 'delete'] as const).map((kind) => (
            <DropdownMenuItem
              key={kind}
              data-variant={kind === 'delete' ? 'destructive' : 'default'}
              onClick={() => {
                setOpen(false);
                onChoose(kind);
              }}
            >
              {kind === 'rename' ? __('Rename') : __('Delete')}
            </DropdownMenuItem>
          ))}
        </div>
      )}
    </div>
  );
}

// The current view, marked by aria-current, takes the accent ground in app.css.
// The rail's rows are ghost buttons drawn by the lathe-tab hook, their text at the start.
const tabClass = 'lathe-tab justify-start';

/**
 * The developer tabs, then the user's saved views, then the lenses, in a 220 px rail at the card's left. A
 * narrow window, an open preview panel beside the card, or a table too wide to fit beside the rail folds
 * it into the toolbar's Views menu, which shows the same list. One owner keeps the saved views, the menus and the dialogs, so both lists
 * agree and a dialog opened from the menu outlives it. Null when the index has no views to list.
 */
export function useViews({ resource, state, visit }: ViewsProps): Views | null {
  const { __ } = useTranslation();
  const { tabs, tabCounts, lens, lenses = [], basePath } = usePage<Pick<IndexProps, 'tabs' | 'tabCounts' | 'lens' | 'lenses'> & SharedProps>().props;
  const id = useId();
  const saved = useSavedTabs(resource.uriKey, tabs?.saved ?? [], tabs?.activeSaved ?? null, visit);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [dragged, setDragged] = useState<number | null>(null);
  const [announcement, setAnnouncement] = useState('');
  const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);

  // A press outside the Views menu closes it.
  useEffect(() => {
    if (!open) {
      return;
    }

    const onPress = (event: PointerEvent) => {
      if (event.target instanceof Node && box.current?.contains(event.target) !== true) {
        setOpen(false);
      }
    };
    document.addEventListener('pointerdown', onPress);

    return () => {
      document.removeEventListener('pointerdown', onPress);
    };
  }, [open]);

  const items = tabs?.items ?? [];

  if (items.length === 0 && saved.list.length === 0 && !resource.definition.savedTabs && lenses.length === 0) {
    return null;
  }

  const activeSaved = tabs?.activeSaved ?? null;
  // On a lens no developer tab is current: the lens's own entry is.
  const active = activeSaved === null && lens == null ? (tabs?.active ?? items[0]?.key) : null;
  const ids = saved.list.map((tab) => tab.id);
  const current = saved.list.find((tab) => tab.id === activeSaved)?.name ?? lens?.name ?? items.find((tab) => tab.key === active)?.name;

  function move(tab: SavedTab, position: number) {
    const next = ids.filter((other) => other !== tab.id);
    next.splice(position, 0, tab.id);
    setAnnouncement(__('Moved :name to position :position', { name: tab.name, position: position + 1 }));
    void saved.reorder(next);
  }

  function onTabKeyDown(event: KeyboardEvent<HTMLButtonElement>, tab: SavedTab, index: number) {
    const step = event.key === 'ArrowUp' ? -1 : event.key === 'ArrowDown' ? 1 : 0;

    if (!event.altKey || step === 0) {
      return;
    }

    event.preventDefault();

    if (index + step >= 0 && index + step < ids.length) {
      const button = event.currentTarget;
      move(tab, index + step);
      // React moves the button's node, which drops its focus.
      requestAnimationFrame(() => {
        button.focus();
      });
    }
  }

  // Choosing a view, or a dialog, from the Views menu closes it. From a lens, a tab or a saved view
  // opens the Resource's own list.
  function choose(changes: IndexUrlState) {
    setOpen(false);

    if (lens == null) {
      visit(changes);
    } else {
      router.get(`${basePath}/resources/${resource.uriKey}`, Object.fromEntries(Object.entries(changes).filter(([, value]) => value !== undefined)));
    }
  }

  function list(where: 'rail' | 'menu') {
    return (
      <nav aria-label={__('Views')} className={cn('lathe-views', where === 'rail' && 'lathe-views-rail w-[220px] shrink-0')}>
        <ul>
          {items.map((tab, index) => (
            <li key={tab.key}>
              <Button
                variant="ghost"
                data-tab={tab.key}
                aria-current={tab.key === active ? 'page' : undefined}
                className={tabClass}
                onClick={() => {
                  // The first tab is the default view, so it leaves tab out of the URL.
                  choose({ tab: index === 0 ? undefined : tab.key, saved_tab: undefined });
                }}
              >
                {tab.icon !== null && <NavIcon name={tab.icon} />}
                {tab.name}
                <TabBadge text={badgeText(tabCounts?.[tab.key], tab.badge)} type={tab.badgeType} deferred={tab.deferredBadge} pending={tabCounts === undefined} />
              </Button>
            </li>
          ))}
          {saved.list.map((tab, index) => {
            return (
              <li
                key={tab.id}
                className="group relative flex flex-wrap items-center"
                onDragOver={(event) => {
                  if (dragged !== null) {
                    event.preventDefault();
                  }
                }}
                onDrop={(event) => {
                  event.preventDefault();
                  const moved = saved.list.find((other) => other.id === dragged);

                  if (moved !== undefined && moved.id !== tab.id) {
                    move(moved, index);
                  }

                  setDragged(null);
                }}
              >
                <span
                  draggable
                  aria-hidden="true"
                  className="cursor-grab text-muted-foreground opacity-0 group-focus-within:opacity-100 group-hover:opacity-100"
                  onDragStart={(event) => {
                    event.dataTransfer.setData('text/plain', String(tab.id));
                    event.dataTransfer.effectAllowed = 'move';
                    setDragged(tab.id);
                  }}
                  onDragEnd={() => {
                    setDragged(null);
                  }}
                >
                  <GripVertical className="size-3.5" />
                </span>
                <Button
                  variant="ghost"
                  data-tab={`saved:${String(tab.id)}`}
                  aria-current={tab.id === activeSaved ? 'page' : undefined}
                  aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
                  className={`${tabClass} flex-1`}
                  onClick={() => {
                    // A saved view opens exactly as saved, so the filters, search and sort on screen go.
                    choose({ saved_tab: String(tab.id), tab: undefined, filter: undefined, q: undefined, sort: undefined, trashed: undefined, group: undefined });
                  }}
                  onKeyDown={(event) => {
                    onTabKeyDown(event, tab, index);
                  }}
                  title={tab.name}
                >
                  {/* A long name ends in an ellipsis so the view's menu stays in the rail. */}
                  <span className="truncate">{tab.name}</span>
                  <TabBadge text={badgeText(tabCounts?.[`saved:${String(tab.id)}`])} type={tab.badgeType} deferred={tab.showCount} pending={tabCounts === undefined} />
                </Button>
                <ViewOptionsMenu
                  tab={tab}
                  onChoose={(kind) => {
                    // The popover closes as its dialog opens, so the dialog hands the focus back to Views.
                    if (where === 'menu') {
                      trigger.current?.focus();
                    }

                    setOpen(false);
                    setDialog({ kind, tab });
                  }}
                />
                {tab.stale && (
                  <span className="inline-flex basis-full items-center gap-1 ps-4 text-xs text-muted-foreground">
                    {__('View updated')}
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        // The payload's filters and sort are already cleaned of dead keys.
                        void saved.update(tab.id, { filters: tab.filters, sort: tab.sort });
                      }}
                    >
                      {__('Reset')}
                    </Button>
                  </span>
                )}
              </li>
            );
          })}
          {/* The lenses come last, each marked with the lens icon and without a count. */}
          {lenses.map((item) => (
            <li key={item.uriKey}>
              <Link
                href={`${basePath}/resources/${resource.uriKey}/lens/${item.uriKey}`}
                aria-current={item.uriKey === lens?.uriKey ? 'page' : undefined}
                className={tabClass}
                onClick={() => {
                  setOpen(false);
                }}
              >
                <NavIcon name="Scan" />
                {item.name}
              </Link>
            </li>
          ))}
        </ul>
        {resource.definition.savedTabs && (
          <Button
            variant="ghost"
            className="lathe-tab lathe-save-view justify-start"
            onClick={() => {
              if (where === 'menu') {
                trigger.current?.focus();
              }

              setOpen(false);
              setDialog({ kind: 'save' });
            }}
          >
            <Bookmark aria-hidden="true" className="size-3.5" />
            {__('Save view')}
          </Button>
        )}
      </nav>
    );
  }

  return {
    rail: list('rail'),
    button: (
      <div ref={box} className="lathe-views-menu">
        <Button
          ref={trigger}
          variant="ghost"
          size="sm"
          aria-expanded={open}
          aria-controls={open ? `${id}-views` : undefined}
          onClick={() => {
            setOpen(!open);
          }}
        >
          {__('Views')}
          {current !== undefined && (
            <>
              {' '}
              <span className="max-w-32 truncate font-semibold text-foreground">{current}</span>
            </>
          )}
          <ChevronDown aria-hidden="true" className="size-3.5" />
        </Button>
        {open && (
          // Absolute, so opening it moves nothing under the toolbar. Escape closes it unless a menu inside took it.
          // eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions
          <div
            id={`${id}-views`}
            role="dialog"
            aria-label={__('Views')}
            className="lathe-views-popover"
            onKeyDown={(event) => {
              if (event.key === 'Escape') {
                setOpen(false);
                trigger.current?.focus();
              }
            }}
          >
            {list('menu')}
          </div>
        )}
      </div>
    ),
    overlays: (
      <>
        <p aria-live="polite" className="sr-only">
          {announcement}
        </p>
        <Suspense fallback={null}>
          {dialog?.kind === 'save' && (
            <SaveTabDialog
              onSave={(values) =>
                saved.create({
                  name: values.name,
                  filters: state.filter ?? {},
                  q: state.q ?? null,
                  sort: state.sort ?? null,
                  trashed: state.trashed ?? null,
                  show_count: values.showCount,
                  badge_type: values.badgeType,
                })
              }
              onClose={() => {
                setDialog(null);
              }}
            />
          )}
          {dialog?.kind === 'rename' && (
            <RenameTabDialog
              name={dialog.tab.name}
              onSave={(name) => saved.update(dialog.tab.id, { name })}
              onClose={() => {
                setDialog(null);
              }}
            />
          )}
        </Suspense>
        {dialog?.kind === 'delete' && (
          <Suspense fallback={null}>
            <DeleteTabDialog
              name={dialog.tab.name}
              onDelete={() => {
                const tab = dialog.tab;
                setDialog(null);
                void saved.delete(tab.id);
              }}
              onClose={() => {
                setDialog(null);
              }}
            />
          </Suspense>
        )}
      </>
    ),
  };
}
