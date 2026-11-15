import { Link, usePage } from '@inertiajs/react';
import { request, toast, useTranslation } from '@lathephp/core';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@lathephp/ui';
import { Copy, Ellipsis, Eye, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import { lazy, Suspense, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { InlineActions } from '../actions/InlineRowActions';
import { Modal, ModalDescription, ModalFooter, ModalHeader, ModalTitle } from '../components/Modal';
import { Button, buttonClass } from '../components/ui/Button';
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

// Each record entry leads with its icon; Delete's takes the destructive colour with its text.
const icons = { view: Eye, edit: Pencil, replicate: Copy, delete: Trash2, restore: RotateCcw, forceDelete: Trash2 };

interface RowMenuPanelProps {
  uriKey: string;
  row: Row;
  /** The button's accessible name. */
  label: string;
  /** Whether the first open came from the keyboard, which puts the focus on the first entry, as the kit menu's own button does. */
  fromKeyboard: boolean;
}

/**
 * The row menu, its delete confirmation and the row's inline actions, loaded on the menu's first
 * open. It takes the place of the row's first-paint button and opens at once, so the click that
 * loaded it needs no second one.
 */
export function RowMenuPanel({ uriKey, row, label, fromKeyboard }: RowMenuPanelProps) {
  const { __ } = useTranslation();
  const { basePath } = usePage<SharedProps>().props;
  const [confirming, setConfirming] = useState<'delete' | 'forceDelete' | null>(null);
  const [inline, setInline] = useState<InlineActions | null>(null);
  const hasInline = (row.actions?.length ?? 0) > 0;
  const titleId = useId();
  const firstFocus = useRef(fromKeyboard);
  const [open, setOpen] = useState(true);
  const content = useRef<HTMLDivElement>(null);
  const url = `${basePath}/api/resources/${uriKey}/${String(row.id)}`;

  // The kit menu holds the wheel while it is open, but a touch scroll still moves the page, and the
  // menu would follow its row under the sticky top bar and cover it, so a scroll outside it closes it.
  useEffect(() => {
    if (!open) {
      return undefined;
    }

    const close = (event: Event) => {
      if (!(event.target instanceof Node && content.current?.contains(event.target) === true)) {
        setOpen(false);
      }
    };
    document.addEventListener('scroll', close, true);

    return () => {
      document.removeEventListener('scroll', close, true);
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
    const content = (
      <>
        <Icon aria-hidden="true" />
        {labels[action]}
      </>
    );

    return isLink(action) ? (
      <DropdownMenuItem key={action} render={<Link href={`${basePath}/resources/${uriKey}/${String(row.id)}${links[action]}`} />}>
        {content}
      </DropdownMenuItem>
    ) : (
      <DropdownMenuItem
        key={action}
        variant={action === 'restore' ? 'default' : 'destructive'}
        onClick={() => {
          choose(action);
        }}
      >
        {content}
      </DropdownMenuItem>
    );
  }

  return (
    <>
      <DropdownMenu open={open} onOpenChange={setOpen}>
        <DropdownMenuTrigger render={<button type="button" tabIndex={-1} aria-label={label} className={buttonClass('ghost', 'icon-sm')} />}>
          <Ellipsis aria-hidden="true" className="size-4" />
        </DropdownMenuTrigger>
        {/* On the body, so the table card's clip cuts nothing; it opens above its button when the window ends first. */}
        <DropdownMenuContent
          ref={content}
          align="end"
          collisionPadding={8}
          className="w-60"
          onFocus={(event) => {
            // The menu takes the focus as it opens; opened from the keyboard, it hands it to its first entry, once.
            if (firstFocus.current && event.target === event.currentTarget) {
              firstFocus.current = false;
              event.currentTarget.querySelector<HTMLElement>('[role=menuitem]')?.focus();
            }
          }}
        >
          {safe.map((action) => entry(action))}
          {row.abilities.impersonate === true && (
            <DropdownMenuItem
              onClick={() => {
                startImpersonating(basePath, uriKey, row.id).catch(() => undefined);
              }}
            >
              {__('Impersonate')}
            </DropdownMenuItem>
          )}
          {/* The row's inline actions come before Delete, so Delete stays the menu's last entry. */}
          {inline?.actions.map((action) => (
            <DropdownMenuItem
              key={action.uriKey}
              variant={action.destructive ? 'destructive' : 'default'}
              onClick={() => {
                inline.run(action);
              }}
            >
              {action.name}
            </DropdownMenuItem>
          ))}
          {destructive.length > 0 && (safe.length > 0 || hasInline || row.abilities.impersonate === true) && <DropdownMenuSeparator />}
          {destructive.map((action) => entry(action))}
        </DropdownMenuContent>
      </DropdownMenu>
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
          <ModalHeader>
            <ModalTitle id={titleId} className="whitespace-normal">
              {confirming === 'delete' ? __('Delete :title?', { title: row.title }) : __('Force delete :title?', { title: row.title })}
            </ModalTitle>
            {confirming === 'forceDelete' && <ModalDescription className="whitespace-normal">{__('This cannot be undone.')}</ModalDescription>}
          </ModalHeader>
          <ModalFooter>
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
          </ModalFooter>
        </Modal>,
          document.body,
        )}
      {hasInline && (
        <Suspense fallback={null}>
          <InlineRowActions uriKey={uriKey} row={row} onLoad={setInline} />
        </Suspense>
      )}
    </>
  );
}
