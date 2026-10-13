import { usePage } from '@inertiajs/react';
import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { Ellipsis, GripVertical, Plus } from 'lucide-react';
import { useId, useState, type KeyboardEvent } from 'react';
import { NavIcon } from '../components/NavIcon';
import { cn } from '../lib/cn';
import { RenameTabDialog } from './RenameTabDialog';
import { SaveTabDialog } from './SaveTabDialog';
import type { BadgeType, IndexProps, SavedTab, TabCount } from './types';
import type { IndexUrlState } from './useIndexState';
import { useSavedTabs } from './useSavedTabs';

interface TabBarProps extends Pick<IndexProps, 'resource'> {
  state: IndexUrlState;
  visit: (changes: IndexUrlState) => void;
}

type Dialog = { kind: 'save' } | { kind: 'rename'; tab: SavedTab } | { kind: 'delete'; tab: SavedTab };

const badgeColours: Record<BadgeType, string> = {
  info: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-100',
  success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-100',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-100',
  danger: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-100',
};

/** A capped count reads "10,000+". */
function badgeText(counts: TabCount | undefined, fallback: string | number | null = null): string | null {
  if (counts?.count !== undefined) {
    return `${counts.count.value.toLocaleString('en')}${counts.count.capped ? '+' : ''}`;
  }

  const badge = counts?.badge ?? fallback;

  return badge === null ? null : String(badge);
}

function TabBadge({ text, type }: { text: string | null; type: BadgeType | null }) {
  if (text === null) {
    return null;
  }

  return <span className={cn('rounded-full px-1.5 text-xs font-medium tabular-nums', type === null ? 'bg-muted' : badgeColours[type])}>{text}</span>;
}

const tabClass = (current: boolean) =>
  cn(
    'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring',
    current ? 'bg-muted text-foreground' : 'text-muted-foreground',
  );

/** The developer tabs, then the user's saved tabs, in one strip above the toolbar. */
export function TabBar({ resource, state, visit }: TabBarProps) {
  const { __ } = useTranslation();
  const { tabs, tabCounts } = usePage<Pick<IndexProps, 'tabs' | 'tabCounts'>>().props;
  const id = useId();
  const saved = useSavedTabs(resource.uriKey, tabs?.saved ?? [], tabs?.activeSaved ?? null, visit);
  const [menu, setMenu] = useState<number | null>(null);
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const [dragged, setDragged] = useState<number | null>(null);
  const [announcement, setAnnouncement] = useState('');

  if (tabs === undefined || (tabs.items.length === 0 && saved.list.length === 0 && !resource.definition.savedTabs)) {
    return null;
  }

  const activeSaved = tabs.activeSaved ?? null;
  const active = activeSaved === null ? (tabs.active ?? tabs.items[0]?.key) : null;
  const ids = saved.list.map((tab) => tab.id);

  function move(tab: SavedTab, position: number) {
    const next = ids.filter((other) => other !== tab.id);
    next.splice(position, 0, tab.id);
    setAnnouncement(__('Moved :name to position :position', { name: tab.name, position: position + 1 }));
    void saved.reorder(next);
  }

  function onTabKeyDown(event: KeyboardEvent<HTMLButtonElement>, tab: SavedTab, index: number) {
    const step = event.key === 'ArrowLeft' ? -1 : event.key === 'ArrowRight' ? 1 : 0;

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

  return (
    <div>
      <nav aria-label={__('Tabs')} className="flex flex-wrap items-center gap-1 border-b border-border pb-2">
        <ul className="flex flex-wrap items-center gap-1">
          {tabs.items.map((tab, index) => (
            <li key={tab.key}>
              <button
                type="button"
                data-tab={tab.key}
                aria-current={tab.key === active ? 'page' : undefined}
                className={tabClass(tab.key === active)}
                onClick={() => {
                  // The first tab is the default view, so it leaves tab out of the URL.
                  visit({ tab: index === 0 ? undefined : tab.key, saved_tab: undefined });
                }}
              >
                {tab.icon !== null && <NavIcon name={tab.icon} />}
                {tab.name}
                <TabBadge text={badgeText(tabCounts?.[tab.key], tab.badge)} type={tab.badgeType} />
              </button>
            </li>
          ))}
          {saved.list.map((tab, index) => (
            <li
              key={tab.id}
              className="group relative flex items-center"
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
              <button
                type="button"
                data-tab={`saved:${String(tab.id)}`}
                aria-current={tab.id === activeSaved ? 'page' : undefined}
                aria-keyshortcuts="Alt+ArrowLeft Alt+ArrowRight"
                className={tabClass(tab.id === activeSaved)}
                onClick={() => {
                  visit({ saved_tab: String(tab.id), tab: undefined });
                }}
                onKeyDown={(event) => {
                  onTabKeyDown(event, tab, index);
                }}
              >
                {tab.name}
                <TabBadge text={badgeText(tabCounts?.[`saved:${String(tab.id)}`])} type={tab.badgeType} />
              </button>
              <button
                type="button"
                aria-label={__('Options for :name', { name: tab.name })}
                aria-haspopup="menu"
                aria-expanded={menu === tab.id}
                aria-controls={menu === tab.id ? `${id}-menu` : undefined}
                className="grid size-6 place-items-center rounded-md text-muted-foreground outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                onClick={() => {
                  setMenu(menu === tab.id ? null : tab.id);
                }}
              >
                <Ellipsis aria-hidden="true" className="size-4" />
              </button>
              {menu === tab.id && (
                <div
                  id={`${id}-menu`}
                  role="menu"
                  aria-label={__('Options for :name', { name: tab.name })}
                  tabIndex={-1}
                  className="absolute start-0 top-full z-10 mt-1 w-36 rounded-lg border border-border bg-background p-1 shadow-lg"
                  onKeyDown={(event) => {
                    if (event.key === 'Escape') {
                      setMenu(null);
                    }
                  }}
                >
                  {(['rename', 'delete'] as const).map((kind) => (
                    <button
                      key={kind}
                      type="button"
                      role="menuitem"
                      className="flex w-full rounded-md px-2 py-1.5 text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                      onClick={() => {
                        setMenu(null);
                        setDialog({ kind, tab });
                      }}
                    >
                      {kind === 'rename' ? __('Rename') : __('Delete')}
                    </button>
                  ))}
                </div>
              )}
              {tab.stale && (
                <span className="ms-1 inline-flex items-center gap-1 text-xs text-muted-foreground">
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
          ))}
        </ul>
        {resource.definition.savedTabs && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setDialog({ kind: 'save' });
            }}
          >
            <Plus aria-hidden="true" className="size-4" />
            {__('Save view')}
          </Button>
        )}
      </nav>
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>
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
      {dialog?.kind === 'delete' && (
        <div className="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-[15vh]">
          <div
            role="alertdialog"
            aria-modal="true"
            aria-labelledby={`${id}-delete`}
            className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-background p-4 text-foreground shadow-lg"
          >
            <h2 id={`${id}-delete`} className="text-base font-semibold">
              {__('Delete :name?', { name: dialog.tab.name })}
            </h2>
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                // eslint-disable-next-line jsx-a11y/no-autofocus -- Cancel is the safe choice of a confirm.
                autoFocus
                onClick={() => {
                  setDialog(null);
                }}
              >
                {__('Cancel')}
              </Button>
              <Button
                onClick={() => {
                  const tab = dialog.tab;
                  setDialog(null);
                  void saved.delete(tab.id);
                }}
              >
                {__('Delete')}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
