import { __panel, useTranslation, type ToastMessage } from '@lathephp/core';
import { createToastManager, Toast, ToastDescription, ToastProvider, ToastViewport, useToastManager } from '@lathephp/ui/toast';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '../lib/cn';

type Manager = ReturnType<typeof createToastManager<ToastMessage>>;

// The toast's buttons and links: 28 px outlined, 13 px text.
const buttonClass =
  'inline-flex h-7 shrink-0 items-center rounded-md border border-border bg-card px-2.5 text-[13px] leading-[18px] font-medium text-foreground outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-focus';

// The panel store stays the list plugins read; the manager mirrors it and runs the timers.
function mirror(manager: Manager): () => void {
  const shown = new Map<number, ToastMessage>();

  const sync = () => {
    const current = __panel.toasts.get();
    const ids = new Set(current.map((toast) => toast.id));

    shown.forEach((_, id) => {
      if (!ids.has(id)) {
        shown.delete(id);
        manager.close(String(id));
      }
    });

    current.forEach((toast) => {
      const before = shown.get(toast.id);

      if (before === toast) {
        return;
      }

      shown.set(toast.id, toast);

      // A running task's toast changes in place; Base UI restarts its timer for the new timeout.
      if (before !== undefined) {
        manager.update(String(toast.id), { description: toast.message, timeout: toast.timeout, data: toast });

        return;
      }

      manager.add({
        id: String(toast.id),
        description: toast.message,
        timeout: toast.timeout,
        data: toast,
        onClose: () => {
          shown.delete(toast.id);
          __panel.dismissToast(toast.id);
        },
      });
    });
  };

  sync();

  return __panel.toasts.subscribe(sync);
}

/** The panel's toasts drawn with Base UI Toast; it loads with the first toast. */
export function ToastStack() {
  const { __ } = useTranslation();
  const [manager] = useState(() => createToastManager<ToastMessage>());

  useEffect(() => mirror(manager), [manager]);

  return (
    <ToastProvider toastManager={manager}>
      <ToastViewport aria-label={__('Notifications')}>
        <ToastList />
        <MoreRow />
      </ToastViewport>
    </ToastProvider>
  );
}

// Base UI marks the toasts past the limit as limited and keeps them; this row counts them.
function MoreRow() {
  const { __ } = useTranslation();
  const { toasts } = useToastManager<ToastMessage>();
  const count = toasts.filter((item) => item.limited === true).length;

  if (count === 0) {
    return null;
  }

  return (
    <p className="inline-flex h-[26px] items-center self-end rounded-full border border-border bg-popover px-2.5 text-xs font-medium text-muted-foreground shadow-xs">
      {__(':count more', { count })}
    </p>
  );
}

const kinds: Record<ToastMessage['type'], { tone: string; path: string }> = {
  success: { tone: 'bg-success text-success-foreground', path: 'M20 6 9 17l-5-5' },
  error: { tone: 'bg-destructive text-destructive-foreground', path: 'M12 7v6M12 17h.01' },
  warning: { tone: 'bg-warning text-warning-foreground', path: 'M12 7v6M12 17h.01' },
  info: { tone: 'bg-info text-info-foreground', path: 'M12 17v-6M12 7h.01' },
};

/** The kind as a solid dot with its mark; a running task, which cannot be dismissed yet, spins. */
function KindIcon({ message }: { message: ToastMessage }) {
  if (message.dismissible === false) {
    return <span data-toast-icon aria-hidden="true" className="m-0.5 size-4 shrink-0 animate-spin rounded-full border-2 border-border border-t-brand" />;
  }

  const { tone, path } = kinds[message.type];

  return (
    <span data-toast-icon aria-hidden="true" className={cn('mt-px inline-flex size-5 shrink-0 items-center justify-center rounded-full', tone)}>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" className="size-3">
        <path d={path} />
      </svg>
    </span>
  );
}

function ToastList() {
  const { __ } = useTranslation();
  const { toasts, close } = useToastManager<ToastMessage>();

  return toasts.map((item) => {
    const message = item.data;

    if (message === undefined) {
      return null;
    }

    return (
      <Toast key={item.id} toast={item} role={message.type === 'error' ? 'alert' : 'status'}>
        <KindIcon message={message} />
        {/* A running task's links and action, such as View run and Cancel, leave its toast to show the result. */}
        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
          <ToastDescription />
          {message.body !== undefined && <p className="text-[13px] leading-[18px] text-muted-foreground">{message.body}</p>}
          {/* Links, such as Download failed rows, are the longer buttons, so they go under the text. */}
          {message.links !== undefined && message.links.length > 0 ? (
            <div className="mt-1.5 flex flex-wrap gap-1.5">
              {message.links.map((link) => (
                <a
                  key={link.href}
                  href={link.href}
                  {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                  className={buttonClass}
                  onClick={() => {
                    if (message.dismissible !== false) {
                      close(item.id);
                    }
                  }}
                >
                  {link.label}
                </a>
              ))}
            </div>
          ) : null}
        </div>
        {/* The action, such as Undo, Retry or Cancel, is short, so it sits at the right. */}
        {message.action !== undefined ? (
          <button
            type="button"
            className={buttonClass}
            onClick={() => {
              if (message.dismissible !== false) {
                close(item.id);
              }
              message.action?.onClick();
            }}
          >
            {message.action.label}
          </button>
        ) : null}
        {message.dismissible === false ? null : (
          /* A plain button: Base UI's Close hides itself from screen readers until the stack expands. */
          <button
            type="button"
            aria-label={__('Dismiss')}
            className="inline-flex size-6 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-focus"
            onClick={() => {
              close(item.id);
            }}
          >
            <X aria-hidden="true" className="size-3.5" />
          </button>
        )}
      </Toast>
    );
  });
}
