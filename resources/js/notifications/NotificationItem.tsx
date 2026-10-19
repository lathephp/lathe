import { useTranslation } from '@lathephp/core';
import { Check, CheckCheck, Download, ExternalLink, Info, Mail, Trash2, X } from 'lucide-react';
import type { ComponentType, MouseEvent } from 'react';
import { cn } from '../lib/cn';
import type { NotificationAction, NotificationData } from './useNotifications';

type Mark = ComponentType<{ className?: string; strokeWidth?: number }>;

/** An exclamation mark, which lucide draws only inside a shape. */
function Bang({ className, strokeWidth }: { className?: string; strokeWidth?: number }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={strokeWidth} strokeLinecap="round" className={className}>
      <path d="M12 7v6M12 17h.01" />
    </svg>
  );
}

// Each icon draws as a plain mark in its kind's circle; the server sends the Icon enum's names or the Heroicons ones.
const icons: Record<string, Mark | undefined> = {
  'check-circle': Check,
  'circle-x': X,
  'x-circle': X,
  'alert-triangle': Bang,
  'exclamation-triangle': Bang,
  info: Info,
  'information-circle': Info,
  download: Download,
};

// Any other icon, such as the default bell, shows its kind's mark.
const marks: Record<NotificationData['notificationType'], Mark> = { success: Check, error: X, warning: Bang, info: Info };

const tones: Record<NotificationData['notificationType'], string> = {
  success: 'bg-success-subtle text-success-subtle-foreground',
  error: 'bg-destructive-subtle text-destructive-subtle-foreground',
  warning: 'bg-warning-subtle text-warning-subtle-foreground',
  info: 'bg-info-subtle text-info-subtle-foreground',
};

const focus = 'outline-none focus-visible:ring-2 focus-visible:ring-focus';

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
      <a href={action.url} aria-label={action.text} title={action.text} className={cn(focus, 'rounded-md p-1 text-muted-foreground hover:bg-accent')} onClick={use} {...target}>
        <ExternalLink aria-hidden="true" className="size-4" />
      </a>
    );
  }

  return (
    <a
      href={action.url}
      className={cn(
        focus,
        action.style === 'button'
          ? 'inline-flex h-7 items-center rounded-md border border-border bg-card px-2.5 text-[13px] leading-[18px] font-medium text-foreground hover:bg-accent'
          : 'rounded-sm text-[13px] font-medium text-link hover:text-link-hover',
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

/**
 * One notification: its kind's icon, message, body, actions and relative time, with an unread dot.
 * Mark as read and Delete show on hover or focus.
 */
export function NotificationItem({ item, onRead, onUnread, onDelete }: Props) {
  const { __ } = useTranslation();
  const Icon = (item.icon === null ? undefined : icons[item.icon]) ?? marks[item.notificationType];
  const unread = item.readAt === null;
  const label = unread ? __('Mark as read') : __('Mark as unread');
  const Toggle = unread ? CheckCheck : Mail;

  return (
    <li className="group relative flex gap-3 border-t border-border py-3 ps-6 pe-4 first:border-t-0 hover:bg-accent focus-within:bg-accent">
      {unread && <span data-unread-dot aria-hidden="true" className="absolute start-2.5 top-6 size-1.5 rounded-full bg-brand" />}
      <span aria-hidden="true" className={cn('inline-flex size-7 shrink-0 items-center justify-center rounded-full', tones[item.notificationType])}>
        <Icon className="size-3.5" strokeWidth={2.25} />
      </span>
      <div className="flex min-w-0 flex-1 flex-col gap-0.5">
        <p className={cn('text-sm leading-5', unread && 'font-medium')}>{item.message}</p>
        {item.body !== null && <p className="text-[13px] leading-[18px] text-muted-foreground">{item.body}</p>}
        {item.actions.length > 0 && (
          <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
            {item.actions.slice(0, 2).map((action) => (
              <ActionLink key={`${action.text}${action.url}`} action={action} onUse={unread ? onRead : () => Promise.resolve()} />
            ))}
          </div>
        )}
        <p className="text-xs text-muted-foreground" title={item.createdAt === null ? undefined : new Date(item.createdAt).toLocaleString()}>
          {item.createdAtHuman}
        </p>
      </div>
      <div className="flex shrink-0 items-start gap-0.5 opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 max-md:opacity-100">
        <button type="button" aria-label={label} title={label} className={cn(focus, 'inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground')} onClick={unread ? () => void onRead() : onUnread}>
          <Toggle aria-hidden="true" className="size-[15px]" />
        </button>
        <button type="button" aria-label={__('Delete')} title={__('Delete')} className={cn(focus, 'inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground')} onClick={onDelete}>
          <Trash2 aria-hidden="true" className="size-[15px]" />
        </button>
      </div>
    </li>
  );
}
