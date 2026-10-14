import { Link, router, usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { EllipsisVertical } from 'lucide-react';
import { lazy, Suspense, useId, useState } from 'react';
import type { SharedProps } from '../types';
import type { Row } from './types';

type MenuAction = 'view' | 'edit' | 'replicate' | 'delete' | 'restore' | 'forceDelete';
type WriteAction = Exclude<MenuAction, 'view' | 'edit' | 'replicate'>;
type LinkAction = Extract<MenuAction, 'view' | 'edit' | 'replicate'>;

// The row's actions load on the first open of its menu, so the index's first load leaves them out.
const InlineRowActions = lazy(() => import('../actions/InlineRowActions').then((module) => ({ default: module.InlineRowActions })));

/** What the row menu offers: View, Edit, Replicate and Delete on a live row; View, Restore and Force Delete on a trashed one. */
export function menuActions(row: Row): MenuAction[] {
  const actions: MenuAction[] = row.trashed === true ? ['view', 'restore', 'forceDelete'] : ['view', 'edit', 'replicate', 'delete'];

  return actions.filter((action) => row.abilities[action === 'edit' ? 'update' : action] === true);
}

/** Whether a row has a menu: a delete family entry, or an action it may run inline. */
export function hasRowMenu(row: Row): boolean {
  return menuActions(row).length > 0 || (row.actions?.length ?? 0) > 0;
}

const links: Record<LinkAction, string> = { view: '', edit: '/edit', replicate: '/replicate' };

function isLink(action: MenuAction): action is LinkAction {
  return action === 'view' || action === 'edit' || action === 'replicate';
}

const itemClass =
  'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

export function RowMenu({ uriKey, row }: { uriKey: string; row: Row }) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [open, setOpen] = useState(false);
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  // Set on the first open of a row with actions; the actions then stay mounted.
  const [opened, setOpened] = useState(false);
  const [menu, setMenu] = useState<HTMLElement | null>(null);
  const inline = (row.actions?.length ?? 0) > 0;
  const menuId = useId();
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
      await request(path, { method });
    } catch {
      toast.error(failed);

      return;
    }

    router.reload({ only: ['table', 'count'] });
    toast.success(done);
  }

  function choose(action: WriteAction) {
    setOpen(false);

    if (action === 'restore') {
      void run(action);
    } else {
      setConfirming(action);
    }
  }

  return (
    <div className="relative flex justify-end">
      <button
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={__('Actions for :title', { title: row.title })}
        className="grid size-7 place-items-center rounded-md outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
        onClick={() => {
          setOpen(!open);
          setOpened(true);
        }}
      >
        <EllipsisVertical aria-hidden="true" className="size-4" />
      </button>
      {open && (
        <div
          id={menuId}
          ref={setMenu}
          role="menu"
          tabIndex={-1}
          className="absolute end-0 top-full z-10 mt-1 w-40 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
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
        </div>
      )}
      {confirming !== null && (
        <div className="fixed inset-0 z-50 grid place-items-center bg-black/40 p-4">
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
                className="inline-flex h-8 items-center rounded-md bg-destructive px-3 text-sm font-medium text-white outline-none hover:bg-destructive/90 focus-visible:ring-2 focus-visible:ring-ring"
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
      {inline && opened && (
        <Suspense fallback={null}>
          <InlineRowActions
            uriKey={uriKey}
            row={row}
            menu={open ? menu : null}
            itemClass={itemClass}
            onChoose={() => {
              setOpen(false);
            }}
          />
        </Suspense>
      )}
    </div>
  );
}
