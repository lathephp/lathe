import { useShortcut, useTranslation } from '@lathephp/core';
import { Button } from '@lathephp/ui';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationPanel } from './NotificationPanel';
import { useNotifications } from './useNotifications';

interface Props {
  basePath: string;
  pollingInterval: number;
  showUnreadCount: boolean;
}

/** The header bell with its unread dot or count, and the panel it opens; `n` toggles the panel. */
export function NotificationCenter({ basePath, pollingInterval, showUnreadCount }: Props) {
  const { __ } = useTranslation();
  const [open, setOpen] = useState(false);
  const state = useNotifications(basePath, pollingInterval, open);

  useShortcut('n', () => {
    setOpen((was) => !was);
  });

  return (
    <>
      <Button
        variant="ghost"
        size="icon"
        className="relative"
        aria-label={__('Notifications')}
        aria-keyshortcuts="N"
        aria-expanded={open}
        onClick={() => {
          setOpen((was) => !was);
        }}
      >
        <Bell aria-hidden="true" className="size-4" />
        {state.unread > 0 &&
          (showUnreadCount ? (
            <span className="absolute -end-1 -top-1 min-w-4 rounded-full bg-red-600 px-1 text-[10px] leading-4 font-medium text-white">
              {state.unread > 99 ? '99+' : state.unread}
            </span>
          ) : (
            <span data-testid="unread-dot" className="absolute end-1.5 top-1.5 size-2 rounded-full bg-red-600" />
          ))}
      </Button>
      {open && (
        <NotificationPanel
          state={state}
          onClose={() => {
            setOpen(false);
          }}
        />
      )}
    </>
  );
}
