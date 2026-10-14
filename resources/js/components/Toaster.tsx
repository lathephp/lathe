import { __panel, useTranslation } from '@lathephp/core';
import { X } from 'lucide-react';
import { useSyncExternalStore } from 'react';
import { cn } from '../lib/cn';

/** The toasts of the panel store, at the bottom end of the screen. */
export function Toaster() {
  const { __ } = useTranslation();
  const toasts = useSyncExternalStore(__panel.toasts.subscribe, __panel.toasts.get);

  if (toasts.length === 0) {
    return null;
  }

  return (
    <section aria-label={__('Notifications')} className="fixed end-4 bottom-4 z-40 flex w-80 flex-col gap-2">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role={toast.type === 'error' ? 'alert' : 'status'}
          className={cn(
            'flex items-start gap-3 rounded-lg border bg-background p-3 text-sm shadow-lg',
            toast.type === 'error' ? 'border-destructive text-destructive' : 'border-border',
          )}
        >
          <p className="flex-1">{toast.message}</p>
          {toast.links?.map((link) => (
            <a
              key={link.href}
              href={link.href}
              {...(link.newTab ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
              className="rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
              {link.label}
            </a>
          ))}
          {toast.action !== undefined ? (
            <button
              type="button"
              className="rounded-sm font-medium underline outline-none focus-visible:ring-2 focus-visible:ring-ring"
              onClick={() => {
                __panel.dismissToast(toast.id);
                toast.action?.onClick();
              }}
            >
              {toast.action.label}
            </button>
          ) : null}
          <button
            type="button"
            aria-label={__('Dismiss')}
            className="rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
            onClick={() => {
              __panel.dismissToast(toast.id);
            }}
          >
            <X aria-hidden="true" className="size-4" />
          </button>
        </div>
      ))}
    </section>
  );
}
