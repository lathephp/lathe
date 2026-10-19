import { useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { NotificationItem } from './NotificationItem';
import type { NotificationData, useNotifications } from './useNotifications';

// The header's commands read as links, 13 px.
const textButton =
  'h-7 rounded-md px-2 text-[13px] leading-[18px] font-medium text-link outline-none hover:bg-accent hover:text-link-hover focus-visible:ring-2 focus-visible:ring-focus disabled:text-muted-foreground disabled:hover:bg-transparent';

function List({ title, rows, state }: { title: string; rows: NotificationData[]; state: Props['state'] }) {
  if (rows.length === 0) {
    return null;
  }

  return (
    <section className="border-t border-border">
      <h3 className="pt-2.5 pb-1.5 ps-6 pe-4 text-xs font-semibold tracking-[0.04em] text-muted-foreground uppercase">{title}</h3>
      <ul>
        {rows.map((item) => (
          <NotificationItem
            key={item.id}
            item={item}
            onRead={() => state.markRead(item.id)}
            onUnread={() => {
              void state.markUnread(item.id);
            }}
            onDelete={() => {
              void state.remove(item.id);
            }}
          />
        ))}
      </ul>
    </section>
  );
}

interface Props {
  state: ReturnType<typeof useNotifications>;
  onClose: () => void;
}

/** The right-side panel: Mark all as read and Delete all above the list, or the empty state. */
export function NotificationPanel({ state, onClose }: Props) {
  const { __ } = useTranslation();
  const { items, unread } = state;
  const day = new Date().toDateString();
  const today = items.filter((item) => item.createdAt !== null && new Date(item.createdAt).toDateString() === day);
  const earlier = items.filter((item) => !today.includes(item));
  const panel = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);

  useEffect(() => {
    close.current = onClose;
  }, [onClose]);

  // The panel takes the focus, Escape closes it, and the focus goes back to the bell that opened it.
  useEffect(() => {
    const opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    panel.current?.focus();

    function onKeyDown(event: KeyboardEvent) {
      // A dialog over the panel takes its own Escape.
      if (event.key === 'Escape' && !event.defaultPrevented && document.querySelector('[data-lathe-modal]') === null) {
        event.preventDefault();
        close.current();
      }
    }

    document.addEventListener('keydown', onKeyDown);

    return () => {
      document.removeEventListener('keydown', onKeyDown);
      opener?.focus();
    };
  }, []);

  // On the body, out of the sticky header's stacking layer, so no page content paints over it.
  return createPortal(
    <div
      ref={panel}
      role="dialog"
      aria-label={__('Notifications')}
      tabIndex={-1}
      // A 400 px card under the bell; on a phone it spans the screen less 8 px gutters.
      className="fixed end-6 top-[52px] z-40 flex max-h-[calc(100dvh-68px)] w-[400px] flex-col overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-md outline-none max-md:inset-x-2 max-md:w-auto"
    >
      <div className="flex h-12 shrink-0 items-center gap-1 ps-6 pe-2">
        <h2 className="flex-1 text-[15px] font-semibold">{__('Notifications')}</h2>
        <button
          type="button"
          className={textButton}
          disabled={unread === 0}
          onClick={() => {
            // The button disables once nothing is unread; the panel keeps the focus.
            panel.current?.focus();
            void state.markAllRead();
          }}
        >
          {__('Mark all as read')}
        </button>
        <button
          type="button"
          className={textButton}
          disabled={items.length === 0}
          onClick={() => {
            void state.removeAll();
          }}
        >
          {__('Delete all')}
        </button>
        <button type="button" aria-label={__('Close')} className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-focus" onClick={onClose}>
          <X aria-hidden="true" className="size-4" />
        </button>
      </div>
      {items.length === 0 ? (
        <div className="flex flex-col items-center gap-1.5 border-t border-border px-6 py-7 text-center">
          <p className="text-sm font-medium">{__('No notifications')}</p>
          <p className="text-[13px] leading-[18px] text-muted-foreground">{__('Exports, imports and long actions report here when they finish.')}</p>
        </div>
      ) : (
        <div className="flex-1 overflow-y-auto">
          <List title={__('Today')} rows={today} state={state} />
          <List title={__('Earlier')} rows={earlier} state={state} />
        </div>
      )}
    </div>,
    document.body,
  );
}
