import { Link, usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { lazy, Suspense, useId, useState } from 'react';
import { Button } from '../components/ui/Button';
import { reloadIndexRows } from '../lib/instant';
import type { SharedProps } from '../types';
import { type MenuAction, menuActions, type WriteAction } from './RowMenu';
import type { Row } from './types';

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
  'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

interface RowMenuPanelProps {
  uriKey: string;
  row: Row;
  open: boolean;
  menuId: string;
  onClose: () => void;
}

/** The row menu's list, its delete confirmation and the row's inline actions, loaded on the menu's first open. */
export function RowMenuPanel({ uriKey, row, open, menuId, onClose }: RowMenuPanelProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  const [menu, setMenu] = useState<HTMLElement | null>(null);
  const inline = (row.actions?.length ?? 0) > 0;
  const titleId = useId();
  const url = `${basePath}/api/resources/${uriKey}/${String(row.id)}`;

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

  return (
    <>
      {open && (
        <div
          id={menuId}
          ref={setMenu}
          role="menu"
          tabIndex={-1}
          className="absolute end-0 top-full z-10 mt-1 w-40 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              onClose();
            }
          }}
        >
          {menuActions(row).map((action) =>
            isLink(action) ? (
              <Link key={action} role="menuitem" href={`${basePath}/resources/${uriKey}/${String(row.id)}${links[action]}`} className={itemClass}>
                {labels[action]}
              </Link>
            ) : (
              <button
                key={action}
                type="button"
                role="menuitem"
                className={action === 'restore' ? itemClass : `${itemClass} text-destructive`}
                onClick={() => {
                  choose(action);
                }}
              >
                {labels[action]}
              </button>
            ),
          )}
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
        </div>
      )}
      {confirming !== null && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-scrim p-4">
          <div
            role="alertdialog"
            aria-modal="true"
            aria-labelledby={titleId}
            className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-background p-4 text-start shadow-lg"
          >
            <h2 id={titleId} className="text-base font-semibold whitespace-normal">
              {confirming === 'delete' ? __('Delete :title?', { title: row.title }) : __('Force delete :title?', { title: row.title })}
            </h2>
            {confirming === 'forceDelete' && <p className="text-sm whitespace-normal text-muted-foreground">{__('This cannot be undone.')}</p>}
            <div className="flex justify-end gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setConfirming(null);
                }}
              >
                {__('Cancel')}
              </Button>
              {/* The ui Button has no destructive variant, and its classes do not merge with an override. */}
              <button
                type="button"
                className="inline-flex h-8 items-center rounded-md bg-destructive px-3 text-sm font-medium text-destructive-foreground outline-none hover:bg-destructive/90 focus-visible:ring-2 focus-visible:ring-ring"
                onClick={() => {
                  void run(confirming);
                }}
              >
                {labels[confirming]}
              </button>
            </div>
          </div>
        </div>
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
