import { useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { X } from 'lucide-react';
import { NotificationItem } from './NotificationItem';
import type { useNotifications } from './useNotifications';

interface Props {
  state: ReturnType<typeof useNotifications>;
  onClose: () => void;
}

/** The right-side panel: Mark all read and Delete all above the list, or the empty state. */
export function NotificationPanel({ state, onClose }: Props) {
  const { __ } = useTranslation();
  const { items, unread } = state;

  return (
    <div
      role="dialog"
      aria-label={__('Notifications')}
      className="fixed inset-y-0 end-0 z-40 flex w-full max-w-sm flex-col border-s border-border bg-background shadow-lg"
    >
      <div className="flex items-center gap-2 border-b border-border p-3">
        <h2 className="flex-1 font-medium">{__('Notifications')}</h2>
        <Button
          variant="ghost"
          size="sm"
          disabled={unread === 0}
          onClick={() => {
            void state.markAllRead();
          }}
        >
          {__('Mark all read')}
        </Button>
        <Button
          variant="ghost"
          size="sm"
          disabled={items.length === 0}
          onClick={() => {
            void state.removeAll();
          }}
        >
          {__('Delete all')}
        </Button>
        <Button variant="ghost" size="icon" aria-label={__('Close')} onClick={onClose}>
          <X aria-hidden="true" className="size-4" />
        </Button>
      </div>
      {items.length === 0 ? (
        <p className="p-6 text-center text-sm text-muted-foreground">{__('No notifications')}</p>
      ) : (
        <ul className="flex-1 overflow-y-auto">
          {items.map((item) => (
            <NotificationItem
              key={item.id}
              item={item}
              onRead={() => {
                void state.markRead(item.id);
              }}
              onUnread={() => {
                void state.markUnread(item.id);
              }}
              onDelete={() => {
                void state.remove(item.id);
              }}
            />
          ))}
        </ul>
      )}
    </div>
  );
}
