import { Link, router, usePage } from '@inertiajs/react';
import { request, toast, useLathe, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { EllipsisVertical } from 'lucide-react';
import { useId, useState } from 'react';
import { recordSelection } from '../actions/RecordActionMenu';
import type { ActionList, ActionMeta } from '../actions/types';
import { useActionRunner } from '../actions/useActionRunner';
import type { SharedProps } from '../types';
import type { Row } from './types';

type MenuAction = 'replicate' | 'delete' | 'restore' | 'forceDelete';
type WriteAction = Exclude<MenuAction, 'replicate'>;

/** What the row menu offers: Replicate and Delete on a live row, Restore and Force Delete on a trashed one. */
export function menuActions(row: Row): MenuAction[] {
  const actions: MenuAction[] = row.trashed === true ? ['restore', 'forceDelete'] : ['replicate', 'delete'];

  return actions.filter((action) => row.abilities[action] === true);
}

/** Whether a row has a menu: a delete family entry, or an action it may run inline. */
export function hasRowMenu(row: Row): boolean {
  return menuActions(row).length > 0 || (row.actions?.length ?? 0) > 0;
}

const itemClass =
  'flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring';

export function RowMenu({ uriKey, row }: { uriKey: string; row: Row }) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [open, setOpen] = useState(false);
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  // The actions shown on rows, loaded the first time a menu opens.
  const [inline, setInline] = useState<ActionMeta[] | null>(null);
  const { apiPath } = useLathe();
  const runner = useActionRunner({
    base: `${apiPath}/resources/${uriKey}`,
    onDone: () => {
      router.reload({ only: ['table', 'count'] });
    },
  });
  const menuId = useId();
  const titleId = useId();
  const url = `${basePath}/api/resources/${uriKey}/${String(row.id)}`;

  const labels: Record<MenuAction, string> = {
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

          if (inline === null && (row.actions?.length ?? 0) > 0) {
            request<ActionList>(`${apiPath}/resources/${uriKey}/actions?display=table-row`)
              .then((list) => {
                setInline(list?.actions ?? []);
              })
              .catch(() => {
                setInline([]);
              });
          }
        }}
      >
        <EllipsisVertical aria-hidden="true" className="size-4" />
      </button>
      {open && (
        <div
          id={menuId}
          role="menu"
          tabIndex={-1}
          className="absolute end-0 top-full z-10 mt-1 w-40 rounded-lg border border-border bg-background p-1 shadow-lg"
          onKeyDown={(event) => {
            if (event.key === 'Escape') {
              setOpen(false);
            }
          }}
        >
          {(inline ?? [])
            .filter((action) => row.actions?.includes(action.uriKey) === true)
            .map((action) => (
              <button
                key={action.uriKey}
                type="button"
                role="menuitem"
                className={action.destructive ? `${itemClass} text-destructive` : itemClass}
                onClick={() => {
                  setOpen(false);
                  runner.start(action, recordSelection(row.id), 1);
                }}
              >
                {action.name}
              </button>
            ))}
          {menuActions(row).map((action) =>
            action === 'replicate' ? (
              <Link key={action} role="menuitem" href={`${basePath}/resources/${uriKey}/${String(row.id)}/replicate`} className={itemClass}>
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
      {runner.element}
    </div>
  );
}
