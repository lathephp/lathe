import { __panel, useTranslation, type ToastMessage } from '@lathephp/core';
import { Button, buttonVariants } from '@lathephp/ui';
import { createToastManager, Toast, ToastContent, ToastDescription, ToastProvider, ToastTitle, ToastViewport, useToastManager } from '@lathephp/ui/toast';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Manager = ReturnType<typeof createToastManager<ToastMessage>>;

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
        manager.update(String(toast.id), { title: toast.message, description: toast.body, timeout: toast.timeout, data: toast });

        return;
      }

      manager.add({
        id: String(toast.id),
        title: toast.message,
        description: toast.body,
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

// Lucide's outline shapes, drawn here: importing the icons would put them on every page's first load.
const icons = {
  'circle-check': ['M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20', 'm16 9-5.5 5.5L8 12'],
  info: ['M12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20', 'M12 16v-4', 'M12 8h.01'],
  'triangle-alert': ['m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3', 'M12 9v4', 'M12 17h.01'],
  'octagon-x': [
    'm15 9-6 6',
    'M2.586 16.726A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2h6.624a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586z',
    'm9 9 6 6',
  ],
  'loader-circle': ['M21 12a9 9 0 1 1-6.219-8.56'],
};

const kinds: Record<ToastMessage['type'], keyof typeof icons> = { success: 'circle-check', error: 'octagon-x', warning: 'triangle-alert', info: 'info' };

/** The kind as a 16 px outline icon, red for an error; a running task, which cannot be dismissed yet, spins. */
function KindIcon({ message }: { message: ToastMessage }) {
  const running = message.dismissible === false;
  const icon = running ? 'loader-circle' : kinds[message.type];

  return (
    <svg
      data-toast-icon
      data-icon={icon}
      aria-hidden="true"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
      className={running ? 'size-4 shrink-0 animate-spin' : message.type === 'error' ? 'size-4 shrink-0 text-destructive' : 'size-4 shrink-0'}
    >
      {icons[icon].map((d) => (
        <path key={d} d={d} />
      ))}
    </svg>
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
        <ToastContent>
          <KindIcon message={message} />
          {/* A running task's links and action, such as View run and Cancel, leave its toast to show the result. */}
          <div className="flex min-w-0 flex-1 flex-col gap-1">
            <ToastTitle />
            <ToastDescription />
            {/* Links, such as Download failed rows, are the longer buttons, so they go under the text. */}
            {message.links !== undefined && message.links.length > 0 ? (
              <div className="mt-1 flex flex-wrap gap-1.5">
                {message.links.map((link) => (
                  <a
                    key={link.href}
                    href={link.href}
                    {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                    className={buttonVariants({ variant: 'outline', size: 'sm' })}
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
            <Button
              variant="outline"
              size="sm"
              className="shrink-0"
              onClick={() => {
                if (message.dismissible !== false) {
                  close(item.id);
                }
                message.action?.onClick();
              }}
            >
              {message.action.label}
            </Button>
          ) : null}
          {message.dismissible === false ? null : (
            /* A plain button: Base UI's Close hides itself from screen readers until the stack expands. */
            <Button
              variant="ghost"
              size="icon-sm"
              aria-label={__('Dismiss')}
              className="relative shrink-0 text-muted-foreground after:absolute after:-inset-2 hover:text-foreground"
              onClick={() => {
                close(item.id);
              }}
            >
              <X aria-hidden="true" />
            </Button>
          )}
        </ToastContent>
      </Toast>
    );
  });
}
