import { __panel, useTranslation, type ToastMessage } from '@lathephp/core';
import { createToastManager, Toast, ToastDescription, ToastProvider, ToastViewport, useToastManager } from '@lathephp/ui/toast';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '../lib/cn';

type Manager = ReturnType<typeof createToastManager<ToastMessage>>;

const buttonClass = 'rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring';

// The panel store stays the list plugins read; the manager mirrors it and runs the timers.
function mirror(manager: Manager): () => void {
  const shown = new Set<number>();

  const sync = () => {
    const current = __panel.toasts.get();
    const ids = new Set(current.map((toast) => toast.id));

    shown.forEach((id) => {
      if (!ids.has(id)) {
        shown.delete(id);
        manager.close(String(id));
      }
    });

    current.forEach((toast) => {
      if (shown.has(toast.id)) {
        return;
      }

      shown.add(toast.id);
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

  return <p className="self-end px-1 text-xs text-muted-foreground">{__(':count more', { count })}</p>;
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
      <Toast
        key={item.id}
        toast={item}
        role={message.type === 'error' ? 'alert' : 'status'}
        className={cn(message.type === 'error' && 'border-destructive text-destructive')}
      >
        <ToastDescription />
        {message.links?.map((link) => (
          <a
            key={link.href}
            href={link.href}
            {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            className={buttonClass}
            onClick={() => {
              close(item.id);
            }}
          >
            {link.label}
          </a>
        ))}
        {message.action !== undefined ? (
          <button
            type="button"
            className={buttonClass}
            onClick={() => {
              close(item.id);
              message.action?.onClick();
            }}
          >
            {message.action.label}
          </button>
        ) : null}
        {/* A plain button: Base UI's Close hides itself from screen readers until the stack expands. */}
        <button
          type="button"
          aria-label={__('Dismiss')}
          className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
          onClick={() => {
            close(item.id);
          }}
        >
          <X aria-hidden="true" className="size-4" />
        </button>
      </Toast>
    );
  });
}
