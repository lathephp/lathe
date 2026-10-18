import { useTranslation } from '@lathephp/core';
import { Bell, CircleCheck, CircleX, ExternalLink, Info, Mail, MailOpen, Trash2, TriangleAlert, type LucideIcon } from 'lucide-react';
import type { MouseEvent } from 'react';
import { cn } from '../lib/cn';
import type { NotificationAction, NotificationData } from './useNotifications';

// The server names the type icons the Heroicons way; only these ship.
const icons: Record<string, LucideIcon | undefined> = {
  'check-circle': CircleCheck,
  'x-circle': CircleX,
  'exclamation-triangle': TriangleAlert,
  'information-circle': Info,
};

const focus = 'rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';

// Using a button marks the item read, then follows it; a same-tab link waits so the request is not cancelled.
function ActionLink({ action, onUse }: { action: NotificationAction; onUse: () => Promise<void> }) {
  const target = action.openInNewTab ? { target: '_blank', rel: 'noopener noreferrer' } : {};
  const use = (event: MouseEvent) => {
    if (action.openInNewTab) {
      void onUse();
    } else {
      event.preventDefault();
      void onUse().then(() => {
        window.location.assign(action.url);
      });
    }
  };

  if (action.style === 'icon') {
    return (
      <a href={action.url} aria-label={action.text} title={action.text} className={cn(focus, 'p-1')} onClick={use} {...target}>
        <ExternalLink aria-hidden="true" className="size-4" />
      </a>
    );
  }

  return (
    <a
      href={action.url}
      className={cn(
        focus,
        action.style === 'button' ? 'rounded-md border border-border px-2 py-1 text-xs font-medium hover:bg-accent' : 'text-xs font-medium underline',
      )}
      onClick={use}
      {...target}
    >
      {action.text}
    </a>
  );
}

interface Props {
  item: NotificationData;
  onRead: () => Promise<void>;
  onUnread: () => void;
  onDelete: () => void;
}

/** One notification: icon, message, body, relative time, its actions and the read and delete controls. */
export function NotificationItem({ item, onRead, onUnread, onDelete }: Props) {
  const { __ } = useTranslation();
  const Icon = (item.icon === null ? undefined : icons[item.icon]) ?? Bell;
  const unread = item.readAt === null;
  const label = unread ? __('Mark as read') : __('Mark as unread');
  const Toggle = unread ? MailOpen : Mail;

  return (
    <li className={cn('flex gap-3 border-b border-border p-4 text-sm', unread && 'bg-accent/40')}>
      <Icon aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
      <div className="min-w-0 flex-1">
        <p className={cn(unread && 'font-medium')}>{item.message}</p>
        {item.body !== null && <p className="text-muted-foreground">{item.body}</p>}
        <p className="text-xs text-muted-foreground">{item.createdAtHuman}</p>
        {item.actions.length > 0 && (
          <div className="mt-2 flex flex-wrap items-center gap-2">
            {item.actions.slice(0, 2).map((action) => (
              <ActionLink key={`${action.text}${action.url}`} action={action} onUse={unread ? onRead : () => Promise.resolve()} />
            ))}
          </div>
        )}
      </div>
      <div className="flex shrink-0 items-start gap-1">
        <button type="button" aria-label={label} title={label} className={cn(focus, 'p-1')} onClick={unread ? () => void onRead() : onUnread}>
          <Toggle aria-hidden="true" className="size-4" />
        </button>
        <button type="button" aria-label={__('Delete')} title={__('Delete')} className={cn(focus, 'p-1')} onClick={onDelete}>
          <Trash2 aria-hidden="true" className="size-4" />
        </button>
      </div>
    </li>
  );
}
