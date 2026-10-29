import { Link, usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { Copy, Eye, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { lazy, type RefObject, Suspense, useEffect, useEffectEvent, useId, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Modal, modalFooter } from '../components/Modal';
import { Button } from '../components/ui/Button';
import { reloadIndexRows } from '../lib/instant';
import type { SharedProps } from '../types';
import { type MenuAction, menuActions, type WriteAction } from './RowMenu';
import type { Row } from './types';
import { clearUndo } from './undo';

type LinkAction = Extract<MenuAction, 'view' | 'edit' | 'replicate'>;

// The row's actions load on the first open of its menu, so the index's first load leaves them out.
const InlineRowActions = lazy(() => import('../actions/InlineRowActions').then((module) => ({ default: module.InlineRowActions })));

const links: Record<LinkAction, string> = { view: '', edit: '/edit', replicate: '/replicate' };

function isLink(action: MenuAction): action is LinkAction {
  return action === 'view' || action === 'edit' || action === 'replicate';
}

// The helper loads on the click, so the index's first load leaves it out. It sits outside the
// component because the React Compiler skips a component that holds an import().
const startImpersonating = (basePath: string, resource: string, resourceId: string | number) =>
  import('../layout/impersonate').then((module) => module.impersonate(basePath, { resource, resourceId }));

const itemClass =
  'flex h-8 w-full items-center gap-2 rounded-md px-2 text-start text-sm outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring [&>svg]:size-4 [&>svg]:text-muted-foreground';

// Each record entry leads with its icon; Delete's takes the destructive colour with its text.
const icons = { view: Eye, edit: Pencil, replicate: Copy, delete: Trash2, restore: RotateCcw, forceDelete: Trash2 };

interface RowMenuPanelProps {
  uriKey: string;
  row: Row;
  open: boolean;
  menuId: string;
  trigger: RefObject<HTMLButtonElement | null>;
  onClose: () => void;
}

/** The row menu's list, its delete confirmation and the row's inline actions, loaded on the menu's first open. */
export function RowMenuPanel({ uriKey, row, open, menuId, trigger, onClose }: RowMenuPanelProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  const [menu, setMenu] = useState<HTMLElement | null>(null);
  const inline = (row.actions?.length ?? 0) > 0;
  const titleId = useId();
  const url = `${basePath}/api/resources/${uriKey}/${String(row.id)}`;
  const panel = useRef<HTMLDivElement>(null);

  // The table card clips what overflows it, so an opening menu is fixed to the window under its
  // button, or above it when the window ends first. It then takes the focus to its first entry,
  // so Escape and Tab work inside it.
  useLayoutEffect(() => {
    const menu = panel.current;
    const button = trigger.current?.getBoundingClientRect();

    if (!open || menu === null || button === undefined) {
      return;
    }

    const { width, height } = menu.getBoundingClientRect();
    // Pinned by its bottom edge when above, so the row's inline actions, which load later, grow it upward.
    const above = button.bottom + 4 + height > window.innerHeight;

    Object.assign(menu.style, {
      position: 'fixed',
      [above ? 'bottom' : 'top']: `${String(above ? window.innerHeight - button.top + 4 : button.bottom + 4)}px`,
      left: `${String(document.dir === 'rtl' ? button.left : button.right - width)}px`,
    });
    menu.querySelector<HTMLElement>('[role=menuitem]')?.focus();
  }, [open, trigger]);

  // Fixed to the window, the open menu would float away from its row on a scroll, so a scroll
  // closes it, as a press outside it and its button does.
  const dismiss = useEffectEvent((event: Event) => {
    const target = event.target instanceof Node ? event.target : null;

    if (event.type === 'scroll' || (!panel.current?.contains(target) && !trigger.current?.contains(target))) {
      onClose();
    }
  });

  useEffect(() => {
    if (!open) {
      return;
    }

    const listener = (event: Event) => {
      dismiss(event);
    };
    document.addEventListener('pointerdown', listener, true);
    document.addEventListener('scroll', listener, true);

    return () => {
      document.removeEventListener('pointerdown', listener, true);
      document.removeEventListener('scroll', listener, true);
    };
  }, [open]);

  const labels: Record<MenuAction, string> = {
    view: __('View'),
    edit: __('Edit'),
    replicate: __('Replicate'),
    delete: __('Delete'),
    restore: __('Restore'),
    forceDelete: __('Force Delete'),
  };

  async function run(action: WriteAction) {
    setConfirming(null);

    const title = { title: row.title };
    const requests: Record<WriteAction, { path: string; method: string; done: string; failed: string }> = {
      delete: { path: url, method: 'DELETE', done: __(':title was deleted.', title), failed: __(':title could not be deleted.', title) },
      restore: {
        path: `${url}/restore`,
        method: 'PUT',
        done: __(':title was restored.', title),
        failed: __(':title could not be restored.', title),
      },
      forceDelete: {
        path: `${url}/force`,
        method: 'DELETE',
        done: __(':title was deleted for good.', title),
        failed: __(':title could not be deleted.', title),
      },
    };
    const { path, method, done, failed } = requests[action];

    // A deleted or restored record takes no undo of an older edit.
    clearUndo();

    try {
      await request(path, { method, toastOnError: false });
    } catch {
      toast.error(failed);

      return;
    }

    reloadIndexRows();
    toast.success(done);
  }

  function choose(action: WriteAction) {
    onClose();

    if (action === 'restore') {
      void run(action);
    } else {
      setConfirming(action);
    }
  }

  const actions = menuActions(row);
  const destructive = actions.filter((action) => action === 'delete' || action === 'forceDelete');
  const safe = actions.filter((action) => action !== 'delete' && action !== 'forceDelete');

  function entry(action: MenuAction) {
    const Icon = icons[action];

    return isLink(action) ? (
      <Link key={action} role="menuitem" href={`${basePath}/resources/${uriKey}/${String(row.id)}${links[action]}`} className={itemClass}>
        <Icon aria-hidden="true" />
        {labels[action]}
      </Link>
    ) : (
      <button
        key={action}
        type="button"
        role="menuitem"
        className={action === 'restore' ? itemClass : `${itemClass} text-destructive [&>svg]:text-current`}
        onClick={() => {
          choose(action);
        }}
      >
        <Icon aria-hidden="true" />
        {labels[action]}
      </button>
    );
  }

  return (
    <>
      {open && (
        <div
          ref={panel}
          id={menuId}
          role="menu"
          tabIndex={-1}
          className="z-10 w-60rounded-[12px] border border-border bg-popover p-1 text-popover-foreground shadow-md"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              onClose();
            }
          }}
        >
          {safe.map((action) => entry(action))}
          {row.abilities.impersonate === true && (
            <button
              type="button"
              role="menuitem"
              className={itemClass}
              onClick={() => {
                onClose();
                startImpersonating(basePath, uriKey, row.id).catch(() => undefined);
              }}
            >
              {__('Impersonate')}
            </button>
          )}
          {/* The row's inline actions portal in here, so Delete stays the menu's last entry. */}
          <div ref={setMenu} role="none" />
          {destructive.length > 0 && (safe.length > 0 || inline || row.abilities.impersonate === true) && (
            <div role="separator" className="-mx-1 my-1 h-px bg-border" />
          )}
          {destructive.map((action) => entry(action))}
        </div>
      )}
      {/* On the body, so its scrim covers the top bar: the row menu's sticky cell is a stacking context. */}
      {confirming !== null &&
        createPortal(
        <Modal
          role="alertdialog"
          labelledBy={titleId}
          onClose={() => {
            setConfirming(null);
          }}
        >
          <h2 id={titleId} className="text-base font-semibold whitespace-normal">
            {confirming === 'delete' ? __('Delete :title?', { title: row.title }) : __('Force delete :title?', { title: row.title })}
          </h2>
          {confirming === 'forceDelete' && <p className="text-sm whitespace-normal text-muted-foreground">{__('This cannot be undone.')}</p>}
          <div className={modalFooter}>
            <Button
              variant="outline"
              onClick={() => {
                setConfirming(null);
              }}
            >
              {__('Cancel')}
            </Button>
            <Button
              variant="destructive-solid"
              onClick={() => {
                void run(confirming);
              }}
            >
              {labels[confirming]}
            </Button>
          </div>
        </Modal>,
          document.body,
        )}
      {inline && (
        <Suspense fallback={null}>
          <InlineRowActions
            uriKey={uriKey}
            row={row}
            menu={open ? menu : null}
            itemClass={itemClass}
            onChoose={() => {
              onClose();
            }}
          />
        </Suspense>
      )}
    </>
  );
}
